<?php

declare(strict_types=1);

namespace App\Provider\Controller\Space;

use App\Payment\Entity\Payment;
use App\Payment\Enum\PaymentStatus;
use App\Provider\Service\ProviderSpace;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * « Revenus & Paiements » — maquette profil_Revenus&Paiements_professionnel.jpeg
 * (02/10) : chiffres du mois choisi comparés à un autre mois, courbe
 * journalière ou hebdomadaire, transactions, résumé financier (commission
 * TrouveMoi, frais de paiement, net), prochain versement et moyens de
 * versement du professionnel.
 */
#[IsGranted('ROLE_PROVIDER')]
final class ProviderRevenueController extends AbstractProviderSpaceController
{
    private const PER_PAGE = 10;

    public function __construct(
        private readonly ProviderSpace $space,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route(path: ['fr' => '/pro/revenus', 'en' => '/en/pro/revenue'], name: 'app_pro_revenue')]
    public function index(Request $request): Response
    {
        $provider = $this->currentProvider();
        $payments = $this->space->payments($provider);

        $month = $this->month((string) $request->query->get('mois', ''), new \DateTimeImmutable('first day of this month'));
        $compare = $this->month((string) $request->query->get('comparer', ''), $month->modify('-1 month'));
        $granularity = 'semaine' === $request->query->get('par') ? 'semaine' : 'jour';

        [$from, $to] = [$month, $month->modify('last day of this month 23:59:59')];
        [$cFrom, $cTo] = [$compare, $compare->modify('last day of this month 23:59:59')];

        $paid = $this->space->paidBetween($payments, $from, $to);
        $cPaid = $this->space->paidBetween($payments, $cFrom, $cTo);
        $gross = $this->space->sum($paid);
        $cGross = $this->space->sum($cPaid);
        $avg = [] !== $paid ? $gross / \count($paid) : 0.0;
        $cAvg = [] !== $cPaid ? $cGross / \count($cPaid) : 0.0;
        $pending = array_values(array_filter($payments, static fn (Payment $p): bool => PaymentStatus::Pending === $p->getStatus()));

        $daysIn = (int) $month->format('t');
        $points = static fn (array $list): array => array_map(static fn (Payment $p): array => [$p->getCreatedAt(), (float) $p->getAmount()], $list);
        $series = $this->space->daily($points($paid), $from, $daysIn);
        $cSeries = $this->space->daily($points($cPaid), $cFrom, (int) $compare->format('t'));
        if ('semaine' === $granularity) {
            $series = array_map('array_sum', array_chunk($series, 7));
            $cSeries = array_map('array_sum', array_chunk($cSeries, 7));
        }

        // « Voir toutes les transactions » : liste complète paginée.
        $showAll = $request->query->getBoolean('toutes');
        $page = max(1, $request->query->getInt('page', 1));
        $pages = max(1, (int) ceil(\count($payments) / self::PER_PAGE));
        $transactions = $showAll ? \array_slice($payments, (min($page, $pages) - 1) * self::PER_PAGE, self::PER_PAGE) : \array_slice($payments, 0, 5);

        $months = [];
        $cursor = new \DateTimeImmutable('first day of this month');
        for ($i = 0; $i < 12; ++$i) {
            $months[] = $cursor->modify(sprintf('-%d months', $i));
        }

        return $this->renderSpace('provider/space/revenue.html.twig', 'Revenus & Paiements', [
            'month' => $month,
            'compare' => $compare,
            'months' => $months,
            'granularity' => $granularity,
            'tiles' => [
                'gross' => ['value' => $gross, 'trend' => $this->space->trend($gross, $cGross)],
                'count' => ['value' => \count($paid), 'trend' => $this->space->trend(\count($paid), \count($cPaid))],
                'avg' => ['value' => $avg, 'trend' => $this->space->trend($avg, $cAvg)],
                'pending' => ['value' => $this->space->sum($pending), 'count' => \count($pending)],
            ],
            'series' => $series,
            'compare_series' => $cSeries,
            'transactions' => $transactions,
            'show_all' => $showAll,
            'page' => min($page, $pages),
            'pages' => $pages,
            'total_transactions' => \count($payments),
            'split' => $this->space->split($gross),
            'commission_rate' => ProviderSpace::COMMISSION_RATE * 100,
            'next_payout' => [
                'date' => new \DateTimeImmutable('first day of next month')->modify('+14 days'),
                'amount' => $this->space->split($this->space->sum($this->space->paidBetween($payments, new \DateTimeImmutable('first day of this month 00:00'), new \DateTimeImmutable())))['net'],
            ],
        ]);
    }

    #[Route(path: ['fr' => '/pro/revenus/export', 'en' => '/en/pro/revenue/export'], name: 'app_pro_revenue_export')]
    public function export(): Response
    {
        $methods = ['card' => 'Carte', 'paypal' => 'PayPal', 'transfer' => 'Virement'];
        $statuses = ['pending' => 'En attente', 'paid' => 'Payé', 'failed' => 'Échoué', 'refunded' => 'Remboursé'];
        $rows = array_map(function (Payment $p) use ($methods, $statuses): array {
            $split = $this->space->split((float) $p->getAmount());

            return [
                $p->getCreatedAt()?->format('d/m/Y H:i') ?? '',
                '#'.$p->getBooking()?->getReference(),
                $p->getBooking()?->getService()?->getTitle() ?? '',
                trim($p->getBooking()?->getClient()?->getFirstName().' '.$p->getBooking()?->getClient()?->getLastName()),
                number_format($split['gross'], 2, ',', ''),
                number_format($split['commission'], 2, ',', ''),
                number_format($split['fees'], 2, ',', ''),
                number_format($split['net'], 2, ',', ''),
                $statuses[$p->getStatus()->value],
                $methods[$p->getMethod()] ?? $p->getMethod(),
            ];
        }, $this->space->payments($this->currentProvider()));

        return self::csv('revenus-'.date('Y-m-d').'.csv', ['Date', 'Réservation', 'Activité', 'Client', 'Montant (€)', 'Commission (€)', 'Frais (€)', 'Net (€)', 'Statut', 'Type'], $rows);
    }

    /**
     * Moyens de versement : ajout / modification (IBAN, PayPal, carte),
     * choix du moyen par défaut, retrait.
     */
    #[Route(path: ['fr' => '/pro/revenus/moyens-de-paiement', 'en' => '/en/pro/revenue/payout-methods'], name: 'app_pro_revenue_payout', methods: ['POST'])]
    public function payout(Request $request): Response
    {
        if (!$this->csrfOk($request)) {
            return $this->back($request, 'app_pro_revenue');
        }

        $provider = $this->currentProvider();
        $type = (string) $request->request->get('type', '');
        $action = (string) $request->request->get('action', 'save');
        $value = trim((string) $request->request->get('value', ''));

        if ('default' === $action && \in_array($type, ['bank', 'paypal', 'card'], true)) {
            $configured = match ($type) {
                'bank' => null !== $provider->getPayoutIban(),
                'paypal' => null !== $provider->getPayoutPaypalEmail(),
                default => null !== $provider->getPayoutCardLast4(),
            };
            if ($configured) {
                $provider->setPayoutMethod($type);
                $this->addFlash('success', 'Moyen de versement par défaut mis à jour.');
            }
        } elseif ('remove' === $action) {
            match ($type) {
                'bank' => $provider->setPayoutIban(null),
                'paypal' => $provider->setPayoutPaypalEmail(null),
                'card' => $provider->setPayoutCardLast4(null),
                default => null,
            };
            $this->addFlash('success', 'Moyen de versement retiré.');
        } else {
            $error = null;
            match ($type) {
                'bank' => 1 === preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/', $iban = strtoupper(str_replace(' ', '', $value))) ? $provider->setPayoutIban($iban) : $error = 'IBAN invalide (ex. FR76 3000 6000 0112 3456 7890 189).',
                'paypal' => false !== filter_var($value, \FILTER_VALIDATE_EMAIL) ? $provider->setPayoutPaypalEmail(mb_substr($value, 0, 180)) : $error = 'Adresse PayPal invalide.',
                'card' => 1 === preg_match('/^\d{12,19}$/', $digits = preg_replace('/\D/', '', $value) ?? '') ? $provider->setPayoutCardLast4(substr($digits, -4)) : $error = 'Numéro de carte invalide.',
                default => $error = 'Choisissez un type de moyen de paiement.',
            };
            if (null !== $error) {
                $this->addFlash('error', $error);

                return $this->back($request, 'app_pro_revenue');
            }
            $this->addFlash('success', 'Moyen de versement enregistré.');
        }

        // Le moyen par défaut doit rester un moyen configuré.
        $available = array_filter(['bank' => $provider->getPayoutIban(), 'paypal' => $provider->getPayoutPaypalEmail(), 'card' => $provider->getPayoutCardLast4()]);
        if ([] !== $available && !isset($available[$provider->getPayoutMethod()])) {
            $provider->setPayoutMethod((string) array_key_first($available));
        }

        $this->entityManager->flush();

        return $this->back($request, 'app_pro_revenue');
    }

    private function month(string $value, \DateTimeImmutable $default): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m', $value);

        return false !== $date ? $date : $default->setTime(0, 0);
    }
}
