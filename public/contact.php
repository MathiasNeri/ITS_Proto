<?php
require_once __DIR__ . '/../backend/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$is_logged_in = checkAuth();
$user_role = $_SESSION['user_role'] ?? '';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $sujet = trim($_POST['sujet'] ?? '');
    $messageTexte = trim($_POST['message'] ?? '');

    if (!csrfVerify()) {
        $error = 'Session expirée, merci de réessayer.';
    } elseif (empty($nom) || empty($email) || empty($sujet) || empty($messageTexte)) {
        $error = 'Tous les champs sont obligatoires';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email invalide';
    } else {
        try {
            $pdo = initDatabase();
            $stmt = $pdo->prepare('INSERT INTO messages (nom, email, sujet, message) VALUES (?, ?, ?, ?)');
            $stmt->execute([$nom, $email, $sujet, $messageTexte]);
            $success = 'Votre message a bien été envoyé, nous vous répondrons rapidement.';
        } catch (PDOException $e) {
            $error = 'Erreur lors de l\'envoi du message';
            logError($e->getMessage());
        }
    }
}
?>
<?php include 'header.php'; ?>

<style>
    .page-container {
        max-width: 1200px;
        margin: 2rem auto;
        padding: 0 2rem;
    }
    
    .page-title {
        font-size: 2.5rem;
        margin-bottom: 2rem;
        color: var(--accent);
        text-align: center;
    }
    
    .contact-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 3rem;
        margin-bottom: 3rem;
    }
    
    .contact-info {
        background: var(--surface);
        border: 2px solid var(--surface-alt);
        border-radius: 12px;
        padding: 2rem;
    }
    
    .contact-form {
        background: var(--surface);
        border: 2px solid var(--surface-alt);
        border-radius: 12px;
        padding: 2rem;
    }
    
    .info-title {
        color: var(--accent);
        font-size: 1.5rem;
        margin-bottom: 1.5rem;
    }
    
    .info-item {
        display: flex;
        align-items: center;
        margin-bottom: 1.5rem;
        color: var(--text-muted);
    }
    
    .info-icon {
        width: 40px;
        height: 40px;
        background: var(--accent);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 1rem;
        font-size: 1.2rem;
    }
    
    .info-text {
        flex: 1;
    }
    
    .info-label {
        font-weight: bold;
        color: var(--text);
        margin-bottom: 0.25rem;
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
    
    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 0.8rem;
        border: 2px solid var(--surface-alt);
        border-radius: 5px;
        background: var(--surface-deep);
        color: var(--text);
        font-size: 1rem;
    }
    
    .form-group input:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: var(--accent);
    }
    
    .form-group textarea {
        height: 120px;
        resize: vertical;
    }
    
    .btn-submit {
        background: var(--accent);
        color: var(--text);
        padding: 1rem 2rem;
        border: none;
        border-radius: 5px;
        font-size: 1.1rem;
        font-weight: bold;
        cursor: pointer;
        transition: background 0.3s;
        width: 100%;
    }
    
    .btn-submit:hover {
        background: var(--accent-hover);
    }
    
    .locations-section {
        margin-top: 3rem;
    }
    
    .locations-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2rem;
        margin-top: 2rem;
    }
    
    .location-card {
        background: var(--surface-alt);
        border-radius: 8px;
        padding: 2rem;
        text-align: center;
    }
    
    .location-name {
        color: var(--accent);
        font-size: 1.3rem;
        font-weight: bold;
        margin-bottom: 1rem;
    }
    
    .location-address {
        color: var(--text-muted);
        line-height: 1.6;
        margin-bottom: 1rem;
    }
    
    .location-hours {
        color: var(--text-muted);
        font-size: 0.9rem;
    }
    
    @media (max-width: 768px) {
        .contact-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<main class="main-content">
    <div class="page-container">
        <h1 class="page-title">@ CONTACT</h1>
        
        <div class="contact-grid">
            <div class="contact-info">
                <h3 class="info-title">Informations de contact</h3>
                
                <div class="info-item">
                    <div class="info-icon">📞</div>
                    <div class="info-text">
                        <div class="info-label">Téléphone</div>
                        <div>04 XX XX XX XX</div>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon">📧</div>
                    <div class="info-text">
                        <div class="info-label">Email</div>
                        <div>contact@its-reparation.fr</div>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon">🕒</div>
                    <div class="info-text">
                        <div class="info-label">Horaires</div>
                        <div>Lun-Ven: 9h-18h<br>Samedi: 9h-12h</div>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon">📍</div>
                    <div class="info-text">
                        <div class="info-label">Adresses</div>
                        <div>Solliès-Pont & Pierrefeu</div>
                    </div>
                </div>
            </div>
            
            <div class="contact-form">
                <h3 class="info-title">Nous écrire</h3>

                <?php if ($success): ?>
                    <div class="message success" style="padding:1rem;border-radius:5px;margin-bottom:1rem;text-align:center;background:var(--success);color:var(--text);"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="message error" style="padding:1rem;border-radius:5px;margin-bottom:1rem;text-align:center;background:var(--accent);color:var(--text);"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST">
                    <?php echo csrfField(); ?>
                    <div class="form-group">
                        <label for="nom">Nom</label>
                        <input type="text" id="nom" name="nom" value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="sujet">Sujet</label>
                        <input type="text" id="sujet" name="sujet" value="<?php echo htmlspecialchars($_POST['sujet'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" placeholder="Votre message..." required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" class="btn-submit">Envoyer le message</button>
                </form>
            </div>
        </div>
        
        <div class="locations-section">
            <h3 class="info-title" style="text-align: center;">Nos boutiques</h3>
            <div class="locations-grid">
                <div class="location-card">
                    <h4 class="location-name">Solliès-Pont</h4>
                    <div class="location-address">
                        123 Rue de la République<br>
                        83210 Solliès-Pont
                    </div>
                    <div class="location-hours">
                        Lun-Ven: 9h-18h<br>
                        Samedi: 9h-12h
                    </div>
                </div>
                
                <div class="location-card">
                    <h4 class="location-name">Pierrefeu</h4>
                    <div class="location-address">
                        456 Avenue des Pins<br>
                        83390 Pierrefeu-du-Var
                    </div>
                    <div class="location-hours">
                        Lun-Ven: 9h-18h<br>
                        Samedi: 9h-12h
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>
