<?php
// Sauvegardes de la base SQLite.
//
// IMPORTANT (Render plan free) : le disque n'est PAS persistant, il est
// réinitialisé à chaque redéploiement — voir render.yaml. Une sauvegarde
// simplement écrite sur disque local serait donc perdue au prochain déploiement.
// C'est pourquoi la sauvegarde est aussi envoyée par email (pièce jointe) à
// l'admin dès qu'un serveur SMTP est configuré : c'est la copie qui survit
// réellement aux redéploiements. Le fichier local reste utile en développement
// et sur un hébergement à disque persistant.

function cheminDossierSauvegardes() {
    return __DIR__ . '/../database/backups';
}

/**
 * Crée une sauvegarde cohérente de la base via VACUUM INTO (SQLite ≥ 3.27) :
 * contrairement à une simple copie de fichier, ça ne risque pas de capturer
 * une écriture en cours. Retourne le chemin du fichier créé.
 */
function creerSauvegardeBaseDeDonnees() {
    $dossier = cheminDossierSauvegardes();
    if (!is_dir($dossier)) {
        mkdir($dossier, 0755, true);
    }

    $nomFichier = 'its-backup-' . date('Y-m-d_His') . '.sqlite';
    $cheminComplet = $dossier . '/' . $nomFichier;

    $pdo = initDatabase();
    $pdo->exec("VACUUM INTO '" . str_replace("'", "''", $cheminComplet) . "'");

    nettoyerAnciennesSauvegardes($dossier, 7);

    return $cheminComplet;
}

/**
 * Supprime les sauvegardes locales plus vieilles que $joursConserves, pour
 * éviter une croissance illimitée du dossier.
 */
function nettoyerAnciennesSauvegardes($dossier, $joursConserves) {
    $fichiers = glob($dossier . '/its-backup-*.sqlite');
    if (!$fichiers) {
        return;
    }
    $limite = time() - ($joursConserves * 86400);
    foreach ($fichiers as $fichier) {
        if (filemtime($fichier) < $limite) {
            @unlink($fichier);
        }
    }
}

/**
 * Envoie la sauvegarde par email à l'admin. Retourne false (sans erreur
 * bloquante) si SMTP n'est pas configuré : la sauvegarde locale existe
 * toujours, seul l'envoi n'a pas eu lieu.
 */
function envoyerSauvegardeParEmail($cheminFichier) {
    if (!isSmtpConfigured()) {
        logError('Sauvegarde créée (' . basename($cheminFichier) . ') mais SMTP non configuré : non envoyée par email, donc non persistante si le disque ne l\'est pas.');
        return false;
    }

    $tailleMo = round(filesize($cheminFichier) / 1024 / 1024, 2);
    if ($tailleMo > 20) {
        logError('Sauvegarde trop volumineuse pour un envoi par email (' . $tailleMo . ' Mo) : ' . basename($cheminFichier));
        return false;
    }

    $contenu = '
        <p>Sauvegarde automatique de la base de données du site, en pièce jointe.</p>
        <p>Fichier : ' . htmlspecialchars(basename($cheminFichier)) . ' (' . $tailleMo . ' Mo)</p>
        <p>Conservez cet email : c\'est la copie qui survit aux redéploiements sur un hébergement à disque non persistant.</p>';

    return sendMail(
        'admin@its-reparation.fr',
        'Sauvegarde base de données ITS — ' . date('d/m/Y'),
        emailTemplate('Sauvegarde automatique', $contenu),
        [['path' => $cheminFichier, 'name' => basename($cheminFichier)]]
    );
}

/**
 * Déclenche une sauvegarde si la dernière date de plus de 24h. Pensée pour
 * être appelée « à l'improviste » (ex. au chargement du panel admin) plutôt
 * que par un vrai cron, absent des plans gratuits : tant qu'un admin se
 * connecte au moins une fois par jour, la sauvegarde part comme prévu.
 * Sans connexion admin pendant 24h, elle attend simplement la prochaine
 * visite (aucune perte de données pour autant, juste un retard de la copie).
 */
function sauvegardeQuotidienneSiNecessaire() {
    $dossier = cheminDossierSauvegardes();
    $marqueur = $dossier . '/.last_backup';

    if (is_file($marqueur) && (time() - filemtime($marqueur)) < 86400) {
        return;
    }

    if (!is_dir($dossier)) {
        mkdir($dossier, 0755, true);
    }
    // Pose le marqueur avant même la fin de la sauvegarde, pour éviter un
    // déclenchement en double si deux requêtes admin arrivent en même temps.
    touch($marqueur);

    try {
        $chemin = creerSauvegardeBaseDeDonnees();
        envoyerSauvegardeParEmail($chemin);
    } catch (Exception $e) {
        logError('Sauvegarde automatique échouée : ' . $e->getMessage());
    }
}
