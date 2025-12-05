param(
    [string]$FilePath = "test-print.txt"
)

Write-Host "Lancement impression fichier texte..."
Write-Host "Fichier: $FilePath"
Write-Host ""

if (-not (Test-Path $FilePath)) {
    Write-Host "ERREUR: Fichier non trouve: $FilePath"
    exit 1
}

# Utiliser Notepad pour imprimer
Write-Host "Methode: Notepad avec impression..."
try {
    # Ouvrir Notepad avec le fichier et lancer l'impression
    $notepad = Start-Process -FilePath "notepad.exe" -ArgumentList $FilePath -PassThru -ErrorAction SilentlyContinue
    
    if ($notepad) {
        Write-Host "OK: Notepad lance (PID: $($notepad.Id))"
        Write-Host "Attente 2 secondes..."
        Start-Sleep -Seconds 2
        
        # Envoyer Ctrl+P pour ouvrir la boite d'impression
        Add-Type -AssemblyName System.Windows.Forms
        [System.Windows.Forms.SendKeys]::SendWait("^p")
        Start-Sleep -Seconds 1
        
        # Appuyer sur Entree pour confirmer l'impression
        [System.Windows.Forms.SendKeys]::SendWait("{ENTER}")
        Start-Sleep -Seconds 1
        
        # Fermer Notepad
        $notepad.CloseMainWindow()
        Write-Host "OK: Impression lancee via Notepad"
    } else {
        Write-Host "ERREUR: Impossible de lancer Notepad"
    }
} catch {
    Write-Host "ERREUR: $($_.Exception.Message)"
}

Write-Host ""
Write-Host "Verifiez la file d'impression Windows"

