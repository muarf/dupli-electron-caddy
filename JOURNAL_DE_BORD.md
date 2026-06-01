# Journal de Bord - Projet Bibliothèque Avancée

Ce journal consigne l'audit, l'architecture et les décisions techniques concernant l'évolution de la bibliothèque Duplicator.

## 🛠️ Journal des Découvertes & Décisions

### [2026-04-27 11:03] Initialisation du projet
- Création de la branche `feature/bibliotheque-avancee`.
- Création du document de spécifications `PROJET_BIBLIOTHEQUE_AVANCEE.md`.
- Localisation de la base de données : `./app/duplinew.sqlite`.

### [2026-04-27 11:10] Audit de la Base de Données (DB)
- **Table cible** : `bibliotheque_files`.
- **État actuel** : Les colonnes `page_count` et `extracted_text` existent mais sont inégalement remplies.
- **Décision Architecture** :
    - Utilisation d'un champ **`metadata_json` (TEXT)** pour stocker TOUTES les infos techniques (format, dimensions, colorimétrie, imposition).
    - Utilisation d'un champ **`tags` (TEXT)** pour les mots-clés.
    - *Avantage* : Évite de multiplier les colonnes et permet une extension facile via l'IA sans migration de schéma complexe.

### [2026-04-27 11:15] Stratégie de Détection Technique (Backend)
- **Moteur d'Analyse Physique** : Utilisation exclusive de **Ghostscript (GS)**.
    - Utilisation de la `MediaBox` pour détecter le format (A4, A3, SRA3).
    - Utilisation de `-sDEVICE=inkcov` pour détecter la couleur (Cyan/Magenta/Jaune > 0).
- **Détection de l'Imposition** :
    - **Règle 1 (Nom)** : Analyse Regex du nom du fichier (`ppp`, `imp`, `conv`, `2up`...).
    - **Règle 2 (Format)** : Si le format est **A3** ou plus ET que le nombre de pages est **supérieur ou égal à 2**, le fichier est marqué comme "Imposé".
- **Compatibilité** : Toute la logique doit être portable et fonctionner en mode **Serveur PHP** (Linux) et **Electron** (Windows/Linux).

### [2026-04-27 11:20] Audit du Flux de Données (Data Flow)
- **Point d'injection** : La méthode `registerFile()` dans `app/models/BibliothequeManager.php`.
- **Asynchronisme** : Les scans de masse utilisent déjà un système de background indexer, idéal pour les appels Ghostscript.

### [2026-04-27 17:25] Évolution de la vision IA (Tags & Corpus)
- **Extraction de Tags "Hybride"** :
    - Calcul local de la fréquence des mots (nettoyage des stop-words en PHP).
    - Ollama intervient pour extraire les concepts clés parmi les mots fréquents.
- **Concept "Chat avec la Bibliothèque" (RAG)** :
    - Utilisation de l'index **SQLite FTS5** existant pour le "retrieval".
    - Ollama génère des réponses basées sur les extraits de texte trouvés.

### [2026-04-27 17:32] Préparation de la Migration DB
- Script créé : `app/models/migrations/add_bibliotheque_metadata_and_tags.php`.
- Intégration effectuée dans `DatabaseMigrationManager.php`.
- **Statut** : Prêt à être appliqué (sera déclenché au prochain démarrage de l'app ou manuellement).

### [2026-04-27 17:34] Migration DB effectuée
- Script exécuté avec succès via `scratch/run_migrations.php`.
- Table `bibliotheque_files` mise à jour avec `metadata_json` et `tags`.
- Backup pré-migration généré.
- **Statut** : Complété.

### [2026-04-27 17:35] Validation des Prototypes Ghostscript
- **Détection Format** : Validée.
- **Détection Couleur** : Validée (Passage en scan intégral du document le 27/04 à 17:39).
- **Sécurité** : Nécessite l'argument `-dNOSAFER`.
- **Statut** : Complété.

### [2026-04-27 17:42] Extraction de Tags Hybride opérationnelle
- Implémentation de `extractKeywords` (PHP) et `refineTagsWithAI` (Ollama).
- Les tags sont correctement extraits et stockés dans la colonne `tags`.
- Fallback automatique sur PHP si Ollama est absent ou lent.
- **Statut** : Complété.

### [2026-04-28 08:30] Modernisation de l'Interface & Filtrage Avancé
- **UI "Premium"** : Refonte visuelle de la bibliothèque avec une barre de recherche persistante, un compteur de résultats dynamique et une navigation par pagination.
- **Filtres Techniques** : Implémentation de sélecteurs pour le format (A4/A3), la couleur, et l'imposition.
- **Filtre par Tags** : 
    - Ajout d'un sélecteur dédié "Tags" peuplé dynamiquement par les tags uniques de la base de données.
    - **Tags Cliquables** : Cliquer sur un badge de tag dans la grille ou le tableau active automatiquement le filtre correspondant.
    - **Synchronisation Dynamique** : Capacité du système à ajouter des options de filtrage au vol si un nouveau tag est rencontré sans rafraîchir la page.

### [2026-04-28 08:40] Optimisation du Moteur de Recherche
- **Recherche SQL Robuste** :
    - Normalisation des tags dans les requêtes (`REPLACE` des espaces) pour garantir des correspondances exactes malgré les variations de stockage.
    - Insensibilité à la casse via `LOWER()` pour une recherche plus intuitive.
- **Support FTS5** : Extension de l'index plein texte SQLite pour inclure la colonne `tags`, permettant des recherches instantanées sur des milliers de documents.
- **Diagnostic des Métadonnées** : Identification des limites d'extraction (PDF scannés sans couche texte) expliquant l'absence de tags sur certains fichiers.

---

## 🔍 Prochaines étapes (Planification)
1.  **Système de Chat RAG** : Utiliser les textes extraits pour permettre de poser des questions à sa bibliothèque via Ollama.
2.  **OCR Intégré** : Étudier l'utilisation de Tesseract pour générer des tags sur les PDF "image" qui n'en ont pas actuellement.
3.  **Gestion des Doublons** : Détection intelligente des fichiers identiques basés sur le contenu ou le nom.
