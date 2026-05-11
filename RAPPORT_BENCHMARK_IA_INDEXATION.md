# Rapport de Recherche : Optimisation de l'Indexation Thématique par IA Locale (Ollama)

Ce document récapitule les tests effectués pour automatiser la création de tags thématiques pour une bibliothèque de brochures radicales. L'objectif est de trouver le meilleur compromis entre vitesse (sur VPS ARM) et pertinence politique/conceptuelle.

## 1. Contexte Technique
- **Serveur** : VPS Oracle ARM (Always Free), 4 Cores, 24 Go RAM.
- **Moteur IA** : Ollama (accès via tunnel SSH).
- **Langage** : PHP 8.x (modèle `BibliothequeManager.php`).
- **Pipeline** : 
    1. Extraction physique (Ghostscript).
    2. Extraction de texte (PdfParser).
    3. Filtrage linguistique (Bibliothèque PHP `voku/stop-words` pour nettoyer les mots vides).
    4. Raffinement thématique (Appel API Ollama).

---

## 2. Historique des Prompts et Résultats

### Tentative A : Le Prompt "Académique" (Trop complexe)
**Prompt :**
> "Consigne : Voici une liste de mots extraits d'un document nommé '$filename'. Génère 3 à 5 tags (mots-clés) thématiques qui résument le sujet. Sois précis et privilégie les lieux (villes, pays), les noms d'organisations ou les concepts politiques/sociaux importants. Réponds uniquement par les tags séparés par des virgules."

**Résultats :**
- **Ministral 8B** (157s) : `Luttes des classes et des terres, Discours des places autoritaires, Soulèvements des personnes en terre`.
- **Analyse** : Très intelligent (synthèse de concepts) mais trop lent et parfois trop "verbeux".

### Tentative B : Le Prompt "Direct" (Échec sur modèles à raisonnement)
**Prompt :**
> "CONSIGNE : Donne directement 3 tags thématiques. Réponds uniquement par les tags séparés par des virgules, sans ton raisonnement."

**Résultats :**
- **Qwen 4B** : Réponse vide.
- **Analyse** : L'IA consommait tous ses jetons (tokens) dans un champ de "pensée interne" (thinking process) et s'arrêtait avant de donner le résultat.

### Tentative C : Le Prompt "Utilisateur" (Le plus efficace)
**Prompt :**
> "Voici les 20 mots les plus cités dans ce pdf [$filename] : [$keywords]. Analyse aussi s'il y a des mots/concepts dans le titre, et si il y en a ajoute-les à la liste. Garde uniquement les mots qui apportent du sens pour tagger un article : des mots concepts, des noms propres, des lieux, des dates, des cibles, des actions concrètes. N'extrapole pas. Réponds uniquement par les tags séparés par des virgules sans aucune phrase."

**Résultats sur le fichier "Gazoduc TAP" :**
- **Gemma 4:e2b** (78s) : `énergie, lutte, gazoduc TAP, chantier, travaux, Salento, Lecce, projet énergétique, opposition, construction`.
- **Analyse** : Excellent. Extraction fidèle des lieux et des cibles sans inventer de concepts inutiles.

---

## 3. Grand Prix Ollama (Benchmark Global)
Test effectué sur le document "Qu'est-ce que le terrorisme ?" (Top 20 mots : terrorisme, etat, violence, terreur, anarchiste, révolution...).

| Modèle | Temps | Qualité | Observation |
| :--- | :--- | :--- | :--- |
| **qwen2.5:0.5b** | 6s | Mauvaise | Hallucinations (invente des dates et des mots). |
| **gemma4:e2b** | **78s** | **Excellente** | Respecte les 5 tags, intègre le titre, très précis. |
| **ministral-3:8b** | 166s | Bonne | Un peu lent, tags plus génériques. |
| **gemma4:e4b** | 265s | Bonne | Identique à la e2b mais 3x plus lente. |
| **qwen3.5:4b** | 300s | Échec | Timeout systématique (boucle de réflexion). |
| **gemma4:26b** | 300s | Échec | Trop lourd pour le CPU ARM. |

---

## 4. PROMPT POUR GEMINI DEEP RESEARCH

Copie-colle le texte ci-dessous dans Gemini Deep Research pour obtenir une analyse architecturale :

> **SUJET : Optimisation d'un moteur d'indexation IA locale sur infrastructure contrainte (ARM/Ollama).**
>
> **CONTEXTE :**
> Je développe une bibliothèque numérique de brochures politiques. J'utilise un VPS Oracle ARM (4 cores) avec Ollama. Mon pipeline PHP extrait les 20 mots les plus fréquents (après nettoyage des stop-words) et les envoie à un LLM local pour générer 5 tags thématiques définitifs.
>
> **PROMPT ACTUEL :**
> "Voici les 20 mots les plus cités dans ce pdf [$filename] : [$keywords]. Analyse aussi s'il y a des mots/concepts dans le titre, et si il y en a ajoute-les à la liste. Garde uniquement les mots qui apportent du sens pour tagger un article : des mots concepts, des noms propres, des lieux, des dates, des cibles, des actions concrètes. N'extrapole pas. Réponds uniquement par les tags séparés par des virgules sans aucune phrase."
>
> **OBSERVATIONS DU BENCHMARK :**
> 1. Les modèles de taille < 1B (Qwen 0.5B) hallucinent dès qu'on leur demande un choix sélectif.
> 2. Les modèles "Instruct/Reasoning" (Qwen 4B) échouent en production car ils passent trop de temps en réflexion interne.
> 3. Le modèle **Gemma 4:e2b** (env. 7-9B) est le plus équilibré (78s de traitement, tags précis).
> 4. Le titre du fichier est crucial pour capturer les noms propres que le comptage de mots dans le corps du texte rate parfois.
>
> **TES MISSIONS :**
> 1. Analyse la structure de mon prompt et propose des améliorations pour réduire encore les hallucinations sur les petits modèles.
> 2. Recherche s'il existe des "Modelfiles" spécifiques ou des paramètres Ollama (temperature, top_p, repeat_penalty) optimisés pour l'extraction de mots-clés pure (tagging) sur des modèles de 2B à 8B.
> 3. Propose une stratégie PHP pour mieux préparer la liste de mots-clés en amont afin d'aider l'IA (ex: détection d'entités nommées légère en PHP avant l'IA).
> 4. Évalue si l'utilisation d'un modèle de "Small Language Model" spécialisé dans le résumé (comme un Bert ou un T5 léger) serait plus efficace que des modèles généralistes type Gemma/Mistral pour cette tâche précise.
