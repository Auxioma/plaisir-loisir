<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Le contenu saisi au MINIMUM ne doit casser aucune page.
 *
 * POURQUOI CE TEST EXISTE
 * Le même défaut s'est produit quatre fois dans ce projet, sous quatre visages :
 * une carte sans photo faisait tomber les listings, un événement sans image
 * cassait trois écrans, une activité sans fiche détaillée renvoyait 404, une
 * destination sans photo aurait fait de même. À chaque fois, la cause était la
 * même : quelqu'un enregistre une fiche en ne remplissant que l'obligatoire, et
 * une page publique suppose davantage.
 *
 * Loïc va saisir des dizaines de fiches. Certaines resteront incomplètes — le
 * temps de trouver une photo, d'obtenir un texte. Ce test reproduit ce cas :
 * il crée par le back-office le strict minimum que les formulaires acceptent,
 * puis ouvre TOUTES les pages publiques où ce contenu apparaît.
 *
 * Il ne prouve pas qu'aucune erreur ne reste. Il prouve que celle-là, qui est
 * revenue quatre fois, ne revient plus sans qu'on le sache.
 */
final class MinimalContentTest extends WebTestCase
{
    public function testTheBarestActivityBreaksNothing(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeAdmin());

        $titre = 'Activite minimale '.uniqid();
        $slug = 'minimale-'.uniqid();

        // 1. Une activité, avec les seuls champs que le formulaire exige.
        $crawler = $client->request('GET', '/admin/service/new');
        $form = $crawler->filter('form[name="Service"]')->form();
        $form['Service[title]'] = $titre;
        $form['Service[slug]'] = $slug;
        $form['Service[description]'] = 'Description minimale.';
        $form['Service[provider]']->select(self::firstValue($crawler, 'Service[provider]'));
        $form['Service[category]']->select(self::firstValue($crawler, 'Service[category]'));
        $form['Service[status]']->select(self::optionNamed($crawler, 'Service[status]', 'Publiée'));
        $client->submit($form);
        self::assertLessThan(400, $client->getResponse()->getStatusCode(), 'Le formulaire activite a refuse le minimum.');

        // Pas de photo, pas de tarif, pas de lieu, pas de fiche détaillée.
        $this->assertPagesAnswer($client, [
            '/activites' => 200,
            '/en/activities' => 200,
            '/activites?q='.rawurlencode($titre) => 200,
            '/destinations' => 200,
            '/destinations/populaires' => 200,
            // Sans fiche detaillee, la page doit repondre 404 — franchement,
            // pas par une erreur serveur.
            '/activites/'.$slug => 404,
        ]);

        // 2. Sa fiche détaillée, elle aussi au minimum.
        $crawler = $client->request('GET', '/admin/service-detail/new');
        $form = $crawler->filter('form[name="ServiceDetail"]')->form();
        $form['ServiceDetail[service]']->select(self::optionNamed($crawler, 'ServiceDetail[service]', $titre));
        $client->submit($form);
        self::assertLessThan(400, $client->getResponse()->getStatusCode(), 'Le formulaire fiche detaillee a refuse le minimum.');

        // Fiche entièrement vide : la page doit s'ouvrir quand même.
        $this->assertPagesAnswer($client, [
            '/activites/'.$slug => 200,
            '/en/activities/'.$slug => 200,
        ]);
    }

    public function testTheBarestDestinationBreaksNothing(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeAdmin());

        $slug = 'ville-minimale-'.uniqid();

        $crawler = $client->request('GET', '/admin/destination/new');
        $form = $crawler->filter('form[name="Destination"]')->form();
        $form['Destination[name]'] = 'Ville minimale '.uniqid();
        $form['Destination[slug]'] = $slug;
        // Le champ pays est une liste : on choisit, on ne tape pas.
        $form['Destination[country]']->select('FR');
        $client->submit($form);
        self::assertLessThan(400, $client->getResponse()->getStatusCode(), 'Le formulaire destination a refuse le minimum.');

        // Ni photo, ni accroche, ni activité rattachée.
        $this->assertPagesAnswer($client, [
            '/destinations' => 200,
            '/en/destinations' => 200,
            '/destinations/populaires' => 200,
            '/destinations/'.$slug => 200,
            '/en/destinations/'.$slug => 200,
        ]);
    }

    /**
     * @param array<string, int> $attendu adresse => code de réponse attendu
     */
    private function assertPagesAnswer(KernelBrowser $client, array $attendu): void
    {
        foreach ($attendu as $url => $code) {
            $client->request('GET', $url);

            self::assertSame(
                $code,
                $client->getResponse()->getStatusCode(),
                sprintf('« %s » repond %d au lieu de %d.', $url, $client->getResponse()->getStatusCode(), $code),
            );
        }
    }

    private static function firstValue(Crawler $crawler, string $name): string
    {
        $valeurs = $crawler->filter(sprintf('select[name="%s"] option', $name))->extract(['value']);
        $valeurs = array_values(array_filter($valeurs, static fn (?string $v): bool => null !== $v && '' !== $v));

        self::assertNotEmpty($valeurs, sprintf('Le menu « %s » ne propose rien.', $name));

        return $valeurs[0];
    }

    private static function optionNamed(Crawler $crawler, string $name, string $libelle): string
    {
        $option = $crawler->filter(sprintf('select[name="%s"] option', $name))
            ->reduce(static fn (Crawler $node): bool => str_contains(trim($node->text()), $libelle));

        self::assertGreaterThan(0, $option->count(), sprintf('« %s » absent du menu « %s ».', $libelle, $name));

        return (string) $option->attr('value');
    }

    private function makeAdmin(): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())
            ->setEmail(sprintf('admin-min-%s@example.com', uniqid()))
            ->setFirstName('Loïc')
            ->setLastName('Test')
            ->setRoles(['ROLE_ADMIN'])
            ->setStatus(UserStatus::Active);
        $user->setPassword('peu-importe');

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
