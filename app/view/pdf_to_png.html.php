<div class="container">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <!-- En-tête -->
            <div class="page-header text-center" style="background: linear-gradient(135deg, #c3aed6 0%, #d5b8e0 100%); padding: 30px; border-radius: 10px; margin-bottom: 30px;">
                <h1 style="color: #333; margin: 0;">
                    <i class="fa fa-picture-o" style="margin-right: 15px;"></i>
                    <?php _e('pdf_to_png.title'); ?>
                </h1>
                <p class="lead" style="color: #666; margin: 10px 0 0 0;">
                    <?php _e('pdf_to_png.subtitle'); ?>
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
                    <div class="panel-body">
                        <div style="text-align: center; font-size: 48px; color: #28a745; margin-bottom: 20px;">
                            <i class="fa fa-images"></i>
                        </div>
                        <h4 style="color: #333; margin-bottom: 20px; text-center;">
                            <?= count($result) ?> image(s) extraite(s) avec succès
                        </h4>
                        
                        <!-- Liste des images avec aperçu -->
                        <div class="row">
                            <?php foreach ($download_urls as $index => $url): ?>
                                <div class="col-md-4 col-sm-6" style="margin-bottom: 15px;">
                                    <div class="thumbnail" style="text-align: center;">
                                        <img src="<?= htmlspecialchars($url) ?>" alt="Page <?= ($index + 1) ?>" style="max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 4px;">
                                        <div class="caption">
                                            <p><strong>Page <?= ($index + 1) ?></strong></p>
                                            <a href="<?= htmlspecialchars($url) ?>" class="btn btn-sm btn-success" download>
                                                <i class="fa fa-download"></i> <?php _e('common.download'); ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Bouton pour tout télécharger -->
                        <div class="text-center" style="margin-top: 20px;">
                            <?php if (!empty($zip_url)): ?>
                                <a href="<?= htmlspecialchars($zip_url) ?>" class="btn btn-primary btn-lg" download>
                                    <i class="fa fa-download"></i> <?php _e('common.download_all_zip'); ?>
                                </a>
                            <?php endif; ?>
                            <a href="?pdf_to_png" class="btn btn-default btn-lg">
                                <i class="fa fa-plus"></i> <?php _e('pdf_to_png.convert_another'); ?>
                            </a>
                        </div>
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
                    <form method="POST" enctype="multipart/form-data" id="pdfForm">
                        <!-- Options de qualité -->
                        <div class="row" style="margin-bottom: 20px;">
                            <div class="col-md-12">
                                <div class="panel panel-info">
                                    <div class="panel-heading">
                                        <h4 class="panel-title">
                                            <i class="fa fa-sliders"></i> <?php _e('pdf_to_png.quality'); ?>
                                        </h4>
                                    </div>
                                    <div class="panel-body">
                                        <div class="radio">
                                            <label>
                                                <input type="radio" name="dpi" value="72">
                                                <strong><?php _e('pdf_to_png.low'); ?></strong> <span class="text-muted"><?php _e('pdf_to_png.low_desc'); ?></span>
                                            </label>
                                        </div>
                                        <div class="radio">
                                            <label>
                                                <input type="radio" name="dpi" value="150" checked>
                                                <strong><?php _e('pdf_to_png.medium'); ?></strong> <span class="text-muted"><?php _e('pdf_to_png.medium_desc'); ?></span>
                                            </label>
                                        </div>
                                        <div class="radio">
                                            <label>
                                                <input type="radio" name="dpi" value="300">
                                                <strong><?php _e('pdf_to_png.high'); ?></strong> <span class="text-muted"><?php _e('pdf_to_png.high_desc'); ?></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Zone d'upload -->
                        <div id="fileUploadArea" style="border: 3px dashed #c3aed6; border-radius: 15px; padding: 40px; text-align: center; background: linear-gradient(135deg, #f9f5ff 0%, #f0e8ff 100%); transition: all 0.3s ease; cursor: pointer;">
                            <div style="font-size: 48px; color: #c3aed6; margin-bottom: 20px;">
                                <i class="fa fa-file-pdf-o"></i>
                            </div>
                            <div id="uploadText">
                                <h3 style="color: #333; margin-bottom: 10px;"><?php _e('pdf_to_png.drag_drop'); ?></h3>
                                <p style="color: #666; margin-bottom: 20px;"><?php _e('pdf_to_png.click_select'); ?></p>
                                <input type="file" name="pdf" id="pdf" accept="application/pdf,.pdf" style="display: none;" required>
                                <button type="button" class="btn btn-lg" style="background: #c3aed6; border: none; color: white; padding: 12px 30px; border-radius: 25px;">
                                    <i class="fa fa-upload"></i> <?php _e('pdf_to_png.select_file'); ?>
                                </button>
                                <p class="text-muted" style="margin-top: 10px; font-size: 12px;">
                                    <i class="fa fa-info-circle"></i> <?php _e('pdf_to_png.file_info'); ?>
                                </p>
                            </div>
                            <div id="fileInfo" style="display: none;">
                                <h4 style="color: #333; margin-bottom: 10px;">
                                    <i class="fa fa-check-circle" style="color: #28a745; margin-right: 10px;"></i>
                                    Fichier sélectionné
                                </h4>
                                <p id="fileName" style="color: #666; margin-bottom: 15px;"></p>
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fa fa-magic"></i> Extraire les pages
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
                        <i class="fa fa-info-circle"></i> <?php _e('pdf_to_png.how_it_works'); ?>
                    </h3>
                </div>
                <div class="panel-body">
                    <p><?php _e('pdf_to_png.how_it_works_desc'); ?></p>
                    <ul>
                        <li><?php _e('pdf_to_png.one_image_per_page'); ?></li>
                        <li><?php _e('pdf_to_png.png_format'); ?></li>
                        <li><?php _e('pdf_to_png.adjustable_quality'); ?></li>
                        <li><?php _e('pdf_to_png.zip_download'); ?></li>
                    </ul>
                    <p class="text-muted">
                        <i class="fa fa-lightbulb-o"></i> 
                        <?php _e('pdf_to_png.tip'); ?>
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

<!-- JavaScript pour le drag & drop -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileUploadArea = document.getElementById('fileUploadArea');
    const fileInput = document.getElementById('pdf');
    const uploadText = document.getElementById('uploadText');
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');
    const form = document.getElementById('pdfForm');

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
        this.style.borderColor = '#c3aed6';
        this.style.backgroundColor = '#f9f5ff';
    });

    fileUploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        this.style.borderColor = '#c3aed6';
        this.style.backgroundColor = '#f9f5ff';
        
        const files = e.dataTransfer.files;
        if (files.length > 0 && files[0].type === 'application/pdf') {
            const dt = new DataTransfer();
            dt.items.add(files[0]);
            fileInput.files = dt.files;
            handleFileSelect(files[0]);
        } else {
            alert('Veuillez sélectionner un fichier PDF valide.');
        }
    });

    function handleFileSelect(file) {
        if (file.type !== 'application/pdf') {
            alert('Veuillez sélectionner un fichier PDF.');
            return;
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
    };
    
    // Protection contre double soumission
    form.addEventListener('submit', function(e) {
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn.disabled) {
            e.preventDefault();
            return false;
        }
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Extraction en cours...';
    });
});
</script>
