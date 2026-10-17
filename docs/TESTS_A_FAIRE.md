# 🧪 Liste des Tests à Faire (Recette & Non-Régression)

Ce document récapitule l'ensemble des tests à exécuter pour valider la stabilité de l'application **Duplicator** (versions Electron Desktop & Serveur PHP).

---

## 1. ⚙️ Tests Automatisés (CI / Développeur)

| Test | Commande | Statut | Objectif |
| :--- | :--- | :---: | :--- |
| **Suite de tests Jest** | `npm run test` | ✅ Passé (16/16) | Vérifie le démarrage Caddy, le proxying PHP et les endpoints de base |
| **Vérification Rust** | `cargo check` | ✅ Passé | S'assure que le code Rust Tauri compile sans erreur ni panic |
| **Build Moteur Impression** | `npm run rebuild:print-engine` | 🔄 À tester sur Win | Vérifie la compilation du binaire d'impression C++ / Rust |

---

## 2. 🖥️ Tests Manuels — Version Electron (Windows)

> 💡 **Base de données SQLite :** `C:\Users\Dupli\AppData\Roaming\Duplicator\duplinew.sqlite`  
> 💡 **Log d'erreurs :** `C:\Users\Dupli\AppData\Local\Temp\duplicator_errors.log`

### A. Changement de Consommables (`changement.html.php`)
- [ ] Sélectionner un duplicopieur (ex: RISO) et vérifier le remplissage dynamique des bacs et tambours.
- [ ] Sélectionner un photocopieur et vérifier que le champ `masters` s'estompe correctement.
- [ ] Soumettre un changement avec des données valides et vérifier l'enregistrement en SQLite.
- [ ] Vérifier l'affichage de l'aide dynamique selon la machine.

### B. Impression & Dialogue (`print_dialog.html.php` & `print-modal.html.php`)
- [ ] Lancer une impression depuis le Studio ou la Bibliothèque.
- [ ] Vérifier la détection automatique des imprimantes locales Windows via l'API Electron (`getPrinters`).
- [ ] Sélectionner des options avancées (recto/verso, bac papier, couleur/NB, copies) et valider l'envoi vers SumatraPDF.
- [ ] Vérifier l'actualisation du moniteur d'impression dans `admin_imprimantes.html.php`.

### C. Administration & Ingestion IA (`admin.bibliotheque_ia.html.php`)
- [ ] Importer un nouveau document PDF dans la Bibliothèque.
- [ ] Lancer la ré-analyse des métadonnées (format A3/A4, détection couleur inkcov, nombre de pages).
- [ ] Vérifier l'extraction des mots-clés et des chunks RAG.

### D. Mises à Jour & Système (`admin.news.html.php` & `create_password.html.php`)
- [ ] Éditer une actualité avec l'éditeur Quill et vérifier l'affichage des images compressées base64.
- [ ] Tester le changement de mot de passe administrateur avec confirmation.

---

## 3. 🌐 Tests de Compatibilité — Version Serveur PHP Web

- [ ] Lancer l'application en mode serveur PHP direct (ex: PHP built-in server ou Apache/Nginx).
- [ ] Vérifier que l'absence de l'API `window.electronAPI` n'engendre pas d'erreur JS et bascule correctement sur les messages d'avertissement Web.
- [ ] Vérifier la cohérence de l'affichage des langues et des traductions (`CONFIG.translations`).
