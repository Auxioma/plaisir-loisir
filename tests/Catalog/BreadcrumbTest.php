<?php

declare(strict_types=1);

namespace App\Tests\Catalog;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Le fil d'Ariane doit décrire la page qu'on lit.
 *
 * POURQUOI CE TEST EXISTE
 * Trois écrans annonçaient le chemin d'un autre parcours. Les mentions légales
 * affichaient « Accueil | Destinations | Destination populaires » ; la page des
 * cadeaux par activités disait « Destinations » sur un lien qui menait aux
 * cadeaux ; la fiche d'une activité affichait « Toutes les destinations |
 * Paris, France » et envoyait ces deux maillons vers le listing des activités.
 *
 * Ce n'était pas une faute de codage : les maquettes portent exactement ces
 * textes, coquille « Acceuil » comprise — un report du parcours Destinations
 * resté dans les autres écrans. Le client a validé l'écart le 26/08.
 *
 * Le test fige la règle plutôt que les libellés : aucun maillon ne doit mener
 * vers un parcours étranger à la page, et le dernier est toujours la page en
 * cours, sans lien.
 */
final class BreadcrumbTest extends WebTestCase
{
    public function testLegalPagesDoNotClaimToBeDestinations(): void
    {
        $client = static::createClient();

        foreach (['/mentions-legales' => 'Mentions légales', '/conditions-generales' => 'Conditions'] as $url => $attendu) {
            $crawler = $client->request('GET', $url);
            self::assertSame(200, $client->getResponse()->getStatusCode());

            // `nav.act-breadcrumb` et non `nav[aria-label]` : la barre de
            // navigation est elle aussi un <nav> étiqueté, et elle porte un
            // lien « Destinations » parfaitement légitime.
            $fil = $crawler->filter('nav.act-breadcrumb')->first();
            self::assertGreaterThan(0, $fil->count(), sprintf('« %s » n\'a pas de fil d\'Ariane.', $url));

            self::assertSame(
                [],
                $this->liens($fil, '/destinations'),
                sprintf('« %s » renvoie encore vers les destinations.', $url),
            );
            self::assertStringContainsString($attendu, $this->courant($fil));
        }
    }

    public function testTheGiftCategoryPageSaysGifts(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/cadeaux/ateliers-creations');
        self::assertSame(200, $client->getResponse()->getStatusCode());

        $fil = $crawler->filter('nav.act-breadcrumb')->first();

        self::assertSame([], $this->liens($fil, '/destinations'), 'Le fil renvoie vers les destinations.');
        // Le maillon du milieu mène aux cadeaux, et le dit.
        $milieu = $fil->filter('a')->eq(1);
        self::assertStringContainsString('/cadeaux', (string) $milieu->attr('href'));
        self::assertStringContainsString('Cadeaux', trim($milieu->text()));
    }

    /**
     * Le cas qui a déclenché la remarque : chaque maillon doit mener là où son
     * libellé le promet.
     */
    public function testAnActivityBreadcrumbLeadsWhereItSays(): void
    {
        $client = static::createClient();

        // On part du listing pour prendre une activité qui existe vraiment.
        $crawler = $client->request('GET', '/activites');
        $lien = $crawler->filter('a[href^="/activites/"]')->first();
        self::assertGreaterThan(0, $lien->count(), 'Aucune activité au catalogue.');

        $crawler = $client->request('GET', (string) $lien->attr('href'));
        self::assertSame(200, $client->getResponse()->getStatusCode());

        $fil = $crawler->filter('nav.act-breadcrumb')->first();
        $liens = $fil->filter('a');

        self::assertGreaterThanOrEqual(2, $liens->count(), 'Le fil a perdu ses maillons.');
        self::assertSame('/', $liens->eq(0)->attr('href'));
        self::assertStringStartsWith('/activites', (string) $liens->eq(1)->attr('href'));

        // Aucun maillon ne part vers un autre parcours.
        foreach (['/destinations', '/cadeaux', '/offres', '/evenements'] as $etranger) {
            self::assertSame(
                [],
                $this->liens($fil, $etranger),
                sprintf('Un maillon de la fiche activité mène vers « %s ».', $etranger),
            );
        }

        // Le dernier maillon est la page en cours : le titre, et pas de lien.
        $courant = $fil->filter('[aria-current="page"]');
        self::assertSame(1, $courant->count(), 'La page en cours n\'est pas marquée dans le fil.');
        self::assertSame(
            trim($crawler->filter('h1')->first()->text()),
            trim($courant->text()),
            'Le dernier maillon ne porte pas le titre de la page.',
        );
    }

    /**
     * @return list<string> les href du fil qui commencent par le préfixe donné
     */
    private function liens(Crawler $fil, string $prefixe): array
    {
        return array_values(array_filter(
            $fil->filter('a')->extract(['href']),
            static fn (?string $href): bool => null !== $href && str_starts_with($href, $prefixe),
        ));
    }

    private function courant(Crawler $fil): string
    {
        return trim($fil->filter('[aria-current="page"]')->text());
    }
}
