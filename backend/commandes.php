<?php
// Logique métier des commandes : création, application d'un code promo,
// et confirmation de paiement (partagée entre le vrai flux Stripe et le
// mode simulation, pour ne jamais dupliquer la logique sensible).

/**
 * Vérifie un code promo et calcule la remise applicable.
 * Retourne ['remise' => float, 'code' => string|null, 'erreur' => string|null].
 */
function calculerRemise(PDO $pdo, $code, $sousTotal) {
    $code = strtoupper(trim($code));
    if ($code === '') {
        return ['remise' => 0, 'code' => null, 'erreur' => null];
    }

    $stmt = $pdo->prepare('SELECT * FROM codes_promo WHERE code = ? AND actif = 1');
    $stmt->execute([$code]);
    $promo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$promo) {
        return ['remise' => 0, 'code' => null, 'erreur' => 'Code promo invalide ou expiré.'];
    }

    if (!empty($promo['date_expiration']) && $promo['date_expiration'] < date('Y-m-d')) {
        return ['remise' => 0, 'code' => null, 'erreur' => 'Ce code promo a expiré.'];
    }

    if ($promo['usage_max'] !== null && (int) $promo['usage_compte'] >= (int) $promo['usage_max']) {
        return ['remise' => 0, 'code' => null, 'erreur' => 'Ce code promo a atteint son nombre d\'utilisations maximum.'];
    }

    if ($promo['type'] === 'pourcentage') {
        $remise = round($sousTotal * ((float) $promo['valeur'] / 100), 2);
    } else {
        $remise = min((float) $promo['valeur'], $sousTotal);
    }

    return ['remise' => $remise, 'code' => $code, 'erreur' => null];
}

/**
 * Calcule les frais de livraison selon le mode choisi et le sous-total.
 */
function calculerFraisLivraison($modeLivraison, $sousTotal) {
    global $config;

    if ($modeLivraison !== 'colissimo') {
        return 0.0;
    }
    if ($sousTotal >= (float) $config['livraison_gratuite_des']) {
        return 0.0;
    }
    return (float) $config['frais_livraison_colissimo'];
}

/**
 * Crée une commande en base avec le statut "en attente de paiement" et ses
 * lignes. Le stock n'est PAS encore décrémenté à ce stade : il ne l'est
 * qu'à la confirmation réelle du paiement (voir confirmerPaiementCommande),
 * pour ne jamais bloquer du stock sur une commande jamais payée.
 */
function creerCommandeEnAttente(PDO $pdo, array $lignes, $nom, $email, $telephone, $adresse, $modeLivraison, $fraisLivraison, $codePromo, $remise, $total, $userId) {
    $numero = 'ITS-' . date('ymd') . '-' . random_int(1000, 9999);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO commandes
            (numero, user_id, nom, email, telephone, adresse, total, statut, mode_livraison, frais_livraison, code_promo, remise)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$numero, $userId, $nom, $email, $telephone, $adresse, $total, 'en_attente_paiement', $modeLivraison, $fraisLivraison, $codePromo, $remise]);
        $commandeId = (int) $pdo->lastInsertId();

        $stmtLigne = $pdo->prepare('INSERT INTO commande_lignes (commande_id, produit_id, nom_produit, prix_unitaire, quantite) VALUES (?, ?, ?, ?, ?)');
        foreach ($lignes as $ligne) {
            $stmtLigne->execute([$commandeId, $ligne['produit']['id'], $ligne['produit']['nom'], $ligne['produit']['prix'], $ligne['qty']]);
        }

        $pdo->commit();
        return ['id' => $commandeId, 'numero' => $numero];
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Confirme le paiement d'une commande : décrémente le stock (de façon
 * atomique, protégée contre la survente), passe la commande en statut
 * "nouvelle" (= payée, à traiter), et envoie les emails. Idempotente :
 * appeler cette fonction plusieurs fois pour la même commande (webhook +
 * retour navigateur, par exemple) ne la traite qu'une seule fois.
 */
function confirmerPaiementCommande(PDO $pdo, $commandeId) {
    $stmt = $pdo->prepare('SELECT * FROM commandes WHERE id = ?');
    $stmt->execute([$commandeId]);
    $commande = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$commande) {
        return null;
    }

    // Déjà traitée (webhook + retour navigateur arrivés tous les deux) : on
    // ne retraite pas, on renvoie juste l'état actuel.
    if ($commande['statut'] !== 'en_attente_paiement') {
        return $commande;
    }

    $stmtLignes = $pdo->prepare('SELECT * FROM commande_lignes WHERE commande_id = ?');
    $stmtLignes->execute([$commandeId]);
    $lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);

    $pdo->beginTransaction();
    try {
        $stmtStock = $pdo->prepare('UPDATE produits SET stock = stock - ? WHERE id = ? AND stock >= ?');
        foreach ($lignes as $ligne) {
            if ($ligne['produit_id']) {
                $stmtStock->execute([$ligne['quantite'], $ligne['produit_id'], $ligne['quantite']]);
            }
        }

        $pdo->prepare('UPDATE commandes SET statut = ? WHERE id = ?')->execute(['nouvelle', $commandeId]);

        if (!empty($commande['code_promo'])) {
            $pdo->prepare('UPDATE codes_promo SET usage_compte = usage_compte + 1 WHERE code = ?')->execute([$commande['code_promo']]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        logError('Confirmation paiement échouée pour commande ' . $commandeId . ' : ' . $e->getMessage());
        throw $e;
    }

    envoyerEmailsCommandePayee($commande, $lignes);

    $commande['statut'] = 'nouvelle';
    return $commande;
}

/**
 * Envoie l'email de confirmation au client et la notification au admin.
 * Ne doit jamais faire échouer la commande si l'envoi échoue (sendMail
 * gère déjà ça en interne).
 */
function envoyerEmailsCommandePayee(array $commande, array $lignes) {
    $lignesHtml = '';
    foreach ($lignes as $l) {
        $lignesHtml .= '<tr>
            <td style="padding:6px 0;">' . htmlspecialchars($l['nom_produit']) . ' × ' . (int) $l['quantite'] . '</td>
            <td style="padding:6px 0; text-align:right;">' . number_format($l['prix_unitaire'] * $l['quantite'], 2, ',', ' ') . ' €</td>
        </tr>';
    }

    $livraisonLabel = $commande['mode_livraison'] === 'colissimo' ? 'Livraison à domicile (Colissimo)' : 'Retrait en boutique';

    $contenuClient = '
        <p>Bonjour ' . htmlspecialchars($commande['nom']) . ',</p>
        <p>Votre commande <strong>' . htmlspecialchars($commande['numero']) . '</strong> est confirmée. Voici le récapitulatif :</p>
        <table style="width:100%; border-collapse: collapse; margin: 16px 0;">' . $lignesHtml . '</table>
        <p><strong>Total réglé : ' . number_format($commande['total'], 2, ',', ' ') . ' €</strong></p>
        <p>Mode de retrait : ' . htmlspecialchars($livraisonLabel) . '<br>Adresse : ' . nl2br(htmlspecialchars($commande['adresse'])) . '</p>
        <p>Nous vous tiendrons informé de l\'avancement de votre commande.</p>';

    sendMail($commande['email'], 'Confirmation de votre commande ' . $commande['numero'], emailTemplate('Commande confirmée', $contenuClient));

    $contenuAdmin = '
        <p>Nouvelle commande payée : <strong>' . htmlspecialchars($commande['numero']) . '</strong></p>
        <p>Client : ' . htmlspecialchars($commande['nom']) . ' (' . htmlspecialchars($commande['email']) . ($commande['telephone'] ? ', ' . htmlspecialchars($commande['telephone']) : '') . ')</p>
        <table style="width:100%; border-collapse: collapse; margin: 16px 0;">' . $lignesHtml . '</table>
        <p><strong>Total : ' . number_format($commande['total'], 2, ',', ' ') . ' €</strong></p>
        <p>Mode de retrait : ' . htmlspecialchars($livraisonLabel) . '<br>Adresse : ' . nl2br(htmlspecialchars($commande['adresse'])) . '</p>';

    sendMail('admin@its-reparation.fr', 'Nouvelle commande ' . $commande['numero'], emailTemplate('Nouvelle commande', $contenuAdmin));
}
