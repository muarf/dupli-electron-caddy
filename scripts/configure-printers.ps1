# Enable KeepPrintedJobs for all printers
# This is required for the Duplicator Print Engine to have enough time to analyze SPL files
# after the spooling is complete.

$logFile = Join-Path $PSScriptRoot "..\logs\printer_config.log"
$timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
"[$timestamp] Script started" | Out-File -FilePath $logFile -Append

$printers = Get-Printer
foreach ($printer in $printers) {
    if ($printer.KeepPrintedJobs -eq $false) {
        "[$timestamp] Enabling KeepPrintedJobs for printer: $($printer.Name)" | Out-File -FilePath $logFile -Append
        try {
            Set-Printer -Name $printer.Name -KeepPrintedJobs $true -ErrorAction Stop
            "[$timestamp] Success." | Out-File -FilePath $logFile -Append
        } catch {
            "[$timestamp] Failed to update printer $($printer.Name): $_" | Out-File -FilePath $logFile -Append
        }
    } else {
        "[$timestamp] Printer $($printer.Name) already has KeepPrintedJobs enabled." | Out-File -FilePath $logFile -Append
    }
}
"[$timestamp] Script finished" | Out-File -FilePath $logFile -Append
