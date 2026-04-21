# Retour et Analyse Critique du Plan UUID + Analyse

## 1. Accord sur la Logique Globale
- **UUID Composite** : Indispensable pour éviter les collisions Windows (Job ID recyclé). Utiliser `Printer + JobId + Time` est la bonne approche.
- **Double Notification** : Nécessaire car l'analyse (30 pages) arrive toujours après la détection initiale (2 pages).

## 2. Points de Vigilance sur la méthode C++ (HTTP POST)
L'implémentation d'un POST HTTP directement en C++ présente plusieurs risques :
- **Complexité Native** : L'ajout de code réseau (`wininet.h` ou autre) dans le module natif alourdit le build et est sensible aux firewalls/VPN locaux.
- **Désynchronisation de l'UUID** : Si l'UUID est calculé en JS **ET** en C++, la moindre différence de format (timezone, séparateurs, casse) rendra l'UPDATE impossible en PHP.
- **Invisibilité des Blocages Actuels** : Même un POST direct depuis le C++ échouera si on ne supprime pas les "verrous" anti-doublons existants dans le PHP.

## 3. Découverte : Les deux "Verrous" (Gatekeepers)
Le problème des "2 pages vs 30" dans le **Pool** est causé par deux blocages successifs :

1. **Le Verrou JS (`print-session-manager.js`)** :
   - Bloque toute notification pendant 60s après la première détection.
   - Ne laisse passer la mise à jour que si `fillRate > 0`. 
   - **Bug** : Si l'impression est en Noir & Blanc (taux à 0), la mise à jour des pages est jetée par le JS.

2. **Le Verrou PHP (`print-notification.php`)** :
   - À la ligne 95, il ne considère comme "meilleure donnée" que le `fillRate` ou le `thumbnail`.
   - **Bug** : Il ignore le changement du nombre de pages (`total_pages`). Si les pages changent de 2 à 30 mais que le taux reste identique, le PHP rejette l'update.

## 4. Proposition de Plan Simplifié (Plus Robuste)

### Étape 1 : UUID à la Source
Générer l'UUID **une seule fois** dans le moteur C++ et le passer au JS. Cela garantit une clé immuable pour toute la durée de vie du job, de l'OS jusqu'à la DB.

### Étape 2 : Communication Unifiée (JS Bridge)
Ne pas ajouter de code HTTP en C++. Utiliser le canal `OnProgress` existant entre le C++ et le JS. C'est le `print-session-manager.js` qui se chargera d'envoyer la notification de mise à jour au PHP. 
*Avantages : Meilleure gestion d'erreurs, logs centralisés, pas de dépendance réseau en natif.*

### Étape 3 : Ouverture des Verrous
- **JS** : Autoriser le renvoi de notification si `totalPages` a augmenté.
- **PHP** : Dans `print-notification.php`, ajouter le test `newTotalPages > existingTotalPages` pour autoriser l'UPDATE.

---
*Ce plan permet de corriger le Pool en temps réel sans redémarrage et sans complexifier le moteur natif.*
