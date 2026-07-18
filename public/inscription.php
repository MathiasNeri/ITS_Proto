<?php
require_once __DIR__ . '/../backend/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$redirectTarget = safeRedirectTarget($_POST['redirect'] ?? $_GET['redirect'] ?? null);

// Si déjà connecté, rediriger vers l'accueil (ou la page demandée)
if (checkAuth()) {
    redirect($redirectTarget);
}

$error = '';
$success = '';

if ($_POST && !csrfVerify()) {
    $error = 'Session expirée, merci de réessayer.';
} elseif ($_POST) {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($nom) || empty($prenom) || empty($email) || empty($password)) {
        $error = 'Tous les champs sont obligatoires';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email invalide';
    } elseif (strlen($password) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères';
    } elseif ($password !== $confirm_password) {
        $error = 'Les mots de passe ne correspondent pas';
    } elseif (rateLimitDepasse('inscription:' . clientIp(), 5, 60)) {
        $error = 'Trop de tentatives d\'inscription depuis cette connexion. Merci de réessayer plus tard.';
    } else {
        try {
            $pdo = initDatabase();
            enregistrerTentativeConnexion('inscription:' . clientIp(), false);

            // Vérifier si l'email existe déjà
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Cet email est déjà utilisé';
            } else {
                // Créer l'utilisateur
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (nom, prenom, email, password, role) VALUES (?, ?, ?, ?, 'user')");
                $stmt->execute([$nom, $prenom, $email, $hashed_password]);

                $success = 'Compte créé avec succès ! Vous pouvez maintenant vous connecter.';
            }
        } catch (PDOException $e) {
            $error = 'Erreur lors de la création du compte';
            logError($e->getMessage());
        }
    }
}
?>
<?php include 'header.php'; ?>

<style>
    .register-page {
        background: var(--bg);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem;
    }
    
    .register-container {
        background: var(--surface);
        padding: 2rem;
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        width: 100%;
        max-width: 400px;
    }
    
    .register-title {
        text-align: center;
        margin-bottom: 2rem;
        color: var(--accent);
        font-size: 1.5rem;
    }
    
    .form-group {
        margin-bottom: 1rem;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        color: var(--text-muted);
        font-weight: bold;
    }
    
    .form-group input {
        width: 100%;
        padding: 0.8rem;
        border: 1px solid var(--surface-alt);
        border-radius: 5px;
        background: var(--surface-alt);
        color: var(--text);
        font-size: 1rem;
    }
    
    .form-group input:focus {
        outline: none;
        border-color: var(--accent);
    }
    
    .btn-register {
        width: 100%;
        padding: 0.8rem;
        background: var(--accent);
        color: var(--text);
        border: none;
        border-radius: 5px;
        font-size: 1rem;
        font-weight: bold;
        cursor: pointer;
        transition: background 0.3s;
    }
    
    .btn-register:hover {
        background: var(--accent-hover);
    }
    
    .error {
        background: var(--accent);
        color: var(--text);
        padding: 1rem;
        border-radius: 5px;
        margin-bottom: 1rem;
        text-align: center;
    }
    
    .success {
        background: var(--success);
        color: var(--text);
        padding: 1rem;
        border-radius: 5px;
        margin-bottom: 1rem;
        text-align: center;
    }
    
    .login-link {
        text-align: center;
        margin-top: 1rem;
    }
    
    .login-link a {
        color: var(--accent);
        text-decoration: none;
    }
    
    .login-link a:hover {
        text-decoration: underline;
    }
</style>

<main class="main-content">
<div class="register-page">
    <div class="register-container">
        <h2 class="register-title">Créer un compte</h2>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <?php echo csrfField(); ?>
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirectTarget); ?>">
            <div class="form-group">
                <label for="nom">Nom :</label>
                <input type="text" id="nom" name="nom" value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="prenom">Prénom :</label>
                <input type="text" id="prenom" name="prenom" value="<?php echo htmlspecialchars($_POST['prenom'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email :</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Mot de passe :</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirmer le mot de passe :</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <button type="submit" class="btn-register">Créer le compte</button>
        </form>
        
        <div class="login-link">
            <p>Déjà un compte ? <a href="connexion.php?redirect=<?php echo urlencode($redirectTarget); ?>">Se connecter</a></p>
        </div>
    </div>
</div>
</main>

<?php include 'footer.php'; ?>
