param(
    [string]$FilePath,
    [string]$PrinterName = "RISO ComColor FW5230"
)

Write-Host "Lancement impression directe..."
Write-Host "Fichier: $FilePath"
Write-Host "Imprimante: $PrinterName"
Write-Host ""

if (-not (Test-Path $FilePath)) {
    Write-Host "ERREUR: Fichier non trouve: $FilePath"
    exit 1
}

# Methode 1: Utiliser Shell.Application
Write-Host "Methode 1: Shell.Application..."
try {
    $shell = New-Object -ComObject Shell.Application
    $shell.ShellExecute($FilePath, "", "", "print", 1)
    Write-Host "OK: Shell.Application execute"
    Start-Sleep -Seconds 2
} catch {
    Write-Host "ERREUR Shell.Application: $($_.Exception.Message)"
}

# Methode 2: Utiliser Start-Process avec l'application par defaut
Write-Host "Methode 2: Start-Process avec application par defaut..."
try {
    $proc = Start-Process -FilePath $FilePath -Verb Print -PassThru -ErrorAction SilentlyContinue
    if ($proc) {
        Write-Host "OK: Processus lance (PID: $($proc.Id))"
        Start-Sleep -Seconds 2
    } else {
        Write-Host "ATTENTION: Processus non lance"
    }
} catch {
    Write-Host "ERREUR Start-Process: $($_.Exception.Message)"
}

# Methode 3: Utiliser l'association de fichier directement
Write-Host "Methode 3: Association de fichier..."
try {
    $psi = New-Object System.Diagnostics.ProcessStartInfo
    $psi.FileName = $FilePath
    $psi.Verb = "print"
    $psi.UseShellExecute = $true
    $psi.WindowStyle = [System.Diagnostics.ProcessWindowStyle]::Hidden
    $proc = [System.Diagnostics.Process]::Start($psi)
    if ($proc) {
        Write-Host "OK: Processus lance via association (PID: $($proc.Id))"
        Start-Sleep -Seconds 2
    }
} catch {
    Write-Host "ERREUR association: $($_.Exception.Message)"
}

Write-Host ""
Write-Host "Toutes les methodes ont ete essayees"
Write-Host "Verifiez la file d'impression Windows"
