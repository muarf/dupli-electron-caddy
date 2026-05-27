<?php
require_once __DIR__ . '/../controler/functions/i18n.php';
$title = __("unimpose.title");
// Sécurisation des variables pour éviter les warnings
$success = $success ?? false;
$errors = $errors ?? [];
$result = $result ?? '';
$download_url = $download_url ?? '';
?>

<div class="container">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <!-- En-tête -->
            <div class="page-header text-center"
                style="background: linear-gradient(135deg, #ffb3ba 0%, #ffdfba 100%); padding: 30px; border-radius: 10px; margin-bottom: 30px;">
                <h1 style="color: #333; margin: 0;">
                    <i class="fa fa-undo" style="margin-right: 15px;"></i>
                    <?php _e('unimpose.title'); ?>
                </h1>
                <p class="lead" style="color: #666; margin: 10px 0 0 0;">
                    <?php _e('unimpose.subtitle'); ?>
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
                            <i class="fa fa-file-text-o"></i>
                        </div>
                        <h4 style="color: #333; margin-bottom: 20px;">
                            <?php _e('unimpose.success_msg'); ?>
                        </h4>
                        <p style="color: #666; margin-bottom: 25px;">
                            <?php _e('unimpose.ready_msg'); ?>
                        </p>
                        <a href="<?= htmlspecialchars($download_url) ?>" class="btn btn-success btn-lg">
                            <i class="fa fa-download"></i> <?php _e('unimpose.download_unimposed'); ?>
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Messages d'erreur -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <h4><i class="fa fa-exclamation-triangle"></i> <?php _e('unimpose.errors_detected'); ?></h4>
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
                    <form method="POST" enctype="multipart/form-data" class="form-horizontal">
                        <!-- Mode de désimposition -->
                        <div class="form-group" style="margin-bottom: 25px;">
                            <label style="display: block; margin-bottom: 10px; font-weight: bold; color: #333;">
                                <i class="fa fa-cog"></i> <?php _e('unimpose.mode_label'); ?>
                            </label>
                            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                                <label
                                    style="display: flex; align-items: center; cursor: pointer; padding: 10px; border: 2px solid #ddd; border-radius: 8px; transition: all 0.3s;">
                                    <input type="radio" name="unimpose_mode" value="booklet" checked
                                        style="margin-right: 8px;">
                                    <div>
                                        <strong><?php _e('unimpose.mode_booklet'); ?></strong>
                                        <small style="display: block; color: #666; margin-top: 3px;"><?php _e('unimpose.mode_booklet_desc'); ?></small>
                                    </div>
                                </label>
                                <label
                                    style="display: flex; align-items: center; cursor: pointer; padding: 10px; border: 2px solid #ddd; border-radius: 8px; transition: all 0.3s;">
                                    <input type="radio" name="unimpose_mode" value="split_double_pages"
                                        style="margin-right: 8px;">
                                    <div>
                                        <strong><?php _e('unimpose.mode_split'); ?></strong>
                                        <small style="display: block; color: #666; margin-top: 3px;"><?php _e('unimpose.mode_split_desc'); ?></small>
                                    </div>
                                </label>
                            </div>
                        </div>
                        <div class="file-upload-area" id="fileUploadArea"
                            style="border: 3px dashed #ffb3ba; border-radius: 15px; padding: 40px; text-align: center; background: linear-gradient(135deg, #fff5f5 0%, #ffe8e8 100%); transition: all 0.3s ease; cursor: pointer;">
                            <div class="file-upload-icon" style="font-size: 48px; color: #ffb3ba; margin-bottom: 20px;">
                                <i class="fa fa-file-pdf-o"></i>
                            </div>
                            <div id="uploadText" style="<?= $from_lib_file ? 'display:none;' : '' ?>">
                                <h3 style="color: #333; margin-bottom: 10px;"><?php _e('unimpose.drag_drop'); ?></h3>
                                <p style="color: #666; margin-bottom: 20px;"><?php _e('unimpose.click_select'); ?></p>
                                <input type="file" name="pdf" id="pdf" accept=".pdf" style="display: none;" <?= $from_lib_file ? '' : 'required' ?>>
                                <button type="button" class="btn btn-lg" id="selectPdfButton"
                                    style="background: #ffb3ba; border: none; color: white; padding: 12px 30px; border-radius: 25px;">
                                    <i class="fa fa-upload"></i> <?php _e('unimpose.select_pdf'); ?>
                                </button>
                            </div>
                            <div id="fileInfo" style="<?= $from_lib_file ? 'display:block;' : 'display:none;' ?>">
                                <h4 style="color: #333; margin-bottom: 10px;">
                                    <i class="fa fa-check-circle" style="color: #28a745; margin-right: 10px;"></i>
                                    <?php _e('unimpose.file_selected'); ?>
                                </h4>
                                <p id="fileName" style="color: #666; margin-bottom: 15px;">
                                    <?= $from_lib_file ? htmlspecialchars($from_lib_file['filename']) : '' ?>
                                </p>
                                <?php if ($from_lib_file): ?>
                                    <input type="hidden" name="lib_file_id" value="<?= $from_lib_file['id'] ?>">
                                <?php endif; ?>
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fa fa-magic"></i> <?php _e('unimpose.unimpose_pdf'); ?>
                                </button>
                                <button type="button" class="btn btn-default btn-lg" onclick="resetForm()"
                                    style="margin-left: 10px;">
                                    <i class="fa fa-times"></i> <?php _e('unimpose.cancel'); ?>
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
                        <i class="fa fa-info-circle"></i> <?php _e('unimpose.how_it_works'); ?>
                    </h3>
                </div>
                <div class="panel-body">
                    <p><?php _e('unimpose.how_it_works_desc'); ?></p>
                    <ul>
                        <li><?php _e('unimpose.a3_to_a4'); ?></li>
                        <li><?php _e('unimpose.booklet_to_sequential'); ?></li>
                        <li><?php _e('unimpose.two_pages_to_one'); ?></li>
                    </ul>
                    <p class="text-muted">
                        <i class="fa fa-lightbulb-o"></i>
                        <?php _e('unimpose.tip'); ?>
                    </p>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Styles pour les options de mode -->
<style>
    label[for],
    label input[type="radio"] {
        cursor: pointer;
    }

    label input[type="radio"]:checked+div {
        color: #28a745;
    }

    label:hover {
        border-color: #ffb3ba !important;
        background-color: #fff5f5;
    }

    label input[type="radio"]:checked~* {
        border-color: #28a745;
    }

    label:has(input[type="radio"]:checked) {
        border-color: #28a745 !important;
        background-color: #f0fff4;
    }
</style>

<!-- JavaScript pour le drag & drop -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const fileUploadArea = document.getElementById('fileUploadArea');
        const fileInput = document.getElementById('pdf');
        const uploadText = document.getElementById('uploadText');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');

        // Gestion du clic sur la zone d'upload
        fileUploadArea.addEventListener('click', function (e) {
            // Ne pas ouvrir le sélecteur si on clique sur un bouton ou un de ses enfants
            if (e.target.closest('button')) {
                return;
            }
            fileInput.click();
        });

        // Gestion du clic direct sur le bouton "Sélectionner un PDF"
        const selectPdfButton = document.getElementById('selectPdfButton');
        if (selectPdfButton) {
            selectPdfButton.addEventListener('click', function (e) {
                e.stopPropagation(); // Empêcher la propagation vers fileUploadArea
                if (fileInput) {
                    fileInput.click();
                }
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
            this.style.borderColor = '#ffb3ba';
            this.style.backgroundColor = '#fff5f5';
        });

        fileUploadArea.addEventListener('drop', function (e) {
            e.preventDefault();
            this.style.borderColor = '#ffb3ba';
            this.style.backgroundColor = '#fff5f5';

            const files = e.dataTransfer.files;
            if (files.length > 0 && files[0].type === 'application/pdf') {
                fileInput.files = files;
                handleFileSelect(files[0]);
            } else {
                showAppModal({ message: 'Veuillez sélectionner un fichier PDF valide.', type: 'warning' });
            }
        });

        function handleFileSelect(file) {
            if (file.type !== 'application/pdf') {
                showAppModal({ message: 'Veuillez sélectionner un fichier PDF.', type: 'warning' });
                return;
            }

            fileName.textContent = file.name;
            uploadText.style.display = 'none';
            fileInfo.style.display = 'block';
        }

        // Fonction pour réinitialiser le formulaire
        window.resetForm = function () {
            fileInput.value = '';
            uploadText.style.display = 'block';
            fileInfo.style.display = 'none';
        };
    });
</script>
