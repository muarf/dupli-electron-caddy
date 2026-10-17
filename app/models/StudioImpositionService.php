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
}
