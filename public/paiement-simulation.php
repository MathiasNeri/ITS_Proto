<?php
require_once __DIR__ . '/../backend/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$is_logged_in = checkAuth();
$user_role = $_SESSION['user_role'] ?? '';

$pdo = initDatabase();

$commandeId = $_SESSION['commande_en_attente'] ?? null;
if (!$commandeId) {
    redirect('boutique.php');
}

$stmt = $pdo->prepare('SELECT * FROM commandes WHERE id = ? AND statut = ?');
$stmt->execute([$commandeId, 'en_attente_paiement']);
$commande = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$commande) {
    redirect('boutique.php');
}

$stmtLignes = $pdo->prepare('SELECT * FROM commande_lignes WHERE commande_id = ?');
$stmtLignes->execute([$commandeId]);
$lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfVerify()) {
    redirect('confirmation.php?commande_id=' . $commandeId);
}
?>
<?php include 'header.php'; ?>

<style>
    .page-container {
        max-width: 480px;
        margin: 2rem auto;
        padding: 0 2rem;
    }

    .sim-banner {
        background: #d8a316;
        color: #1c1c1c;
        text-align: center;
        font-weight: bold;
        font-size: .82rem;
        padding: .7rem;
        border-radius: 6px;
        margin-bottom: 1.5rem;
    }

    .checkout-card {
        background: var(--surface);
        border: 2px solid var(--surface-alt);
        border-radius: 12px;
        padding: 2rem;
    }

    .card-header {
        text-align: center;
        margin-bottom: 1.5rem;
    }

    .card-header .amount {
        font-size: 2.2rem;
        font-weight: 800;
        color: var(--text);
    }

    .card-header .merchant {
        color: var(--text-muted);
        font-size: .85rem;
    }

    .fake-field {
        border: 2px solid var(--surface-alt);
        border-radius: 6px;
        padding: .8rem;
        background: var(--surface-alt);
        color: var(--text-muted);
        font-size: .9rem;
        margin-bottom: 1rem;
    }

    .lignes {
        font-size: .82rem;
        color: var(--text-muted);
        margin-bottom: 1.5rem;
    }

    .lignes div {
        display: flex;
        justify-content: space-between;
        padding: .2rem 0;
    }

    .btn-pay {
        width: 100%;
        background: var(--accent);
        color: #fff;
        border: none;
        border-radius: 6px;
        padding: 1rem;
        font-weight: bold;
        font-size: 1rem;
        cursor: pointer;
    }

    .btn-pay:hover {
        background: var(--accent-hover);
    }

    .cancel-link {
        display: block;
        text-align: center;
        margin-top: 1rem;
        color: var(--text-muted);
        font-size: .82rem;
        text-decoration: none;
    }
</style>

<main class="main-content">
    <div class="page-container">
        <div class="sim-banner">⚠️ Mode simulation — aucun prestataire de paiement configuré, aucun encaissement réel</div>

        <div class="checkout-card">
            <div class="card-header">
                <div class="merchant">ITS — Informatique Téléphonie Service</div>
                <div class="amount"><?php echo number_format($commande['total'], 2, ',', ' '); ?> €</div>
            </div>

            <div class="lignes">
                <?php foreach ($lignes as $l): ?>
                    <div><span><?php echo htmlspecialchars($l['nom_produit']); ?> × <?php echo (int) $l['quantite']; ?></span><span><?php echo number_format($l['prix_unitaire'] * $l['quantite'], 2, ',', ' '); ?> €</span></div>
                <?php endforeach; ?>
            </div>

            <div class="fake-field">💳 •••• •••• •••• 4242 — carte de test</div>
            <div class="fake-field">Titulaire : <?php echo htmlspecialchars($commande['nom']); ?></div>

            <form method="POST">
                <?php echo csrfField(); ?>
                <button type="submit" class="btn-pay">Payer <?php echo number_format($commande['total'], 2, ',', ' '); ?> € (simulation)</button>
            </form>
            <a href="paiement-annule.php" class="cancel-link">Annuler et revenir au panier</a>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>
