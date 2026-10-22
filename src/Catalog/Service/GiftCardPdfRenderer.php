<?php

declare(strict_types=1);

namespace App\Catalog\Service;

use App\Catalog\Entity\GiftCard;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final class GiftCardPdfRenderer
{
    public function __construct(private readonly Environment $twig)
    {
    }

    public function response(GiftCard $card): Response
    {
        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->loadHtml($this->twig->render('gift/card_pdf.html.twig', ['card' => $card]));
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();

        return new Response((string) $pdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, 'bon-cadeau-'.$card->getCode().'.pdf'),
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
