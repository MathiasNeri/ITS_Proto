<?php
require_once __DIR__ . '/../backend/config.php';

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
        color: var(--accent);
        text-align: center;
    }
    
    .pricing-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2rem;
        margin-bottom: 4rem;
    }
    
    .pricing-card {
        background: var(--surface);
        border: 2px solid var(--surface-alt);
        border-radius: 12px;
        padding: 2rem;
        text-align: center;
        transition: transform 0.3s, border-color 0.3s;
    }
    
    .pricing-card:hover {
        transform: translateY(-3px);
        border-color: var(--accent);
    }
    
    .pricing-card.featured {
        border-color: var(--accent);
        transform: scale(1.05);
    }
    
    .service-name {
        font-size: 1.5rem;
        font-weight: bold;
        color: var(--accent);
        margin-bottom: 1rem;
    }
    
    .price {
        font-size: 2rem;
        font-weight: bold;
        color: var(--text);
        margin-bottom: 1rem;
    }
    
    .price-note {
        color: var(--text-muted);
        font-size: 0.9rem;
        margin-bottom: 1.5rem;
    }
    
    .features {
        list-style: none;
        padding: 0;
        margin-bottom: 2rem;
    }
    
    .features li {
        color: var(--text-muted);
        padding: 0.5rem 0;
        border-bottom: 1px solid var(--surface-alt);
    }
    
    .features li:last-child {
        border-bottom: none;
    }
    
    .cta-btn {
        background: var(--accent);
        color: var(--text);
        padding: 1rem 2rem;
        border: none;
        border-radius: 8px;
        font-size: 1.1rem;
        font-weight: bold;
        cursor: pointer;
        transition: background 0.3s;
        text-decoration: none;
        display: inline-block;
    }
    
    .cta-btn:hover {
        background: var(--accent-hover);
    }
    
    .info-section {
        background: var(--surface-alt);
        border-radius: 8px;
        padding: 2rem;
        margin-top: 3rem;
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
</style>

<main class="main-content">
    <div class="page-container">
        <h1 class="page-title">🏷️ TARIFS</h1>
        
        <div class="pricing-grid">
            <div class="pricing-card">
                <h3 class="service-name">Diagnostic</h3>
                <div class="price">Gratuit</div>
                <div class="price-note">Si réparation effectuée</div>
                <ul class="features">
                    <li>✓ Diagnostic complet</li>
                    <li>✓ Devis détaillé</li>
                    <li>✓ Estimation des délais</li>
                    <li>✓ Conseils personnalisés</li>
                </ul>
                <a href="accueil.php#contact" class="cta-btn">Demander un diagnostic</a>
            </div>
            
            <div class="pricing-card featured">
                <h3 class="service-name">Réparation Standard</h3>
                <div class="price">À partir de 29€</div>
                <div class="price-note">Selon le type de réparation</div>
                <ul class="features">
                    <li>✓ Réparation écran</li>
                    <li>✓ Remplacement batterie</li>
                    <li>✓ Réparation logicielle</li>
                    <li>✓ Garantie 3 mois</li>
                </ul>
                <a href="accueil.php#contact" class="cta-btn">Prendre rendez-vous</a>
            </div>
            
            <div class="pricing-card">
                <h3 class="service-name">Réparation Complexe</h3>
                <div class="price">À partir de 79€</div>
                <div class="price-note">Réparations avancées</div>
                <ul class="features">
                    <li>✓ Réparation carte mère</li>
                    <li>✓ Remplacement composants</li>
                    <li>✓ Récupération données</li>
                    <li>✓ Garantie 6 mois</li>
                </ul>
                <a href="accueil.php#contact" class="cta-btn">Demander un devis</a>
            </div>
        </div>
        
        <div class="pricing-grid">
            <div class="pricing-card">
                <h3 class="service-name">Ordinateur Portable</h3>
                <div class="price">À partir de 39€</div>
                <div class="price-note">Réparation PC portable</div>
                <ul class="features">
                    <li>✓ Changement écran</li>
                    <li>✓ Remplacement clavier</li>
                    <li>✓ Réparation ventilateur</li>
                    <li>✓ Nettoyage complet</li>
                </ul>
                <a href="accueil.php#contact" class="cta-btn">Réparer mon PC</a>
            </div>
            
            <div class="pricing-card">
                <h3 class="service-name">Smartphone</h3>
                <div class="price">À partir de 25€</div>
                <div class="price-note">Réparation téléphone</div>
                <ul class="features">
                    <li>✓ Écran cassé</li>
                    <li>✓ Batterie défaillante</li>
                    <li>✓ Boutons défectueux</li>
                    <li>✓ Réparation rapide</li>
                </ul>
                <a href="accueil.php#contact" class="cta-btn">Réparer mon téléphone</a>
            </div>
            
            <div class="pricing-card">
                <h3 class="service-name">Tablette</h3>
                <div class="price">À partir de 35€</div>
                <div class="price-note">Réparation tablette</div>
                <ul class="features">
                    <li>✓ Écran tactile</li>
                    <li>✓ Connectique</li>
                    <li>✓ Batterie</li>
                    <li>✓ Toutes marques</li>
                </ul>
                <a href="accueil.php#contact" class="cta-btn">Réparer ma tablette</a>
            </div>
        </div>
        
        <div class="info-section">
            <h3 class="info-title">Informations importantes</h3>
            <p class="info-text">
                Les tarifs indiqués sont des prix de base. Le coût final dépend du modèle de votre appareil, 
                de la complexité de la réparation et des pièces nécessaires. Nous vous fournirons toujours 
                un devis gratuit et détaillé avant toute intervention. Toutes nos réparations sont garanties.
            </p>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>
