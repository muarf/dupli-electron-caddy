param(
    [string]$PrinterName,
    [string]$DocumentName = "Test Page",
    [string]$PaperSize = "A4",
    [string]$Duplex = "false",
    [string]$Color = "false",
    [int]$Pages = 1
)

Add-Type -AssemblyName System.Drawing

$doc = New-Object System.Drawing.Printing.PrintDocument
$doc.DocumentName = $DocumentName
$doc.PrinterSettings.PrinterName = $PrinterName

# Configurer le format papier
foreach ($size in $doc.PrinterSettings.PaperSizes) {
    if ($size.Kind -eq $PaperSize) {
        $doc.DefaultPageSettings.PaperSize = $size
        break
    }
}

# Convertir les paramètres string en booléens
$DuplexBool = [System.Convert]::ToBoolean($Duplex)
$ColorBool = [System.Convert]::ToBoolean($Color)

# Configurer le duplex
if ($DuplexBool) {
    $doc.PrinterSettings.Duplex = [System.Drawing.Printing.Duplex]::Vertical
}
else {
    $doc.PrinterSettings.Duplex = [System.Drawing.Printing.Duplex]::Simplex
}

# Configurer la couleur
$doc.DefaultPageSettings.Color = $ColorBool

# Gestionnaire d'impression pour générer les pages
$pageCount = 0
$doc.add_PrintPage({
        param($sender, $e)
    
        $font = New-Object System.Drawing.Font("Arial", 20)
        $brush = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::Black)
        $rect = $e.MarginBounds
    
        $e.Graphics.DrawString("Test Page $($sender.DocumentName)", $font, $brush, $rect.Left, $rect.Top)
        $e.Graphics.DrawString("Page $($script:pageCount + 1) of $Pages", $font, $brush, $rect.Left, $rect.Top + 50)
        $e.Graphics.DrawString("Format: $PaperSize", $font, $brush, $rect.Left, $rect.Top + 100)
        $e.Graphics.DrawString("Duplex: $Duplex", $font, $brush, $rect.Left, $rect.Top + 150)
        $e.Graphics.DrawString("Color: $Color", $font, $brush, $rect.Left, $rect.Top + 200)
    
        $script:pageCount++
    
        if ($script:pageCount -lt $Pages) {
            $e.HasMorePages = $true
        }
        else {
            $e.HasMorePages = $false
            $script:pageCount = 0
        }
    })

try {
    $doc.Print()
    Write-Host "Impression envoyée avec succès : $DocumentName sur $PrinterName"
}
catch {
    Write-Error "Erreur lors de l'impression : $_"
    exit 1
}
