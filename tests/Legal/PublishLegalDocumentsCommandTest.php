<?php

declare(strict_types=1);

namespace App\Tests\Legal;

use App\Legal\Entity\LegalDocument;
use App\Legal\Enum\LegalDocumentType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * La commande qui remplit la base des textes juridiques.
 *
 * POURQUOI ELLE MÉRITE UN TEST
 * C'est elle, et rien d'autre, qui mettra les cinq textes en production : le
 * back-office sert ensuite à les corriger, pas à les créer. Si elle échoue au
 * déploiement, le site repart avec des pages « en cours de rédaction » et des
 * cases à cocher qui renvoient vers du vide — exactement l'état qu'on vient de
 * corriger.
 *
 * ELLE DOIT AUSSI ÊTRE REJOUABLE SANS DÉGÂT : un déploiement se relance, et
 * republier par-dessus des textes existants créerait des doublons ou écraserait
 * une version que des membres ont acceptée.
 */
final class PublishLegalDocumentsCommandTest extends KernelTestCase
{
    public function testItPublishesTheFiveLegalTexts(): void
    {
        $this->emptyTable();
        $sortie = $this->execute(['--doc-version' => '1.0']);

        self::assertStringContainsString('publié', $sortie);

        foreach (LegalDocumentType::cases() as $type) {
            $document = $this->repository()->findOneBy(['type' => $type, 'locale' => 'fr']);

            self::assertInstanceOf(
                LegalDocument::class,
                $document,
                sprintf('« %s » n\'a pas été publié : la page correspondante restera vide en production.', $type->label()),
            );
            self::assertTrue($document->isInForce(), sprintf('« %s » est en base mais pas en vigueur.', $type->label()));
            self::assertNotSame('', trim($document->getContent()), sprintf('« %s » a été publié vide.', $type->label()));
        }
    }

    /**
     * Un déploiement se relance : la commande doit être rejouable.
     */
    public function testRunningItTwiceChangesNothing(): void
    {
        $this->emptyTable();

        $this->execute(['--doc-version' => '1.0']);
        $apresLePremier = \count($this->repository()->findAll());

        $sortie = $this->execute(['--doc-version' => '1.0']);

        self::assertStringContainsString('déjà en base', $sortie, 'La commande ne signale pas qu\'elle ne fait rien.');
        self::assertCount(
            $apresLePremier,
            $this->repository()->findAll(),
            'Relancer la commande a créé des doublons : un déploiement rejoué abîmerait la base.',
        );
    }

    /**
     * Chaque texte doit s'ouvrir en articles, sans quoi la page publique
     * n'aurait ni sommaire ni numérotation.
     */
    public function testEveryTextIsSplitIntoArticles(): void
    {
        $this->emptyTable();
        $this->execute(['--doc-version' => '1.0']);

        foreach (LegalDocumentType::cases() as $type) {
            $document = $this->repository()->findOneBy(['type' => $type, 'locale' => 'fr']);
            self::assertInstanceOf(LegalDocument::class, $document);

            self::assertGreaterThanOrEqual(
                3,
                substr_count($document->getContent(), '<h2>'),
                sprintf('« %s » ne contient presque aucun titre de niveau 2 : la page s\'afficherait d\'un bloc, sans sommaire.', $type->label()),
            );
        }
    }

    /**
     * La commande doit dire qu'il s'agit d'une première rédaction.
     *
     * Ce n'est pas cosmétique : celui qui déploie doit savoir que ces textes
     * n'ont pas été relus par un juriste, et que deux points restent à
     * compléter par l'éditeur.
     */
    public function testItWarnsThatTheTextsStillNeedLegalReview(): void
    {
        $this->emptyTable();
        $sortie = $this->execute(['--doc-version' => '1.0']);

        self::assertStringContainsString('RÉDACTION', $sortie);
        self::assertStringContainsString('juridique', $sortie);
        self::assertStringContainsString('médiateur', $sortie);
    }

    /**
     * @param array<string, string> $options
     */
    private function execute(array $options): string
    {
        self::bootKernel();

        $commande = (new Application(self::$kernel))->find('app:legal:publish');
        $testeur = new CommandTester($commande);
        $testeur->execute($options);

        return $testeur->getDisplay();
    }

    /**
     * @return \Doctrine\ORM\EntityRepository<LegalDocument>
     */
    private function repository(): \Doctrine\ORM\EntityRepository
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager->getRepository(LegalDocument::class);
    }

    /**
     * La base de test n'est pas remise à zéro entre deux exécutions : sans
     * cela, les documents laissés par les autres tests fausseraient les
     * comptages.
     */
    private function emptyTable(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        $entityManager->createQuery('DELETE FROM '.LegalDocument::class.' d')->execute();
        $entityManager->clear();
    }
}
