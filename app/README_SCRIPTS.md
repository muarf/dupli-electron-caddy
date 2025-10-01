# 📋 Scripts de Test pour Dupli

Ce dossier contient plusieurs scripts PHP pour faciliter les tests de l'application Dupli.

## 🛠️ Scripts Disponibles

### 1. `reset_database.php`
**Réinitialise complètement la base de données**

```bash
php reset_database.php
```

**Fonction :**
- Supprime toutes les entrées des tables : `cons`, `photocop`, `a3`, `a4`, `prix`, `papier`
- Permet de repartir d'une base vide pour tester l'installation
- Affiche un rapport de suppression

**Utilisation :**
- Avant chaque test d'installation
- Quand vous voulez repartir de zéro

---

### 2. `check_database.php`
**Vérifie l'état actuel de la base de données**

```bash
php check_database.php
```

**Fonction :**
- Affiche le nombre d'entrées dans chaque table
- Indique si l'installation est nécessaire ou terminée
- Fournit les URLs d'accès appropriées

**Utilisation :**
- Pour vérifier l'état de la base
- Après une installation pour confirmer le succès

---

### 3. `test_installation.php`
**Simule une installation complète**

```bash
php test_installation.php
```

**Fonction :**
- Teste les fonctions de configuration des prix
- Teste l'initialisation de la table cons
- Utilise les valeurs par défaut du formulaire
- Affiche l'état final de la base

**Utilisation :**
- Pour tester les fonctions d'installation
- Pour vérifier que les prix et consommables sont bien créés

---

## 🔄 Workflow de Test Typique

1. **Réinitialiser la base :**
   ```bash
   php reset_database.php
   ```

2. **Vérifier l'état :**
   ```bash
   php check_database.php
   ```
   → Devrait indiquer "Installation nécessaire"

3. **Tester la page web :**
   - Aller sur : http://51.21.255.5/?setup
   - Remplir le formulaire d'installation
   - Soumettre le formulaire

4. **Vérifier le résultat :**
   ```bash
   php check_database.php
   ```
   → Devrait indiquer "Installation terminée"

5. **Tester la page admin :**
   - Aller sur : http://51.21.255.5/index.php?admin&prix
   - Mot de passe : `quinoa`

---

## 🎯 URLs Importantes

- **Installation :** http://51.21.255.5/?setup
- **Accueil :** http://51.21.255.5/?accueil
- **Admin Prix :** http://51.21.255.5/index.php?admin&prix

---

## ⚠️ Notes Importantes

- Les scripts utilisent les mêmes identifiants de base que l'application
- `reset_database.php` supprime **TOUTES** les données
- `test_installation.php` ne teste que les prix et consommables, pas l'insertion des machines
- Pour un test complet, utilisez toujours la page web d'installation

---

## 🐛 Dépannage

**Si la page d'installation redirige vers l'accueil :**
- Utilisez `reset_database.php` pour vider la base
- Vérifiez avec `check_database.php`

**Si les scripts ne fonctionnent pas :**
- Vérifiez que les fichiers `controler/conf.php` et `controler/func.php` existent
- Vérifiez les permissions des fichiers PHP
