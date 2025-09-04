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
    
    .services-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2rem;
        margin-bottom: 4rem;
    }
    
    .service-card {
        background: #2c3e50;
        border: 2px solid #34495e;
        border-radius: 12px;
        padding: 2rem;
        text-align: center;
        transition: transform 0.3s, border-color 0.3s;
    }
    
    .service-card:hover {
        transform: translateY(-3px);
        border-color: #e74c3c;
    }
    
    .service-icon {
        width: 80px;
        height: 80px;
        margin: 0 auto 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .service-title {
        font-size: 1.3rem;
        font-weight: bold;
        color: white;
        margin-bottom: 1rem;
    }
    
    .service-description {
        color: #bdc3c7;
        line-height: 1.6;
    }
    
    .cta-section {
        text-align: center;
        margin-top: 3rem;
    }
    
    .cta-btn {
        background: #e74c3c;
        color: white;
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
        background: #c0392b;
    }
</style>

<main class="main-content">
    <div class="page-container">
        <h1 class="page-title">🛒 VENTE</h1>
        
        <div class="services-grid">
            <div class="service-card">
                <div class="service-icon">
                    <img src="images/service-vente.png" alt="Vente" style="width: 80px; height: 80px;">
                </div>
                <h3 class="service-title">Ordinateurs</h3>
                <p class="service-description">
                    Ordinateurs portables et de bureau neufs, reconditionnés et d'occasion. 
                    Toutes marques : HP, Dell, Lenovo, Asus, Acer...
                </p>
            </div>
            
            <div class="service-card">
                <div class="service-icon">
                    <img src="images/service-telephone.png" alt="Téléphones" style="width: 80px; height: 80px;">
                </div>
                <h3 class="service-title">Téléphones & Tablettes</h3>
                <p class="service-description">
                    Smartphones et tablettes neufs, reconditionnés et d'occasion. 
                    iPhone, Samsung, Huawei, Xiaomi...
                </p>
            </div>
            
            <div class="service-card">
                <div class="service-icon">
                    <img src="images/service-ordinateur.png" alt="Accessoires" style="width: 80px; height: 80px;">
                </div>
                <h3 class="service-title">Accessoires</h3>
                <p class="service-description">
                    Souris, claviers, écrans, casques, chargeurs, câbles et tous les accessoires informatiques.
                </p>
            </div>
            
            <div class="service-card">
                <div class="service-icon">
                    <img src="images/service-reparation.png" alt="Pièces" style="width: 80px; height: 80px;">
                </div>
                <h3 class="service-title">Pièces Détachées</h3>
                <p class="service-description">
                    Pièces détachées pour ordinateurs et téléphones : écrans, batteries, claviers, etc.
                </p>
            </div>
        </div>
        
        <div class="cta-section">
            <a href="accueil.php#contact" class="cta-btn">Demander un devis</a>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>
