# État des Tests CI - Dupli Electron Caddy

## Résumé

### Structure actuelle

```
.github/workflows/
├── release-beta.yml    # Tests Linux + Build Linux + Build Windows
├── windows-test.yml    # Tests Windows uniquement (temporaire)
└── (test-linux.yml supprimé)
```

## Ce qui fonctionne

### release-beta.yml (Linux)
- ✅ PHP Tests (Pest)
- ✅ JS Tests (Jest)
- ✅ Build Linux AppImage
- ✅ Build Windows

### windows-test.yml (Windows)
- ❌ PHP Tests - échoue avec `no such table: photocopieurs`
- ❓ JS Tests - pas encoretesté

## Problèmes à résoudre

### 1. Tests Windows - Table photocopieurs manquante

**Erreur:**
```
SQLSTATE[HY000]: General error: 1 no such table: photocopieurs
```

**Cause:**
Les tests chargeant `tirage_multimachines.php` n'initialisent pas `$conf` global avec une DB SQLite.

**Solution appliquée:**
Corriger `app/tests/Unit/TiragePricingTest.php` pour utiliser:
```php
[$dbPath, $pdo] = create_test_sqlite_database();
configure_sqlite_conf($dbPath);
```

**État:** Modification faite, testée localement (OK). En attente de validation CI Windows.

### 2. Tests Windows incomplets

D'autres tests ont probablement le même problème. À corriger un par un.

## Prochaines étapes

1. **Tester la correction** de `TiragePricingTest.php` sur Windows CI
2. **Identifier** les autres tests qui échouent
3. **Corriger** chaque test qui utilise `$conf` sans l'initialiser
4. **Intégrer** les tests Windows dans `release-beta.yml`
5. **Supprimer** `windows-test.yml`

## Commandes útiles

```bash
# Lancer release-beta manuellement
gh workflow run release-beta.yml

# Voir les runs
gh run list

# Voir les logs d'un run
gh run view <run-id> --log-failed
```