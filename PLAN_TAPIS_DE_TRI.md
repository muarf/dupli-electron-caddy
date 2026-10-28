# Spécification et Plan d'Implémentation : Le "Tapis de Tri" des Impressions (Remplacement auto_tirage)

> **Note :** Ce document est un fichier de travail local exclu de Git (`.git/info/exclude`) et ne sera jamais commité.

---

## 1. Objectif & Décision d'Architecture

* **Remplacement direct :** Ce système remplace complètement l'ancien flux `auto_tirage` qui bloquait sur l'étape préalable "Qui êtes-vous ?".
* **Contexte atelier :** 1 seul ordinateur hôte (l'opérateur) sur lequel plusieurs clients envoient leurs impressions de manière entremêlée (ex: Client 1 imprime 2 docs, Client 2 imprime 5 docs, Client 1 ré-imprime 1 doc 10 minutes après...).
* **Philosophie :** 
  - On imprime d'abord sans aucune friction.
  - Tout atterrit sur un "Tapis de Tri" visuel en temps réel (miniatures, détails techniques, prix calculé).
  - Quand un client vient régler, l'opérateur clique sur ses miniatures (ex: les 2 premières + celle relancée après).
  - Le total calculé en direct s'affiche dans une barre d'action inférieure.
  - Un clic sur **[Encaisser direct]** (Espèces / CB) solde et archive le lot en 2 secondes, sans jamais avoir besoin de créer de session.
  - Si le client souhaite un compte / facture : un bouton **[Mettre sur un compte]** permet de l'assigner à une session nommée.
  - La **saisie manuelle** (photocopieur / duplicopieur) est intégrable directement au panier avant encaissement.
* **Double compatibilité garantie :**
  - Mode Electron : notifications d'impression instantanées par IPC (`onPrintJobDetected`).
  - Mode Serveur PHP : polling léger sur l'API (toutes les 2 à 3 secondes).

---

## 2. Modèle de Données (Base SQLite existante)

Aucune migration cassante n'est nécessaire. On utilise la table `print_jobs` déjà présente :

### Cycle de vie d'un job :
1. **Arrivée de l'impression :**
   - Enregistré dans `print_jobs` avec :
     - `session_id = NULL`
     - `staged = 0`
     - `status = 'pending'`
     - `calculated_price` = calculé dès l'arrivée via les tarifs en vigueur
     - `thumbnail_url` = chemin vers la miniature PNG
2. **Lors de l'encaissement direct (Walk-in client) :**
   - Création de l'enregistrement comptabilisé dans `photocop` (ou `dupli`) avec :
     - `contact = 'Passage'`
     - `paye = 'oui'`
     - `prix = montant calculé`
     - `cb = 'oui'` ou `'non'` selon le choix
   - Mise à jour dans `print_jobs` : `status = 'completed'`, `staged = 1`.
   - Le job disparaît du Tapis de Tri actif.
3. **Lors de l'assignation à un compte / session :**
   - `print_jobs.session_id` prend l'ID de la session choisie.
   - `staged = 1`.
   - Le job bascule dans l'onglet/session du client.
4. **En cas de rejet (feuille ratée / brouillon) :**
   - Bouton poubelle : `DELETE FROM print_jobs WHERE id = ?`.

---

## 3. Découpage Technique des Composants

### Étape A : Backend API (`app/api/tapis_tri.php`)
Création d'un endpoint modulaire gérant les actions du tapis de tri :
* `GET ?tapis_tri&action=list` :
  - Retourne la liste JSON des jobs non réglés (`WHERE (session_id IS NULL OR session_id = 0) AND (status = 'pending' OR status = 'unprocessed') AND created_at >= datetime('now', '-24 hours') ORDER BY id DESC`).
* `POST ?tapis_tri&action=checkout` :
  - Reçoit la liste des `job_ids`, le moyen de paiement (`especes`, `cb`, `cheque`), et d'éventuels tirages manuels ajoutés.
  - Insère le tirage final dans `photocop` / `dupli`.
  - Marque les `print_jobs` comme clôturés (`status = 'completed'`).
* `POST ?tapis_tri&action=assign` :
  - Reçoit les `job_ids` et un `session_id` (ou un nouveau nom de contact).
  - Associe les jobs à la session.
* `POST ?tapis_tri&action=delete` :
  - Supprime les jobs sélectionnés de la liste d'attente.

### Étape B : Interface Utilisateur (`app/view/auto_tirage.html.php`)
Remplacement de l'ancienne vue par une interface à 2 zones :
1. **En-tête & Barre d'outils :**
   - Titre "Tapis de Tri des Impressions".
   - Bouton bascule vers les "Comptes en cours / Sessions ouvertes".
   - Bouton d'accès direct à la "Saisie manuelle rapide".
2. **La Grille des Tirages (Tapis de tri) :**
   - Cartes CSS modernes avec :
     - Miniature bien visible (zoom au survol).
     - Badge format (A4 / A3, Couleur / N&B, Recto/Verso, Copies).
     - Nom du fichier et heure précise.
     - Prix calculé en gros caractères.
     - Case à cocher ou clic direct sur toute la carte pour sélectionner/désélectionner.
3. **La Barre Flottante de Caisse (Sticky Bottom Bar) :**
   - S'anime dès qu'au moins 1 document est coché.
   - Affiche : `X document(s) sélectionné(s) • Total : Y,YY €`.
   - Bouton **[ 💰 Encaisser (Y,YY €) ]** ➔ Ouvre le modal rapide avec choix Espèces/CB et calcul du rendu de monnaie.
   - Bouton **[ ➕ Ajout tirage manuel ]** ➔ Ouvre un mini-formulaire pour insérer des copies/duplis manuels dans le panier avant encaissement.
   - Bouton **[ 📁 Assigner à un compte ]** ➔ Liste déroulante des clients habituels.
   - Bouton **[ 🗑️ Supprimer ]**.

### Étape C : Logique Client & Temps Réel (`app/public/js/tapis-tri.js`)
* Gestion de l'état :
  - `selectedJobs: Set<number>`
  - Calcul dynamique du total sélectionné côté client.
* Écoute des événements :
  - En Electron : écoute `window.electronAPI.onPrintJobDetected` pour rafraîchir ou ajouter la carte instantanément en tête de liste sans recharger la page.
  - En Serveur standard : polling `setInterval` toutes les 2.5 secondes sur `?tapis_tri&action=list`.

---

## 4. Plan de Tests & Validation

1. **Test unitaire Pest :**
   - Tester l'API `tapis_tri.php` sur la liste des jobs, l'encaissement direct et l'assignation.
2. **Test E2E / Atelier :**
   - Lancer 3 fichiers PDF consécutifs.
   - Vérifier l'apparition immédiate des 3 cartes avec miniatures.
   - Sélectionner 2 cartes sur 3.
   - Encaisser en "Espèces" avec rendu de monnaie.
   - Vérifier que la 3ème carte reste en attente sur le tapis et que les 2 premières sont archivées en BDD.
3. **Test Tirage Mixte :**
   - Sélectionner 1 print automatique + ajouter manuellement 20 copies A3 couleur.
   - Vérifier que le total additionne correctement les deux prestations.
4. **Vérification Electron + Web classique :**
   - Valider le bon affichage et le fonctionnement identique sous navigateur pur et sous Electron.
