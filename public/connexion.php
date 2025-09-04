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

if ($_POST) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Tous les champs sont requis';
    } else {
        try {
            $pdo = initDatabase();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                redirect('accueil.php');
            } else {
                $error = 'Email ou mot de passe incorrect';
            }
        } catch (PDOException $e) {
            $error = 'Erreur de connexion';
            logError($e->getMessage());
        }
    }
}
?>
<?php include 'header.php'; ?>

<style>
    .login-page {
        background: var(--darkreader-background-f0f0f0, #202325);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem;
    }
    
    .login-container {
        background: #2c3e50;
        padding: 2rem;
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        width: 100%;
        max-width: 400px;
    }
    
    .login-title {
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
    
    .btn-login {
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
    
    .btn-login:hover {
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
    
    .register-link {
        text-align: center;
        margin-top: 1rem;
    }
    
    .register-link a {
        color: #e74c3c;
        text-decoration: none;
    }
    
    .register-link a:hover {
        text-decoration: underline;
    }
</style>

<main class="main-content">
<div class="login-page">
    <div class="login-container">
        <h1 class="login-title">Connexion</h1>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="email">Email :</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Mot de passe :</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn-login">Se connecter</button>
        </form>
        
        <div class="register-link">
            <p>Pas encore de compte ? <a href="inscription.php">S'inscrire</a></p>
        </div>
    </div>
</div>
</main>

<?php include 'footer.php'; ?>
