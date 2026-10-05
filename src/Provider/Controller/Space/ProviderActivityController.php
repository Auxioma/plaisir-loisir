<?php

declare(strict_types=1);

namespace App\Provider\Controller\Space;

use App\Catalog\Entity\Service;
use App\Catalog\Enum\ServiceStatus;
use App\Catalog\Repository\CategoryRepository;
use App\Catalog\Repository\ServiceRepository;
use App\Catalog\Service\ActivityDraftService;
use App\Catalog\Service\ActivityPublishingService;
use App\Provider\Service\ProviderSpace;
use App\Provider\StaticProviderSpace;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

/**
 * « Mes activités » — maquette profil_activites_professionnel.jpeg (02/10) :
 * liste filtrable (onglets de statut, recherche, catégorie, tri), actions
 * en ligne et groupées, export CSV, création et modification d'une activité.
 *
 * Publication : le professionnel SOUMET son activité (statut « En attente »),
 * l'équipe la valide dans le back-office (ServiceCrudController, action
 * « Valider »). Une activité suspendue par l'équipe n'est plus modifiable.
 */
#[IsGranted('ROLE_PROVIDER')]
final class ProviderActivityController extends AbstractProviderSpaceController
{
    private const PER_PAGE = 6;

    private const TABS = [
        'toutes' => null,
        'publiees' => ServiceStatus::Published,
        'en-attente' => ServiceStatus::Pending,
        'brouillons' => ServiceStatus::Draft,
        'suspendues' => ServiceStatus::Suspended,
    ];

    public function __construct(
        private readonly ProviderSpace $space,
        private readonly ServiceRepository $services,
        private readonly CategoryRepository $categories,
        private readonly ActivityPublishingService $publishing,
        private readonly EntityManagerInterface $entityManager,
        private readonly ActivityDraftService $drafts,
    ) {
    }

    #[Route(path: ['fr' => '/pro/activites', 'en' => '/en/pro/activities'], name: 'app_pro_activities')]
    public function index(Request $request): Response
    {
        $provider = $this->currentProvider();
        $all = array_values(array_filter($this->space->services($provider), static fn (Service $s): bool => ServiceStatus::Archived !== $s->getStatus()));
        $bookings = $this->space->bookings($provider);
        $reviews = $this->space->reviews($provider);

        $tab = (string) $request->query->get('onglet', 'toutes');
        if (!\array_key_exists($tab, self::TABS)) {
            $tab = 'toutes';
        }
        $query = trim((string) $request->query->get('q', ''));
        $category = (string) $request->query->get('categorie', '');
        $status = (string) $request->query->get('statut', '');
        $sort = (string) $request->query->get('tri', 'recentes');

        $counts = [];
        foreach (self::TABS as $key => $value) {
            $counts[$key] = null === $value ? \count($all) : \count(array_filter($all, static fn (Service $s): bool => $s->getStatus() === $value));
        }

        $filtered = array_filter($all, static function (Service $s) use ($tab, $query, $category, $status): bool {
            $wanted = self::TABS[$tab];

            return (null === $wanted || $s->getStatus() === $wanted)
                && ('' === $status || $s->getStatus()->value === $status)
                && ('' === $category || $s->getCategory()?->getSlug() === $category || $s->getCategory()?->getParent()?->getSlug() === $category)
                && ('' === $query || false !== mb_stripos($s->getTitle().' '.$s->getCity().' '.$s->getPlaceLabel(), $query));
        });

        $rows = [];
        foreach ($filtered as $service) {
            $rows[] = ['service' => $service, 'cover' => ProviderSpace::cover($service)] + $this->space->activityRow($service, $bookings, $reviews);
        }

        usort($rows, match ($sort) {
            'anciennes' => static fn (array $a, array $b): int => $a['service']->getCreatedAt() <=> $b['service']->getCreatedAt(),
            'reservations' => static fn (array $a, array $b): int => $b['bookings'] <=> $a['bookings'],
            'vues' => static fn (array $a, array $b): int => $b['views'] <=> $a['views'],
            'titre' => static fn (array $a, array $b): int => strcasecmp($a['service']->getTitle(), $b['service']->getTitle()),
            default => static fn (array $a, array $b): int => $b['service']->getCreatedAt() <=> $a['service']->getCreatedAt(),
        });

        $total = \count($rows);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($pages, max(1, $request->query->getInt('page', 1)));

        return $this->renderSpace('provider/space/activities.html.twig', 'Mes activités', [
            'rows' => \array_slice($rows, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'counts' => $counts,
            'tab' => $tab,
            'query' => $query,
            'category' => $category,
            'status' => $status,
            'sort' => $sort,
            'categories' => $this->categories->findRoots(),
            'statuses' => [ServiceStatus::Published, ServiceStatus::Pending, ServiceStatus::Draft, ServiceStatus::Suspended],
            'tips' => StaticProviderSpace::tips()['activities'],
        ]);
    }

    #[Route(path: ['fr' => '/pro/activites/export', 'en' => '/en/pro/activities/export'], name: 'app_pro_activities_export')]
    public function export(): Response
    {
        $provider = $this->currentProvider();
        $bookings = $this->space->bookings($provider);
        $reviews = $this->space->reviews($provider);
        $rows = [];
        foreach ($this->space->services($provider) as $service) {
            $stats = $this->space->activityRow($service, $bookings, $reviews);
            $rows[] = [
                $service->getTitle(),
                $service->getCategory()?->getName() ?? '',
                $service->getCity() ?? $service->getPlaceLabel() ?? '',
                $service->getStatus()->label(),
                $service->getCreatedAt()?->format('d/m/Y') ?? '',
                $service->getDurationLabel() ?? '',
                null !== ($price = ProviderSpace::price($service)) ? number_format($price, 2, ',', '') : '',
                $stats['views'],
                $stats['bookings'],
                null !== $stats['rating'] ? number_format($stats['rating'], 1, ',', '') : '',
            ];
        }

        return self::csv('mes-activites-'.date('Y-m-d').'.csv', ['Activité', 'Catégorie', 'Lieu', 'Statut', 'Créée le', 'Durée', 'Prix (€)', 'Vues', 'Réservations', 'Note'], $rows);
    }

    /**
     * Actions en ligne et groupées : soumettre, repasser en brouillon,
     * dupliquer, supprimer (suppression logique).
     */
    #[Route(path: ['fr' => '/pro/activites/action', 'en' => '/en/pro/activities/action'], name: 'app_pro_activities_action', methods: ['POST'])]
    public function action(Request $request): Response
    {
        if (!$this->csrfOk($request)) {
            return $this->redirectToRoute('app_pro_activities');
        }

        $ids = $request->request->all('ids');
        if ([] === $ids && $request->request->has('id')) {
            $ids = [(string) $request->request->get('id')];
        }
        $action = (string) $request->request->get('action', '');
        $done = 0;
        $errors = [];

        foreach ($ids as $id) {
            $service = $this->own((string) $id);
            try {
                match ($action) {
                    'soumettre' => $this->publishing->submit($service),
                    'brouillon' => $this->publishing->unpublish($service),
                    'dupliquer' => $this->duplicate($service),
                    'supprimer' => $this->softDelete($service),
                    default => throw new \InvalidArgumentException('Action inconnue.'),
                };
                ++$done;
            } catch (\InvalidArgumentException $e) {
                $errors[$e->getMessage()] = true;
            }
        }

        if ($done > 0) {
            $this->addFlash('success', match ($action) {
                'soumettre' => 'Activité(s) envoyée(s) en validation : notre équipe les publie sous 24 h.',
                'brouillon' => 'Activité(s) repassée(s) en brouillon.',
                'dupliquer' => 'Activité(s) dupliquée(s) en brouillon.',
                default => 'Activité(s) supprimée(s).',
            });
        }
        foreach (array_keys($errors) as $message) {
            $this->addFlash('error', $message);
        }
        if (0 === $done && [] === $errors) {
            $this->addFlash('info', 'Sélectionnez au moins une activité.');
        }

        return $this->back($request, 'app_pro_activities');
    }

    private function duplicate(Service $source): void
    {
        // Même chemin que l'assistant : la copie reprend TOUT (fiche
        // détaillée, photos, formules), et repart en brouillon.
        $draft = $this->drafts->fromService($source);
        $draft['id'] = null;
        $draft['title'] = mb_substr($source->getTitle().' (copie)', 0, 180);
        $this->drafts->persist($draft, $this->currentProvider());
    }

    private function softDelete(Service $service): void
    {
        $service->softDelete();
        $this->entityManager->flush();
    }

    private function own(string $id): Service
    {
        $service = Ulid::isValid($id) ? $this->services->find(Ulid::fromString($id)) : null;

        if (null === $service || $service->isDeleted() || $service->getProvider() !== $this->currentProvider()) {
            throw $this->createNotFoundException('Activité introuvable.');
        }

        return $service;
    }
}
