<?php

declare(strict_types=1);

namespace App\Tests\User;

use App\Notification\Enum\NotificationCategory;
use App\Notification\Repository\NotificationRepository;
use App\Notification\Service\NotificationService;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Écran Notifications reconnecté au Lot K (16/09) : jusqu'ici
 * StaticAccount::notifications() fournissait le contenu, quel que soit
 * l'utilisateur connecté.
 */
final class AccountNotificationsFlowTest extends WebTestCase
{
    public function testNotificationsScreenListsRealNotificationsAndCounts(): void
    {
        $client = static::createClient();
        $user = $this->makeClient();
        $notifications = static::getContainer()->get(NotificationService::class);

        $notifications->notify($user, NotificationCategory::Booking, 'Réservation confirmée', 'Votre réservation est confirmée.');
        $read = $notifications->notify($user, NotificationCategory::Review, 'Nouvel avis', 'Vous avez reçu un avis 5/5.');
        $notifications->markAsRead($read);

        $client->loginUser($user);
        $client->request('GET', '/compte/notifications');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Réservation confirmée');
        self::assertSelectorTextContains('body', 'Nouvel avis');
        // La non-lue est encore en surbrillance à l'affichage…
        self::assertSelectorTextContains('.pf-notif.is-unread', 'Réservation confirmée');
        self::assertSelectorCount(1, '.pf-notif.is-unread');
    }

    /**
     * Retour client du 07/10 : une notification vue ne doit plus compter
     * comme non lue (le compteur de l'en-tête ne baissait jamais).
     */
    public function testDisplayedNotificationsBecomeRead(): void
    {
        $client = static::createClient();
        $user = $this->makeClient();
        $notifications = static::getContainer()->get(NotificationService::class);
        $notifications->notify($user, NotificationCategory::Booking, 'Réservation confirmée', 'Détail.');

        $client->loginUser($user);
        $client->request('GET', '/compte/notifications');
        self::assertResponseIsSuccessful();

        $repository = static::getContainer()->get(NotificationRepository::class);
        self::assertSame(0, $repository->countUnread($user));

        $client->request('GET', '/compte/notifications');
        self::assertSelectorNotExists('.pf-notif.is-unread');
    }

    public function testUnreadFilterShowsOnlyUnreadNotifications(): void
    {
        $client = static::createClient();
        $user = $this->makeClient();
        $notifications = static::getContainer()->get(NotificationService::class);

        $notifications->notify($user, NotificationCategory::Booking, 'Réservation confirmée', 'Détail.');
        $read = $notifications->notify($user, NotificationCategory::Review, 'Nouvel avis', 'Détail.');
        $notifications->markAsRead($read);

        $client->loginUser($user);
        $crawler = $client->request('GET', '/compte/notifications', ['filtre' => 'non-lues']);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Réservation confirmée', $crawler->text());
        self::assertStringNotContainsString('Nouvel avis', $crawler->text());
    }

    public function testMarkAllAsReadClearsTheUnreadBadge(): void
    {
        $client = static::createClient();
        $user = $this->makeClient();
        $notifications = static::getContainer()->get(NotificationService::class);

        $notifications->notify($user, NotificationCategory::Booking, 'Réservation confirmée', 'Détail.');
        $notifications->notify($user, NotificationCategory::Review, 'Nouvel avis', 'Détail.');

        $client->loginUser($user);
        $crawler = $client->request('GET', '/compte/notifications');
        $token = (string) $crawler->filter('form[action*="tout-marquer-lu"] input[name="_token"]')->first()->attr('value');

        $client->request('POST', '/compte/notifications/tout-marquer-lu', ['_token' => $token]);
        self::assertResponseRedirects('/compte/notifications');

        $repository = static::getContainer()->get(NotificationRepository::class);
        self::assertSame(0, $repository->countUnread($user));
    }

    /**
     * §7.2/§8.3 du CDC : préférences de notification, seul le canal e-mail
     * étant réellement câblé.
     */
    public function testPreferencesScreenTogglesEmailChannel(): void
    {
        $client = static::createClient();
        $user = $this->makeClient();
        $email = $user->getEmail();

        $client->loginUser($user);
        $crawler = $client->request('GET', '/compte/notifications/preferences');
        self::assertResponseIsSuccessful();

        $token = (string) $crawler->filter('form input[name="_token"]')->first()->attr('value');
        $client->request('POST', '/compte/notifications/preferences', ['_token' => $token]);
        self::assertResponseRedirects('/compte/notifications/preferences');

        $reloaded = static::getContainer()->get(\App\User\Repository\UserRepository::class)->findOneBy(['email' => $email]);
        self::assertNotNull($reloaded);

        $preference = static::getContainer()->get(\App\Notification\Repository\NotificationPreferenceRepository::class)->findOneByUser($reloaded);
        self::assertNotNull($preference);
        self::assertFalse($preference->isEmailEnabled(), 'La case décochée (absente du POST) doit désactiver le canal e-mail.');
    }

    private function makeClient(): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())
            ->setEmail(sprintf('espace-client-notifs-%s@example.com', uniqid()))
            ->setFirstName('Alix')
            ->setLastName('Test')
            ->setStatus(UserStatus::Active);
        $user->setPassword('peu-importe');

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
