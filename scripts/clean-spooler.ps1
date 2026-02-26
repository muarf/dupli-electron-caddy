# clean-spooler.ps1
# Supprime tous les fichiers du dossier spool des imprimantes Windows
# Exécuté via tâche planifiée : chaque jour à minuit + au réveil de l'ordi

$spoolPath = "$env:SystemRoot\System32\spool\PRINTERS"
$logFile = "$env:TEMP\clean-spooler.log"

$timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"

try {
    # Arrêter le service spooler pour pouvoir supprimer les fichiers
    Stop-Service -Name Spooler -Force -ErrorAction Stop
    Start-Sleep -Seconds 2

    $files = Get-ChildItem -Path $spoolPath -File -ErrorAction SilentlyContinue
    $count = ($files | Measure-Object).Count

    if ($count -gt 0) {
        Remove-Item -Path "$spoolPath\*" -Force -ErrorAction SilentlyContinue
        Add-Content -Path $logFile -Value "[$timestamp] Supprimé $count fichier(s) du spool."
    }
    else {
        Add-Content -Path $logFile -Value "[$timestamp] Spool déjà vide, rien à supprimer."
    }
}
catch {
    Add-Content -Path $logFile -Value "[$timestamp] ERREUR: $($_.Exception.Message)"
}
finally {
    # Redémarrer le service spooler dans tous les cas
    Start-Service -Name Spooler -ErrorAction SilentlyContinue
}
