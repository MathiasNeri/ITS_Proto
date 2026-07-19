<?php
// Téléchargement d'une sauvegarde de la base de données. Réservé à l'admin :
// les sauvegardes vivent hors du dossier public et ne sont jamais
// accessibles par une URL directe (même logique que devis-fichier.php).
require_once __DIR__ . '/../backend/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!checkAuth() || ($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Accès refusé.');
}

$nomFichier = basename($_GET['fichier'] ?? '');

// N'autorise que les fichiers de sauvegarde générés par le site (empêche
// tout accès à un autre fichier via un chemin détourné).
if (!preg_match('/^its-backup-\d{4}-\d{2}-\d{2}_\d{6}\.sqlite$/', $nomFichier)) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

$chemin = cheminDossierSauvegardes() . '/' . $nomFichier;

if (!is_file($chemin)) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $nomFichier . '"');
header('Content-Length: ' . filesize($chemin));
readfile($chemin);
exit();
