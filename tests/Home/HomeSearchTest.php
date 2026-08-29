<?php

declare(strict_types=1);

namespace App\Tests\Home;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * La barre de recherche de l'accueil.
 *
 * CE QUI N'ALLAIT PAS
 * Les quatre champs étaient des <div> inertes. Le fichier home_search.js, qui
 * ouvre leurs panneaux, est chargé sur toutes les pages depuis le 23/07 : il
 * attend les attributs data-search-field et data-search-panel, que seul
 * l'accueil connecté portait. Et même là, le bouton « Recherche » menait au
 * catalogue complet : le choix ne quittait jamais la page.
 *
 * LA RÈGLE QUE CE TEST PROTÈGE
 * Une proposition affichée doit ramener AU MOINS un résultat. Les panneaux
 * proposaient les libellés de la maquette — « Île-de-France », « Toulouse »,
 * « La Côte d'Azur » — dont aucun n'est le lieu d'une activité en base :
 * choisir puis chercher ne renvoyait rien, ce qui se lit comme une recherche
 * cassée alors qu'elle a parfaitement fonctionné. Les options viennent donc du
 * catalogue, et ce test le vérifie proposition par proposition.
 */
final class HomeSearchTest extends WebTestCase
{
    public function testTheFourFieldsCanOpenTheirPanel(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();

        foreach (['destination', 'activite', 'date', 'participants'] as $nom) {
            self::assertSame(
                1,
                $crawler->filter(sprintf('[data-search-field="%s"]', $nom))->count(),
                sprintf('Le champ « %s » ne porte pas son attribut : son panneau ne s\'ouvrira pas.', $nom),
            );
            self::assertSame(
                1,
                $crawler->filter(sprintf('[data-search-panel="%s"]', $nom))->count(),
                sprintf('Le panneau « %s » est absent de la page.', $nom),
            );
        }

        self::assertSame(1, $crawler->filter('[data-search-submit]')->count(), 'Le bouton « Recherche » n\'emporte plus les critères choisis.');
    }

    /**
     * Le cœur du sujet : aucune proposition ne doit mener à une page vide.
     */
    public function testEveryProposedPlaceReturnsAtLeastOneActivity(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $lieux = $this->options($crawler, 'destination');
        self::assertNotEmpty($lieux, 'Le panneau des lieux est vide : il ne propose plus rien.');

        foreach ($lieux as $lieu) {
            $resultats = $client->request('GET', '/activites?lieu='.rawurlencode($lieu));

            self::assertResponseIsSuccessful();
            self::assertGreaterThan(
                0,
                $this->countResults($resultats),
                sprintf('« %s » est proposé mais ne ramène aucune activité : la recherche paraîtra cassée.', $lieu),
            );
        }
    }

    public function testEveryProposedActivityReturnsAtLeastOneResult(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $titres = $this->options($crawler, 'activite');
        self::assertNotEmpty($titres, 'Le panneau des activités est vide.');

        foreach ($titres as $titre) {
            $resultats = $client->request('GET', '/activites?q='.rawurlencode($titre));

            self::assertResponseIsSuccessful();
            self::assertGreaterThan(
                0,
                $this->countResults($resultats),
                sprintf('« %s » est proposé mais ne ramène aucun résultat.', $titre),
            );
        }
    }

    /**
     * Un critère doit RESTREINDRE : sans cela le filtre serait ignoré et la page
     * répondrait 200 en montrant tout, ce qui passerait les tests ci-dessus.
     */
    public function testACriterionActuallyNarrowsTheResults(): void
    {
        $client = static::createClient();

        $tout = $this->countResults($client->request('GET', '/activites?q='));
        $crawler = $client->request('GET', '/');
        $titres = $this->options($crawler, 'activite');

        $filtre = $this->countResults($client->request('GET', '/activites?q='.rawurlencode($titres[0])));

        self::assertLessThan(
            $tout,
            $filtre,
            sprintf('Chercher « %s » ramène autant de résultats que le catalogue entier : le critère n\'est pas appliqué.', $titres[0]),
        );
    }

    /**
     * @return list<string>
     */
    private function options(Crawler $crawler, string $panneau): array
    {
        return $crawler
            ->filter(sprintf('[data-search-panel="%s"] [data-option]', $panneau))
            ->each(static fn (Crawler $n): string => trim($n->text()));
    }

    private function countResults(Crawler $crawler): int
    {
        // Les cartes du bandeau « Votre sélection » sont fixes et ne dépendent
        // pas de la recherche : on ne compte que la grille de résultats.
        return $crawler->filter('.act-section .card-grid a[href^="/activites/"]')->count();
    }
}
