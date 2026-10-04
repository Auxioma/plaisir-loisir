<?php

declare(strict_types=1);

namespace App\Tests\Provider;

use App\Catalog\Entity\Category;
use App\Provider\Entity\ProviderProfile;
use App\Provider\Enum\ProviderStatus;
use App\Provider\Repository\ProviderProfileRepository;
use App\Provider\Service\ProviderSlugService;
use App\Quote\Service\QuoteService;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Le tableau de bord et la fiche professionnelle éditable, de bout en bout
 * (§8.3, §33.1 du CDC). Signalé le 14/09 : un compte pro connecté voyait
 * exactement la même page qu'un client — ce test protège l'écran qui répond
 * à ce signalement.
 */
final class ProviderDashboardFlowTest extends WebTestCase
{
    public function testAClientCannotReachTheDashboard(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeClientUser());

        $client->request('GET', '/pro/tableau-de-bord');

        // Zone interdite à ce type de compte : retour sur son propre espace
        // avec un message (AccessDeniedHandler), plus d'erreur 403 brute.
        self::assertResponseRedirects('/compte/tableau-de-bord');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Vous n\'avez pas accès à cette page.');
    }

    public function testTheDashboardShowsRealCountsForTheLoggedInProvider(): void
    {
        $client = static::createClient();
        $category = $this->findOrMakeCategory();
        $providerUser = $this->makeProviderUser($category);

        $requester = $this->makeClientUser();
        $serviceRequest = static::getContainer()->get(QuoteService::class)
            ->createRequest($requester, $category, 'Besoin pour le tableau de bord', 'Description.');

        $provider = static::getContainer()->get(ProviderProfileRepository::class)->findOneByUser($providerUser);
        self::assertNotNull($provider);
        static::getContainer()->get(QuoteService::class)->submitQuote($serviceRequest, $provider, '120.00');

        $client->loginUser($providerUser);
        $client->request('GET', '/pro/tableau-de-bord');

        self::assertResponseIsSuccessful();
        // Tableau de bord des maquettes profil_professionnel (02/10) : la
        // demande ouverte dans la catégorie du professionnel apparaît dans
        // les accès rapides (« Demandes de devis »).
        self::assertSelectorTextContains('h1', 'Tableau de bord');
        self::assertSelectorTextContains('.pp-quick', 'Demandes de devis');
    }

    public function testEditingTheProfilePersistsTheChanges(): void
    {
        $client = static::createClient();
        $category = $this->findOrMakeCategory();
        $providerUser = $this->makeProviderUser($category);

        $client->loginUser($providerUser);
        // La fiche se modifie désormais dans Paramètres › Profil professionnel.
        $client->request('GET', '/pro/profil');
        self::assertResponseRedirects('/pro/parametres?section=profil-pro');
        $crawler = $client->followRedirect();
        self::assertResponseIsSuccessful();

        $token = (string) $crawler->filter('input[name="_token"]')->attr('value');
        $client->request('POST', '/pro/profil', [
            '_token' => $token,
            'displayName' => 'Nouveau Nom Affiché',
            'metier' => $category->getSlug(),
            'bio' => 'Une présentation mise à jour.',
            'ville' => 'Marseille',
        ]);

        self::assertResponseRedirects('/pro/parametres?section=profil-pro');

        $provider = static::getContainer()->get(ProviderProfileRepository::class)->findOneByUser($providerUser);
        self::assertNotNull($provider);
        self::assertSame('Nouveau Nom Affiché', $provider->getDisplayName());
        self::assertSame('Une présentation mise à jour.', $provider->getBio());
        self::assertSame('Marseille', $provider->getCity());
    }

    public function testEditingTheProfileRejectsAnEmptyDisplayName(): void
    {
        $client = static::createClient();
        $category = $this->findOrMakeCategory();
        $providerUser = $this->makeProviderUser($category);

        $client->loginUser($providerUser);
        $crawler = $client->request('GET', '/pro/parametres?section=profil-pro');
        $token = (string) $crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/pro/profil', ['_token' => $token, 'displayName' => '   ']);

        self::assertResponseRedirects('/pro/parametres?section=profil-pro');
        $client->followRedirect();
        self::assertSelectorTextContains('.toast-body', 'ne peut pas être vide');
    }

    private function findOrMakeCategory(): Category
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $category = $entityManager->getRepository(Category::class)->findOneBy([]);
        self::assertNotNull($category);

        return $category;
    }

    private function makeClientUser(): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())
            ->setEmail(sprintf('client-dashboard-%s@example.com', uniqid()))
            ->setFirstName('Client')
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
            ->setEmail(sprintf('pro-dashboard-%s@example.com', uniqid()))
            ->setFirstName('Sacha')
            ->setLastName('Pro')
            ->setStatus(UserStatus::Active);
        $user->setPassword('peu-importe');
        $user->setRoles(['ROLE_PROVIDER']);
        $entityManager->persist($user);

        $profile = (new ProviderProfile())
            ->setUser($user)
            ->setDisplayName('Pro Tableau de Bord '.uniqid())
            ->setMainCategory($category)
            ->setStatus(ProviderStatus::Verified);

        static::getContainer()->get(ProviderSlugService::class)->assign($profile);

        $entityManager->persist($profile);
        $entityManager->flush();

        return $user;
    }
}
