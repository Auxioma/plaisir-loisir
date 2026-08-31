<?php

declare(strict_types=1);

namespace App\Tests\Legal;

use App\Legal\Entity\LegalAcceptance;
use App\Legal\Entity\LegalDocument;
use App\Legal\Enum\LegalDocumentType;
use App\Legal\Service\LegalDocumentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Les pages juridiques lisent la base.
 *
 * CE QUI EST VRAIMENT VÉRIFIÉ ICI
 * Pas que les pages répondent 200 — elles répondaient déjà avec un texte écrit
 * en PHP. Ce qui compte, c'est qu'un texte PUBLIÉ DEPUIS LA BASE apparaisse
 * sur le site, et qu'une nouvelle version REMPLACE la précédente sans
 * déploiement. C'est exactement la demande du CTO du 29/08 : « les conditions
 * générales peuvent évoluer dans le temps ».
 *
 * On vérifie aussi le cas qui n'existait pas avant : une page dont AUCUNE
 * version n'est publiée. Elle ne doit pas renvoyer 404, parce que son adresse
 * est citée dans le pied de page de tout le site et dans les cases à cocher de
 * l'inscription.
 */
final class LegalPagesTest extends WebTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function legalUrls(): iterable
    {
        yield 'mentions légales' => ['/mentions-legales'];
        yield 'conditions générales' => ['/conditions-generales'];
        yield 'conditions de vente' => ['/conditions-generales-de-vente'];
        yield 'confidentialité' => ['/politique-de-confidentialite'];
        yield 'cookies' => ['/politique-de-cookies'];
        yield 'anglais — confidentialité' => ['/en/privacy-policy'];
        yield 'anglais — conditions de vente' => ['/en/terms-of-sale'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('legalUrls')]
    public function testEveryLegalPageAnswers(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url);

        self::assertSame(
            200,
            $client->getResponse()->getStatusCode(),
            sprintf('%s ne répond pas : le pied de page y renvoie depuis toutes les pages du site.', $url),
        );
    }

    /**
     * LE TEST QUI COMPTE : ce qui est saisi en base s'affiche sur le site.
     */
    public function testAPublishedTextAppearsOnTheSite(): void
    {
        $client = static::createClient();
        $marqueur = 'Article temoin '.uniqid();

        // La base de test n'est pas remise à zéro entre deux exécutions : sans
        // ce nettoyage, les versions laissées par un essai précédent
        // resteraient en vigueur et masqueraient celle qu'on publie ici.
        $this->removeAllVersions(LegalDocumentType::CookiePolicy);

        $this->publish(LegalDocumentType::CookiePolicy, 'c'.substr(uniqid(), -8), '<h2>'.$marqueur.'</h2><p>Le corps du temoin.</p>');

        $crawler = $client->request('GET', '/politique-de-cookies');

        self::assertStringContainsString(
            $marqueur,
            (string) $client->getResponse()->getContent(),
            "Le texte publié en base n'apparaît pas sur la page : la page ne lit pas la base.",
        );

        // Le sommaire se déduit des titres : personne ne le saisit.
        self::assertStringContainsString(
            $marqueur,
            $crawler->filter('.lg-toc__list')->text(''),
            'Le titre publié ne remonte pas dans le sommaire « Sur cette page ».',
        );
    }

    /**
     * L'évolution dans le temps, qui est la raison d'être de la table.
     */
    public function testANewVersionReplacesThePreviousOneWithoutDeployment(): void
    {
        $client = static::createClient();
        $ancien = 'Ancienne redaction '.uniqid();
        $nouveau = 'Nouvelle redaction '.uniqid();

        $this->removeAllVersions(LegalDocumentType::TermsOfSale);

        // Les dates sont explicites : la colonne est un TIMESTAMP à la
        // seconde, et deux publications faites dans la même seconde ne
        // seraient pas départageables. Ce n'est pas un problème dans la vie
        // réelle — personne ne publie deux versions de CGU en une seconde —
        // mais un test ne doit pas reposer sur la vitesse de la machine.
        $this->publish(LegalDocumentType::TermsOfSale, 'a'.substr(uniqid(), -8), '<h2>'.$ancien.'</h2><p>Texte initial.</p>', new \DateTimeImmutable('-2 days'));
        $client->request('GET', '/conditions-generales-de-vente');
        self::assertStringContainsString($ancien, (string) $client->getResponse()->getContent());

        $this->publish(LegalDocumentType::TermsOfSale, 'b'.substr(uniqid(), -8), '<h2>'.$nouveau.'</h2><p>Texte revise.</p>', new \DateTimeImmutable('-1 day'));
        $client->request('GET', '/conditions-generales-de-vente');

        $page = (string) $client->getResponse()->getContent();
        self::assertStringContainsString($nouveau, $page, "La nouvelle version ne s'affiche pas.");
        self::assertStringNotContainsString($ancien, $page, "L'ancienne version s'affiche encore alors qu'une plus récente est en vigueur.");
    }

    /**
     * Un texte publié en base ne doit jamais ressortir avec son script.
     */
    public function testAScriptStoredInTheDatabaseNeverReachesThePage(): void
    {
        $client = static::createClient();
        $this->removeAllVersions(LegalDocumentType::CookiePolicy);

        $this->publish(
            LegalDocumentType::CookiePolicy,
            'x'.substr(uniqid(), -8),
            '<h2>Traceurs</h2><p>Texte licite.</p><script>alert("xss")</script>',
        );

        $client->request('GET', '/politique-de-cookies');
        $page = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('Texte licite.', $page);
        self::assertStringNotContainsString('alert("xss")', $page, 'Un script stocké en base est ressorti dans la page.');
    }

    /**
     * Le cas qui n'existait pas avant : aucune version publiée.
     */
    public function testAPageWithoutAnyPublishedVersionExplainsItselfInsteadOfFailing(): void
    {
        $client = static::createClient();
        $this->removeAllVersions(LegalDocumentType::PrivacyPolicy);

        $crawler = $client->request('GET', '/politique-de-confidentialite');

        self::assertSame(200, $client->getResponse()->getStatusCode(), 'Une page sans texte ne doit pas renvoyer 404 : son adresse est citée dans tout le site.');
        self::assertGreaterThan(0, $crawler->filter('.lg-pending')->count(), 'La page ne dit pas que le texte est en préparation : le visiteur voit une page vide.');
        self::assertGreaterThan(0, $crawler->filter('.lg-pending a[href*="contact"]')->count(), 'Aucune porte de sortie proposée au visiteur.');
    }

    /**
     * Les liens du pied de page ne pointent plus dans le vide.
     */
    public function testTheFooterNoLongerLinksToNowhereForLegalTexts(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $attendus = [
            '/centre-d-aide',
            '/faq',
            '/conditions-generales',
            '/conditions-generales-de-vente',
            '/politique-de-confidentialite',
            '/mentions-legales',
        ];

        $liens = $crawler->filter('.pl-footer a')->each(static fn ($noeud): string => (string) $noeud->attr('href'));

        foreach ($attendus as $attendu) {
            self::assertContains($attendu, $liens, sprintf('Le pied de page ne mène pas à %s.', $attendu));
        }
    }

    /**
     * La case à cocher de l'inscription mène à un vrai texte.
     *
     * C'était le point le plus sérieux : on faisait accepter une politique de
     * confidentialité dont le lien pointait sur « # ».
     */
    public function testTheRegistrationConsentLinksToARealPage(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/inscription');

        self::assertGreaterThan(
            0,
            $crawler->filter('a[href*="politique-de-confidentialite"]')->count(),
            "La case « J'accepte … la politique de confidentialité » ne mène toujours nulle part.",
        );
    }

    private function publish(LegalDocumentType $type, string $version, string $contenu, ?\DateTimeImmutable $enVigueur = null): void
    {
        $service = static::getContainer()->get(LegalDocumentService::class);
        self::assertInstanceOf(LegalDocumentService::class, $service);

        $service->publish(
            type: $type,
            version: $version,
            title: $type->label(),
            content: $contenu,
            effectiveAt: $enVigueur,
        );
    }

    /**
     * Efface toutes les versions d'un document, pour observer l'état « aucun
     * texte publié ».
     *
     * Les ACCEPTATIONS sont retirées d'abord, et c'est le point intéressant :
     * la clé étrangère de legal_acceptance est en RESTRICT, si bien que la
     * base refuse de supprimer un document que quelqu'un a accepté. C'est la
     * protection voulue — un texte accepté ne s'efface pas — et elle s'applique
     * aussi à nous. Les acceptations effacées ici sont celles fabriquées par
     * les tests d'inscription, pas des donnees reelles.
     */
    private function removeAllVersions(LegalDocumentType $type): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $documents = $entityManager->getRepository(LegalDocument::class)->findBy(['type' => $type]);

        if ([] === $documents) {
            return;
        }

        // Passage par le repository plutôt qu'un DELETE en DQL : l'identifiant
        // est un ULID côté entité et un UUID côté colonne, et la conversion ne
        // se fait correctement que par la couche objet.
        foreach ($documents as $document) {
            foreach ($entityManager->getRepository(LegalAcceptance::class)->findBy(['document' => $document]) as $acceptation) {
                $entityManager->remove($acceptation);
            }

            $entityManager->remove($document);
        }

        $entityManager->flush();
    }
}
