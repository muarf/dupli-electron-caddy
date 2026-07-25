# Rapport de Bug : Freeze de l'Interface lors de la Suppression d'une Impression

## 📌 Description du Problème
Lorsqu'un utilisateur supprime un travail d'impression depuis l'interface (vue `auto_tirage.html.php`), l'application se met en grisée (modale/overlay de confirmation) et cesse de répondre indéfiniment (plus d'une minute).

---

## 🔍 Analyse & Diagnostic Technique

### 1. Blocage côté Frontend (UI Grisée)
- **Fichier** : `app/view/auto_tirage.html.php` (`deleteBufferJob`)
- **Mécanisme** : La suppression déclenche l'affichage d'une modale de confirmation (`showAppModal`) et applique une opacité (`opacity: 0.5`).
- **Cause** : La fonction async exécute plusieurs requêtes séquentiellement avec `await` :
  1. `await window.electronAPI.deletePrintJob(null, spoolJobId)`
  2. `await fetch('?check_print_jobs', { action: 'delete_jobs' })`
  3. `await fetch('?check_print_jobs', { action: 'delete_by_job_id' })`
  Si **l'une** de ces requêtes ne résout pas sa promesse (délai Win32 ou blocage backend), l'interface reste indéfiniment dans l'état grisé.

### 2. Scrutation lourde Win32 côté Rust
- **Fichier** : `src-tauri/src/printer_commands.rs` (`delete_print_job`)
- **Cause** : L'appel frontend passe `printerName = null`. Dans Rust, si `printer_name` est vide, la fonction tente d'identifier l'imprimante en énumérant **toutes les imprimantes du système** (`enum_printers()`) puis en interrogeant leurs travaux respectifs (`get_print_jobs()`).
- **Impact** : Si le spooler Windows (`spoolsv.exe`) tarde à répondre ou verrouille un job, l'appel Win32 bloque le thread IPC Rust, ce qui gèle l'appel `deletePrintJob` frontend.

### 3. Conflit de verrouillage de fichier & Shredding PHP
- **Fichiers** : `app/api/check-print-jobs.php` & `app/controler/functions/secure_delete.php`
- **Cause** : Lors de la suppression en base, PHP appelle `secure_delete($filepath)`, qui tente d'écraser le fichier PDF/SPL avec des zéros par blocs de 1Mo (`fwrite`), puis d'exécuter `unlink()`.
- **Impact** : Si le Spooler Windows ou l'application détient toujours un descripteur de fichier ouvert sur le document ou la vignette, Windows renvoie une erreur d'accès refusé (`PermissionDenied`) ou bloque la tentative d'ouverture en écriture.

---

## 🛠️ Pistes de Résolution Recommandées

### A. Frontend (`auto_tirage.html.php`)
1. **Fournir le nom de l'imprimante** : Transmettre `job.printer_name` à `deletePrintJob(printerName, spoolJobId)` au lieu de `null` pour éviter le scan Win32 global.
2. **UI Optimiste Non-Bloquante** : Fermer la modale immédiatement et effectuer la suppression en arrière-plan sans bloquer l'UI avec des `await` en cascade.

### B. Rust / Win32 (`printer_commands.rs`)
1. **Timeout & Asynchronisme** : Ne pas bloquer l'IPC indéfiniment sur la scrutation d'imprimantes.
2. **Gestion d'erreur gracieuse** : Si `printer_name` est absent et qu'aucune imprimante ne répond rapidement, retourner un statut sans bloquer.

### C. PHP (`secure_delete.php` & `check-print-jobs.php`)
1. **Gestion des verrous de fichier** : Tenter une suppression simple si l'ouverture en écriture échoue, ou différer le shredding si le fichier est verrouillé par `spoolsv.exe`.
