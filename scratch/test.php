<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$json = json_encode(['jobId' => '999', 'document' => 'test_doc', 'printerName' => 'RISO', 'status' => 'Printed', 'timestamp' => 'test', 'totalPages' => 1]);
// We can't easily mock php://input without stream wrappers, let's just modify the code to read from a file or mock it.
// Actually, let's just make a real cURL request to the running server.
