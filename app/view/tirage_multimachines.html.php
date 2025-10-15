<?php
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
                    <h1><i class="fa fa-print"></i> Multi-Tirages</h1>
                    <p>Gérez facilement vos tirages sur plusieurs machines</p>
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
        <h4>Debug complet:</h4>
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
        <strong><i class="fa fa-check-circle"></i> Succès!</strong> Votre tirage multi-machines a été enregistré !
    </div>
    
    <!-- Récapitulatif après soumission -->
    <?php if (isset($contact) && isset($machines) && ($contact != "")): ?>
    <div class="summary-card">
        <h3 class="text-center"><i class="fa fa-calculator"></i> Récapitulatif du tirage</h3>
        <div class="total-price text-center"><?= number_format($prix_total, 2) ?>€</div>
        <p class="mb-0 text-center">Contact: <strong><?= htmlspecialchars($contact) ?></strong></p>
    </div>
    
            <div class="row">
        <?php if (isset($machines) && !empty($machines)): ?>
            <?php foreach ($machines as $index => $machine): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="machine-card">
                        <h5 class="text-center"><i class="fa fa-print"></i> Tirage #<?= ($index + 1) ?></h5>
                        <p class="text-center"><strong><?= ucfirst($machine['type']) ?></strong></p>
                        <div class="text-center" style="margin-top: 15px;">
                            <h3 style="color: #337ab7; margin: 0;">
                                <strong><?= number_format($machine['prix'], 2) ?>€</strong>
                            </h3>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <div class="alert-modern alert alert-warning">
        <strong><i class="fa fa-exclamation-triangle"></i> Attention!</strong> Ni les locaux, ni l'entretien des machines ne sont comptés dans ce chiffre. Merci de donner un peu plus que demandé, si possible
    </div>
    <?php endif; ?>
    
    <div class="text-center">
        <a href="?accueil" class="btn btn-modern btn-success-modern btn-lg">
            <i class="fa fa-home"></i> Retour à l'accueil
        </a>
    </div>
    <?php 
} else if (isset($_POST['contact']) && isset($_POST['ok'])) {
    ?>
    <!-- Page de confirmation améliorée -->
    <div class="alert-modern alert alert-success">
        <h3><i class="fa fa-check-circle"></i> Confirmation de votre tirage multi-machines</h3>
        <p><strong>Contact :</strong> <?= htmlspecialchars($contact) ?></p>
    </div>
    
    <?php if (isset($machines) && !empty($machines)): ?>
        <div class="row">
            <?php foreach ($machines as $index => $machine): ?>
                <div class="col-md-6">
                    <div class="machine-card">
                        <h4 class="text-center"><i class="fa fa-print"></i> Tirage #<?= ($index + 1) ?> - <?= ucfirst($machine['type']) ?></h4>
                            <?php if ($machine['type'] === 'duplicopieur'): ?>
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5><i class="fa fa-cogs"></i> Configuration</h5>
                                        <ul class="list-unstyled">
                                            <li><strong>Masters :</strong> <?= $machine['nb_masters'] ?? 0 ?></li>
                                            <li><strong>Passages :</strong> <?= $machine['nb_passages'] ?? 0 ?></li>
                                            <?php if (isset($machine['rv']) && $machine['rv'] == 'oui'): ?>
                                                <li><i class="fa fa-check text-success"></i> Recto/Verso</li>
                                            <?php endif; ?>
                                            <?php if (isset($machine['A4']) && $machine['A4'] == 'A4'): ?>
                                                <li><i class="fa fa-check text-success"></i> Format A4</li>
                                            <?php else: ?>
                                                <li><i class="fa fa-check text-info"></i> Format A3</li>
                                            <?php endif; ?>
                                            <?php if (isset($machine['feuilles_payees']) && $machine['feuilles_payees'] == 'oui'): ?>
                                                <li><i class="fa fa-check text-warning"></i> Feuilles déjà payées</li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h5><i class="fa fa-euro"></i> Détail des coûts</h5>
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
                                            <li><strong>Masters :</strong> <?= $nb_masters ?> × <?= number_format($prix_master, 4) ?>€ = <?= number_format($cout_masters, 2) ?>€</li>
                                            <li><strong>Passages :</strong> <?= $nb_passages ?> × <?= number_format($prix_passage, 4) ?>€ = <?= number_format($cout_passages, 2) ?>€</li>
                                            <li><strong>Papier :</strong> <?= $nb_f ?> feuilles × <?= number_format($prix_papier, 3) ?>€ = <?= number_format($cout_papier, 2) ?>€</li>
                                            <?php if (isset($machine['rv']) && $machine['rv'] == 'oui'): ?>
                                                <li><i class="fa fa-info-circle text-info"></i> Recto/Verso : Papier divisé par 2</li>
                                            <?php endif; ?>
                                            <?php if (isset($machine['feuilles_payees']) && $machine['feuilles_payees'] == 'oui'): ?>
                                                <li><i class="fa fa-check text-warning"></i> Feuilles déjà payées : Papier gratuit</li>
                                            <?php endif; ?>
                                            <?php if ($taille === 'A4'): ?>
                                                <li><i class="fa fa-info-circle text-info"></i> Format A4 : Masters et passages divisés par 2</li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5><i class="fa fa-print"></i> Machine</h5>
                                        <p><strong><?= htmlspecialchars($machine['machine']) ?></strong></p>
                                        
                                        <?php if (isset($machine['brochures']) && is_array($machine['brochures'])): ?>
                                            <h5><i class="fa fa-file-text"></i> Brochures</h5>
                                            <?php foreach ($machine['brochures'] as $brochure_index => $brochure): ?>
                                                <div class="well well-sm">
                                                    <strong>Brochure #<?= ($brochure_index + 1) ?></strong><br>
                                                    • <?= $brochure['nb_exemplaires'] ?> exemplaires<br>
                                                    • <?= $brochure['nb_feuilles'] ?> feuilles/exemplaire<br>
                                                    • Format : <?= $brochure['taille'] ?><br>
                                                    <?php if (isset($brochure['rv']) && $brochure['rv'] == 'oui'): ?>
                                                        • <i class="fa fa-check text-success"></i> Recto/Verso<br>
                                                    <?php endif; ?>
                                                    <?php if (isset($brochure['couleur']) && $brochure['couleur'] == 'oui'): ?>
                                                        • <i class="fa fa-check text-success"></i> Couleur<br>
                                                    <?php endif; ?>
                                                    <?php if (isset($brochure['feuilles_payees']) && $brochure['feuilles_payees'] == 'oui'): ?>
                                                        • <i class="fa fa-check text-warning"></i> Feuilles payées<br>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <h5><i class="fa fa-euro"></i> Détail des coûts</h5>
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
                                                        
                                                        // Déterminer la clé de la machine
                                                        $machine_key = null;
                                                        if (strtolower($machine_name) === 'comcolor') {
                                                            $machine_key = 'photocop_1';
                                                        } elseif (strtolower($machine_name) === 'konika') {
                                                            $machine_key = 'photocop_4';
                                                        } else {
                                                            // Chercher la première clé photocop_ disponible
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
                                                                    $prix_encre_brochure += ($machine_prices['bleue']['unite'] ?? 0);
                                                                    $prix_encre_brochure += ($machine_prices['couleur']['unite'] ?? 0);
                                                                    $prix_encre_brochure += ($machine_prices['jaune']['unite'] ?? 0);
                                                                    $prix_encre_brochure += ($machine_prices['noire']['unite'] ?? 0);
                                                                    $prix_encre_brochure += ($machine_prices['rouge']['unite'] ?? 0);
                                                                } else {
                                                                    $prix_encre_brochure += ($machine_prices['noire']['unite'] ?? 0);
                                                                }
                                                            } else {
                                                                // Photocopieur à toner
                                                                if ($couleur) {
                                                                    $prix_encre_brochure += ($machine_prices['cyan']['unite'] ?? 0);
                                                                    $prix_encre_brochure += ($machine_prices['jaune']['unite'] ?? 0);
                                                                    $prix_encre_brochure += ($machine_prices['magenta']['unite'] ?? 0);
                                                                    $prix_encre_brochure += ($machine_prices['noir']['unite'] ?? 0);
                                                                    $prix_encre_brochure += ($machine_prices['tambour']['unite'] ?? 0);
                                                                    $prix_encre_brochure += ($machine_prices['dev']['unite'] ?? 0);
                                                                } else {
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
                                            <li><strong>Papier :</strong> <?= $total_pages ?> pages × <?= number_format($prix_papier, 3) ?>€ = <?= number_format($total_papier, 2) ?>€</li>
                                            <li><strong>Encre/Toner :</strong> <?= $total_pages_encre ?> pages × <?= number_format($prix_encre, 4) ?>€ = <?= number_format($total_encre, 2) ?>€</li>
                                            <li><strong>Total :</strong> <?= number_format($machine['prix'], 2) ?>€</li>
                                            <?php if (isset($machine['brochures']) && is_array($machine['brochures'])): ?>
                                                <?php foreach ($machine['brochures'] as $brochure_index => $brochure): ?>
                                                    <?php if (!empty($brochure['rv']) && $brochure['rv'] == 'oui'): ?>
                                                        <li><i class="fa fa-info-circle text-info"></i> Brochure #<?= ($brochure_index + 1) ?> : Recto/Verso (double encre)</li>
                                                    <?php endif; ?>
                                                    <?php if (!empty($brochure['feuilles_payees']) && $brochure['feuilles_payees'] == 'oui'): ?>
                                                        <li><i class="fa fa-check text-warning"></i> Brochure #<?= ($brochure_index + 1) ?> : Feuilles déjà payées</li>
                                                    <?php endif; ?>
                                                    <?php if (!empty($brochure['taille']) && $brochure['taille'] === 'A4'): ?>
                                                        <li><i class="fa fa-info-circle text-info"></i> Brochure #<?= ($brochure_index + 1) ?> : Format A4 (encre divisée par 2)</li>
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
                                    <strong><?= number_format($machine['prix'], 2) ?>€</strong>
                                </h4>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="alert alert-info text-center">
            <h3><i class="fa fa-calculator"></i> TOTAL GLOBAL</h3>
            <h2 class="text-primary">
                <strong><?= number_format($prix_total, 2) ?>€</strong>
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
                        <div class="col-md-12"><button id="singlebutton" name="enregistrer" value="1" class="btn btn-success btn-block">Enregistrer !</button></div>
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
        <legend class="text-center">Formulaire Multi-Tirages</legend>
        
        <!-- Contact -->
        <div class="form-group">
            <label class="col-md-4 control-label" for="contact">Contact</label>  
            <div class="col-md-4">
                <input id="contact" name="contact" <?= !empty($contact) ? 'value="'.$contact.'"' : 'placeholder="me@example.com"';?> class="form-control input-md" required type="text">
                <span class="help-block">Un mail, pseudo explicite</span>
            </div>
        </div>
        
        <!-- Machines -->
        <div id="machines-container">
            <h4 class="text-center">Tirages</h4>
            
            <!-- Machine par défaut -->
            <div class="machine-item panel panel-primary" data-index="0">
                <!-- Header cliquable avec preview -->
                <div class="panel-heading" style="cursor: pointer;">
                    <div class="row" onclick="toggleMachinePanel(0)">
                        <div class="col-xs-8 col-sm-9">
                            <h4 class="panel-title" style="margin: 0;">
                                <i class="fa fa-chevron-down toggle-icon" id="toggle-icon-0"></i>
                                <strong>Tirage #1</strong>
                                <span class="machine-type-badge badge" id="type-badge-0">Duplicopieur</span>
                            </h4>
                        </div>
                        <div class="col-xs-4 col-sm-3 text-right">
                            <span class="machine-price-preview" id="price-preview-0">0.00€</span>
                        </div>
                    </div>
                </div>
                
                <!-- Corps du panel (pliable) -->
                <div class="panel-body machine-content" id="machine-content-0" style="padding: 20px;">
                
                <!-- Type de machine - Système d'onglets -->
                <div class="form-group">
                    <div class="col-md-12">
                        <ul class="nav nav-tabs" role="tablist" style="margin-bottom: 20px;">
                            <li role="presentation" class="active" id="tab-duplicopieur-0">
                                <a href="#" onclick="selectMachineTypeTab(0, 'duplicopieur'); return false;" style="font-size: 16px;">
                                    <i class="fa fa-print" style="margin-right: 5px;"></i> Duplicopieur
                                </a>
                            </li>
                            <li role="presentation" id="tab-photocopieur-0">
                                <a href="#" onclick="selectMachineTypeTab(0, 'photocopieur'); return false;" style="font-size: 16px;">
                                    <i class="fa fa-copy" style="margin-right: 5px;"></i> Photocopieur
                                </a>
                            </li>
                        </ul>
                        <!-- Inputs cachés pour les valeurs -->
                        <input type="radio" name="machines[0][type]" value="duplicopieur" checked onchange="toggleMachineType(0)" style="display: none;" id="radio-duplicopieur-0">
                        <input type="radio" name="machines[0][type]" value="photocopieur" onchange="toggleMachineType(0)" style="display: none;" id="radio-photocopieur-0">
                    </div>
                </div>
                
                <!-- Interface duplicopieur -->
                <div id="duplicopieur-interface-0" class="machine-interface" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #17a2b8;">
                    <!-- Affichage du duplicopieur -->
                    <div class="form-group">
                        <label class="col-md-3 control-label">
                            <i class="fa fa-cog" style="margin-right: 5px;"></i> Duplicopieur
                        </label>
                        <div class="col-md-9">
                            <?php if(isset($duplicopieur_selectionne)): ?>
                                <input type="hidden" name="machines[0][duplicopieur_id]" value="<?= $duplicopieur_selectionne['id'] ?>">
                                <p class="form-control-static">
                                    <strong><?= htmlspecialchars($duplicopieur_selectionne['marque']) ?> <?= htmlspecialchars($duplicopieur_selectionne['modele']) ?></strong>
                                    <br><small class="text-muted">Supporte A3 et A4</small>
                                </p>
                            <?php elseif(isset($duplicopieurs) && count($duplicopieurs) > 1): ?>
                                <select name="machines[0][duplicopieur_id]" class="form-control" required onchange="updateDuplicopieurCounters(this.value, 0)">
                                    <option value="">Choisir un duplicopieur</option>
                                    <?php foreach($duplicopieurs as $index => $dup): ?>
                                        <?php 
                                        $machine_name = $dup['marque'];
                                        if ($dup['marque'] !== $dup['modele']) {
                                            $machine_name = $dup['marque'] . ' ' . $dup['modele'];
                                        }
                                        ?>
                                        <option value="<?= $dup['id'] ?>" data-name="<?= htmlspecialchars($machine_name) ?>">
                                            <?= htmlspecialchars($dup['marque']) ?> <?= htmlspecialchars($dup['modele']) ?> 
                                            (<?= $dup['supporte_a3'] ? 'A3' : '' ?><?= $dup['supporte_a3'] && $dup['supporte_a4'] ? '/' : '' ?><?= $dup['supporte_a4'] ? 'A4' : '' ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <p class="form-control-static text-danger">Aucun duplicopieur disponible</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Sélection du tambour -->
                    <div class="form-group" id="tambour-group-0" style="display: none;">
                        <label class="col-md-3 control-label">
                            <i class="fa fa-circle" style="margin-right: 5px;"></i> Tambour utilisé
                        </label>
                        <div class="col-md-9">
                            <select name="machines[0][tambour]" class="form-control" id="tambour-select-0">
                                
                            </select>
                            <span class="help-block">Choisissez le tambour utilisé pour ce tirage</span>
                        </div>
                    </div>
                    
                    <!-- Options duplicopieur -->
                    <div class="form-group" style="padding: 10px; margin: 10px 0;">
                        <label class="col-md-2 control-label">
                            <i class="fa fa-sliders" style="margin-right: 5px;"></i> Options
                        </label>
                        <div class="col-md-10">
                            <div class="row">
                                <div class="col-xs-4 col-sm-3">
                                    <div class="checkbox">
                                        <label for="A4_0">
                                            <input name="machines[0][A4]" value="A4" type="checkbox" onchange="calculateTotalPrice()" id="A4_0">
                                            <i class="fa fa-file-text-o"></i> Format A4
                                        </label>
                                    </div>
                                </div>
                                <div class="col-xs-4 col-sm-3">
                                    <div class="checkbox">
                                        <label for="rv_0">
                                            <input name="machines[0][rv]" value="oui" type="checkbox" onchange="calculateTotalPrice()" id="rv_0">
                                            <i class="fa fa-files-o"></i> Recto/verso
                                        </label>
                                    </div>
                                </div>
                                <div class="col-xs-12 col-sm-6">
                                    <div class="checkbox">
                                        <label for="feuilles_payees_0">
                                            <input name="machines[0][feuilles_payees]" value="oui" type="checkbox" onchange="calculateTotalPrice()" id="feuilles_payees_0">
                                            <i class="fa fa-money" style="color: #f39c12;"></i> Feuilles déjà payées
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Mode de saisie -->
                    <div class="col-md-12" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 10px; border-left: 4px solid #28a745;">
                        <legend style="border-bottom: 2px solid #dee2e6; padding-bottom: 10px; margin-bottom: 15px; font-size: 18px;">
                            <i class="fa fa-keyboard-o" style="margin-right: 8px; color: #28a745;"></i> Mode de saisie
                        </legend>
                        <div class="form-group">
                            <label class="col-md-3 control-label">Type de saisie</label>
                            <div class="col-md-4">
                                <div class="radio">
                                    <label>
                                        <input type="radio" name="machines[0][mode_saisie]" value="compteurs" checked onchange="toggleSaisieMode(0)">
                                        Compteurs (avant/après)
                                    </label>
                                </div>
                                <div class="radio">
                                    <label>
                                        <input type="radio" name="machines[0][mode_saisie]" value="manuel" onchange="toggleSaisieMode(0)">
                                        Masters et passages
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Mode compteurs -->
                        <div id="compteurs-mode-0" class="saisie-mode">
                            <div class="row">
                                <div class="col-md-6">
                                    <fieldset style="border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
                                        <legend style="width: auto; padding: 0 10px; font-size: 16px; margin-bottom: 10px;">Compteurs AVANT</legend>
                                        <div class="form-group">
                                            <label class="col-xs-4 control-label" for="master_av_0">Masters</label>  
                                            <div class="col-xs-8">
                                                <input id="master_av_0" name="machines[0][master_av]" class="form-control input-sm" type="number" min="0" value="<?= isset($master_av) ? $master_av : '0' ?>" onchange="calculateTotalPrice()" style="max-width: 120px;">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="col-xs-4 control-label" for="passage_av_0">Passages</label>  
                                            <div class="col-xs-8">
                                                <input id="passage_av_0" name="machines[0][passage_av]" class="form-control input-sm" type="number" min="0" value="<?= isset($passage_av) ? $passage_av : '0' ?>" onchange="calculateTotalPrice()" style="max-width: 120px;">
                                            </div>
                                        </div>
                                    </fieldset>
                                </div>
                                <div class="col-md-6">
                                    <fieldset style="border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
                                        <legend style="width: auto; padding: 0 10px; font-size: 16px; margin-bottom: 10px;">Compteurs APRÈS</legend>
                                        <div class="form-group">
                                            <label class="col-xs-4 control-label" for="master_ap_0">Masters</label>  
                                            <div class="col-xs-8">
                                                <input id="master_ap_0" name="machines[0][master_ap]" class="form-control input-sm" type="number" min="0" value="<?= $master_av ?>" onchange="calculateTotalPrice()" style="max-width: 120px;">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="col-xs-4 control-label" for="passage_ap_0">Passages</label>  
                                            <div class="col-xs-8">
                                                <input id="passage_ap_0" name="machines[0][passage_ap]" class="form-control input-sm" type="number" min="0" value="<?= $passage_av ?>" onchange="calculateTotalPrice()" style="max-width: 120px;">
                                            </div>
                                        </div>
                                    </fieldset>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Mode manuel -->
                        <div id="manuel-mode-0" class="saisie-mode" style="display:none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="col-xs-4 control-label" for="nb_masters_0">Masters</label>  
                                        <div class="col-xs-8">
                                            <input id="nb_masters_0" name="machines[0][nb_masters]" class="form-control input-sm" type="number" min="0" value="0" onchange="calculateTotalPrice()" style="max-width: 120px;">
                                            <span class="help-block">Nombre utilisé</span>  
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="col-xs-4 control-label" for="nb_passages_0">Passages</label>  
                                        <div class="col-xs-8">
                                            <input id="nb_passages_0" name="machines[0][nb_passages]" class="form-control input-sm" type="number" min="0" value="0" onchange="calculateTotalPrice()" style="max-width: 120px;">
                                            <span class="help-block">Nombre effectué</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Interface photocopieur -->
                <div id="photocopieur-interface-0" class="machine-interface" style="display:none; background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #e83e8c;">
                    <!-- Sélection photocopieuse -->
                    <div class="form-group">
                        <label class="col-md-3 control-label" for="marque_0">
                            <i class="fa fa-desktop" style="margin-right: 5px;"></i> Photocopieuse
                        </label>
                        <div class="col-md-9">
                            <select id="marque_0" name="machines[0][machine]" class="form-control">
                                <?php
                                if (isset($photocopiers) && !empty($photocopiers)) {
                                    $first_photocop = true;
                                    foreach ($photocopiers as $photocop) {
                                        $selected = $first_photocop ? 'selected' : '';
                                        echo '<option value="' . htmlspecialchars($photocop->marque) . '" ' . $selected . '>' . htmlspecialchars($photocop->marque) . '</option>';
                                        $first_photocop = false;
                                    }
                                } else {
                                    echo '<option value="">-- Aucune photocopieuse disponible --</option>';
                                }
                                ?>
                            </select>
                            <span class="help-block">Quelle photocopieuse utilisez-vous ?</span>
                        </div>
                    </div>
                    
                    
                    <!-- Section pour les brochures/tracts -->
                    <div class="brochures-container" data-machine="0">
                        <h5 style="background: #f8f9fa; padding: 12px; border-radius: 5px; margin-bottom: 15px; border-left: 3px solid #9c27b0;">
                            <i class="fa fa-book" style="margin-right: 8px; color: #9c27b0;"></i> Brochures/Tracts à imprimer
                        </h5>
                        <div class="brochure-item" data-brochure="0" style="padding: 15px; background: #ffffff; border: 1px solid #dee2e6; border-radius: 5px; margin-bottom: 10px;">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="control-label" for="nb_exemplaires_0_0">
                                            <i class="fa fa-copy"></i> Nombre d'exemplaires
                                        </label>  
                                        <input id="nb_exemplaires_0_0" name="machines[0][brochures][0][nb_exemplaires]" class="form-control input-sm" type="number" min="1" value="1" onchange="calculateTotalPrice()" style="max-width: 100px;" placeholder="Ex: 10">
                                        <small class="text-muted">Combien de copies identiques ?</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="control-label" for="nb_feuilles_0_0">
                                            <i class="fa fa-file-text-o"></i> Feuilles par exemplaire
                                        </label>  
                                        <input id="nb_feuilles_0_0" name="machines[0][brochures][0][nb_feuilles]" class="form-control input-sm" type="number" min="1" onchange="calculateTotalPrice()" style="max-width: 100px;" placeholder="Ex: 5">
                                        <small class="text-muted">Pages par copie</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">
                                            <i class="fa fa-calculator"></i> Total feuilles
                                        </label>
                                        <div class="well well-sm" style="margin: 0; padding: 8px; background: #f8f9fa; border: 1px solid #ddd;">
                                            <span id="total-feuilles-0-0" style="font-weight: bold; color: #007bff;">1 feuille</span>
                                            <small class="text-muted">(exemplaires × feuilles/ex.)</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label" for="radios_0_0">Taille</label>
                                        <div> 
                                            <label class="radio-inline" for="radios-0-0-0">
                                                <input name="machines[0][brochures][0][taille]" id="radios-0-0-0" value="A4" checked="checked" type="radio" onchange="calculateTotalPrice()">
                                                A4
                                            </label> 
                                            <label class="radio-inline" for="radios-0-0-1">
                                                <input name="machines[0][brochures][0][taille]" id="radios-0-0-1" value="A3" type="radio" onchange="calculateTotalPrice()">
                                                A3
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <label class="control-label"><i class="fa fa-cogs"></i> Options</label>
                                    <div class="checkbox-inline" style="margin-right: 20px;">
                                        <label for="rv_0_0">
                                            <input name="machines[0][brochures][0][rv]" value="oui" type="checkbox" onchange="calculateTotalPrice()" id="rv_0_0">
                                            <i class="fa fa-files-o"></i> Recto/verso
                                        </label>
                                    </div>
                                    <div class="checkbox-inline" style="margin-right: 20px;">
                                        <label for="couleur_0_0">
                                            <input name="machines[0][brochures][0][couleur]" value="oui" type="checkbox" onchange="calculateTotalPrice(); toggleFillRateDisplay(0);" id="couleur_0_0">
                                            <i class="fa fa-tint"></i> Couleur
                                        </label>
                                    </div>
                                    <div class="checkbox-inline">
                                        <label for="feuilles_payees_0_0">
                                            <input name="machines[0][brochures][0][feuilles_payees]" value="oui" type="checkbox" onchange="calculateTotalPrice()" id="feuilles_payees_0_0">
                                            <i class="fa fa-money" style="color: #f39c12;"></i> Feuilles déjà payées
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Taux de remplissage couleur - sous la case couleur -->
                            <div class="form-group" id="fill-rate-group-0" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 15px; display: none; border-left: 3px solid #e83e8c;">
                                <label class="col-md-3 control-label">
                                    <i class="fa fa-percent" style="margin-right: 5px;"></i> Taux de remplissage couleur
                                </label>
                                <div class="col-md-9">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <input type="range" id="fill_rate_photocop_slider_0" min="0" max="100" value="50" step="5" 
                                                   class="form-control" oninput="updateFillRateDisplay('photocop', 0)" style="margin: 8px 0;">
                                        </div>
                                        <div class="col-md-4">
                                            <span id="fill_rate_photocop_display_0" style="font-size: 16px; font-weight: bold; color: #e83e8c;">50%</span>
                                        </div>
                                    </div>
                                    <input type="hidden" id="fill_rate_photocop_0" name="machines[0][fill_rate]" value="0.5">
                                    <span class="help-block">Ajustez le taux de remplissage des couleurs (0% = très léger, 100% = très foncé)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div><!-- Fin photocopieur-interface -->
                
                <!-- Prix de la machine -->
                <div class="form-group" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 15px; border-left: 4px solid #28a745;">
                    <label class="col-md-4 control-label" style="font-size: 14px; font-weight: normal;">
                        <i class="fa fa-euro" style="margin-right: 5px; color: #28a745;"></i> Prix de ce tirage
                    </label>
                    <div class="col-md-8">
                        <div class="form-control-static machine-price" data-machine="0" id="machine-price-0" style="font-size: 16px; font-weight: bold; color: #28a745;">0.00€</div>
                    </div>
                </div>
                
                </div><!-- Fin panel-body -->
            </div><!-- Fin machine-item -->
            
            <!-- Bouton pour ajouter une machine (à l'intérieur du container) -->
            <div class="text-center" style="margin: 20px 0;">
                <button type="button" id="add-machine" class="btn btn-success btn-lg">
                    <i class="fa fa-plus-circle"></i> Ajouter un tirage
                </button>
            </div>
        </div><!-- Fin machines-container -->
        
        <!-- Récapitulatif total -->
        <div class="alert alert-info">
            <h4 class="text-center">Récapitulatif du tirage</h4>
            <p class="text-center"><strong>Prix total : <span id="prix-total">0.00€</span></strong></p>
        </div>
        
        <!-- Bouton suivant -->
        <div class="section">
            <div class="container">
                <div class="row">
                    <div class="col-md-12"><button id="singlebutton" name="ok" class="btn btn-success btn-block">Suivant</button></div>
                </div>
            </div>
        </div>
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
    
    // Pour l'instant, utiliser une logique de fallback
    // On va essayer de deviner l'ID en fonction du nom
    if (machineName.toLowerCase() === 'comcolor') {
        return 'photocop_1';
    } else if (machineName.toLowerCase() === 'konika') {
        return 'photocop_4'; // ID réel de la machine konika
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
                    <strong>Détail du calcul :</strong><br>
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
        var totalFeuilles = 0;
        
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
            totalFeuilles += nbExemplaires * nbFeuilles;
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
            
            // NOUVELLE STRUCTURE : Utiliser la fonction pour trouver la clé dynamique
            var machineKey = findMachinePriceKey(photocopName);
            console.log('🔑 Clé trouvée pour le détail', photocopName, ':', machineKey);
            
            if (machineKey && prixData[machineKey]) {
                var machinePrices = prixData[machineKey];
                
                if (photocopName.toLowerCase() === 'comcolor') {
                    // Photocopieur à encre : additionner toutes les encres
                    if (couleur) {
                        // Couleur : bleue + couleur + jaune + noire + rouge
                        var bleue = machinePrices['bleue']?.unite || 0;
                        var couleurPrice = machinePrices['couleur']?.unite || 0;
                        var jaune = machinePrices['jaune']?.unite || 0;
                        var noire = machinePrices['noire']?.unite || 0;
                        var rouge = machinePrices['rouge']?.unite || 0;
                        
                        prixEncre = bleue + couleurPrice + jaune + noire + rouge;
                        detailEncreBrochure = `Bleue: ${bleue.toFixed(4)}€ + Couleur: ${couleurPrice.toFixed(4)}€ + Jaune: ${jaune.toFixed(4)}€ + Noire: ${noire.toFixed(4)}€ + Rouge: ${rouge.toFixed(4)}€ = ${prixEncre.toFixed(4)}€`;
                    } else {
                        // Noir et blanc : seulement noire
                        prixEncre = machinePrices['noire']?.unite || 0;
                        detailEncreBrochure = `Noire: ${prixEncre.toFixed(4)}€`;
                    }
                } else if (photocopName.toLowerCase() === 'konika') {
                    // Photocopieur à toner : additionner tous les toners + tambour + developer
                    if (couleur) {
                        // Couleur : cyan + jaune + magenta + noir + tambour + dev
                        var cyan = machinePrices['cyan']?.unite || 0;
                        var jaune = machinePrices['jaune']?.unite || 0;
                        var magenta = machinePrices['magenta']?.unite || 0;
                        var noir = machinePrices['noir']?.unite || 0;
                        var tambour = machinePrices['tambour']?.unite || 0;
                        var dev = machinePrices['dev']?.unite || 0;
                        
                        prixEncre = cyan + jaune + magenta + noir + tambour + dev;
                        detailEncreBrochure = `Cyan: ${cyan.toFixed(4)}€ + Jaune: ${jaune.toFixed(4)}€ + Magenta: ${magenta.toFixed(4)}€ + Noir: ${noir.toFixed(4)}€ + Tambour: ${tambour.toFixed(4)}€ + Dev: ${dev.toFixed(4)}€ = ${prixEncre.toFixed(4)}€`;
                    } else {
                        // Noir et blanc : noir + tambour + dev
                        var noir = machinePrices['noir']?.unite || 0;
                        var tambour = machinePrices['tambour']?.unite || 0;
                        var dev = machinePrices['dev']?.unite || 0;
                        
                        prixEncre = noir + tambour + dev;
                        detailEncreBrochure = `Noir: ${noir.toFixed(4)}€ + Tambour: ${tambour.toFixed(4)}€ + Dev: ${dev.toFixed(4)}€ = ${prixEncre.toFixed(4)}€`;
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
        
        detailCalcul = `
            <div class="price-detail" style="font-size: 0.9em; color: #666; margin-top: 5px;">
                <strong>Détail du calcul :</strong><br>
                • ${totalExemplaires} exemplaires × ${totalFeuilles} feuilles = ${totalPages} pages<br>
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

// Gestion des machines
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔍 DOM chargé, initialisation des prix...');
    calculateTotalPrice();
    
    const addMachineBtn = document.getElementById('add-machine');
    if (!addMachineBtn) {
        console.log('Bouton add-machine non trouvé - probablement sur la page de confirmation');
        return;
    }
    
    addMachineBtn.addEventListener('click', function() {
    const container = document.getElementById('machines-container');
    const newIndex = machineCount;
    
    // Faire une requête AJAX pour récupérer le HTML de la machine
    fetch(`./get-machine-template.php?index=${newIndex}`)
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
            const addButtonContainer = container.querySelector('div.text-center:last-child');
            
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
        calculateTotalPrice();
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
    
    // S'assurer que le champ cb1 est rempli avant la soumission
    document.getElementById('multimachines-form').addEventListener('submit', function() {
        var payeOui = document.getElementById('payeoui');
        var cbField = document.getElementById('cb1');
        if (payeOui && payeOui.checked && cbField) {
            var total = calculateTotalPrice();
            cbField.value = total.toFixed(2);
        }
    });
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
            var match = totalText.match(/(\d+\.?\d*)/);
            if (match) {
                var total = parseFloat(match[1]);
                cbField.value = total.toFixed(2);
                console.log("Prix total trouvé dans #prix-total:", total);
                return;
            }
        }
        
        // Si pas trouvé dans #prix-total, essayer de trouver le prix total dans le récapitulatif (page de confirmation)
        // Chercher spécifiquement dans l'élément h2.text-primary strong (structure exacte du TOTAL GLOBAL)
        var totalPriceElement = document.querySelector('h2.text-primary strong');
        if (totalPriceElement) {
            var totalText = totalPriceElement.textContent;
            console.log("Prix trouvé dans h2.text-primary strong:", totalText);
            var match = totalText.match(/(\d+\.\d{2})€/);
            if (match) {
                var total = parseFloat(match[1]);
                console.log("Prix total extrait:", total);
                cbField.value = total.toFixed(2);
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