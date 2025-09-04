<?php
// Vérification de l'authentification
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$is_logged_in = checkAuth();
$user_role = $_SESSION['user_role'] ?? '';

// Déterminer la page active
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $config['site_name']; ?> - <?php echo $config['site_title']; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Autour+One&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Autour One', display;
            background-color: var(--darkreader-background-f0f0f0, #202325);
            color: white;
            line-height: 1.6;
            min-height: 100vh;
            padding-top: 80px;
            scroll-behavior: smooth;
        }
        
        /* Header */
        .header {
            background-color: var(--darkreader-background-f0f0f0, #202325);
            padding: 1rem 0;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            border-bottom: 1px solid #333;
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }
        
        .logo {
            display: flex;
            align-items: center;
        }
        
        .logo-image {
            height: 100px;
            width: auto;
        }
        
        .nav-main {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .nav-item {
            color: white;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: bold;
            transition: color 0.3s;
        }
        
        .nav-item:hover {
            color: #e74c3c;
        }
        
        .nav-item.active {
            color: #3498db;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-link {
            color: white;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: bold;
            transition: color 0.3s;
        }
        
        .user-link:hover {
            color: #e74c3c;
        }
        
        .user-link.admin {
            color: #e74c3c;
        }
        
        .user-link.active {
            color: #3498db;
        }
        
        .main-content {
            margin-top: 120px;
            min-height: calc(100vh - 120px);
            padding-bottom: 2rem;
        }
        
        /* Footer */
        .footer {
            background: #1a1a1a;
            padding: 2rem 0;
            margin-top: 2rem;
            border-top: 1px solid #333;
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
            color: #bdc3c7;
        }
        
        .footer-links a {
            color: #3498db;
            text-decoration: none;
            margin: 0 0.5rem;
        }
        
        .footer-links a:hover {
            color: #e74c3c;
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
            color: #bdc3c7;
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
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <img src="images/logo-its.png" alt="ITS Logo" class="logo-image">
            </div>
            
            <nav class="nav-main">
                <a href="accueil.php" class="nav-item <?php echo ($current_page === 'accueil') ? 'active' : ''; ?>">🏠 ACCUEIL</a>
                <a href="vente.php" class="nav-item <?php echo ($current_page === 'vente') ? 'active' : ''; ?>">🛒 VENTE</a>
                <a href="reparations.php" class="nav-item <?php echo ($current_page === 'reparations') ? 'active' : ''; ?>">🔧 RÉPARATIONS</a>
                <a href="tarifs.php" class="nav-item <?php echo ($current_page === 'tarifs') ? 'active' : ''; ?>">🏷️ TARIFS</a>
                <a href="rdv.php" class="nav-item <?php echo ($current_page === 'rdv') ? 'active' : ''; ?>">📅 RDV</a>
                <a href="contact.php" class="nav-item <?php echo ($current_page === 'contact') ? 'active' : ''; ?>">@ CONTACT</a>
            </nav>
            
            <?php if ($is_logged_in): ?>
                <div class="user-menu">
                    <a href="profil.php" class="user-link <?php echo ($current_page === 'profil') ? 'active' : ''; ?>">PROFIL</a>
                    <?php if ($user_role === 'admin'): ?>
                        <a href="administration.php" class="user-link admin <?php echo ($current_page === 'administration') ? 'active' : ''; ?>">ADMIN</a>
                    <?php endif; ?>
                    <a href="logout.php" class="user-link">DÉCONNEXION</a>
                </div>
            <?php else: ?>
                <div class="user-menu">
                    <a href="connexion.php" class="user-link <?php echo ($current_page === 'connexion') ? 'active' : ''; ?>">CONNEXION</a>
                </div>
            <?php endif; ?>
        </div>
    </header>
