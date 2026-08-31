<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Legal\Entity\LegalDocument;
use App\Legal\Enum\LegalDocumentType;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * L'écran de gestion des textes juridiques.
 *
 * POURQUOI IL EXISTE
 * Le CTO a demandé le 29/08 que les textes juridiques soient gérés en base
 * parce qu'ils évoluent dans le temps. Le modèle existait déjà entièrement ;
 * il manquait l'écran, sans lequel la gestion restait théorique.
 *
 * LA RÈGLE QUE CES TESTS PROTÈGENT
 * UN TEXTE PUBLIÉ NE SE MODIFIE PLUS. La table `legal_acceptance` retient quel
 * document chaque membre a accepté à l'inscription ; corriger un texte publié
 * réécrirait rétroactivement ce que tous les membres sont réputés avoir
 * accepté, et effacerait la seule preuve utile en cas de litige.
 *
 * Masquer le bouton « Modifier » ne suffit pas : l'adresse reste devinable.
 * Les tests ci-dessous attaquent donc l'ADRESSE, pas le bouton — et vérifient
 * la BASE, pas le message affiché.
 */
final class LegalDocumentCrudTest extends WebTestCase
{
    public function testTheScreenOpensAndAppearsInTheMenu(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeAdmin());

        $client->request('GET', '/admin/legal-document');

        self::assertSame(200, $client->getResponse()->getStatusCode(), "L'écran des textes juridiques ne s'ouvre pas.");
        self::assertStringContainsString(
            'Textes juridiques',
            (string) $client->getResponse()->getContent(),
            "L'entrée « Textes juridiques » est absente du menu : un écran qu'on ne trouve pas n'existe pas.",
        );
    }

    /**
     * LE TEST QUI PROTÈGE : le formulaire de modification d'un texte publié
     * est refusé, même en tapant l'adresse.
     */
    public function testAPublishedTextCannotBeEditedEvenByTypingTheUrl(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeAdmin());

        $document = $this->makeDocument(publie: true);
        $texteOrigine = $document->getContent();

        $client->request('GET', sprintf('/admin/legal-document/%s/edit', $document->getId()));

        // On vérifie la BASE, pas la page : un contrôle sur le texte affiché
        // passerait aussi bien si le formulaire s'ouvrait avec un avertissement.
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $relu = $entityManager->getRepository(LegalDocument::class)->find($document->getId());

        self::assertInstanceOf(LegalDocument::class, $relu);
        self::assertSame(
            $texteOrigine,
            $relu->getContent(),
            'Le texte d\'un document publié a changé : les acceptations enregistrées ne prouvent plus rien.',
        );
        self::assertStringNotContainsString(
            'name="LegalDocument"',
            (string) $client->getResponse()->getContent(),
            'Le formulaire de modification s\'est ouvert sur un texte publié.',
        );
    }

    /**
     * Un texte publié ne se supprime pas non plus : des acceptations le
     * référencent.
     *
     * LE SCÉNARIO EST CELUI QUI ARRIVE VRAIMENT : la page a été ouverte alors
     * que le document était encore un brouillon — le bouton « Supprimer » y
     * figure donc — puis quelqu'un a publié le document entre-temps, et le
     * bouton est cliqué ensuite. Masquer le bouton ne protège rien dans ce
     * cas ; seule la vérification faite au moment de l'action protège.
     */
    public function testAPublishedTextCannotBeDeletedEvenFromAPageOpenedBeforehand(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeAdmin());

        $document = $this->makeDocument(publie: false);
        $identifiant = $document->getId();

        // 1. La page est ouverte tant que le document est un brouillon. La
        //    suppression d'EasyAdmin passe par un formulaire caché, complété
        //    par JavaScript au moment du clic : on relève son jeton.
        $crawler = $client->request('GET', sprintf('/admin/legal-document/%s', $identifiant));
        $jeton = $crawler->filter('#action-confirmation-form input[name="token"]')->first();
        self::assertGreaterThan(0, $jeton->count(), "Le jeton de suppression est introuvable sur la page d'un brouillon : le test ne prouverait rien.");

        // 2. Le document est publié entre-temps.
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $aPublier = $entityManager->getRepository(LegalDocument::class)->find($identifiant);
        self::assertInstanceOf(LegalDocument::class, $aPublier);
        $aPublier->publish();
        $entityManager->flush();

        // 3. La page ouverte tout à l'heure envoie quand même sa suppression.
        $client->request(
            'POST',
            sprintf('/admin/legal-document/%s/delete', $identifiant),
            ['token' => (string) $jeton->attr('value')],
        );

        $entityManager->clear();

        self::assertNotNull(
            $entityManager->getRepository(LegalDocument::class)->find($identifiant),
            'Un texte juridique publié a été supprimé : les acceptations qui le référencent perdent leur objet.',
        );
    }

    /**
     * L'inverse doit rester vrai, sinon la règle serait une impasse : un
     * BROUILLON se modifie librement.
     */
    public function testADraftCanStillBeEdited(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeAdmin());

        $document = $this->makeDocument(publie: false);

        $client->request('GET', sprintf('/admin/legal-document/%s/edit', $document->getId()));

        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertStringContainsString(
            'name="LegalDocument"',
            (string) $client->getResponse()->getContent(),
            'Un brouillon ne peut pas être modifié : plus rien ne serait saisissable.',
        );
    }

    /**
     * Un brouillon n'est pas visible sur le site tant qu'il n'est pas publié.
     */
    public function testADraftIsNotVisibleOnTheSite(): void
    {
        $client = static::createClient();
        $document = $this->makeDocument(publie: false);

        $client->request('GET', '/politique-de-cookies');

        self::assertStringNotContainsString(
            $document->getContent(),
            (string) $client->getResponse()->getContent(),
            'Un brouillon apparaît sur le site : « brouillon » ne veut plus rien dire.',
        );
    }

    /**
     * Le bouton « Publier » met réellement le texte en ligne.
     */
    public function testPublishingADraftPutsItOnline(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeAdmin());

        $this->removeAllVersions(LegalDocumentType::CookiePolicy);
        $document = $this->makeDocument(publie: false);

        $client->request('GET', sprintf('/admin/legal-document/%s/publish', $document->getId()));

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $relu = $entityManager->getRepository(LegalDocument::class)->find($document->getId());

        self::assertInstanceOf(LegalDocument::class, $relu);
        self::assertTrue($relu->isPublished(), "Le bouton « Publier » n'a pas publié le texte.");

        // Et surtout : il est maintenant sur le site.
        $client->request('GET', '/politique-de-cookies');
        self::assertStringContainsString(
            'Temoin de publication',
            (string) $client->getResponse()->getContent(),
            "Le texte publié depuis le back-office n'apparaît pas sur le site.",
        );
    }

    private function makeDocument(bool $publie): LegalDocument
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $document = (new LegalDocument())
            ->setType(LegalDocumentType::CookiePolicy)
            ->setLocale('fr')
            ->setVersion('t'.substr(uniqid(), -8))
            ->setTitle('Politique de cookies')
            ->setContent('<h2>Temoin de publication</h2><p>Corps du temoin.</p>');

        if ($publie) {
            $document->publish();
        }

        $entityManager->persist($document);
        $entityManager->flush();

        return $document;
    }

    private function removeAllVersions(LegalDocumentType $type): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        foreach ($entityManager->getRepository(LegalDocument::class)->findBy(['type' => $type]) as $document) {
            $entityManager->remove($document);
        }

        $entityManager->flush();
    }

    private function makeAdmin(): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())
            ->setEmail(sprintf('admin-legal-%s@example.com', uniqid()))
            ->setFirstName('Guillaume')
            ->setLastName('Test')
            ->setRoles(['ROLE_ADMIN'])
            ->setStatus(UserStatus::Active);
        $user->setPassword('peu-importe');

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
