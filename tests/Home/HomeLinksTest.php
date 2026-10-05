<?php

declare(strict_types=1);

namespace App\Tests\Home;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Les liens de la première page du site.
 *
 * POURQUOI CE TEST EXISTE
 * Le 29/08, le client a signalé que « rien ne marche sur la première page ».
 * C'était exact : l'accueil visiteur n'avait jamais reçu la passe de câblage,
 * alors que l'accueil connecté, lui, était branché. Les deux boutons du héros,
 * les onglets de la carte de recherche, le bouton « Recherche », « Voir toutes
 * les activités » et les huit cartes ne menaient nulle part.
 *
 * CE QU'IL SURVEILLE, ET POURQUOI C'EST LE BON ANGLE
 * Les cartes portent le slug de leur fiche, recopié à la main dans le gabarit.
 * Une donnée recopiée finit toujours par diverger de sa source : il a suffi une
 * fois qu'un slug change en base pour que les quatre activités du site mènent à
 * une page d'erreur. Ce test suit donc VRAIMENT les liens et vérifie qu'ils
 * ouvrent une page — il ne se contente pas de constater qu'un href est rempli.
 */
final class HomeLinksTest extends WebTestCase
{
    /**
     * Les liens encore sans destination, et la raison de chacun.
     *
     * Cette liste est un CONSTAT, pas une permission : elle doit se vider. Elle
     * est ici pour qu'un lien mort NOUVEAU fasse échouer la construction au
     * lieu de passer inaperçu comme les précédents.
     */
    private const SANS_DESTINATION = [
        'les 4 icônes de réseaux sociaux du pied de page : aucune adresse fournie',
        'Presse, Centre d\'aide, FAQ, Politique de confidentialité : ces pages n\'existent pas',
        '« Voir plus d\'avis » : il n\'y a pas de page listant les avis',
        '« Traduire » (2 fois) : la traduction des avis n\'est pas développée',
    ];

    private const TOLERES = 11;

    public function testEveryLinkOnTheHomePageOpensAPage(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();

        $adresses = [];
        foreach ($crawler->filter('a[href]')->extract(['href']) as $href) {
            // Les fichiers compilés (CSS, JS) ne sont pas des pages : leur
            // présence dépend de l'état du cache, pas du câblage des gabarits.
            if (!str_starts_with($href, '/') || str_starts_with($href, '/assets/')) {
                continue;
            }
            $adresses[$href] = true;
        }

        self::assertGreaterThan(15, \count($adresses), 'La page ne contient presque aucun lien : le rendu a échoué.');

        $echecs = [];
        foreach (array_keys($adresses) as $adresse) {
            $client->request('GET', $adresse);
            $reponse = $client->getResponse();
            $code = $reponse->getStatusCode();

            // L'en-tête unique (30/09) mène aux espaces membres (favoris,
            // album, notifications…) : pour un visiteur, être renvoyé vers la
            // connexion EST la destination attendue, pas un lien mort.
            if (302 === $code && '/login' === parse_url((string) $reponse->headers->get('Location'), \PHP_URL_PATH)) {
                continue;
            }

            if (200 !== $code) {
                $echecs[] = sprintf('%s → %d', $adresse, $code);
            }
        }

        self::assertSame([], $echecs, "Des liens de l'accueil ne mènent nulle part :\n".implode("\n", $echecs));
    }

    /**
     * Le nombre de liens morts ne doit pas remonter.
     */
    public function testNoNewDeadLinkAppears(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $morts = $crawler->filter('a[href="#"]')->count();

        self::assertLessThanOrEqual(
            self::TOLERES,
            $morts,
            sprintf(
                "L'accueil compte %d liens sans destination, contre %d connus. Un lien mort a été ajouté.\nLes seuls tolérés, et pourquoi :\n - %s",
                $morts,
                self::TOLERES,
                implode("\n - ", self::SANS_DESTINATION),
            ),
        );
    }

    /**
     * Chaque carte doit mener à SA fiche, pas à une liste.
     */
    public function testTheCardsLeadToTheirOwnPage(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        // Cartes de la maquette landing_page (05/10) : activités coup de cœur,
        // activités gratuites, destinations.
        $fiches = array_values(array_unique($crawler->filter('a.ld-card__title, a.ld-dest')->extract(['href'])));
        $prefixes = ['/activites/', '/activites-privees/', '/destinations/'];
        foreach ($fiches as $href) {
            self::assertTrue((bool) array_filter($prefixes, static fn (string $p): bool => str_starts_with($href, $p)), sprintf('La carte mène à %s, qui n\'est pas une fiche.', $href));
        }

        self::assertGreaterThanOrEqual(8, \count($fiches), 'Les cartes de l\'accueil ne mènent pas à leur fiche.');

        foreach ($fiches as $fiche) {
            $client->request('GET', $fiche);
            self::assertSame(200, $client->getResponse()->getStatusCode(), sprintf('La carte mène à %s, qui ne s\'ouvre pas. Un slug a probablement changé en base.', $fiche));
        }
    }
}
