<?php

declare(strict_types=1);

namespace App\Provider\Controller\Space;

use App\Booking\Entity\Booking;
use App\Messaging\Entity\Conversation;
use App\Messaging\Repository\ConversationRepository;
use App\Payment\Entity\Payment;
use App\Provider\Service\ProviderSpace;
use App\Stats\Entity\PageView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * « Statistiques » — maquette profil_statistiques_professionnel.jpeg (02/10).
 *
 * Vues du profil et clics (consultations des fiches d'activités) viennent
 * de PageView ; réservations et revenus de Booking / Payment. La période
 * est comparée à une période de même durée choisie (mois précédent par
 * défaut).
 */
#[IsGranted('ROLE_PROVIDER')]
final class ProviderStatsController extends AbstractProviderSpaceController
{
    private const WEEKDAYS = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];

    public function __construct(
        private readonly ProviderSpace $space,
        private readonly ConversationRepository $conversations,
    ) {
    }

    #[Route(path: ['fr' => '/pro/statistiques', 'en' => '/en/pro/statistics'], name: 'app_pro_stats')]
    public function index(Request $request): Response
    {
        $data = $this->compute($request);

        return $this->renderSpace('provider/space/stats.html.twig', 'Statistiques', $data + [
            'promo' => ['title' => 'Développez votre activité', 'text' => 'Analysez vos performances et prenez les meilleures décisions.', 'cta' => 'Découvrir nos conseils', 'href' => $this->generateUrl('app_faq')],
        ]);
    }

    #[Route(path: ['fr' => '/pro/statistiques/export', 'en' => '/en/pro/statistics/export'], name: 'app_pro_stats_export')]
    public function export(Request $request): Response
    {
        $d = $this->compute($request);
        $rows = [];
        foreach ($d['series']['views'] as $i => $views) {
            $rows[] = [
                $d['from']->modify(sprintf('+%d days', $i))->format('d/m/Y'),
                (int) $views,
                (int) $d['series']['clicks'][$i],
                (int) $d['series']['bookings'][$i],
                number_format($d['series']['conversion'][$i], 1, ',', ''),
                number_format($d['series']['revenue'][$i], 2, ',', ''),
            ];
        }

        return self::csv('statistiques-'.$d['from']->format('Y-m-d').'-'.$d['to']->format('Y-m-d').'.csv', ['Jour', 'Vues du profil', 'Clics', 'Réservations', 'Conversion (%)', 'Revenus (€)'], $rows);
    }

    /** @return array<string, mixed> */
    private function compute(Request $request): array
    {
        $provider = $this->currentProvider();

        $from = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $request->query->get('du', '')) ?: new \DateTimeImmutable('first day of this month 00:00');
        $to = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $request->query->get('au', '')) ?: new \DateTimeImmutable('today');
        $to = $to->setTime(23, 59, 59);
        if ($to < $from) {
            [$from, $to] = [$to->setTime(0, 0), $from->setTime(23, 59, 59)];
        }
        $days = max(1, (int) $from->diff($to)->days + 1);
        if ($days > 366) {
            $from = $to->modify('-365 days')->setTime(0, 0);
            $days = 366;
        }
        $cFrom = \DateTimeImmutable::createFromFormat('!Y-m', (string) $request->query->get('comparer', '')) ?: $from->modify(sprintf('-%d days', $days));
        $cTo = $cFrom->modify(sprintf('+%d days', $days - 1))->setTime(23, 59, 59);

        $profileViews = $this->space->views($provider, $from, $to, PageView::KIND_PROFILE);
        $activityViews = $this->space->views($provider, $from, $to, PageView::KIND_ACTIVITY);
        $cProfile = \count($this->space->views($provider, $cFrom, $cTo, PageView::KIND_PROFILE));
        $cActivity = \count($this->space->views($provider, $cFrom, $cTo, PageView::KIND_ACTIVITY));

        $active = array_values(array_filter($this->space->bookings($provider), static fn (Booking $b): bool => \in_array($b->getStatus(), ProviderSpace::ACTIVE_STATUSES, true)));
        $bookings = $this->space->createdBetween($active, $from, $to);
        $cBookings = $this->space->createdBetween($active, $cFrom, $cTo);
        $payments = $this->space->payments($provider);
        $paid = $this->space->paidBetween($payments, $from, $to);
        $revenue = $this->space->sum($paid);
        $cRevenue = $this->space->sum($this->space->paidBetween($payments, $cFrom, $cTo));

        $conv = \count($activityViews) > 0 ? round(\count($bookings) / \count($activityViews) * 100, 1) : 0.0;
        $cConv = $cActivity > 0 ? round(\count($cBookings) / $cActivity * 100, 1) : 0.0;

        $viewPoints = static fn (array $list): array => array_map(static fn (PageView $v): array => [$v->getViewedAt(), 1], $list);
        $sViews = $this->space->daily($viewPoints($profileViews), $from, $days);
        $sClicks = $this->space->daily($viewPoints($activityViews), $from, $days);
        $sBookings = $this->space->daily(array_map(static fn (Booking $b): array => [$b->getCreatedAt(), 1], $bookings), $from, $days);
        $sRevenue = $this->space->daily(array_map(static fn (Payment $p): array => [$p->getCreatedAt(), (float) $p->getAmount()], $paid), $from, $days);
        $sConv = array_map(static fn (float $c, float $b): float => $c > 0 ? round($b / $c * 100, 1) : 0.0, $sClicks, $sBookings);

        // Sources de trafic (toutes consultations confondues).
        $sources = array_fill_keys(array_keys(PageView::SOURCES), 0);
        foreach (array_merge($profileViews, $activityViews) as $v) {
            ++$sources[$v->getSource()];
        }
        $sourcesTotal = max(1, array_sum($sources));

        // Performance par activité.
        $metric = (string) $request->query->get('mesure', 'reservations');
        $perActivity = [];
        foreach ($this->space->services($provider) as $service) {
            $n = \count(array_filter($bookings, static fn (Booking $b): bool => $b->getService() === $service));
            $c = \count(array_filter($cBookings, static fn (Booking $b): bool => $b->getService() === $service));
            $value = match ($metric) {
                'vues' => \count(array_filter($activityViews, static fn (PageView $v): bool => $v->getService() === $service)),
                'revenus' => $this->space->sum(array_filter($paid, static fn (Payment $p): bool => $p->getBooking()?->getService() === $service)),
                default => $n,
            };
            $perActivity[] = ['service' => $service, 'value' => $value, 'trend' => $this->space->trend($n, $c)];
        }
        usort($perActivity, static fn (array $a, array $b): int => $b['value'] <=> $a['value']);
        $perActivity = \array_slice(array_values(array_filter($perActivity, static fn (array $r): bool => $r['value'] > 0)), 0, 5);

        // Périodes les plus performantes (réservations de la période).
        $weekday = [];
        $hours = [];
        foreach ($bookings as $b) {
            $at = $b->getStartsAt() ?? $b->getCreatedAt();
            if (null !== $at) {
                $weekday[(int) $at->format('N')] = ($weekday[(int) $at->format('N')] ?? 0) + 1;
                $band = intdiv((int) $at->format('G'), 4) * 4;
                $hours[$band] = ($hours[$band] ?? 0) + 1;
            }
        }
        arsort($weekday);
        arsort($hours);
        $months = [];
        foreach ($active as $b) {
            $months[$b->getCreatedAt()?->format('Y-m') ?? ''] = ($months[$b->getCreatedAt()?->format('Y-m') ?? ''] ?? 0) + 1;
        }
        arsort($months);
        $bestMonth = array_key_first($months);

        $inquiries = array_filter(
            $this->conversations->findForUser($this->currentUser()),
            static fn (Conversation $c): bool => $c->getProvider() === $provider && $c->getCreatedAt() >= $from && $c->getCreatedAt() <= $to,
        );

        $compareMonths = [];
        for ($i = 1; $i <= 12; ++$i) {
            $compareMonths[] = new \DateTimeImmutable('first day of this month')->modify(sprintf('-%d months', $i));
        }

        return [
            'from' => $from,
            'to' => $to,
            'days' => $days,
            'compare_from' => $cFrom,
            'compare_months' => $compareMonths,
            'compare_value' => (string) $request->query->get('comparer', ''),
            'metric' => $metric,
            'tiles' => [
                'views' => ['value' => \count($profileViews), 'trend' => $this->space->trend(\count($profileViews), $cProfile)],
                'clicks' => ['value' => \count($activityViews), 'trend' => $this->space->trend(\count($activityViews), $cActivity)],
                'bookings' => ['value' => \count($bookings), 'trend' => $this->space->trend(\count($bookings), \count($cBookings))],
                'conversion' => ['value' => $conv, 'delta' => round($conv - $cConv, 1)],
                'revenue' => ['value' => $revenue, 'trend' => $this->space->trend($revenue, $cRevenue)],
            ],
            'series' => ['views' => $sViews, 'clicks' => $sClicks, 'bookings' => $sBookings, 'conversion' => $sConv, 'revenue' => $sRevenue],
            'breakdown' => $this->space->breakdown(array_map(static fn (Booking $b): string => $b->getService()?->getTitle() ?? 'Autres', $bookings), 4),
            'sources' => array_map(static fn (string $key, int $n): array => ['label' => PageView::SOURCES[$key], 'count' => $n, 'percent' => (int) round($n / $sourcesTotal * 100)], array_keys($sources), $sources),
            'per_activity' => $perActivity,
            'per_activity_max' => max(1, $perActivity[0]['value'] ?? 1),
            'inquiries' => \count($inquiries),
            'best' => [
                'weekday' => null !== ($k = array_key_first($weekday)) ? self::WEEKDAYS[$k] : null,
                'hours' => null !== ($h = array_key_first($hours)) ? sprintf('%dh - %dh', $h, $h + 4) : null,
                'month' => null !== $bestMonth && '' !== $bestMonth ? \DateTimeImmutable::createFromFormat('!Y-m', $bestMonth) : null,
            ],
        ];
    }
}
