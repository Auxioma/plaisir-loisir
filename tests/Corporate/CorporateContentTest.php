<?php

declare(strict_types=1);

namespace App\Tests\Corporate;

use App\Corporate\Entity\JobOffer;
use App\Corporate\Entity\PageBlock;
use App\Corporate\Enum\PageBlockSection;
use App\Corporate\Repository\PageBlockRepository;
use App\Corporate\Service\CorporateContentImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Pages institutionnelles servies depuis la base (09/11) : ce que le
 * back-office modifie apparaît sur le site, et l'import ne réécrit jamais
 * une section déjà remplie.
 */
final class CorporateContentTest extends WebTestCase
{
    public function testAboutPageShowsTheTeamFromTheDatabase(): void
    {
        $client = static::createClient();
        $team = static::getContainer()->get(PageBlockRepository::class)->findPublished(PageBlockSection::AboutTeam);
        self::assertNotEmpty($team, 'L\'import doit fournir l\'équipe.');

        $client->request('GET', '/a-propos');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.pg-team__list', $team[0]->getTitle());
    }

    public function testAnEditedValueAppearsAndAnUnpublishedOneDisappears(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $em->persist((new PageBlock())->setSection(PageBlockSection::AboutValues)->setTitle('Valeur ajoutée en test')->setText('Visible.')->setPosition(999));
        $em->persist((new PageBlock())->setSection(PageBlockSection::AboutValues)->setTitle('Valeur masquée en test')->setText('Invisible.')->setPublished(false));
        $em->flush();

        $client->request('GET', '/a-propos');

        self::assertSelectorTextContains('.pg-values2__grid', 'Valeur ajoutée en test');
        self::assertSelectorTextNotContains('.pg-values2__grid', 'Valeur masquée en test');
    }

    public function testJobOffersComeFromTheDatabase(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist((new JobOffer())
            ->setSlug('offre-de-test')
            ->setTitle('Offre de test (H/F)')
            ->setSummary('Résumé de test.')
            ->setCity('Rouen')
            ->setDepartment('Test')
            ->setMissions("Première mission\nSeconde mission"));
        $em->flush();

        $client->request('GET', '/carrieres/offres?service=Test');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.pg-jobs', 'Offre de test (H/F)');

        $client->request('GET', '/carrieres/offres?offre=offre-de-test');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Seconde mission');

        $client->request('GET', '/carrieres/offres?offre=n-existe-pas');
        self::assertResponseStatusCodeSame(404);
    }

    public function testImportNeverRefillsASectionThatHasContent(): void
    {
        static::bootKernel();
        $importer = static::getContainer()->get(CorporateContentImporter::class);

        // Les fixtures ont déjà tout importé : un second passage ne crée rien.
        self::assertSame(0, array_sum($importer->import()));
    }

    public function testDemoFavoritesListIsGone(): void
    {
        $client = static::createClient();
        $client->request('GET', '/compte/favoris/listes/alsace-2026');

        // Visiteur : la connexion d'abord ; jamais plus la liste de démonstration.
        self::assertResponseRedirects();
        self::assertStringNotContainsString('Alsace', (string) $client->getResponse()->getContent());
    }
}
