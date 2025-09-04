# ITS - Site Complet PHP

## 🏗️ Architecture Organisée

```
ITS_Proto/
├── public/              # Frontend - Fichiers accessibles publiquement
│   ├── index.php        # Page principale
│   ├── login.php        # Connexion
│   ├── register.php     # Inscription
│   ├── admin.php        # Panel administrateur
│   ├── logout.php       # Déconnexion
│   └── .htaccess        # Configuration Apache
├── backend/             # Backend - Configuration et logique
│   └── config.php       # Configuration et base de données
├── database/            # Base de données (sécurisée)
│   └── .gitkeep         # Maintient le dossier dans Git
├── frontend/            # Assets statiques (CSS/JS)
│   └── assets/
├── .htaccess            # Configuration racine
├── deploy.sh            # Script de déploiement
├── DEPLOYMENT.md        # Guide de déploiement OVH
└── README.md           # Ce fichier
```

## 🚀 Démarrage Local

### Option 1: Serveur PHP local
```bash
php -S localhost:8000 -t public
```
Puis ouvrez : http://localhost:8000

### Option 2: Serveur web classique
- Copiez le contenu de `public/` dans votre dossier web
- Copiez `backend/` et `database/` dans le dossier parent
- Ouvrez : http://localhost/ITS_Proto

## ✨ Fonctionnalités

- ✅ **Authentification complète** - Login/Register avec email
- ✅ **Panel Admin** - Gestion des RDV et utilisateurs
- ✅ **Base de données SQLite** - Pas de configuration serveur
- ✅ **Formulaire RDV** - Sauvegarde en base de données
- ✅ **Design responsive** - Fonctionne sur mobile/desktop
- ✅ **Architecture sécurisée** - Séparation front/back
- ✅ **Prêt pour OVH** - Configuration de déploiement incluse

## 🔐 Comptes par défaut

**Administrateur :**
- Email: `admin@its-reparation.fr`
- Mot de passe: `admin123`

**Utilisateurs :** Peuvent s'inscrire via le formulaire d'inscription

## 🌐 Déploiement OVH

Voir le fichier `DEPLOYMENT.md` pour le guide complet de déploiement sur OVH.

### Résumé rapide :
1. Modifiez `backend/config.php` avec votre domaine
2. Uploadez tous les fichiers sur OVH
3. Vérifiez les permissions
4. Testez le site

## 🔧 Configuration

### Local
- Aucune configuration nécessaire
- Base de données créée automatiquement

### Production (OVH)
- Modifiez `base_url` dans `backend/config.php`
- Vérifiez les permissions des dossiers
- Activez SSL/HTTPS

## 📁 Sécurité

- **Backend** : Non accessible via web
- **Database** : Non accessible via web
- **Sessions** : Configuration sécurisée
- **HTTPS** : Redirection automatique

**Architecture professionnelle, prête pour la production !** 🎉