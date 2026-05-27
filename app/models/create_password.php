<?php
require_once __DIR__ . '/../controler/functions/database.php';
require_once __DIR__ . '/../controler/functions/utilities.php';

if (!function_exists('Action')) {
function Action($conf = null) {
    // Initialiser la configuration si elle n'est pas fournie
    if ($conf === null) {
        include(__DIR__ . '/../controler/conf.php');
    } else {
        // S'assurer que la conf passée est bien dans GLOBALS pour pdo_connect()
        $GLOBALS['conf'] = $conf;
    }
    
    $errors = [];
    $success = false;
    
    try {
        $db = pdo_connect();
        
        // Vérifier s'il existe déjà un mot de passe admin
        $query = $db->prepare('SELECT COUNT(*) as count FROM admin_passwords WHERE is_active = 1');
        $query->execute();
        $result = $query->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['count'] > 0) {
            // Un mot de passe existe déjà, rediriger vers l'accueil
            header('Location: ?accueil');
            exit;
        }
        
        // Traitement du formulaire POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validation du mot de passe
            if (empty($_POST['admin_password'])) {
                $errors[] = "Veuillez définir un mot de passe administrateur.";
            } elseif ($_POST['admin_password'] !== $_POST['admin_password_confirm']) {
                $errors[] = "Les mots de passe ne correspondent pas.";
            } elseif (strlen($_POST['admin_password']) < 6) {
                $errors[] = "Le mot de passe doit contenir au moins 6 caractères.";
            }
            
            // Si pas d'erreurs, créer le mot de passe
            if (empty($errors)) {
                $admin_password = $_POST['admin_password'];
                $password_hash = password_hash($admin_password, PASSWORD_DEFAULT);
                
                $query = $db->prepare('INSERT INTO admin_passwords (password_hash, is_active) VALUES (?, 1)');
                $query->execute([$password_hash]);
                
                // Rediriger vers l'accueil avec un message de succès
                header('Location: ?accueil&password_created=success');
                exit;
            }
        }
        
    } catch (PDOException $e) {
        $errors[] = "Erreur de connexion à la base de données : " . $e->getMessage();
    } catch (Exception $e) {
        $errors[] = "Erreur : " . $e->getMessage();
    }
    
    // Detecter si mode standalone
    $is_standalone = !isset($_SERVER['ELECTRON_RUNNING']) && php_sapi_name() === 'cli-server';
    $base_path = $is_standalone ? '' : 'public/';
    
    // Préparer les variables pour la vue
    $array = [
        'errors' => $errors,
        'success' => $success,
        'base_path' => $base_path
    ];
    
    // Rendu direct sans template() pour create_password (même logique que setup)
    extract($array);
    include(__DIR__ . '/../view/create_password.html.php');
    return '';
}
}

