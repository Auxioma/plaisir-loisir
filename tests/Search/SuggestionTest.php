<?php

declare(strict_types=1);

namespace App\Tests\Search;

use App\Catalog\Entity\Category;
use App\Catalog\Entity\Service;
use App\Catalog\Enum\ServiceStatus;
use App\Provider\Entity\ProviderProfile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Les propositions affichées pendant la frappe.
 *
 * POURQUOI CE POINT D'ENTRÉE EXISTE
 * Les champs de recherche du site étaient muets : il fallait connaître
 * l'orthographe exacte d'une ville pour la trouver, et une faute de frappe ne
 * donnait aucun résultat sans jamais dire pourquoi. Taper « pa » doit proposer
 * Paris — demande du CTO du 25/08.
 *
 * CE QUE CE TEST SURVEILLE VRAIMENT
 * Un point d'entrée de suggestion est interrogé sans compte, par tout le
 * monde, et à chaque frappe. Deux dérives sont donc à empêcher :
 *  - qu'il laisse deviner ce qui n'est PAS publié — un brouillon en cours de
 *    rédaction ne doit jamais remonter dans une liste publique ;
 *  - qu'il réponde sur une saisie d'un seul caractère, ce qui reviendrait à
 *    faire balayer tout le catalogue à chaque touche.
 */
final class SuggestionTest extends WebTestCase
{
    public function testTypingTwoLettersProposesTheCity(): void
    {
        $client = static::createClient();
        $this->makeService($client, 'Balade en gondole', 'Venise, Italie', ServiceStatus::Published);

        $items = $this->ask($client, '/suggestions?q=ven&type=lieu');

        self::assertNotEmpty($items, '« ven » ne propose rien alors que Venise est au catalogue.');
        self::assertContains('Venise, Italie', array_column($items, 'label'));
    }

    public function testAnActivitySuggestionCarriesItsPage(): void
    {
        $client = static::createClient();
        $this->makeService($client, 'Descente en gondole tranquille', 'Venise, Italie', ServiceStatus::Published);

        $items = $this->ask($client, '/suggestions?q=gondole&type=activite');

        self::assertNotEmpty($items);
        foreach ($items as $item) {
            self::assertStringContainsString('gondole', mb_strtolower($item['label']));
            self::assertNotNull($item['url'], 'Une activité proposée doit mener à sa fiche.');
            $client->request('GET', $item['url']);
            self::assertSame(200, $client->getResponse()->getStatusCode(), 'La fiche proposée ne s\'ouvre pas.');
        }
    }

    /**
     * Le point le plus important : un brouillon reste invisible.
     */
    public function testADraftIsNeverProposed(): void
    {
        $client = static::createClient();
        $secret = 'Sortie confidentielle '.uniqid();
        $this->makeService($client, $secret, 'Wolokolo, Nulle-Part', ServiceStatus::Draft);

        foreach (['/suggestions?q=wolokolo&type=lieu', '/suggestions?q=confidentielle&type=activite'] as $url) {
            self::assertSame([], $this->ask($client, $url), sprintf('« %s » laisse fuiter un brouillon.', $url));
        }
    }

    public function testOneLetterAnswersNothing(): void
    {
        $client = static::createClient();

        self::assertSame([], $this->ask($client, '/suggestions?q=p&type=lieu'));
        self::assertSame([], $this->ask($client, '/suggestions?q=&type=activite'));
    }

    public function testTheEnglishAddressAnswersToo(): void
    {
        $client = static::createClient();
        $this->makeService($client, 'Gondola ride', 'Venise, Italie', ServiceStatus::Published);

        $client->request('GET', '/en/suggestions?q=ven&type=lieu');
        self::assertSame(200, $client->getResponse()->getStatusCode());
    }

    /**
     * @return list<array{label: string, url: string|null}>
     */
    private function ask(KernelBrowser $client, string $url): array
    {
        $client->request('GET', $url);

        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('application/json', (string) $client->getResponse()->headers->get('Content-Type'));

        /** @var array{items?: list<array{label: string, url: string|null}>} $charge */
        $charge = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $charge['items'] ?? [];
    }

    private function makeService(KernelBrowser $client, string $titre, string $lieu, ServiceStatus $statut): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $service = (new Service())
            ->setTitle($titre)
            ->setSlug(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $titre) ?? 'x').'-'.uniqid())
            ->setDescription('Pour le test des suggestions.')
            ->setPlaceLabel($lieu)
            ->setProvider($entityManager->getRepository(ProviderProfile::class)->findOneBy([]))
            ->setCategory($entityManager->getRepository(Category::class)->findOneBy([]))
            ->setStatus($statut);

        $entityManager->persist($service);
        $entityManager->flush();
    }
}
