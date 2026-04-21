# Plan Définitif : Ouverture des Verrous (UUID + Notifications Multiples)

Ce plan résout les deux problèmes :
1. **Recyclage des IDs Windows** : Collision de jobs
2. **Nombre de pages incorrect** : 2 vs 30 (timing)

## Hypothèse Validée

Le C++ envoie le **même callback** plusieurs fois pour un même job, avec des valeurs qui évoluent (2 pages → 30 pages). Le problème est que les verrous JS et PHP bloquent les mises à jour.

---

## Architecture Globale

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   C++ (Moteur)  │───▶│  JS (Frontend)   │───▶│  PHP (Backend)  │
│                 │    │                  │    │                 │
│ - Détection job │    │ - Anti-spam      │    │ - Doublons PHP  │
│ - Analyse SPL   │    │ - UUID generation│    │ - INSERT/UPDATE│
│ - Cache results │    │ - Notif send     │    │ - DB storage   │
└─────────────────┘    └──────────────────┘    └─────────────────┘
        │                       │                       │
        │   Callback #1         │   Notification #1      │
        │   (2 pages)           │   (INSERT)             │
        │───────────────────────│───────────────────────│
        │                       │                       │
        │   Callback #2         │   Notification #2     │
        │   (30 pages)          │   (UPDATE) ◄──────────┘
        │───────────────────────│  ← BLOQUÉ si verrous
        │                       │     pas ouverts
```

---

## Problème 1 : UUID pour IDs Windows

### Cause
Windows recycle les Job IDs rapidement (Job 3 aujourd'hui = Job 3 demain). Si on utilise seulement `job_id`, on peut écraser/mélanger des jobs différents.

### Solution : UUID Composite

#### 1.1 Génération UUID en C++
*Fichier : `src/print-engine/windows/win32-printer.cc`*

Ajouter une fonction pour générer un UUID basé sur les données immuables du job :

```cpp
#include <functional>  // Pour std::hash

std::string GenerateJobUUID(const std::string& printerName, DWORD jobId,
                           const std::string& timeSubmitted) {
    // Données immuables : PrinterName + JobId + TimeSubmitted
    std::string data = printerName + "|" + std::to_string(jobId) + "|" + timeSubmitted;
    
    // Hash simple (SHA-256 overkill pour ce cas)
    std::hash<std::string> hasher;
    size_t hash = hasher(data);
    
    // Format hexadécimal
    std::stringstream ss;
    ss << std::hex << hash;
    return ss.str();
}
```

**L'appeler dans `GetJobInfo()`** et ajouter au callback :

```cpp
// Dans MonitorWorker::OnProgress, ajouter :
obj.Set("jobUuid", StringToNapiString(env_, jobUuid));
```

#### 1.2 Passage UUID au JS
*Fichier : `src/print-engine/windows/win32-printer.cc`*

S'assurer que le callback inclut `jobUuid` :

```cpp
obj.Set("jobUuid", StringToNapiString(env_, jobUuid));  // NOUVEAU
obj.Set("jobId", Napi::Number::New(env_, data[i].jobId));
obj.Set("printerName", StringToNapiString(env_, data[i].printerName));
// ... autres champs
```

---

## Problème 2 : Ouverture du Verrou JS

### Cause
L'anti-spam dans `handlePrintJobDetected` bloque toutes les notifications après la première pendant 60 secondes.

### Solution : Autoriser si données meilleures

#### 2.1 Modifier print-session-manager.js
*Fichier : `app/public/js/print-session-manager.js`*

Remplacer la logique d'anti-spam actuelle :

```js
// === AVANT (verrouillé) ===
// Anti-spam: Vérifier si déjà notifié récemment
const jobId = jobData.jobId || jobData.id || jobData.JobId;
if (jobId && this.processedJobIds.has(jobId)) {
    return; // BLOQUE tout
}

// === APRÈS (verrou ouvert) ===
// Autoriser si les données ont changé
const jobId = jobData.JobId || jobData.jobId;
const jobUuid = jobData.jobUuid || jobData.JobUuid;  // NOUVEAU

// Stocker les données précédente pour comparaison
const storedKey = jobUuid || `${jobData.PrinterName}_${jobId}`;
const prevData = this.lastJobData.get(storedKey);

// Fonction pour vérifier si données "meilleures"
const hasBetterData = (prevData, newData) => {
    if (!prevData) return false;
    
    const newPages = newData.TotalPages || newData.totalPages || 0;
    const oldPages = prevData.TotalPages || prevData.totalPages || 0;
    const newFill = newData.FillRate || newData.fillRate || 0;
    const oldFill = prevData.FillRate || prevData.fillRate || 0;
    const newColor = newData.ColorMode || newData.colorMode || '';
    const oldColor = prevData.ColorMode || prevData.colorMode || '';
    const newDuplex = newData.IsDuplex || newData.duplex;
    const oldDuplex = prevData.IsDuplex || prevData.duplex;
    const newThumb = newData.ThumbnailUrl || newData.thumbnailUrl || '';
    const oldThumb = prevData.ThumbnailUrl || prevData.thumbnailUrl || '';
    
    // Retourne true si AU MOINS UN paramètre a changé
    return (newPages > oldPages) ||
           (newFill > 0 && oldFill === 0) ||
           (newColor && newColor !== oldColor) ||
           (newDuplex !== oldDuplex) ||
           (newThumb && !oldThumb);
};

// Si job déjà vu mais données meilleures → autoriser
if (jobId && this.processedJobIds.has(storedKey) && !hasBetterData(prevData, jobData)) {
    console.log('[PrintSessionManager] Job déjà notifié sans nouvelles données, ignoré:', jobId);
    return;
}

// Stocker les données courantes
this.processedJobIds.add(storedKey);
this.lastJobData.set(storedKey, {...jobData});

// Nettoyer après 60 secondes
setTimeout(() => {
    this.processedJobIds.delete(storedKey);
    this.lastJobData.delete(storedKey);
}, 60000);
```

#### 2.2 Inclure jobUuid dans la notification
*Fichier : `app/public/js/print-session-manager.js`*

Dans `handlePrintJobDetected`, ajouter `jobUuid` au body :

```js
body: JSON.stringify({
    jobId: jobData.JobId || jobData.jobId,
    jobUuid: jobData.jobUuid || jobData.JobUuid || null,  // NOUVEAU
    document: jobData.Document || jobData.documentName,
    // ... autres champs existants
})
```

---

## Problème 3 : Ouverture du Verrou PHP

### Cause
Le PHP rejette les UPDATE si `fillRate` ou `thumbnailUrl` n'a pas changé, ignorant `total_pages`.

### Solution : Accepter si UN paramètre change

#### 3.1 Modifier print-notification.php
*Fichier : `app/api/print-notification.php`*

**3.1.1 Ajouter colonne job_uuid**

```php
// Au début du script, après la création de la table
try {
    $db->execute("ALTER TABLE print_jobs ADD COLUMN job_uuid TEXT");
} catch(Exception $e) {}
```

**3.1.2 Recherche par job_uuid AU LIEU de job_id**

```php
// Modifier la requête de vérification
// AVANT: par job_id + printer_name + timestamp
// APRÈS: par job_uuid (plus robuste)

// Si job_uuid présent, chercher par UUID
if (!empty($data['job_uuid'])) {
    $existingJob = $db->selectOne(
        "SELECT id, total_pages, fill_rate, color_mode, duplex, thumbnail_url 
         FROM print_jobs WHERE job_uuid = ?",
        [$data['job_uuid']]
    );
} else {
    // Fallback: recherche classique (job_id + printer_name + timestamp)
    $existingJob = $db->selectOne(
        "SELECT id, total_pages, fill_rate, color_mode, duplex, thumbnail_url 
         FROM print_jobs WHERE job_id = ? AND printer_name = ?",
        [strval($data['jobId']), $data['printerName']]
    );
}
```

**3.1.3 Logique INSERT vs UPDATE**

```php
// Déterminer si INSERT ou UPDATE
$isUpdate = ($existingJob && !empty($existingJob['id']));

// Pour UPDATE: vérifier si AU MOINS UN paramètre a changé
$shouldUpdate = false;
if ($isUpdate) {
    $newTotalPages = $data['totalPages'] ?? 0;
    $newFillRate = $data['fillRate'] ?? 0;
    $newColorMode = $data['colorMode'] ?? 'unknown';
    $newDuplex = isset($data['duplex']) ? ($data['duplex'] ? 1 : 0) : 0;
    $newThumbnail = $data['thumbnailUrl'] ?? '';
    
    $oldTotalPages = $existingJob['total_pages'] ?? 0;
    $oldFillRate = $existingJob['fill_rate'] ?? 0;
    $oldColorMode = $existingJob['color_mode'] ?? 'unknown';
    $oldDuplex = $existingJob['duplex'] ?? 0;
    $oldThumbnail = $existingJob['thumbnail_url'] ?? '';
    
    // Autoriser UPDATE si UN SEUL paramètre a changé
    $shouldUpdate = ($newTotalPages > $oldTotalPages) ||
                    ($newFillRate > 0 && $oldFillRate == 0) ||
                    ($newColorMode !== 'unknown' && $newColorMode !== $oldColorMode) ||
                    ($newDuplex !== $oldDuplex) ||
                    (!empty($newThumbnail) && empty($oldThumbnail));
}

if ($isUpdate && !$shouldUpdate) {
    echo json_encode(['success' => true, 'message' => 'Job déjà existant sans nouvelles données']);
    exit;
}
```

**3.1.4 Exécution INSERT ou UPDATE**

```php
if ($isUpdate && $shouldUpdate) {
    // UPDATE - enrichir les métadonnées
    $db->execute("
        UPDATE print_jobs SET
            document = ?,
            document_full_path = ?,
            document_display_name = ?,
            status = ?,
            pages_printed = ?,
            total_pages = COALESCE(NULLIF(?, 0), total_pages),
            fill_rate = COALESCE(NULLIF(?, 0), fill_rate),
            color_mode = COALESCE(NULLIF(?, 'unknown'), color_mode),
            duplex = ?,
            thumbnail_url = COALESCE(NULLIF(?, ''), thumbnail_url),
            paper_size = ?,
            copies = ?
        WHERE id = ?
    ", [
        $documentDisplay,
        $documentFull,
        $documentDisplay,
        $data['status'],
        $data['pagesPrinted'] ?? 0,
        $data['totalPages'] ?? 0,
        $data['fillRate'] ?? 0,
        $data['colorMode'] ?? 'unknown',
        isset($data['duplex']) ? ($data['duplex'] ? 1 : 0) : 0,
        $data['thumbnailUrl'] ?? '',
        $data['paperSize'] ?? '',
        $data['copies'] ?? 1,
        $existingJob['id']
    ]);
} else {
    // INSERT - nouveau job
    $db->execute("
        INSERT INTO print_jobs 
        (job_uuid, job_id, document, document_full_path, document_display_name, 
         owner, printer_name, status, pages_printed, total_pages, size, 
         time_submitted, event_type, timestamp, fill_rate, color_mode, duplex, 
         thumbnail_url, paper_size, copies)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ", [
        $data['job_uuid'] ?? null,
        $data['jobId'],
        $documentDisplay,
        $documentFull,
        $documentDisplay,
        $data['owner'] ?? null,
        $data['printerName'],
        $data['status'],
        $data['pagesPrinted'] ?? 0,
        $data['totalPages'] ?? 0,
        $data['size'] ?? 0,
        $data['timeSubmitted'] ?? null,
        $data['eventType'] ?? 'unknown',
        $data['timestamp'],
        $data['fillRate'] ?? 0,
        $data['colorMode'] ?? 'unknown',
        isset($data['duplex']) ? ($data['duplex'] ? 1 : 0) : 0,
        $data['thumbnailUrl'] ?? '',
        $data['paperSize'] ?? '',
        $data['copies'] ?? 1
    ]);
}
```

---

## Résumé des Fichiers à Modifier

| Fichier | Modification |
|---------|-------------|
| `src/print-engine/windows/win32-printer.cc` | Générer UUID, ajouter au callback |
| `app/public/js/print-session-manager.js` | Ouverture verrou JS, comparer données |
| `app/api/print-notification.php` | Recherche par UUID, UPDATE si paramètre changé |

---

## Plan de Tests

### Test 1 : UUID Unique
- [ ] Imprimer document A → vérifier `job_uuid` en DB
- [ ] Imprimer document B → vérifier UUID différent

### Test 2 : Mise à jour nombre de pages
- [ ] Imprimer document 30 pages
- [ ] Vérifier DB affiche 2 pages (valeur initiale)
- [ ] Attendre 5-10 secondes
- [ ] Vérifier DB affiche 30 pages (mise à jour auto)

### Test 3 : Sans restart
- [ ] Faire une impression
- [ ] NE PAS redémarrer l'app
- [ ] Vérifier que les valeurs se mettent à jour

### Test 4 : N&B vs Couleur
- [ ] Imprimer document N&B → vérifier color_mode = "Monochrome"
- [ ] Imprimer document Couleur → vérifier color_mode = "Color"

### Test 5 : Recto vs Verso
- [ ] Imprimer document Recto → vérifier duplex = 0
- [ ] Imprimer document Verso → vérifier duplex = 1

---

## Commandes de Rebuild

```bash
# Rebuild du module C++
npm run rebuild:print-engine

# Kill Electron pour débloquer le fichier .node
taskkill /F /IM electron.exe
```

---

## Avantages de ce Plan

| Avantage | Description |
|----------|-------------|
| ✅ Temps réel | Pas de délai artificiel (5s) |
| ✅ Robuste | UUID éviter collisions Windows |
| ✅ Simple | Pas de code réseau en C++ |
| ✅ Complet | Résout IDs + Pages + Color + Duplex |

---

## Risques et Mitigations

| Risque | Mitigation |
|--------|------------|
| UUID différent C++ vs JS | UUID généré uniquement en C++ |
| Race condition | Vérification "meilleures données" |
| Performances | Index sur job_uuid |
