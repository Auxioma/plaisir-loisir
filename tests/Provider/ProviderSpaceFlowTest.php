<?php

declare(strict_types=1);

namespace App\Tests\Provider;

use App\Availability\Entity\Availability;
use App\Booking\Entity\Booking;
use App\Booking\Enum\BookingStatus;
use App\Catalog\Entity\Category;
use App\Catalog\Entity\Promotion;
use App\Catalog\Entity\Service;
use App\Catalog\Enum\ServiceStatus;
use App\Messaging\Entity\Conversation;
use App\Notification\Repository\NotificationRepository;
use App\Provider\Repository\ProviderProfileRepository;
use App\Support\Entity\SupportTicket;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Espace professionnel des maquettes profil_professionnel (02/10), de bout
 * en bout, avec le compte de démonstration annonceur@trouvemoi.test et ses
 * données (ProviderSpaceFixtures).
 */
final class ProviderSpaceFlowTest extends WebTestCase
{
    private const PAGES = [
        '/pro/tableau-de-bord', '/pro/tableau-de-bord?periode=30', '/pro/activites', '/pro/activites/nouvelle',
        '/pro/reservations', '/pro/reservations?onglet=en-attente', '/pro/reservations/rapport',
        '/pro/calendrier', '/pro/calendrier?vue=semaine', '/pro/calendrier?vue=jour',
        '/pro/messages', '/pro/avis', '/pro/revenus', '/pro/revenus?toutes=1&par=semaine',
        '/pro/offres', '/pro/offres/nouvelle', '/pro/statistiques', '/pro/statistiques?mesure=revenus',
        '/pro/parametres', '/pro/parametres?section=infos&modifier=1', '/pro/parametres?section=profil-pro',
        '/pro/parametres?section=preferences', '/pro/parametres?section=notifications', '/pro/parametres?section=paiement',
        '/pro/parametres?section=securite', '/pro/parametres?section=abonnement', '/pro/parametres?section=integrations',
        '/pro/parametres?section=suppression', '/pro/mon-profil', '/pro/mon-profil?onglet=activites',
        '/pro/mon-profil?onglet=medias', '/pro/mon-profil?onglet=preferences', '/pro/mon-profil?onglet=securite',
        '/pro/support', '/en/pro/dashboard', '/en/pro/bookings',
    ];

    public function testEveryScreenRendersWithTheDemoData(): void
    {
        $client = $this->loggedProvider();

        foreach (self::PAGES as $url) {
            $client->request('GET', $url);
            self::assertResponseIsSuccessful($url);
        }

        foreach (['/pro/activites/export', '/pro/reservations/export', '/pro/avis/export', '/pro/revenus/export', '/pro/statistiques/export'] as $url) {
            $client->request('GET', $url);
            self::assertResponseIsSuccessful($url);
            self::assertResponseHeaderSame('content-type', 'text/csv; charset=UTF-8');
        }
    }

    public function testAClientIsKeptOutOfTheProSpace(): void
    {
        $client = static::createClient();
        $user = (new User())->setEmail(sprintf('client-space-%s@example.com', uniqid()))->setFirstName('C')->setLastName('T')->setStatus(UserStatus::Active);
        $user->setPassword('x');
        $this->em()->persist($user);
        $this->em()->flush();
        $client->loginUser($user);

        foreach (['/pro/reservations' => '/compte/tableau-de-bord', '/pro/revenus' => '/compte/tableau-de-bord', '/en/pro/statistics' => '/en/account/dashboard'] as $url => $target) {
            $client->request('GET', $url);
            self::assertResponseRedirects($target, null, $url);
        }
    }

    public function testCreatingAnActivitySubmitsItForValidation(): void
    {
        $client = $this->loggedProvider();
        $category = $this->em()->getRepository(Category::class)->findOneBy([]);
        self::assertNotNull($category);

        $client->request('POST', '/pro/activites/nouvelle', $this->token($client, '/pro/activites/nouvelle') + [
            'title' => 'Atelier test espace pro',
            'category' => $category->getSlug(),
            'description' => 'Une description suffisamment longue pour passer la validation.',
            'price' => '42,50',
            'city' => 'Lyon',
            'durationMinutes' => '90',
            'capacity' => '8',
            'intent' => 'soumettre',
        ]);
        self::assertResponseRedirects('/pro/activites');

        $service = $this->em()->getRepository(Service::class)->findOneBy(['title' => 'Atelier test espace pro']);
        self::assertNotNull($service);
        self::assertSame(ServiceStatus::Pending, $service->getStatus());
        self::assertSame('1h30', $service->getDurationLabel());
        self::assertSame('42.50', $service->getPackages()->first()->getPrice());
    }

    public function testConfirmingABookingNotifiesTheClient(): void
    {
        $client = $this->loggedProvider();
        // Réservation créée pour le test : les données de démo évoluent
        // d'une exécution à l'autre (la base de test n'est pas remise à zéro).
        $service = $this->em()->getRepository(Service::class)->findOneBy(['status' => ServiceStatus::Published]);
        $buyer = $this->em()->getRepository(User::class)->findOneBy(['email' => 'julie.martin@client.trouvemoi.test']);
        self::assertNotNull($service);
        self::assertNotNull($buyer);
        $booking = (new Booking())->setClient($buyer)->setService($service)->setStartsAt(new \DateTimeImmutable('+5 days 10:00'))->setTotalPrice('50.00');
        $this->em()->persist($booking);
        $this->em()->flush();
        $before = \count(static::getContainer()->get(NotificationRepository::class)->findByRecipient($booking->getClient()));

        $client->request('POST', sprintf('/pro/reservations/%s/confirm', $booking->getId()), $this->token($client, '/pro/reservations'));
        self::assertResponseRedirects();

        $this->em()->clear();
        $booking = $this->em()->getRepository(Booking::class)->find($booking->getId());
        self::assertSame(BookingStatus::Confirmed, $booking->getStatus());
        self::assertCount($before + 1, static::getContainer()->get(NotificationRepository::class)->findByRecipient($booking->getClient()));
    }

    public function testReplyingToAClientAddsTheMessageToTheThread(): void
    {
        $client = $this->loggedProvider();
        $conversation = $this->em()->getRepository(Conversation::class)->findOneBy([]);
        self::assertNotNull($conversation);
        $count = $conversation->getMessages()->count();

        $client->request('POST', sprintf('/pro/messages/%s/envoyer', $conversation->getId()), $this->token($client, '/pro/messages') + ['body' => 'Réponse depuis l’espace pro']);
        self::assertResponseRedirects();

        $this->em()->clear();
        self::assertCount($count + 1, $this->em()->getRepository(Conversation::class)->find($conversation->getId())->getMessages());
    }

    public function testCreatingAnOfferAndOpeningASlot(): void
    {
        $client = $this->loggedProvider();
        $service = $this->em()->getRepository(Service::class)->findOneBy(['status' => ServiceStatus::Published]);
        self::assertNotNull($service);

        $client->request('POST', '/pro/offres/nouvelle', $this->token($client, '/pro/offres/nouvelle') + [
            'title' => 'Offre test -30', 'kind' => 'reduction', 'discount' => '30', 'service' => (string) $service->getId(),
            'startsAt' => date('Y-m-d'), 'endsAt' => date('Y-m-d', strtotime('+10 days')),
        ]);
        self::assertResponseRedirects('/pro/offres');
        self::assertNotNull($this->em()->getRepository(Promotion::class)->findOneBy(['title' => 'Offre test -30']));

        $slots = $this->em()->getRepository(Availability::class)->count([]);
        $client->request('POST', '/pro/calendrier/creneau', $this->token($client, '/pro/calendrier') + [
            'service' => (string) $service->getId(), 'date' => date('Y-m-d', strtotime('+3 days')), 'start' => '10:00', 'end' => '12:00', 'capacity' => '6',
        ]);
        self::assertResponseRedirects();
        self::assertSame($slots + 1, $this->em()->getRepository(Availability::class)->count([]));

        // L'offre active est affichée et comptée sur la fiche de l'activité.
        $client->request('GET', '/activites/'.$service->getSlug());
        self::assertSelectorTextContains('.act-booking__promo', '-30%');
    }

    public function testSettingsAndSupport(): void
    {
        $client = $this->loggedProvider();

        $client->request('POST', '/pro/parametres/enregistrer', $this->token($client, '/pro/parametres?section=securite') + ['form' => 'securite', 'section' => 'securite', 'current' => 'mauvais', 'new' => 'Nouveau123', 'confirm' => 'Nouveau123']);
        self::assertResponseRedirects('/pro/parametres?section=securite');
        $client->followRedirect();
        self::assertSelectorTextContains('.toast-body', 'mot de passe actuel est incorrect');

        $client->request('POST', '/pro/parametres/enregistrer', $this->token($client, '/pro/parametres?section=preferences') + ['form' => 'preferences', 'section' => 'preferences', 'locale' => 'fr', 'timezone' => 'Europe/Paris', 'availabilityLabel' => 'Lun - Ven : 9h - 18h']);
        self::assertResponseRedirects('/pro/parametres?section=preferences');
        $provider = static::getContainer()->get(ProviderProfileRepository::class)->findOneBy(['displayName' => 'Camille Aventures']);
        $this->em()->refresh($provider);
        self::assertSame('Lun - Ven : 9h - 18h', $provider->getAvailabilityLabel());

        $subject = 'Ticket de test '.uniqid();
        $client->request('POST', '/pro/support/tickets', $this->token($client, '/pro/support') + ['category' => 'payments', 'subject' => $subject, 'message' => 'Bonjour, une question.']);
        $ticket = $this->em()->getRepository(SupportTicket::class)->findOneBy(['subject' => $subject]);
        self::assertNotNull($ticket);
        self::assertResponseRedirects(sprintf('/pro/support/tickets/%d', $ticket->getNumber()));
        $client->followRedirect();
        self::assertSelectorTextContains('h1', $subject);
    }

    private function loggedProvider(): KernelBrowser
    {
        $client = static::createClient();
        $user = $this->em()->getRepository(User::class)->findOneBy(['email' => 'annonceur@trouvemoi.test']);
        self::assertNotNull($user, 'Fixtures requises (annonceur@trouvemoi.test).');
        $client->loginUser($user);

        return $client;
    }

    /** @return array{_token: string} */
    private function token(KernelBrowser $client, string $page): array
    {
        $crawler = $client->request('GET', $page);

        return ['_token' => (string) $crawler->filter('input[name="_token"]')->first()->attr('value')];
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
