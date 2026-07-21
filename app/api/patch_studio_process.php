<?php
$file = '/home/ubuntu/dupli-electron-caddy/app/api/studio_process.php';
$content = file_get_contents($file);

// 1. Add check_task action
$checkTaskCode = <<<EOT
if (\$action === 'check_task') {
    \$taskId = \$_POST['task_id'] ?? '';
    if (!\$taskId) {
        echo json_encode(['success' => false, 'error' => 'No task ID']);
        exit;
    }
    \$statusFile = resolveTempDir() . DIRECTORY_SEPARATOR . 'studio' . DIRECTORY_SEPARATOR . 'task_' . \$taskId . '.json';
    if (!file_exists(\$statusFile)) {
        echo json_encode(['success' => false, 'error' => 'Task not found', 'status' => 'error']);
        exit;
    }
    echo file_get_contents(\$statusFile);
    exit;
}

// === ACTION : IMPOSE ===
EOT;
$content = str_replace("// === ACTION : IMPOSE ===", $checkTaskCode, $content);

// 2. Insert background detach logic
$bgDetach = <<<EOT
    } elseif (\$mimeType !== 'application/pdf') {
        echo json_encode(['success' => false, 'errors' => ["Format non supporté : \$mimeType. Veuillez fournir un PDF ou une image (PNG, JPG, WebP)."]]);
        exit;
    }

    // --- BACKGROUND SETUP ---
    \$taskId = 'imp_' . time() . '_' . uniqid();
    \$statusFile = \$tmpBase . 'task_' . \$taskId . '.json';
    
    // Copy uploaded file so it persists after HTTP detach
    \$persistentUpload = \$tmpBase . 'upload_' . \$taskId . '.pdf';
    copy(\$uploadedFile, \$persistentUpload);
    \$uploadedFile = \$persistentUpload;

    file_put_contents(\$statusFile, json_encode(['status' => 'running', 'success' => false]));
    
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
EOT;

$content = preg_replace("/\s*\}\s*elseif \(\\$mimeType !== 'application\/pdf'\) \{.*?exit;\s*\}/s", "\n" . $bgDetach, $content);

// 3. Replace all `echo json_encode([X]); exit;` to `finishTask([X]);` 
// But only inside the impose block! So we will do simple preg_replace logic for the tracts, livre, brochure blocks.

// We will write a helper function finishTask inside the script? Or just replace the echos.
// Since we are writing a patch, let's just do it directly.
file_put_contents($file, $content);
echo "Patch step 1 applied.\n";
