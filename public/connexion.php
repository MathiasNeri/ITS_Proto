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

if ($_POST) {
    if (!csrfVerify()) {
        $error = 'Session expirée, merci de réessayer.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Tous les champs sont requis';
        } elseif (rateLimitDepasse($email)) {
            $error = 'Trop de tentatives échouées. Merci de réessayer dans quelques minutes.';
        } else {
            try {
                $pdo = initDatabase();
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    enregistrerTentativeConnexion($email, true);
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    redirect($redirectTarget);
                } else {
                    enregistrerTentativeConnexion($email, false);
                    $error = 'Email ou mot de passe incorrect';
                }
            } catch (PDOException $e) {
                $error = 'Erreur de connexion';
                logError($e->getMessage());
            }
        }
    }
}
?>
<?php include 'header.php'; ?>

<style>
    .login-page {
        background: var(--bg);
        min-height: calc(100vh - 108px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem;
    }

    @media (max-width: 640px) {
        .login-page {
            min-height: calc(100vh - 96px);
        }
    }

    .login-container {
        background: var(--surface);
        padding: 2.2rem;
        border: 1px solid var(--divider);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-lg);
        width: 100%;
        max-width: 400px;
    }

    .login-title {
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
        border: 2px solid var(--surface-alt);
        border-radius: var(--radius-sm);
        background: var(--surface-alt);
        color: var(--text);
        font-size: 1rem;
        transition: border-color var(--ease);
    }

    .form-group input:focus {
        outline: none;
        border-color: var(--accent);
    }

    .btn-login {
        width: 100%;
        padding: 0.85rem;
        background: var(--accent);
        color: var(--text);
        border: none;
        border-radius: var(--radius-sm);
        font-size: 1rem;
        font-weight: bold;
        cursor: pointer;
        transition: background-color var(--ease), transform var(--ease), box-shadow var(--ease);
    }

    .btn-login:hover {
        background: var(--accent-hover);
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    .error {
        background: var(--accent);
        color: var(--text);
        padding: 1rem;
        border-radius: var(--radius-sm);
        margin-bottom: 1rem;
        text-align: center;
    }

    .register-link {
        text-align: center;
        margin-top: 1.4rem;
        padding-top: 1.2rem;
        border-top: 1px solid var(--divider);
    }

    .register-link p {
        margin-bottom: .5rem;
        color: var(--text-muted);
        font-size: .9rem;
    }

    .register-link p:last-child {
        margin-bottom: 0;
    }

    .register-link a {
        color: var(--accent-2);
        font-weight: bold;
        text-decoration: none;
    }

    .register-link a:hover {
        color: var(--accent-2-hover);
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
            <?php echo csrfField(); ?>
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirectTarget); ?>">
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
            <p><a href="mot-de-passe-oublie.php">Mot de passe oublié ?</a></p>
            <p>Pas encore de compte ? <a href="inscription.php?redirect=<?php echo urlencode($redirectTarget); ?>">S'inscrire</a></p>
        </div>
    </div>
</div>
</main>

<?php include 'footer.php'; ?>
