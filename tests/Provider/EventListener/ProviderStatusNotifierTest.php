<?php

declare(strict_types=1);

namespace App\Tests\Provider\EventListener;

use App\Notification\Repository\NotificationRepository;
use App\Provider\Entity\ProviderProfile;
use App\Provider\Enum\ProviderStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * 12/10 : le professionnel est prévenu (notification + e-mail) à chaque
 * changement de statut de son dossier, y compris depuis le back-office.
 */
final class ProviderStatusNotifierTest extends KernelTestCase
{
    public function testTheProviderIsNotifiedWhenHisAccountIsValidated(): void
    {
        self::bootKernel();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        // Base de test partagée : tout est annulé à la fin.
        $em->getConnection()->beginTransaction();
        try {
            $this->assertNotifiedOnStatusChange($em);
        } finally {
            $em->getConnection()->rollBack();
        }
    }

    private function assertNotifiedOnStatusChange(EntityManagerInterface $em): void
    {
        $profile = $em->getRepository(ProviderProfile::class)->findOneBy(['status' => ProviderStatus::PendingVerification])
            ?? $em->getRepository(ProviderProfile::class)->findOneBy([]);
        self::assertNotNull($profile);
        $user = $profile->getUser();
        $notifications = static::getContainer()->get(NotificationRepository::class);
        $before = \count($notifications->findByRecipient($user));

        $profile->setStatus(ProviderStatus::Verified === $profile->getStatus() ? ProviderStatus::Suspended : ProviderStatus::Verified);
        $em->flush();

        $after = $notifications->findByRecipient($user);
        self::assertCount($before + 1, $after);
        self::assertEmailCount(1);
        self::assertContains($after[0]->getTitle(), ['Compte professionnel validé', 'Compte professionnel non validé']);
    }
}
