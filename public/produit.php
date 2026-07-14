<?php
require_once __DIR__ . '/../backend/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$is_logged_in = checkAuth();
$user_role = $_SESSION['user_role'] ?? '';

$pdo = initDatabase();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$stmt = $pdo->prepare('SELECT * FROM produits WHERE id = ?');
$stmt->execute([$id]);
$produit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$produit) {
    http_response_code(404);
}

$categories = [
    'tel'   => 'Téléphones',
    'pc'    => 'Ordinateurs',
    'tab'   => 'Tablettes',
    'piece' => 'Pièces détachées',
    'acc'   => 'Accessoires',
];

$tagLabels = [
    'neuf'     => 'Neuf',
    'recond'   => 'Reconditionné',
    'occasion' => 'Occasion',
    'promo'    => 'Promo',
];

$avisError = '';
$avisSuccess = '';

if ($produit && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_avis') {
    if (!$is_logged_in) {
        $avisError = 'Merci de vous connecter pour laisser un avis.';
    } elseif (!csrfVerify()) {
        $avisError = 'Session expirée, merci de réessayer.';
    } else {
        $note = max(1, min(5, (int) ($_POST['note'] ?? 0)));
        $commentaire = trim($_POST['commentaire'] ?? '');
        if ($commentaire === '') {
            $avisError = 'Merci de rédiger un commentaire.';
        } else {
            $nomAuteur = trim(($_SESSION['user_email'] ?? 'Client'));
            $stmt = $pdo->prepare('SELECT nom, prenom FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($u) {
                $nomAuteur = $u['prenom'] . ' ' . substr($u['nom'], 0, 1) . '.';
            }

            $pdo->prepare('INSERT INTO avis (produit_id, user_id, nom, note, commentaire, approuve) VALUES (?, ?, ?, ?, ?, 0)')
                ->execute([$produit['id'], $_SESSION['user_id'], $nomAuteur, $note, $commentaire]);
            $avisSuccess = 'Merci ! Votre avis sera visible après validation par notre équipe.';
        }
    }
}

$avisListe = [];
$avisMoyenne = $produit ? (int) $produit['etoiles'] : 0;
if ($produit) {
    $stmt = $pdo->prepare('SELECT * FROM avis WHERE produit_id = ? AND approuve = 1 ORDER BY created_at DESC');
    $stmt->execute([$produit['id']]);
    $avisListe = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($avisListe)) {
        $avisMoyenne = round(array_sum(array_column($avisListe, 'note')) / count($avisListe));
    }
}
?>
<?php include 'header.php'; ?>

<style>
    .page-container {
        max-width: 1000px;
        margin: 2rem auto;
        padding: 0 2rem;
    }

    .breadcrumb {
        font-size: .8rem;
        color: var(--text-muted);
        margin-bottom: 1.5rem;
    }

    .breadcrumb a {
        color: var(--accent-2);
        text-decoration: none;
    }

    .product-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2.5rem;
        background: var(--surface);
        border: 2px solid var(--surface-alt);
        border-radius: 12px;
        padding: 2rem;
    }

    .product-media {
        position: relative;
        height: 320px;
        border-radius: 10px;
        background: var(--surface-alt);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 110px;
    }

    .product-tag {
        position: absolute;
        top: 14px;
        left: 14px;
        font-size: .7rem;
        font-weight: 800;
        letter-spacing: .3px;
        padding: 4px 11px;
        border-radius: 12px;
        color: #fff;
        text-transform: uppercase;
    }

    .product-tag.neuf { background: var(--success); }
    .product-tag.recond { background: var(--accent-2); }
    .product-tag.occasion { background: #d8a316; }
    .product-tag.promo { background: var(--accent); }

    .product-cat {
        font-size: .75rem;
        font-weight: 700;
        letter-spacing: .4px;
        color: var(--accent-2);
        text-transform: uppercase;
        margin-bottom: .4rem;
    }

    .product-title {
        font-size: 1.7rem;
        font-weight: 800;
        color: var(--text);
        margin-bottom: .5rem;
    }

    .product-stars {
        color: #d8a316;
        font-size: 1rem;
        letter-spacing: 2px;
        margin-bottom: 1rem;
    }

    .product-desc {
        color: var(--text-muted);
        line-height: 1.7;
        margin-bottom: 1.2rem;
    }

    .product-price-row {
        display: flex;
        align-items: baseline;
        gap: .8rem;
        margin-bottom: .8rem;
    }

    .product-price {
        font-size: 2rem;
        font-weight: 800;
        color: var(--text);
    }

    .product-price-old {
        font-size: 1.1rem;
        color: var(--text-muted);
        text-decoration: line-through;
    }

    .product-stock {
        font-size: .85rem;
        font-weight: 700;
        color: var(--success);
        margin-bottom: 1.4rem;
    }

    .product-stock.low { color: #d8a316; }
    .product-stock.out { color: var(--accent); }

    .product-actions {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .qty-stepper {
        display: flex;
        align-items: center;
        border: 1px solid var(--surface-alt);
        border-radius: 6px;
        overflow: hidden;
    }

    .qty-stepper input {
        width: 56px;
        text-align: center;
        border: none;
        background: var(--surface-alt);
        color: var(--text);
        padding: .7rem 0;
        font-size: 1rem;
    }

    .product-add-btn {
        flex: 1;
        background: var(--accent);
        color: #fff;
        border: none;
        border-radius: 6px;
        padding: .9rem;
        font-weight: bold;
        font-size: .95rem;
        cursor: pointer;
    }

    .product-add-btn:hover {
        background: var(--accent-hover);
    }

    .product-add-btn:disabled {
        background: var(--surface-alt);
        color: var(--text-muted);
        cursor: not-allowed;
    }

    .not-found {
        text-align: center;
        padding: 4rem 0;
        color: var(--text-muted);
    }

    .avis-section {
        margin-top: 2.5rem;
    }

    .avis-section h2 {
        color: var(--text);
        font-size: 1.3rem;
        margin-bottom: 1.2rem;
    }

    .avis-card {
        background: var(--surface);
        border: 2px solid var(--surface-alt);
        border-radius: 10px;
        padding: 1.2rem 1.4rem;
        margin-bottom: 1rem;
    }

    .avis-card .avis-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: .4rem;
    }

    .avis-card .avis-auteur {
        font-weight: bold;
        color: var(--text);
        font-size: .88rem;
    }

    .avis-card .avis-date {
        color: var(--text-muted);
        font-size: .75rem;
    }

    .avis-card .avis-stars {
        color: #d8a316;
        margin-bottom: .5rem;
    }

    .avis-card p {
        color: var(--text-muted);
        font-size: .88rem;
        line-height: 1.6;
    }

    .avis-empty {
        color: var(--text-muted);
        font-size: .88rem;
        margin-bottom: 1.5rem;
    }

    .avis-form {
        background: var(--surface);
        border: 2px solid var(--surface-alt);
        border-radius: 10px;
        padding: 1.5rem;
    }

    .avis-form label {
        display: block;
        font-weight: bold;
        color: var(--text-muted);
        font-size: .85rem;
        margin-bottom: .4rem;
    }

    .avis-form select,
    .avis-form textarea {
        width: 100%;
        padding: .7rem;
        border: 2px solid var(--surface-alt);
        border-radius: 6px;
        background: var(--surface-alt);
        color: var(--text);
        font-size: .9rem;
        margin-bottom: 1rem;
    }

    .avis-form textarea {
        min-height: 90px;
        resize: vertical;
    }

    .avis-form button {
        background: var(--accent);
        color: #fff;
        border: none;
        border-radius: 6px;
        padding: .8rem 1.6rem;
        font-weight: bold;
        cursor: pointer;
    }

    .avis-form button:hover {
        background: var(--accent-hover);
    }

    .avis-message {
        padding: .9rem;
        border-radius: 6px;
        margin-bottom: 1rem;
        font-size: .85rem;
    }

    .avis-message.error { background: var(--accent); color: #fff; }
    .avis-message.success { background: var(--success); color: #fff; }

    @media (max-width: 768px) {
        .product-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<main class="main-content">
    <div class="page-container">
        <?php if (!$produit): ?>
            <div class="not-found">
                <h2 style="color: var(--text); margin-bottom: 1rem;">Produit introuvable</h2>
                <p>Ce produit n'existe pas ou n'est plus disponible.</p>
                <a href="boutique.php" style="color: var(--accent-2); font-weight: bold;">&larr; Retour à la boutique</a>
            </div>
        <?php else: ?>
            <div class="breadcrumb">
                <a href="boutique.php">Boutique</a> &rsaquo;
                <a href="boutique.php?cat=<?php echo htmlspecialchars($produit['categorie']); ?>"><?php echo $categories[$produit['categorie']] ?? $produit['categorie']; ?></a> &rsaquo;
                <?php echo htmlspecialchars($produit['nom']); ?>
            </div>

            <div class="product-grid">
                <div class="product-media">
                    <span class="product-tag <?php echo htmlspecialchars($produit['tag']); ?>"><?php echo $tagLabels[$produit['tag']] ?? $produit['tag']; ?></span>
                    <?php echo $produit['icone']; ?>
                </div>
                <div>
                    <div class="product-cat"><?php echo $categories[$produit['categorie']] ?? $produit['categorie']; ?></div>
                    <h1 class="product-title"><?php echo htmlspecialchars($produit['nom']); ?></h1>
                    <div class="product-stars">
                        <?php echo str_repeat('★', $avisMoyenne) . str_repeat('☆', 5 - $avisMoyenne); ?>
                        <?php if (!empty($avisListe)): ?>
                            <span style="font-size:.75rem;color:var(--text-muted);">(<?php echo count($avisListe); ?> avis)</span>
                        <?php endif; ?>
                    </div>
                    <p class="product-desc"><?php echo nl2br(htmlspecialchars($produit['description'])); ?></p>

                    <div class="product-price-row">
                        <span class="product-price"><?php echo number_format($produit['prix'], 2, ',', ' '); ?> €</span>
                        <?php if (!empty($produit['prix_barre'])): ?>
                            <span class="product-price-old"><?php echo number_format($produit['prix_barre'], 2, ',', ' '); ?> €</span>
                        <?php endif; ?>
                    </div>

                    <?php if ((int) $produit['stock'] <= 0): ?>
                        <div class="product-stock out">Rupture de stock</div>
                    <?php elseif ((int) $produit['stock'] <= 3): ?>
                        <div class="product-stock low">Plus que <?php echo (int) $produit['stock']; ?> en stock — dépêchez-vous</div>
                    <?php else: ?>
                        <div class="product-stock">En stock (<?php echo (int) $produit['stock']; ?> disponibles)</div>
                    <?php endif; ?>

                    <form method="POST" action="cart_action.php" class="product-actions">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="produit_id" value="<?php echo (int) $produit['id']; ?>">
                        <input type="hidden" name="redirect" value="produit.php?id=<?php echo (int) $produit['id']; ?>&ajout=1">
                        <div class="qty-stepper">
                            <input type="number" name="qty" min="1" max="<?php echo max(1, (int) $produit['stock']); ?>" value="1" <?php echo (int) $produit['stock'] <= 0 ? 'disabled' : ''; ?>>
                        </div>
                        <button type="submit" class="product-add-btn" <?php echo (int) $produit['stock'] <= 0 ? 'disabled' : ''; ?>>
                            <?php echo (int) $produit['stock'] <= 0 ? 'Indisponible' : 'Ajouter au panier'; ?>
                        </button>
                    </form>

                    <?php if (isset($_GET['ajout'])): ?>
                        <p style="color: var(--success); font-weight: bold; margin-top: 1rem;">Produit ajouté au panier !</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="avis-section">
                <h2>Avis clients</h2>

                <?php if (empty($avisListe)): ?>
                    <p class="avis-empty">Aucun avis pour le moment. Soyez le premier à donner votre avis !</p>
                <?php else: ?>
                    <?php foreach ($avisListe as $a): ?>
                        <div class="avis-card">
                            <div class="avis-head">
                                <span class="avis-auteur"><?php echo htmlspecialchars($a['nom']); ?></span>
                                <span class="avis-date"><?php echo date('d/m/Y', strtotime($a['created_at'])); ?></span>
                            </div>
                            <div class="avis-stars"><?php echo str_repeat('★', (int) $a['note']) . str_repeat('☆', 5 - (int) $a['note']); ?></div>
                            <p><?php echo nl2br(htmlspecialchars($a['commentaire'])); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if ($avisError): ?><div class="avis-message error"><?php echo htmlspecialchars($avisError); ?></div><?php endif; ?>
                <?php if ($avisSuccess): ?><div class="avis-message success"><?php echo htmlspecialchars($avisSuccess); ?></div><?php endif; ?>

                <?php if ($is_logged_in): ?>
                    <div class="avis-form">
                        <form method="POST">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="submit_avis">
                            <label for="note">Votre note</label>
                            <select name="note" id="note">
                                <option value="5">★★★★★ Excellent</option>
                                <option value="4">★★★★☆ Très bien</option>
                                <option value="3">★★★☆☆ Correct</option>
                                <option value="2">★★☆☆☆ Décevant</option>
                                <option value="1">★☆☆☆☆ Mauvais</option>
                            </select>
                            <label for="commentaire">Votre commentaire</label>
                            <textarea name="commentaire" id="commentaire" required></textarea>
                            <button type="submit">Publier mon avis</button>
                        </form>
                    </div>
                <?php else: ?>
                    <p class="avis-empty"><a href="connexion.php" style="color: var(--accent-2); font-weight: bold;">Connectez-vous</a> pour laisser un avis sur ce produit.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include 'footer.php'; ?>
