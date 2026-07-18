<?php
// Vérification de l'authentification
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$is_logged_in = checkAuth();
$user_role = $_SESSION['user_role'] ?? '';

// Déterminer la page active
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Nombre d'articles dans le panier (session)
$cart_count = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cart_count = array_sum($_SESSION['cart']);
}

// Notification admin : rendez-vous / demandes de devis / messages en attente
$admin_notif_count = 0;
if ($is_logged_in && $user_role === 'admin') {
    try {
        $pdoNotif = initDatabase();
        $admin_notif_count = (int) $pdoNotif->query("SELECT
            (SELECT COUNT(*) FROM rdv WHERE vu = 0) +
            (SELECT COUNT(*) FROM devis WHERE statut = 'nouveau') +
            (SELECT COUNT(*) FROM messages WHERE lu = 0)
        ")->fetchColumn();
    } catch (PDOException $e) {
        $admin_notif_count = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $config['site_name']; ?> - <?php echo $config['site_title']; ?></title>
    <link rel="icon" type="image/png" href="images/logo-its.png">
    <link href="https://fonts.googleapis.com/css2?family=Autour+One&display=swap" rel="stylesheet">
    <script>
        // Applique le thème mémorisé avant le premier rendu (évite le flash)
        (function () {
            var saved = localStorage.getItem('its_theme');
            if (saved === 'light' || saved === 'dark') {
                document.documentElement.setAttribute('data-theme', saved);
            }
        })();
    </script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --bg: #202325;
            --surface: #2c3e50;
            --surface-alt: #34495e;
            --surface-deep: #1a1a1a;
            --text: #ffffff;
            --text-muted: #bdc3c7;
            --accent: #e74c3c;
            --accent-hover: #c0392b;
            --accent-2: #3498db;
            --accent-2-hover: #2980b9;
            --success: #27ae60;
            --divider: #333333;
        }

        :root[data-theme="light"] {
            --bg: #f4f6f7;
            --surface: #ffffff;
            --surface-alt: #eef1f3;
            --surface-deep: #e4e8ea;
            --text: #1c2226;
            --text-muted: #55606a;
            --divider: #dfe4e6;
        }

        body {
            font-family: 'Autour One', display;
            background-color: var(--bg);
            color: var(--text);
            line-height: 1.6;
            min-height: 100vh;
            padding-top: 108px;
            scroll-behavior: smooth;
            transition: background-color .2s ease, color .2s ease;
        }

        /* Header */
        .header {
            background-color: var(--bg);
            padding: 1.3rem 0;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            border-bottom: 1px solid var(--divider);
            transition: background-color .2s ease, border-color .2s ease;
        }

        .header-content {
            max-width: 1340px;
            margin: 0 auto;
            padding: 0 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        .logo {
            display: flex;
            align-items: center;
            flex-shrink: 0;
            text-decoration: none;
            transition: opacity .2s ease;
        }

        .logo:hover {
            opacity: .85;
        }

        .logo-image {
            height: 64px;
            width: auto;
        }

        .nav-main {
            display: flex;
            align-items: center;
            gap: 1.15rem;
            flex-wrap: wrap;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: .32rem;
            color: var(--text);
            text-decoration: none;
            font-size: .88rem;
            font-weight: bold;
            letter-spacing: .2px;
            transition: color 0.3s;
            white-space: nowrap;
        }

        .nav-item svg {
            width: 15px;
            height: 15px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
            flex-shrink: 0;
        }

        .nav-item .at-symbol {
            font-size: 1rem;
            line-height: 1;
        }

        .nav-item:hover {
            color: var(--accent);
        }

        .nav-item.active {
            color: var(--accent-2);
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: .9rem;
            flex-shrink: 0;
        }

        .action-icons {
            display: flex;
            align-items: center;
            gap: .55rem;
        }

        .icon-btn {
            position: relative;
            background: none;
            border: 1px solid var(--divider);
            border-radius: 50%;
            width: 34px;
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--text);
            text-decoration: none;
            transition: border-color .2s ease, transform .15s ease;
        }

        .icon-btn:hover {
            border-color: var(--accent);
            transform: translateY(-1px);
        }

        .icon-btn svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
        }

        .icon-btn .toggle-glyph {
            font-size: .9rem;
            line-height: 1;
        }

        .cart-badge {
            position: absolute;
            top: -5px;
            right: -6px;
            background: var(--accent);
            color: #fff;
            font-size: .62rem;
            font-weight: 800;
            min-width: 16px;
            height: 16px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 3px;
            line-height: 1;
        }

        .menu-divider {
            width: 1px;
            height: 22px;
            background: var(--divider);
        }

        .auth-links {
            display: flex;
            align-items: center;
            gap: .9rem;
        }

        .user-link {
            color: var(--text);
            text-decoration: none;
            font-size: .88rem;
            font-weight: bold;
            letter-spacing: .3px;
            white-space: nowrap;
            transition: color 0.3s;
        }

        .user-link:hover {
            color: var(--accent);
        }

        .user-link.admin {
            color: var(--accent);
        }

        .admin-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 16px;
            height: 16px;
            margin-left: .3rem;
            padding: 0 4px;
            border-radius: 9px;
            background: var(--accent);
            color: #fff;
            font-size: .62rem;
            font-weight: 800;
            line-height: 1;
        }

        .user-link.active {
            color: var(--accent-2);
        }

        .main-content {
            margin-top: 108px;
            min-height: calc(100vh - 108px);
            padding-bottom: 2rem;
        }

        /* Footer */
        .footer {
            background: var(--surface-deep);
            padding: 2rem 0;
            margin-top: 2rem;
            border-top: 1px solid var(--divider);
            transition: background-color .2s ease, border-color .2s ease;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 2rem;
        }

        .footer-links {
            color: var(--text-muted);
        }

        .footer-links a {
            color: var(--accent-2);
            text-decoration: none;
            margin: 0 0.5rem;
        }

        .footer-links a:hover {
            color: var(--accent);
        }

        .social-locations {
            display: flex;
            gap: 2rem;
        }

        .location {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .social-icons {
            display: flex;
            gap: 0.5rem;
        }

        .location-name {
            color: var(--text-muted);
            font-weight: bold;
        }

        @media (max-width: 768px) {
            .footer-content {
                flex-direction: column;
                text-align: center;
            }

            .social-locations {
                flex-direction: column;
                gap: 1rem;
            }
        }

        .hamburger-btn {
            display: none;
        }

        /* En dessous de 1024px, la nav passe systématiquement dans le menu
           hamburger : une nav qui se replie sur 2-3 lignes selon la largeur
           donne une hauteur de header imprévisible et fragile. */
        @media (max-width: 1024px) {
            .hamburger-btn {
                display: flex;
            }

            .nav-main {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: var(--bg);
                border-top: none;
                border-bottom: 1px solid var(--divider);
                flex-direction: column;
                align-items: flex-start;
                padding: 1rem 1.5rem 1.2rem;
                margin-top: 0;
                gap: .9rem;
                box-shadow: 0 12px 20px rgba(0, 0, 0, .15);
            }

            .nav-main.open {
                display: flex;
            }

            .nav-item {
                font-size: 1rem;
                width: 100%;
            }

            body {
                padding-top: 108px;
            }

            .main-content {
                margin-top: 108px;
                min-height: calc(100vh - 108px);
            }
        }

        /* En dessous de 640px, le trio logo + hamburger + icônes/compte ne
           tient plus sur une seule ligne à taille normale : on resserre tout
           et, en dernier recours, on autorise le retour à la ligne plutôt
           que de laisser le contenu déborder hors de l'écran. */
        @media (max-width: 640px) {
            .header {
                padding: .85rem 0;
            }

            .header-content {
                padding: 0 1rem;
                gap: .6rem;
                flex-wrap: wrap;
                row-gap: .6rem;
            }

            .logo-image {
                height: 42px;
            }

            .hamburger-btn {
                width: 30px;
                height: 30px;
            }

            .hamburger-btn svg {
                width: 15px;
                height: 15px;
            }

            .user-menu {
                gap: .6rem;
            }

            .action-icons {
                gap: .4rem;
            }

            .icon-btn {
                width: 30px;
                height: 30px;
            }

            .user-link {
                font-size: .78rem;
            }

            .auth-links {
                gap: .6rem;
            }

            body {
                padding-top: 96px;
            }

            .main-content {
                margin-top: 96px;
                min-height: calc(100vh - 96px);
            }
        }

        @media (max-width: 360px) {
            .logo-image {
                height: 36px;
            }

            .menu-divider {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <a href="accueil.php" class="logo" aria-label="Retour à l'accueil ITS">
                <img src="images/logo-its.png" alt="ITS Logo" class="logo-image">
            </a>

            <button type="button" class="icon-btn hamburger-btn" id="mobileMenuBtn" aria-label="Ouvrir le menu" aria-expanded="false">
                <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>

            <nav class="nav-main" id="mainNav">
                <a href="accueil.php" class="nav-item <?php echo ($current_page === 'accueil') ? 'active' : ''; ?>">
                    <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    Accueil
                </a>
                <a href="boutique.php" class="nav-item <?php echo ($current_page === 'boutique') ? 'active' : ''; ?>">
                    <svg viewBox="0 0 24 24"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/></svg>
                    Boutique
                </a>
                <a href="reparations.php" class="nav-item <?php echo ($current_page === 'reparations') ? 'active' : ''; ?>">
                    <svg viewBox="0 0 24 24"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                    Réparations
                </a>
                <a href="tarifs.php" class="nav-item <?php echo ($current_page === 'tarifs') ? 'active' : ''; ?>">
                    <svg viewBox="0 0 24 24"><path d="M20.59 13.41L13.42 20.58a2 2 0 0 1-2.83 0L2.59 12.58a2 2 0 0 1 0-2.83L9.76 2.58A2 2 0 0 1 11.17 2H18a2 2 0 0 1 2 2v6.83a2 2 0 0 1-.59 1.41z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                    Tarifs
                </a>
                <a href="rdv.php" class="nav-item <?php echo ($current_page === 'rdv') ? 'active' : ''; ?>">
                    <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    RDV
                </a>
                <a href="contact.php" class="nav-item <?php echo ($current_page === 'contact') ? 'active' : ''; ?>">
                    <span class="at-symbol">@</span>
                    Contact
                </a>
            </nav>

            <div class="user-menu">
                <div class="action-icons">
                    <button type="button" class="icon-btn" onclick="itsToggleTheme()" aria-label="Changer de thème">
                        <span class="toggle-glyph" id="themeToggleIcon">☀️</span>
                    </button>

                    <a href="panier.php" class="icon-btn" aria-label="Panier<?php echo $cart_count > 0 ? ' (' . $cart_count . ' article' . ($cart_count > 1 ? 's' : '') . ')' : ''; ?>">
                        <svg viewBox="0 0 24 24"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/></svg>
                        <?php if ($cart_count > 0): ?><span class="cart-badge"><?php echo $cart_count; ?></span><?php endif; ?>
                    </a>
                </div>

                <span class="menu-divider"></span>

                <div class="auth-links">
                    <?php if ($is_logged_in): ?>
                        <a href="profil.php" class="user-link <?php echo ($current_page === 'profil') ? 'active' : ''; ?>">Profil</a>
                        <?php if ($user_role === 'admin'): ?>
                            <a href="administration.php" class="user-link admin <?php echo ($current_page === 'administration') ? 'active' : ''; ?>">Admin<?php if ($admin_notif_count > 0): ?><span class="admin-badge"><?php echo $admin_notif_count; ?></span><?php endif; ?></a>
                        <?php endif; ?>
                        <a href="logout.php" class="user-link">Déconnexion</a>
                    <?php else: ?>
                        <a href="connexion.php" class="user-link <?php echo ($current_page === 'connexion') ? 'active' : ''; ?>">Connexion</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <script>
        function itsToggleTheme() {
            var root = document.documentElement;
            var current = root.getAttribute('data-theme') || 'dark';
            var next = current === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', next);
            localStorage.setItem('its_theme', next);
            var icon = document.getElementById('themeToggleIcon');
            if (icon) icon.textContent = next === 'dark' ? '☀️' : '🌙';
        }
        document.addEventListener('DOMContentLoaded', function () {
            var current = document.documentElement.getAttribute('data-theme') || 'dark';
            var icon = document.getElementById('themeToggleIcon');
            if (icon) icon.textContent = current === 'dark' ? '☀️' : '🌙';

            var menuBtn = document.getElementById('mobileMenuBtn');
            var nav = document.getElementById('mainNav');
            if (menuBtn && nav) {
                menuBtn.addEventListener('click', function () {
                    var isOpen = nav.classList.toggle('open');
                    menuBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                });
                nav.querySelectorAll('a').forEach(function (link) {
                    link.addEventListener('click', function () {
                        nav.classList.remove('open');
                        menuBtn.setAttribute('aria-expanded', 'false');
                    });
                });
            }
        });
    </script>
