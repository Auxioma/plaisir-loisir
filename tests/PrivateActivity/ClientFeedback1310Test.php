<?php

declare(strict_types=1);

namespace App\Tests\PrivateActivity;

use App\Catalog\Entity\Category;
use App\Catalog\Enum\ActivityLevel;
use App\Messaging\Entity\Conversation;
use App\PrivateActivity\Entity\MemberReview;
use App\PrivateActivity\Entity\Participation;
use App\PrivateActivity\Entity\PrivateActivity;
use App\PrivateActivity\Enum\ParticipationStatus;
use App\PrivateActivity\Enum\PrivateActivityVisibility;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Retours client du 13/10 : profil public des membres, avis sur
 * l'organisateur, messagerie entre membres, recherche « Toutes » (payantes et
 * gratuites), événements dans la rubrique gratuite, recherches mal formées.
 */
final class ClientFeedback1310Test extends WebTestCase
{
    public function testTheOrganizerProfileIsPublicAndLinkedFromTheActivity(): void
    {
        $client = static::createClient();
        $organizer = $this->makeUser('Julie', 'Martin');
        $organizer->setBio('J’adore la randonnée.');
        $activity = $this->makeActivity($organizer, '+3 days');

        $crawler = $client->request('GET', '/activites-privees/'.$activity->getId());
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('a[href="/membres/'.$organizer->getId().'"]')->first());

        $client->request('GET', '/membres/'.$organizer->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Julie M.');
        self::assertSelectorTextContains('body', 'J’adore la randonnée.');
        self::assertSelectorTextContains('body', $activity->getTitle());
        // Le nom de famille complet n'est jamais publié.
        self::assertSelectorTextNotContains('body', 'Julie Martin');

        $client->request('GET', '/membres/01ARZ3NDEKTSV4RRFFQ69G5FAV');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAnAcceptedParticipantReviewsTheOrganizerOnceAfterTheActivity(): void
    {
        $client = static::createClient();
        $organizer = $this->makeUser('Julie', 'Martin');
        $participant = $this->makeUser('Léo', 'Durand');
        $past = $this->makeActivity($organizer, '-2 days');
        $this->join($past, $participant);
        $url = '/activites-privees/'.$past->getId();

        // Un membre qui n'a pas participé ne peut pas noter.
        $client->loginUser($this->makeUser('Zoé', 'Petit'));
        $client->request('GET', $url);
        self::assertSelectorNotExists('form.fd-review__form');

        $client->loginUser($participant);
        $crawler = $client->request('GET', $url);
        $form = $crawler->filter('form.fd-review__form')->form(['punctuality' => '4', 'comment' => 'Super sortie, très bien organisée.']);
        $client->submit($form);
        self::assertResponseRedirects($url.'#avis');

        $review = static::getContainer()->get(EntityManagerInterface::class)->getRepository(MemberReview::class)->findOneBy(['author' => $participant]);
        self::assertNotNull($review);
        self::assertSame(4, $review->getCriterion('punctuality'));

        // Un seul avis par activité.
        $client->request('GET', $url);
        self::assertSelectorNotExists('form.fd-review__form');

        $client->request('GET', '/membres/'.$organizer->getId());
        self::assertSelectorTextContains('body', 'Super sortie, très bien organisée.');
    }

    public function testAMemberStartsAConversationWithAnotherMember(): void
    {
        $client = static::createClient();
        $organizer = $this->makeUser('Julie', 'Martin');
        $this->makeActivity($organizer, '+3 days');
        $member = $this->makeUser('Léo', 'Durand');

        $client->loginUser($member);
        $crawler = $client->request('GET', '/membres/'.$organizer->getId());
        $client->submit($crawler->filter('form[action="/compte/messages/membre/'.$organizer->getId().'"]')->form());
        self::assertResponseRedirects();
        self::assertMatchesRegularExpression('#^/compte/messages/#', (string) $client->getResponse()->headers->get('Location'));

        $conversations = static::getContainer()->get(EntityManagerInterface::class)->getRepository(Conversation::class)->findBy(['member' => $organizer]);
        self::assertCount(1, $conversations);
        self::assertEquals($member->getId(), $conversations[0]->getClient()?->getId());
    }

    public function testTheAllActivitiesSearchListsPaidAndFreeActivities(): void
    {
        $client = static::createClient();
        $free = $this->makeActivity($this->makeUser('Julie', 'Martin'), '+3 days');

        $crawler = $client->request('GET', '/activites');
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('.ex-card .card-kind--pro')->count());
        self::assertSelectorExists('.ex-free .fa-card .card-kind--members');
        // Même recherche que l'accueil en mode « Toutes » : l'activité gratuite répond aussi.
        $client->request('GET', '/activites', ['q' => $free->getTitle(), 'type' => 'toutes']);
        self::assertSelectorTextContains('.ex-free', $free->getTitle());

        // « Payantes » : les professionnels seulement.
        $client->request('GET', '/activites', ['type' => 'payantes']);
        self::assertSelectorNotExists('.ex-free');
    }

    public function testMemberEventsAppearInTheFreeActivitiesSection(): void
    {
        $client = static::createClient();
        $client->request('GET', '/activites-privees');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.pa-events .evn-card');
    }

    public function testMalformedSearchParametersNoLongerEndOnAnErrorPage(): void
    {
        $client = static::createClient();

        $client->request('GET', '/activites?participants=99999999999');
        self::assertResponseIsSuccessful();

        $client->request('GET', '/activites?note=4');
        self::assertResponseIsSuccessful();

        $client->request('GET', '/activites?prix_min=abc');
        self::assertResponseRedirects('/activites');
    }

    private function join(PrivateActivity $activity, User $user): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist((new Participation())
            ->setPrivateActivity($activity)
            ->setParticipant($user)
            ->setStatus(ParticipationStatus::Accepted));
        $entityManager->flush();
    }

    private function makeActivity(User $organizer, string $when): PrivateActivity
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $category = $entityManager->getRepository(Category::class)->findOneBy([]);
        self::assertNotNull($category, 'Les fixtures du catalogue doivent être chargées.');

        $activity = (new PrivateActivity())
            ->setOrganizer($organizer)
            ->setTitle('Sortie 1310 '.uniqid())
            ->setCategory($category)
            ->setDescription('Une sortie de test pour vérifier les retours du client.')
            ->setScheduledAt(new \DateTimeImmutable($when.' 10:00'))
            ->setCity('Rouen')
            ->setVisibility(PrivateActivityVisibility::Public)
            ->setLevel(ActivityLevel::Beginner)
            ->setMaxParticipants(10);
        $entityManager->persist($activity);
        $entityManager->flush();

        return $activity;
    }

    private function makeUser(string $firstName, string $lastName): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())
            ->setEmail(sprintf('retours-1310-%s@example.com', uniqid()))
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setStatus(UserStatus::Active);
        $user->setPassword('peu-importe');
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
