<?php

declare(strict_types=1);

namespace App\Tests\Legal;

use App\Legal\Service\LegalContentRenderer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Le rendu des textes juridiques saisis en base.
 *
 * DEUX CHOSES SONT VÉRIFIÉES ICI, ET ELLES N'ONT PAS LE MÊME POIDS.
 *
 * Le découpage en sections est du confort : il fabrique le sommaire sans que
 * personne ait à le tenir. S'il se trompe, la page est laide.
 *
 * Le FILTRAGE, lui, est une protection. Le contenu de ces pages est du HTML
 * affiché sans échappement — c'est la condition pour que le texte riche saisi
 * en back-office s'affiche vraiment. Si le filtre laissait passer un
 * <script>, un compte d'administration compromis suffirait à injecter du code
 * sur une page vue par tous les visiteurs. Ce test-là garde une porte.
 */
final class LegalContentRendererTest extends KernelTestCase
{
    public function testEachLevelTwoHeadingOpensASection(): void
    {
        $sections = $this->renderer()->sections(
            '<h2>Éditeur du site</h2><p>Premier article.</p>'
            .'<h2>Hébergeur</h2><p>Second article.</p>',
        );

        self::assertCount(2, $sections, 'Deux titres devraient donner deux sections.');
        self::assertSame('Éditeur du site', $sections[0]['title'], 'Les accents doivent survivre à la lecture du HTML.');
        self::assertStringContainsString('Premier article.', $sections[0]['html']);
        self::assertStringNotContainsString('Second article.', $sections[0]['html'], 'Le contenu du second article a débordé dans le premier.');
    }

    /**
     * Ce qui précède le premier titre n'est pas un article, mais ne doit pas
     * disparaître pour autant : un préambule est fréquent dans des CGU.
     */
    public function testTextBeforeTheFirstHeadingIsKeptWithoutATitle(): void
    {
        $sections = $this->renderer()->sections('<p>Préambule.</p><h2>Article premier</h2><p>Le corps.</p>');

        self::assertCount(2, $sections);
        self::assertNull($sections[0]['title'], "Le préambule ne doit pas s'inventer un titre.");
        self::assertStringContainsString('Préambule.', $sections[0]['html']);
        self::assertSame('Article premier', $sections[1]['title']);
    }

    public function testEmptyContentGivesNoSection(): void
    {
        self::assertSame([], $this->renderer()->sections(''));
        self::assertSame([], $this->renderer()->sections('   '));
    }

    /**
     * LE TEST QUI PROTÈGE : un script saisi en back-office ne doit pas
     * ressortir dans la page.
     */
    public function testAScriptIsRemovedEntirely(): void
    {
        $rendu = $this->renderer()->clean(
            '<p>Texte licite.</p><script>alert("vol de session")</script>',
        );

        self::assertStringContainsString('Texte licite.', $rendu, 'Le texte légitime a été perdu au passage.');
        self::assertStringNotContainsString('<script', $rendu, 'La balise <script> a survécu au filtrage.');
        self::assertStringNotContainsString('alert', $rendu, 'Le corps du script a survécu : « bloquer » ne suffit pas, il faut « supprimer ».');
    }

    /**
     * Un attribut d'événement est un script sans balise <script>.
     */
    public function testInlineEventHandlersAreRemoved(): void
    {
        $rendu = $this->renderer()->clean('<p onclick="alert(1)">Bonjour</p>');

        self::assertStringContainsString('Bonjour', $rendu);
        self::assertStringNotContainsString('onclick', $rendu, "L'attribut onclick a survécu.");
    }

    /**
     * Un lien « javascript: » est un script déguisé en lien.
     */
    public function testJavascriptLinksAreRefused(): void
    {
        $rendu = $this->renderer()->clean('<a href="javascript:alert(1)">Cliquez</a>');

        self::assertStringNotContainsString('javascript:', $rendu, 'Un lien javascript: a été conservé.');
    }

    /**
     * L'inverse doit rester vrai : le filtre ne doit pas casser un texte
     * juridique normal, sinon il serait contourné.
     */
    public function testOrdinaryLegalMarkupSurvives(): void
    {
        $rendu = $this->renderer()->clean(
            '<p>Voir les <a href="/conditions-generales-de-vente" title="CGV">conditions de vente</a>.</p>'
            .'<ul><li>Premier point</li><li>Second point</li></ul>'
            .'<p><strong>Important</strong> et <em>nuancé</em>.</p>',
        );

        foreach (['<a href="/conditions-generales-de-vente"', '<ul>', '<li>', '<strong>', '<em>'] as $attendu) {
            self::assertStringContainsString($attendu, $rendu, sprintf('Le filtre a supprimé « %s », qu\'un texte juridique emploie couramment.', $attendu));
        }
    }

    private function renderer(): LegalContentRenderer
    {
        self::bootKernel();

        $renderer = self::getContainer()->get(LegalContentRenderer::class);
        self::assertInstanceOf(LegalContentRenderer::class, $renderer);

        return $renderer;
    }
}
