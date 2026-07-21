<?php
$file = '/home/ubuntu/dupli-electron-caddy/app/api/studio_process.php';
$content = file_get_contents($file);

// 1. Add check_task
$checkTask = <<<EOT
if (\$action === 'check_task') {
    \$taskId = \$_POST['task_id'] ?? '';
    if (!\$taskId) {
        echo json_encode(['success' => false, 'error' => 'No task ID']);
        exit;
    }
    // Using \$tmpBase from further down, but we just re-declare it here
    \$tmpBase = resolveTempDir() . DIRECTORY_SEPARATOR . 'studio' . DIRECTORY_SEPARATOR;
    \$statusFile = \$tmpBase . 'task_' . \$taskId . '.json';
    if (!file_exists(\$statusFile)) {
        echo json_encode(['success' => false, 'error' => 'Task not found', 'status' => 'error']);
        exit;
    }
    echo file_get_contents(\$statusFile);
    exit;
}

// === ACTION : IMPOSE ===
EOT;
$content = str_replace("// === ACTION : IMPOSE ===", $checkTask, $content);

// 2. Add Background Detach Logic
$bgDetach = <<<EOT
    }

    // --- BACKGROUND SETUP ---
    \$taskId = 'imp_' . time() . '_' . uniqid();
    \$statusFile = \$tmpBase . 'task_' . \$taskId . '.json';
    
    // Copy uploaded file so it persists after HTTP detach
    \$persistentUpload = \$tmpBase . 'upload_' . \$taskId . '.pdf';
    copy(\$uploadedFile, \$persistentUpload);
    \$uploadedFile = \$persistentUpload;

    file_put_contents(\$statusFile, json_encode(['status' => 'running', 'success' => false]));
    
    // Helper pour terminer proprement la tâche
    \$finishTask = function(\$response) use (&\$statusFile, &\$persistentUpload, &\$imposeTempPdf) {
        \$response['status'] = 'completed';
        file_put_contents(\$statusFile, json_encode(\$response));
        if (isset(\$persistentUpload) && file_exists(\$persistentUpload)) @unlink(\$persistentUpload);
        if (isset(\$imposeTempPdf) && file_exists(\$imposeTempPdf)) @unlink(\$imposeTempPdf);
        exit;
    };
    
    // Detach HTTP connection
    if (function_exists('fastcgi_finish_request')) {
        echo json_encode(['success' => true, 'task_id' => \$taskId]);
        session_write_close();
        fastcgi_finish_request();
    } else {
        ignore_user_abort(true);
        ob_start();
        echo json_encode(['success' => true, 'task_id' => \$taskId]);
        \$size = ob_get_length();
        header("Connection: close");
        header("Content-Length: \$size");
        ob_end_flush();
        @ob_flush();
        flush();
        if (session_id()) session_write_close();
    }
    set_time_limit(0);
    ini_set('memory_limit', '-1');
    // --- END BACKGROUND SETUP ---

    // === TRACTS (N-up copies identiques) ===
EOT;

$content = preg_replace("/\s*\}\s*\/\/\s*===\s*TRACTS\s*\(N-up copies identiques\)\s*===/s", "\n" . $bgDetach, $content);

file_put_contents($file, $content);
echo "Patch applied.\n";
