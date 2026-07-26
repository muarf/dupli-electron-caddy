# 🛡️ Audit de Sécurité et Plan de Refactoring Restant

**Projet :** Duplicator (Electron & Server PHP)  
**Branche :** `refactor`  
**Dernière mise à jour :** Juillet 2026

---

## 📊 Syntaxe & État Réalisé

| Phase | Description | Statut |
| :--- | :--- | :---: |
| **Phase 0** | Tests d'intégration automatisés (Caddy + PHP) | ✅ terminé |
| **Phase 1** | Extraction complète du JS inline (13 vues HTML.PHP ➔ `app/public/js/`) | ✅ terminé |
| **Phase 2** | Nettoyage JS Global (Chargement Quill.js, `defer`, objet global `CONFIG`) | ✅ terminé |
| **Phase 3.1** | Élimination des `extract()` et unification des tampons `template()` | ✅ terminé |
| **Phase 3.2** | Réduction des `global $db` et refactoring utilitaires | ✅ terminé |
| **Phase 3.6** | Aplatissement de la logique d'extraction de contexte RAG | ✅ terminé |
| **Phase 5** | Remplacement des `.unwrap()` risqués sur Mutex en Rust (Tauri) | ✅ terminé |

---

## 🛡️ 1. Ce qu'il reste à faire — SÉCURITÉ

### A. Protection CSRF (Cross-Site Request Forgery)
- **Constat :** Les formulaires AJAX et POST d'administration exécutent les actions directement sans vérification de jeton d'anti-forgerie.
- **Action recommandée :**
  1. Générer `$_SESSION['csrf_token']` lors de la connexion.
  2. Injecter une balise `<meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?>">` dans le layout principal.
  3. Ajouter la vérification `hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')` dans l'API admin.

### B. Validation des Chemins de Fichiers (Path Traversal)
- **Constat :** Certains scripts de l'API (`get_pdf_path.php`, `delete_bibliotheque_file.php`) reçoivent des chemins de fichiers.
- **Action recommandée :**
  - S'assurer que `realpath($requested_path)` commence impérativement par le chemin racine autorisé `realpath(getBibliothequeDir())` ou `realpath(resolveTempDir())`.

### C. Vérification d'Authentification Systématique
- **Constat :** Quelques endpoints dans `app/api/` comptent sur le routage parent pour restreindre l'accès.
- **Action recommandée :**
  - Ajouter un contrôle explicite `if (!is_logged_in()) { http_response_code(401); exit; }` en tête de chaque endpoint API sensible.

---

## 🛠️ 2. Ce qu'il reste à faire — REFACTORING

### A. Modularisation du monolithe `studio_process.php` (105 KB)
- **Constat :** `app/api/studio_process.php` réunit la logique d'imposition PDF, le calcul de grilles, la découpe de pages et la génération de vignettes.
- **Action recommandée :**
  - Extraire la logique métier dans des classes du dossier `app/models/` :
    - `StudioImpositionService.php`
    - `StudioPreviewService.php`

### B. Phase 4 — Harmonisation Architecture & Autoloading
- **Actions recommandées :**
  - Déplacer/Alias le répertoire `app/controler/` vers la convention standard `app/controller/`.
  - Harmoniser le nommage des fichiers avec underscoring propre sans points multiples (ex: `admin.aide.html.php` ➔ `admin_aide.html.php`).
  - Mettre en place un Autoloader PSR-4 unifié dans Composer pour charger automatiquement les classes de `app/models/` et `app/services/`.

### C. Déduplication et modernisation Tauri / Rust
- **Actions recommandées :**
  - Migrer `tauri_plugin_shell::Shell::open` vers `tauri-plugin-opener` recommandé en Tauri v2.
  - Centraliser la gestion d'erreurs Tauri dans un type `AppResult<T>`.
