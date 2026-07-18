<?php
require_once __DIR__ . '/../backend/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!checkAuth()) {
    redirect('connexion.php');
}

$pdo = initDatabase();
$commandeId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM commandes WHERE id = ?');
$stmt->execute([$commandeId]);
$commande = $stmt->fetch(PDO::FETCH_ASSOC);

$estProprietaire = $commande && (int) $commande['user_id'] === (int) $_SESSION['user_id'];
$estAdmin = ($_SESSION['user_role'] ?? '') === 'admin';

if (!$commande || $commande['statut'] === 'en_attente_paiement' || (!$estProprietaire && !$estAdmin)) {
    http_response_code(404);
    echo 'Facture introuvable.';
    exit();
}

$stmtLignes = $pdo->prepare('SELECT * FROM commande_lignes WHERE commande_id = ?');
$stmtLignes->execute([$commandeId]);
$lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);

$sousTotal = array_sum(array_map(function ($l) { return $l['prix_unitaire'] * $l['quantite']; }, $lignes));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Facture <?php echo htmlspecialchars($commande['numero']); ?> — ITS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #1c2226;
            background: #eef1f2;
            padding: 2rem;
        }
        .invoice {
            max-width: 760px;
            margin: 0 auto;
            background: #fff;
            padding: 3rem;
            border-radius: 8px;
        }
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid #1c2226;
        }
        .invoice-header h1 {
            font-size: 1.4rem;
        }
        .invoice-header .num {
            color: #e74c3c;
            font-weight: bold;
            font-size: 1.1rem;
        }
        .company {
            text-align: right;
            font-size: .82rem;
            color: #55606a;
            line-height: 1.5;
        }
        .parties {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            font-size: .88rem;
        }
        .parties h3 {
            font-size: .75rem;
            text-transform: uppercase;
            letter-spacing: .4px;
            color: #8a9399;
            margin-bottom: .4rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.5rem;
        }
        th, td {
            padding: .7rem .5rem;
            text-align: left;
            border-bottom: 1px solid #dfe4e6;
            font-size: .88rem;
        }
        th {
            background: #eef1f2;
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .3px;
            color: #55606a;
        }
        td.num, th.num { text-align: right; }
        .totals {
            margin-left: auto;
            width: 260px;
        }
        .totals div {
            display: flex;
            justify-content: space-between;
            padding: .35rem 0;
            font-size: .9rem;
        }
        .totals .grand-total {
            border-top: 2px solid #1c2226;
            margin-top: .4rem;
            padding-top: .6rem;
            font-weight: bold;
            font-size: 1.1rem;
        }
        .footer-note {
            margin-top: 3rem;
            padding-top: 1.5rem;
            border-top: 1px solid #dfe4e6;
            font-size: .75rem;
            color: #8a9399;
            text-align: center;
        }
        .print-btn {
            display: block;
            max-width: 760px;
            margin: 0 auto 1rem;
            text-align: right;
        }
        .print-btn button {
            background: #e74c3c;
            color: #fff;
            border: none;
            padding: .7rem 1.4rem;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .print-btn { display: none; }
            .invoice { border-radius: 0; padding: 1.5rem; }
        }

        @media (max-width: 600px) {
            body {
                padding: 1rem;
            }

            .invoice {
                padding: 1.5rem;
            }

            .invoice-header {
                flex-direction: column;
                gap: 1rem;
            }

            .company {
                text-align: left;
            }

            .parties {
                flex-direction: column;
                gap: 1.2rem;
            }

            .totals {
                width: 100%;
            }

            table {
                font-size: .78rem;
            }

            th, td {
                padding: .5rem .3rem;
            }
        }
    </style>
</head>
<body>
    <div class="print-btn"><button onclick="window.print()">Imprimer / Enregistrer en PDF</button></div>
    <div class="invoice">
        <div class="invoice-header">
            <div>
                <h1>Facture</h1>
                <div class="num"><?php echo htmlspecialchars($commande['numero']); ?></div>
                <div style="color:#55606a; font-size:.82rem; margin-top:.3rem;">Date : <?php echo date('d/m/Y', strtotime($commande['created_at'])); ?></div>
            </div>
            <div class="company">
                <strong>ITS — Informatique Téléphonie Service</strong><br>
                S.A.S INFOCOM PIERREFEU<br>
                Pierrefeu<br>
                contact@its-reparation.fr<br>
                SIRET : 893 676 023 00011<br>
                TVA : FR73893676023
            </div>
        </div>

        <div class="parties">
            <div>
                <h3>Facturé à</h3>
                <?php echo htmlspecialchars($commande['nom']); ?><br>
                <?php echo htmlspecialchars($commande['email']); ?><br>
                <?php echo nl2br(htmlspecialchars($commande['adresse'])); ?>
            </div>
            <div>
                <h3>Livraison</h3>
                <?php echo $commande['mode_livraison'] === 'colissimo' ? 'Colissimo' : 'Retrait en boutique'; ?><br>
                <?php if (!empty($commande['numero_suivi'])): ?>Suivi : <?php echo htmlspecialchars($commande['numero_suivi']); ?><?php endif; ?>
            </div>
        </div>

        <table>
            <thead>
                <tr><th>Article</th><th class="num">Prix unitaire</th><th class="num">Qté</th><th class="num">Total</th></tr>
            </thead>
            <tbody>
                <?php foreach ($lignes as $l): ?>
                <tr>
                    <td><?php echo htmlspecialchars($l['nom_produit']); ?></td>
                    <td class="num"><?php echo number_format($l['prix_unitaire'], 2, ',', ' '); ?> €</td>
                    <td class="num"><?php echo (int) $l['quantite']; ?></td>
                    <td class="num"><?php echo number_format($l['prix_unitaire'] * $l['quantite'], 2, ',', ' '); ?> €</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals">
            <div><span>Sous-total</span><span><?php echo number_format($sousTotal, 2, ',', ' '); ?> €</span></div>
            <?php if ($commande['remise'] > 0): ?>
                <div><span>Remise <?php echo $commande['code_promo'] ? '(' . htmlspecialchars($commande['code_promo']) . ')' : ''; ?></span><span>-<?php echo number_format($commande['remise'], 2, ',', ' '); ?> €</span></div>
            <?php endif; ?>
            <div><span>Livraison</span><span><?php echo $commande['frais_livraison'] > 0 ? number_format($commande['frais_livraison'], 2, ',', ' ') . ' €' : 'Gratuite'; ?></span></div>
            <div class="grand-total"><span>Total TTC</span><span><?php echo number_format($commande['total'], 2, ',', ' '); ?> €</span></div>
        </div>

        <div class="footer-note">
            ITS — Informatique Téléphonie Service (S.A.S INFOCOM PIERREFEU) · Pierrefeu · SIRET 893 676 023 00011 · TVA FR73893676023 · Prix TTC, TVA au taux normal de 20 % incluse
        </div>
    </div>
</body>
</html>
