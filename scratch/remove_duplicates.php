<?php
require_once __DIR__ . '/../app/controler/conf.php';
require_once __DIR__ . '/../app/controler/func.php';
require_once __DIR__ . '/../app/models/BibliothequeManager.php';

try {
    $manager = new BibliothequeManager();
    $db = pdo_connect();

    // On cherche les doublons par nom de fichier
    $stmt = $db->query("SELECT filename, MIN(id) as keep_id, GROUP_CONCAT(id) as all_ids 
                        FROM bibliotheque_files 
                        GROUP BY filename 
                        HAVING COUNT(*) > 1");
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Analyse des doublons en cours...\n";
    $deletedCount = 0;

    foreach ($duplicates as $dup) {
        $ids = explode(',', $dup['all_ids']);
        foreach ($ids as $id) {
            $id = trim($id);
            if ($id != $dup['keep_id']) {
                // On supprime proprement de la base (Vecteurs + Chunks + FTS)
                // Mais on NE SUPPRIME PAS du disque (deleteFromDisk = false) 
                // car le fichier physique est partagé.
                $manager->deleteFile($id, false);
                $deletedCount++;
            }
        }
    }

    echo "Nettoyage terminé : $deletedCount entrées en doublon ont été supprimées de la base de données et de l'indexation IA.\n";

} catch (Exception $e) {
    echo "ERREUR : " . $e->getMessage() . "\n";
}
