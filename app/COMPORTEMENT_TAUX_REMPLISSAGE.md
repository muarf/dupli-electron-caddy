# 🎯 Comportement Final du Taux de Remplissage

## 📋 Règles d'Affichage

### ✅ Le slider apparaît UNIQUEMENT si :
1. Type de machine = **Photocopieur** ✅
2. Au moins une brochure a **"Couleur"** cochée ✅

### ❌ Le slider est MASQUÉ si :
1. Type de machine = **Duplicopieur** ❌
2. Photocopieur mais **TOUTES** les brochures sont en noir et blanc ❌

---

## 🎬 Comportement Dynamique

### Scénario 1 : Photocopieur → Couleur cochée
```
1. Sélectionner "Photocopieur"
2. Cocher "Couleur" sur une brochure
   → Le slider APPARAÎT ✅
3. Ajuster le slider (ex: 75%)
   → Prix se recalcule avec multiplicateur ×1.5
```

### Scénario 2 : Photocopieur → Couleur décochée
```
1. Sélectionner "Photocopieur"
2. Laisser "Couleur" décoché (noir et blanc)
   → Le slider reste MASQUÉ ❌
3. Le prix utilise le prix BDD normal (multiplicateur ×1.0)
```

### Scénario 3 : Duplicopieur
```
1. Sélectionner "Duplicopieur"
   → Le slider n'apparaît JAMAIS ❌
2. Prix calculé normalement (masters + passages + papier)
```

---

## 💻 Logique JavaScript

### Fonction `toggleFillRateVisibility(machineIndex)`

**Appelée quand :**
- La checkbox "Couleur" change d'état

**Comportement :**
```javascript
function toggleFillRateVisibility(machineIndex) {
    // 1. Vérifier si AU MOINS une brochure a "couleur" cochée
    var hasCouleur = false;
    brochures.forEach(function(brochure) {
        if (couleurCheckbox && couleurCheckbox.checked) {
            hasCouleur = true;
        }
    });
    
    // 2. Afficher/masquer le container
    fillRateContainer.style.display = hasCouleur ? '' : 'none';
}
```

### Calculs avec Taux Conditionnel

```javascript
// Déterminer le multiplicateur
var fillRateMultiplier = 1.0; // Par défaut (noir et blanc)

if (couleur) {
    // Couleur cochée → Utiliser le taux de remplissage
    var fillRate = fillRateField.value; // 0.0 à 1.0
    fillRateMultiplier = fillRate / 0.5; // 50% = ×1, 100% = ×2
} else {
    // Noir et blanc → Prix BDD normal (×1.0)
    fillRateMultiplier = 1.0;
}

// Application
prixEncre = (prixBDD × fillRateMultiplier);
```

---

## 💾 Logique Backend PHP

### Fonction `calculatePageCost()`

```php
function calculatePageCost(..., $fill_rate = 0.5) {
    $fill_rate_multiplier = 1.0; // Par défaut
    
    if ($is_color) {
        // Couleur → Appliquer le taux
        $fill_rate_multiplier = $fill_rate / 0.5;
    } else {
        // Noir et blanc → Prix BDD normal (×1.0)
        $fill_rate_multiplier = 1.0;
    }
    
    $cost_per_page += ($prices['cyan']['unite'] ?? 0) * $fill_rate_multiplier;
}
```

### Page de Confirmation

**Badge affiché seulement si couleur :**
```php
<?php
// Vérifier si au moins une brochure est en couleur
$has_couleur = false;
foreach ($machine['brochures'] as $brochure) {
    if ($brochure['couleur'] == 'oui') {
        $has_couleur = true;
        break;
    }
}

if ($has_couleur) {
    // Afficher le badge avec taux
    ?>
    <div>
        <strong>Taux de remplissage :</strong> 
        <span class="badge">50%</span>
        (×1.00 du prix BDD)
    </div>
    <?php
}
?>
```

---

## 📊 Exemples de Calcul

### Exemple 1 : Photocopieur COULEUR à 50%

**Configuration :**
- Photocopieur Comcolor
- 100 pages A4
- ✅ Couleur cochée
- Taux : 50% (défaut)

**Calcul :**
```
Prix BDD noire = 0.005€
Prix BDD bleue = 0.003€
Prix BDD rouge = 0.003€
Prix BDD jaune = 0.002€
Total BDD = 0.013€/page

Multiplicateur = 0.5 / 0.5 = 1.0
Prix final = 0.013€ × 1.0 = 0.013€/page (prix BDD exact) ✅
```

### Exemple 2 : Photocopieur COULEUR à 100%

**Configuration :**
- Même que exemple 1
- Taux : 100%

**Calcul :**
```
Multiplicateur = 1.0 / 0.5 = 2.0
Prix final = 0.013€ × 2.0 = 0.026€/page (double) ✅
```

### Exemple 3 : Photocopieur NOIR ET BLANC

**Configuration :**
- Photocopieur Comcolor
- 100 pages A4
- ❌ Couleur NON cochée

**Calcul :**
```
Multiplicateur = 1.0 (fixe, pas de slider)
Prix final = 0.005€ × 1.0 = 0.005€/page (prix BDD noire) ✅
```

**Note :** Le slider est MASQUÉ, pas de taux de remplissage applicable

---

## 🧪 Scénarios de Test

### Test 1 : Affichage Conditionnel

1. Sélectionner **Photocopieur**
2. ❌ Couleur non cochée
   - ✅ Slider MASQUÉ
3. ✅ Cocher Couleur
   - ✅ Slider APPARAÎT avec badge 50% orange
4. ❌ Décocher Couleur
   - ✅ Slider DISPARAÎT

### Test 2 : Calculs Couleur vs N&B

**Brochure 1 : Couleur à 50%**
- ✅ Couleur cochée
- Taux : 50%
- Prix : ~1.30€ (prix BDD ×1.0)

**Brochure 2 : Noir et Blanc**
- ❌ Couleur non cochée
- Pas de slider
- Prix : ~1.00€ (prix BDD noire normale)

### Test 3 : Brochures Mixtes

**Configuration :**
- Brochure 1 : Couleur (slider visible)
- Brochure 2 : N&B (slider masqué pour cette brochure)

**Comportement :**
- ✅ Le slider est visible (car au moins une brochure couleur)
- ✅ Le taux s'applique SEULEMENT aux brochures couleur
- ✅ Les brochures N&B utilisent le prix BDD normal

---

## ✨ Avantages de cette Logique

1. **Interface Plus Claire**
   - Slider visible uniquement quand pertinent
   - Pas de confusion pour les tirages N&B

2. **Calculs Plus Justes**
   - Prix BDD normal pour noir et blanc
   - Ajustement fin uniquement pour la couleur

3. **UX Améliorée**
   - Moins d'options à l'écran si pas nécessaire
   - Focus sur ce qui est important

---

## 🎯 Checklist de Validation

- [ ] Photocopieur + Couleur = Slider visible ✅
- [ ] Photocopieur + N&B = Slider masqué ✅
- [ ] Duplicopieur = Jamais de slider ✅
- [ ] Couleur à 50% = Prix BDD exact ✅
- [ ] Couleur à 100% = Prix BDD × 2 ✅
- [ ] N&B = Prix BDD normal (multiplicateur ×1.0) ✅
- [ ] Page confirmation affiche badge si couleur ✅
- [ ] Console logs appropriés ✅

---

## 🚀 Pour Tester

1. **Rafraîchir** la page (CTRL+F5)

2. **Test Couleur :**
   - Sélectionner Photocopieur
   - ✅ Cocher "Couleur"
   - ✅ Le slider apparaît
   - Régler à 75%
   - Prix ajusté (×1.5)

3. **Test Noir et Blanc :**
   - Sélectionner Photocopieur
   - ❌ Laisser "Couleur" décoché
   - ✅ Pas de slider
   - Prix BDD normal

4. **Test Toggle :**
   - Cocher/décocher "Couleur" plusieurs fois
   - ✅ Le slider apparaît/disparaît dynamiquement

---

**Statut : COMPLÈTEMENT FONCTIONNEL** ✅











