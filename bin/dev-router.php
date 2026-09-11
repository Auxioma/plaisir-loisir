<?php

/**
 * Routeur pour le serveur web intégré de PHP (`php -S`).
 *
 * POURQUOI CE FICHIER EXISTE
 * `php -S 127.0.0.1:8000 -t public/ public/index.php` sert les fichiers
 * physiques de `public/` (dont `public/bundles/easyadmin/*.css` et `*.js`
 * déposés par `assets:install`) avec l'en-tête `Content-Type: text/html`.
 * Les navigateurs, en MIME strict, refusent alors d'appliquer la feuille de
 * style et d'exécuter le script : le back-office EasyAdmin s'affiche sans
 * aucun style (menu en liste verticale, icônes énormes).
 *
 * Ce routeur renvoie les fichiers existants tels quels, mais en corrigeant
 * d'abord leur type. Tout le reste part vers le contrôleur frontal Symfony.
 *
 * En production, ce fichier n'a aucun rôle : un vrai serveur (nginx, Apache,
 * FrankenPHP) sert les statiques avec le bon type.
 *
 * Lancement :
 *   php -S 127.0.0.1:8000 -t public bin/dev-router.php
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . '/../public' . urldecode($path);

if ('/' !== $path && is_file($file)) {
    static $types = [
        'css' => 'text/css',
        'js' => 'text/javascript',
        'mjs' => 'text/javascript',
        'json' => 'application/json',
        'map' => 'application/json',
        'svg' => 'image/svg+xml',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'avif' => 'image/avif',
    ];

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    if (isset($types[$ext])) {
        header('Content-Type: ' . $types[$ext]);
        header('Content-Length: ' . filesize($file));
        readfile($file);

        return true;
    }

    // Type déjà correctement géré par le serveur intégré : on le laisse faire.
    return false;
}

require __DIR__ . '/../public/index.php';
