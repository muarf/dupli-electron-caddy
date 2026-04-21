# Plan : Réanalyse Différée avec Timer JS

Ce plan résout le problème du mauvais nombre de pages (5 vs 30) en utilisant le mécanisme de réanalyse existant via un timer JS.

## Problème : Rappel

- **DB** : 5 pages (valeurs Windows brutes)
- **DevTools/C++** : 30 pages (valeurs analysées)
- **Le polling fonctionne** mais est trop lent ou trop complexe

## Solution : Timer de Réanalyse

### Concept

Au lieu de modifier le C++, utiliser l'API existante `reanalyzePrintJob` après un délai :

1. Job détecté → Envoyer vers DB (valeurs initiales)
2. Attendre X secondes (timer)
3. Appeler `reanalyzePrintJob(jobId)` → lit dans le cache C++
4. Mettre à jour DB avec valeurs analysées

---

## Implémentation

### Fichier : `app/public/js/print-session-manager.js`

Ajouter dans `handlePrintJobDetected` :

```js
async handlePrintJobDetected(jobData) {
    // ... code existant pour première notification ...
    
    // Envoyer première notification vers DB (valeurs Windows brutes)
    await this.sendNotification(jobData);

    // Planifier la réanalyse après délai
    const delayMs = 5000; // 5 secondes (ajustable)
    
    setTimeout(async () => {
        try {
            const jobId = jobData.JobId || jobData.jobId;
            
            // Appeler reanalyzePrintJob (lit le cache C++)
            if (window.electronAPI && window.electronAPI.reanalyzePrintJob) {
                const result = await window.electronAPI.reanalyzePrintJob(jobId);
                
                if (result && result.success) {
                    // Mettre à jour DB avec résultats analysés
                    await fetch('?check_print_jobs', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'update_job_analysis',
                            id: jobId, // Ou utiliser job_id Windows
                            thumbnail_url: result.thumbnailUrl,
                            fill_rate: result.fillRate,
                            is_grayscale: result.isGrayscale,
                            total_pages: result.totalPages
                        })
                    });
                    
                    console.log('[PrintSessionManager] Réanalyse terminée pour job', jobId, result);
                }
            }
        } catch (e) {
            console.warn('[PrintSessionManager] Erreur réanalyse:', e);
        }
    }, delayMs);
}
```

---

## Paramètres Ajustables

| Paramètre | Default | Description |
|----------|---------|-------------|
| `delayMs` | 5000 | Délai avant réanalyse (ms) |
| Timeout | 10000 | Timeout max pour job très longs |

---

## Tests à Faire

1. **Test délai** : Imprimer un document, vérifier que les pages passent de X à 30 après ~5s
2. **Test sans restart** : Pas besoin de redémarrer l'app
3. **Test job court** : Job qui termine avant 5s → fallback ?
4. **Test N&B** : Vérifier que color_mode est correct

---

## Avantages

| Point | Description |
|-------|-------------|
| ✅ Simple | Pas de modification C++ |
| ✅ Fiable | Utilise le mécanisme existant |
| ✅ Contrôlable | Délai paramétrable |
| ✅ Réversible | Facile à disable si problème |

---

## Inconvénients

| Point | Description |
|-------|-------------|
| ⚠️ Délai | 5s d'attente pour valeur finale |
| ⚠️ Race condition | Job disparaît avant réanalyse |

---

## Fichiers à Modifier

- `app/public/js/print-session-manager.js` : Ajouter timer + appel `reanalyzePrintJob`

---

## Commandes de Test

```bash
# Rebuild si nécessaire
npm run rebuild:print-engine
```
