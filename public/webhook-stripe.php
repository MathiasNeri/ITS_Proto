<?php
// Point d'entrée webhook Stripe (à configurer dans le dashboard Stripe :
// URL https://votre-domaine.fr/webhook-stripe.php, événement
// "checkout.session.completed"). C'est la confirmation de paiement fiable
// en production : contrairement au retour navigateur (confirmation.php),
// elle arrive même si le client ferme l'onglet avant la redirection.
require_once __DIR__ . '/../backend/config.php';

header('Content-Type: application/json');

$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (!isStripeConfigured() || !stripeVerifyWebhookSignature($payload, $sigHeader, $config['stripe_webhook_secret'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Signature invalide']);
    exit();
}

$event = json_decode($payload, true);

if (($event['type'] ?? '') === 'checkout.session.completed') {
    $session = $event['data']['object'] ?? [];
    $commandeId = $session['metadata']['commande_id'] ?? null;

    if ($commandeId && ($session['payment_status'] ?? '') === 'paid') {
        try {
            $pdo = initDatabase();
            confirmerPaiementCommande($pdo, $commandeId);
        } catch (Exception $e) {
            logError('Webhook Stripe : ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Traitement échoué']);
            exit();
        }
    }
}

http_response_code(200);
echo json_encode(['received' => true]);
