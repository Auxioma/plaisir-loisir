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
$files = [];
exec('git -C '.escapeshellarg($root).' ls-files -z', $out);
foreach (explode("\0", implode("\0", $out)) as $f) {
    if ('' !== trim($f)) {
        $files[] = $f;
    }
}

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
    if (!is_file($path)) {
        continue;
    }
    $ext = strtolower(pathinfo($file, \PATHINFO_EXTENSION));

    if (isset($magic[$ext])) {
        $head = (string) file_get_contents($path, false, null, 0, 8);
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
        $head = (string) file_get_contents($path, false, null, 0, 36);
        if ('LP' !== substr($head, 34, 2)) {
            $problems[] = sprintf('%s : contenu qui ne correspond pas à un fichier .eot', $file);
        }
    }

    if (str_starts_with($file, '.vscode/') || str_starts_with($file, '.idea/')) {
        $content = (string) file_get_contents($path);
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
