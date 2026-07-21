<?php
$file = '/home/ubuntu/dupli-electron-caddy/app/api/studio_process.php';
$content = file_get_contents($file);

// 0. FIX SETTINGS ORDER (Livre and Brochure blocks)
$content = preg_replace('/(\$paddingResult = padPdfToMultiple\(\$uploadedFile, \$multiple\);\s*\$pdfFile\s*=\s*\$paddingResult\[\'file\'\];\s*)(\$settings = \[.*?\];)/s', "$2\n\n$1", $content, 1);
$content = preg_replace('/(\$paddingResult = padPdfToMultiple\(\$uploadedFile, \$multiple\);\s*\$pdfFile\s*=\s*\$paddingResult\[\'file\'\];\s*\$pageCount\s*=\s*\$paddingResult\[\'page_count\'\];\s*)(\$settings = \[.*?\];)/s', "$2\n\n$1", $content, 1);


// 1. We need to inject the `run_task` action at the top
$runTaskCode = <<<EOT
if (\$action === 'run_task') {
    \$taskId = \$_POST['task_id'] ?? '';
    \$tmpBase = resolveTempDir() . DIRECTORY_SEPARATOR . 'studio' . DIRECTORY_SEPARATOR;
    \$statusFile = \$tmpBase . 'task_' . \$taskId . '.json';
    \$paramsFile = \$tmpBase . 'task_' . \$taskId . '_params.json';
    
    if (!file_exists(\$paramsFile)) {
        file_put_contents(\$statusFile, json_encode(['status' => 'completed', 'success' => false, 'errors' => ['Paramètres introuvables.']]));
        exit;
    }
    
    \$params = json_decode(file_get_contents(\$paramsFile), true);
    \$_POST = \$params;
    \$uploadedFile = \$params['uploadedFile'];
    \$imposeTempPdf = \$params['imposeTempPdf'];
    \$impose_type = \$_POST['impose_type'] ?? 'brochure';
    \$safeName = 'task_' . \$taskId;
    
    \$finishTask = function(\$response) use (&\$statusFile, &\$uploadedFile, &\$imposeTempPdf, &\$paramsFile) {
        \$response['status'] = 'completed';
        file_put_contents(\$statusFile, json_encode(\$response));
        if (isset(\$uploadedFile) && file_exists(\$uploadedFile)) @unlink(\$uploadedFile);
        if (isset(\$imposeTempPdf) && file_exists(\$imposeTempPdf)) @unlink(\$imposeTempPdf);
        if (isset(\$paramsFile) && file_exists(\$paramsFile)) @unlink(\$paramsFile);
        exit;
    };
    
    ignore_user_abort(true);
    set_time_limit(0);
    ini_set('memory_limit', '-1');
    
    // Let's let the rest of the script execute by mocking the action to impose, but skipping the upload part
    \$action = 'impose_run'; // So we don't re-trigger the upload logic
}

if (\$action === 'check_task') {
    \$taskId = \$_POST['task_id'] ?? '';
    if (!\$taskId) {
        echo json_encode(['success' => false, 'error' => 'No task ID']);
        exit;
    }
    \$tmpBase = resolveTempDir() . DIRECTORY_SEPARATOR . 'studio' . DIRECTORY_SEPARATOR;
    \$statusFile = \$tmpBase . 'task_' . \$taskId . '.json';
    if (!file_exists(\$statusFile)) {
        echo json_encode(['success' => false, 'error' => 'Task not found', 'status' => 'error']);
        exit;
    }
    echo file_get_contents(\$statusFile);
    exit;
}

if (\$action === 'impose_run') {
    // Jump straight to Tracts, Livre, Brochure logic below
}
EOT;

$content = preg_replace("/\/\/\s*===\s*ACTION\s*:\s*IMPOSE\s*===/", $runTaskCode . "\n\n// === ACTION : IMPOSE ===", $content);

// 2. Add Background Detach Logic in impose action
$bgNew = <<<EOT
    } elseif (\$mimeType !== 'application/pdf') {
        echo json_encode(['success' => false, 'errors' => ["Format non supporté : \$mimeType. Veuillez fournir un PDF ou une image (PNG, JPG, WebP)."]]);
        exit;
    }

    // --- BACKGROUND SETUP AJAX ---
    \$taskId = 'imp_' . time() . '_' . uniqid();
    \$statusFile = \$tmpBase . 'task_' . \$taskId . '.json';
    \$paramsFile = \$tmpBase . 'task_' . \$taskId . '_params.json';
    
    // Copy uploaded file so it persists
    \$persistentUpload = \$tmpBase . 'upload_' . \$taskId . '.pdf';
    copy(\$uploadedFile, \$persistentUpload);
    
    // Save all parameters for the next request
    \$params = \$_POST;
    \$params['uploadedFile'] = \$persistentUpload;
    \$params['imposeTempPdf'] = \$imposeTempPdf ?? null;
    file_put_contents(\$paramsFile, json_encode(\$params));
    file_put_contents(\$statusFile, json_encode(['status' => 'running', 'success' => false]));
    
    // Return IMMEDIATELY
    echo json_encode(['success' => true, 'task_id' => \$taskId, 'action_required' => 'run_task']);
    exit;
EOT;

$content = preg_replace("/\s*\}\s*elseif \(\\$mimeType !== 'application\/pdf'\) \{.*?exit;\s*\}/s", "\n" . $bgNew, $content);

// 3. Wrap Tracts, Livre, Brochure
$content = preg_replace("/\/\/ === TRACTS \(N-up copies identiques\) ===/", "if (\$action === 'impose_run') {\n// === TRACTS (N-up copies identiques) ===", $content);
$content = preg_replace("/\s*exit;\s*\}\s*\/\/\s*===\s*ACTION\s*:\s*RESIZE\s*===/s", "\n    exit;\n}\n}\n\n// === ACTION : RESIZE ===", $content);

// 4. Replace `echo json_encode` with `$finishTask` INSIDE the `impose_run` block.
// To do this safely, we only replace it within the block.
$parts = explode("if (\$action === 'impose_run') {", $content);
if (count($parts) == 3) {
    $parts[2] = preg_replace("/echo json_encode\(/", "\$finishTask(", $parts[2]);
    $content = implode("if (\$action === 'impose_run') {", $parts);
}

file_put_contents($file, $content);
echo "AJAX background patched successfully.\n";
