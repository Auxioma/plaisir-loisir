<?php

declare(strict_types=1);

namespace App\Provider\Controller\Space;

use App\Provider\Service\ProviderSpace;
use App\Provider\StaticProviderSpace;
use App\Review\Entity\Review;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * « Avis & Évaluations » — maquette profil_Avis&Evaluations_professionnel.jpeg
 * (02/10) : chiffres clés, onglets positifs / neutres / négatifs, filtres,
 * réponse publique (ReviewController::reply), répartition des notes,
 * évolution mensuelle de la note moyenne, export CSV.
 */
#[IsGranted('ROLE_PROVIDER')]
final class ProviderReviewController extends AbstractProviderSpaceController
{
    private const PER_PAGE = 5;

    public function __construct(
        private readonly ProviderSpace $space,
    ) {
    }

    #[Route(path: ['fr' => '/pro/avis', 'en' => '/en/pro/reviews'], name: 'app_pro_reviews')]
    public function index(Request $request): Response
    {
        $provider = $this->currentProvider();
        $all = $this->space->reviews($provider);

        $tab = (string) $request->query->get('onglet', 'tous');
        $query = trim((string) $request->query->get('q', ''));
        $activity = (string) $request->query->get('activite', '');
        $note = $request->query->getInt('note');
        $sort = (string) $request->query->get('tri', 'recents');

        $kind = static fn (Review $r): string => $r->getRating() >= 4 ? 'positifs' : (3 === $r->getRating() ? 'neutres' : 'negatifs');
        $counts = ['tous' => \count($all), 'positifs' => 0, 'neutres' => 0, 'negatifs' => 0];
        foreach ($all as $r) {
            ++$counts[$kind($r)];
        }

        $rows = array_values(array_filter($all, static function (Review $r) use ($tab, $kind, $query, $activity, $note): bool {
            $text = $r->getComment().' '.$r->getAuthor()?->getFirstName().' '.$r->getAuthor()?->getLastName().' '.$r->getService()?->getTitle();

            return ('tous' === $tab || $kind($r) === $tab)
                && ('' === $activity || (string) $r->getService()?->getId() === $activity)
                && (0 === $note || $r->getRating() === $note)
                && ('' === $query || false !== mb_stripos($text, $query));
        }));
        usort($rows, match ($sort) {
            'anciens' => static fn (Review $a, Review $b): int => $a->getCreatedAt() <=> $b->getCreatedAt(),
            'meilleurs' => static fn (Review $a, Review $b): int => [$b->getRating(), $b->getCreatedAt()] <=> [$a->getRating(), $a->getCreatedAt()],
            'moins-bons' => static fn (Review $a, Review $b): int => [$a->getRating(), $b->getCreatedAt()] <=> [$b->getRating(), $a->getCreatedAt()],
            'sans-reponse' => static fn (Review $a, Review $b): int => [null !== $a->getProviderReply(), $b->getCreatedAt()] <=> [null !== $b->getProviderReply(), $a->getCreatedAt()],
            default => static fn (Review $a, Review $b): int => $b->getCreatedAt() <=> $a->getCreatedAt(),
        });

        $total = \count($rows);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($pages, max(1, $request->query->getInt('page', 1)));

        // Répartition 5 → 1 étoiles.
        $distribution = [];
        foreach ([5, 4, 3, 2, 1] as $stars) {
            $n = \count(array_filter($all, static fn (Review $r): bool => $r->getRating() === $stars));
            $distribution[$stars] = ['count' => $n, 'percent' => $counts['tous'] > 0 ? (int) round($n / $counts['tous'] * 100) : 0];
        }

        // Note moyenne cumulée à la fin de chacun des 6 derniers mois.
        $evolution = [];
        $month = new \DateTimeImmutable('first day of this month 00:00');
        for ($i = 5; $i >= 0; --$i) {
            $end = $month->modify(sprintf('-%d months', $i))->modify('last day of this month 23:59:59');
            $until = array_values(array_filter($all, static fn (Review $r): bool => $r->getCreatedAt() <= $end));
            $evolution[] = ['label' => $end, 'value' => $this->space->average($until)];
        }

        $thisMonth = new \DateTimeImmutable('first day of this month 00:00');
        $beforeMonth = array_values(array_filter($all, static fn (Review $r): bool => $r->getCreatedAt() < $thisMonth));
        $average = $this->space->average($all);
        $previousAverage = $this->space->average($beforeMonth);

        $services = [];
        foreach ($this->space->services($provider) as $service) {
            $services[(string) $service->getId()] = $service->getTitle();
        }

        return $this->renderSpace('provider/space/reviews.html.twig', 'Avis & Évaluations', [
            'rows' => \array_slice($rows, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'counts' => $counts,
            'tab' => $tab,
            'query' => $query,
            'activity' => $activity,
            'note' => $note,
            'sort' => $sort,
            'services' => $services,
            'average' => $average,
            'average_delta' => null !== $average && null !== $previousAverage ? round($average - $previousAverage, 1) : null,
            'this_month' => \count($all) - \count($beforeMonth),
            'distribution' => $distribution,
            'evolution' => $evolution,
            'tips' => StaticProviderSpace::tips()['reviews'],
            'promo' => [
                'title' => 'Améliorez votre visibilité',
                'text' => 'Collectez plus d’avis positifs et augmentez votre visibilité auprès des clients.',
                'cta' => 'Découvrir nos conseils',
                'href' => $this->generateUrl('app_faq'),
            ],
        ]);
    }

    #[Route(path: ['fr' => '/pro/avis/export', 'en' => '/en/pro/reviews/export'], name: 'app_pro_reviews_export', priority: 10)]
    public function export(): Response
    {
        $rows = array_map(static fn (Review $r): array => [
            $r->getCreatedAt()?->format('d/m/Y') ?? '',
            trim($r->getAuthor()?->getFirstName().' '.$r->getAuthor()?->getLastName()),
            $r->getService()?->getTitle() ?? '',
            $r->getRating(),
            $r->getComment() ?? '',
            $r->getProviderReply() ?? '',
        ], $this->space->reviews($this->currentProvider()));

        return self::csv('avis-'.date('Y-m-d').'.csv', ['Date', 'Client', 'Activité', 'Note', 'Commentaire', 'Votre réponse'], $rows);
    }
}
