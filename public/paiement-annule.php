<?php
require_once '../backend/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$is_logged_in = checkAuth();
$user_role = $_SESSION['user_role'] ?? '';

$pdo = initDatabase();

// Marque la commande en attente comme annulée (le panier, lui, reste
// intact : rien n'a jamais été débité ni décrémenté du stock).
if (!empty($_SESSION['commande_en_attente'])) {
    $pdo->prepare("UPDATE commandes SET statut = 'annulee' WHERE id = ? AND statut = 'en_attente_paiement'")
        ->execute([$_SESSION['commande_en_attente']]);
    unset($_SESSION['commande_en_attente']);
}
?>
<?php include 'header.php'; ?>

<style>
    .page-container {
        max-width: 520px;
        margin: 2rem auto;
        padding: 0 2rem;
    }

    .checkout-card {
        background: var(--surface);
        border: 2px solid var(--surface-alt);
        border-radius: 12px;
        padding: 2.5rem 2rem;
        text-align: center;
    }

    .icon {
        font-size: 2.6rem;
        margin-bottom: 1rem;
    }

    .checkout-card h1 {
        color: var(--text);
        margin-bottom: .8rem;
        font-size: 1.4rem;
    }

    .checkout-card p {
        color: var(--text-muted);
    }

    .btn-secondary {
        display: inline-block;
        margin-top: 1.5rem;
        background: var(--accent-2);
        color: #fff;
        padding: .8rem 1.6rem;
        border-radius: 6px;
        text-decoration: none;
        font-weight: bold;
    }

    .btn-secondary:hover {
        background: var(--accent-2-hover);
    }
</style>

<main class="main-content">
    <div class="page-container">
        <div class="checkout-card">
            <div class="icon">🛒</div>
            <h1>Paiement annulé</h1>
            <p>Votre commande n'a pas été validée. Votre panier a été conservé, vous pouvez réessayer quand vous le souhaitez.</p>
            <a href="panier.php" class="btn-secondary">Retour au panier</a>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>
