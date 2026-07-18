<?php
require_once __DIR__ . '/../backend/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$is_logged_in = checkAuth();
$user_role = $_SESSION['user_role'] ?? '';

$pdo = initDatabase();
$error = '';
$commande = null;

if (isset($_GET['session_id'])) {
    // Retour depuis Stripe Checkout : on vérifie l'état réel du paiement
    // auprès de Stripe (jamais confiance dans le simple fait d'être revenu
    // sur cette URL).
    try {
        $session = stripeRetrieveSession($_GET['session_id']);
        $commandeId = $session['metadata']['commande_id'] ?? null;

        if (!$commandeId) {
            $error = 'Commande introuvable.';
        } elseif ($session['payment_status'] !== 'paid') {
            $error = 'Le paiement n\'a pas été confirmé par Stripe.';
        } else {
            $commande = confirmerPaiementCommande($pdo, $commandeId);
            $_SESSION['cart'] = [];
            $_SESSION['cart_custom'] = [];
            unset($_SESSION['commande_en_attente']);
        }
    } catch (Exception $e) {
        $error = 'Impossible de vérifier le paiement pour le moment.';
        logError($e->getMessage());
    }
} elseif (isset($_GET['commande_id'])) {
    // Retour du mode simulation : protégé par le jeton de session posé à
    // la création de la commande (pas de vérification externe possible
    // sans vrai prestataire de paiement).
    $commandeId = (int) $_GET['commande_id'];
    if (($_SESSION['commande_en_attente'] ?? null) == $commandeId) {
        $commande = confirmerPaiementCommande($pdo, $commandeId);
        $_SESSION['cart'] = [];
        $_SESSION['cart_custom'] = [];
        unset($_SESSION['commande_en_attente']);
    } else {
        $error = 'Commande introuvable.';
    }
} else {
    $error = 'Aucune commande à confirmer.';
}
?>
<?php include 'header.php'; ?>

<style>
    .page-container {
        max-width: 600px;
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

    .success-check {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: var(--success);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 30px;
        margin: 0 auto 1.2rem;
    }

    .checkout-card h1 {
        color: var(--text);
        margin-bottom: .8rem;
    }

    .checkout-card p {
        color: var(--text-muted);
        margin-bottom: .3rem;
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

    .error-box {
        background: var(--accent);
        color: #fff;
        padding: 1.2rem;
        border-radius: 8px;
    }
</style>

<main class="main-content">
    <div class="page-container">
        <div class="checkout-card">
            <?php if ($error || !$commande): ?>
                <div class="error-box"><?php echo htmlspecialchars($error ?: 'Commande introuvable.'); ?></div>
                <a href="boutique.php" class="btn-secondary">Retour à la boutique</a>
            <?php else: ?>
                <div class="success-check">✓</div>
                <h1>Commande confirmée !</h1>
                <p>Numéro de commande : <strong><?php echo htmlspecialchars($commande['numero']); ?></strong></p>
                <p>Total réglé : <strong><?php echo number_format($commande['total'], 2, ',', ' '); ?> €</strong></p>
                <p>Un e-mail de confirmation vous a été envoyé.</p>
                <?php if ($is_logged_in): ?>
                    <a href="mes-commandes.php" class="btn-secondary">Suivre ma commande</a>
                <?php else: ?>
                    <a href="boutique.php" class="btn-secondary">Retour à la boutique</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>
