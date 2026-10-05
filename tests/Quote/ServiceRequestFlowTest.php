<?php

declare(strict_types=1);

namespace App\Tests\Quote;

use App\Catalog\Entity\Category;
use App\Provider\Entity\ProviderProfile;
use App\Provider\Enum\ProviderStatus;
use App\Provider\Repository\ProviderProfileRepository;
use App\Provider\Service\ProviderSlugService;
use App\Quote\Entity\ServiceRequest;
use App\Quote\Enum\QuoteStatus;
use App\Quote\Enum\ServiceRequestStatus;
use App\Quote\Repository\QuoteRepository;
use App\Quote\Repository\ServiceRequestRepository;
use App\Quote\Service\QuoteService;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

/**
 * Le parcours demande → devis → décision, de bout en bout (§9, §10, §11 du
 * CDC). ServiceRequest et Quote existaient déjà côté service, testés en
 * isolation (tests/Quote/Service/QuoteServiceTest.php) : ce test protège le
 * BRANCHEMENT — les écrans, les routes, les droits — qui n'existait pas avant
 * le 14/09, pas la logique métier elle-même.
 *
 * ENTITÉS RE-INTERROGÉES APRÈS CHAQUE REQUÊTE, JAMAIS RÉUTILISÉES TELLES
 * QUELLES : `$client->request()` fait rebooter le noyau, donc le conteneur et
 * l'EntityManager d'avant la requête. Un objet chargé avant devient détaché ;
 * seul son identifiant (un ULID, une valeur immuable) reste sûr à conserver
 * d'une requête à l'autre. Même règle que dans AccountFavoriteHeartTest.
 */
final class ServiceRequestFlowTest extends WebTestCase
{
    public function testAVerifiedProviderIsSearchablePubliclyByProfessionAndCity(): void
    {
        $client = static::createClient();
        $category = $this->findOrMakeCategory();
        $this->makeVerifiedProvider($category, 'Recherche Demo', 'Bordeaux');

        $client->request('GET', '/professionnels', ['metier' => $category->getSlug(), 'ville' => 'Bordeaux']);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Recherche Demo');
    }

    public function testAClientCanPostARequestAndSeeItInTheirList(): void
    {
        $client = static::createClient();
        $categorySlug = $this->findOrMakeCategory()->getSlug();
        $user = $this->makeClient();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/compte/demandes/nouvelle');
        $token = (string) $crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/compte/demandes/nouvelle', [
            'category' => $categorySlug,
            'title' => 'Anniversaire enfant à domicile',
            'description' => 'Recherche animateur pour 15 enfants, dimanche prochain.',
            'participants' => '15',
            '_token' => $token,
        ]);

        self::assertResponseRedirects();

        $requests = static::getContainer()->get(ServiceRequestRepository::class)->findByClient($user);
        self::assertCount(1, $requests, 'La demande postée depuis le formulaire ne se retrouve pas en base.');
        self::assertSame('Anniversaire enfant à domicile', $requests[0]->getTitle());
        self::assertSame(ServiceRequestStatus::Open, $requests[0]->getStatus());

        $client->request('GET', '/compte/demandes');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Anniversaire enfant à domicile');
    }

    public function testAnUnverifiedProviderCannotSubmitAQuote(): void
    {
        $client = static::createClient();
        $category = $this->findOrMakeCategory();
        $clientUser = $this->makeClient();
        $requestId = (string) $this->makeOpenRequest($clientUser, $category, 'Besoin non vérifié')->getId();

        $providerUser = $this->makeProviderUser($category, ProviderStatus::PendingVerification);
        $client->loginUser($providerUser);

        $client->request('GET', '/pro/demandes/'.$requestId);
        self::assertResponseIsSuccessful('Un professionnel non vérifié doit pouvoir CONSULTER la demande…');
        // Le formulaire de devis ne lui est pas proposé (05/10)…
        self::assertSelectorNotExists('input[name="montant"]');

        $client->request('POST', '/pro/demandes/'.$requestId, [
            'montant' => '120.00',
            '_token' => 'csrf-token',
        ]);

        self::assertResponseStatusCodeSame(403, '…mais pas y déposer de devis avant vérification de son dossier (double verrou, voir CLAUDE.md).');
    }

    public function testAClientCanCloseARequestAndPendingQuotesAreDeclined(): void
    {
        $client = static::createClient();
        $category = $this->findOrMakeCategory();
        $clientUser = $this->makeClient();
        $requestId = (string) $this->makeOpenRequest($clientUser, $category, 'Demande à clôturer')->getId();

        $providerUser = $this->makeProviderUser($category, ProviderStatus::Verified);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $profile = static::getContainer()->get(ProviderProfileRepository::class)->findOneByUser($providerUser);
        $serviceRequest = static::getContainer()->get(ServiceRequestRepository::class)->find(Ulid::fromString($requestId));
        static::getContainer()->get(QuoteService::class)->submitQuote($serviceRequest, $profile, '300.00', 'Proposition');

        $client->loginUser($clientUser);
        $crawler = $client->request('GET', '/compte/demandes/'.$requestId);
        self::assertSelectorTextContains('.rq-quotes', '300,00');
        $token = (string) $crawler->filter('form[action$="/cloturer"] input[name="_token"]')->attr('value');
        $client->request('POST', '/compte/demandes/'.$requestId.'/cloturer', ['_token' => $token]);
        self::assertResponseRedirects('/compte/demandes/'.$requestId);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $closed = static::getContainer()->get(ServiceRequestRepository::class)->find(Ulid::fromString($requestId));
        self::assertSame(ServiceRequestStatus::Closed, $closed->getStatus());
        self::assertSame('declined', $closed->getQuotes()->first()->getStatus()->value);
    }

    public function testTheFullFlowFromRequestToAcceptedQuote(): void
    {
        $client = static::createClient();
        $category = $this->findOrMakeCategory();

        $clientUser = $this->makeClient();
        $clientEmail = $clientUser->getEmail();
        $requestId = (string) $this->makeOpenRequest($clientUser, $category, 'Photographe pour mariage')->getId();

        $providerUser = $this->makeProviderUser($category, ProviderStatus::Verified);

        // Le professionnel dépose un devis.
        $client->loginUser($providerUser);
        $crawler = $client->request('GET', '/pro/demandes/'.$requestId);
        self::assertResponseIsSuccessful();

        $token = (string) $crawler->filter('input[name="_token"]')->attr('value');
        $client->request('POST', '/pro/demandes/'.$requestId, [
            'montant' => '950,00', // virgule française, volontairement.
            'message' => 'Disponible ce jour-là.',
            '_token' => $token,
        ]);
        self::assertResponseRedirects();

        $serviceRequest = $this->reloadRequest($requestId);
        self::assertCount(1, $serviceRequest->getQuotes(), 'Le devis déposé ne se retrouve pas rattaché à la demande.');
        $quote = $serviceRequest->getQuotes()->first();
        self::assertNotFalse($quote);
        self::assertSame('950.00', $quote->getAmount(), 'La virgule française doit être normalisée en point décimal (NUMERIC).');
        $quoteId = (string) $quote->getId();

        // Le client se reconnecte (un autre compte que le professionnel) et accepte le devis.
        $freshClientUser = static::getContainer()->get(\App\User\Repository\UserRepository::class)->findOneBy(['email' => $clientEmail]);
        self::assertNotNull($freshClientUser);
        $client->loginUser($freshClientUser);

        $crawler = $client->request('GET', '/compte/demandes/'.$requestId);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', '950');

        $acceptToken = (string) $crawler->filter('form[action*="accepter"] input[name="_token"]')->attr('value');
        $client->request(
            'POST',
            sprintf('/compte/demandes/%s/devis/%s/accepter', $requestId, $quoteId),
            ['_token' => $acceptToken],
        );
        self::assertResponseRedirects();

        $serviceRequest = $this->reloadRequest($requestId);
        $quote = static::getContainer()->get(QuoteRepository::class)->find(Ulid::fromString($quoteId));
        self::assertNotNull($quote);

        self::assertSame(QuoteStatus::Accepted, $quote->getStatus());
        self::assertSame(ServiceRequestStatus::Closed, $serviceRequest->getStatus(), 'Accepter un devis doit clôturer la demande.');
    }

    public function testAClientCannotDecideOnAnotherClientsQuote(): void
    {
        $client = static::createClient();
        $category = $this->findOrMakeCategory();

        $owner = $this->makeClient();
        $serviceRequest = $this->makeOpenRequest($owner, $category, 'Demande d\'autrui');
        $requestId = (string) $serviceRequest->getId();

        $providerUser = $this->makeProviderUser($category, ProviderStatus::Verified);
        $providerProfile = static::getContainer()->get(ProviderProfileRepository::class)->findOneByUser($providerUser);
        self::assertNotNull($providerProfile);

        $quote = static::getContainer()->get(QuoteService::class)->submitQuote($serviceRequest, $providerProfile, '100.00');
        $quoteId = (string) $quote->getId();

        $intrus = $this->makeClient();
        $client->loginUser($intrus);

        // N'importe quel jeton CSRF valide de session suffit à isoler ce qu'on
        // teste (le refus par le voter), pas la forme du jeton.
        $crawler = $client->request('GET', '/compte/demandes/nouvelle');
        $token = (string) $crawler->filter('input[name="_token"]')->attr('value');

        $client->request(
            'POST',
            sprintf('/compte/demandes/%s/devis/%s/accepter', $requestId, $quoteId),
            ['_token' => $token],
        );

        self::assertResponseStatusCodeSame(403, 'Un client ne doit jamais pouvoir décider du devis reçu par un autre client.');
    }

    private function reloadRequest(string $id): ServiceRequest
    {
        $serviceRequest = static::getContainer()->get(ServiceRequestRepository::class)->find(Ulid::fromString($id));
        self::assertNotNull($serviceRequest);

        return $serviceRequest;
    }

    private function findOrMakeCategory(): Category
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $category = $entityManager->getRepository(Category::class)->findOneBy([]);
        self::assertNotNull($category, 'Aucune catégorie en base : les fixtures ont-elles été chargées ?');

        return $category;
    }

    private function makeClient(): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())
            ->setEmail(sprintf('client-devis-%s@example.com', uniqid()))
            ->setFirstName('Alix')
            ->setLastName('Test')
            ->setStatus(UserStatus::Active);
        $user->setPassword('peu-importe');

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function makeVerifiedProvider(Category $category, string $displayName, ?string $city = null): ProviderProfile
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $profile = (new ProviderProfile())
            ->setUser($this->makeProviderAccount())
            ->setDisplayName($displayName)
            ->setMainCategory($category)
            ->setCity($city)
            ->setStatus(ProviderStatus::Verified);

        static::getContainer()->get(ProviderSlugService::class)->assign($profile);

        $entityManager->persist($profile);
        $entityManager->flush();

        return $profile;
    }

    private function makeProviderUser(Category $category, ProviderStatus $status): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = $this->makeProviderAccount();

        $profile = (new ProviderProfile())
            ->setUser($user)
            ->setDisplayName('Pro '.uniqid())
            ->setMainCategory($category)
            ->setStatus($status);

        static::getContainer()->get(ProviderSlugService::class)->assign($profile);

        $entityManager->persist($profile);
        $entityManager->flush();

        return $user;
    }

    private function makeProviderAccount(): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())
            ->setEmail(sprintf('pro-devis-%s@example.com', uniqid()))
            ->setFirstName('Sacha')
            ->setLastName('Pro')
            ->setStatus(UserStatus::Active);
        $user->setPassword('peu-importe');
        $user->setRoles(['ROLE_PROVIDER']);

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function makeOpenRequest(User $clientUser, Category $category, string $title): ServiceRequest
    {
        return static::getContainer()->get(QuoteService::class)
            ->createRequest($clientUser, $category, $title, 'Description de test.');
    }
}
