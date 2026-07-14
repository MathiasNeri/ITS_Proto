# Migration SQLite → MySQL

Le site tourne en SQLite en développement (zéro configuration, fichier
unique `database/its.sqlite`). C'est très bien pour développer et tester,
mais SQLite verrouille tout le fichier à chaque écriture : dès que
plusieurs commandes arrivent en même temps en production, ça devient un
goulot d'étranglement. Pour une vraie mise en ligne, passer à MySQL (quasi
systématiquement disponible chez tous les hébergeurs, dont OVH) est
recommandé.

Le schéma MySQL (`backend/schema_mysql.sql`) a été **testé réellement**
contre un serveur MySQL 8.3 local : les 11 tables se créent sans erreur,
et une connexion PHP `pdo_mysql` lit/écrit correctement dessus.

## Étapes de bascule

### 1. Créer la base chez l'hébergeur
Sur l'espace client OVH (ou équivalent) : créer une base MySQL, noter
l'hôte, le nom de la base, l'utilisateur et le mot de passe fournis.

### 2. Importer le schéma
```bash
mysql -h HOTE -u UTILISATEUR -p NOM_BASE < backend/schema_mysql.sql
```

### 3. Adapter `backend/config.php`
Remplacer la ligne SQLite :
```php
'db_path' => 'sqlite:' . __DIR__ . '/../database/its.sqlite',
```
par :
```php
'db_path' => 'mysql:host=HOTE;dbname=NOM_BASE;charset=utf8mb4',
'db_user' => 'UTILISATEUR',
'db_pass' => 'MOT_DE_PASSE',
```
Et dans `initDatabase()`, remplacer :
```php
$pdo = new PDO($config['db_path']);
```
par :
```php
$pdo = new PDO($config['db_path'], $config['db_user'] ?? null, $config['db_pass'] ?? null);
```

### 4. Retirer le code spécifique SQLite
`initDatabase()` contient deux choses propres à SQLite à supprimer/adapter
une fois sur MySQL :
- `$pdo->exec('PRAGMA foreign_keys = ON');` → à supprimer (MySQL gère les
  clés étrangères nativement, déjà actives dans `schema_mysql.sql`).
- Les blocs `CREATE TABLE IF NOT EXISTS ...` et migrations `ALTER TABLE`
  ne sont plus nécessaires : le schéma est déjà posé par l'import SQL de
  l'étape 2. Le plus simple est de garder `initDatabase()` uniquement pour
  la connexion + le seed du compte admin/catalogue de démo (à adapter ou
  retirer selon si vous voulez garder les données de démo en prod).

### 5. Migrer les données existantes (si besoin)
Si des vraies données existent déjà en SQLite au moment de la bascule
(commandes clients réelles, comptes utilisateurs), il faut les exporter et
les réimporter — le format n'est pas strictement identique (types de
données, guillemets). Pour un volume raisonnable (quelques centaines de
lignes), le plus fiable est un petit script PHP one-shot : ouvrir les deux
connexions (SQLite en lecture, MySQL en écriture) et copier table par
table avec des `INSERT` préparés. Dites-le si vous voulez que je l'écrive
au moment de la bascule réelle — inutile de le maintenir dans le dépôt
avant d'en avoir besoin.

### 6. Vérifier
- Se connecter à l'admin, vérifier que le catalogue produits s'affiche
- Passer une commande de test (mode simulation ou Stripe test)
- Vérifier dans phpMyAdmin / un client MySQL que la commande est bien
  enregistrée avec ses lignes

## Ce qui ne change pas

Tout le reste du code (100+ requêtes PDO préparées dans `public/*.php`)
fonctionne à l'identique : le projet utilise PDO partout, jamais de SQL
brut spécifique à un moteur, donc aucune autre modification n'est
nécessaire côté pages.
