<?php
/**
 * API pour charger les jobs stagés (non finalisés) d'une session depuis print_jobs
 * GET ?get_session_staging_jobs&session_id=X
 * 
 * Cette API retourne les jobs qui ont été ajoutés à la pool mais pas encore validés.
 * Ils sont stockés dans print_jobs avec staged=1 et un session_id.
 */

require_once __DIR__ . '/../controler/functions/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = pdo_connect();
    $session_id = isset($_GET['session_id']) ? (int)$_GET['session_id'] : null;
    
    if (!$session_id) {
        echo json_encode(['success' => false, 'error' => 'session_id manquant']);
        exit;
    }
    
    // Vérifier que la session existe et est active
    $sessionCheck = $db->prepare("SELECT id, status FROM print_sessions WHERE id = ?");
    $sessionCheck->execute([$session_id]);
    $session = $sessionCheck->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        echo json_encode(['success' => false, 'error' => 'Session non trouvée']);
        exit;
    }
    
    if ($session['status'] !== 'active') {
        echo json_encode(['success' => false, 'error' => 'Session fermée']);
        exit;
    }
    
    // Charger tous les jobs stagés de cette session
    $stmt = $db->prepare("
        SELECT 
            id,
            job_id,
            document,
            printer_name,
            total_pages,
            copies,
            fill_rate,
            color_mode,
            duplex,
            paper_size,
            thumbnail_url,
            calculated_price,
            machine_type,
            machine_id,
            machine_name,
            contact,
            session_id,
            timestamp,
            created_at
        FROM print_jobs
        WHERE session_id = ? AND staged = 1
        ORDER BY created_at ASC
    ");
    
    $stmt->execute([$session_id]);
    $staged_jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Transformer les données pour le format attendu par tirage_multimachines
    $machines = [];
    
    foreach ($staged_jobs as $job) {
        $machine = [
            'db_id' => $job['id'],
            'job_id' => $job['job_id'],
            'document_name' => $job['document'],
            'thumbnail_url' => $job['thumbnail_url'],
            'contact' => $job['contact'],
            'fill_rate' => $job['fill_rate'],
            'prix' => $job['calculated_price']
        ];
        
        if ($job['machine_type'] === 'dupli') {
            // Job de duplicopieur
            $machine['type'] = 'duplicopieur';
            $machine['machine_id'] = $job['machine_id'];
            $machine['duplicopieur_id'] = $job['machine_id'];
            $machine['machine'] = $job['machine_name'];
            
            // Récupérer les détails du duplicopieur depuis la table dupli (s'il existe déjà)
            // ou calculer approximativement depuis les métadonnées
            // Pour simplifier, on stocke les valeurs de base
            $machine['nb_masters'] = ceil($job['total_pages'] / $job['copies']);
            $machine['nb_passages'] = $job['total_pages'];
            
            // Déterminer si duplex et taille
            $machine['rv'] = $job['duplex'] ? 'oui' : 'non';
            $machine['A4'] = (stripos($job['paper_size'], 'A4') !== false) ? 'A4' : 'A3';
            $machine['feuilles_payees'] = 'non';
            
            // Mode saisie manuel par défaut pour les jobs stagés
            $machine['mode_saisie'] = 'manuel';
            
        } else if ($job['machine_type'] === 'photocop') {
            // Job de photocopieur
            $machine['type'] = 'photocopieur';
            $machine['machine_id'] = $job['machine_id'];
            $machine['machine'] = $job['machine_name'];
            
            // Créer une brochure unique avec les caractéristiques du job
            $is_color = (stripos($job['color_mode'], 'color') !== false);
            $is_duplex = $job['duplex'];
            $taille = (stripos($job['paper_size'], 'A4') !== false) ? 'A4' : 'A3';
            
            // Calculer nb_feuilles et nb_exemplaires
            $nb_exemplaires = $job['copies'];
            $pages_per_copy = $job['total_pages'] / $nb_exemplaires;
            $nb_feuilles = $is_duplex ? ceil($pages_per_copy / 2) : $pages_per_copy;
            
            $machine['brochures'] = [[
                'nb_exemplaires' => $nb_exemplaires,
                'nb_feuilles' => $nb_feuilles,
                'nb_pages' => $pages_per_copy,
                'taille' => $taille,
                'rv' => $is_duplex ? 'oui' : 'non',
                'couleur' => $is_color ? 'oui' : 'non',
                'feuilles_payees' => 'non'
            ]];
        } else {
            // Type inconnu, on saute
            continue;
        }
        
        $machines[] = $machine;
    }
    
    echo json_encode([
        'success' => true,
        'machines' => $machines,
        'session_id' => $session_id
    ]);
    
} catch (Exception $e) {
    error_log("[GET_SESSION_STAGING_JOBS] Erreur: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur serveur', 'message' => $e->getMessage()]);
}
