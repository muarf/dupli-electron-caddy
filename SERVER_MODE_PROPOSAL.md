# Proposition : Mode Serveur Multi-Utilisateurs (Multi-tenancy)

## 1. Objectif
Permettre à plusieurs entités (ex: Asso A, Asso B) ou testeurs (comptes demo `new` et `test`) d'utiliser la même instance du serveur tout en ayant des données et des fichiers isolés.

## 2. Diagnostic de l'existant
Actuellement, l'application est pensée pour un utilisateur unique :
- **Bases de données** : Changer de base dans l'admin modifie `conf.php` pour TOUS les utilisateurs.
- **Authentification** : Un seul mot de passe admin global.
- **Fichiers** : Un seul dossier `app/bibliotheque` partagé.

## 3. Architecture proposée (Multi-Tenancy par Session)

### Authentification & Connexion
- Mise en place d'une page de login qui accepte différents mots de passe.
- Chaque mot de passe est lié à une base de données SQLite spécifique.
- **Exemple** :
    - Password "asso_a" -> charge `asso_a.sqlite`
    - Password "demo" -> charge `demo.sqlite` (en lecture seule ou réinitialisable)

### Isolation des données (PHP)
- **`pdo_connect()`** : Sera modifié pour lire le chemin de la base de données dans la session PHP (`$_SESSION['db_path']`) au lieu de `conf.php`.
- **`paths.php`** : La fonction `getBibliothequeDir()` utilisera un sous-dossier session (ex: `app/bibliotheque/users/{user_id}/`) pour isoler les PDF.

### Mode Démo (`new` / `test`)
- Création d'un flag `$_SESSION['is_demo']`.
- Si actif, les opérations d'écriture (Upload, Delete, Update) sont interceptées et bloquées avec un message informatif.
- Possibilité de réinitialiser la base de démo via un script automatique.

## 4. Coexistence avec Electron
- La détection `isElectron()` reste la priorité. 
- Si l'app tourne en Electron, elle ignore toute la logique multi-utilisateur et utilise sa base locale unique.
- Aucune régression pour l'utilisateur de bureau.

## 5. Avantages
- **Sécurité** : Plus aucune fuite de données entre les comptes.
- **Flexibilité** : Permet de faire des démos publiques sans risquer les données de production.
- **Maintenabilité** : Le code métier (imposition, devis) reste inchangé car il utilise les fonctions d'accès aux données qui deviennent "intelligentes".
