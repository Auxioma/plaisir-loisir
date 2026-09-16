<?php

/*
 * Routeur pour `php -S` en local.
 *
 * Sans lui, TOUTES les requêtes (y compris les fichiers statiques réels comme
 * public/bundles/easyadmin/*.css) passent par public/index.php : le serveur
 * intégré de PHP ne détecte alors plus le type MIME lui-même et répond
 * `Content-Type: text/html` même pour un .css ou un .woff2. Les navigateurs
 * refusent d'appliquer une feuille de style envoyée avec un mauvais type MIME
 * → CSS ignoré, icônes et mise en page cassées.
 *
 * En renvoyant `false` pour un fichier qui existe réellement, on laisse le
 * serveur intégré le servir lui-même : c'est cette voie qui déclenche sa
 * détection de type MIME correcte.
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = $_SERVER['DOCUMENT_ROOT'].$path;

if ('/' !== $path && is_file($file)) {
    return false;
}

require $_SERVER['DOCUMENT_ROOT'].'/index.php';
