<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Event\Entity\Event;
use App\Event\Entity\EventCategory;
use App\Event\Entity\EventRegistration;
use App\Event\Service\EventDraftService;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class EventAdministrationTest extends WebTestCase
{
    public function testAdminCanOpenAllEventScreensAndCreateAnEventForAClient(): void
    {
        $client = static::createClient();
        $admin = $this->user(['ROLE_ADMIN']);
        $organizer = $this->user();
        $client->loginUser($admin);
        foreach (['/admin/event', '/admin/event-category', '/admin/event-registration'] as $url) {
            $client->request('GET', $url);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('nav', 'Événements');
        }
        $crawler = $client->request('GET', '/admin/event/new');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('input[type="file"][required]');
        $title = 'Événement admin '.uniqid();
        $form = $crawler->filter('form[name="Event"]')->form();
        $form['Event[title]'] = $title;
        $form['Event[slug]'] = 'admin-'.uniqid();
        $form['Event[organizer]'] = (string) $organizer->getId();
        $form['Event[startsAt]'] = '2027-01-15T10:00';
        $form['Event[status]'] = 'published';
        $form['Event[locationType]'] = 'tbd';
        $client->submit($form);
        self::assertResponseRedirects();
        $event = $this->em()->getRepository(Event::class)->findOneBy(['title' => $title]);
        self::assertNotNull($event);
        self::assertSame('published', $event->getStatus());
        self::assertSame(1, $event->getParticipantsCount());
        self::assertFalse($event->isPrivate());
        self::assertSame((string) $organizer->getId(), (string) $event->getOrganizer()->getId());
        $client->request('GET', '/evenements/detail/'.$event->getSlug());
        self::assertResponseIsSuccessful();
    }

    public function testAdminCanSuspendAnotherClientsEventAndTheOwnerCannotRepublishIt(): void
    {
        $client = static::createClient();
        $admin = $this->user(['ROLE_ADMIN']);
        $owner = $this->user();
        $event = $this->event($owner);
        $id = (string) $event->getId();
        $client->loginUser($admin);
        $crawler = $client->request('GET', '/admin/event/'.$id);
        self::assertResponseIsSuccessful();
        $client->click($crawler->filter('[data-action-name="participants"]')->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', $event->getTitle());
        $crawler = $client->request('GET', '/admin/event/'.$id.'/edit');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[name="Event"]')->form();
        $form['Event[status]'] = 'suspended';
        $client->submit($form);
        self::assertResponseRedirects();
        $this->em()->clear();
        $event = $this->em()->find(Event::class, $id);
        self::assertSame('suspended', $event->getStatus());
        self::assertNull($event->getPublishAt());
        $client->loginUser($event->getOrganizer());
        $client->request('GET', '/compte/mes-evenements');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Suspendu par l’administration');
        self::assertSelectorNotExists('a[href="/evenements/creer/reprendre/'.$event->getSlug().'"]');
        $client->getCookieJar()->clear();
        $client->request('GET', '/evenements/detail/'.$event->getSlug());
        self::assertResponseStatusCodeSame(404);

        $event = $this->em()->find(Event::class, $id);
        $draft = static::getContainer()->get(EventDraftService::class);
        $session = new Session(new MockArraySessionStorage());
        $draft->load($session, $event);
        $this->expectException(AccessDeniedHttpException::class);
        $draft->persist($session, $event->getOrganizer(), 'now');
    }

    public function testAdminCanPreviewPrivateEventsAndClientCannotUseAdminRoutes(): void
    {
        $client = static::createClient();
        $owner = $this->user();
        $event = $this->event($owner)->setVisibility('private')->setPrivate(true)->setStatus('draft');
        $this->em()->flush();
        $client->loginUser($this->user(['ROLE_ADMIN']));
        $client->request('GET', '/evenements/detail/'.$event->getSlug());
        self::assertResponseIsSuccessful();
        $client->loginUser($owner);
        $client->request('GET', '/admin/event/'.(string) $event->getId().'/edit');
        self::assertResponseRedirects('/compte/tableau-de-bord');
    }

    public function testRemovingAnInscriptionRecountsAndPromotesTheWaitingClient(): void
    {
        $client = static::createClient();
        $admin = $this->user(['ROLE_ADMIN']);
        $owner = $this->user();
        $event = $this->event($owner)->setCapacity(2)->setWaitlist(true)->setParticipantsCount(2);
        $going = new EventRegistration($event, $this->user());
        $waiting = new EventRegistration($event, $this->user(), EventRegistration::WAITLIST);
        $this->em()->persist($going);
        $this->em()->persist($waiting);
        $this->em()->flush();
        $eventId = (string) $event->getId();
        $waitingId = (string) $waiting->getId();
        $goingId = (string) $going->getId();
        $client->loginUser($admin);
        $crawler = $client->request('GET', '/admin/event-registration/'.$goingId);
        self::assertResponseIsSuccessful();
        $button = $crawler->filter('[data-action-name="delete"]')->first();
        $token = $crawler->filter('#action-confirmation-form input[name="token"]')->attr('value');
        $client->request('POST', $button->attr('formaction'), ['token' => $token]);
        self::assertResponseRedirects();
        $this->em()->clear();
        self::assertNull($this->em()->find(EventRegistration::class, $goingId));
        self::assertSame(EventRegistration::GOING, $this->em()->find(EventRegistration::class, $waitingId)->getStatus());
        self::assertSame(2, $this->em()->find(Event::class, $eventId)->getParticipantsCount());
    }

    public function testFullEventCannotConfirmAnotherParticipant(): void
    {
        $client = static::createClient();
        $admin = $this->user(['ROLE_ADMIN']);
        $event = $this->event($this->user())->setCapacity(1);
        $waiting = new EventRegistration($event, $this->user(), EventRegistration::WAITLIST);
        $this->em()->persist($waiting);
        $this->em()->flush();
        $id = (string) $waiting->getId();
        $client->loginUser($admin);
        $crawler = $client->request('GET', '/admin/event-registration/'.$id.'/edit');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[name="EventRegistration"]')->form();
        $form['EventRegistration[status]'] = EventRegistration::GOING;
        $client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'L’événement est complet');
        $this->em()->clear();
        self::assertSame(EventRegistration::WAITLIST, $this->em()->find(EventRegistration::class, $id)->getStatus());
    }

    public function testAdminCanAddAClientToAPrivateEventAndRejectDuplicateEnrollment(): void
    {
        $client = static::createClient();
        $admin = $this->user(['ROLE_ADMIN']);
        $participant = $this->user();
        $event = $this->event($this->user())->setPrivate(true)->setVisibility('private');
        $this->em()->flush();
        $eventId = (string) $event->getId();
        $participantId = (string) $participant->getId();
        $client->loginUser($admin);
        $crawler = $client->request('GET', '/admin/event-registration/new');
        self::assertResponseIsSuccessful();
        $client->submit($crawler->selectButton('Ajouter l’inscription')->form([
            'form[event]' => $eventId, 'form[user]' => $participantId, 'form[status]' => EventRegistration::GOING,
        ]));
        self::assertResponseRedirects();
        $this->em()->clear();
        self::assertSame(2, $this->em()->find(Event::class, $eventId)->getParticipantsCount());
        $crawler = $client->request('GET', '/admin/event-registration/new');
        $client->submit($crawler->selectButton('Ajouter l’inscription')->form([
            'form[event]' => $eventId, 'form[user]' => $participantId, 'form[status]' => EventRegistration::GOING,
        ]));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Ce membre est déjà inscrit');
        $client->loginUser($this->em()->find(User::class, $participantId));
        $client->request('GET', '/evenements/detail/'.$event->getSlug());
        self::assertResponseIsSuccessful();
    }

    public function testDeletingAnEventRemovesItsRegistrations(): void
    {
        $client = static::createClient();
        $admin = $this->user(['ROLE_ADMIN']);
        $event = $this->event($this->user());
        $id = (string) $event->getId();
        $client->loginUser($admin);
        $crawler = $client->request('GET', '/admin/event/'.$id);
        self::assertResponseIsSuccessful();
        $button = $crawler->filter('[data-action-name="delete"]')->first();
        $token = $crawler->filter('#action-confirmation-form input[name="token"]')->attr('value');
        $client->request('POST', $button->attr('formaction'), ['token' => $token]);
        self::assertResponseRedirects();
        $this->em()->clear();
        self::assertNull($this->em()->find(Event::class, $id));
        self::assertSame(0, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM event_registration WHERE event_id = ?', [\Symfony\Component\Uid\Ulid::fromString($id)->toRfc4122()]));
    }

    public function testAdminCanUploadCoverAndGalleryAndKeepThemWhenEditing(): void
    {
        $client = static::createClient();
        $admin = $this->user(['ROLE_ADMIN']);
        $event = $this->event($this->user());
        $id = (string) $event->getId();
        $client->loginUser($admin);
        $temp = tempnam(sys_get_temp_dir(), 'admin-event-').'.png';
        $image = imagecreatetruecolor(24, 24);
        imagepng($image, $temp);
        $galleryFile = tempnam(sys_get_temp_dir(), 'admin-gallery-').'.png';
        imagepng($image, $galleryFile);
        $saved = [];
        try {
            $crawler = $client->request('GET', '/admin/event/'.$id.'/edit');
            $form = $crawler->filter('form[name="Event"]')->form();
            $form['Event[imagePath][file]']->upload($temp);
            $form['Event[gallery][file]'][0]->upload($galleryFile);
            $client->submit($form);
            self::assertResponseRedirects();
            $this->em()->clear();
            $event = $this->em()->find(Event::class, $id);
            self::assertCount(1, $event->getGallery());
            $saved = [$event->getImagePath(), ...$event->getGallery()];
            foreach ($saved as $path) {
                self::assertStringStartsWith('uploads/', $path);
                self::assertFileExists(\dirname(__DIR__, 2).'/public/'.$path);
            }
            $crawler = $client->request('GET', '/admin/event/'.$id.'/edit');
            $form = $crawler->filter('form[name="Event"]')->form();
            $form['Event[title]'] = 'Titre corrigé '.uniqid();
            $client->submit($form);
            self::assertResponseRedirects();
            $this->em()->clear();
            $event = $this->em()->find(Event::class, $id);
            self::assertSame($saved, [$event->getImagePath(), ...$event->getGallery()]);
        } finally {
            @unlink($temp);
            @unlink($galleryFile);
            foreach ($saved as $path) {
                @unlink(\dirname(__DIR__, 2).'/public/'.$path);
            }
        }
    }

    public function testAdminCanCreateEventCategories(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user(['ROLE_ADMIN']));
        $crawler = $client->request('GET', '/admin/event-category/new');
        self::assertResponseIsSuccessful();
        $name = 'Catégorie événement '.uniqid();
        $form = $crawler->filter('form[name="EventCategory"]')->form();
        $form['EventCategory[name]'] = $name;
        $form['EventCategory[slug]'] = 'event-category-'.uniqid();
        $form['EventCategory[color]'] = 'violet';
        $client->submit($form);
        self::assertResponseRedirects();
        $category = $this->em()->getRepository(EventCategory::class)->findOneBy(['name' => $name]);
        self::assertNotNull($category);
        self::assertSame('violet', $category->getColor());
    }

    public function testInvalidScheduleDatesAndCapacityDoNotChangeTheEvent(): void
    {
        $client = static::createClient();
        $event = $this->event($this->user());
        $id = (string) $event->getId();
        $client->loginUser($this->user(['ROLE_ADMIN']));
        $crawler = $client->request('GET', '/admin/event/'.$id.'/edit');
        $form = $crawler->filter('form[name="Event"]')->form();
        $form['Event[status]'] = 'scheduled';
        $form['Event[publishAt]'] = '2020-01-01T10:00';
        $form['Event[endsAt]'] = '2020-01-01T11:00';
        $form['Event[capacity]'] = '0';
        $client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Choisissez une date de publication future');
        self::assertSelectorTextContains('body', 'La fin doit être postérieure');
        $this->em()->clear();
        self::assertSame('published', $this->em()->find(Event::class, $id)->getStatus());
    }

    public function testProvidersCannotManageEventsOrBeSelectedAsOrganizersOrParticipants(): void
    {
        $client = static::createClient();
        $provider = $this->user(['ROLE_PROVIDER']);
        $admin = $this->user(['ROLE_ADMIN']);
        $providerId = (string) $provider->getId();
        $client->loginUser($provider);
        foreach (['/admin/event', '/admin/event-category', '/admin/event-registration'] as $url) {
            $client->request('GET', $url);
            self::assertResponseRedirects('/pro/tableau-de-bord');
        }
        $client->loginUser($admin);
        $client->request('GET', '/admin/event/new');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('select[name="Event[organizer]"] option[value="'.$providerId.'"]');
        $client->request('GET', '/admin/event-registration/new');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('select[name="form[user]"] option[value="'.$providerId.'"]');
    }

    private function user(array $roles = []): User
    {
        $user = (new User())->setEmail(uniqid('event-admin-').'@example.test')->setFirstName('Admin')->setLastName('Test')->setPassword('fixture-unused')->setStatus(UserStatus::Active)->setRoles($roles);
        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }

    private function event(User $organizer): Event
    {
        $event = (new Event())->setTitle('Événement géré '.uniqid())->setSlug('event-admin-'.uniqid())->setOrganizer($organizer)->setStartsAt(new \DateTimeImmutable('+2 months'))->setLocation('Paris')->setParticipantsCount(1);
        $this->em()->persist($event);
        $this->em()->persist(new EventRegistration($event, $organizer));
        $this->em()->flush();

        return $event;
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
