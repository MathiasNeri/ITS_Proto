<?php
require_once '../backend/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (checkAuth()) {
    redirect('accueil.php');
}

$is_logged_in = false;
$user_role = '';
$pdo = initDatabase();

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$error = '';
$success = '';

$stmt = $pdo->prepare('SELECT * FROM password_resets WHERE token = ?');
$stmt->execute([$token]);
$reset = $stmt->fetch(PDO::FETCH_ASSOC);

$valide = $reset && !$reset['used'] && $reset['expires_at'] >= date('Y-m-d H:i:s');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valide) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!csrfVerify()) {
        $error = 'Session expirée, merci de réessayer.';
    } elseif (strlen($password) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères.';
    } elseif ($password !== $confirm) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$hash, $reset['user_id']]);
        $pdo->prepare('UPDATE password_resets SET used = 1 WHERE id = ?')->execute([$reset['id']]);
        $success = 'Votre mot de passe a été réinitialisé. Vous pouvez maintenant vous connecter.';
    }
}
?>
<?php include 'header.php'; ?>

<style>
    .auth-page {
        min-height: calc(100vh - 300px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem;
    }

    .auth-container {
        background: var(--surface);
        border: 2px solid var(--surface-alt);
        padding: 2rem;
        border-radius: 10px;
        width: 100%;
        max-width: 400px;
    }

    .auth-title {
        text-align: center;
        margin-bottom: 1.5rem;
        color: var(--accent);
        font-size: 1.4rem;
    }

    .form-group {
        margin-bottom: 1rem;
    }

    .form-group label {
        display: block;
        margin-bottom: .5rem;
        color: var(--text-muted);
        font-weight: bold;
    }

    .form-group input {
        width: 100%;
        padding: .8rem;
        border: 1px solid var(--surface-alt);
        border-radius: 5px;
        background: var(--surface-alt);
        color: var(--text);
        font-size: 1rem;
    }

    .btn-primary {
        width: 100%;
        padding: .8rem;
        background: var(--accent);
        color: #fff;
        border: none;
        border-radius: 5px;
        font-weight: bold;
        cursor: pointer;
    }

    .btn-primary:hover {
        background: var(--accent-hover);
    }

    .error, .success {
        padding: 1rem;
        border-radius: 5px;
        margin-bottom: 1rem;
        text-align: center;
        font-size: .88rem;
    }

    .error { background: var(--accent); color: #fff; }
    .success { background: var(--success); color: #fff; }

    .back-link {
        text-align: center;
        margin-top: 1rem;
    }

    .back-link a {
        color: var(--accent);
        text-decoration: none;
    }
</style>

<main class="main-content">
    <div class="auth-page">
        <div class="auth-container">
            <h1 class="auth-title">Nouveau mot de passe</h1>

            <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <?php if ($success): ?><div class="success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

            <?php if (!$valide && !$success): ?>
                <div class="error">Ce lien de réinitialisation est invalide ou a expiré.</div>
                <div class="back-link"><a href="mot-de-passe-oublie.php">Demander un nouveau lien</a></div>
            <?php elseif (!$success): ?>
                <form method="POST">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <div class="form-group">
                        <label for="password">Nouveau mot de passe</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirmer le mot de passe</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn-primary">Réinitialiser</button>
                </form>
            <?php else: ?>
                <div class="back-link"><a href="connexion.php">Se connecter &rarr;</a></div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>
