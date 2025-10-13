<?php
require_once __DIR__ . '/../controler/functions/database.php';

/**
 * Administration principale - Version modulaire
 * Utilise les modules spécialisés pour une meilleure organisation
 */
require_once __DIR__ . '/../controler/func.php';

function Action($conf = null)
{
    $array = [];
    
    // Gestion AJAX pour récupérer un changement (AVANT la connexion à la base)
    if(isset($_GET['ajax']) && $_GET['ajax'] === 'get_change' && isset($_GET['id'])) {
        try {
            require_once __DIR__ . '/admin/ChangesManager.php';
            $changesManager = new ChangesManager();
            $change_id = intval($_GET['id']);
            $change = $changesManager->getChange($change_id);
            
            if ($change) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'change' => $change]);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Changement non trouvé']);
            }
            exit;
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
    
    // Gestion AJAX pour récupérer le type de machine (toner/encre) - AVANT la connexion à la base
    if(isset($_GET['ajax']) && $_GET['ajax'] === 'get_machine_type' && isset($_GET['machine'])) {
        try {
            $con = pdo_connect();
            $db = pdo_connect();
            $machine = $_GET['machine'];
            
            // Déterminer le type et l'ID de machine selon le nom
            $machine_type = '';
            $machine_id = 0;
            
            if (strtolower($machine) === 'comcolor') {
                $machine_type = 'photocop';
                $machine_id = 1;
            } elseif (strtolower($machine) === 'konika') {
                $machine_type = 'photocop';
                $machine_id = 2;
            } else {
                // Pour les duplicopieurs (Ricoh dx4545, etc.)
                $machine_type = 'dupli';
                $machine_id = 1;
            }
            
            // Vérifier d'abord si c'est un duplicopieur
            if ($machine_type === 'dupli') {
                $machineType = 'duplicopieur';
            } else {
                // Pour les photocopieurs, vérifier le type_encre dans la table photocopieurs
                $query = $db->prepare('SELECT type_encre FROM photocopieurs WHERE marque = ? AND actif = 1');
                $query->execute([$machine]);
                $photocop = $query->fetch(PDO::FETCH_ASSOC);
                
                if ($photocop) {
                    if ($photocop['type_encre'] === 'toner') {
                        $machineType = 'photocop_toner';
                    } else {
                        $machineType = 'photocop_encre';
                    }
                } else {
                    // Fallback : vérifier dans la table prix
                    $query = $db->prepare('SELECT COUNT(*) as count FROM prix WHERE machine_type = ? AND machine_id = ? AND type IN ("tambour", "dev")');
                    $query->execute([$machine_type, $machine_id]);
                    $result = $query->fetch(PDO::FETCH_ASSOC);
                    
                    if ($result['count'] > 0) {
                        $machineType = 'photocop_toner';
                    } else {
                        $machineType = 'photocop_encre';
                    }
                }
            }
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'type' => $machineType]);
            exit;
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
    
    // Gestion AJAX pour récupérer les derniers compteurs d'une machine
    if(isset($_GET['ajax']) && $_GET['ajax'] === 'get_last_counters' && isset($_GET['machine'])) {
        try {
            $con = pdo_connect();
            $machine = $_GET['machine'];
            
            // Pour les photocopieurs, récupérer depuis la table cons
            // Détecter dynamiquement si c'est une photocopieuse
            $db = pdo_connect();
            $query = $db->prepare('SELECT COUNT(*) as count FROM photocop WHERE marque = ?');
            $query->execute([$machine]);
            $result = $query->fetch(PDO::FETCH_ASSOC);
            $isPhotocopier = $result['count'] > 0;
            
            if ($isPhotocopier) {
                $db = pdo_connect();
                $query = $db->prepare('SELECT nb_p, nb_m FROM cons WHERE machine = ? ORDER BY date DESC LIMIT 1');
                $query->execute([$machine]);
                $result = $query->fetch(PDO::FETCH_ASSOC);
                
                if ($result) {
                    $counters = [
                        'master_av' => $result['nb_m'],
                        'passage_av' => $result['nb_p']
                    ];
                } else {
                    $counters = [
                        'master_av' => 0,
                        'passage_av' => 0
                    ];
                }
            } else {
                // Pour les duplicopieurs, utiliser la fonction existante
                $counters = get_last_number($machine);
            }
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'counters' => $counters]);
            exit;
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
    
    // Vérification de l'authentification
    if(!isset($_SESSION['user']) && isset($_POST['password'])) {
        try {
            $db = pdo_connect();
            $query = $db->prepare('SELECT password_hash FROM admin_passwords WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1');
            $query->execute();
            $result = $query->fetch(PDO::FETCH_ASSOC);
            
            if ($result && password_verify($_POST['password'], $result['password_hash'])) {
                $_SESSION['user'] = "1";
            } else {
                $array['login_error'] = 'Mot de passe incorrect. Veuillez réessayer.';
            }
        } catch (Exception $e) {
            // Fallback vers l'ancien système en cas d'erreur
            $hash = '$2y$10$WVQNZ603f.6GpQSmITk1wOMztCPwzHXZyGKANw1q3dwVSuMVch7B.';
            if (password_verify($_POST['password'], $hash)) {
                $_SESSION['user'] = "1";
            } else {
                $array['login_error'] = 'Mot de passe incorrect. Veuillez réessayer.';
            }
        }
    }
    
    if(isset($_SESSION['user'])) {
        // Inclure les modules d'administration
        // Utiliser SQLiteDatabaseManager pour l'application SQLite
        if (isset($conf['db_type']) && $conf['db_type'] === 'sqlite') {
            require_once __DIR__ . '/admin/SQLiteDatabaseManager.php';
        } else {
            require_once __DIR__ . '/admin/DatabaseManager.php';
        }
        require_once __DIR__ . '/admin/BackupManager.php';
        require_once __DIR__ . '/admin/SiteManager.php';
        require_once __DIR__ . '/admin/PriceManager.php';
        require_once __DIR__ . '/admin/TirageManager.php';
        require_once __DIR__ . '/admin/NewsManager.php';
        require_once __DIR__ . '/admin/StatsManager.php';
        require_once __DIR__ . '/admin/EditManager.php';
        require_once __DIR__ . '/admin/MachineManager.php';
        require_once __DIR__ . '/admin/ChangesManager.php';
require_once __DIR__ . '/../controler/func.php';
require_once __DIR__ . '/../controler/conf.php';
        
        // Initialiser les managers
        if ($conf === null) {
            error_log("ERREUR ADMIN: Configuration est NULL!");
            require_once __DIR__ . '/../controler/functions/error_handler.php';
            $result = show_error_page(
                "La configuration de la base de données n'a pas été chargée correctement. Cela peut arriver si les fichiers de configuration sont manquants ou corrompus.",
                "Configuration non définie",
                __FILE__,
                __LINE__,
                null,
                "Vérifiez que le fichier controler/conf.php existe et contient les bonnes informations de connexion à la base de données."
            );
            error_log("ERREUR ADMIN: Page d'erreur générée, longueur: " . strlen($result));
            return $result;
        }
        
        error_log("ADMIN: Configuration chargée, type = " . ($conf['db_type'] ?? 'non défini'));
        
        // Initialiser le tableau de données
        $array = array();
        // Utiliser la classe appropriée selon le type de base de données
        if (isset($conf['db_type']) && $conf['db_type'] === 'sqlite') {
            $dbManager = new SQLiteDatabaseManager($conf);
        } else {
            $dbManager = new AdminDatabaseManager($conf);
        }
        $backupManager = new BackupManager($conf);
        $siteManager = new SiteManager($conf);
        $priceManager = new PriceManager($conf);
        $tirageManager = new TirageManager($conf);
        $newsManager = new NewsManager($conf);
        $statsManager = new StatsManager($conf);
        $editManager = new EditManager($conf);
        $machineManager = new AdminMachineManager($conf);
        $changesManager = new ChangesManager($conf);
        
        // Récupérer la base de données actuelle
        $array['current_db'] = $dbManager->getCurrentDatabase();
        
        // Stocker les variables dans $GLOBALS pour la vue
        $GLOBALS['model_variables'] = $array;
        
        // Gestion des bases de données
        if(array_key_exists('admin', $_GET)) {
            // Gestion des actions POST
            if($_SERVER['REQUEST_METHOD'] === 'POST') {
                $array = handlePostActions($array, $dbManager, $backupManager, $siteManager, $priceManager, $tirageManager, $newsManager, $statsManager, $editManager, $machineManager, $changesManager);
            }
            
            // Retourner la vue appropriée selon la section
            if(isset($_GET['changes'])) {
                return handleChangesSection($array);
            } elseif(isset($_GET['aide'])) {
                return handleAideSection($array);
            } elseif(isset($_GET['bdd'])) {
                return handleDatabaseSection($array, $dbManager, $backupManager);
            } elseif(isset($_GET['machines'])) {
                // Gestion de l'action de renommage
                if(isset($_GET['action']) && $_GET['action'] === 'rename' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                    try {
                        $old_name = $_POST['old_name'] ?? '';
                        $new_name = $_POST['new_name'] ?? '';
                        
                        if(empty($old_name) || empty($new_name)) {
                            header('Content-Type: application/json');
                            echo json_encode(['error' => 'Nom manquant']);
                            exit;
                        }
                        
                        $result = $machineManager->renameMachine($old_name, $new_name);
                        
                        header('Content-Type: application/json');
                        echo json_encode($result);
                        exit;
                        
                    } catch (Exception $e) {
                        header('Content-Type: application/json');
                        echo json_encode(['error' => 'Erreur: ' . $e->getMessage()]);
                        exit;
                    }
                }
                
                // Obtenir la liste des machines
                try {
                    $machines = $machineManager->getMachines();
                    $array['machines'] = $machines;
                } catch (Exception $e) {
                    $array['machines'] = [];
                }
                return template(__DIR__ . "/../view/admin.machines.html.php", $array);
            } elseif(isset($_GET['prix'])) {
                return handlePriceSection($array, $priceManager);
            } elseif(isset($_GET['tirages'])) {
                return handleTirageSection($array, $tirageManager);
            } elseif(isset($_GET['news'])) {
                return handleNewsSection($array, $newsManager);
            } elseif(isset($_GET['stats'])) {
                return handleStatsSection($array, $statsManager);
            } elseif(isset($_GET['edit'])) {
                return handleEditSection($array, $editManager);
            } elseif(isset($_GET['changes'])) {
                return handleChangesSection($array);
            } elseif(isset($_GET['emails'])) {
                return handleEmailsSection($array, $siteManager);
            } elseif(isset($_GET['mots'])) {
                return handlePasswordSection($array, $siteManager);
            } else {
                return handleMainAdminSection($array, $siteManager);
            }
        }
    }
    
    // Page de connexion si non authentifié
    return template("../view/admin.login.html.php", $array);
}

/**
 * Gérer les actions POST
 */
function handlePostActions($array, $dbManager, $backupManager, $siteManager, $priceManager, $tirageManager, $newsManager, $statsManager, $editManager, $machineManager, $changesManager) {
    // Création d'une nouvelle base de données
    if(isset($_POST['create_db']) && isset($_POST['db_name'])) {
        $result = $dbManager->createDatabase(
            $_POST['db_name'],
            $_POST['db_type'] ?? 'dev',
            $_POST['db_template'] ?? ''
        );
        
        if (isset($result['error'])) {
            $array['db_error'] = $result['error'];
        } else {
            $array['db_created'] = $result['success'];
        }
    }
    
    // Changer de base de données
    if(isset($_POST['switch_db'])) {
        $result = $dbManager->switchDatabase($_POST['switch_db']);
        
        if (isset($result['error'])) {
            $array['db_error'] = $result['error'];
        } else {
            $array['db_switched'] = $result['success'];
            $array['current_db'] = $result['current_db'];
        }
    }
    
    // Supprimer une base de données
    if(isset($_POST['delete_db'])) {
        $result = $dbManager->deleteDatabase($_POST['delete_db']);
        
        if (isset($result['error'])) {
            $array['db_error'] = $result['error'];
        } else {
            $array['db_deleted'] = $result['success'];
        }
    }
    
    // Renommer une base de données
    if(isset($_POST['rename_db']) && isset($_POST['old_db_name']) && isset($_POST['new_db_name'])) {
        $result = $dbManager->renameDatabase($_POST['old_db_name'], $_POST['new_db_name']);
        
        if (isset($result['error'])) {
            $array['db_error'] = $result['error'];
        } else {
            $array['db_renamed'] = $result['success'];
        }
    }
    
    // Créer une sauvegarde
    if(isset($_POST['backup_db'])) {
        $backup_name = $_POST['backup_name'] ?? '';
        $result = $backupManager->createBackup($backup_name);
        
        if (isset($result['error'])) {
            $array['backup_error'] = $result['error'];
        } else {
            $array['backup_created'] = $result['success'];
        }
    }
    
    // Restaurer une sauvegarde
    if(isset($_POST['restore_db']) && isset($_POST['backup_file'])) {
        $result = $backupManager->restoreBackup($_POST['backup_file']);
        
        if (isset($result['error'])) {
            $array['restore_error'] = $result['error'];
        } else {
            $array['db_restored'] = $result['success'];
        }
    }
    
    // Supprimer une sauvegarde
    if(isset($_POST['delete_backup']) && isset($_POST['backup_file'])) {
        $result = $backupManager->deleteBackup($_POST['backup_file']);
        
        if (isset($result['error'])) {
            $array['backup_delete_error'] = $result['error'];
        } else {
            $array['backup_deleted'] = $result['success'];
        }
    }
    
    // Upload d'une sauvegarde
    if(isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
        $result = $backupManager->uploadBackup($_FILES['backup_file']);
        
        if (isset($result['error'])) {
            $array['upload_error'] = $result['error'];
        } else {
            $array['backup_uploaded'] = $result['success'];
        }
    }
    
    // Vider la base de données
    if(isset($_POST['empty_db'])) {
        $result = $dbManager->emptyDatabase();
        
        if (isset($result['error'])) {
            $array['db_error'] = $result['error'];
        } else {
            $array['db_emptied'] = $result['success'];
        }
    }
    
    // Réinitialiser la base de données
    if(isset($_POST['reset_db'])) {
        $result = $dbManager->resetDatabase();
        
        if (isset($result['error'])) {
            $array['db_error'] = $result['error'];
        } else {
            $array['db_reset'] = $result['success'];
        }
    }
    
    // Mettre à jour les paramètres du site
    if(isset($_POST['update_site_settings'])) {
        $settings = array(
            'show_mailing_list' => isset($_POST['show_mailing_list']) ? '1' : '0',
            'enable_counter_mode' => isset($_POST['enable_counter_mode']) ? '1' : '0',
            'enable_manual_mode' => isset($_POST['enable_manual_mode']) ? '1' : '0'
        );
        
        $result = $siteManager->updateSiteSettings($settings);
        
        if (isset($result['error'])) {
            $array['settings_error'] = $result['error'];
        } else {
            $array['settings_updated'] = true;
            // Récupérer les paramètres mis à jour pour les afficher dans le formulaire
            $updated_settings = $siteManager->getCurrentSettings();
            $array = array_merge($array, $updated_settings);
        }
    }
    
    
    
    // Supprimer un email
    if(isset($_POST['delmail'])) {
        $result = $siteManager->deleteEmail($_POST['delmail']);
        
        if (isset($result['error'])) {
            $array['email_error'] = $result['error'];
        } else {
            $array['email_deleted'] = $result['success'];
        }
    }
    
    // Supprimer tous les emails
    if(isset($_POST['delete_all_emails'])) {
        $result = $siteManager->deleteAllEmails();
        
        if (isset($result['error'])) {
            $array['email_error'] = $result['error'];
        } else {
            $array['email_deleted'] = $result['success'];
        }
    }
    
    // Gestion des mots de passe
    if(isset($_POST['change_password'])) {
        $result = changePassword($_POST['current_password'], $_POST['new_password'], $_POST['confirm_password']);
        
        if (isset($result['error'])) {
            $array['password_error'] = $result['error'];
        } else {
            $array['password_success'] = $result['success'];
        }
    }
    
    // Gestion des prix
    if(isset($_POST['prix_pack'])) {
        $result = $priceManager->insertPrice($_POST['machine'], $_POST['type'], $_POST['prix_pack'], $_POST['prix_unite']);
        $array['price_updated'] = true;
    }
    
    if(isset($_POST['papier_A3'])) {
        // Le prix A3 est stocké comme prix A4 (A3 = A4 * 2)
        $result = $priceManager->insertPapier($_POST['papier_A3']/2);
        $array['papier_updated'] = true;
    }
    
    if(isset($_POST['papier_A4'])) {
        $result = $priceManager->insertPapier($_POST['papier_A4']);
        $array['papier_updated'] = true;
    }
    
    // Gestion des changements de consommables
    if(isset($_POST['action']) && $_POST['action'] === 'add_change') {
        $result = $changesManager->addChange($_POST);
        if (isset($result['error'])) {
            $array['change_error'] = $result['error'];
        } else {
            $array['change_success'] = $result['success'];
        }
    }
    
    if(isset($_POST['action']) && $_POST['action'] === 'edit_change') {
        error_log("DEBUG: Tentative de modification du changement ID: " . $_POST['id']);
        $result = $changesManager->updateChange($_POST['id'], $_POST);
        error_log("DEBUG: Résultat de la modification: " . print_r($result, true));
        if (isset($result['error'])) {
            $array['change_error'] = $result['error'];
        } else {
            $array['change_success'] = $result['success'] ?? $result['message'] ?? 'Changement modifié avec succès';
        }
    }
    
    if(isset($_POST['action']) && $_POST['action'] === 'delete_change') {
        $result = $changesManager->deleteChange($_POST['id']);
        if (isset($result['error'])) {
            $array['change_error'] = $result['error'];
        } else {
            $array['change_success'] = $result['success'];
        }
    }
    
    // Gestion des tirages (marquer comme payé)
    if(isset($_POST['ids']) && isset($_POST['table'])) {
        $table = $_POST['table'];
        $ids = $_POST['ids'];
        
        // Vérifier si les données sont valides
        if (is_array($ids) && !empty($table)) {
            // Iterate over ids and mark as paid
            foreach ($ids as $id) {
                $tirageManager->marquerCommePaye($id, $table);
            }
            
            // Retourner une réponse JSON
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success']);
            
            // Terminer le script PHP
            exit();
        } else {
            // Si les données ne sont pas valides, afficher un message d'erreur
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Données invalides']);
            
            // Terminer le script PHP
            exit();
        }
    }
    
    // Gestion des news
    if(isset($_POST['titre'])) {
        $titre = htmlspecialchars($_POST['titre'], ENT_QUOTES, 'UTF-8');
        $texte = $_POST['texte']; // Ne pas échapper le HTML pour le WYSIWYG
        
        if(isset($_POST['id2'])) {
            // Modification d'une news existante
            $id = ceil($_POST['id2']);
            $result = $newsManager->updateNews($titre, $texte, $id);
            if($result) {
                header('Location: ?admin&news');
                exit();
            }
        } else {
            // Création d'une nouvelle news
            $result = $newsManager->insertNews($titre, $texte);
            if($result) {
                header('Location: ?admin&news');
                exit();
            }
        }
    }
    
    if(isset($_POST['id']) && !isset($_POST['titre'])) {
        $id = ceil($_POST['id']);
        if(isset($_POST['singlebutton'])) {
            // Suppression d'une news
            $result = $newsManager->deleteNews($id);
            if($result) {
                header('Location: ?admin&news');
                exit();
            }
        } else {
            // Récupérer la news pour édition
            $array['new_edit'] = $newsManager->getNews($id);
        }
    }
    
    // Gestion des statistiques
    if(isset($_POST['update_stats_text'])) {
        $statsManager->updateStatsIntroText($_POST['stats_intro_text']);
        $array['stats_text_updated'] = true;
    }
    
    // Gestion de l'édition des tirages
    if(isset($_POST['delete'])) {
        $machine = $_GET['table'];
        $id = $_GET['edit'];
        
        try {
            $editManager->deleteTirage($id, $machine);
            // Rediriger vers la liste des tirages après suppression
            $array['redirect_url'] = 'index.php?admin&tirages';
            $array['tirage_deleted'] = true;
        } catch (Throwable $e) {
            // Afficher une page d'erreur simple au lieu d'une page blanche
            ?>
            <!DOCTYPE html>
            <html lang="fr">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title>Erreur - Duplicator</title>
                <link href="css/bootstrap.css" rel="stylesheet" type="text/css">
                <link href="css/font-awesome.min.css" rel="stylesheet" type="text/css">
            </head>
            <body style="padding-bottom: 60px;">
                <div class="navbar navbar-default navbar-static-top">
                    <div class="container">
                        <div class="navbar-header">
                            <a class="navbar-brand" href="?accueil">
                                <span><big>Duplicator.</big></span>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="container" style="margin-top: 50px;">
                    <div class="row">
                        <div class="col-md-8 col-md-offset-2">
                            <div class="alert alert-danger">
                                <h2><i class="fa fa-exclamation-triangle"></i> Une erreur s'est produite</h2>
                                <p><strong>Message d'erreur :</strong> <?= htmlspecialchars($e->getMessage()) ?></p>
                                <p><strong>Fichier :</strong> <?= htmlspecialchars($e->getFile()) ?></p>
                                <p><strong>Ligne :</strong> <?= htmlspecialchars($e->getLine()) ?></p>
                                <p><strong>Heure :</strong> <?= date('Y-m-d H:i:s') ?></p>
                                
                                <hr>
                                <p>
                                    <a href="?accueil" class="btn btn-primary">
                                        <i class="fa fa-home"></i> Retour à l'accueil
                                    </a>
                                    <button onclick="history.back()" class="btn btn-default">
                                        <i class="fa fa-arrow-left"></i> Page précédente
                                    </button>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="navbar navbar-default navbar-fixed-bottom">
                    <div class="container">
                        <p class="navbar-text text-center">Codé avec ❤️ pour Duplicator</p>
                    </div>
                </div>
            </body>
            </html>
            <?php
            exit;
        }
    }
    
    if(isset($_POST['save']) && !isset($_POST['titre'])) {
        $machine = $_GET['table'];
        $id = $_GET['edit'];
        $editManager->updateTirage($id, $_POST, $machine);
        $array['tirage_updated'] = true;
        
        // Recharger le tirage après la mise à jour
        $array['tirage'] = $editManager->getTirage($id, $machine);
    }
    
    // Gestion des machines
    if(isset($_POST['add_machine'])) {
        $result = $machineManager->addMachine($_POST);
        
        if (isset($result['error'])) {
            $array['machine_error'] = $result['error'];
        } else {
            $array['machine_created'] = $result['success'];
        }
    }
    
    if(isset($_POST['delete_machine'])) {
        $result = $machineManager->deleteMachine($_POST['delete_machine']);
        
        if (isset($result['error'])) {
            $array['machine_error'] = $result['error'];
        } else {
            $array['machine_deleted'] = $result['success'];
        }
    }
    
    if(isset($_POST['update_machine'])) {
        $result = $machineManager->updateMachine($_POST);
        
        if (isset($result['error'])) {
            $array['machine_error'] = $result['error'];
        } else {
            $array['machine_updated'] = $result['success'];
        }
    }
    
    // Gestion de la suppression multiple de tirages
    if(isset($_POST['action']) && $_POST['action'] === 'delete_selected') {
        if(isset($_POST['delete_ids']) && isset($_POST['delete_machines'])) {
            $tirageManager = new TirageManager($conf);
            $result = $tirageManager->deleteSelectedTirages($_POST['delete_ids'], $_POST['delete_machines']);
            
            if (count($result['errors']) > 0) {
                $array['delete_error'] = implode('<br>', $result['errors']);
            } else {
                $array['delete_success'] = $result['deleted_count'] . ' tirage(s) supprimé(s) avec succès.';
            }
        }
    }
    
    // Gestion des aides
    if(isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if($action === 'add' && isset($_POST['machine']) && isset($_POST['contenu_aide'])) {
            $result = addAide($_POST['machine'], $_POST['contenu_aide']);
            if (isset($result['error'])) {
                $array['aide_error'] = $result['error'];
            } else {
                $array['aide_created'] = $result['success'];
            }
        }
        
        if($action === 'edit' && isset($_POST['aide_id']) && isset($_POST['machine']) && isset($_POST['contenu_aide'])) {
            $result = updateAide($_POST['aide_id'], $_POST['machine'], $_POST['contenu_aide']);
            if (isset($result['error'])) {
                $array['aide_error'] = $result['error'];
            } else {
                $array['aide_updated'] = $result['success'];
            }
        }
        
        if($action === 'delete' && isset($_POST['aide_id'])) {
            $result = deleteAide($_POST['aide_id']);
            if (isset($result['error'])) {
                $array['aide_error'] = $result['error'];
            } else {
                $array['aide_deleted'] = $result['success'];
            }
        }
    }
    
    // Récupération des données d'une machine pour l'édition (AJAX)
    if(isset($_GET['get_machine_data'])) {
        $machineData = $machineManager->getMachineData($_GET['get_machine_data']);
        header('Content-Type: application/json');
        echo json_encode($machineData);
        exit;
    }
    
    return $array;
}

/**
 * Gérer la section des bases de données
 */
function handleDatabaseSection($array, $dbManager, $backupManager) {
    // Obtenir la liste des bases de données
    $array['databases'] = $dbManager->getDatabasesList();
    
    // Obtenir la liste des sauvegardes
    $array['backups'] = $backupManager->getBackupsList();
    
    // Stocker les variables dans $GLOBALS pour les préserver
    $GLOBALS['model_variables'] = $array;
    
    // Extraire les variables pour la vue
    extract($array);
    
    // Capturer le contenu de la vue
    ob_start();
    include("../view/admin.bdd.html.php");
    $content = ob_get_contents();
    ob_end_clean();
    
    return $content;
}


/**
 * Gérer la section principale d'administration
 */
function handleMainAdminSection($array, $siteManager) {
    // Obtenir les statistiques
    $array['stats'] = $siteManager->getStats();
    
    // Obtenir les paramètres actuels
    $settings = $siteManager->getCurrentSettings();
    $array = array_merge($array, $settings);
    
    // Obtenir les emails
    $array['emails'] = $siteManager->getEmails();
    
    return template("../view/admin.html.php", $array);
}

/**
 * Gérer la section des prix
 */
function handlePriceSection($array, $priceManager) {
    // Obtenir toutes les données de prix
    $priceData = $priceManager->getAllPriceData();
    $array = array_merge($array, $priceData);
    
    // Stocker les variables dans $GLOBALS pour les préserver
    $GLOBALS['model_variables'] = $array;
    
    return template("../view/admin.prix.html.php", $array);
}

/**
 * Gérer la section des tirages
 */
function handleTirageSection($array, $tirageManager) {
    // Obtenir toutes les données de tirages
    $tirageData = $tirageManager->getAllTirageData();
    $array = array_merge($array, $tirageData);
    
    // Stocker les variables dans $GLOBALS pour les préserver
    $GLOBALS['model_variables'] = $array;
    
    return template("../view/admin.tirage.html.php", $array);
}

/**
 * Gérer la section des news
 */
function handleNewsSection($array, $newsManager) {
    // Obtenir toutes les données de news
    $newsData = $newsManager->getAllNewsData();
    $array = array_merge($array, $newsData);
    
    // Stocker les variables dans $GLOBALS pour les préserver
    $GLOBALS['model_variables'] = $array;
    
    return template("../view/admin.news.html.php", $array);
}

/**
 * Gérer la section des statistiques
 */
function handleStatsSection($array, $statsManager) {
    // Obtenir toutes les données de statistiques
    $statsData = $statsManager->getAllStatsData();
    $array = array_merge($array, $statsData);
    
    // Stocker les variables dans $GLOBALS pour les préserver
    $GLOBALS['model_variables'] = $array;
    
    return template("../view/admin.stats.html.php", $array);
}

/**
 * Gérer la section d'édition des tirages
 */
function handleEditSection($array, $editManager) {
    // Si on a une redirection après suppression, rediriger
    if(isset($array['redirect_url'])) {
        echo '<script>window.location.href = "' . $array['redirect_url'] . '";</script>';
        return '';
    }
    
    $id = $_GET['edit'];
    $machine = $_GET['table'];
    
    // Obtenir toutes les données d'édition
    $editData = $editManager->getEditData($id, $machine);
    $array = array_merge($array, $editData);
    
    // Stocker les variables dans $GLOBALS pour les préserver
    $GLOBALS['model_variables'] = $array;
    
    return template("../view/admin.edit.html.php", $array);
}


/**
 * Ajouter un changement
 */
function addChange($data) {
    try {
        $con = pdo_connect();
        $db = pdo_connect();
        
        $machine = $data['machine'];
        $type = $data['type'];
        $nb_p = intval($data['nb_p']);
        $nb_m = intval($data['nb_m']);
        $tambour = $data['tambour'] ?? '';
        $date = is_numeric($data['date']) ? $data['date'] : strtotime($data['date']);
        
        $query = $db->prepare('INSERT INTO cons (date, machine, type, nb_p, nb_m, tambour) VALUES (?, ?, ?, ?, ?, ?)');
        $query->execute([$date, $machine, $type, $nb_p, $nb_m, $tambour]);
        
        return ['success' => "Changement ajouté avec succès"];
        
    } catch (Exception $e) {
        return ['error' => "Erreur lors de l'ajout : " . $e->getMessage()];
    }
}

/**
 * Mettre à jour un changement
 */
function updateChange($data) {
    try {
        $con = pdo_connect();
        $db = pdo_connect();
        
        $machine = $data['edit_machine'];
        $type = $data['edit_type'];
        $counter = $data['edit_counter'];
        $new_date = is_numeric($data['edit_date']) ? $data['edit_date'] : strtotime($data['edit_date']);
        $old_date = $data['edit_old_date'];
        
        $query = $db->prepare('UPDATE cons SET date = ?, nb_p = ? WHERE machine = ? AND type = ? AND date = ?');
        $query->execute([$new_date, $counter, $machine, $type, $old_date]);
        
        return ['success' => "Changement mis à jour avec succès"];
        
    } catch (Exception $e) {
        return ['error' => "Erreur lors de la mise à jour : " . $e->getMessage()];
    }
}

/**
 * Supprimer un changement
 */
function deleteChange($data) {
    try {
        $con = pdo_connect();
        $db = pdo_connect();
        
        $id = $data['delete_id'];
        
        // Utiliser l'ID unique pour une suppression précise
        $query = $db->prepare('DELETE FROM cons WHERE id = ?');
        $query->execute([$id]);
        
        $deleted_count = $query->rowCount();
        
        if ($deleted_count === 0) {
            return ['error' => "Aucune entrée trouvée à supprimer"];
        } elseif ($deleted_count > 1) {
            return ['error' => "Plusieurs entrées ont été supprimées par erreur"];
        }
        
        return ['success' => "Changement supprimé avec succès"];
        
    } catch (Exception $e) {
        return ['error' => "Erreur lors de la suppression : " . $e->getMessage()];
    }
}

/**
 * Récupérer les derniers changements pour une machine
 */
function getLastChanges($machine_name) {
    try {
        $con = pdo_connect();
        $db = pdo_connect();
        
        // Pour les duplicopieurs, utiliser le type (a3/a4) au lieu du nom
        $search_name = $machine_name;
        if ($machine_name === 'Ricoh dx4545') {
            $search_name = 'dupli'; // ou 'a4' selon le type
        }
        
        $query = $db->prepare('SELECT id, type, nb_p as counter, date FROM cons WHERE machine = ? ORDER BY date DESC LIMIT 10');
        $query->execute([$search_name]);
        
        $changes = [];
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $changes[] = $row;
        }
        
        return $changes;
        
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Obtenir la classe CSS pour le type
 */
function getTypeClass($type) {
    $classes = [
        'noir' => 'toner', 'cyan' => 'toner', 'magenta' => 'toner', 'jaune' => 'toner',
        'noire' => 'encre', 'bleue' => 'encre', 'rouge' => 'encre',
        'master' => 'master', 'encre' => 'encre', 'tambour' => 'tambour', 'dev' => 'dev'
    ];
    return $classes[$type] ?? 'default';
}

/**
 * Obtenir l'icône pour le type
 */
function getTypeIcon($type) {
    $icons = [
        'noir' => 'fa fa-circle', 'cyan' => 'fa fa-circle', 
        'magenta' => 'fa fa-circle', 'jaune' => 'fa fa-circle',
        'noire' => 'fa fa-tint', 'bleue' => 'fa fa-tint', 
        'rouge' => 'fa fa-tint', 'jaune_encre' => 'fa fa-tint',
        'master' => 'fa fa-layer-group', 'encre' => 'fa fa-tint',
        'tambour' => 'fa fa-cog', 'dev' => 'fa fa-microchip'
    ];
    return $icons[$type] ?? 'fa fa-question';
}

/**
 * Obtenir le label pour le type
 */
function getTypeLabel($type) {
    $labels = [
        'noir' => 'Toner Noir', 'cyan' => 'Toner Cyan', 'magenta' => 'Toner Magenta', 'jaune' => 'Toner Jaune',
        'noire' => 'Encre Noire', 'bleue' => 'Encre Bleue', 'rouge' => 'Encre Rouge', 'jaune_encre' => 'Encre Jaune',
        'master' => 'Master', 'encre' => 'Encre', 'tambour' => 'Tambour', 'dev' => 'Unité de développement'
    ];
    return $labels[$type] ?? $type;
}

/**
 * Obtenir la couleur pour le type
 */
function getTypeColor($type) {
    $colors = [
        'noir' => '#000000', 'cyan' => '#00BFFF', 'magenta' => '#FF1493', 'jaune' => '#FFD700',
        'noire' => '#000000', 'bleue' => '#0066CC', 'rouge' => '#DC143C', 'jaune_encre' => '#FFD700',
        'master' => '#DC143C', 'encre' => '#0066CC', 'tambour' => '#6A5ACD', 'dev' => '#FF8C00'
    ];
    return $colors[$type] ?? '#666666';
}

/**
 * Gérer la section des aides
 */
function handleAideSection($array) {
    try {
        $con = pdo_connect();
        $db = pdo_connect();
        
        // Gestion de la requête AJAX pour récupérer l'ID de l'aide par machine
        if (isset($_GET['get_aide_id'])) {
            $machine = $_GET['get_aide_id'];
            $query = $db->prepare('SELECT id FROM aide_machines WHERE machine = ?');
            $query->execute([$machine]);
            $aide = $query->fetch(PDO::FETCH_ASSOC);
            
            if ($aide) {
                echo $aide['id'];
            } else {
                echo '';
            }
            exit;
        }
        
        // Gestion de la requête AJAX pour récupérer l'aide par machine
        if (isset($_GET['get_aide_by_machine'])) {
            $machine = $_GET['get_aide_by_machine'];
            $query = $db->prepare('SELECT contenu_aide FROM aide_machines WHERE machine = ?');
            $query->execute([$machine]);
            $aide = $query->fetch(PDO::FETCH_ASSOC);
            
            header('Content-Type: text/plain; charset=utf-8');
            if ($aide) {
                echo $aide['contenu_aide'];
            } else {
                echo '';
            }
            exit;
        }
        
        // Gestion de la requête AJAX pour récupérer le contenu d'une aide
        if (isset($_GET['get_content']) && is_numeric($_GET['get_content'])) {
            $id = (int)$_GET['get_content'];
            $query = $db->prepare('SELECT contenu_aide FROM aide_machines WHERE id = ?');
            $query->execute([$id]);
            $aide = $query->fetch(PDO::FETCH_ASSOC);
            
            if ($aide) {
                header('Content-Type: text/plain; charset=utf-8');
                echo $aide['contenu_aide'];
                exit;
            } else {
                http_response_code(404);
                echo 'Aide non trouvée';
                exit;
            }
        }
        
        // Récupérer toutes les aides
        $query = $db->query('SELECT * FROM aide_machines ORDER BY machine');
        $aides = $query->fetchAll(PDO::FETCH_ASSOC);
        $array['aides'] = $aides;
        
        // Récupérer la liste des machines pour le formulaire
        $machines = [];
        
        // Récupérer les duplicopieurs
        $query = $db->query('SELECT marque, modele FROM duplicopieurs WHERE actif = 1');
        $duplicopieurs = $query->fetchAll(PDO::FETCH_ASSOC);
        foreach ($duplicopieurs as $dup) {
            $machine_name = $dup['marque'];
            if ($dup['marque'] !== $dup['modele']) {
                $machine_name = $dup['marque'] . ' ' . $dup['modele'];
            }
            $machines[] = $machine_name;
        }
        
        // Récupérer les photocopieurs
        $query = $db->query('SELECT marque, modele FROM photocopieurs WHERE actif = 1');
        $photocopieurs = $query->fetchAll(PDO::FETCH_ASSOC);
        foreach ($photocopieurs as $photocop) {
            $machine_name = $photocop['marque'];
            if ($photocop['marque'] !== $photocop['modele']) {
                $machine_name = $photocop['marque'] . ' ' . $photocop['modele'];
            }
            $machines[] = $machine_name;
        }
        
        sort($machines);
        $array['machines'] = $machines;
        
        // Gérer les messages
        if (isset($array['aide_created'])) {
            $array['message'] = ['type' => 'success', 'text' => 'Aide créée avec succès !'];
        } elseif (isset($array['aide_updated'])) {
            $array['message'] = ['type' => 'success', 'text' => 'Aide mise à jour avec succès !'];
        } elseif (isset($array['aide_deleted'])) {
            $array['message'] = ['type' => 'success', 'text' => 'Aide supprimée avec succès !'];
        } elseif (isset($array['aide_error'])) {
            $array['message'] = ['type' => 'danger', 'text' => $array['aide_error']];
        }
        
        return template("../view/admin.aide.html.php", $array);
        
    } catch (Exception $e) {
        $array['message'] = ['type' => 'danger', 'text' => 'Erreur : ' . $e->getMessage()];
        return template("../view/admin.aide.html.php", $array);
    }
}

/**
 * Ajouter une aide
 */
function addAide($machine, $contenu) {
    try {
        $con = pdo_connect();
        $db = pdo_connect();
        
        // Vérifier si l'aide existe déjà
        $query = $db->prepare('SELECT COUNT(*) FROM aide_machines WHERE machine = ?');
        $query->execute([$machine]);
        
        if ($query->fetchColumn() > 0) {
            return ['error' => "Une aide existe déjà pour cette machine"];
        }
        
        // Insérer la nouvelle aide
        $query = $db->prepare('INSERT INTO aide_machines (machine, contenu_aide) VALUES (?, ?)');
        $query->execute([$machine, $contenu]);
        
        return ['success' => "Aide créée avec succès pour $machine"];
        
    } catch (Exception $e) {
        return ['error' => "Erreur lors de la création : " . $e->getMessage()];
    }
}

/**
 * Mettre à jour une aide
 */
function updateAide($id, $machine, $contenu) {
    try {
        $con = pdo_connect();
        $db = pdo_connect();
        
        // Vérifier si l'aide existe
        $query = $db->prepare('SELECT COUNT(*) FROM aide_machines WHERE id = ?');
        $query->execute([$id]);
        
        if ($query->fetchColumn() == 0) {
            return ['error' => "Aide non trouvée"];
        }
        
        // Mettre à jour l'aide
        $query = $db->prepare('UPDATE aide_machines SET machine = ?, contenu_aide = ? WHERE id = ?');
        $query->execute([$machine, $contenu, $id]);
        
        return ['success' => "Aide mise à jour avec succès pour $machine"];
        
    } catch (Exception $e) {
        return ['error' => "Erreur lors de la mise à jour : " . $e->getMessage()];
    }
}

/**
 * Supprimer une aide
 */
function deleteAide($id) {
    try {
        $con = pdo_connect();
        $db = pdo_connect();
        
        // Récupérer le nom de la machine avant suppression
        $query = $db->prepare('SELECT machine FROM aide_machines WHERE id = ?');
        $query->execute([$id]);
        $machine = $query->fetchColumn();
        
        if (!$machine) {
            return ['error' => "Aide non trouvée"];
        }
        
        // Supprimer l'aide
        $query = $db->prepare('DELETE FROM aide_machines WHERE id = ?');
        $query->execute([$id]);
        
        return ['success' => "Aide supprimée avec succès pour $machine"];
        
    } catch (Exception $e) {
        return ['error' => "Erreur lors de la suppression : " . $e->getMessage()];
    }
}

/**
 * Gérer la section des changements
 */
function handleChangesSection($array) {
    try {
        // Récupérer la configuration actuelle
        $con = pdo_connect();
        $db = pdo_connect();
        
        // Récupérer tous les changements dans l'ordre chronologique (plus récents en premier)
        $query = $db->query('SELECT * FROM cons ORDER BY date DESC');
        $all_changes = $query->fetchAll(PDO::FETCH_ASSOC);
        
        // Conserver l'ordre chronologique global (pas de groupement par machine)
        $changes_by_machine = [];
        $machine_types = [];
        
        foreach ($all_changes as $change) {
            $machine = $change['machine'];
            if (!isset($changes_by_machine[$machine])) {
                $changes_by_machine[$machine] = [];
            }
            
            // Ajouter tous les changements dans l'ordre chronologique
            $changes_by_machine[$machine][] = $change;
            
            // Déterminer le type de machine basé sur les types de consommables
            if (!isset($machine_types[$machine])) {
                $machine_types[$machine] = 'duplicopieur'; // Par défaut
            }
            
            // Si on trouve des toners colorés, c'est une photocopieuse
            if (in_array($change['type'], ['noir', 'noire', 'jaune', 'rouge', 'bleue', 'cyan', 'magenta'])) {
                $machine_types[$machine] = 'photocopieuse';
            }
        }
        
        $array['changes_by_machine'] = $changes_by_machine;
        $array['machine_types'] = $machine_types;
        
        // Récupérer la liste des machines depuis les tables appropriées
        $machines = [];
        
        // Récupérer les duplicopieurs depuis la table duplicopieurs avec leurs tambours
        $query = $db->query('SELECT marque, modele, tambours FROM duplicopieurs WHERE actif = 1');
        $duplicopieurs_data = $query->fetchAll(PDO::FETCH_ASSOC);
        $duplicopieurs = [];
        $duplicopieurs_tambours = [];
        
        foreach ($duplicopieurs_data as $dup) {
            // Construire le nom correct sans duplication
            $name = $dup['marque'];
            if ($dup['marque'] !== $dup['modele']) {
                $name = $dup['marque'] . ' ' . $dup['modele'];
            }
            $duplicopieurs[] = $name;
            
            // Parser les tambours
            $tambours = [];
            if (!empty($dup['tambours'])) {
                try {
                    $tambours = json_decode($dup['tambours'], true);
                } catch (Exception $e) {
                    $tambours = ['tambour_noir'];
                }
            } else {
                $tambours = ['tambour_noir']; // Fallback pour les anciens duplicopieurs
            }
            $duplicopieurs_tambours[$name] = $tambours;
        }
        
        $machines = array_merge($machines, $duplicopieurs);
        
        // Récupérer les photocopieurs depuis la table photocopieurs
        $query = $db->query('SELECT marque FROM photocopieurs WHERE actif = 1');
        $photocopieurs = $query->fetchAll(PDO::FETCH_COLUMN);
        $machines = array_merge($machines, $photocopieurs);
        
        $array['machines'] = $machines;
        $array['duplicopieurs_tambours'] = $duplicopieurs_tambours;
        
        return template(__DIR__ . "/../view/admin.changes.html.php", $array);
        
    } catch (Exception $e) {
        $array['change_error'] = 'Erreur : ' . $e->getMessage();
        return template(__DIR__ . "/../view/admin.changes.html.php", $array);
    }
}

/**
 * Gérer la section des emails
 */
function handleEmailsSection($array, $siteManager) {
    try {
        // Récupérer la liste des emails
        $array['emails'] = $siteManager->getEmails();
        
        // Récupérer les paramètres actuels
        $settings = $siteManager->getCurrentSettings();
        $array = array_merge($array, $settings);
        
        // Gérer les messages
        if (isset($array['email_deleted'])) {
            $array['message'] = ['type' => 'success', 'text' => 'Email supprimé avec succès !'];
        } elseif (isset($array['email_error'])) {
            $array['message'] = ['type' => 'danger', 'text' => $array['email_error']];
        }
        
        return template("../view/admin.emails.html.php", $array);
        
    } catch (Exception $e) {
        $array['message'] = ['type' => 'danger', 'text' => 'Erreur : ' . $e->getMessage()];
        return template("../view/admin.emails.html.php", $array);
    }
}

/**
 * Gérer la section des mots de passe
 */
function handlePasswordSection($array, $siteManager) {
    try {
        // Gérer les messages
        if (isset($array['password_success'])) {
            $array['message'] = ['type' => 'success', 'text' => $array['password_success']];
        } elseif (isset($array['password_error'])) {
            $array['message'] = ['type' => 'danger', 'text' => $array['password_error']];
        }
        
        return template("../view/admin.mots.html.php", $array);
        
    } catch (Exception $e) {
        $array['message'] = ['type' => 'danger', 'text' => 'Erreur : ' . $e->getMessage()];
        return template("../view/admin.mots.html.php", $array);
    }
}

/**
 * Changer le mot de passe
 */
function changePassword($current_password, $new_password, $confirm_password) {
    try {
        $con = pdo_connect();
        $db = pdo_connect();
        
        // Vérifier que les nouveaux mots de passe correspondent
        if ($new_password !== $confirm_password) {
            return ['error' => 'Les nouveaux mots de passe ne correspondent pas.'];
        }
        
        // Vérifier la longueur du nouveau mot de passe
        if (strlen($new_password) < 6) {
            return ['error' => 'Le nouveau mot de passe doit contenir au moins 6 caractères.'];
        }
        
        // Récupérer le mot de passe actuel
        $query = $db->prepare('SELECT password_hash FROM admin_passwords WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1');
        $query->execute();
        $result = $query->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return ['error' => 'Aucun mot de passe actif trouvé.'];
        }
        
        // Vérifier le mot de passe actuel
        if (!password_verify($current_password, $result['password_hash'])) {
            return ['error' => 'Le mot de passe actuel est incorrect.'];
        }
        
        // Supprimer tous les anciens mots de passe
        $query = $db->prepare('DELETE FROM admin_passwords');
        $query->execute();
        
        // Créer le nouveau hash
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Insérer le nouveau mot de passe
        $query = $db->prepare('INSERT INTO admin_passwords (password_hash, is_active) VALUES (?, 1)');
        $query->execute([$new_hash]);
        
        return ['success' => 'Mot de passe changé avec succès.'];
        
    } catch (Exception $e) {
        return ['error' => 'Erreur lors du changement de mot de passe : ' . $e->getMessage()];
    }
}

?>


