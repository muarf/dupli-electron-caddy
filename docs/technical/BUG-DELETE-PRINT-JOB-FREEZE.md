# Rapport de Bug : Freeze de l'Interface et Perte des Miniatures/Encrage

## 📌 Description du Problème
1. **Freeze de l'application** : Lorsqu'un utilisateur supprime un travail d'impression depuis l'interface (`auto_tirage.html.php`), l'application se met en grisée (modale de confirmation) et cesse de répondre indéfiniment (plus d'une minute).
2. **Miniatures et encrages manquants** : Les nouvelles impressions en attente s'affichent sans aperçu visuel (miniature/thumbnail) et sans taux d'encrage calculé.

---

## 🔍 Découvertes & Analyse Approfondie des Logs

### 1. Élément Déclencheur Principal : Imprimante Réseau Hors Ligne
- **Constat dans les logs** (`windows_native`) :
  - L'imprimante réseau **`duplicopieur`** (IP `192.168.1.20`) possède le statut `0x00000080` (**Hors ligne / Inaccessible**).
- **Fonctionnement de Rust** (`src-tauri/src/printer_commands.rs`) :
  - Les fonctions `delete_print_job` et `reanalyze_print_job` exécutent un balayage de **toutes** les imprimantes installées sous Windows via `enum_printers()` puis `get_print_jobs(&printer.name)` lorsque le nom de l'imprimante n'est pas spécifié (`null`).
- **Conséquence Win32** :
  - L'appel aux API système Windows (`OpenPrinterW` / `EnumJobsW`) sur l'imprimante hors ligne `duplicopieur` tente d'ouvrir une connexion TCP/RPC sur le réseau.
  - L'API Win32 **bloque le thread principal Rust pendant le timeout réseau de Windows (30 à 60 secondes)** par imprimante hors ligne.

### 2. Impact sur l'UI (Freeze & Overlay Grisé)
- `auto_tirage.html.php` exécute la chaîne `await window.electronAPI.deletePrintJob(null, spoolJobId)`.
- Pendant les 30 à 60 secondes de blocage Win32, la promesse JS est suspendue.
- La modale `showAppModal` ne possède pas de bloc `finally` ou de timeout de secours : si la promesse échoue ou tarde, le calque d'opacité reste affiché indéfiniment sur l'écran.
- La suppression BDD SQLite n'est jamais atteinte car le code JS s'interrompt avant d'appeler l'API PHP.

### 3. Impact sur les Miniatures et l'Encrage
- Lorsqu'une nouvelle impression arrive dans le buffer, `reanalyze_print_job` est appelée pour :
  1. Générer la vignette PNG du document.
  2. Calculer le taux de couverture d'encre (encrage).
- Comme `reanalyze_print_job` effectue le même scan d'imprimantes et bute sur l'imprimante hors ligne `duplicopieur`, elle dépasse le délai d'expiration ou échoue.
- **Résultat** : Les tirages s'enregistrent en base sans miniature (`thumbnail_url: ""`) et sans calcul d'encrage (`fill_rate: 0`).

### 4. Pourquoi la purge manuelle du Spooler ne suffit pas
Purger les fichiers `.SPL` dans `C:\Windows\System32\spool\PRINTERS` ne règle pas le problème car le blocage provient de la tentative d'établissement d'une socket/RPC vers l'IP `192.168.1.20` de l'imprimante hors ligne.

---

## 🛠️ Solutions Recommandées pour le Code

1. **Passer le nom de l'imprimante cible** :
   - Transmettre `job.printer_name` directement dans les appels IPC (`deletePrintJob` et `reanalyzePrintJob`) pour cibler l'imprimante concernée sans scanner tout le parc Windows.

2. **Filtrage / Timeout sur les imprimantes hors ligne** dans Rust :
   - Ignorer les imprimantes dont le statut contient `PRINTER_STATUS_NOT_AVAILABLE` / `PRINTER_STATUS_OFFLINE` (`0x00000080`) lors des énumérations globales.

3. **Interface Optimiste & Asynchronisme** :
   - Fermer la modale JS immédiatement et retirer la ligne sans bloquer l'UI avec des `await` en cascade.
   - Séparer la suppression BDD de l'annulation système Windows.
