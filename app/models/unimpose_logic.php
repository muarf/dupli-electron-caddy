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
            
            // CONTOURNEMENT FPDI : Dégrader la version du PDF à 1.4 via Ghostscript
            // FPDI gratuit ne supporte pas les PDF > 1.4, ce qui fait planter l'importation.
            $safeInputFile = tempnam(sys_get_temp_dir(), 'gs_downgrade_');
            $gsCommand = "gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dNOPAUSE -dQUIET -dBATCH -sOutputFile=" . escapeshellarg($safeInputFile) . " " . escapeshellarg($this->inputFile);
            exec($gsCommand, $output, $returnVar);
            
            if ($returnVar !== 0 || !file_exists($safeInputFile)) {
                throw new Exception("Échec de la conversion Ghostscript (downgrade) du fichier PDF.");
            }
            
            // Remplacer temporairement le inputFile par la version dégradée
            $originalInputFile = $this->inputFile;
            $this->inputFile = $safeInputFile;

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
            
            // Sauvegarder le PDF avec le nom fourni
            $finalOutputFile = $this->outputFile;
            $outputPdf->Output($finalOutputFile, 'F');
            
            // Nettoyer les fichiers temporaires
            foreach ($tempFiles as $tempFile) {
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }
            
            // Restaurer le inputFile original
            $this->inputFile = $originalInputFile;
            if (file_exists($safeInputFile)) unlink($safeInputFile);
            
            // Livret transformé avec succès : $finalOutputFile
            
        } catch (Exception $e) {
            error_log("Erreur lors de la transformation : " . $e->getMessage());
            error_log("Trace: " . $e->getTraceAsString());
            
            if (isset($safeInputFile) && file_exists($safeInputFile)) unlink($safeInputFile);
            if (isset($originalInputFile)) $this->inputFile = $originalInputFile;
            
            return false;
        }
        
        return $finalOutputFile;
    }
    
    /**
     * Désimpose un PDF avec format : couverture (page 1) + doubles pages (pages 2-N)
     * La page 1 reste intacte, les pages suivantes sont coupées en deux dans l'ordre séquentiel
     * 
     * @return string|false Chemin du fichier de sortie ou false en cas d'erreur
     */
    public function splitDoublePages() {
        try {
            // Vérifier que le fichier d'entrée existe
            if (!file_exists($this->inputFile)) {
                throw new Exception("Le fichier d'entrée n'existe pas : " . $this->inputFile);
            }
            
            // CONTOURNEMENT FPDI : Dégrader la version du PDF à 1.4 via Ghostscript
            $safeInputFile = tempnam(sys_get_temp_dir(), 'gs_downgrade_');
            $gsCommand = "gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dNOPAUSE -dQUIET -dBATCH -sOutputFile=" . escapeshellarg($safeInputFile) . " " . escapeshellarg($this->inputFile);
            exec($gsCommand, $output, $returnVar);
            
            if ($returnVar !== 0 || !file_exists($safeInputFile)) {
                throw new Exception("Échec de la conversion Ghostscript (downgrade) du fichier PDF.");
            }
            
            $originalInputFile = $this->inputFile;
            $this->inputFile = $safeInputFile;
            
            // Parser le PDF pour obtenir les informations
            $parser = new Parser();
            $pdf = $parser->parseFile($this->inputFile);
            $pages = $pdf->getPages();
            $pageCount = count($pages);
            
            if ($pageCount == 0) {
                throw new Exception("Le fichier PDF ne contient aucune page");
            }
            
            // Créer un nouveau PDF de sortie avec FPDI
            $outputPdf = new setasign\Fpdi\Fpdi();
            $outputPdf->SetCreator('Unimpose PHP Script');
            $outputPdf->SetTitle('PDF désimposé (doubles pages)');
            
            // Ajouter la page 1 (couverture) intacte
            $outputPdf->setSourceFile($this->inputFile);
            $templateId1 = $outputPdf->importPage(1);
            $size1 = $outputPdf->getTemplateSize($templateId1);
            $outputPdf->AddPage('P', array($size1['width'], $size1['height']));
            $outputPdf->useTemplate($templateId1);
            
            // Pour les pages 2-N, les couper en deux et ajouter séquentiellement
            for ($i = 2; $i <= $pageCount; $i++) {
                // Créer des PDF temporaires pour les moitiés gauche et droite
                $halfPdf = new setasign\Fpdi\Fpdi();
                $halfPdf->setSourceFile($this->inputFile);
                
                $templateId = $halfPdf->importPage($i);
                $size = $halfPdf->getTemplateSize($templateId);
                
                $w = $size['width'];
                $h = $size['height'];
                
                // Vérifier si la page est bien une double page (largeur ≈ 2× hauteur ou > 1.5× la largeur de la page 1)
                $expectedWidth = $size1['width'] * 2;
                $widthRatio = $w / $size1['width'];
                
                // Si la page n'est pas une double page (ratio < 1.5), la garder telle quelle
                if ($widthRatio < 1.5) {
                    // Importer la page dans outputPdf
                    $outputPdf->setSourceFile($this->inputFile);
                    $templateIdOutput = $outputPdf->importPage($i);
                    $outputPdf->AddPage('P', array($w, $h));
                    $outputPdf->useTemplate($templateIdOutput, 0, 0, $w, null, false);
                } else {
                    // Page de gauche
                    $halfPdfLeft = new setasign\Fpdi\Fpdi();
                    $halfPdfLeft->setSourceFile($this->inputFile);
                    $templateIdLeft = $halfPdfLeft->importPage($i);
                    $halfPdfLeft->AddPage('P', array($w/2, $h));
                    $halfPdfLeft->useTemplate($templateIdLeft, 0, 0, $w, null, false);
                    
                    $tempFileLeft = tempnam(sys_get_temp_dir(), 'pdf_left_');
                    file_put_contents($tempFileLeft, $halfPdfLeft->Output('S'));
                    
                    // Page de droite
                    $halfPdfRight = new setasign\Fpdi\Fpdi();
                    $halfPdfRight->setSourceFile($this->inputFile);
                    $templateIdRight = $halfPdfRight->importPage($i);
                    $halfPdfRight->AddPage('P', array($w/2, $h));
                    $halfPdfRight->useTemplate($templateIdRight, -$w/2, 0, $w, null, false);
                    
                    $tempFileRight = tempnam(sys_get_temp_dir(), 'pdf_right_');
                    file_put_contents($tempFileRight, $halfPdfRight->Output('S'));
                    
                    // Ajouter la moitié gauche
                    $outputPdf->setSourceFile($tempFileLeft);
                    $tplIdxLeft = $outputPdf->importPage(1);
                    $sizeLeft = $outputPdf->getTemplateSize($tplIdxLeft);
                    $outputPdf->AddPage('P', array($sizeLeft['width'], $sizeLeft['height']));
                    $outputPdf->useTemplate($tplIdxLeft);
                    
                    // Ajouter la moitié droite
                    $outputPdf->setSourceFile($tempFileRight);
                    $tplIdxRight = $outputPdf->importPage(1);
                    $sizeRight = $outputPdf->getTemplateSize($tplIdxRight);
                    $outputPdf->AddPage('P', array($sizeRight['width'], $sizeRight['height']));
                    $outputPdf->useTemplate($tplIdxRight);
                    
                    // Nettoyer les fichiers temporaires
                    if (file_exists($tempFileLeft)) {
                        unlink($tempFileLeft);
                    }
                    if (file_exists($tempFileRight)) {
                        unlink($tempFileRight);
                    }
                }
            }
            
            // Sauvegarder le PDF avec le nom fourni
            $finalOutputFile = $this->outputFile;
            $outputPdf->Output($finalOutputFile, 'F');
            
            $this->inputFile = $originalInputFile;
            if (file_exists($safeInputFile)) unlink($safeInputFile);
            
        } catch (Exception $e) {
            error_log("Erreur lors du découpage des doubles pages : " . $e->getMessage());
            error_log("Trace : " . $e->getTraceAsString());
            
            if (isset($safeInputFile) && file_exists($safeInputFile)) unlink($safeInputFile);
            if (isset($originalInputFile)) $this->inputFile = $originalInputFile;
            
            return false;
        }
        
        return $finalOutputFile;
    }

    /**
     * Désimpose un PDF format séquentiel (ex: page 1 = 1 gauche, 1 droite, page 2 = 2 gauche, 2 droite...)
     * 
     * @return string|false Chemin du fichier de sortie ou false en cas d'erreur
     */
    public function splitSequential() {
        try {
            // Vérifier que le fichier d'entrée existe
            if (!file_exists($this->inputFile)) {
                throw new Exception("Le fichier d'entrée n'existe pas : " . $this->inputFile);
            }
            
            // CONTOURNEMENT FPDI : Dégrader la version du PDF à 1.4 via Ghostscript
            $safeInputFile = tempnam(sys_get_temp_dir(), 'gs_downgrade_');
            $gsCommand = "gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dNOPAUSE -dQUIET -dBATCH -sOutputFile=" . escapeshellarg($safeInputFile) . " " . escapeshellarg($this->inputFile);
            exec($gsCommand, $output, $returnVar);
            
            if ($returnVar !== 0 || !file_exists($safeInputFile)) {
                throw new Exception("Échec de la conversion Ghostscript (downgrade) du fichier PDF.");
            }
            
            $originalInputFile = $this->inputFile;
            $this->inputFile = $safeInputFile;
            
            // Parser le PDF pour obtenir les informations
            $parser = new Parser();
            $pdf = $parser->parseFile($this->inputFile);
            $pages = $pdf->getPages();
            $pageCount = count($pages);
            
            if ($pageCount == 0) {
                throw new Exception("Le fichier PDF ne contient aucune page");
            }
            
            // Créer un nouveau PDF de sortie avec FPDI
            $outputPdf = new setasign\Fpdi\Fpdi();
            $outputPdf->SetCreator('Unimpose PHP Script');
            $outputPdf->SetTitle('PDF désimposé (séquentiel)');
            
            for ($i = 1; $i <= $pageCount; $i++) {
                // Page de gauche
                $halfPdfLeft = new setasign\Fpdi\Fpdi();
                $halfPdfLeft->setSourceFile($this->inputFile);
                $templateIdLeft = $halfPdfLeft->importPage($i);
                $size = $halfPdfLeft->getTemplateSize($templateIdLeft);
                $w = $size['width'];
                $h = $size['height'];
                
                $halfPdfLeft->AddPage('P', array($w/2, $h));
                $halfPdfLeft->useTemplate($templateIdLeft, 0, 0, $w, null, false);
                $tempFileLeft = tempnam(sys_get_temp_dir(), 'pdf_left_');
                file_put_contents($tempFileLeft, $halfPdfLeft->Output('S'));
                
                // Page de droite
                $halfPdfRight = new setasign\Fpdi\Fpdi();
                $halfPdfRight->setSourceFile($this->inputFile);
                $templateIdRight = $halfPdfRight->importPage($i);
                $halfPdfRight->AddPage('P', array($w/2, $h));
                $halfPdfRight->useTemplate($templateIdRight, -$w/2, 0, $w, null, false);
                $tempFileRight = tempnam(sys_get_temp_dir(), 'pdf_right_');
                file_put_contents($tempFileRight, $halfPdfRight->Output('S'));
                
                // Ajouter la moitié gauche
                $outputPdf->setSourceFile($tempFileLeft);
                $tplIdxLeft = $outputPdf->importPage(1);
                $sizeLeft = $outputPdf->getTemplateSize($tplIdxLeft);
                $outputPdf->AddPage('P', array($sizeLeft['width'], $sizeLeft['height']));
                $outputPdf->useTemplate($tplIdxLeft);
                
                // Ajouter la moitié droite
                $outputPdf->setSourceFile($tempFileRight);
                $tplIdxRight = $outputPdf->importPage(1);
                $sizeRight = $outputPdf->getTemplateSize($tplIdxRight);
                $outputPdf->AddPage('P', array($sizeRight['width'], $sizeRight['height']));
                $outputPdf->useTemplate($tplIdxRight);
                
                // Nettoyer les fichiers temporaires
                if (file_exists($tempFileLeft)) {
                    unlink($tempFileLeft);
                }
                if (file_exists($tempFileRight)) {
                    unlink($tempFileRight);
                }
            }
            
            // Sauvegarder le PDF avec le nom fourni
            $finalOutputFile = $this->outputFile;
            $outputPdf->Output($finalOutputFile, 'F');
            
            $this->inputFile = $originalInputFile;
            if (file_exists($safeInputFile)) unlink($safeInputFile);
            
        } catch (Exception $e) {
            error_log("Erreur lors du découpage séquentiel : " . $e->getMessage());
            error_log("Trace : " . $e->getTraceAsString());
            
            if (isset($safeInputFile) && file_exists($safeInputFile)) unlink($safeInputFile);
            if (isset($originalInputFile)) $this->inputFile = $originalInputFile;
            
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