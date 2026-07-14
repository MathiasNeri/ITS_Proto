<?php
require_once __DIR__ . '/../backend/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (checkAuth()) {
    redirect('accueil.php');
}

$is_logged_in = false;
$user_role = '';
$pdo = initDatabase();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!csrfVerify()) {
        $error = 'Session expirée, merci de réessayer.';
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Merci de saisir une adresse email valide.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Message identique que le compte existe ou non : on ne révèle
        // jamais si une adresse email est inscrite ou pas.
        $success = 'Si un compte existe avec cet email, un lien de réinitialisation vient de lui être envoyé.';

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $pdo->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)')
                ->execute([$user['id'], $token, $expiresAt]);

            $lien = rtrim($config['base_url'], '/') . '/reinitialiser-mot-de-passe.php?token=' . $token;
            $contenu = '
                <p>Bonjour ' . htmlspecialchars($user['prenom']) . ',</p>
                <p>Vous avez demandé la réinitialisation de votre mot de passe. Ce lien est valable 1 heure :</p>
                <p><a href="' . htmlspecialchars($lien) . '" style="background:#e74c3c;color:#fff;padding:12px 20px;border-radius:6px;text-decoration:none;font-weight:bold;display:inline-block;">Réinitialiser mon mot de passe</a></p>
                <p>Si vous n\'êtes pas à l\'origine de cette demande, ignorez simplement cet email.</p>';
            sendMail($user['email'], 'Réinitialisation de votre mot de passe', emailTemplate('Mot de passe oublié', $contenu));
        }
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
        margin-bottom: 1rem;
        color: var(--accent);
        font-size: 1.4rem;
    }

    .auth-desc {
        text-align: center;
        color: var(--text-muted);
        font-size: .85rem;
        margin-bottom: 1.5rem;
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
            <h1 class="auth-title">Mot de passe oublié</h1>
            <p class="auth-desc">Saisissez votre email, nous vous enverrons un lien pour choisir un nouveau mot de passe.</p>

            <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <?php if ($success): ?><div class="success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

            <?php if (!$success): ?>
            <form method="POST">
                <?php echo csrfField(); ?>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <button type="submit" class="btn-primary">Envoyer le lien</button>
            </form>
            <?php endif; ?>

            <div class="back-link"><a href="connexion.php">&larr; Retour à la connexion</a></div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>
