# Wrapper pour lancer une impression et enregistrer les paramètres
param(
    [Parameter(Mandatory=$true)]
    [string]$FilePath,
    
    [string]$PrinterName = $null,
    [string]$PaperSize = "A4",
    [bool]$Duplex = $false,
    [bool]$Color = $false,
    [int]$Copies = 1
)

# Enregistrer les paramètres AVANT de lancer l'impression
$hookScript = Join-Path $PSScriptRoot "utils\print-hook.ps1"
if (Test-Path $hookScript) {
    & $hookScript -FilePath $FilePath -PrinterName $PrinterName -PaperSize $PaperSize -Duplex:$Duplex -Color:$Color -Copies $Copies
}

# Lancer l'impression
try {
    $shell = New-Object -ComObject Shell.Application
    $shell.ShellExecute($FilePath, "", "", "print", 1)
    Write-Host "Impression lancee: $FilePath"
} catch {
    Write-Error "Erreur lors de l'impression: $($_.Exception.Message)"
    exit 1
}

