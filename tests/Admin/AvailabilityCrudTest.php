<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Availability\Entity\Availability;
use App\Catalog\Entity\Category;
use App\Catalog\Entity\Service;
use App\Catalog\Enum\ServiceStatus;
use App\Provider\Entity\ProviderProfile;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * L'écran de saisie des disponibilités.
 *
 * POURQUOI IL EXISTE
 * Le filtre « Date » de la barre de recherche s'appuie sur les créneaux depuis
 * le 29/08. Mais aucun écran ne permettait d'en saisir : la table restait vide
 * et le filtre ne pouvait rien exclure en production. La fonctionnalité était
 * complète côté site et inutilisable côté métier.
 *
 * CE QUE CE TEST VÉRIFIE VRAIMENT
 * Pas seulement que l'écran s'ouvre : qu'un créneau saisi dans le back-office
 * CHANGE LE RÉSULTAT DE LA RECHERCHE PUBLIQUE. C'est la seule preuve que les
 * deux bouts sont reliés — un écran qui enregistre dans une table que personne
 * ne lit aurait passé un test plus complaisant.
 */
final class AvailabilityCrudTest extends WebTestCase
{
    public function testTheScreenOpens(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeAdmin());

        $crawler = $client->request('GET', '/admin/availability');

        self::assertSame(200, $client->getResponse()->getStatusCode(), "L'écran des disponibilités ne s'ouvre pas.");

        // Un écran qu'on ne trouve pas dans le menu n'existe pas pour celui qui
        // doit s'en servir : le chemin direct ne suffit pas.
        self::assertGreaterThan(
            0,
            $crawler->filter('a[href*="AvailabilityCrudController"]')->count() + substr_count((string) $client->getResponse()->getContent(), 'Disponibilités'),
            "L'entrée « Disponibilités » est absente du menu du back-office.",
        );
    }

    /**
     * Le test qui compte : la saisie se répercute sur le site.
     */
    public function testASlotEnteredInTheBackOfficeChangesThePublicSearch(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeAdmin());
        $service = $this->makeActivity();
        $mot = '&q='.rawurlencode($service->getTitle());

        // Avant toute déclaration : l'activité sort à n'importe quelle date,
        // parce qu'on ne sait pas quand elle ouvre.
        self::assertSame(1, $this->countResults($client, '/activites?date=2029-04-11'.$mot));

        $crawler = $client->request('GET', '/admin/availability/new');
        self::assertSame(200, $client->getResponse()->getStatusCode(), "L'écran de création ne s'ouvre pas.");

        $form = $crawler->filter('form[name="Availability"]')->form();
        $form['Availability[service]'] = (string) $service->getId();
        $this->fillDate($form, 'startsAt', '2029-04-10', 9);
        $this->fillDate($form, 'endsAt', '2029-04-10', 18);
        $form['Availability[capacity]'] = '12';
        $client->submit($form);

        self::assertLessThan(400, $client->getResponse()->getStatusCode(), 'Le créneau a été refusé.');

        // Après déclaration : elle sort le jour déclaré, et disparaît des autres.
        self::assertSame(1, $this->countResults($client, '/activites?date=2029-04-10'.$mot), 'Le jour déclaré ne ramène pas l\'activité.');
        self::assertSame(0, $this->countResults($client, '/activites?date=2029-04-11'.$mot), 'L\'activité sort encore un jour qu\'elle n\'a pas déclaré : la saisie n\'atteint pas la recherche.');
    }

    /**
     * Les places prises sont une conséquence des réservations, pas une saisie.
     */
    public function testTheNumberOfSeatsTakenCannotBeTypedIn(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeAdmin());

        $crawler = $client->request('GET', '/admin/availability/new');

        self::assertSame(
            0,
            $crawler->filter('[name="Availability[booked]"]')->count(),
            'Le nombre de places prises est proposé à la saisie : le corriger à la main ferait vendre deux fois la même place.',
        );
    }

    public function testASlotThatClosesBeforeItOpensIsRefused(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeAdmin());
        $service = $this->makeActivity();

        $crawler = $client->request('GET', '/admin/availability/new');
        $form = $crawler->filter('form[name="Availability"]')->form();
        $form['Availability[service]'] = (string) $service->getId();
        $this->fillDate($form, 'startsAt', '2029-06-10', 18);
        $this->fillDate($form, 'endsAt', '2029-06-10', 9);
        $form['Availability[capacity]'] = '5';
        $client->submit($form);

        // On regarde la BASE et non le message affiché : le formulaire porte
        // déjà l'aide « doit venir après l'ouverture », si bien qu'une
        // vérification sur le texte de la page passerait même sans validation.
        self::assertSame(
            0,
            $this->countSlots($service),
            'Un créneau qui ferme avant de s\'ouvrir a été enregistré : il resterait en base sans jamais rien ouvrir.',
        );
    }

    /**
     * EasyAdmin rend un unique champ  datetime-local , pas des listes
     * deroulantes separees : la valeur attendue est  2029-04-10T09:00 .
     *
     * @param \Symfony\Component\DomCrawler\Form $form
     */
    private function fillDate(object $form, string $champ, string $jour, int $heure): void
    {
        $form[sprintf('Availability[%s]', $champ)] = sprintf('%sT%02d:00', $jour, $heure);
    }

    private function countSlots(Service $service): int
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        return \count($entityManager->getRepository(Availability::class)->findBy(['service' => $service]));
    }

    private function countResults(KernelBrowser $client, string $url): int
    {
        $crawler = $client->request('GET', $url);
        $compteur = $crawler->filter('.act-results__count strong');

        return 0 === $compteur->count() ? 0 : (int) trim($compteur->first()->text());
    }

    private function makeActivity(): Service
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $service = (new Service())
            ->setTitle('Sortie du back office '.uniqid())
            ->setSlug('sortie-back-office-'.uniqid())
            ->setDescription('Pour le test de saisie des disponibilités.')
            ->setPlaceLabel('Nulle-Part')
            ->setProvider($entityManager->getRepository(ProviderProfile::class)->findOneBy([]))
            ->setCategory($entityManager->getRepository(Category::class)->findOneBy([]))
            ->setStatus(ServiceStatus::Published);

        $entityManager->persist($service);
        $entityManager->flush();

        return $service;
    }

    private function makeAdmin(): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())
            ->setEmail(sprintf('admin-dispo-%s@example.com', uniqid()))
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
