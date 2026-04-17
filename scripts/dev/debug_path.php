<?php
// Script minimal - pas d'include de func.php pour éviter les connexions DB
require_once __DIR__ . '/app/controler/functions/paths.php';

echo 'DUPLICATOR_DB_PATH: ' . getenv('DUPLICATOR_DB_PATH') . "\n";
echo 'getDataDir: ' . getDataDir() . "\n";
echo 'getBibliothequeDir: ' . getBibliothequeDir() . "\n";

$thumbPath = getBibliothequeDir() . DIRECTORY_SEPARATOR . 'thumbnails' . DIRECTORY_SEPARATOR . 'pdf' . DIRECTORY_SEPARATOR . '003a70a3f19278cd33b0b6e911f814d8.png';
echo 'Thumbnail path: ' . $thumbPath . "\n";
echo 'Exists: ' . (file_exists($thumbPath) ? 'YES' : 'NO') . "\n";
