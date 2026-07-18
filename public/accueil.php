<?php
require_once __DIR__ . '/../backend/config.php';

// Vérification de l'authentification
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$is_logged_in = checkAuth();
$user_role = $_SESSION['user_role'] ?? '';
?>
<?php include 'header.php'; ?>

<style>
    /* Hero Section */
    .hero {
        height: 60vh;
        background: linear-gradient(180deg, rgba(0,0,0,.55) 0%, rgba(0,0,0,.5) 55%, rgba(0,0,0,.82) 100%),
                    url('images/hero-bg.jpg');
        background-size: cover;
        background-position: center;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }
    
    /* Fallback si l'image n'existe pas */
    .hero:before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 600"><rect fill="%23333" width="1200" height="600"/><rect fill="%23444" x="100" y="200" width="200" height="100" rx="10"/><rect fill="%23555" x="400" y="150" width="300" height="200" rx="15"/><circle fill="%23666" cx="800" cy="250" r="50"/></svg>');
        background-size: cover;
        background-position: center;
        z-index: -1;
    }
    
    .hero-content {
        text-align: center;
        max-width: 800px;
        padding: 0 2rem;
    }
    
    .hero-text {
        font-size: 1.5rem;
        margin-bottom: 2rem;
        /* Toujours sur la photo assombrie du hero : reste blanc dans les deux thèmes */
        color: #ffffff;
        text-shadow: 2px 2px 4px rgba(0,0,0,0.8);
    }

    .hero-cta {
        display: flex;
        justify-content: center;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .hero-cta a {
        display: inline-flex;
        align-items: center;
        gap: .5rem;
        padding: .9rem 1.8rem;
        border-radius: var(--radius-sm);
        font-weight: bold;
        font-size: .95rem;
        text-decoration: none;
        transition: transform var(--ease), box-shadow var(--ease), background-color var(--ease);
    }

    .hero-cta .cta-primary {
        background: var(--accent);
        color: #fff;
        box-shadow: 0 8px 24px rgba(231, 76, 60, .4);
    }

    .hero-cta .cta-primary:hover {
        background: var(--accent-hover);
        transform: translateY(-2px);
        box-shadow: 0 12px 28px rgba(231, 76, 60, .5);
    }

    .hero-cta .cta-secondary {
        background: rgba(255, 255, 255, .1);
        color: #fff;
        border: 1.5px solid rgba(255, 255, 255, .55);
        backdrop-filter: blur(4px);
    }

    .hero-cta .cta-secondary:hover {
        background: rgba(255, 255, 255, .18);
        transform: translateY(-2px);
        border-color: #fff;
    }

    .typing-line {
        display: inline-block;
    }

    #typedWord {
        text-transform: uppercase;
    }

    .typing-caret {
        display: inline-block;
        color: inherit;
        margin-left: 1px;
        animation: caretBlink 1s step-end infinite;
    }

    @keyframes caretBlink {
        from, to { opacity: 1; }
        50% { opacity: 0; }
    }

    /* Main Content */
    .main-content {
        padding: 4rem 2rem;
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .main-title {
        text-align: center;
        font-size: 1.3rem;
        font-weight: bold;
        margin-bottom: 3rem;
        color: var(--text-muted);
        letter-spacing: .3px;
    }

    .main-title .typing-line {
        font-size: 2.4rem;
        color: var(--accent);
        margin-bottom: .6rem;
    }


    .services-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 2rem;
        margin-bottom: 4rem;
    }
    
    .service-card {
        background: var(--surface);
        border: 1px solid var(--divider);
        border-radius: var(--radius-md);
        padding: 2rem 1.5rem;
        text-align: center;
        box-shadow: var(--shadow-sm);
        transition: transform var(--ease), box-shadow var(--ease), border-color var(--ease);
    }

    .service-card:hover {
        transform: translateY(-4px);
        border-color: var(--accent);
        box-shadow: var(--shadow-md);
    }
    
    .service-icon {
        width: 60px;
        height: 60px;
        margin: 0 auto 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .service-title {
        font-size: 1.1rem;
        font-weight: bold;
        color: var(--text);
    }
    
    /* OS Support */
    .os-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 2rem;
        margin-bottom: 4rem;
    }
    
    .os-card {
        background: var(--surface);
        border: 1px solid var(--divider);
        border-radius: var(--radius-md);
        padding: 1.2rem 1rem;
        text-align: center;
        box-shadow: var(--shadow-sm);
        transition: transform var(--ease), box-shadow var(--ease);
    }

    .os-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-md);
    }
    
    .os-icon {
        width: 50px;
        height: 50px;
        margin: 0 auto 0.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .os-title {
        font-size: 0.9rem;
        color: var(--text);
        font-weight: 500;
    }
    
    /* Action Buttons */
    .action-buttons {
        text-align: center;
        margin-bottom: 4rem;
    }
    
    .action-btn-center {
        margin-bottom: 2rem;
    }
    
    .action-btn-row {
        display: flex;
        justify-content: center;
        gap: 2rem;
    }
    
    .action-btn {
        background: transparent;
        color: var(--text);
        padding: 1rem 2rem;
        border: 2px solid var(--accent-2);
        border-radius: var(--radius-sm);
        font-size: 1rem;
        font-weight: bold;
        cursor: pointer;
        transition: background-color var(--ease), transform var(--ease), box-shadow var(--ease);
        text-decoration: none;
        display: inline-block;
    }

    .action-btn:hover {
        background: var(--accent-2);
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .action-btn.primary {
        border-color: var(--accent);
    }

    .action-btn.primary:hover {
        background: var(--accent);
    }
    
    /* Sections */
    .tarifs-section,
    .rdv-section,
    .contact-section {
        padding: 3rem 0;
        text-align: center;
        border-bottom: 1px solid var(--divider);
    }
    
    .tarifs-section h2,
    .rdv-section h2,
    .contact-section h2 {
        color: var(--accent);
        font-size: 2rem;
        margin-bottom: 1rem;
    }
    
    .tarifs-section p,
    .rdv-section p,
    .contact-section p {
        color: var(--text-muted);
        font-size: 1.1rem;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .services-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .os-grid {
            grid-template-columns: repeat(3, 1fr);
        }

        .action-buttons {
            flex-direction: column;
            align-items: center;
        }

        .action-btn-row {
            flex-direction: column;
            width: 100%;
            gap: 1rem;
        }

        .action-btn {
            width: 100%;
        }

        .main-title {
            font-size: 1.1rem;
        }

        .main-title .typing-line {
            font-size: 1.9rem;
        }

        .hero {
            height: auto;
            min-height: 60vh;
            padding: 2rem 0;
        }
    }

    @media (max-width: 480px) {
        .services-grid {
            grid-template-columns: 1fr;
        }

        .os-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .hero-content {
            padding: 0 1.2rem;
        }

        .hero-text {
            font-size: 1.2rem;
        }

        .main-title {
            font-size: .95rem;
        }

        .main-title .typing-line {
            font-size: 1.5rem;
        }
    }
</style>

<!-- Hero Section -->
<section class="hero" id="accueil">
    <div class="hero-content">
        <?php if ($is_logged_in): ?>
            <div class="hero-text" style="color: var(--accent-2); font-size: 1.2rem; margin-bottom: 1rem;">
                Bienvenue, <?php echo htmlspecialchars($_SESSION['user_email'] ?? 'Utilisateur'); ?> !
            </div>
        <?php endif; ?>
        <div class="hero-text">
            Nous réparons vos ordinateurs, téléphones, tablettes, écrans.
        </div>
        <div class="hero-cta">
            <a href="boutique.php" class="cta-primary">🛍️ Voir la boutique</a>
            <a href="rdv.php" class="cta-secondary">📅 Prendre rendez-vous</a>
        </div>
    </div>
</section>

<!-- Main Content -->
<main class="main-content">
    <h1 class="main-title">
        <span class="typing-line"><span id="typedWord"></span><span class="typing-caret">|</span></span><br>
        INFORMATIQUE ET TÉLÉPHONIE - TOUTES MARQUES<br>
        PRODUITS NEUFS - RECONDITIONNÉS - OCCASIONS
    </h1>
    
    <!-- Services -->
    <section id="vente">
    <div class="services-grid">
        <div class="service-card">
            <div class="service-icon">
                <img src="images/service-vente.png" alt="Vente" style="width: 60px; height: 60px;">
            </div>
            <div class="service-title">Vente</div>
        </div>
        
        <div class="service-card">
            <div class="service-icon">
                <img src="images/service-reparation.png" alt="Réparation" style="width: 60px; height: 60px;">
            </div>
            <div class="service-title">Réparation</div>
        </div>
        
        <div class="service-card">
            <div class="service-icon">
                <img src="images/service-ordinateur.png" alt="Ordinateur" style="width: 60px; height: 60px;">
            </div>
            <div class="service-title">Ordinateur</div>
        </div>
        
        <div class="service-card">
            <div class="service-icon">
                <img src="images/service-telephone.png" alt="Téléphone" style="width: 60px; height: 60px;">
            </div>
            <div class="service-title">Téléphone / Tablette</div>
        </div>
    </div>
    </section>

    <!-- Configurateur PC -->
    <section id="configurateur-teaser" style="background: linear-gradient(135deg, var(--surface) 0%, var(--surface-alt) 100%); border: 1px solid var(--divider); border-radius: var(--radius-lg); padding: 2.5rem 2rem; text-align: center; margin-bottom: 4rem; box-shadow: var(--shadow-md);">
        <h2 style="color: var(--accent); margin-bottom: .8rem; font-size: 1.6rem;">🖥️ Configurateur PC sur mesure</h2>
        <p style="color: var(--text-muted); max-width: 640px; margin: 0 auto 1.5rem; line-height: 1.6;">
            Assemblez votre PC composant par composant : le configurateur vérifie automatiquement la compatibilité
            (socket, mémoire, boîtier) et calcule le prix en temps réel. Envoyez-nous votre configuration pour un devis détaillé.
        </p>
        <a href="configurateur.php" class="action-btn primary">Lancer le configurateur</a>
    </section>

    <!-- OS Support -->
    <section id="reparations">
    <div class="os-grid">
        <div class="os-card">
            <div class="os-icon">
                <svg class="svg-inline--fa fa-windows fa-w-14" aria-hidden="true" data-fa-processed="" data-prefix="fab" data-icon="windows" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" style="width: 32px; height: 32px; color: #0078D4;">
                    <path fill="currentColor" d="M0 93.7l183.6-25.3v177.4H0V93.7zm0 324.6l183.6 25.3V268.4H0v149.9zm203.8 28L448 480V268.4H203.8v177.9zm0-380.6v180.1H448V32L203.8 65.7z"></path>
                </svg>
            </div>
            <div class="os-title">Windows</div>
        </div>
        <div class="os-card">
            <div class="os-icon">
                <svg class="svg-inline--fa fa-apple fa-w-14" aria-hidden="true" data-fa-processed="" data-prefix="fab" data-icon="apple" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" style="width: 32px; height: 32px; color: #A6A6A6;">
                    <path fill="currentColor" d="M247.2 137.6c-6.2 1.9-15.3 3.5-27.9 4.6 1.1-56.7 29.9-96.6 88-110.1 9.3 41.6-26.1 94.1-60.1 105.5zm121.3 72.7c6.4-9.4 16.6-19.9 30.6-31.7-22.3-27.6-48.1-44.3-85.1-44.3-35.4 0-65.2 18.2-87 18.2-18.5 0-51.9-16.1-84.5-16.1-69.6 0-106.5 68.1-106.5 139C36 354.2 95.7 480 156.2 480c23.8 0 45.2-18 73.5-18 29.3 0 52.8 17.2 80.3 17.2 46 0 88.6-77.5 102-119.7-46.8-14.3-84.4-90.2-43.5-149.2z"></path>
                </svg>
            </div>
            <div class="os-title">Apple</div>
        </div>
        <div class="os-card">
            <div class="os-icon">
                <svg class="svg-inline--fa fa-linux fa-w-14" aria-hidden="true" data-fa-processed="" data-prefix="fab" data-icon="linux" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" style="width: 32px; height: 32px; color: #F7931E;">
                    <path fill="currentColor" d="M196.1 123.6c-.2-1.4 1.9-2.3 3.2-2.9 1.7-.7 3.9-1 5.5-.1.4.2.8.7.6 1.1-.4 1.2-2.4 1-3.5 1.6-1 .5-1.8 1.7-3 1.7-1 .1-2.7-.4-2.8-1.4zm24.7-.3c1 .5 1.8 1.7 3 1.7 1.1 0 2.8-.4 2.9-1.5.2-1.4-1.9-2.3-3.2-2.9-1.7-.7-3.9-1-5.5-.1-.4.2-.8.7-.6 1.1.3 1.3 2.3 1.1 3.4 1.7zm214.7 310.2c-.5 8.2-6.5 13.8-13.9 18.3-14.9 9-37.3 15.8-50.9 32.2l-2.6-2.2 2.6 2.2c-14.2 16.9-31.7 26.6-48.3 27.9-16.5 1.3-32-6.3-40.3-23v-.1c-1.1-2.1-1.9-4.4-2.5-6.7-21.5 1.2-40.2-5.3-55.1-4.1-22 1.2-35.8 6.5-48.3 6.6-4.8 10.6-14.3 17.6-25.9 20.2-16 3.7-36.1 0-55.9-10.4l1.6-3-1.6 3c-18.5-9.8-42-8.9-59.3-12.5-8.7-1.8-16.3-5-20.1-12.3-3.7-7.3-3-17.3 2.2-31.7 1.7-5.1.4-12.7-.8-20.8-.6-3.9-1.2-7.9-1.2-11.8 0-4.3.7-8.5 2.8-12.4 4.5-8.5 11.8-12.1 18.5-14.5 6.7-2.4 12.8-4 17-8.3 5.2-5.5 10.1-14.4 16.6-20.2-2.6-17.2.2-35.4 6.2-53.3 12.6-37.9 39.2-74.2 58.1-96.7 16.1-22.9 20.8-41.3 22.5-64.7C158 103.4 132.4-.2 234.8 0c80.9.1 76.3 85.4 75.8 131.3-.3 30.1 16.3 50.5 33.4 72 15.2 18 35.1 44.3 46.5 74.4 9.3 24.6 12.9 51.8 3.7 79.1 1.4.5 2.8 1.2 4.1 2 1.4.8 2.7 1.8 4 2.9 6.6 5.6 8.7 14.3 10.5 22.4 1.9 8.1 3.6 15.7 7.2 19.7 11.1 12.4 15.9 21.5 15.5 29.7zM220.8 109.1c3.6.9 8.9 2.4 13 4.4-2.1-12.2 4.5-23.5 11.8-23 8.9.3 13.9 15.5 9.1 27.3-.8 1.9-2.8 3.4-3.9 4.6 6.7 2.3 11 4.1 12.6 4.9 7.9-9.5 10.8-26.2 4.3-40.4-9.8-21.4-34.2-21.8-44 .4-3.2 7.2-3.9 14.9-2.9 21.8zm-46.2 18.8c7.8-5.7 6.9-4.7 5.9-5.5-8-6.9-6.6-27.4 1.8-28.1 6.3-.5 10.8 10.7 9.6 19.6 3.1-2.1 6.7-3.6 10.2-4.6 1.7-19.3-9-33.5-19.1-33.5-18.9 0-24 37.5-8.4 52.1zm-9.4 20.9c1.5 4.9 6.1 10.5 14.7 15.3 7.8 4.6 12 11.5 20 15 2.6 1.1 5.7 1.9 9.6 2.1 18.4 1.1 27.1-11.3 38.2-14.9 11.7-3.7 20.1-11 22.7-18.1 3.2-8.5-2.1-14.7-10.5-18.2-11.3-4.9-16.3-5.2-22.6-9.3-10.3-6.6-18.8-8.9-25.9-8.9-14.4 0-23.2 9.8-27.9 14.2-.5.5-7.9 5.9-14.1 10.5-4.2 3.3-5.6 7.4-4.2 12.3zm-33.5 252.8L112.1 366c-6.8-9.2-13.8-14.8-21.9-16-7.7-1.2-12.6 1.4-17.7 6.9-4.8 5.1-8.8 12.3-14.3 18-7.8 6.5-9.3 6.2-19.6 9.9-6.3 2.2-11.3 4.6-14.8 11.3-2.7 5-2.1 12.2-.9 20 1.2 7.9 3 16.3.6 23.9v.2c-5 13.7-5 21.7-2.6 26.4 7.9 15.4 46.6 6.1 76.5 21.9 31.4 16.4 72.6 17.1 75.3-18 2.1-20.5-31.5-49-41-68.9zm153.9 35.8c3.2-11 6.3-21.3 6.8-29 .8-15.2 1.6-28.7 4.4-39.9 3.1-12.6 9.3-23.1 21.4-27.3 2.3-21.1 18.7-21.1 38.3-12.5 18.9 8.5 26 16 22.8 26.1 1 0 2-.1 4.2 0 5.2-16.9-14.3-28-30.7-34.8 2.9-12 2.4-24.1-.4-35.7-6-25.3-22.6-47.8-35.2-59-2.3-.1-2.1 1.9 2.6 6.5 11.6 10.7 37.1 49.2 23.3 84.9-3.9-1-7.6-1.5-10.9-1.4-5.3-29.1-17.5-53.2-23.6-64.6-11.5-21.4-29.5-65.3-37.2-95.7-4.5 6.4-12.4 11.9-22.3 15-4.7 1.5-9.7 5.5-15.9 9-13.9 8-30 8.8-42.4-1.2-4.5-3.6-8-7.6-12.6-10.3-1.6-.9-5.1-3.3-6.2-4.1-2 37.8-27.3 85.3-39.3 112.7-8.3 19.7-13.2 40.8-13.8 61.5-21.8-29.1-5.9-66.3 2.6-82.4 9.5-17.6 11-22.5 8.7-20.8-8.6 14-22 36.3-27.2 59.2-2.7 11.9-3.2 24 .3 35.2 3.5 11.2 11.1 21.5 24.6 29.9 0 0 24.8 14.3 38.3 32.5 7.4 10 9.7 18.7 7.4 24.9-2.5 6.7-9.6 8.9-16.7 8.9 4.8 6 10.3 13 14.4 19.6 37.6 25.7 82.2 15.7 114.3-7.2zM415 408.5c-10-11.3-7.2-33.1-17.1-41.6-6.9-6-13.6-5.4-22.6-5.1-7.7 8.8-25.8 19.6-38.4 16.3-11.5-2.9-18-16.3-18.8-29.5-.3.2-.7.3-1 .5-7.1 3.9-11.1 10.8-13.7 21.1-2.5 10.2-3.4 23.5-4.2 38.7-.7 11.8-6.2 26.4-9.9 40.6-3.5 13.2-5.8 25.2-1.1 36.3 7.2 14.5 19.5 20.4 33.7 19.3 14.2-1.1 30.4-9.8 43.6-25.5 22-26.6 62.3-29.7 63.2-46.5.3-5.1-3.1-13-13.7-24.6zM173.3 148.7c2 1.9 4.7 4.5 8 7.1 6.6 5.2 15.8 10.6 27.3 10.6 11.6 0 22.5-5.9 31.8-10.8 4.9-2.6 10.9-7 14.8-10.4 3.9-3.4 5.9-6.3 3.1-6.6-2.8-.3-2.6 2.6-6 5.1-4.4 3.2-9.7 7.4-13.9 9.8-7.4 4.2-19.5 10.2-29.9 10.2-10.4 0-18.7-4.8-24.9-9.7-3.1-2.5-5.7-5-7.7-6.9-1.5-1.4-1.9-4.6-4.3-4.9-1.4-.1-1.8 3.7 1.7 6.5z"></path>
                </svg>
            </div>
            <div class="os-title">Linux</div>
        </div>
        <div class="os-card">
            <div class="os-icon">
                <svg class="svg-inline--fa fa-android fa-w-14" aria-hidden="true" data-fa-processed="" data-prefix="fab" data-icon="android" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" style="width: 32px; height: 32px; color: #3DDC84;">
                    <path fill="currentColor" d="M89.6 204.5v115.8c0 15.4-12.1 27.7-27.5 27.7-15.3 0-30.1-12.4-30.1-27.7V204.5c0-15.1 14.8-27.5 30.1-27.5 15.1 0 27.5 12.4 27.5 27.5zm10.8 157c0 16.4 13.2 29.6 29.6 29.6h19.9l.3 61.1c0 36.9 55.2 36.6 55.2 0v-61.1h37.2v61.1c0 36.7 55.5 36.8 55.5 0v-61.1h20.2c16.2 0 29.4-13.2 29.4-29.6V182.1H100.4v179.4zm248-189.1H99.3c0-42.8 25.6-80 63.6-99.4l-19.1-35.3c-2.8-4.9 4.3-8 6.7-3.8l19.4 35.6c34.9-15.5 75-14.7 108.3 0L297.5 34c2.5-4.3 9.5-1.1 6.7 3.8L285.1 73c37.7 19.4 63.3 56.6 63.3 99.4zm-170.7-55.5c0-5.7-4.6-10.5-10.5-10.5-5.7 0-10.2 4.8-10.2 10.5s4.6 10.5 10.2 10.5c5.9 0 10.5-4.8 10.5-10.5zm113.4 0c0-5.7-4.6-10.5-10.2-10.5-5.9 0-10.5 4.8-10.5 10.5s4.6 10.5 10.5 10.5c5.6 0 10.2-4.8 10.2-10.5zm94.8 60.1c-15.1 0-27.5 12.1-27.5 27.5v115.8c0 15.4 12.4 27.7 27.5 27.7 15.4 0 30.1-12.4 30.1-27.7V204.5c0-15.4-14.8-27.5-30.1-27.5z"></path>
                </svg>
            </div>
            <div class="os-title">Android</div>
        </div>
        <div class="os-card">
            <div class="os-icon">
                <svg class="svg-inline--fa fa-app-store-ios fa-w-14" aria-hidden="true" data-fa-processed="" data-prefix="fab" data-icon="app-store-ios" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" style="width: 32px; height: 32px; color: #007AFF;">
                    <path fill="currentColor" d="M400 32H48C21.5 32 0 53.5 0 80v352c0 26.5 21.5 48 48 48h352c26.5 0 48-21.5 48-48V80c0-26.5-21.5-48-48-48zM127 384.5c-5.5 9.6-17.8 12.8-27.3 7.3-9.6-5.5-12.8-17.8-7.3-27.3l14.3-24.7c16.1-4.9 29.3-1.1 39.6 11.4L127 384.5zm138.9-53.9H84c-11 0-20-9-20-20s9-20 20-20h51l65.4-113.2-20.5-35.4c-5.5-9.6-2.2-21.8 7.3-27.3 9.6-5.5 21.8-2.2 27.3 7.3l8.9 15.4 8.9-15.4c5.5-9.6 17.8-12.8 27.3-7.3 9.6 5.5 12.8 17.8 7.3 27.3l-85.8 148.6h62.1c20.2 0 31.5 23.7 22.7 40zm98.1 0h-29l19.6 33.9c5.5 9.6 2.2 21.8-7.3 27.3-9.6 5.5-21.8 2.2-27.3-7.3-32.9-56.9-57.5-99.7-74-128.1-16.7-29-4.8-58 7.1-67.8 13.1 22.7 32.7 56.7 58.9 102h52c11 0 20 9 20 20 0 11.1-9 20-20 20z"></path>
                </svg>
            </div>
            <div class="os-title">iOS</div>
        </div>
    </div>
    </section>
    
    <!-- Tarifs -->
    <section id="tarifs">
    <div class="tarifs-section">
        <h2>Nos Tarifs</h2>
        <p>Tarifs compétitifs pour tous nos services de réparation et vente.</p>
    </div>
    </section>
    
    <!-- RDV -->
    <section id="rdv">
    <div class="rdv-section">
        <h2>Prendre un Rendez-vous</h2>
        <p>Réservez votre créneau pour une réparation ou un diagnostic.</p>
    </div>
    </section>
    
    <!-- Contact -->
    <section id="contact">
    <div class="contact-section">
        <h2>Contact</h2>
        <p>N'hésitez pas à nous contacter pour toute question.</p>
    </div>
    </section>
    
    <!-- Action Buttons -->
    <div class="action-buttons">
        <div class="action-btn-center">
            <a href="#contact" class="action-btn primary">Contactez-nous</a>
        </div>
        <div class="action-btn-row">
            <a href="rdv.php" class="action-btn">Demander un devis</a>
            <a href="#rdv" class="action-btn">Prendre un Rendez-vous</a>
        </div>
    </div>
    
    <script>
        // Navigation fluide
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Machine à écrire : tape puis efface chaque mot en boucle,
        // avec une vitesse légèrement aléatoire pour un rendu manuel.
        (function () {
            var words = ['Vente', 'Réparation'];
            var el = document.getElementById('typedWord');
            if (!el) {
                return;
            }

            var wordIndex = 0;
            var charIndex = 0;
            var deleting = false;

            function randDelay(min, max) {
                return min + Math.random() * (max - min);
            }

            function tick() {
                var currentWord = words[wordIndex];

                if (!deleting) {
                    charIndex++;
                    el.textContent = currentWord.slice(0, charIndex);
                    if (charIndex === currentWord.length) {
                        deleting = true;
                        setTimeout(tick, 1500);
                        return;
                    }
                    setTimeout(tick, randDelay(80, 180));
                } else {
                    charIndex--;
                    el.textContent = currentWord.slice(0, charIndex);
                    if (charIndex === 0) {
                        deleting = false;
                        wordIndex = (wordIndex + 1) % words.length;
                        setTimeout(tick, 400);
                        return;
                    }
                    setTimeout(tick, randDelay(35, 90));
                }
            }

            tick();
        })();
    </script>
</main>

<?php include 'footer.php'; ?>
