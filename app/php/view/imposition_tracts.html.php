<?php
$title = "Imposition Tracts";
ob_start();
?>

<div class="container">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <!-- En-tête -->
            <div class="page-header text-center" style="background: linear-gradient(135deg, #ffd93d 0%, #ffb347 100%); padding: 30px; border-radius: 10px; margin-bottom: 30px;">
                <h1 style="color: #333; margin: 0;">
                    <i class="fa fa-copy" style="margin-right: 15px;"></i>
                    Imposition Tracts
                </h1>
                <p class="lead" style="color: #333; margin: 10px 0 0 0; opacity: 0.9;">
                    Dupliquer et optimiser vos tracts sur feuilles A3
                </p>
            </div>

            <!-- Messages d'erreur -->
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

            <!-- Formulaire d'upload -->
            <div class="panel panel-default">
                <div class="panel-body">
                    <form method="POST" enctype="multipart/form-data" class="form-horizontal" id="tractsForm">
                        <div class="file-upload-area" id="fileUploadArea" style="border: 3px dashed #ffd93d; border-radius: 15px; padding: 40px; text-align: center; background: linear-gradient(135deg, #fff8e1 0%, #ffeaa7 100%); transition: all 0.3s ease; cursor: pointer;">
                            <div class="file-upload-icon" style="font-size: 48px; color: #ffd93d; margin-bottom: 20px;">
                                <i class="fa fa-file-pdf-o"></i>
                            </div>
                            <h4 style="color: #333; margin-bottom: 15px;">Glissez-déposez votre tract PDF ici</h4>
                            <p style="color: #666; margin-bottom: 20px;">ou cliquez pour sélectionner un fichier</p>
                            <input type="file" name="pdf_file" id="pdfFile" accept=".pdf" style="display: none;" required>
                            <button type="button" class="btn btn-warning btn-lg" id="selectFileBtn">
                                <i class="fa fa-folder-open"></i> Choisir un tract PDF
                            </button>
                        </div>
                        
                        <div id="fileInfo" style="display: none; margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                            <h5><i class="fa fa-file-pdf-o"></i> Fichier sélectionné :</h5>
                            <p id="fileName" style="margin: 5px 0; font-weight: 500;"></p>
                            <p id="fileSize" style="margin: 5px 0; color: #666; font-size: 0.9em;"></p>
                            <p id="pageCount" style="margin: 5px 0; color: #666; font-size: 0.9em;"></p>
                        </div>

                        <!-- Options d'imposition -->
                        <div id="impositionOptions" style="display: none; margin-top: 30px;">
                            <h4><i class="fa fa-cog"></i> Options d'imposition</h4>
                            
                            <!-- Type de tract détecté -->
                            <div class="form-group">
                                <label class="col-md-4 control-label">Type détecté :</label>
                                <div class="col-md-8">
                                    <div id="tractType" class="form-control-static" style="font-weight: bold; color: #ffd93d;"></div>
                                </div>
                            </div>

                            <!-- Sélection manuelle du format (si détection incorrecte) -->
                            <div class="form-group">
                                <label class="col-md-4 control-label" for="manual_format">Format réel du tract :</label>
                                <div class="col-md-8">
                                    <select name="manual_format" id="manual_format" class="form-control">
                                        <option value="auto">Détection automatique</option>
                                        <option value="A4">A4 (210×297 mm)</option>
                                        <option value="A5">A5 (148×210 mm)</option>
                                        <option value="A6">A6 (105×148 mm)</option>
                                    </select>
                                    <small class="help-block text-muted">Utilisez ceci si la détection automatique est incorrecte</small>
                                </div>
                            </div>

                            <!-- Option de redimensionnement -->
                            <div class="form-group">
                                <label class="col-md-4 control-label">Redimensionnement :</label>
                                <div class="col-md-8">
                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" name="force_resize" id="force_resize" value="1">
                                            Forcer le redimensionnement si le format ne correspond pas
                                        </label>
                                    </div>
                                    <small class="help-block text-muted">Redimensionne automatiquement le PDF pour qu'il corresponde au format sélectionné</small>
                                </div>
                            </div>


                            <!-- Informations sur l'imposition -->
                            <div class="form-group">
                                <label class="col-md-4 control-label">Résultat attendu :</label>
                                <div class="col-md-8">
                                    <div id="impositionResult" class="form-control-static" style="color: #007bff; font-weight: bold;">
                                        Sélectionnez un format pour voir le résultat
                                    </div>
                                </div>
                            </div>

                            <!-- Marge de coupe -->
                            <div class="form-group">
                                <label class="col-md-4 control-label" for="cut_margin">Marge de coupe :</label>
                                <div class="col-md-8">
                                    <select name="cut_margin" id="cut_margin" class="form-control" required>
                                        <option value="0">Aucune marge</option>
                                        <option value="2" selected>2 mm</option>
                                        <option value="3">3 mm</option>
                                        <option value="5">5 mm</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group" style="margin-top: 30px;">
                            <div class="col-md-12 text-center">
                                <button type="submit" name="submit" class="btn btn-warning btn-lg" id="submitBtn" style="padding: 15px 40px; font-size: 16px;">
                                    <i class="fa fa-copy"></i> Créer l'imposition
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Zone d'erreur -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" style="margin-top: 30px;">
                    <h4><i class="fa fa-exclamation-triangle"></i> Erreur</h4>
                    <p><?= htmlspecialchars($error) ?></p>
                </div>
            <?php endif; ?>

            <!-- Zone de résultat -->
            <?php if (!empty($success) || !empty($download_url)): ?>
                <div class="alert alert-success" style="margin-top: 30px;">
                    <h4><i class="fa fa-check-circle"></i> Imposition réussie !</h4>
                    <p>Votre tract a été dupliqué et optimisé avec succès.</p>
                    
                    <?php if (!empty($download_url)): ?>
                        <div style="text-align: center; margin-top: 20px;">
                            <a href="<?= htmlspecialchars($download_url) ?>" class="btn btn-success btn-lg" download>
                                <i class="fa fa-download"></i> Télécharger le PDF optimisé
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($preview_url)): ?>
                        <div style="margin-top: 30px;">
                            <h4><i class="fa fa-eye"></i> Prévisualisation</h4>
                            <div style="border: 2px solid #ddd; border-radius: 8px; overflow: hidden;">
                                <iframe src="<?= htmlspecialchars($preview_url) ?>" width="100%" height="600px" style="border: none;"></iframe>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($result)): ?>
                        <div style="margin-top: 20px; padding: 15px; background: rgba(255,255,255,0.7); border-radius: 8px;">
                            <h5>Informations :</h5>
                            <p><?= htmlspecialchars($result) ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div style="text-align: center; margin-top: 20px;">
                        <button onclick="window.location.href='?imposition_tracts'" class="btn btn-warning">
                            <i class="fa fa-plus"></i> Nouvelle imposition
                        </button>
                        <button onclick="window.location.href='?accueil'" class="btn btn-default">
                            <i class="fa fa-home"></i> Retour à l'accueil
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Instructions -->
            <div class="panel panel-warning" style="margin-top: 30px;">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-info-circle"></i> Comment ça marche ?</h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5><i class="fa fa-file-o" style="color: #ffd93d;"></i> Tract A4</h5>
                            <p>Duplique automatiquement et place 2 copies sur 1 feuille A3.</p>
                        </div>
                        <div class="col-md-6">
                            <h5><i class="fa fa-file-text-o" style="color: #ffd93d;"></i> Tract A5</h5>
                            <p>Duplique automatiquement et place 4 copies sur 1 feuille A3.</p>
                        </div>
                    </div>
                    <div class="row" style="margin-top: 15px;">
                        <div class="col-md-6">
                            <h5><i class="fa fa-th" style="color: #ffd93d;"></i> Tract A6</h5>
                            <p>Duplique automatiquement et place 8 copies sur 1 feuille A3.</p>
                        </div>
                        <div class="col-md-6">
                            <h5><i class="fa fa-refresh" style="color: #ffd93d;"></i> Recto/Verso</h5>
                            <p>Gère automatiquement les tracts recto/verso avec la même logique.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Styles spécifiques pour la page d'imposition tracts */
.file-upload-area:hover {
    border-color: #ff9800 !important;
    background: linear-gradient(135deg, #ffeaa7 0%, #fdcb6e 100%) !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(255, 217, 61, 0.3);
}

.form-control {
    border: 2px solid #e9ecef !important;
    border-radius: 6px !important;
    padding: 12px 15px !important;
    font-size: 16px !important;
    transition: border-color 0.3s ease !important;
    background-color: white !important;
    color: #495057 !important;
    min-height: 48px !important;
    height: auto !important;
    line-height: 1.5 !important;
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
}

select.form-control option {
    background-color: white !important;
    color: #495057 !important;
    padding: 10px !important;
    font-size: 16px !important;
}

.form-control:focus {
    border-color: #ffd93d !important;
    box-shadow: 0 0 0 0.2rem rgba(255, 217, 61, 0.25) !important;
}

.btn-warning {
    background: linear-gradient(135deg, #ffd93d 0%, #ff9800 100%) !important;
    border: none !important;
    border-radius: 6px !important;
    padding: 15px 40px !important;
    font-size: 16px !important;
    font-weight: 500 !important;
    transition: all 0.3s ease !important;
}

.btn-warning:hover {
    background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%) !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(255, 152, 0, 0.3);
}

.btn-warning:disabled {
    background: #ccc !important;
    transform: none !important;
    box-shadow: none !important;
}

.alert-success {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%) !important;
    border-color: #c3e6cb !important;
    color: #155724 !important;
}

.panel-warning {
    border-color: #ffd93d !important;
}

.panel-warning > .panel-heading {
    background: linear-gradient(135deg, #ffd93d 0%, #ffb347 100%) !important;
    border-color: #ffd93d !important;
    color: #333 !important;
}

@media (max-width: 768px) {
    .col-md-offset-2 {
        margin-left: 0 !important;
    }
    
    .file-upload-area {
        padding: 20px !important;
    }
    
    .btn-lg {
        padding: 12px 25px !important;
        font-size: 14px !important;
    }
}
</style>

<script>
$(document).ready(function() {
    // Variables globales
    let detectedFormat = null;
    let pageCount = 0;
    
    // Gestion du drag & drop
    const fileUploadArea = document.getElementById('fileUploadArea');
    const fileInput = document.getElementById('pdfFile');
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const pageCountEl = document.getElementById('pageCount');
    const impositionOptions = document.getElementById('impositionOptions');
    const tractType = document.getElementById('tractType');
    const submitBtn = $('#submitBtn'); // Utiliser jQuery
    const impositionResult = document.getElementById('impositionResult');
    
    // Drag & drop events
    fileUploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        fileUploadArea.style.borderColor = '#ff9800';
        fileUploadArea.style.background = 'linear-gradient(135deg, #ffeaa7 0%, #fdcb6e 100%)';
    });
    
    fileUploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        fileUploadArea.style.borderColor = '#ffd93d';
        fileUploadArea.style.background = 'linear-gradient(135deg, #fff8e1 0%, #ffeaa7 100%)';
    });
    
    fileUploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        fileUploadArea.style.borderColor = '#ffd93d';
        fileUploadArea.style.background = 'linear-gradient(135deg, #fff8e1 0%, #ffeaa7 100%)';
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            updateFileInfo(files[0]);
        }
    });
    
    // Click to upload (seulement sur la zone, pas sur le bouton)
    fileUploadArea.addEventListener('click', function(e) {
        // Ne pas déclencher si on clique sur le bouton
        if (e.target.tagName === 'BUTTON' || e.target.closest('button')) {
            return;
        }
        fileInput.click();
    });
    
    // File input change
    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            updateFileInfo(this.files[0]);
        }
    });
    
    // Bouton de sélection de fichier
    document.getElementById('selectFileBtn').addEventListener('click', function(e) {
        e.stopPropagation(); // Empêcher la propagation vers la zone de drag & drop
        fileInput.click();
    });
    
    function updateFileInfo(file) {
        fileName.textContent = file.name;
        fileSize.textContent = formatFileSize(file.size);
        fileInfo.style.display = 'block';
        
        // Validation du type de fichier
        if (!file.name.toLowerCase().endsWith('.pdf')) {
            alert('Veuillez sélectionner un fichier PDF valide.');
            fileInput.value = '';
            fileInfo.style.display = 'none';
            return;
        }
        
        // Analyser le PDF pour détecter le format et le nombre de pages
        analyzePDF(file);
    }
    
    function analyzePDF(file) {
        // Créer un FormData pour envoyer le fichier
        const formData = new FormData();
        formData.append('pdf_file', file);
        
        // Afficher un indicateur de chargement
        tractType.innerHTML = `<i class="fa fa-spinner fa-spin"></i> Analyse du PDF en cours...`;
        impositionOptions.style.display = 'block';
        submitBtn.prop('disabled', true);
        
        // Envoyer la requête AJAX
        fetch('?imposition_tracts&ajax=analyze_pdf', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return response.text(); // D'abord récupérer comme texte
        })
        .then(text => {
            console.log('Raw response:', text);
            
            try {
                const data = JSON.parse(text);
                console.log('Parsed data:', data);
                
                if (data.success) {
                    detectedFormat = data.format;
                    pageCount = data.page_count;
                    
                    pageCountEl.textContent = `${data.page_count} page(s) détectée(s)`;
                    
                    if (data.format === 'unknown') {
                        tractType.innerHTML = `<strong>Format non standard (${data.dimensions.width}×${data.dimensions.height}mm)</strong><br><small style="color: #ff9800;">Sélectionnez manuellement le format</small>`;
                    } else {
                        tractType.innerHTML = `<strong>Tract ${data.format} (${data.page_count} page${data.page_count > 1 ? 's' : ''})</strong><br><small style="color: #28a745;">Détection automatique</small>`;
                    }
                    
                    // Configurer le mode automatique
                    setupAutomaticMode();
                    
                    // Mettre à jour la sélection manuelle
                    $('#manual_format').val('auto');
                    
                    // Activer le bouton de soumission
                    submitBtn.prop('disabled', false);
                } else {
                    tractType.innerHTML = `<strong style="color: #dc3545;">Erreur d'analyse</strong><br><small style="color: #dc3545;">${data.error}</small>`;
                    submitBtn.prop('disabled', true);
                }
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                tractType.innerHTML = `<strong style="color: #dc3545;">Erreur de format de réponse</strong><br><small style="color: #dc3545;">Réponse serveur invalide</small>`;
                submitBtn.prop('disabled', true);
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            tractType.innerHTML = `<strong style="color: #dc3545;">Erreur de connexion</strong><br><small style="color: #dc3545;">Impossible d'analyser le PDF</small>`;
            submitBtn.prop('disabled', true);
        });
    }
    
    function setupAutomaticMode() {
        if (detectedFormat && pageCount) {
            let recommendedCopies = 2;
            
            switch(detectedFormat) {
                case 'A4':
                    recommendedCopies = pageCount === 1 ? 2 : 2;
                    break;
                case 'A5':
                    recommendedCopies = pageCount === 1 ? 4 : 4;
                    break;
                case 'A6':
                    recommendedCopies = pageCount === 1 ? 8 : 8;
                    break;
            }
            
            // Activer le bouton
            submitBtn.prop('disabled', false);
            
            // Afficher le résultat attendu
            const resultText = `${recommendedCopies} copies de votre tract ${detectedFormat} sur une feuille A3`;
            impositionResult.innerHTML = `<i class="fa fa-check-circle"></i> ${resultText}`;
            impositionResult.style.color = '#28a745';
        }
    }
    
    function setupAutomaticModeForFormat(format) {
        if (format && pageCount) {
            let recommendedCopies = 2;

            switch(format) {
                case 'A4':
                    recommendedCopies = pageCount === 1 ? 2 : 2;
                    break;
                case 'A5':
                    recommendedCopies = pageCount === 1 ? 4 : 4;
                    break;
                case 'A6':
                    recommendedCopies = pageCount === 1 ? 8 : 8;
                    break;
            }
            
            // Activer le bouton
            submitBtn.prop('disabled', false);
            
            // Afficher le résultat attendu avec le format forcé
            const resultText = `${recommendedCopies} copies de votre tract ${format} sur une feuille A3`;
            impositionResult.innerHTML = `<i class="fa fa-check-circle"></i> ${resultText}`;
            impositionResult.style.color = '#ff9800'; // Orange pour indiquer que c'est forcé
            
            // NE PAS modifier tractType - garder le format détecté original
        }
    }
    
            // Pas de gestion de mode - tout est automatique maintenant
    
    // Gestion de la sélection manuelle du format
    $('#manual_format').change(function() {
        const manualFormat = $(this).val();
        if (manualFormat !== 'auto') {
            // NE PAS modifier tractType - garder le format détecté original
            // Seulement recalculer les recommandations avec le nouveau format
            setupAutomaticModeForFormat(manualFormat);
        } else {
            // Revenir à la détection automatique
            setupAutomaticMode();
        }
    });
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    // Validation du formulaire
    $('#tractsForm').submit(function(e) {
        const file = fileInput.files[0];
        const mode = impositionMode.val();
        
        if (!file) {
            e.preventDefault();
            alert('Veuillez sélectionner un fichier PDF.');
            return false;
        }
        
        if (!mode) {
            e.preventDefault();
            alert('Veuillez sélectionner un mode d\'imposition.');
            return false;
        }
        
        // Afficher un indicateur de chargement
        submitBtn.html('<i class="fa fa-spinner fa-spin"></i> Traitement en cours...');
        submitBtn.prop('disabled', true);
    });
});
</script>
