<?php
require_once __DIR__ . '/../backend/config.php';

header('Content-Type: text/plain; charset=UTF-8');

$base = rtrim($config['base_url'], '/');

echo "User-agent: *\n";
echo "Allow: /\n";
echo "Disallow: /panier.php\n";
echo "Disallow: /commande.php\n";
echo "Disallow: /confirmation.php\n";
echo "Disallow: /paiement-simulation.php\n";
echo "Disallow: /paiement-annule.php\n";
echo "Disallow: /connexion.php\n";
echo "Disallow: /inscription.php\n";
echo "Disallow: /mot-de-passe-oublie.php\n";
echo "Disallow: /reinitialiser-mot-de-passe.php\n";
echo "Disallow: /profil.php\n";
echo "Disallow: /mes-commandes.php\n";
echo "Disallow: /facture.php\n";
echo "Disallow: /administration.php\n";
echo "Disallow: /cart_action.php\n";
echo "Disallow: /devis-fichier.php\n";
echo "Disallow: /backup-download.php\n";
echo "Disallow: /logout.php\n";
echo "\n";
echo "Sitemap: " . $base . "/sitemap.xml\n";
