<?php
// Inclure le système de traduction
require_once __DIR__ . '/../controler/functions/i18n.php';

// Extraire les variables du tableau $array si elles existent
if (isset($array['duplicopieurs'])) {
    $duplicopieurs = $array['duplicopieurs'];
}
if (isset($array['duplicopieur_selectionne'])) {
    $duplicopieur_selectionne = $array['duplicopieur_selectionne'];
}
if (isset($array['prix_data'])) {
    $prix_data = $array['prix_data'];
}

// Générer les mappings machine -> price_key côté serveur
$machine_price_mappings = [];
try {
    require_once __DIR__ . '/../controler/functions/database.php';
    $db = pdo_connect();
    
    // Récupérer tous les photocopieurs actifs
    $stmt = $db->prepare("SELECT id, marque FROM photocopieurs WHERE actif = 1 ORDER BY marque");
    $stmt->execute();
    $photocopieurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($photocopieurs as $photocopieur) {
        $machine_name = $photocopieur['marque'];
        $photocopier_id = $photocopieur['id'];
        
        // Vérifier si des prix existent pour cet ID
        $stmt = $db->prepare("SELECT COUNT(*) FROM prix WHERE machine_type = 'photocop' AND machine_id = ?");
        $stmt->execute([$photocopier_id]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            $machine_price_mappings[$machine_name] = "photocop_$photocopier_id";
        }
    }
} catch (Exception $e) {
    error_log("Erreur lors de la génération des mappings machine: " . $e->getMessage());
    $machine_price_mappings = [];
}
?>

<style>
    .main-container {
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        margin: 1rem auto;
        overflow: hidden;
    }
    
    .header-section {
        background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
        color: #424242;
        padding: 1.5rem;
        text-align: center;
        border-bottom: 1px solid #e0e0e0;
    }
    
    .header-section h1 {
        margin: 0;
        font-weight: 400;
        font-size: 2.2rem;
        color: #616161;
    }
    
    .header-section p {
        margin: 0.5rem 0 0 0;
        color: #757575;
        font-size: 1.1rem;
    }
    
    .form-section {
        padding: 1.5rem;
    }
    
    .form-card {
        background: white;
        border: 1px solid #e9ecef;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    .form-card h4 {
        color: #81c784;
        border-bottom: 1px solid #e9ecef;
        padding-bottom: 0.5rem;
        margin-bottom: 1rem;
    }
    
    .machine-card {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        position: relative;
    }
    
    .machine-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, #81c784, #a5d6a7);
    }
    
    .btn-modern {
        border-radius: 10px;
        padding: 0.75rem 1.5rem;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    
    .btn-primary-modern {
        background: linear-gradient(135deg, #81c784, #a5d6a7);
        border: none;
        color: white;
    }
    
    .btn-success-modern {
        background: linear-gradient(135deg, #a5d6a7, #c8e6c9);
        border: none;
        color: #2e7d32;
    }
    
    .btn-warning-modern {
        background: linear-gradient(135deg, #ffcc02, #ffeb3b);
        border: none;
        color: #f57f17;
    }
    
    .btn-danger-modern {
        background: linear-gradient(135deg, #ef9a9a, #ffcdd2);
        border: none;
        color: #c62828;
    }
    
    .form-control, .form-select {
        border-radius: 10px;
        border: 1px solid #ced4da;
        transition: all 0.3s ease;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: #81c784;
        box-shadow: 0 0 0 0.2rem rgba(129, 199, 132, 0.25);
    }
    
    .alert-modern {
        border-radius: 12px;
        border: none;
        padding: 1.5rem;
    }
    
    .summary-card {
        background: linear-gradient(135deg, #a5d6a7, #c8e6c9);
        color: #2e7d32;
        border-radius: 12px;
        padding: 1.5rem;
        text-align: center;
        margin-bottom: 1.5rem;
    }
    
    .summary-card h3 {
        margin-bottom: 1rem;
        font-weight: 500;
    }
    
    .summary-card .total-price {
        font-size: 2rem;
        font-weight: bold;
    }
    
    /* Styles pour l'accordéon */
    .machine-item {
        background: #fff;
        border: 1px solid #337ab7;
        border-radius: 8px;
        margin-bottom: 15px;
        box-shadow: 0 2px 8px rgba(51, 122, 183, 0.15);
        transition: all 0.3s ease;
        overflow: hidden;
    }
    
    .machine-item:hover {
        box-shadow: 0 4px 12px rgba(51, 122, 183, 0.25);
        transform: translateY(-2px);
    }
    
    .machine-item.panel-expanded {
        border-color: #2e6da4;
    }
    
    .machine-item .panel-heading {
        background: linear-gradient(135deg, #337ab7 0%, #2e6da4 100%);
        color: white;
        padding: 15px 20px;
        cursor: pointer;
        border-radius: 8px 8px 0 0;
        transition: background 0.3s ease;
    }
    
    .machine-item .panel-heading:hover {
        background: linear-gradient(135deg, #2e6da4 0%, #286090 100%);
    }
    
    .machine-item .panel-title {
        font-size: 18px;
        font-weight: 600;
    }
    
    .machine-item .toggle-icon {
        transition: transform 0.3s ease;
        margin-right: 10px;
    }
    
    .machine-item .machine-type-badge {
        background-color: rgba(255, 255, 255, 0.3);
        color: white;
        padding: 5px 12px;
        border-radius: 15px;
        font-size: 13px;
        font-weight: 500;
    }
    
    .machine-item .machine-price-preview {
        font-size: 20px;
        font-weight: bold;
        color: white;
        text-shadow: 0 1px 3px rgba(0,0,0,0.2);
    }
    
    .machine-item .panel-body {
        padding: 25px;
        background: #fafafa;
    }
</style>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-12 col-xl-10">
            <div class="main-container">
                <!-- Header -->
                <div class="header-section">
                    <h1><i class="fa fa-print"></i> <?php _e('tirage_multimachines.title'); ?></h1>
                    <p><?php _e('tirage_multimachines.subtitle'); ?></p>
                </div>

                <!-- Form Section -->
                <div class="form-section">

<?php
// Debug POST - seulement si debug dans l'URL
if (isset($_GET['debug']) && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
    <div class="alert alert-danger">
        <h4>Debug POST complet test:</h4>
        <pre>REQUEST_METHOD: <?php echo $_SERVER['REQUEST_METHOD']; ?></pre>
        <pre>POST count: <?php echo count($_POST); ?></pre>
        <pre>POST keys: <?php print_r(array_keys($_POST), true); ?></pre>
        <pre>POST content var_dump: <?php var_dump($_POST); ?></pre>
    </div>
<?php endif; ?>

<?php if (isset($_GET['debug']) && isset($debug)): ?>
    <div class="alert alert-info">
        <h4><?php _e('tirage_multimachines.debug_full'); ?></h4>
        <pre><?php var_dump($debug); ?></pre>
    </div>
<?php elseif (isset($_GET['debug'])): ?>
    <div class="alert alert-warning">
        <h4>Debug activé mais variable \$debug non définie</h4>
    </div>
<?php endif; ?>

<?php
if (isset($_POST['contact']) && isset($_POST['enregistrer'])) {
    
    ?>
    
    <div class="alert-modern alert alert-success">
        <strong><i class="fa fa-check-circle"></i> <?php _e('tirage_multimachines.success_message'); ?></strong> <?php _e('tirage_multimachines.success_description'); ?>
    </div>
    
    <!-- Récapitulatif après soumission -->
    <?php if (isset($contact) && isset($machines) && ($contact != "")): ?>
    <!-- Script pour sauvegarder les données de la confirmation dans sessionStorage -->
    <script>
    (function() {
        // Sauvegarder les données depuis PHP vers sessionStorage pour permettre le retour
        try {
            const formData = {
                contact: <?= json_encode($contact ?? '') ?>,
                machines: <?= json_encode($machines ?? []) ?>
            };
            
            // Convertir les données PHP en format compatible avec le formulaire
            const savedData = {};
            savedData['contact'] = formData.contact;
            
            // Convertir les machines en format formulaire
            if (formData.machines && Array.isArray(formData.machines)) {
                formData.machines.forEach((machine, index) => {
                    Object.keys(machine).forEach(key => {
                        if (key === 'brochures' && Array.isArray(machine[key])) {
                            // Gérer les brochures
                            machine[key].forEach((brochure, brochureIndex) => {
                                Object.keys(brochure).forEach(brochureKey => {
                                    savedData[`machines[${index}][brochures][${brochureIndex}][${brochureKey}]`] = brochure[brochureKey];
                                });
                            });
                        } else {
                            savedData[`machines[${index}][${key}]`] = machine[key];
                        }
                    });
                });
            }
            
            // Sauvegarder le nombre de machines dans les métadonnées
            savedData['_machine_count'] = formData.machines ? formData.machines.length : 0;
            
            // Sauvegarder dans sessionStorage
            sessionStorage.setItem('tirage_multimachines_form_data', JSON.stringify(savedData));
            console.log('✅ Données de confirmation sauvegardées pour retour possible:', {
                nombreMachines: savedData['_machine_count'],
                cles: Object.keys(savedData).filter(k => k.startsWith('machines[')).length
            });
        } catch (e) {
            console.error('❌ Erreur lors de la sauvegarde des données de confirmation:', e);
        }
    })();
    </script>
    
    <div class="summary-card">
        <h3 class="text-center"><i class="fa fa-calculator"></i> <?php _e('tirage_multimachines.summary'); ?></h3>
        <div class="total-price text-center"><?= number_format($prix_total, 2) ?> <?php _e('tirage_multimachines.currency'); ?></div>
        <p class="mb-0 text-center"><?php _e('tirage_multimachines.contact_label'); ?> <strong><?= htmlspecialchars($contact) ?></strong></p>
    </div>
    
            <div class="row">
        <?php if (isset($machines) && !empty($machines)): ?>
            <?php foreach ($machines as $index => $machine): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="machine-card">
                        <h5 class="text-center"><i class="fa fa-print"></i> <?php _e('tirage_multimachines.tirage_number_prefix'); ?><?= ($index + 1) ?></h5>
                        <p class="text-center"><strong><?= ucfirst($machine['type']) ?></strong></p>
                        <div class="text-center" style="margin-top: 15px;">
                            <h3 style="color: #337ab7; margin: 0;">
                                <strong><?= number_format($machine['prix'], 2) ?> <?php _e('tirage_multimachines.currency'); ?></strong>
                            </h3>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <?php endif; ?>
    
    <div class="text-center">
        <a href="?accueil" class="btn btn-modern btn-success-modern btn-lg">
            <i class="fa fa-home"></i> <?php _e('accueil.back_to_home'); ?>
        </a>
    </div>
    <?php 
} else if (isset($_POST['contact']) && isset($_POST['ok'])) {
    ?>
    <!-- Page de confirmation améliorée -->
    <div class="alert-modern alert alert-success">
        <h3><i class="fa fa-check-circle"></i> <?php _e('tirage_multimachines.confirmation_title'); ?></h3>
        <p><strong><?php _e('tirage_multimachines.contact_label'); ?></strong> <?= htmlspecialchars($contact) ?></p>
    </div>
    
    <?php if (isset($machines) && !empty($machines)): ?>
        <div class="row">
            <?php foreach ($machines as $index => $machine): ?>
                <div class="col-md-6">
                    <div class="machine-card">
                        <h4 class="text-center"><i class="fa fa-print"></i> <?php _e('tirage_multimachines.tirage_number_prefix'); ?><?= ($index + 1) ?> - <?= ucfirst($machine['type']) ?></h4>
                            <?php if ($machine['type'] === 'duplicopieur'): ?>
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5><i class="fa fa-cogs"></i> <?php _e('tirage_multimachines.configuration_title'); ?></h5>
                                        <ul class="list-unstyled">
                                            <li><strong><?php _e('tirage_multimachines.masters'); ?> :</strong> <?= $machine['nb_masters'] ?? 0 ?></li>
                                            <li><strong><?php _e('tirage_multimachines.passes'); ?> :</strong> <?= $machine['nb_passages'] ?? 0 ?></li>
                                            <?php if (isset($machine['rv']) && $machine['rv'] == 'oui'): ?>
                                                <li><i class="fa fa-check text-success"></i> <?php _e('tirage_multimachines.recto_verso_enabled'); ?></li>
                                            <?php endif; ?>
                                            <?php if (isset($machine['A4']) && $machine['A4'] == 'A4'): ?>
                                                <li><i class="fa fa-check text-success"></i> <?php _e('tirage_multimachines.format_a4_enabled'); ?></li>
                                            <?php else: ?>
                                                <li><i class="fa fa-check text-info"></i> <?php _e('tirage_multimachines.format_a3_enabled'); ?></li>
                                            <?php endif; ?>
                                            <?php if (isset($machine['feuilles_payees']) && $machine['feuilles_payees'] == 'oui'): ?>
                                                <li><i class="fa fa-check text-warning"></i> <?php _e('tirage_multimachines.sheets_already_paid'); ?></li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h5><i class="fa fa-euro"></i> <?php _e('tirage_multimachines.cost_details'); ?></h5>
                                        <ul class="list-unstyled">
                                            <?php 
                                            // Calculer les coûts détaillés pour le duplicopieur
                                            $prix_data = $prix_data ?? [];
                                            $duplicopieur_id = $machine['duplicopieur_id'] ?? $duplicopieur_selectionne['id'];
                                            $machine_key = 'dupli_' . $duplicopieur_id;
                                            $prix_master = $prix_data[$machine_key]['master']['unite'] ?? 0;
                                            
                                            // Prix des passages selon le tambour sélectionné
                                            $tambour_selected = $machine['tambour'] ?? '';
                                            $prix_passage = 0;
                                            if (!empty($tambour_selected) && isset($prix_data[$machine_key][$tambour_selected]['unite'])) {
                                                $prix_passage = $prix_data[$machine_key][$tambour_selected]['unite'];
                                            } elseif (isset($prix_data[$machine_key]['tambour_noir']['unite'])) {
                                                $prix_passage = $prix_data[$machine_key]['tambour_noir']['unite'];
                                            }
                                            
                                            // Prix du papier selon la taille
                                            $taille = isset($machine['A4']) && $machine['A4'] == 'A4' ? 'A4' : 'A3';
                                            $prix_papier = $prix_data['papier'][$taille] ?? 0;
                                            
                                            // Ajuster pour A4
                                            if ($taille === 'A4') {
                                                $prix_master = $prix_master / 2;
                                                $prix_passage = $prix_passage / 2;
                                            }
                                            
                                            $nb_masters = $machine['nb_masters'] ?? 0;
                                            $nb_passages = $machine['nb_passages'] ?? 0;
                                            $nb_f = $nb_passages;
                                            if (isset($machine['rv']) && $machine['rv'] == 'oui') {
                                                $nb_f = $nb_passages / 2;
                                            }
                                            if (isset($machine['feuilles_payees']) && $machine['feuilles_payees'] == 'oui') {
                                                $nb_f = 0;
                                            }
                                            
                                            $cout_masters = $nb_masters * $prix_master;
                                            $cout_passages = $nb_passages * $prix_passage;
                                            $cout_papier = $nb_f * $prix_papier;
                                            ?>
                                            <li><strong><?php _e('tirage_multimachines.masters'); ?> :</strong> <?= $nb_masters ?> × <?= number_format($prix_master, 4) ?> <?php _e('tirage_multimachines.currency'); ?> = <?= number_format($cout_masters, 2) ?> <?php _e('tirage_multimachines.currency'); ?></li>
                                            <li><strong><?php _e('tirage_multimachines.passes'); ?> :</strong> <?= $nb_passages ?> × <?= number_format($prix_passage, 4) ?> <?php _e('tirage_multimachines.currency'); ?> = <?= number_format($cout_passages, 2) ?> <?php _e('tirage_multimachines.currency'); ?></li>
                                            <li><strong><?php _e('tirage_multimachines.paper'); ?> :</strong> <?= $nb_f ?> <?php _e('tirage_multimachines.sheets'); ?> × <?= number_format($prix_papier, 3) ?> <?php _e('tirage_multimachines.currency'); ?> = <?= number_format($cout_papier, 2) ?> <?php _e('tirage_multimachines.currency'); ?></li>
                                            <?php if (isset($machine['rv']) && $machine['rv'] == 'oui'): ?>
                                                <li><i class="fa fa-info-circle text-info"></i> <?php _e('tirage_multimachines.recto_verso'); ?> : <?php _e('tirage_multimachines.paper_divided_by_2'); ?></li>
                                            <?php endif; ?>
                                            <?php if (isset($machine['feuilles_payees']) && $machine['feuilles_payees'] == 'oui'): ?>
                                                <li><i class="fa fa-check text-warning"></i> <?php _e('tirage_multimachines.sheets_already_paid'); ?> : <?php _e('tirage_multimachines.free_paper'); ?></li>
                                            <?php endif; ?>
                                            <?php if ($taille === 'A4'): ?>
                                                <li><i class="fa fa-info-circle text-info"></i> <?php _e('tirage_multimachines.format_a4_masters_passes_divided'); ?></li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5><i class="fa fa-print"></i> <?php _e('tirage_multimachines.machine_title'); ?></h5>
                                        <p><strong><?= htmlspecialchars($machine['machine']) ?></strong></p>
                                        
                                        <?php if (isset($machine['brochures']) && is_array($machine['brochures'])): ?>
                                            <h5><i class="fa fa-file-text"></i> <?php _e('tirage_multimachines.brochures'); ?></h5>
                                            <?php foreach ($machine['brochures'] as $brochure_index => $brochure): ?>
                                                <div class="well well-sm">
                                                    <strong><?php _e('tirage_multimachines.brochure'); ?> #<?= ($brochure_index + 1) ?></strong><br>
                                                    • <?= $brochure['nb_exemplaires'] ?> <?php _e('tirage_multimachines.exemplaires'); ?><br>
                                                    • <?= $brochure['nb_feuilles'] ?> <?php _e('tirage_multimachines.feuilles_per_exemplaire'); ?><br>
                                                    • <?php _e('tirage_multimachines.format'); ?> : <?= $brochure['taille'] ?><br>
                                                    <?php if (isset($brochure['rv']) && $brochure['rv'] == 'oui'): ?>
                                                        • <i class="fa fa-check text-success"></i> <?php _e('tirage_multimachines.recto_verso'); ?><br>
                                                    <?php endif; ?>
                                                    <?php if (isset($brochure['couleur']) && $brochure['couleur'] == 'oui'): ?>
                                                        • <i class="fa fa-check text-success"></i> <?php _e('tirage_multimachines.color'); ?><br>
                                                    <?php endif; ?>
                                                    <?php if (isset($brochure['feuilles_payees']) && $brochure['feuilles_payees'] == 'oui'): ?>
                                                        • <i class="fa fa-check text-warning"></i> <?php _e('tirage_multimachines.sheets_paid'); ?><br>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <h5><i class="fa fa-euro"></i> <?php _e('tirage_multimachines.cost_details'); ?></h5>
                                        <ul class="list-unstyled">
                                            <?php 
                                            // Calculer les coûts détaillés pour le photocopieur
                                            $prix_data = $prix_data ?? [];
                                            
                                            $total_papier = 0;
                                            $total_encre = 0;
                                            $total_pages = 0;
                                            $total_pages_encre = 0;
                                            $prix_papier = 0;
                                            $prix_encre = 0;
                                            
                                            if (isset($machine['brochures']) && is_array($machine['brochures'])) {
                                                foreach ($machine['brochures'] as $brochure) {
                                                    if (!empty($brochure['nb_exemplaires']) && !empty($brochure['nb_feuilles']) && !empty($brochure['taille'])) {
                                                        $nb_exemplaires = intval($brochure['nb_exemplaires']);
                                                        $nb_feuilles = intval($brochure['nb_feuilles']);
                                                        $nb_pages = $nb_exemplaires * $nb_feuilles;
                                                        $taille = $brochure['taille'];
                                                        $rv = isset($brochure['rv']) && $brochure['rv'] == 'oui';
                                                        $couleur = isset($brochure['couleur']) && $brochure['couleur'] == 'oui';
                                                        $feuilles_payees = isset($brochure['feuilles_payees']) && $brochure['feuilles_payees'] == 'oui';
                                                        
                                                        // Prix du papier
                                                        $prix_papier = $prix_data['papier'][$taille] ?? 0;
                                                        $cout_papier = $feuilles_payees ? 0 : ($nb_pages * $prix_papier);
                                                        $total_papier += $cout_papier;
                                                        
                                                        // Prix d'encre selon le type de machine
                                                        $prix_encre_brochure = 0;
                                                        $machine_name = $machine['machine'];
                                                        
                                                        // Récupérer le taux de remplissage (valeur par défaut 0.5 = 50%)
                                                        $fill_rate = isset($machine['fill_rate']) ? floatval($machine['fill_rate']) : 0.5;
                                                        $fill_rate_multiplier = $couleur ? ($fill_rate / 0.5) : 1.0; // 50% = ×1, 100% = ×2
                                                        
                                                        // Déterminer la clé de la machine dynamiquement
                                                        $machine_key = null;
                                                        
                                                        // Récupérer l'ID du photocopieur par son nom
                                                        $stmt = $db->prepare("SELECT id FROM photocopieurs WHERE marque = ? AND actif = 1");
                                                        $stmt->execute([$machine_name]);
                                                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                                        
                                                        if ($result) {
                                                            $photocopier_id = $result['id'];
                                                            $machine_key = "photocop_$photocopier_id";
                                                            
                                                            // Vérifier si des prix existent pour cet ID
                                                            if (!isset($prix_data[$machine_key])) {
                                                                $machine_key = null; // Pas de prix trouvé
                                                            }
                                                        }
                                                        
                                                        // Fallback si pas trouvé
                                                        if (!$machine_key) {
                                                            foreach ($prix_data as $key => $value) {
                                                                if (strpos($key, 'photocop_') === 0) {
                                                                    $machine_key = $key;
                                                                    break;
                                                                }
                                                            }
                                                        }
                                                        
                                                        if ($machine_key && isset($prix_data[$machine_key])) {
                                                            $machine_prices = $prix_data[$machine_key];
                                                            
                                                            if (strtolower($machine_name) === 'comcolor') {
                                                                // Photocopieur à encre
                                                                if ($couleur) {
                                                                    // Couleur : bleue + couleur + jaune + noire + rouge (avec taux de remplissage)
                                                                    $prix_encre_brochure += (($machine_prices['bleue']['unite'] ?? 0) * $fill_rate_multiplier);
                                                                    $prix_encre_brochure += (($machine_prices['couleur']['unite'] ?? 0) * $fill_rate_multiplier);
                                                                    $prix_encre_brochure += (($machine_prices['jaune']['unite'] ?? 0) * $fill_rate_multiplier);
                                                                    $prix_encre_brochure += (($machine_prices['noire']['unite'] ?? 0) * $fill_rate_multiplier);
                                                                    $prix_encre_brochure += (($machine_prices['rouge']['unite'] ?? 0) * $fill_rate_multiplier);
                                                                } else {
                                                                    // Noir et blanc : seulement noire (pas de taux de remplissage)
                                                                    $prix_encre_brochure += ($machine_prices['noire']['unite'] ?? 0);
                                                                }
                                                            } else {
                                                                // Photocopieur à toner
                                                                if ($couleur) {
                                                                    // Couleur : cyan + jaune + magenta + noir (avec taux de remplissage) + tambour + dev (sans taux)
                                                                    $prix_encre_brochure += (($machine_prices['cyan']['unite'] ?? 0) * $fill_rate_multiplier);
                                                                    $prix_encre_brochure += (($machine_prices['jaune']['unite'] ?? 0) * $fill_rate_multiplier);
                                                                    $prix_encre_brochure += (($machine_prices['magenta']['unite'] ?? 0) * $fill_rate_multiplier);
                                                                    $prix_encre_brochure += (($machine_prices['noir']['unite'] ?? 0) * $fill_rate_multiplier);
                                                                    // Tambour et dev ne sont pas affectés par le taux de remplissage
                                                                    $prix_encre_brochure += ($machine_prices['tambour']['unite'] ?? 0);
                                                                    $prix_encre_brochure += ($machine_prices['dev']['unite'] ?? 0);
                                                                } else {
                                                                    // Noir et blanc : noir + tambour + dev (pas de taux de remplissage)
                                                                    $prix_encre_brochure += ($machine_prices['noir']['unite'] ?? 0);
                                                                    $prix_encre_brochure += ($machine_prices['tambour']['unite'] ?? 0);
                                                                    $prix_encre_brochure += ($machine_prices['dev']['unite'] ?? 0);
                                                                }
                                                            }
                                                        }
                                                        
                                                        // Ajuster selon la taille
                                                        if ($taille === 'A4') {
                                                            $prix_encre_brochure = $prix_encre_brochure / 2;
                                                        }
                                                        
                                                        // Calculer le coût d'encre
                                                        $nb_pages_encre = $nb_pages;
                                                        if ($rv) {
                                                            $nb_pages_encre = $nb_pages * 2;
                                                        }
                                                        $cout_encre = $nb_pages_encre * $prix_encre_brochure;
                                                        $total_encre += $cout_encre;
                                                        $total_pages += $nb_pages;
                                                        $total_pages_encre += $nb_pages_encre;
                                                        
                                                        // Stocker les prix pour l'affichage (prendre la dernière brochure)
                                                        $prix_papier = $prix_papier;
                                                        $prix_encre = $prix_encre_brochure;
                                                    }
                                                }
                                            }
                                            ?>
                                            <li><strong><?php _e('tirage_multimachines.paper_label'); ?> :</strong> <?= $total_pages ?> <?php _e('tirage_multimachines.pages'); ?> × <?= number_format($prix_papier, 3) ?> <?php _e('tirage_multimachines.currency'); ?> = <?= number_format($total_papier, 2) ?> <?php _e('tirage_multimachines.currency'); ?></li>
                                            <li><strong><?php _e('tirage_multimachines.ink_toner_label'); ?> :</strong> <?= $total_pages_encre ?> <?php _e('tirage_multimachines.pages'); ?> × <?= number_format($prix_encre, 4) ?> <?php _e('tirage_multimachines.currency'); ?> = <?= number_format($total_encre, 2) ?> <?php _e('tirage_multimachines.currency'); ?></li>
                                            <li><strong><?php _e('tirage_multimachines.total'); ?> :</strong> <?= number_format($machine['prix'], 2) ?> <?php _e('tirage_multimachines.currency'); ?></li>
                                            <?php if (isset($machine['brochures']) && is_array($machine['brochures'])): ?>
                                                <?php foreach ($machine['brochures'] as $brochure_index => $brochure): ?>
                                                    <?php if (!empty($brochure['rv']) && $brochure['rv'] == 'oui'): ?>
                                                        <li><i class="fa fa-info-circle text-info"></i> <?php _e('tirage_multimachines.brochure_number'); ?><?= ($brochure_index + 1) ?> : <?php _e('tirage_multimachines.recto_verso_double_ink'); ?></li>
                                                    <?php endif; ?>
                                                    <?php if (!empty($brochure['feuilles_payees']) && $brochure['feuilles_payees'] == 'oui'): ?>
                                                        <li><i class="fa fa-check text-warning"></i> <?php _e('tirage_multimachines.brochure_number'); ?><?= ($brochure_index + 1) ?> : <?php _e('tirage_multimachines.sheets_already_paid'); ?></li>
                                                    <?php endif; ?>
                                                    <?php if (!empty($brochure['taille']) && $brochure['taille'] === 'A4'): ?>
                                                        <li><i class="fa fa-info-circle text-info"></i> <?php _e('tirage_multimachines.brochure_number'); ?><?= ($brochure_index + 1) ?> : <?php _e('tirage_multimachines.format_a4_ink_divided'); ?></li>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="text-center" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                                <h4 class="text-primary">
                                    <i class="fa fa-euro"></i> 
                                    <strong><?= number_format($machine['prix'], 2) ?> <?php _e('tirage_multimachines.currency'); ?></strong>
                                </h4>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="alert alert-info text-center">
            <h3><i class="fa fa-calculator"></i> <?php _e('tirage_multimachines.total_global'); ?></h3>
            <h2 class="text-primary">
                <strong><?= number_format($prix_total, 2) ?> <?php _e('tirage_multimachines.currency'); ?></strong>
            </h2>
        </div>
    <?php endif; ?>
    
    <!-- Formulaire d'enregistrement -->
    <form class="form-horizontal" action="" method="post" id="form-enregistrement" onsubmit="console.log('Formulaire soumis !'); return true;">
        <fieldset>
            
            <!-- Champs cachés -->
            <input type="hidden" value="<?php echo $contact; ?>" name="contact"/>
            <?php foreach ($machines as $index => $machine): ?>
                <input type="hidden" name="machines[<?= $index ?>][type]" value="<?= $machine['type'] ?>" />
                <input type="hidden" name="machines[<?= $index ?>][contact]" value="<?= isset($machine['contact']) && !empty($machine['contact']) ? $machine['contact'] : $contact ?>" />
                <?php if ($machine['type'] === 'duplicopieur'): ?>
                    <input type="hidden" name="machines[<?= $index ?>][nb_masters]" value="<?= $machine['nb_masters'] ?>" />
                    <input type="hidden" name="machines[<?= $index ?>][nb_passages]" value="<?= $machine['nb_passages'] ?>" />
                    <input type="hidden" name="machines[<?= $index ?>][master_av]" value="<?= $machine['master_av'] ?>" />
                    <input type="hidden" name="machines[<?= $index ?>][master_ap]" value="<?= $machine['master_ap'] ?>" />
                    <input type="hidden" name="machines[<?= $index ?>][passage_av]" value="<?= $machine['passage_av'] ?>" />
                    <input type="hidden" name="machines[<?= $index ?>][passage_ap]" value="<?= $machine['passage_ap'] ?>" />
                    <input type="hidden" name="machines[<?= $index ?>][prix]" value="<?= $machine['prix'] ?>" />
                    <input type="hidden" name="machines[<?= $index ?>][rv]" value="<?= isset($machine['rv']) ? $machine['rv'] : 'non' ?>" />
                    <input type="hidden" name="machines[<?= $index ?>][feuilles_payees]" value="<?= isset($machine['feuilles_payees']) ? $machine['feuilles_payees'] : 'non' ?>" />
                    <input type="hidden" name="machines[<?= $index ?>][A4]" value="<?= isset($machine['A4']) ? $machine['A4'] : 'non' ?>" />
                    <?php if (isset($machine['duplicopieur_id'])): ?>
                        <input type="hidden" name="machines[<?= $index ?>][duplicopieur_id]" value="<?= $machine['duplicopieur_id'] ?>" />
                    <?php endif; ?>
                    <?php if (isset($machine['tambour'])): ?>
                        <input type="hidden" name="machines[<?= $index ?>][tambour]" value="<?= $machine['tambour'] ?>" />
                    <?php endif; ?>
                <?php else: ?>
                    <input type="hidden" name="machines[<?= $index ?>][machine]" value="<?= $machine['machine'] ?>" />
                    <input type="hidden" name="machines[<?= $index ?>][fill_rate]" value="<?= isset($machine['fill_rate']) ? htmlspecialchars($machine['fill_rate']) : '0.5' ?>" />
                    <?php if (isset($machine['brochures'])): ?>
                        <?php foreach ($machine['brochures'] as $brochureIndex => $brochure): ?>
                            <input type="hidden" name="machines[<?= $index ?>][brochures][<?= $brochureIndex ?>][nb_exemplaires]" value="<?= $brochure['nb_exemplaires'] ?>" />
                            <input type="hidden" name="machines[<?= $index ?>][brochures][<?= $brochureIndex ?>][nb_feuilles]" value="<?= $brochure['nb_feuilles'] ?>" />
                            <input type="hidden" name="machines[<?= $index ?>][brochures][<?= $brochureIndex ?>][taille]" value="<?= $brochure['taille'] ?>" />
                            <input type="hidden" name="machines[<?= $index ?>][brochures][<?= $brochureIndex ?>][rv]" value="<?= isset($brochure['rv']) ? $brochure['rv'] : 'non' ?>" />
                            <input type="hidden" name="machines[<?= $index ?>][brochures][<?= $brochureIndex ?>][couleur]" value="<?= isset($brochure['couleur']) ? $brochure['couleur'] : 'non' ?>" />
                            <input type="hidden" name="machines[<?= $index ?>][brochures][<?= $brochureIndex ?>][feuilles_payees]" value="<?= isset($brochure['feuilles_payees']) ? $brochure['feuilles_payees'] : 'non' ?>" />
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endforeach; ?>
            
            <!-- Champ "As-tu payé" -->
            <div class="form-group">
                <label class="col-md-4 control-label" for="payeoui">As-tu payé ?</label>
                <div class="col-md-4">
                    <label class="radio-inline">
                        <input type="radio" name="paye" value="oui" id="payeoui" onchange="updatePaymentAmount()"> Oui
                    </label>
                    <label class="radio-inline">
                        <input type="radio" name="paye" value="non" id="payenon" onchange="updatePaymentAmount()" checked> Non
                    </label>
                </div>
            </div>
            
            <!-- Champ montant -->
            <div class="form-group">
                <label class="col-md-4 control-label" for="cb1">Montant payé</label>
                <div class="col-md-4">
                    <input id="cb1" name="cb" class="form-control input-md" type="number" step="0.01" min="0" placeholder="0.00">
                    <span class="help-block">Montant en euros</span>
                </div>
            </div>
            
            <!-- Champ "Un petit mot" -->
            <div class="form-group">
                <label class="col-md-4 control-label" for="mot">Un petit mot, une réclamation, un encouragement, une info?</label>  
                <div class="col-md-4">
                    <textarea id="mot" name="mot" class="form-control input-md"></textarea>
                </div>
            </div>
            
            <hr>
            <div class="section">
                <div class="container">
                    <div class="row">
                        <div class="col-md-6">
                            <button type="button" id="btn-retour" class="btn btn-warning btn-block" onclick="returnToForm()">
                                <i class="fa fa-arrow-left"></i> Retour au formulaire
                            </button>
                        </div>
                        <div class="col-md-6">
                            <button id="singlebutton" name="enregistrer" value="1" class="btn btn-success btn-block">Enregistrer !</button>
                        </div>
                    </div>
                </div>
            </div>
        </fieldset>
    </form>
    <?php 
} else {
?>
<?php if(!empty($errors)): ?>
    <div class="alert alert-danger">
        <strong>Erreurs détectées :</strong>
        <ul>
            <?php foreach($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if(!empty($success_message)): ?>
    <div class="alert alert-success">
        <strong>Succès!</strong> <?= htmlspecialchars($success_message) ?>
    </div>
<?php endif; ?>

<?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
    <div class="alert alert-info">
        <h4>Debug POST:</h4>
        <pre><?php var_dump($_POST); ?></pre>
    </div>
<?php endif; ?>

<?php if (isset($debug_sql)): ?>
    <div class="alert alert-warning">
        <h4>Debug SQL:</h4>
        <p><strong>Requête:</strong> <?php echo htmlspecialchars($debug_sql); ?></p>
        <p><strong>Paramètres:</strong></p>
        <pre><?php var_dump($debug_params); ?></pre>
    </div>
<?php endif; ?>

<?php if (isset($debug_sql_vardump)): ?>
    <div class="alert alert-danger">
        <h4>Debug SQL avec var_dump:</h4>
        <?php echo $debug_sql_vardump; ?>
    </div>
<?php endif; ?>

<?php if (isset($debug_enregistrement)): ?>
    <div class="alert alert-warning">
        <h4>Debug Enregistrement:</h4>
        <?php echo $debug_enregistrement; ?>
    </div>
<?php endif; ?>

<?php if (isset($debug_simple)): ?>
    <div class="alert alert-success">
        <h4>Debug Simple:</h4>
        <p><?php echo htmlspecialchars($debug_simple); ?></p>
    </div>
<?php endif; ?>

<?php if (isset($debug_model_executed)): ?>
    <div class="alert alert-info">
        <h4>Debug Modèle:</h4>
        <p><?php echo htmlspecialchars($debug_model_executed); ?></p>
    </div>
<?php endif; ?>

<?php if (isset($debug_post)): ?>
    <div class="alert alert-info">
        <h4>Debug POST détecté:</h4>
        <p><?php echo htmlspecialchars($debug_post); ?></p>
        <?php if (isset($debug_ok)): ?>
            <p><strong>Bouton 'ok':</strong> <?php echo htmlspecialchars($debug_ok); ?></p>
        <?php endif; ?>
        <?php if (isset($debug_enregistrer)): ?>
            <p><strong>Bouton 'enregistrer':</strong> <?php echo htmlspecialchars($debug_enregistrer); ?></p>
        <?php endif; ?>
        <?php if (isset($debug_machines)): ?>
            <p><strong>Machines:</strong> <?php echo htmlspecialchars($debug_machines); ?></p>
        <?php endif; ?>
        <?php if (isset($debug_post_keys)): ?>
            <p><strong>Clés POST:</strong> <?php echo htmlspecialchars($debug_post_keys); ?></p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<form class="form-horizontal" action="#after" method="post" id="multimachines-form">
    <fieldset>
        <legend class="text-center"><?php _e('tirage_multimachines.form_title'); ?></legend>
        
        <!-- Contact -->
        <div class="form-group">
            <label class="col-md-4 control-label" for="contact"><?php _e('tirage_multimachines.contact'); ?></label>  
            <div class="col-md-4">
                <input id="contact" name="contact" <?= !empty($contact) ? 'value="'.$contact.'"' : 'placeholder="me@example.com"';?> class="form-control input-md" required type="text">
                <span class="help-block"><?php _e('tirage_multimachines.contact_help'); ?></span>
            </div>
        </div>
        
        <!-- Machines -->
        <div id="machines-container">
            <h4 class="text-center"><?php _e('tirage_multimachines.tirages'); ?></h4>
            
            <!-- Machine par défaut -->
                                        <?php 
            $index = 0;
            include __DIR__ . '/partials/machine_item.html.php';
            ?>
            
            <!-- Bouton pour ajouter une machine (à l'intérieur du container) -->
            <!-- Boutons actions -->
            <div id="buttons-container" class="row" style="margin: 20px 0;">
                <div class="col-md-6 text-center">
                    <button type="button" id="add-machine" class="btn btn-success btn-lg">
                        <i class="fa fa-plus-circle"></i> <?php _e('tirage_multimachines.add_tirage'); ?>
                    </button>
                </div>
                <div class="col-md-6 text-center">
                    <button id="singlebutton" name="ok" class="btn btn-success btn-lg">
                        <?php _e('tirage_multimachines.next'); ?> <i class="fa fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div><!-- Fin machines-container -->
        
        <!-- Récapitulatif total -->
        <div class="alert alert-info">
            <h4 class="text-center"><?php _e('tirage_multimachines.summary'); ?></h4>
            <p class="text-center"><strong><?php _e('tirage_multimachines.total_price'); ?> <span id="prix-total">0.00€</span></strong></p>
        </div>
        
        <!-- Bouton suivant -->

    </fieldset>
</form>

    <!-- Formulaire d'enregistrement -->
    <form class="form-horizontal" action="" method="post">
        <fieldset>
            
            <!-- Champs cachés -->
            <input type="hidden" value="<?php echo $contact; ?>" name="contact"/>
            <input type="hidden" value="ok" name="ok"/>
            <?php foreach ($machines as $index => $machine): ?>
                <input type="hidden" name="machines[<?= $index ?>][type]" value="<?= $machine['type'] ?>" />
                <?php if ($machine['type'] === 'duplicopieur'): ?>
                    <input type="hidden" name="machines[<?= $index ?>][nb_masters]" value="<?= $machine['nb_masters'] ?>" />
                    <input type="hidden" name="machines[<?= $index ?>][nb_passages]" value="<?= $machine['nb_passages'] ?>" />
                    <input type="hidden" name="machines[<?= $index ?>][master_av]" value="<?= $machine['master_av'] ?>" />
                    <input type="hidden" name="machines[<?= $index ?>][master_ap]" value="<?= $machine['master_ap'] ?>" />
                    <input type="hidden" name="machines[<?= $index ?>][passage_av]" value="<?= $machine['passage_av'] ?>" />
                    <input type="hidden" name="machines[<?= $index ?>][passage_ap]" value="<?= $machine['passage_ap'] ?>" />
                    <input type="hidden" name="machines[<?= $index ?>][prix]" value="<?= $machine['prix'] ?>" />
                    <input type="hidden" name="machines[<?= $index ?>][rv]" value="<?= isset($machine['rv']) ? $machine['rv'] : 'non' ?>" />
                    <input type="hidden" name="machines[<?= $index ?>][feuilles_payees]" value="<?= isset($machine['feuilles_payees']) ? $machine['feuilles_payees'] : 'non' ?>" />
                    <input type="hidden" name="machines[<?= $index ?>][A4]" value="<?= isset($machine['A4']) ? $machine['A4'] : 'non' ?>" />
                    <?php if (isset($machine['duplicopieur_id'])): ?>
                        <input type="hidden" name="machines[<?= $index ?>][duplicopieur_id]" value="<?= $machine['duplicopieur_id'] ?>" />
                    <?php endif; ?>
                    <?php if (isset($machine['tambour'])): ?>
                        <input type="hidden" name="machines[<?= $index ?>][tambour]" value="<?= $machine['tambour'] ?>" />
                    <?php endif; ?>
                <?php else: ?>
                    <input type="hidden" name="machines[<?= $index ?>][machine]" value="<?= $machine['machine'] ?>" />
                    <input type="hidden" name="machines[<?= $index ?>][fill_rate]" value="<?= isset($machine['fill_rate']) ? htmlspecialchars($machine['fill_rate']) : '0.5' ?>" />
                    <input type="hidden" name="machines[<?= $index ?>][prix]" value="<?= $machine['prix'] ?>" />
                    <input type="hidden" name="machines[<?= $index ?>][rv]" value="<?= isset($machine['rv']) ? $machine['rv'] : 'non' ?>" />
                    <input type="hidden" name="machines[<?= $index ?>][feuilles_payees]" value="<?= isset($machine['feuilles_payees']) ? $machine['feuilles_payees'] : 'non' ?>" />
                    <input type="hidden" name="machines[<?= $index ?>][A4]" value="<?= isset($machine['A4']) ? $machine['A4'] : 'non' ?>" />
                    <?php if (isset($machine['brochures'])): ?>
                        <?php foreach ($machine['brochures'] as $brochureIndex => $brochure): ?>
                            <input type="hidden" name="machines[<?= $index ?>][brochures][<?= $brochureIndex ?>][nb_exemplaires]" value="<?= $brochure['nb_exemplaires'] ?>" />
                            <input type="hidden" name="machines[<?= $index ?>][brochures][<?= $brochureIndex ?>][nb_feuilles]" value="<?= $brochure['nb_feuilles'] ?>" />
                            <input type="hidden" name="machines[<?= $index ?>][brochures][<?= $brochureIndex ?>][taille]" value="<?= $brochure['taille'] ?>" />
                            <input type="hidden" name="machines[<?= $index ?>][brochures][<?= $brochureIndex ?>][rv]" value="<?= $brochure['rv'] ? 'oui' : 'non' ?>" />
                            <input type="hidden" name="machines[<?= $index ?>][brochures][<?= $brochureIndex ?>][couleur]" value="<?= $brochure['couleur'] ? 'oui' : 'non' ?>" />
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endforeach; ?>
            
            <!-- Champs communs -->

<?php } ?>

<script>
let machineCount = 1;

// ========================================
// GESTION DE LA SAUVEGARDE/RESTAURATION DES DONNÉES DU FORMULAIRE
// ========================================

/**
 * Sauvegarder toutes les données du formulaire dans sessionStorage
 */
function saveFormData() {
    const form = document.getElementById('multimachines-form');
    if (!form) {
        console.log('Formulaire multimachines-form non trouvé - probablement sur la page de confirmation');
        return;
    }
    
    try {
        const formData = new FormData(form);
        const data = {};
        
        // Convertir FormData en objet, gérer les arrays
        for (let [key, value] of formData.entries()) {
            if (data[key]) {
                // Si la clé existe déjà, convertir en array
                if (!Array.isArray(data[key])) {
                    data[key] = [data[key]];
                }
                data[key].push(value);
            } else {
                data[key] = value;
            }
        }
        
        // Sauvegarder aussi les radios/checkboxes non sélectionnés pour connaître l'état
        form.querySelectorAll('input[type="radio"], input[type="checkbox"]').forEach(input => {
            const name = input.name;
            if (input.type === 'checkbox') {
                if (!data[name]) data[name] = [];
                if (input.checked && !data[name].includes(input.value)) {
                    data[name].push(input.value);
                }
            } else if (input.type === 'radio' && input.checked) {
                data[name] = input.value;
            }
        });
        
        // Sauvegarder le nombre de machines pour pouvoir les recréer si nécessaire
        // Compter les indices des machines trouvés dans les inputs
        const machineIndicesInForm = new Set();
        form.querySelectorAll('input[name^="machines["], select[name^="machines["]').forEach(input => {
            const match = input.name.match(/machines\[(\d+)\]/);
            if (match) {
                machineIndicesInForm.add(parseInt(match[1]));
            }
        });
        const maxIndex = machineIndicesInForm.size > 0 ? Math.max(...machineIndicesInForm) : 0;
        const machineCountFromIndices = maxIndex + 1;
        
        const machineItems = form.querySelectorAll('.machine-item, [class*="machine-item"]').length;
        const machinePanels = form.querySelectorAll('[id^="duplicopieur-interface-"], [id^="photocopieur-interface-"]').length;
        
        // Utiliser le maximum entre les différentes méthodes de comptage
        data['_machine_count'] = Math.max(machineCountFromIndices, machineItems, machinePanels, machineCount, 1);
        
        console.log('💾 Sauvegarde - Nombre de machines détecté:', data['_machine_count'], {
            machineCountFromIndices,
            machineItems,
            machinePanels,
            machineCount,
            indices: Array.from(machineIndicesInForm)
        });
        
        // Sauvegarder l'état des interfaces masquées/affichées
        data['_ui_state'] = {};
        form.querySelectorAll('[id*="interface"]').forEach(el => {
            data['_ui_state'][el.id] = el.style.display !== 'none';
        });
        
        sessionStorage.setItem('tirage_multimachines_form_data', JSON.stringify(data));
        console.log('✅ Données du formulaire sauvegardées');
    } catch (e) {
        console.error('❌ Erreur lors de la sauvegarde:', e);
    }
}

/**
 * Ajouter une machine de manière asynchrone (retourne une Promise)
 */
function addMachineAsync(index) {
    return new Promise((resolve, reject) => {
        const container = document.getElementById('machines-container');
        if (!container) {
            reject('Container machines-container non trouvé');
            return;
        }
        
        fetch(`?get-machine-template&index=${index}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    reject(data.error);
                    return;
                }
                
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = data.html;
                const newMachineContainer = tempDiv.firstElementChild;
                
                if (!newMachineContainer) {
                    reject('Aucun élément trouvé dans le HTML généré');
                    return;
                }
                
                const addButtonContainer = document.getElementById('buttons-container');
                
                if (addButtonContainer && container.contains(addButtonContainer)) {
                    container.insertBefore(newMachineContainer, addButtonContainer);
                } else {
                    container.appendChild(newMachineContainer);
                }
                
                // Mettre à jour machineCount pour qu'il soit supérieur ou égal à l'index créé + 1
                machineCount = Math.max(machineCount, index + 1);
                
                // Ajouter l'événement pour supprimer
                const removeBtn = newMachineContainer.querySelector('.remove-machine');
                if (removeBtn) {
                    removeBtn.addEventListener('click', function() {
                        newMachineContainer.remove();
                        machineCount = Math.max(1, machineCount - 1);
                        calculateTotalPrice();
                        saveFormData();
                    });
                }
                
                // Initialiser la validation pour cette machine
                setTimeout(() => {
                    try {
                        toggleMachineType(index);
                        
                        const duplicopieurIdField = document.querySelector(`select[name="machines[${index}][duplicopieur_id]"]`) || document.querySelector(`input[name="machines[${index}][duplicopieur_id]"]`);
                        if (duplicopieurIdField && duplicopieurIdField.value) {
                            const duplicopieurId = duplicopieurIdField.value;
                            if (typeof updateDuplicopieurCounters === 'function') {
                                updateDuplicopieurCounters(duplicopieurId, index);
                            } else if (typeof loadTamboursForDuplicopieur === 'function') {
                                loadTamboursForDuplicopieur(duplicopieurId, index);
                            }
                        }
                        
                        console.log(`✅ Machine ${index} initialisée complètement`);
                        resolve(newMachineContainer);
                    } catch (e) {
                        console.error(`❌ Erreur lors de l'initialisation de la machine ${index}:`, e);
                        resolve(newMachineContainer); // Résoudre quand même pour ne pas bloquer
                    }
                }, 150);
            })
            .catch(error => {
                reject(error);
            });
    });
}

/**
 * Restaurer les données du formulaire depuis sessionStorage
 */
function restoreFormData() {
    const saved = sessionStorage.getItem('tirage_multimachines_form_data');
    if (!saved) {
        console.log('Aucune donnée sauvegardée à restaurer');
        return false;
    }
    
    const form = document.getElementById('multimachines-form');
    if (!form) {
        console.log('Formulaire non trouvé pour restauration');
        return false;
    }
    
    try {
        const data = JSON.parse(saved);
        console.log('🔄 Restauration des données du formulaire...');
        
        // Déterminer les indices des machines sauvegardées
        const savedMachineIndices = new Set();
        Object.keys(data).forEach(key => {
            if (key.startsWith('_')) return;
            const match = key.match(/machines\[(\d+)\]/);
            if (match) {
                savedMachineIndices.add(parseInt(match[1]));
            }
        });
        
        // Convertir en tableau trié
        const savedIndicesArray = Array.from(savedMachineIndices).sort((a, b) => a - b);
        const maxMachineIndex = savedIndicesArray.length > 0 ? Math.max(...savedIndicesArray) : 0;
        
        // Déterminer les indices des machines existantes dans le DOM
        const existingMachineIndices = new Set();
        form.querySelectorAll('input[name^="machines["], select[name^="machines["]').forEach(input => {
            const match = input.name.match(/machines\[(\d+)\]/);
            if (match) {
                existingMachineIndices.add(parseInt(match[1]));
            }
        });
        const existingIndicesArray = Array.from(existingMachineIndices).sort((a, b) => a - b);
        
        // Trouver les indices manquants (sauvegardés mais pas présents dans le DOM)
        const missingIndices = savedIndicesArray.filter(idx => !existingMachineIndices.has(idx));
        
        console.log(`🔍 Machines sauvegardées: indices ${savedIndicesArray.join(', ')}`, {
            savedIndices: savedIndicesArray,
            existingIndices: existingIndicesArray,
            missingIndices: missingIndices,
            maxIndex: maxMachineIndex
        });
        
        // Fonction pour restaurer les données une fois que toutes les machines sont créées
        const restoreFields = () => {
            console.log('🔄 Début de la restauration des champs...');
            let restoredCount = 0;
            let missingCount = 0;
            
            // Restaurer chaque champ
            Object.keys(data).forEach(key => {
                // Ignorer les métadonnées
                if (key.startsWith('_')) return;
                
                const inputs = form.querySelectorAll(`[name="${key}"]`);
                if (inputs.length === 0) {
                    // Si le champ n'existe pas, c'est peut-être une brochure ou un champ pas encore créé
                    const brochureMatch = key.match(/machines\[(\d+)\]\[brochures\]\[(\d+)\]\[(\w+)\]/);
                    if (brochureMatch) {
                        // Les brochures seront restaurées après - pour l'instant on ignore
                        missingCount++;
                        return;
                    }
                    // Autre champ manquant - log pour debug
                    if (!key.includes('brochures')) {
                        console.log(`⚠️ Champ non trouvé: ${key}`);
                        missingCount++;
                    }
                    return;
                }
                
                inputs.forEach(input => {
                    try {
                        if (input.type === 'checkbox') {
                            const value = Array.isArray(data[key]) ? data[key] : [data[key]];
                            input.checked = value.includes(input.value);
                        } else if (input.type === 'radio') {
                            input.checked = input.value === data[key];
                        } else {
                            input.value = data[key];
                        }
                        restoredCount++;
                    } catch (e) {
                        console.error(`❌ Erreur lors de la restauration de ${key}:`, e);
                    }
                });
            });
            
            console.log(`✅ Champs restaurés: ${restoredCount}, champs manquants: ${missingCount}`);
            
            // Restaurer l'état des interfaces
            if (data['_ui_state']) {
                Object.keys(data['_ui_state']).forEach(id => {
                    const el = document.getElementById(id);
                    if (el) {
                        el.style.display = data['_ui_state'][id] ? '' : 'none';
                    }
                });
            }
            
            // Déclencher les événements pour mettre à jour l'UI
            form.querySelectorAll('input[type="radio"]:checked').forEach(radio => {
                const name = radio.name;
                const match = name.match(/machines\[(\d+)\]\[type\]/);
                if (match) {
                    const index = match[1];
                    setTimeout(() => {
                        toggleMachineType(index);
                        // Si c'est un duplicopieur, charger les tambours et les compteurs
                        const duplicopieurSelect = document.querySelector(`select[name="machines[${index}][duplicopieur_id]"]`);
                        if (duplicopieurSelect && duplicopieurSelect.value) {
                            // Déclencher l'événement change pour charger les tambours et compteurs
                            if (typeof updateDuplicopieurCounters === 'function') {
                                updateDuplicopieurCounters(duplicopieurSelect.value, index);
                            } else if (typeof loadTamboursForDuplicopieur === 'function') {
                                loadTamboursForDuplicopieur(duplicopieurSelect.value, index);
                            }
                            // Déclencher aussi l'événement change natif
                            const event = new Event('change', { bubbles: true });
                            duplicopieurSelect.dispatchEvent(event);
                        }
                    }, 50);
                }
                
                const modeMatch = name.match(/machines\[(\d+)\]\[mode_saisie\]/);
                if (modeMatch) {
                    const index = modeMatch[1];
                    setTimeout(() => toggleSaisieMode(index), 50);
                }
            });
            
            // Déclencher les événements change sur les selects pour charger les données dépendantes
            form.querySelectorAll('select').forEach(select => {
                if (select.value) {
                    // Déclencher l'événement change pour charger les données dépendantes
                    const event = new Event('change', { bubbles: true });
                    setTimeout(() => {
                        try {
                            select.dispatchEvent(event);
                        } catch (e) {
                            console.error('❌ Erreur lors du déclenchement de l\'événement change:', e);
                        }
                    }, 100);
                }
            });
            
            // Vérifier et afficher le slider de taux de remplissage pour chaque machine si la couleur est cochée
            form.querySelectorAll('input[id^="couleur_"][type="checkbox"]').forEach(checkbox => {
                const match = checkbox.id.match(/couleur_(\d+)_\d+/);
                if (match) {
                    const machineIndex = match[1];
                    if (checkbox.checked) {
                        setTimeout(() => {
                            if (typeof toggleFillRateDisplay === 'function') {
                                toggleFillRateDisplay(machineIndex);
                            }
                        }, 100);
                    }
                }
            });
            
            // Attendre un peu plus avant de recalculer le prix pour être sûr que tout est prêt
            setTimeout(() => {
                if (typeof calculateTotalPrice === 'function') {
                    console.log('💰 Recalcul du prix total...');
                    calculateTotalPrice();
                }
            }, 500);
            
            console.log('✅ Restauration des données terminée');
        };
        
        // Si des machines manquent, les créer avec les bons indices
        if (missingIndices.length > 0) {
            console.log(`🔨 Création de ${missingIndices.length} machine(s) manquante(s) avec indices: ${missingIndices.join(', ')}...`);
            
            // Créer les machines une par une (séquentiellement) avec les bons indices
            const createMachinesSequentially = async () => {
                for (const machineIndex of missingIndices) {
                    try {
                        console.log(`🔨 Création machine avec index ${machineIndex}...`);
                        
                        // Créer la machine avec l'index spécifique
                        await addMachineAsync(machineIndex);
                        console.log(`✅ Machine ${machineIndex} créée et initialisée`);
                        
                        // Attendre un peu entre chaque création pour laisser le temps au DOM de se mettre à jour
                        await new Promise(resolve => setTimeout(resolve, 300));
                    } catch (error) {
                        console.error(`❌ Erreur lors de la création de la machine ${machineIndex}:`, error);
                        // Continuer même en cas d'erreur pour ne pas bloquer
                    }
                }
                
                // Vérifier que toutes les machines sont bien présentes avant de restaurer
                const finalIndices = new Set();
                form.querySelectorAll('input[name^="machines["], select[name^="machines["]').forEach(input => {
                    const match = input.name.match(/machines\[(\d+)\]/);
                    if (match) {
                        finalIndices.add(parseInt(match[1]));
                    }
                });
                console.log(`🔍 Vérification finale: machines avec indices ${Array.from(finalIndices).sort((a, b) => a - b).join(', ')}`);
                
                console.log('✅ Toutes les machines créées, restauration des données...');
                // Attendre un peu plus avant de restaurer pour être sûr que tout est prêt
                setTimeout(restoreFields, 600);
            };
            
            createMachinesSequentially();
        } else {
            // Pas besoin de créer de machines supplémentaires, restaurer directement
            console.log('✅ Toutes les machines sont déjà présentes, restauration directe...');
            restoreFields();
        }
        
        return true;
    } catch (e) {
        console.error('❌ Erreur lors de la restauration:', e);
        return false;
    }
}

/**
 * Fonction pour retourner au formulaire depuis la page de confirmation
 */
function returnToForm() {
    // Les données sont déjà sauvegardées dans sessionStorage
    // Recharger la page principale avec un paramètre pour déclencher la restauration
    window.location.href = '?tirage_multimachines&retour=1';
}

/**
 * Initialiser la sauvegarde automatique du formulaire
 */
function initAutoSave() {
    const form = document.getElementById('multimachines-form');
    if (!form) return;
    
    // Sauvegarder à chaque changement dans le formulaire
    form.addEventListener('input', function(e) {
        // Délai pour éviter trop de sauvegardes
        clearTimeout(window.autoSaveTimeout);
        window.autoSaveTimeout = setTimeout(saveFormData, 500);
    });
    
    form.addEventListener('change', function(e) {
        saveFormData();
    });
    
    // Sauvegarder avant la soumission du formulaire
    form.addEventListener('submit', function(e) {
        saveFormData();
    });
    
    console.log('✅ Auto-sauvegarde activée');
}

// Prix depuis la base de données
const prixData = <?= json_encode($prix_data ?? []) ?>;

// Debug : afficher la structure des prix
console.log('🔍 DEBUG PRIX - Prix data:', prixData);
console.log('🔍 DEBUG PRIX - Type de prixData:', typeof prixData);
console.log('🔍 DEBUG PRIX - Taille de prixData:', Object.keys(prixData).length);
console.log('🔍 DEBUG PRIX - Clés disponibles:', Object.keys(prixData));
console.log('🔍 DEBUG PRIX - dupli_1 structure:', prixData['dupli_1']);
console.log('🔍 DEBUG PRIX - tambour_noir price:', prixData['dupli_1'] ? prixData['dupli_1']['tambour_noir'] : 'NOT_FOUND');

// Fonction pour trouver la clé de prix d'une machine par son nom
function findMachinePriceKey(machineName) {
    console.log('🔍 Recherche de la clé pour la machine:', machineName);
    
    // Parcourir toutes les clés de prixData
    for (const key in prixData) {
        if (key.startsWith('photocop_')) {
            // Vérifier si cette clé correspond à la machine recherchée
            // Pour l'instant, on va utiliser une approche simple
            // TODO: Améliorer cette logique si nécessaire
            console.log('🔍 Clé trouvée:', key);
        }
    }
    
    // Vérifier le cache des mappings
    if (window.machinePriceCache && window.machinePriceCache[machineName]) {
        const priceKey = window.machinePriceCache[machineName];
        console.log(`🔑 Clé depuis le cache pour ${machineName}: ${priceKey}`);
        return priceKey;
    }
    
    // Si on ne trouve pas, essayer de trouver la première clé photocop_ disponible
    for (const key in prixData) {
        if (key.startsWith('photocop_') && prixData[key]) {
            console.log('🔍 Utilisation de la clé de fallback:', key);
            return key;
        }
    }
    
    console.log('❌ Aucune clé trouvée pour:', machineName);
    return null;
}

function toggleSaisieMode(machineIndex) {
    var compteursRadio = document.querySelector(`input[name="machines[${machineIndex}][mode_saisie]"][value="compteurs"]`);
    var manuelRadio = document.querySelector(`input[name="machines[${machineIndex}][mode_saisie]"][value="manuel"]`);
    var compteursMode = document.getElementById(`compteurs-mode-${machineIndex}`);
    var manuelMode = document.getElementById(`manuel-mode-${machineIndex}`);
    
    if (compteursRadio.checked) {
        compteursMode.style.display = '';
        manuelMode.style.display = 'none';
    } else if (manuelRadio.checked) {
        compteursMode.style.display = 'none';
        manuelMode.style.display = '';
    }
    
    calculateTotalPrice();
}

function toggleMachineType(machineIndex) {
    var duplicopieurRadio = document.querySelector(`input[name="machines[${machineIndex}][type]"][value="duplicopieur"]`);
    var photocopieurRadio = document.querySelector(`input[name="machines[${machineIndex}][type]"][value="photocopieur"]`);
    var duplicopieurInterface = document.getElementById(`duplicopieur-interface-${machineIndex}`);
    var photocopieurInterface = document.getElementById(`photocopieur-interface-${machineIndex}`);
    var duplicopieurSelect = document.querySelector(`select[name="machines[${machineIndex}][duplicopieur_id]"]`);
    
    // Vérifier que tous les éléments existent
    if (!duplicopieurRadio || !photocopieurRadio || !duplicopieurInterface || !photocopieurInterface) {
        console.log('Éléments manquants pour toggleMachineType:', {
            machineIndex: machineIndex,
            duplicopieurRadio: !!duplicopieurRadio,
            photocopieurRadio: !!photocopieurRadio,
            duplicopieurInterface: !!duplicopieurInterface,
            photocopieurInterface: !!photocopieurInterface
        });
        return;
    }
    
    if (duplicopieurRadio.checked) {
        // Duplicopieur sélectionné - rendre le champ duplicopieur_id requis
        if (duplicopieurSelect) {
            duplicopieurSelect.required = true;
        }
        duplicopieurInterface.style.display = 'block';
        photocopieurInterface.style.display = 'none';
        
        // Activer les champs duplicopieur
        var duplicopieurFields = duplicopieurInterface.querySelectorAll('input, select, textarea');
        duplicopieurFields.forEach(function(field) {
            field.disabled = false;
        });
        
        // Désactiver ET désactiver la validation des champs photocopieur
        var photocopFields = photocopieurInterface.querySelectorAll('input, select, textarea');
        photocopFields.forEach(function(field) {
            field.disabled = true; // CORRECTION: désactiver pour ne pas envoyer dans POST
            field.removeAttribute('required');
        });
        
    } else if (photocopieurRadio.checked) {
        // Photocopieur sélectionné - rendre le champ duplicopieur_id non requis
        if (duplicopieurSelect) {
            duplicopieurSelect.required = false;
            duplicopieurSelect.value = ''; // Vider le champ
        }
        duplicopieurInterface.style.display = 'none';
        photocopieurInterface.style.display = 'block';
        
        // Désactiver les champs duplicopieur
        var duplicopieurFields = duplicopieurInterface.querySelectorAll('input, select, textarea');
        duplicopieurFields.forEach(function(field) {
            field.disabled = true; // CORRECTION: désactiver pour ne pas envoyer dans POST
            field.removeAttribute('required');
        });
        
        // Activer ET activer la validation des champs photocopieur
        var photocopFields = photocopieurInterface.querySelectorAll('input, select, textarea');
        photocopFields.forEach(function(field) {
            field.disabled = false;
        });
        
        // Activer la validation des champs requis
        var requiredFields = photocopieurInterface.querySelectorAll('input[name*="[nb_exemplaires]"], input[name*="[nb_feuilles]"]');
        requiredFields.forEach(function(field) {
            field.setAttribute('required', 'required');
        });
        
        // Ajouter les gestionnaires pour mettre à jour le total en temps réel
        var exemplairesInput = photocopieurInterface.querySelector('input[name*="[nb_exemplaires]"]');
        var feuillesInput = photocopieurInterface.querySelector('input[name*="[nb_feuilles]"]');
        
        if (exemplairesInput && feuillesInput) {
            exemplairesInput.addEventListener('input', updateTotalFeuilles);
            feuillesInput.addEventListener('input', updateTotalFeuilles);
        }
    }
    
    calculateTotalPrice();
    // Mettre à jour le preview du panel
    updatePanelPreview(machineIndex);
    
    // Mettre à jour le total des feuilles pour cette machine
    updateTotalFeuillesForMachine(machineIndex);
}

// Fonction pour mettre à jour le total des feuilles en temps réel
function updateTotalFeuilles() {
    var machineIndex = this.closest('[data-index]').getAttribute('data-index');
    updateTotalFeuillesForMachine(machineIndex);
}

// Fonction pour mettre à jour le total des feuilles pour une machine spécifique
function updateTotalFeuillesForMachine(machineIndex) {
    var brochures = document.querySelectorAll(`[data-index="${machineIndex}"] .brochure-item`);
    
    brochures.forEach(function(brochure, brochureIndex) {
        var exemplairesInput = brochure.querySelector('input[name*="[nb_exemplaires]"]');
        var feuillesInput = brochure.querySelector('input[name*="[nb_feuilles]"]');
        var totalSpan = document.getElementById(`total-feuilles-${machineIndex}-${brochureIndex}`);
        
        if (exemplairesInput && feuillesInput && totalSpan) {
            var exemplaires = parseInt(exemplairesInput.value) || 0;
            var feuilles = parseInt(feuillesInput.value) || 0;
            var total = exemplaires * feuilles;
            
            if (total > 0) {
                totalSpan.textContent = total + (total > 1 ? ' feuilles' : ' feuille');
                totalSpan.style.color = '#007bff';
            } else {
                totalSpan.textContent = '0 feuille';
                totalSpan.style.color = '#dc3545';
            }
        }
    });
}

function calculateMachinePrice(machineIndex) {
    console.log("🔍 calculateMachinePrice appelé avec index:", machineIndex);
    var machineElement = document.querySelector(`[data-index="${machineIndex}"]`);
    console.log("🔍 machineElement trouvé:", machineElement ? "oui" : "non");
    if (!machineElement) {
        console.log("❌ ERREUR: machineElement non trouvé pour index", machineIndex);
        return 0;
    }
    
    var typeRadio = machineElement.querySelector(`input[name="machines[${machineIndex}][type]"]:checked`);
    console.log("🔍 typeRadio trouvé:", typeRadio ? typeRadio.value : "non");
    if (!typeRadio) {
        console.log("❌ ERREUR: typeRadio non trouvé pour index", machineIndex);
        return 0;
    }
    
    var price = 0;
    var detailCalcul = '';
    
    if (typeRadio.value === 'duplicopieur') {
        console.log("🔍 Calcul duplicopieur pour index:", machineIndex);
        // Calcul pour duplicopieur
        var modeSaisieRadio = machineElement.querySelector(`input[name="machines[${machineIndex}][mode_saisie]"]:checked`);
        console.log("🔍 modeSaisieRadio trouvé:", modeSaisieRadio ? modeSaisieRadio.value : "non");
        var nbMasters = 0;
        var nbPassages = 0;
        
        if (modeSaisieRadio && modeSaisieRadio.value === 'compteurs') {
            var masterAvElement = machineElement.querySelector(`#master_av_${machineIndex}`);
            var masterApElement = machineElement.querySelector(`#master_ap_${machineIndex}`);
            var passageAvElement = machineElement.querySelector(`#passage_av_${machineIndex}`);
            var passageApElement = machineElement.querySelector(`#passage_ap_${machineIndex}`);
            
            // Debug: vérifier le contenu de machineElement
            console.log("machineElement.innerHTML:", machineElement.innerHTML.substring(0, 300) + '...');
            console.log("Recherche des éléments avec ID:", {
                masterAv: `#master_av_${machineIndex}`,
                masterAp: `#master_ap_${machineIndex}`,
                passageAv: `#passage_av_${machineIndex}`,
                passageAp: `#passage_ap_${machineIndex}`
            });
            
            console.log("Éléments trouvés:", {
                masterAv: masterAvElement ? "oui" : "non",
                masterAp: masterApElement ? "oui" : "non", 
                passageAv: passageAvElement ? "oui" : "non",
                passageAp: passageApElement ? "oui" : "non"
            });
            
            var masterAv = parseFloat(masterAvElement ? masterAvElement.value : 0) || 0;
            var masterAp = parseFloat(masterApElement ? masterApElement.value : 0) || 0;
            var passageAv = parseFloat(passageAvElement ? passageAvElement.value : 0) || 0;
            var passageAp = parseFloat(passageApElement ? passageApElement.value : 0) || 0;
            
            console.log("🔍 Valeurs brutes des champs:", {
                masterAvElement_value: masterAvElement ? masterAvElement.value : "élément non trouvé",
                masterApElement_value: masterApElement ? masterApElement.value : "élément non trouvé",
                passageAvElement_value: passageAvElement ? passageAvElement.value : "élément non trouvé",
                passageApElement_value: passageApElement ? passageApElement.value : "élément non trouvé"
            });
            
            nbMasters = Math.max(0, masterAp - masterAv);
            nbPassages = Math.max(0, passageAp - passageAv);
            
            console.log("🔍 Valeurs calculées:", {
                masterAv: masterAv,
                masterAp: masterAp,
                passageAv: passageAv,
                passageAp: passageAp,
                nbMasters: nbMasters,
                nbPassages: nbPassages
            });
    } else {
            nbMasters = parseFloat(machineElement.querySelector(`#nb_masters_${machineIndex}`).value) || 0;
            nbPassages = parseFloat(machineElement.querySelector(`#nb_passages_${machineIndex}`).value) || 0;
        }
        
        // Calculer nb_f selon les options
        var nb_f = nbPassages;
        var rv = machineElement.querySelector(`input[name="machines[${machineIndex}][rv]"]`).checked;
        var feuilles_payees = machineElement.querySelector(`input[name="machines[${machineIndex}][feuilles_payees]"]`) ? machineElement.querySelector(`input[name="machines[${machineIndex}][feuilles_payees]"]`).checked : false;
        var a4 = machineElement.querySelector(`input[name="machines[${machineIndex}][A4]"]`).checked;
        
        if (rv) nb_f = nbPassages / 2;
        if (feuilles_payees) nb_f = 0;
        
        
        // Déterminer la taille selon les options
        var taille = 'A3'; // Par défaut A3
        var a4 = machineElement.querySelector(`input[name="machines[${machineIndex}][A4]"]`).checked;
        if (a4) taille = 'A4';
        
        // Tarifs depuis la base de données selon la nouvelle structure
        // Utiliser l'ID du duplicopieur sélectionné
        var duplicopieurSelect = machineElement.querySelector('select[name*="[duplicopieur_id]"]');
        var duplicopieurId = duplicopieurSelect ? duplicopieurSelect.value : '<?= $duplicopieur_selectionne['id'] ?? '' ?>'; // Utiliser l'ID du duplicopieur sélectionné
        var machineKey = 'dupli_' + duplicopieurId;
        var prixMaster = prixData[machineKey] && prixData[machineKey]['master'] ? prixData[machineKey]['master']['unite'] : 0;
        
        // Prix des passages selon le tambour sélectionné
        var tambourSelect = machineElement.querySelector('select[name*="[tambour]"]');
        var tambourSelected = tambourSelect ? tambourSelect.value : '';
        var prixPassage = 0;
        
        console.log('🔍 Calcul prix passage - machineKey:', machineKey, 'tambourSelected:', tambourSelected);
        console.log('🔍 prixData[machineKey]:', prixData[machineKey]);
        
        if (tambourSelected && prixData[machineKey] && prixData[machineKey][tambourSelected]) {
            prixPassage = prixData[machineKey][tambourSelected]['unite'] || 0;
            console.log('✅ Prix passage (tambour sélectionné):', prixPassage);
        } else if (prixData[machineKey] && prixData[machineKey]['tambour_noir']) {
            // Fallback sur le tambour noir si pas de tambour spécifique
            prixPassage = prixData[machineKey]['tambour_noir']['unite'] || 0;
            console.log('✅ Prix passage (tambour noir fallback):', prixPassage);
        } else {
            console.log('❌ Aucun prix trouvé pour machineKey:', machineKey);
        }
        
        var prixPapier = prixData['papier'] && prixData['papier'][taille] ? prixData['papier'][taille] : 0;
        
        // NOUVELLE LOGIQUE : A4 = A3/2 pour masters et passages
        if (taille === 'A4') {
            prixMaster = prixMaster / 2;
            prixPassage = prixPassage / 2;
        }
        
        console.log("Prix calculés:", {
            taille: taille,
            machineKey: machineKey,
            prixMaster: prixMaster,
            prixPassage: prixPassage,
            prixPapier: prixPapier
        });
        
        var coutMasters = nbMasters * prixMaster;
        var coutPassages = nbPassages * prixPassage;
        var coutPapier = nb_f * prixPapier;
        
        price = coutMasters + coutPassages + coutPapier;
        
        // Vérifier que les prix sont disponibles
        if (prixMaster === 0 && prixPassage === 0 && prixPapier === 0) {
            detailCalcul = `
                <div class="price-detail" style="font-size: 0.9em; color: red; margin-top: 5px;">
                    <strong>⚠️ Erreur :</strong> Les prix ne sont pas disponibles dans la base de données.<br>
                    Veuillez vérifier la configuration des prix.
                </div>
            `;
            price = 0;
        } else {
            // Détail du calcul
            detailCalcul = `
                <div class="price-detail" style="font-size: 0.9em; color: #666; margin-top: 5px;">
                    <strong><?php _e('tirage_multimachines.calculation_detail'); ?> :</strong><br>
                    • ${nbMasters} masters × ${prixMaster.toFixed(2)}€ = ${coutMasters.toFixed(2)}€<br>
                    • ${nbPassages} passages × ${prixPassage.toFixed(2)}€ = ${coutPassages.toFixed(2)}€<br>
                    • ${nb_f.toFixed(0)} feuilles papier × ${prixPapier.toFixed(2)}€ = ${coutPapier.toFixed(2)}€<br>
                    <strong>Total : ${price.toFixed(2)}€</strong>
                </div>
            `;
        }
        
    } else if (typeRadio.value === 'photocopieur') {
        // Calcul pour photocopieur
        var brochures = machineElement.querySelectorAll('.brochure-item');
        var totalExemplaires = 0;
        
        brochures.forEach(function(brochure) {
            var nbExemplaires = parseFloat(brochure.querySelector('input[name*="[nb_exemplaires]"]').value) || 0;
            var nbFeuilles = parseFloat(brochure.querySelector('input[name*="[nb_feuilles]"]').value) || 0;
            var taille = brochure.querySelector('input[name*="[taille]"]:checked').value;
            var rv = brochure.querySelector('input[name*="[rv]"]').checked;
            var couleur = brochure.querySelector('input[name*="[couleur]"]').checked;
            var feuilles_payees = brochure.querySelector('input[name*="[feuilles_payees]"]') ? brochure.querySelector('input[name*="[feuilles_payees]"]').checked : false;
            
            // Calculer le prix selon la taille et les options
            var prixPapier = prixData['papier'] && prixData['papier'][taille] ? prixData['papier'][taille] : 0;
            
            // Calculer le prix d'encre selon le type de photocopieuse
            var photocopName = machineElement.querySelector('select[name*="[machine]"]').value;
            var prixEncre = 0;
            
            // Récupérer le taux de remplissage
            var fillRateElement = machineElement.querySelector('#fill_rate_photocop_' + machineIndex);
            var fillRate = fillRateElement ? parseFloat(fillRateElement.value) : 0.5;
            var fillRateMultiplier = couleur ? (fillRate / 0.5) : 1.0; // 50% = ×1, 100% = ×2
            
            // NOUVELLE STRUCTURE : Utiliser la fonction pour trouver la clé dynamique
            var machineKey = findMachinePriceKey(photocopName);
            console.log('🔑 Clé trouvée pour', photocopName, ':', machineKey);
            
            if (machineKey && prixData[machineKey]) {
                var machinePrices = prixData[machineKey];
                
                if (photocopName.toLowerCase() === 'comcolor') {
                    // Photocopieur à encre : additionner toutes les encres
                    if (couleur) {
                        // Couleur : bleue + couleur + jaune + noire + rouge (avec taux de remplissage)
                        prixEncre += (machinePrices['bleue']?.unite || 0) * fillRateMultiplier;
                        prixEncre += (machinePrices['couleur']?.unite || 0) * fillRateMultiplier;
                        prixEncre += (machinePrices['jaune']?.unite || 0) * fillRateMultiplier;
                        prixEncre += (machinePrices['noire']?.unite || 0) * fillRateMultiplier;
                        prixEncre += (machinePrices['rouge']?.unite || 0) * fillRateMultiplier;
                    } else {
                        // Noir et blanc : seulement noire (pas de taux de remplissage)
                        prixEncre += (machinePrices['noire']?.unite || 0);
                    }
                } else if (photocopName.toLowerCase() === 'konika') {
                    // Photocopieur à toner : additionner tous les toners + tambour + developer
                    if (couleur) {
                        // Couleur : cyan + jaune + magenta + noir + tambour + dev (avec taux de remplissage)
                        prixEncre += (machinePrices['cyan']?.unite || 0) * fillRateMultiplier;
                        prixEncre += (machinePrices['jaune']?.unite || 0) * fillRateMultiplier;
                        prixEncre += (machinePrices['magenta']?.unite || 0) * fillRateMultiplier;
                        prixEncre += (machinePrices['noir']?.unite || 0) * fillRateMultiplier;
                        prixEncre += (machinePrices['tambour']?.unite || 0);
                        prixEncre += (machinePrices['dev']?.unite || 0);
                    } else {
                        // Noir et blanc : noir + tambour + dev
                        prixEncre += (machinePrices['noir']?.unite || 0);
                        prixEncre += (machinePrices['tambour']?.unite || 0);
                        prixEncre += (machinePrices['dev']?.unite || 0);
                    }
                }
            }
            
            // Ajuster selon la taille (A3 = prix normal, A4 = prix/2)
            if (taille === 'A4') prixEncre = prixEncre / 2;
            
            // Calculer le coût
            var nbPages = nbExemplaires * nbFeuilles;
            var coutPapier = feuilles_payees ? 0 : (nbPages * prixPapier); // Papier = nombre de feuilles (0 si déjà payées)
            var coutEncre = nbPages * prixEncre; // Encre de base
            if (rv) coutEncre = coutEncre * 2; // Recto-verso = 2 fois plus d'encre
            var coutBrochure = coutPapier + coutEncre;
            
            console.log(`Brochure ${taille}: exemplaires=${nbExemplaires}, feuilles=${nbFeuilles}, rv=${rv}, nbPages=${nbPages}, prixPapier=${prixPapier}, prixEncre=${prixEncre}, coutBrochure=${coutBrochure}`);
            
            price += coutBrochure;
            
            totalExemplaires += nbExemplaires;
        });
        
        // Détail du calcul pour photocopieur
        var prixPapierMoyen = 0;
        var prixEncreMoyen = 0;
        var totalPages = 0;
        var totalPagesEncre = 0;
        var coutEncreTotal = 0;
        var coutPapierTotal = 0;
        var detailEncre = '';
        
        brochures.forEach(function(brochure) {
            var nbExemplaires = parseFloat(brochure.querySelector('input[name*="[nb_exemplaires]"]').value) || 0;
            var nbFeuilles = parseFloat(brochure.querySelector('input[name*="[nb_feuilles]"]').value) || 0;
            var taille = brochure.querySelector('input[name*="[taille]"]:checked').value;
            var couleur = brochure.querySelector('input[name*="[couleur]"]').checked;
            var rv = brochure.querySelector('input[name*="[rv]"]').checked;
            
            var prixPapier = prixData['papier'] && prixData['papier'][taille] ? prixData['papier'][taille] : 0;
            var prixEncre = 0;
            var detailEncreBrochure = '';
            
            var photocopName = machineElement.querySelector('select[name*="[machine]"]').value;
            
            // Récupérer le taux de remplissage (comme dans la première boucle)
            var fillRateElement = machineElement.querySelector('#fill_rate_photocop_' + machineIndex);
            var fillRate = fillRateElement ? parseFloat(fillRateElement.value) : 0.5;
            var fillRateMultiplier = couleur ? (fillRate / 0.5) : 1.0; // 50% = ×1, 100% = ×2
            
            // NOUVELLE STRUCTURE : Utiliser la fonction pour trouver la clé dynamique
            var machineKey = findMachinePriceKey(photocopName);
            console.log('🔑 Clé trouvée pour le détail', photocopName, ':', machineKey);
            
            if (machineKey && prixData[machineKey]) {
                var machinePrices = prixData[machineKey];
                
                if (photocopName.toLowerCase() === 'comcolor') {
                    // Photocopieur à encre : additionner toutes les encres
                    if (couleur) {
                        // Couleur : bleue + couleur + jaune + noire + rouge (avec taux de remplissage)
                        var bleue = (machinePrices['bleue']?.unite || 0) * fillRateMultiplier;
                        var couleurPrice = (machinePrices['couleur']?.unite || 0) * fillRateMultiplier;
                        var jaune = (machinePrices['jaune']?.unite || 0) * fillRateMultiplier;
                        var noire = (machinePrices['noire']?.unite || 0) * fillRateMultiplier;
                        var rouge = (machinePrices['rouge']?.unite || 0) * fillRateMultiplier;
                        
                        prixEncre = bleue + couleurPrice + jaune + noire + rouge;
                        
                        // Ajuster selon la taille AVANT d'afficher le détail
                        var prixEncrePourDetail = prixEncre;
                        if (taille === 'A4') prixEncrePourDetail = prixEncre / 2;
                        
                        var bleueDetail = taille === 'A4' ? bleue / 2 : bleue;
                        var couleurPriceDetail = taille === 'A4' ? couleurPrice / 2 : couleurPrice;
                        var jauneDetail = taille === 'A4' ? jaune / 2 : jaune;
                        var noireDetail = taille === 'A4' ? noire / 2 : noire;
                        var rougeDetail = taille === 'A4' ? rouge / 2 : rouge;
                        
                        detailEncreBrochure = `Bleue: ${bleueDetail.toFixed(4)}€ + Couleur: ${couleurPriceDetail.toFixed(4)}€ + Jaune: ${jauneDetail.toFixed(4)}€ + Noire: ${noireDetail.toFixed(4)}€ + Rouge: ${rougeDetail.toFixed(4)}€ = ${prixEncrePourDetail.toFixed(4)}€`;
                    } else {
                        // Noir et blanc : seulement noire (pas de taux de remplissage)
                        prixEncre = machinePrices['noire']?.unite || 0;
                        
                        // Ajuster selon la taille AVANT d'afficher le détail
                        var prixEncrePourDetail = prixEncre;
                        if (taille === 'A4') prixEncrePourDetail = prixEncre / 2;
                        
                        detailEncreBrochure = `Noire: ${prixEncrePourDetail.toFixed(4)}€`;
                    }
                } else if (photocopName.toLowerCase() === 'konika') {
                    // Photocopieur à toner : additionner tous les toners + tambour + developer
                    if (couleur) {
                        // Couleur : cyan + jaune + magenta + noir (avec taux de remplissage) + tambour + dev (sans taux)
                        var cyan = (machinePrices['cyan']?.unite || 0) * fillRateMultiplier;
                        var jaune = (machinePrices['jaune']?.unite || 0) * fillRateMultiplier;
                        var magenta = (machinePrices['magenta']?.unite || 0) * fillRateMultiplier;
                        var noir = (machinePrices['noir']?.unite || 0) * fillRateMultiplier;
                        var tambour = machinePrices['tambour']?.unite || 0;
                        var dev = machinePrices['dev']?.unite || 0;
                        
                        prixEncre = cyan + jaune + magenta + noir + tambour + dev;
                        
                        // Ajuster selon la taille AVANT d'afficher le détail
                        var prixEncrePourDetail = prixEncre;
                        if (taille === 'A4') prixEncrePourDetail = prixEncre / 2;
                        
                        var cyanDetail = taille === 'A4' ? cyan / 2 : cyan;
                        var jauneDetail = taille === 'A4' ? jaune / 2 : jaune;
                        var magentaDetail = taille === 'A4' ? magenta / 2 : magenta;
                        var noirDetail = taille === 'A4' ? noir / 2 : noir;
                        var tambourDetail = taille === 'A4' ? tambour / 2 : tambour;
                        var devDetail = taille === 'A4' ? dev / 2 : dev;
                        
                        detailEncreBrochure = `Cyan: ${cyanDetail.toFixed(4)}€ + Jaune: ${jauneDetail.toFixed(4)}€ + Magenta: ${magentaDetail.toFixed(4)}€ + Noir: ${noirDetail.toFixed(4)}€ + Tambour: ${tambourDetail.toFixed(4)}€ + Dev: ${devDetail.toFixed(4)}€ = ${prixEncrePourDetail.toFixed(4)}€`;
                    } else {
                        // Noir et blanc : noir + tambour + dev (pas de taux de remplissage)
                        var noir = machinePrices['noir']?.unite || 0;
                        var tambour = machinePrices['tambour']?.unite || 0;
                        var dev = machinePrices['dev']?.unite || 0;
                        
                        prixEncre = noir + tambour + dev;
                        
                        // Ajuster selon la taille AVANT d'afficher le détail
                        var prixEncrePourDetail = prixEncre;
                        if (taille === 'A4') prixEncrePourDetail = prixEncre / 2;
                        
                        var noirDetail = taille === 'A4' ? noir / 2 : noir;
                        var tambourDetail = taille === 'A4' ? tambour / 2 : tambour;
                        var devDetail = taille === 'A4' ? dev / 2 : dev;
                        
                        detailEncreBrochure = `Noir: ${noirDetail.toFixed(4)}€ + Tambour: ${tambourDetail.toFixed(4)}€ + Dev: ${devDetail.toFixed(4)}€ = ${prixEncrePourDetail.toFixed(4)}€`;
                    }
                }
            }
            
            if (taille === 'A4') prixEncre = prixEncre / 2;
            
            var nbPages = nbExemplaires * nbFeuilles;
            var nbPagesEncre = nbPages; // Pages pour l'encre
            if (rv) nbPagesEncre = nbPages * 2; // Recto-verso = 2 fois plus de pages pour l'encre
            
            var coutEncreBrochure = nbPagesEncre * prixEncre;
            
            prixPapierMoyen += prixPapier;
            prixEncreMoyen += prixEncre;
            totalPages += nbPages;
            totalPagesEncre += nbPagesEncre;
            coutEncreTotal += coutEncreBrochure;
            
            // Calculer le coût papier pour cette brochure
            var coutPapierBrochure = feuilles_payees ? 0 : (nbPages * prixPapier);
            coutPapierTotal += coutPapierBrochure;
            
            if (detailEncreBrochure) {
                detailEncre += `<br>&nbsp;&nbsp;&nbsp;&nbsp;${detailEncreBrochure}`;
            }
        });
        
        if (brochures.length > 0) {
            prixPapierMoyen = prixPapierMoyen / brochures.length;
            prixEncreMoyen = prixEncreMoyen / brochures.length;
        }
        
        const feuillesParExemplaire = totalExemplaires > 0 ? totalPages / totalExemplaires : 0;
        const feuillesParExemplaireText = Number.isInteger(feuillesParExemplaire) ? feuillesParExemplaire : feuillesParExemplaire.toFixed(2);
        const totalPagesText = Number.isInteger(totalPages) ? totalPages : totalPages.toFixed(2);
        detailCalcul = `
            <div class="price-detail" style="font-size: 0.9em; color: #666; margin-top: 5px;">
                <strong>Détail du calcul :</strong><br>
                • ${totalExemplaires} exemplaires × ${feuillesParExemplaireText} feuilles = ${totalPagesText} pages<br>
                • Papier : ${totalPages} feuilles × ${prixPapierMoyen.toFixed(3)}€ = ${coutPapierTotal.toFixed(2)}€<br>
                • Encre : ${totalPagesEncre} pages × ${prixEncreMoyen.toFixed(4)}€ = ${coutEncreTotal.toFixed(2)}€${detailEncre}<br>
                <strong>Total : ${price.toFixed(2)}€</strong>
        </div>
    `;
    }
    
    // Mettre à jour l'affichage du prix de cette machine
    var priceElement = machineElement.querySelector('.machine-price');
    console.log("🔍 Élément .machine-price trouvé:", priceElement ? "oui" : "non");
    if (priceElement) {
        priceElement.innerHTML = price.toFixed(2) + '€' + detailCalcul;
        console.log("✅ Prix mis à jour dans l'élément:", price.toFixed(2) + '€');
    } else {
        console.log("❌ ERREUR: Élément .machine-price non trouvé pour machine", machineIndex);
        // Essayer de trouver l'élément par ID
        var priceElementById = document.getElementById('machine-price-' + machineIndex);
        console.log("🔍 Élément #machine-price-" + machineIndex + " trouvé:", priceElementById ? "oui" : "non");
        if (priceElementById) {
            priceElementById.innerHTML = price.toFixed(2) + '€' + detailCalcul;
            console.log("✅ Prix mis à jour par ID:", price.toFixed(2) + '€');
        }
    }
    
    console.log(`🔍 Prix final retourné pour machine ${machineIndex}: ${price.toFixed(2)}€`);
    return price;
}

function calculateTotalPrice() {
    console.log("🔍 calculateTotalPrice appelé");
    var total = 0;
    var machineElements = document.querySelectorAll('.machine-item');
    console.log("🔍 machineElements trouvés:", machineElements.length);
    
    if (machineElements.length === 0) {
        console.log("❌ ERREUR: Aucune machine trouvée avec la classe .machine-item");
        return;
    }
    
    machineElements.forEach(function(machineElement) {
        var machineIndex = machineElement.getAttribute('data-index');
        console.log("🔍 machineIndex:", machineIndex);
        var price = calculateMachinePrice(machineIndex);
        console.log("🔍 prix calculé pour index", machineIndex, ":", price);
        total += price;
        
        // Mettre à jour le preview du panel
        updatePanelPreview(machineIndex);
    });
    
    console.log("Total final:", total);
    
    // Vérifier que l'élément existe avant de le modifier
    var prixTotalElement = document.getElementById('prix-total');
    if (prixTotalElement) {
        prixTotalElement.textContent = total.toFixed(2) + '€';
    } else {
        console.log("Élément #prix-total non trouvé");
    }
    
    // Mettre à jour le champ de paiement si "oui" est coché
    var payeOui = document.getElementById('payeoui');
    if (payeOui && payeOui.checked) {
        var cbField = document.getElementById('cb1');
        if (cbField) {
            cbField.value = total.toFixed(2);
        }
    }
    
    return total; // Retourner le total pour utilisation dans updatePaymentAmount
}

// Initialiser le cache des mappings machine -> price_key
function initializeMachinePriceCache() {
    console.log('🔄 Initialisation du cache des mappings machine...');
    
    // Utiliser les mappings générés côté serveur
    window.machinePriceCache = <?= json_encode($machine_price_mappings) ?>;
    
    console.log('✅ Cache des mappings initialisé côté serveur:', window.machinePriceCache);
}

// Gestion des machines
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔍 DOM chargé, initialisation des prix...');
    
    // Initialiser le cache des mappings machine -> price_key
    initializeMachinePriceCache();
    
    // Initialiser l'auto-sauvegarde du formulaire
    initAutoSave();
    
    // Vérifier si on doit restaurer les données
    // Seulement si on vient de la page de confirmation (paramètre retour=1)
    const urlParams = new URLSearchParams(window.location.search);
    const shouldRestore = urlParams.get('retour') === '1' && sessionStorage.getItem('tirage_multimachines_form_data');
    
    if (shouldRestore) {
        console.log('🔄 Restauration des données du formulaire depuis la page de confirmation...');
        setTimeout(() => {
            const restored = restoreFormData();
            if (restored) {
                console.log('✅ Données restaurées, recalcul du prix...');
                // Attendre un peu pour que tous les éléments soient restaurés
                setTimeout(() => {
                    calculateTotalPrice();
                    // Nettoyer l'URL pour retirer le paramètre retour
                    if (window.history && window.history.replaceState) {
                        window.history.replaceState({}, '', '?tirage_multimachines');
                    }
                }, 300);
            } else {
                calculateTotalPrice();
            }
        }, 200);
    } else {
        calculateTotalPrice();
    }
    
    const addMachineBtn = document.getElementById('add-machine');
    if (!addMachineBtn) {
        console.log('Bouton add-machine non trouvé - probablement sur la page de confirmation');
        return;
    }
    
    addMachineBtn.addEventListener('click', function() {
    const container = document.getElementById('machines-container');
    const newIndex = machineCount;
    
    // Faire une requête AJAX pour récupérer le HTML de la machine
    fetch(`?get-machine-template&index=${newIndex}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error('Erreur:', data.error);
                alert('Erreur lors de l\'ajout de la machine: ' + data.error);
                return;
            }
            
            // Créer un élément div temporaire pour parser le HTML
            // Debug: vérifier le HTML reçu
            console.log('HTML reçu de l\'endpoint:', data.html.substring(0, 200) + '...');
            
            // Créer un élément temporaire pour parser le HTML
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = data.html;
            
            // Debug: vérifier le parsing
            console.log('tempDiv.children.length:', tempDiv.children.length);
            console.log('Tous les enfants:', Array.from(tempDiv.children).map(el => el.tagName));
            
            // Le HTML généré est déjà un panel complet, on l'utilise directement
            const newMachineContainer = tempDiv.firstElementChild;
            
            if (!newMachineContainer) {
                console.error('Aucun élément trouvé dans le HTML généré');
                alert('Erreur lors de l\'ajout de la machine: HTML invalide');
                return;
            }
            
            // Ajouter la machine au container
            // Trouver le div qui contient le bouton "Ajouter un tirage" (le dernier .text-center)
            const addButtonContainer = document.getElementById('buttons-container');
            
            console.log('🔍 container:', container);
            console.log('🔍 addButtonContainer:', addButtonContainer);
            console.log('🔍 container.children:', Array.from(container.children).map(el => el.className || el.tagName));
            
            if (addButtonContainer && container.contains(addButtonContainer)) {
                // Insérer la nouvelle machine AVANT le div du bouton
                container.insertBefore(newMachineContainer, addButtonContainer);
                console.log('✅ Machine ajoutée avec succès avant le bouton!');
            } else {
                // Fallback : ajouter à la fin du container
                console.log('⚠️ Fallback: ajout à la fin');
                container.appendChild(newMachineContainer);
            }
    machineCount++;
    
            // Debug: vérifier le contenu de newMachineContainer
            console.log('newMachineContainer HTML:', newMachineContainer.innerHTML.substring(0, 200) + '...');
            console.log('Recherche du bouton remove-machine...');
    
    // Ajouter l'événement pour supprimer
            const removeBtn = newMachineContainer.querySelector('.remove-machine');
            if (removeBtn) {
                console.log('Bouton remove-machine trouvé:', removeBtn);
                removeBtn.addEventListener('click', function() {
                    newMachineContainer.remove();
                    machineCount = Math.max(1, machineCount - 1);
                    calculateTotalPrice();
                    saveFormData(); // Sauvegarder après suppression
                });
            } else {
                console.error('Bouton remove-machine non trouvé dans le HTML généré');
                console.log('Tous les boutons dans newMachineContainer:', newMachineContainer.querySelectorAll('button'));
            }
            
            // Initialiser la validation pour cette machine
            // Attendre un peu que le DOM soit mis à jour
            setTimeout(() => {
                console.log('Appel de toggleMachineType pour index:', newIndex);
                console.log('Recherche des éléments radio...');
                const duplicopieurRadio = document.querySelector(`input[name="machines[${newIndex}][type]"][value="duplicopieur"]`);
                const photocopieurRadio = document.querySelector(`input[name="machines[${newIndex}][type]"][value="photocopieur"]`);
                console.log('duplicopieurRadio trouvé:', !!duplicopieurRadio);
                console.log('photocopieurRadio trouvé:', !!photocopieurRadio);
                toggleMachineType(newIndex);
                
                // Charger les tambours du duplicopieur si un duplicopieur est sélectionné
                const duplicopieurIdField = document.querySelector(`select[name="machines[${newIndex}][duplicopieur_id]"]`) || document.querySelector(`input[name="machines[${newIndex}][duplicopieur_id]"]`);
                if (duplicopieurIdField && duplicopieurIdField.value) {
                    const duplicopieurId = duplicopieurIdField.value;
                    console.log('🎯 Chargement des tambours pour machine', newIndex, ', duplicopieur ID:', duplicopieurId);
                    loadTamboursForDuplicopieur(duplicopieurId, newIndex);
                } else {
                    console.log('⚠️ Pas de duplicopieur sélectionné pour machine', newIndex);
                }
                
                // Sauvegarder après l'ajout d'une machine
                saveFormData();
            }, 100);
    
    calculateTotalPrice();
        })
        .catch(error => {
            console.error('Erreur AJAX:', error);
            console.error('Type d\'erreur:', typeof error);
            console.error('Message d\'erreur:', error.message);
            console.error('Stack trace:', error.stack);
            alert('Erreur lors de l\'ajout de la machine: ' + error.message);
        });
    });
});

// Initialiser le champ au chargement de la page si "oui" est déjà sélectionné
document.addEventListener('DOMContentLoaded', function() {
    var payeOui = document.getElementById('payeoui');
    if (payeOui && payeOui.checked) {
        updatePaymentAmount();
    }
    
    // Initialiser la validation pour la première machine (duplicopieur par défaut)
    toggleMachineType(0);
    
    // Charger les tambours pour la machine 0 si un duplicopieur est déjà sélectionné
    var duplicopieurSelect0 = document.querySelector('select[name="machines[0][duplicopieur_id]"]');
    var duplicopieurHidden0 = document.querySelector('input[name="machines[0][duplicopieur_id]"]');
    var duplicopieurId0 = null;
    
    if (duplicopieurSelect0 && duplicopieurSelect0.value) {
        duplicopieurId0 = duplicopieurSelect0.value;
    } else if (duplicopieurHidden0 && duplicopieurHidden0.value) {
        duplicopieurId0 = duplicopieurHidden0.value;
    }
    
    if (duplicopieurId0) {
        console.log('🎯 Chargement initial des tambours pour machine 0, duplicopieur ID:', duplicopieurId0);
        loadTamboursForDuplicopieur(duplicopieurId0, 0);
    }
    
    calculateTotalPrice();
    
    // Logic for Main Edit Form
    const multimachinesForm = document.getElementById('multimachines-form');
    if (multimachinesForm) {
        multimachinesForm.addEventListener('submit', function() {
            var payeOui = document.getElementById('payeoui');
            var cbField = document.getElementById('cb1');
            if (payeOui && payeOui.checked && cbField) {
                var total = calculateTotalPrice();
                cbField.value = total.toFixed(2);
            }
            saveFormData();
        });
    }

    // Logic for Confirmation Form (Final Validation)
    const confirmationForm = document.getElementById('form-enregistrement');
    if (confirmationForm) {
        confirmationForm.addEventListener('submit', function() {
            // KILL SESSION AUTO TIRAGE ON FINAL VALIDATION
            console.log('🧹 Validation finale : Nettoyage de la session auto_tirage et de l\'utilisateur...');
            sessionStorage.removeItem('auto_tirage_session_jobs');
            sessionStorage.removeItem('auto_tirage_session_user');
            // Remove persistent user data to fully logout
            localStorage.removeItem('auto_tirage_user');
            
            // Note: No need to calc price here, it's already fixed in the summary
        });
    }
});
</script>

                </div>
            </div>
        </div>
    </div>
</div>


<script>
// Attacher les événements seulement si les éléments existent
// Fonction globale pour mettre à jour le montant de paiement
function updatePaymentAmount() {
    console.log("updatePaymentAmount appelé");
    var payeOui = document.getElementById('payeoui');
    var cbField = document.getElementById('cb1');
    
    // Vérifier que les éléments existent avant de les utiliser
    if (!payeOui || !cbField) {
        console.log("Éléments payeOui ou cbField non trouvés");
        return;
    }
    
    if (payeOui.checked) {
        // Essayer de trouver le prix total dans l'élément #prix-total
        var prixTotalElement = document.getElementById('prix-total');
        if (prixTotalElement) {
            var totalText = prixTotalElement.textContent;
            var cleanedTotal = cleanNumberString(totalText);
            if (!isNaN(cleanedTotal)) {
                cbField.value = cleanedTotal.toFixed(2);
                console.log("Prix total trouvé dans #prix-total:", cleanedTotal);
                return;
            }
        }
        
        // Si pas trouvé dans #prix-total, essayer de trouver le prix total dans le récapitulatif (page de confirmation)
        // Chercher spécifiquement dans l'élément h2.text-primary strong (structure exacte du TOTAL GLOBAL)
        var totalPriceElement = document.querySelector('h2.text-primary strong');
        if (totalPriceElement) {
            var totalText = totalPriceElement.textContent;
            console.log("Prix trouvé dans h2.text-primary strong:", totalText);
            var cleanedTotal = cleanNumberString(totalText);
            if (!isNaN(cleanedTotal)) {
                console.log("Prix total extrait:", cleanedTotal);
                cbField.value = cleanedTotal.toFixed(2);
                return;
            }
        }
        
        console.log("Aucun prix total trouvé");
    } else {
        // Si "non" est sélectionné, vider le champ
        cbField.value = '';
        console.log("cbField.value vidé");
    }
}

function cleanNumberString(value) {
    if (!value) return NaN;
    var normalized = value.replace(/\s+/g, '').replace(',', '.');
    var match = normalized.match(/-?\d+(\.\d+)?/);
    return match ? parseFloat(match[0]) : NaN;
}

document.addEventListener('DOMContentLoaded', function() {
    
    var payeOui = document.getElementById('payeoui');
    var payeNon = document.getElementById('payenon');
    
    if (payeOui) {
        payeOui.addEventListener('change', updatePaymentAmount);
    }
    
    if (payeNon) {
        payeNon.addEventListener('change', updatePaymentAmount);
    }
});

// Fonction pour mettre à jour les compteurs d'un duplicopieur
function updateDuplicopieurCounters(duplicopieurId, machineIndex) {
    console.log('🔧 updateDuplicopieurCounters appelée avec ID:', duplicopieurId, 'Index:', machineIndex);
    console.log('🔍 jQuery disponible:', typeof $ !== 'undefined');
    
    if (!duplicopieurId) {
        console.log('❌ Pas d\'ID duplicopieur fourni');
        return;
    }
    
    // Récupérer le nom de la machine depuis l'option sélectionnée
    var selectElement = document.querySelector('select[name="machines[' + machineIndex + '][duplicopieur_id]"]');
    var selectedOption = selectElement.options[selectElement.selectedIndex];
    var machineName = selectedOption.getAttribute('data-name');
    
    console.log('🔍 Nom de la machine récupéré:', machineName);
    
    if (!machineName) {
        console.log('❌ Pas de nom de machine trouvé');
        return;
    }
    
    console.log('🌐 Appel AJAX vers: ?tirage_multimachines&ajax=get_last_counters&machine=' + encodeURIComponent(machineName));
    
    // Charger les tambours du duplicopieur sélectionné
    loadTamboursForDuplicopieur(duplicopieurId, machineIndex);
    
    // Faire un appel AJAX pour récupérer les compteurs
    $.get('?tirage_multimachines&ajax=get_last_counters&machine=' + encodeURIComponent(machineName))
        .done(function(response) {
            console.log('✅ Réponse AJAX reçue:', response);
            if (response.success) {
                console.log('📊 Compteurs reçus:', response.counters);
                // Mettre à jour les champs de compteurs
                $('#master_av_' + machineIndex).val(response.counters.master_av || 0);
                $('#passage_av_' + machineIndex).val(response.counters.passage_av || 0);
                
                console.log('🔄 Compteurs mis à jour - Master:', response.counters.master_av, 'Passage:', response.counters.passage_av);
                
                // Recalculer le prix total (les prix vont changer selon le duplicopieur sélectionné)
                if (typeof calculateTotalPrice === 'function') {
                    calculateTotalPrice();
                }
            } else {
                console.log('❌ Réponse AJAX indique un échec:', response);
            }
        })
        .fail(function(xhr, status, error) {
            console.log('❌ Erreur AJAX:', xhr.responseText);
            console.log('❌ Status:', status);
            console.log('❌ Error:', error);
        });
}

// Fonction pour traduire les noms de tambours
function translateTambour(tambour) {
    const translations = {
        'tambour_noir': 'Tambour Noir',
        'tambour_rouge': 'Tambour Rouge',
        'tambour_bleu': 'Tambour Bleu',
        'tambour_vert': 'Tambour Vert',
        'tambour_jaune': 'Tambour Jaune'
    };
    return translations[tambour] || tambour.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

// Fonction pour gérer le système d'onglets
function selectMachineTypeTab(machineIndex, type) {
    console.log('Sélection onglet:', type, 'pour machine:', machineIndex);
    
    // Mettre à jour les classes des onglets
    const tabDupli = document.getElementById('tab-duplicopieur-' + machineIndex);
    const tabPhoto = document.getElementById('tab-photocopieur-' + machineIndex);
    
    if (tabDupli && tabPhoto) {
        if (type === 'duplicopieur') {
            tabDupli.classList.add('active');
            tabPhoto.classList.remove('active');
        } else {
            tabPhoto.classList.add('active');
            tabDupli.classList.remove('active');
        }
    }
    
    // Cocher le bon radio button caché
    const radioDupli = document.getElementById('radio-duplicopieur-' + machineIndex);
    const radioPhoto = document.getElementById('radio-photocopieur-' + machineIndex);
    
    if (radioDupli && radioPhoto) {
        if (type === 'duplicopieur') {
            radioDupli.checked = true;
        } else {
            radioPhoto.checked = true;
        }
    }
    
    // Déclencher le changement d'interface
    toggleMachineType(machineIndex);
}

// Fonction pour ouvrir/fermer un panel d'accordéon
function toggleMachinePanel(machineIndex) {
    const content = document.getElementById('machine-content-' + machineIndex);
    const icon = document.getElementById('toggle-icon-' + machineIndex);
    const panel = document.querySelector('.machine-item[data-index="' + machineIndex + '"]');
    
    if (content && icon) {
        if (content.style.display === 'none') {
            // Ouvrir le panel
            $(content).slideDown(300);
            icon.classList.remove('fa-chevron-right');
            icon.classList.add('fa-chevron-down');
            panel.classList.add('panel-expanded');
        } else {
            // Fermer le panel
            $(content).slideUp(300);
            icon.classList.remove('fa-chevron-down');
            icon.classList.add('fa-chevron-right');
            panel.classList.remove('panel-expanded');
        }
    }
}

// Fonction pour mettre à jour le preview du panel (prix et type)
function updatePanelPreview(machineIndex) {
    console.log("🔍 updatePanelPreview appelé pour machine", machineIndex);
    const pricePreview = document.getElementById('price-preview-' + machineIndex);
    const typeBadge = document.getElementById('type-badge-' + machineIndex);
    
    console.log("🔍 Éléments trouvés:", {
        pricePreview: pricePreview ? "oui" : "non",
        typeBadge: typeBadge ? "oui" : "non"
    });
    
    // Mettre à jour le type
    const typeRadio = document.querySelector(`input[name="machines[${machineIndex}][type]"]:checked`);
    if (typeBadge && typeRadio) {
        typeBadge.textContent = typeRadio.value === 'duplicopieur' ? 'Duplicopieur' : 'Photocopieur';
        console.log("✅ Type mis à jour:", typeRadio.value);
    }
    
    // Mettre à jour le prix
    if (pricePreview) {
        const price = calculateMachinePrice(machineIndex);
        pricePreview.textContent = price.toFixed(2) + '€';
        console.log("✅ Prix preview mis à jour:", price.toFixed(2) + '€');
    } else {
        console.log("❌ ERREUR: price-preview-" + machineIndex + " non trouvé");
    }
}

function updateFillRateDisplay(prefix, machineIndex) {
    var slider = document.getElementById('fill_rate_' + prefix + '_slider_' + machineIndex);
    var display = document.getElementById('fill_rate_' + prefix + '_display_' + machineIndex);
    var hidden = document.getElementById('fill_rate_' + prefix + '_' + machineIndex);
    
    if (slider && display && hidden) {
        var value = parseInt(slider.value);
        var percentage = value + '%';
        var fillRate = (value / 100).toFixed(2);
        
        display.textContent = percentage;
        hidden.value = fillRate;
        
        // Recalculer le prix
        calculateTotalPrice();
    }
}

function toggleFillRateDisplay(machineIndex) {
    var fillRateGroup = document.getElementById('fill-rate-group-' + machineIndex);
    var couleurCheckbox = document.getElementById('couleur_' + machineIndex + '_0');
    
    if (fillRateGroup && couleurCheckbox) {
        if (couleurCheckbox.checked) {
            fillRateGroup.style.display = 'block';
        } else {
            fillRateGroup.style.display = 'none';
        }
    }
}

// Fonction pour charger les tambours d'un duplicopieur
function loadTamboursForDuplicopieur(duplicopieurId, machineIndex) {
    console.log('🥁 Chargement des tambours pour duplicopieur ID:', duplicopieurId);
    
    $.get('?tirage_multimachines&ajax=get_tambours&duplicopieur_id=' + duplicopieurId)
        .done(function(response) {
            console.log('✅ Tambours reçus:', response);
            if (response.success && response.tambours) {
                var tambourSelect = $('#tambour-select-' + machineIndex);
                var tambourGroup = $('#tambour-group-' + machineIndex);
                
                // Vider le select
                tambourSelect.empty();
                
                // Ajouter les tambours disponibles avec traduction
                response.tambours.forEach(function(tambour, index) {
                    var tambourLabel = translateTambour(tambour);
                    var option = $('<option></option>')
                        .attr('value', tambour)
                        .text(tambourLabel);
                    
                    // Sélectionner automatiquement le premier tambour
                    if (index === 0) {
                        option.attr('selected', 'selected');
                    }
                    
                    tambourSelect.append(option);
                });
                
                // Afficher le groupe tambour uniquement si plusieurs tambours disponibles
                if (response.tambours.length > 1) {
                    tambourGroup.show();
                    tambourSelect.prop('required', true);
                } else {
                    // Un seul tambour : le sélectionner automatiquement en arrière-plan (caché)
                    tambourGroup.hide();
                    tambourSelect.prop('required', false);
                    tambourSelect.val(response.tambours[0]);
                }
                
                console.log('🎯 Tambours chargés:', response.tambours.length, 'tambour(s)');
                
                // Ajouter un event listener pour recalculer le prix quand le tambour change
                tambourSelect.off('change.tambour').on('change.tambour', function() {
                    console.log('🥁 Tambour changé, recalcul du prix pour index:', machineIndex);
                    if (typeof calculateTotalPrice === 'function') {
                        calculateTotalPrice();
                    }
                    // Mettre à jour le preview du panel
                    updatePanelPreview(machineIndex);
                });
                
                // Déclencher le calcul initial du prix
                if (typeof calculateTotalPrice === 'function') {
                    calculateTotalPrice();
                }
                // Mettre à jour le preview du panel
                updatePanelPreview(machineIndex);
            } else {
                console.log('❌ Erreur lors du chargement des tambours:', response.error);
            }
        })
        .fail(function(xhr, status, error) {
            console.log('❌ Erreur AJAX pour les tambours:', status, error);
        });
}

// Initialisation au chargement de la page
$(document).ready(function() {
    // Mettre à jour tous les totaux de feuilles au chargement
    var machines = document.querySelectorAll('[data-index]');
    machines.forEach(function(machine) {
        var machineIndex = machine.getAttribute('data-index');
        updateTotalFeuillesForMachine(machineIndex);
    });
});
</script>