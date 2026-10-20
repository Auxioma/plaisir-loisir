<?php

declare(strict_types=1);

namespace App\Provider\Controller\Space;

use App\Payment\Entity\Payment;
use App\Payment\Enum\PaymentStatus;
use App\Provider\Service\ProviderPaymentMethods;
use App\Provider\Service\ProviderSpace;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * « Revenus & Paiements » — maquette profil_Revenus&Paiements_professionnel.jpeg
 * (02/10) : chiffres du mois choisi comparés à un autre mois, courbe
 * journalière ou hebdomadaire, règlements, résumé et moyens de paiement
 * proposés aux clients.
 *
 * Encaissement direct (17/10, demande du client) : le client règle le
 * professionnel lui-même ; TrouveMoi n'encaisse rien, ne prélève aucune
 * commission et ne fait aucun versement. Les montants affichés sont les
 * règlements que le professionnel a déclarés reçus.
 */
#[IsGranted('ROLE_PROVIDER')]
final class ProviderRevenueController extends AbstractProviderSpaceController
{
    private const PER_PAGE = 10;

    public function __construct(
        private readonly ProviderSpace $space,
        private readonly ProviderPaymentMethods $paymentMethods,
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
        $points = static fn (array $list): array => array_map(static fn (Payment $p): array => [ProviderSpace::paidOn($p), (float) $p->getAmount()], $list);
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
            'gross' => $gross,
            'payment_methods' => $this->paymentMethods->summary($provider),
        ]);
    }

    #[Route(path: ['fr' => '/pro/revenus/export', 'en' => '/en/pro/revenue/export'], name: 'app_pro_revenue_export')]
    public function export(): Response
    {
        $statuses = ['pending' => 'À régler', 'paid' => 'Réglé', 'failed' => 'Échoué', 'refunded' => 'Remboursé'];
        $rows = array_map(static fn (Payment $p): array => [
            $p->getCreatedAt()?->format('d/m/Y H:i') ?? '',
            '#'.$p->getBooking()?->getReference(),
            $p->getBooking()?->getService()?->getTitle() ?? '',
            trim($p->getBooking()?->getClient()?->getFirstName().' '.$p->getBooking()?->getClient()?->getLastName()),
            number_format((float) $p->getAmount(), 2, ',', ''),
            $statuses[$p->getStatus()->value],
            $p->getPaidAt()?->format('d/m/Y') ?? '',
            $p->getMethodLabel() ?? '',
        ], $this->space->payments($this->currentProvider()));

        return self::csv('revenus-'.date('Y-m-d').'.csv', ['Demandée le', 'Réservation', 'Activité', 'Client', 'Montant (€)', 'Statut', 'Réglée le', 'Moyen de paiement'], $rows);
    }

    /**
     * « Paiement et encaissements » (20/10) : paiement en ligne, moyens
     * acceptés (Stripe CB, PayPal, espèces sur place) et précisions. Le
     * client paie toujours le professionnel directement.
     */
    #[Route(path: ['fr' => '/pro/revenus/moyens-de-paiement', 'en' => '/en/pro/revenue/payment-methods'], name: 'app_pro_payment_methods', methods: ['POST'])]
    public function paymentMethods(Request $request): Response
    {
        if (!$this->csrfOk($request)) {
            return $this->redirectToRoute('app_pro_settings', ['section' => 'paiement', 'modifier' => 1]);
        }

        try {
            $this->paymentMethods->update($this->currentProvider(), $request->request->all());
            $this->entityManager->flush();
            $this->addFlash('success', 'Vos paramètres de paiement ont été enregistrés.');

            return $this->redirectToRoute('app_pro_settings', ['section' => 'paiement']);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_pro_settings', ['section' => 'paiement', 'modifier' => 1]);
        }
    }

    private function month(string $value, \DateTimeImmutable $default): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m', $value);

        return false !== $date ? $date : $default->setTime(0, 0);
    }
}
