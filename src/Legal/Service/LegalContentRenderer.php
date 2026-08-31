<?php

declare(strict_types=1);

namespace App\Legal\Service;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

/**
 * Transforme le texte d'un document juridique, saisi en base par un
 * administrateur, en sections affichables par le gabarit.
 *
 * POURQUOI CETTE CLASSE EXISTE
 * La maquette des pages légales n'affiche pas un bloc de texte : elle affiche
 * un sommaire « Sur cette page » à gauche et des sections numérotées à droite.
 * Tant que le contenu était écrit en PHP, la structure était donnée à la main
 * — un tableau par section. Maintenant que le texte vient de la base, il faut
 * la retrouver, et surtout ne pas la demander à celui qui rédige : personne ne
 * doit tenir un sommaire à jour à la main, ni renuméroter en insérant un
 * article.
 *
 * LA RÈGLE, VOLONTAIREMENT SIMPLE
 * Chaque titre de niveau 2 ouvre une section. Le sommaire, la numérotation et
 * les ancres en découlent. Le rédacteur ne connaît qu'une chose : « un titre =
 * un article ».
 *
 * Le contenu est filtré AVANT découpage : le gabarit reçoit donc du HTML déjà
 * nettoyé, ce qui justifie le « raw » à l'affichage.
 */
final class LegalContentRenderer
{
    public function __construct(
        private readonly HtmlSanitizerInterface $legalContent,
    ) {
    }

    /**
     * Découpe le texte en sections, une par titre de niveau 2.
     *
     * Ce qui précède le premier titre — un préambule, souvent — n'est pas
     * perdu : il forme une section sans titre, que le gabarit affiche avant le
     * sommaire numéroté.
     *
     * @return list<array{title: ?string, html: string}>
     */
    public function sections(string $content): array
    {
        $propre = $this->legalContent->sanitize($content);

        if ('' === trim($propre)) {
            return [];
        }

        $document = new \DOMDocument();

        // Deux précautions indispensables :
        //  - la déclaration d'encodage, sans laquelle DOMDocument suppose du
        //    latin-1 et transforme les accents en caractères illisibles ;
        //  - la mise en silence des avertissements, car le HTML d'un éditeur
        //    de texte riche n'est pas du XML valide et n'a pas à l'être.
        $prealable = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"?><body>'.$propre.'</body>',
            \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($prealable);

        $corps = $document->getElementsByTagName('body')->item(0);

        if (null === $corps) {
            return [];
        }

        $sections = [];
        $courante = ['title' => null, 'html' => ''];

        foreach ($corps->childNodes as $noeud) {
            if ($noeud instanceof \DOMElement && 'h2' === $noeud->nodeName) {
                // Une section ne se referme que si elle contient quelque
                // chose : deux titres qui se suivent ne créent pas de section
                // vide au milieu du sommaire.
                if (null !== $courante['title'] || '' !== trim($courante['html'])) {
                    $sections[] = $courante;
                }

                $courante = ['title' => trim($noeud->textContent), 'html' => ''];

                continue;
            }

            $courante['html'] .= $document->saveHTML($noeud);
        }

        if (null !== $courante['title'] || '' !== trim($courante['html'])) {
            $sections[] = $courante;
        }

        return $sections;
    }

    /**
     * Le texte entier, filtré, sans découpage.
     *
     * Sert aux contenus qui n'ont pas de sommaire — une réponse de FAQ, par
     * exemple, qui tient en quelques phrases.
     */
    public function clean(string $content): string
    {
        return $this->legalContent->sanitize($content);
    }
}
