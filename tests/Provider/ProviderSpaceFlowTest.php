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
use App\PrivateActivity\Entity\PrivateActivity;
use App\PrivateActivity\Enum\PrivateActivityStatus;
use App\PrivateActivity\Enum\PrivateActivityVisibility;
use App\Provider\Entity\ProviderProfile;
use App\Provider\Enum\ProviderStatus;
use App\Provider\Repository\ProviderProfileRepository;
use App\Provider\Service\ProviderSlugService;
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
        '/pro/tableau-de-bord', '/pro/tableau-de-bord?periode=30', '/pro/activites', '/pro/activites/assistant/1', '/pro/notifications',
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
            if (\in_array($url, ['/pro/tableau-de-bord', '/en/pro/dashboard'], true)) {
                self::assertSelectorExists('.tm-header__logo[href="'.(str_starts_with($url, '/en/') ? '/en/pro/dashboard' : '/pro/tableau-de-bord').'"]', $url);
            }
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

    public function testTheActivityWizardCreatesACompleteActivity(): void
    {
        $client = $this->loggedProvider();
        $category = $this->em()->getRepository(Category::class)->findOneBy([]);
        self::assertNotNull($category);
        $title = 'Atelier test assistant '.uniqid();

        $client->request('GET', '/pro/activites/nouvelle?vierge=1');
        self::assertResponseRedirects('/pro/activites/assistant/1');
        // Pas d'étape sautée.
        $client->request('GET', '/pro/activites/assistant/4');
        self::assertResponseRedirects('/pro/activites/assistant/1');

        // Étape 1 incomplète : refusée, erreurs sous les champs.
        $client->request('POST', '/pro/activites/assistant/1', $this->token($client, '/pro/activites/assistant/1') + ['action' => 'next', 'title' => 'Ab']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('.ew-error');

        $this->step($client, 1, ['title' => $title, 'category' => $category->getSlug(), 'activity_type' => 'supervised', 'level' => 'beginner', 'languages' => ['Français', 'Anglais'], 'subtitle' => 'Une accroche de test assez longue.']);
        $meetingPoint = str_repeat('Instructions détaillées pour rejoindre le point de rendez-vous. ', 8);
        $this->step($client, 2, ['address' => '12 rue de la République, Lyon', 'city' => 'Lyon', 'postal_code' => '69002', 'lat' => '45.76', 'lng' => '4.83', 'meeting_point' => $meetingPoint, 'opening_period' => 'all_year']);

        // Étape 3 : la photo principale est obligatoire.
        $client->request('POST', '/pro/activites/assistant/3', $this->token($client, '/pro/activites/assistant/3') + ['action' => 'next', 'description' => str_repeat('Une description détaillée. ', 5)]);
        self::assertResponseStatusCodeSame(422);
        $image = tempnam(sys_get_temp_dir(), 'aw').'.png';
        imagepng(imagecreatetruecolor(40, 30), $image);
        $client->request('POST', '/pro/activites/assistant/3', $this->token($client, '/pro/activites/assistant/3') + ['action' => 'next', 'description' => str_repeat('Une description détaillée. ', 5)], ['cover' => new \Symfony\Component\HttpFoundation\File\UploadedFile($image, 'cover.png', 'image/png', null, true)]);
        self::assertResponseRedirects('/pro/activites/assistant/4');

        $this->step($client, 4, ['duration' => '90', 'capacity' => '8', 'highlights' => "Point fort un\nPoint fort deux", 'included' => 'Matériel', 'to_bring' => 'Bonne humeur']);
        // Étape 5 : une prestation pro est payante, un prix à 0 est refusé (11/10).
        $client->request('POST', '/pro/activites/assistant/5', $this->token($client, '/pro/activites/assistant/5') + ['action' => 'next', 'cancellation' => 'moderate', 'booking_type' => 'calendar', 'packages' => [
            ['name' => 'Gratuit', 'price' => '0', 'unit' => 'per_person', 'description' => ''],
        ]]);
        self::assertResponseStatusCodeSame(422);

        $this->step($client, 5, ['cancellation' => 'moderate', 'booking_type' => 'calendar', 'packages' => [
            ['name' => 'Solo', 'price' => '42,50', 'unit' => 'per_person', 'description' => ''],
            ['name' => 'Groupe', 'price' => '150', 'unit' => 'per_group', 'description' => 'Jusqu’à 6 personnes'],
        ]]);

        // Sans la case d'engagement : refus.
        $client->request('POST', '/pro/activites/assistant/6', $this->token($client, '/pro/activites/assistant/6') + ['action' => 'next']);
        self::assertResponseStatusCodeSame(422);
        $client->request('POST', '/pro/activites/assistant/6', $this->token($client, '/pro/activites/assistant/6') + ['action' => 'next', 'accept_charter' => '1']);
        self::assertResponseRedirects('/pro/activites');

        $this->em()->clear();
        $service = $this->em()->getRepository(Service::class)->findOneBy(['title' => $title]);
        self::assertNotNull($service);
        self::assertSame(ServiceStatus::Pending, $service->getStatus());
        self::assertSame('1h30', $service->getDurationLabel());
        self::assertSame($meetingPoint, $service->getMeetingPoint());
        self::assertSame(['Français', 'Anglais'], $service->getLanguages());
        self::assertCount(2, $service->getPackages());
        self::assertSame('42.50', $service->getPackages()->first()->getPrice());
        self::assertSame(['Point fort un', 'Point fort deux'], $service->getDetail()?->getHighlights());
        self::assertGreaterThan(0, $service->getMedia()->count());
    }

    public function testAProviderNeverLandsInTheMemberAccount(): void
    {
        $client = $this->loggedProvider();
        foreach (['/compte/notifications' => '/pro/notifications', '/compte/tableau-de-bord' => '/pro/tableau-de-bord', '/compte/mes-evenements' => '/pro/tableau-de-bord', '/compte/activites-privees/nouvelle' => '/pro/activites/nouvelle', '/en/account/notifications' => '/en/pro/notifications'] as $url => $target) {
            $client->request('GET', $url);
            self::assertResponseRedirects($target, null, $url);
        }
        $crawler = $client->request('GET', '/pro/tableau-de-bord');
        self::assertGreaterThan(0, $crawler->filter('header a[href="/pro/notifications"]')->count(), 'La cloche de l’en-tête doit mener aux notifications pro.');
    }

    /**
     * Retours client du 11/10 : un professionnel ne crée rien de gratuit
     * (activité entre membres, événement, groupe) et n'y participe pas.
     */
    public function testAProviderNeitherCreatesNorJoinsFreeContent(): void
    {
        $client = $this->loggedProvider();
        foreach (['/creer-une-activite-gratuite', '/compte/activites-privees/creer/1', '/evenements/creer', '/evenements/creer/1', '/evenements/groupes/creer/1', '/en/events/create'] as $url) {
            $client->request('GET', $url);
            self::assertResponseRedirects(str_starts_with($url, '/en/') ? '/en/pro/activities/new' : '/pro/activites/nouvelle', null, $url);
        }
        $client->request('POST', '/evenements/creer/1', ['action' => 'next']);
        self::assertResponseRedirects('/pro/activites/nouvelle', null, 'POST de l’assistant événement');

        $activity = $this->em()->getRepository(PrivateActivity::class)->findOneBy(['status' => PrivateActivityStatus::Open, 'visibility' => PrivateActivityVisibility::Public]);
        self::assertNotNull($activity, 'Fixtures requises : une activité gratuite publique ouverte.');
        $crawler = $client->request('GET', '/activites-privees/'.$activity->getId());
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('form[action$="/participer"]'), 'Aucun bouton « Participer » pour un professionnel.');
        $client->request('POST', '/activites-privees/'.$activity->getId().'/participer', ['_token' => 'x']);
        self::assertResponseStatusCodeSame(403);
    }

    /**
     * Bandeau d'état du dossier (11/10) : un professionnel non vérifié sait
     * où il en est, et ne peut pas envoyer un dossier sans justificatif.
     */
    public function testTheProSpaceShowsTheApplicationStatus(): void
    {
        $client = static::createClient();
        $user = (new User())->setEmail(sprintf('pro-dossier-%s@example.com', uniqid()))->setFirstName('Dora')->setLastName('Pro')->setStatus(UserStatus::Active);
        $user->setPassword('x');
        $user->setRoles(['ROLE_PROVIDER']);
        $profile = (new ProviderProfile())->setUser($user)->setDisplayName('Dossier '.uniqid())->setStatus(ProviderStatus::Draft);
        static::getContainer()->get(ProviderSlugService::class)->assign($profile);
        $this->em()->persist($user);
        $this->em()->persist($profile);
        $this->em()->flush();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/pro/tableau-de-bord');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.pp-status.is-draft');
        self::assertSelectorExists('.pp-brand__state.is-draft');

        $client->request('POST', '/pro/dossier/envoyer', ['_token' => (string) $crawler->filter('.pp-status input[name="_token"]')->attr('value')]);
        self::assertResponseRedirects();
        $profile = $this->em()->getRepository(ProviderProfile::class)->find($profile->getId());
        self::assertNotNull($profile);
        self::assertSame(ProviderStatus::Draft, $profile->getStatus(), 'Un dossier sans justificatif ne part pas en vérification.');

        $profile->setStatus(ProviderStatus::PendingVerification);
        $this->em()->flush();
        $client->request('GET', '/pro/tableau-de-bord');
        self::assertSelectorExists('.pp-status.is-pending');
    }

    /** @param array<string, mixed> $data */
    private function step(KernelBrowser $client, int $step, array $data): void
    {
        $page = '/pro/activites/assistant/'.$step;
        $client->request('POST', $page, $this->token($client, $page) + ['action' => 'next'] + $data);
        self::assertResponseRedirects('/pro/activites/assistant/'.($step + 1), null, 'Étape '.$step);
    }

    public function testConfirmingABookingNotifiesTheClient(): void
    {
        $client = $this->loggedProvider();
        // Réservation créée pour le test : les données de démo évoluent
        // d'une exécution à l'autre (la base de test n'est pas remise à zéro).
        $service = $this->em()->getRepository(Service::class)->findOneBy(['status' => ServiceStatus::Published, 'provider' => $this->demoProvider()]);
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
        $conversation = $this->em()->getRepository(Conversation::class)->findOneBy(['provider' => $this->demoProvider()]);
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
        $service = $this->em()->getRepository(Service::class)->findOneBy(['status' => ServiceStatus::Published, 'provider' => $this->demoProvider()]);
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
        $provider = $this->demoProvider();
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

    /**
     * « Paiement et encaissements » (20/10, maquette parametre_configuration_
     * paiement_prestataire) : paiement en ligne, moyens acceptés, récapitulatif
     * « Paiement configuré » ; aucune commission TrouveMoi.
     */
    public function testTheProviderConfiguresHowHeIsPaid(): void
    {
        $client = $this->loggedProvider();
        $form = '/pro/parametres?section=paiement&modifier=1';

        // Récapitulatif (étape 3) avec la configuration des fixtures.
        $client->request('GET', '/pro/parametres?section=paiement');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.rg-done', 'Paiement en ligne activé');
        self::assertSelectorTextContains('.rg-recap', 'Carte Bancaire');
        self::assertSelectorExists('a[href*="modifier=1"]');

        // Formulaire (étape 2).
        $client->request('GET', $form);
        self::assertSelectorExists('input[name="onlinePaymentEnabled"]');
        self::assertSelectorExists('input[name="acceptsStripe"][checked]');
        self::assertSelectorTextContains('.rg-config', 'Compte Stripe relié');

        // PayPal coché sans adresse, ou paiement en ligne sans Stripe ni PayPal : refusé.
        $client->request('POST', '/pro/revenus/moyens-de-paiement', $this->token($client, $form) + ['acceptsPaypal' => '1', 'paymentPaypal' => '']);
        $client->followRedirect();
        self::assertSelectorTextContains('.toast-body', 'PayPal');
        $client->request('POST', '/pro/revenus/moyens-de-paiement', $this->token($client, $form) + ['onlinePaymentEnabled' => '1', 'acceptsCash' => '1']);
        $client->followRedirect();
        self::assertSelectorTextContains('.toast-body', 'Carte Bancaire et/ou PayPal');

        // Espèces seulement : paiement en ligne coupé, Stripe et PayPal non proposés.
        $client->request('POST', '/pro/revenus/moyens-de-paiement', $this->token($client, $form) + ['acceptsCash' => '1', 'paymentInstructions' => 'Monnaie appréciée']);
        self::assertResponseRedirects('/pro/parametres?section=paiement');
        $provider = $this->demoProvider();
        $provider = $this->demoProvider();
        $this->em()->refresh($provider);
        self::assertFalse($provider->isOnlinePaymentEnabled());
        self::assertFalse($provider->acceptsStripe());
        self::assertTrue($provider->acceptsCash());
        self::assertSame('Monnaie appréciée', $provider->getPaymentInstructions());
        $client->request('GET', '/pro/parametres?section=paiement');
        self::assertSelectorTextContains('.rg-done', 'Paiement configuré');

        // Délier puis relier le compte Stripe (Stripe Connect simulé en test).
        $client->request('POST', '/pro/parametres/paiement/stripe/delier', $this->token($client, $form));
        $provider = $this->demoProvider();
        $this->em()->refresh($provider);
        self::assertNull($provider->getStripeAccountId());
        $client->request('POST', '/pro/parametres/paiement/stripe', $this->token($client, $form) + ['onlinePaymentEnabled' => '1', 'acceptsCash' => '1', 'acceptsPaypal' => '1', 'paymentPaypal' => 'Camille.Aventures@paypal.test']);
        self::assertResponseRedirects();
        self::assertStringContainsString('/pro/parametres/paiement/stripe/retour', (string) $client->getResponse()->headers->get('Location'));
        $client->followRedirect();
        self::assertResponseRedirects('/pro/parametres?section=paiement');
        $client->followRedirect();
        self::assertSelectorTextContains('.toast-body', 'compte Stripe est relié');
        self::assertSelectorTextContains('.rg-done', 'Paiement en ligne activé');

        $provider = $this->demoProvider();
        $this->em()->refresh($provider);
        self::assertTrue($provider->isOnlinePaymentEnabled());
        self::assertTrue($provider->acceptsStripe());
        self::assertTrue($provider->acceptsPaypal());
        self::assertSame('camille.aventures@paypal.test', $provider->getPaymentPaypal());
        self::assertStringStartsWith('mock_acct_', (string) $provider->getStripeAccountId());
        self::assertTrue($provider->isStripeChargesEnabled());

        $client->request('GET', '/pro/revenus');
        self::assertSelectorTextContains('body', 'Moyens de paiement proposés');
        self::assertSelectorTextContains('.pp-kvs', 'Commission TrouveMoi0,00');
        self::assertSelectorTextNotContains('body', 'Prochain versement');
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

    /** Le dossier du compte de démonstration : d'autres prestataires existent en base (05/10). */
    private function demoProvider(): ProviderProfile
    {
        $user = $this->em()->getRepository(User::class)->findOneBy(['email' => 'annonceur@trouvemoi.test']);
        $provider = static::getContainer()->get(ProviderProfileRepository::class)->findOneByUser($user);
        self::assertNotNull($provider);

        return $provider;
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
