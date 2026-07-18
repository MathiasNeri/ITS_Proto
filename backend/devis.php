<?php
// Gestion des demandes de devis/réparation : upload de fichier joint
// (photo de l'appareil, facture, etc.) stocké hors du dossier public et
// jamais accessible directement par URL — uniquement via
// public/devis-fichier.php, réservé à l'admin.

/**
 * Valide et déplace le fichier joint envoyé avec le formulaire de devis.
 * Retourne ['nom' => nom d'origine|null, 'chemin' => nom stocké|null, 'erreur' => string|null].
 * Un champ fichier vide n'est pas une erreur (upload optionnel).
 */
function traiterUploadDevis($fichierPost) {
    if (empty($fichierPost) || ($fichierPost['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['nom' => null, 'chemin' => null, 'erreur' => null];
    }

    if ($fichierPost['error'] !== UPLOAD_ERR_OK) {
        return ['nom' => null, 'chemin' => null, 'erreur' => 'Erreur lors de l\'envoi du fichier.'];
    }

    $maxSize = 5 * 1024 * 1024;
    if ($fichierPost['size'] > $maxSize) {
        return ['nom' => null, 'chemin' => null, 'erreur' => 'Le fichier dépasse la taille maximale de 5 Mo.'];
    }

    $extensionsAutorisees = ['pdf', 'docx', 'jpg', 'jpeg', 'png', 'rtf', 'txt'];
    $extension = strtolower(pathinfo($fichierPost['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $extensionsAutorisees, true)) {
        return ['nom' => null, 'chemin' => null, 'erreur' => 'Format de fichier non autorisé (PDF, DOCX, JPG, PNG, RTF, TXT uniquement).'];
    }

    $dossier = __DIR__ . '/../database/uploads/devis';
    if (!is_dir($dossier)) {
        mkdir($dossier, 0755, true);
    }

    $nomStocke = bin2hex(random_bytes(16)) . '.' . $extension;

    if (!move_uploaded_file($fichierPost['tmp_name'], $dossier . '/' . $nomStocke)) {
        return ['nom' => null, 'chemin' => null, 'erreur' => 'Impossible d\'enregistrer le fichier.'];
    }

    return ['nom' => basename($fichierPost['name']), 'chemin' => $nomStocke, 'erreur' => null];
}

/**
 * Envoie les emails (client + admin) après une demande de devis.
 */
function envoyerEmailsDevis(array $devis) {
    $contenuClient = '
        <p>Bonjour ' . htmlspecialchars($devis['prenom']) . ',</p>
        <p>Votre demande de devis pour <strong>' . htmlspecialchars($devis['materiel']) . '</strong> a bien été reçue.</p>
        <p>Nous revenons vers vous rapidement avec une estimation. Vous pouvez nous joindre au besoin par téléphone ou par email.</p>';
    sendMail($devis['email'], 'Votre demande de devis a bien été reçue', emailTemplate('Demande de devis reçue', $contenuClient));

    $contenuAdmin = '
        <p>Nouvelle demande de devis :</p>
        <p><strong>' . htmlspecialchars($devis['prenom'] . ' ' . $devis['nom']) . '</strong> — ' . htmlspecialchars($devis['materiel']) . '</p>
        <p>' . htmlspecialchars($devis['email']) . ' / ' . htmlspecialchars($devis['telephone']) . '</p>
        <p>' . nl2br(htmlspecialchars($devis['message'] ?? '')) . '</p>';
    sendMail('admin@its-reparation.fr', 'Nouvelle demande de devis', emailTemplate('Nouvelle demande de devis', $contenuAdmin));
}
