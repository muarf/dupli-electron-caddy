# Rapport de Bug : Fix de la Réanalyse Automatique des Miniatures et Détection Couleur

## 📌 Description du Problème
Lors de la détection de nouvelles impressions dans le buffer (`auto_tirage.html.php`), les impressions perdaient leur miniature (thumbnail), affichaient un taux d'encrage à `0%` et basculaient en mode **Monochrome (N&B)**.

---

## 🔍 Analyse & Cause Racine

### 1. Surécriture par la tâche de fond (`regenerateMissingThumbnails`)
- **Fichier** : `app/public/js/print-session-manager.js` (`regenerateMissingThumbnails`)
- **Mécanisme** : Une boucle automatique s'exécutait toutes les 10 secondes pour traiter les travaux en base sans miniature.
- **Cause** : À la ligne 142, la fonction exécutait `reanalyzePrintJob(jobId)` avec un seul argument (omettant `documentName`, `printerName` et `driverColor`).
- **Conséquence** : 
  1. `driverColor` valant `undefined` (`false`), Rust évaluait par défaut `isGrayscale = true`.
  2. `printerName` étant vide dans l'appel `update_job_analysis`, SQLite ne pouvait pas faire correspondre la ligne du job.
  3. Toutes les 10 secondes, cette tâche de fond écrasait les données en BDD SQLite avec `color_mode = Monochrome` et `thumbnail_url = ""`.

---

## 🛠️ Résolution Apportée

1. **Passage complet des métadonnées dans la boucle de 10s** (`print-session-manager.js`) :
   - Récupération de `documentName` (`job.document_name`), `printerName` (`job.printer_name`) et déduction de `driverColor` (!isMono).
   - Appel corrigé : `window.electronAPI.reanalyzePrintJob(jobId, documentName, '', '', driverColor)`.

2. **Transmission de l'ID et du nom d'imprimante dans l'update SQLite** :
   - Envoi de `id`, `job_id` et `printer_name` à `update_job_analysis` pour que la mise à jour BDD cible la bonne ligne et enregistre la miniature (`thumbnail_url`) ainsi que l'encrage réel.
