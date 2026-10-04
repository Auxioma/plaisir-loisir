<?php

declare(strict_types=1);

namespace App\Catalog\Service;

use App\Catalog\Entity\Promotion;
use App\Catalog\Entity\Service;
use App\Catalog\Enum\PromotionKind;
use App\Catalog\Enum\ServiceStatus;
use App\Catalog\Presenter\ActivityPresenter;
use App\Catalog\Repository\PromotionRepository;
use App\Catalog\Repository\ServiceRepository;

/**
 * « Offres du moment » (maquette offres_moments.jpeg, 04/10) : les offres en
 * cours des professionnels (Promotion), mises en forme pour les cartes, avec
 * les filtres de la colonne de gauche.
 *
 * Une offre « toutes activités » (sans activité) se décline sur chaque
 * activité publiée du professionnel. Le prix affiché remisé est celui que
 * facture la réservation (BookingService::discount()).
 */
final class OfferCatalog
{
    /** Filtres « Type d'offre » de la maquette. */
    public const TYPES = ['special' => 'Offres spéciales', 'flash' => 'Ventes flash', 'pack' => 'Pack & combo', 'nouveau' => 'Nouveautés'];

    /** Filtres « Disponibilité » de la maquette. */
    public const AVAILABILITY = ['maintenant' => 'Réservable maintenant', 'derniere-minute' => 'Dernière minute', 'semaine' => 'Cette semaine', 'mois' => 'Ce mois-ci'];

    public const SORTS = ['meilleures' => 'Meilleures offres', 'fin' => 'Se terminent bientôt', 'prix' => 'Prix croissant', 'recentes' => 'Plus récentes'];

    public function __construct(
        private readonly PromotionRepository $promotions,
        private readonly ServiceRepository $services,
        private readonly ActivityPresenter $presenter,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $rows = [];

        foreach ($this->promotions->findRunningPublic($now) as $promotion) {
            $targets = null !== $promotion->getService()
                ? [$promotion->getService()]
                : array_filter(
                    null !== $promotion->getProvider() ? $this->services->findForProvider($promotion->getProvider()) : [],
                    static fn (Service $s): bool => ServiceStatus::Published === $s->getStatus(),
                );

            foreach ($targets as $service) {
                $rows[] = $this->row($promotion, $service, $now);
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>>                                                                                            $rows
     * @param array{categorie?: string, prix?: int, reduction?: list<int>, dispo?: list<string>, type?: list<string>, tri?: string} $filters
     *
     * @return list<array<string, mixed>>
     */
    public function filter(array $rows, array $filters): array
    {
        $rows = array_values(array_filter($rows, static function (array $r) use ($filters): bool {
            if (($filters['categorie'] ?? '') !== '' && $r['categorySlug'] !== $filters['categorie']) {
                return false;
            }
            if (isset($filters['prix']) && $filters['prix'] < 500 && null !== $r['price'] && $r['price'] > $filters['prix']) {
                return false;
            }
            if ([] !== ($filters['reduction'] ?? []) && ($r['discount'] ?? 0) < min($filters['reduction'])) {
                return false;
            }
            foreach ($filters['dispo'] ?? [] as $d) {
                $ok = match ($d) {
                    'derniere-minute' => $r['hoursLeft'] <= 48,
                    'semaine' => $r['hoursLeft'] <= 24 * 7,
                    'mois' => $r['hoursLeft'] <= 24 * 31,
                    default => true,
                };
                if (!$ok) {
                    return false;
                }
            }
            if ([] !== ($filters['type'] ?? []) && [] === array_intersect($filters['type'], $r['types'])) {
                return false;
            }

            return true;
        }));

        usort($rows, match ($filters['tri'] ?? 'meilleures') {
            'fin' => static fn (array $a, array $b): int => $a['endsAt'] <=> $b['endsAt'],
            'prix' => static fn (array $a, array $b): int => ($a['price'] ?? \PHP_INT_MAX) <=> ($b['price'] ?? \PHP_INT_MAX),
            'recentes' => static fn (array $a, array $b): int => $b['startsAt'] <=> $a['startsAt'],
            default => static fn (array $a, array $b): int => [$b['discount'] ?? 0, $a['endsAt']] <=> [$a['discount'] ?? 0, $b['endsAt']],
        });

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Promotion $promotion, Service $service, \DateTimeImmutable $now): array
    {
        $card = $this->presenter->card($service, true);
        $discount = PromotionKind::Reduction === $promotion->getKind() ? $promotion->getDiscountPercent() : null;
        $full = null !== $card['price'] ? (float) $card['price'] : null;
        $price = null !== $full && null !== $discount ? round($full * (100 - $discount) / 100, 2) : $full;
        $hoursLeft = max(0, intdiv($promotion->getEndsAt()->getTimestamp() - $now->getTimestamp(), 3600));

        $types = [];
        if (PromotionKind::Special === $promotion->getKind()) {
            $types[] = 'special';
        }
        if (PromotionKind::TwoForOne === $promotion->getKind()) {
            $types[] = 'pack';
        }
        if ($hoursLeft <= 72) {
            $types[] = 'flash';
        }
        if ($promotion->getStartsAt() >= $now->modify('-7 days')) {
            $types[] = 'nouveau';
        }

        return [
            'id' => (string) $promotion->getId(),
            'offer' => $promotion->getTitle(),
            'subtitle' => $promotion->getSubtitle(),
            'badge' => $promotion->getBadge(),
            'kind' => $promotion->getKind()->value,
            'discount' => $discount,
            'oldPrice' => $price !== $full ? $full : null,
            'price' => $price,
            'categorySlug' => $service->getCategory()?->getSlug(),
            'startsAt' => $promotion->getStartsAt(),
            'endsAt' => $promotion->getEndsAt(),
            'hoursLeft' => $hoursLeft,
            'types' => $types,
        ] + $card;
    }
}
