# Plan de Refactoring — Vibe Code → Code Pro

## Contexte

Ce document est le résultat d'un audit architectural complet du projet Duplicator. Le projet est fonctionnel mais "vibe codé" — il nécessite un refactoring structurel pour devenir maintenable et professionnel.

**Projet :** Tauri 2.x + PHP/Caddy + SQLite  
**Fichiers analysés :** 271 PHP, 54 JS, 8 Rust  
**Date :** 25 juillet 2026

---

## État des lieux

| Problème | Volume |
|----------|--------|
| JS inline dans des fichiers PHP | **~14 300 lignes** dans 32 fichiers |
| Fichiers monolithes (>500 lignes) | **45 fichiers** |
| Fichiers monolithes (>1000 lignes) | **10 fichiers** |
| `var` au lieu de `const`/`let` | **923 occurrences** |
| `extract()` PHP (anti-sécurité) | **22 appels** dans 7 fichiers |
| Variables globales PHP | **13 fichiers** |
| Fichiers morts (patch, backup, debug) | **~20 fichiers** |
| Fichiers JS orphelins (jamais chargés) | **4 fichiers** |
| Dépendances npm inutilisées | **3 packages** |
| Dépendances Rust inutilisées | **5 crates** |
| Méthodes Tauri bridge mortes | **9 méthodes** |
| Conventions de nommage mélangées | **3 conventions** |
| Functions defined inside functions | **2 fichiers** |
| Deeply nested code (4+ levels) | **8 fichiers, 440+ blocs** |

---

## Phase 0 — Quick wins : Code mort (1 journée)

### 0.1 Fichiers PHP à supprimer

| Fichier | Raison | Lignes récupérées |
|---------|--------|-------------------|
| `app/view/bibliotheque_old_branch.html.php` | Copie de `bibliotheque.html.php` | 1 330 |
| `app/models/BibliothequeManager_old_branch.php` | Copie de `BibliothequeManager.php` | 860 |
| `app/phpunit.xml.bak` | Backup config | — |
| `app/api/patch_bg.php` | Réécrit du code source via regex preg_replace | — |
| `app/api/patch_ajax.php` | Idem | — |
| `app/api/patch_studio_process.php` | Idem | — |
| `app/api/fix_settings.php` | Idem — réécrit studio_process.php sans auth | — |
| `app/public/diag_pdf.php` | Debug exposé au web | — |
| `app/public/diag_post.php` | Idem | — |
| `app/public/test_web.php` | Idem | — |
| `app/brain/*/scratch/test_gliner.php` | Scratch avec paths hardcodés | — |
| `app/brain/*/scratch/test_all_models.php` | Idem | — |
| `app/brain/*/scratch/debug_ollama.php` | Idem | — |
| `app/brain/*/scratch/*.php` (6 autres) | Idem | — |
| `php-install-guide.html` | Guide temporaire root-level | — |

### 0.2 Fichiers JS root-level à supprimer

| Fichier | Raison |
|---------|--------|
| `alpha_spool.js` (206 lignes) | Script experimental, non référencé |
| `beta_spool.js` (231 lignes) | Idem |
| `debug-launch.js` (47 lignes) | Script debug |
| `test-updater.js` (28 lignes) | Script test |
| `scripts/check-db.js` (54 lignes) | Doublon exact de `scripts/dev/check-db.js` |

### 0.3 Fichiers JS orphelins dans app/public/js/

| Fichier | Raison |
|---------|--------|
| `js/bootstrap.js` | Seul `bootstrap.min.js` est chargé |
| `js/npm.js` | Jamais référencé par aucun `<script src=` |
| `js/web/viewer.js` | Jamais référencé |
| `js/web/debugger.js` | Jamais référencé |

### 0.4 Dépendances npm à supprimer (package.json)

| Package | Raison |
|---------|--------|
| `bcryptjs` | Jamais `require()` dans aucun fichier JS |
| `pdfjs-dist` | Jamais importé — le projet utilise le vendor `js/build/pdf.js` |
| `sqlite3` | Jamais importé — la DB est gérée par PHP/PDO |

### 0.5 Dépendances Rust à supprimer (src-tauri/Cargo.toml)

| Crate | Raison |
|-------|--------|
| `rusqlite` | Aucun `use rusqlite` — PHP gère toute la DB |
| `zip` | Aucun `use zip` dans les fichiers .rs |
| `tar` | Aucun `use tar` dans les fichiers .rs |
| `flate2` | Aucun `use flate2` dans les fichiers .rs |
| `libc` | Aucun `use libc` —功能/commenté |

### 0.6 Composer root à supprimer

`/home/ubuntu/dupli-electron-caddy/composer.json` ne contient que `voku/stop-words` qui est déjà déclaré dans `app/composer.json`. Supprimer le fichier racine et `vendor/` racine.

### 0.7 Méthodes Tauri bridge mortes (src-tauri/tauri-bridge.js)

Ces 9 méthodes sont exposées via `window.electronAPI` mais jamais appelées depuis le webview :

| Méthode | Action |
|---------|--------|
| `openFile` | Supprimer du bridge + handler Rust |
| `cleanupTmpFiles` | Idem |
| `getDatabasePath` | Idem |
| `getAppVersion` | Idem |
| `onPhpLog` | Idem |
| `onPhpFatal` | Idem |
| `onPhpStatus` | Idem |
| `onPrintMonitorError` | Idem |
| `onPrintMonitorStarted` | Idem |

### 0.8 Script de vérification post-suppression

```bash
# Vérifier qu'aucune référence ne reste
grep -r "bibliotheque_old_branch" app/
grep -r "BibliothequeManager_old_branch" app/
grep -r "alpha_spool\|beta_spool\|debug-launch\|test-updater" .
grep -r "patch_bg\|patch_ajax\|patch_studio_process\|fix_settings" app/
grep -r "diag_pdf\|diag_post\|test_web" app/public/
grep -r "bcryptjs\|pdfjs-dist\|sqlite3" package.json
grep -r "rusqlite\|use zip;\|use tar;\|use flate2;\|use libc;" src-tauri/src/
```

---

## Phase 1 — Extraire le JS inline des vues PHP (3-5 jours)

### Principe

Chaque fichier `.html.php` ne contient que du HTML + du PHP template + des variables JS transmises via `json_encode()`. Tout le JS logique va dans des fichiers `.js` séparés dans `app/public/js/`.

### 1.1 Priorité d'extraction (top 10)

| # | Fichier PHP | Lignes JS inline | Fichier JS cible | Priorité |
|---|-------------|------------------|-----------------|----------|
| 1 | `app/view/studio.html.php` | 3 883 | `app/public/js/studio.js` | CRITIQUE |
| 2 | `app/view/tirage_multimachines.html.php` | 1 786 | `app/public/js/tirage-multimachines.js` | HAUTE |
| 3 | `app/view/auto_tirage.html.php` | 1 764 | `app/public/js/auto-tirage.js` | HAUTE |
| 4 | `app/view/bibliotheque.html.php` | 1 048 | `app/public/js/bibliotheque.js` | HAUTE |
| 5 | `app/view/admin.imprimantes.html.php` | 719 | `app/public/js/admin-imprimantes.js` | MOYENNE |
| 6 | `app/view/admin.aide.html.php` | 634 | `app/public/js/admin-aide.js` | MOYENNE |
| 7 | `app/models/admin.php` | 583 | `app/public/js/admin-dashboard.js` | MOYENNE |
| 8 | `app/view/admin.machines.html.php` | 344 | `app/public/js/admin-machines.js` | BASSE |
| 9 | `app/view/admin.changes.html.php` | 364 | `app/public/js/admin-changes.js` | BASSE |
| 10 | `app/view/setup.html.php` | 360 | `app/public/js/setup.js` | BASSE |

**Total : ~11 485 lignes à extraire**

### 1.2 Pattern d'extraction

```php
<!-- AVANT (dans studio.html.php, 3883 lignes) -->
<!DOCTYPE html>
<html>
<head>...</head>
<body>
  <!-- HTML template -->
  <script>
    const API_URL = "<?= $api_url ?>";
    function initCanvas() { ... }
    // ... 3883 lignes de JS ...
  </script>
</body>
</html>

<!-- APRÈS -->
<!-- studio.html.php : HTML + PHP uniquement -->
<!DOCTYPE html>
<html>
<head>
  <script src="<?= $base_path ?>js/studio.js" defer></script>
</head>
<body>
  <!-- HTML template -->
  <script>
    // Seulement les données PHP à transmettre au JS
    const CONFIG = <?= json_encode([
        'api_url' => $api_url,
        'tmp_dir' => $tmp_dir,
        'user' => $_SESSION['user'] ?? null,
    ]) ?>;
  </script>
</body>
</html>
```

### 1.3 Migration des event handlers inline

Les ~228 `onclick=`, `onchange=`, `onsubmit=` etc. seront remplacés par des `addEventListener` dans les fichiers JS extraits :

```php
<!-- AVANT -->
<button onclick="deleteJob(<?= $job['id'] ?>)">Supprimer</button>
<select onchange="updatePrinter(this.value)">

<!-- APRÈS -->
<button data-job-id="<?= $job['id'] ?>" class="btn-delete-job">Supprimer</button>
<select class="printer-select" data-action="update-printer">
```

```javascript
// Dans le fichier JS extrait
document.querySelectorAll('.btn-delete-job').forEach(btn => {
    btn.addEventListener('click', (e) => {
        deleteJob(e.target.dataset.jobId);
    });
});
```

### 1.4 Fichiers PHP view sans extraction nécessaire

Ces fichiers ont peu ou pas de JS inline — pas besoin de les toucher :

- `app/view/admin.login.html.php` (70 lignes, 0 JS)
- `app/view/components/app-modal.html.php` (44 lignes)
- `app/view/components/session-modal.html.php` (65 lignes)
- `app/view/components/edit-job-modal.html.php`
- `app/view/error.html.php`
- `app/view/base.html.php` (45 lignes, 20 JS minimes)
- `app/view/header.html.php` (158 lignes, 6 JS)

### 1.5 Fichiers modérés à extraire en 2e vague

| Fichier | Lignes JS | Fichier JS cible |
|---------|-----------|-----------------|
| `admin.tirage.html.php` | 333 | `js/admin-tirage.js` |
| `admin.bibliotheque_ia.html.php` | 338 | `js/admin-bibliotheque-ia.js` |
| `print_dialog.html.php` | 307 | `js/print-dialog.js` |
| `changement.html.php` | 281 | `js/changement.js` |
| `admin_imprimantes.html.php` | 265 | `js/admin-imprimantes-list.js` |
| `components/print-modal.html.php` | 189 | `js/components/print-modal.js` |
| `components/global-task-manager.html.php` | 158 | `js/components/task-manager.js` |
| `admin.news.html.php` | 163 | `js/admin-news.js` |
| `admin_translations.html.php` | 130 | `js/admin-translations.js` |
| `create_password.html.php` | 225 | `js/create-password.js` |

---

## Phase 2 — Nettoyage JS global (1-2 jours)

### 2.1 Remplacer `var` par `const`/`let` (923 occurrences)

```bash
# Script automatique via ESLint
npx eslint --fix --rule 'no-var: error' 'app/public/js/**/*.js'
```

Répartition :
- PHP view inline JS : 445 occurrences
- Fichiers JS standalone : 478 occurrences
- Pire fichier : `tirage_multimachines.html.php` (181 `var`)

### 2.2 Unifier les patterns DOM-ready

Supprimer les 11 `$(document).ready()` restants, garder uniquement `document.addEventListener('DOMContentLoaded')`.

Fichiers concernés ( jQuery → vanilla JS ) :
- `tirage_multimachines.html.php`
- `admin.tirage.html.php`
- `admin.aide.html.php`
- `admin.machines.html.php`
- `admin.stats.html.php`
- `admin.bdd.html.php`
- Et 5 autres

### 2.3 Corriger les CDN sans SRI (Subresource Integrity)

```html
<!-- AVANT -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>

<!-- APRÈS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"
        integrity="sha384-XXXXX"
        crossorigin="anonymous"></script>
```

Fichiers concernés :
- `app/view/bibliotheque.html.php` — pdf.js CDN
- `app/view/admin_aide_machines.html.php` — summernote CDN (×2)

### 2.4 Corriger le double chargement de scripts

| Script | Fichier | Action |
|--------|---------|--------|
| `quill.min.js` | `admin.news.html.php` lignes 32 ET 116 | Supprimer le doublon |

### 2.5 Optimiser le chargement global

`calcul.js` et `lazy-loading.js` sont chargés sur **TOUTES les pages** via `base.html.php`. Vérifier si c'est nécessaire ou les charger uniquement sur les pages qui en ont besoin.

---

## Phase 3 — Nettoyage PHP (2-3 jours)

### 3.1 Supprimer les `extract()` (22 appels, 7 fichiers)

| Fichier | Nombre d'appels | Action |
|---------|----------------|--------|
| `app/models/BibliothequeManager.php` | **15** | Remplacer par accès explicites `$data['key']` |
| `app/models/admin.php` | 2 | Idem |
| `app/api/setup.php` | 2 | Idem |
| `app/api/create_password.php` | 1 | Idem |
| `app/controler/functions/utilities.php` | 1 | Idem |
| `app/controler/functions/init.php` | 1 | Idem |

**Pourquoi c'est grave :** `extract()` crée des variables à partir d'un tableau — c'est une faille d'injection de variable si le tableau contient des clés non attendues.

### 3.2 Réduire les variables globales (13 fichiers)

Fichiers utilisant `global $` ou `$GLOBALS` :
- `app/models/admin.php`
- `app/models/BibliothequeManager.php`
- `app/controler/functions/init.php`
- `app/controler/functions/database.php`
- `app/controler/functions/binary_utilities.php`
- `app/api/studio_process.php`
- `app/api/chat_rag.php`
- `app/api/save_auto_print.php`
- Et 5 autres

**Pattern de correction :**
```php
// AVANT
function maFonction() {
    global $db;
    $db->query(...);
}

// APRÈS
function maFonction(PDO $db) {
    $db->query(...);
}
```

### 3.3 Dédupliquer `normalizePath()` (4 définitions)

| Fichier | Action |
|---------|--------|
| `app/controler/functions/utilities.php` | GARDER (définition principale) |
| `app/controler/functions/bibliotheque.php` | SUPPRIMER, utiliser celle de utilities |
| `app/api/upload_aide_pdf.php` | SUPPRIMER, utiliser celle de utilities |
| `app/public/index.php` | SUPPRIMER, utiliser celle de utilities |

Toutes ont des guards `function_exists()` — les supprimer ne cassera pas le code.

### 3.4 HTML dans les fichiers non-view (12 fichiers)

Ces fichiers mélangent logique PHP et HTML brut :

| Fichier | Type | Action |
|---------|------|--------|
| `app/public/index.php` | Routeur | Extraire le HTML dans des vues |
| `app/models/admin.php` | Model | Séparer presentation de logique |
| `app/models/admin/DatabaseManager.php` | Model | Idem |
| `app/controler/functions/email.php` | Function | Extraire les templates email |
| `app/controler/functions/utilities.php` | Function | Supprimer le HTML de debug |

### 3.5 Functions defined inside functions

| Fichier | Fonction imbriquée | Action |
|---------|-------------------|--------|
| `app/controler/functions/utilities.php:268` | `normalizePath()` dans une autre fonction | Déplacer au scope global |
| `app/view/admin.tirage.html.php` | 12 fonctions JS dans un `<script>` | Sera corrigé en Phase 1 (extraction JS) |

### 3.6 Deeply nested code (anti-pattern)

| Fichier | Blocs imbriqués 4+ | Action |
|---------|-------------------|--------|
| `app/api/studio_process.php` | **122** | Refactoriser en fonctions plus petites |
| `app/models/BibliothequeManager.php` | **72** | Idem |
| `app/models/admin.php` | **56** | Idem |
| `app/view/studio.html.php` | **51** | Sera corrigé en Phase 1 |
| `app/models/admin/PriceManager.php` | **39** | Idem |
| `app/api/chat_rag.php` | 35 | Idem |
| `app/api/check-print-jobs.php` | 28 | Idem |
| `app/api/sessions.php` | 24 | Idem |

---

## Phase 4 — Architecture et nommage (2-3 jours)

### 4.1 Conventions de nommage unifiées

| Type | Convention | Exemple |
|------|-----------|---------|
| PHP functions | `snake_case` | `get_pending_jobs()` |
| PHP classes | `PascalCase` | `BibliothequeManager` |
| PHP fichiers API | `snake_case.php` | `save_auto_print.php` |
| PHP fichiers model | `snake_case.php` | `admin.php` |
| PHP fichiers view | `snake_case.html.php` | `admin_imprimantes.html.php` |
| JS fichiers | `kebab-case.js` | `print-session-manager.js` |
| JS fonctions | `camelCase` | `initCanvas()` |
| JS constantes | `UPPER_SNAKE_CASE` | `API_URL` |
| CSS classes | `kebab-case` | `print-modal` |

### 4.2 Fichiers PHP nécessitant un renommage

| Fichier actuel | Nouveau nom | Convention |
|---------------|-------------|------------|
| `app/view/admin.aide.html.php` | `admin_aide.html.php` | snake_case |
| `app/view/admin.bdd.html.php` | `admin_bdd.html.php` | snake_case |
| `app/view/admin.edit.html.php` | `admin_edit.html.php` | snake_case |
| `app/view/admin.machines.html.php` | `admin_machines.html.php` | snake_case |
| `app/view/admin.mots.html.php` | `admin_mots.html.php` | snake_case |
| `app/view/admin.prix.html.php` | `admin_prix.html.php` | snake_case |
| `app/view/admin.stats.html.php` | `admin_stats.html.php` | snake_case |
| `app/view/admin.emails.html.php` | `admin_emails.html.php` | snake_case |
| `app/view/admin.imprimantes.html.php` | `admin_imprimantes.html.php` (doublon — fusionner) |
| `app/view/admin.tirage.html.php` | `admin_tirage.html.php` | snake_case |
| `app/view/admin.changes.html.php` | `admin_changes.html.php` | snake_case |
| `app/view/admin.bibliotheque_ia.html.php` | `admin_bibliotheque_ia.html.php` | snake_case |
| `app/view/admin.news.html.php` | `admin_news.html.php` | snake_case |
| `app/view/admin.login.html.php` | `admin_login.html.php` | snake_case |
| `app/view/admin_translations.html.php` | (déjà ok) | — |

**Note :** Le routeur `index.php` utilise la variable `$page` pour charger les vues (`models/$page.php`).Après renommage, il faudra mettre à jour les appels dans le routeur.

### 4.3 Corriger le dossier `controler/` → `controller/`

```bash
mv app/controler app/controller
# Puis mettre à jour TOUS les require_once/include dans le codebase
grep -r "controler/" app/ --include="*.php" -l | xargs sed -i 's/controler/controller/g'
```

### 4.4 Autoloader PSR-4 (remplacer le classmap)

```json
// app/composer.json — AVRANT
"autoload": {
    "classmap": [
        "models/admin/",
        "models/BibliothequeManager.php",
        "models/ImpositionLeaflet.php",
        "controler/functions/SpoolManager.php",
        "controler/functions/"
    ]
}

// app/composer.json — APRÈS
"autoload": {
    "psr-4": {
        "App\\": "src/"
    },
    "classmap": [
        "controler/functions/"
    ]
}
```

**Note :** Le passage complet à PSR-4 nécessite de déplacer les fichiers dans un dossier `src/` avec des namespaces. C'est un gros chantier — à faire en phase finale.

### 4.5 Mettre en place PHPStan

```bash
cd app
composer require --dev shipmonk/dead-code-detector phpstan/phpstan
```

Créer `app/phpstan.neon` :
```neon
parameters:
    level: 5
    paths:
        - models/
        - controler/
        - api/
    excludePaths:
        - vendor/
        - brain/
        - maintenance/

    deadCodeDetectors:
        shipmonk:
            enabled: true
```

---

## Phase 5 — Rust quality (1 jour)

### 5.1 Remplacer les `unwrap()` par du vrai error handling (16 occurrences)

| Fichier | Occurrences | Risque |
|---------|-------------|--------|
| `src-tauri/src/server_manager.rs` | **8** | Mutex poison → panic crash |
| `src-tauri/src/app_commands.rs` | **4** | Mutex poison → panic crash |
| `src-tauri/build.rs` | **3** | Build script, risque faible |
| `src-tauri/src/printer_commands.rs` | **1** | Status check |

**Pattern de correction :**
```rust
// AVANT
let mut child = app.shell().sidecar("caddy").unwrap();
let lock = state.caddy_child.lock().unwrap();

// APRÈS
let mut child = app.shell().sidecar("caddy")
    .map_err(|e| format!("Failed to spawn caddy sidecar: {}", e))?;
let lock = state.caddy_child.lock()
    .map_err(|e| format!("Mutex poisoned (caddy_child): {}", e))?;
```

### 5.2 Ajouter cargo clippy au workflow CI

```bash
cargo clippy -- -D warnings
```

---

## Phase 6 — Bonus : Outillage et CI (1 jour)

### 6.1 Ajouter ESLint pour le JS

```bash
npm install --save-dev eslint @eslint/js
```

Créer `eslint.config.js` :
```javascript
import js from "@eslint/js";
export default [
    js.configs.recommended,
    {
        rules: {
            "no-var": "error",
            "prefer-const": "error",
            "no-unused-vars": "warn",
            "eqeqeq": "error"
        },
        ignores: ["**/node_modules/", "**/*.min.js", "**/vendor/"]
    }
];
```

### 6.2 Ajouter un Makefile ou des scripts npm

```json
// package.json scripts ajoutés
"scripts": {
    "lint:js": "eslint app/public/js/ scripts/ utils/ src/",
    "lint:php": "cd app && vendor/bin/phpstan analyse",
    "lint:rust": "cd src-tauri && cargo clippy",
    "dead-code": "cd app && vendor/bin/phpstan analyse --configuration=phpstan-dead-code.neon",
    "check": "npm run lint:js && npm run lint:php && npm run lint:rust"
}
```

---

## Planning récapitulatif

| Phase | Jours | Impact | Risque |
|-------|-------|--------|--------|
| **Phase 0** — Dead code | 1 | -20 fichiers, nettoyage immédiat | FAIBLE |
| **Phase 1** — Extract JS | 3-5 | Vues PHP lisibles, 11 485 lignes extraites | MOYEN |
| **Phase 2** — JS quality | 1-2 | Code JS moderne (const/let, no jQuery) | FAIBLE |
| **Phase 3** — PHP quality | 2-3 | Sécurité (extract, globals) + maintainabilité | MOYEN |
| **Phase 4** — Architecture | 2-3 | Structure professionnelle, autoloading | ÉLEVÉ |
| **Phase 5** — Rust quality | 1 | Robustesse, pas de panics | FAIBLE |
| **Phase 6** — Outillage | 1 | CI/CD lint, guardrails automatiques | FAIBLE |
| **Total** | **10-15 jours** | | |

---

## Matrice de risque par phase

| Phase | Ce qui peut casser | Mitigation |
|-------|-------------------|------------|
| Phase 0 | Référence à un fichier supprimé | `grep` de vérification post-suppression |
| Phase 1 | Variables PHP non transmises au JS | Tests manuels de chaque page après extraction |
| Phase 2 | `var` avait un scope différents de `let` | Vérifier les closures |
| Phase 3 | `extract()` créait des variables utilisées ailleurs | Analyser chaque cas individuellement |
| Phase 4 | Routes cassées après renommage de fichiers | Mettre à jour le routeur en même temps |
| Phase 5 | Error handling改变了le flow | Tests unitaires Rust |
| Phase 6 | Aucun risque | CI uniquement |

---

## Outils recommandés (installation)

```bash
# PHP
cd app
composer require --dev shipmonk/dead-code-detector phpstan/phpstan

# JS
npm install --save-dev eslint @eslint/js

# Rust (déjà installé)
cargo install cargo-udeps  # optionnel, pour vérifier les deps inutilisées

# Pas besoin de Knip, Deptrac, ou Dependency Cruiser pour ce projet
# Leur架构 ne correspond pas à l'architecture du projet
```

---

## Annexe — Inventaire complet des fichiers monolithes

### Fichiers > 1000 lignes

| Fichier | Lignes | Type |
|---------|--------|------|
| `app/view/studio.html.php` | 3 897 | View + inline JS |
| `app/view/tirage_multimachines.html.php` | 2 886 | View + inline JS |
| `app/api/studio_process.php` | 2 406 | API logic |
| `app/view/auto_tirage.html.php` | 2 110 | View + inline JS |
| `app/view/bibliotheque.html.php` | 1 721 | View + inline JS |
| `app/models/BibliothequeManager.php` | 1 701 | Model |
| `app/models/admin.php` | 1 565 | Model + inline JS |
| `app/public/index.php` | 1 427 | Router |
| `app/models/tirage_multimachines.php` | 1 346 | Model |
| `app/view/bibliotheque_old_branch.html.php` | 1 330 | **DEAD — supprimer** |

### Fichiers Rust > 500 lignes

| Fichier | Lignes |
|---------|--------|
| `src-tauri/src/printer_commands.rs` | 1 059 |
| `src-tauri/src/server_manager.rs` | 590 |
| `src-tauri/src/windows_native.rs` | 634 |

### Fichiers JS standalone > 500 lignes

| Fichier | Lignes |
|---------|--------|
| `app/public/js/studio-montage.js` | 843 |
| `app/public/js/riso-tools.js` | 810 |
| `app/public/js/studio-modification.js` | 680 |
| `app/public/js/studio-metadata.js` | 654 |
| `app/public/js/print-session-manager.js` | 612 |
