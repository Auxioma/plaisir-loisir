<?php

declare(strict_types=1);

namespace App\Tests\User;

use App\Catalog\Entity\Category;
use App\Event\Entity\Event;
use App\Event\Repository\EventRepository;
use App\Notification\Enum\NotificationCategory;
use App\Notification\Service\NotificationService;
use App\PrivateActivity\Service\PrivateActivityService;
use App\Provider\Entity\ProviderProfile;
use App\Provider\Enum\ProviderStatus;
use App\Quote\Service\QuoteService;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Rubriques restantes de l'espace client (Lot J, 15/09) : mes activités
 * créées et l'historique (demandes clôturées + activités privées passées).
 */
final class AccountPagesFlowTest extends WebTestCase
{
    public function testTheSettingsScreenUpdatesNameAndPhone(): void
    {
        $client = static::createClient();
        $user = $this->makeClient();
        $email = $user->getEmail();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/compte/parametres');
        self::assertResponseIsSuccessful();

        $token = (string) $crawler->filter('form[action*="parametres"] input[name="account_settings_form[_token]"]')->first()->attr('value');
        $client->request('POST', '/compte/parametres', [
            'account_settings_form' => [
                'firstName' => 'Nouveau',
                'lastName' => 'Nom',
                'phone' => ['country' => 'FR', 'number' => '0600000000'],
                '_token' => $token,
            ],
        ]);
        self::assertResponseRedirects('/compte/parametres');

        // $user est détaché depuis le reboot du noyau par $client->request() :
        // on le recharge par son e-mail, seul identifiant sûr d'une requête à
        // l'autre (même règle que ServiceRequestFlowTest).
        $reloaded = static::getContainer()->get(\App\User\Repository\UserRepository::class)->findOneBy(['email' => $email]);
        self::assertNotNull($reloaded);
        self::assertSame('Nouveau', $reloaded->getFirstName());
        self::assertSame('Nom', $reloaded->getLastName());
        // Stocké en E.164, comme tout numéro passé par PhoneNumberType.
        self::assertSame('+33600000000', $reloaded->getPhone());
    }

    public function testTheSettingsScreenRejectsAnInvalidPhoneNumber(): void
    {
        $client = static::createClient();
        $user = $this->makeClient();
        $email = $user->getEmail();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/compte/parametres');
        $token = (string) $crawler->filter('form[action*="parametres"] input[name="account_settings_form[_token]"]')->first()->attr('value');

        $client->request('POST', '/compte/parametres', [
            'account_settings_form' => [
                'firstName' => 'Nouveau',
                'lastName' => 'Nom',
                'phone' => ['country' => 'FR', 'number' => '123'],
                '_token' => $token,
            ],
        ]);
        // Formulaire soumis invalide réaffiché : Symfony répond 422, pas 200.
        self::assertResponseStatusCodeSame(422);

        // La dernière requête n'a pas rebooté le noyau : sans ce clear(),
        // l'identity map Doctrine renverrait l'entité en mémoire mutée par
        // la soumission du formulaire (firstName déjà écrit par le data
        // mapper avant l'échec de validation), pas l'état réellement
        // persisté en base (même pattern que tests/Admin/EditAndDeleteTest.php).
        static::getContainer()->get(EntityManagerInterface::class)->clear();

        $reloaded = static::getContainer()->get(\App\User\Repository\UserRepository::class)->findOneBy(['email' => $email]);
        self::assertNotNull($reloaded);
        self::assertNotSame('Nouveau', $reloaded->getFirstName());
        self::assertNull($reloaded->getPhone());
    }

    public function testMyEventsListsWhatIOrganized(): void
    {
        $client = static::createClient();
        $user = $this->makeClient();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $event = (new Event())
            ->setTitle('Mon événement de test')
            ->setSlug(sprintf('mon-evenement-%s', uniqid()))
            ->setOrganizer($user)
            ->setStartsAt(new \DateTimeImmutable('+1 month'));
        $entityManager->persist($event);
        $entityManager->flush();

        self::assertNotEmpty(static::getContainer()->get(EventRepository::class)->findByOrganizer($user));

        $client->loginUser($user);
        $client->request('GET', '/compte/mes-evenements');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Mon événement de test');
    }

    public function testHistoryListsClosedRequestsAndPastParticipations(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $category = $entityManager->getRepository(Category::class)->findOneBy([]);
        self::assertNotNull($category, 'Aucune catégorie en base : les fixtures ont-elles été chargées ?');

        $user = $this->makeClient();

        // Une demande clôturée (devis accepté).
        $providerUser = $this->makeProviderUser($category);
        $providerProfile = static::getContainer()->get(\App\Provider\Repository\ProviderProfileRepository::class)->findOneByUser($providerUser);
        self::assertNotNull($providerProfile);

        $quoteService = static::getContainer()->get(QuoteService::class);
        $serviceRequest = $quoteService->createRequest($user, $category, 'Demande passée', 'Description de test.');
        $quote = $quoteService->submitQuote($serviceRequest, $providerProfile, '150.00');
        $quoteService->accept($quote);

        // Une activité privée passée, à laquelle le client a participé.
        $activityService = static::getContainer()->get(PrivateActivityService::class);
        $activity = $activityService->create($this->makeClient(), 'Sortie passée', $category, scheduledAt: new \DateTimeImmutable('-1 week'));
        $participation = $activityService->requestParticipation($activity, $user);
        $activityService->decide($participation, $activity->getOrganizer(), true);

        $client->loginUser($user);
        $client->request('GET', '/compte/historique');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Demande passée');
        self::assertSelectorTextContains('body', 'Sortie passée');
    }

    /**
     * §26 du CDC : export des données personnelles (droit à la portabilité).
     */
    public function testExportDownloadsAJsonFileWithThePersonalData(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $category = $entityManager->getRepository(Category::class)->findOneBy([]);
        self::assertNotNull($category);

        $user = $this->makeClient();
        static::getContainer()->get(QuoteService::class)
            ->createRequest($user, $category, 'Demande à exporter', 'Description.');

        $client->loginUser($user);
        $client->request('GET', '/compte/parametres/exporter');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');
        self::assertStringContainsString('attachment', (string) $client->getResponse()->headers->get('content-disposition'));

        $data = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame($user->getEmail(), $data['compte']['email']);
        self::assertSame('Demande à exporter', $data['demandes_de_devis'][0]['titre']);
    }

    /**
     * §7.1 du CDC : le tableau de bord doit afficher les notifications
     * récentes.
     */
    public function testDashboardShowsRecentNotifications(): void
    {
        $client = static::createClient();
        $user = $this->makeClient();

        static::getContainer()->get(NotificationService::class)
            ->notify($user, NotificationCategory::System, 'Bienvenue sur TrouveMoi', 'Votre compte est prêt.');

        $client->loginUser($user);
        $client->request('GET', '/compte/tableau-de-bord');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Bienvenue sur TrouveMoi');
    }

    private function makeClient(): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())
            ->setEmail(sprintf('espace-client-%s@example.com', uniqid()))
            ->setFirstName('Alix')
            ->setLastName('Test')
            ->setStatus(UserStatus::Active);
        $user->setPassword('peu-importe');

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function makeProviderUser(Category $category): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())
            ->setEmail(sprintf('espace-client-pro-%s@example.com', uniqid()))
            ->setFirstName('Sacha')
            ->setLastName('Pro')
            ->setStatus(UserStatus::Active);
        $user->setPassword('peu-importe');
        $user->setRoles(['ROLE_PROVIDER']);
        $entityManager->persist($user);

        $profile = (new ProviderProfile())
            ->setUser($user)
            ->setDisplayName('Pro '.uniqid())
            ->setMainCategory($category)
            ->setStatus(ProviderStatus::Verified);

        static::getContainer()->get(\App\Provider\Service\ProviderSlugService::class)->assign($profile);

        $entityManager->persist($profile);
        $entityManager->flush();

        return $user;
    }
}
