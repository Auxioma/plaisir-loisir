<?php

declare(strict_types=1);

namespace App\Tests\User\Service;

use App\Notification\Entity\Notification;
use App\Notification\Repository\NotificationRepository;
use App\Notification\Service\NotificationService;
use App\User\Entity\User;
use App\User\Repository\UserRepository;
use App\User\Service\PasswordResetService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * §15 du CDC (« Compte ») : un changement de mot de passe fait partie des
 * événements de compte à notifier — vérifié ici, en complément du parcours
 * bout en bout de PasswordResetFlowTest.
 */
final class PasswordResetServiceTest extends TestCase
{
    public function testResetNotifiesTheUserOfThePasswordChange(): void
    {
        $user = (new User())->setEmail('bob@example.com')->setFirstName('Bob')->setLastName('Martin');

        $users = $this->createStub(UserRepository::class);
        $users->method('findOneBy')->willReturn($user);

        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hashed');

        $notified = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity) use (&$notified): void {
            if ($entity instanceof Notification) {
                $notified[] = $entity;
            }
        });

        $code = $this->captureSentCode($em, $users, $hasher, $user);

        $service = new PasswordResetService($em, $users, $hasher, $this->createStub(MailerInterface::class), new NotificationService($em, $this->createStub(NotificationRepository::class)));

        self::assertTrue($service->reset($user->getEmail(), $code, 'NouveauMotDePasse123'));
        self::assertCount(1, $notified);
        self::assertSame($user, $notified[0]->getRecipient());
    }

    private function captureSentCode(
        EntityManagerInterface $em,
        UserRepository $users,
        UserPasswordHasherInterface $hasher,
        User $user,
    ): string {
        $captured = null;

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->willReturnCallback(function (Email $email) use (&$captured): void {
            preg_match('/vérification est : ([A-Z0-9]{8})/u', (string) $email->getTextBody(), $matches);
            $captured = $matches[1] ?? null;
        });

        (new PasswordResetService($em, $users, $hasher, $mailer, new NotificationService($em, $this->createStub(NotificationRepository::class))))
            ->requestCode($user->getEmail());

        self::assertNotNull($captured, 'Le code envoyé par e-mail n\'a pas pu être capturé.');

        return $captured;
    }
}
