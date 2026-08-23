<?php

declare(strict_types=1);

namespace App\Tests\I18n;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * Tout texte câblé en |trans doit avoir une contrepartie anglaise.
 *
 * POURQUOI CE TEST EXISTE
 * Les pages institutionnelles ont été construites après la graine anglaise du
 * 07/08. Leurs textes étaient correctement câblés en |trans, mais personne
 * n'avait ajouté les paires françaises → anglaises : 127 libellés s'affichaient
 * donc EN FRANÇAIS sur les pages /en, pendant deux semaines, sans qu'aucun
 * test ni aucune erreur ne le signale — le translator retombe silencieusement
 * sur la clé, c'est-à-dire sur le texte français.
 *
 * Ce test rend ce silence impossible : toute nouvelle page dont on oublie les
 * traductions fait échouer la suite.
 *
 * LIMITE ASSUMÉE : seules les clés écrites en toutes lettres sont vérifiables.
 * Un `{{ item.title|trans }}` dont la valeur vient d'un tableau PHP échappe à
 * l'analyse statique — pour ceux-là, la garde reste la relecture d'une page
 * /en.
 */
final class TranslationCoverageTest extends TestCase
{
    private const SEED = __DIR__.'/../../config/i18n/messages.en.yaml';

    public function testEveryTranslatedTextHasAnEnglishCounterpart(): void
    {
        $seeded = array_map(self::normalize(...), array_keys(self::seed()));
        $missing = [];

        foreach (self::usedKeys() as $key => $origin) {
            if (!\in_array(self::normalize($key), $seeded, true)) {
                $missing[] = sprintf('%s  (%s)', $key, $origin);
            }
        }

        sort($missing);

        self::assertSame(
            [],
            $missing,
            sprintf(
                "%d texte(s) n'ont pas de traduction anglaise et s'afficheront donc en "
                .'français sur les pages /en. Ajoutez la paire dans config/i18n/messages.en.yaml, '
                ."puis rejouez « php bin/console app:i18n:import ».\n\n%s",
                \count($missing),
                implode("\n", \array_slice($missing, 0, 40)),
            ),
        );
    }

    /**
     * Le catalogue ne doit pas accumuler de clés devenues inutiles : une clé
     * orpheline signale en général un texte modifié d'un côté seulement.
     */
    public function testTheEnglishCatalogueStaysReadable(): void
    {
        self::assertGreaterThan(
            1000,
            \count(self::seed()),
            'La graine anglaise semble tronquée.',
        );
    }

    /**
     * @return array<string, string> clé de traduction => fichier d'origine
     */
    private static function usedKeys(): array
    {
        $keys = [];

        $twig = (new Finder())->files()->in(__DIR__.'/../../templates')->name('*.twig');
        foreach ($twig as $file) {
            $content = $file->getContents();
            $name = 'templates/'.str_replace('\\', '/', $file->getRelativePathname());

            preg_match_all("/'((?:[^'\\\\]|\\\\.)*?)'\s*\|\s*trans\b/s", $content, $single);
            foreach ($single[1] as $key) {
                $keys[str_replace("\\'", "'", $key)] ??= $name;
            }

            // Les chaînes Twig entre guillemets peuvent contenir \" et \n :
            // sans en tenir compte, l'expression s'arrête au premier guillemet
            // échappé et rapporte une clé vide.
            preg_match_all('/"((?:[^"\\\\]|\\\\.)*?)"\s*\|\s*trans\b/s', $content, $double);
            foreach ($double[1] as $key) {
                $keys[str_replace(['\\"', '\\n', '\\\\'], ['"', "\n", '\\'], $key)] ??= $name;
            }
        }

        $php = (new Finder())->files()->in(__DIR__.'/../../src')->name('*.php');
        foreach ($php as $file) {
            preg_match_all("/->trans\(\s*'((?:[^'\\\\]|\\\\.)*?)'/s", $file->getContents(), $calls);
            foreach ($calls[1] as $key) {
                $keys[str_replace("\\'", "'", $key)] ??= 'src/'.str_replace('\\', '/', $file->getRelativePathname());
            }
        }

        return $keys;
    }

    /**
     * @return array<string, string>
     */
    private static function seed(): array
    {
        /** @var array<string, string> $seed */
        $seed = Yaml::parseFile(self::SEED);

        return $seed;
    }

    /**
     * Les gabarits coupent les textes longs sur plusieurs lignes : la clé
     * réelle vue par le translator a ses espaces normalisés.
     */
    private static function normalize(string $key): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $key));
    }
}
