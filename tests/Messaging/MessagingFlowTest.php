<?php

declare(strict_types=1);

namespace App\Tests\Messaging;

use App\Catalog\Entity\Category;
use App\Messaging\Repository\ConversationRepository;
use App\Messaging\Repository\MessageRepository;
use App\Provider\Entity\ProviderProfile;
use App\Provider\Enum\ProviderStatus;
use App\Provider\Service\ProviderSlugService;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Le parcours de messagerie de bout en bout (§14 du CDC). MessagingService
 * existait déjà côté service, testé en isolation
 * (tests/Messaging/Service/MessagingServiceTest.php) : ce test protège le
 * BRANCHEMENT — les écrans, les routes, les droits — ajouté le 15/09.
 */
final class MessagingFlowTest extends WebTestCase
{
    public function testAClientCanStartAConversationAndSendAMessage(): void
    {
        $client = static::createClient();
        $category = $this->findOrMakeCategory();
        $provider = $this->makeVerifiedProvider($category, 'Photographe Demo');

        $clientUser = $this->makeClient();
        $client->loginUser($clientUser);

        // Depuis la fiche publique : « Contacter ».
        $crawler = $client->request('GET', '/professionnels/'.$provider->getSlug());
        self::assertResponseIsSuccessful();

        $token = (string) $crawler->filter('form[action*="/compte/messages/nouvelle/"] input[name="_token"]')->attr('value');
        $client->request('POST', '/compte/messages/nouvelle/'.$provider->getSlug(), ['_token' => $token]);
        self::assertResponseRedirects();

        $client->followRedirect();
        self::assertResponseIsSuccessful();

        $crawler = $client->getCrawler();
        $sendToken = (string) $crawler->filter('form textarea[name="message"]')->closest('form')->filter('input[name="_token"]')->attr('value');
        $conversationUrl = (string) $crawler->filter('form textarea[name="message"]')->closest('form')->attr('action');

        $client->request('POST', $conversationUrl, [
            'message' => 'Bonjour, êtes-vous disponible le 12 ?',
            '_token' => $sendToken,
        ]);
        self::assertResponseRedirects();

        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Bonjour, êtes-vous disponible le 12 ?');

        $conversations = static::getContainer()->get(ConversationRepository::class)->findForUser($clientUser);
        self::assertCount(1, $conversations, 'La conversation démarrée depuis la fiche pro ne se retrouve pas en base.');

        $providerUnread = static::getContainer()->get(MessageRepository::class)->countUnreadForUser($provider->getUser());
        self::assertSame(1, $providerUnread, 'Le message envoyé doit apparaître comme non lu pour le professionnel.');
    }

    public function testAThirdPartyCannotReadSomeoneElsesConversation(): void
    {
        $client = static::createClient();
        $category = $this->findOrMakeCategory();
        $provider = $this->makeVerifiedProvider($category, 'Traiteur Demo');
        $clientUser = $this->makeClient();

        $conversation = static::getContainer()->get(\App\Messaging\Service\MessagingService::class)
            ->openConversation($clientUser, $provider);

        $intrus = $this->makeClient();
        $client->loginUser($intrus);

        $client->request('GET', '/compte/messages/'.(string) $conversation->getId());

        self::assertResponseStatusCodeSame(403, 'Un tiers ne doit jamais pouvoir consulter la conversation d\'autrui.');
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
            ->setEmail(sprintf('client-messagerie-%s@example.com', uniqid()))
            ->setFirstName('Alix')
            ->setLastName('Test')
            ->setStatus(UserStatus::Active);
        $user->setPassword('peu-importe');

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function makeVerifiedProvider(Category $category, string $displayName): ProviderProfile
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())
            ->setEmail(sprintf('pro-messagerie-%s@example.com', uniqid()))
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

        return $profile;
    }
}
