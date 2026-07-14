<?php
// Configuration générale et connexion base de données

$config = [
    'site_name'  => 'ITS',
    'site_title' => 'Informatique Téléphonie Service',
    // RENDER_EXTERNAL_URL est posée automatiquement par Render.com au
    // déploiement ; sinon on retombe sur BASE_URL si définie, puis le
    // localhost de dev. Aucune valeur à coder en dur pour déployer.
    'base_url'   => getenv('RENDER_EXTERNAL_URL') ?: (getenv('BASE_URL') ?: 'http://localhost:8000'),
    'db_path'    => 'sqlite:' . __DIR__ . '/../database/its.sqlite',

    // --- Paiement (Stripe) --------------------------------------------
    // Ce fichier est public sur GitHub : aucun secret n'y est écrit en
    // dur, tout se configure via variables d'environnement (dashboard
    // Render, ou export local avant de lancer php -S). Laisser vide =
    // mode simulation (paiement accepté automatiquement, aucun
    // encaissement réel). Clés de test gratuites sur
    // https://dashboard.stripe.com
    'stripe_secret_key'      => getenv('STRIPE_SECRET_KEY') ?: '',
    'stripe_publishable_key' => getenv('STRIPE_PUBLISHABLE_KEY') ?: '',
    'stripe_webhook_secret'  => getenv('STRIPE_WEBHOOK_SECRET') ?: '',

    // --- Email (SMTP) ---------------------------------------------------
    // Laisser vide = les emails sont journalisés dans la table emails_log
    // et consultables dans le panel admin, au lieu d'être réellement
    // envoyés. Renseigner via variables d'environnement pour un envoi
    // réel (Brevo, Gmail, OVH, etc).
    'smtp_host'     => getenv('SMTP_HOST') ?: '',
    'smtp_port'     => getenv('SMTP_PORT') ?: 587,
    'smtp_user'     => getenv('SMTP_USER') ?: '',
    'smtp_password' => getenv('SMTP_PASSWORD') ?: '',
    'smtp_from'     => getenv('SMTP_FROM') ?: 'contact@its-reparation.fr',
    'smtp_from_name' => 'ITS - Informatique Téléphonie Service',

    // --- Livraison --------------------------------------------------
    'frais_livraison_colissimo' => 5.90,
    'livraison_gratuite_des'    => 80,
];

/**
 * Vrai si la colonne existe déjà (utile pour les migrations légères en SQLite,
 * qui n'a pas de "ADD COLUMN IF NOT EXISTS").
 */
function columnExists(PDO $pdo, $table, $column) {
    $stmt = $pdo->query("PRAGMA table_info($table)");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        if ($col['name'] === $column) {
            return true;
        }
    }
    return false;
}

/**
 * Ouvre (et initialise si besoin) la base SQLite.
 * Crée les tables manquantes, migre le schéma et le compte admin par défaut.
 */
function initDatabase() {
    global $config;
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $pdo = new PDO($config['db_path']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON');

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nom TEXT NOT NULL,
        prenom TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'user',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS rdv (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nom TEXT NOT NULL,
        prenom TEXT NOT NULL,
        email TEXT NOT NULL,
        telephone TEXT NOT NULL,
        boutique TEXT NOT NULL,
        service TEXT NOT NULL,
        date_rdv TEXT NOT NULL,
        message TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS produits (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nom TEXT NOT NULL,
        categorie TEXT NOT NULL,
        icone TEXT NOT NULL DEFAULT '📦',
        prix REAL NOT NULL,
        prix_barre REAL,
        tag TEXT NOT NULL DEFAULT 'neuf',
        etoiles INTEGER NOT NULL DEFAULT 5,
        description TEXT NOT NULL DEFAULT '',
        stock INTEGER NOT NULL DEFAULT 0
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS commandes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        numero TEXT NOT NULL UNIQUE,
        user_id INTEGER,
        nom TEXT NOT NULL,
        email TEXT NOT NULL,
        adresse TEXT NOT NULL,
        total REAL NOT NULL,
        statut TEXT NOT NULL DEFAULT 'nouvelle',
        mode_livraison TEXT NOT NULL DEFAULT 'boutique',
        frais_livraison REAL NOT NULL DEFAULT 0,
        numero_suivi TEXT,
        stripe_session_id TEXT,
        code_promo TEXT,
        remise REAL NOT NULL DEFAULT 0,
        relance_envoyee INTEGER NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS commande_lignes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        commande_id INTEGER NOT NULL REFERENCES commandes(id),
        produit_id INTEGER,
        nom_produit TEXT NOT NULL,
        prix_unitaire REAL NOT NULL,
        quantite INTEGER NOT NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nom TEXT NOT NULL,
        email TEXT NOT NULL,
        sujet TEXT NOT NULL,
        message TEXT NOT NULL,
        lu INTEGER NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL REFERENCES users(id),
        token TEXT NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        used INTEGER NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        identifiant TEXT NOT NULL,
        ip TEXT NOT NULL,
        reussi INTEGER NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS codes_promo (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT NOT NULL UNIQUE,
        type TEXT NOT NULL DEFAULT 'pourcentage',
        valeur REAL NOT NULL,
        actif INTEGER NOT NULL DEFAULT 1,
        date_expiration TEXT,
        usage_max INTEGER,
        usage_compte INTEGER NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS avis (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        produit_id INTEGER NOT NULL REFERENCES produits(id),
        user_id INTEGER,
        nom TEXT NOT NULL,
        note INTEGER NOT NULL,
        commentaire TEXT NOT NULL DEFAULT '',
        approuve INTEGER NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS emails_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        destinataire TEXT NOT NULL,
        sujet TEXT NOT NULL,
        corps_html TEXT NOT NULL,
        statut TEXT NOT NULL DEFAULT 'journalise',
        erreur TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Migrations légères pour les bases créées avant l'ajout de ces colonnes
    if (!columnExists($pdo, 'produits', 'stock')) {
        $pdo->exec("ALTER TABLE produits ADD COLUMN stock INTEGER NOT NULL DEFAULT 0");
    }
    if (!columnExists($pdo, 'commandes', 'user_id')) {
        $pdo->exec("ALTER TABLE commandes ADD COLUMN user_id INTEGER");
    }
    if (!columnExists($pdo, 'commandes', 'statut')) {
        $pdo->exec("ALTER TABLE commandes ADD COLUMN statut TEXT NOT NULL DEFAULT 'nouvelle'");
    }
    if (!columnExists($pdo, 'commandes', 'mode_livraison')) {
        $pdo->exec("ALTER TABLE commandes ADD COLUMN mode_livraison TEXT NOT NULL DEFAULT 'boutique'");
    }
    if (!columnExists($pdo, 'commandes', 'frais_livraison')) {
        $pdo->exec("ALTER TABLE commandes ADD COLUMN frais_livraison REAL NOT NULL DEFAULT 0");
    }
    if (!columnExists($pdo, 'commandes', 'numero_suivi')) {
        $pdo->exec("ALTER TABLE commandes ADD COLUMN numero_suivi TEXT");
    }
    if (!columnExists($pdo, 'commandes', 'stripe_session_id')) {
        $pdo->exec("ALTER TABLE commandes ADD COLUMN stripe_session_id TEXT");
    }
    if (!columnExists($pdo, 'commandes', 'code_promo')) {
        $pdo->exec("ALTER TABLE commandes ADD COLUMN code_promo TEXT");
    }
    if (!columnExists($pdo, 'commandes', 'remise')) {
        $pdo->exec("ALTER TABLE commandes ADD COLUMN remise REAL NOT NULL DEFAULT 0");
    }
    if (!columnExists($pdo, 'commandes', 'relance_envoyee')) {
        $pdo->exec("ALTER TABLE commandes ADD COLUMN relance_envoyee INTEGER NOT NULL DEFAULT 0");
    }

    // Catalogue de démarrage (une seule fois)
    $stmt = $pdo->query('SELECT COUNT(*) FROM produits');
    if ((int) $stmt->fetchColumn() === 0) {
        $produits = [
            ['iPhone 13 128Go', 'tel', '📱', 349, 429, 'recond', 5, "iPhone 13 reconditionné grade A, batterie certifiée 90%+, garantie 12 mois.", 8],
            ['Samsung Galaxy S21', 'tel', '📱', 299, null, 'occasion', 4, "Galaxy S21 d'occasion, très bon état, contrôlé sur 40 points.", 5],
            ['MacBook Air M1', 'pc', '💻', 699, 849, 'recond', 5, "MacBook Air M1 8Go/256Go reconditionné, coque impeccable, garantie 12 mois.", 3],
            ['Asus VivoBook 15', 'pc', '💻', 449, null, 'neuf', 4, "PC portable neuf 15\", Ryzen 5, 8Go RAM, 512Go SSD. Idéal bureautique.", 6],
            ['iPad 9e génération', 'tab', '📲', 279, null, 'neuf', 5, "iPad 9e gén 64Go Wi-Fi, neuf sous blister, garantie constructeur.", 7],
            ['Samsung Tab A8', 'tab', '📲', 189, null, 'neuf', 4, "Tablette Samsung Tab A8 10.5\", 32Go, parfaite pour le streaming et la lecture.", 4],
            ['Écran iPhone 12 (pièce)', 'piece', '🔧', 39, null, 'neuf', 4, "Vitre + dalle OEM compatible iPhone 12, pose en atelier possible.", 15],
            ['Batterie Galaxy S20', 'piece', '🔋', 24, null, 'neuf', 4, "Batterie de remplacement compatible Galaxy S20, 4000mAh.", 12],
            ['Chargeur USB-C 20W', 'acc', '🔌', 14, 18, 'promo', 5, "Chargeur rapide USB-C 20W, compatible iPhone/Android/tablettes.", 25],
            ['Coque iPhone 13', 'acc', '🛡️', 9, 12, 'promo', 4, "Coque de protection silicone, intérieur microfibre, coloris variés.", 30],
            ['Écouteurs Bluetooth', 'acc', '🎧', 29, null, 'neuf', 4, "Écouteurs sans fil, autonomie 20h avec boîtier, étanches IPX4.", 10],
            ['SSD 1To interne', 'piece', '💾', 79, null, 'neuf', 5, "Disque SSD 1To SATA, installation possible en boutique en 15 min.", 0],
        ];
        $ins = $pdo->prepare('INSERT INTO produits (nom, categorie, icone, prix, prix_barre, tag, etoiles, description, stock) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($produits as $p) {
            $ins->execute($p);
        }
    }

    // Code promo de démonstration (une seule fois)
    $stmt = $pdo->query('SELECT COUNT(*) FROM codes_promo');
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->prepare('INSERT INTO codes_promo (code, type, valeur, actif) VALUES (?, ?, ?, 1)')
            ->execute(['BIENVENUE10', 'pourcentage', 10]);
    }

    // Compte administrateur par défaut (voir README.md)
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute(['admin@its-reparation.fr']);
    if (!$stmt->fetch()) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare('INSERT INTO users (nom, prenom, email, password, role) VALUES (?, ?, ?, ?, ?)')
            ->execute(['Admin', 'ITS', 'admin@its-reparation.fr', $hash, 'admin']);
    }

    return $pdo;
}

/**
 * Vrai si un utilisateur est actuellement connecté.
 */
function checkAuth() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_id']);
}

/**
 * Redirige vers une page du site et termine le script.
 */
function redirect($path) {
    header('Location: ' . $path);
    exit();
}

/**
 * Journalise une erreur backend (log PHP standard, jamais affiché au visiteur).
 */
function logError($message) {
    error_log('[ITS] ' . $message);
}

/**
 * Jeton anti-CSRF : un seul jeton par session, réutilisé sur tous les formulaires.
 */
function csrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Affiche le champ caché à inclure dans chaque <form method="POST">.
 */
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

/**
 * Vérifie le jeton reçu en POST. À appeler en tout début de traitement POST.
 */
function csrfVerify() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $token = $_POST['csrf_token'] ?? '';
    return !empty($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Adresse IP du visiteur (best effort, suffisant pour du rate limiting
 * anti brute-force, pas pour de la sécurité forte type ban IP).
 */
function clientIp() {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Vrai si l'identifiant (email) a dépassé le nombre d'essais de connexion
 * autorisés sur la fenêtre glissante définie. Protège contre le brute-force
 * sans bannir définitivement (la fenêtre expire toute seule).
 */
function rateLimitDepasse($identifiant, $maxEssais = 5, $fenetreMinutes = 15) {
    $pdo = initDatabase();
    $depuis = date('Y-m-d H:i:s', strtotime("-$fenetreMinutes minutes"));
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE identifiant = ? AND reussi = 0 AND created_at >= ?');
    $stmt->execute([strtolower($identifiant), $depuis]);
    return (int) $stmt->fetchColumn() >= $maxEssais;
}

/**
 * Journalise une tentative de connexion (réussie ou non).
 */
function enregistrerTentativeConnexion($identifiant, $reussi) {
    $pdo = initDatabase();
    $pdo->prepare('INSERT INTO login_attempts (identifiant, ip, reussi) VALUES (?, ?, ?)')
        ->execute([strtolower($identifiant), clientIp(), $reussi ? 1 : 0]);
}

/**
 * Vrai si des clés Stripe ont été configurées (sinon le site tourne en
 * mode simulation de paiement).
 */
function isStripeConfigured() {
    global $config;
    return !empty($config['stripe_secret_key']) && !empty($config['stripe_publishable_key']);
}

/**
 * Vrai si un serveur SMTP a été configuré (sinon les emails sont
 * journalisés en base au lieu d'être réellement envoyés).
 */
function isSmtpConfigured() {
    global $config;
    return !empty($config['smtp_host']) && !empty($config['smtp_user']);
}

require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/stripe.php';
require_once __DIR__ . '/commandes.php';

// La base doit exister dès le chargement de la config (ex: profil.php
// ouvre une connexion directe sans repasser par initDatabase()).
initDatabase();
