<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration initiale - Dupli</title>
    <link href="css/bootstrap.css" rel="stylesheet" type="text/css">
    <style>
        .setup-container {
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
        }

        .machine-card {
            border: 2px solid #ddd;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .machine-card:hover {
            border-color: #007bff;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .machine-card.active {
            border-color: #28a745;
            background-color: #f8fff9;
        }

        .counter-input {
            max-width: 200px;
        }

        .machine-type-selector {
            margin-bottom: 20px;
        }

        .price-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }

        .choice-card {
            border: 2px solid #ddd;
            border-radius: 10px;
            padding: 30px;
            margin: 20px 0;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .choice-card:hover {
            border-color: #007bff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }

        .choice-icon {
            font-size: 48px;
            margin-bottom: 15px;
            color: #007bff;
        }

        .upload-zone {
            border: 2px dashed #007bff;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            background: #f8f9fa;
            margin: 20px 0;
        }

        .printer-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            transition: all 0.2s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fff;
        }

        .printer-card:hover {
            border-color: #007bff;
            background: #f0f7ff;
            transform: translateX(5px);
        }

        .printer-info {
            display: flex;
            align-items: center;
        }

        .printer-icon {
            font-size: 24px;
            margin-right: 15px;
            color: #6c757d;
        }

        .printer-name {
            font-weight: bold;
            font-size: 1.1em;
        }

        .printer-status {
            font-size: 0.85em;
            color: #6c757d;
        }

        .machine-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            margin-top: 4px;
        }

        .badge-dupli { background-color: #e3f2fd; color: #0d47a1; }
        .badge-photocop { background-color: #f3e5f5; color: #4a148c; }
    </style>
</head>

<body>
    <div class="setup-container">
        <div class="text-center mb-4">
            <h1>🚀 <?php _e('setup.title'); ?></h1>
            <p class="lead">
                <?= $mode === 'choice' ? 'Choisissez comment configurer votre application' : ($mode === 'upload' ? 'Importer une base de données existante' : 'Ajoutez vos machines pour commencer') ?>
            </p>
        </div>

        <?php if (isset($errors) && !empty($errors)): ?>
            <div class="alert alert-danger">
                <h5>⚠️ Erreurs détectées :</h5>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <strong>✅ Succès :</strong> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($mode === 'choice'): ?>
            <!-- ÉCRAN DE CHOIX -->
            <div class="row">
                <div class="col-md-6">
                    <a href="?setup&mode=create" style="text-decoration: none; color: inherit;">
                        <div class="choice-card text-center">
                            <div class="choice-icon">
                                <i class="fa fa-plus-circle"></i>
                            </div>
                            <h3>Créer mes machines</h3>
                            <p class="text-muted">
                                Configurer manuellement vos duplicopieurs et photocopieurs
                            </p>
                        </div>
                    </a>
                </div>
                <div class="col-md-6">
                    <a href="?setup&mode=upload" style="text-decoration: none; color: inherit;">
                        <div class="choice-card text-center">
                            <div class="choice-icon">
                                <i class="fa fa-upload"></i>
                            </div>
                            <h3>Importer une base</h3>
                            <p class="text-muted">
                                Restaurer une base de données existante depuis un fichier
                            </p>
                        </div>
                    </a>
                </div>
            </div>

        <?php elseif ($mode === 'upload'): ?>
            <!-- ÉCRAN D'UPLOAD -->
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="fa fa-upload"></i> Importer une base de données
                    </h3>
                </div>
                <div class="panel-body">
                    <form action="?setup_upload" method="POST" enctype="multipart/form-data">
                        <div class="upload-zone">
                            <i class="fa fa-database" style="font-size: 64px; color: #007bff; margin-bottom: 20px;"></i>
                            <h4>Sélectionnez votre fichier SQLite</h4>
                            <p class="text-muted">Fichier .sqlite (max 50 MB)</p>
                            <input type="file" name="database_file" accept=".sqlite" required class="form-control"
                                style="margin-top: 20px;">
                        </div>

                        <div class="alert alert-info" style="margin-top: 20px;">
                            <strong><i class="fa fa-info-circle"></i> Information :</strong>
                            <ul>
                                <li>Le fichier doit être une base de données SQLite (.sqlite)</li>
                                <li>La base doit contenir au moins une machine configurée</li>
                                <li>Une sauvegarde de sécurité sera créée si une base existe déjà</li>
                            </ul>
                        </div>

                        <div class="text-center" style="margin-top: 30px;">
                            <a href="?setup&mode=choice" class="btn btn-default btn-lg">
                                <i class="fa fa-arrow-left"></i> Retour
                            </a>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fa fa-upload"></i> Importer la base de données
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        <?php else: // mode === 'create' ?>
            <!-- ÉCRAN DE CRÉATION DES MACHINES -->
            <div class="text-right mb-3">
                <a href="?setup&mode=choice" class="btn btn-default">
                    <i class="fa fa-arrow-left"></i> Retour au choix
                </a>
            </div>

            <form id="setupForm" method="POST" action="?setup_save">
                <!-- Configuration du mot de passe administrateur -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4>🔐 Configuration du mot de passe administrateur</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="admin_password">Mot de passe administrateur :</label>
                                    <input type="password" class="form-control" id="admin_password" name="admin_password"
                                        required minlength="6">
                                    <small class="form-text text-muted">Minimum 6 caractères. Ce mot de passe vous permettra
                                        d'accéder à l'administration.</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="admin_password_confirm">Confirmer le mot de passe :</label>
                                    <input type="password" class="form-control" id="admin_password_confirm"
                                        name="admin_password_confirm" required minlength="6">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Imprimantes système détectées -->
                <div id="system-printers-section" class="mb-4">
                    <h3>🔍 Imprimantes système détectées</h3>
                    <p class="text-muted">Sélectionnez une imprimante pour la configurer comme machine (Duplicopieur ou Photocopieur).</p>
                    
                    <div id="loading-printers" class="text-center p-4">
                        <i class="fa fa-spinner fa-spin fa-2x text-primary"></i>
                        <p>Recherche des imprimantes installées...</p>
                    </div>

                    <div id="printers-container">
                        <!-- Les imprimantes système apparaîtront ici -->
                    </div>

                    <div class="text-center mt-3">
                        <button type="button" class="btn btn-link" id="add-manual-btn">
                            <i class="fa fa-keyboard-o"></i> Ajouter une machine manuellement
                        </button>
                    </div>
                </div>

                <!-- Sélection du type de machine (Caché par défaut, affiché lors du choix d'une imprimante) -->
                <div class="machine-type-selector" id="type-selector-section" style="display: none;">
                    <hr>
                    <h3 id="type-selector-title">📋 Configurer l'imprimante : <span id="selected-printer-name"></span></h3>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="machine_type" id="type_duplicopieur"
                                    value="duplicopieur">
                                <label class="form-check-label" for="type_duplicopieur">
                                    <h5>🖨️ Duplicopieur</h5>
                                    <small class="text-muted">Machine de reproduction A3/A4</small>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="machine_type" id="type_photocop_encre"
                                    value="photocop_encre">
                                <label class="form-check-label" for="type_photocop_encre">
                                    <h5>📷 Photocopieuse (Encre)</h5>
                                    <small class="text-muted">Machine à encre liquide</small>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="machine_type" id="type_photocop_toner"
                                    value="photocop_toner">
                                <label class="form-check-label" for="type_photocop_toner">
                                    <h5>🖨️ Photocopieuse (Toner)</h5>
                                    <small class="text-muted">Machine à toner</small>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Formulaire de machine -->
                <div id="machine-form" style="display: none;">
                    <div class="machine-card">
                        <h4 id="machine-title">Configuration de la machine</h4>

                        <!-- Informations de base -->
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="machine_name">Nom de la machine :</label>
                                    <input type="text" class="form-control" id="machine_name" name="machine_name"
                                        placeholder="Ex: Ricoh dx4545, ComColor, etc." required>
                                </div>
                            </div>
                            <div class="col-md-4" id="master-counter-field" style="display: none;">
                                <div class="form-group">
                                    <label for="master_counter">Compteur Master :</label>
                                    <input type="number" class="form-control" id="master_counter" name="master_counter"
                                        placeholder="Ex: 12345" min="0">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="passage_counter">Compteur Passage :</label>
                                    <input type="number" class="form-control" id="passage_counter" name="passage_counter"
                                        placeholder="Ex: 67890" min="0" required>
                                </div>
                            </div>
                        </div>

                        <!-- Configuration des prix -->
                        <div class="price-section">
                            <h5>💰 Configuration des prix</h5>

                            <!-- Prix duplicopieur -->
                            <div id="duplicopieur-prices" style="display: none;">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Master :</h6>
                                        <div class="form-group">
                                            <label for="prix_master_unite">Prix master à l'unité (€) :</label>
                                            <input type="number" step="0.001" class="form-control" id="prix_master_unite"
                                                name="prix_master_unite" value="0.4" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="prix_master_pack">Prix master en pack (€) :</label>
                                            <input type="number" step="0.001" class="form-control" id="prix_master_pack"
                                                name="prix_master_pack" value="70" required>
                                        </div>
                                    </div>
                                </div>

                                <!-- Configuration des tambours -->
                                <hr>
                                <h6>Configuration des tambours :</h6>
                                <div class="form-group">
                                    <div id="tambours-container">
                                        <!-- Tambour par défaut -->
                                        <div class="tambour-item" style="margin-bottom: 10px;">
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <label>Nom du tambour :</label>
                                                    <input type="text" class="form-control" name="tambours[]"
                                                        placeholder="ex: tambour_noir" value="tambour_noir" required>
                                                </div>
                                                <div class="col-md-3">
                                                    <label>Prix unité (€) :</label>
                                                    <input type="number" class="form-control" name="prix_tambour_unite[]"
                                                        placeholder="Prix unité" step="0.001" min="0" value="0.002"
                                                        required>
                                                </div>
                                                <div class="col-md-3">
                                                    <label>Prix pack (€) :</label>
                                                    <input type="number" class="form-control" name="prix_tambour_pack[]"
                                                        placeholder="Prix pack" step="0.01" min="0" value="11">
                                                </div>
                                                <div class="col-md-2">
                                                    <label>&nbsp;</label>
                                                    <button type="button" class="btn btn-danger btn-sm remove-tambour"
                                                        style="display: none;">
                                                        <i class="fa fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-info btn-sm" id="add-tambour">
                                        <i class="fa fa-plus"></i> Ajouter un tambour
                                    </button>
                                    <small class="help-block">Définissez les tambours disponibles pour ce duplicopieur.
                                        Chaque tambour peut avoir un nom personnalisé.</small>
                                </div>
                            </div>

                            <!-- Prix photocopieuse encre -->
                            <div id="photocop-encre-prices" style="display: none;">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Encre Noire :</h6>
                                        <div class="form-group">
                                            <label for="prix_noire_unite">Prix à l'unité (€) :</label>
                                            <input type="number" step="0.001" class="form-control" id="prix_noire_unite"
                                                name="prix_noire_unite" value="0.015">
                                        </div>
                                        <div class="form-group">
                                            <label for="prix_noire_pack">Prix en pack (€) :</label>
                                            <input type="number" step="0.001" class="form-control" id="prix_noire_pack"
                                                name="prix_noire_pack" value="140">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Encre Bleue :</h6>
                                        <div class="form-group">
                                            <label for="prix_bleue_unite">Prix à l'unité (€) :</label>
                                            <input type="number" step="0.001" class="form-control" id="prix_bleue_unite"
                                                name="prix_bleue_unite" value="0.005">
                                        </div>
                                        <div class="form-group">
                                            <label for="prix_bleue_pack">Prix en pack (€) :</label>
                                            <input type="number" step="0.001" class="form-control" id="prix_bleue_pack"
                                                name="prix_bleue_pack" value="140">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Encre Rouge :</h6>
                                        <div class="form-group">
                                            <label for="prix_rouge_unite">Prix à l'unité (€) :</label>
                                            <input type="number" step="0.001" class="form-control" id="prix_rouge_unite"
                                                name="prix_rouge_unite" value="0.005">
                                        </div>
                                        <div class="form-group">
                                            <label for="prix_rouge_pack">Prix en pack (€) :</label>
                                            <input type="number" step="0.001" class="form-control" id="prix_rouge_pack"
                                                name="prix_rouge_pack" value="140">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Encre Jaune :</h6>
                                        <div class="form-group">
                                            <label for="prix_jaune_unite">Prix à l'unité (€) :</label>
                                            <input type="number" step="0.001" class="form-control" id="prix_jaune_unite"
                                                name="prix_jaune_unite" value="0.005">
                                        </div>
                                        <div class="form-group">
                                            <label for="prix_jaune_pack">Prix en pack (€) :</label>
                                            <input type="number" step="0.001" class="form-control" id="prix_jaune_pack"
                                                name="prix_jaune_pack" value="140">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Prix photocopieuse toner -->
                            <div id="photocop-toner-prices" style="display: none;">
                                <h6>Toners :</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Toner Noir :</h6>
                                        <div class="form-group">
                                            <label for="toner_noir_prix">Prix cartouche (€) :</label>
                                            <input type="number" step="0.01" class="form-control" id="toner_noir_prix"
                                                name="toner_noir_prix" value="80">
                                        </div>
                                        <div class="form-group">
                                            <label for="toner_noir_prix_copie">Prix par page (€) :</label>
                                            <input type="number" step="0.00001" class="form-control"
                                                id="toner_noir_prix_copie" name="toner_noir_prix_copie" value="0.00348">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Toner Cyan :</h6>
                                        <div class="form-group">
                                            <label for="toner_cyan_prix">Prix cartouche (€) :</label>
                                            <input type="number" step="0.01" class="form-control" id="toner_cyan_prix"
                                                name="toner_cyan_prix" value="80">
                                        </div>
                                        <div class="form-group">
                                            <label for="toner_cyan_prix_copie">Prix par page (€) :</label>
                                            <input type="number" step="0.00001" class="form-control"
                                                id="toner_cyan_prix_copie" name="toner_cyan_prix_copie" value="0.00444">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Toner Magenta :</h6>
                                        <div class="form-group">
                                            <label for="toner_magenta_prix">Prix cartouche (€) :</label>
                                            <input type="number" step="0.01" class="form-control" id="toner_magenta_prix"
                                                name="toner_magenta_prix" value="80">
                                        </div>
                                        <div class="form-group">
                                            <label for="toner_magenta_prix_copie">Prix par page (€) :</label>
                                            <input type="number" step="0.00001" class="form-control"
                                                id="toner_magenta_prix_copie" name="toner_magenta_prix_copie"
                                                value="0.00444">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Toner Jaune :</h6>
                                        <div class="form-group">
                                            <label for="toner_jaune_prix">Prix cartouche (€) :</label>
                                            <input type="number" step="0.01" class="form-control" id="toner_jaune_prix"
                                                name="toner_jaune_prix" value="80">
                                        </div>
                                        <div class="form-group">
                                            <label for="toner_jaune_prix_copie">Prix par page (€) :</label>
                                            <input type="number" step="0.00001" class="form-control"
                                                id="toner_jaune_prix_copie" name="toner_jaune_prix_copie" value="0.00444">
                                        </div>
                                    </div>
                                </div>

                                <hr>
                                <h6>Tambour et unité de développement :</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Tambour :</h6>
                                        <div class="form-group">
                                            <label for="tambour_prix">Prix (€) :</label>
                                            <input type="number" step="0.01" class="form-control" id="tambour_prix"
                                                name="tambour_prix" value="200">
                                        </div>
                                        <div class="form-group">
                                            <label for="tambour_prix_copie">Prix par copie (€) :</label>
                                            <input type="number" step="0.00001" class="form-control" id="tambour_prix_copie"
                                                name="tambour_prix_copie" value="0.00167">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Unité de développement :</h6>
                                        <div class="form-group">
                                            <label for="dev_prix">Prix (€) :</label>
                                            <input type="number" step="0.01" class="form-control" id="dev_prix"
                                                name="dev_prix" value="300">
                                        </div>
                                        <div class="form-group">
                                            <label for="dev_prix_copie">Prix par copie (€) :</label>
                                            <input type="number" step="0.00001" class="form-control" id="dev_prix_copie"
                                                name="dev_prix_copie" value="0.00250">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Boutons d'action -->
                        <div class="text-center mt-3">
                            <button type="button" class="btn btn-success" id="add-machine-btn">
                                ➕ Ajouter cette machine
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Liste des machines ajoutées -->
                <div id="machines-list" style="display: none;">
                    <h3>📋 Machines configurées</h3>
                    <div id="machines-container">
                        <!-- Les machines ajoutées apparaîtront ici -->
                    </div>
                </div>

                <!-- Configuration du papier -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h4>📄 Configuration du prix du papier</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="prix_papier_A3">Prix feuille A3 (€) :</label>
                                    <input type="number" step="0.001" class="form-control" id="prix_papier_A3"
                                        name="prix_papier_A3" value="0.02" required>
                                    <small class="form-text text-muted">Le prix A4 sera automatiquement la moitié</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-primary btn-lg" id="submitBtn" disabled>
                        ✅ Terminer la configuration
                    </button>
                </div>
            </form>

            <div class="text-center mt-4">
                <small class="text-muted">
                    Vous pourrez modifier ces informations plus tard dans l'administration
                </small>
            </div>

        <?php endif; // Fin du mode create ?>

        <script src="js/jquery.min.js"></script>
        <script src="js/bootstrap.min.js"></script>

        <?php if ($mode === 'create'): // Script uniquement pour le mode create ?>
            <script>
                $(document).ready(function () {
                    let machines = [];
                    let machineCounter = 0;
                    let selectedPrinter = null;
                    let systemPrinters = [];

                    // Charger les imprimantes système au démarrage
                    fetchSystemPrinters();

                    async function fetchSystemPrinters() {
                        if (window.electronAPI && window.electronAPI.getPrinters) {
                            try {
                                const response = await window.electronAPI.getPrinters();
                                if (response && response.success && Array.isArray(response.printers)) {
                                    systemPrinters = response.printers;
                                    displaySystemPrinters();
                                } else {
                                    console.error('Format réponse imprimantes invalide:', response);
                                    $('#loading-printers').html('<div class="alert alert-warning">Format de réponse des imprimantes invalide.</div>');
                                }
                            } catch (err) {
                                console.error('Erreur récupération imprimantes:', err);
                                $('#loading-printers').html('<div class="alert alert-warning">Impossible de récupérer les imprimantes système.</div>');
                            }
                        } else {
                            $('#loading-printers').html('<div class="alert alert-info">API Electron non disponible (Mode Web).</div>');
                        }
                    }

                    function displaySystemPrinters() {
                        $('#loading-printers').hide();
                        let html = '';
                        
                        if (systemPrinters.length === 0) {
                            html = '<div class="alert alert-info">Aucune imprimante système détectée.</div>';
                        } else {
                            systemPrinters.forEach(printer => {
                                const isMapped = machines.some(m => m.systemPrinterName === printer.name);
                                html += `
                                    <div class="printer-card ${isMapped ? 'active' : ''}" id="printer-${printer.name.replace(/\s+/g, '_')}">
                                        <div class="printer-info">
                                            <div class="printer-icon"><i class="fa fa-print"></i></div>
                                            <div>
                                                <div class="printer-name">${printer.name}</div>
                                                <div class="printer-status">${printer.description || 'Imprimante système'}</div>
                                                ${isMapped ? '<span class="machine-badge badge-dupli"><i class="fa fa-check"></i> Déjà configurée</span>' : ''}
                                            </div>
                                        </div>
                                        <div>
                                            <button type="button" class="btn btn-primary btn-sm" onclick="selectPrinter('${printer.name}')">
                                                ${isMapped ? 'Re-configurer' : 'Configurer'}
                                            </button>
                                        </div>
                                    </div>
                                `;
                            });
                        }
                        
                        $('#printers-container').html(html);
                    }

                    window.selectPrinter = function(printerName) {
                        selectedPrinter = printerName;
                        $('#selected-printer-name').text(printerName);
                        $('#type-selector-section').fadeIn();
                        $('#machine-form').hide();
                        $('input[name="machine_type"]').prop('checked', false);
                        
                        // Scroll to type selector
                        $('html, body').animate({
                            scrollTop: $("#type-selector-section").offset().top - 100
                        }, 500);
                        
                        // Pre-fill machine name
                        $('#machine_name').val(printerName);
                    };

                    $('#add-manual-btn').click(function() {
                        selectedPrinter = null;
                        $('#selected-printer-name').text('Manuel');
                        $('#type-selector-section').fadeIn();
                        $('#machine-form').hide();
                        $('input[name="machine_type"]').prop('checked', false);
                        $('#machine_name').val('');
                    });

                    // Gestion du changement de type de machine
                    $('input[name="machine_type"]').change(function () {
                        const type = $(this).val();
                        showMachineForm(type);
                    });

                    function showMachineForm(type) {
                        $('#machine-form').show();
                        $('#duplicopieur-prices, #photocop-encre-prices, #photocop-toner-prices').hide();

                        if (type === 'duplicopieur') {
                            $('#machine-title').text('Configuration du duplicopieur');
                            $('#duplicopieur-prices').show();
                            $('#master-counter-field').show();
                            $('#master_counter').prop('required', true);
                        } else if (type === 'photocop_encre') {
                            $('#machine-title').text('Configuration de la photocopieuse (Encre)');
                            $('#photocop-encre-prices').show();
                            $('#master-counter-field').hide();
                            $('#master_counter').prop('required', false);
                        } else if (type === 'photocop_toner') {
                            $('#machine-title').text('Configuration de la photocopieuse (Toner)');
                            $('#photocop-toner-prices').show();
                            $('#master-counter-field').hide();
                            $('#master_counter').prop('required', false);
                        }

                        // Scroll to form
                        $('html, body').animate({
                            scrollTop: $("#machine-form").offset().top - 50
                        }, 500);
                    }

                    // Ajouter une machine
                    $('#add-machine-btn').click(function () {
                        const type = $('input[name="machine_type"]:checked').val();
                        const name = $('#machine_name').val();
                        const masterCounter = $('#master_counter').val();
                        const passageCounter = $('#passage_counter').val();

                        if (!name || !passageCounter) {
                            let missing = [];
                            if (!name) missing.push("Nom de la machine");
                            if (!passageCounter) missing.push("Compteur Passage");
                            showAppModal({ 
                                title: 'Champs manquants',
                                message: 'Veuillez remplir les champs suivants :<br>• ' + missing.join('<br>• '), 
                                type: 'warning' 
                            });
                            return;
                        }

                        if (type === 'duplicopieur' && !masterCounter) {
                            showAppModal({ 
                                title: 'Champ manquant',
                                message: 'Veuillez renseigner le compteur master pour le duplicopieur', 
                                type: 'warning' 
                            });
                            return;
                        }

                        const machine = {
                            id: machineCounter++,
                            type: type,
                            name: name,
                            systemPrinterName: selectedPrinter,
                            masterCounter: masterCounter || 0,
                            passageCounter: passageCounter,
                            tambours: getTambours(),
                            prices: getPricesForType(type)
                        };

                        machines.push(machine);
                        updateMachinesList();
                        clearForm();
                        updateSubmitButton();
                        displaySystemPrinters(); // Refresh printer list to show badges
                        
                        // Scroll back to printers or paper price
                        $('html, body').animate({
                            scrollTop: $("#machines-list").offset().top - 100
                        }, 500);
                    });

                    function getTambours() {
                        const tambours = [];
                        const tambourNames = $('input[name="tambours[]"]');
                        const tambourUnite = $('input[name="prix_tambour_unite[]"]');
                        const tambourPack = $('input[name="prix_tambour_pack[]"]');

                        tambourNames.each(function (index) {
                            tambours.push({
                                name: $(this).val(),
                                unite: tambourUnite.eq(index).val(),
                                pack: tambourPack.eq(index).val()
                            });
                        });
                        return tambours;
                    }

                    function getPricesForType(type) {
                        const prices = {};
                        if (type === 'duplicopieur') {
                            prices.master_unite = $('#prix_master_unite').val();
                            prices.master_pack = $('#prix_master_pack').val();
                        } else if (type === 'photocop_encre') {
                            prices.noire_unite = $('#prix_noire_unite').val();
                            prices.noire_pack = $('#prix_noire_pack').val();
                            prices.bleue_unite = $('#prix_bleue_unite').val();
                            prices.bleue_pack = $('#prix_bleue_pack').val();
                            prices.rouge_unite = $('#prix_rouge_unite').val();
                            prices.rouge_pack = $('#prix_rouge_pack').val();
                            prices.jaune_unite = $('#prix_jaune_unite').val();
                            prices.jaune_pack = $('#prix_jaune_pack').val();
                        } else if (type === 'photocop_toner') {
                            prices.toner_noir_prix = $('#toner_noir_prix').val();
                            prices.toner_noir_prix_copie = $('#toner_noir_prix_copie').val();
                            prices.toner_cyan_prix = $('#toner_cyan_prix').val();
                            prices.toner_cyan_prix_copie = $('#toner_cyan_prix_copie').val();
                            prices.toner_magenta_prix = $('#toner_magenta_prix').val();
                            prices.toner_magenta_prix_copie = $('#toner_magenta_prix_copie').val();
                            prices.toner_jaune_prix = $('#toner_jaune_prix').val();
                            prices.toner_jaune_prix_copie = $('#toner_jaune_prix_copie').val();
                            prices.tambour_prix = $('#tambour_prix').val();
                            prices.tambour_prix_copie = $('#tambour_prix_copie').val();
                            prices.dev_prix = $('#dev_prix').val();
                            prices.dev_prix_copie = $('#dev_prix_copie').val();
                        }
                        return prices;
                    }

                    function updateMachinesList() {
                        if (machines.length === 0) {
                            $('#machines-list').hide();
                            return;
                        }

                        $('#machines-list').show();
                        let html = '';

                        machines.forEach((machine, index) => {
                            const typeLabel = getTypeLabel(machine.type);
                            html += `
                                <div class="alert alert-info d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>${typeLabel}:</strong> ${machine.name} 
                                        ${machine.systemPrinterName ? `<br><small class="text-muted"><i class="fa fa-link"></i> Mappée sur : ${machine.systemPrinterName}</small>` : ''}
                                        <br><small class="text-muted">Compteur: ${machine.passageCounter}</small>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="removeMachine(${machine.id})">
                                        ❌ Supprimer
                                    </button>
                                </div>
                            `;
                        });

                        $('#machines-container').html(html);
                    }

                    function getTypeLabel(type) {
                        const labels = {
                            'duplicopieur': '🖨️ Duplicopieur',
                            'photocop_encre': '📷 Photocopieuse (Encre)',
                            'photocop_toner': '🖨️ Photocopieuse (Toner)'
                        };
                        return labels[type] || type;
                    }

                    function removeMachine(id) {
                        machines = machines.filter(m => m.id !== id);
                        updateMachinesList();
                        updateSubmitButton();
                        displaySystemPrinters();
                    }

                    function clearForm() {
                        $('#machine_name, #master_counter, #passage_counter').val('');
                        $('input[name="machine_type"]').prop('checked', false);
                        $('#machine-form').hide();
                        $('#type-selector-section').hide();
                        selectedPrinter = null;
                        
                        // Reset required
                        $('#machine_name, #passage_counter, #master_counter').prop('required', false);
                    }

                    function updateSubmitButton() {
                        const hasMachines = machines.length > 0;
                        const hasPaperPrice = $('#prix_papier_A3').val() !== '';
                        const hasPassword = $('#admin_password').val() !== '';
                        const passwordsMatch = $('#admin_password').val() === $('#admin_password_confirm').val();
                        const passwordValid = $('#admin_password').val().length >= 6;

                        $('#submitBtn').prop('disabled', !hasMachines || !hasPaperPrice || !hasPassword || !passwordsMatch || !passwordValid);
                    }

                    $('#prix_papier_A3, #admin_password, #admin_password_confirm').on('input', updateSubmitButton);

                    // Soumission du formulaire
                    $('#setupForm').submit(function (e) {
                        if (machines.length === 0) {
                            e.preventDefault();
                            showAppModal({ 
                                title: 'Aucune machine',
                                message: 'Veuillez ajouter au moins une machine avant de terminer.', 
                                type: 'warning' 
                            });
                            return;
                        }

                        // Nettoyer les champs visibles pour éviter les erreurs de validation HTML5 lors de la soumission de champs cachés
                        $('#machine_name, #passage_counter, #master_counter').prop('required', false);

                        machines.forEach((machine, index) => {
                            $('<input>').attr({ type: 'hidden', name: `machines[${index}][type]`, value: machine.type }).appendTo('#setupForm');
                            $('<input>').attr({ type: 'hidden', name: `machines[${index}][name]`, value: machine.name }).appendTo('#setupForm');
                            $('<input>').attr({ type: 'hidden', name: `machines[${index}][system_printer_name]`, value: machine.systemPrinterName || '' }).appendTo('#setupForm');
                            $('<input>').attr({ type: 'hidden', name: `machines[${index}][master_counter]`, value: machine.masterCounter }).appendTo('#setupForm');
                            $('<input>').attr({ type: 'hidden', name: `machines[${index}][passage_counter]`, value: machine.passageCounter }).appendTo('#setupForm');

                            if (machine.type === 'duplicopieur' && machine.tambours) {
                                machine.tambours.forEach((tambour, tambourIndex) => {
                                    $('<input>').attr({ type: 'hidden', name: `machines[${index}][tambours][${tambourIndex}][name]`, value: tambour.name }).appendTo('#setupForm');
                                    $('<input>').attr({ type: 'hidden', name: `machines[${index}][tambours][${tambourIndex}][unite]`, value: tambour.unite }).appendTo('#setupForm');
                                    $('<input>').attr({ type: 'hidden', name: `machines[${index}][tambours][${tambourIndex}][pack]`, value: tambour.pack }).appendTo('#setupForm');
                                });
                            }

                            Object.keys(machine.prices).forEach(key => {
                                $('<input>').attr({ type: 'hidden', name: `machines[${index}][${key}]`, value: machine.prices[key] }).appendTo('#setupForm');
                            });
                        });
                    });

                    // Gestion des tambours
                    $('#add-tambour').click(function () {
                        var tambourHtml = `
                            <div class="tambour-item" style="margin-bottom: 10px;">
                                <div class="row">
                                    <div class="col-md-4">
                                        <label>Nom du tambour :</label>
                                        <input type="text" class="form-control" name="tambours[]" placeholder="ex: tambour_bleu" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label>Prix unité (€) :</label>
                                        <input type="number" class="form-control" name="prix_tambour_unite[]" placeholder="Prix unité" step="0.001" min="0" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label>Prix pack (€) :</label>
                                        <input type="number" class="form-control" name="prix_tambour_pack[]" placeholder="Prix pack" step="0.01" min="0" value="11">
                                    </div>
                                    <div class="col-md-2">
                                        <label>&nbsp;</label>
                                        <button type="button" class="btn btn-danger btn-sm remove-tambour"><i class="fa fa-trash"></i></button>
                                    </div>
                                </div>
                            </div>
                        `;
                        $('#tambours-container').append(tambourHtml);
                        updateRemoveButtons();
                    });

                    $(document).on('click', '.remove-tambour', function () { $(this).closest('.tambour-item').remove(); updateRemoveButtons(); });

                    function updateRemoveButtons() {
                        var tambourItems = $('.tambour-item');
                        if (tambourItems.length > 1) { $('.remove-tambour').show(); } else { $('.remove-tambour').hide(); }
                    }

                    updateRemoveButtons();
                    window.removeMachine = removeMachine;
                });
            </script>
        <?php endif; ?>
        <?php include __DIR__ . '/footer.html.php'; ?>

    </div> <!-- Fin setup-container -->
</body>

</html>