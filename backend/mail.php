<?php
// Envoi d'emails : client SMTP minimal (sans dépendance externe).
// Si aucun serveur SMTP n'est configuré (backend/config.php), les emails
// sont journalisés dans la table emails_log et consultables dans le panel
// admin, au lieu d'être réellement envoyés — pratique en développement.

/**
 * Point d'entrée unique pour envoyer un email transactionnel du site.
 * Retourne toujours true/false ; ne lève jamais d'exception (un email qui
 * échoue ne doit jamais casser une commande ou une inscription).
 *
 * $attachments (optionnel) : tableau de ['path' => ..., 'name' => ...] pour
 * joindre des fichiers (utilisé par les sauvegardes automatiques).
 */
function sendMail($destinataire, $sujet, $corpsHtml, array $attachments = []) {
    global $config;

    if (!isSmtpConfigured()) {
        return logMailInstead($destinataire, $sujet, $corpsHtml, 'journalise', null, $attachments);
    }

    try {
        smtpSend($destinataire, $sujet, $corpsHtml, $attachments);
        logMailInstead($destinataire, $sujet, $corpsHtml, 'envoye', null, $attachments);
        return true;
    } catch (Exception $e) {
        logError('Envoi email échoué : ' . $e->getMessage());
        logMailInstead($destinataire, $sujet, $corpsHtml, 'echec', $e->getMessage(), $attachments);
        return false;
    }
}

function logMailInstead($destinataire, $sujet, $corpsHtml, $statut, $erreur = null, array $attachments = []) {
    try {
        if (!empty($attachments)) {
            $noms = implode(', ', array_map(function ($a) { return $a['name']; }, $attachments));
            $corpsHtml .= '<p style="color:#8a9399;font-size:12px;">Pièce(s) jointe(s) : ' . htmlspecialchars($noms) . ' (non conservée(s) dans ce journal)</p>';
        }
        $pdo = initDatabase();
        $pdo->prepare('INSERT INTO emails_log (destinataire, sujet, corps_html, statut, erreur) VALUES (?, ?, ?, ?, ?)')
            ->execute([$destinataire, $sujet, $corpsHtml, $statut, $erreur]);
    } catch (Exception $e) {
        logError('Journalisation email échouée : ' . $e->getMessage());
    }
    return true;
}

/**
 * Client SMTP minimal (fsockopen), avec support STARTTLS et AUTH LOGIN.
 * Couvre les besoins d'un envoi transactionnel simple (Brevo, Gmail, OVH...).
 */
function smtpSend($destinataire, $sujet, $corpsHtml, array $attachments = []) {
    global $config;

    $host = $config['smtp_host'];
    $port = (int) $config['smtp_port'];
    $useTls = in_array($port, [587, 25], true);
    $useSsl = $port === 465;

    $transport = $useSsl ? 'ssl://' . $host : $host;
    $socket = @fsockopen($transport, $port, $errno, $errstr, 15);
    if (!$socket) {
        throw new RuntimeException("Connexion SMTP impossible ($errstr)");
    }

    $read = function () use ($socket) {
        $data = '';
        while ($line = fgets($socket, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };

    // Écrit en boucle jusqu'à épuisement du buffer : nécessaire pour les
    // messages volumineux (pièces jointes encodées en base64), fwrite()
    // n'étant pas garanti d'écrire tout le contenu en un seul appel.
    $write = function ($command) use ($socket) {
        $reste = $command . "\r\n";
        while (strlen($reste) > 0) {
            $ecrit = fwrite($socket, $reste);
            if ($ecrit === false) {
                throw new RuntimeException('Écriture SMTP échouée');
            }
            $reste = substr($reste, $ecrit);
        }
    };

    $expect = function ($response, $code) {
        if (strpos($response, (string) $code) !== 0) {
            throw new RuntimeException('Réponse SMTP inattendue : ' . trim($response));
        }
    };

    $expect($read(), 220);

    $write('EHLO ' . parse_url($config['base_url'], PHP_URL_HOST) ?: 'localhost');
    $expect($read(), 250);

    if ($useTls) {
        $write('STARTTLS');
        $expect($read(), 220);
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('Échec du chiffrement TLS');
        }
        $write('EHLO ' . (parse_url($config['base_url'], PHP_URL_HOST) ?: 'localhost'));
        $expect($read(), 250);
    }

    if (!empty($config['smtp_user'])) {
        $write('AUTH LOGIN');
        $expect($read(), 334);
        $write(base64_encode($config['smtp_user']));
        $expect($read(), 334);
        $write(base64_encode($config['smtp_password']));
        $expect($read(), 235);
    }

    $write('MAIL FROM:<' . $config['smtp_from'] . '>');
    $expect($read(), 250);
    $write('RCPT TO:<' . $destinataire . '>');
    $expect($read(), 250);
    $write('DATA');
    $expect($read(), 354);

    $headers = [
        'From: ' . $config['smtp_from_name'] . ' <' . $config['smtp_from'] . '>',
        'To: <' . $destinataire . '>',
        'Subject: =?UTF-8?B?' . base64_encode($sujet) . '?=',
        'MIME-Version: 1.0',
    ];

    if (empty($attachments)) {
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';
        $body = implode("\r\n", $headers) . "\r\n\r\n" . $corpsHtml . "\r\n.";
    } else {
        $boundary = 'its_' . uniqid();
        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';

        $parts = "--$boundary\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $corpsHtml . "\r\n";

        foreach ($attachments as $piece) {
            if (empty($piece['path']) || !is_file($piece['path'])) {
                continue;
            }
            $nomPiece = $piece['name'] ?? basename($piece['path']);
            $contenuBase64 = chunk_split(base64_encode(file_get_contents($piece['path'])));
            $parts .= "--$boundary\r\n"
                . 'Content-Type: application/octet-stream; name="' . $nomPiece . "\"\r\n"
                . "Content-Transfer-Encoding: base64\r\n"
                . 'Content-Disposition: attachment; filename="' . $nomPiece . "\"\r\n\r\n"
                . $contenuBase64 . "\r\n";
        }

        $parts .= "--$boundary--";
        $body = implode("\r\n", $headers) . "\r\n\r\n" . $parts . "\r\n.";
    }

    $write($body);
    $expect($read(), 250);

    $write('QUIT');
    fclose($socket);
}

/**
 * Petit gabarit HTML commun pour tous les emails du site (cohérent avec
 * la charte : logo, couleur accent, pied de page).
 */
function emailTemplate($titre, $contenuHtml) {
    return '
    <div style="font-family: Arial, sans-serif; max-width: 560px; margin: 0 auto; background: #f4f6f7; padding: 24px;">
        <div style="background: #202325; padding: 20px 24px; border-radius: 8px 8px 0 0;">
            <span style="color: #ffffff; font-weight: bold; font-size: 18px;">ITS — Informatique Téléphonie Service</span>
        </div>
        <div style="background: #ffffff; padding: 28px 24px; border-radius: 0 0 8px 8px; color: #1c2226;">
            <h1 style="font-size: 20px; color: #e74c3c; margin: 0 0 16px;">' . htmlspecialchars($titre) . '</h1>
            ' . $contenuHtml . '
        </div>
        <p style="text-align: center; color: #8a9399; font-size: 12px; margin-top: 16px;">
            ITS — Pierrefeu &middot; contact@its-reparation.fr
        </p>
    </div>';
}
