<?php

declare(strict_types=1);

namespace App\Tests\Provider;

use App\Catalog\Entity\Category;
use App\Provider\Entity\ProviderProfile;
use App\Provider\Enum\ProviderStatus;
use App\Provider\Service\ProviderSlugService;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Recherche de professionnels et fiche publique (§5, §9 du CDC) : avant le
 * 14/09, /professionnels ne menait nulle part.
 */
final class ProviderSearchControllerTest extends WebTestCase
{
    public function testAnUnverifiedProviderNeverAppearsInSearchResults(): void
    {
        $client = static::createClient();
        $category = $this->findOrMakeCategory();
        $this->makeProvider($category, 'Dossier Pas Encore Vérifié', ProviderStatus::PendingVerification);

        $client->request('GET', '/professionnels');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', 'Dossier Pas Encore Vérifié');
    }

    public function testAVerifiedProviderProfilePageIsPublic(): void
    {
        $client = static::createClient();
        $category = $this->findOrMakeCategory();
        $profile = $this->makeProvider($category, 'Fiche Publique Test', ProviderStatus::Verified);

        $client->request('GET', '/professionnels/'.$profile->getSlug());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Fiche Publique Test');
    }

    /**
     * Annuaire refait le 05/10 : chaque carte porte les vraies données du
     * prestataire, et la catégorie ressort aussi par ses activités.
     */
    public function testTheDirectoryShowsRealFiguresAndFiltersByActivities(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/professionnels', ['q' => 'Normandie Kayak']);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('.pd-card'));
        self::assertSelectorTextContains('.pd-card', 'Canoë sur la Seine');
        self::assertSelectorTextContains('.pd-card__stats', '2 activités');

        // « Sports & Aventures » n'est pas sa catégorie principale, mais il y propose une activité.
        $crawler = $client->request('GET', '/professionnels', ['metier' => 'sports-aventures']);
        self::assertStringContainsString('Normandie Kayak', $crawler->filter('.pd-grid')->text());

        $crawler = $client->request('GET', '/professionnels', ['ville' => 'Rouen', 'rayon' => '50']);
        self::assertStringNotContainsString('Escape Le Havre', $crawler->filter('body')->text());
        self::assertStringContainsString('Zoo de Clères', $crawler->filter('.pd-grid')->text());

        $link = $crawler->filter('a.pd-card__name')->first()->attr('href');
        $client->request('GET', (string) $link);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#activites .gc-card');
    }

    /**
     * §5, §9 du CDC : recherche par rayon (Lot K, 16/09), sur la table
     * statique FrenchCityCoordinates — Lyon est à environ 392 km de Paris,
     * donc hors d'un rayon de 100 km mais dans un rayon de 500 km.
     */
    public function testRadiusExcludesProvidersOutsideItAndIncludesThoseWithin(): void
    {
        $client = static::createClient();
        $category = $this->findOrMakeCategory();
        // Noms uniques : la base de test garde les prestataires des exécutions précédentes.
        $tag = substr(uniqid(), -6);
        $this->makeProvider($category, 'Pro Parisien '.$tag, ProviderStatus::Verified)->setCity('Paris');
        $lyonnais = $this->makeProvider($category, 'Pro Lyonnais '.$tag, ProviderStatus::Verified);
        $lyonnais->setCity('Lyon');
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $client->request('GET', '/professionnels', ['ville' => 'Paris', 'rayon' => '100', 'q' => $tag]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Pro Parisien '.$tag);
        self::assertSelectorTextNotContains('body', 'Pro Lyonnais '.$tag);

        $client->request('GET', '/professionnels', ['ville' => 'Paris', 'rayon' => '500', 'q' => $tag]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Pro Parisien '.$tag);
        self::assertSelectorTextContains('body', 'Pro Lyonnais '.$tag);
    }

    /**
     * Ville inconnue de la table statique : le rayon ne peut pas s'appliquer,
     * la recherche retombe sur la correspondance texte habituelle plutôt que
     * de renvoyer zéro résultat sans explication.
     */
    public function testRadiusOnAnUnknownCityFallsBackToTextMatch(): void
    {
        $client = static::createClient();
        $category = $this->findOrMakeCategory();
        $this->makeProvider($category, 'Pro Hameau', ProviderStatus::Verified)->setCity('Un Hameau Improbable');
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $client->request('GET', '/professionnels', ['ville' => 'Un Hameau Improbable', 'rayon' => '10']);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Pro Hameau');
    }

    public function testAnUnknownSlugReturnsNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/professionnels/ce-slug-n-existe-pas');

        self::assertResponseStatusCodeSame(404);
    }

    private function findOrMakeCategory(): Category
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $category = $entityManager->getRepository(Category::class)->findOneBy([]);
        self::assertNotNull($category);

        return $category;
    }

    private function makeProvider(Category $category, string $displayName, ProviderStatus $status): ProviderProfile
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())
            ->setEmail(sprintf('recherche-pro-%s@example.com', uniqid()))
            ->setFirstName('Test')
            ->setLastName('Pro')
            ->setStatus(UserStatus::Active);
        $user->setPassword('peu-importe');
        $user->setRoles(['ROLE_PROVIDER']);
        $entityManager->persist($user);

        $profile = (new ProviderProfile())
            ->setUser($user)
            ->setDisplayName($displayName)
            ->setMainCategory($category)
            ->setStatus($status);

        static::getContainer()->get(ProviderSlugService::class)->assign($profile);

        $entityManager->persist($profile);
        $entityManager->flush();

        return $profile;
    }
}
