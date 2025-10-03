# Extract VCRuntime DLLs from Windows Redistributable
param(
    [string]$RedistPath = "vc_redist.x64.exe",
    [string]$OutputDir = "php"
)

if (-not (Test-Path $RedistPath)) {
    Write-Error "Redistributable not found: $RedistPath"
    exit 1
}

if (-not (Test-Path $OutputDir)) {
    New-Item -ItemType Directory -Path $OutputDir -Force
}

Write-Host "Extracting VCRuntime DLLs..."

# Download if needed
if (-not (Test-Path $RedistPath)) {
    Write-Host "Downloading Visual C++ Redistributable..."
    Invoke-WebRequest -Uri "https://aka.ms/vs/17/release/vc_redist.x64.exe" -OutFile $RedistPath
}

# Extract using Windows built-in methods
$extractArgs = "/x:`"$OutputDir`" /q"
Start-Process -FilePath $RedistPath -ArgumentList $extractArgs -Wait

Write-Host "Looking for extracted DLLs..."

# Find and copy the required DLLs
$dllFiles = @("vcruntime140.dll", "msvcp140.dll", "ucrtbase.dll")

foreach ($dll in $dllFiles) {
    # Look in common extraction locations
    $searchPaths = @(
        "$OutputDir\x64\Microsoft.VC143.CRT\*",
        "$OutputDir\x64\microsoft.vc143.crt\*",
        "$OutputDir\VC_redist.x64\*",
        "$OutputDir\c_\windows\system32\*"
    )
    
    foreach ($path in $searchPaths) {
        $foundFiles = Get-ChildItem -Path $path -Filter $dll -Recurse -ErrorAction SilentlyContinue
        if ($foundFiles) {
            foreach ($file in $foundFiles) {
                $destination = Join-Path $OutputDir $dll
                Copy-Item $file.FullName $destination -Force
                Write-Host "✅ Copied $dll from $($file.FullName)"
            }
        }
    }
}

Write-Host "Verification:"
Get-ChildItem $OutputDir -Filter "*.dll" | ForEach-Object {
    Write-Host "  ✅ $($_.Name) ($($_.Length) bytes)"
}
