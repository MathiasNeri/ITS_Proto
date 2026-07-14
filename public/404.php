<?php
require_once __DIR__ . '/../backend/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$is_logged_in = checkAuth();
$user_role = $_SESSION['user_role'] ?? '';

http_response_code(404);
?>
<?php include 'header.php'; ?>

<style>
    .error-page {
        min-height: calc(100vh - 320px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 3rem 2rem;
    }

    .error-card {
        text-align: center;
        max-width: 520px;
    }

    .error-code {
        font-size: 6rem;
        font-weight: 800;
        color: var(--accent);
        line-height: 1;
        margin-bottom: .5rem;
    }

    .error-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
    }

    .error-title {
        font-size: 1.4rem;
        font-weight: 800;
        color: var(--text);
        margin-bottom: .8rem;
    }

    .error-text {
        color: var(--text-muted);
        line-height: 1.6;
        margin-bottom: 2rem;
    }

    .error-actions {
        display: flex;
        gap: 1rem;
        justify-content: center;
        flex-wrap: wrap;
        margin-bottom: 2.5rem;
    }

    .error-btn {
        padding: .9rem 1.8rem;
        border-radius: 6px;
        font-weight: bold;
        font-size: .9rem;
        text-decoration: none;
        transition: background .2s ease, transform .15s ease;
    }

    .error-btn.primary {
        background: var(--accent);
        color: #fff;
    }

    .error-btn.primary:hover {
        background: var(--accent-hover);
        transform: translateY(-1px);
    }

    .error-btn.secondary {
        background: var(--surface);
        border: 2px solid var(--surface-alt);
        color: var(--text);
    }

    .error-btn.secondary:hover {
        border-color: var(--accent-2);
        transform: translateY(-1px);
    }

    .error-links {
        display: flex;
        gap: 1.2rem;
        justify-content: center;
        flex-wrap: wrap;
        font-size: .85rem;
    }

    .error-links a {
        color: var(--accent-2);
        text-decoration: none;
        font-weight: bold;
    }

    .error-links a:hover {
        text-decoration: underline;
    }
</style>

<main class="main-content">
    <div class="error-page">
        <div class="error-card">
            <div class="error-icon">🔌</div>
            <div class="error-code">404</div>
            <h1 class="error-title">Cette page est introuvable</h1>
            <p class="error-text">
                La page que vous cherchez n'existe pas, a été déplacée, ou l'adresse comporte une erreur.
                Vérifiez l'URL ou repartez depuis l'accueil.
            </p>
            <div class="error-actions">
                <a href="accueil.php" class="error-btn primary">Retour à l'accueil</a>
                <a href="boutique.php" class="error-btn secondary">Voir la boutique</a>
            </div>
            <div class="error-links">
                <a href="reparations.php">Réparations</a>
                <a href="tarifs.php">Tarifs</a>
                <a href="rdv.php">Prendre RDV</a>
                <a href="contact.php">Contact</a>
            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>
