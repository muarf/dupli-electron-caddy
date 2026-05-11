# Architecture Hybride Duplicator

Ce document décrit l'organisation réseau et matérielle entre la machine locale et le serveur de calcul.

## 🏗️ Répartition des Machines

### 🏠 Local : **big-arm** (Machine ARM 4 Cores)
*   **Rôle** : Serveur d'application et interface utilisateur.
*   **Services** :
    *   **Caddy** : Serveur Web (Reverse Proxy).
    *   **PHP 8.x** : Logique métier et API.
    *   **SQLite** : Base de données (`duplinew.sqlite`).
    *   **Antigravity** : Agent IA d'assistance au code.
    *   **Stockage** : Bibliothèque de documents (PDF/PNG).

### ☁️ Remote : **vps-oracle** (Instance Oracle Cloud 4 Cores)
*   **Rôle** : Serveur de calcul IA (LLM, Embeddings & Reranking).
*   **Services** :
    *   **Ollama** : Gère les Embeddings (`bge-m3`) pour la recherche RAG.
    *   **Llama-Server (Gemma)** : Modèle expert (`Gemma-2-9B`).
    *   **Llama-Server (Luth)** : Modèle rapide (`Luth-1.7B`).
    *   **Llama-Server (Reranker)** : Classement de précision (`bge-reranker-v2-m3`).

---

## 🚇 Tunnels SSH (Mapping des Ports)

Quatre tunnels SSH permanents relient `big-arm` à `vps-oracle` pour permettre à l'application PHP de solliciter l'IA comme si elle était locale.

| Port Local (big-arm) | Port Distant (vps-oracle) | Service Correspondant | Usage |
| :--- | :--- | :--- | :--- |
| **11434** | **11434** | **Ollama** | Vectorisation (Embeddings `bge-m3`) |
| **11435** | **8080** | **Gemma-2-9B** | Chat IA - Mode **Expert** (Pro) |
| **11436** | **8081** | **Luth-1.7B** | Chat IA - Mode **Rapide** (Fast) |
| **11437** | **11437** | **BGE-Reranker** | **Reranking** (Précision RAG) |

### Commande de maintenance (Service Systemd) :
Les tunnels sont gérés par le service systemd `ollama-tunnel.service` sur `big-arm`.

---

## 🔄 Flux de données RAG (Turbo)
1.  **Indexation** : `big-arm` lit un PDF -> envoie le texte à **OVH AI Endpoints** (Turbo) ou local (`11434`) -> calcule le vecteur -> `big-arm` stocke le vecteur en SQLite.
2.  **Chat** : 
    *   L'utilisateur pose une question -> `big-arm` vectorise la question (`11434`).
    *   Recherche Top 20 en SQLite.
    *   Envoie les 20 chunks au **Reranker** (`11437`) -> Sélectionne les 5 meilleurs.
    *   Envoie ces 5 chunks et la question à **Gemma** (`11435`) ou **Luth** (`11436`).
    *   `vps-oracle` génère la réponse -> `big-arm` affiche le résultat.
