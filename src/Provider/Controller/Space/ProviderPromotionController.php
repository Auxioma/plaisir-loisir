<?php

declare(strict_types=1);

namespace App\Provider\Controller\Space;

use App\Booking\Entity\Booking;
use App\Catalog\Entity\Promotion;
use App\Catalog\Entity\Service;
use App\Catalog\Enum\PromotionKind;
use App\Catalog\Repository\PromotionRepository;
use App\Provider\Service\ProviderSpace;
use App\Provider\StaticProviderSpace;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

/**
 * « Offres & Promotions » — maquette profil_Offres&Promotions_professionnel.jpeg
 * (02/10) : création et suivi des offres (réduction, 2 pour 1…) sur une
 * activité ou sur toutes. Une offre active s'affiche sur la fiche de
 * l'activité (ActivityController::show) ; vues et clics y sont comptés,
 * les réservations sont celles de l'activité pendant la période de l'offre.
 */
#[IsGranted('ROLE_PROVIDER')]
final class ProviderPromotionController extends AbstractProviderSpaceController
{
    private const PER_PAGE = 6;

    public function __construct(
        private readonly ProviderSpace $space,
        private readonly PromotionRepository $promotions,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route(path: ['fr' => '/pro/offres', 'en' => '/en/pro/offers'], name: 'app_pro_promotions')]
    public function index(Request $request): Response
    {
        $provider = $this->currentProvider();
        $all = $this->promotions->findForProvider($provider);
        $bookings = $this->space->bookings($provider);
        $now = new \DateTimeImmutable();

        $query = trim((string) $request->query->get('q', ''));
        $status = (string) $request->query->get('statut', '');
        $activity = (string) $request->query->get('activite', '');
        $sort = (string) $request->query->get('tri', 'recentes');

        $rows = [];
        foreach ($all as $promo) {
            $rows[] = ['p' => $promo, 'status' => $promo->getStatus($now), 'bookings' => $this->bookingsFor($promo, $bookings)];
        }

        $filtered = array_values(array_filter($rows, static fn (array $r): bool => ('' === $status || $r['status'] === $status)
            && ('' === $activity || (string) $r['p']->getService()?->getId() === $activity)
            && ('' === $query || false !== mb_stripos($r['p']->getTitle().' '.$r['p']->getSubtitle().' '.$r['p']->getService()?->getTitle(), $query))));
        usort($filtered, match ($sort) {
            'anciennes' => static fn (array $a, array $b): int => $a['p']->getStartsAt() <=> $b['p']->getStartsAt(),
            'performance' => static fn (array $a, array $b): int => $b['bookings'] <=> $a['bookings'],
            default => static fn (array $a, array $b): int => $b['p']->getStartsAt() <=> $a['p']->getStartsAt(),
        });

        $total = \count($filtered);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($pages, max(1, $request->query->getInt('page', 1)));

        $monthStart = new \DateTimeImmutable('first day of this month 00:00');
        $views = array_sum(array_map(static fn (Promotion $p): int => $p->getViewsCount(), $all));
        $clicks = array_sum(array_map(static fn (Promotion $p): int => $p->getClicksCount(), $all));
        $generated = array_sum(array_column($rows, 'bookings'));

        $kinds = array_map(static fn (Promotion $p): string => $p->getKind()->label(), $all);

        $services = [];
        foreach ($this->space->services($provider) as $service) {
            $services[(string) $service->getId()] = $service->getTitle();
        }

        return $this->renderSpace('provider/space/promotions.html.twig', 'Offres & Promotions', [
            'rows' => \array_slice($filtered, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'query' => $query,
            'status' => $status,
            'activity' => $activity,
            'sort' => $sort,
            'services' => $services,
            'tiles' => [
                'active' => \count(array_filter($rows, static fn (array $r): bool => Promotion::STATUS_ACTIVE === $r['status'])),
                'active_new' => \count(array_filter($all, static fn (Promotion $p): bool => $p->getCreatedAt() >= $monthStart)),
                'scheduled' => \count(array_filter($rows, static fn (array $r): bool => Promotion::STATUS_SCHEDULED === $r['status'])),
                'views' => $views,
                'clicks' => $clicks,
                'bookings' => $generated,
                'conversion' => $views > 0 ? round($generated / $views * 100, 1) : null,
            ],
            'kinds' => $this->space->breakdown($kinds, 3),
            'kinds_total' => \count($all),
            'tips' => StaticProviderSpace::tips()['offers'],
            'promo' => ['title' => 'Boostez vos réservations', 'text' => 'Créez des offres attractives et attirez plus de clients.', 'cta' => 'Créer une offre', 'href' => $this->generateUrl('app_pro_promotions_new')],
        ]);
    }

    #[Route(path: ['fr' => '/pro/offres/nouvelle', 'en' => '/en/pro/offers/new'], name: 'app_pro_promotions_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        return $this->form($request, (new Promotion())->setProvider($this->currentProvider()), true);
    }

    #[Route(path: ['fr' => '/pro/offres/{id}/modifier', 'en' => '/en/pro/offers/{id}/edit'], name: 'app_pro_promotions_edit', methods: ['GET', 'POST'])]
    public function edit(string $id, Request $request): Response
    {
        return $this->form($request, $this->own($id), false);
    }

    #[Route(path: ['fr' => '/pro/offres/{id}/action', 'en' => '/en/pro/offers/{id}/action'], name: 'app_pro_promotions_action', methods: ['POST'])]
    public function action(string $id, Request $request): Response
    {
        $promo = $this->own($id);
        if ($this->csrfOk($request)) {
            match ((string) $request->request->get('action')) {
                'pause' => $promo->setPaused(true),
                'reprendre' => $promo->setPaused(false),
                'terminer' => $promo->setEndsAt(new \DateTimeImmutable('-1 minute')),
                'supprimer' => $promo->softDelete(),
                default => null,
            };
            $this->entityManager->flush();
            $this->addFlash('success', 'Offre mise à jour.');
        }

        return $this->back($request, 'app_pro_promotions');
    }

    private function form(Request $request, Promotion $promo, bool $isNew): Response
    {
        $services = array_values(array_filter($this->space->services($this->currentProvider()), static fn (Service $s): bool => !$s->isDeleted()));
        $errors = [];
        $values = [
            'title' => $promo->getTitle(),
            'subtitle' => $promo->getSubtitle() ?? '',
            'kind' => $promo->getKind()->value,
            'discount' => $promo->getDiscountPercent() ?? '',
            'service' => (string) $promo->getService()?->getId(),
            'startsAt' => $promo->getStartsAt()->format('Y-m-d'),
            'endsAt' => $promo->getEndsAt()->format('Y-m-d'),
        ];

        if ($request->isMethod('POST') && $this->csrfOk($request)) {
            foreach (array_keys($values) as $key) {
                $values[$key] = trim((string) $request->request->get($key, ''));
            }
            $kind = PromotionKind::tryFrom($values['kind']) ?? PromotionKind::Reduction;
            $start = \DateTimeImmutable::createFromFormat('!Y-m-d', $values['startsAt']);
            $end = \DateTimeImmutable::createFromFormat('!Y-m-d', $values['endsAt']);
            $service = null;
            foreach ($services as $s) {
                if ((string) $s->getId() === $values['service']) {
                    $service = $s;
                }
            }

            if ('' === $values['title']) {
                $errors['title'] = 'Donnez un nom à votre offre.';
            }
            if (PromotionKind::Reduction === $kind && ((int) $values['discount'] < 1 || (int) $values['discount'] > 90)) {
                $errors['discount'] = 'La réduction doit être comprise entre 1 et 90 %.';
            }
            if (false === $start || false === $end || $end < $start) {
                $errors['endsAt'] = 'La date de fin doit suivre la date de début.';
            }

            if ([] === $errors) {
                \assert(false !== $start && false !== $end);
                $promo->setTitle(mb_substr($values['title'], 0, 120))
                    ->setSubtitle(self::nullIfEmpty($values['subtitle'], 180))
                    ->setKind($kind)
                    ->setDiscountPercent(PromotionKind::Reduction === $kind ? (int) $values['discount'] : null)
                    ->setService($service)
                    ->setStartsAt($start)
                    ->setEndsAt($end->setTime(23, 59, 59));
                if ($isNew) {
                    $this->entityManager->persist($promo);
                }
                $this->entityManager->flush();
                $this->addFlash('success', $isNew ? 'Offre créée : elle s’affichera sur la fiche de l’activité pendant sa période.' : 'Offre mise à jour.');

                return $this->redirectToRoute('app_pro_promotions');
            }
        }

        return $this->renderSpace('provider/space/promotion_form.html.twig', 'Offres & Promotions', [
            'promotion' => $promo,
            'is_new' => $isNew,
            'values' => $values,
            'errors' => $errors,
            'services' => $services,
            'kinds' => PromotionKind::cases(),
        ], new Response(null, [] === $errors ? 200 : 422));
    }

    /**
     * Réservations de l'activité visée (ou de toutes) créées pendant la période de l'offre.
     *
     * @param list<Booking> $bookings
     */
    private function bookingsFor(Promotion $promo, array $bookings): int
    {
        return \count(array_filter($bookings, static fn (Booking $b): bool => (null === $promo->getService() || $b->getService() === $promo->getService())
            && \in_array($b->getStatus(), ProviderSpace::ACTIVE_STATUSES, true)
            && $b->getCreatedAt() >= $promo->getStartsAt() && $b->getCreatedAt() <= $promo->getEndsAt()));
    }

    private function own(string $id): Promotion
    {
        $promo = Ulid::isValid($id) ? $this->promotions->find(Ulid::fromString($id)) : null;
        if (null === $promo || $promo->isDeleted() || $promo->getProvider() !== $this->currentProvider()) {
            throw $this->createNotFoundException('Offre introuvable.');
        }

        return $promo;
    }
}
