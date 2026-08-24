<?php

declare(strict_types=1);

namespace App\Tests\Catalog\Presenter;

use App\Catalog\Entity\Destination;
use App\Catalog\Entity\Service;
use App\Catalog\Presenter\ActivityPresenter;
use App\Catalog\Presenter\DestinationPresenter;
use App\Event\Entity\GroupAlbum;
use App\Event\Presenter\GroupPresenter;
use PHPUnit\Framework\TestCase;

/**
 * Une fiche sans photo ne doit jamais faire tomber une page.
 *
 * POURQUOI CE TEST EXISTE
 * `asset(null)` lève une exception : le gabarit s'arrête, la page rend une
 * erreur 500. Le défaut est invisible en local, où toutes les données de
 * démonstration ont une image ; il apparaît en production dès la première
 * fiche saisie sans photo.
 *
 * Il s'est produit TROIS fois : sur les cartes d'événement et de groupe en
 * août, puis sur /activites et /destinations en production. Les deux premières
 * fois, le repli a été posé dans le gabarit — ce qui ne protège que CE
 * gabarit. Il est désormais posé dans le présentateur, à la source du null,
 * et ce test le vérifie là où il est produit.
 */
final class CardImageFallbackTest extends TestCase
{
    public function testAnActivityWithoutAPhotoStillHasAnImage(): void
    {
        $service = (new Service())->setTitle('Sans photo')->setSlug('sans-photo');

        $card = (new ActivityPresenter())->card($service);

        self::assertIsString($card['image'], 'Une activité sans photo doit recevoir une image de repli, sinon asset(null) fait tomber la page.');
        self::assertNotSame('', $card['image']);
    }

    public function testADestinationWithoutAPhotoStillHasAnImage(): void
    {
        $destination = (new Destination())->setName('Sans photo')->setSlug('sans-photo')->setCountry('France');

        $card = (new DestinationPresenter())->card($destination);

        self::assertIsString($card['image'], 'Une destination sans photo doit recevoir une image de repli.');
        self::assertNotSame('', $card['image']);
    }

    public function testAGroupAlbumWithoutAPhotoStillHasAnImage(): void
    {
        $album = (new GroupAlbum())->setTitle('Sans photo')->setPhotosCount(0);

        $cards = (new GroupPresenter())->albums([$album]);

        self::assertIsString($cards[0]['image'], 'Un album sans photo doit recevoir une image de repli.');
        self::assertNotSame('', $cards[0]['image']);
    }
}
