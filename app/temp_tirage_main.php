<?php
// Inclure le syst├¿me de traduction
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

// G├®n├®rer les mappings machine -> price_key c├┤t├® serveur
$machine_price_mappings = [];
try {
    require_once __DIR__ . '/../controler/functions/database.php';
    $db = pdo_connect();
    
    // R├®cup├®rer tous les photocopieurs actifs
    $stmt = $db->prepare("SELECT id, marque FROM photocopieurs WHERE actif = 1 ORDER BY marque");
    $stmt->execute();
    $photocopieurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($photocopieurs as $photocopieur) {
        $machine_name = $photocopieur['marque'];
        $photocopier_id = $photocopieur['id'];
        
        // V├®rifier si des prix existent pour cet ID
        $stmt = $db->prepare("SELECT COUNT(*) FROM prix WHERE machine_type = 'photocop' AND machine_id = ?");
        $stmt->execute([$photocopier_id]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            $machine_price_mappings[$machine_name] = "photocop_$photocopier_id";
        }
    }
} catch (Exception $e) {
    error_log("Erreur lors de la g├®n├®ration des mappings machine: " . $e->getMessage());
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
    
    /* Styles pour l'accord├®on */
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
        <h4>Debug activ├® mais variable \$debug non d├®finie</h4>
    </div>
<?php endif; ?>

<?php
if (isset($_POST['contact']) && isset($_POST['enregistrer'])) {
    
    ?>
    
    <div class="alert-modern alert alert-success">
        <strong><i class="fa fa-check-circle"></i> <?php _e('tirage_multimachines.success_message'); ?></strong> <?php _e('tirage_multimachines.success_description'); ?>
    </div>
    
    <!-- R├®capitulatif apr├¿s soumission -->
    <?php if (isset($contact) && isset($machines) && ($contact != "")): ?>
    <!-- Script pour sauvegarder les donn├®es de la confirmation dans sessionStorage -->
    <script>
    (function() {
        // Sauvegarder les donn├®es depuis PHP vers sessionStorage pour permettre le retour
        try {
            const formData = {
                contact: <?= json_encode($contact ?? '') ?>,
                machines: <?= json_encode($machines ?? []) ?>
            };
            
            // Convertir les donn├®es PHP en format compatible avec le formulaire
            const savedData = {};
            savedData['contact'] = formData.contact;
            
            // Convertir les machines en format formulaire
            if (formData.machines && Array.isArray(formData.machines)) {
                formData.machines.forEach((machine, index) => {
                    Object.keys(machine).forEach(key => {
                        if (key === 'brochures' && Array.isArray(machine[key])) {
                            // G├®rer les brochures
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
            
            // Sauvegarder le nombre de machines dans les m├®tadonn├®es
            savedData['_machine_count'] = formData.machines ? formData.machines.length : 0;
            
            // Sauvegarder dans sessionStorage
            sessionStorage.setItem('tirage_multimachines_form_data', JSON.stringify(savedData));
            console.log('Ô£à Donn├®es de confirmation sauvegard├®es pour retour possible:', {
                nombreMachines: savedData['_machine_count'],
                cles: Object.keys(savedData).filter(k => k.startsWith('machines[')).length
            });
        } catch (e) {
            console.error('ÔØî Erreur lors de la sauvegarde des donn├®es de confirmation:', e);
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
    <!-- Page de confirmation am├®lior├®e -->
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
                                            // Calculer les co├╗ts d├®taill├®s pour le duplicopieur
                                            $prix_data = $prix_data ?? [];
                                            $duplicopieur_id = $machine['duplicopieur_id'] ?? $duplicopieur_selectionne['id'];
                                            $machine_key = 'dupli_' . $duplicopieur_id;
                                            $prix_master = $prix_data[$machine_key]['master']['unite'] ?? 0;
                                            
                                            // Prix des passages selon le tambour s├®lectionn├®
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
                                            <li><strong><?php _e('tirage_multimachines.masters'); ?> :</strong> <?= $nb_masters ?> ├ù <?= number_format($prix_master, 4) ?> <?php _e('tirage_multimachines.currency'); ?> = <?= number_format($cout_masters, 2) ?> <?php _e('tirage_multimachines.currency'); ?></li>
                                            <li><strong><?php _e('tirage_multimachines.passes'); ?> :</strong> <?= $nb_passages ?> ├ù <?= number_format($prix_passage, 4) ?> <?php _e('tirage_multimachines.currency'); ?> = <?= number_format($cout_passages, 2) ?> <?php _e('tirage_multimachines.currency'); ?></li>
                                            <li><strong><?php _e('tirage_multimachines.paper'); ?> :</strong> <?= $nb_f ?> <?php _e('tirage_multimachines.sheets'); ?> ├ù <?= number_format($prix_papier, 3) ?> <?php _e('tirage_multimachines.currency'); ?> = <?= number_format($cout_papier, 2) ?> <?php _e('tirage_multimachines.currency'); ?></li>
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
                                                    ÔÇó <?= $brochure['nb_exemplaires'] ?> <?php _e('tirage_multimachines.exemplaires'); ?><br>
                                                    ÔÇó <?= $brochure['nb_feuilles'] ?> <?php _e('tirage_multimachines.feuilles_per_exemplaire'); ?><br>
                                                    ÔÇó <?php _e('tirage_multimachines.format'); ?> : <?= $brochure['taille'] ?><br>
                                                    <?php if (isset($brochure['rv']) && $brochure['rv'] == 'oui'): ?>
                                                        ÔÇó <i class="fa fa-check text-success"></i> <?php _e('tirage_multimachines.recto_verso'); ?><br>
                                                    <?php endif; ?>
                                                    <?php if (isset($brochure['couleur']) && $brochure['couleur'] == 'oui'): ?>
                                                        ÔÇó <i class="fa fa-check text-success"></i> <?php _e('tirage_multimachines.color'); ?><br>
                                                    <?php endif; ?>
                                                    <?php if (isset($brochure['feuilles_payees']) && $brochure['feuilles_payees'] == 'oui'): ?>
                                                        ÔÇó <i class="fa fa-check text-warning"></i> <?php _e('tirage_multimachines.sheets_paid'); ?><br>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <h5><i class="fa fa-euro"></i> <?php _e('tirage_multimachines.cost_details'); ?></h5>
                                        <ul class="list-unstyled">
                                            <?php 
                                            // Calculer les co├╗ts d├®taill├®s pour le photocopieur
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
                                                        
                                                        // R├®cup├®rer le taux de remplissage (valeur par d├®faut 0.5 = 50%)
                                                        $fill_rate = isset($machine['fill_rate']) ? floatval($machine['fill_rate']) : 0.5;
                                                        $fill_rate_multiplier = $couleur ? ($fill_rate / 0.5) : 1.0; // 50% = ├ù1, 100% = ├ù2
                                                        
                                                        // D├®terminer la cl├® de la machine dynamiquement
                                                        $machine_key = null;
                                                        
                                                        // R├®cup├®rer l'ID du photocopieur par son nom
                                                        $stmt = $db->prepare("SELECT id FROM photocopieurs WHERE marque = ? AND actif = 1");
                                                        $stmt->execute([$machine_name]);
                                                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                                        
                                                        if ($result) {
                                                            $photocopier_id = $result['id'];
                                                            $machine_key = "photocop_$photocopier_id";
                                                            
                                                            // V├®rifier si des prix existent pour cet ID
                                                            if (!isset($prix_data[$machine_key])) {
                                                                $machine_key = null; // Pas de prix trouv├®
                                                            }
                                                        }
                                                        
                                                        // Fallback si pas trouv├®
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
                                                                // Photocopieur ├á encre
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
                                                                // Photocopieur ├á toner
                                                                if ($couleur) {
                                                                    // Couleur : cyan + jaune + magenta + noir (avec taux de remplissage) + tambour + dev (sans taux)
                                                                    $prix_encre_brochure += (($machine_prices['cyan']['unite'] ?? 0) * $fill_rate_multiplier);
                                                                    $prix_encre_brochure += (($machine_prices['jaune']['unite'] ?? 0) * $fill_rate_multiplier);
                                                                    $prix_encre_brochure += (($machine_prices['magenta']['unite'] ?? 0) * $fill_rate_multiplier);
                                                                    $prix_encre_brochure += (($machine_prices['noir']['unite'] ?? 0) * $fill_rate_multiplier);
                                                                    // Tambour et dev ne sont pas affect├®s par le taux de remplissage
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
                                                        
                                                        // Calculer le co├╗t d'encre
                                                        $nb_pages_encre = $nb_pages;
                                                        if ($rv) {
                                                            $nb_pages_encre = $nb_pages * 2;
                                                        }
                                                        $cout_encre = $nb_pages_encre * $prix_encre_brochure;
                                                        $total_encre += $cout_encre;
                                                        $total_pages += $nb_pages;
                                                        $total_pages_encre += $nb_pages_encre;
                                                        
                                                        // Stocker les prix pour l'affichage (prendre la derni├¿re brochure)
                                                        $prix_papier = $prix_papier;
                                                        $prix_encre = $prix_encre_brochure;
                                                    }
                                                }
                                            }
                                            ?>
                                            <li><strong><?php _e('tirage_multimachines.paper_label'); ?> :</strong> <?= $total_pages ?> <?php _e('tirage_multimachines.pages'); ?> ├ù <?= number_format($prix_papier, 3) ?> <?php _e('tirage_multimachines.currency'); ?> = <?= number_format($total_papier, 2) ?> <?php _e('tirage_multimachines.currency'); ?></li>
                                            <li><strong><?php _e('tirage_multimachines.ink_toner_label'); ?> :</strong> <?= $total_pages_encre ?> <?php _e('tirage_multimachines.pages'); ?> ├ù <?= number_format($prix_encre, 4) ?> <?php _e('tirage_multimachines.currency'); ?> = <?= number_format($total_encre, 2) ?> <?php _e('tirage_multimachines.currency'); ?></li>
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
            
            <!-- Champs cach├®s -->
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
            
            <!-- Champ "As-tu pay├®" -->
            <div class="form-group">
                <label class="col-md-4 control-label" for="payeoui">As-tu pay├® ?</label>
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
                <label class="col-md-4 control-label" for="cb1">Montant pay├®</label>
                <div class="col-md-4">
                    <input id="cb1" name="cb" class="form-control input-md" type="number" step="0.01" min="0" placeholder="0.00">
                    <span class="help-block">Montant en euros</span>
                </div>
            </div>
            
            <!-- Champ "Un petit mot" -->
            <div class="form-group">
                <label class="col-md-4 control-label" for="mot">Un petit mot, une r├®clamation, un encouragement, une info?</label>  
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
        <strong>Erreurs d├®tect├®es :</strong>
        <ul>
            <?php foreach($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if(!empty($success_message)): ?>
    <div class="alert alert-success">
        <strong>Succ├¿s!</strong> <?= htmlspecialchars($success_message) ?>
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
        <p><strong>Requ├¬te:</strong> <?php echo htmlspecialchars($debug_sql); ?></p>
        <p><strong>Param├¿tres:</strong></p>
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
        <h4>Debug Mod├¿le:</h4>
        <p><?php echo htmlspecialchars($debug_model_executed); ?></p>
    </div>
<?php endif; ?>

<?php if (isset($debug_post)): ?>
    <div class="alert alert-info">
        <h4>Debug POST d├®tect├®:</h4>
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
            <p><strong>Cl├®s POST:</strong> <?php echo htmlspecialchars($debug_post_keys); ?></p>
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
            
            <!-- Machine par d├®faut -->
                                        <?php 
            $index = 0;
            include __DIR__ . '/partials/machine_item.html.php';
            ?>
            
            <!-- Bouton pour ajouter une machine (├á l'int├®rieur du container) -->
            <!-- Boutons actions -->
            <div class="row" style="margin: 20px 0;">
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
        
        <!-- R├®capitulatif total -->
        <div class="alert alert-info">
            <h4 class="text-center"><?php _e('tirage_multimachines.summary'); ?></h4>
            <p class="text-center"><strong><?php _e('tirage_multimachines.total_price'); ?> <span id="prix-total">0.00Ôé¼</span></strong></p>
        </div>
        
        <!-- Bouton suivant -->

    </fieldset>
</form>

    <!-- Formulaire d'enregistrement -->
    <form class="form-horizontal" action="" method="post">
        <fieldset>
            
            <!-- Champs cach├®s -->
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
// GESTION DE LA SAUVEGARDE/RESTAURATION DES DONN├ëES DU FORMULAIRE
// ========================================

/**
 * Sauvegarder toutes les donn├®es du formulaire dans sessionStorage
 */
function saveFormData() {
    const form = document.getElementById('multimachines-form');
    if (!form) {
        console.log('Formulaire multimachines-form non trouv├® - probablement sur la page de confirmation');
        return;
    }
    
    try {
        const formData = new FormData(form);
        const data = {};
        
        // Convertir FormData en objet, g├®rer les arrays
        for (let [key, value] of formData.entries()) {
            if (data[key]) {
                // Si la cl├® existe d├®j├á, convertir en array
                if (!Array.isArray(data[key])) {
                    data[key] = [data[key]];
                }
                data[key].push(value);
            } else {
                data[key] = value;
            }
        }
        
        // Sauvegarder aussi les radios/checkboxes non s├®lectionn├®s pour conna├«tre l'├®tat
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
        
        // Sauvegarder le nombre de machines pour pouvoir les recr├®er si n├®cessaire
        // Compter les indices des machines trouv├®s dans les inputs
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
        
        // Utiliser le maximum entre les diff├®rentes m├®thodes de comptage
        data['_machine_count'] = Math.max(machineCountFromIndices, machineItems, machinePanels, machineCount, 1);
        
        console.log('­ƒÆ¥ Sauvegarde - Nombre de machines d├®tect├®:', data['_machine_count'], {
            machineCountFromIndices,
            machineItems,
            machinePanels,
            machineCount,
            indices: Array.from(machineIndicesInForm)
        });
        
        // Sauvegarder l'├®tat des interfaces masqu├®es/affich├®es
        data['_ui_state'] = {};
        form.querySelectorAll('[id*="interface"]').forEach(el => {
            data['_ui_state'][el.id] = el.style.display !== 'none';
        });
        
        sessionStorage.setItem('tirage_multimachines_form_data', JSON.stringify(data));
        console.log('Ô£à Donn├®es du formulaire sauvegard├®es');
    } catch (e) {
        console.error('ÔØî Erreur lors de la sauvegarde:', e);
    }
}

/**
 * Ajouter une machine de mani├¿re asynchrone (retourne une Promise)
 */
function addMachineAsync(index) {
    return new Promise((resolve, reject) => {
        const container = document.getElementById('machines-container');
        if (!container) {
            reject('Container machines-container non trouv├®');
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
                    reject('Aucun ├®l├®ment trouv├® dans le HTML g├®n├®r├®');
                    return;
                }
                
                const addButtonContainer = container.querySelector('div.text-center:last-child');
                
                if (addButtonContainer && container.contains(addButtonContainer)) {
                    container.insertBefore(newMachineContainer, addButtonContainer);
                } else {
                    container.appendChild(newMachineContainer);
                }
                
                // Mettre ├á jour machineCount pour qu'il soit sup├®rieur ou ├®gal ├á l'index cr├®├® + 1
                machineCount = Math.max(machineCount, index + 1);
                
                // Ajouter l'├®v├®nement pour supprimer
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
                        
                        console.log(`Ô£à Machine ${index} initialis├®e compl├¿tement`);
                        resolve(newMachineContainer);
                    } catch (e) {
                        console.error(`ÔØî Erreur lors de l'initialisation de la machine ${index}:`, e);
                        resolve(newMachineContainer); // R├®soudre quand m├¬me pour ne pas bloquer
                    }
                }, 150);
            })
            .catch(error => {
                reject(error);
            });
    });
}

/**
 * Restaurer les donn├®es du formulaire depuis sessionStorage
 */
function restoreFormData() {
    const saved = sessionStorage.getItem('tirage_multimachines_form_data');
    if (!saved) {
        console.log('Aucune donn├®e sauvegard├®e ├á restaurer');
        return false;
    }
    
    const form = document.getElementById('multimachines-form');
    if (!form) {
        console.log('Formulaire non trouv├® pour restauration');
        return false;
    }
    
    try {
        const data = JSON.parse(saved);
        console.log('­ƒöä Restauration des donn├®es du formulaire...');
        
        // D├®terminer les indices des machines sauvegard├®es
        const savedMachineIndices = new Set();
        Object.keys(data).forEach(key => {
            if (key.startsWith('_')) return;
            const match = key.match(/machines\[(\d+)\]/);
            if (match) {
                savedMachineIndices.add(parseInt(match[1]));
            }
        });
        
        // Convertir en tableau tri├®
        const savedIndicesArray = Array.from(savedMachineIndices).sort((a, b) => a - b);
        const maxMachineIndex = savedIndicesArray.length > 0 ? Math.max(...savedIndicesArray) : 0;
        
        // D├®terminer les indices des machines existantes dans le DOM
        const existingMachineIndices = new Set();
        form.querySelectorAll('input[name^="machines["], select[name^="machines["]').forEach(input => {
            const match = input.name.match(/machines\[(\d+)\]/);
            if (match) {
                existingMachineIndices.add(parseInt(match[1]));
            }
        });
        const existingIndicesArray = Array.from(existingMachineIndices).sort((a, b) => a - b);
        
        // Trouver les indices manquants (sauvegard├®s mais pas pr├®sents dans le DOM)
        const missingIndices = savedIndicesArray.filter(idx => !existingMachineIndices.has(idx));
        
        console.log(`­ƒöì Machines sauvegard├®es: indices ${savedIndicesArray.join(', ')}`, {
            savedIndices: savedIndicesArray,
            existingIndices: existingIndicesArray,
            missingIndices: missingIndices,
            maxIndex: maxMachineIndex
        });
        
        // Fonction pour restaurer les donn├®es une fois que toutes les machines sont cr├®├®es
        const restoreFields = () => {
            console.log('­ƒöä D├®but de la restauration des champs...');
            let restoredCount = 0;
            let missingCount = 0;
            
            // Restaurer chaque champ
            Object.keys(data).forEach(key => {
                // Ignorer les m├®tadonn├®es
                if (key.startsWith('_')) return;
                
                const inputs = form.querySelectorAll(`[name="${key}"]`);
                if (inputs.length === 0) {
                    // Si le champ n'existe pas, c'est peut-├¬tre une brochure ou un champ pas encore cr├®├®
                    const brochureMatch = key.match(/machines\[(\d+)\]\[brochures\]\[(\d+)\]\[(\w+)\]/);
                    if (brochureMatch) {
                        // Les brochures seront restaur├®es apr├¿s - pour l'instant on ignore
                        missingCount++;
                        return;
                    }
                    // Autre champ manquant - log pour debug
                    if (!key.includes('brochures')) {
                        console.log(`ÔÜá´©Å Champ non trouv├®: ${key}`);
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
                        console.error(`ÔØî Erreur lors de la restauration de ${key}:`, e);
                    }
                });
            });
            
            console.log(`Ô£à Champs restaur├®s: ${restoredCount}, champs manquants: ${missingCount}`);
            
            // Restaurer l'├®tat des interfaces
            if (data['_ui_state']) {
                Object.keys(data['_ui_state']).forEach(id => {
                    const el = document.getElementById(id);
                    if (el) {
                        el.style.display = data['_ui_state'][id] ? '' : 'none';
                    }
                });
            }
            
            // D├®clencher les ├®v├®nements pour mettre ├á jour l'UI
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
                            // D├®clencher l'├®v├®nement change pour charger les tambours et compteurs
                            if (typeof updateDuplicopieurCounters === 'function') {
                                updateDuplicopieurCounters(duplicopieurSelect.value, index);
                            } else if (typeof loadTamboursForDuplicopieur === 'function') {
                                loadTamboursForDuplicopieur(duplicopieurSelect.value, index);
                            }
                            // D├®clencher aussi l'├®v├®nement change natif
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
            
            // D├®clencher les ├®v├®nements change sur les selects pour charger les donn├®es d├®pendantes
            form.querySelectorAll('select').forEach(select => {
                if (select.value) {
                    // D├®clencher l'├®v├®nement change pour charger les donn├®es d├®pendantes
                    const event = new Event('change', { bubbles: true });
                    setTimeout(() => {
                        try {
                            select.dispatchEvent(event);
                        } catch (e) {
                            console.error('ÔØî Erreur lors du d├®clenchement de l\'├®v├®nement change:', e);
                        }
                    }, 100);
                }
            });
            
            // V├®rifier et afficher le slider de taux de remplissage pour chaque machine si la couleur est coch├®e
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
            
            // Attendre un peu plus avant de recalculer le prix pour ├¬tre s├╗r que tout est pr├¬t
            setTimeout(() => {
                if (typeof calculateTotalPrice === 'function') {
                    console.log('­ƒÆ░ Recalcul du prix total...');
                    calculateTotalPrice();
                }
            }, 500);
            
            console.log('Ô£à Restauration des donn├®es termin├®e');
        };
        
        // Si des machines manquent, les cr├®er avec les bons indices
        if (missingIndices.length > 0) {
            console.log(`­ƒö¿ Cr├®ation de ${missingIndices.length} machine(s) manquante(s) avec indices: ${missingIndices.join(', ')}...`);
            
            // Cr├®er les machines une par une (s├®quentiellement) avec les bons indices
            const createMachinesSequentially = async () => {
                for (const machineIndex of missingIndices) {
                    try {
                        console.log(`­ƒö¿ Cr├®ation machine avec index ${machineIndex}...`);
                        
                        // Cr├®er la machine avec l'index sp├®cifique
                        await addMachineAsync(machineIndex);
                        console.log(`Ô£à Machine ${machineIndex} cr├®├®e et initialis├®e`);
                        
                        // Attendre un peu entre chaque cr├®ation pour laisser le temps au DOM de se mettre ├á jour
                        await new Promise(resolve => setTimeout(resolve, 300));
                    } catch (error) {
                        console.error(`ÔØî Erreur lors de la cr├®ation de la machine ${machineIndex}:`, error);
                        // Continuer m├¬me en cas d'erreur pour ne pas bloquer
                    }
                }
                
                // V├®rifier que toutes les machines sont bien pr├®sentes avant de restaurer
                const finalIndices = new Set();
                form.querySelectorAll('input[name^="machines["], select[name^="machines["]').forEach(input => {
                    const match = input.name.match(/machines\[(\d+)\]/);
                    if (match) {
                        finalIndices.add(parseInt(match[1]));
                    }
                });
                console.log(`­ƒöì V├®rification finale: machines avec indices ${Array.from(finalIndices).sort((a, b) => a - b).join(', ')}`);
                
                console.log('Ô£à Toutes les machines cr├®├®es, restauration des donn├®es...');
                // Attendre un peu plus avant de restaurer pour ├¬tre s├╗r que tout est pr├¬t
                setTimeout(restoreFields, 600);
            };
            
            createMachinesSequentially();
        } else {
            // Pas besoin de cr├®er de machines suppl├®mentaires, restaurer directement
            console.log('Ô£à Toutes les machines sont d├®j├á pr├®sentes, restauration directe...');
            restoreFields();
        }
        
        return true;
    } catch (e) {
        console.error('ÔØî Erreur lors de la restauration:', e);
        return false;
    }
}

/**
 * Fonction pour retourner au formulaire depuis la page de confirmation
 */
function returnToForm() {
    // Les donn├®es sont d├®j├á sauvegard├®es dans sessionStorage
    // Recharger la page principale avec un param├¿tre pour d├®clencher la restauration
    window.location.href = '?tirage_multimachines&retour=1';
}

/**
 * Initialiser la sauvegarde automatique du formulaire
 */
function initAutoSave() {
    const form = document.getElementById('multimachines-form');
    if (!form) return;
    
    // Sauvegarder ├á chaque changement dans le formulaire
    form.addEventListener('input', function(e) {
        // D├®lai pour ├®viter trop de sauvegardes
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
    
    console.log('Ô£à Auto-sauvegarde activ├®e');
}

// Prix depuis la base de donn├®es
const prixData = <?= json_encode($prix_data ?? []) ?>;

// Debug : afficher la structure des prix
console.log('­ƒöì DEBUG PRIX - Prix data:', prixData);
console.log('­ƒöì DEBUG PRIX - Type de prixData:', typeof prixData);
console.log('­ƒöì DEBUG PRIX - Taille de prixData:', Object.keys(prixData).length);
console.log('­ƒöì DEBUG PRIX - Cl├®s disponibles:', Object.keys(prixData));
console.log('­ƒöì DEBUG PRIX - dupli_1 structure:', prixData['dupli_1']);
console.log('­ƒöì DEBUG PRIX - tambour_noir price:', prixData['dupli_1'] ? prixData['dupli_1']['tambour_noir'] : 'NOT_FOUND');

// Fonction pour trouver la cl├® de prix d'une machine par son nom
function findMachinePriceKey(machineName) {
    console.log('­ƒöì Recherche de la cl├® pour la machine:', machineName);
    
    // Parcourir toutes les cl├®s de prixData
    for (const key in prixData) {
        if (key.startsWith('photocop_')) {
            // V├®rifier si cette cl├® correspond ├á la machine recherch├®e
            // Pour l'instant, on va utiliser une approche simple
            // TODO: Am├®liorer cette logique si n├®cessaire
            console.log('­ƒöì Cl├® trouv├®e:', key);
        }
    }
    
    // V├®rifier le cache des mappings
    if (window.machinePriceCache && window.machinePriceCache[machineName]) {
        const priceKey = window.machinePriceCache[machineName];
        console.log(`­ƒöæ Cl├® depuis le cache pour ${machineName}: ${priceKey}`);
        return priceKey;
    }
    
    // Si on ne trouve pas, essayer de trouver la premi├¿re cl├® photocop_ disponible
    for (const key in prixData) {
        if (key.startsWith('photocop_') && prixData[key]) {
            console.log('­ƒöì Utilisation de la cl├® de fallback:', key);
            return key;
        }
    }
    
    console.log('ÔØî Aucune cl├® trouv├®e pour:', machineName);
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
    
    // V├®rifier que tous les ├®l├®ments existent
    if (!duplicopieurRadio || !photocopieurRadio || !duplicopieurInterface || !photocopieurInterface) {
        console.log('├ël├®ments manquants pour toggleMachineType:', {
            machineIndex: machineIndex,
            duplicopieurRadio: !!duplicopieurRadio,
            photocopieurRadio: !!photocopieurRadio,
            duplicopieurInterface: !!duplicopieurInterface,
            photocopieurInterface: !!photocopieurInterface
        });
        return;
    }
    
    if (duplicopieurRadio.checked) {
        // Duplicopieur s├®lectionn├® - rendre le champ duplicopieur_id requis
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
        
        // D├®sactiver ET d├®sactiver la validation des champs photocopieur
        var photocopFields = photocopieurInterface.querySelectorAll('input, select, textarea');
        photocopFields.forEach(function(field) {
            field.disabled = true; // CORRECTION: d├®sactiver pour ne pas envoyer dans POST
            field.removeAttribute('required');
        });
        
    } else if (photocopieurRadio.checked) {
        // Photocopieur s├®lectionn├® - rendre le champ duplicopieur_id non requis
        if (duplicopieurSelect) {
            duplicopieurSelect.required = false;
            duplicopieurSelect.value = ''; // Vider le champ
        }
        duplicopieurInterface.style.display = 'none';
        photocopieurInterface.style.display = 'block';
        
        // D├®sactiver les champs duplicopieur
        var duplicopieurFields = duplicopieurInterface.querySelectorAll('input, select, textarea');
        duplicopieurFields.forEach(function(field) {
            field.disabled = true; // CORRECTION: d├®sactiver pour ne pas envoyer dans POST
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
        
        // Ajouter les gestionnaires pour mettre ├á jour le total en temps r├®el
        var exemplairesInput = photocopieurInterface.querySelector('input[name*="[nb_exemplaires]"]');
        var feuillesInput = photocopieurInterface.querySelector('input[name*="[nb_feuilles]"]');
        
        if (exemplairesInput && feuillesInput) {
            exemplairesInput.addEventListener('input', updateTotalFeuilles);
            feuillesInput.addEventListener('input', updateTotalFeuilles);
        }
    }
    
    calculateTotalPrice();
    // Mettre ├á jour le preview du panel
    updatePanelPreview(machineIndex);
    
    // Mettre ├á jour le total des feuilles pour cette machine
    updateTotalFeuillesForMachine(machineIndex);
}

// Fonction pour mettre ├á jour le total des feuilles en temps r├®el
function updateTotalFeuilles() {
    var machineIndex = this.closest('[data-index]').getAttribute('data-index');
    updateTotalFeuillesForMachine(machineIndex);
}

// Fonction pour mettre ├á jour le total des feuilles pour une machine sp├®cifique
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
    console.log("­ƒöì calculateMachinePrice appel├® avec index:", machineIndex);
    var machineElement = document.querySelector(`[data-index="${machineIndex}"]`);
    console.log("­ƒöì machineElement trouv├®:", machineElement ? "oui" : "non");
    if (!machineElement) {
        console.log("ÔØî ERREUR: machineElement non trouv├® pour index", machineIndex);
        return 0;
    }
    
    var typeRadio = machineElement.querySelector(`input[name="machines[${machineIndex}][type]"]:checked`);
    console.log("­ƒöì typeRadio trouv├®:", typeRadio ? typeRadio.value : "non");
    if (!typeRadio) {
        console.log("ÔØî ERREUR: typeRadio non trouv├® pour index", machineIndex);
        return 0;
    }
    
    var price = 0;
    var detailCalcul = '';
    
    if (typeRadio.value === 'duplicopieur') {
        console.log("­ƒöì Calcul duplicopieur pour index:", machineIndex);
        // Calcul pour duplicopieur
        var modeSaisieRadio = machineElement.querySelector(`input[name="machines[${machineIndex}][mode_saisie]"]:checked`);
        console.log("­ƒöì modeSaisieRadio trouv├®:", modeSaisieRadio ? modeSaisieRadio.value : "non");
        var nbMasters = 0;
        var nbPassages = 0;
        
        if (modeSaisieRadio && modeSaisieRadio.value === 'compteurs') {
            var masterAvElement = machineElement.querySelector(`#master_av_${machineIndex}`);
            var masterApElement = machineElement.querySelector(`#master_ap_${machineIndex}`);
            var passageAvElement = machineElement.querySelector(`#passage_av_${machineIndex}`);
            var passageApElement = machineElement.querySelector(`#passage_ap_${machineIndex}`);
            
            // Debug: v├®rifier le contenu de machineElement
            console.log("machineElement.innerHTML:", machineElement.innerHTML.substring(0, 300) + '...');
            console.log("Recherche des ├®l├®ments avec ID:", {
                masterAv: `#master_av_${machineIndex}`,
                masterAp: `#master_ap_${machineIndex}`,
                passageAv: `#passage_av_${machineIndex}`,
                passageAp: `#passage_ap_${machineIndex}`
            });
            
            console.log("├ël├®ments trouv├®s:", {
                masterAv: masterAvElement ? "oui" : "non",
                masterAp: masterApElement ? "oui" : "non", 
                passageAv: passageAvElement ? "oui" : "non",
                passageAp: passageApElement ? "oui" : "non"
            });
            
            var masterAv = parseFloat(masterAvElement ? masterAvElement.value : 0) || 0;
            var masterAp = parseFloat(masterApElement ? masterApElement.value : 0) || 0;
            var passageAv = parseFloat(passageAvElement ? passageAvElement.value : 0) || 0;
            var passageAp = parseFloat(passageApElement ? passageApElement.value : 0) || 0;
            
            console.log("­ƒöì Valeurs brutes des champs:", {
                masterAvElement_value: masterAvElement ? masterAvElement.value : "├®l├®ment non trouv├®",
                masterApElement_value: masterApElement ? masterApElement.value : "├®l├®ment non trouv├®",
                passageAvElement_value: passageAvElement ? passageAvElement.value : "├®l├®ment non trouv├®",
                passageApElement_value: passageApElement ? passageApElement.value : "├®l├®ment non trouv├®"
            });
            
            nbMasters = Math.max(0, masterAp - masterAv);
            nbPassages = Math.max(0, passageAp - passageAv);
            
            console.log("­ƒöì Valeurs calcul├®es:", {
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
        
        
        // D├®terminer la taille selon les options
        var taille = 'A3'; // Par d├®faut A3
        var a4 = machineElement.querySelector(`input[name="machines[${machineIndex}][A4]"]`).checked;
        if (a4) taille = 'A4';
        
        // Tarifs depuis la base de donn├®es selon la nouvelle structure
        // Utiliser l'ID du duplicopieur s├®lectionn├®
        var duplicopieurSelect = machineElement.querySelector('select[name*="[duplicopieur_id]"]');
        var duplicopieurId = duplicopieurSelect ? duplicopieurSelect.value : '<?= $duplicopieur_selectionne['id'] ?? '' ?>'; // Utiliser l'ID du duplicopieur s├®lectionn├®
        var machineKey = 'dupli_' + duplicopieurId;
        var prixMaster = prixData[machineKey] && prixData[machineKey]['master'] ? prixData[machineKey]['master']['unite'] : 0;
        
        // Prix des passages selon le tambour s├®lectionn├®
        var tambourSelect = machineElement.querySelector('select[name*="[tambour]"]');
        var tambourSelected = tambourSelect ? tambourSelect.value : '';
        var prixPassage = 0;
        
        console.log('­ƒöì Calcul prix passage - machineKey:', machineKey, 'tambourSelected:', tambourSelected);
        console.log('­ƒöì prixData[machineKey]:', prixData[machineKey]);
        
        if (tambourSelected && prixData[machineKey] && prixData[machineKey][tambourSelected]) {
            prixPassage = prixData[machineKey][tambourSelected]['unite'] || 0;
            console.log('Ô£à Prix passage (tambour s├®lectionn├®):', prixPassage);
        } else if (prixData[machineKey] && prixData[machineKey]['tambour_noir']) {
            // Fallback sur le tambour noir si pas de tambour sp├®cifique
            prixPassage = prixData[machineKey]['tambour_noir']['unite'] || 0;
            console.log('Ô£à Prix passage (tambour noir fallback):', prixPassage);
        } else {
            console.log('ÔØî Aucun prix trouv├® pour machineKey:', machineKey);
        }
        
        var prixPapier = prixData['papier'] && prixData['papier'][taille] ? prixData['papier'][taille] : 0;
        
        // NOUVELLE LOGIQUE : A4 = A3/2 pour masters et passages
        if (taille === 'A4') {
            prixMaster = prixMaster / 2;
            prixPassage = prixPassage / 2;
        }
        
        console.log("Prix calcul├®s:", {
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
        
        // V├®rifier que les prix sont disponibles
        if (prixMaster === 0 && prixPassage === 0 && prixPapier === 0) {
            detailCalcul = `
                <div class="price-detail" style="font-size: 0.9em; color: red; margin-top: 5px;">
                    <strong>ÔÜá´©Å Erreur :</strong> Les prix ne sont pas disponibles dans la base de donn├®es.<br>
                    Veuillez v├®rifier la configuration des prix.
                </div>
            `;
            price = 0;
        } else {
            // D├®tail du calcul
            detailCalcul = `
                <div class="price-detail" style="font-size: 0.9em; color: #666; margin-top: 5px;">
                    <strong><?php _e('tirage_multimachines.calculation_detail'); ?> :</strong><br>
                    ÔÇó ${nbMasters} masters ├ù ${prixMaster.toFixed(2)}Ôé¼ = ${coutMasters.toFixed(2)}Ôé¼<br>
                    ÔÇó ${nbPassages} passages ├ù ${prixPassage.toFixed(2)}Ôé¼ = ${coutPassages.toFixed(2)}Ôé¼<br>
                    ÔÇó ${nb_f.toFixed(0)} feuilles papier ├ù ${prixPapier.toFixed(2)}Ôé¼ = ${coutPapier.toFixed(2)}Ôé¼<br>
                    <strong>Total : ${price.toFixed(2)}Ôé¼</strong>
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
            
            // R├®cup├®rer le taux de remplissage
            var fillRateElement = machineElement.querySelector('#fill_rate_photocop_' + machineIndex);
            var fillRate = fillRateElement ? parseFloat(fillRateElement.value) : 0.5;
            var fillRateMultiplier = couleur ? (fillRate / 0.5) : 1.0; // 50% = ├ù1, 100% = ├ù2
            
            // NOUVELLE STRUCTURE : Utiliser la fonction pour trouver la cl├® dynamique
            var machineKey = findMachinePriceKey(photocopName);
            console.log('­ƒöæ Cl├® trouv├®e pour', photocopName, ':', machineKey);
            
            if (machineKey && prixData[machineKey]) {
                var machinePrices = prixData[machineKey];
                
                if (photocopName.toLowerCase() === 'comcolor') {
                    // Photocopieur ├á encre : additionner toutes les encres
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
                    // Photocopieur ├á toner : additionner tous les toners + tambour + developer
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
            
            // Calculer le co├╗t
            var nbPages = nbExemplaires * nbFeuilles;
            var coutPapier = feuilles_payees ? 0 : (nbPages * prixPapier); // Papier = nombre de feuilles (0 si d├®j├á pay├®es)
            var coutEncre = nbPages * prixEncre; // Encre de base
            if (rv) coutEncre = coutEncre * 2; // Recto-verso = 2 fois plus d'encre
            var coutBrochure = coutPapier + coutEncre;
            
            console.log(`Brochure ${taille}: exemplaires=${nbExemplaires}, feuilles=${nbFeuilles}, rv=${rv}, nbPages=${nbPages}, prixPapier=${prixPapier}, prixEncre=${prixEncre}, coutBrochure=${coutBrochure}`);
            
            price += coutBrochure;
            
            totalExemplaires += nbExemplaires;
        });
        
        // D├®tail du calcul pour photocopieur
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
            
            // R├®cup├®rer le taux de remplissage (comme dans la premi├¿re boucle)
            var fillRateElement = machineElement.querySelector('#fill_rate_photocop_' + machineIndex);
            var fillRate = fillRateElement ? parseFloat(fillRateElement.value) : 0.5;
            var fillRateMultiplier = couleur ? (fillRate / 0.5) : 1.0; // 50% = ├ù1, 100% = ├ù2
            
            // NOUVELLE STRUCTURE : Utiliser la fonction pour trouver la cl├® dynamique
            var machineKey = findMachinePriceKey(photocopName);
            console.log('­ƒöæ Cl├® trouv├®e pour le d├®tail', photocopName, ':', machineKey);
            
            if (machineKey && prixData[machineKey]) {
                var machinePrices = prixData[machineKey];
                
                if (photocopName.toLowerCase() === 'comcolor') {
                    // Photocopieur ├á encre : additionner toutes les encres
                    if (couleur) {
                        // Couleur : bleue + couleur + jaune + noire + rouge (avec taux de remplissage)
                        var bleue = (machinePrices['bleue']?.unite || 0) * fillRateMultiplier;
                        var couleurPrice = (machinePrices['couleur']?.unite || 0) * fillRateMultiplier;
                        var jaune = (machinePrices['jaune']?.unite || 0) * fillRateMultiplier;
                        var noire = (machinePrices['noire']?.unite || 0) * fillRateMultiplier;
                        var rouge = (machinePrices['rouge']?.unite || 0) * fillRateMultiplier;
                        
                        prixEncre = bleue + couleurPrice + jaune + noire + rouge;
                        
                        // Ajuster selon la taille AVANT d'afficher le d├®tail
                        var prixEncrePourDetail = prixEncre;
                        if (taille === 'A4') prixEncrePourDetail = prixEncre / 2;
                        
                        var bleueDetail = taille === 'A4' ? bleue / 2 : bleue;
                        var couleurPriceDetail = taille === 'A4' ? couleurPrice / 2 : couleurPrice;
                        var jauneDetail = taille === 'A4' ? jaune / 2 : jaune;
                        var noireDetail = taille === 'A4' ? noire / 2 : noire;
                        var rougeDetail = taille === 'A4' ? rouge / 2 : rouge;
                        
                        detailEncreBrochure = `Bleue: ${bleueDetail.toFixed(4)}Ôé¼ + Couleur: ${couleurPriceDetail.toFixed(4)}Ôé¼ + Jaune: ${jauneDetail.toFixed(4)}Ôé¼ + Noire: ${noireDetail.toFixed(4)}Ôé¼ + Rouge: ${rougeDetail.toFixed(4)}Ôé¼ = ${prixEncrePourDetail.toFixed(4)}Ôé¼`;
                    } else {
                        // Noir et blanc : seulement noire (pas de taux de remplissage)
                        prixEncre = machinePrices['noire']?.unite || 0;
                        
                        // Ajuster selon la taille AVANT d'afficher le d├®tail
                        var prixEncrePourDetail = prixEncre;
                        if (taille === 'A4') prixEncrePourDetail = prixEncre / 2;
                        
                        detailEncreBrochure = `Noire: ${prixEncrePourDetail.toFixed(4)}Ôé¼`;
                    }
                } else if (photocopName.toLowerCase() === 'konika') {
                    // Photocopieur ├á toner : additionner tous les toners + tambour + developer
                    if (couleur) {
                        // Couleur : cyan + jaune + magenta + noir (avec taux de remplissage) + tambour + dev (sans taux)
                        var cyan = (machinePrices['cyan']?.unite || 0) * fillRateMultiplier;
                        var jaune = (machinePrices['jaune']?.unite || 0) * fillRateMultiplier;
                        var magenta = (machinePrices['magenta']?.unite || 0) * fillRateMultiplier;
                        var noir = (machinePrices['noir']?.unite || 0) * fillRateMultiplier;
                        var tambour = machinePrices['tambour']?.unite || 0;
                        var dev = machinePrices['dev']?.unite || 0;
                        
                        prixEncre = cyan + jaune + magenta + noir + tambour + dev;
                        
                        // Ajuster selon la taille AVANT d'afficher le d├®tail
                        var prixEncrePourDetail = prixEncre;
                        if (taille === 'A4') prixEncrePourDetail = prixEncre / 2;
                        
                        var cyanDetail = taille === 'A4' ? cyan / 2 : cyan;
                        var jauneDetail = taille === 'A4' ? jaune / 2 : jaune;
                        var magentaDetail = taille === 'A4' ? magenta / 2 : magenta;
                        var noirDetail = taille === 'A4' ? noir / 2 : noir;
                        var tambourDetail = taille === 'A4' ? tambour / 2 : tambour;
                        var devDetail = taille === 'A4' ? dev / 2 : dev;
                        
                        detailEncreBrochure = `Cyan: ${cyanDetail.toFixed(4)}Ôé¼ + Jaune: ${jauneDetail.toFixed(4)}Ôé¼ + Magenta: ${magentaDetail.toFixed(4)}Ôé¼ + Noir: ${noirDetail.toFixed(4)}Ôé¼ + Tambour: ${tambourDetail.toFixed(4)}Ôé¼ + Dev: ${devDetail.toFixed(4)}Ôé¼ = ${prixEncrePourDetail.toFixed(4)}Ôé¼`;
                    } else {
                        // Noir et blanc : noir + tambour + dev (pas de taux de remplissage)
                        var noir = machinePrices['noir']?.unite || 0;
                        var tambour = machinePrices['tambour']?.unite || 0;
                        var dev = machinePrices['dev']?.unite || 0;
                        
                        prixEncre = noir + tambour + dev;
                        
                        // Ajuster selon la taille AVANT d'afficher le d├®tail
                        var prixEncrePourDetail = prixEncre;
                        if (taille === 'A4') prixEncrePourDetail = prixEncre / 2;
                        
                        var noirDetail = taille === 'A4' ? noir / 2 : noir;
                        var tambourDetail = taille === 'A4' ? tambour / 2 : tambour;
                        var devDetail = taille === 'A4' ? dev / 2 : dev;
                        
                        detailEncreBrochure = `Noir: ${noirDetail.toFixed(4)}Ôé¼ + Tambour: ${tambourDetail.toFixed(4)}Ôé¼ + Dev: ${devDetail.toFixed(4)}Ôé¼ = ${prixEncrePourDetail.toFixed(4)}Ôé¼`;
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
            
            // Calculer le co├╗t papier pour cette brochure
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
                <strong>D├®tail du calcul :</strong><br>
                ÔÇó ${totalExemplaires} exemplaires ├ù ${feuillesParExemplaireText} feuilles = ${totalPagesText} pages<br>
                ÔÇó Papier : ${totalPages} feuilles ├ù ${prixPapierMoyen.toFixed(3)}Ôé¼ = ${coutPapierTotal.toFixed(2)}Ôé¼<br>
                ÔÇó Encre : ${totalPagesEncre} pages ├ù ${prixEncreMoyen.toFixed(4)}Ôé¼ = ${coutEncreTotal.toFixed(2)}Ôé¼${detailEncre}<br>
                <strong>Total : ${price.toFixed(2)}Ôé¼</strong>
        </div>
    `;
    }
    
    // Mettre ├á jour l'affichage du prix de cette machine
    var priceElement = machineElement.querySelector('.machine-price');
    console.log("­ƒöì ├ël├®ment .machine-price trouv├®:", priceElement ? "oui" : "non");
    if (priceElement) {
        priceElement.innerHTML = price.toFixed(2) + 'Ôé¼' + detailCalcul;
        console.log("Ô£à Prix mis ├á jour dans l'├®l├®ment:", price.toFixed(2) + 'Ôé¼');
    } else {
        console.log("ÔØî ERREUR: ├ël├®ment .machine-price non trouv├® pour machine", machineIndex);
        // Essayer de trouver l'├®l├®ment par ID
        var priceElementById = document.getElementById('machine-price-' + machineIndex);
        console.log("­ƒöì ├ël├®ment #machine-price-" + machineIndex + " trouv├®:", priceElementById ? "oui" : "non");
        if (priceElementById) {
            priceElementById.innerHTML = price.toFixed(2) + 'Ôé¼' + detailCalcul;
            console.log("Ô£à Prix mis ├á jour par ID:", price.toFixed(2) + 'Ôé¼');
        }
    }
    
    console.log(`­ƒöì Prix final retourn├® pour machine ${machineIndex}: ${price.toFixed(2)}Ôé¼`);
    return price;
}

function calculateTotalPrice() {
    console.log("­ƒöì calculateTotalPrice appel├®");
    var total = 0;
    var machineElements = document.querySelectorAll('.machine-item');
    console.log("­ƒöì machineElements trouv├®s:", machineElements.length);
    
    if (machineElements.length === 0) {
        console.log("ÔØî ERREUR: Aucune machine trouv├®e avec la classe .machine-item");
        return;
    }
    
    machineElements.forEach(function(machineElement) {
        var machineIndex = machineElement.getAttribute('data-index');
        console.log("­ƒöì machineIndex:", machineIndex);
        var price = calculateMachinePrice(machineIndex);
        console.log("­ƒöì prix calcul├® pour index", machineIndex, ":", price);
        total += price;
        
        // Mettre ├á jour le preview du panel
        updatePanelPreview(machineIndex);
    });
    
    console.log("Total final:", total);
    
    // V├®rifier que l'├®l├®ment existe avant de le modifier
    var prixTotalElement = document.getElementById('prix-total');
    if (prixTotalElement) {
        prixTotalElement.textContent = total.toFixed(2) + 'Ôé¼';
    } else {
        console.log("├ël├®ment #prix-total non trouv├®");
    }
    
    // Mettre ├á jour le champ de paiement si "oui" est coch├®
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
    console.log('­ƒöä Initialisation du cache des mappings machine...');
    
    // Utiliser les mappings g├®n├®r├®s c├┤t├® serveur
    window.machinePriceCache = <?= json_encode($machine_price_mappings) ?>;
    
    console.log('Ô£à Cache des mappings initialis├® c├┤t├® serveur:', window.machinePriceCache);
}

// Gestion des machines
document.addEventListener('DOMContentLoaded', function() {
    console.log('­ƒöì DOM charg├®, initialisation des prix...');
    
    // Initialiser le cache des mappings machine -> price_key
    initializeMachinePriceCache();
    
    // Initialiser l'auto-sauvegarde du formulaire
    initAutoSave();
    
    // V├®rifier si on doit restaurer les donn├®es
    // Seulement si on vient de la page de confirmation (param├¿tre retour=1)
    const urlParams = new URLSearchParams(window.location.search);
    const shouldRestore = urlParams.get('retour') === '1' && sessionStorage.getItem('tirage_multimachines_form_data');
    
    if (shouldRestore) {
        console.log('­ƒöä Restauration des donn├®es du formulaire depuis la page de confirmation...');
        setTimeout(() => {
            const restored = restoreFormData();
            if (restored) {
                console.log('Ô£à Donn├®es restaur├®es, recalcul du prix...');
                // Attendre un peu pour que tous les ├®l├®ments soient restaur├®s
                setTimeout(() => {
                    calculateTotalPrice();
                    // Nettoyer l'URL pour retirer le param├¿tre retour
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
        console.log('Bouton add-machine non trouv├® - probablement sur la page de confirmation');
        return;
    }
    
    addMachineBtn.addEventListener('click', function() {
    const container = document.getElementById('machines-container');
    const newIndex = machineCount;
    
    // Faire une requ├¬te AJAX pour r├®cup├®rer le HTML de la machine
    fetch(`?get-machine-template&index=${newIndex}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error('Erreur:', data.error);
                alert('Erreur lors de l\'ajout de la machine: ' + data.error);
                return;
            }
            
            // Cr├®er un ├®l├®ment div temporaire pour parser le HTML
            // Debug: v├®rifier le HTML re├ºu
            console.log('HTML re├ºu de l\'endpoint:', data.html.substring(0, 200) + '...');
            
            // Cr├®er un ├®l├®ment temporaire pour parser le HTML
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = data.html;
            
            // Debug: v├®rifier le parsing
            console.log('tempDiv.children.length:', tempDiv.children.length);
            console.log('Tous les enfants:', Array.from(tempDiv.children).map(el => el.tagName));
            
            // Le HTML g├®n├®r├® est d├®j├á un panel complet, on l'utilise directement
            const newMachineContainer = tempDiv.firstElementChild;
            
            if (!newMachineContainer) {
                console.error('Aucun ├®l├®ment trouv├® dans le HTML g├®n├®r├®');
                alert('Erreur lors de l\'ajout de la machine: HTML invalide');
                return;
            }
            
            // Ajouter la machine au container
            // Trouver le div qui contient le bouton "Ajouter un tirage" (le dernier .text-center)
            const addButtonContainer = container.querySelector('div.text-center:last-child');
            
            console.log('­ƒöì container:', container);
            console.log('­ƒöì addButtonContainer:', addButtonContainer);
            console.log('­ƒöì container.children:', Array.from(container.children).map(el => el.className || el.tagName));
            
            if (addButtonContainer && container.contains(addButtonContainer)) {
                // Ins├®rer la nouvelle machine AVANT le div du bouton
                container.insertBefore(newMachineContainer, addButtonContainer);
                console.log('Ô£à Machine ajout├®e avec succ├¿s avant le bouton!');
            } else {
                // Fallback : ajouter ├á la fin du container
                console.log('ÔÜá´©Å Fallback: ajout ├á la fin');
                container.appendChild(newMachineContainer);
            }
    machineCount++;
    
            // Debug: v├®rifier le contenu de newMachineContainer
            console.log('newMachineContainer HTML:', newMachineContainer.innerHTML.substring(0, 200) + '...');
            console.log('Recherche du bouton remove-machine...');
    
    // Ajouter l'├®v├®nement pour supprimer
            const removeBtn = newMachineContainer.querySelector('.remove-machine');
            if (removeBtn) {
                console.log('Bouton remove-machine trouv├®:', removeBtn);
                removeBtn.addEventListener('click', function() {
                    newMachineContainer.remove();
                    machineCount = Math.max(1, machineCount - 1);
                    calculateTotalPrice();
                    saveFormData(); // Sauvegarder apr├¿s suppression
                });
            } else {
                console.error('Bouton remove-machine non trouv├® dans le HTML g├®n├®r├®');
                console.log('Tous les boutons dans newMachineContainer:', newMachineContainer.querySelectorAll('button'));
            }
            
            // Initialiser la validation pour cette machine
            // Attendre un peu que le DOM soit mis ├á jour
            setTimeout(() => {
                console.log('Appel de toggleMachineType pour index:', newIndex);
                console.log('Recherche des ├®l├®ments radio...');
                const duplicopieurRadio = document.querySelector(`input[name="machines[${newIndex}][type]"][value="duplicopieur"]`);
                const photocopieurRadio = document.querySelector(`input[name="machines[${newIndex}][type]"][value="photocopieur"]`);
                console.log('duplicopieurRadio trouv├®:', !!duplicopieurRadio);
                console.log('photocopieurRadio trouv├®:', !!photocopieurRadio);
                toggleMachineType(newIndex);
                
                // Charger les tambours du duplicopieur si un duplicopieur est s├®lectionn├®
                const duplicopieurIdField = document.querySelector(`select[name="machines[${newIndex}][duplicopieur_id]"]`) || document.querySelector(`input[name="machines[${newIndex}][duplicopieur_id]"]`);
                if (duplicopieurIdField && duplicopieurIdField.value) {
                    const duplicopieurId = duplicopieurIdField.value;
                    console.log('­ƒÄ» Chargement des tambours pour machine', newIndex, ', duplicopieur ID:', duplicopieurId);
                    loadTamboursForDuplicopieur(duplicopieurId, newIndex);
                } else {
                    console.log('ÔÜá´©Å Pas de duplicopieur s├®lectionn├® pour machine', newIndex);
                }
                
                // Sauvegarder apr├¿s l'ajout d'une machine
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

// Initialiser le champ au chargement de la page si "oui" est d├®j├á s├®lectionn├®
document.addEventListener('DOMContentLoaded', function() {
    var payeOui = document.getElementById('payeoui');
    if (payeOui && payeOui.checked) {
        updatePaymentAmount();
    }
    
    // Initialiser la validation pour la premi├¿re machine (duplicopieur par d├®faut)
    toggleMachineType(0);
    
    // Charger les tambours pour la machine 0 si un duplicopieur est d├®j├á s├®lectionn├®
    var duplicopieurSelect0 = document.querySelector('select[name="machines[0][duplicopieur_id]"]');
    var duplicopieurHidden0 = document.querySelector('input[name="machines[0][duplicopieur_id]"]');
    var duplicopieurId0 = null;
    
    if (duplicopieurSelect0 && duplicopieurSelect0.value) {
        duplicopieurId0 = duplicopieurSelect0.value;
    } else if (duplicopieurHidden0 && duplicopieurHidden0.value) {
        duplicopieurId0 = duplicopieurHidden0.value;
    }
    
    if (duplicopieurId0) {
        console.log('­ƒÄ» Chargement initial des tambours pour machine 0, duplicopieur ID:', duplicopieurId0);
        loadTamboursForDuplicopieur(duplicopieurId0, 0);
    }
    
    calculateTotalPrice();
    
    // S'assurer que le champ cb1 est rempli avant la soumission
    const multimachinesForm = document.getElementById('multimachines-form');
    if (multimachinesForm) {
        multimachinesForm.addEventListener('submit', function() {
            var payeOui = document.getElementById('payeoui');
            var cbField = document.getElementById('cb1');
            if (payeOui && payeOui.checked && cbField) {
                var total = calculateTotalPrice();
                cbField.value = total.toFixed(2);
            }
            // Sauvegarder une derni├¿re fois avant la soumission
            saveFormData();
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
// Attacher les ├®v├®nements seulement si les ├®l├®ments existent
// Fonction globale pour mettre ├á jour le montant de paiement
function updatePaymentAmount() {
    console.log("updatePaymentAmount appel├®");
    var payeOui = document.getElementById('payeoui');
    var cbField = document.getElementById('cb1');
    
    // V├®rifier que les ├®l├®ments existent avant de les utiliser
    if (!payeOui || !cbField) {
        console.log("├ël├®ments payeOui ou cbField non trouv├®s");
        return;
    }
    
    if (payeOui.checked) {
        // Essayer de trouver le prix total dans l'├®l├®ment #prix-total
        var prixTotalElement = document.getElementById('prix-total');
        if (prixTotalElement) {
            var totalText = prixTotalElement.textContent;
            var cleanedTotal = cleanNumberString(totalText);
            if (!isNaN(cleanedTotal)) {
                cbField.value = cleanedTotal.toFixed(2);
                console.log("Prix total trouv├® dans #prix-total:", cleanedTotal);
                return;
            }
        }
        
        // Si pas trouv├® dans #prix-total, essayer de trouver le prix total dans le r├®capitulatif (page de confirmation)
        // Chercher sp├®cifiquement dans l'├®l├®ment h2.text-primary strong (structure exacte du TOTAL GLOBAL)
        var totalPriceElement = document.querySelector('h2.text-primary strong');
        if (totalPriceElement) {
            var totalText = totalPriceElement.textContent;
            console.log("Prix trouv├® dans h2.text-primary strong:", totalText);
            var cleanedTotal = cleanNumberString(totalText);
            if (!isNaN(cleanedTotal)) {
                console.log("Prix total extrait:", cleanedTotal);
                cbField.value = cleanedTotal.toFixed(2);
                return;
            }
        }
        
        console.log("Aucun prix total trouv├®");
    } else {
        // Si "non" est s├®lectionn├®, vider le champ
        cbField.value = '';
        console.log("cbField.value vid├®");
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

// Fonction pour mettre ├á jour les compteurs d'un duplicopieur
function updateDuplicopieurCounters(duplicopieurId, machineIndex) {
    console.log('­ƒöº updateDuplicopieurCounters appel├®e avec ID:', duplicopieurId, 'Index:', machineIndex);
    console.log('­ƒöì jQuery disponible:', typeof $ !== 'undefined');
    
    if (!duplicopieurId) {
        console.log('ÔØî Pas d\'ID duplicopieur fourni');
        return;
    }
    
    // R├®cup├®rer le nom de la machine depuis l'option s├®lectionn├®e
    var selectElement = document.querySelector('select[name="machines[' + machineIndex + '][duplicopieur_id]"]');
    var selectedOption = selectElement.options[selectElement.selectedIndex];
    var machineName = selectedOption.getAttribute('data-name');
    
    console.log('­ƒöì Nom de la machine r├®cup├®r├®:', machineName);
    
    if (!machineName) {
        console.log('ÔØî Pas de nom de machine trouv├®');
        return;
    }
    
    console.log('­ƒîÉ Appel AJAX vers: ?tirage_multimachines&ajax=get_last_counters&machine=' + encodeURIComponent(machineName));
    
    // Charger les tambours du duplicopieur s├®lectionn├®
    loadTamboursForDuplicopieur(duplicopieurId, machineIndex);
    
    // Faire un appel AJAX pour r├®cup├®rer les compteurs
    $.get('?tirage_multimachines&ajax=get_last_counters&machine=' + encodeURIComponent(machineName))
        .done(function(response) {
            console.log('Ô£à R├®ponse AJAX re├ºue:', response);
            if (response.success) {
                console.log('­ƒôè Compteurs re├ºus:', response.counters);
                // Mettre ├á jour les champs de compteurs
                $('#master_av_' + machineIndex).val(response.counters.master_av || 0);
                $('#passage_av_' + machineIndex).val(response.counters.passage_av || 0);
                
                console.log('­ƒöä Compteurs mis ├á jour - Master:', response.counters.master_av, 'Passage:', response.counters.passage_av);
                
                // Recalculer le prix total (les prix vont changer selon le duplicopieur s├®lectionn├®)
                if (typeof calculateTotalPrice === 'function') {
                    calculateTotalPrice();
                }
            } else {
                console.log('ÔØî R├®ponse AJAX indique un ├®chec:', response);
            }
        })
        .fail(function(xhr, status, error) {
            console.log('ÔØî Erreur AJAX:', xhr.responseText);
            console.log('ÔØî Status:', status);
            console.log('ÔØî Error:', error);
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

// Fonction pour g├®rer le syst├¿me d'onglets
function selectMachineTypeTab(machineIndex, type) {
    console.log('S├®lection onglet:', type, 'pour machine:', machineIndex);
    
    // Mettre ├á jour les classes des onglets
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
    
    // Cocher le bon radio button cach├®
    const radioDupli = document.getElementById('radio-duplicopieur-' + machineIndex);
    const radioPhoto = document.getElementById('radio-photocopieur-' + machineIndex);
    
    if (radioDupli && radioPhoto) {
        if (type === 'duplicopieur') {
            radioDupli.checked = true;
        } else {
            radioPhoto.checked = true;
        }
    }
    
    // D├®clencher le changement d'interface
    toggleMachineType(machineIndex);
}

// Fonction pour ouvrir/fermer un panel d'accord├®on
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

// Fonction pour mettre ├á jour le preview du panel (prix et type)
function updatePanelPreview(machineIndex) {
    console.log("­ƒöì updatePanelPreview appel├® pour machine", machineIndex);
    const pricePreview = document.getElementById('price-preview-' + machineIndex);
    const typeBadge = document.getElementById('type-badge-' + machineIndex);
    
    console.log("­ƒöì ├ël├®ments trouv├®s:", {
        pricePreview: pricePreview ? "oui" : "non",
        typeBadge: typeBadge ? "oui" : "non"
    });
    
    // Mettre ├á jour le type
    const typeRadio = document.querySelector(`input[name="machines[${machineIndex}][type]"]:checked`);
    if (typeBadge && typeRadio) {
        typeBadge.textContent = typeRadio.value === 'duplicopieur' ? 'Duplicopieur' : 'Photocopieur';
        console.log("Ô£à Type mis ├á jour:", typeRadio.value);
    }
    
    // Mettre ├á jour le prix
    if (pricePreview) {
        const price = calculateMachinePrice(machineIndex);
        pricePreview.textContent = price.toFixed(2) + 'Ôé¼';
        console.log("Ô£à Prix preview mis ├á jour:", price.toFixed(2) + 'Ôé¼');
    } else {
        console.log("ÔØî ERREUR: price-preview-" + machineIndex + " non trouv├®");
    }
}

function updateFillRateDisplay(prefix, machineIndex) {
    var slider = document.getElementById('fill_rate_' + prefix + '_slider_' + machineIndex);
    var display = document.getElementById('fill_rate_' + prefix + '_display_' + machineIndex);
    var hidden = document.getElementById('fill_rate_' + prefix + '_' + machineIndex);
    
    if (slider && display && hidden) {
        var value = parseInt(slider.value);
        var percentage = value + '%';
        var fillRate = (value / 100).toFixed(1);
        
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
    console.log('­ƒÑü Chargement des tambours pour duplicopieur ID:', duplicopieurId);
    
    $.get('?tirage_multimachines&ajax=get_tambours&duplicopieur_id=' + duplicopieurId)
        .done(function(response) {
            console.log('Ô£à Tambours re├ºus:', response);
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
                    
                    // S├®lectionner automatiquement le premier tambour
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
                    // Un seul tambour : le s├®lectionner automatiquement en arri├¿re-plan (cach├®)
                    tambourGroup.hide();
                    tambourSelect.prop('required', false);
                    tambourSelect.val(response.tambours[0]);
                }
                
                console.log('­ƒÄ» Tambours charg├®s:', response.tambours.length, 'tambour(s)');
                
                // Ajouter un event listener pour recalculer le prix quand le tambour change
                tambourSelect.off('change.tambour').on('change.tambour', function() {
                    console.log('­ƒÑü Tambour chang├®, recalcul du prix pour index:', machineIndex);
                    if (typeof calculateTotalPrice === 'function') {
                        calculateTotalPrice();
                    }
                    // Mettre ├á jour le preview du panel
                    updatePanelPreview(machineIndex);
                });
                
                // D├®clencher le calcul initial du prix
                if (typeof calculateTotalPrice === 'function') {
                    calculateTotalPrice();
                }
                // Mettre ├á jour le preview du panel
                updatePanelPreview(machineIndex);
            } else {
                console.log('ÔØî Erreur lors du chargement des tambours:', response.error);
            }
        })
        .fail(function(xhr, status, error) {
            console.log('ÔØî Erreur AJAX pour les tambours:', status, error);
        });
}

// Initialisation au chargement de la page
$(document).ready(function() {
    // Mettre ├á jour tous les totaux de feuilles au chargement
    var machines = document.querySelectorAll('[data-index]');
    machines.forEach(function(machine) {
        var machineIndex = machine.getAttribute('data-index');
        updateTotalFeuillesForMachine(machineIndex);
    });
});
</script>
