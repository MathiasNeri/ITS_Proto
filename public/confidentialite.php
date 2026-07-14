<?php
require_once '../backend/config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$is_logged_in = checkAuth();
$user_role = $_SESSION['user_role'] ?? '';
?>
<?php include 'header.php'; ?>

<style>
    .legal-container {
        max-width: 800px;
        margin: 2rem auto;
        padding: 0 2rem;
    }

    .legal-container h1 {
        color: var(--accent);
        font-size: 2rem;
        margin-bottom: .5rem;
    }

    .legal-container .maj {
        color: var(--text-muted);
        font-size: .82rem;
        margin-bottom: 2rem;
    }

    .legal-container h2 {
        color: var(--text);
        font-size: 1.2rem;
        margin: 2rem 0 .8rem;
    }

    .legal-container p, .legal-container li {
        color: var(--text-muted);
        line-height: 1.7;
        font-size: .92rem;
    }

    .legal-container table {
        width: 100%;
        border-collapse: collapse;
        margin: 1rem 0;
        font-size: .85rem;
    }

    .legal-container th, .legal-container td {
        border: 1px solid var(--surface-alt);
        padding: .6rem;
        text-align: left;
        color: var(--text-muted);
    }

    .legal-container th {
        color: var(--text);
        background: var(--surface-alt);
    }
</style>

<main class="main-content">
    <div class="legal-container">
        <h1>Politique de confidentialité</h1>
        <p class="maj">Dernière mise à jour : <?php echo date('d/m/Y'); ?></p>

        <h2>Données collectées</h2>
        <p>Dans le cadre de son activité, ITS collecte les données suivantes : nom, prénom, email, téléphone, adresse (pour la livraison), historique de commandes et de rendez-vous. Ces données sont fournies directement par vous lors de la création d'un compte, d'une commande, d'une prise de rendez-vous ou de l'envoi d'un message via le formulaire de contact.</p>

        <h2>Finalités</h2>
        <ul>
            <li>Gestion des commandes et des paiements</li>
            <li>Gestion des rendez-vous de réparation</li>
            <li>Réponse aux demandes via le formulaire de contact</li>
            <li>Gestion du compte client (connexion, historique de commandes)</li>
        </ul>

        <h2>Cookies</h2>
        <table>
            <thead>
                <tr><th>Cookie</th><th>Finalité</th><th>Durée</th></tr>
            </thead>
            <tbody>
                <tr><td>PHPSESSID</td><td>Session (panier, connexion)</td><td>Session navigateur</td></tr>
                <tr><td>its_theme</td><td>Préférence d'affichage clair/sombre</td><td>Persistant (local)</td></tr>
                <tr><td>its_cookie_consent</td><td>Mémorisation de votre choix cookies</td><td>Persistant (local)</td></tr>
            </tbody>
        </table>
        <p>Aucun cookie publicitaire ou de traçage tiers n'est déposé par ce site.</p>

        <h2>Conservation</h2>
        <p>Les données liées à un compte client sont conservées tant que le compte est actif. Les données de commande sont conservées 10 ans à des fins comptables (obligation légale). Vous pouvez demander la suppression de votre compte à tout moment.</p>

        <h2>Vos droits</h2>
        <p>Conformément au RGPD, vous disposez d'un droit d'accès, de rectification, d'effacement et de portabilité de vos données, ainsi que d'un droit d'opposition. Pour exercer ces droits, contactez-nous à contact@its-reparation.fr.</p>

        <h2>Sécurité</h2>
        <p>Les mots de passe sont stockés de façon chiffrée (hachage), jamais en clair. Les paiements par carte bancaire sont traités directement par notre prestataire de paiement (Stripe) : ITS ne stocke jamais vos données de carte bancaire.</p>
    </div>
</main>

<?php include 'footer.php'; ?>
