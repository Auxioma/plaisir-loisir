<?php

declare(strict_types=1);

namespace App\Tests\Catalog;

use App\Catalog\Entity\Category;
use App\Catalog\Entity\Service;
use App\Catalog\Entity\ServicePackage;
use App\Catalog\Enum\ServiceStatus;
use App\Provider\Entity\ProviderProfile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Pagination et tri du catalogue d'activités.
 *
 * POURQUOI CES TESTS SONT INDISPENSABLES ICI
 * Le catalogue ne compte que huit activités : avec douze par page, il n'y a
 * qu'UNE page, et la pagination ne se voit pas à l'écran. On ne peut donc pas
 * la vérifier en regardant le site — il faut fabriquer assez d'activités pour
 * qu'une seconde page existe. Sans ces tests, la fonctionnalité resterait
 * invérifiable jusqu'au jour où le catalogue serait rempli, c'est-à-dire trop
 * tard.
 *
 * LE PIÈGE QU'ILS PROTÈGENT
 * La requête d'affichage joint les formules et les photos. Une activité
 * portant six photos occupe six LIGNES SQL, si bien qu'un simple
 * `setMaxResults(12)` ramènerait douze lignes — deux ou trois activités — au
 * lieu de douze activités. C'est pourquoi les activités créées ici portent
 * plusieurs formules : sans elles, un découpage naïf passerait le test.
 */
final class ActivityPaginationTest extends WebTestCase
{
    private const PAR_PAGE = 12;

    /**
     * LE TEST QUI COMPTE : deux pages, sans trou ni doublon.
     */
    public function testTheCatalogueIsSplitIntoPagesWithoutLosingAnything(): void
    {
        $client = static::createClient();
        $marqueur = $this->makeActivities(20);

        $premiere = $this->slugsOnPage($client, 1, $marqueur);
        $seconde = $this->slugsOnPage($client, 2, $marqueur);

        self::assertCount(
            self::PAR_PAGE,
            $premiere,
            'La première page ne contient pas douze activités : le découpage compte des lignes SQL, pas des activités.',
        );

        self::assertNotSame([], $seconde, 'La seconde page est vide alors que le catalogue en contient assez.');

        self::assertSame(
            [],
            array_intersect($premiere, $seconde),
            'Une même activité apparaît sur les deux pages : le tri n\'est pas stable entre les requêtes.',
        );
    }

    /**
     * Le compteur doit dire la vérité, pas le nombre de cartes visibles.
     */
    public function testTheCounterShowsTheWholeTotalAndNotThePage(): void
    {
        $client = static::createClient();
        $this->makeActivities(20);

        $crawler = $client->request('GET', '/activites');
        $compteur = (int) trim($crawler->filter('.act-results__count strong')->first()->text());

        self::assertGreaterThan(
            self::PAR_PAGE,
            $compteur,
            'Le compteur affiche le nombre de cartes de la page au lieu du total : le visiteur croit le catalogue plus petit qu\'il n\'est.',
        );
    }

    /**
     * « Voir plus » conduit à la suite, et disparaît quand il n'y a plus rien.
     */
    public function testTheLoadMoreButtonLeadsToTheNextPageAndVanishesAtTheEnd(): void
    {
        $client = static::createClient();
        $this->makeActivities(20);

        $crawler = $client->request('GET', '/activites');
        $bouton = $crawler->filter('.act-more a');

        self::assertGreaterThan(0, $bouton->count(), '« Voir plus d\'activités » est absent alors qu\'une page suivante existe.');
        self::assertStringContainsString('page=2', (string) $bouton->attr('href'));

        // Sur la dernière page, le bouton n'a plus rien à charger.
        $crawler = $client->request('GET', '/activites?page=999');

        self::assertSame(
            0,
            $crawler->filter('.act-more a')->count(),
            'Le bouton « Voir plus » subsiste sur la dernière page : il promet une suite qui n\'existe pas.',
        );
    }

    /**
     * Une page hors bornes ne doit pas afficher un catalogue vide.
     */
    public function testAPageBeyondTheEndFallsBackToTheLastOne(): void
    {
        $client = static::createClient();
        $this->makeActivities(20);

        $crawler = $client->request('GET', '/activites?page=999');

        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertGreaterThan(
            0,
            $crawler->filter('.act-grid a[href^="/activites/"]')->count(),
            'Une page hors bornes affiche une liste vide : le visiteur croit le catalogue vide.',
        );
    }

    /**
     * Le tri par prix ordonne réellement.
     *
     * Le prix affiché est le PLUS BAS des formules : c'est sur lui que le tri
     * doit porter, et non sur une formule prise au hasard.
     */
    public function testSortingByPriceOrdersTheCards(): void
    {
        $client = static::createClient();

        // UN MARQUEUR PROPRE A CE TEST, ET UNE RECHERCHE DESSUS.
        // Une premiere version triait le catalogue entier : elle echouait par
        // intermittence, parce que d'autres suites y laissent des activites
        // dont ce test ne maitrise ni le prix ni la presence. Un test qui
        // ordonne des donnees qu'il ne cree pas ne prouve rien de stable.
        $marqueur = 'Tri'.substr(uniqid(), -6);
        $this->makeActivities(6, $marqueur);

        $croissant = $this->pricesOnFirstGrid($client, '/activites?q='.$marqueur.'&tri=prix-asc');
        $decroissant = $this->pricesOnFirstGrid($client, '/activites?q='.$marqueur.'&tri=prix-desc');

        self::assertNotSame([], $croissant, 'Aucun prix lu : le test ne prouverait rien.');

        $attendu = $croissant;
        sort($attendu);
        self::assertSame($attendu, $croissant, 'Le tri « prix croissant » ne range pas les cartes du moins cher au plus cher.');

        $attendu = $decroissant;
        rsort($attendu);
        self::assertSame($attendu, $decroissant, 'Le tri « prix décroissant » ne range pas les cartes du plus cher au moins cher.');
    }

    /**
     * UNE ACTIVITE SANS PRIX NE MENE PAS LE TRI PAR PRIX.
     *
     * Toutes les activites n'ont pas de formule : leur prix minimum vaut donc
     * NULL, et PostgreSQL range les NULL EN TETE d'un tri decroissant. Sans
     * precaution, « prix decroissant » aurait commence par les activites qui
     * n'affichent aucun prix — les plus cheres, en somme, parce qu'on ignore
     * leur prix. Ce test est ne d'un echec intermittent : il ne se produisait
     * que lorsqu'une autre suite avait laisse une activite sans formule.
     */
    public function testAnActivityWithoutAPriceNeverLeadsThePriceSort(): void
    {
        $client = static::createClient();

        // Un marqueur propre a ce test, et une recherche dessus : les cinq
        // activites tiennent alors sur une seule page, et l'on peut affirmer
        // OU se trouve celle qui n'a pas de prix. Sans cette restriction, elle
        // etait simplement repoussee au-dela de la premiere page et le test
        // ne verifiait rien.
        $marqueur = 'Sansprix'.substr(uniqid(), -6);
        $this->makeActivities(4, $marqueur);
        $sansPrix = $this->makeActivityWithoutPackage($marqueur);

        foreach (['prix-asc', 'prix-desc'] as $tri) {
            $crawler = $client->request('GET', sprintf('/activites?q=%s&tri=%s', $marqueur, $tri));
            $liens = $crawler->filter('.act-grid a[href^="/activites/"]')->each(
                static fn ($noeud): string => (string) $noeud->attr('href'),
            );

            self::assertCount(5, $liens, sprintf('Les cinq activités du marqueur ne sont pas toutes affichées (tri « %s »).', $tri));

            self::assertSame(
                '/activites/'.$sansPrix,
                end($liens),
                sprintf('L\'activité sans prix n\'est pas en dernier avec le tri « %s » : elle passe devant celles qui affichent un prix.', $tri),
            );
        }
    }

    /**
     * Le tri choisi doit rester visible, sinon on ne sait plus ce qu'on lit.
     */
    public function testTheChosenSortIsShownOnTheButton(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/activites?tri=prix-asc');

        self::assertStringContainsString(
            'Prix croissant',
            $crawler->filter('.act-sort summary')->text(''),
            'Le bouton continue d\'annoncer « Les plus populaires » alors qu\'un autre tri est appliqué.',
        );
    }

    /**
     * Un tri inconnu — adresse tapée à la main, vieux lien — ne doit pas
     * casser la page.
     */
    public function testAnUnknownSortFallsBackToTheDefault(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/activites?tri=nimporte-quoi');

        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('Les plus populaires', $crawler->filter('.act-sort summary')->text(''));
    }

    /**
     * Changer de tri ou de page ne doit pas perdre la recherche en cours.
     *
     * C'est le défaut le plus agaçant d'une pagination : on filtre, on clique
     * sur « page 2 », et l'on retombe sur le catalogue entier.
     */
    public function testSortAndPaginationKeepTheCurrentFilters(): void
    {
        $client = static::createClient();
        $this->makeActivities(20);

        $crawler = $client->request('GET', '/activites?q=Paginee');

        $lienTri = (string) $crawler->filter('.act-sort__menu a')->first()->attr('href');
        self::assertStringContainsString('q=Paginee', $lienTri, 'Changer de tri perd la recherche en cours.');

        $bouton = $crawler->filter('.act-more a');

        if ($bouton->count() > 0) {
            self::assertStringContainsString('q=Paginee', (string) $bouton->attr('href'), '« Voir plus » perd la recherche en cours.');
        }
    }

    /**
     * Une activite depourvue de toute formule, donc sans prix affichable.
     */
    private function makeActivityWithoutPackage(string $marqueur = 'Paginee'): string
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $slug = 'sans-prix-'.uniqid();

        $service = (new Service())
            ->setTitle($marqueur.' sans prix '.uniqid())
            ->setSlug($slug)
            ->setDescription('Activité sans aucune formule tarifaire.')
            ->setPlaceLabel('Nulle-Part')
            ->setProvider($entityManager->getRepository(ProviderProfile::class)->findOneBy([]))
            ->setCategory($entityManager->getRepository(Category::class)->findOneBy([]))
            ->setStatus(ServiceStatus::Published);

        $entityManager->persist($service);
        $entityManager->flush();

        return $slug;
    }

    /**
     * @return list<string>
     */
    private function slugsOnPage(KernelBrowser $client, int $page, string $marqueur): array
    {
        $crawler = $client->request('GET', sprintf('/activites?q=%s&page=%d', rawurlencode($marqueur), $page));

        return $crawler->filter('.act-grid a[href^="/activites/"]')->each(
            static fn ($noeud): string => (string) $noeud->attr('href'),
        );
    }

    /**
     * @return list<int>
     */
    private function pricesOnFirstGrid(KernelBrowser $client, string $url): array
    {
        $crawler = $client->request('GET', $url);

        return $crawler->filter('.act-grid .pl-card__price')->each(
            static fn ($noeud): int => (int) preg_replace('/\D/', '', $noeud->text('')),
        );
    }

    /**
     * Fabrique assez d'activités pour qu'une seconde page existe.
     *
     * CHACUNE PORTE TROIS FORMULES, ET C'EST VOULU : la requête d'affichage
     * les joint, si bien qu'une activité occupe plusieurs lignes SQL. Sans
     * elles, un découpage qui compte les lignes au lieu des activités
     * passerait ce test sans qu'on s'en aperçoive.
     *
     * @return string le marqueur commun, pour ne retrouver que ces activités
     */
    private function makeActivities(int $combien, string $marqueur = 'Paginee'): string
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $provider = $entityManager->getRepository(ProviderProfile::class)->findOneBy([]);
        $category = $entityManager->getRepository(Category::class)->findOneBy([]);

        for ($rang = 0; $rang < $combien; ++$rang) {
            $unique = uniqid();

            $service = (new Service())
                ->setTitle(sprintf('%s %02d %s', $marqueur, $rang, $unique))
                ->setSlug(sprintf('paginee-%02d-%s', $rang, $unique))
                ->setDescription('Activité fabriquée pour éprouver la pagination.')
                ->setPlaceLabel('Nulle-Part')
                ->setProvider($provider)
                ->setCategory($category)
                ->setPosition($rang)
                ->setStatus(ServiceStatus::Published);

            $entityManager->persist($service);

            // Des prix volontairement désordonnés : un tri qui ne trierait pas
            // passerait si les prix arrivaient déjà rangés.
            foreach ([120 - $rang * 3, 45 + $rang, 300 - $rang * 7] as $prix) {
                $formule = (new ServicePackage())
                    ->setService($service)
                    ->setName('Formule '.$prix)
                    ->setPrice((string) max(5, $prix));

                $entityManager->persist($formule);
            }
        }

        $entityManager->flush();

        return $marqueur;
    }
}
