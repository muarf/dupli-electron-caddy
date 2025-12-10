# Install-DupliPrintProcessor.ps1
# PowerShell script to install the Dupli Print Processor

param (
    [switch]$Uninstall
)

$ErrorActionPreference = "Stop"

# Check for admin privileges
if (-NOT ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator")) {
    Write-Error "This script requires Administrator privileges. Run as Administrator."
    exit 1
}

$ProcessorName = "DupliPrintProcessor"
$DllName = "DupliPrintProcessor.dll"
$SourcePath = Join-Path $PSScriptRoot "build\Release\$DllName"
$DestPath = "$env:SystemRoot\System32\spool\prtprocs\x64\$DllName"

if ($Uninstall) {
    Write-Host "Uninstalling $ProcessorName..."
    
    # Remove registry key
    $regPath = "HKLM:\SYSTEM\CurrentControlSet\Control\Print\Environments\Windows x64\Print Processors\$ProcessorName"
    if (Test-Path $regPath) {
        Remove-Item -Path $regPath -Recurse -Force
        Write-Host "Registry entry removed."
    }
    
    # Remove DLL (may need spooler restart)
    Stop-Service Spooler -Force
    Start-Sleep -Seconds 2
    
    if (Test-Path $DestPath) {
        Remove-Item -Path $DestPath -Force
        Write-Host "DLL removed."
    }
    
    Start-Service Spooler
    Write-Host "$ProcessorName uninstalled successfully."
    exit 0
}

# Install
Write-Host "Installing $ProcessorName..."

# Check if DLL exists
if (-NOT (Test-Path $SourcePath)) {
    Write-Error "DLL not found at $SourcePath. Build the project first."
    exit 1
}

# Stop spooler service
Write-Host "Stopping Print Spooler..."
Stop-Service Spooler -Force
Start-Sleep -Seconds 2

# Copy DLL to system directory
Write-Host "Copying DLL to $DestPath..."
Copy-Item -Path $SourcePath -Destination $DestPath -Force

# Create registry entry
Write-Host "Creating registry entry..."
$regPath = "HKLM:\SYSTEM\CurrentControlSet\Control\Print\Environments\Windows x64\Print Processors\$ProcessorName"
if (-NOT (Test-Path $regPath)) {
    New-Item -Path $regPath -Force | Out-Null
}
Set-ItemProperty -Path $regPath -Name "Driver" -Value $DllName -Type String

# Create data directory
$dataPath = "C:\ProgramData\Dupli\PrintProcessor"
if (-NOT (Test-Path $dataPath)) {
    New-Item -Path $dataPath -ItemType Directory -Force | Out-Null
}

# Start spooler service
Write-Host "Starting Print Spooler..."
Start-Service Spooler
Start-Sleep -Seconds 2

Write-Host "$ProcessorName installed successfully!"
Write-Host ""
Write-Host "To use this print processor with a printer:"
Write-Host "1. Open Printer Properties"
Write-Host "2. Go to Advanced tab"
Write-Host "3. Change Print Processor from 'winprint' to '$ProcessorName'"
