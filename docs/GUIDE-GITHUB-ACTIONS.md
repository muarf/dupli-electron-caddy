# 🚀 Guide : Tester Windows avec GitHub Actions

## Pourquoi GitHub Actions ?

- ✅ **Windows RÉEL** (pas Wine, pas émulation)
- ✅ **GRATUIT** (2000 minutes/mois)
- ✅ **AUTOMATIQUE** (build + test)
- ✅ **LOGS DÉTAILLÉS** de tout ce qui se passe
- ✅ **ARTIFACTS** : télécharger le build Windows

## 📋 Étapes complètes

### Étape 1 : Créer un repo GitHub

```bash
# 1. Créer le repo sur GitHub.com
#    - Aller sur https://github.com/new
#    - Nom : dupli-electron-caddy
#    - Public ou Private (au choix)
#    - NE PAS initialiser avec README

# 2. Configurer Git localement
cd /home/ubuntu/dupli-electron-caddy

# Si pas déjà fait
git init
git branch -M main

# Ajouter vos fichiers
git add .
git commit -m "Initial commit: Electron + Caddy + PHP cross-platform"

# Lier au repo GitHub (remplacer VOTRE-USERNAME)
git remote add origin https://github.com/VOTRE-USERNAME/dupli-electron-caddy.git

# Pousser le code
git push -u origin main
```

### Étape 2 : Activer GitHub Actions

1. Sur GitHub.com, aller sur votre repo
2. Cliquer sur l'onglet **"Actions"**
3. GitHub détectera les workflows automatiquement
4. Cliquer **"I understand my workflows, go ahead and enable them"**

### Étape 3 : Lancer le test Windows

**Option A : Automatique (au prochain push)**
```bash
# N'importe quel push déclenchera le workflow
git commit --allow-empty -m "Trigger Windows test"
git push
```

**Option B : Manuel (immédiat)**
1. Onglet **"Actions"**
2. Dans la liste de gauche, cliquer **"Test Windows Build"**
3. Cliquer le bouton **"Run workflow"** (à droite)
4. Sélectionner la branche **"main"**
5. Cliquer **"Run workflow"** (bouton vert)

### Étape 4 : Suivre l'exécution

1. Le workflow apparaît dans la liste (statut jaune 🟡)
2. Cliquer dessus pour voir les détails
3. Cliquer sur le job **"build-and-test-windows"**
4. Voir chaque étape en temps réel :
   - ✅ Download Caddy
   - ✅ Copy PHP binaries
   - ✅ Build Windows app
   - ✅ Verify build output
   - ✅ Test Caddy binary
   - ✅ Test PHP binary
   - ✅ Test application launch
   - ✅ Upload artifacts

### Étape 5 : Voir les résultats

**Logs :**
- Chaque étape affiche ses logs
- Chercher les ✅ et ❌
- Les erreurs sont en rouge

**Exemple de ce que vous verrez :**
```
✅ Duplicator.exe existe
   Taille: 168.5 MB
✅ caddy.exe existe
✅ php.exe existe
✅ Caddyfile existe
=== Test de Caddy ===
v2.7.6 h1:w0NymbG2m9PcvKWsrXO6EEkY9Ru4FJK8uQbYcev1p3A=
=== Test de PHP ===
PHP 8.3.6 (cli) (built: Apr 9 2024 19:34:55) (NTS Visual C++ 2019 x64)
=== Test de démarrage de l'application ===
Application démarrée avec PID: 1234
✅ Processus PHP détecté: php
✅ Processus Caddy détecté: caddy
✅ Application répond sur le port 8000
   Status: 200
```

### Étape 6 : Télécharger le build

1. En bas de la page du workflow
2. Section **"Artifacts"**
3. Cliquer sur **"duplicator-windows-build"**
4. Le ZIP se télécharge
5. Vous avez maintenant le build Windows testé !

## 🎯 Ce qui est testé

- ✅ Build de l'application
- ✅ Présence de tous les binaires
- ✅ Version de Caddy
- ✅ Version de PHP
- ✅ Validation de la configuration Caddy
- ✅ Lancement de l'application
- ✅ Processus Caddy et PHP démarrent
- ✅ Port 8000 répond

## ⏱️ Temps estimé

- **Setup GitHub** : 5 minutes
- **Exécution du workflow** : 5-10 minutes
- **Total** : ~15 minutes

## 💰 Coût

- **GRATUIT** (2000 minutes/mois GitHub Free)
- Ce workflow utilise ~7-8 minutes par run
- Donc ~250 tests gratuits/mois

## 🆘 Dépannage

### Le workflow ne se lance pas
- Vérifier que les fichiers sont dans `.github/workflows/`
- Vérifier que GitHub Actions est activé
- Vérifier les permissions du repo

### Le build échoue
- Voir les logs de l'étape qui échoue
- Les erreurs sont affichées en détail
- Corriger et re-pusher

### Les artifacts ne sont pas disponibles
- Vérifier que le workflow a complété
- Les artifacts sont conservés 7 jours
- Télécharger avant expiration

## 📚 Documentation

- GitHub Actions : https://docs.github.com/en/actions
- Workflows syntax : https://docs.github.com/en/actions/reference/workflow-syntax-for-github-actions
- Electron Builder : https://www.electron.build/

## 🎉 Résultat

À la fin, vous aurez :
- ✅ Un build Windows testé sur Windows réel
- ✅ Des logs détaillés du test
- ✅ Un artifact téléchargeable
- ✅ La confirmation que tout fonctionne

**Sans avoir installé Windows sur votre machine !**
