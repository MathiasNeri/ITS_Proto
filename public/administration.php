<?php
require_once __DIR__ . '/../backend/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté et est admin
if (!checkAuth() || $_SESSION['user_role'] !== 'admin') {
    redirect('connexion.php');
}

$pdo = initDatabase();
sauvegardeQuotidienneSiNecessaire();

$categories = [
    'tel'   => 'Téléphones',
    'pc'    => 'Ordinateurs',
    'tab'   => 'Tablettes',
    'piece' => 'Pièces détachées',
    'acc'   => 'Accessoires',
];
$tags = [
    'neuf'     => 'Neuf',
    'recond'   => 'Reconditionné',
    'occasion' => 'Occasion',
    'promo'    => 'Promo',
];
$statuts = [
    'en_attente_paiement' => 'En attente de paiement',
    'nouvelle'            => 'Nouvelle (payée)',
    'preparation'         => 'En préparation',
    'expediee'            => 'Expédiée',
    'livree'              => 'Livrée / retirée',
    'annulee'             => 'Annulée',
];
$statuts_devis = [
    'nouveau'  => 'Nouveau',
    'en_cours' => 'En cours',
    'traite'   => 'Traité',
    'annule'   => 'Annulé',
];

$message = '';
$error = '';

if ($_POST) {
    if (!csrfVerify()) {
        $error = 'Session expirée, merci de réessayer.';
    } else {
        $action = $_POST['action'] ?? '';

        try {
            if ($action === 'delete_rdv' && isset($_POST['rdv_id'])) {
                $pdo->prepare('DELETE FROM rdv WHERE id = ?')->execute([$_POST['rdv_id']]);
                $message = 'Rendez-vous supprimé avec succès';
            } elseif ($action === 'mark_rdv_vu' && isset($_POST['rdv_id'])) {
                $pdo->prepare('UPDATE rdv SET vu = 1 WHERE id = ?')->execute([$_POST['rdv_id']]);
                $message = 'Rendez-vous marqué comme vu';
            } elseif ($action === 'update_statut' && isset($_POST['commande_id'], $_POST['statut'])) {
                if (array_key_exists($_POST['statut'], $statuts)) {
                    $pdo->prepare('UPDATE commandes SET statut = ? WHERE id = ?')->execute([$_POST['statut'], $_POST['commande_id']]);
                    $message = 'Statut de la commande mis à jour';
                }
            } elseif ($action === 'update_suivi' && isset($_POST['commande_id'])) {
                $pdo->prepare('UPDATE commandes SET numero_suivi = ? WHERE id = ?')
                    ->execute([trim($_POST['numero_suivi'] ?? ''), $_POST['commande_id']]);
                $message = 'Numéro de suivi enregistré';
            } elseif ($action === 'add_promo') {
                $code = strtoupper(trim($_POST['code'] ?? ''));
                $type = ($_POST['type'] ?? '') === 'montant' ? 'montant' : 'pourcentage';
                $valeur = (float) ($_POST['valeur'] ?? 0);
                $usageMax = trim($_POST['usage_max'] ?? '') !== '' ? (int) $_POST['usage_max'] : null;
                $dateExpiration = trim($_POST['date_expiration'] ?? '') !== '' ? $_POST['date_expiration'] : null;

                if ($code === '' || $valeur <= 0) {
                    $error = 'Code et valeur requis';
                } else {
                    $pdo->prepare('INSERT INTO codes_promo (code, type, valeur, usage_max, date_expiration) VALUES (?, ?, ?, ?, ?)')
                        ->execute([$code, $type, $valeur, $usageMax, $dateExpiration]);
                    $message = 'Code promo créé';
                }
            } elseif ($action === 'toggle_promo' && isset($_POST['promo_id'])) {
                $pdo->prepare('UPDATE codes_promo SET actif = 1 - actif WHERE id = ?')->execute([$_POST['promo_id']]);
                $message = 'Code promo mis à jour';
            } elseif ($action === 'delete_promo' && isset($_POST['promo_id'])) {
                $pdo->prepare('DELETE FROM codes_promo WHERE id = ?')->execute([$_POST['promo_id']]);
                $message = 'Code promo supprimé';
            } elseif ($action === 'approve_avis' && isset($_POST['avis_id'])) {
                $pdo->prepare('UPDATE avis SET approuve = 1 WHERE id = ?')->execute([$_POST['avis_id']]);
                $message = 'Avis publié';
            } elseif ($action === 'delete_avis' && isset($_POST['avis_id'])) {
                $pdo->prepare('DELETE FROM avis WHERE id = ?')->execute([$_POST['avis_id']]);
                $message = 'Avis supprimé';
            } elseif ($action === 'add_produit') {
                $nom = trim($_POST['nom'] ?? '');
                $categorie = $_POST['categorie'] ?? '';
                $icone = trim($_POST['icone'] ?? '📦') ?: '📦';
                $prix = (float) ($_POST['prix'] ?? 0);
                $prixBarre = trim($_POST['prix_barre'] ?? '') !== '' ? (float) $_POST['prix_barre'] : null;
                $tag = $_POST['tag'] ?? 'neuf';
                $etoiles = max(1, min(5, (int) ($_POST['etoiles'] ?? 5)));
                $description = trim($_POST['description'] ?? '');
                $stock = max(0, (int) ($_POST['stock'] ?? 0));

                if ($nom === '' || !array_key_exists($categorie, $categories) || $prix <= 0) {
                    $error = 'Merci de remplir correctement le nom, la catégorie et le prix du produit';
                } else {
                    $pdo->prepare('INSERT INTO produits (nom, categorie, icone, prix, prix_barre, tag, etoiles, description, stock) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
                        ->execute([$nom, $categorie, $icone, $prix, $prixBarre, $tag, $etoiles, $description, $stock]);
                    $message = 'Produit ajouté au catalogue';
                }
            } elseif ($action === 'edit_produit' && isset($_POST['produit_id'])) {
                $nom = trim($_POST['nom'] ?? '');
                $prix = (float) ($_POST['prix'] ?? 0);
                $prixBarre = trim($_POST['prix_barre'] ?? '') !== '' ? (float) $_POST['prix_barre'] : null;
                $tag = $_POST['tag'] ?? 'neuf';
                $stock = max(0, (int) ($_POST['stock'] ?? 0));

                if ($nom === '' || $prix <= 0) {
                    $error = 'Nom et prix invalides';
                } else {
                    $pdo->prepare('UPDATE produits SET nom = ?, prix = ?, prix_barre = ?, tag = ?, stock = ? WHERE id = ?')
                        ->execute([$nom, $prix, $prixBarre, $tag, $stock, $_POST['produit_id']]);
                    $message = 'Produit mis à jour';
                }
            } elseif ($action === 'delete_produit' && isset($_POST['produit_id'])) {
                $pdo->prepare('DELETE FROM produits WHERE id = ?')->execute([$_POST['produit_id']]);
                $message = 'Produit supprimé';
            } elseif ($action === 'delete_message' && isset($_POST['message_id'])) {
                $pdo->prepare('DELETE FROM messages WHERE id = ?')->execute([$_POST['message_id']]);
                $message = 'Message supprimé';
            } elseif ($action === 'mark_message_lu' && isset($_POST['message_id'])) {
                $pdo->prepare('UPDATE messages SET lu = 1 WHERE id = ?')->execute([$_POST['message_id']]);
                $message = 'Message marqué comme lu';
            } elseif ($action === 'update_statut_devis' && isset($_POST['devis_id'], $_POST['statut'])) {
                if (array_key_exists($_POST['statut'], $statuts_devis)) {
                    $pdo->prepare('UPDATE devis SET statut = ? WHERE id = ?')->execute([$_POST['statut'], $_POST['devis_id']]);
                    $message = 'Statut de la demande de devis mis à jour';
                }
            } elseif ($action === 'delete_devis' && isset($_POST['devis_id'])) {
                $stmt = $pdo->prepare('SELECT fichier_chemin FROM devis WHERE id = ?');
                $stmt->execute([$_POST['devis_id']]);
                $fichierChemin = $stmt->fetchColumn();
                if ($fichierChemin) {
                    $chemin = __DIR__ . '/../database/uploads/devis/' . basename($fichierChemin);
                    if (is_file($chemin)) {
                        unlink($chemin);
                    }
                }
                $pdo->prepare('DELETE FROM devis WHERE id = ?')->execute([$_POST['devis_id']]);
                $message = 'Demande de devis supprimée';
            } elseif ($action === 'creer_sauvegarde') {
                $cheminSauvegarde = creerSauvegardeBaseDeDonnees();
                $envoyee = envoyerSauvegardeParEmail($cheminSauvegarde);
                $message = 'Sauvegarde créée (' . basename($cheminSauvegarde) . ')' . ($envoyee ? ', envoyée par email à l\'admin.' : '. SMTP non configuré : elle reste locale uniquement.');
            }
        } catch (PDOException $e) {
            $error = 'Erreur lors du traitement de l\'action';
            logError($e->getMessage());
        }
    }
}

// Récupérer les données
try {
    $rdv_list = $pdo->query('SELECT * FROM rdv ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
    $devis_list = $pdo->query('SELECT * FROM devis ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
    $users_list = $pdo->query('SELECT * FROM users ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
    $produits_list = $pdo->query('SELECT * FROM produits ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
    $commandes_list = $pdo->query('SELECT * FROM commandes ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
    $messages_list = $pdo->query('SELECT * FROM messages ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
    $emails_list = $pdo->query('SELECT * FROM emails_log ORDER BY created_at DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
    $promos_list = $pdo->query('SELECT * FROM codes_promo ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
    $avis_list = $pdo->query('SELECT a.*, p.nom AS produit_nom FROM avis a LEFT JOIN produits p ON p.id = a.produit_id ORDER BY a.created_at DESC')->fetchAll(PDO::FETCH_ASSOC);

    $stmtLignes = $pdo->prepare('SELECT * FROM commande_lignes WHERE commande_id = ?');
    $lignesParCommande = [];
    foreach ($commandes_list as $c) {
        $stmtLignes->execute([$c['id']]);
        $lignesParCommande[$c['id']] = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = 'Erreur lors du chargement des données';
    logError($e->getMessage());
    $rdv_list = $devis_list = $users_list = $produits_list = $commandes_list = $messages_list = $emails_list = $promos_list = $avis_list = [];
    $lignesParCommande = [];
}

// Sauvegardes locales disponibles (les plus récentes en premier)
$backups_list = glob(cheminDossierSauvegardes() . '/its-backup-*.sqlite') ?: [];
rsort($backups_list);
$derniere_sauvegarde = !empty($backups_list) ? filemtime($backups_list[0]) : null;

$is_logged_in = true;
$user_role = 'admin';
$messagesNonLus = count(array_filter($messages_list, function ($m) { return (int) $m['lu'] === 0; }));
$rdvNonVus = count(array_filter($rdv_list, function ($r) { return (int) $r['vu'] === 0; }));
$devisNonTraites = count(array_filter($devis_list, function ($d) { return $d['statut'] === 'nouveau'; }));

$page_noindex = true;
?>
<?php include 'header.php'; ?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<style>
    .admin-container {
        max-width: 1240px;
        margin: 2rem auto;
        padding: 0 2rem;
    }

    /* Calendrier flatpickr thémé pour coller aux couleurs du site */
    .flatpickr-calendar {
        background: var(--surface);
        border: 2px solid var(--surface-alt);
        box-shadow: none;
        color: var(--text);
    }
    .flatpickr-months, .flatpickr-weekdays { background: var(--surface); }
    .flatpickr-month, .flatpickr-current-month, .flatpickr-current-month input.cur-year {
        color: var(--text);
        fill: var(--text);
    }
    .flatpickr-current-month .flatpickr-monthDropdown-months {
        background: var(--surface);
        color: var(--text);
    }
    span.flatpickr-weekday { background: var(--surface); color: var(--text-muted); }
    .flatpickr-day { color: var(--text); }
    .flatpickr-day.flatpickr-disabled, .flatpickr-day.flatpickr-disabled:hover {
        color: var(--text-muted);
        opacity: .35;
    }
    .flatpickr-day.prevMonthDay, .flatpickr-day.nextMonthDay { color: var(--text-muted); opacity: .4; }
    .flatpickr-day:hover { background: var(--surface-alt); }
    .flatpickr-day.today { border-color: var(--accent-2); }
    .flatpickr-day.selected, .flatpickr-day.selected:hover {
        background: var(--accent);
        border-color: var(--accent);
        color: #fff;
    }
    .flatpickr-prev-month svg, .flatpickr-next-month svg { fill: var(--text); }

    .admin-title {
        font-size: 2rem;
        margin-bottom: 1.5rem;
        color: var(--accent);
        text-align: center;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 1rem;
        margin-bottom: 1.6rem;
    }

    .stat-card {
        background: var(--surface-alt);
        padding: 1.2rem;
        border-radius: 8px;
        text-align: center;
    }

    .stat-number {
        font-size: 1.8rem;
        font-weight: bold;
        color: var(--accent);
    }

    .stat-label {
        color: var(--text-muted);
        margin-top: 0.4rem;
        font-size: .82rem;
    }

    .tabs {
        display: flex;
        gap: .5rem;
        flex-wrap: wrap;
        border-bottom: 1px solid var(--surface-alt);
        margin-bottom: 1.6rem;
    }

    .tab-btn {
        background: none;
        border: none;
        color: var(--text-muted);
        font-weight: bold;
        font-size: .85rem;
        padding: .7rem 1rem;
        cursor: pointer;
        border-bottom: 3px solid transparent;
    }

    .tab-btn.active {
        color: var(--accent-2);
        border-bottom-color: var(--accent-2);
    }

    .tab-badge {
        display: inline-block;
        min-width: 18px;
        padding: 1px 5px;
        margin-left: .4rem;
        border-radius: 9px;
        background: var(--accent);
        color: #fff;
        font-size: .68rem;
        font-weight: 800;
        line-height: 1.4;
    }

    .tab-panel {
        display: none;
    }

    .tab-panel.active {
        display: block;
    }

    .admin-card {
        background: var(--surface);
        padding: 1.6rem;
        border-radius: 10px;
        border: 2px solid var(--surface-alt);
        margin-bottom: 1.5rem;
    }

    .card-title {
        font-size: 1.15rem;
        margin-bottom: 1rem;
        color: var(--accent);
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: .5rem;
        font-size: .85rem;
    }

    .data-table th,
    .data-table td {
        padding: 0.65rem;
        text-align: left;
        border-bottom: 1px solid var(--surface-alt);
        vertical-align: middle;
    }

    .data-table th {
        background: var(--surface-alt);
        color: var(--accent);
        font-weight: bold;
    }

    .data-table td {
        color: var(--text-muted);
    }

    .table-scroll {
        overflow-x: auto;
    }

    .btn-delete {
        background: var(--accent);
        color: #fff;
        padding: 0.4rem 0.8rem;
        border: none;
        border-radius: 3px;
        cursor: pointer;
        font-size: 0.75rem;
        white-space: nowrap;
    }

    .btn-delete:hover {
        background: var(--accent-hover);
    }

    .btn-small {
        background: var(--accent-2);
        color: #fff;
        padding: 0.4rem 0.8rem;
        border: none;
        border-radius: 3px;
        cursor: pointer;
        font-size: 0.75rem;
        white-space: nowrap;
    }

    .btn-small:hover {
        background: var(--accent-2-hover);
    }

    .inline-form {
        display: flex;
        gap: .3rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .inline-form input,
    .inline-form select {
        background: var(--surface-alt);
        color: var(--text);
        border: 1px solid var(--surface-alt);
        border-radius: 4px;
        padding: .35rem .5rem;
        font-size: .78rem;
    }

    .inline-form input[type="text"],
    .inline-form input[name="nom"] {
        width: 130px;
    }

    .inline-form input[type="number"] {
        width: 70px;
    }

    .inline-form .field {
        display: flex;
        flex-direction: column;
        gap: .2rem;
    }

    .inline-form .field label {
        font-size: .65rem;
        color: var(--text-muted);
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: .3px;
    }

    .badge {
        display: inline-block;
        font-size: .68rem;
        font-weight: 800;
        padding: 2px 8px;
        border-radius: 10px;
        color: #fff;
    }

    .badge.unread { background: var(--accent); }
    .badge.read { background: var(--success); }

    .message-box {
        padding: 1rem;
        border-radius: 5px;
        margin-bottom: 1rem;
        text-align: center;
    }

    .message-box.success {
        background: var(--success);
        color: #fff;
    }

    .message-box.error {
        background: var(--accent);
        color: #fff;
    }

    .add-product-form {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: .8rem;
        align-items: end;
    }

    .add-product-form .full {
        grid-column: 1 / -1;
    }

    .add-product-form label {
        display: block;
        font-size: .72rem;
        color: var(--text-muted);
        margin-bottom: .3rem;
        font-weight: bold;
    }

    .add-product-form input,
    .add-product-form select,
    .add-product-form textarea {
        width: 100%;
        padding: .6rem;
        border: 1px solid var(--surface-alt);
        border-radius: 5px;
        background: var(--surface-alt);
        color: var(--text);
        font-size: .85rem;
    }

    .add-product-form textarea {
        min-height: 60px;
        resize: vertical;
    }

    .add-product-form button {
        background: var(--accent);
        color: #fff;
        border: none;
        border-radius: 5px;
        padding: .7rem;
        font-weight: bold;
        cursor: pointer;
        font-size: .85rem;
    }

    .add-product-form button:hover {
        background: var(--accent-hover);
    }

    .order-lines-cell {
        font-size: .75rem;
        line-height: 1.5;
    }

    .stock-out { color: var(--accent); font-weight: bold; }
    .stock-low { color: #d8a316; font-weight: bold; }
</style>

<main class="main-content">
    <div class="admin-container">
        <h1 class="admin-title">Panel d'Administration</h1>

        <?php if ($message): ?>
            <div class="message-box success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message-box error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($rdv_list); ?></div>
                <div class="stat-label">Rendez-vous</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($devis_list); ?></div>
                <div class="stat-label">Demandes de devis</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($users_list); ?></div>
                <div class="stat-label">Utilisateurs</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($commandes_list); ?></div>
                <div class="stat-label">Commandes</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($produits_list); ?></div>
                <div class="stat-label">Produits</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $messagesNonLus; ?></div>
                <div class="stat-label">Messages non lus</div>
            </div>
        </div>

        <?php if (!isStripeConfigured()): ?>
            <div class="message-box" style="background:#d8a316;color:#1c1c1c;">⚠️ Stripe non configuré — les commandes sont payées en mode simulation (aucun encaissement réel). Renseignez <code>stripe_secret_key</code> / <code>stripe_publishable_key</code> dans backend/config.php.</div>
        <?php endif; ?>
        <?php if (!isSmtpConfigured()): ?>
            <div class="message-box" style="background:#d8a316;color:#1c1c1c;">⚠️ SMTP non configuré — les emails sont journalisés (onglet Emails) au lieu d'être réellement envoyés. Renseignez <code>smtp_host</code> / <code>smtp_user</code> dans backend/config.php.</div>
        <?php endif; ?>

        <div class="tabs" id="adminTabs">
            <button type="button" class="tab-btn active" data-tab="rdv">Rendez-vous<?php if ($rdvNonVus > 0): ?><span class="tab-badge"><?php echo $rdvNonVus; ?></span><?php endif; ?></button>
            <button type="button" class="tab-btn" data-tab="devis">Devis<?php if ($devisNonTraites > 0): ?><span class="tab-badge"><?php echo $devisNonTraites; ?></span><?php endif; ?></button>
            <button type="button" class="tab-btn" data-tab="commandes">Commandes</button>
            <button type="button" class="tab-btn" data-tab="produits">Produits</button>
            <button type="button" class="tab-btn" data-tab="promos">Codes promo</button>
            <button type="button" class="tab-btn" data-tab="avis">Avis clients</button>
            <button type="button" class="tab-btn" data-tab="messages">Messages<?php if ($messagesNonLus > 0): ?><span class="tab-badge"><?php echo $messagesNonLus; ?></span><?php endif; ?></button>
            <button type="button" class="tab-btn" data-tab="emails">Emails</button>
            <button type="button" class="tab-btn" data-tab="utilisateurs">Utilisateurs</button>
            <button type="button" class="tab-btn" data-tab="maintenance">Maintenance</button>
        </div>

        <!-- RDV -->
        <div class="tab-panel active" id="tab-rdv">
            <div class="admin-card">
                <h3 class="card-title">Rendez-vous</h3>
                <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr><th>Statut</th><th>Nom</th><th>Email</th><th>Service</th><th>Boutique</th><th>Date</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rdv_list as $rdv): ?>
                        <tr>
                            <td><span class="badge <?php echo $rdv['vu'] ? 'read' : 'unread'; ?>"><?php echo $rdv['vu'] ? 'Vu' : 'Nouveau'; ?></span></td>
                            <td><?php echo htmlspecialchars($rdv['nom'] . ' ' . $rdv['prenom']); ?></td>
                            <td><?php echo htmlspecialchars($rdv['email']); ?></td>
                            <td><?php echo htmlspecialchars($rdv['service']); ?></td>
                            <td><?php echo htmlspecialchars($rdv['boutique']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($rdv['date_rdv'])); ?></td>
                            <td class="inline-form">
                                <?php if (!$rdv['vu']): ?>
                                <form method="POST">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="mark_rdv_vu">
                                    <input type="hidden" name="rdv_id" value="<?php echo $rdv['id']; ?>">
                                    <button type="submit" class="btn-small">Marquer vu</button>
                                </form>
                                <?php endif; ?>
                                <form method="POST">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="delete_rdv">
                                    <input type="hidden" name="rdv_id" value="<?php echo $rdv['id']; ?>">
                                    <button type="submit" class="btn-delete" onclick="return confirm('Supprimer ce rendez-vous ?')">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($rdv_list)): ?>
                            <tr><td colspan="7">Aucun rendez-vous pour le moment.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <!-- DEVIS -->
        <div class="tab-panel" id="tab-devis">
            <div class="admin-card">
                <h3 class="card-title">Demandes de devis</h3>
                <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr><th>Client</th><th>Matériel</th><th>Contact</th><th>Adresse</th><th>Message</th><th>Fichier</th><th>Date</th><th>Statut</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($devis_list as $d): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($d['prenom'] . ' ' . $d['nom']); ?></td>
                            <td><?php echo htmlspecialchars($d['materiel']); ?></td>
                            <td><?php echo htmlspecialchars($d['email']); ?><br><span style="font-size:.72rem;"><?php echo htmlspecialchars($d['telephone']); ?></span></td>
                            <td style="max-width:180px;"><?php echo htmlspecialchars($d['adresse'] . ', ' . $d['code_postal'] . ' ' . $d['ville']); ?></td>
                            <td style="max-width:220px;">
                                <?php if (strlen($d['message'] ?? '') > 140): ?>
                                    <details>
                                        <summary style="cursor:pointer;color:var(--accent-2);">Voir le détail</summary>
                                        <div style="margin-top:.5rem; white-space: pre-wrap;"><?php echo htmlspecialchars($d['message']); ?></div>
                                    </details>
                                <?php else: ?>
                                    <?php echo nl2br(htmlspecialchars($d['message'] ?? '')); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($d['fichier_chemin'])): ?>
                                    <a href="devis-fichier.php?id=<?php echo $d['id']; ?>" class="btn-small" style="text-decoration:none;display:inline-block;">Télécharger</a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($d['created_at'])); ?></td>
                            <td>
                                <form method="POST" class="inline-form">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="update_statut_devis">
                                    <input type="hidden" name="devis_id" value="<?php echo $d['id']; ?>">
                                    <select name="statut" onchange="this.form.submit()">
                                        <?php foreach ($statuts_devis as $key => $label): ?>
                                            <option value="<?php echo $key; ?>" <?php echo $d['statut'] === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Supprimer cette demande de devis ?')">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="delete_devis">
                                    <input type="hidden" name="devis_id" value="<?php echo $d['id']; ?>">
                                    <button type="submit" class="btn-delete">Suppr.</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($devis_list)): ?>
                            <tr><td colspan="9">Aucune demande de devis pour le moment.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <!-- COMMANDES -->
        <div class="tab-panel" id="tab-commandes">
            <div class="admin-card">
                <h3 class="card-title">Commandes</h3>
                <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr><th>N°</th><th>Client</th><th>Articles</th><th>Total</th><th>Livraison</th><th>Adresse</th><th>N° suivi</th><th>Date</th><th>Statut</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($commandes_list as $commande): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($commande['numero']); ?><?php if (!empty($commande['code_promo'])): ?><br><span style="font-size:.68rem;color:var(--success);">Promo : <?php echo htmlspecialchars($commande['code_promo']); ?></span><?php endif; ?></td>
                            <td><?php echo htmlspecialchars($commande['nom']); ?><br><span style="font-size:.72rem;"><?php echo htmlspecialchars($commande['email']); ?></span><?php if (!empty($commande['telephone'])): ?><br><span style="font-size:.72rem;"><?php echo htmlspecialchars($commande['telephone']); ?></span><?php endif; ?></td>
                            <td class="order-lines-cell">
                                <?php foreach ($lignesParCommande[$commande['id']] ?? [] as $l): ?>
                                    <?php echo htmlspecialchars($l['nom_produit']); ?> × <?php echo (int) $l['quantite']; ?><br>
                                <?php endforeach; ?>
                            </td>
                            <td><?php echo number_format($commande['total'], 2, ',', ' '); ?> €</td>
                            <td><?php echo $commande['mode_livraison'] === 'colissimo' ? '📦 Colissimo' : '🏬 Boutique'; ?></td>
                            <td style="max-width:180px;">
                                <details>
                                    <summary style="cursor:pointer;color:var(--accent-2);font-size:.78rem;">Voir</summary>
                                    <div style="margin-top:.4rem;white-space:pre-wrap;font-size:.76rem;color:var(--text-muted);"><?php echo htmlspecialchars($commande['adresse']); ?></div>
                                </details>
                            </td>
                            <td>
                                <?php if ($commande['mode_livraison'] === 'colissimo'): ?>
                                <form method="POST" class="inline-form">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="update_suivi">
                                    <input type="hidden" name="commande_id" value="<?php echo $commande['id']; ?>">
                                    <input type="text" name="numero_suivi" value="<?php echo htmlspecialchars($commande['numero_suivi'] ?? ''); ?>" placeholder="N° suivi" style="width:100px;">
                                    <button type="submit" class="btn-small">OK</button>
                                </form>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($commande['created_at'])); ?></td>
                            <td>
                                <form method="POST" class="inline-form">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="update_statut">
                                    <input type="hidden" name="commande_id" value="<?php echo $commande['id']; ?>">
                                    <select name="statut" onchange="this.form.submit()">
                                        <?php foreach ($statuts as $key => $label): ?>
                                            <option value="<?php echo $key; ?>" <?php echo $commande['statut'] === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($commandes_list)): ?>
                            <tr><td colspan="9">Aucune commande pour le moment.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <!-- PRODUITS -->
        <div class="tab-panel" id="tab-produits">
            <div class="admin-card">
                <h3 class="card-title">Ajouter un produit</h3>
                <form method="POST" class="add-product-form">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="add_produit">
                    <div>
                        <label>Nom</label>
                        <input type="text" name="nom" required>
                    </div>
                    <div>
                        <label>Catégorie</label>
                        <select name="categorie" required>
                            <?php foreach ($categories as $key => $label): ?>
                                <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Icône (emoji)</label>
                        <input type="text" name="icone" value="📦" maxlength="4">
                    </div>
                    <div>
                        <label>Prix (€)</label>
                        <input type="number" step="0.01" min="0.01" name="prix" required>
                    </div>
                    <div>
                        <label>Prix barré (optionnel)</label>
                        <input type="number" step="0.01" min="0" name="prix_barre">
                    </div>
                    <div>
                        <label>État</label>
                        <select name="tag">
                            <?php foreach ($tags as $key => $label): ?>
                                <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Étoiles (1-5)</label>
                        <input type="number" name="etoiles" min="1" max="5" value="5">
                    </div>
                    <div>
                        <label>Stock</label>
                        <input type="number" name="stock" min="0" value="0">
                    </div>
                    <div class="full">
                        <label>Description</label>
                        <textarea name="description"></textarea>
                    </div>
                    <div>
                        <button type="submit">Ajouter le produit</button>
                    </div>
                </form>
            </div>

            <div class="admin-card">
                <h3 class="card-title">Catalogue</h3>
                <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr><th>Produit</th><th>Modifier</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produits_list as $p): ?>
                        <tr>
                            <td><?php echo $p['icone']; ?> <?php echo htmlspecialchars($p['nom']); ?><br><span style="font-size:.7rem;"><?php echo $categories[$p['categorie']] ?? $p['categorie']; ?></span></td>
                            <td>
                                <form method="POST" class="inline-form">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="edit_produit">
                                    <input type="hidden" name="produit_id" value="<?php echo $p['id']; ?>">
                                    <span class="field"><label>Nom</label><input type="text" name="nom" value="<?php echo htmlspecialchars($p['nom']); ?>"></span>
                                    <span class="field"><label>Prix €</label><input type="number" step="0.01" name="prix" value="<?php echo $p['prix']; ?>"></span>
                                    <span class="field"><label>Prix barré</label><input type="number" step="0.01" name="prix_barre" value="<?php echo $p['prix_barre']; ?>" placeholder="—"></span>
                                    <span class="field"><label>État</label>
                                        <select name="tag">
                                            <?php foreach ($tags as $key => $label): ?>
                                                <option value="<?php echo $key; ?>" <?php echo $p['tag'] === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </span>
                                    <span class="field"><label>Stock</label><input type="number" name="stock" value="<?php echo (int) $p['stock']; ?>" class="<?php echo (int) $p['stock'] <= 0 ? 'stock-out' : ((int) $p['stock'] <= 3 ? 'stock-low' : ''); ?>"></span>
                                    <button type="submit" class="btn-small">Enregistrer</button>
                                </form>
                            </td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Supprimer ce produit ?')">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="delete_produit">
                                    <input type="hidden" name="produit_id" value="<?php echo $p['id']; ?>">
                                    <button type="submit" class="btn-delete">Suppr.</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($produits_list)): ?>
                            <tr><td colspan="3">Aucun produit dans le catalogue.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <!-- MESSAGES -->
        <div class="tab-panel" id="tab-messages">
            <div class="admin-card">
                <h3 class="card-title">Messages de contact</h3>
                <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr><th>Statut</th><th>Nom</th><th>Email</th><th>Sujet</th><th>Message</th><th>Date</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($messages_list as $m): ?>
                        <tr>
                            <td><span class="badge <?php echo $m['lu'] ? 'read' : 'unread'; ?>"><?php echo $m['lu'] ? 'Lu' : 'Non lu'; ?></span></td>
                            <td><?php echo htmlspecialchars($m['nom']); ?></td>
                            <td><?php echo htmlspecialchars($m['email']); ?></td>
                            <td><?php echo htmlspecialchars($m['sujet']); ?></td>
                            <td style="max-width: 260px;"><?php echo nl2br(htmlspecialchars($m['message'])); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($m['created_at'])); ?></td>
                            <td class="inline-form">
                                <?php if (!$m['lu']): ?>
                                <form method="POST">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="mark_message_lu">
                                    <input type="hidden" name="message_id" value="<?php echo $m['id']; ?>">
                                    <button type="submit" class="btn-small">Marquer lu</button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" onsubmit="return confirm('Supprimer ce message ?')">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="delete_message">
                                    <input type="hidden" name="message_id" value="<?php echo $m['id']; ?>">
                                    <button type="submit" class="btn-delete">Suppr.</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($messages_list)): ?>
                            <tr><td colspan="7">Aucun message pour le moment.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <!-- CODES PROMO -->
        <div class="tab-panel" id="tab-promos">
            <div class="admin-card">
                <h3 class="card-title">Créer un code promo</h3>
                <form method="POST" class="add-product-form">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="add_promo">
                    <div>
                        <label>Code</label>
                        <input type="text" name="code" placeholder="ETE2026" required style="text-transform:uppercase;">
                    </div>
                    <div>
                        <label>Type</label>
                        <select name="type">
                            <option value="pourcentage">Pourcentage (%)</option>
                            <option value="montant">Montant fixe (€)</option>
                        </select>
                    </div>
                    <div>
                        <label>Valeur</label>
                        <input type="number" step="0.01" min="0.01" name="valeur" required>
                    </div>
                    <div>
                        <label>Utilisations max (optionnel)</label>
                        <input type="number" min="1" name="usage_max">
                    </div>
                    <div>
                        <label>Expire le (optionnel)</label>
                        <input type="text" id="date_expiration" name="date_expiration" autocomplete="off" placeholder="JJ/MM/AAAA">
                    </div>
                    <div>
                        <button type="submit">Créer</button>
                    </div>
                </form>
            </div>

            <div class="admin-card">
                <h3 class="card-title">Codes existants</h3>
                <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr><th>Code</th><th>Type</th><th>Valeur</th><th>Utilisations</th><th>Expire</th><th>Statut</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($promos_list as $promo): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($promo['code']); ?></strong></td>
                            <td><?php echo $promo['type'] === 'pourcentage' ? 'Pourcentage' : 'Montant fixe'; ?></td>
                            <td><?php echo $promo['type'] === 'pourcentage' ? (float) $promo['valeur'] . ' %' : number_format($promo['valeur'], 2, ',', ' ') . ' €'; ?></td>
                            <td><?php echo (int) $promo['usage_compte']; ?><?php echo $promo['usage_max'] ? ' / ' . (int) $promo['usage_max'] : ''; ?></td>
                            <td><?php echo $promo['date_expiration'] ? htmlspecialchars($promo['date_expiration']) : '—'; ?></td>
                            <td><span class="badge <?php echo $promo['actif'] ? 'read' : 'unread'; ?>"><?php echo $promo['actif'] ? 'Actif' : 'Désactivé'; ?></span></td>
                            <td class="inline-form">
                                <form method="POST">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="toggle_promo">
                                    <input type="hidden" name="promo_id" value="<?php echo $promo['id']; ?>">
                                    <button type="submit" class="btn-small"><?php echo $promo['actif'] ? 'Désactiver' : 'Activer'; ?></button>
                                </form>
                                <form method="POST" onsubmit="return confirm('Supprimer ce code ?')">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="delete_promo">
                                    <input type="hidden" name="promo_id" value="<?php echo $promo['id']; ?>">
                                    <button type="submit" class="btn-delete">Suppr.</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($promos_list)): ?>
                            <tr><td colspan="7">Aucun code promo.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <!-- AVIS CLIENTS -->
        <div class="tab-panel" id="tab-avis">
            <div class="admin-card">
                <h3 class="card-title">Avis clients</h3>
                <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr><th>Statut</th><th>Produit</th><th>Auteur</th><th>Note</th><th>Commentaire</th><th>Date</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($avis_list as $a): ?>
                        <tr>
                            <td><span class="badge <?php echo $a['approuve'] ? 'read' : 'unread'; ?>"><?php echo $a['approuve'] ? 'Publié' : 'En attente'; ?></span></td>
                            <td><?php echo htmlspecialchars($a['produit_nom'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($a['nom']); ?></td>
                            <td><?php echo str_repeat('★', (int) $a['note']) . str_repeat('☆', 5 - (int) $a['note']); ?></td>
                            <td style="max-width:260px;"><?php echo nl2br(htmlspecialchars($a['commentaire'])); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($a['created_at'])); ?></td>
                            <td class="inline-form">
                                <?php if (!$a['approuve']): ?>
                                <form method="POST">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="approve_avis">
                                    <input type="hidden" name="avis_id" value="<?php echo $a['id']; ?>">
                                    <button type="submit" class="btn-small">Publier</button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" onsubmit="return confirm('Supprimer cet avis ?')">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="delete_avis">
                                    <input type="hidden" name="avis_id" value="<?php echo $a['id']; ?>">
                                    <button type="submit" class="btn-delete">Suppr.</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($avis_list)): ?>
                            <tr><td colspan="7">Aucun avis pour le moment.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <!-- EMAILS -->
        <div class="tab-panel" id="tab-emails">
            <div class="admin-card">
                <h3 class="card-title">Emails (100 derniers)</h3>
                <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr><th>Statut</th><th>Destinataire</th><th>Sujet</th><th>Date</th><th>Aperçu</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($emails_list as $e): ?>
                        <tr>
                            <td><span class="badge <?php echo $e['statut'] === 'envoye' ? 'read' : ($e['statut'] === 'echec' ? 'unread' : ''); ?>" style="<?php echo $e['statut'] === 'journalise' ? 'background:var(--accent-2);' : ''; ?>">
                                <?php echo ['envoye' => 'Envoyé', 'echec' => 'Échec', 'journalise' => 'Journalisé (dev)'][$e['statut']] ?? $e['statut']; ?>
                            </span></td>
                            <td><?php echo htmlspecialchars($e['destinataire']); ?></td>
                            <td><?php echo htmlspecialchars($e['sujet']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($e['created_at'])); ?></td>
                            <td>
                                <details>
                                    <summary style="cursor:pointer;color:var(--accent-2);">Voir</summary>
                                    <div style="max-width:400px; margin-top:.5rem; background:var(--surface-deep); padding:.6rem; border-radius:6px;"><?php echo $e['corps_html']; ?></div>
                                </details>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($emails_list)): ?>
                            <tr><td colspan="5">Aucun email pour le moment.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <!-- UTILISATEURS -->
        <div class="tab-panel" id="tab-utilisateurs">
            <div class="admin-card">
                <h3 class="card-title">Utilisateurs</h3>
                <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr><th>Nom</th><th>Email</th><th>Rôle</th><th>Inscription</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users_list as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['nom'] . ' ' . $user['prenom']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['role']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <!-- MAINTENANCE -->
        <div class="tab-panel" id="tab-maintenance">
            <div class="admin-card">
                <h3 class="card-title">Sauvegardes de la base de données</h3>
                <?php if (!isSmtpConfigured()): ?>
                    <div class="message-box" style="background:#d8a316;color:#1c1c1c;">⚠️ SMTP non configuré : les sauvegardes restent uniquement locales et seront perdues au prochain redéploiement si l'hébergement n'a pas de disque persistant (c'est le cas du plan Render gratuit). Configurez <code>smtp_host</code> pour qu'elles soient aussi envoyées par email à l'admin.</div>
                <?php endif; ?>
                <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem;">
                    Une sauvegarde automatique est déclenchée dès qu'un admin se connecte et que la précédente date de plus de 24h.
                    Dernière sauvegarde locale :
                    <strong style="color:var(--text);"><?php echo $derniere_sauvegarde ? date('d/m/Y à H:i', $derniere_sauvegarde) : 'aucune pour le moment'; ?></strong>.
                </p>
                <form method="POST" style="margin-bottom:1.5rem;">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="creer_sauvegarde">
                    <button type="submit" class="btn-small">Créer une sauvegarde maintenant</button>
                </form>
                <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr><th>Fichier</th><th>Date</th><th>Taille</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backups_list as $fichierSauvegarde): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(basename($fichierSauvegarde)); ?></td>
                            <td><?php echo date('d/m/Y H:i', filemtime($fichierSauvegarde)); ?></td>
                            <td><?php echo round(filesize($fichierSauvegarde) / 1024 / 1024, 2); ?> Mo</td>
                            <td><a href="backup-download.php?fichier=<?php echo urlencode(basename($fichierSauvegarde)); ?>" class="btn-small" style="text-decoration:none;display:inline-block;">Télécharger</a></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($backups_list)): ?>
                            <tr><td colspan="4">Aucune sauvegarde locale pour le moment.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
    (function () {
        var tabs = document.querySelectorAll('#adminTabs .tab-btn');
        tabs.forEach(function (btn) {
            btn.addEventListener('click', function () {
                tabs.forEach(function (b) { b.classList.remove('active'); });
                document.querySelectorAll('.tab-panel').forEach(function (p) { p.classList.remove('active'); });
                btn.classList.add('active');
                document.getElementById('tab-' + btn.getAttribute('data-tab')).classList.add('active');
            });
        });
    })();
</script>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js"></script>
<script>
    (function () {
        var el = document.getElementById('date_expiration');
        if (!el || typeof flatpickr === 'undefined') return;
        flatpickr(el, {
            locale: 'fr',
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd/m/Y',
            minDate: 'today',
            disableMobile: true
        });
    })();
</script>

<?php include 'footer.php'; ?>
