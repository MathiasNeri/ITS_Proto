# Guide de Déploiement OVH

## 📋 Préparation

### 1. Configuration du domaine
Modifiez dans `backend/config.php` :
```php
'base_url' => 'https://votre-domaine.com',
```

### 2. Structure de déploiement
```
www/ (racine OVH)
├── public/          # Fichiers accessibles publiquement
│   ├── index.php
│   ├── login.php
│   ├── register.php
│   ├── admin.php
│   ├── logout.php
│   └── .htaccess
├── backend/         # Configuration (non accessible)
│   └── config.php
├── database/        # Base de données (non accessible)
│   └── its.sqlite
└── .htaccess        # Configuration racine
```

## 🚀 Déploiement

### Option 1: Upload manuel
1. Connectez-vous à votre espace OVH
2. Uploadez tous les fichiers dans le dossier `www/`
3. Vérifiez les permissions (755 pour les dossiers, 644 pour les fichiers)

### Option 2: Script automatique
```bash
chmod +x deploy.sh
./deploy.sh
```

### Option 3: FTP/SFTP
```bash
# Avec lftp
lftp -u username -e "mirror -R . /www/" ftp.cluster0XX.hosting.ovh.net
```

## 🔧 Configuration OVH

### 1. PHP
- Version PHP : 8.0+ (recommandé 8.1+)
- Extensions requises : PDO, PDO_SQLite

### 2. Base de données
- SQLite est inclus avec PHP
- Pas de configuration MySQL nécessaire

### 3. SSL/HTTPS
- Activez le SSL dans votre espace OVH
- Le .htaccess redirige automatiquement vers HTTPS

## 🔐 Sécurité

### Fichiers protégés
- `backend/` : Non accessible via web
- `database/` : Non accessible via web
- `*.sqlite` : Fichiers de base de données protégés

### Permissions recommandées
```bash
chmod 755 backend/
chmod 755 database/
chmod 644 database/its.sqlite
chmod 644 backend/config.php
```

## ✅ Vérification

1. **Test du site** : https://votre-domaine.com
2. **Test de connexion** : https://votre-domaine.com/login.php
3. **Test admin** : admin@its-reparation.fr / admin123
4. **Test inscription** : Créer un compte utilisateur
5. **Test RDV** : Prendre un rendez-vous

## 🐛 Dépannage

### Erreur 500
- Vérifiez les permissions des fichiers
- Vérifiez les logs d'erreur PHP dans OVH

### Base de données
- Vérifiez que le dossier `database/` est accessible en écriture
- Vérifiez l'extension PDO_SQLite

### SSL
- Vérifiez que le certificat SSL est actif
- Testez la redirection HTTPS

## 📞 Support

- **OVH** : Support technique hébergement
- **ITS** : admin@its-reparation.fr
