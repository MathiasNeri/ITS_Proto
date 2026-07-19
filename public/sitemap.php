<?php
require_once __DIR__ . '/../backend/config.php';

header('Content-Type: application/xml; charset=UTF-8');

$base = rtrim($config['base_url'], '/');

// Pages statiques du site (hors comptes/panier/paiement, non indexables —
// voir robots.txt), avec une priorité relative indicative.
$pagesStatiques = [
    ['loc' => '/', 'priority' => '1.0', 'changefreq' => 'weekly'],
    ['loc' => '/boutique.php', 'priority' => '0.9', 'changefreq' => 'daily'],
    ['loc' => '/configurateur.php', 'priority' => '0.8', 'changefreq' => 'weekly'],
    ['loc' => '/reparations.php', 'priority' => '0.9', 'changefreq' => 'monthly'],
    ['loc' => '/tarifs.php', 'priority' => '0.7', 'changefreq' => 'monthly'],
    ['loc' => '/rdv.php', 'priority' => '0.7', 'changefreq' => 'monthly'],
    ['loc' => '/contact.php', 'priority' => '0.6', 'changefreq' => 'monthly'],
    ['loc' => '/cgv.php', 'priority' => '0.2', 'changefreq' => 'yearly'],
    ['loc' => '/mentions-legales.php', 'priority' => '0.2', 'changefreq' => 'yearly'],
    ['loc' => '/confidentialite.php', 'priority' => '0.2', 'changefreq' => 'yearly'],
];

$produits = [];
try {
    $pdo = initDatabase();
    $produits = $pdo->query('SELECT id, created_at FROM produits')->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    logError('Génération sitemap.xml : ' . $e->getMessage());
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

foreach ($pagesStatiques as $page) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars($base . $page['loc']) . "</loc>\n";
    echo '    <changefreq>' . $page['changefreq'] . "</changefreq>\n";
    echo '    <priority>' . $page['priority'] . "</priority>\n";
    echo "  </url>\n";
}

foreach ($produits as $produit) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars($base . '/produit.php?id=' . $produit['id']) . "</loc>\n";
    if (!empty($produit['created_at'])) {
        echo '    <lastmod>' . date('Y-m-d', strtotime($produit['created_at'])) . "</lastmod>\n";
    }
    echo "    <changefreq>weekly</changefreq>\n";
    echo "    <priority>0.6</priority>\n";
    echo "  </url>\n";
}

echo '</urlset>' . "\n";
