<?php
// Routeur pour le serveur de développement intégré de PHP (php -S).
// Reproduit le comportement des ErrorDocument d'Apache défini dans .htaccess,
// que le serveur intégré ignore autrement.
// Lancement : php -S localhost:8000 -t public public/router.php

$path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . $path;

// Fichier ou asset existant (css, images, .php...) : le serveur le sert normalement.
if ($path !== '/' && file_exists($file) && !is_dir($file)) {
    return false;
}

if ($path === '/') {
    require __DIR__ . '/accueil.php';
    return true;
}

http_response_code(404);
require __DIR__ . '/404.php';
