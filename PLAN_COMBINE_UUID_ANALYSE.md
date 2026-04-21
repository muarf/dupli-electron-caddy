# Plan Combiné : UUID + Notification Automatique Après Analyse

Ce plan résout deux problèmes distincts :
1. **Recyclage des IDs Windows** : Collision de jobs (Job ID 3 réutilisé)
2. **Timing (5 vs 30 pages)** : L'analyse n'est pas terminée lors de la première notification

---

## Problème 1 : Recyclage IDs Windows

### Cause
Windows réutilise rapidement les IDs (ex: Job 3). Si un Job 3 existe déjà en base, le backend met à jour l'ancienne ligne au lieu d'en créer une nouvelle.

### Solution : Identifiant UUID Composite

#### 1.1 Génération UUID côté JS
*Fichier : `app/public/js/print-session-manager.js`*

Modifier `handlePrintJobDetected` pour :
- Utiliser `jobData.timeSubmitted` (minuscule) au lieu de `TimeSubmitted`
- Générer un UUID unique : `MD5(PrinterName + JobId + timeSubmitted)`

```js
// Générer UUID composite
function generateJobUUID(printerName, jobId, timeSubmitted) {
    const data = `${printerName}|${jobId}|${timeSubmitted}`;
    // Utiliser SubtleCrypto si disponible, sinon fallback simple
    if (window.crypto && window.crypto.subtle) {
        return crypto.subtle.digest('SHA-256', new TextEncoder().encode(data))
            .then(buf => Array.from(new Uint8Array(buf)).map(b => b.toString(16).padStart(2, '0')).join(''));
    }
    // Fallback: hash simple
    let hash = 0;
    for (let i = 0; i < data.length; i++) {
        hash = ((hash << 5) - hash) + data.charCodeAt(i);
        hash |= 0;
    }
    return Math.abs(hash).toString(16);
}
```

Envoyer `job_uuid` dans la notification vers PHP.

---

#### 1.2 Mise à jour PHP
*Fichier : `app/api/print-notification.php`*

1. Ajouter colonne `job_uuid` (TEXT) à la table `print_jobs`
2. Utiliser UUID comme clé d'identification unique
3. INSERT si nouveau UUID, UPDATE si existant

```php
// Ajouter colonne si pas exists
$db->execute("ALTER TABLE print_jobs ADD COLUMN job_uuid TEXT");

// Recherche par UUID
$existingJob = $db->selectOne(
    "SELECT id FROM print_jobs WHERE job_uuid = ?",
    [$data['job_uuid']]
);

if ($existingJob) {
    // UPDATE - enrichir les métadonnées
    $db->execute("UPDATE print_jobs SET ... WHERE id = ?", [...]);
} else {
    // INSERT nouveau job
    $db->execute("INSERT INTO print_jobs (job_uuid, ...) VALUES (?, ...)", [...]);
}
```

---

## Problème 2 : Timing (5 vs 30 pages)

### Cause
La première notification arrive avec les valeurs Windows brutes (5 pages). L'analyse C++ (30 pages) arrive après mais n'est pas transmise à la DB.

### Solution : Double Notification via HTTP POST depuis C++

#### 2.1 C++ HTTP POST après analyse
*Fichier : `src/print-engine/windows/win32-printer.cc`*

Après `AnalyzeSpoolFile()` dans `GetJobInfo()`, ajouter un HTTP POST vers `print-notification` avec les valeurs analysées :

```cpp
// Après AnalyzeSpoolFile dans GetJobInfo()
void NotifyAnalysisComplete(DWORD jobId, const std::string& documentName,
                           bool isGrayscale, float fillRate,
                           const std::string& thumbnailUrl, DWORD totalPages) {
    // Construire JSON avec job_uuid et données analysées
    std::string json = "{";
    json += "\"job_uuid\":\"" + generateJobUUID(printerName, jobId, timeSubmitted) + "\",";
    json += "\"jobId\":" + std::to_string(jobId) + ",";
    json += "\"printerName\":\"" + printerName + "\",";
    json += "\"document\":\"" + documentName + "\",";
    json += "\"totalPages\":" + std::to_string(totalPages) + ",";
    json += "\"fillRate\":" + std::to_string(fillRate) + ",";
    json += "\"colorMode\":\"" + std::string(isGrayscale ? "Monochrome" : "Color") + "\",";
    json += "\"thumbnailUrl\":\"" + thumbnailUrl + "\",";
    json += "\"eventType\":\"analysis_complete\"";
    json += "}";

    // HTTP POST vers http://127.0.0.1:8001/?print_notification
    HINTERNET hInternet = InternetOpenA("PrintEngine/1.0", ...);
    HINTERNET hConnect = InternetOpenUrlA(hInternet, url.c_str(), ...);
    // Envoyer JSON dans le body
    HttpSendRequestA(hConnect, "Content-Type: application/json", ...);
}
```

**Important** : Utiliser le **même UUID** que la première notification pour que le PHP fasse un UPDATE.

---

#### 2.2 PHP Update basé sur UUID
*Fichier : `app/api/print-notification.php`*

Le PHP reçoit la notification d'analyse et fait un UPDATE :

```php
if ($data['eventType'] === 'analysis_complete') {
    // UPDATE uniquement - ne pas créer de nouvelle ligne
    $db->execute("
        UPDATE print_jobs SET
            total_pages = ?,
            fill_rate = ?,
            color_mode = ?,
            thumbnail_url = ?
        WHERE job_uuid = ?
    ", [
        $data['totalPages'],
        $data['fillRate'],
        $data['colorMode'],
        $data['thumbnailUrl'],
        $data['job_uuid']
    ]);
}
```

---

## Résumé des Fichiers à Modifier

| Fichier | Modification |
|---------|-------------|
| `app/public/js/print-session-manager.js` | Générer UUID, utiliser `timeSubmitted`, envoyer dans notif |
| `app/api/print-notification.php` | Colonne UUID, INSERT/UPDATE par UUID, handle `analysis_complete` |
| `src/print-engine/windows/win32-printer.cc` | HTTP POST après `AnalyzeSpoolFile` avec valeurs analysées |

---

## Plan de Vérification

### Test 1 : UUID unique
- [ ] Imprimer un document
- [ ] Vérifier que `job_uuid` est présent en DB
- [ ] Imprimer un second document avec même printer
- [ ] Vérifier que les 2 jobs ont des UUID différents

### Test 2 : 30 pages sans restart
- [ ] Imprimer un document de 30 pages
- [ ] Vérifier que l'UI affiche d'abord 5 pages (ou valeur Windows)
- [ ] Attendre 5-10 secondes
- [ ] Vérifier que l'UI affiche maintenant 30 pages (sans restart)
- [ ] Vérifier que fill_rate et color_mode sont corrects

### Test 3 : Vérification logs
- [ ] Voir les logs C++ pour confirmation de l'HTTP POST
- [ ] Voir les logs PHP pour confirmation de l'UPDATE

---

## Commandes de Rebuild

```bash
# Rebuild du module C++
npm run rebuild:print-engine

# Kill Electron pour débloquer le fichier .node
taskkill /F /IM electron.exe
```

---

## Risques et Mitigations

| Risque | Mitigation |
|--------|------------|
| C++ crash sur HTTP POST | Wrap dans try/catch, ne pas bloquer l'analyse |
| Double notification race condition | UUID comme clé, UPDATE idempotent |
| Performance HTTP | Timeout court (5s), async |
