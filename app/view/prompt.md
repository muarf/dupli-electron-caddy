### Systèmes critiques
- **Pricing** : Calculs automatiques basés sur consommation historique
- **Stats** : Agrégation par mois avec pagination
- **Multi-machines** : Support de plusieurs duplicopieurs simultanés
- **Consommables** : Tracking tambours colorés, masters, encre

## ⚠️ Problèmes Types à Résoudre

1. **Incohérences de noms** : 
   - Machines nommées "Duplicopieur" mais données sous "dupli"
   - Clés de prix `dupli_1` vs noms d'affichage

2. **Calculs de statistiques** :
   - `rv` contient 'oui'/'non' pas le nb de feuilles
   - Utiliser `sum(passage_ap - passage_av)` pour les feuilles

3. **Compatibility** :
   - Ancien système (1 duplicopieur) vs nouveau (multi-duplicopieurs)
   - Fallbacks nécessaires pour les données historiques

4. **Prévisions de changement** :
   - Dépendent des données dans table `cons`
   - Mapping machine names critique pour retrouver les bonnes données

## 🛠️ Instructions de Travail

### Toujours faire avant de débuter
1. **Comprendre le flux de données** : Table → Fonction → Vue
2. **Vérifier les noms de machines** dans toutes les tables concernées
3. **Tester avec les deux types** (duplicopieurs ET photocopieurs)
4. **Préserver la compatibilité** avec l'existant

### Approche de debug recommandée
1. Tracer les **appels de fonctions** critiques (`get_cons`, `get_price`, `stats_by_machine`)
2. Vérifier les **clés de tableaux** utilisées (`dupli_1` vs `DUPLICOPIEUR`)
3. Contrôler les **requêtes SQL** et leurs résultats
4. Tester les **noms de machines** dans chaque table

### Priorités de correction
1. **Données manquantes** dans `cons` → Ajouter les changements nécessaires
2. **Mapping incorrect** → Corriger les conditions de noms
3. **Calculs erronés** → Vérifier les formules (passages, masters)
4. **Affichage** → Adapter les templates si nécessaire

## 💡 Bonnes Pratiques

- **Toujours préserver** les données existantes qui fonctionnent
- **Utiliser les fallbacks** pour la rétrocompatibilité  
- **Tester en parallèle** duplicopieurs ET photocopieurs
- **Commiter atomiquement** chaque fix spécifique
- **Documenter** les changements dans les messages de commit

## 🚨 Attention Particulière

- **Ne jamais casser** les photocopieurs qui fonctionnent
- **SQLite vs MySQL** : Syntaxe différente (`||` vs `CONCAT`)
- **Noms de machines sensibles** : "dupli" ≠ "Duplicopieur" ≠ "duplicopieur"
- **Structures de données** : Ancienne (master/encre) vs Nouvelle (tambours multiples)

Commence toujours par comprendre le problème exact avant de proposer une solution !