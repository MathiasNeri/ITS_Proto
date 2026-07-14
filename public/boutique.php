<?php
require_once '../backend/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$is_logged_in = checkAuth();
$user_role = $_SESSION['user_role'] ?? '';

$pdo = initDatabase();
$produits = $pdo->query('SELECT * FROM produits ORDER BY id')->fetchAll();

$categories = [
    'tel'   => ['label' => 'Téléphones', 'icon' => '📱'],
    'pc'    => ['label' => 'Ordinateurs', 'icon' => '💻'],
    'tab'   => ['label' => 'Tablettes', 'icon' => '📲'],
    'piece' => ['label' => 'Pièces détachées', 'icon' => '🔧'],
    'acc'   => ['label' => 'Accessoires', 'icon' => '🎧'],
];

$tagLabels = [
    'neuf'     => 'Neuf',
    'recond'   => 'Reconditionné',
    'occasion' => 'Occasion',
    'promo'    => 'Promo',
];
?>
<?php include 'header.php'; ?>

<style>
    .page-container {
        max-width: 1200px;
        margin: 2rem auto;
        padding: 0 2rem;
    }

    .page-title {
        font-size: 2.5rem;
        margin-bottom: .5rem;
        color: var(--accent);
        text-align: center;
    }

    .page-subtitle {
        text-align: center;
        color: var(--text-muted);
        margin-bottom: 2.5rem;
    }

    .filter-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        background: var(--surface);
        border: 2px solid var(--surface-alt);
        border-radius: 12px;
        padding: 1rem 1.25rem;
        margin-bottom: 2rem;
    }

    .chip-row {
        display: flex;
        gap: .5rem;
        flex-wrap: wrap;
    }

    .chip {
        border: 1px solid var(--surface-alt);
        background: transparent;
        color: var(--text-muted);
        font-weight: bold;
        font-size: .8rem;
        padding: .5rem .9rem;
        border-radius: 20px;
        cursor: pointer;
    }

    .chip.active {
        background: var(--accent-2);
        border-color: var(--accent-2);
        color: #fff;
    }

    .chip:hover:not(.active) {
        border-color: var(--accent-2);
        color: var(--accent-2);
    }

    .filter-tools {
        display: flex;
        align-items: center;
        gap: .75rem;
        flex-wrap: wrap;
    }

    .filter-tools input,
    .filter-tools select {
        background: var(--surface-alt);
        color: var(--text);
        border: 1px solid var(--surface-alt);
        border-radius: 6px;
        padding: .55rem .8rem;
        font-size: .85rem;
    }

    .shop-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
        gap: 1.5rem;
        margin-bottom: 3rem;
    }

    .shop-card {
        background: var(--surface);
        border: 2px solid var(--surface-alt);
        border-radius: 12px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        transition: transform .2s ease, border-color .2s ease;
    }

    .shop-card:hover {
        transform: translateY(-3px);
        border-color: var(--accent);
    }

    .shop-media {
        position: relative;
        height: 140px;
        background: var(--surface-alt);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 48px;
    }

    .shop-tag {
        position: absolute;
        top: 10px;
        left: 10px;
        font-size: .65rem;
        font-weight: 800;
        letter-spacing: .3px;
        padding: 3px 9px;
        border-radius: 12px;
        color: #fff;
        text-transform: uppercase;
    }

    .shop-tag.neuf { background: var(--success); }
    .shop-tag.recond { background: var(--accent-2); }
    .shop-tag.occasion { background: #d8a316; }
    .shop-tag.promo { background: var(--accent); }

    .shop-body {
        padding: 1rem 1.1rem 1.2rem;
        display: flex;
        flex-direction: column;
        gap: .4rem;
        flex: 1;
    }

    .shop-cat {
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .4px;
        color: var(--accent-2);
        text-transform: uppercase;
    }

    .shop-name {
        font-size: .95rem;
        font-weight: 700;
        color: var(--text);
        line-height: 1.3;
    }

    .shop-stars {
        color: #d8a316;
        font-size: .75rem;
        letter-spacing: 1px;
    }

    .shop-price-row {
        display: flex;
        align-items: baseline;
        gap: .5rem;
        margin-top: auto;
    }

    .shop-price {
        font-size: 1.1rem;
        font-weight: 800;
        color: var(--text);
    }

    .shop-price-old {
        font-size: .78rem;
        color: var(--text-muted);
        text-decoration: line-through;
    }

    .shop-add-btn {
        margin-top: .4rem;
        background: var(--accent);
        color: #fff;
        border: none;
        border-radius: 6px;
        padding: .6rem;
        font-weight: bold;
        font-size: .8rem;
        cursor: pointer;
        width: 100%;
    }

    .shop-add-btn:hover {
        background: var(--accent-hover);
    }

    .shop-empty {
        grid-column: 1/-1;
        text-align: center;
        padding: 3rem 0;
        color: var(--text-muted);
    }

    .shop-media a {
        color: inherit;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
    }

    .shop-name a {
        color: inherit;
        text-decoration: none;
    }

    .shop-name a:hover {
        color: var(--accent-2);
    }

    .shop-stock {
        font-size: .7rem;
        font-weight: 700;
        color: var(--success);
    }

    .shop-stock.low {
        color: #d8a316;
    }

    .shop-stock.out {
        color: var(--accent);
    }

    .shop-add-btn:disabled {
        background: var(--surface-alt);
        color: var(--text-muted);
        cursor: not-allowed;
    }

    .flash {
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        text-align: center;
        background: var(--success);
        color: #fff;
        font-weight: bold;
    }
</style>

<main class="main-content">
    <div class="page-container">
        <h1 class="page-title">🛍️ BOUTIQUE</h1>
        <p class="page-subtitle">Informatique et téléphonie — toutes marques, neuf, reconditionné et occasion.</p>

        <?php if (isset($_GET['ajout'])): ?>
            <div class="flash">Produit ajouté au panier !</div>
        <?php endif; ?>

        <div class="filter-bar">
            <div class="chip-row" id="chipRow">
                <button type="button" class="chip active" data-cat="all">🗂️ Tout voir</button>
                <?php foreach ($categories as $key => $c): ?>
                    <button type="button" class="chip" data-cat="<?php echo $key; ?>"><?php echo $c['icon']; ?> <?php echo $c['label']; ?></button>
                <?php endforeach; ?>
            </div>
            <div class="filter-tools">
                <input type="text" id="searchInput" placeholder="Rechercher un produit…">
                <select id="sortSelect">
                    <option value="default">Pertinence</option>
                    <option value="asc">Prix croissant</option>
                    <option value="desc">Prix décroissant</option>
                    <option value="name">Nom (A-Z)</option>
                </select>
            </div>
        </div>

        <div class="shop-grid" id="shopGrid">
            <?php foreach ($produits as $p): ?>
                <div class="shop-card"
                     data-cat="<?php echo htmlspecialchars($p['categorie']); ?>"
                     data-name="<?php echo htmlspecialchars(strtolower($p['nom'])); ?>"
                     data-price="<?php echo (float) $p['prix']; ?>">
                    <div class="shop-media">
                        <span class="shop-tag <?php echo htmlspecialchars($p['tag']); ?>"><?php echo $tagLabels[$p['tag']] ?? $p['tag']; ?></span>
                        <a href="produit.php?id=<?php echo (int) $p['id']; ?>"><?php echo $p['icone']; ?></a>
                    </div>
                    <div class="shop-body">
                        <div class="shop-cat"><?php echo $categories[$p['categorie']]['label'] ?? $p['categorie']; ?></div>
                        <div class="shop-name"><a href="produit.php?id=<?php echo (int) $p['id']; ?>"><?php echo htmlspecialchars($p['nom']); ?></a></div>
                        <div class="shop-stars"><?php echo str_repeat('★', (int) $p['etoiles']) . str_repeat('☆', 5 - (int) $p['etoiles']); ?></div>
                        <div class="shop-price-row">
                            <span class="shop-price"><?php echo number_format($p['prix'], 2, ',', ' '); ?> €</span>
                            <?php if (!empty($p['prix_barre'])): ?>
                                <span class="shop-price-old"><?php echo number_format($p['prix_barre'], 2, ',', ' '); ?> €</span>
                            <?php endif; ?>
                        </div>
                        <?php if ((int) $p['stock'] <= 0): ?>
                            <div class="shop-stock out">Rupture de stock</div>
                        <?php elseif ((int) $p['stock'] <= 3): ?>
                            <div class="shop-stock low">Plus que <?php echo (int) $p['stock']; ?> en stock</div>
                        <?php else: ?>
                            <div class="shop-stock">En stock</div>
                        <?php endif; ?>
                        <form method="POST" action="cart_action.php">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="produit_id" value="<?php echo (int) $p['id']; ?>">
                            <input type="hidden" name="redirect" value="boutique.php?ajout=1">
                            <button type="submit" class="shop-add-btn" <?php echo (int) $p['stock'] <= 0 ? 'disabled' : ''; ?>>
                                <?php echo (int) $p['stock'] <= 0 ? 'Indisponible' : 'Ajouter au panier'; ?>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</main>

<script>
    (function () {
        var chipRow = document.getElementById('chipRow');
        var search = document.getElementById('searchInput');
        var sort = document.getElementById('sortSelect');
        var grid = document.getElementById('shopGrid');
        var cards = Array.prototype.slice.call(grid.querySelectorAll('.shop-card'));
        var activeCat = 'all';

        function applyFilters() {
            var term = search.value.trim().toLowerCase();
            var visible = cards.filter(function (card) {
                var matchCat = activeCat === 'all' || card.getAttribute('data-cat') === activeCat;
                var matchSearch = card.getAttribute('data-name').indexOf(term) !== -1;
                return matchCat && matchSearch;
            });

            var sortVal = sort.value;
            if (sortVal === 'asc') {
                visible.sort(function (a, b) { return parseFloat(a.getAttribute('data-price')) - parseFloat(b.getAttribute('data-price')); });
            } else if (sortVal === 'desc') {
                visible.sort(function (a, b) { return parseFloat(b.getAttribute('data-price')) - parseFloat(a.getAttribute('data-price')); });
            } else if (sortVal === 'name') {
                visible.sort(function (a, b) { return a.getAttribute('data-name').localeCompare(b.getAttribute('data-name')); });
            }

            cards.forEach(function (card) { card.style.display = 'none'; });
            visible.forEach(function (card) {
                card.style.display = '';
                grid.appendChild(card);
            });

            var existingEmpty = grid.querySelector('.shop-empty');
            if (existingEmpty) existingEmpty.remove();
            if (visible.length === 0) {
                var empty = document.createElement('div');
                empty.className = 'shop-empty';
                empty.textContent = 'Aucun produit ne correspond à votre recherche.';
                grid.appendChild(empty);
            }
        }

        chipRow.addEventListener('click', function (e) {
            var btn = e.target.closest('.chip');
            if (!btn) return;
            chipRow.querySelectorAll('.chip').forEach(function (c) { c.classList.remove('active'); });
            btn.classList.add('active');
            activeCat = btn.getAttribute('data-cat');
            applyFilters();
        });

        search.addEventListener('input', applyFilters);
        sort.addEventListener('change', applyFilters);

        // Lien profond depuis d'autres pages : boutique.php?cat=pc
        var urlParams = new URLSearchParams(window.location.search);
        var catParam = urlParams.get('cat');
        if (catParam) {
            var targetChip = chipRow.querySelector('.chip[data-cat="' + catParam + '"]');
            if (targetChip) {
                chipRow.querySelectorAll('.chip').forEach(function (c) { c.classList.remove('active'); });
                targetChip.classList.add('active');
                activeCat = catParam;
                applyFilters();
            }
        }
    })();
</script>

<?php include 'footer.php'; ?>
