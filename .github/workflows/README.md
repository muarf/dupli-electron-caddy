# GitHub Actions Workflows

## Workflows disponibles

### 1. test-windows.yml
Test automatique de la version Windows sur une vraie machine Windows.

**Déclenchement :**
- Push sur main/master
- Pull request
- Manuellement depuis l'onglet Actions

**Actions :**
- Build de l'application Windows
- Test des binaires (Caddy, PHP)
- Validation de la configuration Caddy
- Lancement de l'application (15 secondes)
- Vérification des processus et du port 8000
- Upload des artifacts (build Windows)

### 2. test-linux.yml
Test automatique de la version Linux AppImage.

**Actions :**
- Build de l'AppImage
- Test des binaires
- Validation de la configuration
- Upload de l'AppImage

## Comment utiliser

### Lancer manuellement un test :
1. Aller sur GitHub → votre repo
2. Cliquer sur l'onglet "Actions"
3. Choisir le workflow (test-windows ou test-linux)
4. Cliquer sur "Run workflow"
5. Attendre ~5-10 minutes
6. Voir les résultats et télécharger les artifacts

### Voir les logs :
1. Onglet Actions
2. Cliquer sur le run
3. Cliquer sur le job
4. Voir chaque étape en détail

### Télécharger les builds :
1. Onglet Actions
2. Cliquer sur le run
3. Section "Artifacts" en bas
4. Télécharger le ZIP

## Artifacts générés

- `duplicator-windows-build` : Version Windows complète
- `duplicator-linux-appimage` : AppImage Linux
- `test-logs` : Logs d'erreur (si disponibles)

## Limites

- 2000 minutes gratuites/mois (GitHub Free)
- Retention des artifacts : 7 jours
- Windows et Linux testés sur machines virtuelles réelles
