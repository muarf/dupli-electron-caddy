# 🚀 Guide de publication sur GitHub et merge avec dupli-electron-caddy

## Étape 1 : Créer le repository sur GitHub

1. Allez sur https://github.com/new
2. Remplissez les informations :
   - **Repository name** : `dupli-php-dev`
   - **Description** : Application PHP de gestion d'impression - Version 0.3a
   - **Visibilité** : Public ou Private (selon votre choix)
   - ⚠️ **NE PAS** cocher "Add a README" (on en a déjà un)
   - ⚠️ **NE PAS** ajouter de .gitignore (on en a déjà un)
   - ⚠️ **NE PAS** choisir de licence pour l'instant

## Étape 2 : Pousser le code vers GitHub

```bash
cd /home/ubuntu/dupli-php-dev

# Ajouter le remote GitHub (remplacez VOTRE_USERNAME par votre nom d'utilisateur GitHub)
git remote add origin https://github.com/VOTRE_USERNAME/dupli-php-dev.git

# Renommer la branche en 'main' (convention moderne)
git branch -M main

# Pousser le code
git push -u origin main
```

## Étape 3 : Merger avec dupli-electron-caddy (si souhaité)

### Option A : Ajouter dupli-php-dev comme sous-répertoire

```bash
cd /chemin/vers/dupli-electron-caddy

# Ajouter dupli-php-dev comme remote
git remote add php-dev https://github.com/VOTRE_USERNAME/dupli-php-dev.git

# Récupérer les changements
git fetch php-dev

# Merger en permettant les historiques non liés
git merge php-dev/main --allow-unrelated-histories -m "Merge dupli-php-dev into dupli-electron-caddy"

# Ou si vous voulez le mettre dans un sous-dossier
git read-tree --prefix=php-backend/ -u php-dev/main
```

### Option B : Utiliser Git Subtree (recommandé)

```bash
cd /chemin/vers/dupli-electron-caddy

# Ajouter dupli-php-dev comme subtree dans un sous-dossier
git subtree add --prefix=php-backend https://github.com/VOTRE_USERNAME/dupli-php-dev.git main --squash

# Plus tard, pour mettre à jour depuis dupli-php-dev :
git subtree pull --prefix=php-backend https://github.com/VOTRE_USERNAME/dupli-php-dev.git main --squash

# Pour pousser des changements vers dupli-php-dev :
git subtree push --prefix=php-backend https://github.com/VOTRE_USERNAME/dupli-php-dev.git main
```

### Option C : Utiliser Git Submodule (alternative)

```bash
cd /chemin/vers/dupli-electron-caddy

# Ajouter dupli-php-dev comme submodule
git submodule add https://github.com/VOTRE_USERNAME/dupli-php-dev.git php-backend

# Commit le submodule
git commit -m "Add dupli-php-dev as submodule"

# Pour cloner le projet avec ses submodules :
git clone --recursive https://github.com/VOTRE_USERNAME/dupli-electron-caddy.git
```

## Étape 4 : Configuration des remotes (pour un développement parallèle)

Si vous voulez travailler sur les deux repositories en même temps :

```bash
cd /home/ubuntu/dupli-php-dev

# Lister les remotes
git remote -v

# Ajouter dupli-electron-caddy comme remote secondaire
git remote add electron https://github.com/VOTRE_USERNAME/dupli-electron-caddy.git

# Maintenant vous pouvez :
# - Pousser vers dupli-php-dev : git push origin main
# - Pousser vers dupli-electron-caddy : git push electron main
```

## 📊 Comparaison des méthodes

| Méthode | Avantages | Inconvénients |
|---------|-----------|---------------|
| **Merge direct** | Simple, historique complet | Peut créer des conflits |
| **Subtree** | Intégration propre, facile à merger | Plus complexe à configurer |
| **Submodule** | Repositories séparés, versions fixes | Nécessite `--recursive` pour cloner |

## 🎯 Recommandation

**Pour votre cas**, je recommande **Git Subtree** car :
- ✅ Vous pouvez développer les deux projets séparément
- ✅ Vous pouvez merger facilement quand vous voulez
- ✅ L'historique reste propre
- ✅ Pas besoin de `--recursive` pour cloner

## 🔄 Workflow de développement recommandé

```bash
# 1. Travailler sur dupli-php-dev
cd /home/ubuntu/dupli-php-dev
git add .
git commit -m "✨ Nouvelle fonctionnalité"
git push origin main

# 2. Mettre à jour dupli-electron-caddy
cd /chemin/vers/dupli-electron-caddy
git subtree pull --prefix=php-backend https://github.com/VOTRE_USERNAME/dupli-php-dev.git main --squash
git push origin main
```

## 📝 Notes importantes

- Les fichiers sensibles sont déjà exclus via `.gitignore`
- La base SQLite n'est pas versionnée (normal)
- Les sessions et fichiers temporaires sont ignorés
- Pensez à mettre à jour les URLs dans le README.md après la création du repo

---

**Besoin d'aide ?** Consultez la documentation Git : https://git-scm.com/docs
