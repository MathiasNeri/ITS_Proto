<?php
require_once '../backend/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Détruire la session
session_destroy();

// Rediriger vers la page d'accueil
header('Location: accueil.php');
exit();
?>