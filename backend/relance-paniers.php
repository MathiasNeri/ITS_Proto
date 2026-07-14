<?php
// Script de relance des paniers abandonnés — À LANCER EN CRON, PAS EN WEB.
// Repère les commandes créées mais jamais payées depuis plus de 2h et
// envoie un email de relance (une seule fois par commande).
//
// Planification :
//   - OVH / hébergement mutualisé : tâche CRON dans l'espace client,
//     commande : php /chemin/vers/backend/relance-paniers.php
//     fréquence conseillée : toutes les heures
//   - Linux (crontab -e) :
//     0 * * * * php /chemin/vers/ITS_Proto/backend/relance-paniers.php >> /var/log/its-relance.log 2>&1
//   - Windows (local, test) : Planificateur de tâches -> action
//     "php.exe" avec argument "C:\...\backend\relance-paniers.php"

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Ce script ne peut être exécuté qu\'en ligne de commande (cron).');
}

require_once __DIR__ . '/config.php';

$pdo = initDatabase();

$seuil = date('Y-m-d H:i:s', strtotime('-2 hours'));

$stmt = $pdo->prepare("SELECT * FROM commandes
    WHERE statut = 'en_attente_paiement'
    AND relance_envoyee = 0
    AND created_at <= ?");
$stmt->execute([$seuil]);
$commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmtLignes = $pdo->prepare('SELECT * FROM commande_lignes WHERE commande_id = ?');
$compteur = 0;

foreach ($commandes as $commande) {
    $stmtLignes->execute([$commande['id']]);
    $lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);

    if (empty($lignes)) {
        continue;
    }

    $lignesHtml = '';
    foreach ($lignes as $l) {
        $lignesHtml .= '<tr>
            <td style="padding:6px 0;">' . htmlspecialchars($l['nom_produit']) . ' × ' . (int) $l['quantite'] . '</td>
            <td style="padding:6px 0; text-align:right;">' . number_format($l['prix_unitaire'] * $l['quantite'], 2, ',', ' ') . ' €</td>
        </tr>';
    }

    $lienBoutique = rtrim($config['base_url'], '/') . '/boutique.php';

    $contenu = '
        <p>Bonjour ' . htmlspecialchars($commande['nom']) . ',</p>
        <p>Vous avez laissé ces articles dans votre panier ITS sans finaliser votre commande :</p>
        <table style="width:100%; border-collapse: collapse; margin: 16px 0;">' . $lignesHtml . '</table>
        <p><strong>Total : ' . number_format($commande['total'], 2, ',', ' ') . ' €</strong></p>
        <p><a href="' . htmlspecialchars($lienBoutique) . '" style="background:#e74c3c;color:#fff;padding:12px 20px;border-radius:6px;text-decoration:none;font-weight:bold;display:inline-block;">Reprendre ma commande</a></p>
        <p>Ces articles ne sont pas réservés : en cas de forte demande, le stock peut évoluer.</p>';

    $envoye = sendMail($commande['email'], 'Vous avez oublié quelque chose 🛒', emailTemplate('Votre panier vous attend', $contenu));

    $pdo->prepare('UPDATE commandes SET relance_envoyee = 1 WHERE id = ?')->execute([$commande['id']]);

    if ($envoye) {
        $compteur++;
    }
}

echo date('Y-m-d H:i:s') . " — {$compteur} relance(s) envoyée(s) sur " . count($commandes) . " panier(s) abandonné(s) détecté(s).\n";
