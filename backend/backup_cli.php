<?php
// Script en ligne de commande pour déclencher une sauvegarde de la base,
// destiné à un vrai cron (crontab Linux, tâche planifiée Windows, ou un
// Render Cron Job si vous passez à un plan payant) plutôt qu'au
// déclenchement « à l'improviste » utilisé par défaut (voir backup.php).
//
// Utilisation : php backend/backup_cli.php
// Exemple crontab (tous les jours à 3h) :
//   0 3 * * * /usr/bin/php /chemin/vers/ITS_Proto/backend/backup_cli.php

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Ce script ne s\'exécute qu\'en ligne de commande.');
}

require_once __DIR__ . '/config.php';

try {
    $chemin = creerSauvegardeBaseDeDonnees();
    echo 'Sauvegarde créée : ' . $chemin . PHP_EOL;

    if (envoyerSauvegardeParEmail($chemin)) {
        echo 'Sauvegarde envoyée par email à l\'admin.' . PHP_EOL;
    } else {
        echo 'Sauvegarde locale uniquement (SMTP non configuré ou fichier trop volumineux).' . PHP_EOL;
    }
} catch (Exception $e) {
    fwrite(STDERR, 'Échec de la sauvegarde : ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
