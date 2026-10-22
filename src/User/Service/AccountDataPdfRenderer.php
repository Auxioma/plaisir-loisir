<?php

declare(strict_types=1);

namespace App\User\Service;

use App\User\Entity\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final class AccountDataPdfRenderer
{
    public function __construct(private readonly AccountDataExporter $exporter, private readonly Environment $twig)
    {
    }

    public function response(User $user): Response
    {
        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->loadHtml($this->twig->render('account/data_export_pdf.html.twig', ['data' => $this->exporter->export($user)]));
        $pdf->setPaper('A4');
        $pdf->render();

        return new Response((string) $pdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, 'mes-donnees-trouvemoi.pdf'),
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
