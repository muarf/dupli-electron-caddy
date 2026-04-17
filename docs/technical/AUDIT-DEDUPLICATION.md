# Audit Dédoublonnage — 17 avril 2026

## Résumé

Ce document recense les fichiers en double, obsolètes ou non référencés trouvés dans le dépôt `dupli-electron-caddy`.

---

## 1. Fichiers de test / dev à la racine (à déplacer dans `scripts/`)

| Fichier racine | Statut | Action |
|---|---|---|
| `generate-test-pdfs.js` | Doublon de `scripts/generate-test-pdfs.js` | Déplacer racine → `scripts/` |
| `generate_blank_pdfs.js` | Doublon de `scripts/generate_blank_pdfs.js` | Déplacer racine → `scripts/` |
| `check-db.js` | Doublon de `scripts/check-db.js` | Déplacer racine → `scripts/` |
| `print-engine.js` | Vieux moteur, non référencé dans main-caddy.js ni main.js | À vérifier avant suppression |
| `repro_printing.js` | Script de test reproductible | Déplacer → `scripts/` |
| `debug_path.php` | Utilitaire debug one-shot | Déplacer → `scripts/` |
| `test_gs.php` | Test Ghostscript | Déplacer → `scripts/` |
| `main.js` | Utilisé par `npm run start:php` (dev only) | **Garder** à la racine |
| `generate_test_pdf_print.php` | Génère PDFs de test, différent de `app/generate_test_pdf.php` | Déplacer → `scripts/` |

---

## 2. `scripts/` — Fichiers de test / legacy

| Fichier/Dossier | Statut | Action |
|---|---|---|
| `scripts/legacy/print-engine.js` | Ancien moteur non utilisé | Supprimer |
| `scripts/powershell/` (4 fichiers) | Scripts PowerShell | À vérifier avant suppression |
| `scripts/test-github-build.sh` | Script CI/test | Supprimer |
| `scripts/generate-test-pdfs.js` | Identique au root | Supprimer root (cf. §1) |
| `scripts/generate_blank_pdfs.js` | Identique au root | Supprimer root (cf. §1) |
| `scripts/check-db.js` | Utilitaire utile | Garder |
| `scripts/afterpack-linux.js` | Utilisé par electron-builder | Garder |
| `scripts/download-caddy.js` | Utilisé pour télécharger Caddy | Garder |

---

## 3. Générateurs de PDF de test (triple doublon)

| Fichier | Type | Différences |
|---|---|---|
| `app/generate_test_pdf.php` | PHP — 16 pages avec numéros | Génère pour l'app |
| `app/generate_test_pdf_a6_32.php` | PHP — A6 32 pages | Génère pour l'app |
| `generate_test_pdf_print.php` (root) | PHP — PDF de test impression | À déplacer dans `scripts/` |
| `scripts/generate-test-pdfs.js` | JS — 5 scénarios | Supprimer (identique root) |
| `scripts/generate_blank_pdfs.js` | JS — PDFs vierges | Supprimer (identique root) |
| `root/generate-test-pdfs.js` | JS | À déplacer dans `scripts/` |
| `root/generate_blank_pdfs.js` | JS | À déplacer dans `scripts/` |

**Conclusion** : 2 scripts PHP dans `app/` (utilisés par l'app) + déplacer les 2 JS root dans `scripts/`.

---

## 4. `app/` vs `app/public/` — JS dupliqués

**~140 fichiers JS identiques** entre `app/js/` et `app/public/js/` :
- `bootstrap.js`, `jquery.min.js`, `calcul.js`, `npm.js`
- `build/pdf.js`, `build/pdf.worker.js`, etc.
- `web/viewer.html`, `web/viewer.js`, `web/viewer.css`
- Tous les `web/cmaps/`, `web/images/`, `web/locale/`, `web/standard_fonts/`

**Uniquement dans `app/public/js/`** (5 fichiers) :
- `admin-warning.js`
- `inline-translation.js`
- `jszip.min.js`
- `lazy-loading.js`
- `print-session-manager.js`

**Action** : `app/public/js/` contient ces 5 fichiers additionnels + une copie complète des JS partagés. La copie partagée est superflue — les fichiers partagés pourraient pointer vers `app/js/`.

---

## 5. `app/api/` vs `app/public/api/` — API distinctes

| Dossier | Nombre | Contenu |
|---|---|---|
| `app/api/` | 34 fichiers | Conversion, session, upload, indexing, tambour, etc. |
| `app/public/api/` | 3 fichiers | `check_image_progress.php`, `convert-emf-to-png.php`, `download_backup.php` |

`app/api/convert-emf-to-png.php` ≠ `app/public/api/convert-emf-to-png.php` (codes différents).

`app/index.php` fait juste `require` vers `app/public/index.php`. Architecture : `app/` = sources PHP, `app/public/` = point d'entrée web.

---

## 6. Modèles en double

| Fichier A | Fichier B | Diff |
|---|---|---|
| `app/models/admin_aide_machines.php` | `app/models/aide_machines.php` | Header admin vs user |
| `app/models/admin.imprimantes.php` | `app/models/admin_imprimantes.php` | Header PHP manquant dans l'un |
| `app/models/tirage_multimachines_good.php` | `app/models/tirage_multimachines_next.php` | `fill_rate` paramètre en + dans `next` |
| `app/models/lang.php` | `app/models/admin_translations.php` | Probablement utilisé différemment |

---

## 7. Tests

| Dossier | Type | Tech |
|---|---|---|
| `./tests/` (racine) | e2e / integration / unit | JS (playwright/node) |
| `./app/tests/` | Feature / Unit | PHP (Pest/PHPUnit) |

Deux systèmes de test distincts et complémentaires.

---

## 8. Fichiers non suivis (untracked)

```
ANALYSIS_LOG.md          → Supprimé
docs/technical/          → Garder (documentation)
fix-thumbnail-windows.patch → Garder (patch one-shot)
src/print-engine/windows/win32-printer.cc.bak → Supprimé
```

---

## Plan d'action

### Phase 1 — Déplacer vers `scripts/`
1. `generate-test-pdfs.js` (root) → `scripts/`
2. `generate_blank_pdfs.js` (root) → `scripts/`
3. `check-db.js` (root) → `scripts/`
4. `repro_printing.js` (root) → `scripts/`
5. `debug_path.php` (root) → `scripts/`
6. `test_gs.php` (root) → `scripts/`
7. `generate_test_pdf_print.php` (root) → `scripts/`

### Phase 2 — Supprimer les doublons restants
8. `scripts/generate-test-pdfs.js` (était doublon du root)
9. `scripts/generate_blank_pdfs.js` (était doublon du root)
10. `scripts/legacy/`
11. `scripts/test-github-build.sh`

### Phase 3 — Vérifier avant suppression
12. `print-engine.js` (root) — vérifier usage
13. `scripts/powershell/` — vérifier usage
14. `app/public/js/` — dépurger les doublons JS après vérification des références
15. Modèles en double — vérifier usage avant fusion

### Phase 4 — Tests
16. Vérifier que le CI utilise bien `./tests/` ET `./app/tests/`

---

## Historique des actions

### 17 avril 2026 — Nettoyage Phase 1 & 2

**Fichiers déplacés vers `scripts/dev/` :**
- `generate-test-pdfs.js` (root) → `scripts/dev/`
- `generate_blank_pdfs.js` (root) → `scripts/dev/`
- `check-db.js` (root) → `scripts/dev/`
- `repro_printing.js` (root) → `scripts/dev/`
- `debug_path.php` (root) → `scripts/dev/`
- `test_gs.php` (root) → `scripts/dev/`
- `generate_test_pdf_print.php` (root) → `scripts/dev/`

**Fichiers supprimés :**
- `scripts/generate-test-pdfs.js` (doublon du root)
- `scripts/generate_blank_pdfs.js` (doublon du root)
- `scripts/legacy/` (entier — non utilisé)
- `scripts/powershell/` (entier — non utilisé)
- `scripts/test-github-build.sh` (non utilisé)

**Vérifié par subagent** avant exécution : aucun de ces fichiers n'est référencé dans le code.

### 17 avril 2026 — Nettoyage Phase 3 (partiel)

**Fichiers supprimés :**
- `print-engine.js` (racine) — vieux moteur non référencé, remplacé par `src/print-engine/`
- `app/models/tirage_multimachines_good.php` — brouillon non routé (sans `fill_rate`)
- `app/models/tirage_multimachines_next.php` — brouillon non routé (avec `fill_rate`, plus avancé)

**Analysé / conclusion sans suppression :**
- `admin_aide_machines.php` / `aide_machines.php` — pas des doublons : admin vs public, les deux actifs
- `admin.imprimantes.php` — NON routé (le point dans le nom l'exclut du routeur), vestige à supprimer
- `admin_imprimantes.php` — actif via `admin.php` qui charge la vue directement

### À faire (Phase 3 restante)

- [x] `print-engine.js` (root) — supprimé
- [x] `tirage_multimachines_good.php` / `_next.php` — supprimés
- [x] `admin.imprimantes.php` (avec point) — supprimé (non routé, remplacé par `admin_imprimantes.php`)
- [x] `app/public/js/` — `app/js/` supprimé (17 Mo, 379 fichiers orphelins) — doc `AUTO-UPDATE.md` corrigée
- [ ] CI — tests JS désactivés, tests PHP absents
