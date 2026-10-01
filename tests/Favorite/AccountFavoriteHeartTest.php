<?php

declare(strict_types=1);

namespace App\Tests\Favorite;

use App\Catalog\Entity\Category;
use App\Catalog\Entity\Service;
use App\Catalog\Enum\ServiceStatus;
use App\Favorite\Service\FavoriteService;
use App\Provider\Entity\ProviderProfile;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Le cœur de la page « Mes favoris ».
 *
 * LE DÉFAUT QU'ILS PROTÈGENT
 * La route de bascule existait depuis le câblage du 22/08, et les cartes du
 * catalogue comme celles des destinations l'appelaient déjà. Mais la carte
 * propre à l'espace compte — `account/_favorite_card.html.twig` — ne portait
 * qu'un `<button>` nu. On pouvait donc ajouter un favori depuis n'importe où
 * sur le site, et pas le retirer depuis la page faite pour cela.
 *
 * Le défaut est resté invisible longtemps pour une raison instructive :
 * l'onglet Destinations, lui, emploie la carte de destination déjà câblée. Le
 * cœur y fonctionnait. Seul l'onglet Activités — celui qui s'ouvre par défaut —
 * était mort.
 */
final class AccountFavoriteHeartTest extends WebTestCase
{
    public function testTheHeartOfAFavoriteActivityCallsTheToggleRoute(): void
    {
        $client = static::createClient();
        $user = $this->makeUser();
        $client->loginUser($user);

        $activite = $this->makeActivity();
        $this->favorites()->toggleService($user, $activite);

        $crawler = $client->request('GET', '/compte/favoris');

        self::assertSame(200, $client->getResponse()->getStatusCode());

        $coeur = $crawler->filter(sprintf('.pf-favs [data-favorite-slug="%s"]', $activite->getSlug()));

        self::assertGreaterThan(
            0,
            $coeur->count(),
            'Le cœur de la carte n\'appelle aucune route : on ne peut pas retirer un favori depuis la page des favoris.',
        );
        self::assertSame('activite', $coeur->attr('data-favorite-type'));
        self::assertSame('/favoris/basculer', $coeur->attr('data-favorite-url'));
        self::assertNotSame('', (string) $coeur->attr('data-favorite-token'), 'Sans jeton, la bascule serait refusée.');
    }

    /**
     * LE TEST QUI COMPTE : le clic retire réellement le favori, et la page
     * cesse de l'afficher.
     */
    public function testRemovingAFavoriteMakesItDisappearFromTheList(): void
    {
        $client = static::createClient();
        $user = $this->makeUser();
        $client->loginUser($user);

        $activite = $this->makeActivity();
        $this->favorites()->toggleService($user, $activite);

        $crawler = $client->request('GET', '/compte/favoris');
        $coeur = $crawler->filter(sprintf('[data-favorite-slug="%s"]', $activite->getSlug()));
        self::assertGreaterThan(0, $coeur->count());

        $client->request('POST', '/favoris/basculer', [
            'type' => 'activite',
            'slug' => $activite->getSlug(),
            '_token' => (string) $coeur->attr('data-favorite-token'),
        ]);

        self::assertSame(200, $client->getResponse()->getStatusCode(), 'La bascule a été refusée.');
        self::assertStringContainsString('"favori":false', (string) $client->getResponse()->getContent());

        $crawler = $client->request('GET', '/compte/favoris');

        self::assertSame(
            0,
            $crawler->filter(sprintf('.pf-favs [data-favorite-slug="%s"]', $activite->getSlug()))->count(),
            'L\'activité retirée figure encore dans la liste des favoris.',
        );
    }

    /**
     * Le retrait doit être réversible : le même appel remet le favori.
     *
     * C'est ce qui autorise le bandeau « Annuler » côté navigateur — il ne
     * fait rien d'autre que rejouer la bascule.
     */
    public function testTheRemovalCanBeUndone(): void
    {
        $client = static::createClient();
        $user = $this->makeUser();
        $client->loginUser($user);

        $activite = $this->makeActivity();
        $this->favorites()->toggleService($user, $activite);

        $crawler = $client->request('GET', '/compte/favoris');
        $jeton = (string) $crawler->filter(sprintf('[data-favorite-slug="%s"]', $activite->getSlug()))->attr('data-favorite-token');

        $corps = ['type' => 'activite', 'slug' => $activite->getSlug(), '_token' => $jeton];

        $client->request('POST', '/favoris/basculer', $corps);
        $client->request('POST', '/favoris/basculer', $corps);

        self::assertStringContainsString('"favori":true', (string) $client->getResponse()->getContent());

        $crawler = $client->request('GET', '/compte/favoris');

        self::assertGreaterThan(
            0,
            $crawler->filter(sprintf('.pf-favs [data-favorite-slug="%s"]', $activite->getSlug()))->count(),
            'Annuler le retrait ne fait pas revenir l\'activité dans la liste.',
        );
    }

    /**
     * La grille doit être reconnaissable comme une LISTE DE FAVORIS.
     *
     * C'est ce marqueur qui distingue cette page d'une grille de catalogue :
     * ailleurs, vider un cœur laisse la carte en place, ce qui est correct.
     * Ici, la laisser afficherait une liste de favoris contenant un élément
     * qui n'en est plus un.
     */
    public function testTheGridIsMarkedAsAFavoriteList(): void
    {
        $client = static::createClient();
        $user = $this->makeUser();
        $client->loginUser($user);

        $activite = $this->makeActivity();
        $this->favorites()->toggleService($user, $activite);

        $crawler = $client->request('GET', '/compte/favoris');
        $grille = $crawler->filter('[data-favorite-list]');

        self::assertGreaterThan(0, $grille->count(), 'La grille des favoris n\'est pas marquée : la carte resterait affichée après retrait.');
        self::assertNotSame('', (string) $grille->attr('data-favorite-removed'), 'Le libellé du bandeau est vide : il s\'afficherait sans texte.');
        self::assertNotSame('', (string) $grille->attr('data-favorite-undo'), 'Le libellé « Annuler » est vide.');
    }

    /**
     * L'onglet Prestataires a disparu avec la maquette du 30/09 (on ne peut
     * mettre en favori qu'une activité ou une destination) : une ancienne
     * adresse qui le demande retombe sur « Toutes » au lieu d'échouer.
     */
    public function testTheFormerProvidersTabFallsBackToAllFavorites(): void
    {
        $client = static::createClient();
        $user = $this->makeUser();
        $client->loginUser($user);

        $activite = $this->makeActivity();
        $this->favorites()->toggleService($user, $activite);

        $crawler = $client->request('GET', '/compte/favoris?onglet=prestataires');

        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertSame(1, $crawler->filter(sprintf('.pf-favs [data-favorite-slug="%s"]', $activite->getSlug()))->count());
    }

    private function favorites(): FavoriteService
    {
        $service = static::getContainer()->get(FavoriteService::class);
        self::assertInstanceOf(FavoriteService::class, $service);

        return $service;
    }

    private function makeActivity(): Service
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $service = (new Service())
            ->setTitle('Activite mise en favori '.uniqid())
            ->setSlug('favori-'.uniqid())
            ->setDescription('Pour le test du cœur des favoris.')
            ->setPlaceLabel('Nulle-Part')
            ->setProvider($entityManager->getRepository(ProviderProfile::class)->findOneBy([]))
            ->setCategory($entityManager->getRepository(Category::class)->findOneBy([]))
            ->setStatus(ServiceStatus::Published);

        $entityManager->persist($service);
        $entityManager->flush();

        return $service;
    }

    private function makeUser(): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())
            ->setEmail(sprintf('favoris-%s@example.com', uniqid()))
            ->setFirstName('Agnès')
            ->setLastName('Test')
            ->setStatus(UserStatus::Active);
        $user->setPassword('peu-importe');

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
