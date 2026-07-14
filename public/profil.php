<?php
require_once '../backend/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté
if (!checkAuth()) {
    redirect('connexion.php');
}

$user_email = $_SESSION['user_email'] ?? '';
$user_role = $_SESSION['user_role'] ?? 'user';

// Traitement du formulaire de modification de profil
$message = '';
$error = '';

if ($_POST) {
    if (!csrfVerify()) {
        $error = 'Session expirée, merci de réessayer.';
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_email = trim($_POST['email'] ?? '');
        $new_password = trim($_POST['password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');

        if (empty($current_password)) {
            $error = 'Merci de saisir votre mot de passe actuel pour confirmer les changements';
        } elseif (empty($new_email)) {
            $error = 'L\'email est requis';
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format d\'email invalide';
        } elseif (!empty($new_password) && strlen($new_password) < 6) {
            $error = 'Le nouveau mot de passe doit contenir au moins 6 caractères';
        } elseif (!empty($new_password) && $new_password !== $confirm_password) {
            $error = 'Les mots de passe ne correspondent pas';
        } else {
            try {
                $pdo = initDatabase();

                $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
                $stmt->execute([$_SESSION['user_id']]);
                $currentUser = $stmt->fetch();

                if (!$currentUser || !password_verify($current_password, $currentUser['password'])) {
                    $error = 'Mot de passe actuel incorrect';
                } else {
                    // Vérifier si l'email existe déjà
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $stmt->execute([$new_email, $_SESSION['user_id']]);
                    if ($stmt->fetch()) {
                        $error = 'Cet email est déjà utilisé';
                    } else {
                        // Mettre à jour le profil
                        if (!empty($new_password)) {
                            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("UPDATE users SET email = ?, password = ? WHERE id = ?");
                            $stmt->execute([$new_email, $hashed_password, $_SESSION['user_id']]);
                        } else {
                            $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
                            $stmt->execute([$new_email, $_SESSION['user_id']]);
                        }

                        $_SESSION['user_email'] = $new_email;
                        $message = 'Profil mis à jour avec succès';
                    }
                }
            } catch (PDOException $e) {
                $error = 'Erreur lors de la mise à jour du profil';
                logError($e->getMessage());
            }
        }
    }
}
?>
<?php include 'header.php'; ?>

<style>
    .profile-container {
        max-width: 800px;
        margin: 2rem auto;
        padding: 0 2rem;
    }
    
    .profile-card {
        background: var(--surface);
        padding: 2rem;
        border-radius: 10px;
        border: 2px solid var(--surface-alt);
    }
    
    .profile-title {
        font-size: 2rem;
        margin-bottom: 2rem;
        color: var(--accent);
        text-align: center;
    }
    
    .form-group {
        margin-bottom: 1.5rem;
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
        border-radius: 5px;
        background: var(--surface-deep);
        color: var(--text);
        font-size: 1rem;
    }
    
    .form-group input:focus {
        outline: none;
        border-color: var(--accent);
    }
    
    .btn {
        background: var(--accent);
        color: var(--text);
        padding: 1rem 2rem;
        border: none;
        border-radius: 5px;
        font-size: 1rem;
        font-weight: bold;
        cursor: pointer;
        transition: background 0.3s;
        width: 100%;
        margin-bottom: 1rem;
    }
    
    .btn:hover {
        background: var(--accent-hover);
    }
    
    .btn-secondary {
        background: var(--accent-2);
        text-decoration: none;
        display: inline-block;
        text-align: center;
    }
    
    .btn-secondary:hover {
        background: var(--accent-2-hover);
    }
    
    .message {
        padding: 1rem;
        border-radius: 5px;
        margin-bottom: 1rem;
        text-align: center;
    }
    
    .message.success {
        background: var(--success);
        color: var(--text);
    }
    
    .message.error {
        background: var(--accent);
        color: var(--text);
    }
    
    .user-info {
        background: var(--surface-alt);
        padding: 1.5rem;
        border-radius: 5px;
        margin-bottom: 2rem;
    }
    
    .user-info h3 {
        color: var(--accent);
        margin-bottom: 1rem;
    }
    
    .user-info p {
        color: var(--text-muted);
        margin-bottom: 0.5rem;
    }
</style>

<main class="main-content">
    <div class="profile-container">
        <div class="profile-card">
            <h1 class="profile-title">Mon Profil</h1>
            
            <?php if ($message): ?>
                <div class="message success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="user-info">
                <h3>Informations actuelles</h3>
                <p><strong>Email :</strong> <?php echo htmlspecialchars($user_email); ?></p>
                <p><strong>Rôle :</strong> <?php echo htmlspecialchars($user_role); ?></p>
            </div>
            
            <form method="POST">
                <?php echo csrfField(); ?>
                <div class="form-group">
                    <label for="email">Nouvel email :</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_email); ?>" required>
                </div>

                <div class="form-group">
                    <label for="password">Nouveau mot de passe (laisser vide pour ne pas changer) :</label>
                    <input type="password" id="password" name="password">
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirmer le nouveau mot de passe :</label>
                    <input type="password" id="confirm_password" name="confirm_password">
                </div>

                <div class="form-group">
                    <label for="current_password">Mot de passe actuel (pour confirmer) :</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>

                <button type="submit" class="btn">Mettre à jour le profil</button>
            </form>

            <a href="mes-commandes.php" class="btn btn-secondary">Mes commandes</a>
            <a href="accueil.php" class="btn btn-secondary" style="margin-top: .6rem;">Retour à l'accueil</a>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>
