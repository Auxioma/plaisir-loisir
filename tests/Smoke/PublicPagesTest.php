<?php

declare(strict_types=1);

namespace App\Tests\Smoke;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Chaque page publique doit répondre.
 *
 * POURQUOI CE TEST EXISTE
 * Le câblage front/back a produit plusieurs erreurs 500 qui ne se voyaient sur
 * AUCUN des écrans modifiés : un composant de calendrier partagé avec l'onglet
 * d'un groupe, une page de destination dont le formulaire fabriquait son
 * adresse à partir du nom affiché, un identifiant ULID lié dans le mauvais
 * format. Toutes ont été trouvées en reparcourant le site à la main.
 *
 * Ce test fait ce parcours automatiquement, à chaque exécution de la suite.
 *
 * Il ne vérifie que le code de réponse : c'est volontaire. Comparer le contenu
 * reviendrait à figer des écrans qui bougent encore, et le défaut qu'on veut
 * attraper ici est la page qui casse, pas celle qui change.
 */
final class PublicPagesTest extends WebTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function publicUrls(): iterable
    {
        $urls = [
            // Accueil et catalogue
            '/',
            '/activites',
            '/activites/descente-en-canoe',
            '/activites?q=canoe',
            '/activites?categorie=bien-etre',
            '/activites?panneau=1&prix_min=0&prix_max=30',
            '/activites?demo=filtres',
            // Destinations
            '/destinations',
            '/destinations/populaires',
            '/destinations/populaires?q=paris',
            '/destinations/lille',
            '/destinations/paris-france',
            // Authentification
            '/login',
            '/inscription',
            '/inscription?type=pro',
            '/authentification',
            '/mot-de-passe-oublie',
            // Événements
            '/evenements',
            '/evenements/tous',
            '/evenements/prives',
            '/evenements/calendrier',
            '/evenements/calendrier?mois='.date('Y-m'),
            // Slugs repris de EventFixtures / GroupFixtures : les fiches
            // détail sont réelles depuis le 17/09, plus une seule page fixe.
            '/evenements/detail/competitions-canoe-kayak',
            '/evenements/detail/competitions-canoe-kayak/participants',
            '/evenements/groupes',
            '/evenements/groupes/detail/cours-collectifs-de-fitness-a-lyon/apropos',
            '/evenements/groupes/detail/cours-collectifs-de-fitness-a-lyon/evenements',
            '/evenements/groupes/detail/cours-collectifs-de-fitness-a-lyon/membres',
            '/evenements/groupes/detail/cours-collectifs-de-fitness-a-lyon/photos',
            '/evenements/groupes/detail/cours-collectifs-de-fitness-a-lyon/discussions',
            '/evenements/groupes/detail/demande-envoyee',
            // Assistants de création
            '/evenements/creer',
            '/evenements/groupes/creer/1',
            '/evenements/groupes/creer/4',
            '/evenements/groupes/creer/succes',
            // Cadeaux et offres
            '/cadeaux',
            '/cadeaux?categorie[]=ateliers-creations&montant[]=0-25',
            '/cadeaux?type=libre',
            '/cadeaux/offrir',
            '/cadeaux/offrir?activite=descente-en-canoe',
            '/offres',
            '/offres?reduction[]=20&dispo[]=semaine&type[]=flash&tri=prix',
            '/explorer',
            // Pages institutionnelles
            '/a-propos',
            '/devenir-partenaire',
            '/devenir-partenaire/formulaire',
            '/carrieres',
            '/carrieres/offres',
            '/contactez-nous',
            '/paiement-securise',
            '/mentions-legales',
            '/conditions-generales',
            '/conditions-generales-de-vente',
            '/politique-de-confidentialite',
            '/politique-de-cookies',
        ];

        foreach ($urls as $url) {
            yield $url => [$url];
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('publicUrls')]
    public function testPublicUrlAnswers(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url);

        self::assertSame(
            200,
            $client->getResponse()->getStatusCode(),
            sprintf('La page « %s » ne répond pas.', $url),
        );
    }

    /**
     * Une adresse inconnue doit donner un 404 franc, jamais une erreur serveur.
     */
    public function testUnknownAddressesAnswer404(): void
    {
        $client = static::createClient();

        foreach (['/activites/nexiste-pas', '/destinations/nexiste-pas'] as $url) {
            $client->request('GET', $url);

            self::assertSame(
                404,
                $client->getResponse()->getStatusCode(),
                sprintf('La page « %s » devrait répondre 404.', $url),
            );
        }
    }

    /**
     * L'espace compte est fermé aux visiteurs.
     *
     * Le pare-feu visait « ^/account » et « ^/provider », deux préfixes sans
     * aucune route : /compte était donc ouvert à tous. Ce test empêche la
     * régression.
     */
    public function testAccountAreaIsClosedToVisitors(): void
    {
        $client = static::createClient();

        foreach (['/compte/favoris', '/compte/notifications', '/compte/parrainage'] as $url) {
            $client->request('GET', $url);

            self::assertTrue(
                $client->getResponse()->isRedirect(),
                sprintf('La page « %s » devrait exiger une connexion.', $url),
            );
        }
    }
}
