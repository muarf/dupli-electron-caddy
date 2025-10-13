<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imposition PDF - Version PHP</title>
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
            background: linear-gradient(135deg, #a8e6cf 0%, #dcedc1 100%);
            color: #2c5530;
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
        .form-control option {
            background-color: white;
            color: #495057;
            font-weight: 500;
            padding: 10px;
        }
        select.form-control {
            background-color: white !important;
            color: #495057 !important;
            font-weight: 500 !important;
            font-size: 16px !important;
            line-height: 1.5 !important;
            border: 2px solid #e9ecef !important;
            border-radius: 6px !important;
            padding: 12px 15px !important;
            min-height: 45px !important;
        }
        select.form-control option {
            background-color: white !important;
            color: #000 !important;
            font-weight: normal !important;
            padding: 12px !important;
            font-size: 16px !important;
        }
        .form-control:focus {
            border-color: #a8e6cf;
            box-shadow: 0 0 0 0.2rem rgba(168, 230, 207, 0.25);
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
            background: linear-gradient(135deg, #a8e6cf 0%, #dcedc1 100%);
            border: none;
            border-radius: 6px;
            padding: 15px 40px;
            font-size: 18px;
            font-weight: 500;
            color: #2c5530;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .btn-impose:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(168, 230, 207, 0.4);
            color: #2c5530;
        }
        .btn-impose:active {
            transform: translateY(0);
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
        .result-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 20px;
            margin: 15px 0;
        }
        .result-card h3 {
            color: #495057;
            margin-bottom: 15px;
            font-weight: 500;
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
        .loading {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        .file-upload-area {
            border: 3px dashed #a8e6cf;
            border-radius: 12px;
            padding: 60px 20px;
            text-align: center;
            background: linear-gradient(135deg, #f8fffe 0%, #f0f9f5 100%);
            transition: all 0.3s ease;
            cursor: pointer;
            margin: 20px 0;
        }
        .file-upload-area:hover {
            border-color: #dcedc1;
            background: linear-gradient(135deg, #f0f9f5 0%, #e8f5f0 100%);
            transform: translateY(-2px);
        }
        .file-upload-area.dragover {
            border-color: #2c5530;
            background: linear-gradient(135deg, #e8f5f0 0%, #dcedc1 100%);
            transform: scale(1.02);
        }
        .file-upload-icon {
            font-size: 4em;
            color: #a8e6cf;
            margin-bottom: 20px;
        }
        .file-upload-text {
            font-size: 1.4em;
            color: #2c5530;
            font-weight: 500;
            margin-bottom: 10px;
        }
        .file-upload-subtext {
            color: #6c757d;
            font-size: 1em;
        }
        .file-selected {
            background: linear-gradient(135deg, #dcedc1 0%, #a8e6cf 100%);
            border-color: #2c5530;
            color: #2c5530;
        }
        .file-selected .file-upload-icon {
            color: #2c5530;
        }
        .file-selected .file-upload-text {
            color: #2c5530;
        }
        #fileInput {
            display: none;
        }
        
        /* Styles responsive pour l'affichage des pages */
        @media (max-width: 768px) {
            .sheet-content {
                flex-direction: column !important;
            }
            .recto-side {
                border-right: none !important;
                border-bottom: 1px solid #e9ecef !important;
            }
            .page-number {
                min-width: 35px !important;
                padding: 6px 8px !important;
                font-size: 14px !important;
            }
        }
        
        /* Animation pour les pages */
        .page-number {
            transition: all 0.3s ease;
        }
        .page-number:hover {
            background: #e3f2fd !important;
            border-color: #2196f3 !important;
            transform: scale(1.05);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="main-container">
            <div class="page-header text-center">
                <h1><i class="fa fa-magic"></i> Imposer un PDF</h1>
                <div class="subtitle">Créer un livret A3 à partir de pages A4</div>
            </div>

            <?php if ($success): ?>
                <div class="result-section">
                    <div class="alert alert-success">
                        <h4><i class="fa fa-check-circle"></i> Succès !</h4>
                        <p class="mb-0"><?= htmlspecialchars($result) ?></p>
                    </div>

                    <?php if ($page_count > 0): ?>
                        <div class="result-card">
                            <h3><i class="fa fa-info-circle"></i> Informations du PDF</h3>
                            <p><strong>Nombre de pages :</strong> <?= $page_count ?></p>
                            <p><strong>Ordre des pages réorganisées :</strong></p>
                            <div class="page-sequence-container" style="background: white; border: 1px solid #ddd; padding: 20px; border-radius: 8px; margin-top: 15px;">
                                <?php 
                                // Diviser la séquence en groupes selon le type d'imposition
                                $pages_array = explode(', ', $ordered_pages);
                                $total_pages = count($pages_array);
                                
                                // Déterminer le type d'imposition depuis le POST
                                $imposition_type = isset($_POST['imposition_type']) ? $_POST['imposition_type'] : 'a5';
                                
                                if ($imposition_type === 'a6') {
                                    $pages_per_sheet = 16; // 8 recto + 8 verso pour A6
                                    $recto_count = 8;
                                    $verso_count = 8;
                                } else {
                                    $pages_per_sheet = 8; // 4 recto + 4 verso pour A5
                                    $recto_count = 4;
                                    $verso_count = 4;
                                }
                                
                                $num_sheets = ceil($total_pages / $pages_per_sheet);
                                
                                // Afficher seulement la première feuille
                                $sheet_pages = array_slice($pages_array, 0, $pages_per_sheet);
                                $recto_pages = array_slice($sheet_pages, 0, $recto_count);
                                $verso_pages = array_slice($sheet_pages, $recto_count, $verso_count);
                                ?>
                                
                                <div class="sheet-info" style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 20px; text-align: center;">
                                    <h4 style="margin: 0; color: #495057;">
                                        <i class="fa fa-info-circle"></i> 
                                        <?= $num_sheets ?> feuille<?= $num_sheets > 1 ? 's' : '' ?> A3 nécessaire<?= $num_sheets > 1 ? 's' : '' ?>
                                        (<?= $total_pages ?> pages au total)
                                    </h4>
                                </div>
                                
                                <div class="sheet-group" style="margin-bottom: 25px; border: 2px solid #e9ecef; border-radius: 8px; overflow: hidden;">
                                    <div class="sheet-header" style="background: linear-gradient(135deg, #a8e6cf 0%, #dcedc1 100%); padding: 10px; text-align: center; font-weight: bold; color: #2c5530;">
                                        Exemple - Feuille 1 (<?= $page_count ?> pages)
                                    </div>
                                    <div class="sheet-content" style="display: flex; background: white;">
                                        <div class="recto-side" style="flex: 1; padding: 15px; border-right: 1px solid #e9ecef;">
                                            <div class="side-label" style="background: #007bff; color: white; padding: 5px 10px; border-radius: 4px; font-size: 12px; font-weight: bold; margin-bottom: 10px; display: inline-block;">
                                                RECTO (<?= $recto_count ?> pages)
                                            </div>
                                            <div class="page-numbers" style="font-family: monospace; font-size: 16px; line-height: 1.8;">
                                                <?php foreach ($recto_pages as $index => $page): ?>
                                                    <span class="page-number" style="display: inline-block; background: #f8f9fa; border: 1px solid #dee2e6; padding: 8px 12px; margin: 2px; border-radius: 4px; min-width: 40px; text-align: center; font-weight: bold;">
                                                        <?= htmlspecialchars(trim($page)) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <div class="verso-side" style="flex: 1; padding: 15px;">
                                            <div class="side-label" style="background: #28a745; color: white; padding: 5px 10px; border-radius: 4px; font-size: 12px; font-weight: bold; margin-bottom: 10px; display: inline-block;">
                                                VERSO (<?= $verso_count ?> pages)
                                            </div>
                                            <div class="page-numbers" style="font-family: monospace; font-size: 16px; line-height: 1.8;">
                                                <?php foreach ($verso_pages as $index => $page): ?>
                                                    <span class="page-number" style="display: inline-block; background: #f8f9fa; border: 1px solid #dee2e6; padding: 8px 12px; margin: 2px; border-radius: 4px; min-width: 40px; text-align: center; font-weight: bold;">
                                                        <?= htmlspecialchars(trim($page)) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Séquence complète en format compact -->
                                <div class="full-sequence" style="margin-top: 20px; padding-top: 15px; border-top: 2px solid #e9ecef;">
                                    <div class="sequence-label" style="font-weight: bold; color: #495057; margin-bottom: 10px;">
                                        <i class="fa fa-list"></i> Séquence complète :
                                    </div>
                                    <div class="sequence-text" style="font-family: monospace; font-size: 14px; background: #f8f9fa; padding: 15px; border-radius: 4px; word-break: break-all; line-height: 1.4;">
                                        <?= htmlspecialchars($ordered_pages) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($preview_url): ?>
                        <div class="result-card">
                            <h3><i class="fa fa-eye"></i> Prévisualisation</h3>
                            <div class="pdf-preview">
                                <iframe src="<?= htmlspecialchars($preview_url) ?>" width="100%" height="600px" style="border: none;"></iframe>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($download_url): ?>
                        <div class="text-center">
                            <a href="<?= htmlspecialchars($download_url) ?>" target="_blank" class="btn btn-download" onclick="openPdfInApp('<?= htmlspecialchars($download_url) ?>')">
                                <i class="fa fa-download"></i> Télécharger le PDF imposé
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="form-section">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <h4><i class="fa fa-exclamation-triangle"></i> Erreurs détectées :</h4>
                        <ul class="mb-3">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="mt-3">
                            <button onclick="history.back()" class="btn btn-secondary me-2">
                                <i class="fa fa-arrow-left"></i> Retour
                            </button>
                            <button onclick="location.reload()" class="btn btn-primary me-2">
                                <i class="fa fa-refresh"></i> Recharger
                            </button>
                            <button onclick="window.location.href='?accueil'" class="btn btn-success">
                                <i class="fa fa-home"></i> Accueil
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="form-horizontal">
                    <div class="file-upload-area" id="fileUploadArea">
                        <div class="file-upload-icon">
                            <i class="fa fa-cloud-upload"></i>
                        </div>
                        <div class="file-upload-text" id="uploadText">
                            Glissez-déposez votre PDF ici
                        </div>
                        <div class="file-upload-subtext" id="uploadSubtext">
                            ou cliquez pour sélectionner un fichier
                        </div>
                        <input type="file" name="pdf" id="pdf" accept="application/pdf" required style="display: none;">
                    </div>

                    <div class="form-group">
                        <label for="imposition_type"><i class="fa fa-cogs"></i> Type d'imposition :</label>
                        <select name="imposition_type" id="imposition_type" class="form-control">
                            <option value="a5">8 pages A5 par A3 (4 recto + 4 verso)</option>
                            <option value="a6">16 pages A6 par A3 (8 recto + 8 verso)</option>
                        </select>
                    </div>

                    <div class="checkbox-group">
                        <div class="row">
                            <div class="col-md-6">
                                <label for="preview">
                                    <input type="checkbox" name="preview" id="preview">
                                    <i class="fa fa-eye"></i> Prévisualiser avec numéros de page
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label for="force_resize">
                                    <input type="checkbox" name="force_resize" id="force_resize">
                                    <i class="fa fa-expand"></i> Forcer le redimensionnement
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="checkbox-group">
                        <div class="row">
                            <div class="col-md-12">
                                <label for="add_crop_marks">
                                    <input type="checkbox" name="add_crop_marks" id="add_crop_marks">
                                    <i class="fa fa-scissors"></i> Ajouter les traits de coupe
                                </label>
                            </div>
                        </div>
                        
                        <div id="crop_marks_options" style="display: none; margin-top: 15px; padding-left: 30px;">
                            <div class="form-group">
                                <label for="imposition_mode"><i class="fa fa-book"></i> Mode d'imposition :</label>
                                <select name="imposition_mode" id="imposition_mode" class="form-control">
                                    <option value="brochure">Mode brochure (sans marges intérieures)</option>
                                    <option value="livre">Mode livre (avec marges intérieures)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="bleed_mode"><i class="fa fa-arrows-alt"></i> Gestion du format :</label>
                                <select name="bleed_mode" id="bleed_mode" class="form-control">
                                    <option value="fullsize">Fond perdu (pages en taille réelle)</option>
                                    <option value="resize">Redimensionner (réduire les pages)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="bleed_size"><i class="fa fa-ruler"></i> Taille marge de coupe (mm) :</label>
                                <input type="number" name="bleed_size" id="bleed_size" class="form-control" value="3" min="1" max="10" step="0.5">
                            </div>

                            <div class="form-group">
                                <label for="crop_marks_type"><i class="fa fa-scissors"></i> Type de traits de coupe :</label>
                                <select name="crop_marks_type" id="crop_marks_type" class="form-control">
                                    <option value="normal">Traits de coupe normaux (coins)</option>
                                    <option value="central">Traits de coupe centraux (A3→A4)</option>
                                    <option value="both">Les deux types</option>
                                </select>
                                <small class="help-block text-muted">
                                    <strong>Normaux :</strong> Traits aux 4 coins<br>
                                    <strong>Centraux :</strong> Trait au milieu selon orientation (21cm)<br>
                                    <strong>Les deux :</strong> Combinaison des deux
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="text-center">
                        <button type="submit" class="btn btn-impose">
                            <i class="fa fa-magic"></i> Imposer le PDF
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>

    <script src="js/bootstrap.min.js"></script>
    <script>
        // Gestion des erreurs JavaScript
        window.addEventListener('error', function(e) {
            console.error('Erreur JavaScript:', e.error);
        });

        // Fonction pour ouvrir un PDF dans l'application
        async function openPdfInApp(pdfUrl) {
            try {
                // Vérifier si on est dans Tauri
                if (window.__TAURI__) {
                    const { invoke } = window.__TAURI__.tauri;
                    
                    // Construire le chemin local du fichier
                    const localPath = './app/public/' + pdfUrl;
                    
                    // Essayer d'ouvrir le fichier avec l'application par défaut
                    await invoke('open_file', { filePath: localPath });
                } else {
                    // Fallback pour navigateur web
                    window.open(pdfUrl, '_blank');
                }
            } catch (error) {
                console.error('Erreur lors de l\'ouverture du PDF:', error);
                // Fallback: ouvrir dans un nouvel onglet
                window.open(pdfUrl, '_blank');
            }
        }
        
        // Gestion des erreurs de soumission de formulaire
        document.querySelector('form').addEventListener('submit', function(e) {
            const fileInput = document.getElementById('pdf');
            if (!fileInput.files || fileInput.files.length === 0) {
                e.preventDefault();
                alert('Veuillez sélectionner un fichier PDF avant de continuer.');
                return false;
            }
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fileUploadArea = document.getElementById('fileUploadArea');
            const fileInput = document.getElementById('pdf');
            const uploadText = document.getElementById('uploadText');
            const uploadSubtext = document.getElementById('uploadSubtext');

            // Clic sur la zone pour ouvrir le sélecteur de fichier
            fileUploadArea.addEventListener('click', function() {
                fileInput.click();
            });

            // Gestion du changement de fichier
            fileInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    const fileName = this.files[0].name;
                    fileUploadArea.classList.add('file-selected');
                    uploadText.innerHTML = '<i class="fa fa-file-pdf-o"></i> ' + fileName;
                    uploadSubtext.textContent = 'Cliquez pour changer de fichier';
                }
            });

            // Gestion du drag & drop
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

            // Empêcher le comportement par défaut du drag & drop sur la page
            document.addEventListener('dragover', function(e) {
                e.preventDefault();
            });

            document.addEventListener('drop', function(e) {
                e.preventDefault();
            });

            // Gestion de l'affichage des options traits de coupe
            const addCropMarks = document.getElementById('add_crop_marks');
            const cropMarksOptions = document.getElementById('crop_marks_options');

            if (addCropMarks && cropMarksOptions) {
                addCropMarks.addEventListener('change', function() {
                    if (this.checked) {
                        cropMarksOptions.style.display = 'block';
                    } else {
                        cropMarksOptions.style.display = 'none';
                    }
                });
            }
        });
    </script>
</body>
</html>
