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
            $cmd .= ' "' . $targetDir . '"';
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
            $cmd = 'open -a Terminal "' . $scriptPath . '"';
            if ($targetDir !== '') {
                $cmd .= ' --args "' . $targetDir . '"';
            }
            exec($cmd . ' > /dev/null 2>&1 &');
        } else {
            // Linux : we try x-terminal-emulator, gnome-terminal, etc. 
            // We just use a generic wrapper or nohup if we can't open a terminal easily.
            // Using x-terminal-emulator is usually safest on desktop linux.
            $cmd = 'x-terminal-emulator -e "bash \'' . $scriptPath . '\' \'' . $targetDir . '\'" > /dev/null 2>&1 &';
            exec($cmd);
        }
    }

    echo json_encode(['success' => true, 'message' => 'Installation en cours...']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
