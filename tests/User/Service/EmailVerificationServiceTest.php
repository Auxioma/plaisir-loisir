<?php

declare(strict_types=1);

namespace App\Tests\User\Service;

use App\User\Entity\User;
use App\User\Enum\UserStatus;
use App\User\Repository\UserRepository;
use App\User\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class EmailVerificationServiceTest extends TestCase
{
    public function testSendCodeArmsAHashedCodeAndSendsAnEmail(): void
    {
        $user = (new User())->setEmail('bob@example.com')->setFirstName('Bob')->setLastName('Martin');

        $em = $this->createStub(EntityManagerInterface::class);
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send');

        (new EmailVerificationService($em, $this->createStub(UserRepository::class), $mailer))->sendCode($user);

        self::assertNotNull($user->getEmailVerificationCodeHash());
        self::assertNotNull($user->getEmailVerificationExpiresAt());
        self::assertSame(0, $user->getEmailVerificationAttempts());
    }

    public function testConfirmActivatesTheAccountWithTheRightCode(): void
    {
        $user = (new User())->setEmail('bob@example.com')->setFirstName('Bob')->setLastName('Martin')->setStatus(UserStatus::Pending);

        $code = $this->captureSentCode($user);

        $users = $this->createStub(UserRepository::class);
        $users->method('findOneBy')->willReturn($user);
        $service = new EmailVerificationService($this->createStub(EntityManagerInterface::class), $users, $this->createStub(MailerInterface::class));

        self::assertTrue($service->confirm($user->getEmail(), $code));
        self::assertSame(UserStatus::Active, $user->getStatus());
        self::assertNull($user->getEmailVerificationCodeHash());
    }

    public function testConfirmRejectsAWrongCodeAndCountsTheAttempt(): void
    {
        $user = (new User())->setEmail('bob@example.com')->setFirstName('Bob')->setLastName('Martin')->setStatus(UserStatus::Pending);

        $users = $this->createStub(UserRepository::class);
        $users->method('findOneBy')->willReturn($user);
        $service = new EmailVerificationService($this->createStub(EntityManagerInterface::class), $users, $this->createStub(MailerInterface::class));

        $service->sendCode($user);

        self::assertFalse($service->confirm($user->getEmail(), 'WRONGCODE'));
        self::assertSame(UserStatus::Pending, $user->getStatus());
        self::assertSame(1, $user->getEmailVerificationAttempts());
    }

    public function testConfirmDestroysTheCodeAfterTooManyFailedAttempts(): void
    {
        $user = (new User())->setEmail('bob@example.com')->setFirstName('Bob')->setLastName('Martin')->setStatus(UserStatus::Pending);

        $users = $this->createStub(UserRepository::class);
        $users->method('findOneBy')->willReturn($user);
        $service = new EmailVerificationService($this->createStub(EntityManagerInterface::class), $users, $this->createStub(MailerInterface::class));

        $service->sendCode($user);

        for ($i = 0; $i < 5; ++$i) {
            $service->confirm($user->getEmail(), 'WRONGCODE');
        }

        self::assertNull($user->getEmailVerificationCodeHash(), 'Le code doit être détruit après 5 échecs.');
    }

    public function testResendIsSilentWhenNoVerificationIsPending(): void
    {
        $user = (new User())->setEmail('bob@example.com')->setFirstName('Bob')->setLastName('Martin')->setStatus(UserStatus::Active);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $users = $this->createStub(UserRepository::class);
        $users->method('findOneBy')->willReturn($user);

        (new EmailVerificationService($this->createStub(EntityManagerInterface::class), $users, $mailer))->resend($user->getEmail());
    }

    /**
     * Le code n'est jamais stocké en clair : on le capture au moment de
     * l'envoi de l'e-mail, comme le ferait une boîte mail de test.
     */
    private function captureSentCode(User $user): string
    {
        $captured = null;

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->willReturnCallback(function (Email $email) use (&$captured): void {
            preg_match('/vérification est : ([A-Z0-9]{8})/u', (string) $email->getTextBody(), $matches);
            $captured = $matches[1] ?? null;
        });

        (new EmailVerificationService($this->createStub(EntityManagerInterface::class), $this->createStub(UserRepository::class), $mailer))
            ->sendCode($user);

        self::assertNotNull($captured, 'Le code envoyé par e-mail n\'a pas pu être capturé.');

        return $captured;
    }
}
