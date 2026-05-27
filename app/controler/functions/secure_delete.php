<?php

/**
 * Fonction de suppression sécurisée (Shredding)
 * Écrase le fichier avec des zéros avant de le supprimer pour empêcher la récupération.
 * 
 * @param string $filepath Chemin absolu du fichier à supprimer
 * @return bool Succès ou échec
 */
function secure_delete($filepath) {
    if (!file_exists($filepath)) {
        return true; // Le fichier n'existe pas, on considère que c'est un succès
    }

    if (!is_writable($filepath)) {
        error_log("[SECURE DELETE] Erreur : Le fichier n'est pas accessible en écriture : $filepath");
        return false;
    }

    $filesize = filesize($filepath);

    if ($filesize > 0) {
        $handle = fopen($filepath, 'r+');
        if ($handle) {
            // Écrasement avec des zéros
            // On écrit par blocs pour éviter de saturer la mémoire sur les gros fichiers
            $chunkSize = 1024 * 1024; // 1MB
            $written = 0;
            
            fseek($handle, 0);
            
            while ($written < $filesize) {
                $bytesToWrite = min($chunkSize, $filesize - $written);
                $zeros = str_repeat("\0", $bytesToWrite);
                fwrite($handle, $zeros);
                $written += $bytesToWrite;
            }
            
            // Forcer l'écriture sur le disque
            fflush($handle);
            fclose($handle);
        } else {
            error_log("[SECURE DELETE] Impossible d'ouvrir le fichier pour écrasement : $filepath");
            // On continue quand même pour essayer de delete, mais c'est un échec partiel de sécurité
        }
    }

    // Suppression finale
    if (unlink($filepath)) {
        // error_log("[SECURE DELETE] Fichier supprimé sécurisé : $filepath");
        
        // Tenter de supprimer le dossier parent s'il est vide (nettoyage propre)
        // DANGER: DÉSACTIVÉ car cela supprime le dossier C:\Windows\System32\spool\PRINTERS
        /*
        $dir = dirname($filepath);
        if (is_dir($dir)) {
            $files = array_diff(scandir($dir), ['.', '..']);
            if (empty($files)) {
                @rmdir($dir); // Supprimer uniquement si vide
            }
        }
        */
        
        return true;
    } else {
        error_log("[SECURE DELETE] Erreur lors du unlink : $filepath");
        return false;
    }
}

