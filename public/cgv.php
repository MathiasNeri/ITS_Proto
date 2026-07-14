<?php
require_once __DIR__ . '/../backend/config.php';
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

    .legal-container ul {
        padding-left: 1.3rem;
        margin-bottom: 1rem;
    }

    .placeholder {
        background: rgba(231, 76, 60, .12);
        border: 1px dashed var(--accent);
        border-radius: 4px;
        padding: 0 .4rem;
        color: var(--accent);
        font-weight: bold;
    }
</style>

<main class="main-content">
    <div class="legal-container">
        <h1>Conditions Générales de Vente</h1>
        <p class="maj">Dernière mise à jour : <?php echo date('d/m/Y'); ?></p>

        <h2>1. Objet</h2>
        <p>Les présentes conditions générales de vente régissent les ventes de produits (téléphones, ordinateurs, tablettes, pièces détachées, accessoires) et de prestations de réparation réalisées par ITS, que ce soit en boutique (Solliès-Pont, Pierrefeu) ou via ce site.</p>

        <h2>2. Prix</h2>
        <p>Les prix sont indiqués en euros, toutes taxes comprises (TVA française en vigueur). ITS se réserve le droit de modifier ses prix à tout moment, les produits étant facturés sur la base du tarif en vigueur au moment de la validation de la commande.</p>

        <h2>3. Commande et paiement</h2>
        <p>La commande est validée après confirmation du paiement en ligne (carte bancaire, via un prestataire de paiement sécurisé). Un email de confirmation est envoyé au client à la validation.</p>

        <h2>4. Livraison</h2>
        <ul>
            <li><strong>Retrait en boutique</strong> : gratuit, sur les boutiques de Solliès-Pont et Pierrefeu, dès notification de disponibilité.</li>
            <li><strong>Livraison à domicile</strong> : via Colissimo, frais indiqués lors de la commande, livraison gratuite au-delà du montant affiché au panier.</li>
        </ul>

        <h2>5. Droit de rétractation</h2>
        <p>Conformément au Code de la consommation, le client dispose d'un délai de 14 jours à compter de la réception du produit pour exercer son droit de rétractation, sans avoir à justifier de motif. Le produit doit être retourné en parfait état, dans son emballage d'origine si possible. Les frais de retour sont à la charge du client, sauf erreur d'ITS.</p>
        <p>Ce droit ne s'applique pas aux prestations de réparation pleinement exécutées avant la fin du délai de rétractation, avec l'accord exprès du client.</p>

        <h2>6. Garantie</h2>
        <p>Les produits neufs bénéficient de la garantie légale de conformité (2 ans) et de la garantie contre les vices cachés. Les produits reconditionnés et d'occasion bénéficient d'une garantie commerciale de 12 mois (sauf mention contraire sur la fiche produit), portant sur les défauts de fonctionnement.</p>

        <h2>7. Réparations</h2>
        <p>Un diagnostic est établi avant toute intervention. Le client reçoit un devis qu'il doit valider avant le début des travaux. Les pièces remplacées sont, sauf demande contraire, restituées ou détruites après réparation.</p>

        <h2>8. Litiges</h2>
        <p>En cas de litige, le client peut recourir à une médiation de la consommation. À défaut d'accord amiable, les tribunaux français seront seuls compétents. Éditeur : S.A.S INFOCOM PIERREFEU, SIRET 893 676 023 00011, R.C.S. Toulon.</p>
    </div>
</main>

<?php include 'footer.php'; ?>
