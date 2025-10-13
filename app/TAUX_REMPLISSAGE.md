# Système de Taux de Remplissage - Documentation

## Vue d'ensemble

Le système de taux de remplissage permet d'ajuster les calculs de coûts couleur pour les **photocopieurs uniquement** dans la page "Tirage Multimachines".

## ⚠️ Important : Nouvelle Logique

**50% = Prix normal de la BDD** (référence)
- Si vous réglez le slider à **50%**, le prix est **exactement celui de la base de données**
- Si vous réglez à **100%**, le prix est **doublé** (×2)
- Si vous réglez à **25%**, le prix est **divisé par 2** (×0.5)

**Formule : Prix Final = Prix BDD × (Taux / 50%)**

## Fonctionnalités Implémentées

### 1. Interface Utilisateur

#### A. Slider de Taux de Remplissage

**Pour les Duplicopieurs :**
- Slider de 0% à 100% avec pas de 5%
- Badge coloré affichant le pourcentage :
  - Vert (>70%) : remplissage élevé
  - Orange (30-70%) : remplissage moyen
  - Rouge (<30%) : remplissage faible
- Valeur par défaut : 100% (comportement identique à l'ancien système)
- Appliqué au prix des passages (usage encre/tambour)

**Pour les Photocopieurs :**
- Même interface que pour les duplicopieurs
- Appliqué au prix de toutes les couleurs (cyan, magenta, yellow, noir, etc.)
- Affecte uniquement les coûts d'encre/toner, pas le papier

### 2. Calculs Backend (PHP)

#### Fonction `calculatePageCost()`
Modifiée pour accepter un paramètre `$fill_rate` (0.0 à 1.0) :
```php
function calculatePageCost($machine_name, $machine_type, $prices, $is_color, $is_duplex, $fill_rate = 1.0)
```

**Logique :**
- Chaque couleur est multipliée par le `fill_rate`
- Par défaut : `fill_rate = 1.0` (100%) pour préserver le comportement existant
- Exemple : Si `fill_rate = 0.5` (50%), le coût d'encre cyan passe de 0.10€ à 0.05€

#### Calculs Duplicopieurs
- Le taux de remplissage s'applique au **prix des passages** uniquement
- Les masters ne sont pas affectés (coût fixe par master)
- Formule : `prix_total = (nb_masters × prix_master) + (nb_passages × prix_passage × fill_rate) + (nb_feuilles × prix_papier)`

#### Calculs Photocopieurs
- Le taux de remplissage s'applique à **toutes les couleurs**
- Pour un photocopieur à toner en couleur :
  - Ancien : `coût = cyan + magenta + yellow + noir`
  - Nouveau : `coût = (cyan + magenta + yellow + noir) × fill_rate`

### 3. Calculs Frontend (JavaScript)

#### Fonction `updateFillRate(machineIndex)`
- Met à jour le badge visuel en temps réel
- Stocke la valeur dans un champ caché
- Recalcule automatiquement le prix total
- Change la couleur du badge selon le taux

#### Intégration dans `calculateMachinePrice()`
- Récupère le `fill_rate` du champ caché
- Applique le taux aux calculs duplicopieurs et photocopieurs
- Cohérence totale avec les calculs PHP backend

### 4. Transmission des Données

#### Champs de Formulaire
```html
<!-- Slider visible -->
<input type="range" id="fill_rate_slider_0" min="0" max="100" value="100" step="5" 
    onchange="updateFillRate(0)" oninput="updateFillRate(0)">

<!-- Champ caché pour transmission -->
<input type="hidden" id="fill_rate_0" name="machines[0][fill_rate]" value="1.0">
```

#### Page de Confirmation
Le taux de remplissage est transmis via champ caché :
```html
<input type="hidden" name="machines[<?= $index ?>][fill_rate]" 
    value="<?= isset($machine['fill_rate']) ? $machine['fill_rate'] : '1.0' ?>" />
```

## Cas d'Usage

### Exemples Pratiques

1. **Texte seul (faible remplissage) :**
   - Taux recommandé : 25-30%
   - Économie : ~70% sur les coûts d'encre/toner

2. **Texte avec quelques images :**
   - Taux recommandé : 50%
   - Économie : ~50% sur les coûts d'encre/toner

3. **Images pleines / Photos :**
   - Taux recommandé : 100%
   - Coût normal (comportement par défaut)

## Compatibilité

### Rétrocompatibilité
- **100% compatible** avec l'ancien système
- Valeur par défaut : 100% (comportement identique)
- Si le champ `fill_rate` n'est pas fourni, le système utilise 1.0 automatiquement

### Types de Machines Supportés

✅ **Duplicopieurs :**
- Tambour noir
- Tambour rouge
- Tambour bleu
- Tous les tambours personnalisés

✅ **Photocopieurs à Encre (ex: Comcolor) :**
- Bleue, Jaune, Noire, Rouge, Couleur

✅ **Photocopieurs à Toner (ex: Konika) :**
- Cyan, Magenta, Yellow, Noir, Tambour, Developer

## Impact sur les Prix

### Exemple Concret : Photocopieur Couleur

**Configuration :**
- 100 pages couleur A4 recto-verso
- Prix cyan : 0.003€/page
- Prix magenta : 0.003€/page
- Prix yellow : 0.003€/page
- Prix noir : 0.002€/page
- **Total encre par page : 0.011€**

**Sans taux de remplissage (ancien système) :**
- Coût encre : 200 pages × 0.011€ = **2.20€**

**Avec taux de remplissage 50% (nouveau système) :**
- Coût encre : 200 pages × 0.011€ × 0.5 = **1.10€**
- **Économie : 1.10€ (50%)**

### Exemple : Duplicopieur

**Configuration :**
- 500 passages
- Prix passage (tambour noir) : 0.008€
- Taille : A3

**Sans taux de remplissage :**
- Coût passages : 500 × 0.008€ = **4.00€**

**Avec taux de remplissage 30% :**
- Coût passages : 500 × 0.008€ × 0.3 = **1.20€**
- **Économie : 2.80€ (70%)**

## Fonctionnalités Futures (Non Implémentées)

### Analyse Automatique de Fichier
- Upload d'un PDF ou image
- Calcul automatique du taux de remplissage via analyse de pixels
- Mise à jour automatique du slider

**Technologies possibles :**
- Canvas API (JavaScript côté client)
- ImageMagick ou GD (PHP côté serveur)
- PDF.js pour extraction des pages PDF

### Préréglages Rapides
Boutons pour sélection rapide :
- 25% (Texte léger)
- 50% (Texte avec images)
- 75% (Images nombreuses)
- 100% (Images pleines)

## Fichiers Modifiés

### Backend (PHP)
- `models/tirage_multimachines.php`
  - `calculatePageCost()` : Ajout paramètre `$fill_rate`
  - `calculateBrochurePriceOptimized()` : Ajout paramètre `$fill_rate`
  - Action() : Récupération et application du `fill_rate` dans les calculs

### Frontend (HTML/JS)
- `view/tirage_multimachines.html.php`
  - Interface slider pour duplicopieurs (ligne ~779)
  - Interface slider pour photocopieurs (ligne ~909)
  - Fonction `updateFillRate()` (ligne ~1133)
  - Modification `calculateMachinePrice()` pour duplicopieurs (ligne ~1383)
  - Modification `calculateMachinePrice()` pour photocopieurs (ligne ~1455)
  - Champs cachés dans la page de confirmation

## Tests Recommandés

### Tests Manuels
1. ✅ Créer un tirage duplicopieur avec 50% de remplissage
2. ✅ Créer un tirage photocopieur couleur avec 25% de remplissage
3. ✅ Vérifier que 100% donne le même résultat que l'ancien système
4. ✅ Tester avec plusieurs machines simultanément
5. ✅ Vérifier la cohérence entre l'affichage temps réel et le prix enregistré

### Tests de Régression
1. ✅ Sans spécifier fill_rate : comportement identique à l'ancien système
2. ✅ Avec machines multiples : chaque machine conserve son taux indépendant
3. ✅ Compatibilité avec tous les types de photocopieurs et duplicopieurs

## Assistance TODO

Cette fonctionnalité répond à l'item TODO "Statistiques de remplissage" du README principal.

## Notes Techniques

### Validation des Valeurs
- Le taux est validé entre 0 et 1 (0% à 100%)
- Si une valeur invalide est fournie, le système utilise 1.0 par défaut
- Les valeurs négatives ou supérieures à 1 sont automatiquement corrigées

### Performance
- Aucun impact sur les performances (simple multiplication)
- Pas de requêtes DB supplémentaires
- Calcul instantané côté client

### Sécurité
- Validation côté serveur du taux de remplissage
- Protection contre les valeurs malveillantes
- Type float forcé pour éviter les injections

## Support

Pour toute question ou amélioration, consulter le README principal ou contacter l'équipe de développement.


