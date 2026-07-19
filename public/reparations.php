<?php
require_once __DIR__ . '/../backend/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$is_logged_in = checkAuth();
$user_role = $_SESSION['user_role'] ?? '';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nom'])) {
    $materiel = trim($_POST['materiel'] ?? '');
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $code_postal = trim($_POST['code_postal'] ?? '');
    $ville = trim($_POST['ville'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $consentement = isset($_POST['consentement']);

    if (!csrfVerify()) {
        $error = 'Session expirée, merci de réessayer.';
    } elseif (!honeypotPasses()) {
        // Soumission détectée comme un bot : succès silencieux, rien n'est enregistré ni envoyé.
        $success = 'Votre demande de devis a bien été envoyée ! Nous revenons vers vous rapidement.';
    } elseif (empty($materiel) || empty($nom) || empty($prenom) || empty($adresse) || empty($code_postal) || empty($ville) || empty($email) || empty($telephone)) {
        $error = 'Tous les champs obligatoires doivent être remplis.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email invalide.';
    } elseif (!$consentement) {
        $error = 'Merci d\'accepter l\'utilisation de vos données pour traiter votre demande.';
    } else {
        $fichier = traiterUploadDevis($_FILES['fichier'] ?? null);

        if ($fichier['erreur']) {
            $error = $fichier['erreur'];
        } else {
            $pdo = initDatabase();
            $stmt = $pdo->prepare('INSERT INTO devis (materiel, nom, prenom, adresse, code_postal, ville, email, telephone, boutique, message, fichier_nom, fichier_chemin) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

            if ($stmt->execute([$materiel, $nom, $prenom, $adresse, $code_postal, $ville, $email, $telephone, 'Pierrefeu', $message, $fichier['nom'], $fichier['chemin']])) {
                envoyerEmailsDevis([
                    'materiel' => $materiel,
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'email' => $email,
                    'telephone' => $telephone,
                    'message' => $message,
                ]);
                $success = 'Votre demande de devis a bien été envoyée ! Nous revenons vers vous rapidement.';
                $_POST = [];
            } else {
                $error = 'Erreur lors de l\'envoi de la demande.';
            }
        }
    }
}

$page_title = 'Réparations';
$page_description = "Réparation de smartphones, tablettes et ordinateurs toutes marques (Windows, Apple, Linux, Android, iOS) à Pierrefeu-du-Var. Demande de devis gratuite en ligne.";
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
    
    .os-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 2rem;
        margin-bottom: 4rem;
    }
    
    .os-card {
        background: var(--surface);
        border: 1px solid var(--divider);
        border-radius: var(--radius-md);
        padding: 2rem;
        text-align: center;
        box-shadow: var(--shadow-sm);
        transition: transform var(--ease), border-color var(--ease), box-shadow var(--ease);
    }

    .os-card:hover {
        transform: translateY(-4px);
        border-color: var(--accent);
        box-shadow: var(--shadow-md);
    }
    
    .os-icon {
        width: 60px;
        height: 60px;
        margin: 0 auto 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .os-title {
        font-size: 1.3rem;
        font-weight: bold;
        color: var(--text);
        margin-bottom: 1rem;
    }
    
    .os-description {
        color: var(--text-muted);
        line-height: 1.6;
    }
    
    .services-section {
        margin-bottom: 3rem;
    }
    
    .services-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2rem;
        margin-bottom: 2rem;
    }
    
    .service-card {
        background: var(--surface-alt);
        border-radius: var(--radius-sm);
        padding: 1.5rem;
        text-align: center;
        box-shadow: var(--shadow-sm);
        transition: transform var(--ease), box-shadow var(--ease);
    }

    .service-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-md);
    }
    
    .service-title {
        color: var(--accent);
        font-size: 1.2rem;
        margin-bottom: 1rem;
    }
    
    .service-description {
        color: var(--text-muted);
        line-height: 1.6;
    }
    
    .cta-section {
        text-align: center;
        margin-top: 3rem;
    }
    
    .cta-btn {
        background: var(--accent);
        color: var(--text);
        padding: 1rem 2rem;
        border: none;
        border-radius: var(--radius-sm);
        font-size: 1.1rem;
        font-weight: bold;
        cursor: pointer;
        transition: background-color var(--ease), transform var(--ease), box-shadow var(--ease);
        text-decoration: none;
        display: inline-block;
    }

    .cta-btn:hover {
        background: var(--accent-hover);
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .devis-section {
        margin-bottom: 3rem;
    }

    .devis-layout {
        display: grid;
        grid-template-columns: 150px 1fr 150px;
        gap: 2rem;
        align-items: start;
    }

    .brand-column {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .brand-badge {
        background: var(--surface-alt);
        border-radius: var(--radius-sm);
        padding: 0.9rem 0.5rem;
        text-align: center;
        color: var(--text-muted);
        font-weight: 700;
        letter-spacing: 0.5px;
        font-size: 0.95rem;
        box-shadow: var(--shadow-sm);
        transition: transform var(--ease), box-shadow var(--ease);
    }

    .brand-badge:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
        color: var(--text);
    }

    .devis-form-card {
        background: var(--surface);
        border: 1px solid var(--divider);
        border-radius: var(--radius-lg);
        padding: 2rem;
        box-shadow: var(--shadow-md);
    }

    .devis-form .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .devis-form .form-group {
        margin-bottom: 1rem;
    }

    .devis-form .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        color: var(--text-muted);
        font-weight: bold;
    }

    .devis-form .form-group input,
    .devis-form .form-group select,
    .devis-form .form-group textarea {
        width: 100%;
        padding: 0.8rem;
        border: 2px solid var(--surface-alt);
        border-radius: var(--radius-sm);
        background: var(--surface-deep);
        color: var(--text);
        font-size: 1rem;
        transition: border-color var(--ease);
    }

    .devis-form .form-group input:focus,
    .devis-form .form-group select:focus,
    .devis-form .form-group textarea:focus {
        outline: none;
        border-color: var(--accent);
    }

    .devis-form .form-group textarea {
        height: 100px;
        resize: vertical;
    }

    .devis-form .form-group input[readonly] {
        opacity: 0.75;
        cursor: not-allowed;
    }

    .file-upload-group {
        margin-bottom: 1rem;
    }

    .file-label {
        display: inline-flex;
        align-items: center;
        gap: 0.6rem;
        background: var(--surface-alt);
        color: var(--text);
        padding: 0.7rem 1.2rem;
        border-radius: var(--radius-sm);
        cursor: pointer;
        font-weight: bold;
        transition: background-color var(--ease);
    }

    .file-label:hover {
        background: var(--accent);
    }

    .file-label input[type="file"] {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        opacity: 0;
    }

    .file-name {
        margin-left: 0.8rem;
        color: var(--text-muted);
        font-size: 0.9rem;
    }

    .file-hint {
        display: block;
        margin-top: 0.4rem;
        color: var(--text-muted);
        font-size: 0.8rem;
    }

    .checkbox-group {
        display: flex;
        align-items: flex-start;
        gap: 0.6rem;
        margin-bottom: 1.5rem;
    }

    .checkbox-group input {
        width: auto;
        margin-top: 0.3rem;
    }

    .btn-submit {
        background: var(--accent);
        color: var(--text);
        padding: 1rem 2rem;
        border: none;
        border-radius: var(--radius-sm);
        font-size: 1.1rem;
        font-weight: bold;
        cursor: pointer;
        width: 100%;
        transition: background-color var(--ease), transform var(--ease), box-shadow var(--ease);
    }

    .btn-submit:hover {
        background: var(--accent-hover);
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    .checkbox-group label {
        color: var(--text-muted);
        font-size: 0.9rem;
        line-height: 1.4;
    }

    @media (max-width: 480px) {
        .page-container {
            padding: 0 1.2rem;
        }

        .page-title {
            font-size: 1.7rem;
        }
    }

    @media (max-width: 360px) {
        .services-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 900px) {
        .devis-layout {
            grid-template-columns: 1fr;
        }

        .brand-column {
            flex-direction: row;
            flex-wrap: wrap;
            justify-content: center;
        }

        .brand-badge {
            flex: 1 1 120px;
        }
    }

    @media (max-width: 640px) {
        .devis-form .form-row {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
    function updateFileName(input) {
        const span = document.getElementById('file-name-display');
        span.textContent = input.files.length ? input.files[0].name : '';
    }
</script>

<main class="main-content">
    <div class="page-container">
        <h1 class="page-title">🔧 RÉPARATIONS</h1>
        
        <div class="services-section">
            <h2 style="color: var(--accent); text-align: center; margin-bottom: 2rem;">Systèmes d'exploitation supportés</h2>
            <div class="os-grid">
                <div class="os-card">
                    <div class="os-icon">
                        <svg class="svg-inline--fa fa-windows fa-w-14" aria-hidden="true" data-fa-processed="" data-prefix="fab" data-icon="windows" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" style="width: 40px; height: 40px; color: #0078D4;">
                            <path fill="currentColor" d="M0 93.7l183.6-25.3v177.4H0V93.7zm0 324.6l183.6 25.3V268.4H0v149.9zm203.8 28L448 480V268.4H203.8v177.9zm0-380.6v180.1H448V32L203.8 65.7z"></path>
                        </svg>
                    </div>
                    <h3 class="os-title">Windows</h3>
                    <p class="os-description">Réparation et maintenance des ordinateurs Windows</p>
                </div>
                
                <div class="os-card">
                    <div class="os-icon">
                        <svg class="svg-inline--fa fa-apple fa-w-14" aria-hidden="true" data-fa-processed="" data-prefix="fab" data-icon="apple" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" style="width: 40px; height: 40px; color: #A6A6A6;">
                            <path fill="currentColor" d="M247.2 137.6c-6.2 1.9-15.3 3.5-27.9 4.6 1.1-56.7 29.9-96.6 88-110.1 9.3 41.6-26.1 94.1-60.1 105.5zm121.3 72.7c6.4-9.4 16.6-19.9 30.6-31.7-22.3-27.6-48.1-44.3-85.1-44.3-35.4 0-65.2 18.2-87 18.2-18.5 0-51.9-16.1-84.5-16.1-69.6 0-106.5 68.1-106.5 139C36 354.2 95.7 480 156.2 480c23.8 0 45.2-18 73.5-18 29.3 0 52.8 17.2 80.3 17.2 46 0 88.6-77.5 102-119.7-46.8-14.3-84.4-90.2-43.5-149.2z"></path>
                        </svg>
                    </div>
                    <h3 class="os-title">Apple</h3>
                    <p class="os-description">Réparation des Mac et appareils Apple</p>
                </div>
                
                <div class="os-card">
                    <div class="os-icon">
                        <svg class="svg-inline--fa fa-linux fa-w-14" aria-hidden="true" data-fa-processed="" data-prefix="fab" data-icon="linux" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" style="width: 40px; height: 40px; color: #F7931E;">
                            <path fill="currentColor" d="M196.1 123.6c-.2-1.4 1.9-2.3 3.2-2.9 1.7-.7 3.9-1 5.5-.1.4.2.8.7.6 1.1-.4 1.2-2.4 1-3.5 1.6-1 .5-1.8 1.7-3 1.7-1 .1-2.7-.4-2.8-1.4zm24.7-.3c1 .5 1.8 1.7 3 1.7 1.1 0 2.8-.4 2.9-1.5.2-1.4-1.9-2.3-3.2-2.9-1.7-.7-3.9-1-5.5-.1-.4.2-.8.7-.6 1.1.3 1.3 2.3 1.1 3.4 1.7zm214.7 310.2c-.5 8.2-6.5 13.8-13.9 18.3-14.9 9-37.3 15.8-50.9 32.2l-2.6-2.2 2.6 2.2c-14.2 16.9-31.7 26.6-48.3 27.9-16.5 1.3-32-6.3-40.3-23v-.1c-1.1-2.1-1.9-4.4-2.5-6.7-21.5 1.2-40.2-5.3-55.1-4.1-22 1.2-35.8 6.5-48.3 6.6-4.8 10.6-14.3 17.6-25.9 20.2-16 3.7-36.1 0-55.9-10.4l1.6-3-1.6 3c-18.5-9.8-42-8.9-59.3-12.5-8.7-1.8-16.3-5-20.1-12.3-3.7-7.3-3-17.3 2.2-31.7 1.7-5.1.4-12.7-.8-20.8-.6-3.9-1.2-7.9-1.2-11.8 0-4.3.7-8.5 2.8-12.4 4.5-8.5 11.8-12.1 18.5-14.5 6.7-2.4 12.8-4 17-8.3 5.2-5.5 10.1-14.4 16.6-20.2-2.6-17.2.2-35.4 6.2-53.3 12.6-37.9 39.2-74.2 58.1-96.7 16.1-22.9 20.8-41.3 22.5-64.7C158 103.4 132.4-.2 234.8 0c80.9.1 76.3 85.4 75.8 131.3-.3 30.1 16.3 50.5 33.4 72 15.2 18 35.1 44.3 46.5 74.4 9.3 24.6 12.9 51.8 3.7 79.1 1.4.5 2.8 1.2 4.1 2 1.4.8 2.7 1.8 4 2.9 6.6 5.6 8.7 14.3 10.5 22.4 1.9 8.1 3.6 15.7 7.2 19.7 11.1 12.4 15.9 21.5 15.5 29.7zM220.8 109.1c3.6.9 8.9 2.4 13 4.4-2.1-12.2 4.5-23.5 11.8-23 8.9.3 13.9 15.5 9.1 27.3-.8 1.9-2.8 3.4-3.9 4.6 6.7 2.3 11 4.1 12.6 4.9 7.9-9.5 10.8-26.2 4.3-40.4-9.8-21.4-34.2-21.8-44 .4-3.2 7.2-3.9 14.9-2.9 21.8zm-46.2 18.8c7.8-5.7 6.9-4.7 5.9-5.5-8-6.9-6.6-27.4 1.8-28.1 6.3-.5 10.8 10.7 9.6 19.6 3.1-2.1 6.7-3.6 10.2-4.6 1.7-19.3-9-33.5-19.1-33.5-18.9 0-24 37.5-8.4 52.1zm-9.4 20.9c1.5 4.9 6.1 10.5 14.7 15.3 7.8 4.6 12 11.5 20 15 2.6 1.1 5.7 1.9 9.6 2.1 18.4 1.1 27.1-11.3 38.2-14.9 11.7-3.7 20.1-11 22.7-18.1 3.2-8.5-2.1-14.7-10.5-18.2-11.3-4.9-16.3-5.2-22.6-9.3-10.3-6.6-18.8-8.9-25.9-8.9-14.4 0-23.2 9.8-27.9 14.2-.5.5-7.9 5.9-14.1 10.5-4.2 3.3-5.6 7.4-4.2 12.3zm-33.5 252.8L112.1 366c-6.8-9.2-13.8-14.8-21.9-16-7.7-1.2-12.6 1.4-17.7 6.9-4.8 5.1-8.8 12.3-14.3 18-7.8 6.5-9.3 6.2-19.6 9.9-6.3 2.2-11.3 4.6-14.8 11.3-2.7 5-2.1 12.2-.9 20 1.2 7.9 3 16.3.6 23.9v.2c-5 13.7-5 21.7-2.6 26.4 7.9 15.4 46.6 6.1 76.5 21.9 31.4 16.4 72.6 17.1 75.3-18 2.1-20.5-31.5-49-41-68.9zm153.9 35.8c3.2-11 6.3-21.3 6.8-29 .8-15.2 1.6-28.7 4.4-39.9 3.1-12.6 9.3-23.1 21.4-27.3 2.3-21.1 18.7-21.1 38.3-12.5 18.9 8.5 26 16 22.8 26.1 1 0 2-.1 4.2 0 5.2-16.9-14.3-28-30.7-34.8 2.9-12 2.4-24.1-.4-35.7-6-25.3-22.6-47.8-35.2-59-2.3-.1-2.1 1.9 2.6 6.5 11.6 10.7 37.1 49.2 23.3 84.9-3.9-1-7.6-1.5-10.9-1.4-5.3-29.1-17.5-53.2-23.6-64.6-11.5-21.4-29.5-65.3-37.2-95.7-4.5 6.4-12.4 11.9-22.3 15-4.7 1.5-9.7 5.5-15.9 9-13.9 8-30 8.8-42.4-1.2-4.5-3.6-8-7.6-12.6-10.3-1.6-.9-5.1-3.3-6.2-4.1-2 37.8-27.3 85.3-39.3 112.7-8.3 19.7-13.2 40.8-13.8 61.5-21.8-29.1-5.9-66.3 2.6-82.4 9.5-17.6 11-22.5 8.7-20.8-8.6 14-22 36.3-27.2 59.2-2.7 11.9-3.2 24 .3 35.2 3.5 11.2 11.1 21.5 24.6 29.9 0 0 24.8 14.3 38.3 32.5 7.4 10 9.7 18.7 7.4 24.9-2.5 6.7-9.6 8.9-16.7 8.9 4.8 6 10.3 13 14.4 19.6 37.6 25.7 82.2 15.7 114.3-7.2zM415 408.5c-10-11.3-7.2-33.1-17.1-41.6-6.9-6-13.6-5.4-22.6-5.1-7.7 8.8-25.8 19.6-38.4 16.3-11.5-2.9-18-16.3-18.8-29.5-.3.2-.7.3-1 .5-7.1 3.9-11.1 10.8-13.7 21.1-2.5 10.2-3.4 23.5-4.2 38.7-.7 11.8-6.2 26.4-9.9 40.6-3.5 13.2-5.8 25.2-1.1 36.3 7.2 14.5 19.5 20.4 33.7 19.3 14.2-1.1 30.4-9.8 43.6-25.5 22-26.6 62.3-29.7 63.2-46.5.3-5.1-3.1-13-13.7-24.6zM173.3 148.7c2 1.9 4.7 4.5 8 7.1 6.6 5.2 15.8 10.6 27.3 10.6 11.6 0 22.5-5.9 31.8-10.8 4.9-2.6 10.9-7 14.8-10.4 3.9-3.4 5.9-6.3 3.1-6.6-2.8-.3-2.6 2.6-6 5.1-4.4 3.2-9.7 7.4-13.9 9.8-7.4 4.2-19.5 10.2-29.9 10.2-10.4 0-18.7-4.8-24.9-9.7-3.1-2.5-5.7-5-7.7-6.9-1.5-1.4-1.9-4.6-4.3-4.9-1.4-.1-1.8 3.7 1.7 6.5z"></path>
                        </svg>
                    </div>
                    <h3 class="os-title">Linux</h3>
                    <p class="os-description">Support et maintenance des systèmes Linux</p>
                </div>
                
                <div class="os-card">
                    <div class="os-icon">
                        <svg class="svg-inline--fa fa-android fa-w-14" aria-hidden="true" data-fa-processed="" data-prefix="fab" data-icon="android" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" style="width: 40px; height: 40px; color: #3DDC84;">
                            <path fill="currentColor" d="M89.6 204.5v115.8c0 15.4-12.1 27.7-27.5 27.7-15.3 0-30.1-12.4-30.1-27.7V204.5c0-15.1 14.8-27.5 30.1-27.5 15.1 0 27.5 12.4 27.5 27.5zm10.8 157c0 16.4 13.2 29.6 29.6 29.6h19.9l.3 61.1c0 36.9 55.2 36.6 55.2 0v-61.1h37.2v61.1c0 36.7 55.5 36.8 55.5 0v-61.1h20.2c16.2 0 29.4-13.2 29.4-29.6V182.1H100.4v179.4zm248-189.1H99.3c0-42.8 25.6-80 63.6-99.4l-19.1-35.3c-2.8-4.9 4.3-8 6.7-3.8l19.4 35.6c34.9-15.5 75-14.7 108.3 0L297.5 34c2.5-4.3 9.5-1.1 6.7 3.8L285.1 73c37.7 19.4 63.3 56.6 63.3 99.4zm-170.7-55.5c0-5.7-4.6-10.5-10.5-10.5-5.7 0-10.2 4.8-10.2 10.5s4.6 10.5 10.2 10.5c5.9 0 10.5-4.8 10.5-10.5zm113.4 0c0-5.7-4.6-10.5-10.2-10.5-5.9 0-10.5 4.8-10.5 10.5s4.6 10.5 10.5 10.5c5.6 0 10.2-4.8 10.2-10.5zm94.8 60.1c-15.1 0-27.5 12.1-27.5 27.5v115.8c0 15.4 12.4 27.7 27.5 27.7 15.4 0 30.1-12.4 30.1-27.7V204.5c0-15.4-14.8-27.5-30.1-27.5z"></path>
                        </svg>
                    </div>
                    <h3 class="os-title">Android</h3>
                    <p class="os-description">Réparation des smartphones Android</p>
                </div>
                
                <div class="os-card">
                    <div class="os-icon">
                        <svg class="svg-inline--fa fa-app-store-ios fa-w-14" aria-hidden="true" data-fa-processed="" data-prefix="fab" data-icon="app-store-ios" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" style="width: 40px; height: 40px; color: #007AFF;">
                            <path fill="currentColor" d="M400 32H48C21.5 32 0 53.5 0 80v352c0 26.5 21.5 48 48 48h352c26.5 0 48-21.5 48-48V80c0-26.5-21.5-48-48-48zM127 384.5c-5.5 9.6-17.8 12.8-27.3 7.3-9.6-5.5-12.8-17.8-7.3-27.3l14.3-24.7c16.1-4.9 29.3-1.1 39.6 11.4L127 384.5zm138.9-53.9H84c-11 0-20-9-20-20s9-20 20-20h51l65.4-113.2-20.5-35.4c-5.5-9.6-2.2-21.8 7.3-27.3 9.6-5.5 21.8-2.2 27.3 7.3l8.9 15.4 8.9-15.4c5.5-9.6 17.8-12.8 27.3-7.3 9.6 5.5 12.8 17.8 7.3 27.3l-85.8 148.6h62.1c20.2 0 31.5 23.7 22.7 40zm98.1 0h-29l19.6 33.9c5.5 9.6 2.2 21.8-7.3 27.3-9.6 5.5-21.8 2.2-27.3-7.3-32.9-56.9-57.5-99.7-74-128.1-16.7-29-4.8-58 7.1-67.8 13.1 22.7 32.7 56.7 58.9 102h52c11 0 20 9 20 20 0 11.1-9 20-20 20z"></path>
                        </svg>
                    </div>
                    <h3 class="os-title">iOS</h3>
                    <p class="os-description">Réparation des iPhone et iPad</p>
                </div>
            </div>
        </div>
        
        <div class="services-section">
            <h2 style="color: var(--accent); text-align: center; margin-bottom: 2rem;">Types de réparations</h2>
            <div class="services-grid">
                <div class="service-card">
                    <h3 class="service-title">Diagnostic</h3>
                    <p class="service-description">Diagnostic complet de votre appareil pour identifier le problème</p>
                </div>
                
                <div class="service-card">
                    <h3 class="service-title">Réparation écran</h3>
                    <p class="service-description">Remplacement d'écrans cassés sur tous types d'appareils</p>
                </div>
                
                <div class="service-card">
                    <h3 class="service-title">Réparation batterie</h3>
                    <p class="service-description">Remplacement de batteries défaillantes</p>
                </div>
                
                <div class="service-card">
                    <h3 class="service-title">Réparation logicielle</h3>
                    <p class="service-description">Résolution des problèmes logiciels et réinstallation</p>
                </div>
            </div>
        </div>
        
        <div class="devis-section" id="devis">
            <h2 style="color: var(--accent); text-align: center; margin-bottom: 2rem;">Demande de Devis / Réparation</h2>

            <?php if ($success): ?>
                <div class="message success" style="padding:1rem; border-radius:5px; margin-bottom:1.5rem; text-align:center; background: var(--success); color: var(--text);"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="message error" style="padding:1rem; border-radius:5px; margin-bottom:1.5rem; text-align:center; background: var(--accent); color: var(--text);"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="devis-layout">
                <div class="brand-column">
                    <div class="brand-badge">SAMSUNG</div>
                    <div class="brand-badge">HUAWEI</div>
                    <div class="brand-badge">XIAOMI</div>
                    <div class="brand-badge">AMD</div>
                    <div class="brand-badge">OPPO</div>
                    <div class="brand-badge">PACKARD BELL</div>
                    <div class="brand-badge">SEAGATE</div>
                </div>

                <div class="devis-form-card">
                    <form method="POST" class="devis-form" enctype="multipart/form-data">
                        <?php echo csrfField(); ?>
                        <?php echo honeypotField(); ?>

                        <div class="form-group">
                            <label for="materiel">Type de matériel *</label>
                            <select id="materiel" name="materiel" required>
                                <option value="">Sélectionner un type</option>
                                <?php $materiels = ['Ordinateur portable', 'Ordinateur de bureau', 'Smartphone', 'Tablette', 'Console de jeux', 'Imprimante', 'Autre']; ?>
                                <?php foreach ($materiels as $m): ?>
                                    <option value="<?php echo htmlspecialchars($m); ?>" <?php echo (($_POST['materiel'] ?? '') === $m) ? 'selected' : ''; ?>><?php echo htmlspecialchars($m); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

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

                        <div class="form-group">
                            <label for="adresse">Adresse *</label>
                            <input type="text" id="adresse" name="adresse" value="<?php echo htmlspecialchars($_POST['adresse'] ?? ''); ?>" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="code_postal">Code Postal *</label>
                                <input type="text" id="code_postal" name="code_postal" value="<?php echo htmlspecialchars($_POST['code_postal'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="ville">Ville *</label>
                                <input type="text" id="ville" name="ville" value="<?php echo htmlspecialchars($_POST['ville'] ?? ''); ?>" required>
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

                        <div class="form-group">
                            <label for="boutique">Boutique</label>
                            <input type="text" id="boutique" value="ITS — Pierrefeu" readonly>
                            <input type="hidden" name="boutique" value="Pierrefeu">
                        </div>

                        <div class="form-group">
                            <label for="message">Infos Complémentaires</label>
                            <textarea id="message" name="message" placeholder="Décrivez la panne ou votre demande..."><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                        </div>

                        <div class="file-upload-group">
                            <label class="file-label" for="fichier">
                                📎 Ajouter un fichier
                                <input type="file" id="fichier" name="fichier" accept=".pdf,.docx,.jpg,.jpeg,.png,.rtf,.txt" onchange="updateFileName(this)">
                            </label>
                            <span class="file-name" id="file-name-display"></span>
                            <span class="file-hint">Formats acceptés : PDF, DOCX, JPG, PNG, RTF, TXT — 5 Mo max.</span>
                        </div>

                        <div class="checkbox-group">
                            <input type="checkbox" id="consentement" name="consentement" required>
                            <label for="consentement">J'accepte que mes données soient utilisées pour traiter ma demande de devis. *</label>
                        </div>

                        <button type="submit" class="btn-submit">Envoyer</button>
                    </form>
                </div>

                <div class="brand-column">
                    <div class="brand-badge">ASUS</div>
                    <div class="brand-badge">HP</div>
                    <div class="brand-badge">LENOVO</div>
                    <div class="brand-badge">KINGSTON</div>
                    <div class="brand-badge">ATI</div>
                    <div class="brand-badge">WD</div>
                    <div class="brand-badge">TOSHIBA</div>
                    <div class="brand-badge">DELL</div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>
