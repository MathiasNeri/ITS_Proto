<?php
require_once '../backend/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$action = $_POST['action'] ?? '';
$produitId = isset($_POST['produit_id']) ? (int) $_POST['produit_id'] : 0;
$redirectTo = $_POST['redirect'] ?? 'boutique.php';

// N'autorise que les redirections internes (pas d'open redirect)
if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.php(\?[a-zA-Z0-9_=&]*)?$/', $redirectTo)) {
    $redirectTo = 'boutique.php';
}

if (!csrfVerify()) {
    redirect($redirectTo);
}

if ($produitId > 0 && in_array($action, ['add', 'update'], true)) {
    $pdo = initDatabase();
    $stmt = $pdo->prepare('SELECT stock FROM produits WHERE id = ?');
    $stmt->execute([$produitId]);
    $stock = $stmt->fetchColumn();

    if ($stock === false) {
        redirect($redirectTo);
    }
    $stock = (int) $stock;
}

if ($produitId > 0) {
    switch ($action) {
        case 'add':
            $qty = isset($_POST['qty']) ? max(1, (int) $_POST['qty']) : 1;
            $desired = ($_SESSION['cart'][$produitId] ?? 0) + $qty;
            $_SESSION['cart'][$produitId] = min($desired, $stock);
            if ($stock <= 0) {
                unset($_SESSION['cart'][$produitId]);
            }
            break;

        case 'update':
            $qty = isset($_POST['qty']) ? (int) $_POST['qty'] : 0;
            $qty = min($qty, $stock);
            if ($qty <= 0) {
                unset($_SESSION['cart'][$produitId]);
            } else {
                $_SESSION['cart'][$produitId] = $qty;
            }
            break;

        case 'remove':
            unset($_SESSION['cart'][$produitId]);
            break;
    }
}

redirect($redirectTo);
