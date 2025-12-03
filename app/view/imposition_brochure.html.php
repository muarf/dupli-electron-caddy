<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imposition Brochure (Leaflet) - Version PHP</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/font-awesome.min.css" rel="stylesheet">
    <style>
        body { 
            background-color: #f8f9fa; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .main-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-top: 30px;
            margin-bottom: 30px;
        }
        .page-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 30px;
            border-radius: 8px 8px 0 0;
            margin-bottom: 0;
        }
        .page-header h1 {
            margin: 0;
            font-weight: 300;
            font-size: 2.2em;
        }
        .page-header .subtitle {
            opacity: 0.9;
            font-size: 1.1em;
            margin-top: 5px;
        }
        .form-section {
            padding: 40px;
        }
        .form-group label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 8px;
        }
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 6px;
            padding: 12px 15px;
            font-size: 16px;
            transition: border-color 0.3s ease;
            background-color: white;
            color: #495057;
            font-weight: 500;
        }
        .form-control:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        select.form-control {
            height: auto !important;
            color: #495057 !important;
            background-color: white !important;
            -webkit-appearance: menulist; /* Force l'apparence standard pour éviter les problèmes */
            appearance: menulist;
        }
        select.form-control option {
            color: #495057 !important;
            background-color: white !important;
            padding: 10px;
        }
        .checkbox-group {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }
        .checkbox-group label {
            font-weight: normal;
            color: #6c757d;
            margin-bottom: 0;
            cursor: pointer;
        }
        .checkbox-group input[type="checkbox"] {
            margin-right: 10px;
            transform: scale(1.2);
        }
        .btn-impose {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            border-radius: 6px;
            padding: 15px 40px;
            font-size: 18px;
            font-weight: 500;
            color: white;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .btn-impose:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.4);
            color: white;
        }
        .result-section {
            padding: 40px;
            border-top: 1px solid #e9ecef;
        }
        .alert {
            border: none;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
        }
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
        }
        .file-upload-area {
            border: 3px dashed #28a745;
            border-radius: 12px;
            padding: 60px 20px;
            text-align: center;
            background: linear-gradient(135deg, #f8fffe 0%, #f0f9f5 100%);
            transition: all 0.3s ease;
            cursor: pointer;
            margin: 20px 0;
        }
        .file-upload-area:hover {
            border-color: #20c997;
            background: linear-gradient(135deg, #f0f9f5 0%, #e8f5f0 100%);
            transform: translateY(-2px);
        }
        .file-upload-area.dragover {
            border-color: #155724;
            background: linear-gradient(135deg, #e8f5f0 0%, #dcedc1 100%);
            transform: scale(1.02);
        }
        .file-upload-icon {
            font-size: 4em;
            color: #28a745;
            margin-bottom: 20px;
        }
        .file-upload-text {
            font-size: 1.4em;
            color: #155724;
            font-weight: 500;
            margin-bottom: 10px;
        }
        .file-upload-subtext {
            color: #6c757d;
            font-size: 1em;
        }
        .file-selected {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border-color: #155724;
            color: #155724;
        }
        .file-selected .file-upload-icon {
            color: #155724;
        }
        .file-selected .file-upload-text {
            color: #155724;
        }
        #fileInput {
            display: none;
        }
        .pdf-preview {
            border: 2px solid #e9ecef;
            border-radius: 6px;
            margin: 20px 0;
            background: white;
        }
        .btn-download {
            background: #28a745;
            border: none;
            border-radius: 6px;
            padding: 12px 25px;
            color: white;
            font-weight: 500;
            transition: background-color 0.3s ease;
        }
        .btn-download:hover {
            background: #218838;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="main-container">
            <div class="page-header text-center">
                <h1><i class="fa fa-book"></i> Imposition Brochure (Leaflet)</h1>
                <div class="subtitle">Génération de livrets pour impression recto-verso</div>
            </div>

            <?php if (isset($success) && $success): ?>
                <div class="result-section">
                    <div class="alert alert-success">
                        <h4><i class="fa fa-check-circle"></i> Succès</h4>
                        <p class="mb-0"><?= htmlspecialchars($result) ?></p>
                    </div>

                    <?php if (isset($preview_url) && $preview_url): ?>
                        <div class="result-card" style="background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; padding: 20px; margin: 15px 0;">
                            <h3><i class="fa fa-eye"></i> Aperçu</h3>
                            <div class="pdf-preview">
                                <iframe src="<?= htmlspecialchars($preview_url) ?>" width="100%" height="600px" style="border: none;"></iframe>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($download_url) && $download_url): ?>
                        <div class="text-center">
                            <a href="<?= htmlspecialchars($download_url) ?>" class="btn btn-download">
                                <i class="fa fa-download"></i> Télécharger le PDF imposé
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="form-section">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <h4><i class="fa fa-exclamation-triangle"></i> Erreurs détectées</h4>
                        <ul class="mb-3">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="form-horizontal">
                    <input type="hidden" name="lib_file_id" id="lib_file_id" value="<?= isset($from_lib_file) ? $from_lib_file['id'] : '' ?>">
                    
                    <div class="text-end mb-2">
                        <a href="?bibliotheque" class="btn btn-outline-primary btn-sm">
                            <i class="fa fa-book"></i> Ouvrir la bibliothèque
                        </a>
                    </div>

                    <div class="file-upload-area" id="fileUploadArea">
                        <div class="file-upload-icon">
                            <i class="fa fa-cloud-upload"></i>
                        </div>
                        <div class="file-upload-text" id="uploadText">
                            Glissez-déposez votre fichier PDF ici
                        </div>
                        <div class="file-upload-subtext" id="uploadSubtext">
                            ou cliquez pour sélectionner un fichier
                        </div>
                        <input type="file" name="pdf" id="pdf" accept="application/pdf" required style="display: none;">
                    </div>

                    <div class="form-group">
                        <label for="output_format"><i class="fa fa-file-o"></i> Format de sortie :</label>
                        <select name="output_format" id="output_format" class="form-control">
                            <option value="A3" selected>A3 (420×297 mm / 297×420 mm)</option>
                            <option value="A4">A4 (210×297 mm / 297×210 mm)</option>
                        </select>
                        <small class="form-text text-muted">Choisissez le format de la feuille d'impression</small>
                    </div>

                    <div class="form-group">
                        <label for="n_up"><i class="fa fa-cogs"></i> Format de Livret :</label>
                        <select name="n_up" id="n_up" class="form-control">
                            <option value="2" selected>2 poses (Standard 1 pli)</option>
                            <option value="4">4 poses (2 livrets Tête-Bêche)</option>
                            <option value="8">8 poses (4 livrets Tête-Bêche)</option>
                        </select>
                        <small class="form-text text-muted">Note : L'imposition génère automatiquement les pages Recto et Verso.</small>
                    </div>

                    <div class="form-group">
                        <label><i class="fa fa-expand"></i> Mode de redimensionnement :</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="resize_mode" id="mode_percent" value="percent" checked>
                            <label class="form-check-label" for="mode_percent">Échelle en Pourcentage (%)</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="resize_mode" id="mode_mm" value="mm">
                            <label class="form-check-label" for="mode_mm">Dimensions Cibles (mm)</label>
                        </div>
                    </div>

                    <div id="block_percent">
                        <div class="form-group">
                            <label for="scale">Échelle (%) :</label>
                            <input type="number" class="form-control" id="scale" name="scale" value="100" min="1" max="200" step="1">
                        </div>
                    </div>

                    <div id="block_mm" style="display:none;">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="target_width">Largeur Cible (mm) :</label>
                                    <input type="number" class="form-control" id="target_width" name="target_width" placeholder="ex: 105" step="0.1">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="target_height">Hauteur Cible (mm) :</label>
                                    <input type="number" class="form-control" id="target_height" name="target_height" placeholder="ex: 148" step="0.1">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fa fa-arrows-alt"></i> Gouttières :</label>
                        <div class="row">
                            <div class="col-md-4">
                                <label for="gutter_x">Horizontale (X) (mm) :</label>
                                <input type="number" class="form-control" id="gutter_x" name="gutter_x" value="0" min="0" step="0.5">
                            </div>
                            <div class="col-md-4">
                                <label for="gutter_y">Verticale (Y) (mm) :</label>
                                <input type="number" class="form-control" id="gutter_y" name="gutter_y" value="0" min="0" step="0.5">
                            </div>
                            <div class="col-md-4">
                                <label for="gutter_strategy">Si manque de place :</label>
                                <select name="gutter_strategy" id="gutter_strategy" class="form-control">
                                    <option value="reduce">Réduire (Scale)</option>
                                    <option value="crop">Rogner (Crop)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="checkbox-group">
                        <div class="row">
                            <div class="col-md-4">
                                <label for="preview">
                                    <input type="checkbox" name="preview" id="preview">
                                    <i class="fa fa-eye"></i> Preview avec numéros de pages
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label for="crop_marks">
                                    <input type="checkbox" name="crop_marks" id="crop_marks" value="1">
                                    <i class="fa fa-scissors"></i> Ajouter des traits de coupe
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label for="add_page_numbers_in_gutters">
                                    <input type="checkbox" name="add_page_numbers_in_gutters" id="add_page_numbers_in_gutters" value="1">
                                    <i class="fa fa-sort-numeric-asc"></i> Numéros dans les gouttières
                                </label>
                            </div>
                        </div>
                    </div>

                    <div id="crop_settings" style="display:none; margin-top: 15px; padding-left: 30px;">
                        <div class="form-group">
                            <label for="crop_style">Style des traits :</label>
                            <select class="form-control" name="crop_style" id="crop_style">
                                <option value="standard">Standard (Autour de chaque pose)</option>
                                <option value="spreads" selected>Double Pose (Autour de chaque paire)</option>
                                <option value="booklet">Ligne Entière (Coins extérieurs de la ligne)</option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="crop_mark_len">Longueur du trait (mm) :</label>
                                    <input type="number" class="form-control" id="crop_mark_len" name="crop_mark_len" value="5" min="1">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="crop_mark_width">Épaisseur (mm) :</label>
                                    <input type="number" class="form-control" id="crop_mark_width" name="crop_mark_width" value="0.1" min="0.1" step="0.1">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center">
                        <button type="submit" class="btn btn-impose">
                            <i class="fa fa-magic"></i> Générer le PDF
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>

    <script src="js/bootstrap.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fileUploadArea = document.getElementById('fileUploadArea');
            const fileInput = document.getElementById('pdf');
            const uploadText = document.getElementById('uploadText');
            const uploadSubtext = document.getElementById('uploadSubtext');
            const libFileId = document.getElementById('lib_file_id');

            <?php if (isset($from_lib_file)): ?>
            // Pré-remplissage si fichier bibliothèque
            fileUploadArea.classList.add('file-selected');
            uploadText.innerHTML = '<i class="fa fa-file-pdf-o"></i> ' + <?= json_encode($from_lib_file['filename']) ?>;
            uploadSubtext.textContent = 'Fichier sélectionné depuis la bibliothèque';
            document.getElementById('pdf').removeAttribute('required');
            <?php endif; ?>

            fileUploadArea.addEventListener('click', function() {
                fileInput.click();
            });

            fileInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    const fileName = this.files[0].name;
                    fileUploadArea.classList.add('file-selected');
                    uploadText.innerHTML = '<i class="fa fa-file-pdf-o"></i> ' + fileName;
                    uploadSubtext.textContent = 'Cliquez pour changer de fichier';
                    
                    // Reset bibliothèque selection
                    if (libFileId) libFileId.value = '';
                }
            });

            fileUploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                fileUploadArea.classList.add('dragover');
            });

            fileUploadArea.addEventListener('dragleave', function(e) {
                e.preventDefault();
                fileUploadArea.classList.remove('dragover');
            });

            fileUploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                fileUploadArea.classList.remove('dragover');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    const file = files[0];
                    if (file.type === 'application/pdf') {
                        fileInput.files = files;
                        fileUploadArea.classList.add('file-selected');
                        uploadText.innerHTML = '<i class="fa fa-file-pdf-o"></i> ' + file.name;
                        uploadSubtext.textContent = 'Cliquez pour changer de fichier';
                    } else {
                        alert('Veuillez sélectionner un fichier PDF.');
                    }
                }
            });

            const modePercent = document.getElementById('mode_percent');
            const modeMm = document.getElementById('mode_mm');
            const blockPercent = document.getElementById('block_percent');
            const blockMm = document.getElementById('block_mm');

            function updateResizeMode() {
                if (modePercent.checked) {
                    blockPercent.style.display = 'block';
                    blockMm.style.display = 'none';
                } else {
                    blockPercent.style.display = 'none';
                    blockMm.style.display = 'block';
                }
            }
            modePercent.addEventListener('change', updateResizeMode);
            modeMm.addEventListener('change', updateResizeMode);

            const cropCheck = document.getElementById('crop_marks');
            const cropSettings = document.getElementById('crop_settings');
            if (cropCheck && cropSettings) {
                cropCheck.addEventListener('change', function() {
                    cropSettings.style.display = this.checked ? 'block' : 'none';
                });
            }
        });
    </script>
</body>
</html>

