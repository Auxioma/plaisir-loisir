<?php

declare(strict_types=1);

namespace App\Tests\Catalog;

use App\Availability\Entity\Availability;
use App\Catalog\Entity\Category;
use App\Catalog\Entity\Service;
use App\Catalog\Enum\ServiceStatus;
use App\Provider\Entity\ProviderProfile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Les filtres « Date » et « Participant(s) » de la barre de recherche.
 *
 * POURQUOI ILS N'EXISTAIENT PAS
 * Le modèle était pourtant là depuis longtemps : Service::capacity et l'entité
 * Availability (créneau, capacité, places déjà prises). Mais la table des
 * disponibilités était VIDE et toutes les capacités NULL. Choisir un jour et un
 * nombre de personnes ne pouvait donc rien changer : ces deux critères ne
 * partaient même pas dans l'adresse.
 *
 * LA SÉMANTIQUE QUE CE TEST PROTÈGE : L'INCONNU N'EXCLUT PAS.
 * Une activité sans capacité renseignée reste proposée, et une activité qui n'a
 * déclaré aucun créneau aussi. Traiter l'absence de donnée comme un refus
 * viderait le catalogue au premier filtre, et le visiteur conclurait à une
 * panne. Le filtre se resserre à mesure que les prestataires remplissent leur
 * fiche — pas avant.
 */
final class ActivityFilterTest extends WebTestCase
{
    public function testAskingForMorePeopleNarrowsTheList(): void
    {
        $client = static::createClient();

        $petit = $this->countResults($client, '/activites?participants=2');
        $grand = $this->countResults($client, '/activites?participants=100');

        self::assertGreaterThan(0, $petit, 'Chercher pour deux personnes ne ramène rien.');
        self::assertLessThan($petit, $grand, 'Demander 100 places ne restreint pas la liste.');
    }

    /**
     * Une capacité non renseignée ne doit pas faire disparaître l'activité.
     */
    public function testAnActivityWithoutCapacityStaysVisible(): void
    {
        $client = static::createClient();
        $titre = 'Sortie sans capacite '.uniqid();
        $this->makeService($client, $titre, capacity: null, creneau: null);

        $crawler = $client->request('GET', '/activites?participants=50&q='.rawurlencode($titre));

        self::assertGreaterThan(
            0,
            $this->cards($crawler),
            'Une activité dont la capacité est inconnue a été exclue : « on ne sait pas » a été traité comme « complet ».',
        );
    }

    /**
     * Une activite qui a declare des creneaux disparait les autres jours.
     *
     * Le test cree sa propre activite plutot que d'interroger une date au
     * hasard : la base de test est PARTAGEE, et d'autres tests y deposent des
     * activites sans creneau, lesquelles restent visibles tous les jours — a
     * juste titre. Sans cette precaution, le test mesurerait le desordre
     * laisse par ses voisins plutot que le filtre.
     */
    public function testADayWithoutSlotReturnsNothing(): void
    {
        $client = static::createClient();
        $titre = 'Sortie du seul jour '.uniqid();
        $this->makeService($client, $titre, capacity: 10, creneau: '2027-05-10');

        $mot = '&q='.rawurlencode($titre);

        self::assertSame(
            1,
            $this->cards($client->request('GET', '/activites?date=2027-05-10'.$mot)),
            'Le jour declare ne ramene pas l\'activite.',
        );
        self::assertSame(
            0,
            $this->cards($client->request('GET', '/activites?date=2027-05-11'.$mot)),
            'L\'activite ressort un jour ou elle n\'a declare aucun creneau : la date ne filtre pas.',
        );
    }

    public function testADayWithSlotsReturnsFewerThanTheWholeCatalogue(): void
    {
        $client = static::createClient();

        $tout = $this->countResults($client, '/activites?participants=1');
        $unJour = $this->countResults($client, '/activites?date=2026-07-05');

        self::assertGreaterThan(0, $unJour, 'Le 5 juillet 2026 ne ramène rien alors que des créneaux y sont déclarés.');
        self::assertLessThan($tout, $unJour, 'Choisir un jour ramène tout le catalogue : la date ne filtre pas.');
    }

    /**
     * Une activité sans créneau déclaré n'est pas « fermée », elle est inconnue.
     */
    public function testAnActivityWithoutSlotStaysVisibleOnAnyDay(): void
    {
        $client = static::createClient();
        $titre = 'Sortie sans creneau '.uniqid();
        $this->makeService($client, $titre, capacity: 10, creneau: null);

        $crawler = $client->request('GET', '/activites?date=2001-01-01&q='.rawurlencode($titre));

        self::assertGreaterThan(
            0,
            $this->cards($crawler),
            'Une activité qui n\'a déclaré aucun créneau a été exclue : l\'absence de calendrier a été lue comme une fermeture.',
        );
    }

    /**
     * Une date illisible ne doit pas casser la page ni vider la liste.
     */
    public function testAnUnreadableDateIsIgnored(): void
    {
        $client = static::createClient();
        $client->request('GET', '/activites?date=le-32-du-mois');

        self::assertResponseIsSuccessful('Une date bricolée dans l\'adresse fait tomber le catalogue.');
    }

    /**
     * Un créneau complet ne doit plus être propose.
     */
    public function testAFullSlotIsNoLongerProposed(): void
    {
        $client = static::createClient();
        $titre = 'Sortie complete '.uniqid();
        $this->makeService($client, $titre, capacity: 5, creneau: '2027-03-04', booked: 5);

        $crawler = $client->request('GET', '/activites?date=2027-03-04&q='.rawurlencode($titre));

        self::assertSame(
            0,
            $this->cards($crawler),
            'Un créneau dont toutes les places sont prises est encore proposé.',
        );
    }

    private function countResults(KernelBrowser $client, string $url): int
    {
        return $this->cards($client->request('GET', $url));
    }

    /**
     * Le nombre affiche par la page, et non un comptage de cartes : le listing
     * porte aussi un bandeau « Votre selection » et des suggestions, dont les
     * cartes menent elles aussi vers /activites/ sans etre des resultats.
     */
    private function cards(Crawler $crawler): int
    {
        $compteur = $crawler->filter('.act-results__count strong');

        if (0 === $compteur->count()) {
            return 0;
        }

        return (int) trim($compteur->first()->text());
    }

    private function makeService(KernelBrowser $client, string $titre, ?int $capacity, ?string $creneau, int $booked = 0): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $service = (new Service())
            ->setTitle($titre)
            ->setSlug(strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $titre)))
            ->setDescription('Pour le test des filtres.')
            ->setPlaceLabel('Nulle-Part')
            ->setProvider($entityManager->getRepository(ProviderProfile::class)->findOneBy([]))
            ->setCategory($entityManager->getRepository(Category::class)->findOneBy([]))
            ->setCapacity($capacity)
            ->setStatus(ServiceStatus::Published);

        $entityManager->persist($service);

        if (null !== $creneau) {
            $jour = new \DateTimeImmutable($creneau);
            $creneauEntite = (new Availability())
                ->setService($service)
                ->setStartsAt($jour->setTime(9, 0))
                ->setEndsAt($jour->setTime(18, 0))
                ->setCapacity((int) $capacity);
            $entityManager->persist($creneauEntite);

            if ($booked > 0) {
                // Il n'y a pas de setter : les places se prennent par le geste
                // metier, qui refuse d'en reserver plus qu'il n'en reste.
                $creneauEntite->reserve($booked);
            }
        }

        $entityManager->flush();
    }
}
