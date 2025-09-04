<?php
require_once '../backend/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$is_logged_in = checkAuth();
$user_role = $_SESSION['user_role'] ?? '';
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
        color: #e74c3c;
        text-align: center;
    }
    
    .contact-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 3rem;
        margin-bottom: 3rem;
    }
    
    .contact-info {
        background: #2c3e50;
        border: 2px solid #34495e;
        border-radius: 12px;
        padding: 2rem;
    }
    
    .contact-form {
        background: #2c3e50;
        border: 2px solid #34495e;
        border-radius: 12px;
        padding: 2rem;
    }
    
    .info-title {
        color: #e74c3c;
        font-size: 1.5rem;
        margin-bottom: 1.5rem;
    }
    
    .info-item {
        display: flex;
        align-items: center;
        margin-bottom: 1.5rem;
        color: #bdc3c7;
    }
    
    .info-icon {
        width: 40px;
        height: 40px;
        background: #e74c3c;
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
        color: white;
        margin-bottom: 0.25rem;
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
    
    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 0.8rem;
        border: 2px solid #34495e;
        border-radius: 5px;
        background: #1a1a1a;
        color: white;
        font-size: 1rem;
    }
    
    .form-group input:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #e74c3c;
    }
    
    .form-group textarea {
        height: 120px;
        resize: vertical;
    }
    
    .btn-submit {
        background: #e74c3c;
        color: white;
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
        background: #c0392b;
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
        background: #34495e;
        border-radius: 8px;
        padding: 2rem;
        text-align: center;
    }
    
    .location-name {
        color: #e74c3c;
        font-size: 1.3rem;
        font-weight: bold;
        margin-bottom: 1rem;
    }
    
    .location-address {
        color: #bdc3c7;
        line-height: 1.6;
        margin-bottom: 1rem;
    }
    
    .location-hours {
        color: #bdc3c7;
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
                <form method="POST">
                    <div class="form-group">
                        <label for="nom">Nom</label>
                        <input type="text" id="nom" name="nom" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="sujet">Sujet</label>
                        <input type="text" id="sujet" name="sujet" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" placeholder="Votre message..." required></textarea>
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
