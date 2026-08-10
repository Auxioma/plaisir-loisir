<?php

declare(strict_types=1);

/*
 * Vérifie que chaque var(--pl-…) employée dans assets/styles/ correspond bien à
 * un jeton déclaré dans design-system.css.
 *
 * Pourquoi ce garde-fou : en CSS, une variable non résolue rend TOUTE la
 * déclaration invalide, et le navigateur l'ignore sans le moindre avertissement.
 * Un simple `padding-block: var(--pl-sp-32) var(--pl-sp-40)` avec un jeton
 * --pl-sp-40 inexistant supprime donc l'espacement en entier. Ce bug a fait
 * disparaître le rythme vertical de cinq parcours sans qu'aucun outil ne le
 * signale (le CSS reste valide au sens de la syntaxe).
 *
 * Usage : php bin/check-css-tokens.php
 * Sortie : 0 si tout est déclaré, 1 sinon.
 */

$stylesDir = __DIR__ . '/../assets/styles';
$tokensFile = $stylesDir . '/design-system.css';

if (!is_file($tokensFile)) {
    fwrite(STDERR, "Introuvable : {$tokensFile}\n");
    exit(1);
}

// Jetons déclarés : « --pl-quelque-chose: valeur; » en partie gauche.
preg_match_all('/^\s*(--pl-[a-z0-9-]+)\s*:/mi', (string) file_get_contents($tokensFile), $m);
$declared = array_flip($m[1]);

$missing = [];

/**
 * Neutralise les commentaires /* … *\/ sans décaler la numérotation : chaque
 * caractère est remplacé par une espace, les retours à la ligne sont conservés.
 */
$stripComments = static function (string $css): string {
    return (string) preg_replace_callback(
        '#/\*.*?\*/#s',
        static fn (array $m): string => preg_replace('/[^\n]/', ' ', $m[0]) ?? '',
        $css
    );
};

foreach (glob($stylesDir . '/*.css') ?: [] as $file) {
    $lines = explode("\n", $stripComments((string) file_get_contents($file)));

    foreach ($lines as $i => $line) {
        // On ne retient que les var(--pl-…) SANS valeur de repli : avec un repli,
        // la déclaration reste valide, il n'y a pas de disparition silencieuse.
        if (!preg_match_all('/var\(\s*(--pl-[a-z0-9-]+)\s*\)/i', $line, $uses)) {
            continue;
        }

        foreach ($uses[1] as $name) {
            if (!isset($declared[$name])) {
                $missing[] = sprintf('%s:%d  %s', basename($file), $i + 1, $name);
            }
        }
    }
}

if ([] === $missing) {
    printf("[OK] Tous les jetons --pl-* employés sont déclarés (%d jetons dans design-system.css).\n", \count($declared));
    exit(0);
}

fwrite(STDERR, sprintf("[ERREUR] %d utilisation(s) de jeton non déclaré — la déclaration CSS entière sera ignorée par le navigateur :\n\n", \count($missing)));

foreach ($missing as $line) {
    fwrite(STDERR, "  {$line}\n");
}

fwrite(STDERR, "\nAjoutez le jeton manquant dans assets/styles/design-system.css.\n");

exit(1);
