<?php
// Intégration Stripe minimale (appels REST directs, sans SDK/Composer).
// Utilisée uniquement quand isStripeConfigured() est vrai — sinon
// commande.php bascule en mode simulation de paiement.

/**
 * Appelle l'API Stripe en POST (form-urlencoded, comme attendu par Stripe).
 * $params peut contenir des tableaux imbriqués (ex: line_items[0][price]=...),
 * on les aplatit nous-mêmes car Stripe attend le format PHP classique.
 */
function stripeRequest($method, $endpoint, array $params = []) {
    global $config;

    $ch = curl_init('https://api.stripe.com/v1/' . ltrim($endpoint, '/'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $config['stripe_secret_key'] . ':');
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    } elseif ($method === 'GET' && !empty($params)) {
        curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/' . ltrim($endpoint, '/') . '?' . http_build_query($params));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Erreur réseau Stripe : ' . $curlError);
    }

    $data = json_decode($response, true);

    if ($httpCode >= 400) {
        $message = $data['error']['message'] ?? 'Erreur Stripe inconnue';
        throw new RuntimeException('Stripe : ' . $message);
    }

    return $data;
}

/**
 * Crée une session Stripe Checkout pour une commande et renvoie son URL
 * de paiement (à rediriger le client dessus).
 *
 * $lignes : tableau de ['nom' => ..., 'prix' => float (euros), 'quantite' => int]
 */
function stripeCreateCheckoutSession(array $lignes, $fraisLivraison, $successUrl, $cancelUrl, array $metadata = []) {
    $params = [
        'mode' => 'payment',
        'success_url' => $successUrl,
        'cancel_url' => $cancelUrl,
        'payment_method_types' => ['card'],
    ];

    $i = 0;
    foreach ($lignes as $ligne) {
        $params['line_items'][$i]['price_data']['currency'] = 'eur';
        $params['line_items'][$i]['price_data']['unit_amount'] = (int) round($ligne['prix'] * 100);
        $params['line_items'][$i]['price_data']['product_data']['name'] = $ligne['nom'];
        $params['line_items'][$i]['quantity'] = (int) $ligne['quantite'];
        $i++;
    }

    if ($fraisLivraison > 0) {
        $params['line_items'][$i]['price_data']['currency'] = 'eur';
        $params['line_items'][$i]['price_data']['unit_amount'] = (int) round($fraisLivraison * 100);
        $params['line_items'][$i]['price_data']['product_data']['name'] = 'Frais de livraison';
        $params['line_items'][$i]['quantity'] = 1;
    }

    foreach ($metadata as $key => $value) {
        $params['metadata'][$key] = $value;
    }

    return stripeRequest('POST', 'checkout/sessions', $params);
}

/**
 * Récupère l'état d'une session Stripe Checkout (utilisé sur la page de
 * retour pour confirmer le paiement sans dépendre uniquement du webhook).
 */
function stripeRetrieveSession($sessionId) {
    return stripeRequest('GET', 'checkout/sessions/' . urlencode($sessionId));
}

/**
 * Vérifie la signature d'un webhook Stripe (empêche qu'un tiers déclenche
 * de fausses confirmations de paiement).
 */
function stripeVerifyWebhookSignature($payload, $sigHeader, $secret) {
    if (empty($sigHeader) || empty($secret)) {
        return false;
    }

    $parts = [];
    foreach (explode(',', $sigHeader) as $part) {
        [$k, $v] = array_pad(explode('=', $part, 2), 2, '');
        $parts[$k][] = $v;
    }

    $timestamp = $parts['t'][0] ?? null;
    $signatures = $parts['v1'] ?? [];

    if (!$timestamp || empty($signatures)) {
        return false;
    }

    $signedPayload = $timestamp . '.' . $payload;
    $expected = hash_hmac('sha256', $signedPayload, $secret);

    foreach ($signatures as $sig) {
        if (hash_equals($expected, $sig)) {
            return true;
        }
    }

    return false;
}
