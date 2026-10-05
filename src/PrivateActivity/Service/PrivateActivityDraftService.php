<?php

declare(strict_types=1);

namespace App\PrivateActivity\Service;

use App\Catalog\Repository\CategoryRepository;
use App\PrivateActivity\Entity\PrivateActivity;
use App\PrivateActivity\Enum\ParticipationMode;
use App\PrivateActivity\Enum\PrivateActivityStatus;
use App\PrivateActivity\Enum\PrivateActivityVisibility;
use App\PrivateActivity\Repository\PrivateActivityRepository;
use App\Shared\Service\PublicImageStorage;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Brouillon de l'assistant « Organiser une activité privée » (05/10).
 *
 * Même principe que les assistants des événements et des activités pro :
 * saisie en session, chaque étape validée côté serveur, publication à la
 * dernière étape via PrivateActivityService::create().
 */
final class PrivateActivityDraftService
{
    public const STEPS = 5;
    private const SESSION_KEY = 'private_activity_draft';

    private const STEP_FIELDS = [
        1 => ['title', 'category', 'description'],
        2 => ['date', 'start_time', 'end_time', 'address', 'city', 'postal_code', 'lat', 'lng', 'meeting_point'],
        3 => ['to_bring'],
        4 => ['min', 'max', 'mode', 'visibility'],
        5 => [],
    ];

    private const DEFAULTS = [
        'show_exact' => '1', 'mode' => 'validation', 'visibility' => 'public', 'cover' => '', 'done' => [],
    ];

    public function __construct(
        private readonly CategoryRepository $categories,
        private readonly PublicImageStorage $images,
        private readonly PrivateActivityRepository $repository,
        private readonly EntityManagerInterface $entityManager,
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

    /** @return array<string, string> */
    public function submitStep(SessionInterface $session, int $step, Request $request): array
    {
        $draft = $this->current($session);
        $errors = [];
        foreach (self::STEP_FIELDS[$step] ?? [] as $field) {
            if ($request->request->has($field)) {
                $draft[$field] = trim((string) $request->request->get($field));
            }
        }
        if (2 === $step) {
            self::cityFromAddress($draft);
        }
        if (2 === $step) {
            $draft['show_exact'] = $request->request->getBoolean('show_exact') ? '1' : '';
        }
        if (3 === $step) {
            $cover = $request->files->get('cover');
            if ($cover instanceof UploadedFile) {
                try {
                    $draft['cover'] = $this->images->store($cover, 'private-activities');
                } catch (\InvalidArgumentException $e) {
                    $errors['cover'] = $e->getMessage();
                }
            } elseif ($request->request->getBoolean('remove_cover')) {
                $draft['cover'] = '';
            }
        }
        if (5 === $step) {
            $draft['accept_terms'] = $request->request->getBoolean('accept_terms') ? '1' : '';
        }

        $errors += $this->validateStep($step, $draft);
        // Une catégorie existante est choisie : la proposition en attente n'a plus à s'afficher.
        if (1 === $step && !isset($errors['category'])) {
            $draft['category_suggestion'] = null;
        }
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
                if ($len('title') < 5) {
                    $e['title'] = 'Donnez un titre à votre activité (5 caractères minimum).';
                } elseif ($len('title') > 150) {
                    $e['title'] = 'Le titre ne doit pas dépasser 150 caractères.';
                }
                if (null === $this->categories->findOneBy(['slug' => (string) ($d['category'] ?? '')])) {
                    $e['category'] = 'Choisissez une catégorie.';
                }
                if ($len('description') < 30) {
                    $e['description'] = 'Décrivez votre activité (30 caractères minimum).';
                } elseif ($len('description') > 3000) {
                    $e['description'] = '3000 caractères maximum.';
                }
                break;

            case 2:
                $start = $this->start($d);
                if (false === \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($d['date'] ?? ''))) {
                    $e['date'] = 'Indiquez la date.';
                } elseif (null === $start) {
                    $e['start_time'] = "Indiquez l'heure de début.";
                } elseif ($start < new \DateTimeImmutable()) {
                    $e['date'] = 'La date et l’heure doivent être à venir.';
                }
                if ('' !== (string) ($d['end_time'] ?? '')) {
                    $end = $this->end($d);
                    if (null === $end || (null !== $start && $end <= $start)) {
                        $e['end_time'] = 'L’heure de fin doit suivre l’heure de début.';
                    }
                }
                if ($len('address') < 5) {
                    $e['address'] = 'Indiquez le lieu de l’activité.';
                }
                if ($len('meeting_point') > 500) {
                    $e['meeting_point'] = '500 caractères maximum.';
                }
                break;

            case 3:
                if ($len('to_bring') > 1000) {
                    $e['to_bring'] = '1000 caractères maximum.';
                }
                break;

            case 4:
                $min = (string) ($d['min'] ?? '');
                $max = (string) ($d['max'] ?? '');
                if ('' !== $min && (!ctype_digit($min) || (int) $min < 1 || (int) $min > 500)) {
                    $e['min'] = 'Minimum invalide (1 à 500).';
                }
                if ('' !== $max && (!ctype_digit($max) || (int) $max < 1 || (int) $max > 500)) {
                    $e['max'] = 'Maximum invalide (1 à 500).';
                } elseif ('' !== $max && '' !== $min && ctype_digit($min) && (int) $max < (int) $min) {
                    $e['max'] = 'Le maximum doit être supérieur ou égal au minimum.';
                }
                if (null === ParticipationMode::tryFrom((string) ($d['mode'] ?? ''))) {
                    $e['mode'] = 'Choisissez comment les participants rejoignent l’activité.';
                }
                if (null === PrivateActivityVisibility::tryFrom((string) ($d['visibility'] ?? ''))) {
                    $e['visibility'] = 'Choisissez qui peut voir l’activité.';
                }
                break;

            case 5:
                if ('1' !== ($d['accept_terms'] ?? '')) {
                    $e['accept_terms'] = 'Vous devez accepter les conditions générales d’utilisation.';
                }
                break;
        }

        return $e;
    }

    /**
     * @param array<string, mixed> $draft
     *
     * @return array{0: int|null, 1: array<string, string>}
     */
    public function firstInvalidStep(array $draft): array
    {
        for ($step = 1; $step < self::STEPS; ++$step) {
            $errors = $this->validateStep($step, $draft);
            if ([] !== $errors) {
                return [$step, $errors];
            }
        }

        return [null, []];
    }

    /**
     * Publie l'activité : met à jour le brouillon enregistré s'il existe
     * (sinon la crée), au statut « ouverte ».
     *
     * @param array<string, mixed> $d
     */
    public function publish(array $d, User $organizer): PrivateActivity
    {
        $category = $this->categories->findOneBy(['slug' => (string) $d['category']]);
        if (null === $category) {
            throw new \InvalidArgumentException('Choisissez une catégorie.');
        }

        $activity = $this->existing($d, $organizer) ?? (new PrivateActivity())->setOrganizer($organizer);
        $this->fill($activity, $d);
        $activity->setStatus(PrivateActivityStatus::Open);
        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        return $activity;
    }

    /**
     * Enregistre la saisie en brouillon (05/10), même incomplète : un titre
     * suffit. La catégorie peut manquer (proposition en attente de validation).
     *
     * @param array<string, mixed> $d
     */
    public function saveDraft(SessionInterface $session, array $d, User $organizer): PrivateActivity
    {
        if (mb_strlen(trim((string) ($d['title'] ?? ''))) < 3) {
            throw new \InvalidArgumentException('Donnez au moins un titre (étape 1) pour enregistrer un brouillon.');
        }

        $activity = $this->existing($d, $organizer) ?? (new PrivateActivity())->setOrganizer($organizer)->setStatus(PrivateActivityStatus::Draft);
        $this->fill($activity, $d);
        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        $session->set(self::SESSION_KEY, ['id' => (string) $activity->getId()] + $this->current($session));

        return $activity;
    }

    /** Reprend un brouillon (ou une activité à modifier) dans l'assistant. */
    public function load(SessionInterface $session, PrivateActivity $activity): void
    {
        $start = $activity->getScheduledAt();
        $session->set(self::SESSION_KEY, [
            'id' => (string) $activity->getId(),
            'title' => $activity->getTitle(),
            'category' => $activity->getCategory()?->getSlug() ?? '',
            'description' => (string) $activity->getDescription(),
            'date' => $start?->format('Y-m-d') ?? '',
            'start_time' => $start?->format('H:i') ?? '',
            'end_time' => $activity->getEndsAt()?->format('H:i') ?? '',
            'address' => (string) $activity->getLocation(),
            'city' => (string) $activity->getCity(),
            'postal_code' => (string) $activity->getPostalCode(),
            'lat' => (string) $activity->getLatitude(),
            'lng' => (string) $activity->getLongitude(),
            'show_exact' => $activity->showsExactAddress() ? '1' : '',
            'meeting_point' => (string) $activity->getMeetingPoint(),
            'to_bring' => (string) $activity->getToBring(),
            'cover' => (string) $activity->getCoverImage(),
            'min' => null !== $activity->getMinParticipants() ? (string) $activity->getMinParticipants() : '',
            'max' => null !== $activity->getMaxParticipants() ? (string) $activity->getMaxParticipants() : '',
            'mode' => $activity->getParticipationMode()->value,
            'visibility' => $activity->getVisibility()->value,
            // Étapes déjà valides : navigation libre jusqu'à la première incomplète.
            'done' => array_values(array_filter([1, 2, 3, 4], fn (int $n): bool => [] === $this->validateStep($n, ['title' => $activity->getTitle(), 'category' => $activity->getCategory()?->getSlug() ?? '', 'description' => (string) $activity->getDescription(), 'date' => $start?->format('Y-m-d') ?? '', 'start_time' => $start?->format('H:i') ?? '', 'address' => (string) $activity->getLocation(), 'city' => (string) $activity->getCity(), 'mode' => $activity->getParticipationMode()->value, 'visibility' => $activity->getVisibility()->value]))),
        ]);
    }

    /**
     * Enregistre des valeurs dans le brouillon en session (catégorie proposée…).
     *
     * @param array<string, mixed> $values
     */
    public function patch(SessionInterface $session, array $values): void
    {
        $session->set(self::SESSION_KEY, $values + $this->current($session));
    }

    /** @param array<string, mixed> $d */
    private function existing(array $d, User $organizer): ?PrivateActivity
    {
        $id = (string) ($d['id'] ?? '');
        if ('' === $id || !Ulid::isValid($id)) {
            return null;
        }
        $activity = $this->repository->find(Ulid::fromString($id));

        return null !== $activity && $activity->getOrganizer() === $organizer ? $activity : null;
    }

    /** @param array<string, mixed> $d */
    private function fill(PrivateActivity $activity, array $d): void
    {
        $str = static fn (string $key, int $max): ?string => '' !== trim((string) ($d[$key] ?? '')) ? mb_substr(trim((string) $d[$key]), 0, $max) : null;
        $activity
            ->setTitle(mb_substr(trim((string) ($d['title'] ?? '')), 0, 150))
            ->setCategory($this->categories->findOneBy(['slug' => (string) ($d['category'] ?? '')]) ?? $activity->getCategory())
            ->setDescription($str('description', 3000))
            ->setScheduledAt($this->start($d))
            ->setEndsAt('' !== (string) ($d['end_time'] ?? '') ? $this->end($d) : null)
            ->setCity($str('city', 120))
            ->setLocation($str('address', 255))
            ->setPostalCode($str('postal_code', 10))
            ->setLatitude(is_numeric($d['lat'] ?? null) ? number_format((float) $d['lat'], 7, '.', '') : null)
            ->setLongitude(is_numeric($d['lng'] ?? null) ? number_format((float) $d['lng'], 7, '.', '') : null)
            ->setShowExactAddress('1' === ($d['show_exact'] ?? ''))
            ->setMeetingPoint($str('meeting_point', 500))
            ->setToBring($str('to_bring', 1000))
            ->setCoverImage($str('cover', 255))
            ->setVisibility(PrivateActivityVisibility::tryFrom((string) ($d['visibility'] ?? '')) ?? PrivateActivityVisibility::Public)
            ->setParticipationMode(ParticipationMode::tryFrom((string) ($d['mode'] ?? '')) ?? ParticipationMode::Validation)
            ->setMinParticipants(ctype_digit((string) ($d['min'] ?? '')) ? (int) $d['min'] : null)
            ->setMaxParticipants(ctype_digit((string) ($d['max'] ?? '')) ? (int) $d['max'] : null);
    }

    /** @param array<string, mixed> $d */
    private function start(array $d): ?\DateTimeImmutable
    {
        return self::at((string) ($d['date'] ?? ''), (string) ($d['start_time'] ?? ''));
    }

    /** @param array<string, mixed> $d */
    private function end(array $d): ?\DateTimeImmutable
    {
        return self::at((string) ($d['date'] ?? ''), (string) ($d['end_time'] ?? ''));
    }

    private static function at(string $date, string $time): ?\DateTimeImmutable
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
            return null;
        }

        return \DateTimeImmutable::createFromFormat('Y-m-d H:i', $date.' '.$time) ?: null;
    }

    /**
     * Adresse tapée sans choisir de suggestion : la ville est le début du
     * texte (« Dassa-Zoumè, Bénin »).
     *
     * @param array<string, mixed> $draft
     */
    private static function cityFromAddress(array &$draft): void
    {
        if ('' === trim((string) ($draft['city'] ?? '')) && '' !== trim((string) ($draft['address'] ?? ''))) {
            $draft['city'] = mb_substr(trim(explode(',', (string) $draft['address'])[0]), 0, 120);
        }
    }
}
