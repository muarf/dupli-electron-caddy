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
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
            appearance: none !important;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6,9 12,15 18,9'%3e%3c/polyline%3e%3c/svg%3e") !important;
            background-repeat: no-repeat !important;
            background-position: right 12px center !important;
            background-size: 16px !important;
            padding-right: 40px !important;
            background-color: white !important;
            color: #495057 !important;
            font-weight: 500 !important;
            font-size: 16px !important;
            line-height: 1.5 !important;
            border: 2px solid #e9ecef !important;
            border-radius: 6px !important;
        }
        select.form-control option {
            background-color: white !important;
            color: #495057 !important;
            font-weight: 500 !important;
            padding: 10px !important;
            font-size: 16px !important;
        }
        /* Style de test pour forcer la visibilité */
        #imposition_type {
            background-color: #fff !important;
            color: #000 !important;
            font-size: 18px !important;
            font-weight: bold !important;
            border: 3px solid #007bff !important;
            min-height: 50px !important;
        }
        #imposition_type option {
            background-color: #fff !important;
            color: #000 !important;
            font-size: 16px !important;
            font-weight: bold !important;
            padding: 15px !important;
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
                <h1><i class="fa fa-file-pdf-o"></i> Imposition PDF</h1>
                <div class="subtitle">Version PHP - Compatible avec tous les formats</div>
            </div>
            
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

                    <div class="text-center">
                        <button type="submit" class="btn btn-impose">
                            <i class="fa fa-magic"></i> Imposer le PDF
                        </button>
                    </div>
                </form>
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
        });
    </script>
</body>
</html>
