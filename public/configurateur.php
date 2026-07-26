<?php
require_once __DIR__ . '/../backend/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$is_logged_in = checkAuth();
$user_role = $_SESSION['user_role'] ?? '';

$pdo = initDatabase();

$categories = ['boitier', 'cpu', 'carte_mere', 'ram', 'gpu', 'stockage', 'alimentation', 'refroidissement', 'os'];
$labels = [
    'boitier' => 'Boîtier',
    'cpu' => 'Processeur (CPU)',
    'carte_mere' => 'Carte mère',
    'ram' => 'Mémoire vive (RAM)',
    'gpu' => 'Carte graphique (GPU)',
    'stockage' => 'Stockage (SSD/HDD)',
    'alimentation' => 'Alimentation',
    'refroidissement' => 'Refroidissement',
    'os' => "Système d'exploitation",
];
$icones = [
    'cpu' => '🧠', 'carte_mere' => '🔌', 'ram' => '🧩', 'gpu' => '🎮',
    'stockage' => '💾', 'alimentation' => '🔋', 'boitier' => '🖥️',
    'refroidissement' => '❄️', 'os' => '💽',
];
$requis = ['cpu', 'carte_mere', 'ram', 'stockage', 'alimentation', 'boitier'];

$composants = $pdo->query('SELECT * FROM composants_pc ORDER BY type, prix ASC')->fetchAll(PDO::FETCH_ASSOC);
$parType = [];
$parId = [];
foreach ($composants as $c) {
    $parType[$c['type']][] = $c;
    $parId[(int) $c['id']] = $c;
}

function nomComplet($c) {
    return $c['marque'] === '—' ? $c['nom'] : $c['marque'] . ' ' . $c['nom'];
}

// Sélections par défaut (les options gratuites/incluses de gpu, refroidissement, os)
$defaults = [];
foreach (['gpu', 'refroidissement', 'os'] as $type) {
    foreach ($parType[$type] ?? [] as $c) {
        if ((float) $c['prix'] === 0.0) {
            $defaults[$type] = (int) $c['id'];
            break;
        }
    }
}

$success = '';
$error = '';
$direction = $_POST['direction'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($direction, ['devis', 'panier'], true)) {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $code_postal = trim($_POST['code_postal'] ?? '');
    $ville = trim($_POST['ville'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $messageComplement = trim($_POST['message'] ?? '');
    $consentement = isset($_POST['consentement']);

    $selection = [];
    foreach ($categories as $type) {
        $id = (int) ($_POST['comp_' . $type] ?? 0);
        if ($id > 0 && isset($parId[$id]) && $parId[$id]['type'] === $type) {
            $selection[$type] = $parId[$id];
        }
    }

    $peripheriques = [];
    foreach ((array) ($_POST['comp_peripherique'] ?? []) as $pid) {
        $pid = (int) $pid;
        if (isset($parId[$pid]) && $parId[$pid]['type'] === 'peripherique') {
            $peripheriques[] = $parId[$pid];
        }
    }

    $manquants = [];
    foreach ($requis as $type) {
        if (!isset($selection[$type])) {
            $manquants[] = $labels[$type];
        }
    }

    if (!csrfVerify()) {
        $error = 'Session expirée, merci de réessayer.';
    } elseif ($direction === 'devis' && !honeypotPasses()) {
        // Soumission détectée comme un bot : succès silencieux, rien n'est enregistré ni envoyé.
        $success = 'Votre configuration a bien été envoyée ! Nous revenons vers vous avec un devis détaillé et les délais de montage.';
    } elseif (!empty($manquants)) {
        $error = 'Merci de sélectionner : ' . implode(', ', $manquants) . '.';
    } elseif ($direction === 'devis' && (empty($nom) || empty($prenom) || empty($adresse) || empty($code_postal) || empty($ville) || empty($email) || empty($telephone))) {
        $error = 'Tous les champs de contact obligatoires doivent être remplis.';
    } elseif ($direction === 'devis' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email invalide.';
    } elseif ($direction === 'devis' && !$consentement) {
        $error = 'Merci d\'accepter l\'utilisation de vos données pour traiter votre demande.';
    } else {
        $total = 0;
        $totalPower = 0;
        $lignes = [];
        foreach ($categories as $type) {
            if (isset($selection[$type])) {
                $c = $selection[$type];
                $total += (float) $c['prix'];
                $totalPower += (int) ($c['power_draw'] ?? 0);
                $prixTxt = (float) $c['prix'] > 0 ? number_format($c['prix'], 2, ',', ' ') . ' €' : 'Inclus';
                $lignes[] = '- ' . $labels[$type] . ' : ' . nomComplet($c) . ' (' . $prixTxt . ')';
            } else {
                $lignes[] = '- ' . $labels[$type] . ' : —';
            }
        }
        if (!empty($peripheriques)) {
            $lignes[] = '- Périphériques :';
            foreach ($peripheriques as $p) {
                $total += (float) $p['prix'];
                $lignes[] = '  • ' . nomComplet($p) . ' (' . number_format($p['prix'], 2, ',', ' ') . ' €)';
            }
        } else {
            $lignes[] = '- Périphériques : aucun';
        }
        $recap = "Configuration PC sur mesure demandée en ligne :\n" . implode("\n", $lignes) .
            "\n\nTotal (hors main d'oeuvre de montage) : " . number_format($total, 2, ',', ' ') . " €" .
            "\nConsommation électrique estimée : " . $totalPower . " W";
        if ($messageComplement !== '') {
            $recap .= "\n\nInfos complémentaires du client :\n" . $messageComplement;
        }

        if ($direction === 'panier') {
            $sommaireParts = [];
            if (isset($selection['cpu'])) {
                $sommaireParts[] = nomComplet($selection['cpu']);
            }
            if (isset($selection['gpu']) && $selection['gpu']['marque'] !== '—') {
                $sommaireParts[] = nomComplet($selection['gpu']);
            }
            $label = 'PC sur mesure : ' . implode(' + ', $sommaireParts);
            if (strlen($label) > 250) {
                $label = substr($label, 0, 247) . '...';
            }

            if (!isset($_SESSION['cart_custom']) || !is_array($_SESSION['cart_custom'])) {
                $_SESSION['cart_custom'] = [];
            }
            $customId = uniqid('cfg_', true);
            $_SESSION['cart_custom'][$customId] = [
                'nom' => $label,
                'prix' => $total,
                'icone' => '🖥️',
                'details' => $recap,
            ];
            redirect('panier.php');
        } else {
            $stmt = $pdo->prepare('INSERT INTO devis (materiel, nom, prenom, adresse, code_postal, ville, email, telephone, boutique, message) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

            if ($stmt->execute(['Configuration PC sur mesure', $nom, $prenom, $adresse, $code_postal, $ville, $email, $telephone, 'Pierrefeu', $recap])) {
                envoyerEmailsDevis([
                    'materiel' => 'Configuration PC sur mesure',
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'email' => $email,
                    'telephone' => $telephone,
                    'message' => $recap,
                ]);
                $success = 'Votre configuration a bien été envoyée ! Nous revenons vers vous avec un devis détaillé et les délais de montage.';
                $_POST = [];
            } else {
                $error = 'Erreur lors de l\'envoi de la demande.';
            }
        }
    }
}

$isValidationError = ($_SERVER['REQUEST_METHOD'] === 'POST' && $error !== '');
$checkedPeripheriqueIds = $isValidationError ? array_map('intval', (array) ($_POST['comp_peripherique'] ?? [])) : [];
$etapeInitiale = $isValidationError ? 'validation' : 'usage';

$page_title = 'Configurateur PC';
$page_description = "Configurez votre PC sur mesure : gaming ou bureautique, composants garantis compatibles, prix en temps réel. Devis ou achat en ligne.";
?>
<?php include 'header.php'; ?>

<style>
    .page-container {
        max-width: 1300px;
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
        max-width: 700px;
        margin: 0 auto 2rem;
        line-height: 1.6;
    }

    .usage-panel-title {
        font-size: 1.6rem;
        color: var(--text);
        margin-bottom: .6rem;
    }

    .usage-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
        gap: 1.5rem;
        max-width: 920px;
        margin: 2rem auto 0;
        text-align: left;
    }

    .usage-card {
        background: var(--surface);
        border: 2px solid var(--divider);
        border-radius: var(--radius-md);
        padding: 2rem 1.6rem;
        text-align: center;
        cursor: pointer;
        box-shadow: var(--shadow-sm);
        transition: transform var(--ease), box-shadow var(--ease), border-color var(--ease);
    }

    .usage-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-md);
        border-color: var(--accent-2);
    }

    .usage-card.custom {
        border-style: dashed;
    }

    .usage-card-icon {
        width: 68px;
        height: 68px;
        margin: 0 auto 1.2rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        color: #fff;
        background: linear-gradient(135deg, var(--accent) 0%, var(--accent-2) 100%);
    }

    .usage-card-icon.bureautique {
        background: linear-gradient(135deg, var(--accent-2) 0%, var(--success) 100%);
    }

    .usage-card-icon.custom {
        background: var(--surface-deep);
    }

    .usage-card h3 {
        font-size: 1.15rem;
        color: var(--text);
        margin-bottom: .6rem;
    }

    .usage-card p {
        font-size: .82rem;
        color: var(--text-muted);
        line-height: 1.5;
    }

    .message {
        padding: 1rem;
        border-radius: 5px;
        margin-bottom: 1.5rem;
        text-align: center;
    }

    .message.success { background: var(--success); color: #fff; }
    .message.error { background: var(--accent); color: #fff; }

    /* --- Étapes (Votre utilisation / configuration / options / validation) --- */
    .config-steps {
        display: flex;
        border-radius: 999px;
        overflow: hidden;
        box-shadow: var(--shadow-sm);
        margin-bottom: 2rem;
    }

    .config-step {
        flex: 1;
        background: var(--surface-alt);
        color: var(--text-muted);
        border: none;
        padding: .9rem .6rem;
        font-size: .85rem;
        font-weight: bold;
        cursor: pointer;
        text-align: center;
        transition: background-color var(--ease), color var(--ease);
        border-right: 1px solid var(--surface-deep);
    }

    .config-step:last-child {
        border-right: none;
    }

    .config-step:hover {
        background: var(--surface-deep);
        color: var(--text);
    }

    .config-step.active {
        background: #1c1c1c;
        color: #fff;
    }

    .config-step-num {
        opacity: .7;
        margin-right: .3rem;
    }

    .config-step-panel {
        display: none;
    }

    .config-step-panel.active {
        display: block;
    }

    .config-step-nav {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        margin-top: 1.5rem;
    }

    .config-step-nav.single {
        justify-content: flex-end;
    }

    .btn-step {
        background: var(--surface-alt);
        color: var(--text);
        border: none;
        border-radius: var(--radius-sm);
        padding: .75rem 1.5rem;
        font-weight: bold;
        font-size: .9rem;
        cursor: pointer;
        transition: background-color var(--ease), transform var(--ease);
    }

    .btn-step:hover {
        background: var(--surface-deep);
        transform: translateY(-1px);
    }

    .btn-step.primary {
        background: var(--accent);
        color: #fff;
    }

    .btn-step.primary:hover {
        background: var(--accent-hover);
    }

    /* --- Étape "Votre utilisation" --- */
    .usage-panel {
        text-align: center;
        padding: 2rem 0;
    }

    /* --- Étape "Votre configuration" : menu de catégories --- */
    .category-strip-wrap {
        position: relative;
        display: flex;
        align-items: center;
        gap: .5rem;
        margin-bottom: 1.8rem;
    }

    .category-scroll-btn {
        flex-shrink: 0;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: none;
        background: var(--surface-alt);
        color: var(--text);
        font-size: 1.1rem;
        cursor: pointer;
        transition: background-color var(--ease);
    }

    .category-scroll-btn:hover {
        background: var(--surface-deep);
    }

    .category-strip {
        display: flex;
        gap: 1rem;
        overflow-x: auto;
        scroll-behavior: smooth;
        padding: .3rem;
        scrollbar-width: none;
    }

    .category-strip::-webkit-scrollbar {
        display: none;
    }

    .category-card {
        flex: 0 0 150px;
        background: var(--surface-alt);
        border: 2px solid transparent;
        border-radius: var(--radius-md);
        padding: 1rem .8rem;
        text-align: center;
        cursor: pointer;
        transition: border-color var(--ease), transform var(--ease), background-color var(--ease);
    }

    .category-card:hover {
        transform: translateY(-2px);
        border-color: var(--accent-2);
    }

    .category-card.active {
        background: #1c1c1c;
        border-color: #1c1c1c;
    }

    .category-card.locked {
        opacity: .5;
        cursor: not-allowed;
    }

    .category-card.locked:hover {
        transform: none;
        border-color: transparent;
    }

    .category-card.locked .category-choose-btn {
        cursor: not-allowed;
    }

    .category-card.active .category-card-label,
    .category-card.active .category-card-selection {
        color: #fff;
    }

    .category-card-visual {
        width: 56px;
        height: 56px;
        margin: 0 auto .6rem;
        border-radius: var(--radius-sm);
        background: var(--surface-deep);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
    }

    .category-card-label {
        font-weight: bold;
        font-size: .82rem;
        color: var(--text);
        margin-bottom: .5rem;
        min-height: 2.1em;
    }

    .category-card-required {
        color: var(--accent);
        font-size: .68rem;
        font-weight: bold;
        display: block;
        margin-bottom: .4rem;
    }

    .category-card-required.done {
        color: var(--success);
    }

    .category-choose-btn {
        background: var(--surface-deep);
        color: var(--text);
        border: none;
        border-radius: 20px;
        padding: .4rem 1rem;
        font-size: .75rem;
        font-weight: bold;
        cursor: pointer;
        transition: background-color var(--ease);
    }

    .category-card:not(.active) .category-choose-btn:hover {
        background: var(--accent-2);
        color: #fff;
    }

    .category-card.active .category-choose-btn {
        background: #fff;
        color: #1c1c1c;
    }

    .category-card-selection {
        margin-top: .5rem;
        font-size: .68rem;
        color: var(--success);
        font-weight: bold;
        line-height: 1.3;
        min-height: 1.6em;
    }

    .category-drawer-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .55);
        opacity: 0;
        pointer-events: none;
        transition: opacity var(--ease);
        z-index: 1099;
    }

    .category-drawer-backdrop.open {
        opacity: 1;
        pointer-events: auto;
    }

    .category-drawer {
        position: fixed;
        top: 0;
        right: 0;
        height: 100vh;
        width: min(460px, 100vw);
        background: var(--surface);
        box-shadow: -12px 0 30px rgba(0, 0, 0, .35);
        transform: translateX(100%);
        transition: transform var(--ease);
        z-index: 1100;
        display: flex;
        flex-direction: column;
    }

    .category-drawer.open {
        transform: translateX(0);
    }

    .category-drawer-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1.2rem 1.4rem;
        border-bottom: 1px solid var(--divider);
        flex-shrink: 0;
    }

    .category-drawer-back {
        background: var(--surface-alt);
        border: none;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        font-size: 1.2rem;
        color: var(--text);
        cursor: pointer;
        flex-shrink: 0;
        transition: background-color var(--ease);
    }

    .category-drawer-back:hover {
        background: var(--surface-deep);
    }

    .category-drawer-title {
        font-size: 1.1rem;
        color: var(--accent);
        margin: 0;
    }

    .category-drawer-body {
        flex: 1;
        overflow-y: auto;
        padding: 1rem 1.4rem 2rem;
    }

    .category-drawer .options-grid {
        display: flex;
        flex-direction: column;
        gap: .8rem;
    }

    .category-drawer .option-card {
        flex-direction: row;
        flex-wrap: wrap;
        align-items: center;
        gap: .5rem 1rem;
        padding: .9rem 1rem;
    }

    .category-drawer .option-card .option-thumb-img,
    .category-drawer .option-card .option-icon {
        order: 1;
        flex-shrink: 0;
    }

    .category-drawer .option-card .option-thumb-img {
        width: 48px;
        height: 48px;
    }

    .category-drawer .option-card .option-icon {
        font-size: 1.8rem;
    }

    .category-drawer .option-card .option-name {
        order: 2;
        flex: 1 1 140px;
        padding-right: 0;
    }

    .category-drawer .option-card .option-specs {
        order: 3;
        flex-basis: 100%;
        margin-left: 64px;
    }

    .category-drawer .option-card .option-desc {
        display: none;
    }

    .category-drawer .option-card .option-price {
        order: 4;
        margin-left: auto;
    }

    .category-drawer .option-card input[type="radio"],
    .category-drawer .option-card input[type="checkbox"] {
        order: 5;
        position: static;
        margin: 0;
    }

    .config-section-title {
        display: flex;
        align-items: center;
        gap: .6rem;
        color: var(--accent);
        font-size: 1.2rem;
        margin: 0;
    }

    .config-section-hint {
        font-size: .78rem;
        color: var(--text-muted);
        font-weight: normal;
        margin-left: .3rem;
    }

    .category-options-panel {
        display: none;
    }

    .category-options-panel.active {
        display: block;
    }

    /* --- Étape "Vos options" (périphériques) --- */
    .config-section {
        background: var(--surface);
        border: 1px solid var(--divider);
        border-radius: var(--radius-md);
        padding: 1.6rem;
        box-shadow: var(--shadow-sm);
    }

    .options-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 1rem;
    }

    .option-card {
        display: flex;
        flex-direction: column;
        gap: .4rem;
        background: var(--surface-alt);
        border: 2px solid transparent;
        border-radius: var(--radius-sm);
        padding: 1rem;
        cursor: pointer;
        transition: border-color var(--ease), transform var(--ease), box-shadow var(--ease);
        position: relative;
    }

    .option-card:hover {
        border-color: var(--accent-2);
        transform: translateY(-2px);
        box-shadow: var(--shadow-sm);
    }

    .option-card.selected {
        border-color: var(--accent);
        background: var(--surface-deep);
        box-shadow: var(--shadow-sm);
    }

    .option-card.hidden-incompatible {
        display: none;
    }

    .option-card input[type="radio"],
    .option-card input[type="checkbox"] {
        position: absolute;
        top: .9rem;
        right: .9rem;
        width: 22px;
        height: 22px;
        margin: 0;
        appearance: none;
        -webkit-appearance: none;
        background-color: var(--surface-deep);
        box-shadow: inset 0 0 0 2px var(--text-muted);
        cursor: pointer;
        transition: box-shadow var(--ease), background-color var(--ease);
        background-repeat: no-repeat;
        background-position: center;
    }

    .option-card input[type="radio"] {
        border-radius: 50%;
    }

    .option-card input[type="checkbox"] {
        border-radius: 6px;
    }

    .option-card input[type="radio"]:checked,
    .option-card input[type="checkbox"]:checked {
        background-color: var(--accent);
        box-shadow: inset 0 0 0 2px var(--accent);
    }

    .option-card input[type="radio"]:checked {
        background-image: radial-gradient(circle, #fff 28%, transparent 30%);
    }

    .option-card input[type="checkbox"]:checked {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none' stroke='white' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='3 8 6.5 11.5 13 4.5'/%3E%3C/svg%3E");
        background-size: 14px;
    }

    .option-card input[type="radio"]:hover,
    .option-card input[type="checkbox"]:hover {
        box-shadow: inset 0 0 0 2px var(--accent-2);
    }

    .option-icon {
        font-size: 1.6rem;
    }

    .option-thumb-img {
        width: 44px;
        height: 44px;
        object-fit: cover;
        border-radius: var(--radius-sm);
        flex-shrink: 0;
    }

    .option-name {
        font-weight: bold;
        color: var(--text);
        padding-right: 1.8rem;
    }

    .option-specs {
        display: flex;
        flex-wrap: wrap;
        gap: .3rem;
    }

    .spec-badge {
        background: var(--surface-deep);
        color: var(--text-muted);
        font-size: .68rem;
        font-weight: bold;
        padding: 2px 7px;
        border-radius: 9px;
    }

    .option-desc {
        color: var(--text-muted);
        font-size: .78rem;
        line-height: 1.4;
        flex: 1;
    }

    .option-price {
        color: var(--accent-2);
        font-weight: bold;
        font-size: 1rem;
    }

    .config-summary {
        background: var(--surface);
        border: 1px solid var(--divider);
        border-radius: var(--radius-md);
        padding: 1.5rem;
        box-shadow: var(--shadow-md);
        max-width: 560px;
        margin: 0 auto;
    }

    .config-summary h3 {
        color: var(--accent);
        margin-bottom: 1rem;
        font-size: 1.15rem;
    }

    .summary-row {
        display: flex;
        justify-content: space-between;
        gap: .6rem;
        font-size: .8rem;
        padding: .5rem 0;
        border-bottom: 1px solid var(--surface-alt);
    }

    .summary-label {
        color: var(--text-muted);
        min-width: 0;
    }

    .summary-value {
        color: var(--text);
        text-align: right;
    }

    .summary-total {
        display: flex;
        justify-content: space-between;
        font-size: 1.2rem;
        font-weight: bold;
        color: var(--text);
        margin: 1rem 0;
        padding-top: .8rem;
        border-top: 2px solid var(--surface-alt);
    }

    .power-note {
        font-size: .78rem;
        color: var(--text-muted);
        margin-bottom: 1rem;
        line-height: 1.4;
        min-height: 1.1em;
    }

    .power-note.ok { color: var(--success); }
    .power-note.warning { color: var(--accent); font-weight: bold; }

    .missing-warning {
        background: rgba(216, 163, 22, .15);
        border: 1px solid #d8a316;
        color: #d8a316;
        border-radius: var(--radius-sm);
        padding: .7rem .9rem;
        font-size: .8rem;
        font-weight: bold;
        margin-bottom: 1rem;
        line-height: 1.4;
    }


    .config-contact .form-group {
        margin-bottom: .8rem;
    }

    .config-contact label {
        display: block;
        margin-bottom: .3rem;
        color: var(--text-muted);
        font-weight: bold;
        font-size: .8rem;
    }

    .config-contact input,
    .config-contact textarea {
        width: 100%;
        padding: .6rem;
        border: 2px solid var(--surface-alt);
        border-radius: var(--radius-sm);
        background: var(--surface-deep);
        color: var(--text);
        font-size: .85rem;
        transition: border-color var(--ease);
    }

    .config-contact input:focus,
    .config-contact textarea:focus {
        outline: none;
        border-color: var(--accent);
    }

    .config-contact textarea {
        height: 70px;
        resize: vertical;
    }

    .config-contact .form-row-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: .6rem;
    }

    .checkbox-group {
        display: flex;
        align-items: flex-start;
        gap: .5rem;
        margin: 1rem 0;
    }

    .checkbox-group input {
        width: auto;
        margin-top: .2rem;
    }

    .checkbox-group label {
        color: var(--text-muted);
        font-size: .76rem;
        line-height: 1.4;
    }

    .btn-submit-config {
        width: 100%;
        background: var(--accent);
        color: #fff;
        padding: .9rem;
        border: none;
        border-radius: var(--radius-sm);
        font-size: 1rem;
        font-weight: bold;
        cursor: pointer;
        transition: background-color var(--ease), transform var(--ease), box-shadow var(--ease);
    }

    .btn-submit-config:hover {
        background: var(--accent-hover);
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    .btn-reset-config {
        width: 100%;
        background: none;
        border: 2px solid var(--surface-alt);
        color: var(--text-muted);
        padding: .6rem;
        border-radius: var(--radius-sm);
        font-size: .82rem;
        cursor: pointer;
        margin-bottom: .8rem;
        transition: border-color var(--ease), color var(--ease);
    }

    .btn-reset-config:hover {
        border-color: var(--accent-2);
        color: var(--text);
    }

    .btn-add-cart {
        width: 100%;
        background: var(--accent-2);
        color: #fff;
        padding: .9rem;
        border: none;
        border-radius: var(--radius-sm);
        font-size: 1rem;
        font-weight: bold;
        cursor: pointer;
        transition: background-color var(--ease), transform var(--ease), box-shadow var(--ease);
    }

    .btn-add-cart:hover {
        background: var(--accent-2-hover);
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    .cart-hint {
        font-size: .72rem;
        color: var(--text-muted);
        text-align: center;
        margin: .5rem 0 1rem;
        line-height: 1.4;
    }

    .config-contact-divider {
        display: flex;
        align-items: center;
        text-align: center;
        color: var(--text-muted);
        font-size: .78rem;
        margin: 1rem 0;
    }

    .config-contact-divider::before,
    .config-contact-divider::after {
        content: '';
        flex: 1;
        border-bottom: 1px solid var(--surface-alt);
    }

    .config-contact-divider span {
        padding: 0 .8rem;
    }

    .btn-add-cart:disabled,
    .btn-submit-config:disabled,
    .btn-step:disabled {
        background: var(--surface-alt) !important;
        color: var(--text-muted) !important;
        cursor: not-allowed;
        box-shadow: none !important;
        transform: none !important;
    }

    @media (max-width: 900px) {
        .config-summary {
            max-width: none;
        }
    }

    @media (max-width: 640px) {
        .config-step {
            font-size: 0;
            padding: .9rem .3rem;
        }

        .config-step-num {
            font-size: .95rem;
            margin-right: 0;
        }
    }

    @media (max-width: 480px) {
        .page-container {
            padding: 0 1.2rem;
        }

        .page-title {
            font-size: 1.6rem;
        }

        .config-section {
            padding: 1rem;
        }

        .category-drawer-body {
            padding: 1rem 1rem 1.5rem;
        }

        .options-grid {
            grid-template-columns: 1fr;
        }

        .category-card {
            flex-basis: 120px;
        }

        .config-contact .form-row-2 {
            grid-template-columns: 1fr;
        }
    }
</style>

<main class="main-content">
    <div class="page-container">
        <h1 class="page-title">🖥️ CONFIGURATEUR PC SUR MESURE</h1>
        <p class="page-subtitle">
            Assemblez votre PC composant par composant : le configurateur ne vous montre que les pièces compatibles
            entre elles (socket, mémoire, format de boîtier). Une fois prête, ajoutez votre configuration au panier
            pour l'acheter directement, ou demandez un devis pour être recontacté par notre équipe.
        </p>

        <?php if ($success): ?>
            <div class="message success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="config-steps" id="configSteps">
            <button type="button" class="config-step<?php echo $etapeInitiale === 'usage' ? ' active' : ''; ?>" data-step="usage"><span class="config-step-num">1.</span> Votre utilisation</button>
            <button type="button" class="config-step" data-step="configuration"><span class="config-step-num">2.</span> Votre configuration</button>
            <button type="button" class="config-step" data-step="options"><span class="config-step-num">3.</span> Vos options</button>
            <button type="button" class="config-step<?php echo $etapeInitiale === 'validation' ? ' active' : ''; ?>" data-step="validation"><span class="config-step-num">4.</span> Votre validation</button>
        </div>

        <form method="POST" id="configForm">
            <?php echo csrfField(); ?>
            <?php echo honeypotField(); ?>

            <!-- ÉTAPE 1 : VOTRE UTILISATION -->
            <div class="config-step-panel<?php echo $etapeInitiale === 'usage' ? ' active' : ''; ?>" data-step-panel="usage">
                <div class="usage-panel">
                    <h2 class="usage-panel-title">Quel est votre usage principal ?</h2>
                    <p class="page-subtitle">
                        Partez d'un profil prêt à l'emploi — entièrement modifiable ensuite composant par composant —
                        ou construisez votre configuration de zéro.
                    </p>
                    <div class="usage-cards">
                        <div class="usage-card" id="presetGaming" role="button" tabindex="0">
                            <div class="usage-card-icon gaming">🎮</div>
                            <h3>Gaming</h3>
                            <p>Ryzen 5 7600X, RTX 4070 Super, 32 Go DDR5 : une config équilibrée pour jouer en 1440p.</p>
                        </div>
                        <div class="usage-card" id="presetBureautique" role="button" tabindex="0">
                            <div class="usage-card-icon bureautique">💼</div>
                            <h3>Bureautique</h3>
                            <p>Ryzen 5 5600, 16 Go DDR4, SSD NVMe : rapide et silencieux pour le travail au quotidien.</p>
                        </div>
                        <div class="usage-card custom" id="usageCustomBtn" role="button" tabindex="0">
                            <div class="usage-card-icon custom">🛠️</div>
                            <h3>Personnalisée</h3>
                            <p>Je choisis chaque composant moi-même, sans partir d'un profil prédéfini.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ÉTAPE 2 : VOTRE CONFIGURATION -->
            <div class="config-step-panel" data-step-panel="configuration">
                <div class="category-strip-wrap">
                    <button type="button" class="category-scroll-btn" id="categoryScrollLeft" aria-label="Précédent">‹</button>
                    <div class="category-strip" id="categoryStrip">
                        <?php foreach ($categories as $type): ?>
                            <div class="category-card" data-type="<?php echo $type; ?>">
                                <?php if (in_array($type, $requis, true)): ?>
                                    <span class="category-card-required">Obligatoire</span>
                                <?php endif; ?>
                                <div class="category-card-visual"><?php echo $icones[$type]; ?></div>
                                <div class="category-card-label"><?php echo htmlspecialchars($labels[$type]); ?></div>
                                <button type="button" class="category-choose-btn" data-type="<?php echo $type; ?>">Je choisis</button>
                                <div class="category-card-selection" data-type="<?php echo $type; ?>">—</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="category-scroll-btn" id="categoryScrollRight" aria-label="Suivant">›</button>
                </div>

                <div class="category-drawer-backdrop" id="categoryDrawerBackdrop"></div>
                <div class="category-drawer" id="categoryDrawer">
                    <div class="category-drawer-header">
                        <button type="button" class="category-drawer-back" id="categoryDrawerBack" aria-label="Fermer">←</button>
                        <h2 class="category-drawer-title" id="categoryDetailTitle">Choisissez votre <?php echo htmlspecialchars(mb_strtolower($labels[$categories[0]])); ?></h2>
                    </div>
                    <div class="category-drawer-body">

                    <?php foreach ($categories as $type): ?>
                        <div class="category-options-panel<?php echo $type === $categories[0] ? ' active' : ''; ?>" data-type-panel="<?php echo $type; ?>">
                            <div class="options-grid" data-type="<?php echo $type; ?>">
                                <?php foreach ($parType[$type] ?? [] as $c):
                                    $id = (int) $c['id'];
                                    if ($isValidationError) {
                                        $checked = ((int) ($_POST['comp_' . $type] ?? 0)) === $id;
                                    } else {
                                        $checked = isset($defaults[$type]) && $defaults[$type] === $id;
                                    }
                                ?>
                                    <label class="option-card<?php echo $checked ? ' selected' : ''; ?>"
                                           data-id="<?php echo $id; ?>" data-type="<?php echo $type; ?>">
                                        <input type="radio" name="comp_<?php echo $type; ?>" value="<?php echo $id; ?>" <?php echo $checked ? 'checked' : ''; ?>>
                                        <?php echo visuelHtml($c, 'composants', nomComplet($c), 'option-thumb-img'); ?>
                                        <span class="option-name"><?php echo htmlspecialchars(nomComplet($c)); ?></span>
                                        <span class="option-specs">
                                            <?php if ($c['socket'] && in_array($type, ['cpu', 'carte_mere'], true)): ?>
                                                <span class="spec-badge"><?php echo htmlspecialchars($c['socket']); ?></span>
                                            <?php endif; ?>
                                            <?php if ($c['ram_type']): ?>
                                                <span class="spec-badge"><?php echo htmlspecialchars($c['ram_type']); ?></span>
                                            <?php endif; ?>
                                            <?php if ($c['form_factor']): ?>
                                                <span class="spec-badge"><?php echo htmlspecialchars($c['form_factor']); ?></span>
                                            <?php endif; ?>
                                            <?php if ($c['wattage']): ?>
                                                <span class="spec-badge"><?php echo (int) $c['wattage']; ?> W</span>
                                            <?php endif; ?>
                                            <?php if ($c['power_draw']): ?>
                                                <span class="spec-badge">TDP <?php echo (int) $c['power_draw']; ?> W</span>
                                            <?php endif; ?>
                                            <?php if ($c['capacite']): ?>
                                                <span class="spec-badge"><?php echo htmlspecialchars($c['capacite']); ?></span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="option-desc"><?php echo htmlspecialchars($c['description']); ?></span>
                                        <span class="option-price"><?php echo (float) $c['prix'] > 0 ? number_format($c['prix'], 2, ',', ' ') . ' €' : 'Inclus'; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>

                <p class="missing-warning" id="configMissingWarning" hidden></p>

                <div class="config-step-nav">
                    <button type="button" class="btn-step" data-goto="usage">← Précédent</button>
                    <button type="button" class="btn-step primary" data-goto="options" id="configNextBtn" disabled>Suivant →</button>
                </div>
            </div>

            <!-- ÉTAPE 3 : VOS OPTIONS (périphériques) -->
            <div class="config-step-panel" data-step-panel="options">
                <div class="config-section">
                    <h2 class="config-section-title">
                        🖱️ Périphériques
                        <span class="config-section-hint">(optionnel, plusieurs choix possibles)</span>
                    </h2>
                    <div class="options-grid" data-type="peripherique">
                        <?php foreach ($parType['peripherique'] ?? [] as $c):
                            $id = (int) $c['id'];
                            $checked = in_array($id, $checkedPeripheriqueIds, true);
                        ?>
                            <label class="option-card<?php echo $checked ? ' selected' : ''; ?>"
                                   data-id="<?php echo $id; ?>" data-type="peripherique">
                                <input type="checkbox" class="peripherique-check" name="comp_peripherique[]" value="<?php echo $id; ?>" <?php echo $checked ? 'checked' : ''; ?>>
                                <?php echo visuelHtml($c, 'composants', nomComplet($c), 'option-thumb-img'); ?>
                                <span class="option-name"><?php echo htmlspecialchars(nomComplet($c)); ?></span>
                                <span class="option-desc"><?php echo htmlspecialchars($c['description']); ?></span>
                                <span class="option-price"><?php echo number_format($c['prix'], 2, ',', ' '); ?> €</span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="config-step-nav">
                    <button type="button" class="btn-step" data-goto="configuration">← Précédent</button>
                    <button type="button" class="btn-step primary" data-goto="validation">Suivant →</button>
                </div>
            </div>

            <!-- ÉTAPE 4 : VOTRE VALIDATION -->
            <div class="config-step-panel<?php echo $etapeInitiale === 'validation' ? ' active' : ''; ?>" data-step-panel="validation">
                <div class="config-summary">
                    <h3>Votre configuration</h3>
                    <?php foreach ($categories as $type): ?>
                        <div class="summary-row" data-type="<?php echo $type; ?>">
                            <span class="summary-label"><?php echo htmlspecialchars($labels[$type]); ?></span>
                            <span class="summary-value">—</span>
                        </div>
                    <?php endforeach; ?>
                    <div class="summary-row" data-type="peripherique">
                        <span class="summary-label">Périphériques</span>
                        <span class="summary-value" id="summaryPeripheriques">Aucun</span>
                    </div>
                    <div class="summary-total">
                        <span>Prix final</span>
                        <span id="summaryTotal">0,00 €</span>
                    </div>
                    <p class="power-note" id="powerNote"></p>

                    <p class="missing-warning" id="missingWarning" hidden></p>

                    <button type="button" class="btn-reset-config" id="resetConfig">Réinitialiser la configuration</button>

                    <button type="submit" name="direction" value="panier" class="btn-add-cart" id="addToCartBtn" disabled>🛒 Ajouter au panier</button>
                    <p class="cart-hint">Achat direct : votre configuration part au panier, vous réglez en ligne comme un produit classique.</p>

                    <div class="config-contact-divider"><span>ou</span></div>

                    <div class="config-contact">
                        <div class="form-group">
                            <label for="nom">Nom *</label>
                            <input type="text" id="nom" name="nom" value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="prenom">Prénom *</label>
                            <input type="text" id="prenom" name="prenom" value="<?php echo htmlspecialchars($_POST['prenom'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="adresse">Adresse *</label>
                            <input type="text" id="adresse" name="adresse" value="<?php echo htmlspecialchars($_POST['adresse'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group form-row-2">
                            <div>
                                <label for="code_postal">Code Postal *</label>
                                <input type="text" id="code_postal" name="code_postal" value="<?php echo htmlspecialchars($_POST['code_postal'] ?? ''); ?>" required>
                            </div>
                            <div>
                                <label for="ville">Ville *</label>
                                <input type="text" id="ville" name="ville" value="<?php echo htmlspecialchars($_POST['ville'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="telephone">Téléphone *</label>
                            <input type="tel" id="telephone" name="telephone" value="<?php echo htmlspecialchars($_POST['telephone'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="message">Infos complémentaires</label>
                            <textarea id="message" name="message" placeholder="Usage prévu, contraintes particulières..."><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                        </div>

                        <div class="checkbox-group">
                            <input type="checkbox" id="consentement" name="consentement" required>
                            <label for="consentement">J'accepte que mes données soient utilisées pour traiter ma demande de devis. *</label>
                        </div>

                        <button type="submit" name="direction" value="devis" class="btn-submit-config" id="devisSubmitBtn" disabled>Demander un devis pour cette configuration</button>
                    </div>
                </div>

                <div class="config-step-nav single">
                    <button type="button" class="btn-step" data-goto="options">← Précédent</button>
                </div>
            </div>
        </form>
    </div>
</main>

<script>
    (function () {
        var catalog = <?php echo json_encode(array_map(function ($c) {
            return [
                'id' => (int) $c['id'],
                'type' => $c['type'],
                'nom' => nomComplet($c),
                'prix' => (float) $c['prix'],
                'socket' => $c['socket'],
                'ram_type' => $c['ram_type'],
                'form_factor' => $c['form_factor'],
                'wattage' => $c['wattage'] !== null ? (int) $c['wattage'] : null,
                'power_draw' => $c['power_draw'] !== null ? (int) $c['power_draw'] : null,
            ];
        }, $composants), JSON_UNESCAPED_UNICODE); ?>;

        var byId = {};
        catalog.forEach(function (c) { byId[c.id] = c; });

        var categories = <?php echo json_encode($categories); ?>;
        var labelsByType = <?php echo json_encode($labels, JSON_UNESCAPED_UNICODE); ?>;
        var requis = <?php echo json_encode($requis); ?>;

        function getSelected(type) {
            var input = document.querySelector('input[name="comp_' + type + '"]:checked');
            return input ? byId[input.value] : null;
        }

        function getSelectedPeripheriques() {
            var inputs = document.querySelectorAll('input.peripherique-check:checked');
            return Array.prototype.map.call(inputs, function (input) { return byId[input.value]; }).filter(Boolean);
        }

        function setCompatibility(type, isCompatible) {
            document.querySelectorAll('.option-card[data-type="' + type + '"]').forEach(function (card) {
                var comp = byId[card.dataset.id];
                var compatible = isCompatible(comp);
                card.classList.toggle('hidden-incompatible', !compatible);
                if (!compatible) {
                    var input = card.querySelector('input');
                    if (input.checked) input.checked = false;
                }
            });
        }

        function updateCompatibility() {
            var cpu = getSelected('cpu');
            var mobo = getSelected('carte_mere');

            setCompatibility('carte_mere', function (m) {
                return !cpu || (m.socket === cpu.socket && m.ram_type === cpu.ram_type);
            });
            setCompatibility('cpu', function (c) {
                return !mobo || (c.socket === mobo.socket && c.ram_type === mobo.ram_type);
            });

            // Recalcule après un éventuel dé-sélection automatique ci-dessus
            cpu = getSelected('cpu');
            mobo = getSelected('carte_mere');
            var ramType = (mobo && mobo.ram_type) || (cpu && cpu.ram_type) || null;

            setCompatibility('ram', function (r) {
                return !ramType || r.ram_type === ramType;
            });
            setCompatibility('boitier', function (b) {
                return !mobo || (b.form_factor || '').split(',').indexOf(mobo.form_factor) !== -1;
            });
        }

        function updateSummary() {
            var total = 0;
            var totalPower = 0;

            categories.forEach(function (type) {
                var comp = getSelected(type);
                var row = document.querySelector('.summary-row[data-type="' + type + '"] .summary-value');
                if (comp) {
                    total += comp.prix;
                    totalPower += comp.power_draw || 0;
                    row.textContent = comp.nom + (comp.prix > 0 ? ' — ' + comp.prix.toFixed(2).replace('.', ',') + ' €' : ' (inclus)');
                } else {
                    row.textContent = 'Non sélectionné';
                }
            });

            var peripheriques = getSelectedPeripheriques();
            var peripheriquesTotal = peripheriques.reduce(function (sum, p) { return sum + p.prix; }, 0);
            total += peripheriquesTotal;
            var periphRow = document.getElementById('summaryPeripheriques');
            if (peripheriques.length) {
                periphRow.textContent = peripheriques.length + ' sélectionné' + (peripheriques.length > 1 ? 's' : '') + ' — ' + peripheriquesTotal.toFixed(2).replace('.', ',') + ' €';
            } else {
                periphRow.textContent = 'Aucun';
            }

            document.getElementById('summaryTotal').textContent = total.toFixed(2).replace('.', ',') + ' €';

            var alim = getSelected('alimentation');
            var recommended = totalPower + 150;
            var powerNote = document.getElementById('powerNote');
            if (alim) {
                if (alim.wattage < recommended) {
                    powerNote.textContent = '⚠️ Alimentation possiblement insuffisante (recommandé : ' + recommended + ' W minimum).';
                    powerNote.className = 'power-note warning';
                } else {
                    powerNote.textContent = '✓ Consommation estimée : ' + totalPower + ' W — alimentation suffisante.';
                    powerNote.className = 'power-note ok';
                }
            } else if (totalPower > 0) {
                powerNote.textContent = 'Consommation estimée : ' + totalPower + ' W — choisissez une alimentation d\'au moins ' + recommended + ' W.';
                powerNote.className = 'power-note';
            } else {
                powerNote.textContent = '';
                powerNote.className = 'power-note';
            }
        }

        function updateCategoryCards() {
            categories.forEach(function (type) {
                var comp = getSelected(type);
                var label = document.querySelector('.category-card-selection[data-type="' + type + '"]');
                if (label) {
                    label.textContent = comp ? (comp.nom + (comp.prix > 0 ? ' · ' + comp.prix.toFixed(2).replace('.', ',') + ' €' : ' · inclus')) : '—';
                }
                var requiredTag = document.querySelector('.category-card[data-type="' + type + '"] .category-card-required');
                if (requiredTag) {
                    requiredTag.textContent = comp ? '✓ Choisi' : 'Obligatoire';
                    requiredTag.classList.toggle('done', !!comp);
                }
            });
        }

        function getMissingRequis() {
            return requis.filter(function (type) { return !getSelected(type); });
        }

        function updateValidationGate() {
            var missing = getMissingRequis();
            var isComplete = missing.length === 0;
            var message = isComplete ? '' : ('⚠️ Il vous reste à choisir : ' + missing.map(function (type) { return labelsByType[type]; }).join(', ') + '.');

            [
                ['configNextBtn', 'configMissingWarning'],
                ['addToCartBtn', 'missingWarning'],
            ].forEach(function (pair) {
                var btn = document.getElementById(pair[0]);
                var warning = document.getElementById(pair[1]);
                if (btn) btn.disabled = !isComplete;
                if (warning) {
                    warning.textContent = message;
                    warning.hidden = isComplete;
                }
            });

            var devisBtn = document.getElementById('devisSubmitBtn');
            if (devisBtn) devisBtn.disabled = !isComplete;
        }

        // --- Verrouillage séquentiel : on ne peut choisir une catégorie
        // obligatoire que si la précédente catégorie obligatoire (dans
        // l'ordre du menu) a déjà une sélection. Les catégories optionnelles
        // (gpu, refroidissement, os) ont toutes un choix gratuit par défaut
        // et restent donc toujours accessibles, sans bloquer la suite. ---
        function previousRequiredIndex(idx) {
            for (var i = idx - 1; i >= 0; i--) {
                if (requis.indexOf(categories[i]) !== -1) return i;
            }
            return -1;
        }

        function isCategoryUnlocked(type) {
            if (requis.indexOf(type) === -1) return true;
            var prevIdx = previousRequiredIndex(categories.indexOf(type));
            if (prevIdx === -1) return true;
            return !!getSelected(categories[prevIdx]);
        }

        function updateCategoryLocks() {
            categories.forEach(function (type) {
                var card = document.querySelector('.category-card[data-type="' + type + '"]');
                if (!card) return;
                var unlocked = isCategoryUnlocked(type);
                card.classList.toggle('locked', !unlocked);
                var btn = card.querySelector('.category-choose-btn');
                if (btn) btn.disabled = !unlocked;
            });
        }

        function refresh() {
            updateCompatibility();
            updateSummary();
            updateCategoryCards();
            updateCategoryLocks();
            updateValidationGate();
            document.querySelectorAll('.option-card').forEach(function (card) {
                card.classList.toggle('selected', card.querySelector('input').checked);
            });
        }

        // Rend les cartes cliquables (div[role="button"]) utilisables au clavier.
        document.querySelectorAll('[role="button"][tabindex]').forEach(function (el) {
            el.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    el.click();
                }
            });
        });

        // --- Navigation par étapes (Votre utilisation / configuration / options / validation) ---
        function goToStep(step) {
            document.querySelectorAll('.config-step').forEach(function (btn) {
                btn.classList.toggle('active', btn.dataset.step === step);
            });
            document.querySelectorAll('.config-step-panel').forEach(function (panel) {
                panel.classList.toggle('active', panel.dataset.stepPanel === step);
            });
            var steps = document.getElementById('configSteps');
            if (steps) {
                window.scrollTo({ top: steps.getBoundingClientRect().top + window.scrollY - 20, behavior: 'smooth' });
            }
        }

        document.querySelectorAll('.config-step').forEach(function (btn) {
            btn.addEventListener('click', function () { goToStep(btn.dataset.step); });
        });
        document.querySelectorAll('[data-goto]').forEach(function (btn) {
            btn.addEventListener('click', function () { goToStep(btn.dataset.goto); });
        });
        var usageCustomBtn = document.getElementById('usageCustomBtn');
        if (usageCustomBtn) {
            usageCustomBtn.addEventListener('click', function () { goToStep('configuration'); });
        }

        // --- Menu de catégories (étape "Votre configuration") ---
        function goToCategory(type) {
            if (!isCategoryUnlocked(type)) return;

            document.querySelectorAll('.category-card').forEach(function (card) {
                card.classList.toggle('active', card.dataset.type === type);
            });
            document.querySelectorAll('.category-options-panel').forEach(function (panel) {
                panel.classList.toggle('active', panel.dataset.typePanel === type);
            });
            var title = document.getElementById('categoryDetailTitle');
            if (title && labelsByType[type]) {
                title.textContent = 'Choisissez votre ' + labelsByType[type].toLowerCase();
            }
            var activeCard = document.querySelector('.category-card[data-type="' + type + '"]');
            if (activeCard) {
                // 'nearest' ne bouge le strip que si la carte n'est pas déjà visible,
                // au lieu de la recentrer à chaque fois (ce qui poussait les choix déjà
                // faits, comme le boîtier, hors champ sur la gauche).
                activeCard.scrollIntoView({ behavior: 'smooth', inline: 'nearest', block: 'nearest' });
            }

            var drawer = document.getElementById('categoryDrawer');
            var backdrop = document.getElementById('categoryDrawerBackdrop');
            if (drawer) drawer.classList.add('open');
            if (backdrop) backdrop.classList.add('open');
        }

        function closeCategoryDrawer() {
            var drawer = document.getElementById('categoryDrawer');
            var backdrop = document.getElementById('categoryDrawerBackdrop');
            if (drawer) drawer.classList.remove('open');
            if (backdrop) backdrop.classList.remove('open');
        }

        document.querySelectorAll('.category-choose-btn, .category-card').forEach(function (el) {
            el.addEventListener('click', function () { goToCategory(el.dataset.type); });
        });

        var categoryDrawerBack = document.getElementById('categoryDrawerBack');
        if (categoryDrawerBack) categoryDrawerBack.addEventListener('click', closeCategoryDrawer);
        var categoryDrawerBackdrop = document.getElementById('categoryDrawerBackdrop');
        if (categoryDrawerBackdrop) categoryDrawerBackdrop.addEventListener('click', closeCategoryDrawer);

        var categoryStrip = document.getElementById('categoryStrip');
        var categoryScrollLeft = document.getElementById('categoryScrollLeft');
        var categoryScrollRight = document.getElementById('categoryScrollRight');
        if (categoryScrollLeft && categoryStrip) {
            categoryScrollLeft.addEventListener('click', function () { categoryStrip.scrollBy({ left: -300, behavior: 'smooth' }); });
        }
        if (categoryScrollRight && categoryStrip) {
            categoryScrollRight.addEventListener('click', function () { categoryStrip.scrollBy({ left: 300, behavior: 'smooth' }); });
        }

        document.querySelectorAll('.option-card input[type="radio"], .option-card input[type="checkbox"]').forEach(function (input) {
            input.addEventListener('change', function () {
                refresh();
                var card = input.closest('.option-card');
                var type = card ? card.dataset.type : null;
                // Choisir un composant referme le tiroir : on revoit la grille de
                // catégories et on reclique "Je choisis" pour passer à la suivante.
                if (input.type === 'radio' && type && categories.indexOf(type) !== -1) {
                    closeCategoryDrawer();
                }
            });
        });

        var resetBtn = document.getElementById('resetConfig');
        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                document.querySelectorAll('.option-card input[type="radio"], .option-card input[type="checkbox"]').forEach(function (i) { i.checked = false; });
                refresh();
            });
        }

        // Le bouton "Ajouter au panier" n'a pas besoin des coordonnées du
        // client (elles seront demandées au moment du paiement) : on
        // désactive la validation HTML5 de ces champs avant que le clic ne
        // déclenche la soumission (le contrôle de validité a lieu avant
        // l'évènement "submit", donc trop tard pour agir dedans).
        var form = document.getElementById('configForm');
        var addToCartBtn = document.getElementById('addToCartBtn');
        if (addToCartBtn) {
            addToCartBtn.addEventListener('click', function () {
                form.querySelectorAll('.config-contact [required]').forEach(function (el) {
                    el.required = false;
                });
            });
        }

        function selectByName(type, nom) {
            var comp = catalog.find(function (c) { return c.type === type && c.nom === nom; });
            if (!comp) return;
            var input = document.querySelector('.option-card[data-type="' + type + '"][data-id="' + comp.id + '"] input');
            if (input) input.checked = true;
        }

        function selectPeripheriques(noms) {
            document.querySelectorAll('input.peripherique-check').forEach(function (i) { i.checked = false; });
            noms.forEach(function (nom) {
                var comp = catalog.find(function (c) { return c.type === 'peripherique' && c.nom === nom; });
                if (!comp) return;
                var input = document.querySelector('.option-card[data-type="peripherique"][data-id="' + comp.id + '"] input');
                if (input) input.checked = true;
            });
        }

        var presetGaming = document.getElementById('presetGaming');
        if (presetGaming) {
            presetGaming.addEventListener('click', function () {
                selectByName('cpu', 'AMD Ryzen 5 7600X');
                selectByName('carte_mere', 'ASUS TUF Gaming B650-PLUS');
                selectByName('ram', 'Corsair Vengeance 32 Go (2x16) 6000MHz');
                selectByName('gpu', 'NVIDIA GeForce RTX 4070 Super');
                selectByName('stockage', 'Samsung SSD NVMe 990 Pro 1 To');
                selectByName('alimentation', 'Corsair RM750 750W 80+ Gold');
                selectByName('boitier', 'NZXT H5 Flow ATX Gaming');
                selectByName('refroidissement', 'Corsair iCUE 240mm AIO');
                selectByName('os', 'Microsoft Windows 11 Famille (OEM)');
                selectPeripheriques([
                    'Samsung Écran 27" 2K 144Hz',
                    'Corsair Clavier mécanique gaming RGB',
                    'Logitech Souris gaming filaire',
                    'HyperX Casque gaming avec micro'
                ]);
                refresh();
                goToStep('validation');
            });
        }

        var presetBureautique = document.getElementById('presetBureautique');
        if (presetBureautique) {
            presetBureautique.addEventListener('click', function () {
                selectByName('cpu', 'AMD Ryzen 5 5600');
                selectByName('carte_mere', 'ASRock B450M Pro4');
                selectByName('ram', 'Corsair Vengeance LPX 16 Go (2x8) 3200MHz');
                selectByName('gpu', 'Sans carte graphique dédiée (iGPU)');
                selectByName('stockage', 'Samsung SSD NVMe 980 500 Go');
                selectByName('alimentation', 'Corsair CV550 550W 80+ Bronze');
                selectByName('boitier', 'Fractal Design Meshify Compact mATX');
                selectByName('refroidissement', 'Ventirad d\'origine');
                selectByName('os', 'Microsoft Windows 11 Famille (OEM)');
                selectPeripheriques([
                    'AOC Écran 24" Full HD 75Hz',
                    'Logitech Pack clavier + souris bureautique'
                ]);
                refresh();
                goToStep('validation');
            });
        }

        refresh();
    })();
</script>

<?php include 'footer.php'; ?>
