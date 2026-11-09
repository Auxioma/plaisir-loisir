<?php

declare(strict_types=1);

namespace App\Tests\Catalog;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Plus de contenu de maquette sur les pages publiques (06/11) : listes de
 * villes, compteurs « 400+ » ou « 88 activités » et pages de démonstration
 * venaient de classes Static*, identiques quel que soit le catalogue.
 */
final class DynamicContentTest extends WebTestCase
{
    public function testMockListingsRedirectToTheRealOnes(): void
    {
        $client = static::createClient();

        $client->request('GET', '/destinations/populaires?q=paris');
        self::assertResponseStatusCodeSame(301);
        self::assertResponseRedirects('/destinations?q=paris');

        $client->request('GET', '/evenements/tous');
        self::assertResponseStatusCodeSame(301);
        self::assertResponseRedirects('/evenements');
    }

    public function testDemoCityPageIsGone(): void
    {
        $client = static::createClient();
        $client->request('GET', '/destinations/lille');

        self::assertResponseStatusCodeSame(404);
    }

    public function testCityPageShowsRealCounts(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/destinations/paris-france');

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('400+', $html);
        self::assertStringNotContainsString('Côte d\'Azur</button>', $html);

        $count = $crawler->filter('.act-results__count strong');
        if ($count->count() > 0) {
            self::assertMatchesRegularExpression('/^\d+$/', trim($count->text()));
        }
    }

    public function testAnotherPlaceTypedOnACityPageLeavesIt(): void
    {
        $client = static::createClient();
        $client->request('GET', '/destinations/paris-france?lieu=Annecy');

        self::assertResponseRedirects('/destinations/annecy-france');
    }
}
