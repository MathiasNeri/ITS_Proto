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
    $erreurPassword = erreurMotDePasse($password);

    // Validation
    if (empty($nom) || empty($prenom) || empty($email) || empty($password)) {
        $error = 'Tous les champs sont obligatoires';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email invalide';
    } elseif ($erreurPassword) {
        $error = $erreurPassword;
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

$page_noindex = true;
?>
<?php include 'header.php'; ?>

<style>
    .register-page {
        background: var(--bg);
        min-height: calc(100vh - 108px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem;
    }

    @media (max-width: 640px) {
        .register-page {
            min-height: calc(100vh - 96px);
        }
    }

    .register-container {
        background: var(--surface);
        padding: 2.2rem;
        border: 1px solid var(--divider);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-lg);
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

    .btn-register {
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

    .btn-register:hover {
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

    .success {
        background: var(--success);
        color: var(--text);
        padding: 1rem;
        border-radius: var(--radius-sm);
        margin-bottom: 1rem;
        text-align: center;
    }

    .login-link {
        text-align: center;
        margin-top: 1.4rem;
        padding-top: 1.2rem;
        border-top: 1px solid var(--divider);
    }

    .login-link a {
        color: var(--accent-2);
        font-weight: bold;
        text-decoration: none;
    }

    .login-link a:hover {
        color: var(--accent-2-hover);
        text-decoration: underline;
    }

    .password-strength {
        margin-top: .5rem;
    }

    .password-strength-bar {
        height: 5px;
        border-radius: 3px;
        background: var(--surface-alt);
        overflow: hidden;
    }

    .password-strength-bar span {
        display: block;
        height: 100%;
        width: 0%;
        background: var(--accent);
        transition: width var(--ease, .2s ease), background-color var(--ease, .2s ease);
    }

    .password-strength-label {
        display: block;
        margin-top: .35rem;
        font-size: .76rem;
        color: var(--text-muted);
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
                <input type="password" id="password" name="password" minlength="8" pattern="(?=.*[A-Za-z])(?=.*\d).{8,}" title="Au moins 8 caractères, avec au moins une lettre et un chiffre" required>
                <div class="password-strength" id="passwordStrength">
                    <div class="password-strength-bar"><span id="passwordStrengthFill"></span></div>
                    <span class="password-strength-label" id="passwordStrengthLabel">Au moins 8 caractères, avec une lettre et un chiffre</span>
                </div>
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

<script>
    (function () {
        var input = document.getElementById('password');
        var fill = document.getElementById('passwordStrengthFill');
        var label = document.getElementById('passwordStrengthLabel');
        if (!input || !fill || !label) return;

        function evaluer(mdp) {
            if (!mdp) return { pct: 0, texte: 'Au moins 8 caractères, avec une lettre et un chiffre', couleur: 'var(--accent)' };
            var aLettre = /[a-zA-Z]/.test(mdp);
            var aChiffre = /[0-9]/.test(mdp);
            var aSpecial = /[^a-zA-Z0-9]/.test(mdp);
            var assezLong = mdp.length >= 8;

            if (!assezLong || !aLettre || !aChiffre) {
                return { pct: 33, texte: 'Trop faible : 8 caractères minimum, avec une lettre et un chiffre', couleur: 'var(--accent)' };
            }
            if (mdp.length >= 12 && aSpecial) {
                return { pct: 100, texte: 'Mot de passe robuste', couleur: 'var(--success)' };
            }
            return { pct: 66, texte: 'Correct — plus long et avec un symbole, ce serait encore mieux', couleur: '#d8a316' };
        }

        input.addEventListener('input', function () {
            var etat = evaluer(input.value);
            fill.style.width = etat.pct + '%';
            fill.style.backgroundColor = etat.couleur;
            label.textContent = etat.texte;
        });
    })();
</script>

<?php include 'footer.php'; ?>
