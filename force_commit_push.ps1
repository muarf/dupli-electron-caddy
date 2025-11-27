cd "C:\Users\Dupli\AppData\Local\Programs\dupli-electron-caddy"

Write-Host "=== Vérification des modifications ==="
if (Select-String -Path "app\view\admin.html.php" -Pattern "admin&imprimantes" -Quiet) {
    Write-Host "✓ Lien imprimantes présent"
} else {
    Write-Host "✗ Lien imprimantes MANQUANT"
    exit 1
}

if (Select-String -Path "app\models\admin.php" -Pattern "handleImprimantesSection" -Quiet) {
    Write-Host "✓ Fonction handleImprimantesSection présente"
} else {
    Write-Host "✗ Fonction MANQUANTE"
    exit 1
}

Write-Host "`n=== Ajout des fichiers ==="
git add .github/workflows/test-windows.yml
git add app/models/admin.php
git add app/view/admin.html.php
git add main-caddy.js
git add preload.js
git add utils/printer-monitor.js

Write-Host "`n=== Commit ==="
git commit -m "fix: Ajout menu et routage admin imprimantes + modifications Electron"

if ($LASTEXITCODE -ne 0) {
    Write-Host "Erreur lors du commit"
    exit 1
}

Write-Host "`n=== Push ==="
git push origin feature/printer-monitor-integration

if ($LASTEXITCODE -eq 0) {
    Write-Host "✓ Push réussi !"
} else {
    Write-Host "✗ Erreur lors du push"
    exit 1
}

Write-Host "`n=== Vérification finale ==="
git status

