<?php

declare(strict_types=1);

namespace App\Tests\PrivateActivity;

use App\Catalog\Entity\Category;
use App\PrivateActivity\Entity\PrivateActivity;
use App\PrivateActivity\Enum\ParticipationMode;
use App\PrivateActivity\Enum\ParticipationStatus;
use App\PrivateActivity\Enum\PrivateActivityStatus;
use App\PrivateActivity\Enum\PrivateActivityVisibility;
use App\PrivateActivity\Repository\ParticipationRepository;
use App\PrivateActivity\Repository\PrivateActivityRepository;
use App\PrivateActivity\Service\PrivateActivityService;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

/**
 * Le parcours activité privée, de bout en bout (§12-13 du CDC) : découverte,
 * demande de participation, capacité, liste d'attente, décision de
 * l'organisateur — le branchement écrans/routes/droits que
 * PrivateActivityServiceTest (logique pure) et PrivateActivityVoterTest
 * (droits en isolation) ne peuvent pas prouver à eux seuls.
 *
 * Entités re-interrogées après chaque requête HTTP, jamais réutilisées
 * telles quelles : voir ServiceRequestFlowTest pour pourquoi (le noyau
 * rebbote entre deux appels à $client->request()).
 */
final class PrivateActivityFlowTest extends WebTestCase
{
    public function testAPublicActivityIsDiscoverableByAnonymousVisitors(): void
    {
        $client = static::createClient();
        $category = $this->findOrMakeCategory();
        $organizer = $this->makeUser();

        $activityId = (string) static::getContainer()->get(PrivateActivityService::class)
            ->create($organizer, 'Rando publique de test', $category, visibility: PrivateActivityVisibility::Public)
            ->getId();

        $client->request('GET', '/activites-privees');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Rando publique de test');

        $client->request('GET', '/activites-privees/'.$activityId);
        self::assertResponseIsSuccessful();
    }

    public function testAMembersOnlyActivityIsHiddenFromTheAnonymousListingButVisibleOnceLoggedIn(): void
    {
        $client = static::createClient();
        $category = $this->findOrMakeCategory();
        $organizer = $this->makeUser();

        static::getContainer()->get(PrivateActivityService::class)
            ->create($organizer, 'Sortie réservée aux membres', $category, visibility: PrivateActivityVisibility::MembersOnly);

        $client->request('GET', '/activites-privees');
        self::assertSelectorTextNotContains('body', 'Sortie réservée aux membres');

        $client->loginUser($this->makeUser());
        $client->request('GET', '/activites-privees');
        self::assertSelectorTextContains('body', 'Sortie réservée aux membres');
    }

    /**
     * Le parcours complet : création avec capacité 1, un premier participant
     * pris automatiquement, un second mis en liste d'attente, puis promu
     * automatiquement quand le premier annule (§13.3, §13.4 du CDC).
     */
    public function testCapacityAndWaitingListPromoteTheOldestOnCancellation(): void
    {
        $client = static::createClient();
        $category = $this->findOrMakeCategory();
        $organizer = $this->makeUser();

        $activity = static::getContainer()->get(PrivateActivityService::class)->create(
            $organizer,
            'Atelier à une place',
            $category,
            participationMode: ParticipationMode::Automatic,
            maxParticipants: 1,
        );
        $activityId = (string) $activity->getId();

        $first = $this->makeUser();
        $second = $this->makeUser();

        // Le premier prend la seule place, immédiatement (mode automatique).
        $client->loginUser($first);
        $crawler = $client->request('GET', '/activites-privees/'.$activityId);
        $token = (string) $crawler->filter('form[action*="participer"] input[name="_token"]')->attr('value');
        $client->request('POST', '/activites-privees/'.$activityId.'/participer', ['_token' => $token]);
        self::assertResponseRedirects();

        // Le second arrive trop tard : liste d'attente, pas de refus silencieux.
        $client->loginUser($second);
        $crawler = $client->request('GET', '/activites-privees/'.$activityId);
        self::assertResponseIsSuccessful();
        $token = (string) $crawler->filter('form[action*="participer"] input[name="_token"]')->attr('value');
        $client->request('POST', '/activites-privees/'.$activityId.'/participer', ['_token' => $token]);
        self::assertResponseRedirects();

        [$firstParticipation, $secondParticipation] = $this->reloadParticipations($activityId, $first, $second);
        self::assertSame(ParticipationStatus::Accepted, $firstParticipation->getStatus());
        self::assertSame(ParticipationStatus::WaitingList, $secondParticipation->getStatus());
        self::assertSame(PrivateActivityStatus::Full, $this->reloadActivity($activityId)->getStatus());

        // Le premier annule : le second doit être promu automatiquement.
        $client->loginUser($first);
        $crawler = $client->request('GET', '/activites-privees/'.$activityId);
        // L'URL rendue porte le nom de route FRANÇAIS ("…/participations/{id}/annuler"),
        // pas le nom symbolique de la route : "participations" y figure, "participation_cancel" non.
        $cancelToken = (string) $crawler->filter('form[action*="participations"] input[name="_token"]')->attr('value');
        $client->request(
            'POST',
            sprintf('/compte/activites-privees/%s/participations/%s/annuler', $activityId, $firstParticipation->getId()),
            ['_token' => $cancelToken],
        );
        self::assertResponseRedirects();

        [$firstParticipation, $secondParticipation] = $this->reloadParticipations($activityId, $first, $second);
        self::assertSame(ParticipationStatus::Cancelled, $firstParticipation->getStatus());
        self::assertSame(ParticipationStatus::Accepted, $secondParticipation->getStatus(), 'La personne en liste d\'attente n\'a pas été promue.');
        // La place reste occupée — par quelqu'un d'autre : l'activité doit
        // rester FULL, pas repasser Open avec sa seule place déjà reprise.
        self::assertSame(PrivateActivityStatus::Full, $this->reloadActivity($activityId)->getStatus());
    }

    /**
     * Mode VALIDATION : l'organisateur décide, et lui seul.
     */
    public function testOrganizerAcceptsAPendingRequest(): void
    {
        $client = static::createClient();
        $category = $this->findOrMakeCategory();
        $organizer = $this->makeUser();

        $activity = static::getContainer()->get(PrivateActivityService::class)->create(
            $organizer,
            'Sortie sur validation',
            $category,
            participationMode: ParticipationMode::Validation,
        );
        $activityId = (string) $activity->getId();

        $candidate = $this->makeUser();
        $client->loginUser($candidate);
        $crawler = $client->request('GET', '/activites-privees/'.$activityId);
        $token = (string) $crawler->filter('form[action*="participer"] input[name="_token"]')->attr('value');
        $client->request('POST', '/activites-privees/'.$activityId.'/participer', ['_token' => $token]);

        [$participation] = $this->reloadParticipations($activityId, $candidate);
        self::assertSame(ParticipationStatus::Pending, $participation->getStatus());

        // Un tiers ne peut pas décider à la place de l'organisateur.
        $client->loginUser($this->makeUser());
        $crawler = $client->request('GET', '/activites-privees/'.$activityId);
        $decideToken = (string) $crawler->filter('input[name="_token"]')->attr('value');
        $client->request(
            'POST',
            sprintf('/compte/activites-privees/%s/participations/%s/decider', $activityId, $participation->getId()),
            ['decision' => 'accepter', '_token' => $decideToken],
        );
        self::assertResponseStatusCodeSame(403, 'Seul l\'organisateur doit pouvoir décider d\'une demande.');

        // L'organisateur, lui, peut.
        $client->loginUser($organizer);
        $crawler = $client->request('GET', '/activites-privees/'.$activityId);
        $decideToken = (string) $crawler->filter('form[action*="decider"] input[name="_token"]')->attr('value');
        $client->request(
            'POST',
            sprintf('/compte/activites-privees/%s/participations/%s/decider', $activityId, $participation->getId()),
            ['decision' => 'accepter', '_token' => $decideToken],
        );
        self::assertResponseRedirects();

        [$participation] = $this->reloadParticipations($activityId, $candidate);
        self::assertSame(ParticipationStatus::Accepted, $participation->getStatus());
    }

    /**
     * §12.4, §25.1 : le lieu exact ne doit jamais fuiter vers une demande
     * encore en attente, même en HTML brut.
     */
    public function testExactLocationNeverLeaksToAPendingParticipant(): void
    {
        $client = static::createClient();
        $category = $this->findOrMakeCategory();
        $organizer = $this->makeUser();

        static::getContainer()->get(PrivateActivityService::class)->create(
            $organizer,
            'Sortie avec adresse confidentielle',
            $category,
            city: 'Lyon',
            location: 'ADRESSE-SECRETE-42-RUE-TEST',
            showExactAddress: true,
            participationMode: ParticipationMode::Validation,
        );

        $activity = static::getContainer()->get(PrivateActivityRepository::class)->findByOrganizer($organizer)[0];
        $activityId = (string) $activity->getId();

        $client->loginUser($this->makeUser());
        $crawler = $client->request('GET', '/activites-privees/'.$activityId);
        self::assertResponseIsSuccessful();

        self::assertStringNotContainsString(
            'ADRESSE-SECRETE-42-RUE-TEST',
            (string) $client->getResponse()->getContent(),
            'Le lieu exact ne doit jamais être visible avant qu\'une participation soit acceptée.',
        );
    }

    /**
     * @return list<\App\PrivateActivity\Entity\Participation>
     */
    public function testTheCreationWizardPublishesACompleteActivity(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeUser());
        $category = $this->findOrMakeCategory();
        $title = 'Sortie assistant '.uniqid();
        $base = '/compte/activites-privees/creer/';
        $post = function (int $step, array $data) use ($client, $base): void {
            $crawler = $client->request('GET', $base.$step);
            $token = (string) $crawler->filter('form[data-ew-form] input[name="_token"]')->attr('value');
            $client->request('POST', $base.$step, ['_token' => $token, 'action' => 'next'] + $data);
        };

        $client->request('GET', '/compte/activites-privees/nouvelle?vierge=1');
        self::assertResponseRedirects($base.'1');

        $post(1, ['title' => 'Ab']);
        self::assertResponseStatusCodeSame(422);
        $post(1, ['title' => $title, 'category' => $category->getSlug(), 'description' => 'Une sortie conviviale entre membres, ouverte à tous.']);
        self::assertResponseRedirects($base.'2');

        // Une date passée est refusée.
        $post(2, ['date' => '2020-01-01', 'start_time' => '10:00', 'address' => 'Place Bellecour, Lyon', 'city' => 'Lyon']);
        self::assertResponseStatusCodeSame(422);
        $day = (new \DateTimeImmutable('+6 days'))->format('Y-m-d');
        $post(2, ['date' => $day, 'start_time' => '10:00', 'end_time' => '12:30', 'address' => 'Place Bellecour, Lyon', 'city' => 'Lyon', 'postal_code' => '69002', 'lat' => '45.7578', 'lng' => '4.8320', 'show_exact' => '1']);
        self::assertResponseRedirects($base.'3');
        $post(3, ['to_bring' => 'De l’eau']);
        self::assertResponseRedirects($base.'4');
        $post(4, ['min' => '5', 'max' => '3', 'mode' => 'automatic', 'visibility' => 'public']);
        self::assertResponseStatusCodeSame(422);
        $post(4, ['min' => '2', 'max' => '8', 'mode' => 'automatic', 'visibility' => 'public']);
        self::assertResponseRedirects($base.'5');
        $post(5, []);
        self::assertResponseStatusCodeSame(422);
        $post(5, ['accept_terms' => '1']);
        self::assertResponseRedirects();

        $activity = static::getContainer()->get(PrivateActivityRepository::class)->findOneBy(['title' => $title]);
        self::assertNotNull($activity);
        self::assertSame($day.' 10:00', $activity->getScheduledAt()?->format('Y-m-d H:i'));
        self::assertSame('12:30', $activity->getEndsAt()?->format('H:i'));
        self::assertSame(8, $activity->getMaxParticipants());
        self::assertSame('45.7578000', $activity->getLatitude());

        // Visible dans la liste publique.
        $client->request('GET', '/activites-privees');
        self::assertSelectorTextContains('.pa-grid', $title);
    }

    private function reloadParticipations(string $activityId, User ...$users): array
    {
        $activity = $this->reloadActivity($activityId);
        $repository = static::getContainer()->get(ParticipationRepository::class);

        return array_map(
            fn (User $user): \App\PrivateActivity\Entity\Participation => $repository->findOneByActivityAndParticipant($activity, $user)
                ?? self::fail('Participation introuvable pour '.$user->getEmail()),
            $users,
        );
    }

    private function reloadActivity(string $id): PrivateActivity
    {
        $activity = static::getContainer()->get(PrivateActivityRepository::class)->find(Ulid::fromString($id));
        self::assertNotNull($activity);

        return $activity;
    }

    private function findOrMakeCategory(): Category
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $category = $entityManager->getRepository(Category::class)->findOneBy([]);
        self::assertNotNull($category, 'Aucune catégorie en base : les fixtures ont-elles été chargées ?');

        return $category;
    }

    private function makeUser(): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())
            ->setEmail(sprintf('activite-privee-%s@example.com', uniqid()))
            ->setFirstName('Test')
            ->setLastName('Membre')
            ->setStatus(UserStatus::Active);
        $user->setPassword('peu-importe');

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
