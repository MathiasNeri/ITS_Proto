<?php
// Upload et affichage des images produits/composants : contrairement aux
// pièces jointes de devis (backend/devis.php), ces images sont publiques
// (photos de produits) et stockées directement dans public/images/, servies
// comme des fichiers statiques classiques.

/**
 * Valide et déplace une image envoyée depuis un formulaire admin.
 * Retourne ['fichier' => nom stocké|null, 'erreur' => string|null].
 * Un champ fichier vide n'est pas une erreur (upload optionnel, l'emoji
 * reste utilisé en repli tant qu'aucune image n'est envoyée).
 */
function traiterUploadImage($fichierPost, $sousDossier) {
    if (empty($fichierPost) || ($fichierPost['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['fichier' => null, 'erreur' => null];
    }

    if ($fichierPost['error'] !== UPLOAD_ERR_OK) {
        return ['fichier' => null, 'erreur' => 'Erreur lors de l\'envoi de l\'image.'];
    }

    $maxSize = 3 * 1024 * 1024;
    if ($fichierPost['size'] > $maxSize) {
        return ['fichier' => null, 'erreur' => 'L\'image dépasse la taille maximale de 3 Mo.'];
    }

    $extensionsAutorisees = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    $extension = strtolower(pathinfo($fichierPost['name'], PATHINFO_EXTENSION));
    if (!array_key_exists($extension, $extensionsAutorisees)) {
        return ['fichier' => null, 'erreur' => 'Format d\'image non autorisé (JPG, PNG ou WEBP uniquement).'];
    }

    // Vérifie que le contenu est bien une image (pas juste l'extension) avant de la stocker.
    $infos = @getimagesize($fichierPost['tmp_name']);
    if ($infos === false) {
        return ['fichier' => null, 'erreur' => 'Le fichier envoyé n\'est pas une image valide.'];
    }

    $dossier = __DIR__ . '/../public/images/' . $sousDossier;
    if (!is_dir($dossier)) {
        mkdir($dossier, 0755, true);
    }

    $nomStocke = bin2hex(random_bytes(16)) . '.' . $extension;

    if (!move_uploaded_file($fichierPost['tmp_name'], $dossier . '/' . $nomStocke)) {
        return ['fichier' => null, 'erreur' => 'Impossible d\'enregistrer l\'image.'];
    }

    return ['fichier' => $nomStocke, 'erreur' => null];
}

/**
 * Supprime un fichier image du disque (utilisé au remplacement ou à la
 * suppression d'un produit/composant). Ne fait rien si le fichier n'existe pas.
 */
function supprimerImage($sousDossier, $nomFichier) {
    if (empty($nomFichier)) {
        return;
    }
    $chemin = __DIR__ . '/../public/images/' . $sousDossier . '/' . basename($nomFichier);
    if (is_file($chemin)) {
        unlink($chemin);
    }
}

/**
 * HTML à afficher pour un produit/composant : son image si elle existe,
 * sinon son emoji en repli. $item doit contenir les clés 'image' et 'icone'.
 */
function visuelHtml(array $item, $sousDossier, $altText, $class = '') {
    $classAttr = $class !== '' ? ' class="' . htmlspecialchars($class) . '"' : '';
    if (!empty($item['image'])) {
        $url = 'images/' . $sousDossier . '/' . rawurlencode($item['image']);
        return '<img src="' . htmlspecialchars($url) . '" alt="' . htmlspecialchars($altText) . '"' . $classAttr . ' loading="lazy">';
    }
    return '<span' . $classAttr . '>' . $item['icone'] . '</span>';
}
