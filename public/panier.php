<?php
require_once __DIR__ . '/../backend/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$is_logged_in = checkAuth();
$user_role = $_SESSION['user_role'] ?? '';

$pdo = initDatabase();
$cart = (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) ? $_SESSION['cart'] : [];
$cartCustom = (!empty($_SESSION['cart_custom']) && is_array($_SESSION['cart_custom'])) ? $_SESSION['cart_custom'] : [];

$lignes = [];
$total = 0;

if (!empty($cart)) {
    $ids = array_map('intval', array_keys($cart));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM produits WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $produitsById = [];
    foreach ($produits as $p) {
        $produitsById[$p['id']] = $p;
    }

    foreach ($cart as $id => $qty) {
        if (!isset($produitsById[$id])) {
            continue;
        }
        $p = $produitsById[$id];
        $sousTotal = $p['prix'] * $qty;
        $total += $sousTotal;
        $lignes[] = [
            'id' => $id,
            'custom' => false,
            'produit' => $p,
            'qty' => $qty,
            'sous_total' => $sousTotal,
        ];
    }
}

// Configurations PC sur mesure ajoutées depuis configurateur.php
foreach ($cartCustom as $customId => $entry) {
    $sousTotal = (float) $entry['prix'];
    $total += $sousTotal;
    $lignes[] = [
        'id' => $customId,
        'custom' => true,
        'produit' => [
            'nom' => $entry['nom'],
            'prix' => $sousTotal,
            'icone' => $entry['icone'] ?? '🖥️',
            'stock' => 999,
        ],
        'details' => $entry['details'] ?? '',
        'qty' => 1,
        'sous_total' => $sousTotal,
    ];
}

$page_noindex = true;
?>
<?php include 'header.php'; ?>

<style>
    .page-container {
        max-width: 900px;
        margin: 2rem auto;
        padding: 0 2rem;
    }

    .page-title {
        font-size: 2.5rem;
        margin-bottom: 2rem;
        color: var(--accent);
        text-align: center;
    }

    .cart-card {
        background: var(--surface);
        border: 1px solid var(--divider);
        border-radius: var(--radius-md);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: var(--shadow-sm);
    }

    .cart-row {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem 0;
        border-bottom: 1px solid var(--divider);
        transition: background-color var(--ease);
    }

    .cart-row:last-child {
        border-bottom: none;
    }

    .cart-thumb {
        width: 56px;
        height: 56px;
        border-radius: var(--radius-sm);
        background: linear-gradient(135deg, var(--surface-alt) 0%, var(--surface-deep) 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 26px;
        flex-shrink: 0;
    }

    .cart-info {
        flex: 1;
        min-width: 0;
    }

    .cart-info .cn {
        font-weight: bold;
        color: var(--text);
    }

    .cart-info .cp {
        color: var(--text-muted);
        font-size: .85rem;
    }

    .qty-form {
        display: flex;
        align-items: center;
        gap: .4rem;
    }

    .qty-form input[type="number"] {
        width: 56px;
        padding: .4rem;
        text-align: center;
        border: 1px solid var(--surface-alt);
        border-radius: 6px;
        background: var(--surface-alt);
        color: var(--text);
    }

    .qty-form button {
        background: var(--accent-2);
        color: #fff;
        border: none;
        border-radius: var(--radius-sm);
        padding: .5rem .8rem;
        font-size: .75rem;
        font-weight: bold;
        cursor: pointer;
        transition: background-color var(--ease);
    }

    .qty-form button:hover {
        background: var(--accent-2-hover);
    }

    .cart-sous-total {
        min-width: 90px;
        text-align: right;
        font-weight: bold;
        color: var(--text);
    }

    .cart-remove {
        background: none;
        border: none;
        color: var(--accent);
        font-weight: bold;
        font-size: .8rem;
        cursor: pointer;
    }

    .cart-empty {
        text-align: center;
        padding: 3.5rem 1rem;
        color: var(--text-muted);
    }

    .cart-empty-icon {
        width: 84px;
        height: 84px;
        margin: 0 auto 1.2rem;
        border-radius: 50%;
        background: var(--surface-alt);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 38px;
    }

    .cart-empty p {
        font-size: 1rem;
        line-height: 1.6;
    }

    .cart-empty a {
        color: var(--accent-2);
        font-weight: bold;
        text-decoration: none;
    }

    .cart-empty a:hover {
        text-decoration: underline;
    }

    .cart-config-details {
        margin-top: .4rem;
    }

    .cart-config-details summary {
        cursor: pointer;
        color: var(--accent-2);
        font-size: .78rem;
    }

    .cart-config-details div {
        margin-top: .4rem;
        color: var(--text-muted);
        font-size: .76rem;
        line-height: 1.5;
    }

    .cart-summary {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 1.3rem;
        font-weight: bold;
        color: var(--text);
        margin-bottom: 1.5rem;
    }

    .checkout-btn {
        display: block;
        width: 100%;
        text-align: center;
        background: var(--accent);
        color: #fff;
        border: none;
        border-radius: var(--radius-sm);
        padding: 1rem;
        font-size: 1.05rem;
        font-weight: bold;
        text-decoration: none;
        cursor: pointer;
        transition: background-color var(--ease), transform var(--ease), box-shadow var(--ease);
    }

    .checkout-btn:hover {
        background: var(--accent-hover);
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    .checkout-btn.disabled {
        background: var(--surface-alt);
        color: var(--text-muted);
        pointer-events: none;
    }

    .continue-link {
        display: block;
        text-align: center;
        margin-top: 1rem;
        color: var(--accent-2);
        text-decoration: none;
        font-weight: bold;
    }

    /* En dessous de 600px, la ligne panier (miniature + infos + quantité +
       sous-total + suppression) ne tient plus sur une seule ligne : on la
       fait passer sur deux lignes plutôt que de laisser tout déborder. */
    @media (max-width: 600px) {
        .page-container {
            padding: 0 1.2rem;
        }

        .cart-card {
            padding: 1rem;
        }

        .cart-row {
            flex-wrap: wrap;
            gap: .6rem;
        }

        .cart-thumb {
            width: 44px;
            height: 44px;
            font-size: 20px;
        }

        .cart-info {
            flex: 1 1 calc(100% - 44px - .6rem);
        }

        .qty-form {
            order: 3;
        }

        .cart-sous-total {
            order: 4;
            margin-left: auto;
            min-width: auto;
        }

        .cart-row > form:last-of-type {
            order: 5;
        }
    }
</style>

<main class="main-content">
    <div class="page-container">
        <h1 class="page-title">🛒 MON PANIER</h1>

        <div class="cart-card">
            <?php if (empty($lignes)): ?>
                <div class="cart-empty">
                    <div class="cart-empty-icon">🛒</div>
                    <p>Votre panier est vide.<br>Direction la <a href="boutique.php">boutique</a> !</p>
                </div>
            <?php else: ?>
                <?php foreach ($lignes as $ligne): ?>
                    <div class="cart-row">
                        <div class="cart-thumb"><?php echo $ligne['produit']['icone']; ?></div>
                        <div class="cart-info">
                            <div class="cn"><?php echo htmlspecialchars($ligne['produit']['nom']); ?></div>
                            <?php if (!empty($ligne['custom'])): ?>
                                <div class="cp"><?php echo number_format($ligne['produit']['prix'], 2, ',', ' '); ?> € — configuration sur mesure</div>
                                <?php if (!empty($ligne['details'])): ?>
                                    <details class="cart-config-details">
                                        <summary>Voir le détail des composants</summary>
                                        <div><?php echo nl2br(htmlspecialchars($ligne['details'])); ?></div>
                                    </details>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="cp"><?php echo number_format($ligne['produit']['prix'], 2, ',', ' '); ?> € / unité</div>
                                <?php if ($ligne['qty'] > (int) $ligne['produit']['stock']): ?>
                                    <div class="cp" style="color: var(--accent);">Stock insuffisant, quantité à ajuster</div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <?php if (empty($ligne['custom'])): ?>
                            <form method="POST" action="cart_action.php" class="qty-form">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="produit_id" value="<?php echo (int) $ligne['id']; ?>">
                                <input type="hidden" name="redirect" value="panier.php">
                                <input type="number" name="qty" min="1" max="<?php echo (int) $ligne['produit']['stock']; ?>" value="<?php echo (int) $ligne['qty']; ?>">
                                <button type="submit">Maj</button>
                            </form>
                        <?php endif; ?>
                        <div class="cart-sous-total"><?php echo number_format($ligne['sous_total'], 2, ',', ' '); ?> €</div>
                        <form method="POST" action="cart_action.php">
                            <?php echo csrfField(); ?>
                            <?php if (!empty($ligne['custom'])): ?>
                                <input type="hidden" name="action" value="remove_custom">
                                <input type="hidden" name="custom_id" value="<?php echo htmlspecialchars($ligne['id']); ?>">
                            <?php else: ?>
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="produit_id" value="<?php echo (int) $ligne['id']; ?>">
                            <?php endif; ?>
                            <input type="hidden" name="redirect" value="panier.php">
                            <button type="submit" class="cart-remove">Suppr.</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if (!empty($lignes)): ?>
            <div class="cart-summary">
                <span>Total</span>
                <span><?php echo number_format($total, 2, ',', ' '); ?> €</span>
            </div>
            <a href="<?php echo $is_logged_in ? 'commande.php' : 'connexion.php?redirect=commande.php'; ?>" class="checkout-btn">Passer commande</a>
            <?php if (!$is_logged_in): ?>
                <p style="text-align:center; color: var(--text-muted); font-size:.85rem; margin-top:.6rem;">Connectez-vous pour finaliser votre commande.</p>
            <?php endif; ?>
        <?php else: ?>
            <a href="boutique.php" class="checkout-btn">Retour à la boutique</a>
        <?php endif; ?>
    </div>
</main>

<?php include 'footer.php'; ?>
