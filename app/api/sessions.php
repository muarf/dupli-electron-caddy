<?php
/**
 * API Gestion des Sessions d'Impression Multi-Contacts
 * 
 * Endpoints:
 * - GET ?sessions&action=list : Lister sessions actives
 * - POST ?sessions&action=create : Créer nouvelle session
 * - POST ?sessions&action=close : Fermer une session
 * - POST ?sessions&action=reassign_job : Réassigner un job entre sessions
 */

require_once __DIR__ . '/../controler/functions/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = pdo_connect();
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            listActiveSessions($db);
            break;

        case 'last':
            getLastSession($db);
            break;
            
        case 'create':
            createSession($db);
            break;
            
        case 'close':
            closeSession($db);
            break;
            
        case 'reassign_job':
            reassignJob($db);
            break;
            
        case 'close_all':
            closeAllSessions($db);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Action invalide']);
    }
    
} catch (Exception $e) {
    error_log("[SESSIONS API] Erreur: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur', 'message' => $e->getMessage()]);
}

/**
 * Lister toutes les sessions actives avec leurs statistiques
 */
function listActiveSessions($db) {
    $query = $db->query("
        SELECT 
            s.id,
            s.contact,
            s.session_name,
            s.opened_at,
            s.status,
            s.total_price,
            s.notes,
            (
                SELECT COUNT(*) 
                FROM photocop p 
                WHERE p.session_id = s.id
            ) + (
                SELECT COUNT(*) 
                FROM dupli d 
                WHERE d.session_id = s.id
            ) + (
                SELECT COUNT(*) 
                FROM print_jobs pj 
                WHERE pj.session_id = s.id AND pj.staged = 1
            ) as job_count
        FROM print_sessions s
        WHERE s.status = 'active'
        ORDER BY s.opened_at DESC
    ");
    
    $sessions = $query->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculer le prix total réel depuis les jobs (finalisés + stagés)
    foreach ($sessions as &$session) {
        $session['job_count'] = (int)$session['job_count'];
        
        // Recalculer le prix total depuis les tables (finalisées + stagées)
        $priceQuery = $db->prepare("
            SELECT 
                (SELECT COALESCE(SUM(CAST(prix AS REAL)), 0) FROM photocop WHERE session_id = ?) +
                (SELECT COALESCE(SUM(CAST(prix AS REAL)), 0) FROM dupli WHERE session_id = ?) +
                (SELECT COALESCE(SUM(CAST(calculated_price AS REAL)), 0) FROM print_jobs WHERE session_id = ? AND staged = 1) as total
        ");
        $priceQuery->execute([$session['id'], $session['id'], $session['id']]);
        $priceResult = $priceQuery->fetch(PDO::FETCH_ASSOC);
        $session['total_price'] = (float)$priceResult['total'];
    }
    
    echo json_encode(['sessions' => $sessions]);
}

/**
 * Créer une nouvelle session pour un contact
 */
function createSession($db) {
    $jsonInput = file_get_contents('php://input');
    if (empty($jsonInput) && PHP_SAPI === 'cli') {
        $jsonInput = file_get_contents('php://stdin');
    }
    $input = json_decode($jsonInput, true);
    
    $contact = trim($input['contact'] ?? '');
    $session_name = trim($input['session_name'] ?? '');
    $force_new = isset($input['force_new']) && $input['force_new'] === true;
    
    if (empty($contact)) {
        http_response_code(400);
        echo json_encode(['error' => 'Contact requis']);
        return;
    }
    
    // Toujours préparer la requête de vérification au cas où on en a besoin dans le catch
    $checkQuery = $db->prepare("
        SELECT id FROM print_sessions 
        WHERE contact = ? AND status = 'active'
        ORDER BY opened_at DESC
        LIMIT 1
    ");

    // Vérifier si une session active existe déjà pour ce contact, SAUF si on force la nouvelle
    if (!$force_new) {
        $checkQuery->execute([$contact]);
        $existing = $checkQuery->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Session déjà active, la retourner
            echo json_encode([
                'success' => true,
                'session_id' => $existing['id'],
                'message' => 'Session existante réutilisée',
                'existing' => true
            ]);
            return;
        }
    }
    
    // Créer nouvelle session
    $insertQuery = $db->prepare("
        INSERT INTO print_sessions (contact, session_name, status, opened_at)
        VALUES (?, ?, 'active', datetime('now'))
    ");
    
    try {
        $insertQuery->execute([$contact, $session_name]);
        $session_id = $db->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'session_id' => $session_id,
            'message' => 'Session créée',
            'existing' => false
        ]);
    } catch (PDOException $e) {
        // En cas d'erreur UNIQUE constraint (si l'index unique est encore là), 
        // ou race condition, récupérer la session existante
        if (strpos($e->getMessage(), 'UNIQUE') !== false) {
            $checkQuery->execute([$contact]);
            $existing = $checkQuery->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                echo json_encode([
                    'success' => true,
                    'session_id' => $existing['id'],
                    'message' => 'Session existante réutilisée (race condition/contrainte)',
                    'existing' => true
                ]);
            } else {
                throw $e;
            }
        } else {
            throw $e;
        }
    }
}

/**
 * Fermer une session
 */
function closeSession($db) {
    $jsonInput = file_get_contents('php://input');
    if (empty($jsonInput) && PHP_SAPI === 'cli') {
        $jsonInput = file_get_contents('php://stdin');
    }
    $input = json_decode($jsonInput, true);
    
    // Support JSON body OR GET parameter
    $session_id = (int)($input['session_id'] ?? $_GET['id'] ?? 0);
    
    if ($session_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'session_id invalide']);
        return;
    }
    
    // Calculer le total final avant fermeture
    $priceQuery = $db->prepare("
        SELECT 
            (SELECT COALESCE(SUM(CAST(prix AS REAL)), 0) FROM photocop WHERE session_id = ?) +
            (SELECT COALESCE(SUM(CAST(prix AS REAL)), 0) FROM dupli WHERE session_id = ?) as total
    ");
    $priceQuery->execute([$session_id, $session_id]);
    $priceResult = $priceQuery->fetch(PDO::FETCH_ASSOC);
    $total_price = (float)$priceResult['total'];
    
    // Fermer la session
    $updateQuery = $db->prepare("
        UPDATE print_sessions 
        SET status = 'closed', 
            closed_at = datetime('now'),
            total_price = ?
        WHERE id = ?
    ");
    
    $updateQuery->execute([$total_price, $session_id]);
    
    if ($updateQuery->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Session fermée',
            'total_price' => $total_price
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Session non trouvée']);
    }
}

/**
 * Réassigner un job d'une session à une autre
 */
function reassignJob($db) {
    $jsonInput = file_get_contents('php://input');
    if (empty($jsonInput) && PHP_SAPI === 'cli') {
        $jsonInput = file_get_contents('php://stdin');
    }
    $input = json_decode($jsonInput, true);
    
    $job_id = (int)($input['job_id'] ?? 0);
    $job_table = $input['job_table'] ?? ''; // 'photocop' ou 'dupli'
    $to_session = (int)($input['to_session'] ?? 0);
    
    if ($job_id <= 0 || $to_session <= 0 || !in_array($job_table, ['photocop', 'dupli', 'print_jobs'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Paramètres invalides']);
        return;
    }

    
    // Vérifier que la session de destination existe et est active
    $checkSession = $db->prepare("SELECT id FROM print_sessions WHERE id = ? AND status = 'active'");
    $checkSession->execute([$to_session]);
    if (!$checkSession->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => 'Session de destination invalide ou fermée']);
        return;
    }
    
    // Réassigner le job
    $updateQuery = $db->prepare("
        UPDATE $job_table 
        SET session_id = ? 
        WHERE id = ?
    ");
    
    $updateQuery->execute([$to_session, $job_id]);
    
    if ($updateQuery->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Job réassigné'
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Job non trouvé']);
    }
}

/**
 * Récupérer la dernière session active (la plus récemment ouverte)
 */
function getLastSession($db) {
    $query = $db->query("
        SELECT
            s.id,
            s.contact,
            s.session_name,
            s.opened_at,
            s.status,
            s.total_price
        FROM print_sessions s
        WHERE s.status = 'active'
        ORDER BY s.opened_at DESC
        LIMIT 1
    ");

    $session = $query->fetch(PDO::FETCH_ASSOC);

    if ($session) {
        echo json_encode(['success' => true, 'session' => $session]);
    } else {
        echo json_encode(['success' => true, 'session' => null]);
    }
}

function closeAllSessions($db) {
    try {
        // Récupérer toutes les sessions actives
        $query = $db->query("SELECT id FROM print_sessions WHERE status = 'active'");
        $sessionIds = $query->fetchAll(PDO::FETCH_COLUMN);
        
        $closed = 0;
        foreach ($sessionIds as $id) {
            // Calculer le total réel
            $priceQuery = $db->prepare("
                SELECT 
                    (SELECT COALESCE(SUM(CAST(prix AS REAL)), 0) FROM photocop WHERE session_id = ?) +
                    (SELECT COALESCE(SUM(CAST(prix AS REAL)), 0) FROM dupli WHERE session_id = ?) as total
            ");
            $priceQuery->execute([$id, $id]);
            $total_price = (float)$priceQuery->fetchColumn();
            
            // Fermer
            $update = $db->prepare("
                UPDATE print_sessions 
                SET status = 'closed', 
                    closed_at = datetime('now'),
                    total_price = ?
                WHERE id = ?
            ");
            $update->execute([$total_price, $id]);
            $closed++;
        }
        
        echo json_encode(['success' => true, 'closed_count' => $closed]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
