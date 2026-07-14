<?php
require_once '../backend/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!checkAuth()) {
    redirect('connexion.php');
}

$is_logged_in = true;
$user_role = $_SESSION['user_role'] ?? '';

$pdo = initDatabase();

$stmt = $pdo->prepare("SELECT * FROM commandes WHERE user_id = ? AND statut != 'en_attente_paiement' ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmtLignes = $pdo->prepare('SELECT * FROM commande_lignes WHERE commande_id = ?');

$statutLabels = [
    'nouvelle'       => ['label' => 'Nouvelle', 'color' => 'var(--accent-2)'],
    'preparation'    => ['label' => 'En préparation', 'color' => '#d8a316'],
    'expediee'       => ['label' => 'Expédiée', 'color' => 'var(--accent-2)'],
    'livree'         => ['label' => 'Livrée / retirée', 'color' => 'var(--success)'],
    'annulee'        => ['label' => 'Annulée', 'color' => 'var(--accent)'],
];
?>
<?php include 'header.php'; ?>

<style>
    .page-container {
        max-width: 900px;
        margin: 2rem auto;
        padding: 0 2rem;
    }

    .page-title {
        font-size: 2.2rem;
        margin-bottom: 2rem;
        color: var(--accent);
        text-align: center;
    }

    .order-card {
        background: var(--surface);
        border: 2px solid var(--surface-alt);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.2rem;
    }

    .order-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: .6rem;
        margin-bottom: .8rem;
        padding-bottom: .8rem;
        border-bottom: 1px solid var(--surface-alt);
    }

    .order-num {
        font-weight: 800;
        color: var(--text);
    }

    .order-date {
        color: var(--text-muted);
        font-size: .85rem;
    }

    .order-status {
        font-size: .7rem;
        font-weight: 800;
        letter-spacing: .3px;
        text-transform: uppercase;
        padding: 4px 10px;
        border-radius: 12px;
        color: #fff;
    }

    .order-lines {
        font-size: .88rem;
        color: var(--text-muted);
        margin-bottom: .6rem;
    }

    .order-lines div {
        display: flex;
        justify-content: space-between;
        padding: .2rem 0;
    }

    .order-total {
        text-align: right;
        font-weight: 800;
        color: var(--text);
        font-size: 1.05rem;
    }

    .empty-state {
        text-align: center;
        padding: 3rem 0;
        color: var(--text-muted);
    }

    .empty-state a {
        color: var(--accent-2);
        font-weight: bold;
        text-decoration: none;
    }

    .facture-link {
        display: inline-block;
        margin-top: .8rem;
        color: var(--accent-2);
        font-size: .82rem;
        font-weight: bold;
        text-decoration: none;
    }

    .facture-link:hover {
        text-decoration: underline;
    }
</style>

<main class="main-content">
    <div class="page-container">
        <h1 class="page-title">📦 Mes commandes</h1>

        <?php if (empty($commandes)): ?>
            <div class="empty-state">Vous n'avez pas encore passé de commande.<br><a href="boutique.php">Découvrir la boutique →</a></div>
        <?php else: ?>
            <?php foreach ($commandes as $commande): ?>
                <?php
                $stmtLignes->execute([$commande['id']]);
                $lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);
                $statutInfo = $statutLabels[$commande['statut']] ?? ['label' => $commande['statut'], 'color' => 'var(--text-muted)'];
                ?>
                <div class="order-card">
                    <div class="order-head">
                        <div>
                            <div class="order-num"><?php echo htmlspecialchars($commande['numero']); ?></div>
                            <div class="order-date"><?php echo date('d/m/Y à H:i', strtotime($commande['created_at'])); ?></div>
                        </div>
                        <span class="order-status" style="background: <?php echo $statutInfo['color']; ?>;"><?php echo htmlspecialchars($statutInfo['label']); ?></span>
                    </div>
                    <div class="order-lines">
                        <?php foreach ($lignes as $ligne): ?>
                            <div><span><?php echo htmlspecialchars($ligne['nom_produit']); ?> × <?php echo (int) $ligne['quantite']; ?></span><span><?php echo number_format($ligne['prix_unitaire'] * $ligne['quantite'], 2, ',', ' '); ?> €</span></div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($commande['mode_livraison'] === 'colissimo'): ?>
                        <div class="order-lines"><div><span>📦 Livraison Colissimo</span><span><?php echo !empty($commande['numero_suivi']) ? 'Suivi : ' . htmlspecialchars($commande['numero_suivi']) : 'En attente d\'expédition'; ?></span></div></div>
                    <?php endif; ?>
                    <div class="order-total">Total : <?php echo number_format($commande['total'], 2, ',', ' '); ?> €</div>
                    <a href="facture.php?id=<?php echo $commande['id']; ?>" target="_blank" class="facture-link">🧾 Voir la facture</a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<?php include 'footer.php'; ?>
