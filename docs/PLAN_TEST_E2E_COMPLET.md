# Plan de Tests E2E Complet — Duplicator

> Couverture totale : ~440+ fonctions PHP backend, ~200+ fonctions JS frontend  
> Frameworks : Pest (PHP), Jest (JS unit/integration), Playwright (E2E navigateur)  
> Date : 2026-07-27

---

## Table des matières

1. [Infrastructure & Setup](#1-infrastructure--setup)
2. [Core Framework (PHP)](#2-core-framework-php)
3. [Auth & Sécurité](#3-auth--sécurité)
4. [Gestion des Bases de Données](#4-gestion-des-bases-de-données)
5. [Machines (Photocopieurs & Duplicopieurs)](#5-machines)
6. [Tirages (Print Jobs)](#6-tirages)
7. [Consommables & Compteurs](#7-consommables--compteurs)
8. [Prix & Tarification](#8-prix--tarification)
9. [Administration Générale](#9-administration-générale)
10. [Bibliothèque Documentaire](#10-bibliothèque-documentaire)
11. [Studio PDF](#11-studio-pdf)
12. [Impression & Spooling](#12-impression--spooling)
13. [IA / RAG / Vectorisation](#13-ia--rag--vectorisation)
14. [Sessions Multi-Contact](#14-sessions-multi-contact)
15. [Notifications & Maintenance](#15-notifications--maintenance)
16. [i18n / Traductions](#16-i18n--traductions)
17. [Setup & Installation](#17-setup--installation)
18. [Frontend JS — Composants UI](#18-frontend-js--composants-ui)
19. [Frontend JS — Pages](#19-frontend-js--pages)
20. [Playwright E2E Navigateur](#20-playwright-e2e-navigateur)
21. [Récapitulatif & Priorités](#21-récapitulatif--priorités)

---

## 1. Infrastructure & Setup

### 1.1 Environnement de test

| Composant | Outil | Config |
|-----------|-------|--------|
| PHP Unit/Feature | Pest v3 | `app/phpunit.xml`, `app/tests/Pest.php` |
| JS Unit/Integration | Jest v29 | `package.json` (inline jest config) |
| JS E2E Navigateur | Playwright v1.59 | `playwright.config.js` |
| Serveur de test | `tests/helpers/test-server.js` (Caddy+PHP) |
| Helpers PHP | `app/tests/helpers/test_db_helpers.php` | DB éphémère en `/tmp/` |
| Assets | `tests/assets/*.pdf`, `app/tests/Feature/fixtures/` | PDFs de test |

### 1.2 Commandes

```bash
# PHP tests
cd app && vendor/bin/pest

# JS unit + integration
npm run test

# JS E2E
npx playwright test

# Tout d'un coup
cd app && vendor/bin/pest && cd .. && npm run test && npx playwright test
```

### 1.3 Couverture actuelle

| Type | Fichiers | Statut |
|------|----------|--------|
| Pest Unit | 7 fichiers | ✅ Existant |
| Pest Feature | 22 fichiers | ✅ Existant |
| Jest Unit | 1 fichier | ✅ Existant |
| Jest Integration | 2 fichiers | ✅ Existant |
| Playwright E2E | 1 fichier (smoke) | ⚠️ Minimal |
| **Total** | **33 fichiers** | **~15% couverture** |

---

## 2. Core Framework (PHP)

### 2.1 `conf.php` — Configuration

| # | Test | Type | Existe |
|---|------|------|--------|
| 2.1.1 | `$conf` contient `dsn`, `login`, `pass`, `uploaddir`, `db_type`, `db_path` | Unit | ❌ |
| 2.1.2 | Valeurs par défaut valides pour chaque clé | Unit | ❌ |

### 2.2 `database.php` — DatabaseManager

| # | Test | Type | Existe |
|---|------|------|--------|
| 2.2.1 | `createDatabase()` crée un fichier SQLite valide | Feature | ❌ |
| 2.2.2 | `createDatabase()` avec template copie les données | Feature | ❌ |
| 2.2.3 | `switchDatabase()` change la connexion active | Feature | ❌ |
| 2.2.4 | `deleteDatabase()` supprime le fichier | Feature | ❌ |
| 2.2.5 | `renameDatabase()` renomme sans perte de données | Feature | ❌ |
| 2.2.6 | `getDatabasesList()` retourne la liste des DBs | Feature | ❌ |
| 2.2.7 | `getCurrentDatabase()` retourne le nom actuel | Feature | ❌ |
| 2.2.8 | `createEssentialTables()` crée toutes les tables requises | Feature | ❌ |
| 2.2.9 | `insertInitialAideData()` seed les données d'aide | Feature | ❌ |
| 2.2.10 | `pdo_connect()` retourne un PDO SQLite fonctionnel | Unit | ❌ |
| 2.2.11 | Gestion d'erreur sur DB inexistante | Unit | ❌ |

### 2.3 `utilities.php`

| # | Test | Type | Existe |
|---|------|------|--------|
| 2.3.1 | `template()` charge et rend un fichier Vue | Unit | ❌ |
| 2.3.2 | `template()` injecte les variables correctement | Unit | ❌ |
| 2.3.3 | `getSetting()` / `setSetting()` CRUD basique | Feature | ❌ |
| 2.3.4 | `getAllSettings()` retourne toutes les valeurs | Feature | ❌ |
| 2.3.5 | `tva()` calcule correctement (20% par défaut) | Unit | ❌ |
| 2.3.6 | `ajout_tva()` ajoute la TVA au montant | Unit | ❌ |
| 2.3.7 | `log_info()` / `log_error()` écrivent dans le log | Unit | ❌ |
| 2.3.8 | `get_system_info()` retourne les infos système | Unit | ❌ |
| 2.3.9 | `getPhpSettings()` retourne les settings PHP | Unit | ❌ |
| 2.3.10 | `check_session_health()` détecte session invalide | Unit | ❌ |

### 2.4 `paths.php`

| # | Test | Type | Existe |
|---|------|------|--------|
| 2.4.1 | `getDataDir()` retourne un chemin valide | Unit | ❌ |
| 2.4.2 | `getTmpDir()` retourne un chemin valide | Unit | ❌ |
| 2.4.3 | `getAppRoot()` retourne la racine de l'app | Unit | ❌ |
| 2.4.4 | `getBibliothequeDir()` retourne le dossier bibliothèque | Unit | ❌ |
| 2.4.5 | `getLogDir()` retourne le dossier de logs | Unit | ❌ |
| 2.4.6 | `getDbDir()` retourne le dossier des bases | Unit | ❌ |
| 2.4.7 | `isAppImage()` détecte le mode AppImage | Unit | ❌ |

### 2.5 `init.php`

| # | Test | Type | Existe |
|---|------|------|--------|
| 2.5.1 | `init_application()` charge toutes les dépendances | Feature | ❌ |
| 2.5.2 | Ne plante pas en mode serveur (sans Tauri) | Feature | ❌ |

### 2.6 `error_handler.php`

| # | Test | Type | Existe |
|---|------|------|--------|
| 2.6.1 | `store_last_error()` écrit l'erreur dans le fichier | Unit | ❌ |
| 2.6.2 | `getLastError()` retourne la dernière erreur | Unit | ❌ |
| 2.6.3 | `handleException()`捕获 les exceptions non catchées | Unit | ❌ |
| 2.6.4 | `handleError()`捕获 les warnings/notices PHP | Unit | ❌ |
| 2.6.5 | `setupErrorHandling()` enregistre les handlers | Unit | ❌ |

### 2.7 `security.php`

| # | Test | Type | Existe |
|---|------|------|--------|
| 2.7.1 | `generate_csrf_token()` crée un token en session | Unit | ❌ |
| 2.7.2 | `verify_csrf_token()` accepte un token valide | Unit | ❌ |
| 2.7.3 | `verify_csrf_token()` rejette un token invalide | Unit | ❌ |
| 2.7.4 | `verify_csrf_token()` rejette un token vide | Unit | ❌ |
| 2.7.5 | `validate_safe_path()` accepte un chemin sûr | Unit | ❌ |
| 2.7.6 | `validate_safe_path()` rejette `../../etc/passwd` | Unit | ❌ |
| 2.7.7 | `validate_safe_path()` rejette les null bytes | Unit | ❌ |

---

## 3. Auth & Sécurité

| # | Test | Type | Existe |
|---|------|------|--------|
| 3.1 | Page admin inaccessible sans mot de passe | E2E | ❌ |
| 3.2 | Création de mot de passe admin (`create_password.php`) | Feature | ❌ |
| 3.3 | Login admin fonctionne avec bon mot de passe | E2E | ❌ |
| 3.4 | Login admin échoue avec mauvais mot de passe | E2E | ❌ |
| 3.5 | Session admin persiste après navigation | E2E | ❌ |
| 3.6 | Logout détruit la session (`?logout`) | Feature | ❌ |
| 3.7 | CSRF token requis sur formulaires admin POST | Feature | ❌ |
| 3.8 | Path traversal bloqué sur endpoints fichiers | Feature | ❌ |
| 3.9 | CORS headers restreints au Tauri WebView | Feature | ❌ |
| 3.10 | Headers de sécurité présents (X-Frame-Options, etc.) | Feature | ❌ |
| 3.11 | Rate limiting sur tentatives de login (si implémenté) | Feature | ❌ |
| 3.12 | `requireBibliothequeAuth()` bloque l'accès non autorisé | Feature | ❌ |

---

## 4. Gestion des Bases de Données

### 4.1 CRUD Bases

| # | Test | Type | Existe |
|---|------|------|--------|
| 4.1.1 | Créer une nouvelle base depuis l'admin | Feature | ❌ |
| 4.1.2 | Basculer vers une autre base | Feature | ❌ |
| 4.1.3 | Renommer une base | Feature | ❌ |
| 4.1.4 | Supprimer une base | Feature | ❌ |
| 4.1.5 | Lister toutes les bases disponibles | Feature | ❌ |
| 4.1.6 | Base actuelle affichée correctement | Feature | ❌ |
| 4.1.7 | Création avec template copie les données | Feature | ❌ |
| 4.1.8 | Impossible de supprimer la base active | Feature | ❌ |
| 4.1.9 | Renommage avec nom déjà existant échoue | Feature | ❌ |

### 4.2 Backup & Restore

| # | Test | Type | Existe |
|---|------|------|--------|
| 4.2.1 | `createBackup()` crée un fichier backup | Feature | ✅ |
| 4.2.2 | `restoreBackup()` restaure correctement | Feature | ✅ |
| 4.2.3 | `deleteBackup()` supprime le backup | Feature | ✅ |
| 4.2.4 | `getBackupsList()` liste les backups | Feature | ✅ |
| 4.2.5 | `uploadBackup()` restaure depuis un upload | Feature | ✅ |
| 4.2.6 | `pruneOldAutoBackups()` supprime les vieux backups | Feature | ❌ |
| 4.2.7 | `download_backup.php` sert le fichier | Feature | ✅ |
| 4.2.8 | Backup de DB vide ne plante pas | Unit | ❌ |

### 4.3 Migrations

| # | Test | Type | Existe |
|---|------|------|--------|
| 4.3.1 | `runMigrations()` applique toutes les migrations | Feature | ❌ |
| 4.3.2 | Migrations idempotentes (re-run sans erreur) | Feature | ❌ |
| 4.3.3 | Chaque migration `up()` crée les bonnes colonnes/tables | Unit | ❌ |
| 4.3.4 | Migration `create_bibliotheque_fts` crée la table FTS5 | Feature | ❌ |
| 4.3.5 | Auto-migration au démarrage de l'app | Feature | ❌ |

---

## 5. Machines

### 5.1 CRUD Machines

| # | Test | Type | Existe |
|---|------|------|--------|
| 5.1.1 | `create_photocop()` crée un photocopieur | Feature | ✅ |
| 5.1.2 | `create_duplicateur()` crée un duplicopieur | Feature | ✅ |
| 5.1.3 | `update_photocop_machine()` met à jour | Feature | ✅ |
| 5.1.4 | `update_duplicateur()` met à jour | Feature | ✅ |
| 5.1.5 | `delete_photocop_machine()` supprime | Feature | ✅ |
| 5.1.6 | `delete_duplicateur()` supprime | Feature | ✅ |
| 5.1.7 | `get_all_machines()` retourne toutes les machines | Feature | ✅ |
| 5.1.8 | `check_machines_exist()` retourne true quand >0 | Feature | ✅ |
| 5.1.9 | Machine avec nom dupliqué échoue | Unit | ❌ |
| 5.1.10 | Nom de table sanitisé (`sanitizeTableName`) | Unit | ❌ |
| 5.1.11 | `createMachineTable()` crée la bonne structure | Unit | ❌ |
| 5.1.12 | Changement de type (photocop→dupli) géré correctement | Unit | ❌ |
| 5.1.13 | Rename machine met à jour les FK | Feature | ❌ |

### 5.2 MachineManager Class

| # | Test | Type | Existe |
|---|------|------|--------|
| 5.2.1 | `checkMachinesExist()` retourne bool | Unit | ❌ |
| 5.2.2 | `getActivePhotocopiers()` filtre les actifs | Unit | ❌ |
| 5.2.3 | `getActiveDuplicators()` filtre les actifs | Unit | ❌ |
| 5.2.4 | `getMachineById()` retourne la bonne machine | Unit | ❌ |
| 5.2.5 | `updateMachine()` met à jour les données | Unit | ❌ |

### 5.3 Admin Frontend (JS)

| # | Test | Type | Existe |
|---|------|------|--------|
| 5.3.1 | `admin-machines.js`: `deleteMachine()` envoie POST | E2E | ❌ |
| 5.3.2 | `admin-machines.js`: `editTambours()` charge les tambours | E2E | ❌ |
| 5.3.3 | `admin-machines.js`: `saveTambours()` sauvegarde | E2E | ❌ |
| 5.3.4 | `machine-rename.js`: `openRenameModal()` affiche le modal | E2E | ❌ |
| 5.3.5 | `machine-rename.js`: `saveRename()` renomme | E2E | ❌ |

### 5.4 Templates Machines

| # | Test | Type | Existe |
|---|------|------|--------|
| 5.4.1 | `get-machine-template.php` retourne HTML valide | Feature | ✅ |
| 5.4.2 | Template pour photocopieur contient bons champs | Unit | ❌ |
| 5.4.3 | Template pour duplicateur contient bons champs | Unit | ❌ |

---

## 6. Tirages

### 6.1 CRUD Tirages

| # | Test | Type | Existe |
|---|------|------|--------|
| 6.1.1 | `insert_photocop()` insère un tirage | Feature | ✅ |
| 6.1.2 | `insert_dupli()` insère un tirage | Feature | ✅ |
| 6.1.3 | `update_photocop()` met à jour | Feature | ✅ |
| 6.1.4 | `update_dupli()` met à jour | Feature | ✅ |
| 6.1.5 | `delete_photocop()` supprime | Feature | ✅ |
| 6.1.6 | `delete_dupli()` supprime | Feature | ✅ |
| 6.1.7 | `get_photocop()` retourne le bon tirage | Feature | ✅ |
| 6.1.8 | `get_dupli()` retourne le bon tirage | Feature | ✅ |
| 6.1.9 | `get_all_tirages()` retourne tous les tirages | Feature | ❌ |
| 6.1.10 | `rechercher()` trouve par nom/machine | Feature | ❌ |
| 6.1.11 | `get_tirage_stats()` retourne les statistiques | Feature | ❌ |

### 6.2 Tirage Global

| # | Test | Type | Existe |
|---|------|------|--------|
| 6.2.1 | `createTirageGlobal()` crée un groupe | Feature | ❌ |
| 6.2.2 | `addTirageToGlobal()` ajoute un tirage au groupe | Feature | ❌ |
| 6.2.3 | `getTirageGlobal()` retourne le groupe | Feature | ❌ |
| 6.2.4 | `getAllTiragesByGlobal()` retourne tous les tirages du groupe | Feature | ❌ |
| 6.2.5 | `updateTirageGlobalStatus()` change le statut | Feature | ❌ |
| 6.2.6 | `closeTirageGlobal()` ferme le groupe | Feature | ❌ |
| 6.2.7 | `deleteTirageGlobal()` supprime le groupe | Feature | ❌ |
| 6.2.8 | `isTirageGlobalEmpty()` vérifie si vide | Feature | ❌ |
| 6.2.9 | `hasActiveTirageGlobal()` détecte un groupe actif | Feature | ❌ |
| 6.2.10 | `getActiveTirageGlobal()` retourne le groupe actif | Feature | ❌ |

### 6.3 Admin Tirage (Manager + JS)

| # | Test | Type | Existe |
|---|------|------|--------|
| 6.3.1 | `TirageManager::getMachines()` retourne les machines avec derniers tirages | Feature | ✅ |
| 6.3.2 | `TirageManager::getLastTirages()` pagination | Feature | ✅ |
| 6.3.3 | `TirageManager::getPrixEnAttente()` total en attente | Feature | ✅ |
| 6.3.4 | `TirageManager::marquerCommePaye()` marque payé | Feature | ✅ |
| 6.3.5 | `TirageManager::deleteSelectedTirages()` suppression multiple | Feature | ✅ |
| 6.3.6 | `TirageManager::markSelectedAsPaid()` paiement multiple | Feature | ✅ |
| 6.3.7 | `admin-tirage.js`: `loadTirages()` charge la liste | E2E | ❌ |
| 6.3.8 | `admin-tirage.js`: `filterTirages()` filtre par machine/date | E2E | ❌ |
| 6.3.9 | `admin-tirage.js`: `deleteSelected()` supprime | E2E | ❌ |
| 6.3.10 | `admin-tirage.js`: `markAsPaid()` marque payé | E2E | ❌ |
| 6.3.11 | `admin-tirage.js`: `exportCsv()` exporte CSV | E2E | ❌ |

### 6.4 Tirage Multi-Machines

| # | Test | Type | Existe |
|---|------|------|--------|
| 6.4.1 | `getMachinePrices()` retourne les prix | Feature | ❌ |
| 6.4.2 | `calculateBrochurePriceOptimized()` calcule le prix brochure | Unit | ❌ |
| 6.4.3 | `determineMachineType()` détecte le type | Unit | ❌ |
| 6.4.4 | `calculatePageCost()` calcule le coût par page | Unit | ❌ |
| 6.4.5 | `generateMachineHTML()` génère le HTML | Unit | ❌ |
| 6.4.6 | `tirage-multimachines.js`: `addMachine()` ajoute un slot | E2E | ❌ |
| 6.4.7 | `tirage-multimachines.js`: `calculatePrice()` calcule | E2E | ❌ |
| 6.4.8 | `tirage-multimachines.js`: `submitTirage()` soumet | E2E | ❌ |
| 6.4.9 | `tirage-multimachines.js`: `validateForm()` valide | E2E | ❌ |

### 6.5 Auto Tirage

| # | Test | Type | Existe |
|---|------|------|--------|
| 6.5.1 | `auto-tirage.js`: `startSession()` crée une session | E2E | ❌ |
| 6.5.2 | `auto-tirage.js`: `finishSession()` ferme la session | E2E | ❌ |
| 6.5.3 | `auto-tirage.js`: `assignJobToSession()` assigne | E2E | ❌ |
| 6.5.4 | `auto-tirage.js`: `deleteJob()` supprime | E2E | ❌ |
| 6.5.5 | `auto-tirage.js`: `bulkMoveBufferToSession()` déplace | E2E | ❌ |
| 6.5.6 | `auto-tirage.js`: `bulkDeleteBufferJob()` supprime | E2E | ❌ |
| 6.5.7 | `auto-tirage.js`: `editJob()` / `saveEditedJob()` | E2E | ❌ |

### 6.6 Tirage Action

| # | Test | Type | Existe |
|---|------|------|--------|
| 6.6.1 | `TirageActionTest` — flux complet de tirage | Feature | ✅ |

---

## 7. Consommables & Compteurs

### 7.1 CRUD Consommables

| # | Test | Type | Existe |
|---|------|------|--------|
| 7.1.1 | `insert_cons()` insère un consommable | Feature | ✅ |
| 7.1.2 | `insert_cons_photocop()` insère photocop | Feature | ✅ |
| 7.1.3 | `insert_cons_photocop_by_name()` par nom | Feature | ❌ |
| 7.1.4 | `update_cons()` met à jour | Feature | ✅ |
| 7.1.5 | `delete_cons()` supprime | Feature | ✅ |
| 7.1.6 | `get_cons()` retourne un consommable | Feature | ✅ |
| 7.1.7 | `get_cons_photocop()` retourne les consommables photocop | Feature | ❌ |
| 7.1.8 | `reset_compteur()` reset le compteur | Feature | ❌ |
| 7.1.9 | `reset_compteur_by_name()` reset par nom | Feature | ❌ |
| 7.1.10 | `get_compteur()` retourne la valeur actuelle | Feature | ❌ |
| 7.1.11 | `get_compteur_by_name()` par nom | Feature | ❌ |
| 7.1.12 | `getHistoriqueConso()` historique par période | Feature | ❌ |

### 7.2 Frontend Changement (JS)

| # | Test | Type | Existe |
|---|------|------|--------|
| 7.2.1 | `changement.js`: `selectMachine()` charge les infos | E2E | ❌ |
| 7.2.2 | `changement.js`: `loadInstructions()` charge les instructions | E2E | ❌ |
| 7.2.3 | `changement.js`: `submitChange()` soumet le changement | E2E | ❌ |
| 7.2.4 | `changement.js`: `validateForm()` valide les champs | E2E | ❌ |
| 7.2.5 | `changement.js`: `updateCounterFields()` adapte les champs au type | E2E | ❌ |

### 7.3 Admin Changes

| # | Test | Type | Existe |
|---|------|------|--------|
| 7.3.1 | `ChangesManager::addChange()` ajoute | Feature | ✅ |
| 7.3.2 | `ChangesManager::deleteChange()` supprime | Feature | ✅ |
| 7.3.3 | `ChangesManager::updateChange()` met à jour | Feature | ✅ |
| 7.3.4 | `ChangesManager::getAllChanges()` paginé | Feature | ✅ |
| 7.3.5 | `admin-changes.js`: `loadChanges()` charge | E2E | ❌ |
| 7.3.6 | `admin-changes.js`: `addChange()` ajoute | E2E | ❌ |
| 7.3.7 | `admin-changes.js`: `deleteChange()` supprime | E2E | ❌ |
| 7.3.8 | `admin-changes.js`: `filterChanges()` filtre | E2E | ❌ |

---

## 8. Prix & Tarification

### 8.1 Core Pricing

| # | Test | Type | Existe |
|---|------|------|--------|
| 8.1.1 | `get_price()` retourne le prix pour machine/type | Feature | ✅ |
| 8.1.2 | `getMachinePrices()` retourne tous les prix | Feature | ✅ |
| 8.1.3 | `saveMachinePrices()` sauvegarde en bulk | Feature | ✅ |
| 8.1.4 | `saveMachinePrice()` sauvegarde un prix | Feature | ✅ |
| 8.1.5 | `savePaperPrice()` sauvegarde le prix papier | Feature | ✅ |
| 8.1.6 | `saveDupliTambourPrice()` sauvegarde prix tambour | Feature | ✅ |
| 8.1.7 | `getDefaultPrices()` retourne les prix par défaut | Feature | ✅ |
| 8.1.8 | `saveDefaultPrices()` sauvegarde les prix par défaut | Feature | ✅ |
| 8.1.9 | `deleteMachinePrice()` supprime un prix | Feature | ❌ |
| 8.1.10 | `getMachinePriceCount()` compte les entrées | Feature | ❌ |
| 8.1.11 | `getFormattedPrices()` format structuré | Feature | ❌ |

### 8.2 PriceManager (Admin)

| # | Test | Type | Existe |
|---|------|------|--------|
| 8.2.1 | `getPrices()` retourne tous les prix | Feature | ✅ |
| 8.2.2 | `insertPrice()` insère un prix | Feature | ✅ |
| 8.2.3 | `insertPapier()` insère le prix papier | Feature | ✅ |
| 8.2.4 | `getConsommables()` retourne les consommables | Feature | ✅ |
| 8.2.5 | `getPhotocopieurs()` retourne la liste | Feature | ✅ |
| 8.2.6 | `getDuplicopieurs()` retourne la liste | Feature | ✅ |
| 8.2.7 | `getPrixEncrePhotocop()` prix encre photocop | Feature | ❌ |
| 8.2.8 | `getPrixEncreDuplicop()` prix encre dupli | Feature | ❌ |
| 8.2.9 | `getPrixTambourDuplicop()` prix tambour | Feature | ❌ |
| 8.2.10 | `getAllPriceData()` toutes les données | Feature | ❌ |

### 8.3 Calcul JS

| # | Test | Type | Existe |
|---|------|------|--------|
| 8.3.1 | `calcul.js`: `calculerDuplicopieur()` calcule correctement | Unit | ❌ |
| 8.3.2 | `calcul.js`: `calculerPhotocopieuse()` calcule correctement | Unit | ❌ |
| 8.3.3 | `calcul.js`: `updateDisplay()` met à jour l'affichage | E2E | ❌ |
| 8.3.4 | `admin.prix.html.php`: `setupSync()` synchronise feuille/ramette | E2E | ❌ |

---

## 9. Administration Générale

### 9.1 Site Settings

| # | Test | Type | Existe |
|---|------|------|--------|
| 9.1.1 | `SiteManager::getStats()` retourne les stats | Feature | ✅ |
| 9.1.2 | `SiteManager::updateSiteSettings()` met à jour | Feature | ✅ |
| 9.1.3 | `SiteManager::updateSiteSetting()` met à jour un champ | Feature | ✅ |
| 9.1.4 | `SiteManager::getSiteSetting()` retourne une valeur | Feature | ✅ |
| 9.1.5 | `SiteManager::getEmails()` retourne les emails | Feature | ✅ |
| 9.1.6 | `SiteManager::deleteEmail()` supprime un email | Feature | ✅ |
| 9.1.7 | `SiteManager::deleteAllEmails()` vide la liste | Feature | ✅ |
| 9.1.8 | `SiteManager::getCurrentSettings()` retourne tout | Feature | ✅ |

### 9.2 News

| # | Test | Type | Existe |
|---|------|------|--------|
| 9.2.1 | `NewsManager::getAllNews()` retourne toutes | Feature | ✅ |
| 9.2.2 | `NewsManager::getNews()` par ID | Feature | ✅ |
| 9.2.3 | `NewsManager::insertNews()` insère | Feature | ✅ |
| 9.2.4 | `NewsManager::updateNews()` met à jour | Feature | ✅ |
| 9.2.5 | `NewsManager::deleteNews()` supprime | Feature | ✅ |
| 9.2.6 | `get_last_news()` retourne les dernières | Feature | ❌ |
| 9.2.7 | `admin-news.js`: `initQuill()` initialise l'éditeur | E2E | ❌ |
| 9.2.8 | `admin-news.js`: `saveNews()` sauvegarde | E2E | ❌ |
| 9.2.9 | `admin-news.js`: `deleteNews()` supprime | E2E | ❌ |

### 9.3 Aide Machines

| # | Test | Type | Existe |
|---|------|------|--------|
| 9.3.1 | `AideManager::addQA()` ajoute Q&R | Feature | ✅ |
| 9.3.2 | `AideManager::updateQA()` met à jour | Feature | ✅ |
| 9.3.3 | `AideManager::deleteQA()` supprime | Feature | ✅ |
| 9.3.4 | `AideManager::getAideByMachine()` par machine | Feature | ✅ |
| 9.3.5 | `AideManager::getAllQA()` toutes les Q&R | Feature | ✅ |
| 9.3.6 | `AideManager::addAide()` ajoute aide | Feature | ✅ |
| 9.3.7 | `AideManager::updateAide()` met à jour | Feature | ✅ |
| 9.3.8 | `AideManager::deleteAide()` supprime | Feature | ✅ |
| 9.3.9 | `AideManager::getMachinesWithAide()` liste | Feature | ✅ |
| 9.3.10 | Filtrage par catégorie fonctionne | Feature | ❌ |
| 9.3.11 | `admin-aide.js`: `loadAideContent()` charge | E2E | ❌ |
| 9.3.12 | `admin-aide.js`: `saveAideContent()` sauvegarde | E2E | ❌ |
| 9.3.13 | `admin-aide.js`: `uploadPdf()` upload PDF aide | E2E | ❌ |
| 9.3.14 | `admin-aide.js`: `deletePdf()` supprime PDF | E2E | ❌ |

### 9.4 Stats

| # | Test | Type | Existe |
|---|------|------|--------|
| 9.4.1 | `StatsManager::getStatsIntroText()` | Feature | ✅ |
| 9.4.2 | `StatsManager::updateStatsIntroText()` | Feature | ✅ |
| 9.4.3 | `StatsManager::getAllStatsData()` | Feature | ✅ |
| 9.4.4 | `nombre_feuilles_depuis_duplicopieur()` | Feature | ❌ |
| 9.4.5 | `nombre_feuilles_depuis_photocop()` | Feature | ❌ |
| 9.4.6 | `nombre_feuilles_periode()` | Feature | ❌ |
| 9.4.7 | `nombre_feuilles_toutes_machines()` | Feature | ❌ |
| 9.4.8 | `get_stats_mensuelles()` | Feature | ❌ |
| 9.4.9 | `get_stats_annuelles()` | Feature | ❌ |
| 9.4.10 | `get_stats_periode()` | Feature | ❌ |
| 9.4.11 | `get_stats_machines()` | Feature | ❌ |
| 9.4.12 | `get_stats_jour()` | Feature | ❌ |

---

## 10. Bibliothèque Documentaire

### 10.1 BibliothequeManager (Core)

| # | Test | Type | Existe |
|---|------|------|--------|
| 10.1.1 | `addFile()` ajoute un fichier | Feature | ✅ |
| 10.1.2 | `getFileInfo()` retourne les métadonnées | Feature | ✅ |
| 10.1.3 | `deleteFile()` supprime fichier + métadonnées | Feature | ✅ |
| 10.1.4 | `searchFiles()` recherche FTS5 | Feature | ✅ |
| 10.1.5 | `listFiles()` liste avec filtres | Feature | ✅ |
| 10.1.6 | `getTags()` retourne tous les tags | Feature | ✅ |
| 10.1.7 | `updateMetadata()` met à jour les métadonnées | Feature | ✅ |
| 10.1.8 | `generateThumbnail()` crée un thumbnail | Feature | ❌ |
| 10.1.9 | `reindexFile()` réindexe un fichier | Feature | ❌ |
| 10.1.10 | `fullReindex()` réindexe toute la bibliothèque | Feature | ❌ |
| 10.1.11 | `getStats()` retourne les stats | Feature | ❌ |
| 10.1.12 | `purgeOrphanThumbnails()` nettoie orphelins | Feature | ❌ |
| 10.1.13 | `getFilePath()` retourne le chemin physique | Feature | ❌ |
| 10.1.14 | `hasFTS5Support()` détecte FTS5 | Unit | ❌ |
| 10.1.15 | `createFTS5Table()` crée la table FTS5 | Feature | ❌ |
| 10.1.16 | `getFileContent()` extrait le texte | Feature | ❌ |
| 10.1.17 | `extractKeywords()` extrait les mots-clés | Unit | ❌ |
| 10.1.18 | `buildSearchQuery()` construit la requête FTS5 | Unit | ❌ |
| 10.1.19 | `analyzePdf()` analyse un PDF | Feature | ❌ |
| 10.1.20 | `searchHybrid()` recherche hybride vector+FTS | Feature | ❌ |
| 10.1.21 | `getAllFiles()` paginé avec recherche | Feature | ❌ |
| 10.1.22 | `countAllFiles()` compte avec filtres | Feature | ❌ |
| 10.1.23 | `checkIntegrity()` vérifie l'intégrité | Feature | ❌ |
| 10.1.24 | `cleanOrphans()` supprime les orphelins | Feature | ❌ |
| 10.1.25 | `repairFTS()` répare l'index FTS5 | Feature | ❌ |
| 10.1.26 | `resetLibrary()` réinitialise tout | Feature | ❌ |
| 10.1.27 | `addExternalFile()` ajoute un fichier externe | Feature | ❌ |
| 10.1.28 | `scanDirectoryForLibrary()` scanne un dossier | Feature | ❌ |

### 10.2 API Bibliothèque

| # | Test | Type | Existe |
|---|------|------|--------|
| 10.2.1 | `bibliotheque_list.php` retourne HTML paginé | Feature | ✅ |
| 10.2.2 | `search_bibliotheque.php` retourne JSON | Feature | ❌ |
| 10.2.3 | `upload_bibliotheque.php` upload un fichier | Feature | ✅ |
| 10.2.4 | `delete_bibliotheque_file.php` supprime | Feature | ✅ |
| 10.2.5 | `rename_bibliotheque_file.php` renomme | Feature | ❌ |
| 10.2.6 | `get_bibliotheque_file.php` sert le fichier | Feature | ✅ |
| 10.2.7 | `get_bibliotheque_file_info.php` métadonnées | Feature | ✅ |
| 10.2.8 | `get_bibliotheque_thumbnail.php` sert le thumb | Feature | ❌ |
| 10.2.9 | `get_bibliotheque_tags.php` liste les tags | Feature | ❌ |
| 10.2.10 | `update_bibliotheque_metadata.php` met à jour | Feature | ✅ |
| 10.2.11 | `preview_directory.php` prévisualise un dossier | Feature | ❌ |
| 10.2.12 | `bibliotheque_maintenance.php` : `check_integrity` | Feature | ❌ |
| 10.2.13 | `bibliotheque_maintenance.php` : `clean_orphans` | Feature | ❌ |
| 10.2.14 | `bibliotheque_maintenance.php` : `repair_fts` | Feature | ❌ |
| 10.2.15 | `bibliotheque_maintenance.php` : `reset_library` | Feature | ❌ |
| 10.2.16 | `bibliotheque_maintenance.php` : `regenerate_thumbnails` | Feature | ❌ |
| 10.2.17 | `bibliotheque_maintenance.php` : `rescan` | Feature | ❌ |
| 10.2.18 | `index_file.php` indexe un fichier pour RAG | Feature | ❌ |

### 10.3 Frontend Bibliothèque (JS)

| # | Test | Type | Existe |
|---|------|------|--------|
| 10.3.1 | `loadLibrary()` charge la liste via AJAX | E2E | ❌ |
| 10.3.2 | `filterByTag()` filtre par tag | E2E | ❌ |
| 10.3.3 | `openLibraryFile()` ouvre un fichier | E2E | ❌ |
| 10.3.4 | `openDeleteModal()` / `confirmDeleteFile()` supprime | E2E | ❌ |
| 10.3.5 | `editFile()` / `saveMetadata()` édite les métadonnées | E2E | ❌ |
| 10.3.6 | `handleFiles()` / `uploadFile()` upload | E2E | ❌ |
| 10.3.7 | `rescanLibrary()` relance le scan | E2E | ❌ |
| 10.3.8 | `checkActiveJob()` / `monitorIndexing()` poll l'indexation | E2E | ❌ |
| 10.3.9 | `loadTags()` charge les tags | E2E | ❌ |
| 10.3.10 | `addTagFilter()` / `removeTagFilter()` filtre | E2E | ❌ |
| 10.3.11 | `toggleAllPdfs()` sélectionne/désélectionne tout | E2E | ❌ |
| 10.3.12 | `printLibraryFile()` imprime un fichier | E2E | ❌ |
| 10.3.13 | `openPdfViewer()` ouvre le visualiseur PDF | E2E | ❌ |
| 10.3.14 | `renderPage()` / `changePage()` navigue le PDF | E2E | ❌ |

### 10.4 Helper Functions

| # | Test | Type | Existe |
|---|------|------|--------|
| 10.4.1 | `isFileSafe()` valide un fichier sûr | Unit | ❌ |
| 10.4.2 | `requireBibliothequeAuth()` bloque non autorisé | Feature | ❌ |
| 10.4.3 | `isElectron()` détecte le mode Electron | Unit | ❌ |
| 10.4.4 | `resolveTempDir()` résout le dossier temp | Unit | ❌ |

---

## 11. Studio PDF

### 11.1 StudioImpositionService

| # | Test | Type | Existe |
|---|------|------|--------|
| 11.1.1 | `getStudioSettings()` retourne les settings | Feature | ✅ |
| 11.1.2 | `moveUploadedFile()` déplace le fichier | Feature | ✅ |
| 11.1.3 | `generateImpositionPreview()` génère un aperçu PNG | Feature | ✅ |

### 11.2 Imposition (Standard)

| # | Test | Type | Existe |
|---|------|------|--------|
| 11.2.1 | `Imposition::process()` imposition standard | Feature | ✅ |
| 11.2.2 | `Imposition::getPreviewPdf()` retourne l'aperçu | Feature | ❌ |
| 11.2.3 | `Imposition::placePage()` positionne correctement | Unit | ❌ |
| 11.2.4 | `Imposition::drawCollationMark()` dessine les marques | Unit | ❌ |
| 11.2.5 | `Imposition::drawSmartCropMarks()` dessine crop marks | Unit | ❌ |
| 11.2.6 | `Imposition::addPageNumberInGutter()` ajoute numéros | Unit | ❌ |

### 11.3 ImpositionLeaflet (Brochure)

| # | Test | Type | Existe |
|---|------|------|--------|
| 11.3.1 | `ImpositionLeaflet::process()` imposition brochure | Feature | ✅ |
| 11.3.2 | `ImpositionLeaflet::getPreviewPdf()` aperçu | Feature | ❌ |
| 11.3.3 | `ImpositionLeaflet::renderSheetSide()` rendu recto/verso | Unit | ❌ |
| 11.3.4 | `ImpositionLeaflet::calculatePageMetrics()` calculs | Unit | ❌ |
| 11.3.5 | `ImpositionLeaflet::placePage()` positionnement | Unit | ❌ |
| 11.3.6 | `ImpositionLeaflet::drawIndividualCropMarks()` | Unit | ❌ |
| 11.3.7 | `ImpositionLeaflet::drawRowCropMarks()` | Unit | ❌ |
| 11.3.8 | `ImpositionLeaflet::drawSpreadCropMarks()` | Unit | ❌ |

### 11.4 Imposition Tracts

| # | Test | Type | Existe |
|---|------|------|--------|
| 11.4.1 | `analyzePDFFormat()` analyse les dimensions | Unit | ❌ |
| 11.4.2 | `determineFormat()` détecte le format (A4, A3, etc.) | Unit | ❌ |
| 11.4.3 | `determineAutomaticParams()` calcule auto les params | Unit | ❌ |
| 11.4.4 | `processImpositionTracts()` impose les tracts | Feature | ❌ |
| 11.4.5 | `performImposition()` exécute l'imposition | Unit | ❌ |

### 11.5 Unimpose (Dé-imposition)

| # | Test | Type | Existe |
|---|------|------|--------|
| 11.5.1 | `UnimposeBooklet::unimposeBooklet()` dé-impose | Feature | ✅ |
| 11.5.2 | `UnimposeBooklet::splitDoublePages()` sépare | Unit | ❌ |
| 11.5.3 | `UnimposeBooklet::splitSequential()` séquentiel | Unit | ❌ |

### 11.6 PDF Utils

| # | Test | Type | Existe |
|---|------|------|--------|
| 11.6.1 | `padPdfToMultiple()` complète le PDF | Feature | ✅ |
| 11.6.2 | `addPageNumber()` ajoute un numéro | Feature | ✅ |
| 11.6.3 | `addTextAndBox()` ajoute une zone texte | Feature | ✅ |
| 11.6.4 | `addCustomPageNumber()` texte custom | Feature | ✅ |
| 11.6.5 | `addRedaction()` ajoute un masque | Feature | ✅ |
| 11.6.6 | `downgradePdfTo14()` rétrograde le PDF | Feature | ✅ |

### 11.7 PDF Merge & Convert

| # | Test | Type | Existe |
|---|------|------|--------|
| 11.7.1 | `merge_pdfs()` fusionne 2+ PDFs | Feature | ✅ |
| 11.7.2 | `convert_pdf_to_png()` convertit PDF→PNG | Feature | ✅ |
| 11.7.3 | `convert_png_to_pdf()` convertit PNG→PDF | Feature | ✅ |
| 11.7.4 | Merge de PDFs vides/plante pas | Unit | ❌ |
| 11.7.5 | Conversion avec DPI invalide gère l'erreur | Unit | ❌ |

### 11.8 Studio API (studio_process.php)

| # | Test | Type | Existe |
|---|------|------|--------|
| 11.8.1 | `impose` — imposition PPP | Feature | ✅ |
| 11.8.2 | `impose` — imposition tracts | Feature | ✅ |
| 11.8.3 | `impose` — imposition livre | Feature | ✅ |
| 11.8.4 | `impose` — imposition brochure | Feature | ✅ |
| 11.8.5 | `resize` — redimensionne PDF/image | Feature | ✅ |
| 11.8.6 | `to_pdf` — image→PDF | Feature | ✅ |
| 11.8.7 | `riso_pdf` — traitement Riso | Feature | ✅ |
| 11.8.8 | `analyze_ink` — analyse encre | Feature | ✅ |
| 11.8.9 | `pdf_to_images` — PDF→images | Feature | ✅ |
| 11.8.10 | `merge` — fusion | Feature | ✅ |
| 11.8.11 | `unimpose` — dé-imposition | Feature | ✅ |
| 11.8.12 | `organize_pages` — réorganiser pages | Feature | ✅ |
| 11.8.13 | `montage_libre` — montage libre | Feature | ❌ |
| 11.8.14 | `crop_pdf` — rogner PDF | Feature | ❌ |
| 11.8.15 | `ocr_cleanup` — OCR | Feature | ❌ |
| 11.8.16 | `modification` — modifications (redaction, numéros) | Feature | ❌ |
| 11.8.17 | `list_fonts` — liste polices | Feature | ❌ |
| 11.8.18 | `upload_font` — upload police | Feature | ❌ |
| 11.8.19 | `read_metadata` — lit métadonnées PDF | Feature | ❌ |
| 11.8.20 | `update_metadata` — met à jour métadonnées | Feature | ❌ |
| 11.8.21 | `recognize_font` — reconnaissance IA police | Feature | ❌ |
| 11.8.22 | `download_google_font` — télécharge police Google | Feature | ❌ |
| 11.8.23 | `passthrough_pdf` — passe sans changer | Feature | ❌ |
| 11.8.24 | `ocr_status` / `analyze_ink_status` / `task_status` — statut | Feature | ❌ |
| 11.8.25 | `get_active_jobs` — jobs actifs | Feature | ❌ |
| 11.8.26 | `delete_job` — supprime un job | Feature | ❌ |

### 11.9 Frontend Studio (JS)

| # | Test | Type | Existe |
|---|------|------|--------|
| 11.9.1 | `initStudio()` initialise la page | E2E | ❌ |
| 11.9.2 | `loadPdf()` charge un PDF | E2E | ❌ |
| 11.9.3 | `renderPage()` rend une page | E2E | ❌ |
| 11.9.4 | `exportPdf()` exporte le PDF | E2E | ❌ |
| 11.9.5 | `exportPng()` exporte en PNG | E2E | ❌ |
| 11.9.6 | `saveToLibrary()` sauvegarde en bibliothèque | E2E | ❌ |
| 11.9.7 | `applyFilters()` applique les filtres | E2E | ❌ |
| 11.9.8 | `applyBitmap()` applique le filtre bitmap | E2E | ❌ |
| 11.9.9 | `resetFilters()` réinitialise les filtres | E2E | ❌ |
| 11.9.10 | `handleFileUpload()` gère le drag-and-drop | E2E | ❌ |
| 11.9.11 | `switchTool()` change d'outil | E2E | ❌ |
| 11.9.12 | `applyImposition()` applique l'imposition | E2E | ❌ |
| 11.9.13 | `applyDeskew()` redresse | E2E | ❌ |
| 11.9.14 | `applyResize()` redimensionne | E2E | ❌ |
| 11.9.15 | `activateCrop()` / `applyCropExport()` rogne | E2E | ❌ |
| 11.9.16 | `runOcr()` lance OCR | E2E | ❌ |
| 11.9.17 | `showLightbox()` pleine page | E2E | ❌ |
| 11.9.18 | `updateThumbnails()` barre de miniatures | E2E | ❌ |
| 11.9.19 | `showErrorOverlay()` affiche erreurs | E2E | ❌ |

### 11.10 Studio Modification (JS)

| # | Test | Type | Existe |
|---|------|------|--------|
| 11.10.1 | `initFabricCanvas()` initialise Fabric.js | E2E | ❌ |
| 11.10.2 | `addTextTool()` ajoute du texte | E2E | ❌ |
| 11.10.3 | `addRedactTool()` ajoute un masque | E2E | ❌ |
| 11.10.4 | `addStrikeoutTool()` barette | E2E | ❌ |
| 11.10.5 | `addPageNumberTool()` numéro de page | E2E | ❌ |
| 11.10.6 | `setColor()` / `setFontSize()` style | E2E | ❌ |
| 11.10.7 | `setEraserMode()` gomme | E2E | ❌ |
| 11.10.8 | `clearCanvas()` efface tout | E2E | ❌ |
| 11.10.9 | `getCanvasAsImage()` exporte en image | E2E | ❌ |

### 11.11 Studio Montage (JS)

| # | Test | Type | Existe |
|---|------|------|--------|
| 11.11.1 | `initMontage()` initialise le montage | E2E | ❌ |
| 11.11.2 | `addPlanche()` ajoute une planche | E2E | ❌ |
| 11.11.3 | `handleMontageUpload()` upload fichiers | E2E | ❌ |
| 11.11.4 | `dragToCanvas()` place sur le canvas | E2E | ❌ |
| 11.11.5 | `generateMontagePdf()` génère le PDF final | E2E | ❌ |
| 11.11.6 | `updateMontageRulers()` dessine les règles | E2E | ❌ |

### 11.12 Studio Metadata (JS)

| # | Test | Type | Existe |
|---|------|------|--------|
| 11.12.1 | `loadMetadata()` charge les métadonnées | E2E | ❌ |
| 11.12.2 | `saveMetadata()` sauvegarde | E2E | ❌ |
| 11.12.3 | `renderMetadataPanel()` rendu du panneau | E2E | ❌ |

### 11.13 Riso Tools (JS)

| # | Test | Type | Existe |
|---|------|------|--------|
| 11.13.1 | `posterize()` réduit les couleurs | Unit | ❌ |
| 11.13.2 | `halftone()` crée le tramage | Unit | ❌ |
| 11.13.3 | `autoBichromie()` séparation auto 2 couleurs | Unit | ❌ |
| 11.13.4 | `rgbSeparate()` sépare RGB | Unit | ❌ |
| 11.13.5 | `cmykSeparate()` sépare CMYK | Unit | ❌ |
| 11.13.6 | `twoColorSeparate()` séparation custom | Unit | ❌ |
| 11.13.7 | `pipetteSeparate()` séparation par pipette | Unit | ❌ |
| 11.13.8 | `exportChannelZip()` exporte ZIP | Unit | ❌ |
| 11.13.9 | `exportRisoPdf()` exporte PDF multi-pages | Unit | ❌ |

### 11.14 CropMarks

| # | Test | Type | Existe |
|---|------|------|--------|
| 11.14.1 | `drawCropMarks()` dessine les crop marks | Unit | ❌ |
| 11.14.2 | `drawTrimNumbers()` dessine les numéros | Unit | ❌ |

### 11.15 ImpositionProcessor

| # | Test | Type | Existe |
|---|------|------|--------|
| 11.15.1 | `processPage()` traite une page | Unit | ❌ |

### 11.16 Fill Rate / Ink Coverage

| # | Test | Type | Existe |
|---|------|------|--------|
| 11.16.1 | `calculate_fill_rate()` calcule le taux de remplissage | Feature | ❌ |
| 11.16.2 | `analyze_pdf_ink_coverage_gs()` analyse via Ghostscript | Feature | ❌ |
| 11.16.3 | `convert_pdf_to_thumbnail()` convertit en miniature | Feature | ❌ |

---

## 12. Impression & Spooling

### 12.1 Print Engine (Node.js)

| # | Test | Type | Existe |
|---|------|------|--------|
| 12.1.1 | `printFile()` Linux — envoie via `lp` | Unit | ❌ |
| 12.1.2 | `getAvailablePrinters()` Linux — liste via `lpstat` | Unit | ❌ |
| 12.1.3 | `sendToPrinter()` Linux — envoie à imprimante spécifique | Unit | ❌ |
| 12.1.4 | `cancelPrintJob()` Linux — annule un job | Unit | ❌ |
| 12.1.5 | `printFile()` Windows — envoie via SumatraPDF | Unit | ❌ |
| 12.1.6 | `getAvailablePrinters()` Windows — liste via PowerShell | Unit | ❌ |
| 12.1.7 | `sendToPrinter()` Windows — envoie spécifique | Unit | ❌ |
| 12.1.8 | `getPrintJobStatus()` Windows — poll la queue | Unit | ❌ |

### 12.2 Printer Monitor

| # | Test | Type | Existe |
|---|------|------|--------|
| 12.2.1 | `PrinterMonitor::start()` démarre le polling | Unit | ❌ |
| 12.2.2 | `PrinterMonitor::stop()` arrête le polling | Unit | ❌ |
| 12.2.3 | `PrinterMonitor::checkForNewJobs()` détecte les jobs | Unit | ❌ |
| 12.2.4 | `PrinterMonitor::getJobHistory()` retourne l'historique | Unit | ❌ |

### 12.3 Spool Analyzer

| # | Test | Type | Existe |
|---|------|------|--------|
| 12.3.1 | `SpoolAnalyzer::analyzeSpoolFile()` analyse .SPL | Unit | ❌ |
| 12.3.2 | `SpoolAnalyzer::extractPdfFromSpool()` extrait PDF | Unit | ❌ |
| 12.3.3 | `SpoolAnalyzer::getInkCoverage()` calcul encre | Unit | ❌ |
| 12.3.4 | `analyzeCupsSpool()` Linux CUPS | Unit | ❌ |
| 12.3.5 | `getInkCoverageFromPdf()` Linux | Unit | ❌ |

### 12.4 SpoolManager (PHP)

| # | Test | Type | Existe |
|---|------|------|--------|
| 12.4.1 | `SpoolManager::findSpoolFile()` trouve le fichier | Unit | ✅ |
| 12.4.2 | `SpoolManager::deleteSpoolFiles()` supprime SPL/SHD | Unit | ✅ |

### 12.5 Conversion API (Spool→PNG)

| # | Test | Type | Existe |
|---|------|------|--------|
| 12.5.1 | `convert-emf-to-png.php` convertit EMF→PNG | Feature | ✅ |
| 12.5.2 | `convert-pcl-to-png.php` convertit PCL→PNG | Feature | ✅ |
| 12.5.3 | `convert-xps-to-png.php` convertit XPS→PNG | Feature | ✅ |
| 12.5.4 | `convert-ps-to-png.php` convertit PS→PNG | Feature | ✅ |

### 12.6 Print API Endpoints

| # | Test | Type | Existe |
|---|------|------|--------|
| 12.6.1 | `check-print-jobs.php` : list jobs | Feature | ✅ |
| 12.6.2 | `check-print-jobs.php` : `delete_jobs` | Feature | ✅ |
| 12.6.3 | `check-print-jobs.php` : `purge_all` | Feature | ✅ |
| 12.6.4 | `check-print-jobs.php` : `regenerate_thumbnails` | Feature | ✅ |
| 12.6.5 | `check-print-jobs.php` : `update_thumbnail` | Feature | ❌ |
| 12.6.6 | `check-print-jobs.php` : `update_job_analysis` | Feature | ❌ |
| 12.6.7 | `print-notification.php` reçoit notification Windows | Feature | ❌ |
| 12.6.8 | `save_auto_print.php` sauvegarde auto | Feature | ✅ |
| 12.6.9 | `get_pending_jobs.php` liste les jobs en attente | Feature | ❌ |
| 12.6.10 | `get_session_jobs.php` liste les jobs d'une session | Feature | ❌ |
| 12.6.11 | `get_session_staging_jobs.php` liste les jobs staged | Feature | ❌ |
| 12.6.12 | `delete_session_job.php` supprime un job de session | Feature | ❌ |

### 12.7 Print Dialog & Modal (JS)

| # | Test | Type | Existe |
|---|------|------|--------|
| 12.7.1 | `PrintDialog::show()` affiche le dialogue | E2E | ❌ |
| 12.7.2 | `PrintDialog::getPrinterOptions()` charge les options | E2E | ❌ |
| 12.7.3 | `PrintDialog::sendPrint()` envoie l'impression | E2E | ❌ |
| 12.7.4 | `PrintModal::show()` affiche le modal | E2E | ❌ |
| 12.7.5 | `PrintModal::loadPrinters()` charge les imprimantes | E2E | ❌ |
| 12.7.6 | `PrintModal::sendPrint()` envoie | E2E | ❌ |

### 12.8 Print Session Manager (JS)

| # | Test | Type | Existe |
|---|------|------|--------|
| 12.8.1 | `PrintSessionManager::startMonitoring()` démarre | E2E | ❌ |
| 12.8.2 | `PrintSessionManager::stopMonitoring()` arrête | E2E | ❌ |
| 12.8.3 | `PrintSessionManager::showToast()` notification | E2E | ❌ |
| 12.8.4 | `PrintSessionManager::showSessionModal()` modal | E2E | ❌ |
| 12.8.5 | `PrintSessionManager::assignJobToSession()` assigne | E2E | ❌ |

---

## 13. IA / RAG / Vectorisation

### 13.1 RAG Chat API

| # | Test | Type | Existe |
|---|------|------|--------|
| 13.1.1 | `chat_rag.php` — question simple retourne réponse | Feature | ❌ |
| 13.1.2 | `chat_rag.php` — streaming SSE fonctionne | Feature | ❌ |
| 13.1.3 | `chat_rag.php` — réponse contextuelle (bibliothèque) | Feature | ❌ |
| 13.1.4 | `chat_rag.php` — mode fast vs pro | Feature | ❌ |

### 13.2 Search & Vectorisation

| # | Test | Type | Existe |
|---|------|------|--------|
| 13.2.1 | `search_chunks.php` retourne des chunks | Feature | ❌ |
| 13.2.2 | `trigger_vectorization.php` lance la vectorisation | Feature | ❌ |
| 13.2.3 | `install_local_ai.php` installe l'IA locale | Feature | ❌ |
| 13.2.4 | `save_ai_settings.php` sauvegarde les settings | Feature | ❌ |
| 13.2.5 | `start_indexing.php` démarre l'indexation | Feature | ❌ |
| 13.2.6 | `get_indexing_status.php` retourne le statut | Feature | ❌ |

### 13.3 Markdown Migration

| # | Test | Type | Existe |
|---|------|------|--------|
| 13.3.1 | `trigger_markdown_migration.php` lance | Feature | ❌ |
| 13.3.2 | `get_markdown_migration_status.php` statut | Feature | ❌ |
| 13.3.3 | `get_markdown_migration_logs.php` logs | Feature | ❌ |
| 13.3.4 | `stop_markdown_migration.php` arrête | Feature | ❌ |

### 13.4 Background Workers

| # | Test | Type | Existe |
|---|------|------|--------|
| 13.4.1 | `background_studio_task.php` exécute une tâche | Feature | ❌ |
| 13.4.2 | `background_studio_ocr.php` OCR en arrière-plan | Feature | ❌ |
| 13.4.3 | `background_ocr.php` OCR + docx | Feature | ❌ |
| 13.4.4 | `background_analyze_ink.php` analyse encre | Feature | ❌ |
| 13.4.5 | `background_indexer.php` indexation | Feature | ❌ |

### 13.5 Maintenance Scripts

| # | Test | Type | Existe |
|---|------|------|--------|
| 13.5.1 | `process_markdown_chunks.php` pipeline PDF→MD | Feature | ❌ |
| 13.5.2 | `vectorize_chunks.php` embed les chunks | Feature | ❌ |
| 13.5.3 | `compress_images.php` compresse les images base64 | Feature | ❌ |

### 13.6 Frontend IA (JS)

| # | Test | Type | Existe |
|---|------|------|--------|
| 13.6.1 | `triggerAiSearch()` envoie une recherche IA | E2E | ❌ |
| 13.6.2 | `sendAiMessage()` envoie un message | E2E | ❌ |
| 13.6.3 | `addChatMessage()` ajoute un message | E2E | ❌ |
| 13.6.4 | `toggleAiChat()` toggle la sidebar | E2E | ❌ |
| 13.6.5 | `setAiMode()` change le mode | E2E | ❌ |
| 13.6.6 | `updateAiStatus()` met à jour le statut | E2E | ❌ |
| 13.6.7 | `admin-bibliotheque-ia.js`: `loadAiConfig()` | E2E | ❌ |
| 13.6.8 | `admin-bibliotheque-ia.js`: `saveAiConfig()` | E2E | ❌ |
| 13.6.9 | `admin-bibliotheque-ia.js`: `triggerReindex()` | E2E | ❌ |
| 13.6.10 | `admin-bibliotheque-ia.js`: `testAiConnection()` | E2E | ❌ |

---

## 14. Sessions Multi-Contact

| # | Test | Type | Existe |
|---|------|------|--------|
| 14.1 | `sessions.php` : `list` — liste les sessions actives | Feature | ✅ |
| 14.2 | `sessions.php` : `create` — crée une session | Feature | ✅ |
| 14.3 | `sessions.php` : `close` — ferme une session | Feature | ✅ |
| 14.4 | `sessions.php` : `reassign_job` — réassigne un job | Feature | ✅ |
| 14.5 | `sessions.php` : `last` — dernière session | Feature | ✅ |
| 14.6 | `sessions.php` : `close_all` — ferme toutes | Feature | ✅ |
| 14.7 | `SessionManagerTest` — flux complet | Feature | ✅ |
| 14.8 | Session sans jobs se ferme proprement | Feature | ❌ |
| 14.9 | Réassignation dans session inactive échoue | Feature | ❌ |

---

## 15. Notifications & Maintenance

### 15.1 Secure Purge

| # | Test | Type | Existe |
|---|------|------|--------|
| 15.1.1 | `secure_purge.php` supprime les jobs >7 jours | Feature | ✅ |
| 15.1.2 | `secure_delete()` écrase le fichier avant suppression | Unit | ✅ |
| 15.1.3 | `resolve_local_path()` résout correctement | Unit | ❌ |
| 15.1.4 | `rrmdir_secure()` supprime récursivement | Unit | ❌ |

### 15.2 Maintenance

| # | Test | Type | Existe |
|---|------|------|--------|
| 15.2.1 | `run_background_maintenance.php` exécute la maintenance | Feature | ❌ |
| 15.2.2 | `check_ghostscript.php` vérifie Ghostscript | Feature | ❌ |
| 15.2.3 | `debug_paths.php` retourne les chemins debug | Feature | ❌ |

### 15.3 System Health

| # | Test | Type | Existe |
|---|------|------|--------|
| 15.3.1 | `checkSystemHealth()` vérifie l'état système | Feature | ✅ |
| 15.3.2 | `getSystemHealthReport()` génère le rapport | Feature | ✅ |
| 15.3.3 | `getMissingPhpExtensions()` extensions manquantes | Feature | ✅ |
| 15.3.4 | `checkBinary()` vérifie un binaire | Unit | ❌ |
| 15.3.5 | `getBinaryStatusReport()` rapport binaire | Unit | ❌ |
| 15.3.6 | `checkDiskSpace()` espace disque | Unit | ❌ |
| 15.3.7 | `checkDatabaseHealth()` santé BD | Unit | ❌ |
| 15.3.8 | `checkMemoryUsage()` mémoire | Unit | ❌ |

### 15.4 Binary Utilities

| # | Test | Type | Existe |
|---|------|------|--------|
| 15.4.1 | `get_binary_path()` résout le chemin | Unit | ❌ |
| 15.4.2 | `get_gs_path()` chemin Ghostscript | Unit | ❌ |
| 15.4.3 | `get_imagick_path()` chemin ImageMagick | Unit | ❌ |
| 15.4.4 | `get_pcl6_path()` chemin PCL6 | Unit | ❌ |
| 15.4.5 | `run_ghostscript()` exécute GS | Unit | ❌ |
| 15.4.6 | `run_imagick()` exécute IM | Unit | ❌ |

### 15.5 Upload Aide PDF

| # | Test | Type | Existe |
|---|------|------|--------|
| 15.5.1 | `upload_aide_pdf.php` : `upload` | Feature | ✅ |
| 15.5.2 | `upload_aide_pdf.php` : `list` | Feature | ✅ |
| 15.5.3 | `upload_aide_pdf.php` : `delete` | Feature | ✅ |
| 15.5.4 | `formatFileSize()` formatage | Unit | ❌ |
| 15.5.5 | `sanitizeFileName()` sanitisation | Unit | ❌ |

---

## 16. i18n / Traductions

### 16.1 I18nManager

| # | Test | Type | Existe |
|---|------|------|--------|
| 16.1.1 | `getInstance()` singleton | Unit | ✅ |
| 16.1.2 | `detectLanguage()` détecte la langue | Unit | ✅ |
| 16.1.3 | `loadTranslations()` charge les traductions | Unit | ✅ |
| 16.1.4 | `get()` retourne la traduction par clé | Unit | ✅ |
| 16.1.5 | `setLanguage()` change la langue | Unit | ✅ |
| 16.1.6 | `getAvailableLanguages()` liste les langues | Unit | ✅ |
| 16.1.7 | `__()` fonction alias | Unit | ✅ |
| 16.1.8 | Fallback si clé inexistante | Unit | ❌ |
| 16.1.9 | `reloadTranslations()` recharge | Unit | ❌ |

### 16.2 TranslationManager (Admin)

| # | Test | Type | Existe |
|---|------|------|--------|
| 16.2.1 | `getTranslations()` retourne les traductions | Feature | ✅ |
| 16.2.2 | `saveTranslations()` sauvegarde | Feature | ✅ |
| 16.2.3 | `getAllTranslationKeys()` toutes les clés | Feature | ✅ |
| 16.2.4 | `getTranslationValue()` une valeur | Feature | ✅ |
| 16.2.5 | `updateTranslation()` met à jour | Feature | ✅ |
| 16.2.6 | `getAvailableLanguages()` langues dispo | Feature | ✅ |
| 16.2.7 | `getTranslationStats()` stats | Feature | ✅ |
| 16.2.8 | `exportToCSV()` export CSV | Feature | ✅ |
| 16.2.9 | `importFromCSV()` import CSV | Feature | ✅ |
| 16.2.10 | `getPageStats()` stats par page | Feature | ❌ |
| 16.2.11 | `getPageTranslations()` traductions par page | Feature | ❌ |

### 16.3 Admin Translations (JS)

| # | Test | Type | Existe |
|---|------|------|--------|
| 16.3.1 | `loadTranslations()` charge | E2E | ❌ |
| 16.3.2 | `saveTranslation()` sauvegarde | E2E | ❌ |
| 16.3.3 | `bulkSaveTranslations()` bulk save | E2E | ❌ |
| 16.3.4 | `exportTranslations()` exporte | E2E | ❌ |
| 16.3.5 | `importTranslations()` importe | E2E | ❌ |
| 16.3.6 | `filterTranslations()` filtre côté client | E2E | ❌ |

### 16.4 Inline Translation (JS)

| # | Test | Type | Existe |
|---|------|------|--------|
| 16.4.1 | `InlineTranslationEditor::init()` initialise | E2E | ❌ |
| 16.4.2 | `editTranslation()` ouvre l'édition | E2E | ❌ |
| 16.4.3 | `saveTranslation()` sauvegarde | E2E | ❌ |
| 16.4.4 | `cancelEdit()` annule | E2E | ❌ |

---

## 17. Setup & Installation

### 17.1 Setup (PHP)

| # | Test | Type | Existe |
|---|------|------|--------|
| 17.1.1 | `setup.php` affiche le wizard | Feature | ✅ |
| 17.1.2 | `setup_save.php` sauvegarde la config | Feature | ✅ |
| 17.1.3 | `configure_prices()` configure les prix par défaut | Feature | ✅ |
| 17.1.4 | `initialize_cons_table()` initialise les compteurs | Feature | ✅ |
| 17.1.5 | `setup_upload.php` gère l'upload | Feature | ❌ |
| 17.1.6 | `installation.php` page d'installation | Feature | ❌ |
| 17.1.7 | `lang.php` sélection de langue | Feature | ❌ |
| 17.1.8 | `create_password.php` crée le mot de passe | Feature | ❌ |

### 17.2 Setup (JS)

| # | Test | Type | Existe |
|---|------|------|--------|
| 17.2.1 | `Setup::loadSystemPrinters()` détecte les imprimantes | E2E | ❌ |
| 17.2.2 | `Setup::selectPrinter()` sélectionne | E2E | ❌ |
| 17.2.3 | `Setup::selectMachineType()` type de machine | E2E | ❌ |
| 17.2.4 | `Setup::addMachine()` / `removeMachine()` | E2E | ❌ |
| 17.2.5 | `Setup::showManualPrinterForm()` formulaire manuel | E2E | ❌ |
| 17.2.6 | `Setup::addTambour()` / `removeTambour()` | E2E | ❌ |
| 17.2.7 | `Setup::validateForm()` validation | E2E | ❌ |
| 17.2.8 | `Setup::submitSetup()` soumet | E2E | ❌ |
| 17.2.9 | `create-password.js`: `validatePasswords()` | E2E | ❌ |
| 17.2.10 | `create-password.js`: `submitPassword()` | E2E | ❌ |

---

## 18. Frontend JS — Composants UI

### 18.1 Task Manager

| # | Test | Type | Existe |
|---|------|------|--------|
| 18.1.1 | `TaskManager::addTask()` ajoute une tâche | E2E | ❌ |
| 18.1.2 | `TaskManager::updateTaskProgress()` met à jour la progression | E2E | ❌ |
| 18.1.3 | `TaskManager::completeTask()` termine | E2E | ❌ |
| 18.1.4 | `TaskManager::failTask()` échec | E2E | ❌ |
| 18.1.5 | `TaskManager::removeTask()` supprime | E2E | ❌ |

### 18.2 Updater UI

| # | Test | Type | Existe |
|---|------|------|--------|
| 18.2.1 | `initUpdater()` initialise | E2E | ❌ |
| 18.2.2 | `checkForUpdates()` vérifie | E2E | ❌ |
| 18.2.3 | `showUpdateNotification()` notifie | E2E | ❌ |
| 18.2.4 | `downloadAndInstall()` installe | E2E | ❌ |

### 18.3 Lazy Loading

| # | Test | Type | Existe |
|---|------|------|--------|
| 18.3.1 | `initLazyLoading()` configure l'observer | E2E | ❌ |
| 18.3.2 | `observeImages()` observe les images | E2E | ❌ |

### 18.4 Admin Warning

| # | Test | Type | Existe |
|---|------|------|--------|
| 18.4.1 | `AdminWarning.show()` affiche l'avertissement | E2E | ❌ |
| 18.4.2 | Dismissable fonctionne | E2E | ❌ |

### 18.5 Modal Global (showAppModal)

| # | Test | Type | Existe |
|---|------|------|--------|
| 18.5.1 | `showAppModal` type alert affiche le modal | E2E | ❌ |
| 18.5.2 | `showAppModal` type confirm retourne true/false | E2E | ❌ |
| 18.5.3 | `showAppModal` type prompt retourne la valeur | E2E | ❌ |
| 18.5.4 | `showAppModal` onConfirm callback fonctionne | E2E | ❌ |

### 18.6 Admin Warning

| # | Test | Type | Existe |
|---|------|------|--------|
| 18.6.1 | Vérification des droits admin (Electron) | E2E | ❌ |
| 18.6.2 | Fallback en mode web (pas de electronAPI) | E2E | ❌ |

---

## 19. Frontend JS — Pages

### 19.1 Accueil (Dashboard)

| # | Test | Type | Existe |
|---|------|------|--------|
| 19.1.1 | La page d'accueil charge sans erreur JS | E2E | ❌ |
| 19.1.2 | Le warning admin s'affiche (si pas admin) | E2E | ❌ |
| 19.1.3 | Les widgets/stats s'affichent | E2E | ❌ |
| 19.1.4 | Les actualités s'affichent | E2E | ❌ |

### 19.2 Aide Machines (Frontend)

| # | Test | Type | Existe |
|---|------|------|--------|
| 19.2.1 | Sélection machine charge l'aide | E2E | ❌ |
| 19.2.2 | `toggleMachineContent()` expand/collapse | E2E | ❌ |
| 19.2.3 | `toggleQA()` expand/collapse réponse | E2E | ❌ |
| 19.2.4 | Les images PDF d'aide s'affichent | E2E | ❌ |

### 19.3 Admin BDD

| # | Test | Type | Existe |
|---|------|------|--------|
| 19.3.1 | `showRenameForm()` affiche le formulaire | E2E | ❌ |
| 19.3.2 | `confirmDBAction()` confirmation fonctionne | E2E | ❌ |
| 19.3.3 | Switch de base fonctionne | E2E | ❌ |
| 19.3.4 | Backup/Restore depuis l'UI | E2E | ❌ |

### 19.4 Admin Emails

| # | Test | Type | Existe |
|---|------|------|--------|
| 19.4.1 | Liste des emails affichée | E2E | ❌ |
| 19.4.2 | `confirmEmailAction()` fonctionne | E2E | ❌ |
| 19.4.3 | Suppression d'email | E2E | ❌ |

### 19.5 Admin Machines

| # | Test | Type | Existe |
|---|------|------|--------|
| 19.5.1 | Liste des machines affichée | E2E | ❌ |
| 19.5.2 | Ajout de machine (formulaire) | E2E | ❌ |
| 19.5.3 | Modification de machine | E2E | ❌ |
| 19.5.4 | Suppression de machine | E2E | ❌ |
| 19.5.5 | Édition des tambours | E2E | ❌ |

### 19.6 Admin Edit

| # | Test | Type | Existe |
|---|------|------|--------|
| 19.6.1 | `confirmDeleteTirage()` confirmation | E2E | ❌ |
| 19.6.2 | Auto-calcul prix/cb | E2E | ❌ |

### 19.7 Admin Stats

| # | Test | Type | Existe |
|---|------|------|--------|
| 19.7.1 | Quill editor initialise | E2E | ❌ |
| 19.7.2 | Formulaire soumet correctement | E2E | ❌ |

### 19.8 PNG to PDF

| # | Test | Type | Existe |
|---|------|------|--------|
| 19.8.1 | Page charge sans erreur | E2E | ❌ |
| 19.8.2 | Conversion PNG→PDF fonctionne | E2E | ❌ |

### 19.9 Base/Footer/Header

| # | Test | Type | Existe |
|---|------|------|--------|
| 19.9.1 | Bootstrap tooltips initialisés | E2E | ❌ |
| 19.9.2 | Bootstrap dropdowns fonctionnent | E2E | ❌ |
| 19.9.3 | F3 intercepté (pas de search bar) | E2E | ❌ |
| 19.9.4 | `window.isElectronMode` défini | E2E | ❌ |
| 19.9.5 | Background maintenance lancé au chargement | E2E | ❌ |

---

## 20. Playwright E2E Navigateur

> Tests complets de bout en bout naviguant dans l'application comme un utilisateur réel.

### 20.1 Smoke Tests (existants, à enrichir)

| # | Scénario | Priorité | Existe |
|---|----------|----------|--------|
| 20.1.1 | Le serveur démarre et la homepage charge | Haute | ✅ |
| 20.1.2 | Navigation vers toutes les pages principales | Haute | ❌ |
| 20.1.3 | Aucune erreur JS console sur chaque page | Haute | ❌ |

### 20.2 Flux Setup Complet

| # | Scénario | Priorité | Existe |
|---|----------|----------|--------|
| 20.2.1 | Premier lancement → wizard setup s'affiche | Haute | ❌ |
| 20.2.2 | Détection imprimantes → sélection machine | Haute | ❌ |
| 20.2.3 | Configuration prix → sauvegarde | Haute | ❌ |
| 20.2.4 | Création mot de passe admin → login | Haute | ❌ |
| 20.2.5 | Redirection vers dashboard après setup | Haute | ❌ |

### 20.3 Flux Machine CRUD

| # | Scénario | Priorité | Existe |
|---|----------|----------|--------|
| 20.3.1 | Ajouter un photocopieur via l'admin | Haute | ❌ |
| 20.3.2 | Ajouter un duplicateur via l'admin | Haute | ❌ |
| 20.3.3 | Modifier le nom d'une machine | Haute | ❌ |
| 20.3.4 | Éditer les tambours d'un duplicateur | Haute | ❌ |
| 20.3.5 | Supprimer une machine | Haute | ❌ |
| 20.3.6 | Vérifier que la machine supprimée n'apparaît plus | Moyenne | ❌ |

### 20.4 Flux Tirage Complet

| # | Scénario | Priorité | Existe |
|---|----------|----------|--------|
| 20.4.1 | Soumettre un tirage photocopieur | Haute | ❌ |
| 20.4.2 | Soumettre un tirage duplicateur | Haute | ❌ |
| 20.4.3 | Tirage multi-machines complet | Haute | ❌ |
| 20.4.4 | Le tirage apparaît dans l'admin tirage | Haute | ❌ |
| 20.4.5 | Marquer le tirage comme payé | Haute | ❌ |
| 20.4.6 | Supprimer le tirage | Moyenne | ❌ |
| 20.4.7 | Exporter en CSV | Moyenne | ❌ |
| 20.4.8 | Filtrer les tirages par machine/date | Moyenne | ❌ |

### 20.5 Flux Changement Consommables

| # | Scénario | Priorité | Existe |
|---|----------|----------|--------|
| 20.5.1 | Sélectionner un duplicateur → champs dynamiques | Haute | ❌ |
| 20.5.2 | Soumettre un changement valide | Haute | ❌ |
| 20.5.3 | Le changement apparaît dans l'historique | Haute | ❌ |
| 20.5.4 | Filtrer l'historique par machine | Moyenne | ❌ |

### 20.6 Flux Bibliothèque Complet

| # | Scénario | Priorité | Existe |
|---|----------|----------|--------|
| 20.6.1 | Upload un PDF dans la bibliothèque | Haute | ❌ |
| 20.6.2 | Le fichier apparaît dans la liste | Haute | ❌ |
| 20.6.3 | Rechercher le fichier par mot-clé | Haute | ❌ |
| 20.6.4 | Filtrer par tag | Haute | ❌ |
| 20.6.5 | Éditer les métadonnées | Haute | ❌ |
| 20.6.6 | Ouvrir le visualiseur PDF | Haute | ❌ |
| 20.6.7 | Naviguer les pages du PDF | Moyenne | ❌ |
| 20.6.8 | Imprimer le fichier | Moyenne | ❌ |
| 20.6.9 | Supprimer le fichier | Haute | ❌ |
| 20.6.10 | Vérifier le fichier supprimé n'apparaît plus | Haute | ❌ |
| 20.6.11 | Rescan / réindexation | Moyenne | ❌ |
| 20.6.12 | Monitoring de l'indexation (barre de progression) | Moyenne | ❌ |
| 20.6.13 | Upload drag-and-drop | Haute | ❌ |

### 20.7 Flux IA / RAG

| # | Scénario | Priorité | Existe |
|---|----------|----------|--------|
| 20.7.1 | Ouvrir la sidebar chat IA | Haute | ❌ |
| 20.7.2 | Envoyer un message → recevoir une réponse | Haute | ❌ |
| 20.7.3 | La réponse streaming s'affiche progressivement | Haute | ❌ |
| 20.7.4 | Switcher mode fast/pro | Moyenne | ❌ |
| 20.7.5 | L'historique chat persiste pendant la session | Moyenne | ❌ |
| 20.7.6 | Admin : configurer les settings IA | Haute | ❌ |
| 20.7.7 | Admin : tester la connexion IA | Moyenne | ❌ |
| 20.7.8 | Admin : lancer la réindexation | Moyenne | ❌ |

### 20.8 Flux Studio PDF

| # | Scénario | Priorité | Existe |
|---|----------|----------|--------|
| 20.8.1 | Ouvrir le Studio | Haute | ❌ |
| 20.8.2 | Charger un PDF (drag-and-drop) | Haute | ❌ |
| 20.8.3 | Les thumbnails s'affichent | Haute | ❌ |
| 20.8.4 | Naviguer entre les pages | Haute | ❌ |
| 20.8.5 | Appliquer un filtre (contraste, luminosité) | Moyenne | ❌ |
| 20.8.6 | Appliquer le filtre bitmap | Moyenne | ❌ |
| 20.8.7 | Switcher d'outil (sidebar) | Moyenne | ❌ |
| 20.8.8 | Appliquer l'imposition (PPP, tracts, brochure) | Haute | ❌ |
| 20.8.9 | Appliquer le redressement | Moyenne | ❌ |
| 20.8.10 | Redimensionner | Moyenne | ❌ |
| 20.8.11 | Rogner | Moyenne | ❌ |
| 20.8.12 | Lancer OCR | Moyenne | ❌ |
| 20.8.13 | Exporter le PDF | Haute | ❌ |
| 20.8.14 | Exporter en PNG | Moyenne | ❌ |
| 20.8.15 | Sauvegarder en bibliothèque | Moyenne | ❌ |
| 20.8.16 | Pleine page (lightbox) | Basse | ❌ |

### 20.9 Flux Studio — Modification

| # | Scénario | Priorité | Existe |
|---|----------|----------|--------|
| 20.9.1 | Ajouter du texte sur le PDF | Moyenne | ❌ |
| 20.9.2 | Ajouter un masque (redact) | Moyenne | ❌ |
| 20.9.3 | Ajouter un numero de page | Moyenne | ❌ |
| 20.9.4 | Changer la couleur/taille | Basse | ❌ |
| 20.9.5 | Utiliser la gomme | Basse | ❌ |
| 20.9.6 | Effacer tout | Basse | ❌ |

### 20.10 Flux Studio — Montage

| # | Scénario | Priorité | Existe |
|---|----------|----------|--------|
| 20.10.1 | Initialiser le mode montage | Moyenne | ❌ |
| 20.10.2 | Ajouter une planche | Moyenne | ❌ |
| 20.10.3 | Upload des fichiers pour montage | Moyenne | ❌ |
| 20.10.4 | Drag sur le canvas | Moyenne | ❌ |
| 20.10.5 | Générer le PDF montage | Moyenne | ❌ |

### 20.11 Flux Studio — Metadata

| # | Scénario | Priorité | Existe |
|---|----------|----------|--------|
| 20.11.1 | Charger les métadonnées PDF | Moyenne | ❌ |
| 20.11.2 | Éditer et sauvegarder | Moyenne | ❌ |

### 20.12 Flux Admin Complet

| # | Scénario | Priorité | Existe |
|---|----------|----------|--------|
| 20.12.1 | Login admin → dashboard admin | Haute | ❌ |
| 20.12.2 | Gérer les actualités (CRUD) | Moyenne | ❌ |
| 20.12.3 | Gérer l'aide machines (Q&R CRUD) | Moyenne | ❌ |
| 20.12.4 | Gérer les traductions | Moyenne | ❌ |
| 20.12.5 | Gérer les prix | Moyenne | ❌ |
| 20.12.6 | Backup/Restore base de données | Haute | ❌ |
| 20.12.7 | Switch de base de données | Moyenne | ❌ |
| 20.12.8 | Statistiques s'affichent | Moyenne | ❌ |
| 20.12.9 | Admin imprimantes : monitoring | Moyenne | ❌ |

### 20.13 Flux Auto Tirage

| # | Scénario | Priorité | Existe |
|---|----------|----------|--------|
| 20.13.1 | Ouvrir la page auto tirage | Haute | ❌ |
| 20.13.2 | Créer une session | Haute | ❌ |
| 20.13.3 | Assigner un job à la session | Haute | ❌ |
| 20.13.4 | Éditer un job | Moyenne | ❌ |
| 20.13.5 | Bulk move buffer → session | Moyenne | ❌ |
| 20.13.6 | Bulk delete | Moyenne | ❌ |
| 20.13.7 | Terminer la session | Haute | ❌ |

### 20.14 Flux Multi-Session

| # | Scénario | Priorité | Existe |
|---|----------|----------|--------|
| 20.14.1 | Modal de session s'affiche à l'ouverture | Moyenne | ❌ |
| 20.14.2 | Sélectionner une session existante | Moyenne | ❌ |
| 20.14.3 | Créer une nouvelle session depuis le modal | Moyenne | ❌ |

### 20.15 Compatibilité Web (sans Electron)

| # | Scénario | Priorité | Existe |
|---|----------|----------|--------|
| 20.15.1 | L'app fonctionne sans `window.electronAPI` | Haute | ❌ |
| 20.15.2 | Pas d'erreurs JS en mode web | Haute | ❌ |
| 20.15.3 | Les boutons Electron masqués en mode web | Moyenne | ❌ |
| 20.15.4 | Les traductions fonctionnent | Moyenne | ❌ |

### 20.16 i18n

| # | Scénario | Priorité | Existe |
|---|----------|----------|--------|
| 20.16.1 | Changer de langue depuis l'admin | Moyenne | ❌ |
| 20.16.2 | Toutes les pages s'affichent dans la langue choisie | Haute | ❌ |
| 20.16.3 | Aucun texte brut (non traduit) visible | Haute | ❌ |

### 20.17 Sécurité

| # | Scénario | Priorité | Existe |
|---|----------|----------|--------|
| 20.17.1 | Accès admin bloqué sans login | Haute | ❌ |
| 20.17.2 | Path traversal bloqué sur les fichiers | Haute | ❌ |
| 20.17.3 | CSRF token requis sur les POST admin | Haute | ❌ |
| 20.17.4 | XSS refusé dans les inputs | Haute | ❌ |
| 20.17.5 | Logout détruit la session | Haute | ❌ |

### 20.18 Erreurs & Résilience

| # | Scénario | Priorité | Existe |
|---|----------|----------|--------|
| 20.18.1 | Erreur réseau → affichage gracieux | Moyenne | ❌ |
| 20.18.2 | Fichier corrompu → pas de crash | Moyenne | ❌ |
| 20.18.3 | Upload de trop gros fichier → message d'erreur | Moyenne | ❌ |
| 20.18.4 | Double-clic sur bouton → pas de double soumission | Moyenne | ❌ |

---

## 21. Récapitulatif & Priorités

### Couverture actuelle vs objectif

| Catégorie | Tests existants | Tests à créer | Couverture |
|-----------|----------------|---------------|------------|
| Core Framework PHP | 0 | ~30 | 0% |
| Auth & Sécurité | 0 | ~12 | 0% |
| BDD & Migrations | 0 | ~15 | 0% |
| Machines | 6 | ~15 | 29% |
| Tirages | 15 | ~35 | 30% |
| Consommables | 4 | ~15 | 21% |
| Prix | 8 | ~14 | 36% |
| Admin Général | 16 | ~15 | 52% |
| Bibliothèque | 8 | ~40 | 17% |
| Studio PDF | 10 | ~55 | 15% |
| Impression | 8 | ~30 | 21% |
| IA / RAG | 0 | ~25 | 0% |
| Sessions | 6 | ~3 | 67% |
| Maintenance | 4 | ~15 | 21% |
| i18n | 14 | ~12 | 54% |
| Setup | 4 | ~14 | 22% |
| **Frontend JS (total)** | **0** | **~180** | **0%** |
| **Playwright E2E** | **1** | **~85** | **1%** |
| **TOTAL** | **~84** | **~620** | **~12%** |

### Priorités d'implémentation

#### P0 — Critique (Sprint 1)
1. **Playwright E2E** : Smoke test enrichi (toutes les pages sans erreur JS)
2. **Auth** : Login/logout, protection admin, CSRF
3. **Sécurité** : Path traversal, XSS
4. **Bibliothèque** : Upload, recherche, suppression (flux core)
5. **Setup** : Premier lancement complet
6. **Machines** : CRUD complet

#### P1 — Haute (Sprint 2)
7. **Tirages** : CRUD + admin + multi-machines
8. **Studio** : Chargement PDF, imposition, export
9. **Consommables** : Changement + historique
10. **Admin** : News, aide, prix
11. **Sessions** : Multi-contact
12. **Frontend JS** : Calcul prix, changement, tirage

#### P2 — Moyenne (Sprint 3)
13. **IA / RAG** : Chat, settings, vectorisation
14. **Studio avancé** : Montage, modification, metadata
15. **i18n** : Traductions, inline editing
16. **Print engine** : Monitor, spool, dialog
17. **Frontend Studio** : Tous les outils JS
18. **Migrations** : Tests de compatibilité

#### P3 — Basse (Sprint 4)
19. **Riso Tools** : Séparation couleurs
20. **Maintenance scripts** : Tous les workers
21. **Compatibilité** : Cross-platform, edge cases
22. **Performance** : Tests de charge
23. **Updater** : Auto-update UI
24. **Lazy loading** : Images

---

## Annexe A : Fichiers de test à créer

### PHP (Pest) — Nouveaux fichiers

```
app/tests/Unit/CoreFrameworkTest.php          # conf, utilities, paths, error_handler
app/tests/Unit/SecurityTest.php               # CSRF, path traversal, XSS
app/tests/Unit/DatabaseManagerTest.php         # CRUD DB, migrations
app/tests/Unit/MachineCoreTest.php             # MachineManager class
app/tests/Unit/PricingCoreTest.php             # pricing.php functions
app/tests/Unit/ImpositionCoreTest.php          # placePage, drawMarks, metrics
app/tests/Unit/TractsImpositionTest.php        # tract-specific
app/tests/Unit/ImpositionLeafletTest.php       # leaflet-specific
app/tests/Unit/PdfUtilsCoreTest.php            # PDF utility functions
app/tests/Unit/BinaryUtilsTest.php             # binary resolution
app/tests/Unit/HealthCheckCoreTest.php         # system health
app/tests/Unit/ConsommationCoreTest.php        # consumable functions
app/tests/Unit/StatsCoreTest.php               # statistics functions
app/tests/Unit/BibliothequeCoreTest.php        # BibliothequeManager methods
app/tests/Unit/RisoToolsCoreTest.php           # color separation
app/tests/Unit/InkCoverageTest.php             # fill rate / ink analysis
app/tests/Feature/AuthFlowTest.php             # auth complete flow
app/tests/Feature/BibliothequeFullFlowTest.php # library complete flow
app/tests/Feature/StudioFullFlowTest.php       # studio complete flow
app/tests/Feature/SetupFullFlowTest.php        # setup wizard flow
app/tests/Feature/RagChatTest.php              # RAG chat API
app/tests/Feature/VectorizationTest.php        # vectorization pipeline
app/tests/Feature/MigrationTest.php            # all migrations
app/tests/Feature/MaintenanceTest.php          # maintenance endpoints
app/tests/Feature/SecurityFlowTest.php         # security end-to-end
app/tests/Feature/PrintEngineFlowTest.php      # print flow
app/tests/Feature/ApiGetPdfPathTest.php        # api/get_pdf_path.php endpoint validation
app/tests/Feature/ApiAjaxEditTamboursTest.php  # api/ajax_edit_tambours.php endpoint validation
```

### Python — Nouveaux fichiers

```
app/api/scripts/tests/test_pdf_to_semantic_chunks.py # pdf_to_semantic_chunks.py unit tests
app/api/scripts/tests/test_docling_copyfit.py        # docling_copyfit.py font scaling checks
app/api/scripts/tests/test_docling_export.py         # docling_export.py layout & markdown structure
app/api/scripts/tests/test_install_pipeline.py       # install.py systems & dependencies checks
```

### JS (Jest) — Nouveaux fichiers

```
tests/unit/calcul.test.js                      # calcul.js pricing functions
tests/unit/riso-tools.test.js                  # color separation algorithms
tests/unit/spool-analyzer.test.js              # spool file analysis
tests/unit/printer-monitor.test.js             # printer monitor
tests/unit/admin-checker.test.js               # admin rights check
tests/integration/bibliotheque-api.test.js     # library API integration
tests/integration/studio-api.test.js           # studio API integration
tests/integration/admin-api.test.js            # admin API integration
```

### JS (Playwright) — Nouveaux fichiers

```
tests/e2e/smoke-all-pages.test.js              # all pages load without error
tests/e2e/setup-wizard.test.js                 # complete setup flow
tests/e2e/machine-crud.test.js                 # machine management
tests/e2e/tirage-flow.test.js                  # print job flow
tests/e2e/changement-flow.test.js              # consumable change
tests/e2e/bibliotheque-flow.test.js            # library CRUD
tests/e2e/ai-chat.test.js                      # AI RAG chat
tests/e2e/studio-flow.test.js                  # studio PDF editing
tests/e2e/admin-flow.test.js                   # admin panel
tests/e2e/auto-tirage-flow.test.js             # auto print session
tests/e2e/i18n-flow.test.js                    # translations
tests/e2e/security-flow.test.js                # auth + security
tests/e2e/multi-session.test.js                # print sessions
tests/e2e/web-compat.test.js                   # without Electron
tests/e2e/error-resilience.test.js             # error scenarios
```

---

## Annexe B : Helpers à créer/étendre

| Helper | Fichier | Purpose |
|--------|---------|---------|
| `createTestMachine()` | `test_db_helpers.php` | Crée une machine de test rapide |
| `createTestTirage()` | `test_db_helpers.php` | Crée un tirage de test |
| `createTestLibraryFile()` | `test_db_helpers.php` | Crée un fichier bibliothèque de test |
| `mockGhostscript()` | `mock_binary.php` | Mock Ghostscript pour les tests |
| `mockImageMagick()` | `mock_binary.php` | Mock ImageMagick pour les tests |
| `createTestPDF()` | `test_helpers.php` | Génère un PDF de test dynamique |
| `loginAsAdmin()` | E2E helper | Helper Playwright pour login admin |
| `navigateToAdmin()` | E2E helper | Helper navigation admin |
| `waitForAjax()` | E2E helper | Attendre les requêtes AJAX |
| `uploadFileE2E()` | E2E helper | Upload fichier en E2E |
