<?php
// Téléchargement du fichier joint à une demande de devis. Réservé à
// l'admin : les fichiers vivent hors du dossier public et ne sont
// jamais accessibles par une URL directe.
require_once __DIR__ . '/../backend/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!checkAuth() || ($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Accès refusé.');
}

$pdo = initDatabase();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT fichier_nom, fichier_chemin FROM devis WHERE id = ?');
$stmt->execute([$id]);
$devis = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$devis || empty($devis['fichier_chemin'])) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

$chemin = __DIR__ . '/../database/uploads/devis/' . basename($devis['fichier_chemin']);

if (!is_file($chemin)) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($devis['fichier_nom'] ?: $devis['fichier_chemin']) . '"');
header('Content-Length: ' . filesize($chemin));
readfile($chemin);
exit();
