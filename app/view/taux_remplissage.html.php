<div class="container">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <!-- En-tête -->
            <div class="page-header text-center" style="background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%); padding: 30px; border-radius: 10px; margin-bottom: 30px;">
                <h1 style="color: #333; margin: 0;">
                    <i class="fa fa-bar-chart" style="margin-right: 15px;"></i>
                    Calcul du Taux de Remplissage
                </h1>
                <p class="lead" style="color: #666; margin: 10px 0 0 0;">
                    Analysez le pourcentage d'encre utilisé dans vos PDF et images
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
                </div>
            <?php endif; ?>

            <!-- Résultat (affiché en priorité si présent) -->
            <?php if ($success && !empty($result)): ?>
                <div class="panel panel-success">
                    <div class="panel-heading">
                        <h3 class="panel-title">
                            <i class="fa fa-check-circle"></i> Analyse terminée !
                        </h3>
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <!-- Aperçu de l'image -->
                            <div class="col-md-5">
                                <h4 style="margin-top: 0;">Aperçu :</h4>
                                <div class="thumbnail">
                                    <img src="<?= htmlspecialchars($result['preview_url']) ?>" alt="Aperçu" style="max-width: 100%; height: auto; border: 1px solid #ddd;">
                                    <div class="caption text-center">
                                        <small class="text-muted"><?= htmlspecialchars($result['filename']) ?></small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Statistiques -->
                            <div class="col-md-7">
                                <h4 style="margin-top: 0;">Résultats de l'analyse :</h4>
                                
                                <!-- Taux de remplissage principal -->
                                <div style="background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%); padding: 20px; border-radius: 10px; margin-bottom: 20px; text-align: center;">
                                    <h2 style="margin: 0; color: #333; font-size: 48px; font-weight: bold;">
                                        <?= $result['fill_rate'] ?>%
                                    </h2>
                                    <p style="margin: 5px 0 0 0; color: #666; font-size: 18px;">
                                        Taux de remplissage
                                    </p>
                                </div>
                                
                                <!-- Barre de progression -->
                                <div class="progress" style="height: 30px; margin-bottom: 20px;">
                                    <div class="progress-bar progress-bar-success" role="progressbar" 
                                         style="width: <?= $result['fill_rate'] ?>%; line-height: 30px; font-size: 16px; font-weight: bold;">
                                        <?= $result['fill_rate'] ?>% rempli
                                    </div>
                                    <div class="progress-bar progress-bar-default" role="progressbar" 
                                         style="width: <?= $result['empty_rate'] ?>%; line-height: 30px; font-size: 16px;">
                                        <?= $result['empty_rate'] ?>% vide
                                    </div>
                                </div>
                                
                                <!-- Détails techniques -->
                                <div class="panel panel-default">
                                    <div class="panel-heading">
                                        <strong><i class="fa fa-cog"></i> Détails techniques</strong>
                                    </div>
                                    <div class="panel-body">
                                        <table class="table table-condensed" style="margin-bottom: 0;">
                                            <tr>
                                                <td><strong>Dimensions :</strong></td>
                                                <td><?= number_format($result['width']) ?> × <?= number_format($result['height']) ?> pixels</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Pixels totaux :</strong></td>
                                                <td><?= number_format($result['total_pixels']) ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Pixels remplis :</strong></td>
                                                <td style="color: #28a745; font-weight: bold;"><?= number_format($result['filled_pixels']) ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Pixels vides :</strong></td>
                                                <td style="color: #6c757d;"><?= number_format($result['empty_pixels']) ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Tolérance utilisée :</strong></td>
                                                <td><?= $result['tolerance'] ?> / 255</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                
                                <!-- Couleurs dominantes -->
                                <?php if (!empty($result['top_colors'])): ?>
                                <div class="panel panel-default">
                                    <div class="panel-heading">
                                        <strong><i class="fa fa-palette"></i> Couleurs principales</strong>
                                    </div>
                                    <div class="panel-body">
                                        <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                                            <?php 
                                            $color_count = 0;
                                            foreach ($result['top_colors'] as $color => $count): 
                                                if ($color_count++ >= 8) break; // Limiter à 8 couleurs
                                                $percentage = round(($count / $result['total_pixels']) * 100, 2);
                                            ?>
                                                <div style="text-align: center;">
                                                    <div style="width: 50px; height: 50px; background-color: #<?= $color ?>; border: 2px solid #ddd; border-radius: 5px; margin-bottom: 5px;"></div>
                                                    <small style="display: block; font-size: 10px;">#<?= $color ?></small>
                                                    <small style="display: block; font-weight: bold;"><?= $percentage ?>%</small>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Interprétation -->
                                <div class="alert alert-info">
                                    <strong><i class="fa fa-lightbulb-o"></i> Interprétation :</strong>
                                    <?php if ($result['fill_rate'] < 5): ?>
                                        <p>Document très peu chargé. Consommation d'encre minimale.</p>
                                    <?php elseif ($result['fill_rate'] < 20): ?>
                                        <p>Document peu chargé. Consommation d'encre faible.</p>
                                    <?php elseif ($result['fill_rate'] < 50): ?>
                                        <p>Document moyennement chargé. Consommation d'encre modérée.</p>
                                    <?php elseif ($result['fill_rate'] < 75): ?>
                                        <p>Document bien chargé. Consommation d'encre élevée.</p>
                                    <?php else: ?>
                                        <p>Document très chargé. Consommation d'encre très élevée.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Boutons d'action -->
                        <div class="text-center" style="margin-top: 20px;">
                            <a href="?taux_remplissage" class="btn btn-primary btn-lg">
                                <i class="fa fa-plus"></i> Analyser un autre document
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Formulaire d'upload -->
            <div class="panel panel-default">
                <div class="panel-body">
                    <form method="POST" enctype="multipart/form-data" id="fillRateForm">
                        <!-- Options de tolérance -->
                        <div class="row" style="margin-bottom: 20px;">
                            <div class="col-md-12">
                                <div class="panel panel-info">
                                    <div class="panel-heading">
                                        <h4 class="panel-title">
                                            <i class="fa fa-sliders"></i> Paramètres d'analyse
                                        </h4>
                                    </div>
                                    <div class="panel-body">
                                        <div class="form-group">
                                            <label for="tolerance">
                                                <strong>Tolérance pour le blanc :</strong>
                                                <span class="text-muted">(0 = stricte, 255 = permissive)</span>
                                            </label>
                                            <div class="row">
                                                <div class="col-md-8">
                                                    <input type="range" class="form-control" name="tolerance" id="tolerance" min="0" max="255" value="245" style="width: 100%;">
                                                </div>
                                                <div class="col-md-4">
                                                    <input type="number" class="form-control" id="toleranceValue" min="0" max="255" value="245" readonly style="text-align: center; font-weight: bold;">
                                                </div>
                                            </div>
                                            <p class="text-muted" style="margin-top: 10px; font-size: 12px;">
                                                <i class="fa fa-info-circle"></i> La tolérance détermine quels pixels sont considérés comme "blancs" (vides). Une valeur de 245 est recommandée.
                                            </p>
                                        </div>
                                        
                                        <div class="form-group" id="pageNumberGroup" style="display: none;">
                                            <label for="page_number">
                                                <strong>Numéro de page à analyser :</strong>
                                                <span class="text-muted">(pour les PDF multi-pages)</span>
                                            </label>
                                            <input type="number" class="form-control" name="page_number" id="page_number" min="1" value="1" style="max-width: 200px;">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Zone d'upload -->
                        <div id="fileUploadArea" style="border: 3px dashed #84fab0; border-radius: 15px; padding: 40px; text-align: center; background: linear-gradient(135deg, #f0fff4 0%, #e0f7fa 100%); transition: all 0.3s ease; cursor: pointer;">
                            <div style="font-size: 48px; color: #84fab0; margin-bottom: 20px;">
                                <i class="fa fa-file-image-o"></i>
                            </div>
                            <div id="uploadText">
                                <h3 style="color: #333; margin-bottom: 10px;">Glissez votre PDF ou image ici</h3>
                                <p style="color: #666; margin-bottom: 20px;">ou cliquez pour sélectionner un fichier</p>
                                <input type="file" name="file" id="file" accept="application/pdf,.pdf,image/jpeg,image/jpg,image/png,image/gif" style="display: none;" required>
                                <button type="button" class="btn btn-lg" style="background: #84fab0; border: none; color: white; padding: 12px 30px; border-radius: 25px;">
                                    <i class="fa fa-upload"></i> Sélectionner un fichier
                                </button>
                                <p class="text-muted" style="margin-top: 10px; font-size: 12px;">
                                    <i class="fa fa-info-circle"></i> PDF, JPEG, PNG ou GIF - Maximum 50MB
                                </p>
                            </div>
                            <div id="fileInfo" style="display: none;">
                                <h4 style="color: #333; margin-bottom: 10px;">
                                    <i class="fa fa-check-circle" style="color: #28a745; margin-right: 10px;"></i>
                                    Fichier sélectionné
                                </h4>
                                <p id="fileName" style="color: #666; margin-bottom: 15px;"></p>
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fa fa-calculator"></i> Calculer le taux de remplissage
                                </button>
                                <button type="button" class="btn btn-default btn-lg" onclick="resetForm()" style="margin-left: 10px;">
                                    <i class="fa fa-times"></i> Annuler
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Informations -->
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="fa fa-info-circle"></i> Comment ça marche ?
                    </h3>
                </div>
                <div class="panel-body">
                    <p>Cet outil analyse vos documents pour calculer le pourcentage d'encre/toner utilisé :</p>
                    <ul>
                        <li><strong>Formats supportés</strong> : PDF, JPEG, PNG, GIF</li>
                        <li><strong>Analyse pixel par pixel</strong> : Compte les pixels non blancs</li>
                        <li><strong>Tolérance réglable</strong> : Ajustez la sensibilité de détection</li>
                        <li><strong>Statistiques détaillées</strong> : Taille, résolution, couleurs principales</li>
                    </ul>
                    <p class="text-muted">
                        <i class="fa fa-lightbulb-o"></i> 
                        Astuce : Un taux de remplissage élevé indique une consommation d'encre importante. Idéal pour estimer les coûts d'impression.
                    </p>
                </div>
            </div>
            
            <!-- Bouton retour -->
            <div class="text-center" style="margin-top: 20px;">
                <a href="?accueil" class="btn btn-default">
                    <i class="fa fa-home"></i> Retour à l'accueil
                </a>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript pour le drag & drop et l'interface -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileUploadArea = document.getElementById('fileUploadArea');
    const fileInput = document.getElementById('file');
    const uploadText = document.getElementById('uploadText');
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');
    const form = document.getElementById('fillRateForm');
    const toleranceSlider = document.getElementById('tolerance');
    const toleranceValue = document.getElementById('toleranceValue');
    const pageNumberGroup = document.getElementById('pageNumberGroup');

    // Synchroniser le slider et le champ de valeur
    toleranceSlider.addEventListener('input', function() {
        toleranceValue.value = this.value;
    });
    
    toleranceValue.addEventListener('change', function() {
        toleranceSlider.value = this.value;
    });

    // Gestion du clic sur la zone d'upload
    fileUploadArea.addEventListener('click', function(e) {
        if (e.target.tagName !== 'BUTTON' && e.target.tagName !== 'INPUT') {
            fileInput.click();
        }
    });
    
    // Gestion du clic sur le bouton
    const selectBtn = document.querySelector('#uploadText button');
    if (selectBtn) {
        selectBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            fileInput.click();
        });
    }

    // Gestion de la sélection de fichier
    fileInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            handleFileSelect(this.files[0]);
        }
    });

    // Gestion du drag & drop
    fileUploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.style.borderColor = '#28a745';
        this.style.backgroundColor = '#f8fff8';
    });

    fileUploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.style.borderColor = '#84fab0';
        this.style.backgroundColor = '#f0fff4';
    });

    fileUploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        this.style.borderColor = '#84fab0';
        this.style.backgroundColor = '#f0fff4';
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            const file = files[0];
            const validTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif'];
            if (validTypes.includes(file.type)) {
                const dt = new DataTransfer();
                dt.items.add(file);
                fileInput.files = dt.files;
                handleFileSelect(file);
            } else {
                alert('Veuillez sélectionner un fichier PDF ou image valide (JPEG, PNG, GIF).');
            }
        }
    });

    function handleFileSelect(file) {
        const validTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif'];
        if (!validTypes.includes(file.type)) {
            alert('Veuillez sélectionner un fichier PDF ou image valide.');
            return;
        }

        // Afficher le groupe de numéro de page si c'est un PDF
        if (file.type === 'application/pdf') {
            pageNumberGroup.style.display = 'block';
        } else {
            pageNumberGroup.style.display = 'none';
        }

        fileName.textContent = file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + ' MB)';
        uploadText.style.display = 'none';
        fileInfo.style.display = 'block';
    }

    // Fonction pour réinitialiser le formulaire
    window.resetForm = function() {
        fileInput.value = '';
        uploadText.style.display = 'block';
        fileInfo.style.display = 'none';
        pageNumberGroup.style.display = 'none';
    };
    
    // Protection contre double soumission
    form.addEventListener('submit', function(e) {
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn.disabled) {
            e.preventDefault();
            return false;
        }
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Analyse en cours...';
    });
});
</script>


