# Fix-And-Automate-DupliPrintProcessor.ps1
$ErrorActionPreference = "Stop"

function Write-Log {
    param($Message)
    Write-Host "[$(Get-Date -Format 'HH:mm:ss')] $Message" -ForegroundColor Cyan
}

# 1. Administrator Check
if (-NOT ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator")) {
    Write-Error "ERROR: Must run as Administrator."
    exit 1
}

$DllName = "DupliPrintProcessor.dll"
$SourcePath = Join-Path $PSScriptRoot "build\Release\$DllName"
$SystemProcsPath = "$env:SystemRoot\System32\spool\prtprocs\x64"
$DestPath = Join-Path $SystemProcsPath $DllName
$RegPath = "HKLM:\SYSTEM\CurrentControlSet\Control\Print\Environments\Windows x64\Print Processors\DupliPrintProcessor"

# 2. Stop Spooler
Write-Log "Stopping Print Spooler..."
Stop-Service Spooler -Force
Start-Sleep -Seconds 2

# 3. Install DLL
Write-Log "Installing DLL to $DestPath..."
if (-not (Test-Path $SourcePath)) {
    Write-Error "Source DLL not found at $SourcePath"
    exit 1
}
Copy-Item -Path $SourcePath -Destination $DestPath -Force

# 4. Configure Registry
Write-Log "Configuring Registry..."
if (-not (Test-Path $RegPath)) {
    New-Item -Path $RegPath -Force | Out-Null
}
Set-ItemProperty -Path $RegPath -Name "Driver" -Value $DllName -Type String

# 5. Create Data Directory
$DataPath = "C:\ProgramData\Dupli\PrintProcessor"
if (-not (Test-Path $DataPath)) {
    New-Item -Path $DataPath -ItemType Directory -Force | Out-Null
}

# 6. Start Spooler
Write-Log "Starting Print Spooler..."
Start-Service Spooler
Write-Log "Waiting for Spooler to initialize (10s)..."
Start-Sleep -Seconds 10

# 7. Verify System Recognizes It
Write-Log "Verifying Print Processor registration..."
$procs = @(Get-CimInstance Win32_PrintProcessor | Select-Object -ExpandProperty Name)
if ($procs -contains "DupliPrintProcessor") {
    Write-Log "SUCCESS: DupliPrintProcessor is recognized by Windows."
}
else {
    Write-Log "WARNING: DupliPrintProcessor not found in list. Registry might be cached. Attempting to proceed anyway."
    Write-Log "Current Processors: $($procs -join ', ')"
}

# 8. Automate Assignment to RISO
Write-Log "Looking for RISO printer..."
$riso = Get-Printer | Where-Object { $_.Name -like "*RISO*" -or $_.Name -like "*FW5230*" }

if ($riso) {
    Write-Log "Found Printer: $($riso.Name) (Current Processor: $($riso.PrintProcessor))"
    
    # Try multiple methods to set the processor
    try {
        Write-Log "Attempting to set Print Processor via Set-Printer..."
        Set-Printer -Name $riso.Name -PrintProcessor "DupliPrintProcessor"
        Write-Log "Set-Printer command executed."
    }
    catch {
        Write-Log "Set-Printer failed: $_"
        Write-Log "Attempting fallback via rundll32 printui..."
        $cmd = "rundll32 printui.dll,PrintUIEntry /Xs /n `"$($riso.Name)`" PrintProcessor `"DupliPrintProcessor`""
        Invoke-Expression $cmd
    }
    
    # Verification
    Start-Sleep -Seconds 2
    $risoUpdated = Get-Printer -Name $riso.Name
    if ($risoUpdated.PrintProcessor -eq "DupliPrintProcessor") {
        Write-Host "SUCCESS: Printer '$($riso.Name)' is now using DupliPrintProcessor." -ForegroundColor Green
    }
    else {
        Write-Host "ERROR: Failed to update printer configuration. Current: $($risoUpdated.PrintProcessor)" -ForegroundColor Red
    }
}
else {
    Write-Error "RISO printer not found."
}
