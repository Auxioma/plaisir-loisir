<?php

declare(strict_types=1);

namespace App\Event\Service;

use App\Event\Entity\Event;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final class EventDocumentRenderer
{
    public function __construct(
        private readonly Environment $twig,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /** @param array<string, mixed> $context */
    public function response(Event $event, string $link, bool $invitation, array $context = []): Response
    {
        $qr = (new QRCode(new QROptions([
            'outputInterface' => QRGdImagePNG::class, 'outputBase64' => true,
            'eccLevel' => QRCode::ECC_M, 'scale' => 6, 'addQuietzone' => true,
        ])))->render($link);
        $photos = [];
        foreach (array_filter(array_merge([$event->getImagePath()], $event->getGallery())) as $path) {
            $image = $this->image($path);
            if (null !== $image) {
                $photos[] = $image;
            }
        }
        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $options->setChroot($this->projectDir.'/public');
        $pdf = new Dompdf($options);
        $pdf->loadHtml($this->twig->render('event/document_pdf.html.twig', $context + [
            'entity' => $event, 'link' => $link, 'qr' => $qr,
            'invitation' => $invitation, 'photos' => $photos,
        ]));
        $pdf->setPaper('A4');
        $pdf->render();

        return new Response((string) $pdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT,
                ($invitation ? 'invitation-' : 'evenement-').$event->getSlug().'.pdf'),
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function image(string $path): ?string
    {
        foreach (['public', 'assets'] as $root) {
            $base = $this->projectDir.'/'.$root;
            $file = realpath($base.'/'.ltrim($path, '/'));
            if (false !== $file && str_starts_with($file, $base.'/') && is_file($file)) {
                $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file);
                if (\in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                    return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($file));
                }
            }
        }

        return null;
    }
}
