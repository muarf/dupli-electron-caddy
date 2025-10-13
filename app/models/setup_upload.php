<?php
/**
 * Upload et restauration d'une base de données existante
 * Permet d'importer une BDD SQLite au lieu de créer manuellement les machines
 */

require_once __DIR__ . '/../controler/functions/database.php';

function Action($conf = null){
    if ($conf === null) {
        include(__DIR__ . '/../controler/conf.php');
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ?setup&mode=choice');
        exit;
    }
    
    $errors = array();
    
    // Vérifier si un fichier a été uploadé
    if (!isset($_FILES['database_file']) || $_FILES['database_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Erreur lors de l'upload du fichier. Code: " . ($_FILES['database_file']['error'] ?? 'inconnu');
    } else {
        $uploaded_file = $_FILES['database_file'];
        
        // Vérifier l'extension
        $file_ext = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));
        if ($file_ext !== 'sqlite') {
            $errors[] = "Le fichier doit être une base de données SQLite (.sqlite).";
        }
        
        // Vérifier la taille (max 50 MB)
        if ($uploaded_file['size'] > 50 * 1024 * 1024) {
            $errors[] = "Le fichier est trop volumineux (max 50 MB).";
        }
        
        if (empty($errors)) {
            try {
                // Tester si le fichier est une base SQLite valide
                $test_db = new PDO('sqlite:' . $uploaded_file['tmp_name']);
                $test_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Vérifier qu'il y a des tables
                $tables = $test_db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll();
                if (empty($tables)) {
                    $errors[] = "Le fichier SQLite ne contient aucune table.";
                } else {
                    // Obtenir le chemin de la base de données cible
                    $target_db_path = $conf['db_path'];
                    
                    // Créer une sauvegarde de sécurité si une BDD existe déjà
                    if (file_exists($target_db_path)) {
                        $backup_dir = dirname($target_db_path);
                        $safety_backup = $backup_dir . '/duplinew_before_upload_' . date('Y-m-d_H-i-s') . '.sqlite';
                        copy($target_db_path, $safety_backup);
                    }
                    
                    // Copier le fichier uploadé vers la destination
                    if (copy($uploaded_file['tmp_name'], $target_db_path)) {
                        // Vérifier que la BDD a des machines
                        try {
                            $db = pdo_connect();
                            $has_machines = check_machines_exist();
                            
                            if (!$has_machines) {
                                $errors[] = "La base de données importée ne contient aucune machine. Veuillez créer vos machines manuellement.";
                                // Restaurer le backup si nécessaire
                                if (isset($safety_backup) && file_exists($safety_backup)) {
                                    copy($safety_backup, $target_db_path);
                                }
                            } else {
                                // Succès ! Rediriger vers l'accueil
                                $_SESSION['upload_success'] = true;
                                header('Location: ?accueil&setup=restored');
                                exit;
                            }
                        } catch (Exception $e) {
                            $errors[] = "Erreur lors de la vérification de la base : " . $e->getMessage();
                            // Restaurer le backup
                            if (isset($safety_backup) && file_exists($safety_backup)) {
                                copy($safety_backup, $target_db_path);
                            }
                        }
                    } else {
                        $errors[] = "Erreur lors de la copie du fichier vers la destination.";
                    }
                }
                
            } catch (PDOException $e) {
                $errors[] = "Le fichier n'est pas une base de données SQLite valide : " . $e->getMessage();
            }
        }
    }
    
    // En cas d'erreur, sauvegarder et rediriger
    if (!empty($errors)) {
        $_SESSION['setup_errors'] = $errors;
    }
    
    header('Location: ?setup&mode=upload');
    exit;
}
?>

