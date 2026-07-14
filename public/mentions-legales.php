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
        <h1>Mentions légales</h1>
        <p class="maj">Dernière mise à jour : <?php echo date('d/m/Y'); ?></p>

        <h2>Éditeur du site</h2>
        <p>
            ITS — Informatique Téléphonie Service, enseigne commerciale de S.A.S INFOCOM PIERREFEU<br>
            Forme juridique : SAS, société par actions simplifiée<br>
            Capital social : 1 000,00 €<br>
            SIREN : 893 676 023<br>
            SIRET (siège) : 893 676 023 00011<br>
            RCS : 893 676 023 R.C.S. Toulon (greffe de Toulon, inscrite le 05/02/2021)<br>
            N° de TVA intracommunautaire : FR73893676023<br>
            Code APE/NAF : 47.41Z (Commerce de détail d'ordinateurs, d'unités périphériques et de logiciels en magasin spécialisé)<br>
            Adresse du siège : <span class="placeholder">[à compléter]</span><br>
            Boutiques : Solliès-Pont &amp; Pierrefeu<br>
            Téléphone : <span class="placeholder">[à compléter]</span><br>
            Email : contact@its-reparation.fr<br>
            Directeur de la publication : <span class="placeholder">[à compléter]</span>
        </p>

        <h2>Hébergement</h2>
        <p>
            Hébergeur : <span class="placeholder">[à compléter — ex. OVH SAS]</span><br>
            Adresse : <span class="placeholder">[à compléter]</span>
        </p>

        <h2>Propriété intellectuelle</h2>
        <p>L'ensemble des contenus présents sur ce site (textes, images, logos, structure) est la propriété d'ITS, sauf mention contraire, et ne peut être reproduit sans autorisation préalable.</p>

        <h2>Responsabilité</h2>
        <p>ITS s'efforce d'assurer l'exactitude des informations diffusées sur ce site mais ne saurait être tenu responsable des erreurs, omissions, ou de l'indisponibilité temporaire du service.</p>

        <h2>Litiges</h2>
        <p>Le présent site est soumis au droit français. En cas de litige, les tribunaux français seront seuls compétents.</p>
    </div>
</main>

<?php include 'footer.php'; ?>
