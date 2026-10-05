<?php

declare(strict_types=1);

namespace App\Catalog\Service;

use App\Catalog\Entity\Media;
use App\Catalog\Entity\Service;
use App\Catalog\Entity\ServiceDetail;
use App\Catalog\Entity\ServicePackage;
use App\Catalog\Enum\ActivityLevel;
use App\Catalog\Enum\ActivityType;
use App\Catalog\Enum\BookingType;
use App\Catalog\Enum\CancellationPolicy;
use App\Catalog\Enum\OpeningPeriod;
use App\Catalog\Enum\PricingUnit;
use App\Catalog\Presenter\ActivityPresenter;
use App\Catalog\Repository\CategoryRepository;
use App\Catalog\Repository\ServiceRepository;
use App\Catalog\StaticActivityWizard;
use App\Provider\Entity\ProviderProfile;
use App\Shared\Service\PublicImageStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Brouillon de l'assistant « Créer une activité » de l'espace pro (05/10).
 *
 * La saisie vit en session d'une étape à l'autre ; chaque étape est validée
 * côté serveur avant la suivante. L'enregistrement remplit TOUT ce que la
 * fiche publique affiche : Service (titre, lieu, durée, niveau, langues…),
 * ServiceDetail (points forts, inclus, à apporter…), photos (Media) et
 * formules (ServicePackage) — ce que l'ancien formulaire ne faisait pas.
 */
final class ActivityDraftService
{
    public const STEPS = 6;
    public const MAX_GALLERY = 8;
    public const MAX_PACKAGES = 5;
    private const SESSION_KEY = 'activity_draft';

    private const STEP_FIELDS = [
        1 => ['title', 'category', 'activity_type', 'subtitle', 'level', 'audience', 'minimum_age'],
        2 => ['address', 'city', 'postal_code', 'lat', 'lng', 'place_label', 'meeting_point', 'opening_period'],
        3 => ['description', 'programme'],
        4 => ['duration', 'capacity', 'highlights', 'included', 'excluded', 'to_bring', 'cannot_participate'],
        5 => ['cancellation', 'booking_type'],
        6 => [],
    ];

    private const DEFAULTS = [
        'id' => null, 'activity_type' => 'supervised', 'level' => 'all_levels', 'languages' => ['Français'],
        'opening_period' => 'all_year', 'cancellation' => 'flexible', 'booking_type' => 'calendar',
        'cover' => '', 'gallery' => [], 'packages' => [['name' => 'Tarif unique', 'price' => '', 'unit' => 'per_person', 'description' => '']],
        'done' => [],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CategoryRepository $categories,
        private readonly ServiceRepository $services,
        private readonly PublicImageStorage $images,
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

    public function start(SessionInterface $session): void
    {
        $session->set(self::SESSION_KEY, []);
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

    /**
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
        if (1 === $step) {
            $draft['languages'] = array_values(array_intersect(array_map('strval', $request->request->all('languages')), StaticActivityWizard::languages()));
        }
        if (3 === $step) {
            $errors += $this->handleImages($draft, $request);
        }
        if (5 === $step) {
            $packages = [];
            foreach ($request->request->all('packages') as $row) {
                if (!\is_array($row)) {
                    continue;
                }
                $row = array_map(static fn (mixed $v): string => trim((string) $v), $row) + ['name' => '', 'price' => '', 'unit' => 'per_person', 'description' => ''];
                if ('' === $row['name'] && '' === $row['price'] && '' === $row['description']) {
                    continue;
                }
                $row['price'] = str_replace(',', '.', $row['price']);
                $packages[] = ['name' => $row['name'], 'price' => $row['price'], 'unit' => $row['unit'], 'description' => $row['description']];
            }
            $draft['packages'] = \array_slice($packages, 0, self::MAX_PACKAGES);
        }
        if (6 === $step) {
            $draft['accept_charter'] = $request->request->getBoolean('accept_charter') ? '1' : '';
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
                } elseif ($len('title') > 120) {
                    $e['title'] = 'Le titre ne doit pas dépasser 120 caractères.';
                }
                if (null === $this->categories->findOneBy(['slug' => (string) ($d['category'] ?? '')])) {
                    $e['category'] = 'Choisissez une catégorie.';
                }
                if (!\array_key_exists((string) ($d['activity_type'] ?? ''), StaticActivityWizard::activityTypes())) {
                    $e['activity_type'] = "Choisissez le type d'activité.";
                }
                if ($len('subtitle') < 10) {
                    $e['subtitle'] = 'Résumez votre activité en une phrase (10 caractères minimum).';
                } elseif ($len('subtitle') > 160) {
                    $e['subtitle'] = 'L’accroche ne doit pas dépasser 160 caractères.';
                }
                if (!\array_key_exists((string) ($d['level'] ?? ''), StaticActivityWizard::levels())) {
                    $e['level'] = 'Choisissez un niveau.';
                }
                if ([] === (array) ($d['languages'] ?? [])) {
                    $e['languages'] = 'Indiquez au moins une langue parlée.';
                }
                if ('' !== (string) ($d['minimum_age'] ?? '') && (!ctype_digit((string) $d['minimum_age']) || (int) $d['minimum_age'] > 99)) {
                    $e['minimum_age'] = 'Âge minimum invalide.';
                }
                if ($len('audience') > 255) {
                    $e['audience'] = '255 caractères maximum.';
                }
                break;

            case 2:
                if ($len('address') < 5) {
                    $e['address'] = "Indiquez l'adresse de l'activité.";
                } elseif ($len('city') < 2) {
                    $e['address'] = 'Choisissez l’adresse dans la liste proposée pour la placer sur la carte.';
                }
                if ($len('place_label') > 120) {
                    $e['place_label'] = '120 caractères maximum.';
                }
                if ($len('meeting_point') < 5) {
                    $e['meeting_point'] = 'Décrivez le point de rendez-vous.';
                } elseif ($len('meeting_point') > 500) {
                    $e['meeting_point'] = '500 caractères maximum.';
                }
                if (!\array_key_exists((string) ($d['opening_period'] ?? ''), StaticActivityWizard::openingPeriods())) {
                    $e['opening_period'] = 'Choisissez la période d’ouverture.';
                }
                break;

            case 3:
                if ('' === (string) ($d['cover'] ?? '')) {
                    $e['cover'] = 'Ajoutez une photo principale.';
                }
                if ($len('description') < 80) {
                    $e['description'] = 'Décrivez votre activité en détail (80 caractères minimum).';
                } elseif ($len('description') > 5000) {
                    $e['description'] = 'La description ne doit pas dépasser 5000 caractères.';
                }
                if ($len('programme') > 3000) {
                    $e['programme'] = '3000 caractères maximum.';
                }
                break;

            case 4:
                if (!\array_key_exists((int) ($d['duration'] ?? 0), StaticActivityWizard::durations())) {
                    $e['duration'] = 'Choisissez la durée de l’activité.';
                }
                $capacity = (string) ($d['capacity'] ?? '');
                if (!ctype_digit($capacity) || (int) $capacity < 1 || (int) $capacity > 500) {
                    $e['capacity'] = 'Indiquez le nombre de places par séance (1 à 500).';
                }
                if ([] === self::lines((string) ($d['highlights'] ?? ''))) {
                    $e['highlights'] = 'Ajoutez au moins un point fort.';
                }
                if ([] === self::lines((string) ($d['included'] ?? ''))) {
                    $e['included'] = 'Précisez ce qui est inclus.';
                }
                foreach (['highlights', 'included', 'excluded', 'to_bring', 'cannot_participate'] as $key) {
                    if (\count(self::lines((string) ($d[$key] ?? ''))) > 12) {
                        $e[$key] = '12 éléments maximum.';
                    }
                }
                break;

            case 5:
                $packages = (array) ($d['packages'] ?? []);
                if ([] === $packages) {
                    $e['packages'] = 'Ajoutez au moins une formule avec son prix.';
                }
                foreach ($packages as $i => $p) {
                    if (mb_strlen((string) $p['name']) < 2 || mb_strlen((string) $p['name']) > 80) {
                        $e['packages'] = sprintf('Formule %d : donnez-lui un nom (2 à 80 caractères).', $i + 1);
                    } elseif (!is_numeric($p['price']) || (float) $p['price'] < 0 || (float) $p['price'] > 100000) {
                        $e['packages'] = sprintf('Formule %d : indiquez un prix valide (0 pour gratuit).', $i + 1);
                    } elseif (!\array_key_exists((string) $p['unit'], StaticActivityWizard::pricingUnits())) {
                        $e['packages'] = sprintf('Formule %d : choisissez l’unité du prix.', $i + 1);
                    }
                    if (isset($e['packages'])) {
                        break;
                    }
                }
                if (null === CancellationPolicy::tryFrom((string) ($d['cancellation'] ?? ''))) {
                    $e['cancellation'] = 'Choisissez une politique d’annulation.';
                }
                if (!\array_key_exists((string) ($d['booking_type'] ?? ''), StaticActivityWizard::bookingTypes())) {
                    $e['booking_type'] = 'Choisissez le mode de réservation.';
                }
                break;

            case 6:
                if ('1' !== ($d['accept_charter'] ?? '')) {
                    $e['accept_charter'] = 'Vous devez certifier l’exactitude des informations et accepter les conditions.';
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

    /** Charge une activité existante dans le brouillon (modification). */
    public function load(SessionInterface $session, Service $service): void
    {
        $session->set(self::SESSION_KEY, $this->fromService($service));
    }

    /**
     * L'activité sous forme de brouillon (modification, duplication).
     *
     * @return array<string, mixed>
     */
    public function fromService(Service $service): array
    {
        $detail = $service->getDetail();
        $cover = '';
        $gallery = [];
        foreach ($service->getMedia() as $media) {
            if (ActivityPresenter::MEDIA_COVER === $media->getType()) {
                $cover = $media->getPath();
            } elseif (ActivityPresenter::MEDIA_GALLERY === $media->getType()) {
                $gallery[] = $media->getPath();
            }
        }
        $packages = [];
        foreach ($service->getPackages() as $p) {
            $packages[] = ['name' => $p->getName(), 'price' => $p->getPrice(), 'unit' => $p->getPricingUnit()->value, 'description' => (string) $p->getDescription()];
        }

        return [
            'id' => (string) $service->getId(),
            'title' => $service->getTitle(),
            'category' => $service->getCategory()?->getSlug() ?? '',
            'activity_type' => $service->getActivityType()->value ?? 'supervised',
            'subtitle' => $service->getSubtitle() ?? $service->getShortDescription() ?? '',
            'level' => $service->getLevel()->value ?? 'all_levels',
            'languages' => $service->getLanguages() ?: ['Français'],
            'audience' => $service->getAudience() ?? '',
            'minimum_age' => null !== $service->getMinimumAge() ? (string) $service->getMinimumAge() : '',
            'address' => $service->getAddress() ?? '',
            'city' => $service->getCity() ?? '',
            'postal_code' => $service->getPostalCode() ?? '',
            'lat' => $service->getLatitude() ?? '',
            'lng' => $service->getLongitude() ?? '',
            'place_label' => $service->getPlaceLabel() ?? '',
            'meeting_point' => $service->getMeetingPoint() ?? '',
            'opening_period' => $service->getOpeningPeriod()->value ?? 'all_year',
            'cover' => $cover,
            'gallery' => \array_slice($gallery, 0, self::MAX_GALLERY),
            'description' => $service->getDescription(),
            'programme' => $service->getProgramme() ?? '',
            'duration' => null !== $service->getDurationMinutes() ? (string) $service->getDurationMinutes() : '',
            'capacity' => null !== $service->getCapacity() ? (string) $service->getCapacity() : '',
            'highlights' => implode("\n", $detail?->getHighlights() ?? []),
            'included' => implode("\n", $detail?->getIncluded() ?: self::lines((string) $service->getIncluded())),
            'excluded' => implode("\n", $detail?->getExcluded() ?? []),
            'to_bring' => implode("\n", $detail?->getToBring() ?? []),
            'cannot_participate' => implode("\n", $detail?->getCannotParticipate() ?? []),
            'packages' => $packages ?: self::DEFAULTS['packages'],
            'cancellation' => $service->getCancellationPolicy()->value,
            'booking_type' => BookingType::ServiceProduct === $service->getBookingType() ? 'service_product' : 'calendar',
            // Une activité existante a déjà franchi les étapes : on peut naviguer librement.
            'done' => [1, 2, 3, 4, 5],
        ];
    }

    /**
     * Crée ou met à jour l'activité à partir du brouillon.
     *
     * @param array<string, mixed> $d
     */
    public function persist(array $d, ProviderProfile $provider): Service
    {
        $service = null;
        if (null !== ($d['id'] ?? null)) {
            $service = $this->services->find((string) $d['id']);
            if (null === $service || $service->getProvider() !== $provider) {
                throw new \InvalidArgumentException('Activité introuvable.');
            }
        }
        $isNew = null === $service;
        $service ??= (new Service())->setProvider($provider)->setCurrency('EUR');

        $category = $this->categories->findOneBy(['slug' => (string) ($d['category'] ?? '')]);
        $duration = (int) ($d['duration'] ?? 0);
        $subtitle = self::str($d, 'subtitle', 160);

        $service
            ->setTitle(mb_substr((string) $d['title'], 0, 180))
            ->setCategory($category ?? $service->getCategory())
            ->setActivityType(ActivityType::tryFrom((string) ($d['activity_type'] ?? '')))
            ->setSubtitle($subtitle)
            ->setShortDescription($subtitle)
            ->setLevel(ActivityLevel::tryFrom((string) ($d['level'] ?? '')))
            ->setLanguages(array_values((array) ($d['languages'] ?? [])))
            ->setAudience(self::str($d, 'audience', 255))
            ->setMinimumAge('' !== (string) ($d['minimum_age'] ?? '') ? (int) $d['minimum_age'] : null)
            ->setAddress(self::str($d, 'address', 255))
            ->setCity(self::str($d, 'city', 120))
            ->setPostalCode(self::str($d, 'postal_code', 10))
            ->setCountry($service->getCountry() ?? 'FR')
            ->setLatitude(is_numeric($d['lat'] ?? null) ? number_format((float) $d['lat'], 7, '.', '') : null)
            ->setLongitude(is_numeric($d['lng'] ?? null) ? number_format((float) $d['lng'], 7, '.', '') : null)
            ->setPlaceLabel(self::str($d, 'place_label', 120) ?? self::str($d, 'city', 120))
            ->setMeetingPoint(self::str($d, 'meeting_point', 2000))
            ->setOpeningPeriod(OpeningPeriod::tryFrom((string) ($d['opening_period'] ?? '')))
            ->setDescription((string) ($d['description'] ?? ''))
            ->setProgramme(self::str($d, 'programme', 3000))
            ->setDurationMinutes($duration > 0 ? $duration : null)
            ->setDurationLabel($duration > 0 ? StaticActivityWizard::durationLabel($duration) : null)
            ->setCapacity(ctype_digit((string) ($d['capacity'] ?? '')) ? (int) $d['capacity'] : null)
            ->setIncluded(implode("\n", self::lines((string) ($d['included'] ?? ''))) ?: null)
            ->setCancellationPolicy(CancellationPolicy::tryFrom((string) ($d['cancellation'] ?? '')) ?? CancellationPolicy::Flexible)
            ->setBookingType('service_product' === ($d['booking_type'] ?? '') ? BookingType::ServiceProduct : BookingType::Calendar);

        if ($isNew) {
            $service->setSlug($this->uniqueSlug($service->getTitle()));
            $this->entityManager->persist($service);
        }

        // Fiche détaillée : ce que la page publique affiche en listes.
        $detail = $service->getDetail();
        if (null === $detail) {
            $detail = (new ServiceDetail())->setService($service);
            $service->setDetail($detail);
            $this->entityManager->persist($detail);
        }
        $detail
            ->setHighlightsTitle('Points forts de l’activité')
            ->setHighlights(self::lines((string) ($d['highlights'] ?? '')))
            ->setIncluded(self::lines((string) ($d['included'] ?? '')))
            ->setExcluded(self::lines((string) ($d['excluded'] ?? '')))
            ->setToBring(self::lines((string) ($d['to_bring'] ?? '')))
            ->setCannotParticipate(self::lines((string) ($d['cannot_participate'] ?? '')))
            // Ces valeurs sont recalculées à l'affichage depuis l'activité.
            ->setKeyFacts([])
            ->setMeetingPoints([])
            ->setPresentationSubtitle(null)
            ->setPresentationText(null);

        $this->syncMedia($service, (string) ($d['cover'] ?? ''), array_values((array) ($d['gallery'] ?? [])));
        $this->syncPackages($service, (array) ($d['packages'] ?? []));

        $this->entityManager->flush();

        return $service;
    }

    /**
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
                $draft['cover'] = $this->images->store($cover, 'activities');
            } catch (\InvalidArgumentException $e) {
                $errors['cover'] = $e->getMessage();
            }
        }

        $remove = array_map('strval', $request->request->all('remove_gallery'));
        $gallery = array_values(array_diff((array) $draft['gallery'], $remove));
        foreach ((array) $request->files->get('gallery', []) as $file) {
            if (!$file instanceof UploadedFile || \count($gallery) >= self::MAX_GALLERY) {
                continue;
            }
            try {
                $gallery[] = $this->images->store($file, 'activities');
            } catch (\InvalidArgumentException $e) {
                $errors['gallery'] = $e->getMessage();
            }
        }
        $draft['gallery'] = $gallery;

        return $errors;
    }

    /**
     * @param list<string> $gallery
     */
    private function syncMedia(Service $service, string $cover, array $gallery): void
    {
        $keep = array_filter([$cover, ...$gallery]);
        foreach ($service->getMedia()->toArray() as $media) {
            if (!\in_array($media->getPath(), $keep, true)
                || (ActivityPresenter::MEDIA_COVER === $media->getType() && $media->getPath() !== $cover)) {
                $service->removeMedia($media);
            }
        }

        $existing = [];
        foreach ($service->getMedia() as $media) {
            $existing[$media->getType().'|'.$media->getPath()] = $media;
        }
        if ('' !== $cover && !isset($existing[ActivityPresenter::MEDIA_COVER.'|'.$cover])) {
            $service->addMedia((new Media())->setPath($cover)->setType(ActivityPresenter::MEDIA_COVER)->setPosition(0));
        }
        foreach ($gallery as $i => $path) {
            $media = $existing[ActivityPresenter::MEDIA_GALLERY.'|'.$path] ?? null;
            if (null === $media) {
                $service->addMedia((new Media())->setPath($path)->setType(ActivityPresenter::MEDIA_GALLERY)->setPosition($i + 1));
            } else {
                $media->setPosition($i + 1);
            }
        }
    }

    /**
     * @param array<int, mixed> $rows
     */
    private function syncPackages(Service $service, array $rows): void
    {
        $current = array_values($service->getPackages()->toArray());
        foreach (array_values($rows) as $i => $row) {
            $package = $current[$i] ?? null;
            if (null === $package) {
                $package = (new ServicePackage())->setCurrency('EUR');
                $service->addPackage($package);
            }
            $package
                ->setName(mb_substr((string) $row['name'], 0, 120))
                ->setDescription('' !== (string) $row['description'] ? mb_substr((string) $row['description'], 0, 255) : null)
                ->setPrice(number_format((float) $row['price'], 2, '.', ''))
                ->setPricingUnit(PricingUnit::tryFrom((string) $row['unit']) ?? PricingUnit::PerPerson);
        }
        foreach (\array_slice($current, \count($rows)) as $extra) {
            $service->removePackage($extra);
        }
    }

    /** @return list<string> une ligne par élément, puces et lignes vides retirées */
    public static function lines(string $text): array
    {
        $out = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim((string) preg_replace('/^[\s\-•*·]+/u', '', $line));
            if ('' !== $line) {
                $out[] = mb_substr($line, 0, 200);
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $d */
    private static function str(array $d, string $key, int $max): ?string
    {
        $value = trim((string) ($d[$key] ?? ''));

        return '' !== $value ? mb_substr($value, 0, $max) : null;
    }

    private function uniqueSlug(string $title): string
    {
        $base = strtolower((string) $this->slugger->slug($title)) ?: 'activite';
        $slug = $base;
        for ($i = 2; null !== $this->services->findOneBy(['slug' => $slug]); ++$i) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
