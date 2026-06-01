<?php
require_once __DIR__ . '/../app/controler/conf.php';
require_once __DIR__ . '/../app/controler/func.php';
require_once __DIR__ . '/../app/models/BibliothequeManager.php';

$pdfFile = __DIR__ . '/../app/bibliotheque/files/pdf/Blanqui ou l_insurrection d_Etat.pdf';
$filename = basename($pdfFile);

$manager = new BibliothequeManager();
$db = pdo_connect();

echo "Suppression du fichier de la DB pour forcer la ré-indexation...\n";
$db->prepare("DELETE FROM bibliotheque_files WHERE filename = ?")->execute([$filename]);

echo "Indexation du fichier : $filename\n";
$result = $manager->addExternalFile($pdfFile);

if (isset($result['status']) && ($result['status'] === 'indexed' || $result['status'] === 'updated')) {
    echo "Succès de l'indexation.\n";
    
    // Vérifier les données en base
    $stmt = $db->prepare("SELECT metadata_json, tags FROM bibliotheque_files WHERE filename = ?");
    $stmt->execute([$filename]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "--- Données enregistrées ---\n";
    echo "metadata_json : " . $row['metadata_json'] . "\n";
    echo "tags : " . $row['tags'] . "\n";
    
    $meta = json_decode($row['metadata_json'], true);
    if (isset($meta['format']) && isset($meta['is_color'])) {
        echo "VERIFICATION OK : Les métadonnées techniques sont présentes.\n";
    } else {
        echo "ERREUR : Métadonnées incomplètes.\n";
    }
} else {
    echo "Erreur lors de l'indexation : " . json_encode($result) . "\n";
}
