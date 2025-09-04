<?php
require_once '../backend/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté et est admin
if (!checkAuth() || $_SESSION['user_role'] !== 'admin') {
    redirect('connexion.php');
}

// Traitement des actions
$message = '';
$error = '';

if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete_rdv' && isset($_POST['rdv_id'])) {
        try {
            $pdo = initDatabase();
            $stmt = $pdo->prepare("DELETE FROM rdv WHERE id = ?");
            $stmt->execute([$_POST['rdv_id']]);
            $message = 'Rendez-vous supprimé avec succès';
        } catch (PDOException $e) {
            $error = 'Erreur lors de la suppression';
            logError($e->getMessage());
        }
    }
}

// Récupérer les données
try {
    $pdo = initDatabase();
    
    // Rendez-vous
    $stmt = $pdo->query("SELECT * FROM rdv ORDER BY created_at DESC");
    $rdv_list = $stmt->fetchAll();
    
    // Utilisateurs
    $stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
    $users_list = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Erreur lors du chargement des données';
    logError($e->getMessage());
}
?>
<?php include 'header.php'; ?>

<style>
    .admin-container {
        max-width: 1200px;
        margin: 2rem auto;
        padding: 0 2rem;
    }
    
    .admin-title {
        font-size: 2rem;
        margin-bottom: 2rem;
        color: #e74c3c;
        text-align: center;
    }
    
    .admin-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
        gap: 2rem;
        margin-bottom: 2rem;
    }
    
    .admin-card {
        background: #2c3e50;
        padding: 2rem;
        border-radius: 10px;
        border: 2px solid #34495e;
    }
    
    .card-title {
        font-size: 1.3rem;
        margin-bottom: 1rem;
        color: #e74c3c;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1rem;
    }
    
    .data-table th,
    .data-table td {
        padding: 0.8rem;
        text-align: left;
        border-bottom: 1px solid #34495e;
    }
    
    .data-table th {
        background: #34495e;
        color: #e74c3c;
        font-weight: bold;
    }
    
    .data-table td {
        color: #bdc3c7;
    }
    
    .btn-delete {
        background: #e74c3c;
        color: white;
        padding: 0.5rem 1rem;
        border: none;
        border-radius: 3px;
        cursor: pointer;
        font-size: 0.8rem;
    }
    
    .btn-delete:hover {
        background: #c0392b;
    }
    
    .message {
        padding: 1rem;
        border-radius: 5px;
        margin-bottom: 1rem;
        text-align: center;
    }
    
    .message.success {
        background: #27ae60;
        color: white;
    }
    
    .message.error {
        background: #e74c3c;
        color: white;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }
    
    .stat-card {
        background: #34495e;
        padding: 1.5rem;
        border-radius: 8px;
        text-align: center;
    }
    
    .stat-number {
        font-size: 2rem;
        font-weight: bold;
        color: #e74c3c;
    }
    
    .stat-label {
        color: #bdc3c7;
        margin-top: 0.5rem;
    }
</style>

<main class="main-content">
    <div class="admin-container">
        <h1 class="admin-title">Panel d'Administration</h1>
        
        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($rdv_list); ?></div>
                <div class="stat-label">Rendez-vous</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($users_list); ?></div>
                <div class="stat-label">Utilisateurs</div>
            </div>
        </div>
        
        <div class="admin-grid">
            <!-- Rendez-vous -->
            <div class="admin-card">
                <h3 class="card-title">Rendez-vous récents</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Service</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($rdv_list, 0, 10) as $rdv): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($rdv['nom'] . ' ' . $rdv['prenom']); ?></td>
                            <td><?php echo htmlspecialchars($rdv['email']); ?></td>
                            <td><?php echo htmlspecialchars($rdv['service']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($rdv['date_rdv'])); ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="delete_rdv">
                                    <input type="hidden" name="rdv_id" value="<?php echo $rdv['id']; ?>">
                                    <button type="submit" class="btn-delete" onclick="return confirm('Supprimer ce rendez-vous ?')">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Utilisateurs -->
            <div class="admin-card">
                <h3 class="card-title">Utilisateurs</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Inscription</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users_list as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['nom'] . ' ' . $user['prenom']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['role']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>
