<?php
require_once '../backend/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si déjà connecté, rediriger vers l'accueil
if (checkAuth()) {
    redirect('accueil.php');
}

$error = '';
$success = '';

if ($_POST) {
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
    } else {
        try {
            $pdo = initDatabase();
            
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
        background: var(--darkreader-background-f0f0f0, #202325);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem;
    }
    
    .register-container {
        background: #2c3e50;
        padding: 2rem;
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        width: 100%;
        max-width: 400px;
    }
    
    .register-title {
        text-align: center;
        margin-bottom: 2rem;
        color: #e74c3c;
        font-size: 1.5rem;
    }
    
    .form-group {
        margin-bottom: 1rem;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        color: #bdc3c7;
        font-weight: bold;
    }
    
    .form-group input {
        width: 100%;
        padding: 0.8rem;
        border: 1px solid #34495e;
        border-radius: 5px;
        background: #34495e;
        color: white;
        font-size: 1rem;
    }
    
    .form-group input:focus {
        outline: none;
        border-color: #e74c3c;
    }
    
    .btn-register {
        width: 100%;
        padding: 0.8rem;
        background: #e74c3c;
        color: white;
        border: none;
        border-radius: 5px;
        font-size: 1rem;
        font-weight: bold;
        cursor: pointer;
        transition: background 0.3s;
    }
    
    .btn-register:hover {
        background: #c0392b;
    }
    
    .error {
        background: #e74c3c;
        color: white;
        padding: 1rem;
        border-radius: 5px;
        margin-bottom: 1rem;
        text-align: center;
    }
    
    .success {
        background: #27ae60;
        color: white;
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
        color: #e74c3c;
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
            <p>Déjà un compte ? <a href="connexion.php">Se connecter</a></p>
        </div>
    </div>
</div>
</main>

<?php include 'footer.php'; ?>
