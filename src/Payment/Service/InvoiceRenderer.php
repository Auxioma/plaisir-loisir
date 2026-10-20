<?php

declare(strict_types=1);

namespace App\Payment\Service;

use App\Booking\Entity\Booking;
use App\Legal\Repository\CompanyIdentityRepository;
use App\Payment\Entity\Payment;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * Facture / reçu d'une réservation réglée (20/10, maquettes
 * reservation_confirmee_client et _prestataire) : le même document pour le
 * client et pour le professionnel, en PDF. Le professionnel reste le vendeur
 * de la prestation ; TrouveMoi établit le document sans rien encaisser.
 */
final class InvoiceRenderer
{
    public function __construct(
        private readonly Environment $twig,
        private readonly CompanyIdentityRepository $companies,
        private readonly string $projectDir,
    ) {
    }

    /**
     * @param bool $download true : « Télécharger », false : « Voir » dans le navigateur
     *
     * @throws \InvalidArgumentException règlement non reçu
     */
    public function response(Payment $payment, bool $download): Response
    {
        if (!$payment->isPaid() || null === $payment->getInvoiceNumber()) {
            throw new \InvalidArgumentException('La facture sera disponible une fois la réservation réglée.');
        }

        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $options->setChroot($this->projectDir.'/assets/images');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->html($payment));
        $dompdf->setPaper('A4');
        $dompdf->render();

        $filename = sprintf('facture-%s.pdf', $payment->getInvoiceNumber());

        return new Response((string) $dompdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition($download ? HeaderUtils::DISPOSITION_ATTACHMENT : HeaderUtils::DISPOSITION_INLINE, $filename),
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function html(Payment $payment): string
    {
        $provider = $payment->getBooking()?->getService()?->getProvider();
        $logo = $this->projectDir.'/assets/images/logo-trouvemoi-horizontal.png';

        return $this->twig->render('payment/invoice_pdf.html.twig', [
            'payment' => $payment,
            'booking' => $payment->getBooking(),
            'provider' => $provider,
            'company' => null !== $provider ? $this->companies->findOneByProvider($provider) : null,
            'logo' => is_file($logo) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logo)) : null,
        ]);
    }

    /**
     * Billet de réservation PDF (20/10) : infos clés de l'activité, facture
     * (ou récapitulatif à régler sur place) et QR à présenter.
     *
     * @param string $qrPng image PNG du QR (BookingQrCode::png())
     */
    public function ticketResponse(Booking $booking, ?Payment $payment, string $qrPng): Response
    {
        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $options->setChroot($this->projectDir.'/assets/images');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->ticketHtml($booking, $payment, $qrPng));
        $dompdf->setPaper('A4');
        $dompdf->render();

        return new Response((string) $dompdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, sprintf('billet-%s.pdf', $booking->getReference())),
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function ticketHtml(Booking $booking, ?Payment $payment, string $qrPng): string
    {
        $logo = $this->projectDir.'/assets/images/logo-trouvemoi-horizontal.png';

        return $this->twig->render('payment/ticket_pdf.html.twig', [
            'booking' => $booking,
            'payment' => $payment,
            'provider' => $booking->getService()?->getProvider(),
            'qr' => 'data:image/png;base64,'.base64_encode($qrPng),
            'logo' => is_file($logo) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logo)) : null,
        ]);
    }
}
