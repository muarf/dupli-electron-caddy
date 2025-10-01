# Conversion MySQL vers SQLite - Application Duplicator

## Résumé

L'application a été convertie avec succès de MySQL vers SQLite. La table `duplinew` a été créée avec les colonnes :
- `id` (INTEGER PRIMARY KEY AUTOINCREMENT)
- `login` (VARCHAR(255))
- `password` (VARCHAR(255))
- `created_at` (DATETIME)
- `updated_at` (DATETIME)

## Modifications apportées

### 1. Configuration (`controler/conf.php`)
- Changement du DSN de MySQL vers SQLite
- Suppression des paramètres de connexion (login/password)
- Ajout du type de base de données (`db_type`)

### 2. Gestionnaire de base de données (`controler/functions/database.php`)
- Adaptation de la classe `DatabaseManager` pour SQLite
- Support des requêtes spécifiques à SQLite (`sqlite_master`, `PRAGMA`)
- Compatibilité maintenue avec MySQL

### 3. Base de données SQLite
- Fichier : `/home/ubuntu/dupli-sqlite/duplinew.sqlite`
- Table `duplinew` créée avec utilisateur par défaut :
  - Login : `dupli_user`
  - Password : `mot_de_pass_solide`

## Scripts créés

### `init_sqlite.php`
Script d'initialisation de la base SQLite avec création de la table `duplinew`.

### `test_sqlite.php`
Script de test complet pour vérifier toutes les fonctionnalités SQLite.

### `migrate_to_sqlite.php`
Script de migration des données MySQL vers SQLite (si nécessaire).

### `test_final.php`
Test final de validation de la conversion.

## Utilisation

### Démarrer l'application
```bash
cd /home/ubuntu/dupli-sqlite
php -S 127.0.0.1:8000 -t public
```

### Accéder à l'application
- URL : http://127.0.0.1:8000
- Login : `dupli_user`
- Password : `mot_de_pass_solide`

## Avantages de SQLite

1. **Simplicité** : Pas de serveur de base de données à gérer
2. **Portabilité** : Base de données dans un seul fichier
3. **Performance** : Accès direct aux données
4. **Maintenance** : Sauvegarde simple (copie du fichier)

## Compatibilité

- ✅ Toutes les fonctionnalités existantes préservées
- ✅ Code existant compatible
- ✅ Classe `Pdotest` fonctionnelle
- ✅ Gestionnaire `DatabaseManager` adapté

## Fichiers de base de données

- **SQLite** : `/home/ubuntu/dupli-sqlite/duplinew.sqlite`
- **Backup MySQL** : `/home/ubuntu/dupli-sqlite/duplinew_with_data.sql.backup`

## Tests effectués

- ✅ Connexion à la base de données
- ✅ Création et gestion des tables
- ✅ Opérations CRUD (Create, Read, Update, Delete)
- ✅ Transactions
- ✅ Compatibilité avec l'ancien code
- ✅ Permissions de fichiers

La conversion est **complète et fonctionnelle** ! 🎉

