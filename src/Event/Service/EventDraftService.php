<?php

declare(strict_types=1);

namespace App\Event\Service;

use App\Event\Entity\Event;
use App\Event\Entity\EventInvitation;
use App\Event\Entity\EventRegistration;
use App\Event\Repository\EventCategoryRepository;
use App\Event\Repository\EventRepository;
use App\Event\StaticEventWizard;
use App\Notification\Enum\NotificationCategory;
use App\Notification\Service\NotificationService;
use App\Shared\Service\PublicImageStorage;
use App\User\Entity\User;
use App\User\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Le brouillon d'événement de l'assistant « Créer un événement » (maquettes
 * creation_evenements, 04/10).
 *
 * La saisie vit en session d'une étape à l'autre ; chaque étape est
 * VALIDÉE côté serveur avant de passer à la suivante (champs requis,
 * longueurs, dates cohérentes, image de couverture…). « Enregistrer en
 * brouillon » crée un Event au statut « draft » (reprise possible depuis
 * « Mes événements ») ; « Publier » le rend visible maintenant ou à une date
 * programmée.
 */
final class EventDraftService
{
    private const SESSION_KEY = 'event_draft';
    public const STEPS = 8;

    /** Champs envoyés par chaque étape (les cases à cocher sont listées à part). */
    private const STEP_FIELDS = [
        1 => ['title', 'type', 'summary', 'description', 'visibility'],
        2 => ['date_mode', 'recurrence', 'start_date', 'start_time', 'end_date', 'end_time', 'timezone', 'reminder', 'arrival'],
        3 => ['location_type', 'address', 'city', 'postal_code', 'lat', 'lng', 'online_url', 'instructions', 'meeting_point'],
        4 => ['category'],
        5 => ['description'],
        6 => ['visibility', 'capacity', 'reminder'],
        7 => ['invite_emails'],
        8 => ['publish_mode', 'publish_date', 'publish_time'],
    ];

    private const STEP_CHECKBOXES = [
        2 => ['no_end', 'in_calendar', 'all_day'],
        6 => ['registration', 'waitlist', 'show_participants', 'allow_invites', 'comments', 'email_updates'],
        8 => ['accept_terms'],
    ];

    private const DEFAULTS = [
        'visibility' => 'public', 'date_mode' => 'precise', 'timezone' => 'Europe/Paris', 'reminder' => '24h',
        'in_calendar' => '1', 'all_day' => '', 'no_end' => '', 'location_type' => 'precise', 'registration' => '1',
        'capacity' => '50', 'waitlist' => '', 'show_participants' => '1', 'allow_invites' => '1', 'comments' => '',
        'email_updates' => '1', 'publish_mode' => 'now', 'gallery' => [], 'invites' => [], 'done' => [],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventRepository $events,
        private readonly EventCategoryRepository $categories,
        private readonly UserRepository $users,
        private readonly PublicImageStorage $images,
        private readonly NotificationService $notifications,
        private readonly SluggerInterface $slugger,
    ) {
    }

    /** @return array<string, mixed> */
    public function current(SessionInterface $session): array
    {
        /** @var array<string, mixed> $draft */
        $draft = $session->get(self::SESSION_KEY, []);

        return $draft + self::DEFAULTS;
    }

    public function clear(SessionInterface $session): void
    {
        $session->remove(self::SESSION_KEY);
    }

    /** Étapes déjà validées (le stepper n'ouvre que celles-là). */
    public function maxReachable(SessionInterface $session): int
    {
        $done = (array) $this->current($session)['done'];

        for ($step = 1; $step <= self::STEPS; ++$step) {
            if (!\in_array($step, $done, true)) {
                return $step;
            }
        }

        return self::STEPS;
    }

    /**
     * Enregistre ce que l'étape envoie, puis la valide.
     *
     * @return array<string, string> erreurs par champ (vide = étape valide)
     */
    public function submitStep(SessionInterface $session, int $step, Request $request): array
    {
        $draft = $this->current($session);
        $errors = [];

        foreach (self::STEP_FIELDS[$step] ?? [] as $field) {
            if ($request->request->has($field)) {
                $draft[$field] = trim((string) $request->request->get($field));
            }
        }
        foreach (self::STEP_CHECKBOXES[$step] ?? [] as $field) {
            $draft[$field] = $request->request->getBoolean($field) ? '1' : '';
        }

        if (5 === $step) {
            $errors += $this->handleImages($draft, $request);
        }
        if (7 === $step) {
            $draft['invites'] = array_values(array_unique(array_filter(array_map('strval', $request->request->all('invites')), static fn (string $id): bool => Ulid::isValid($id))));
        }

        $errors += $this->validateStep($step, $draft);

        $done = array_values(array_diff((array) $draft['done'], [$step]));
        if ([] === $errors) {
            $done[] = $step;
        }
        $draft['done'] = $done;
        $session->set(self::SESSION_KEY, $draft);

        return $errors;
    }

    /**
     * @param array<string, mixed> $d
     *
     * @return array<string, string>
     */
    public function validateStep(int $step, array $d): array
    {
        $e = [];
        $len = static fn (string $key): int => mb_strlen((string) ($d[$key] ?? ''));

        switch ($step) {
            case 1:
                if ($len('title') < 3) {
                    $e['title'] = 'Donnez un titre à votre événement (3 caractères minimum).';
                } elseif ($len('title') > 120) {
                    $e['title'] = 'Le titre ne doit pas dépasser 120 caractères.';
                }
                if (!\array_key_exists((string) ($d['type'] ?? ''), StaticEventWizard::types())) {
                    $e['type'] = "Choisissez le type d'événement.";
                }
                if ($len('summary') < 10) {
                    $e['summary'] = 'Décrivez votre événement en quelques mots (10 caractères minimum).';
                } elseif ($len('summary') > 120) {
                    $e['summary'] = 'La courte description ne doit pas dépasser 120 caractères.';
                }
                if ($len('description') > 3000) {
                    $e['description'] = 'La description ne doit pas dépasser 3000 caractères.';
                }
                if (!\in_array($d['visibility'] ?? '', ['private', 'public', 'group'], true)) {
                    $e['visibility'] = 'Choisissez la visibilité de votre événement.';
                }
                break;

            case 2:
                if (!\in_array($d['date_mode'] ?? '', ['precise', 'periode', 'recurrent'], true)) {
                    $e['date_mode'] = 'Choisissez le type de date.';
                }
                $allDay = '1' === ($d['all_day'] ?? '');
                $start = $this->dateTime($d['start_date'] ?? '', $allDay ? '00:00' : ($d['start_time'] ?? ''));
                if (null === $this->dateTime($d['start_date'] ?? '', '00:00')) {
                    $e['start_date'] = 'Indiquez la date de début.';
                } elseif (!$allDay && null === $start) {
                    $e['start_time'] = "Indiquez l'heure de début.";
                } elseif (null !== $start && $start < new \DateTimeImmutable('today')) {
                    $e['start_date'] = 'La date de début doit être aujourd’hui ou plus tard.';
                }
                if ('periode' === ($d['date_mode'] ?? '') && '' === (string) ($d['end_date'] ?? '')) {
                    $e['end_date'] = 'Une période demande une date de fin.';
                }
                if ('recurrent' === ($d['date_mode'] ?? '') && !\in_array($d['recurrence'] ?? '', ['weekly', 'monthly'], true)) {
                    $e['recurrence'] = 'Choisissez la fréquence de répétition.';
                }
                if (null !== $start && '1' !== ($d['no_end'] ?? '') && !$allDay) {
                    $end = $this->dateTime(($d['end_date'] ?? '') ?: (string) $d['start_date'], $d['end_time'] ?? '');
                    if ('' === (string) ($d['end_time'] ?? '')) {
                        $e['end_time'] = "Indiquez l'heure de fin ou cochez « Pas d'heure de fin ».";
                    } elseif (null === $end || $end <= $start) {
                        $e['end_time'] = 'La fin doit être après le début.';
                    }
                } elseif (null !== $start && '' !== (string) ($d['end_date'] ?? '')) {
                    $endDay = $this->dateTime((string) $d['end_date'], '23:59');
                    if (null === $endDay || $endDay < $start) {
                        $e['end_date'] = 'La date de fin doit suivre la date de début.';
                    }
                }
                if (!\array_key_exists((string) ($d['timezone'] ?? ''), StaticEventWizard::timezones())) {
                    $e['timezone'] = 'Choisissez un fuseau horaire.';
                }
                if ('' !== (string) ($d['arrival'] ?? '') && !preg_match('/^\d{2}:\d{2}$/', (string) $d['arrival'])) {
                    $e['arrival'] = "Heure d'arrivée invalide.";
                }
                break;

            case 3:
                $type = (string) ($d['location_type'] ?? '');
                if (!\in_array($type, ['precise', 'approx', 'online', 'tbd'], true)) {
                    $e['location_type'] = 'Choisissez le type de lieu.';
                } elseif (\in_array($type, ['precise', 'approx'], true) && $len('address') < 3) {
                    $e['address'] = 'precise' === $type ? "Indiquez l'adresse ou le lieu exact." : 'Indiquez la zone ou le quartier.';
                } elseif ('online' === $type && false === filter_var($d['online_url'] ?? '', \FILTER_VALIDATE_URL)) {
                    $e['online_url'] = 'Indiquez le lien de connexion (https://…).';
                }
                if ($len('instructions') > 200) {
                    $e['instructions'] = '200 caractères maximum.';
                }
                if ($len('meeting_point') > 200) {
                    $e['meeting_point'] = '200 caractères maximum.';
                }
                break;

            case 4:
                if (null === $this->categories->findOneBy(['slug' => (string) ($d['category'] ?? '')])) {
                    $e['category'] = 'Choisissez une catégorie.';
                }
                break;

            case 5:
                if ('' === (string) ($d['cover'] ?? '')) {
                    $e['cover'] = 'Ajoutez une image de couverture.';
                }
                if ($len('description') < 30) {
                    $e['description'] = 'Décrivez votre événement en détail (30 caractères minimum).';
                } elseif ($len('description') > 3000) {
                    $e['description'] = 'La description ne doit pas dépasser 3000 caractères.';
                }
                break;

            case 6:
                if (!\in_array($d['visibility'] ?? '', ['private', 'public', 'group'], true)) {
                    $e['visibility'] = "Choisissez le type d'événement.";
                }
                if ('unlimited' !== ($d['capacity'] ?? '') && !\in_array((int) ($d['capacity'] ?? 0), StaticEventWizard::capacities(), true)) {
                    $e['capacity'] = 'Choisissez une limite de participants.';
                }
                if (!\array_key_exists((string) ($d['reminder'] ?? ''), StaticEventWizard::reminders())) {
                    $e['reminder'] = 'Choisissez un rappel.';
                }
                break;

            case 7:
                foreach (array_filter(array_map('trim', preg_split('/[,;\s]+/', (string) ($d['invite_emails'] ?? '')) ?: [])) as $email) {
                    if (false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
                        $e['invite_emails'] = sprintf('Adresse e-mail invalide : %s', $email);
                        break;
                    }
                }
                if (\count((array) $d['invites']) > 200) {
                    $e['invites'] = '200 invités maximum.';
                }
                break;

            case 8:
                if (!\in_array($d['publish_mode'] ?? '', ['now', 'scheduled', 'draft'], true)) {
                    $e['publish_mode'] = 'Choisissez une option de publication.';
                }
                if ('scheduled' === ($d['publish_mode'] ?? '')) {
                    $at = $this->dateTime($d['publish_date'] ?? '', $d['publish_time'] ?? '');
                    if (null === $at || $at <= new \DateTimeImmutable()) {
                        $e['publish_date'] = 'Choisissez une date et une heure de publication à venir.';
                    }
                }
                if ('draft' !== ($d['publish_mode'] ?? '') && '1' !== ($d['accept_terms'] ?? '')) {
                    $e['accept_terms'] = "Acceptez les conditions d'utilisation pour publier.";
                }
                break;
        }

        return $e;
    }

    /**
     * Toutes les étapes, pour la publication : renvoie la première étape en
     * défaut et ses erreurs.
     *
     * @param array<string, mixed> $draft
     *
     * @return array{0: int|null, 1: array<string, string>}
     */
    public function firstInvalidStep(array $draft): array
    {
        for ($step = 1; $step <= self::STEPS; ++$step) {
            $errors = $this->validateStep($step, $draft);
            if ([] !== $errors) {
                return [$step, $errors];
            }
        }

        return [null, []];
    }

    /**
     * Crée ou met à jour l'événement depuis le brouillon.
     *
     * @param 'draft'|'now'|'scheduled' $mode
     */
    public function persist(SessionInterface $session, User $organizer, string $mode): Event
    {
        $d = $this->current($session);

        $event = null;
        if (Ulid::isValid((string) ($d['event_id'] ?? ''))) {
            $existing = $this->events->find(Ulid::fromString((string) $d['event_id']));
            if ($existing instanceof Event && $existing->getOrganizer() === $organizer) {
                $event = $existing;
            }
        }
        $isNew = null === $event;
        $event ??= (new Event())->setOrganizer($organizer)->setPosition(100);

        $title = mb_substr((string) ($d['title'] ?? ''), 0, 180) ?: 'Brouillon sans titre';
        if ($isNew || $event->getTitle() !== $title) {
            $event->setSlug($this->uniqueSlug($title, $isNew ? null : $event));
        }
        $allDay = '1' === $d['all_day'];
        $start = $this->dateTime((string) ($d['start_date'] ?? ''), $allDay ? '00:00' : (string) ($d['start_time'] ?? '')) ?? $event->getStartsAt();
        $end = null;
        if ($allDay) {
            $end = $this->dateTime(((string) ($d['end_date'] ?? '')) ?: (string) ($d['start_date'] ?? ''), '23:59');
        } elseif ('1' !== $d['no_end'] && '' !== (string) ($d['end_time'] ?? '')) {
            $end = $this->dateTime(((string) ($d['end_date'] ?? '')) ?: (string) ($d['start_date'] ?? ''), (string) $d['end_time']);
        }

        $locationType = (string) $d['location_type'];
        $location = match ($locationType) {
            'online' => 'En ligne',
            'tbd' => 'Lieu à définir',
            default => trim(((string) ($d['city'] ?? '')).(('' !== (string) ($d['postal_code'] ?? '')) ? ', '.$d['postal_code'] : '')) ?: mb_substr((string) ($d['address'] ?? ''), 0, 180),
        };

        $event
            ->setTitle($title)
            ->setEventType(($d['type'] ?? '') ?: null)
            ->setShortDescription(($d['summary'] ?? '') ?: null)
            ->setDescription(($d['description'] ?? '') ?: null)
            ->setVisibility((string) $d['visibility'])
            ->setPrivate('public' !== $d['visibility'])
            ->setDateMode((string) $d['date_mode'])
            ->setRecurrence('recurrent' === $d['date_mode'] ? (($d['recurrence'] ?? '') ?: null) : null)
            ->setAllDay($allDay)
            ->setStartsAt($start)
            ->setEndsAt($end)
            ->setTimezone((string) $d['timezone'])
            ->setReminder(($d['reminder'] ?? '') ?: null)
            ->setShowInCalendar('1' === $d['in_calendar'])
            ->setArrivalTime(($d['arrival'] ?? '') ?: null)
            ->setLocationType($locationType)
            ->setLocation('' !== $location ? $location : null)
            ->setAddress(\in_array($locationType, ['precise', 'approx'], true) ? (($d['address'] ?? '') ?: null) : null)
            ->setCity(($d['city'] ?? '') ?: null)
            ->setPostalCode(($d['postal_code'] ?? '') ?: null)
            ->setLatitude(is_numeric($d['lat'] ?? null) ? (string) $d['lat'] : null)
            ->setLongitude(is_numeric($d['lng'] ?? null) ? (string) $d['lng'] : null)
            ->setOnlineUrl('online' === $locationType ? (($d['online_url'] ?? '') ?: null) : null)
            ->setAccessInstructions(($d['instructions'] ?? '') ?: null)
            ->setMeetingPoint(($d['meeting_point'] ?? '') ?: null)
            ->setCategory($this->categories->findOneBy(['slug' => (string) ($d['category'] ?? '')]))
            ->setImagePath(($d['cover'] ?? '') ?: null)
            ->setGallery(array_values(array_map('strval', array_slice((array) $d['gallery'], 0, 5))))
            ->setRegistrationRequired('1' === $d['registration'])
            ->setCapacity('unlimited' === $d['capacity'] ? null : max(1, (int) $d['capacity']))
            ->setWaitlist('1' === $d['waitlist'])
            ->setShowParticipants('1' === $d['show_participants'])
            ->setAllowGuestInvites('1' === $d['allow_invites'])
            ->setCommentsEnabled('1' === $d['comments'])
            ->setEmailUpdates('1' === $d['email_updates']);

        if ('draft' === $mode) {
            $event->setStatus('draft')->setPublishAt(null);
        } elseif ('scheduled' === $mode) {
            $event->setStatus('scheduled')->setPublishAt($this->dateTime((string) ($d['publish_date'] ?? ''), (string) ($d['publish_time'] ?? '')));
        } else {
            $event->setStatus('published')->setPublishAt(new \DateTimeImmutable());
        }

        if ($isNew) {
            $this->entityManager->persist($event);
        }
        $this->entityManager->flush();

        if ('draft' === $mode) {
            $d['event_id'] = (string) $event->getId();
            $session->set(self::SESSION_KEY, $d);

            return $event;
        }

        // Organisateur inscrit d'office, invitations notifiées.
        $repo = $this->entityManager->getRepository(EventRegistration::class);
        if (null === $repo->findOneBy(['event' => $event, 'user' => $organizer])) {
            $this->entityManager->persist(new EventRegistration($event, $organizer));
        }
        $this->invite($event, $organizer, (array) $d['invites'], (string) ($d['invite_emails'] ?? ''));
        $this->entityManager->flush();
        $event->setParticipantsCount($repo->count(['event' => $event, 'status' => EventRegistration::GOING]));
        $this->entityManager->flush();

        $this->clear($session);

        return $event;
    }

    /** Reprend un brouillon enregistré pour le modifier dans l'assistant. */
    public function load(SessionInterface $session, Event $event): void
    {
        $session->set(self::SESSION_KEY, [
            'event_id' => (string) $event->getId(),
            'title' => $event->getTitle(),
            'type' => (string) $event->getEventType(),
            'summary' => (string) $event->getShortDescription(),
            'description' => (string) $event->getDescription(),
            'visibility' => $event->getVisibility(),
            'date_mode' => $event->getDateMode(),
            'recurrence' => (string) $event->getRecurrence(),
            'start_date' => $event->getStartsAt()->format('Y-m-d'),
            'start_time' => $event->isAllDay() ? '' : $event->getStartsAt()->format('H:i'),
            'end_date' => $event->getEndsAt()?->format('Y-m-d') ?? '',
            'end_time' => $event->isAllDay() ? '' : ($event->getEndsAt()?->format('H:i') ?? ''),
            'no_end' => null === $event->getEndsAt() ? '1' : '',
            'all_day' => $event->isAllDay() ? '1' : '',
            'timezone' => $event->getTimezone(),
            'reminder' => (string) $event->getReminder(),
            'in_calendar' => $event->isShowInCalendar() ? '1' : '',
            'arrival' => (string) $event->getArrivalTime(),
            'location_type' => $event->getLocationType(),
            'address' => (string) $event->getAddress(),
            'city' => (string) $event->getCity(),
            'postal_code' => (string) $event->getPostalCode(),
            'lat' => (string) $event->getLatitude(),
            'lng' => (string) $event->getLongitude(),
            'online_url' => (string) $event->getOnlineUrl(),
            'instructions' => (string) $event->getAccessInstructions(),
            'meeting_point' => (string) $event->getMeetingPoint(),
            'category' => (string) $event->getCategory()?->getSlug(),
            'cover' => (string) $event->getImagePath(),
            'gallery' => $event->getGallery(),
            'registration' => $event->isRegistrationRequired() ? '1' : '',
            'capacity' => null === $event->getCapacity() ? 'unlimited' : (string) $event->getCapacity(),
            'waitlist' => $event->isWaitlist() ? '1' : '',
            'show_participants' => $event->isShowParticipants() ? '1' : '',
            'allow_invites' => $event->isAllowGuestInvites() ? '1' : '',
            'comments' => $event->isCommentsEnabled() ? '1' : '',
            'email_updates' => $event->isEmailUpdates() ? '1' : '',
            'invites' => [],
            'done' => [],
        ]);
    }

    /**
     * Couverture (obligatoire) et galerie (5 images au plus), retrait inclus.
     *
     * @param array<string, mixed> $draft
     *
     * @return array<string, string>
     */
    private function handleImages(array &$draft, Request $request): array
    {
        $errors = [];
        $cover = $request->files->get('cover');
        if ($cover instanceof UploadedFile) {
            try {
                $draft['cover'] = $this->images->store($cover, 'events');
            } catch (\InvalidArgumentException $e) {
                $errors['cover'] = $e->getMessage();
            }
        }

        $gallery = array_values(array_diff((array) $draft['gallery'], array_map('strval', $request->request->all('remove_gallery'))));
        foreach ((array) $request->files->get('gallery', []) as $file) {
            if ($file instanceof UploadedFile && \count($gallery) < 5) {
                try {
                    $gallery[] = $this->images->store($file, 'events');
                } catch (\InvalidArgumentException $e) {
                    $errors['gallery'] = $e->getMessage();
                }
            }
        }
        $draft['gallery'] = $gallery;

        return $errors;
    }

    /** @param list<string> $userIds */
    private function invite(Event $event, User $organizer, array $userIds, string $emails): void
    {
        $repo = $this->entityManager->getRepository(EventInvitation::class);
        $name = trim($organizer->getFirstName().' '.$organizer->getLastName());

        foreach ($userIds as $id) {
            $user = $this->users->find(Ulid::fromString($id));
            if (null === $user || $user === $organizer || null !== $repo->findOneBy(['event' => $event, 'user' => $user])) {
                continue;
            }
            $this->entityManager->persist(new EventInvitation($event, $user));
            $this->notifications->notify($user, NotificationCategory::Activity, 'Invitation à un événement', sprintf('%s vous invite à « %s » le %s.', $name, $event->getTitle(), $event->getStartsAt()->format('d/m/Y')));
        }

        foreach (array_unique(array_filter(array_map('trim', preg_split('/[,;\s]+/', $emails) ?: []))) as $email) {
            if (false === filter_var($email, \FILTER_VALIDATE_EMAIL) || null !== $repo->findOneBy(['event' => $event, 'email' => mb_strtolower($email)])) {
                continue;
            }
            $member = $this->users->findOneBy(['email' => mb_strtolower($email)]);
            $this->entityManager->persist(new EventInvitation($event, $member, mb_strtolower($email)));
            if (null !== $member && $member !== $organizer) {
                $this->notifications->notify($member, NotificationCategory::Activity, 'Invitation à un événement', sprintf('%s vous invite à « %s ».', $name, $event->getTitle()));
            }
        }
    }

    /** « 2026-05-24 » + « 18:00 » → date (formats des champs HTML). */
    public function dateTime(string $date, string $time): ?\DateTimeImmutable
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($date)) || !preg_match('/^\d{2}:\d{2}$/', trim($time))) {
            return null;
        }
        $value = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', trim($date).' '.trim($time));

        return false !== $value ? $value : null;
    }

    private function uniqueSlug(string $title, ?Event $self): string
    {
        $base = strtolower($this->slugger->slug($title)->toString()) ?: 'evenement';
        $slug = $base;
        for ($i = 2; ($found = $this->events->findOneBySlug($slug)) && $found !== $self; ++$i) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
