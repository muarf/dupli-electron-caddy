# Projet : Bibliothèque Avancée (Duplicator)

Ce document détaille les spécifications pour l'évolution technique de la bibliothèque de fichiers de l'application Duplicator.

## Objectifs Principaux

Transformer la bibliothèque actuelle en un outil technique performant capable d'automatiser l'analyse des fichiers et d'améliorer l'organisation, tout en restant compatible entre les versions Electron et Serveur PHP.

### 1. Analyse Automatique des Fichiers (via Ghostscript)
Le système détecte automatiquement les caractéristiques techniques lors de l'indexation :
- **Format** : Détection du format physique (A4, A3, SRA3, etc.) via la MediaBox.
- **Nombre de pages** : Comptage précis du nombre de pages.
- **Couleur** : Détection de la présence de couleur via le module `inkcov`.
- **État d'imposition** :
    - Basé sur le nom du fichier (mots-clés : `ppp`, `imp`, `conv`, `2up`...).
    - Heuristique : Si le format est **A3** ou plus et qu'il y a **au moins 2 pages**, le fichier est considéré comme imposé.

### 2. Interface "Tableau de Bord"
Passage d'une vue grille à une vue liste/tableau optimisée :
- **Vue Tableau** : Affichage des métadonnées techniques dans des colonnes dédiées.
- **Tris et Filtres** : Filtrage rapide par format, couleur, état d'imposition et tags.
- **Recherche Avancée** : Recherche textuelle rapide via l'index FTS5 existant.

### 3. Organisation Intelligente (Tags & AI)
- **Tags par Fréquence** : Extraction automatique des mots les plus fréquents (après retrait des "mots vides").
- **Filtrage Ollama** : Utilisation de l'IA locale pour sélectionner les mots-clés les plus significatifs parmi les fréquences calculées.
- **Chat avec le Corpus (RAG)** : Vision future permettant de discuter avec l'ensemble de la bibliothèque. L'IA utilise l'index FTS5 pour retrouver le contexte et répondre aux questions de l'utilisateur.

---

## Architecture Technique

### Base de données (SQLite)
- **`metadata_json` (TEXT)** : Champ unique pour stocker toutes les infos techniques (format, couleur, imposition, etc.) afin d'éviter les migrations de schéma fréquentes.
- **`tags` (TEXT)** : Champ dédié aux mots-clés pour faciliter la recherche.

### Backend (PHP + Binaires)
- **Analyseur** : Utilisation exclusive de **Ghostscript** (déjà présent pour les miniatures).
- **Compatibilité** : Tout le code d'analyse doit fonctionner en **mode Serveur PHP** (via les binaires système) comme en **mode Electron**.

### Frontend
- Remplacement de la grille actuelle par un composant de type Tableau dynamique avec support des filtres multi-critères.
