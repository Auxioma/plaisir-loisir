<?php

declare(strict_types=1);

namespace App\Tests\User;

use App\Catalog\Entity\Category;
use App\Event\Entity\Event;
use App\Event\Repository\EventRepository;
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

        $token = (string) $crawler->filter('form[action*="parametres"] input[name="_token"]')->first()->attr('value');
        $client->request('POST', '/compte/parametres', [
            'prenom' => 'Nouveau',
            'nom' => 'Nom',
            'telephone' => '0600000000',
            '_token' => $token,
        ]);
        self::assertResponseRedirects('/compte/parametres');

        // $user est détaché depuis le reboot du noyau par $client->request() :
        // on le recharge par son e-mail, seul identifiant sûr d'une requête à
        // l'autre (même règle que ServiceRequestFlowTest).
        $reloaded = static::getContainer()->get(\App\User\Repository\UserRepository::class)->findOneBy(['email' => $email]);
        self::assertNotNull($reloaded);
        self::assertSame('Nouveau', $reloaded->getFirstName());
        self::assertSame('Nom', $reloaded->getLastName());
        self::assertSame('0600000000', $reloaded->getPhone());
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
