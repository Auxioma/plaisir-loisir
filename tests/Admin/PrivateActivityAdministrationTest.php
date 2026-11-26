<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Admin\Entity\AuditLog;
use App\Catalog\Entity\Category;
use App\Notification\Entity\Notification;
use App\PrivateActivity\Entity\MemberReview;
use App\PrivateActivity\Entity\Participation;
use App\PrivateActivity\Entity\PrivateActivity;
use App\PrivateActivity\Enum\ParticipationMode;
use App\PrivateActivity\Enum\ParticipationStatus;
use App\PrivateActivity\Enum\PrivateActivityStatus;
use App\PrivateActivity\Enum\PrivateActivityVisibility;
use App\PrivateActivity\Service\PrivateActivityService;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Gestion des activités gratuites depuis le back-office (26/11).
 */
final class PrivateActivityAdministrationTest extends WebTestCase
{
    public function testAdminOpensEveryFreeActivityScreen(): void
    {
        $client = static::createClient();
        $activity = $this->activity($this->user());
        $this->em()->persist((new MemberReview())->setPrivateActivity($activity)->setAuthor($this->user())->rate([]));
        $this->em()->flush();
        $client->loginUser($this->user(['ROLE_ADMIN']));

        foreach (['/admin/private-activity', '/admin/private-activity/new', '/admin/participation', '/admin/participation/new', '/admin/album-photo', '/admin/member-review'] as $url) {
            $client->request('GET', $url);
            self::assertResponseIsSuccessful($url);
            self::assertSelectorTextContains('nav', 'Activités gratuites');
        }
        $id = (string) $activity->getId();
        foreach (['/admin/private-activity/'.$id, '/admin/private-activity/'.$id.'/edit'] as $url) {
            $client->request('GET', $url);
            self::assertResponseIsSuccessful($url);
        }
    }

    public function testAMemberCannotOpenTheBackOffice(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user());
        $client->request('GET', '/admin/private-activity');
        self::assertResponseRedirects('/compte/tableau-de-bord');
    }

    public function testAdminCreatesAFreeActivityForAMember(): void
    {
        $client = static::createClient();
        $organizer = $this->user();
        $client->loginUser($this->user(['ROLE_ADMIN']));
        $crawler = $client->request('GET', '/admin/private-activity/new');
        $title = 'Sortie créée par l’équipe '.uniqid();
        $form = $crawler->filter('form[name="PrivateActivity"]')->form();
        $form['PrivateActivity[title]'] = $title;
        $form['PrivateActivity[organizer]'] = (string) $organizer->getId();
        $form['PrivateActivity[category]'] = (string) $this->category()->getId();
        $form['PrivateActivity[status]'] = 'open';
        $form['PrivateActivity[visibility]'] = 'public';
        $form['PrivateActivity[participationMode]'] = 'automatic';
        $form['PrivateActivity[scheduledAt]'] = (new \DateTimeImmutable('+1 month'))->format('Y-m-d\TH:i');
        $form['PrivateActivity[city]'] = 'Lyon';
        $client->submit($form);
        self::assertResponseRedirects();

        $activity = $this->em()->getRepository(PrivateActivity::class)->findOneBy(['title' => $title]);
        self::assertNotNull($activity);
        self::assertSame(PrivateActivityStatus::Open, $activity->getStatus());
        self::assertSame((string) $organizer->getId(), (string) $activity->getOrganizer()?->getId());

        $client->getCookieJar()->clear();
        $client->request('GET', '/activites-privees');
        self::assertSelectorTextContains('body', $title);
    }

    public function testAProviderCannotBeChosenAsOrganizer(): void
    {
        $client = static::createClient();
        $provider = $this->user(['ROLE_PROVIDER']);
        $client->loginUser($this->user(['ROLE_ADMIN']));
        $crawler = $client->request('GET', '/admin/private-activity/new');
        self::assertCount(0, $crawler->filter(sprintf('select[name="PrivateActivity[organizer]"] option[value="%s"]', (string) $provider->getId())));
    }

    public function testSuspendingHidesTheActivityAndLocksItsOrganizerOut(): void
    {
        $client = static::createClient();
        $organizer = $this->user();
        $participant = $this->user();
        $activity = $this->activity($organizer);
        static::getContainer()->get(PrivateActivityService::class)->requestParticipation($activity, $participant);
        $id = (string) $activity->getId();
        $title = $activity->getTitle();

        $client->loginUser($this->user(['ROLE_ADMIN']));
        $crawler = $client->request('GET', '/admin/private-activity/'.$id);
        $this->submitAction($client, $crawler, 'suspend');
        self::assertResponseRedirects();

        $this->em()->clear();
        self::assertSame(PrivateActivityStatus::Suspended, $this->reload($id)->getStatus());
        self::assertNotNull($this->lastNotification($organizer, 'Activité suspendue'));
        self::assertNotNull($this->lastNotification($participant, 'Activité suspendue'));

        // Hors du site : listes et fiche.
        $client->getCookieJar()->clear();
        $client->request('GET', '/activites-privees');
        self::assertSelectorTextNotContains('body', $title);
        $client->request('GET', '/activites-privees/'.$id);
        self::assertResponseStatusCodeSame(302);

        // L'organisateur la voit encore, mais ne peut plus la modifier.
        $client->loginUser($this->em()->find(User::class, $organizer->getId()));
        $client->request('GET', '/activites-privees/'.$id);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Suspendue par l’équipe TrouveMoi');
        $client->request('GET', '/compte/activites-privees/'.$id.'/reprendre');
        self::assertResponseRedirects('/activites-privees/'.$id);

        // L'équipe la remet en ligne.
        $client->loginUser($this->user(['ROLE_ADMIN']));
        $crawler = $client->request('GET', '/admin/private-activity/'.$id);
        $this->submitAction($client, $crawler, 'reinstate');
        $this->em()->clear();
        self::assertSame(PrivateActivityStatus::Open, $this->reload($id)->getStatus());
    }

    public function testAdminSeesAPrivateActivityOnTheSite(): void
    {
        $client = static::createClient();
        $activity = $this->activity($this->user(), PrivateActivityVisibility::Private);
        $client->loginUser($this->user(['ROLE_ADMIN']));
        $client->request('GET', '/activites-privees/'.(string) $activity->getId());
        self::assertResponseIsSuccessful();
    }

    public function testRaisingCapacityPromotesTheWaitingList(): void
    {
        $client = static::createClient();
        $activity = $this->activity($this->user(), max: 1);
        $service = static::getContainer()->get(PrivateActivityService::class);
        $service->requestParticipation($activity, $this->user());
        $waiting = $service->requestParticipation($activity, $this->user());
        self::assertSame(ParticipationStatus::WaitingList, $waiting->getStatus());
        self::assertSame(PrivateActivityStatus::Full, $activity->getStatus());
        $id = (string) $activity->getId();
        $waitingId = (string) $waiting->getId();

        $client->loginUser($this->user(['ROLE_ADMIN']));
        $crawler = $client->request('GET', '/admin/private-activity/'.$id.'/edit');
        $form = $crawler->filter('form[name="PrivateActivity"]')->form();
        $form['PrivateActivity[maxParticipants]'] = '3';
        $client->submit($form);
        self::assertResponseRedirects();

        $this->em()->clear();
        self::assertSame(ParticipationStatus::Accepted, $this->em()->find(Participation::class, $waitingId)?->getStatus());
        self::assertSame(PrivateActivityStatus::Open, $this->reload($id)->getStatus());
    }

    public function testCapacityCannotDropBelowConfirmedParticipants(): void
    {
        $client = static::createClient();
        $activity = $this->activity($this->user(), max: 3);
        $service = static::getContainer()->get(PrivateActivityService::class);
        $service->requestParticipation($activity, $this->user());
        $service->requestParticipation($activity, $this->user());

        $client->loginUser($this->user(['ROLE_ADMIN']));
        $crawler = $client->request('GET', '/admin/private-activity/'.(string) $activity->getId().'/edit');
        $form = $crawler->filter('form[name="PrivateActivity"]')->form();
        $form['PrivateActivity[maxParticipants]'] = '1';
        $client->submit($form);
        self::assertResponseStatusCodeSame(422);
    }

    public function testRemovingAConfirmedParticipantPromotesTheNextOne(): void
    {
        $client = static::createClient();
        $activity = $this->activity($this->user(), max: 1);
        $service = static::getContainer()->get(PrivateActivityService::class);
        $confirmed = $service->requestParticipation($activity, $this->user());
        $waiting = $service->requestParticipation($activity, $this->user());
        $confirmedId = (string) $confirmed->getId();
        $waitingId = (string) $waiting->getId();

        $client->loginUser($this->user(['ROLE_ADMIN']));
        $crawler = $client->request('GET', '/admin/participation/'.$confirmedId);
        self::assertResponseIsSuccessful();
        $token = $crawler->filter('#action-confirmation-form input[name="token"]')->attr('value');
        $client->request('POST', $crawler->filter('[data-action-name="delete"]')->first()->attr('formaction'), ['token' => $token]);
        self::assertResponseRedirects();

        $this->em()->clear();
        self::assertNull($this->em()->find(Participation::class, $confirmedId));
        self::assertSame(ParticipationStatus::Accepted, $this->em()->find(Participation::class, $waitingId)?->getStatus());
    }

    public function testAdminEnrolsAMemberAndRefusesWhenFull(): void
    {
        $client = static::createClient();
        $activity = $this->activity($this->user(), max: 1);
        $member = $this->user();
        $latecomer = $this->user();
        $client->loginUser($this->user(['ROLE_ADMIN']));

        $crawler = $client->request('GET', '/admin/participation/new');
        $client->submit($crawler->selectButton('Inscrire le membre')->form([
            'form[activity]' => (string) $activity->getId(),
            'form[member]' => (string) $member->getId(),
            'form[status]' => ParticipationStatus::Accepted->value,
        ]));
        self::assertResponseRedirects();

        $crawler = $client->request('GET', '/admin/participation/new');
        $client->submit($crawler->selectButton('Inscrire le membre')->form([
            'form[activity]' => (string) $activity->getId(),
            'form[member]' => (string) $latecomer->getId(),
            'form[status]' => ParticipationStatus::Accepted->value,
        ]));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'L’activité est complète');
    }

    public function testDeletingAnActivityIsAuditedAndRemovesItsReviews(): void
    {
        $client = static::createClient();
        $organizer = $this->user();
        $activity = $this->activity($organizer);
        $review = (new MemberReview())->setPrivateActivity($activity)->setAuthor($this->user())->rate([]);
        $this->em()->persist($review);
        $this->em()->flush();
        $id = (string) $activity->getId();
        $reviewId = (string) $review->getId();

        $client->loginUser($this->user(['ROLE_ADMIN']));
        $crawler = $client->request('GET', '/admin/private-activity/'.$id);
        $token = $crawler->filter('#action-confirmation-form input[name="token"]')->attr('value');
        $client->request('POST', $crawler->filter('[data-action-name="delete"]')->first()->attr('formaction'), ['token' => $token]);
        self::assertResponseRedirects();

        $this->em()->clear();
        self::assertNull($this->em()->find(PrivateActivity::class, $id));
        self::assertNull($this->em()->find(MemberReview::class, $reviewId));
        self::assertNotNull($this->em()->getRepository(AuditLog::class)->findOneBy(['targetType' => 'private_activity', 'targetLabel' => $activity->getTitle()]));
        self::assertNotNull($this->lastNotification($organizer, 'Activité supprimée'));
    }

    /** Actions « Suspendre » / « Remettre en ligne » : un formulaire POST par action. */
    private function submitAction(KernelBrowser $client, Crawler $crawler, string $action): void
    {
        $form = $crawler->filter('form.action-'.$action);
        self::assertGreaterThan(0, $form->count(), 'Action introuvable : '.$action);
        $client->submit($form->first()->form());
    }

    private function lastNotification(User $user, string $title): ?Notification
    {
        return $this->em()->getRepository(Notification::class)->findOneBy(['recipient' => $user->getId(), 'title' => $title]);
    }

    private function reload(string $id): PrivateActivity
    {
        $activity = $this->em()->find(PrivateActivity::class, $id);
        self::assertNotNull($activity);

        return $activity;
    }

    private function activity(User $organizer, PrivateActivityVisibility $visibility = PrivateActivityVisibility::Public, ?int $max = null): PrivateActivity
    {
        return static::getContainer()->get(PrivateActivityService::class)->create(
            $organizer,
            'Sortie gratuite gérée '.uniqid(),
            $this->category(),
            scheduledAt: new \DateTimeImmutable('+1 month'),
            city: 'Annecy',
            visibility: $visibility,
            participationMode: ParticipationMode::Automatic,
            maxParticipants: $max,
        );
    }

    private function category(): Category
    {
        $category = $this->em()->getRepository(Category::class)->findOneBy([]);
        self::assertNotNull($category, 'Fixtures non chargées.');

        return $category;
    }

    /** @param list<string> $roles */
    private function user(array $roles = []): User
    {
        $user = (new User())->setEmail(uniqid('free-admin-').'@example.test')->setFirstName('Membre')->setLastName('Test')
            ->setPassword('fixture-unused')->setStatus(UserStatus::Active)->setRoles($roles);
        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
