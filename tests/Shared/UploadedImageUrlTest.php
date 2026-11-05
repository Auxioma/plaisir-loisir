<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\Image\UploadedImageUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Asset\Packages;
use Twig\Environment;

/**
 * Les photos déposées sont servies compressées par LiipImagine (05/11) ;
 * les autres fichiers gardent leur URL.
 */
final class UploadedImageUrlTest extends KernelTestCase
{
    public function testAssetServesCompressedCopyOfUploadedPhoto(): void
    {
        $url = self::getContainer()->get(Packages::class)->getUrl('uploads/activities/photo.jpg');

        self::assertStringContainsString('/media/cache/', $url);
        self::assertStringContainsString('/web/uploads/activities/photo.jpg', $url);
    }

    public function testImageUrlUsesRequestedFilter(): void
    {
        $twig = self::getContainer()->get(Environment::class);
        $html = $twig->createTemplate("{{ image_url('uploads/avatars/a.png', 'avatar') }}|{{ image_url(null) }}")->render();

        self::assertStringContainsString('/avatar/uploads/avatars/a.png', $html);
    }

    #[DataProvider('untouchedPaths')]
    public function testOtherFilesAreLeftAlone(string $path): void
    {
        self::assertFalse(self::getContainer()->get(UploadedImageUrl::class)->supports($path));
        self::assertStringNotContainsString('/media/cache/', self::getContainer()->get(Packages::class)->getUrl($path));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function untouchedPaths(): iterable
    {
        yield 'image du thème' => ['images/account/avatar-default.svg'];
        yield 'svg déposé' => ['uploads/activities/logo.svg'];
        yield 'pièce jointe de contact' => ['uploads/contact/devis.jpg'];
        yield 'avatar externe' => ['https://lh3.googleusercontent.com/a/photo.jpg'];
        yield 'remontée de dossier' => ['uploads/../config/secret.jpg'];
    }
}
