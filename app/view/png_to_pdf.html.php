<div class="container">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <!-- En-tête -->
            <div class="page-header text-center" style="background: linear-gradient(135deg, #a8e6cf 0%, #c3f0ca 100%); padding: 30px; border-radius: 10px; margin-bottom: 30px;">
                <h1 style="color: #333; margin: 0;">
                    <i class="fa fa-file-image-o" style="margin-right: 15px;"></i>
                    <?php _e('png_to_pdf.title'); ?>
                </h1>
                <p class="lead" style="color: #666; margin: 10px 0 0 0;">
                    <?php _e('png_to_pdf.subtitle'); ?>
                </p>
            </div>

            <!-- Résultat -->
            <?php if ($success && !empty($result)): ?>
                <div class="panel panel-success">
                    <div class="panel-heading">
                        <h3 class="panel-title">
                            <i class="fa fa-check-circle"></i> <?php _e('common.success'); ?> !
                        </h3>
                    </div>
                    <div class="panel-body text-center">
                        <div style="font-size: 48px; color: #28a745; margin-bottom: 20px;">
                            <i class="fa fa-file-pdf-o"></i>
                        </div>
                        <h4 style="color: #333; margin-bottom: 20px;">
                            Votre PDF est prêt !
                        </h4>
                        <p style="color: #666; margin-bottom: 25px;">
                            Le fichier <strong><?= htmlspecialchars($result) ?></strong> a été créé avec succès.
                        </p>
                        <a href="<?= htmlspecialchars($download_url) ?>" class="btn btn-success btn-lg" download>
                            <i class="fa fa-download"></i> Télécharger le PDF
                        </a>
                        <a href="?png_to_pdf" class="btn btn-default btn-lg" style="margin-left: 10px;">
                            <i class="fa fa-plus"></i> Convertir d'autres images
                        </a>
                    </div>
                </div>
            <?php endif; ?>

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

            <!-- Formulaire d'upload -->
            <div class="panel panel-default">
                <div class="panel-body">
                    <form method="POST" enctype="multipart/form-data" id="imageForm">
                        <!-- Options de format -->
                        <div class="row" style="margin-bottom: 20px;">
                            <div class="col-md-6">
                                <div class="panel panel-info">
                                    <div class="panel-heading">
                                        <h4 class="panel-title">
                                            <i class="fa fa-file-o"></i> Format de page
                                        </h4>
                                    </div>
                                    <div class="panel-body">
                                        <div class="radio">
                                            <label>
                                                <input type="radio" name="format" value="A4" checked>
                                                <strong>A4</strong> <span class="text-muted">(210 × 297 mm)</span>
                                            </label>
                                        </div>
                                        <div class="radio">
                                            <label>
                                                <input type="radio" name="format" value="A3">
                                                <strong>A3</strong> <span class="text-muted">(297 × 420 mm)</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="panel panel-info">
                                    <div class="panel-heading">
                                        <h4 class="panel-title">
                                            <i class="fa fa-rotate-right"></i> <?php _e('png_to_pdf.orientation'); ?>
                                        </h4>
                                    </div>
                                    <div class="panel-body">
                                        <div class="radio">
                                            <label>
                                                <input type="radio" name="orientation" value="P" checked>
                                                <strong><?php _e('png_to_pdf.portrait'); ?></strong> <i class="fa fa-arrows-v"></i>
                                            </label>
                                        </div>
                                        <div class="radio">
                                            <label>
                                                <input type="radio" name="orientation" value="L">
                                                <strong><?php _e('png_to_pdf.landscape'); ?></strong> <i class="fa fa-arrows-h"></i>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Zone d'upload -->
                        <div id="fileUploadArea" style="border: 3px dashed #a8e6cf; border-radius: 15px; padding: 40px; text-align: center; background: linear-gradient(135deg, #f5fff5 0%, #e8ffe8 100%); transition: all 0.3s ease; cursor: pointer;">
                            <div style="font-size: 48px; color: #a8e6cf; margin-bottom: 20px;">
                                <i class="fa fa-picture-o"></i>
                            </div>
                            <div id="uploadText">
                                <h3 style="color: #333; margin-bottom: 10px;"><?php _e('png_to_pdf.drag_drop'); ?></h3>
                                <p style="color: #666; margin-bottom: 20px;"><?php _e('png_to_pdf.click_select'); ?></p>
                                <input type="file" name="images[]" id="images" accept="image/png,image/jpeg,image/jpg" multiple style="display: none;" required>
                                <button type="button" class="btn btn-lg" style="background: #a8e6cf; border: none; color: white; padding: 12px 30px; border-radius: 25px;">
                                    <i class="fa fa-upload"></i> <?php _e('png_to_pdf.select_images'); ?>
                                </button>
                                <p class="text-muted" style="margin-top: 10px; font-size: 12px;">
                                    <i class="fa fa-info-circle"></i> <?php _e('png_to_pdf.file_info'); ?>
                                </p>
                            </div>
                            <div id="fileInfo" style="display: none;">
                                <h4 style="color: #333; margin-bottom: 10px;">
                                    <i class="fa fa-check-circle" style="color: #28a745; margin-right: 10px;"></i>
                                    <span id="fileCount">0</span> <?php _e('png_to_pdf.images_selected'); ?>
                                </h4>
                                <div id="fileList" style="color: #666; margin-bottom: 15px; max-height: 150px; overflow-y: auto;"></div>
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fa fa-file-pdf-o"></i> <?php _e('png_to_pdf.create_pdf'); ?>
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
                        <i class="fa fa-info-circle"></i> <?php _e('png_to_pdf.how_it_works'); ?>
                    </h3>
                </div>
                <div class="panel-body">
                    <p><?php _e('png_to_pdf.how_it_works_desc'); ?></p>
                    <ul>
                        <li><?php _e('png_to_pdf.format_a4'); ?></li>
                        <li><?php _e('png_to_pdf.format_a3'); ?></li>
                        <li><?php _e('png_to_pdf.multi_images'); ?></li>
                        <li><?php _e('png_to_pdf.auto_fit'); ?></li>
                    </ul>
                    <p class="text-muted">
                        <i class="fa fa-lightbulb-o"></i> 
                        <?php _e('png_to_pdf.tip'); ?>
                    </p>
                </div>
            </div>
            
            <!-- Bouton retour -->
            <div class="text-center" style="margin-top: 20px;">
                <a href="?accueil" class="btn btn-default">
                    <i class="fa fa-home"></i> <?php _e('png_to_pdf.back_home'); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript pour le drag & drop -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileUploadArea = document.getElementById('fileUploadArea');
    const fileInput = document.getElementById('images');
    const uploadText = document.getElementById('uploadText');
    const fileInfo = document.getElementById('fileInfo');
    const fileCount = document.getElementById('fileCount');
    const fileList = document.getElementById('fileList');
    const form = document.getElementById('imageForm');

    // Gestion du clic sur la zone d'upload
    fileUploadArea.addEventListener('click', function(e) {
        if (e.target.tagName !== 'BUTTON' && e.target.tagName !== 'INPUT') {
            fileInput.click();
        }
    });
    
    // Gestion du clic sur le bouton
    document.querySelector('#uploadText button').addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        fileInput.click();
    });

    // Gestion de la sélection de fichier
    fileInput.addEventListener('change', function() {
        if (this.files && this.files.length > 0) {
            handleFileSelect(this.files);
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
        this.style.borderColor = '#a8e6cf';
        this.style.backgroundColor = '#f5fff5';
    });

    fileUploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        this.style.borderColor = '#a8e6cf';
        this.style.backgroundColor = '#f5fff5';
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            // Créer un DataTransfer pour affecter les fichiers à l'input
            const dt = new DataTransfer();
            let validFiles = 0;
            
            for (let i = 0; i < files.length; i++) {
                if (files[i].type === 'image/png' || files[i].type === 'image/jpeg' || files[i].type === 'image/jpg') {
                    dt.items.add(files[i]);
                    validFiles++;
                }
            }
            
            if (validFiles > 0) {
                fileInput.files = dt.files;
                handleFileSelect(dt.files);
            } else {
                alert('Veuillez sélectionner des fichiers image valides (PNG ou JPG).');
            }
        }
    });

    function handleFileSelect(files) {
        let validCount = 0;
        let html = '<ul style="text-align: left; display: inline-block; margin: 0;">';
        
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            if (file.type === 'image/png' || file.type === 'image/jpeg' || file.type === 'image/jpg') {
                html += '<li><i class="fa fa-image"></i> ' + file.name + ' <span class="text-muted">(' + (file.size / 1024 / 1024).toFixed(2) + ' MB)</span></li>';
                validCount++;
            }
        }
        
        html += '</ul>';
        
        if (validCount > 0) {
            fileCount.textContent = validCount;
            fileList.innerHTML = html;
            uploadText.style.display = 'none';
            fileInfo.style.display = 'block';
        }
    }

    // Fonction pour réinitialiser le formulaire
    window.resetForm = function() {
        fileInput.value = '';
        uploadText.style.display = 'block';
        fileInfo.style.display = 'none';
    };
    
    // Protection contre double soumission
    form.addEventListener('submit', function(e) {
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn.disabled) {
            e.preventDefault();
            return false;
        }
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Conversion en cours...';
    });
});
</script>
