<?php

declare(strict_types=1);

namespace App\PrivateActivity\Service;

use App\Catalog\Repository\CategoryRepository;
use App\PrivateActivity\Entity\PrivateActivity;
use App\PrivateActivity\Enum\ParticipationMode;
use App\PrivateActivity\Enum\PrivateActivityVisibility;
use App\Shared\Service\PublicImageStorage;
use App\User\Entity\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

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
        private readonly PrivateActivityService $activities,
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
                } elseif ($len('city') < 2) {
                    $e['address'] = 'Choisissez l’adresse dans la liste proposée.';
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

    /** @param array<string, mixed> $d */
    public function publish(array $d, User $organizer): PrivateActivity
    {
        $category = $this->categories->findOneBy(['slug' => (string) $d['category']]);
        if (null === $category) {
            throw new \InvalidArgumentException('Choisissez une catégorie.');
        }

        $activity = $this->activities->create(
            organizer: $organizer,
            title: mb_substr((string) $d['title'], 0, 150),
            category: $category,
            description: (string) $d['description'],
            scheduledAt: $this->start($d),
            city: '' !== (string) ($d['city'] ?? '') ? mb_substr((string) $d['city'], 0, 120) : null,
            location: mb_substr((string) $d['address'], 0, 255),
            showExactAddress: '1' === ($d['show_exact'] ?? ''),
            visibility: PrivateActivityVisibility::from((string) $d['visibility']),
            participationMode: ParticipationMode::from((string) $d['mode']),
            minParticipants: ctype_digit((string) ($d['min'] ?? '')) ? (int) $d['min'] : null,
            maxParticipants: ctype_digit((string) ($d['max'] ?? '')) ? (int) $d['max'] : null,
        );

        $activity
            ->setEndsAt('' !== (string) ($d['end_time'] ?? '') ? $this->end($d) : null)
            ->setPostalCode('' !== (string) ($d['postal_code'] ?? '') ? mb_substr((string) $d['postal_code'], 0, 10) : null)
            ->setLatitude(is_numeric($d['lat'] ?? null) ? number_format((float) $d['lat'], 7, '.', '') : null)
            ->setLongitude(is_numeric($d['lng'] ?? null) ? number_format((float) $d['lng'], 7, '.', '') : null)
            ->setMeetingPoint('' !== (string) ($d['meeting_point'] ?? '') ? mb_substr((string) $d['meeting_point'], 0, 500) : null)
            ->setToBring('' !== (string) ($d['to_bring'] ?? '') ? (string) $d['to_bring'] : null)
            ->setCoverImage('' !== (string) ($d['cover'] ?? '') ? (string) $d['cover'] : null);
        $this->activities->save();

        return $activity;
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
}
