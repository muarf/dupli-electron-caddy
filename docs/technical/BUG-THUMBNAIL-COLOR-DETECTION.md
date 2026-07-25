# Rapport de Bug : Absence de Miniature, Encrage à 0 et Mauvaise Détection Couleur (N&B)

## 📌 Description du Problème
Lors de la détection de nouvelles impressions dans le buffer (`auto_tirage.html.php`), le système affichait les symptômes suivants :
1. Le mode couleur était systématiquement détecté en **N&B (Monochrome)** au lieu de **Couleur**.
2. La miniature (thumbnail/preview) n'était pas générée ni affichée.
3. Le taux d'encrage (`fill_rate`) restait bloqué à `0%`.

---

## 🔍 Analyse & Cause Racine

### 1. Forçage du mode Monochrome (`driver_color = false`)
- **Fichier** : `app/public/js/print-session-manager.js` (`scheduleReanalysis`)
- **Mécanisme** : La fonction appelait `window.electronAPI.reanalyzePrintJob(numericJobId)` avec 1 seul argument (le `jobId`), au lieu des 5 arguments attendus par la bridge (`jobId, documentName, format, splPath, driverColor`).
- **Conséquence** : `driverColor` valait `undefined` (`false`). Dans Rust (`printer_commands.rs`), la condition `is_grayscale = !is_color || !driver_color` évaluait toujours à `true`, forçant la couleur à `Monochrome` (N&B) en base SQLite.

### 2. Incohérence de clés d'indexation & Échec de la mise à jour SQLite
- **Fichier** : `app/public/js/print-session-manager.js` (`lastJobData`)
- **Mécanisme** : Le job ID était stocké sous forme de chaîne ou de nombre indifféremment. Lors de la réanalyse 3 secondes plus tard, `this.lastJobData.get(jobId)` échouait à retrouver le nom de l'imprimante (`printerName`).
- **Conséquence** : La requête `update_job_analysis` envoyée à `check-print-jobs.php` contenait un `printer_name` vide, ne correspondant à aucune ligne dans SQLite. Les colonnes `thumbnail_url` et `fill_rate` n'étaient donc jamais enregistrées.

---

## 🛠️ Résolution Apportée

1. **Stockage complet et tolérant aux types** (`print-session-manager.js`) :
   - Stockage des métadonnées du job (`PrinterName`, `Document`, `isGrayscale`, `colorMode`) dans `lastJobData` sous les deux formes (chaine `String(jobId)` et nombre `Number(jobId)`).

2. **Transmission de `driverColor` et du `documentName`** (`print-session-manager.js`) :
   - Mise à jour de l'appel `reanalyzePrintJob(numericJobId, documentName, '', '', driverColor)` pour transmettre la couleur réelle du pilote (défaut à `true` sauf si explicitement monochrome).

3. **Correction de la mise à jour BDD** (`check-print-jobs.php`) :
   - Grâce à la récupération garantie du `printerName`, la requête SQL `update_job_analysis` applique correctement le `thumbnail_url`, le `fill_rate` calculé et le `color_mode` en SQLite.
