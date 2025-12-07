param(
    [string]$FilePath = "test-print.txt",
    [string]$PrinterName = "RISO ComColor FW5230"
)

Write-Host "Lancement impression via commande PRINT..."
Write-Host "Fichier: $FilePath"
Write-Host "Imprimante: $PrinterName"
Write-Host ""

if (-not (Test-Path $FilePath)) {
    Write-Host "ERREUR: Fichier non trouve: $FilePath"
    exit 1
}

# Methode 1: Utiliser la commande PRINT de Windows
Write-Host "Methode 1: Commande PRINT..."
try {
    $fullPath = (Resolve-Path $FilePath).Path
    $printCmd = "print /D:`"$PrinterName`" `"$fullPath`""
    Write-Host "Commande: $printCmd"
    
    $result = cmd /c $printCmd 2>&1
    if ($LASTEXITCODE -eq 0) {
        Write-Host "OK: Commande PRINT executee avec succes"
    } else {
        Write-Host "ATTENTION: Code de retour: $LASTEXITCODE"
        Write-Host "Sortie: $result"
    }
} catch {
    Write-Host "ERREUR: $($_.Exception.Message)"
}

# Methode 2: Utiliser Out-Printer (si disponible)
Write-Host ""
Write-Host "Methode 2: Out-Printer..."
try {
    Get-Content $FilePath | Out-Printer -Name $PrinterName -ErrorAction SilentlyContinue
    if ($?) {
        Write-Host "OK: Out-Printer execute"
    } else {
        Write-Host "ATTENTION: Out-Printer a peut-etre echoue"
    }
} catch {
    Write-Host "ERREUR: $($_.Exception.Message)"
}

Write-Host ""
Write-Host "Verifiez la file d'impression Windows"

