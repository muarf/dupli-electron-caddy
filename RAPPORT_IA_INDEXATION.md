# Rapport d'Expérimentation : Indexation Intelligente (IA)

Ce document récapitule les recherches et tests effectués pour automatiser l'extraction de tags (mots-clés) sur des documents longs (brochures PDF) en utilisant l'IA.

---

## 1. Architecture Infrastructure
Pour éviter de saturer les machines locales, nous avons déporté l'intelligence sur un **VPS Oracle (ARM)**.
- **API GLiNER** : Serveur Python (FastAPI) tournant sur le port 8095.
- **Tunnel SSH** : Connexion sécurisée entre l'application et le VPS pour simuler un accès local.
- **Traitement par Blocs (Chunking)** : Découpage des textes longs (jusqu'à 100 000 caractères) en segments de 2 000 caractères pour ne pas saturer la mémoire vidéo (VRAM).

---

## 2. Approche A : Ollama (Modèle Qwen 2.5 0.5b)

### Le Prompt utilisé :
```text
Voici les 20 mots les plus cités dans ce pdf [Titre du PDF] : [Mots-clés PHP].
Analyse aussi s'il y a des noms propres ou concepts dans le titre et ajoute-les à ta réflexion.
CONSIGNE : Parmi ces mots uniquement, choisis les 5 tags les plus pertinents pour caractériser le document.
N'invente rien. Réponds uniquement par les 5 tags séparés par des virgules sans aucune phrase.
```

### Résultats :
- **Points forts** : Très rapide, capable de faire une synthèse sémantique.
- **Points faibles** : Tendance à ignorer les noms propres complexes cachés dans le texte. Limité par la "fenêtre de contexte" (ne peut pas lire 50 000 caractères d'un coup sans perdre le fil).

---

## 3. Approche B : GLiNER (Named Entity Recognition)
Modèle utilisé : `urchade/gliner_medium-v2.1` (Spécialisé dans l'extraction d'entités).

### La Configuration :
Nous avons demandé au modèle de chercher trois types d'entités :
- **Personnalité**
- **Organisation**
- **Lieu**

### Résultats (Exemple sur Elon Musk) :
- **Tags extraits** : `Elon Musk`, `Yang`, `Silicon Valley`, `DeepMind`.
- **Analyse** : GLiNER est chirurgical pour trouver les noms propres, mais il ignore totalement les concepts abstraits (ex: "Capitalisme", "Sabotage", "Peur").

---

## 4. Approche C : La Stratégie Hybride (PHP + GLiNER)
C'est l'approche la plus équilibrée que nous avons validée.

### Logique de Fusion :
- **Top 2 Tags (GLiNER)** : On prend les deux acteurs principaux (Personnes ou Organisations).
- **Top 3 Tags (PHP)** : On prend les trois thèmes les plus fréquents, avec un **bonus de +500 points** pour les mots présents dans le titre.

### Benchmark sur 50 000 caractères :
| Fichier | Tags Hybrides (2 Noms + 3 Concepts) |
| :--- | :--- |
| **Elon Musk** | `Elon Musk`, `Yang`, `peur`, `science`, `technologies` |
| **Gazoduc TAP** | `Silence radio`, `Zurich`, `lutte`, `gazoduc`, `construction` |
| **Terrorisme** | `Turin`, `roi Umberto I`, `terrorisme`, `etat`, `violence` |

---

## 5. Synthèse des Performances (Sur VPS Oracle)

| Méthode | Temps (100k chars) | Précision Noms | Pertinence Thèmes |
| :--- | :--- | :--- | :--- |
| **PHP Pur** | < 1 sec | ⭐⭐ | ⭐⭐⭐ |
| **Ollama** | ~10 sec | ⭐ | ⭐⭐⭐⭐ |
| **GLiNER** | ~60 sec | ⭐⭐⭐⭐⭐ | ⭐ |
| **HYBRIDE** | ~65 sec | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ |

---

## Conclusion
L'IA (GLiNER) est indispensable pour identifier les acteurs (noms propres) que les statistiques PHP ratent souvent. Cependant, le PHP reste le meilleur pour capturer l'essence du titre. La fusion des deux est la solution "Premium" pour une bibliothèque de haute qualité.

*Note : Pour l'instant, nous avons déployé la version "PHP Pur" améliorée (Stemming + Titre) pour stabiliser la bibliothèque sur 661 fichiers avant de réactiver le module IA.*
