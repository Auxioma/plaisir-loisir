<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Catalog\Entity\Service;
use App\Catalog\Enum\ServiceStatus;
use App\Catalog\Repository\ServiceRepository;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Ce qui est saisi dans le back-office doit apparaître sur le site.
 *
 * POURQUOI CE TEST EXISTE
 * Un back-office qui s'affiche correctement n'est pas un back-office qui
 * marche. Entre le formulaire et la page publique, quatre choses peuvent
 * rompre la chaîne sans qu'aucun écran ne le signale :
 *
 *  1. l'enregistrement échoue en silence ;
 *  2. l'activité est bien créée mais reste en brouillon, donc invisible ;
 *  3. elle s'affiche mais reste introuvable par la recherche, parce que les
 *     colonnes de recherche ne sont pas recalculées ;
 *  4. elle n'a pas de photo, et la page du catalogue tombe en erreur 500 —
 *     ce défaut-là s'est produit trois fois dans ce projet.
 *
 * Ce test suit le parcours complet : Loïc saisit, un visiteur voit.
 */
final class BackOfficeReachesTheSiteTest extends WebTestCase
{
    public function testAnActivityCreatedInTheBackOfficeAppearsOnTheSite(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeAdmin());

        $titre = 'Randonnee raquettes au Semnoz '.uniqid();

        $crawler = $client->request('GET', '/admin/service/new');
        self::assertSame(200, $client->getResponse()->getStatusCode());

        $form = $crawler->filter('form[name="Service"]')->form();
        $form['Service[title]'] = $titre;
        $form['Service[slug]'] = 'rando-'.uniqid();
        $form['Service[description]'] = 'Une sortie encadree de trois heures, raquettes fournies.';
        $form['Service[shortDescription]'] = 'Trois heures en raquettes, materiel fourni.';
        $form['Service[placeLabel]'] = 'Annecy, Haute-Savoie';
        // Le prestataire est obligatoire et n'est plus pre-selectionne :
        // le formulaire refuserait la saisie sans ce choix.
        $form['Service[provider]']->select(self::firstOptionValue($crawler, 'Service[provider]'));
        $form['Service[category]']->select(self::firstOptionValue($crawler, 'Service[category]'));
        // EasyAdmin numerote les options quand leurs valeurs sont des objets :
        // on choisit donc par le LIBELLE affiche, ce qui verifie au passage
        // que le menu est bien en francais.
        $form['Service[status]']->select(self::optionValue($crawler, 'Publiée'));

        $client->submit($form);

        self::assertLessThan(
            400,
            $client->getResponse()->getStatusCode(),
            'Le formulaire du back-office a refuse la saisie.',
        );

        // 1. L'activité existe vraiment en base.
        $services = static::getContainer()->get(ServiceRepository::class);
        $service = $services->findOneBy(['title' => $titre]);
        self::assertInstanceOf(Service::class, $service, "L'activite n'a pas ete enregistree.");
        self::assertSame(ServiceStatus::Published, $service->getStatus());

        // 2. Les colonnes de recherche ont été recalculées à l'enregistrement,
        //    sans que le back-office ait eu à s'en occuper.
        self::assertNotNull($service->getSearchText(), "L'index de recherche n'a pas ete alimente.");
        self::assertStringContainsString('semnoz', (string) $service->getSearchText());

        // 3. Un visiteur anonyme la voit dans le catalogue — et la page ne
        //    tombe pas, alors que cette activite n'a AUCUNE photo.
        // On redevient un visiteur anonyme en vidant les cookies : le noyau
        // ne peut etre demarre qu'une fois par test.
        $client->getCookieJar()->clear();
        $visiteur = $client;
        $visiteur->request('GET', '/activites');

        self::assertSame(
            200,
            $visiteur->getResponse()->getStatusCode(),
            'Le catalogue tombe alors qu une activite vient d etre creee sans photo.',
        );
        // ON NE VERIFIE PLUS ICI QUE L'ACTIVITE FIGURE SUR CETTE PAGE.
        // Le catalogue est pagine depuis le 01/09 : une activite creee a
        // l'instant se trouve la ou son classement la place, pas forcement sur
        // la premiere page. L'affirmation « elle est publiee sur le site » est
        // prouvee juste en dessous, par la recherche — qui est d'ailleurs la
        // facon dont on retrouve une activite precise dans un vrai catalogue.
        //
        // Ce qui se joue ici reste essentiel : le catalogue ne doit pas TOMBER
        // a cause d'une activite sans photo. Le defaut s'est produit trois fois
        // sur ce projet.

        // 4. Et la recherche la trouve.
        $visiteur->request('GET', '/activites?q=Semnoz');
        self::assertStringContainsString(
            $titre,
            $visiteur->getResponse()->getContent() ?: '',
            'La recherche du site ne trouve pas une activite pourtant publiee.',
        );

        // 5. La version anglaise la montre aussi : les deux adresses servent
        //    les mêmes données. On y cherche également l'activité plutôt que de
        //    la guetter sur la première page — le catalogue est paginé.
        $visiteur->request('GET', '/en/activities?q=Semnoz');
        self::assertStringContainsString(
            $titre,
            $visiteur->getResponse()->getContent() ?: '',
            "L'adresse anglaise ne sert pas les mêmes données que la française.",
        );
    }

    /**
     * Une activité laissée en brouillon ne doit PAS fuiter sur le site : c'est
     * ce qui permet à Loïc de préparer une fiche avant de la publier.
     */
    public function testADraftStaysInvisible(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeAdmin());

        $titre = 'Brouillon a ne pas montrer '.uniqid();

        $crawler = $client->request('GET', '/admin/service/new');
        $form = $crawler->filter('form[name="Service"]')->form();
        $form['Service[title]'] = $titre;
        $form['Service[slug]'] = 'brouillon-'.uniqid();
        $form['Service[description]'] = 'Fiche en cours de redaction.';
        $form['Service[provider]']->select(self::firstOptionValue($crawler, 'Service[provider]'));
        $form['Service[category]']->select(self::firstOptionValue($crawler, 'Service[category]'));
        $form['Service[status]']->select(self::optionValue($crawler, 'Brouillon'));
        $client->submit($form);

        $client->getCookieJar()->clear();
        $visiteur = $client;
        $visiteur->request('GET', '/activites');

        self::assertStringNotContainsString(
            $titre,
            $visiteur->getResponse()->getContent() ?: '',
            'Un brouillon est visible sur le site public.',
        );
    }

    /**
     * Premiere option reelle d'un menu, en sautant le choix vide.
     */
    private static function firstOptionValue(Crawler $crawler, string $name): string
    {
        $valeurs = $crawler->filter(sprintf('select[name="%s"] option', $name))->extract(['value']);
        $valeurs = array_values(array_filter($valeurs, static fn (?string $v): bool => null !== $v && '' !== $v));

        self::assertNotEmpty($valeurs, sprintf('Le menu « %s » ne propose rien.', $name));

        return $valeurs[0];
    }

    /**
     * Valeur de l'option portant ce libelle dans le menu « Statut ».
     */
    private static function optionValue(Crawler $crawler, string $label): string
    {
        $option = $crawler->filter('select[name="Service[status]"] option')
            ->reduce(static fn (Crawler $node): bool => trim($node->text()) === $label);

        self::assertGreaterThan(0, $option->count(), sprintf("Le statut « %s » n'est pas propose.", $label));

        return (string) $option->attr('value');
    }

    private function makeAdmin(): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())
            ->setEmail(sprintf('admin-e2e-%s@example.com', uniqid()))
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
