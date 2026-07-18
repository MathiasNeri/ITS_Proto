<?php
require_once __DIR__ . '/../backend/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$is_logged_in = checkAuth();
$user_role = $_SESSION['user_role'] ?? '';

// Traitement du formulaire RDV
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nom'])) {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $boutique = $_POST['boutique'] ?? '';
    $service = $_POST['service'] ?? '';
    $date = $_POST['date'] ?? '';
    $message = trim($_POST['message'] ?? '');

    // Validation
    if (!csrfVerify()) {
        $error = 'Session expirée, merci de réessayer.';
    } elseif (empty($nom) || empty($prenom) || empty($email) || empty($telephone) || empty($boutique) || empty($service) || empty($date)) {
        $error = 'Tous les champs obligatoires doivent être remplis';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email invalide';
    } elseif ($date < date('Y-m-d')) {
        $error = 'La date sélectionnée est déjà passée';
    } else {
        $pdo = initDatabase();
        
        // Sauvegarde en base
        $stmt = $pdo->prepare("INSERT INTO rdv (nom, prenom, email, telephone, boutique, service, date_rdv, message) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt->execute([$nom, $prenom, $email, $telephone, $boutique, $service, $date, $message])) {
            $success = 'Votre demande de rendez-vous a été envoyée avec succès !';
        } else {
            $error = 'Erreur lors de l\'envoi de la demande';
        }
    }
}
?>
<?php include 'header.php'; ?>

<style>
    .page-container {
        max-width: 800px;
        margin: 2rem auto;
        padding: 0 2rem;
    }
    
    .page-title {
        font-size: 2.5rem;
        margin-bottom: 2rem;
        color: var(--accent);
        text-align: center;
    }
    
    .rdv-form {
        background: var(--surface);
        border: 2px solid var(--surface-alt);
        border-radius: 12px;
        padding: 2rem;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1rem;
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
    .form-group select,
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
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: var(--accent);
    }
    
    .form-group textarea {
        height: 100px;
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
    
    .info-section {
        background: var(--surface-alt);
        border-radius: 8px;
        padding: 2rem;
        margin-top: 2rem;
        text-align: center;
    }
    
    .info-title {
        color: var(--accent);
        font-size: 1.5rem;
        margin-bottom: 1rem;
    }
    
    .info-text {
        color: var(--text-muted);
        line-height: 1.6;
    }
    
    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
        }
    }
</style>

<main class="main-content">
    <div class="page-container">
        <h1 class="page-title">📅 PRENDRE UN RENDEZ-VOUS</h1>
        
        <?php if ($success): ?>
            <div class="message success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" class="rdv-form">
            <?php echo csrfField(); ?>
            <div class="form-row">
                <div class="form-group">
                    <label for="nom">Nom *</label>
                    <input type="text" id="nom" name="nom" value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="prenom">Prénom *</label>
                    <input type="text" id="prenom" name="prenom" value="<?php echo htmlspecialchars($_POST['prenom'] ?? ''); ?>" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="telephone">Téléphone *</label>
                    <input type="tel" id="telephone" name="telephone" value="<?php echo htmlspecialchars($_POST['telephone'] ?? ''); ?>" required>
                </div>
            </div>
            
            <input type="hidden" name="boutique" value="Pierrefeu">

            <div class="form-group">
                <label for="service">Service *</label>
                <select id="service" name="service" required>
                    <option value="">Sélectionner un service</option>
                    <option value="Diagnostic" <?php echo (($_POST['service'] ?? '') === 'Diagnostic') ? 'selected' : ''; ?>>Diagnostic</option>
                    <option value="Réparation ordinateur" <?php echo (($_POST['service'] ?? '') === 'Réparation ordinateur') ? 'selected' : ''; ?>>Réparation ordinateur</option>
                    <option value="Réparation téléphone" <?php echo (($_POST['service'] ?? '') === 'Réparation téléphone') ? 'selected' : ''; ?>>Réparation téléphone</option>
                    <option value="Réparation tablette" <?php echo (($_POST['service'] ?? '') === 'Réparation tablette') ? 'selected' : ''; ?>>Réparation tablette</option>
                    <option value="Vente" <?php echo (($_POST['service'] ?? '') === 'Vente') ? 'selected' : ''; ?>>Vente</option>
                    <option value="Autre" <?php echo (($_POST['service'] ?? '') === 'Autre') ? 'selected' : ''; ?>>Autre</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="date">Date souhaitée *</label>
                <input type="date" id="date" name="date" min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($_POST['date'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="message">Message (optionnel)</label>
                <textarea id="message" name="message" placeholder="Décrivez votre problème ou votre demande..."><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
            </div>
            
            <button type="submit" class="btn-submit">Envoyer la demande</button>
        </form>
        
        <div class="info-section">
            <h3 class="info-title">Informations pratiques</h3>
            <p class="info-text">
                Nous vous contacterons dans les plus brefs délais pour confirmer votre rendez-vous. 
                Les créneaux disponibles sont du lundi au vendredi de 9h à 18h et le samedi de 9h à 12h.
            </p>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>
