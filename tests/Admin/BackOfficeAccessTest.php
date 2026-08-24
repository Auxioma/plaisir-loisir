<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Le back-office n'est accessible qu'aux administrateurs.
 *
 * POURQUOI CE TEST EXISTE
 * Le back-office donne accès à TOUT le catalogue en écriture. Une règle
 * d'accès mal posée l'ouvrirait au premier visiteur venu, et rien à l'écran ne
 * le signalerait : la page s'afficherait simplement, comme prévu.
 *
 * Le projet a déjà connu ce défaut : les règles visaient « ^/account » et
 * « ^/provider », deux préfixes ne correspondant à aucune route, si bien que
 * tout l'espace compte était public. Le même piège existe ici, puisque les
 * règles portent sur des chemins.
 *
 * Les trois cas vérifiés sont donc : visiteur anonyme, membre connecté sans
 * droits, et administrateur.
 */
final class BackOfficeAccessTest extends WebTestCase
{
    public function testAVisitorIsSentToTheLoginPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin');

        self::assertSame(302, $client->getResponse()->getStatusCode());
        self::assertStringContainsString(
            '/login',
            (string) $client->getResponse()->headers->get('Location'),
            'Un visiteur non connecté doit être renvoyé vers la connexion.',
        );
    }

    public function testAnOrdinaryMemberIsRefused(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeUser($client, roles: []));

        $client->request('GET', '/admin');

        self::assertSame(
            403,
            $client->getResponse()->getStatusCode(),
            'Un membre sans le rôle administrateur ne doit pas entrer dans le back-office.',
        );
    }

    public function testAnAdministratorReachesTheCatalogue(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeUser($client, roles: ['ROLE_ADMIN']));

        // Le tableau de bord ouvre directement sur les activités.
        $client->request('GET', '/admin');
        self::assertSame(302, $client->getResponse()->getStatusCode());

        $client->followRedirect();
        self::assertSame(200, $client->getResponse()->getStatusCode());
    }

    /**
     * Les écrans de saisie doivent au moins s'ouvrir : une erreur de
     * configuration des champs ne se voit qu'à l'affichage.
     */
    public function testEveryCatalogueScreenOpens(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeUser($client, roles: ['ROLE_ADMIN']));

        foreach (['service', 'service-package', 'media', 'destination', 'category'] as $entity) {
            foreach (['/admin/'.$entity, '/admin/'.$entity.'/new'] as $url) {
                $client->request('GET', $url);

                self::assertSame(
                    200,
                    $client->getResponse()->getStatusCode(),
                    sprintf('L\'écran « %s » ne s\'ouvre pas.', $url),
                );
            }
        }
    }

    /**
     * Le formulaire doit être réellement utilisable, pas seulement répondre.
     *
     * Une activité porte une quarantaine de champs : sans le découpage en
     * onglets, la saisie devient impraticable. Et un libellé resté en anglais
     * ou en nom de propriété (« placeLabel ») ne se voit qu'à l'écran.
     */
    public function testTheActivityFormIsUsable(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeUser($client, roles: ['ROLE_ADMIN']));

        $crawler = $client->request('GET', '/admin/service/new');
        $html = $client->getResponse()->getContent() ?: '';

        foreach (['Présentation', 'Classement', 'Lieu', 'Déroulé', 'Réservation'] as $tab) {
            self::assertStringContainsString($tab, $html, sprintf('L\'onglet « %s » manque.', $tab));
        }

        // Un repère par onglet, pour attraper un champ perdu au passage.
        foreach (['Adresse de la page', 'Ordre d\'affichage', 'Lieu affiché', 'Point de rendez-vous'] as $label) {
            self::assertStringContainsString($label, $html, sprintf('Le champ « %s » manque.', $label));
        }

        // Les statuts sont proposés en français, pas en identifiants techniques.
        self::assertStringContainsString('Brouillon', $html);
        self::assertStringNotContainsString('>Draft<', $html);

        self::assertGreaterThan(0, $crawler->filter('form')->count());
    }

    /**
     * @param list<string> $roles
     */
    private function makeUser(KernelBrowser $client, array $roles): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())
            ->setEmail(sprintf('admin-test-%s@example.com', uniqid()))
            ->setFirstName('Loïc')
            ->setLastName('Test')
            ->setRoles($roles)
            ->setStatus(UserStatus::Active);
        $user->setPassword('peu-importe');

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
