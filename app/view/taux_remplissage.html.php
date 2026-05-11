<div class="container">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <!-- En-tête -->
            <div class="page-header text-center"
                style="background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%); padding: 30px; border-radius: 10px; margin-bottom: 30px;">
                <h1 style="color: #333; margin: 0;">
                    <i class="fa fa-bar-chart" style="margin-right: 15px;"></i>
                    <?php _e('taux_remplissage.title'); ?>
                </h1>
                <p class="lead" style="color: #666; margin: 10px 0 0 0;">
                    <?php _e('taux_remplissage.subtitle'); ?>
                </p>
            </div>

            <!-- Messages d'erreur -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <h4><i class="fa fa-exclamation-triangle"></i> <?php _e('common.error'); ?> :</h4>
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
                            <i class="fa fa-check-circle"></i> <?php _e('common.completed'); ?> !
                        </h3>
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <!-- Aperçu de l'image -->
                            <div class="col-md-5">
                                <h4 style="margin-top: 0;"><?php _e('taux_remplissage.preview'); ?> :</h4>
                                <div class="thumbnail">
                                    <img src="<?= htmlspecialchars($result['preview_url']) ?>" alt="<?php echo __('taux_remplissage.preview'); ?>"
                                        style="max-width: 100%; height: auto; border: 1px solid #ddd;">
                                    <div class="caption text-center">
                                        <small class="text-muted"><?= htmlspecialchars($result['filename']) ?></small>
                                    </div>
                                </div>
                            </div>

                            <!-- Statistiques -->
                            <div class="col-md-7">
                                <h4 style="margin-top: 0;"><?php _e('taux_remplissage.fill_rate'); ?> :</h4>

                                <!-- Taux de remplissage principal -->
                                <div
                                    style="background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%); padding: 20px; border-radius: 10px; margin-bottom: 20px; text-align: center;">
                                    <h2 style="margin: 0; color: #333; font-size: 48px; font-weight: bold;">
                                        <?= $result['fill_rate'] ?>%
                                    </h2>
                                    <p style="margin: 5px 0 0 0; color: #666; font-size: 18px;">
                                        <?php _e('taux_remplissage.subtitle_desc'); ?>
                                    </p>
                                </div>

                                <!-- Barre de progression -->
                                <div class="progress" style="height: 30px; margin-bottom: 20px;">
                                    <div class="progress-bar progress-bar-success" role="progressbar"
                                        style="width: <?= $result['fill_rate'] ?>%; line-height: 30px; font-size: 16px; font-weight: bold;">
                                        <?= $result['fill_rate'] ?>% <?php _e('taux_remplissage.filled'); ?>
                                    </div>
                                    <div class="progress-bar progress-bar-default" role="progressbar"
                                        style="width: <?= $result['empty_rate'] ?>%; line-height: 30px; font-size: 16px;">
                                        <?= $result['empty_rate'] ?>% <?php _e('taux_remplissage.empty'); ?>
                                    </div>
                                </div>

                                <!-- Détails techniques -->
                                <div class="panel panel-default">
                                    <div class="panel-heading">
                                        <strong><i class="fa fa-cog"></i> <?php _e('taux_remplissage.technical_details'); ?></strong>
                                    </div>
                                    <div class="panel-body">
                                        <table class="table table-condensed" style="margin-bottom: 0;">
                                            <tr>
                                                <td><strong><?php _e('taux_remplissage.dimensions'); ?></strong></td>
                                                <td><?= number_format($result['width']) ?> ×
                                                    <?= number_format($result['height']) ?> pixels</td>
                                            </tr>
                                            <tr>
                                                <td><strong><?php _e('taux_remplissage.total_pixels'); ?></strong></td>
                                                <td><?= number_format($result['total_pixels']) ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong><?php _e('taux_remplissage.filled_pixels'); ?></strong></td>
                                                <td style="color: #28a745; font-weight: bold;">
                                                    <?= number_format($result['filled_pixels']) ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong><?php _e('taux_remplissage.empty_pixels'); ?></strong></td>
                                                <td style="color: #6c757d;"><?= number_format($result['empty_pixels']) ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td><strong><?php _e('taux_remplissage.tolerance_used'); ?></strong></td>
                                                <td><?= $result['tolerance'] ?> / 255</td>
                                            </tr>
                                            <?php if (isset($result['page_count']) && $result['page_count'] > 1): ?>
                                            <tr>
                                                <td><strong><?php _e('taux_remplissage.pages_analyzed'); ?></strong></td>
                                                <td><?= $result['page_count'] ?></td>
                                            </tr>
                                            <?php endif; ?>
                                        </table>
                                    </div>
                                </div>

                                <!-- Couleurs dominantes -->
                                <?php if (!empty($result['top_colors'])): ?>
                                    <div class="panel panel-default">
                                        <div class="panel-heading">
                                            <strong><i class="fa fa-palette"></i> <?php _e('taux_remplissage.main_colors'); ?></strong>
                                        </div>
                                        <div class="panel-body">
                                            <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                                                <?php
                                                $color_count = 0;
                                                foreach ($result['top_colors'] as $color => $count):
                                                    if ($color_count++ >= 8)
                                                        break; // Limiter à 8 couleurs
                                                    $percentage = round(($count / $result['total_pixels']) * 100, 2);
                                                    ?>
                                                    <div style="text-align: center;">
                                                        <div
                                                            style="width: 50px; height: 50px; background-color: #<?= $color ?>; border: 2px solid #ddd; border-radius: 5px; margin-bottom: 5px;">
                                                        </div>
                                                        <small style="display: block; font-size: 10px;">#<?= $color ?></small>
                                                        <small
                                                            style="display: block; font-weight: bold;"><?= $percentage ?>%</small>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Interprétation -->
                                <div class="alert alert-info">
                                    <strong><i class="fa fa-lightbulb-o"></i> <?php _e('taux_remplissage.interpretation'); ?></strong>
                                    <?php if ($result['fill_rate'] < 5): ?>
                                        <p><?php _e('taux_remplissage.interp_very_low'); ?></p>
                                    <?php elseif ($result['fill_rate'] < 20): ?>
                                        <p><?php _e('taux_remplissage.interp_low'); ?></p>
                                    <?php elseif ($result['fill_rate'] < 50): ?>
                                        <p><?php _e('taux_remplissage.interp_medium'); ?></p>
                                    <?php elseif ($result['fill_rate'] < 75): ?>
                                        <p><?php _e('taux_remplissage.interp_high'); ?></p>
                                    <?php else: ?>
                                        <p><?php _e('taux_remplissage.interp_very_high'); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Boutons d'action -->
                        <div class="text-center" style="margin-top: 20px;">
                            <a href="?taux_remplissage" class="btn btn-primary btn-lg">
                                <i class="fa fa-plus"></i> <?php _e('taux_remplissage.analyze_another'); ?>
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
                                            <i class="fa fa-sliders"></i> <?php _e('taux_remplissage.analysis_params'); ?>
                                        </h4>
                                    </div>
                                    <div class="panel-body">
                                        <div class="form-group">
                                            <label for="tolerance">
                                                <strong><?php _e('taux_remplissage.tolerance_white'); ?></strong>
                                                <span class="text-muted"><?php _e('taux_remplissage.tolerance_scale'); ?></span>
                                            </label>
                                            <div class="row">
                                                <div class="col-md-8">
                                                    <input type="range" class="form-control" name="tolerance"
                                                        id="tolerance" min="0" max="255" value="245"
                                                        style="width: 100%;">
                                                </div>
                                                <div class="col-md-4">
                                                    <input type="number" class="form-control" id="toleranceValue"
                                                        min="0" max="255" value="245" readonly
                                                        style="text-align: center; font-weight: bold;">
                                                </div>
                                            </div>
                                            <p class="text-muted" style="margin-top: 10px; font-size: 12px;">
                                                <i class="fa fa-info-circle"></i> <?php _e('taux_remplissage.tolerance_help'); ?>
                                            </p>
                                        </div>

                                        <!-- Analyse automatique de toutes les pages pour les PDF -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Zone d'upload -->
                        <div id="fileUploadArea"
                            style="border: 3px dashed #84fab0; border-radius: 15px; padding: 40px; text-align: center; background: linear-gradient(135deg, #f0fff4 0%, #e0f7fa 100%); transition: all 0.3s ease; cursor: pointer;">
                            <div style="font-size: 48px; color: #84fab0; margin-bottom: 20px;">
                                <i class="fa fa-file-image-o"></i>
                            </div>
                            <div id="uploadText" style="<?= $from_lib_file ? 'display:none;' : '' ?>">
                                <h3 style="color: #333; margin-bottom: 10px;"><?php _e('taux_remplissage.drag_drop'); ?>
                                </h3>
                                <p style="color: #666; margin-bottom: 20px;">
                                    <?php _e('taux_remplissage.click_select'); ?></p>
                                <input type="file" name="file" id="file"
                                    accept="application/pdf,.pdf,image/jpeg,image/jpg,image/png,image/gif"
                                    style="display: none;" <?= $from_lib_file ? '' : 'required' ?>>
                                <button type="button" class="btn btn-lg" id="selectFileButton"
                                    style="background: #84fab0; border: none; color: white; padding: 12px 30px; border-radius: 25px;">
                                    <i class="fa fa-upload"></i> <?php _e('taux_remplissage.select_file'); ?>
                                </button>
                                <p class="text-muted" style="margin-top: 10px; font-size: 12px;">
                                    <i class="fa fa-info-circle"></i> <?php _e('taux_remplissage.file_info'); ?>
                                </p>
                            </div>
                            <div id="fileInfo" style="<?= $from_lib_file ? 'display:block;' : 'display:none;' ?>">
                                <h4 style="color: #333; margin-bottom: 10px;">
                                    <i class="fa fa-check-circle" style="color: #28a745; margin-right: 10px;"></i>
                                    <?php _e('taux_remplissage.file_selected'); ?>
                                </h4>
                                <p id="fileName" style="color: #666; margin-bottom: 15px;">
                                    <?= $from_lib_file ? htmlspecialchars($from_lib_file['filename']) : '' ?>
                                </p>
                                <?php if ($from_lib_file): ?>
                                    <input type="hidden" name="lib_file_id" id="lib_file_id" value="<?= $from_lib_file['id'] ?>">
                                <?php endif; ?>
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fa fa-calculator"></i> <?php _e('taux_remplissage.calculate_btn'); ?>
                                </button>
                                <button type="button" class="btn btn-default btn-lg" onclick="resetForm()"
                                    style="margin-left: 10px;">
                                    <i class="fa fa-times"></i> <?php _e('taux_remplissage.cancel'); ?>
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
                        <i class="fa fa-info-circle"></i> <?php _e('taux_remplissage.how_it_works'); ?>
                    </h3>
                </div>
                <div class="panel-body">
                    <p><?php _e('taux_remplissage.how_it_works_desc'); ?></p>
                    <ul>
                        <li><?php _e('taux_remplissage.supported_formats'); ?></li>
                        <li><?php _e('taux_remplissage.pixel_analysis'); ?></li>
                        <li><?php _e('taux_remplissage.adjustable_tolerance'); ?></li>
                        <li><?php _e('taux_remplissage.detailed_stats'); ?></li>
                    </ul>
                    <p class="text-muted">
                        <i class="fa fa-lightbulb-o"></i>
                        <?php _e('taux_remplissage.tip'); ?>
                    </p>
                </div>
            </div>

            <!-- Bouton retour -->
            <div class="text-center" style="margin-top: 20px;">
                <a href="?accueil" class="btn btn-default">
                    <i class="fa fa-home"></i> <?php _e('accueil.back_to_home'); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript pour le drag & drop et l'interface -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
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
        toleranceSlider.addEventListener('input', function () {
            toleranceValue.value = this.value;
        });

        toleranceValue.addEventListener('change', function () {
            toleranceSlider.value = this.value;
        });

        // Gestion du clic sur la zone d'upload
        fileUploadArea.addEventListener('click', function (e) {
            if (e.target.tagName !== 'BUTTON' && e.target.tagName !== 'INPUT') {
                fileInput.click();
            }
        });

        // Gestion du clic sur le bouton
        const selectBtn = document.getElementById('selectFileButton');
        if (selectBtn) {
            selectBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                fileInput.click();
            });
        }

        // Gestion de la sélection de fichier
        fileInput.addEventListener('change', function () {
            if (this.files && this.files[0]) {
                handleFileSelect(this.files[0]);
            }
        });

        // Gestion du drag & drop
        fileUploadArea.addEventListener('dragover', function (e) {
            e.preventDefault();
            this.style.borderColor = '#28a745';
            this.style.backgroundColor = '#f8fff8';
        });

        fileUploadArea.addEventListener('dragleave', function (e) {
            e.preventDefault();
            this.style.borderColor = '#84fab0';
            this.style.backgroundColor = '#f0fff4';
        });

        fileUploadArea.addEventListener('drop', function (e) {
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
                    showAppModal({ message:  'FIXME_EMPTY_KEY' , type: 'warning' });
                }
            }
        });

        function handleFileSelect(file) {
            const validTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif'];
            if (!validTypes.includes(file.type)) {
                showAppModal({ message:  'Veuillez sélectionner un fichier valide.' , type: 'warning' });
                return;
            }
            // Supprimer lib_file_id si présent car on a sélectionné un nouveau fichier
            const libInput = document.getElementById('lib_file_id');
            if (libInput) libInput.remove();
            fileName.textContent = file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + ' MB)';
            uploadText.style.display = 'none';
            fileInfo.style.display = 'block';
        }

        // Fonction pour réinitialiser le formulaire
        window.resetForm = function () {
            fileInput.value = '';
            uploadText.style.display = 'block';
            fileInfo.style.display = 'none';
        };

        // Protection contre double soumission
        form.addEventListener('submit', function (e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn.disabled) {
                e.preventDefault();
                return false;
            }
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> <?php _ejs('taux_remplissage.analyzing'); ?>';
        });
    });
</script>
