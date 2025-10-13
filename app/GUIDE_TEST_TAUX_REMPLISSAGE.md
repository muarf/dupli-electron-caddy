# 🧪 Guide de Test - Taux de Remplissage

## 🚀 Démarrage Rapide

### Étape 1 : Rafraîchir la page
1. Ouvrir `?tirage_multimachines`
2. Appuyer sur **CTRL+F5** (vider le cache)
3. ✅ La page doit se charger sans erreur

### Étape 2 : Vérifier l'interface

#### Duplicopieurs
1. Sélectionner le type **"Duplicopieur"**
2. ✅ **Aucun slider de taux de remplissage** ne doit apparaître
3. ✅ Interface normale avec compteurs/manuel

#### Photocopieurs
1. Sélectionner le type **"Photocopieur"**
2. ✅ Un slider "Taux de remplissage couleur" doit apparaître
3. ✅ Badge affiché : **"50%"** en orange 🟠
4. ✅ Texte d'aide : "50% = Prix normal BDD"

## ✅ Test Complet : Photocopieur à 50%

### Configuration
- Type : **Photocopieur**
- Machine : **Comcolor**
- Taux de remplissage : **50%** (ne pas toucher au slider)
- Brochure :
  - Exemplaires : **1**
  - Feuilles : **100**
  - Taille : **A4**
  - ✅ **Couleur** coché
  - ❌ Recto/verso non coché

### Console JavaScript (F12)
Vous devriez voir :
```javascript
Fill rate récupéré pour photocopieur: 0.5 ( 50 %) - Multiplicateur: 1.00
prixEncre > 0 (pas 0 !)
```

### Prix Attendu
Si les prix BDD sont :
- Noire : 0.005€
- Bleue : 0.003€
- Rouge : 0.003€
- Jaune : 0.002€

**Calcul :**
```
Total encre BDD = 0.013€/page
Multiplicateur = 0.5 / 0.5 = 1.0
Prix encre ajusté = 0.013€ × 1.0 = 0.013€/page
Prix A4 = 0.013€ / 2 = 0.0065€/page
Total 100 pages = 0.65€ encre + 1.00€ papier = 1.65€
```

### Page de Confirmation
Cliquer sur **"Suivant"**

✅ Vous devriez voir :
```
Taux de remplissage : [50%] 🟠 (×1.00 du prix BDD)

• Papier : 100 pages × 0.010€ = 1.00€
• Encre/Toner : 100 pages × 0.0065€ = 0.65€
• Total : 1.65€
```

## ✅ Test : Photocopieur à 100%

### Configuration
- Même que le test précédent
- **Régler le slider à 100%**

### Résultat Attendu
- Badge : **"100%"** en vert 🟢
- **Prix doit être le DOUBLE** de celui à 50%

**Calcul :**
```
Multiplicateur = 1.0 / 0.5 = 2.0
Prix encre ajusté = 0.013€ × 2.0 = 0.026€/page
Prix A4 = 0.026€ / 2 = 0.013€/page
Total 100 pages = 1.30€ encre + 1.00€ papier = 2.30€
```

✅ **2.30€ = 1.65€ × 1.4** (environ le double car le papier n'est pas affecté)

### Page de Confirmation
```
Taux de remplissage : [100%] 🟢 (×2.00 du prix BDD)

• Papier : 100 pages × 0.010€ = 1.00€
• Encre/Toner : 100 pages × 0.013€ = 1.30€
  (Taux: 100%, ×2.00 du prix BDD)
• Total : 2.30€
```

## ✅ Test : Photocopieur à 25%

### Configuration
- Même que le test précédent
- **Régler le slider à 25%**

### Résultat Attendu
- Badge : **"25%"** en rouge 🔴
- **Prix doit être la MOITIÉ** de celui à 50%

**Calcul :**
```
Multiplicateur = 0.25 / 0.5 = 0.5
Prix encre ajusté = 0.013€ × 0.5 = 0.0065€/page
Prix A4 = 0.0065€ / 2 = 0.00325€/page
Total 100 pages = 0.325€ encre + 1.00€ papier = 1.325€
```

### Page de Confirmation
```
Taux de remplissage : [25%] 🔴 (×0.50 du prix BDD)

• Papier : 100 pages × 0.010€ = 1.00€
• Encre/Toner : 100 pages × 0.00325€ = 0.33€
  (Taux: 25%, ×0.50 du prix BDD)
• Total : 1.33€
```

## ✅ Test : Duplicopieur (pas de taux)

### Configuration
- Type : **Duplicopieur**
- Mode : **Compteurs**
- Avant : 0 masters, 0 passages
- Après : 10 masters, 1000 passages
- ❌ A4 non coché (A3)

### Résultat Attendu
- ❌ **Aucun slider** de taux de remplissage
- ✅ Prix calculé normalement

**Calcul (exemple avec dupli_18) :**
```
Prix master = 0.4€
Prix passage (tambour_noir) = 0.008€ (exemple)

Total = (10 × 0.4€) + (1000 × 0.008€) + (1000 × 0.02€)
Total = 4€ + 8€ + 20€ = 32€
```

### Page de Confirmation
```
Détail des coûts

• Masters : 10 × 0.4000€ = 4.00€
• Passages : 1000 × 0.0080€ = 8.00€
• Papier : 1000 feuilles × 0.020€ = 20.00€
```

❌ **Pas de badge de taux de remplissage**

## 📊 Tableau Récapitulatif

| Taux | Multiplicateur | Prix Encre (base 0.013€) | Prix 100 pages A4 |
|------|----------------|--------------------------|-------------------|
| 0%   | ×0.0           | 0€                       | 1.00€ (papier)    |
| 25%  | ×0.5           | 0.33€                    | 1.33€             |
| **50%** | **×1.0**   | **0.65€**                | **1.65€** ✅      |
| 75%  | ×1.5           | 0.98€                    | 1.98€             |
| 100% | ×2.0           | 1.30€                    | 2.30€             |

## 🐛 Dépannage

### Problème : Le slider ne bouge pas
**Solution :** Vider le cache (CTRL+F5)

### Problème : Prix reste à 0€
**Vérification :**
1. Ouvrir la console (F12)
2. Taper : `console.log(prixData)`
3. Vous devez voir `photocop_1`, `dupli_18`, etc.
4. Si vous ne voyez que `{papier: {...}}`, la BDD n'est pas chargée

**Solution :** Les prix sont maintenant chargés grâce à la correction CONCAT() → ||

### Problème : Badge reste à 100%
**Cause :** Conflit d'IDs
**Solution :** Déjà corrigé, les IDs sont maintenant uniques par type

## ✨ Cas d'Usage Réels

### Cas 1 : Tract texte simple (25%)
- Documents principalement texte
- Peu de couleurs
- **Économie : 50% par rapport au prix BDD**

### Cas 2 : Brochure mixte (50%)
- Texte + quelques images
- **Prix BDD exact** (référence)

### Cas 3 : Magazine illustré (75%)
- Nombreuses photos
- Couleurs riches
- **Surcoût : +50% du prix BDD**

### Cas 4 : Affiche pleine couleur (100%)
- Photos pleine page
- Maximum de couleurs
- **Surcoût : +100% (double du prix BDD)**

## 📞 Support

En cas de problème, vérifier dans l'ordre :
1. ✅ Cache vidé (CTRL+F5)
2. ✅ Console JavaScript sans erreur
3. ✅ `prixData` chargé correctement
4. ✅ Champ "Nombre de feuilles" rempli (pas 0)

## 🎯 Checklist Finale

Avant de déployer en production :
- [ ] Test à 50% = prix BDD exact ✅
- [ ] Test à 100% = prix × 2 ✅
- [ ] Test à 25% = prix / 2 ✅
- [ ] Duplicopieur sans slider ✅
- [ ] Page de confirmation affiche le taux ✅
- [ ] Badge change de couleur ✅
- [ ] Enregistrement en BDD fonctionne ✅

**Tous les tests passent = PRÊT !** 🚀











