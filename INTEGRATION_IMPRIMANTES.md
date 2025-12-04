# Intégration du Moniteur d'Imprimantes Windows

## Vue d'ensemble

Cette fonctionnalité permet de surveiller automatiquement le pool d'imprimantes Windows et de notifier l'application lorsqu'une impression est lancée. Les informations sur chaque job d'impression sont automatiquement enregistrées dans la base de données.

## Architecture

### Composants

1. **Module Node.js** (`utils/printer-monitor.js`)
   - Surveille le spooler d'impression Windows via WMI
   - Utilise PowerShell pour détecter les événements d'impression en temps réel
   - Envoie les notifications à l'API PHP

2. **API PHP** (`app/api/print-notification.php`)
   - Reçoit les notifications d'impression
   - Enregistre les informations dans la table `print_jobs` de la base de données

3. **Intégration Electron** (`main-caddy.js`)
   - Démarre automatiquement le moniteur au lancement de l'application (Windows uniquement)
   - Gère les handlers IPC pour la communication avec le frontend
   - Arrête proprement le moniteur à la fermeture

4. **Interface Frontend** (`preload.js`)
   - Expose les fonctions JavaScript pour interagir avec le moniteur
   - Permet d'écouter les événements d'impression

## Fonctionnalités

### Détection automatique
- Détecte les nouveaux jobs d'impression
- Surveille les jobs en cours (Printing, Spooling)
- Détecte les jobs terminés (Completed)

### Informations capturées
- **Job ID** : Identifiant unique du job
- **Document** : Nom du document imprimé
- **Owner** : Utilisateur qui a lancé l'impression
- **Printer Name** : Nom de l'imprimante
- **Status** : Statut du job (Printing, Spooling, Completed)
- **Pages** : Nombre de pages imprimées / total
- **Size** : Taille du job en octets
- **Time Submitted** : Date/heure de soumission
- **Timestamp** : Date/heure de l'événement

### Base de données

La table `print_jobs` est créée automatiquement avec la structure suivante :

```sql
CREATE TABLE print_jobs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    job_id TEXT NOT NULL,
    document TEXT NOT NULL,
    owner TEXT,
    printer_name TEXT NOT NULL,
    status TEXT NOT NULL,
    pages_printed INTEGER DEFAULT 0,
    total_pages INTEGER DEFAULT 0,
    size INTEGER DEFAULT 0,
    time_submitted TEXT,
    event_type TEXT,
    timestamp TEXT NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(job_id, printer_name, timestamp)
)
```

## Utilisation

### Depuis le frontend (JavaScript)

```javascript
// Écouter les impressions détectées
window.electronAPI.onPrintJobDetected((printData) => {
    console.log('Impression détectée:', printData);
    // printData contient toutes les informations du job
});

// Obtenir la liste des imprimantes
const result = await window.electronAPI.getPrinters();
if (result.success) {
    console.log('Imprimantes:', result.printers);
}

// Obtenir le statut du moniteur
const status = await window.electronAPI.getPrinterMonitorStatus();
console.log('Statut:', status.status); // 'active' ou 'inactive'

// Démarrer/Arrêter le moniteur manuellement
await window.electronAPI.togglePrinterMonitor(true);  // Démarrer
await window.electronAPI.togglePrinterMonitor(false); // Arrêter
```

### Depuis PHP

Les données sont automatiquement enregistrées dans la base de données. Vous pouvez les récupérer avec :

```php
require_once(__DIR__ . '/../controler/functions/database.php');
$db = create_database_manager();

// Récupérer les dernières impressions
$jobs = $db->select("
    SELECT * FROM print_jobs 
    ORDER BY timestamp DESC 
    LIMIT 10
");

// Récupérer les impressions d'une imprimante spécifique
$jobs = $db->select("
    SELECT * FROM print_jobs 
    WHERE printer_name = ? 
    ORDER BY timestamp DESC
", ['Nom de l\'imprimante']);
```

## Limitations

- **Windows uniquement** : Cette fonctionnalité n'est disponible que sur Windows
- **Machine locale** : Surveille uniquement les impressions sur la machine locale
- **Permissions** : Nécessite des droits d'exécution PowerShell (normalement disponibles)

## Dépannage

### Le moniteur ne démarre pas
- Vérifiez que vous êtes sur Windows
- Vérifiez les logs de la console Electron
- Vérifiez que PowerShell est disponible

### Les impressions ne sont pas détectées
- Vérifiez que le moniteur est actif : `getPrinterMonitorStatus()`
- Vérifiez les logs de la console Electron
- Vérifiez que l'API PHP répond : `http://127.0.0.1:8001/api/print-notification.php`

### Erreurs PowerShell
- Vérifiez que l'exécution de scripts PowerShell n'est pas bloquée
- Le script utilise `-ExecutionPolicy Bypass` pour contourner les restrictions

## Notes techniques

- Le moniteur utilise WMI (Windows Management Instrumentation) pour surveiller les événements
- Les événements sont détectés en temps réel via `Register-WmiEvent`
- Un polling périodique (toutes les 5 secondes) complète la surveillance événementielle
- Les doublons sont évités grâce à un cache des jobs récents

