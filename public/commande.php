<?php
require_once __DIR__ . '/../backend/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$is_logged_in = checkAuth();
$user_role = $_SESSION['user_role'] ?? '';

if (!$is_logged_in) {
    redirect('connexion.php?redirect=commande.php');
}

$pdo = initDatabase();
$cart = (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) ? $_SESSION['cart'] : [];
$cartCustom = (!empty($_SESSION['cart_custom']) && is_array($_SESSION['cart_custom'])) ? $_SESSION['cart_custom'] : [];

$error = '';
$promoMessage = '';

function chargerLignesPanier(PDO $pdo, array $cart, array $cartCustom) {
    $lignes = [];

    if (!empty($cart)) {
        $ids = array_map('intval', array_keys($cart));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT * FROM produits WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $produitsById = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $produitsById[$p['id']] = $p;
        }

        foreach ($cart as $id => $qty) {
            if (!isset($produitsById[$id])) {
                continue;
            }
            $p = $produitsById[$id];
            $lignes[] = ['produit' => $p, 'qty' => (int) $qty, 'sous_total' => $p['prix'] * $qty];
        }
    }

    // Configurations PC sur mesure ajoutées depuis configurateur.php : pas
    // de produit_id (produit_id NULL est déjà supporté par commande_lignes).
    foreach ($cartCustom as $entry) {
        $prix = (float) $entry['prix'];
        $lignes[] = [
            'produit' => ['id' => null, 'nom' => $entry['nom'], 'prix' => $prix, 'stock' => 999],
            'qty' => 1,
            'sous_total' => $prix,
        ];
    }

    return $lignes;
}

$lignes = chargerLignesPanier($pdo, $cart, $cartCustom);
$sousTotal = array_sum(array_column($lignes, 'sous_total'));
$modeLivraison = $_SESSION['mode_livraison'] ?? 'boutique';

// --- Application / retrait d'un code promo (n'engage rien, juste la session) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply_promo' && csrfVerify()) {
    $resultat = calculerRemise($pdo, $_POST['code_promo'] ?? '', $sousTotal);
    if ($resultat['erreur']) {
        $promoMessage = $resultat['erreur'];
        unset($_SESSION['promo_code']);
    } else {
        $_SESSION['promo_code'] = $resultat['code'];
        $promoMessage = 'Code "' . $resultat['code'] . '" appliqué !';
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove_promo' && csrfVerify()) {
    unset($_SESSION['promo_code']);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_livraison' && csrfVerify()) {
    $modeLivraison = ($_POST['mode_livraison'] ?? 'boutique') === 'colissimo' ? 'colissimo' : 'boutique';
    $_SESSION['mode_livraison'] = $modeLivraison;
}

$remiseInfo = calculerRemise($pdo, $_SESSION['promo_code'] ?? '', $sousTotal);
$remise = $remiseInfo['remise'];
$fraisLivraison = calculerFraisLivraison($modeLivraison, $sousTotal);
$total = max(0, $sousTotal - $remise) + $fraisLivraison;

// --- Validation finale et création de la commande + redirection paiement ---
$boutiques = ['Pierrefeu' => 'Pierrefeu'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'commander') {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $adresseLigne1 = trim($_POST['adresse_ligne1'] ?? '');
    $adresseLigne2 = trim($_POST['adresse_ligne2'] ?? '');
    $codePostal = trim($_POST['code_postal'] ?? '');
    $ville = trim($_POST['ville'] ?? '');
    $boutique = trim($_POST['boutique'] ?? '');

    if ($modeLivraison === 'colissimo') {
        $adresseParts = [$adresseLigne1];
        if ($adresseLigne2 !== '') {
            $adresseParts[] = $adresseLigne2;
        }
        $adresseParts[] = trim($codePostal . ' ' . $ville);
        $adresse = implode("\n", $adresseParts);
    } else {
        $adresse = 'Retrait en boutique — ' . $boutique;
    }

    if (!csrfVerify()) {
        $error = 'Session expirée, merci de réessayer.';
    } elseif (empty($lignes)) {
        $error = 'Votre panier est vide.';
    } elseif (empty($nom) || empty($email) || empty($telephone)) {
        $error = 'Tous les champs sont obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email invalide.';
    } elseif ($modeLivraison === 'colissimo' && (empty($adresseLigne1) || !preg_match('/^\d{5}$/', $codePostal) || empty($ville))) {
        $error = 'Merci de renseigner une adresse de livraison complète (adresse, code postal à 5 chiffres, ville).';
    } elseif ($modeLivraison === 'boutique' && !array_key_exists($boutique, $boutiques)) {
        $error = 'Merci de sélectionner une boutique de retrait.';
    } else {
        $rupture = null;
        foreach ($lignes as $ligne) {
            if ($ligne['qty'] > (int) $ligne['produit']['stock']) {
                $rupture = $ligne['produit']['nom'];
                break;
            }
        }

        if ($rupture !== null) {
            $error = 'Stock insuffisant pour "' . $rupture . '". Merci d\'ajuster votre panier.';
        } else {
            try {
                $commande = creerCommandeEnAttente(
                    $pdo, $lignes, $nom, $email, $telephone, $adresse,
                    $modeLivraison, $fraisLivraison,
                    $remiseInfo['code'], $remise, $total,
                    $_SESSION['user_id'] ?? null
                );

                $_SESSION['commande_en_attente'] = $commande['id'];
                $baseUrl = rtrim($config['base_url'], '/');

                if (isStripeConfigured()) {
                    $stripeLignes = array_map(function ($l) {
                        return ['nom' => $l['produit']['nom'], 'prix' => $l['produit']['prix'], 'quantite' => $l['qty']];
                    }, $lignes);

                    // La remise est appliquée sous forme de ligne négative
                    // n'existe pas nativement chez Stripe Checkout simple ;
                    // on l'intègre en ajustant la 1re ligne au prorata n'est
                    // pas fiable non plus : on l'applique donc directement
                    // en réduisant le prix envoyé à Stripe côté "livraison"
                    // si besoin, sinon on l'ajoute comme ligne de réduction.
                    if ($remise > 0) {
                        $stripeLignes[] = ['nom' => 'Remise (' . $remiseInfo['code'] . ')', 'prix' => -$remise, 'quantite' => 1];
                    }

                    $session = stripeCreateCheckoutSession(
                        $stripeLignes,
                        $fraisLivraison,
                        $baseUrl . '/confirmation.php?session_id={CHECKOUT_SESSION_ID}',
                        $baseUrl . '/paiement-annule.php',
                        ['commande_id' => $commande['id'], 'numero' => $commande['numero']]
                    );

                    $pdo->prepare('UPDATE commandes SET stripe_session_id = ? WHERE id = ?')->execute([$session['id'], $commande['id']]);

                    unset($_SESSION['promo_code'], $_SESSION['mode_livraison']);
                    redirect($session['url']);
                } else {
                    redirect('paiement-simulation.php');
                }
            } catch (Exception $e) {
                $error = 'Erreur lors de la préparation du paiement. Merci de réessayer.';
                logError($e->getMessage());
            }
        }
    }
}

$page_noindex = true;
?>
<?php include 'header.php'; ?>

<style>
    .page-container {
        max-width: 760px;
        margin: 2rem auto;
        padding: 0 2rem;
    }

    .page-title {
        font-size: 2.5rem;
        margin-bottom: 2rem;
        color: var(--accent);
        text-align: center;
    }

    .checkout-card {
        background: var(--surface);
        border: 2px solid var(--surface-alt);
        border-radius: 12px;
        padding: 2rem;
    }

    .form-group {
        margin-bottom: 1.2rem;
    }

    .form-group label {
        display: block;
        margin-bottom: .5rem;
        color: var(--text-muted);
        font-weight: bold;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: .8rem;
        border: 2px solid var(--surface-alt);
        border-radius: var(--radius-sm);
        background: var(--surface-alt);
        color: var(--text);
        font-size: 1rem;
        transition: border-color var(--ease);
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: var(--accent);
    }

    .form-group .optional {
        font-weight: normal;
        color: var(--text-muted);
        font-size: .8rem;
    }

    .form-row-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }

    .address-autocomplete {
        position: relative;
    }

    .address-suggestions {
        display: none;
        position: absolute;
        top: calc(100% + 4px);
        left: 0;
        right: 0;
        z-index: 20;
        list-style: none;
        margin: 0;
        padding: .3rem;
        background: var(--surface);
        border: 1px solid var(--divider);
        border-radius: var(--radius-sm);
        box-shadow: var(--shadow-md);
        max-height: 240px;
        overflow-y: auto;
    }

    .address-suggestions.show {
        display: block;
    }

    .address-suggestions li {
        padding: .6rem .7rem;
        border-radius: 6px;
        color: var(--text);
        font-size: .88rem;
        cursor: pointer;
    }

    .address-suggestions li:hover {
        background: var(--surface-alt);
        color: var(--accent-2);
    }

    @media (max-width: 560px) {
        .form-row-2 {
            grid-template-columns: 1fr;
        }
    }

    .shipping-options {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .shipping-option {
        border: 2px solid var(--surface-alt);
        border-radius: 8px;
        padding: 1rem;
        cursor: pointer;
        background: var(--surface-alt);
        transition: border-color .15s ease;
    }

    .shipping-option.active {
        border-color: var(--accent-2);
    }

    .shipping-option strong {
        display: block;
        color: var(--text);
        margin-bottom: .3rem;
        font-size: .9rem;
    }

    .shipping-option span {
        color: var(--text-muted);
        font-size: .8rem;
    }

    .shipping-option button {
        background: none;
        border: none;
        width: 100%;
        text-align: left;
        cursor: pointer;
        padding: 0;
    }

    .promo-row {
        display: flex;
        gap: .6rem;
        margin-bottom: 1.5rem;
    }

    .promo-row input {
        flex: 1;
        padding: .7rem .9rem;
        border: 2px solid var(--surface-alt);
        border-radius: 6px;
        background: var(--surface-alt);
        color: var(--text);
    }

    .promo-row button {
        background: var(--accent-2);
        color: #fff;
        border: none;
        border-radius: 6px;
        padding: .7rem 1.2rem;
        font-weight: bold;
        cursor: pointer;
        white-space: nowrap;
    }

    .promo-row button:hover {
        background: var(--accent-2-hover);
    }

    .promo-message {
        font-size: .82rem;
        margin: -1rem 0 1.3rem;
        color: var(--accent-2);
    }

    .promo-applied {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: var(--surface-alt);
        border-radius: 6px;
        padding: .7rem .9rem;
        margin-bottom: 1.5rem;
        font-size: .85rem;
    }

    .promo-applied button {
        background: none;
        border: none;
        color: var(--accent);
        font-weight: bold;
        cursor: pointer;
    }

    .order-summary {
        background: var(--surface-alt);
        border-radius: 8px;
        padding: 1rem 1.2rem;
        margin-bottom: 1.5rem;
    }

    .order-summary .row {
        display: flex;
        justify-content: space-between;
        padding: .3rem 0;
        color: var(--text-muted);
        font-size: .9rem;
    }

    .order-summary .row.remise {
        color: var(--success);
    }

    .order-summary .row.total {
        color: var(--text);
        font-weight: bold;
        font-size: 1.1rem;
        border-top: 1px solid var(--divider);
        margin-top: .5rem;
        padding-top: .7rem;
    }

    .payment-note {
        font-size: .78rem;
        color: var(--text-muted);
        text-align: center;
        margin-top: .8rem;
    }

    .btn-submit {
        background: var(--accent);
        color: #fff;
        padding: 1rem 2rem;
        border: none;
        border-radius: 6px;
        font-size: 1.05rem;
        font-weight: bold;
        cursor: pointer;
        width: 100%;
    }

    .btn-submit:hover {
        background: var(--accent-hover);
    }

    .error {
        background: var(--accent);
        color: #fff;
        padding: 1rem;
        border-radius: 6px;
        margin-bottom: 1rem;
        text-align: center;
    }

    .success-box {
        text-align: center;
        padding: 1rem 0;
    }

    .btn-secondary {
        display: inline-block;
        margin-top: 1.2rem;
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

    @media (max-width: 560px) {
        .shipping-options {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 480px) {
        .page-container {
            padding: 0 1.2rem;
        }

        .order-summary .row {
            flex-wrap: wrap;
            gap: .2rem;
        }

        .promo-row {
            flex-wrap: wrap;
        }

        .promo-row input {
            flex: 1 1 100%;
        }
    }
</style>

<main class="main-content">
    <div class="page-container">
        <h1 class="page-title">💳 COMMANDE</h1>

        <div class="checkout-card">
            <?php if (empty($lignes)): ?>
                <div class="success-box">
                    <p>Votre panier est vide.</p>
                    <a href="boutique.php" class="btn-secondary">Découvrir la boutique</a>
                </div>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" id="livraisonForm">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="set_livraison">
                    <div class="shipping-options">
                        <div class="shipping-option <?php echo $modeLivraison === 'boutique' ? 'active' : ''; ?>">
                            <button type="submit" name="mode_livraison" value="boutique">
                                <strong>🏬 Retrait en boutique</strong>
                                <span>Gratuit — boutique de Pierrefeu</span>
                            </button>
                        </div>
                        <div class="shipping-option <?php echo $modeLivraison === 'colissimo' ? 'active' : ''; ?>">
                            <button type="submit" name="mode_livraison" value="colissimo">
                                <strong>📦 Livraison Colissimo</strong>
                                <span><?php echo $sousTotal >= (float) $config['livraison_gratuite_des'] ? 'Gratuite (commande ≥ ' . (int) $config['livraison_gratuite_des'] . ' €)' : number_format($config['frais_livraison_colissimo'], 2, ',', ' ') . ' €'; ?></span>
                            </button>
                        </div>
                    </div>
                </form>

                <?php if (!empty($_SESSION['promo_code'])): ?>
                    <div class="promo-applied">
                        <span>Code promo <strong><?php echo htmlspecialchars($_SESSION['promo_code']); ?></strong> appliqué</span>
                        <form method="POST" style="margin:0;">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="remove_promo">
                            <button type="submit">Retirer</button>
                        </form>
                    </div>
                <?php else: ?>
                    <form method="POST" class="promo-row">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="apply_promo">
                        <input type="text" name="code_promo" placeholder="Code promo">
                        <button type="submit">Appliquer</button>
                    </form>
                    <?php if ($promoMessage): ?>
                        <p class="promo-message"><?php echo htmlspecialchars($promoMessage); ?></p>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="order-summary">
                    <?php foreach ($lignes as $ligne): ?>
                        <div class="row">
                            <span><?php echo htmlspecialchars($ligne['produit']['nom']); ?> × <?php echo (int) $ligne['qty']; ?></span>
                            <span><?php echo number_format($ligne['sous_total'], 2, ',', ' '); ?> €</span>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($remise > 0): ?>
                        <div class="row remise"><span>Remise</span><span>-<?php echo number_format($remise, 2, ',', ' '); ?> €</span></div>
                    <?php endif; ?>
                    <div class="row"><span>Livraison</span><span><?php echo $fraisLivraison > 0 ? number_format($fraisLivraison, 2, ',', ' ') . ' €' : 'Gratuite'; ?></span></div>
                    <div class="row total"><span>Total</span><span><?php echo number_format($total, 2, ',', ' '); ?> €</span></div>
                </div>

                <form method="POST">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="commander">
                    <div class="form-group">
                        <label for="nom">Nom complet</label>
                        <input type="text" id="nom" name="nom" value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>" required>
                    </div>
                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="email">E-mail</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? $_SESSION['user_email'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="telephone">Téléphone</label>
                            <input type="tel" id="telephone" name="telephone" value="<?php echo htmlspecialchars($_POST['telephone'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <?php if ($modeLivraison === 'colissimo'): ?>
                        <div class="form-group">
                            <label for="adresse_ligne1">Adresse (n° et voie)</label>
                            <div class="address-autocomplete">
                                <input type="text" id="adresse_ligne1" name="adresse_ligne1" autocomplete="off" placeholder="Ex : 12 rue de la République" value="<?php echo htmlspecialchars($_POST['adresse_ligne1'] ?? ''); ?>" required>
                                <ul class="address-suggestions" id="addressSuggestions"></ul>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="adresse_ligne2">Complément d'adresse <span class="optional">(optionnel)</span></label>
                            <input type="text" id="adresse_ligne2" name="adresse_ligne2" placeholder="Bâtiment, appartement, étage..." value="<?php echo htmlspecialchars($_POST['adresse_ligne2'] ?? ''); ?>">
                        </div>
                        <div class="form-row-2">
                            <div class="form-group">
                                <label for="code_postal">Code postal</label>
                                <input type="text" id="code_postal" name="code_postal" inputmode="numeric" pattern="\d{5}" maxlength="5" value="<?php echo htmlspecialchars($_POST['code_postal'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="ville">Ville</label>
                                <input type="text" id="ville" name="ville" list="villeOptions" autocomplete="off" value="<?php echo htmlspecialchars($_POST['ville'] ?? ''); ?>" required>
                                <datalist id="villeOptions"></datalist>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="form-group">
                            <label for="boutique">Boutique de retrait</label>
                            <select id="boutique" name="boutique" required>
                                <?php foreach ($boutiques as $valeur => $libelle): ?>
                                    <option value="<?php echo htmlspecialchars($valeur); ?>" <?php echo ($_POST['boutique'] ?? 'Pierrefeu') === $valeur ? 'selected' : ''; ?>><?php echo htmlspecialchars($libelle); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="btn-submit">
                        <?php echo isStripeConfigured() ? 'Payer ' . number_format($total, 2, ',', ' ') . ' € par carte' : 'Payer ' . number_format($total, 2, ',', ' ') . ' € (mode simulation)'; ?>
                    </button>
                    <?php if (!isStripeConfigured()): ?>
                        <p class="payment-note">Stripe n'est pas configuré : ce paiement est simulé, aucun encaissement réel n'a lieu.</p>
                    <?php else: ?>
                        <p class="payment-note">Paiement sécurisé par Stripe. Vous serez redirigé vers la page de paiement.</p>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php if ($modeLivraison === 'colissimo'): ?>
<script>
    (function () {
        // Autocomplétion d'adresse via l'API officielle "Base Adresse Nationale"
        // (data.gouv.fr, gratuite et sans clé) : suggère des adresses réelles et
        // pré-remplit code postal / ville en un clic.
        var adresseInput = document.getElementById('adresse_ligne1');
        var suggestionsList = document.getElementById('addressSuggestions');
        var codePostalInput = document.getElementById('code_postal');
        var villeInput = document.getElementById('ville');
        var villeDatalist = document.getElementById('villeOptions');
        var debounceTimer = null;

        function chargerVilles(codePostal) {
            if (!villeDatalist || !/^\d{5}$/.test(codePostal)) return;
            fetch('https://geo.api.gouv.fr/communes?codePostal=' + codePostal + '&fields=nom&format=json')
                .then(function (r) { return r.json(); })
                .then(function (communes) {
                    villeDatalist.innerHTML = '';
                    (communes || []).forEach(function (c) {
                        var opt = document.createElement('option');
                        opt.value = c.nom;
                        villeDatalist.appendChild(opt);
                    });
                })
                .catch(function () { /* API indisponible : la ville reste saisissable librement */ });
        }

        if (adresseInput && suggestionsList) {
            adresseInput.addEventListener('input', function () {
                var query = adresseInput.value.trim();
                clearTimeout(debounceTimer);
                if (query.length < 4) {
                    suggestionsList.innerHTML = '';
                    suggestionsList.classList.remove('show');
                    return;
                }
                debounceTimer = setTimeout(function () {
                    fetch('https://api-adresse.data.gouv.fr/search/?q=' + encodeURIComponent(query) + '&limit=5&autocomplete=1')
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            suggestionsList.innerHTML = '';
                            (data.features || []).forEach(function (f) {
                                var li = document.createElement('li');
                                li.textContent = f.properties.label;
                                li.addEventListener('click', function () {
                                    adresseInput.value = f.properties.name || f.properties.label;
                                    if (codePostalInput) codePostalInput.value = f.properties.postcode || '';
                                    if (villeInput) villeInput.value = f.properties.city || '';
                                    suggestionsList.innerHTML = '';
                                    suggestionsList.classList.remove('show');
                                    chargerVilles(f.properties.postcode || '');
                                });
                                suggestionsList.appendChild(li);
                            });
                            suggestionsList.classList.toggle('show', suggestionsList.children.length > 0);
                        })
                        .catch(function () { /* API indisponible : la saisie manuelle reste possible */ });
                }, 300);
            });

            document.addEventListener('click', function (e) {
                if (e.target !== adresseInput && !suggestionsList.contains(e.target)) {
                    suggestionsList.classList.remove('show');
                }
            });
        }

        if (codePostalInput) {
            codePostalInput.addEventListener('input', function () {
                chargerVilles(codePostalInput.value.trim());
            });
            // Réaffichage du formulaire après une erreur de validation : on
            // recharge les villes correspondant au code postal déjà saisi.
            if (codePostalInput.value.trim()) {
                chargerVilles(codePostalInput.value.trim());
            }
        }
    })();
</script>
<?php endif; ?>

<?php include 'footer.php'; ?>
