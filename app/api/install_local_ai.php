<?php
/**
 * API : Lance l'installation de l'IA locale (PyTorch, Docling, Models)
 * POST - Accessible admin seulement
 */
ini_set('display_errors', 0);
while (ob_get_level()) ob_end_clean();

require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../controler/func.php';
require_once __DIR__ . '/../controler/functions/paths.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ((!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) && 
    (!isset($_SESSION['user']) || $_SESSION['user'] !== "1")) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accès réservé à l\'administrateur']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

try {
    $targetDir = trim($_POST['target_dir'] ?? '');

    // OS detection
    $isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');

    if ($isWindows) {
        // Path to batch script
        $scriptPath = realpath(__DIR__ . '/../../bin/win-x64/install_local_ai.bat');
        if (!$scriptPath || !file_exists($scriptPath)) {
            throw new Exception("Script d'installation introuvable : install_local_ai.bat");
        }
        
        $cmd = 'start cmd.exe /c ""' . $scriptPath . '"';
        if ($targetDir !== '') {
            $cmd .= ' ' . escapeshellarg($targetDir);
        }
        $cmd .= '"';
        
        // Launch in background (popen)
        pclose(popen($cmd, "r"));
    } else {
        // Mac/Linux
        $scriptPath = realpath(__DIR__ . '/../../scripts/install_local_ai.sh');
        if (!$scriptPath || !file_exists($scriptPath)) {
            throw new Exception("Script d'installation introuvable : install_local_ai.sh");
        }

        // Try to launch a terminal based on OS. For Mac it's Terminal.app, for Linux it depends.
        $osType = php_uname('s');
        if (stripos($osType, 'darwin') !== false) {
            // macOS
            $cmd = 'open -a Terminal ' . escapeshellarg($scriptPath);
            if ($targetDir !== '') {
                $cmd .= ' --args ' . escapeshellarg($targetDir);
            }
            exec($cmd . ' > /dev/null 2>&1 &');
        } else {
            // Linux : using escapeshellarg for scriptPath and targetDir
            $cmd = 'x-terminal-emulator -e "bash ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($targetDir) . '" > /dev/null 2>&1 &';
            exec($cmd);
        }
    }

    echo json_encode(['success' => true, 'message' => 'Installation en cours...']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
