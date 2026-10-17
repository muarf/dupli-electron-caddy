<?php
/**
 * Service d'imposition et utilitaires pour Dupli Studio (studio_process.php)
 */

require_once __DIR__ . '/SettingsManager.php';
require_once __DIR__ . '/../controler/functions/binary_utilities.php';

class StudioImpositionService
{
    /**
     * Retourne tous les réglages de l'application (cached).
     *
     * @return array
     */
    public static function getStudioSettings(): array
    {
        static $cache = null;
        if ($cache === null) {
            try {
                $db = pdo_connect();
                $sm = new SettingsManager($db);
                $cache = $sm->getAll();
            } catch (Throwable $e) {
                $cache = [];
            }
        }
        return $cache;
    }

    /**
     * Déplace un fichier uploadé ou copié localement (compatible HTTP et CLI/test).
     *
     * @param string $tmp
     * @param string $dest
     * @return bool
     */
    public static function moveUploadedFile(string $tmp, string $dest): bool
    {
        if (is_uploaded_file($tmp)) {
            return move_uploaded_file($tmp, $dest);
        }
        if (file_exists($tmp)) {
            if (rename($tmp, $dest)) {
                return true;
            }
            return copy($tmp, $dest);
        }
        return false;
    }

    /**
     * Génère un aperçu PNG de la première page d'un PDF via Ghostscript.
     *
     * @param string $pdfPath
     * @param string $tmpBase
     * @param string $safeName
     * @return string|null Nom du fichier PNG créé, ou null si Ghostscript échoue
     */
    public static function generateImpositionPreview(string $pdfPath, string $tmpBase, string $safeName): ?string
    {
        $previewFile = $tmpBase . $safeName . '_preview.png';
        $gs_args = implode(' ', [
            '-dNOPAUSE', '-dBATCH', '-dSAFER',
            '-sDEVICE=png16m',
            '-r150',
            '-dFirstPage=1', '-dLastPage=1',
            '-dFitPage',
            '-sOutputFile=' . escapeshellarg($previewFile),
            escapeshellarg($pdfPath),
        ]);
        $result = run_ghostscript($gs_args);
        if ($result['success'] && file_exists($previewFile) && filesize($previewFile) > 0) {
            return basename($previewFile);
        }
        return null;
    }

    /**
     * Reconstruit un PDF selon une structure de pages (suppression, réorganisation, rotation, pages blanches).
     * Si la structure est identique au document source original, retourne le document original sans modification.
     *
     * @param array $sourceFiles Map file_idx => chemin du fichier (ex: [0 => '/path/to/doc.pdf'])
     * @param array $structure Tableau d'objets [{'type':'page','file_idx':0,'page_num':1,'rotation':0}, ...]
     * @param string $tmpBase Répertoire temporaire
     * @return string|null Chemin du PDF reconstruit (ou original si inchangé), ou null en cas d'erreur
     */
    public static function applyPageStructure(array $sourceFiles, array $structure, string $tmpBase): ?string
    {
        if (empty($structure)) {
            return $sourceFiles[0] ?? null;
        }

        $primaryFile = $sourceFiles[0] ?? null;
        if (!$primaryFile || !file_exists($primaryFile)) {
            return null;
        }

        require_once __DIR__ . '/../vendor/autoload.php';

        // Vérifier si le document a réellement été modifié
        $isModified = false;
        try {
            $checker = new \setasign\Fpdi\TcpdfFpdi();
            $origPageCount = $checker->setSourceFile($primaryFile);
            if (count($structure) !== $origPageCount) {
                $isModified = true;
            } else {
                foreach ($structure as $i => $item) {
                    $type = $item['type'] ?? 'page';
                    $fIdx = (int)($item['file_idx'] ?? 0);
                    $pNum = (int)($item['page_num'] ?? 0);
                    $rot = (int)($item['rotation'] ?? 0);
                    if ($type !== 'page' || $fIdx !== 0 || $pNum !== ($i + 1) || ($rot % 360 !== 0) || !empty($item['flipH']) || !empty($item['flipV'])) {
                        $isModified = true;
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Si la vérification échoue (ex: format PDF compressé > 1.4), on considère comme potentiellement modifié
            $isModified = true;
        }

        if (!$isModified) {
            return $primaryFile;
        }

        // Helper interne pour construire le PDF avec TCPDI
        $buildPdf = function(array $files) use ($structure, $tmpBase): ?string {
            $pdf = new \setasign\Fpdi\TcpdfFpdi();
            $pdf->SetCreator('Dupli Studio');
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetAutoPageBreak(false, 0);

            // Déterminer la taille par défaut (à partir de la première page source valide)
            $defaultW = 210.0;
            $defaultH = 297.0;
            $defaultOrientation = 'P';

            foreach ($structure as $item) {
                if (($item['type'] ?? 'page') === 'page') {
                    $fIdx = (int)($item['file_idx'] ?? 0);
                    $pNum = (int)($item['page_num'] ?? 1);
                    if (isset($files[$fIdx]) && file_exists($files[$fIdx])) {
                        try {
                            $pdf->setSourceFile($files[$fIdx]);
                            $tpl = $pdf->importPage($pNum);
                            $size = $pdf->getTemplateSize($tpl);
                            $defaultW = (float)$size['width'];
                            $defaultH = (float)$size['height'];
                            $defaultOrientation = ($defaultW > $defaultH) ? 'L' : 'P';
                            break;
                        } catch (\Throwable $e) {
                            // continuer la recherche
                        }
                    }
                }
            }

            foreach ($structure as $item) {
                $type = $item['type'] ?? 'page';
                if ($type === 'blank') {
                    $pdf->AddPage($defaultOrientation, [$defaultW, $defaultH]);
                    continue;
                }

                $fIdx = (int)($item['file_idx'] ?? 0);
                $pNum = (int)($item['page_num'] ?? 1);
                $src = $files[$fIdx] ?? null;
                if (!$src || !file_exists($src)) {
                    continue;
                }

                $pdf->setSourceFile($src);
                $tpl = $pdf->importPage($pNum);
                $size = $pdf->getTemplateSize($tpl);
                $origW = (float)$size['width'];
                $origH = (float)$size['height'];
                $rot = ((int)($item['rotation'] ?? 0)) % 360;
                if ($rot < 0) $rot += 360;

                if ($rot === 90 || $rot === 270) {
                    $targetW = $origH;
                    $targetH = $origW;
                } else {
                    $targetW = $origW;
                    $targetH = $origH;
                }

                $orientation = ($targetW > $targetH) ? 'L' : 'P';
                $pdf->AddPage($orientation, [$targetW, $targetH]);

                if ($rot === 0) {
                    $pdf->useTemplate($tpl, 0, 0, $origW, $origH);
                } elseif ($rot === 90) {
                    $pdf->StartTransform();
                    $pdf->Rotate(-90, $targetW / 2, $targetH / 2);
                    $x = ($targetW - $origW) / 2;
                    $y = ($targetH - $origH) / 2;
                    $pdf->useTemplate($tpl, $x, $y, $origW, $origH);
                    $pdf->StopTransform();
                } elseif ($rot === 180) {
                    $pdf->StartTransform();
                    $pdf->Rotate(180, $targetW / 2, $targetH / 2);
                    $pdf->useTemplate($tpl, 0, 0, $origW, $origH);
                    $pdf->StopTransform();
                } elseif ($rot === 270) {
                    $pdf->StartTransform();
                    $pdf->Rotate(90, $targetW / 2, $targetH / 2);
                    $x = ($targetW - $origW) / 2;
                    $y = ($targetH - $origH) / 2;
                    $pdf->useTemplate($tpl, $x, $y, $origW, $origH);
                    $pdf->StopTransform();
                }
            }

            if ($pdf->getNumPages() === 0) {
                return null;
            }

            $outPath = $tmpBase . 'structured_' . time() . '_' . uniqid() . '.pdf';
            $pdf->Output($outPath, 'F');
            return file_exists($outPath) ? $outPath : null;
        };

        try {
            return $buildPdf($sourceFiles);
        } catch (\Throwable $e) {
            // Si échec TCPDI (ex: compression PDF 1.5+ non gérée directement),
            // nettoyer avec Ghostscript en PDF 1.4 et réessayer
            $cleanedFiles = [];
            $tempToClean = [];
            foreach ($sourceFiles as $idx => $fPath) {
                $cleaned = $tmpBase . 'cleaned_struct_' . $idx . '_' . uniqid() . '.pdf';
                $gs = run_ghostscript("-dNOPAUSE -dBATCH -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -sOutputFile=" . escapeshellarg($cleaned) . " " . escapeshellarg($fPath));
                if ($gs['success'] && file_exists($cleaned)) {
                    $cleanedFiles[$idx] = $cleaned;
                    $tempToClean[] = $cleaned;
                } else {
                    $cleanedFiles[$idx] = $fPath;
                }
            }

            try {
                $result = $buildPdf($cleanedFiles);
                foreach ($tempToClean as $tf) @unlink($tf);
                return $result;
            } catch (\Throwable $e2) {
                foreach ($tempToClean as $tf) @unlink($tf);
                error_log('[applyPageStructure] Error: ' . $e2->getMessage());
                return null;
            }
        }
    }
}
