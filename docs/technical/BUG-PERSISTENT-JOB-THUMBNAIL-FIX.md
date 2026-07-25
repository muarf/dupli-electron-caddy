# Rapport de Bug : Suppression en BDD et Chargement des Miniatures sur Caddy (Port 8000)

## 📌 Description du Problème
1. **Échec de suppression en BDD SQLite** : Les impressions supprimées depuis l'interface `auto_tirage` ne disparaissaient pas de la base de données SQLite et réapparaissaient au rafraîchissement.
2. **Absence de miniature et d'encrage à 0%** : Les nouvelles impressions ne généraient pas leur miniature PNG et n'affichaient aucun taux d'encrage.

---

## 🔍 Diagnostic & Origine des Bugs

### 1. Rejet de la suppression en BDD (`check-print-jobs.php`)
- **Cause** : L'API PHP `check-print-jobs.php` contenait une vérification de sécurité stricte exigeant `$_SESSION['admin'] === true` ou `$_SESSION['user'] === "1"`.
- **Impact** : Lors des requêtes locales `delete_jobs` envoyées depuis l'interface de tirage, PHP levait une exception `403/500`, annulant l'instruction SQL `DELETE FROM print_jobs`.

### 2. Échec du chargement des miniatures dans Tauri (`tauri-bridge.js`)
- **Cause** : Pour analyser le taux d'encrage et la couleur via un élément Canvas, `tauri-bridge.js` chargeait la miniature avec l'URL relative `/thumbnails/{job_id}/page_0.png`.
- **Impact** : Dans la WebView Tauri (`origin: http://tauri.localhost`), l'URL relative résolvait vers `http://tauri.localhost/thumbnails/...`, ce qui retournait une **Erreur 404** (les fichiers statiques étant hébergés par le serveur Web Caddy sur `http://127.0.0.1:8000`).
- L'événement `img.onerror` de l'image se déclenchait systématiquement, réinitialisant le `fillRate` à `0%` et `isColor` à `false`.

---

## 🛠️ Résolution Apportée

1. **Permission de suppression locale (`check-print-jobs.php`)** :
   - Mise à jour de la vérification dans `check-print-jobs.php` pour autoriser la suppression si l'utilisateur est connecté en session OU si la requête provient du serveur local (`REMOTE_ADDR` = `127.0.0.1` / `::1` / `localhost`).

2. **Résolution d'URL absolue vers Caddy (`tauri-bridge.js`)** :
   - Préfixage dynamique des requêtes d'images par `http://127.0.0.1:8000` si l'origine courante n'est pas déjà sur le port 8000.
   - L'analyse Canvas charge ainsi l'image réelle depuis le serveur Web Caddy, permettant le calcul exact du taux d'encrage et la détection de la couleur.
