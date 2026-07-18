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
        vu INTEGER NOT NULL DEFAULT 0,
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

    $pdo->exec("CREATE TABLE IF NOT EXISTS devis (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        materiel TEXT NOT NULL,
        nom TEXT NOT NULL,
        prenom TEXT NOT NULL,
        adresse TEXT NOT NULL,
        code_postal TEXT NOT NULL,
        ville TEXT NOT NULL,
        email TEXT NOT NULL,
        telephone TEXT NOT NULL,
        boutique TEXT NOT NULL,
        message TEXT,
        fichier_nom TEXT,
        fichier_chemin TEXT,
        statut TEXT NOT NULL DEFAULT 'nouveau',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Composants pour le configurateur de PC sur mesure. socket/ram_type/
    // form_factor/wattage/power_draw servent au moteur de compatibilité
    // (voir configurateur.php) selon le type de composant.
    $pdo->exec("CREATE TABLE IF NOT EXISTS composants_pc (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        type TEXT NOT NULL,
        marque TEXT NOT NULL,
        nom TEXT NOT NULL,
        icone TEXT NOT NULL DEFAULT '🔧',
        prix REAL NOT NULL,
        description TEXT NOT NULL DEFAULT '',
        socket TEXT,
        ram_type TEXT,
        form_factor TEXT,
        wattage INTEGER,
        power_draw INTEGER,
        capacite TEXT,
        stock INTEGER NOT NULL DEFAULT 5,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Migrations légères pour les bases créées avant l'ajout de ces colonnes
    if (!columnExists($pdo, 'rdv', 'vu')) {
        $pdo->exec("ALTER TABLE rdv ADD COLUMN vu INTEGER NOT NULL DEFAULT 0");
    }
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

    // Catalogue du configurateur PC (une seule fois)
    $stmt = $pdo->query('SELECT COUNT(*) FROM composants_pc');
    if ((int) $stmt->fetchColumn() === 0) {
        // [type, marque, nom, icone, prix, description, socket, ram_type, form_factor, wattage, power_draw, capacite, stock]
        $composants = [
            // --- Processeurs ---
            ['cpu', 'AMD', 'Ryzen 5 5600', '🧠', 139, '6 coeurs / 12 threads, excellent rapport performance/prix en AM4.', 'AM4', 'DDR4', null, null, 65, null, 12],
            ['cpu', 'AMD', 'Ryzen 5 7600X', '🧠', 219, '6 coeurs / 12 threads, plateforme AM5 nouvelle génération.', 'AM5', 'DDR5', null, null, 105, null, 10],
            ['cpu', 'AMD', 'Ryzen 7 7800X3D', '🧠', 389, '8 coeurs / 16 threads, cache 3D vertical, référence gaming.', 'AM5', 'DDR5', null, null, 120, null, 8],
            ['cpu', 'AMD', 'Ryzen 9 7950X', '🧠', 549, '16 coeurs / 32 threads, pour montage vidéo et calcul intensif.', 'AM5', 'DDR5', null, null, 170, null, 5],
            ['cpu', 'Intel', 'Core i5-13400F', '🧠', 179, '10 coeurs, très bon compromis bureautique/gaming, sans iGPU.', 'LGA1700', 'DDR4', null, null, 65, null, 12],
            ['cpu', 'Intel', 'Core i5-13600K', '🧠', 299, '14 coeurs, débridé (overclockable), plateforme DDR5.', 'LGA1700', 'DDR5', null, null, 125, null, 9],
            ['cpu', 'Intel', 'Core i7-14700K', '🧠', 419, '20 coeurs, polyvalent haut de gamme.', 'LGA1700', 'DDR5', null, null, 125, null, 6],
            ['cpu', 'Intel', 'Core i9-14900K', '🧠', 589, '24 coeurs, le plus performant du catalogue Intel.', 'LGA1700', 'DDR5', null, null, 125, null, 4],

            // --- Cartes mères ---
            ['carte_mere', 'Gigabyte', 'B550 AORUS Elite', '🔌', 139, 'ATX, AM4, DDR4, robuste et évolutive.', 'AM4', 'DDR4', 'ATX', null, null, null, 8],
            ['carte_mere', 'ASRock', 'B450M Pro4', '🔌', 79, 'mATX, AM4, DDR4, entrée de gamme fiable.', 'AM4', 'DDR4', 'mATX', null, null, null, 8],
            ['carte_mere', 'ASUS', 'TUF Gaming B650-PLUS', '🔌', 189, 'ATX, AM5, DDR5, VRM renforcé.', 'AM5', 'DDR5', 'ATX', null, null, null, 8],
            ['carte_mere', 'MSI', 'PRO B650M-A', '🔌', 149, 'mATX, AM5, DDR5, compacte et complète.', 'AM5', 'DDR5', 'mATX', null, null, null, 8],
            ['carte_mere', 'ASUS', 'ROG Strix X670E-I', '🔌', 349, 'Mini-ITX, AM5, DDR5, pour PC compact haut de gamme.', 'AM5', 'DDR5', 'ITX', null, null, null, 4],
            ['carte_mere', 'ASUS', 'Prime B760-Plus D4', '🔌', 129, 'ATX, LGA1700, DDR4, plateforme Intel économique.', 'LGA1700', 'DDR4', 'ATX', null, null, null, 8],
            ['carte_mere', 'Gigabyte', 'B760M DS3H DDR4', '🔌', 99, 'mATX, LGA1700, DDR4, très bon rapport qualité/prix.', 'LGA1700', 'DDR4', 'mATX', null, null, null, 8],
            ['carte_mere', 'MSI', 'MAG Z790 Tomahawk', '🔌', 279, 'ATX, LGA1700, DDR5, pour CPU débridés (overclocking).', 'LGA1700', 'DDR5', 'ATX', null, null, null, 6],
            ['carte_mere', 'ASUS', 'ROG Strix Z790-I', '🔌', 399, 'Mini-ITX, LGA1700, DDR5, format compact premium.', 'LGA1700', 'DDR5', 'ITX', null, null, null, 4],

            // --- Mémoire vive ---
            ['ram', 'Corsair', 'Vengeance LPX 16 Go (2x8) 3200MHz', '🧩', 39, 'DDR4, kit dual channel, dissipateur bas profil.', null, 'DDR4', null, null, null, '16 Go', 15],
            ['ram', 'Corsair', 'Vengeance LPX 32 Go (2x16) 3200MHz', '🧩', 74, 'DDR4, kit dual channel, idéal multitâche.', null, 'DDR4', null, null, null, '32 Go', 12],
            ['ram', 'Kingston', 'Fury Beast 16 Go (2x8) 3600MHz', '🧩', 45, 'DDR4, fréquence élevée pour gaming.', null, 'DDR4', null, null, null, '16 Go', 10],
            ['ram', 'Corsair', 'Vengeance 16 Go (2x8) 5600MHz', '🧩', 59, 'DDR5, kit dual channel nouvelle génération.', null, 'DDR5', null, null, null, '16 Go', 14],
            ['ram', 'Corsair', 'Vengeance 32 Go (2x16) 6000MHz', '🧩', 109, 'DDR5, référence gaming/création.', null, 'DDR5', null, null, null, '32 Go', 12],
            ['ram', 'G.Skill', 'Trident Z5 32 Go (2x16) 6400MHz', '🧩', 129, 'DDR5 haute fréquence, RGB.', null, 'DDR5', null, null, null, '32 Go', 8],
            ['ram', 'Corsair', 'Vengeance 64 Go (2x32) 5600MHz', '🧩', 219, 'DDR5, gros volume pour montage vidéo/3D.', null, 'DDR5', null, null, null, '64 Go', 5],

            // --- Cartes graphiques ---
            ['gpu', '—', 'Sans carte graphique dédiée (iGPU)', '🎮', 0, 'Utilise le circuit graphique intégré du processeur (si disponible).', null, null, null, null, 0, null, 999],
            ['gpu', 'AMD', 'Radeon RX 7600', '🎮', 289, '8 Go VRAM, 1080p fluide.', null, null, null, null, 165, '8 Go VRAM', 8],
            ['gpu', 'NVIDIA', 'GeForce RTX 4060', '🎮', 319, '8 Go VRAM, très bon rapport prix/1080p-1440p.', null, null, null, null, 115, '8 Go VRAM', 10],
            ['gpu', 'NVIDIA', 'GeForce RTX 4060 Ti', '🎮', 429, '8 Go VRAM, confortable en 1440p.', null, null, null, null, 160, '8 Go VRAM', 8],
            ['gpu', 'AMD', 'Radeon RX 7800 XT', '🎮', 549, '16 Go VRAM, excellent en 1440p.', null, null, null, null, 263, '16 Go VRAM', 6],
            ['gpu', 'NVIDIA', 'GeForce RTX 4070 Super', '🎮', 649, '12 Go VRAM, très polyvalente 1440p/4K.', null, null, null, null, 220, '12 Go VRAM', 6],
            ['gpu', 'NVIDIA', 'GeForce RTX 4070 Ti Super', '🎮', 899, '16 Go VRAM, 4K confortable.', null, null, null, null, 285, '16 Go VRAM', 4],
            ['gpu', 'NVIDIA', 'GeForce RTX 4080 Super', '🎮', 1149, '16 Go VRAM, haut de gamme 4K.', null, null, null, null, 320, '16 Go VRAM', 3],

            // --- Stockage ---
            ['stockage', 'Crucial', 'SSD SATA 1 To MX500', '💾', 59, 'SSD SATA fiable, bon rapport capacité/prix.', null, null, null, null, null, '1 To', 15],
            ['stockage', 'Samsung', 'SSD NVMe 980 500 Go', '💾', 39, 'NVMe rapide pour système d\'exploitation.', null, null, null, null, null, '500 Go', 15],
            ['stockage', 'Samsung', 'SSD NVMe 990 Pro 1 To', '💾', 89, 'NVMe haut de gamme, très rapide.', null, null, null, null, null, '1 To', 10],
            ['stockage', 'WD', 'SSD NVMe Black SN850X 2 To', '💾', 159, 'NVMe très haut de gamme, gaming/création.', null, null, null, null, null, '2 To', 6],
            ['stockage', 'Seagate', 'HDD Barracuda 2 To', '💾', 54, 'Disque dur pour stockage de masse économique.', null, null, null, null, null, '2 To', 10],
            ['stockage', 'WD', 'HDD Blue 4 To', '💾', 89, 'Disque dur grande capacité pour archivage/médias.', null, null, null, null, null, '4 To', 8],

            // --- Alimentations ---
            ['alimentation', 'Corsair', 'CV550 550W 80+ Bronze', '🔋', 54, 'Alimentation pour configuration sans carte graphique dédiée ou d\'entrée de gamme.', null, null, null, 550, null, null, 10],
            ['alimentation', 'Corsair', 'RM650 650W 80+ Gold', '🔋', 79, 'Alimentation semi-modulaire, bon rendement.', null, null, null, 650, null, null, 10],
            ['alimentation', 'Corsair', 'RM750 750W 80+ Gold', '🔋', 99, 'Pour configurations gaming milieu/haut de gamme.', null, null, null, 750, null, null, 10],
            ['alimentation', 'be quiet!', 'Straight Power 850W 80+ Gold', '🔋', 129, 'Pour cartes graphiques haut de gamme, très silencieuse.', null, null, null, 850, null, null, 6],
            ['alimentation', 'Corsair', 'HX1000 1000W 80+ Platinum', '🔋', 179, 'Pour configurations très haut de gamme.', null, null, null, 1000, null, null, 4],

            // --- Boîtiers ---
            ['boitier', 'NZXT', 'Mini-ITX Compact', '🖥️', 69, 'Boîtier compact pour carte mère Mini-ITX uniquement.', null, null, 'ITX', null, null, null, 8],
            ['boitier', 'Fractal Design', 'Meshify Compact mATX', '🖥️', 79, 'Boîtier compact bien ventilé, mATX/ITX.', null, null, 'mATX,ITX', null, null, null, 8],
            ['boitier', 'NZXT', 'H5 Flow ATX Gaming', '🖥️', 89, 'Boîtier ATX aéré avec panneau vitré RGB.', null, null, 'ATX,mATX,ITX', null, null, null, 10],
            ['boitier', 'be quiet!', 'Pure Base 500DX Silencieux', '🖥️', 129, 'Boîtier ATX silencieux, excellente ventilation.', null, null, 'ATX,mATX,ITX', null, null, null, 8],
            ['boitier', 'Fractal Design', 'Define 7 Full Tower', '🖥️', 159, 'Grand boîtier ATX, nombreux emplacements de stockage.', null, null, 'ATX,mATX,ITX', null, null, null, 5],

            // --- Refroidissement ---
            ['refroidissement', '—', 'Ventirad d\'origine', '❄️', 0, 'Ventirad fourni avec le processeur, refroidissement standard.', 'universel', null, null, null, null, null, 999],
            ['refroidissement', 'Cooler Master', 'Hyper 212 (tour 120mm)', '❄️', 29, 'Ventirad tour, bon compromis silence/performance.', 'universel', null, null, null, null, null, 10],
            ['refroidissement', 'Noctua', 'NH-U12S (tour 120mm haut de gamme)', '❄️', 59, 'Ventirad tour premium, très silencieux.', 'universel', null, null, null, null, null, 6],
            ['refroidissement', 'Corsair', 'iCUE 240mm AIO', '❄️', 89, 'Watercooling tout-en-un 240mm.', 'universel', null, null, null, null, null, 6],
            ['refroidissement', 'Corsair', 'iCUE 360mm AIO', '❄️', 129, 'Watercooling tout-en-un 360mm, pour CPU haut de gamme.', 'universel', null, null, null, null, null, 4],

            // --- Système d'exploitation ---
            ['os', 'Microsoft', 'Windows 11 Famille (OEM)', '💽', 109, 'Licence OEM, préinstallation en boutique possible.', null, null, null, null, null, null, 999],
            ['os', 'Microsoft', 'Windows 11 Pro (OEM)', '💽', 149, 'Licence OEM Pro, chiffrement BitLocker et Bureau à distance.', null, null, null, null, null, null, 999],
            ['os', '—', 'Sans système d\'exploitation', '💽', 0, 'Vous installez vous-même votre OS (Linux, licence existante...).', null, null, null, null, null, null, 999],
        ];
        $ins = $pdo->prepare('INSERT INTO composants_pc (type, marque, nom, icone, prix, description, socket, ram_type, form_factor, wattage, power_draw, capacite, stock) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($composants as $c) {
            $ins->execute($c);
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
 * Valide une cible de redirection post-connexion venant de l'utilisateur
 * (paramètre ?redirect=) : n'autorise qu'un nom de fichier .php local, pour
 * éviter toute redirection ouverte vers un autre site.
 */
function safeRedirectTarget($value, $default = 'accueil.php') {
    if (is_string($value) && preg_match('/^[a-zA-Z0-9_-]+\.php$/', $value)) {
        return $value;
    }
    return $default;
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
require_once __DIR__ . '/devis.php';

// La base doit exister dès le chargement de la config (ex: profil.php
// ouvre une connexion directe sans repasser par initDatabase()).
initDatabase();
