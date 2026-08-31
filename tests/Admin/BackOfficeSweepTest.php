<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Balayage complet du back-office : chaque écran, chaque tri, chaque filtre.
 *
 * POURQUOI CE TEST EXISTE
 * Le 31/08, cliquer sur l'en-tête « Rôles » de la liste des membres renvoyait
 * une erreur. La cause est instructive : les rôles sont stockés dans une
 * colonne JSON, et EasyAdmin propose le tri sur toutes les colonnes sans
 * savoir lesquelles SQL sait ordonner. Rien dans les tests ne couvrait les
 * liens de tri — on vérifiait que les écrans s'ouvrent, pas qu'on puisse s'en
 * servir.
 *
 * CE QUE CE TEST FAIT, ET POURQUOI IL EST ÉCRIT AINSI
 * Il ne récite pas une liste de colonnes tenue à la main, qui se périmerait au
 * premier champ ajouté. Il OUVRE chaque écran, RELÈVE les liens que la page
 * propose réellement — tris, filtres, pagination — et les suit tous. Un champ
 * ajouté demain sera donc couvert sans que personne y pense.
 *
 * Treize écrans, et autant de colonnes que chacun affiche : c'est le genre de
 * vérification qu'on ne fait pas à la main deux fois.
 *
 * IL NE DOIT RIEN EXIGER D'UNE BASE VIDE. Première version rejetée par
 * l'intégration continue : elle réclamait au moins un lien de tri par écran,
 * or EasyAdmin n'affiche pas d'en-tête triable quand la liste est vide. En
 * local les tables étaient pleines, sur le serveur elles ne l'étaient pas. Un
 * test de parcours ne doit rien supposer des données présentes — mais il doit
 * rester exigeant DÈS QU'il y en a, sans quoi il passerait sans rien éprouver.
 */
final class BackOfficeSweepTest extends WebTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function screens(): iterable
    {
        foreach ([
            'activités' => '/admin/service',
            'fiches détaillées' => '/admin/service-detail',
            'tarifs' => '/admin/service-package',
            'photos' => '/admin/media',
            'disponibilités' => '/admin/availability',
            'destinations' => '/admin/destination',
            'catégories' => '/admin/category',
            'membres' => '/admin/user',
            'prestataires' => '/admin/provider-profile',
            'candidatures' => '/admin/partner-application',
            'messages' => '/admin/contact-message',
            'textes juridiques' => '/admin/legal-document',
            'FAQ' => '/admin/faq-entry',
        ] as $nom => $url) {
            yield $nom => [$url];
        }
    }

    /**
     * LE TEST QUI A TROUVÉ LE DÉFAUT : suivre tous les liens de tri.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('screens')]
    public function testEverySortLinkWorks(string $url): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeAdmin());

        $crawler = $client->request('GET', $url);
        self::assertSame(200, $client->getResponse()->getStatusCode(), sprintf('%s ne s\'ouvre pas.', $url));

        if (0 === $this->dataRows($crawler)->count()) {
            self::assertTrue(true, 'Liste vide : EasyAdmin n\'affiche alors aucun en-tête triable.');

            return;
        }

        $liens = $this->sortLinks($crawler);

        self::assertNotSame([], $liens, sprintf('%s affiche des lignes mais aucun tri : le relevé est vide, le test ne prouverait rien.', $url));

        foreach ($liens as $intitule => $lien) {
            $client->request('GET', $lien);

            self::assertSame(
                200,
                $client->getResponse()->getStatusCode(),
                sprintf('Trier « %s » sur %s renvoie une erreur.', $intitule, $url),
            );
        }
    }

    /**
     * Les filtres proposés doivent s'ouvrir sans erreur.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('screens')]
    public function testTheFilterPanelOpens(string $url): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeAdmin());

        $crawler = $client->request('GET', $url);
        $bouton = $crawler->filter('a[href*="render-filters"], [data-ea-filters-url]');

        if (0 === $bouton->count()) {
            self::assertTrue(true, 'Cet écran ne déclare aucun filtre.');

            return;
        }

        $lien = (string) ($bouton->attr('href') ?? $bouton->attr('data-ea-filters-url'));
        $client->request('GET', $lien);

        self::assertLessThan(
            500,
            $client->getResponse()->getStatusCode(),
            sprintf('Le panneau de filtres de %s renvoie une erreur.', $url),
        );
    }

    /**
     * La fiche détaillée d'une ligne, quand il y en a une.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('screens')]
    public function testTheFirstRowDetailOpens(string $url): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeAdmin());

        $crawler = $client->request('GET', $url);
        $lien = $this->dataRows($crawler)->filter('a[href]')->first();

        if (0 === $lien->count()) {
            self::assertTrue(true, 'Aucune ligne à ouvrir sur cet écran.');

            return;
        }

        $client->request('GET', (string) $lien->attr('href'));

        self::assertLessThan(
            500,
            $client->getResponse()->getStatusCode(),
            sprintf('Ouvrir la première ligne de %s renvoie une erreur.', $url),
        );
    }

    /**
     * Le formulaire de création, sur les écrans qui le proposent.
     *
     * Les trois écrans en lecture seule — candidatures, messages, membres —
     * redirigent volontairement : on vérifie qu'ils ne PLANTENT pas, pas
     * qu'ils acceptent.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('screens')]
    public function testTheCreationFormDoesNotBreak(string $url): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeAdmin());

        $client->request('GET', $url.'/new');

        self::assertLessThan(
            500,
            $client->getResponse()->getStatusCode(),
            sprintf('%s/new renvoie une erreur serveur.', $url),
        );
    }

    /**
     * Suit CHAQUE ACTION DISTINCTE proposée par la liste.
     *
     * C'est le menu « … » des lignes : consulter, modifier, et les actions
     * propres à l'écran — publier un texte juridique, marquer une candidature
     * comme traitée, anonymiser un compte. Ces liens sont fabriqués par
     * EasyAdmin à partir de nos déclarations ; une déclaration incorrecte ne
     * se voit qu'au clic. C'est ainsi qu'a été trouvé le 500 sur la
     * modification d'un prestataire.
     *
     * ON PARCOURT TOUTES LES LIGNES, PAS SEULEMENT LA PREMIÈRE : les actions
     * dépendent de l'état de la ligne. Un texte juridique publié n'offre ni
     * « Modifier » ni « Publier » ; un brouillon, si. S'arrêter à la première
     * ligne laisserait donc des actions jamais essayées.
     *
     * Chaque action n'est suivie qu'une fois — inutile d'ouvrir seize fiches
     * pour éprouver le même gabarit.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('screens')]
    public function testEveryRowActionLinkWorks(string $url): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeAdmin());

        $crawler = $client->request('GET', $url);
        $lignes = $this->dataRows($crawler);

        if (0 === $lignes->count()) {
            self::assertTrue(true, 'Aucune ligne sur cet écran.');

            return;
        }

        // Les liens d'action sont rendus en URL absolue par EasyAdmin :
        // « commence par /admin » n'en trouvait aucun.
        $parAction = [];

        foreach ($lignes->filter('a[href*="/admin"]') as $noeud) {
            $lien = (string) (new Crawler($noeud))->attr('href');
            $parAction[$this->actionName($lien)] ??= $lien;
        }

        self::assertNotSame([], $parAction, sprintf('Aucune action proposée sur les lignes de %s.', $url));

        foreach ($parAction as $action => $lien) {
            $client->request('GET', $lien);

            self::assertLessThan(
                500,
                $client->getResponse()->getStatusCode(),
                sprintf('L\'action « %s » de %s renvoie une erreur serveur (%s).', $action, $url, $lien),
            );
        }
    }

    /**
     * Les lignes qui portent VRAIMENT une donnée.
     *
     * Une liste vide n'est pas un tableau sans `<tr>` : EasyAdmin y place une
     * ligne « aucun résultat ». On reconnaît une vraie ligne au fait qu'elle
     * propose au moins une action.
     */
    private function dataRows(Crawler $crawler): Crawler
    {
        return $crawler->filter('table.datagrid tbody tr')->reduce(
            static fn (Crawler $ligne): bool => $ligne->filter('a[href*="/admin"]')->count() > 0,
        );
    }

    /**
     * Le dernier segment d'une adresse d'action : « edit », « publish »,
     * « anonymize »… ou l'identifiant, pour la fiche détaillée.
     */
    private function actionName(string $lien): string
    {
        $chemin = (string) parse_url($lien, \PHP_URL_PATH);
        $segments = explode('/', trim($chemin, '/'));
        $dernier = end($segments);

        // Une fiche détaillée finit par l'identifiant : on les regroupe sous un
        // même nom pour n'en ouvrir qu'une.
        return 1 === preg_match('/^[0-9A-Z]{26}$/', $dernier) ? 'detail' : $dernier;
    }

    /**
     * Les liens de pagination, quand la liste en compte assez pour en avoir.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('screens')]
    public function testPaginationLinksWork(string $url): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeAdmin());

        $crawler = $client->request('GET', $url);
        $liens = array_unique($crawler->filter('.pagination a[href]')->each(
            static fn (Crawler $noeud): string => (string) $noeud->attr('href'),
        ));

        if ([] === $liens) {
            self::assertTrue(true, 'Une seule page sur cet écran.');

            return;
        }

        foreach ($liens as $lien) {
            $client->request('GET', $lien);

            self::assertSame(200, $client->getResponse()->getStatusCode(), sprintf('La pagination de %s renvoie une erreur.', $url));
        }
    }

    /**
     * Relève les liens de tri proposés par l'en-tête du tableau.
     *
     * @return array<string, string>
     */
    private function sortLinks(Crawler $crawler): array
    {
        $liens = [];

        foreach ($crawler->filter('table.datagrid thead a[href]') as $noeud) {
            $lien = new Crawler($noeud);
            $href = (string) $lien->attr('href');

            if (!str_contains($href, 'sort')) {
                continue;
            }

            $liens[trim($lien->text(''))] = $href;
        }

        return $liens;
    }

    private function makeAdmin(): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())
            ->setEmail(sprintf('sweep-%s@example.com', uniqid()))
            ->setFirstName('Balayage')
            ->setLastName('Test')
            ->setRoles(['ROLE_ADMIN'])
            ->setStatus(UserStatus::Active);
        $user->setPassword('peu-importe');

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
