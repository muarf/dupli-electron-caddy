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
if (isset($array['session_id'])) {
    $session_id = $array['session_id'];
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
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
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
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
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

    .form-control,
    .form-select {
        border-radius: 10px;
        border: 1px solid #ced4da;
        transition: all 0.3s ease;
    }

    .form-control:focus,
    .form-select:focus {
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
        text-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
    }

    .machine-item .panel-body {
        padding: 25px;
        background: #fafafa;
    }

    /* Styles for Enhanced Recap */
    .recap-thumbnail-container {
        width: 100%;
        max-width: 150px;
        margin: 0 auto 15px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        overflow: hidden;
        border: 2px solid white;
        background: #f0f0f0;
    }

    .recap-thumbnail-container img {
        width: 100%;
        height: auto;
        display: block;
    }

    .document-info {
        background: rgba(0,0,0,0.03);
        border-radius: 8px;
        padding: 8px 12px;
        margin-bottom: 15px;
        border-left: 4px solid #337ab7;
    }

    .document-info .doc-name {
        font-weight: bold;
        color: #333;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        display: block;
    }

    .ink-coverage-box {
        margin-top: 15px;
        padding: 10px;
        background: white;
        border-radius: 8px;
        border: 1px solid #eee;
    }

    .ink-bar-label {
        display: flex;
        justify-content: space-between;
        font-size: 11px;
        margin-bottom: 3px;
        color: #666;
    }

    .ink-progress {
        height: 6px;
        border-radius: 3px;
        background: #f0f0f0;
        margin-bottom: 8px;
        overflow: hidden;
    }

    .ink-progress-bar {
        height: 100%;
        border-radius: 3px;
    }

    .ink-c { background: #00ffff; }
    .ink-m { background: #ff00ff; }
    .ink-y { background: #ffff00; }
    .ink-k { background: #000000; }
    .ink-global { background: linear-gradient(90deg, #81c784, #a5d6a7); }
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
                            <strong><i class="fa fa-check-circle"></i>
                                <?php _e('tirage_multimachines.success_message'); ?></strong>
                            <?php _e('tirage_multimachines.success_description'); ?>
                        </div>

                        <!-- Récapitulatif après soumission -->
                        <?php if (isset($contact) && isset($machines) && ($contact != "")): ?>
                            <!-- Script pour sauvegarder les données de la confirmation dans sessionStorage -->
                            <script>
                                (function () {
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
                                <h3 class="text-center"><i class="fa fa-calculator"></i>
                                    <?php _e('tirage_multimachines.summary'); ?></h3>
                                <div class="total-price text-center"><?= number_format($prix_total, 2) ?>
                                    <?php _e('tirage_multimachines.currency'); ?></div>
                                <p class="mb-0 text-center"><?php _e('tirage_multimachines.contact_label'); ?>
                                    <strong><?= htmlspecialchars($contact) ?></strong></p>
                            </div>

                            <div class="row">
                                <?php if (isset($machines) && !empty($machines)): ?>
                                    <?php foreach ($machines as $index => $machine): ?>
                                        <div class="col-md-6 col-lg-4 mb-4">
                                            <div class="machine-card">
                                                <?php if (!empty($machine['thumbnail_url'])): ?>
                                                    <div class="recap-thumbnail-container">
                                                        <img src="<?= htmlspecialchars($machine['thumbnail_url']) ?>" alt="Aperçu">
                                                    </div>
                                                <?php endif; ?>

                                                <h5 class="text-center"><i class="fa fa-print"></i>
                                                    <?php _e('tirage_multimachines.tirage_number_prefix'); ?>                <?= ($index + 1) ?></h5>
                                                
                                                <p class="text-center"><strong><?= ucfirst($machine['type']) ?></strong></p>
                                                
                                                <?php if (!empty($machine['document_name'])): ?>
                                                    <div class="document-info text-center">
                                                        <span class="doc-name"><?= htmlspecialchars($machine['document_name']) ?></span>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="text-center" style="margin-top: 15px;">
                                                    <h3 style="color: #337ab7; margin: 0;">
                                                        <strong><?= number_format($machine['prix'], 2) ?>
                                                            <?php _e('tirage_multimachines.currency'); ?></strong>
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
                                <h3><i class="fa fa-check-circle"></i> <?php _e('tirage_multimachines.confirmation_title'); ?>
                                </h3>
                                <p><strong><?php _e('tirage_multimachines.contact_label'); ?></strong>
                                <?= htmlspecialchars($contact) ?></p>
                            </div>

                        <?php if (isset($machines) && !empty($machines)): ?>
                                <div class="row">
                                <?php
                                $session_total_corrected = 0; // Initialiser le total de la session
                                foreach ($machines as $index => $machine):
                                    ?>
                                        <div class="col-md-6">
                                            <div class="machine-card">
                                                <?php if (!empty($machine['thumbnail_url'])): ?>
                                                    <div class="recap-thumbnail-container">
                                                        <img src="<?= htmlspecialchars($machine['thumbnail_url']) ?>" alt="Aperçu">
                                                    </div>
                                                <?php endif; ?>

                                                <h4 class="text-center"><i class="fa fa-print"></i>
                                                <?php _e('tirage_multimachines.tirage_number_prefix'); ?>            <?= ($index + 1) ?> -
                                                <?= ucfirst($machine['type']) ?></h4>

                                                <?php if (!empty($machine['document_name'])): ?>
                                                    <div class="document-info text-center">
                                                        <span class="doc-name"><?= htmlspecialchars($machine['document_name']) ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            <?php if ($machine['type'] === 'duplicopieur'): ?>
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <h5><i class="fa fa-cogs"></i>
                                                            <?php _e('tirage_multimachines.configuration_title'); ?></h5>
                                                            <ul class="list-unstyled">
                                                                <li><strong><?php _e('tirage_multimachines.masters'); ?> :</strong>
                                                                <?= $machine['nb_masters'] ?? 0 ?></li>
                                                                <li><strong><?php _e('tirage_multimachines.passes'); ?> :</strong>
                                                                <?= $machine['nb_passages'] ?? 0 ?></li>
                                                            <?php if (isset($machine['rv']) && $machine['rv'] == 'oui'): ?>
                                                                    <li><i class="fa fa-check text-success"></i>
                                                                    <?php _e('tirage_multimachines.recto_verso_enabled'); ?></li>
                                                            <?php endif; ?>
                                                            <?php if (isset($machine['A4']) && $machine['A4'] == 'A4'): ?>
                                                                    <li><i class="fa fa-check text-success"></i>
                                                                    <?php _e('tirage_multimachines.format_a4_enabled'); ?></li>
                                                            <?php else: ?>
                                                                    <li><i class="fa fa-check text-info"></i>
                                                                    <?php _e('tirage_multimachines.format_a3_enabled'); ?></li>
                                                            <?php endif; ?>
                                                            <?php if (isset($machine['feuilles_payees']) && $machine['feuilles_payees'] == 'oui'): ?>
                                                                    <li><i class="fa fa-check text-warning"></i>
                                                                    <?php _e('tirage_multimachines.sheets_already_paid'); ?></li>
                                                            <?php endif; ?>
                                                            </ul>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <h5><i class="fa fa-euro"></i> <?php _e('tirage_multimachines.cost_details'); ?>
                                                            </h5>
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
                                                                <li><strong><?php _e('tirage_multimachines.masters'); ?> :</strong>
                                                                <?= $nb_masters ?> × <?= number_format($prix_master, 4) ?>
                                                                <?php _e('tirage_multimachines.currency'); ?> =
                                                                <?= number_format($cout_masters, 2) ?>
                                                                <?php _e('tirage_multimachines.currency'); ?></li>
                                                                <li><strong><?php _e('tirage_multimachines.passes'); ?> :</strong>
                                                                <?= $nb_passages ?> × <?= number_format($prix_passage, 4) ?>
                                                                <?php _e('tirage_multimachines.currency'); ?> =
                                                                <?= number_format($cout_passages, 2) ?>
                                                                <?php _e('tirage_multimachines.currency'); ?></li>
                                                                <li><strong><?php _e('tirage_multimachines.paper'); ?> :</strong>
                                                                <?= $nb_f ?>                 <?php _e('tirage_multimachines.sheets'); ?> ×
                                                                <?= number_format($prix_papier, 3) ?>
                                                                <?php _e('tirage_multimachines.currency'); ?> =
                                                                <?= number_format($cout_papier, 2) ?>
                                                                <?php _e('tirage_multimachines.currency'); ?></li>
                                                            <?php if (isset($machine['rv']) && $machine['rv'] == 'oui'): ?>
                                                                    <li><i class="fa fa-info-circle text-info"></i>
                                                                    <?php _e('tirage_multimachines.recto_verso'); ?> :
                                                                    <?php _e('tirage_multimachines.paper_divided_by_2'); ?></li>
                                                            <?php endif; ?>
                                                            <?php if (isset($machine['feuilles_payees']) && $machine['feuilles_payees'] == 'oui'): ?>
                                                                    <li><i class="fa fa-check text-warning"></i>
                                                                    <?php _e('tirage_multimachines.sheets_already_paid'); ?> :
                                                                    <?php _e('tirage_multimachines.free_paper'); ?></li>
                                                            <?php endif; ?>
                                                            <?php if ($taille === 'A4'): ?>
                                                                    <li><i class="fa fa-info-circle text-info"></i>
                                                                    <?php _e('tirage_multimachines.format_a4_masters_passes_divided'); ?>
                                                                    </li>
                                                            <?php endif; ?>
                                                            </ul>
                                                        </div>
                                                    </div>
                                            <?php else: ?>
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <h5><i class="fa fa-print"></i>
                                                            <?php _e('tirage_multimachines.machine_title'); ?></h5>
                                                            <p><strong><?= htmlspecialchars($machine['machine']) ?></strong></p>

                                                        <?php if (isset($machine['brochures']) && is_array($machine['brochures'])): ?>
                                                                <h5><i class="fa fa-file-text"></i>
                                                                <?php _e('tirage_multimachines.brochures'); ?></h5>
                                                            <?php foreach ($machine['brochures'] as $brochure_index => $brochure): ?>
                                                                    <div class="well well-sm">
                                                                        <strong><?php _e('tirage_multimachines.brochure'); ?>
                                                                            #<?= ($brochure_index + 1) ?></strong><br>
                                                                        • <?= $brochure['nb_exemplaires'] ?>
                                                                    <?php _e('tirage_multimachines.exemplaires'); ?><br>
                                                                        • <?= $brochure['nb_feuilles'] ?>
                                                                    <?php _e('tirage_multimachines.feuilles_per_exemplaire'); ?><br>
                                                                        • <?php _e('tirage_multimachines.format'); ?> :
                                                                    <?= $brochure['taille'] ?><br>
                                                                    <?php if (isset($brochure['rv']) && $brochure['rv'] == 'oui'): ?>
                                                                            • <i class="fa fa-check text-success"></i>
                                                                        <?php _e('tirage_multimachines.recto_verso'); ?><br>
                                                                    <?php endif; ?>
                                                                    <?php if (isset($brochure['couleur']) && $brochure['couleur'] == 'oui'): ?>
                                                                            • <i class="fa fa-check text-success"></i>
                                                                        <?php _e('tirage_multimachines.color'); ?><br>
                                                                    <?php endif; ?>
                                                                    <?php if (isset($brochure['feuilles_payees']) && $brochure['feuilles_payees'] == 'oui'): ?>
                                                                            • <i class="fa fa-check text-warning"></i>
                                                                        <?php _e('tirage_multimachines.sheets_paid'); ?><br>
                                                                    <?php endif; ?>
                                                                    </div>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <h5><i class="fa fa-euro"></i> <?php _e('tirage_multimachines.cost_details'); ?></h5>
                                                            <ul class="list-unstyled">
                                                            <?php if (isset($machine['breakdown'])): 
                                                                $b = $machine['breakdown'];
                                                            ?>
                                                                <li><strong><?php _e('tirage_multimachines.paper_label'); ?> :</strong>
                                                                    <?= $b['nb_pages_papier'] ?> <?php _e('tirage_multimachines.sheets'); ?> ×
                                                                    <?= number_format($b['prix_papier_unite'], 3) ?> <?php _e('tirage_multimachines.currency'); ?> =
                                                                    <?= number_format($b['papier'], 2) ?> <?php _e('tirage_multimachines.currency'); ?>
                                                                </li>
                                                                <li><strong><?php _e('tirage_multimachines.ink_toner_label'); ?> :</strong>
                                                                    <?= $b['nb_pages_encre'] ?> <?php _e('tirage_multimachines.pages'); ?> ×
                                                                    <?= number_format($b['prix_encre_page'], 4) ?> <?php _e('tirage_multimachines.currency'); ?> =
                                                                    <?= number_format($b['total_encre'], 2) ?> <?php _e('tirage_multimachines.currency'); ?>
                                                                </li>
                                                                <li><strong><?php _e('tirage_multimachines.total'); ?> :</strong>
                                                                    <?= number_format($b['total'], 2) ?> <?php _e('tirage_multimachines.currency'); ?>
                                                                </li>

                                                                <?php if (isset($machine['brochures'])): ?>
                                                                    <?php foreach ($machine['brochures'] as $brochure_index => $brochure): ?>
                                                                        <?php if (!empty($brochure['rv']) && $brochure['rv'] == 'oui'): ?>
                                                                            <li><i class="fa fa-info-circle text-info"></i>
                                                                                <?php _e('tirage_multimachines.brochure_number'); ?> <?= ($brochure_index + 1) ?> : 
                                                                                <?php _e('tirage_multimachines.recto_verso_double_ink'); ?>
                                                                            </li>
                                                                        <?php endif; ?>
                                                                        <?php if (!empty($brochure['taille']) && $brochure['taille'] === 'A4'): ?>
                                                                            <li><i class="fa fa-info-circle text-info"></i>
                                                                                <?php _e('tirage_multimachines.brochure_number'); ?> <?= ($brochure_index + 1) ?> : 
                                                                                <?php _e('tirage_multimachines.format_a4_ink_divided'); ?>
                                                                            </li>
                                                                        <?php endif; ?>
                                                                    <?php endforeach; ?>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <li class="text-danger">Erreur: Détails du prix non calculés.</li>
                                                            <?php endif; ?>
                                                            </ul>
                                                        </div>
                                                        </div>
                                                    </div>
                                            <?php endif; ?>

                                                <div class="text-center"
                                                    style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                                                    
                                                    <?php if (isset($machine['breakdown'])): 
                                                        $b = $machine['breakdown'];
                                                    ?>
                                                        <div class="ink-coverage-box text-left" style="margin-top: 20px;">
                                                            <div class="ink-bar-label">
                                                                <span><strong><i class="fa fa-tint"></i> Couverture d'encre: <?= round($b['fr_percent'], 1) ?>%</strong></span>
                                                                <span class="label label-primary">x<?= number_format($b['multiplier'], 2) ?> sur couleurs</span>
                                                            </div>
                                                            <div class="ink-progress">
                                                                <div class="ink-progress-bar ink-global" style="width: <?= min(100, $b['fr_percent']) ?>%"></div>
                                                            </div>

                                                            <table class="table table-condensed" style="margin-bottom: 0; font-size: 11px; margin-top: 10px;">
                                                                <tr>
                                                                    <td>Papier</td>
                                                                    <td class="text-right"><?= number_format($b['papier'], 2) ?> €</td>
                                                                </tr>
                                                                <tr>
                                                                    <td>Noir & Composants (Fixe)</td>
                                                                    <td class="text-right"><?= number_format($b['noir'], 2) ?> €</td>
                                                                </tr>
                                                                <?php if ($b['is_color']): ?>
                                                                <tr class="info">
                                                                    <td>Couleurs (Ajusté x<?= number_format($b['multiplier'], 2) ?>)</td>
                                                                    <td class="text-right"><?= number_format($b['couleurs'], 2) ?> €</td>
                                                                </tr>
                                                                <?php endif; ?>
                                                                <tr style="border-top: 2px solid #ddd; font-weight: bold;">
                                                                    <td>Total cette machine</td>
                                                                    <td class="text-right"><?= number_format($b['papier'] + $b['noir'] + $b['couleurs'], 2) ?> €</td>
                                                                </tr>
                                                            </table>
                                                        </div>
                                                    <?php elseif (isset($machine['fill_rate'])): 
                                                        $fr = floatval($machine['fill_rate']);
                                                        $fr_percent = ($fr <= 1.0) ? ($fr * 100) : $fr;
                                                    ?>
                                                        <div class="ink-coverage-box text-left">
                                                            <div class="ink-bar-label">
                                                                <span><strong><i class="fa fa-tint"></i> Couverture d'encre estimée</strong></span>
                                                                <span><?= round($fr_percent, 1) ?>%</span>
                                                            </div>
                                                            <div class="ink-progress">
                                                                <div class="ink-progress-bar ink-global" style="width: <?= min(100, $fr_percent) ?>%"></div>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>

                                                    <h4 class="text-primary">
                                                        <i class="fa fa-euro"></i>
                                                        <strong><?= number_format($b['total'] ?? 0, 2) ?>
                                                        <?php _e('tirage_multimachines.currency'); ?></strong>
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
                                    <strong><?= number_format($prix_total, 2) ?>
                                    <?php _e('tirage_multimachines.currency'); ?></strong>
                                </h2>
                            </div>
                    <?php endif; ?>

                        <!-- Formulaire d'enregistrement -->
                        <form class="" action="" method="post" id="form-enregistrement"
                            onsubmit="console.log('Formulaire soumis !'); return true;">
                            <fieldset>

                                <!-- Champs cachés -->
                                <input type="hidden" value="<?php echo $contact; ?>" name="contact" />
                                <?php if (isset($session_id) && $session_id): ?>
                                    <input type="hidden" name="session_id" value="<?= $session_id ?>" />
                                <?php endif; ?>
                            <?php foreach ($machines as $index => $machine): ?>
                                    <input type="hidden" name="machines[<?= $index ?>][type]" value="<?= $machine['type'] ?>" />
                                    <input type="hidden" name="machines[<?= $index ?>][contact]"
                                        value="<?= isset($machine['contact']) && !empty($machine['contact']) ? $machine['contact'] : $contact ?>" />

                                    <!-- FIX: Relayer les IDs pour éviter les doublons -->
                                    <input type="hidden" name="machines[<?= $index ?>][db_id]"
                                        value="<?= isset($machine['db_id']) ? $machine['db_id'] : '' ?>" />
                                    <input type="hidden" name="machines[<?= $index ?>][job_id]"
                                        value="<?= isset($machine['job_id']) ? $machine['job_id'] : '' ?>" />
                                    <input type="hidden" name="machines[<?= $index ?>][thumbnail_url]"
                                        value="<?= isset($machine['thumbnail_url']) ? $machine['thumbnail_url'] : '' ?>" />
                                    <input type="hidden" name="machines[<?= $index ?>][document_name]"
                                        value="<?= isset($machine['document_name']) ? $machine['document_name'] : '' ?>" />
                                    <input type="hidden" name="machines[<?= $index ?>][fill_rate]"
                                        value="<?= isset($machine['fill_rate']) ? $machine['fill_rate'] : '' ?>" />

                                <?php if ($machine['type'] === 'duplicopieur'): ?>
                                        <input type="hidden" name="machines[<?= $index ?>][nb_masters]"
                                            value="<?= $machine['nb_masters'] ?>" />
                                        <input type="hidden" name="machines[<?= $index ?>][nb_passages]"
                                            value="<?= $machine['nb_passages'] ?>" />
                                        <input type="hidden" name="machines[<?= $index ?>][master_av]"
                                            value="<?= $machine['master_av'] ?>" />
                                        <input type="hidden" name="machines[<?= $index ?>][master_ap]"
                                            value="<?= $machine['master_ap'] ?>" />
                                        <input type="hidden" name="machines[<?= $index ?>][passage_av]"
                                            value="<?= $machine['passage_av'] ?>" />
                                        <input type="hidden" name="machines[<?= $index ?>][passage_ap]"
                                            value="<?= $machine['passage_ap'] ?>" />
                                        <input type="hidden" name="machines[<?= $index ?>][prix]" value="<?= $machine['prix'] ?>" />
                                        <input type="hidden" name="machines[<?= $index ?>][rv]"
                                            value="<?= isset($machine['rv']) ? $machine['rv'] : 'non' ?>" />
                                        <input type="hidden" name="machines[<?= $index ?>][feuilles_payees]"
                                            value="<?= isset($machine['feuilles_payees']) ? $machine['feuilles_payees'] : 'non' ?>" />
                                        <input type="hidden" name="machines[<?= $index ?>][A4]"
                                            value="<?= isset($machine['A4']) ? $machine['A4'] : 'non' ?>" />
                                    <?php if (isset($machine['duplicopieur_id'])): ?>
                                            <input type="hidden" name="machines[<?= $index ?>][duplicopieur_id]"
                                                value="<?= $machine['duplicopieur_id'] ?>" />
                                    <?php endif; ?>
                                    <?php if (isset($machine['tambour'])): ?>
                                            <input type="hidden" name="machines[<?= $index ?>][tambour]"
                                                value="<?= $machine['tambour'] ?>" />
                                    <?php endif; ?>
                                <?php else: ?>
                                        <input type="hidden" name="machines[<?= $index ?>][machine]"
                                            value="<?= $machine['machine'] ?>" />
                                        <input type="hidden" name="machines[<?= $index ?>][fill_rate]"
                                            value="<?= isset($machine['fill_rate']) ? htmlspecialchars($machine['fill_rate']) : '0.5' ?>" />
                                    <?php if (isset($machine['brochures'])): ?>
                                        <?php foreach ($machine['brochures'] as $brochureIndex => $brochure): ?>
                                                <input type="hidden"
                                                    name="machines[<?= $index ?>][brochures][<?= $brochureIndex ?>][nb_exemplaires]"
                                                    value="<?= $brochure['nb_exemplaires'] ?>" />
                                                <input type="hidden"
                                                    name="machines[<?= $index ?>][brochures][<?= $brochureIndex ?>][nb_feuilles]"
                                                    value="<?= $brochure['nb_feuilles'] ?>" />
                                            <?php if (isset($brochure['nb_pages'])): ?>
                                                    <input type="hidden" name="machines[<?= $index ?>][brochures][<?= $brochureIndex ?>][nb_pages]"
                                                        value="<?= $brochure['nb_pages'] ?>" />
                                            <?php endif; ?>
                                                <input type="hidden" name="machines[<?= $index ?>][brochures][<?= $brochureIndex ?>][taille]"
                                                    value="<?= $brochure['taille'] ?>" />
                                                <input type="hidden" name="machines[<?= $index ?>][brochures][<?= $brochureIndex ?>][rv]"
                                                    value="<?= isset($brochure['rv']) ? $brochure['rv'] : 'non' ?>" />
                                                <input type="hidden" name="machines[<?= $index ?>][brochures][<?= $brochureIndex ?>][couleur]"
                                                    value="<?= isset($brochure['couleur']) ? $brochure['couleur'] : 'non' ?>" />
                                                <input type="hidden"
                                                    name="machines[<?= $index ?>][brochures][<?= $brochureIndex ?>][feuilles_payees]"
                                                    value="<?= isset($brochure['feuilles_payees']) ? $brochure['feuilles_payees'] : 'non' ?>" />
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                        <input type="hidden" name="machines[<?= $index ?>][prix]"
                                            value="<?= $machine_total_corrected ?>" />
                                <?php endif; ?>
                            <?php endforeach; ?>

                                <!-- Champ "As-tu payé" -->
                                <div class="clearfix"></div>
                                <div class="row" style="margin-top: 20px;">
                                    <div class="col-xs-12">
                                        <div class="form-group">
                                            <label for="payeoui"><strong><?php _e('tirage_multimachines.have_you_paid'); ?></strong></label><br>
                                            <label class="radio-inline">
                                                <input type="radio" name="paye" value="oui" id="payeoui"
                                                    onchange="updatePaymentAmount()"> <?php _e('tirage_multimachines.yes'); ?>
                                            </label>
                                            <label class="radio-inline">
                                                <input type="radio" name="paye" value="non" id="payenon"
                                                    onchange="updatePaymentAmount()" checked> <?php _e('tirage_multimachines.no'); ?>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Champ montant -->
                                <div class="row">
                                    <div class="col-xs-12 col-md-6">
                                        <div class="form-group">
                                            <label for="cb1"><?php _e('tirage_multimachines.amount_paid'); ?></label>
                                            <input id="cb1" name="cb" class="form-control" type="number" step="0.01"
                                                min="0" placeholder="0.00">
                                            <span class="help-block"><?php _e('tirage_multimachines.amount_in_euros'); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Champ "Un petit mot" -->
                                <div class="row">
                                    <div class="col-xs-12 col-md-6">
                                        <div class="form-group">
                                            <label for="mot"><?php _e('tirage_multimachines.message_placeholder'); ?></label>
                                            <textarea id="mot" name="mot" class="form-control"></textarea>
                                        </div>
                                    </div>
                                </div>

                                <hr>
                                <div class="section">
                                    <div class="container">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <button type="button" id="btn-retour" class="btn btn-warning btn-block"
                                                    onclick="returnToForm()">
                                                    <i class="fa fa-arrow-left"></i> <?php _e('tirage_multimachines.back_to_form'); ?>
                                                </button>
                                            </div>
                                            <div class="col-md-6">
                                                <button id="singlebutton" name="enregistrer" value="1"
                                                    class="btn btn-success btn-block"><?php _e('tirage_multimachines.save_btn'); ?></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </fieldset>
                        </form>

                        <!-- Modale de confirmation de sortie du formulaire de paiement -->
                        <div id="confirmLeaveModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 9999;">
                            <div class="modal-overlay" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5);"></div>
                            <div class="modal-dialog" style="position: relative; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 12px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); max-width: 500px; width: 90%; z-index: 10000;">
                                <h3 style="margin-top: 0; color: #f39c12;">
                                    <i class="fa fa-exclamation-triangle"></i> Attention
                                </h3>
                                <p>Vous êtes sur le formulaire de paiement final.</p>
                                <p><strong>Si vous quittez maintenant, les informations saisies seront perdues.</strong></p>
                                <p>Voulez-vous vraiment annuler et quitter cette page ?</p>
                                
                                <div style="margin-top: 25px; text-align: right;">
                                    <button id="btnStay" class="btn btn-primary" style="margin-right: 10px;">
                                        <i class="fa fa-check"></i> Rester sur la page
                                    </button>
                                    <button id="btnLeave" class="btn btn-danger">
                                        <i class="fa fa-times"></i> Annuler et quitter
                                    </button>
                                </div>
                            </div>
                        </div>

                <?php
                    } else {
                        ?>
                    <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <strong>Erreurs détectées :</strong>
                                <ul>
                                <?php foreach ($errors as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                                </ul>
                            </div>
                    <?php endif; ?>

                    <?php if (!empty($success_message)): ?>
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
                                    <label class="col-md-4 control-label"
                                        for="contact"><?php _e('tirage_multimachines.contact'); ?></label>
                                    <div class="col-md-4">
                                        <input id="contact" name="contact" <?= !empty($contact) ? 'value="' . $contact . '"' : 'placeholder="me@example.com"'; ?> class="form-control input-md" required
                                            type="text">
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
                                                <i class="fa fa-plus-circle"></i>
                                            <?php _e('tirage_multimachines.add_tirage'); ?>
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
                                    <p class="text-center"><strong><?php _e('tirage_multimachines.total_price'); ?> <span
                                                id="prix-total">0.00€</span></strong></p>
                                </div>

                                <!-- Bouton suivant -->

                            </fieldset>
                        </form>

                        <!-- Formulaire d'enregistrement -->
                        <form class="form-horizontal" action="" method="post">
                            <fieldset>

                                <!-- Champs cachés -->
                                <input type="hidden" value="<?php echo $contact; ?>" name="contact" />
                                <input type="hidden" value="ok" name="ok" />
                            <?php foreach ($machines as $index => $machine): ?>
                                    <input type="hidden" name="machines[<?= $index ?>][type]" value="<?= $machine['type'] ?>" />
                                <?php if ($machine['type'] === 'duplicopieur'): ?>
                                        <input type="hidden" name="machines[<?= $index ?>][nb_masters]"
                                            value="<?= $machine['nb_masters'] ?>" />
                                        <input type="hidden" name="machines[<?= $index ?>][nb_passages]"
                                            value="<?= $machine['nb_passages'] ?>" />
                                        <input type="hidden" name="machines[<?= $index ?>][master_av]"
                                            value="<?= $machine['master_av'] ?>" />
                                        <input type="hidden" name="machines[<?= $index ?>][master_ap]"
                                            value="<?= $machine['master_ap'] ?>" />
                                        <input type="hidden" name="machines[<?= $index ?>][passage_av]"
                                            value="<?= $machine['passage_av'] ?>" />
                                        <input type="hidden" name="machines[<?= $index ?>][passage_ap]"
                                            value="<?= $machine['passage_ap'] ?>" />
                                        <input type="hidden" name="machines[<?= $index ?>][prix]" value="<?= $machine['prix'] ?>" />
                                        <input type="hidden" name="machines[<?= $index ?>][rv]"
                                            value="<?= isset($machine['rv']) ? $machine['rv'] : 'non' ?>" />
                                        <input type="hidden" name="machines[<?= $index ?>][feuilles_payees]"
                                            value="<?= isset($machine['feuilles_payees']) ? $machine['feuilles_payees'] : 'non' ?>" />
                                        <input type="hidden" name="machines[<?= $index ?>][A4]"
                                            value="<?= isset($machine['A4']) ? $machine['A4'] : 'non' ?>" />
                                    <?php if (isset($machine['duplicopieur_id'])): ?>
                                            <input type="hidden" name="machines[<?= $index ?>][duplicopieur_id]"
                                                value="<?= $machine['duplicopieur_id'] ?>" />
                                    <?php endif; ?>
                                    <?php if (isset($machine['tambour'])): ?>
                                            <input type="hidden" name="machines[<?= $index ?>][tambour]"
                                                value="<?= $machine['tambour'] ?>" />
                                    <?php endif; ?>
                                <?php else: ?>
                                        <input type="hidden" name="machines[<?= $index ?>][machine]"
                                            value="<?= $machine['machine'] ?>" />
                                        <input type="hidden" name="machines[<?= $index ?>][fill_rate]"
                                            value="<?= isset($machine['fill_rate']) ? htmlspecialchars($machine['fill_rate']) : '0.5' ?>" />
                                        <input type="hidden" name="machines[<?= $index ?>][prix]" value="<?= $machine['prix'] ?>" />
                                        <input type="hidden" name="machines[<?= $index ?>][rv]"
                                            value="<?= isset($machine['rv']) ? $machine['rv'] : 'non' ?>" />
                                        <input type="hidden" name="machines[<?= $index ?>][feuilles_payees]"
                                            value="<?= isset($machine['feuilles_payees']) ? $machine['feuilles_payees'] : 'non' ?>" />
                                        <input type="hidden" name="machines[<?= $index ?>][A4]"
                                            value="<?= isset($machine['A4']) ? $machine['A4'] : 'non' ?>" />
                                    <?php if (isset($machine['brochures'])): ?>
                                        <?php foreach ($machine['brochures'] as $brochureIndex => $brochure): ?>
                                                <input type="hidden"
                                                    name="machines[<?= $index ?>][brochures][<?= $brochureIndex ?>][nb_exemplaires]"
                                                    value="<?= $brochure['nb_exemplaires'] ?>" />
                                                <input type="hidden"
                                                    name="machines[<?= $index ?>][brochures][<?= $brochureIndex ?>][nb_feuilles]"
                                                    value="<?= $brochure['nb_feuilles'] ?>" />
                                                <input type="hidden" name="machines[<?= $index ?>][brochures][<?= $brochureIndex ?>][taille]"
                                                    value="<?= $brochure['taille'] ?>" />
                                                <input type="hidden" name="machines[<?= $index ?>][brochures][<?= $brochureIndex ?>][rv]"
                                                    value="<?= $brochure['rv'] ? 'oui' : 'non' ?>" />
                                                <input type="hidden" name="machines[<?= $index ?>][brochures][<?= $brochureIndex ?>][couleur]"
                                                    value="<?= $brochure['couleur'] ? 'oui' : 'non' ?>" />
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>

                                <!-- Champs communs -->

                        <?php } ?>

                        <script>
                            const CONFIG = <?= json_encode([
                                'prix_data' => $prix_data ?? [],
                                'duplicopieur_id' => $duplicopieur_selectionne['id'] ?? '',
                                'machine_price_mappings' => $machine_price_mappings ?? [],
                                'strings' => [
                                    'calculation_detail' => __js('tirage_multimachines.calculation_detail'),
                                ],
                            ]) ?>;
                        </script>
                        <script src="js/tirage-multimachines.js" defer></script>

            </div>
        </div>
    </div>
</div>
</div>




