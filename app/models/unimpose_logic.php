<?php
/**
 * Script PHP pour désimposer un livret PDF
 * Traduit fidèlement du script Python unimpose.py
 * Version utilisant smalot/pdfparser pour manipuler directement les objets PDF
 */

require_once(__DIR__ . '/../vendor/autoload.php');

use Smalot\PdfParser\Parser;

class UnimposeBooklet {
    private $inputFile;
    private $outputFile;
    
    public function __construct($inputFile, $outputFile = 'output.pdf') {
        $this->inputFile = $inputFile;
        $this->outputFile = $outputFile;
    }
    
    /**
     * Transforme un livret en PDF page par page
     * Approche : utiliser les commandes PDF directement pour manipuler les CropBox
     */
    public function unimposeBooklet() {
        try {
            // Vérifier que le fichier d'entrée existe
            if (!file_exists($this->inputFile)) {
                throw new Exception("Le fichier d'entrée n'existe pas : " . $this->inputFile);
            }
            
            // Lire le contenu du PDF
            $pdfContent = file_get_contents($this->inputFile);
            
            // Parser le PDF pour obtenir les informations
            $parser = new Parser();
            $pdf = $parser->parseFile($this->inputFile);
            $pages = $pdf->getPages();
            $pageCount = count($pages);
            
            // Nombre de pages dans le PDF d'entrée : $pageCount
            
            if ($pageCount == 0) {
                throw new Exception("Le fichier PDF ne contient aucune page");
            }
            
            // Obtenir les dimensions de la première page
            $firstPage = $pages[0];
            $details = $firstPage->getDetails();
            
            // Détails de la première page : print_r($details, true)
            
            // Calculer le mapping selon le pattern de livret
            $totalHalfPages = $pageCount * 2;
            // Nombre total de demi-pages : $totalHalfPages
            
            $pageToIndex = array();
            
            for ($pdfPage = 0; $pdfPage < $pageCount; $pdfPage++) {
                $leftIndex = $pdfPage * 2;
                $rightIndex = $pdfPage * 2 + 1;
                
                if ($pdfPage % 2 == 0) {
                    $leftPageNum = $totalHalfPages - $pdfPage;
                    $rightPageNum = $pdfPage + 1;
                } else {
                    $leftPageNum = $pdfPage + 1;
                    $rightPageNum = $totalHalfPages - $pdfPage;
                }
                
                $pageToIndex[$leftPageNum] = $leftIndex;
                $pageToIndex[$rightPageNum] = $rightIndex;
                
                // PDF Page $pdfPage -> Page $leftPageNum (index $leftIndex), Page $rightPageNum (index $rightIndex)
            }
            
            // Maintenant, créer un nouveau PDF avec les pages découpées et réorganisées
            // Utiliser FPDI pour créer le PDF final
            $outputPdf = new setasign\Fpdi\Fpdi();
            $outputPdf->SetCreator('Unimpose PHP Script');
            $outputPdf->SetTitle('Livret désimposé');
            
            // Créer un fichier temporaire pour chaque demi-page
            $tempFiles = array();
            
            for ($i = 1; $i <= $pageCount; $i++) {
                // Extraire chaque page et la diviser en deux
                $tempPdfLeft = tempnam(sys_get_temp_dir(), 'pdf_left_');
                $tempPdfRight = tempnam(sys_get_temp_dir(), 'pdf_right_');
                
                // Créer les demi-pages avec FPDI
                $halfPdf = new setasign\Fpdi\Fpdi();
                $halfPdf->setSourceFile($this->inputFile);
                
                $templateId = $halfPdf->importPage($i);
                $size = $halfPdf->getTemplateSize($templateId);
                
                $w = $size['width'];
                $h = $size['height'];
                
                // Page de gauche
                $halfPdf->AddPage('P', array($w/2, $h));
                $halfPdf->useTemplate($templateId, 0, 0, $w, null, false);
                file_put_contents($tempPdfLeft, $halfPdf->Output('S'));
                $tempFiles[($i-1) * 2] = $tempPdfLeft;
                
                // Page de droite
                $halfPdf2 = new setasign\Fpdi\Fpdi();
                $halfPdf2->setSourceFile($this->inputFile);
                $templateId2 = $halfPdf2->importPage($i);
                $halfPdf2->AddPage('P', array($w/2, $h));
                $halfPdf2->useTemplate($templateId2, -$w/2, 0, $w, null, false);
                file_put_contents($tempPdfRight, $halfPdf2->Output('S'));
                $tempFiles[($i-1) * 2 + 1] = $tempPdfRight;
            }
            
            // Maintenant réorganiser les pages selon le mapping
            for ($pageNum = 1; $pageNum <= $totalHalfPages; $pageNum++) {
                if (isset($pageToIndex[$pageNum])) {
                    $index = $pageToIndex[$pageNum];
                    $tempFile = $tempFiles[$index];
                    
                    if (file_exists($tempFile)) {
                        $outputPdf->setSourceFile($tempFile);
                        $tplIdx = $outputPdf->importPage(1);
                        $size = $outputPdf->getTemplateSize($tplIdx);
                        
                        $outputPdf->AddPage('P', array($size['width'], $size['height']));
                        $outputPdf->useTemplate($tplIdx);
                        
                        // Ajout de la page $pageNum (index $index)
                    }
                }
            }
            
            // Sauvegarder le PDF avec suffixe -ppp
            $pathInfo = pathinfo($this->outputFile);
            $finalOutputFile = $pathInfo['dirname'] . DIRECTORY_SEPARATOR . $pathInfo['filename'] . '-ppp.pdf';
            $outputPdf->Output($finalOutputFile, 'F');
            
            // Nettoyer les fichiers temporaires
            foreach ($tempFiles as $tempFile) {
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }
            
            // Livret transformé avec succès : $finalOutputFile
            
        } catch (Exception $e) {
            // Erreur lors de la transformation : $e->getMessage()
            // Trace: $e->getTraceAsString()
            return false;
        }
        
        return $finalOutputFile;
    }
}

// Fonction principale
function main() {
    global $argv;
    
    if (count($argv) != 2) {
        echo "Usage: php unimpose.php input.pdf\n";
        exit(1);
    }
    
    $inputFile = $argv[1];
    
    // Générer le nom du fichier de sortie basé sur le nom d'entrée
    $pathInfo = pathinfo($inputFile);
    $outputFile = $pathInfo['filename'] . '-ppp.pdf';
    
    $unimpose = new UnimposeBooklet($inputFile, $outputFile);
    
    $success = $unimpose->unimposeBooklet();
    
    if (!$success) {
        exit(1);
    }
}

// Exécuter le script si appelé directement
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    main();
}
?>