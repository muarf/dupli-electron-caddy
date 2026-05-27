# Hook pour intercepter les paramètres d'impression au niveau Windows
# Ce script surveille les appels à l'API Print Windows et capture les paramètres

param(
    [Parameter(Mandatory=$true)]
    [string]$FilePath,
    [string]$PrinterName = $null,
    [string]$PaperSize = "A4",
    [switch]$Duplex,
    [switch]$Color,
    [int]$Copies = 1
)

# Convertir les paramètres en format normalisé
$colorMode = if ($Color) { "Color" } else { "Monochrome" }
$duplexMode = if ($Duplex) { "Duplex" } else { "Simplex" }

# Créer un fichier de cache temporaire pour stocker les paramètres
$cacheDir = "$env:APPDATA\dupli-electron"
if (-not (Test-Path $cacheDir)) {
    New-Item -ItemType Directory -Path $cacheDir -Force | Out-Null
}

$cacheFile = Join-Path $cacheDir "print-params-cache.json"

# Lire le cache existant
$cache = @{}
if (Test-Path $cacheFile) {
    try {
        $cacheContent = Get-Content $cacheFile -Raw -ErrorAction SilentlyContinue
        if ($cacheContent) {
            $cache = $cacheContent | ConvertFrom-Json -AsHashtable -ErrorAction SilentlyContinue
            if (-not $cache) {
                $cache = @{}
            }
        }
    } catch {
        $cache = @{}
    }
}

# Créer l'entrée de cache
$fileName = [System.IO.Path]::GetFileName($FilePath)
$baseName = [System.IO.Path]::GetFileNameWithoutExtension($FilePath)
$fullPath = [System.IO.Path]::GetFullPath($FilePath)

$cacheEntry = @{
    timestamp = (Get-Date).ToUniversalTime().ToString("o")
    fileName = $fileName
    baseName = $baseName
    fullPath = $fullPath
    options = @{
        pageSize = $PaperSize
        duplex = $duplexMode
        colorMode = $colorMode
        copies = $Copies
        printerName = $PrinterName
    }
}

# Stocker avec plusieurs clés
$keys = @($fileName, $baseName, $fullPath, $fileName.ToLower(), $baseName.ToLower(), $fullPath.ToLower())

foreach ($key in $keys) {
    $cache[$key] = $cacheEntry
}

# Nettoyer les entrées expirées (plus de 2 minutes)
$now = Get-Date
$keysToRemove = @()
foreach ($key in $cache.Keys) {
    $entry = $cache[$key]
    if ($entry -and $entry.timestamp) {
        try {
            $entryTime = [DateTime]::Parse($entry.timestamp)
            $age = ($now - $entryTime).TotalSeconds
            if ($age -gt 120) {
                $keysToRemove += $key
            }
        } catch {
            $keysToRemove += $key
        }
    }
}

foreach ($key in $keysToRemove) {
    $cache.Remove($key)
}

# Sauvegarder le cache (sans BOM UTF-8)
try {
    $jsonContent = $cache | ConvertTo-Json -Depth 10
    # Utiliser UTF8NoBOM pour éviter le BOM qui cause des erreurs de parsing JSON
    $utf8NoBom = New-Object System.Text.UTF8Encoding $false
    [System.IO.File]::WriteAllText($cacheFile, $jsonContent, $utf8NoBom)
    Write-Host "Parametres d'impression enregistres pour: $fileName"
} catch {
    Write-Host "Erreur sauvegarde cache: $($_.Exception.Message)"
}

