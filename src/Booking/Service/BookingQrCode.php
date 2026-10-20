<?php

declare(strict_types=1);

namespace App\Booking\Service;

use App\Booking\Entity\Booking;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * QR de réservation (20/10, maquettes reservation_confirmee_*) : le client le
 * présente le jour de l'activité ; scanné par l'appareil photo du
 * professionnel, il ouvre la page de contrôle de la réservation dans son
 * espace pro (connexion exigée, réservation de SES activités seulement).
 */
final class BookingQrCode
{
    public function __construct(
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    /** Adresse encodée dans le QR. */
    public function target(Booking $booking): string
    {
        return $this->urls->generate('app_pro_booking_check', ['id' => (string) $booking->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /** Image SVG en data URI, pour l'afficher dans la page. */
    public function dataUri(Booking $booking): string
    {
        return (new QRCode(new QROptions(['outputBase64' => true, 'eccLevel' => QRCode::ECC_M, 'addQuietzone' => true])))->render($this->target($booking));
    }

    /** Image PNG à télécharger. */
    public function png(Booking $booking): string
    {
        return (new QRCode(new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64' => false,
            'eccLevel' => QRCode::ECC_M,
            'scale' => 12,
            'addQuietzone' => true,
        ])))->render($this->target($booking));
    }
}
