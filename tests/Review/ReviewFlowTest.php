<?php

declare(strict_types=1);

namespace App\Tests\Review;

use App\Catalog\Entity\Category;
use App\Provider\Entity\ProviderProfile;
use App\Provider\Enum\ProviderStatus;
use App\Provider\Service\ProviderSlugService;
use App\Quote\Service\QuoteService;
use App\Review\Repository\ReviewRepository;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Le parcours dépôt d'avis → réponse du professionnel, de bout en bout
 * (§16.2 du CDC). ReviewService/ReviewModerationService existaient déjà côté
 * service (adossés à Booking) : ce test protège le nouveau BRANCHEMENT — sur
 * le modèle demande/devis, avec ses propres écrans — ajouté le 15/09 (Lot H).
 */
final class ReviewFlowTest extends WebTestCase
{
    public function testAClientCanReviewAnAcceptedQuoteAndTheProviderCanReply(): void
    {
        $client = static::createClient();
        $category = $this->findOrMakeCategory();

        $clientUser = $this->makeClient();
        $clientEmail = $clientUser->getEmail();
        $providerUser = $this->makeProviderUser($category, 'Traiteur Avis Demo');
        $providerProfile = $this->providerProfileFor($providerUser);

        $serviceRequest = static::getContainer()->get(QuoteService::class)
            ->createRequest($clientUser, $category, 'Repas de mariage', 'Description de test.');
        $quote = static::getContainer()->get(QuoteService::class)
            ->submitQuote($serviceRequest, $providerProfile, '800.00');
        static::getContainer()->get(QuoteService::class)->accept($quote);

        // Le client dépose son avis.
        $client->loginUser($clientUser);
        $crawler = $client->request('GET', '/compte/demandes/'.(string) $quote->getServiceRequest()->getId());
        self::assertResponseIsSuccessful();

        $token = (string) $crawler->filter('form[action*="/avis"] input[name="_token"]')->attr('value');
        $client->request('POST', '/compte/devis/'.(string) $quote->getId().'/avis', [
            'note' => '5',
            'commentaire' => 'Prestation impeccable.',
            '_token' => $token,
        ]);
        self::assertResponseRedirects();

        $review = static::getContainer()->get(ReviewRepository::class)->findOneByQuote($quote);
        self::assertNotNull($review, "L'avis déposé ne se retrouve pas en base.");
        self::assertSame(5, $review->getRating());

        // Vérifié sur la fiche publique du professionnel.
        $client->request('GET', '/professionnels/'.$providerProfile->getSlug());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Prestation impeccable.');

        // Le professionnel se connecte et répond.
        $freshProviderUser = static::getContainer()->get(\App\User\Repository\UserRepository::class)->findOneBy(['email' => $providerUser->getEmail()]);
        self::assertNotNull($freshProviderUser);
        $client->loginUser($freshProviderUser);

        $crawler = $client->request('GET', '/pro/avis');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Prestation impeccable.');

        $replyToken = (string) $crawler->filter('form[action*="/repondre"] input[name="_token"]')->attr('value');
        $client->request('POST', '/pro/avis/'.(string) $review->getId().'/repondre', [
            'reponse' => 'Merci beaucoup !',
            '_token' => $replyToken,
        ]);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Merci beaucoup !');

        // Un second avis sur le même devis est refusé.
        $freshClientUser = static::getContainer()->get(\App\User\Repository\UserRepository::class)->findOneBy(['email' => $clientEmail]);
        self::assertNotNull($freshClientUser);
        $client->loginUser($freshClientUser);

        $crawler = $client->request('GET', '/compte/demandes/'.(string) $quote->getServiceRequest()->getId());
        self::assertSelectorTextContains('body', 'Vous avez déjà noté ce professionnel.');
    }

    public function testAThirdPartyCannotReviewSomeoneElsesQuote(): void
    {
        $client = static::createClient();
        $category = $this->findOrMakeCategory();

        $clientUser = $this->makeClient();
        $providerUser = $this->makeProviderUser($category, 'Photographe Avis Demo');
        $providerProfile = $this->providerProfileFor($providerUser);

        $serviceRequest = static::getContainer()->get(QuoteService::class)
            ->createRequest($clientUser, $category, 'Séance photo', 'Description de test.');
        $quote = static::getContainer()->get(QuoteService::class)
            ->submitQuote($serviceRequest, $providerProfile, '300.00');
        static::getContainer()->get(QuoteService::class)->accept($quote);

        $intrus = $this->makeClient();
        $client->loginUser($intrus);

        // N'importe quel jeton CSRF valide de session suffit à isoler ce
        // qu'on teste (le refus par le voter), pas la forme du jeton.
        $crawler = $client->request('GET', '/compte/demandes/nouvelle');
        $token = (string) $crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/compte/devis/'.(string) $quote->getId().'/avis', [
            'note' => '5',
            '_token' => $token,
        ]);

        self::assertResponseStatusCodeSame(403, 'Un tiers ne doit jamais pouvoir noter le devis de quelqu\'un d\'autre.');
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
            ->setEmail(sprintf('client-avis-%s@example.com', uniqid()))
            ->setFirstName('Alix')
            ->setLastName('Test')
            ->setStatus(UserStatus::Active);
        $user->setPassword('peu-importe');

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function makeProviderUser(Category $category, string $displayName): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())
            ->setEmail(sprintf('pro-avis-%s@example.com', uniqid()))
            ->setFirstName('Sacha')
            ->setLastName('Pro')
            ->setStatus(UserStatus::Active);
        $user->setPassword('peu-importe');
        $user->setRoles(['ROLE_PROVIDER']);
        $entityManager->persist($user);

        $profile = (new ProviderProfile())
            ->setUser($user)
            ->setDisplayName($displayName)
            ->setMainCategory($category)
            ->setStatus(ProviderStatus::Verified);

        static::getContainer()->get(ProviderSlugService::class)->assign($profile);

        $entityManager->persist($profile);
        $entityManager->flush();

        return $user;
    }

    private function providerProfileFor(User $user): ProviderProfile
    {
        $profile = static::getContainer()->get(\App\Provider\Repository\ProviderProfileRepository::class)->findOneByUser($user);
        self::assertNotNull($profile);

        return $profile;
    }
}
