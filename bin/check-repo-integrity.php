<?php

declare(strict_types=1);

/*
 * Contrôle d'intégrité du dépôt (13/10/2026, après une injection de code).
 *
 * Du JavaScript malveillant a été glissé à plusieurs reprises dans le dépôt,
 * déguisé en fichiers de police (.eot, .otf, .woff2) et lancé par une tâche
 * VS Code cachée (.vscode/tasks.json, « runOn: folderOpen »). Ce script fait
 * échouer la CI si l'on retrouve :
 *   - un fichier de police ou d'image dont le contenu n'a pas la signature
 *     binaire de son format (une « police » qui est en réalité du texte) ;
 *   - une configuration d'éditeur qui exécute une commande à l'ouverture du
 *     dossier, ou autorise les tâches automatiques.
 *
 * Usage : php bin/check-repo-integrity.php
 * Sortie : 0 si rien de suspect, 1 sinon.
 */

$root = dirname(__DIR__);
$staged = in_array('--staged', $argv, true);
$ref = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--ref=')) {
        $ref = substr($arg, 6);
    }
}
$listCommand = null === $ref ? 'ls-files --cached -z' : 'ls-tree -r --name-only -z '.escapeshellarg($ref);
$list = shell_exec('git -C '.escapeshellarg($root).' '.$listCommand);
if (null === $list) {
    fwrite(\STDERR, "[ERREUR] Impossible de lire l'index Git.\n");
    exit(1);
}
$files = array_values(array_filter(explode("\0", $list), static fn (string $file): bool => '' !== $file));

// Signatures binaires attendues (premiers octets) par extension.
$magic = [
    'woff2' => ['wOF2'],
    'woff' => ['wOFF'],
    'ttf' => ["\x00\x01\x00\x00", 'true', 'OTTO'],
    'otf' => ['OTTO', "\x00\x01\x00\x00"],
    'png' => ["\x89PNG"],
    'jpg' => ["\xFF\xD8\xFF"],
    'jpeg' => ["\xFF\xD8\xFF"],
    'gif' => ['GIF8'],
    'webp' => ['RIFF'],
    'ico' => ["\x00\x00\x01\x00"],
];

$problems = [];
foreach ($files as $file) {
    $path = $root.'/'.$file;
    if (!$staged && null === $ref && !is_file($path)) {
        continue;
    }
    $ext = strtolower(pathinfo($file, \PATHINFO_EXTENSION));
    // Le hook vérifie le contenu réellement indexé, même si le fichier de
    // travail a ensuite été remplacé par une version saine.
    $content = $staged || null !== $ref
        ? (string) shell_exec('git -C '.escapeshellarg($root).' show '.escapeshellarg(($ref ?? '').':'.$file))
        : (string) file_get_contents($path);

    if ('orc' === $ext) {
        $problems[] = sprintf('%s : extension utilisée pour une charge utile Node', $file);
    }

    if (isset($magic[$ext])) {
        $head = substr($content, 0, 8);
        $ok = false;
        foreach ($magic[$ext] as $sig) {
            if (str_starts_with($head, $sig)) {
                $ok = true;
                break;
            }
        }
        if (!$ok) {
            $problems[] = sprintf('%s : contenu qui ne correspond pas à un fichier .%s', $file, $ext);
        }
    }

    if ('eot' === $ext) {
        // EOT : octets 34-35 = « LP » (signature du format Embedded OpenType).
        $head = substr($content, 0, 36);
        if ('LP' !== substr($head, 34, 2)) {
            $problems[] = sprintf('%s : contenu qui ne correspond pas à un fichier .eot', $file);
        }
    }

    // Code caché en bout de ligne derrière des centaines d'espaces (invisible
    // dans l'éditeur, 20/10), et chargeurs Node exécutés hors navigateur.
    if (in_array($ext, ['js', 'mjs', 'cjs', 'php', 'twig', 'json', 'css'], true) && !str_starts_with($file, 'assets/vendor/')) {
        if (preg_match('/[ \t]{150,}\S/', $content)) {
            $problems[] = sprintf('%s : code dissimulé derrière une longue suite d\'espaces', $file);
        }
        if ('js' === $ext && preg_match('/\bcreateRequire\b|\bchild_process\b/', $content)) {
            $problems[] = sprintf('%s : chargeur Node (createRequire / child_process) dans un script front', $file);
        }
    }

    // Dossiers de leurres déjà utilisés pour cacher la charge utile.
    if (preg_match('#(^|/)public/fonts/|^dist/|^assets/lib/leaflet/images/public/#', $file)) {
        $problems[] = sprintf('%s : emplacement utilisé par le code malveillant (fausses polices / dist)', $file);
    }

    if (str_starts_with($file, '.vscode/') || str_starts_with($file, '.idea/')) {
        if (preg_match('/folderOpen|allowAutomaticTasks/i', $content)) {
            $problems[] = sprintf('%s : exécution automatique de commande à l\'ouverture du dossier', $file);
        }
    }
}

if ([] !== $problems) {
    fwrite(\STDERR, "[ERREUR] Fichiers suspects dans le dépôt :\n - ".implode("\n - ", $problems)."\n");
    exit(1);
}

echo '[OK] Aucun fichier suspect ('.count($files)." fichiers suivis contrôlés).\n";
exit(0);
