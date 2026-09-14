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
