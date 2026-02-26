<!-- PDF.js pour la prévisualisation des PDFs -->
<script src="public/js/build/pdf.js" defer></script>
<!-- Riso Tools pour les fonctions de traitement -->
<script src="public/js/riso-tools.js" defer></script>

<style>
    .image-processor-container {
        max-width: 1200px;
        margin: 20px auto;
        padding: 20px;
    }

    .processor-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        border-radius: 10px;
        text-align: center;
        margin-bottom: 30px;
    }

    .upload-zone {
        border: 3px dashed #667eea;
        border-radius: 15px;
        padding: 50px;
        text-align: center;
        background: #f8f9ff;
        cursor: pointer;
        transition: all 0.3s;
    }

    .upload-zone:hover {
        border-color: #764ba2;
        background: #f0f2ff;
    }

    .upload-zone.dragover {
        border-color: #764ba2;
        background: #e8ebff;
    }

    .controls-panel {
        background: white;
        border: 2px solid #e0e0e0;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .preview-container {
        text-align: center;
        margin: 20px 0;
    }

    .preview-canvas {
        max-width: 100%;
        height: auto;
        border: 2px solid #333;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        border-radius: 5px;
    }

    .slider-group {
        margin-bottom: 20px;
    }

    .slider-label {
        display: flex;
        justify-content: space-between;
        margin-bottom: 5px;
    }

    .slider-value {
        font-weight: bold;
        color: #667eea;
    }

    input[type="range"] {
        width: 100%;
    }

    /* Modal de progression */
    .progress-modal {
        display: none;
        position: fixed;
        z-index: 10000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.7);
    }

    .progress-modal-content {
        background-color: #fefefe;
        margin: 15% auto;
        padding: 30px;
        border: 1px solid #888;
        border-radius: 10px;
        width: 80%;
        max-width: 500px;
        text-align: center;
    }

    .progress-bar-container {
        width: 100%;
        height: 30px;
        background-color: #e0e0e0;
        border-radius: 15px;
        overflow: hidden;
        margin: 20px 0;
    }

    .progress-bar-fill {
        height: 100%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        width: 0%;
        transition: width 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
    }
</style>

<div class="image-processor-container">
    <!-- En-tête -->
    <div class="processor-header">
        <h1><i class="fa fa-adjust"></i> <?php _e('image_processor.title'); ?></h1>
        <p><?php _e('image_processor.subtitle'); ?></p>
    </div>

    <!-- Messages d'erreur -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <h4><i class="fa fa-exclamation-triangle"></i> <?php _e('image_processor.errors_detected'); ?></h4>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Résultat -->
    <?php if ($success && !empty($result)): ?>
        <div class="panel panel-success">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <i class="fa fa-check-circle"></i> <?php _e('image_processor.processing_completed'); ?>
                </h3>
            </div>
            <div class="panel-body text-center">
                <?php if (!$result['is_pdf'] && isset($result['preview_url'])): ?>
                    <h4><?php _e('image_processor.result_preview'); ?></h4>
                    <div class="preview-container">
                        <img src="<?= htmlspecialchars($result['preview_url']) ?>" alt="<?php echo __('common.completed'); ?>" class="preview-canvas"
                            style="max-width: 600px;">
                    </div>
                <?php endif; ?>
                <div style="margin-top: 20px;">
                    <a href="<?= htmlspecialchars($result['download_url']) ?>" class="btn btn-primary btn-lg" download>
                        <i class="fa fa-download"></i> <?= $result['is_pdf'] ? __('image_processor.download_pdf') : __('image_processor.download_image') ?>
                    </a>
                    <a href="?image_processor" class="btn btn-default btn-lg">
                        <i class="fa fa-plus"></i> <?php _e('image_processor.process_another'); ?>
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Zone d'upload -->
    <div id="uploadSection">
        <?php if (isset($from_lib_file)): ?>
            <div class="text-end mb-2">
                <a href="?bibliotheque" class="btn btn-outline-primary btn-sm">
                    <i class="fa fa-book"></i> <?php _e('imposition_brochure.open_library'); ?>
                </a>
            </div>
        <?php endif; ?>
        <div class="upload-zone" id="uploadZone">
            <div style="font-size: 64px; color: #667eea; margin-bottom: 20px;">
                <i class="fa fa-cloud-upload"></i>
            </div>
            <h3><?php _e('image_processor.drag_drop'); ?></h3>
            <p class="text-muted"><?php _e('image_processor.click_select'); ?></p>
            <p class="text-muted"><small><?php _e('image_processor.supported_formats'); ?></small></p>
            <input type="file" id="fileInput" name="file" accept=".pdf,.png,.jpg,.jpeg,.gif,.webp"
                style="display: none;">
        </div>
    </div>

    <!-- Contrôles et Prévisualisation -->
    <div id="processingSection" style="display: none;">
        <!-- Prévisualisation -->
        <div class="panel panel-default">
            <div class="panel-heading">
                <h4><i class="fa fa-eye"></i> <?php _e('image_processor.preview'); ?></h4>
            </div>
            <div class="panel-body">
                <div class="preview-container">
                    <canvas id="previewCanvas" class="preview-canvas"></canvas>
                </div>
                <p class="text-muted text-center" style="margin-top: 10px;">
                    <i class="fa fa-info-circle"></i> <?php _e('image_processor.real_time_adjustments'); ?>
                </p>
            </div>
        </div>

        <!-- Contrôles -->
        <div class="controls-panel">
            <h4><i class="fa fa-sliders"></i> <?php _e('image_processor.adjustments'); ?></h4>

            <!-- Contraste -->
            <div class="slider-group">
                <div class="slider-label">
                    <span><i class="fa fa-adjust"></i> <?php _e('image_processor.contrast'); ?></span>
                    <span class="slider-value" id="contrastValue">0</span>
                </div>
                <input type="range" id="contrastSlider" min="-100" max="100" value="0" step="1">
            </div>

            <!-- Luminosité -->
            <div class="slider-group">
                <div class="slider-label">
                    <span><i class="fa fa-sun-o"></i> <?php _e('image_processor.brightness'); ?></span>
                    <span class="slider-value" id="brightnessValue">0</span>
                </div>
                <input type="range" id="brightnessSlider" min="-100" max="100" value="0" step="1">
            </div>

            <!-- Gamma -->
            <div class="slider-group">
                <div class="slider-label">
                    <span><i class="fa fa-sliders"></i> <?php _e('image_processor.gamma'); ?></span>
                    <span class="slider-value" id="gammaValue">1.0</span>
                </div>
                <input type="range" id="gammaSlider" min="0.1" max="3.0" value="1.0" step="0.1">
            </div>

            <!-- Saturation -->
            <div class="slider-group">
                <div class="slider-label">
                    <span><i class="fa fa-tint"></i> <?php _e('image_processor.saturation'); ?></span>
                    <span class="slider-value" id="saturationValue">0</span>
                </div>
                <input type="range" id="saturationSlider" min="-100" max="100" value="0" step="1">
            </div>

            <!-- Bitmap -->
            <div class="slider-group">
                <div class="checkbox">
                    <label>
                        <input type="checkbox" id="bitmapEnabled">
                        <strong><?php _e('image_processor.bitmap_enabled'); ?></strong>
                    </label>
                </div>
                <div id="bitmapOptions" style="display: none; margin-top: 15px; padding-left: 30px;">
                    <div class="radio" style="margin-bottom: 10px;">
                        <label>
                            <input type="radio" name="bitmapMethod" value="threshold" checked>
                            <?php _e('image_processor.threshold_simple'); ?>
                        </label>
                    </div>
                    <div class="radio" style="margin-bottom: 10px;">
                        <label>
                            <input type="radio" name="bitmapMethod" value="dithering">
                            <?php _e('image_processor.dithering'); ?>
                        </label>
                    </div>
                    <div id="thresholdOption" style="margin-top: 10px;">
                        <div class="slider-label">
                            <span><?php _e('image_processor.threshold'); ?></span>
                            <span class="slider-value" id="thresholdValue">128</span>
                        </div>
                        <input type="range" id="thresholdSlider" min="0" max="255" value="128" step="1">
                    </div>
                </div>
            </div>

            <!-- Bouton Appliquer -->
            <div class="text-center" style="margin-top: 30px;">
                <button type="button" id="applyButton" class="btn btn-primary btn-lg">
                    <i class="fa fa-magic"></i> <?php _e('image_processor.apply_processing'); ?>
                </button>
                <button type="button" id="resetButton" class="btn btn-default btn-lg">
                    <i class="fa fa-undo"></i> <?php _e('image_processor.reset'); ?>
                </button>
                <button type="button" class="btn btn-warning btn-lg" onclick="window.location.reload()">
                    <i class="fa fa-upload"></i> <?php _e('image_processor.upload_new'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Formulaire caché pour soumission -->
    <form method="POST" enctype="multipart/form-data" id="processForm" style="display: none;">
        <input type="hidden" name="lib_file_id" id="lib_file_id"
            value="<?= isset($from_lib_file) ? $from_lib_file['id'] : '' ?>">
        <input type="file" name="file" id="hiddenFileInput">
        <input type="hidden" name="contrast" id="hiddenContrast">
        <input type="hidden" name="brightness" id="hiddenBrightness">
        <input type="hidden" name="gamma" id="hiddenGamma">
        <input type="hidden" name="saturation" id="hiddenSaturation">
        <input type="hidden" name="bitmap_enabled" id="hiddenBitmapEnabled">
        <input type="hidden" name="bitmap_method" id="hiddenBitmapMethod">
        <input type="hidden" name="bitmap_threshold" id="hiddenBitmapThreshold">
    </form>
</div>

<!-- Modal de progression -->
<div id="progressModal" class="progress-modal">
    <div class="progress-modal-content">
        <h3><i class="fa fa-spinner fa-spin"></i> <?php _e('image_processor.processing_in_progress'); ?></h3>
        <p id="progressMessage"><?php _e('image_processor.initialization'); ?></p>
        <div class="progress-bar-container">
            <div class="progress-bar-fill" id="progressBar">0%</div>
        </div>
        <p class="text-muted"><small><?php _e('image_processor.please_wait'); ?></small></p>
    </div>
</div>

<script>
    let originalImage = null;
    let originalImageFullSize = null; // Image originale à pleine résolution
    let originalPdf = null; // PDF original chargé avec PDF.js
    let originalPdfPage = null; // Première page du PDF
    let originalPdfViewport = null; // Viewport de la page PDF
    let pdfRenderedCanvas = null; // Canvas avec le rendu original du PDF (sans effets)
    let previewCanvas = null;
    let previewCtx = null;
    let fullSizeCanvas = null; // Canvas à pleine résolution pour le traitement
    let fullSizeCtx = null;
    let currentImageData = null;
    let progressInterval = null;
    let isPdf = false; // Flag pour savoir si on traite un PDF

    document.addEventListener('DOMContentLoaded', function () {
        const uploadZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('fileInput');
        const hiddenFileInput = document.getElementById('hiddenFileInput');
        const processingSection = document.getElementById('processingSection');
        const previewCanvas = document.getElementById('previewCanvas');
        const previewCtx = previewCanvas.getContext('2d');

        // Références globales
        window.previewCanvas = previewCanvas;
        window.previewCtx = previewCtx;

        // Upload zone click
        uploadZone.addEventListener('click', () => fileInput.click());

        // Drag and drop
        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });

        uploadZone.addEventListener('dragleave', () => {
            uploadZone.classList.remove('dragover');
        });

        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
            if (e.dataTransfer.files.length > 0) {
                handleFile(e.dataTransfer.files[0]);
            }
        });

        // File input change
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFile(e.target.files[0]);
            }
        });

        <?php if (isset($from_lib_file)): ?>
                // Charger automatiquement le fichier depuis la bibliothèque
                (async function () {
                    const fileUrl = '?get_bibliotheque_file&id=' + encodeURIComponent(<?= $from_lib_file['id'] ?>);
                    const fileName = <?= json_encode($from_lib_file['filename']) ?>;
                    const fileType = <?= json_encode($from_lib_file['file_type'] === 'pdf' ? 'application/pdf' : 'image/png') ?>;

                    try {
                        const response = await fetch(fileUrl);
                        if (!response.ok) throw new Error('Erreur lors du chargement du fichier');

                        const blob = await response.blob();
                        const file = new File([blob], fileName, { type: fileType });

                        // Assigner le fichier au fileInput pour que le formulaire le reconnaisse
                        const dataTransfer = new DataTransfer();
                        dataTransfer.items.add(file);
                        fileInput.files = dataTransfer.files;

                        // Aussi assigner au hiddenFileInput pour la soumission
                        const hiddenDataTransfer = new DataTransfer();
                        hiddenDataTransfer.items.add(file);
                        hiddenFileInput.files = hiddenDataTransfer.files;

                        // Charger le fichier pour la prévisualisation
                        handleFile(file);
                    } catch (error) {
                        console.error('Erreur chargement fichier bibliothèque:', error);
                        showAppModal({ message: 'Erreur lors du chargement du fichier depuis la bibliothèque: ' + error.message, type: 'danger' });
                    }
                })();
        <?php endif; ?>

        // Sliders
        const sliders = {
            contrast: { slider: document.getElementById('contrastSlider'), value: document.getElementById('contrastValue'), hidden: document.getElementById('hiddenContrast') },
            brightness: { slider: document.getElementById('brightnessSlider'), value: document.getElementById('brightnessValue'), hidden: document.getElementById('hiddenBrightness') },
            gamma: { slider: document.getElementById('gammaSlider'), value: document.getElementById('gammaValue'), hidden: document.getElementById('hiddenGamma') },
            saturation: { slider: document.getElementById('saturationSlider'), value: document.getElementById('saturationValue'), hidden: document.getElementById('hiddenSaturation') },
            threshold: { slider: document.getElementById('thresholdSlider'), value: document.getElementById('thresholdValue'), hidden: document.getElementById('hiddenBitmapThreshold') }
        };

        Object.keys(sliders).forEach(key => {
            if (sliders[key].slider) {
                sliders[key].slider.addEventListener('input', () => {
                    updateSliderValue(key);
                    updatePreview();
                });
            }
        });

        // Bitmap checkbox
        document.getElementById('bitmapEnabled').addEventListener('change', (e) => {
            document.getElementById('bitmapOptions').style.display = e.target.checked ? 'block' : 'none';
            updatePreview();
        });

        // Bitmap method radio
        document.querySelectorAll('input[name="bitmapMethod"]').forEach(radio => {
            radio.addEventListener('change', () => {
                document.getElementById('thresholdOption').style.display =
                    document.querySelector('input[name="bitmapMethod"]:checked').value === 'threshold' ? 'block' : 'none';
                updatePreview();
            });
        });

        // Reset button
        document.getElementById('resetButton').addEventListener('click', () => {
            document.getElementById('contrastSlider').value = 0;
            document.getElementById('brightnessSlider').value = 0;
            document.getElementById('gammaSlider').value = 1.0;
            document.getElementById('saturationSlider').value = 0;
            document.getElementById('bitmapEnabled').checked = false;
            document.getElementById('thresholdSlider').value = 128;
            document.getElementById('bitmapOptions').style.display = 'none';
            updateSliderValues();
            updatePreview();
        });

        // Apply button
        document.getElementById('applyButton').addEventListener('click', () => {
            applyProcessing();
        });

        function updateSliderValue(key) {
            const slider = sliders[key].slider;
            const valueDisplay = sliders[key].value;
            if (slider && valueDisplay) {
                const value = parseFloat(slider.value);
                if (key === 'gamma') {
                    valueDisplay.textContent = value.toFixed(1);
                } else {
                    valueDisplay.textContent = Math.round(value);
                }
            }
        }

        function updateSliderValues() {
            Object.keys(sliders).forEach(key => updateSliderValue(key));
        }

        function handleFile(file) {
            // Vérifier le type
            const validTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp', 'application/pdf'];
            if (!validTypes.includes(file.type)) {
                showAppModal({ message: 'Format de fichier non supporté. Utilisez PDF, PNG, JPEG, GIF ou WebP.', type: 'warning' });
                return;
            }

            // Pour les PDFs, charger avec PDF.js
            if (file.type === 'application/pdf') {
                isPdf = true;
                originalImage = null;
                originalImageFullSize = null;

                // Configurer PDF.js worker (attendre que le script soit chargé)
                if (typeof pdfjsLib === 'undefined') {
                    console.error('PDF.js n\'est pas chargé. Vérifiez que js/build/pdf.js est accessible.');
                    showAppModal({ message: 'Erreur : PDF.js n\'est pas chargé. Impossible de prévisualiser le PDF.', type: 'danger' });
                    return;
                }

                // Configurer le worker avec le chemin absolu si nécessaire
                if (!pdfjsLib.GlobalWorkerOptions.workerSrc) {
                    pdfjsLib.GlobalWorkerOptions.workerSrc = 'js/build/pdf.worker.js';
                }

                // Charger le PDF
                const reader = new FileReader();
                reader.onload = async (e) => {
                    try {
                        const typedArray = new Uint8Array(e.target.result);

                        // Charger le document PDF
                        const loadingTask = pdfjsLib.getDocument({ data: typedArray });
                        const pdf = await loadingTask.promise;
                        originalPdf = pdf;

                        // Obtenir la première page
                        const page = await pdf.getPage(1);

                        // Obtenir les dimensions de la page
                        const viewport = page.getViewport({ scale: 1.0 });
                        const scale = Math.min(800 / viewport.width, 600 / viewport.height);
                        const scaledViewport = page.getViewport({ scale: scale });

                        // Créer un canvas à pleine résolution pour le traitement
                        fullSizeCanvas = document.createElement('canvas');
                        fullSizeCanvas.width = viewport.width;
                        fullSizeCanvas.height = viewport.height;
                        fullSizeCtx = fullSizeCanvas.getContext('2d');

                        // Sauvegarder la page et le viewport pour réutilisation
                        originalPdfPage = page;
                        originalPdfViewport = viewport;

                        // Créer un canvas pour stocker le rendu original (sans effets)
                        pdfRenderedCanvas = document.createElement('canvas');
                        pdfRenderedCanvas.width = viewport.width;
                        pdfRenderedCanvas.height = viewport.height;
                        const pdfRenderedCtx = pdfRenderedCanvas.getContext('2d');

                        // Rendre la page à pleine résolution sur le canvas de rendu
                        const renderContext = {
                            canvasContext: pdfRenderedCtx,
                            viewport: viewport
                        };
                        await page.render(renderContext).promise;

                        // Copier vers le canvas de traitement
                        fullSizeCtx.drawImage(pdfRenderedCanvas, 0, 0);

                        // Ajuster la taille du canvas de prévisualisation
                        previewCanvas.width = scaledViewport.width;
                        previewCanvas.height = scaledViewport.height;

                        // Redessiner à la taille de prévisualisation
                        previewCtx.drawImage(fullSizeCanvas, 0, 0, scaledViewport.width, scaledViewport.height);
                        currentImageData = previewCtx.getImageData(0, 0, scaledViewport.width, scaledViewport.height);

                        // Afficher la section de traitement
                        document.getElementById('uploadSection').style.display = 'none';
                        processingSection.style.display = 'block';

                        // Afficher le nombre de pages (vérifier si l'élément existe déjà)
                        const existingInfo = previewCanvas.parentElement.querySelector('.pdf-page-info');
                        if (existingInfo) {
                            existingInfo.remove();
                        }
                        const pageCountInfo = document.createElement('p');
                        pageCountInfo.className = 'text-info text-center pdf-page-info';
                        pageCountInfo.style.marginTop = '10px';
                        pageCountInfo.innerHTML = `<i class="fa fa-info-circle"></i> ${ "<?php echo __js('image_processor.pdf_page_info', ['count' => '']); ?>" .replace(':count', pdf.numPages)}`;
                        previewCanvas.parentElement.appendChild(pageCountInfo);

                        // Mettre à jour la prévisualisation
                        updatePreview();
                    } catch (error) {
                        console.error('Erreur lors du chargement du PDF:', error);
                        showAppModal({ message: 'Erreur lors du chargement du PDF : ' + error.message, type: 'danger' });
                    }
                };
                reader.readAsArrayBuffer(file);
                return;
            }

            // Pour les images, charger et afficher
            isPdf = false;
            originalPdf = null;
            const reader = new FileReader();
            reader.onload = (e) => {
                const img = new Image();
                img.onload = () => {
                    originalImage = img;
                    originalImageFullSize = img; // Garder l'image originale à pleine résolution

                    // Créer un canvas à pleine résolution (caché) pour le traitement final
                    fullSizeCanvas = document.createElement('canvas');
                    fullSizeCanvas.width = img.width;
                    fullSizeCanvas.height = img.height;
                    fullSizeCtx = fullSizeCanvas.getContext('2d');
                    fullSizeCtx.drawImage(img, 0, 0);

                    // Ajuster la taille du canvas de prévisualisation (pour l'affichage seulement)
                    const maxWidth = 800;
                    const maxHeight = 600;
                    let width = img.width;
                    let height = img.height;

                    if (width > maxWidth) {
                        height = (height * maxWidth) / width;
                        width = maxWidth;
                    }
                    if (height > maxHeight) {
                        width = (width * maxHeight) / height;
                        height = maxHeight;
                    }

                    previewCanvas.width = width;
                    previewCanvas.height = height;

                    // Dessiner l'image originale (redimensionnée pour prévisualisation)
                    previewCtx.drawImage(img, 0, 0, width, height);
                    currentImageData = previewCtx.getImageData(0, 0, width, height);

                    // Afficher la section de traitement
                    document.getElementById('uploadSection').style.display = 'none';
                    processingSection.style.display = 'block';

                    // Mettre à jour la prévisualisation
                    updatePreview();
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }

        // Fonction pour appliquer les effets à un ImageData
        function applyEffectsToImageData(imageData) {
            const contrast = parseFloat(document.getElementById('contrastSlider').value);
            const brightness = parseFloat(document.getElementById('brightnessSlider').value);
            const gamma = parseFloat(document.getElementById('gammaSlider').value);
            const saturation = parseFloat(document.getElementById('saturationSlider').value);
            const bitmapEnabled = document.getElementById('bitmapEnabled').checked;
            const bitmapMethod = document.querySelector('input[name="bitmapMethod"]:checked')?.value || 'threshold';
            const threshold = parseInt(document.getElementById('thresholdSlider').value);

            // Contraste
            if (contrast !== 0) {
                const contrastValue = contrast * 2.55;
                const factor = (259 * (contrastValue + 255)) / (255 * (259 - contrastValue));
                for (let i = 0; i < imageData.data.length; i += 4) {
                    imageData.data[i] = Math.min(255, Math.max(0, factor * (imageData.data[i] - 128) + 128));
                    imageData.data[i + 1] = Math.min(255, Math.max(0, factor * (imageData.data[i + 1] - 128) + 128));
                    imageData.data[i + 2] = Math.min(255, Math.max(0, factor * (imageData.data[i + 2] - 128) + 128));
                }
            }

            // Luminosité
            if (brightness !== 0) {
                if (typeof adjustBrightness === 'function') {
                    imageData = adjustBrightness(imageData, brightness);
                } else {
                    for (let i = 0; i < imageData.data.length; i += 4) {
                        imageData.data[i] = Math.min(255, Math.max(0, imageData.data[i] + brightness));
                        imageData.data[i + 1] = Math.min(255, Math.max(0, imageData.data[i + 1] + brightness));
                        imageData.data[i + 2] = Math.min(255, Math.max(0, imageData.data[i + 2] + brightness));
                    }
                }
            }

            // Gamma
            if (gamma !== 1.0) {
                for (let i = 0; i < imageData.data.length; i += 4) {
                    imageData.data[i] = Math.min(255, Math.max(0, Math.pow(imageData.data[i] / 255, 1 / gamma) * 255));
                    imageData.data[i + 1] = Math.min(255, Math.max(0, Math.pow(imageData.data[i + 1] / 255, 1 / gamma) * 255));
                    imageData.data[i + 2] = Math.min(255, Math.max(0, Math.pow(imageData.data[i + 2] / 255, 1 / gamma) * 255));
                }
            }

            // Saturation
            if (saturation !== 0) {
                const satFactor = 1 + (saturation / 100);
                for (let i = 0; i < imageData.data.length; i += 4) {
                    const gray = (imageData.data[i] + imageData.data[i + 1] + imageData.data[i + 2]) / 3;
                    imageData.data[i] = Math.min(255, Math.max(0, gray + (imageData.data[i] - gray) * satFactor));
                    imageData.data[i + 1] = Math.min(255, Math.max(0, gray + (imageData.data[i + 1] - gray) * satFactor));
                    imageData.data[i + 2] = Math.min(255, Math.max(0, gray + (imageData.data[i + 2] - gray) * satFactor));
                }
            }

            // Bitmap
            if (bitmapEnabled) {
                if (bitmapMethod === 'dithering' && typeof applyDithering === 'function') {
                    imageData = applyDithering(imageData, 'floydsteinberg');
                } else {
                    // Seuil simple
                    for (let i = 0; i < imageData.data.length; i += 4) {
                        const gray = (imageData.data[i] + imageData.data[i + 1] + imageData.data[i + 2]) / 3;
                        const val = gray < threshold ? 0 : 255;
                        imageData.data[i] = val;
                        imageData.data[i + 1] = val;
                        imageData.data[i + 2] = val;
                    }
                }
            }

            return imageData;
        }

        function updatePreview() {
            // Pour les PDFs, utiliser le canvas à pleine résolution
            if (isPdf && fullSizeCanvas && fullSizeCtx && pdfRenderedCanvas) {
                // Copier le rendu original (sans effets) vers le canvas de traitement
                fullSizeCtx.clearRect(0, 0, fullSizeCanvas.width, fullSizeCanvas.height);
                fullSizeCtx.drawImage(pdfRenderedCanvas, 0, 0);

                // Obtenir les données de l'image
                let imageData = fullSizeCtx.getImageData(0, 0, fullSizeCanvas.width, fullSizeCanvas.height);

                // Appliquer les effets
                imageData = applyEffectsToImageData(imageData);

                // Appliquer au canvas plein écran
                fullSizeCtx.putImageData(imageData, 0, 0);

                // Redessiner sur le canvas de prévisualisation (redimensionné)
                const scale = Math.min(previewCanvas.width / fullSizeCanvas.width, previewCanvas.height / fullSizeCanvas.height);
                previewCtx.clearRect(0, 0, previewCanvas.width, previewCanvas.height);
                previewCtx.drawImage(fullSizeCanvas, 0, 0, previewCanvas.width, previewCanvas.height);
                return;
            }

            if (!originalImage || !currentImageData) return;

            const width = previewCanvas.width;
            const height = previewCanvas.height;

            // Créer une copie des données
            let imageData = new ImageData(
                new Uint8ClampedArray(currentImageData.data),
                width,
                height
            );

            // Appliquer les effets avec la fonction partagée
            imageData = applyEffectsToImageData(imageData);

            // Dessiner sur le canvas
            previewCtx.putImageData(imageData, 0, 0);
        }

        function applyProcessing() {
            // Vérifier qu'un fichier est sélectionné ou chargé depuis la bibliothèque
            let file = null;
            let isPDF = false;

            if (fileInput.files.length > 0) {
                file = fileInput.files[0];
                isPDF = file.type === 'application/pdf';
            } else if (originalPdf || originalImage || fullSizeCanvas) {
                // Un fichier a été chargé depuis la bibliothèque (prévisualisation visible)
                // Utiliser le fichier depuis hiddenFileInput ou recréer depuis le blob
                if (hiddenFileInput.files.length > 0) {
                    file = hiddenFileInput.files[0];
                    isPDF = file.type === 'application/pdf';
                } else {
                    // Si aucun fichier dans les inputs, on ne peut pas continuer
                    showAppModal({ message: 'Veuillez sélectionner un fichier.', type: 'warning' });
                    return;
                }
            } else {
                showAppModal({ message: 'Veuillez sélectionner un fichier.', type: 'warning' });
                return;
            }

            // Pour les PDFs, utiliser le traitement serveur via AJAX
            if (isPDF) {
                // Créer un FormData avec le fichier et les paramètres
                const formData = new FormData();
                formData.append('file', file);

                // Ajouter lib_file_id si on vient de la bibliothèque
                const libFileId = document.getElementById('lib_file_id');
                if (libFileId && libFileId.value) {
                    formData.append('lib_file_id', libFileId.value);
                }

                formData.append('contrast', document.getElementById('contrastSlider').value);
                formData.append('brightness', document.getElementById('brightnessSlider').value);
                formData.append('gamma', document.getElementById('gammaSlider').value);
                formData.append('saturation', document.getElementById('saturationSlider').value);
                formData.append('bitmap_enabled', document.getElementById('bitmapEnabled').checked ? '1' : '0');
                formData.append('bitmap_method', document.querySelector('input[name="bitmapMethod"]:checked')?.value || 'threshold');
                formData.append('bitmap_threshold', document.getElementById('thresholdSlider').value);

                // Afficher le modal de progression
                showProgressModal();
                document.getElementById('progressMessage').textContent = 'Initialisation...';
                document.getElementById('progressBar').style.width = '0%';
                document.getElementById('progressBar').textContent = '0%';

                // Envoyer au serveur
                fetch('?image_processor', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => {
                        // Vérifier le Content-Type
                        const contentType = response.headers.get('content-type');
                        if (contentType && contentType.includes('application/json')) {
                            return response.json();
                        } else {
                            return response.text().then(text => {
                                // Essayer de parser comme JSON
                                try {
                                    return JSON.parse(text);
                                } catch (e) {
                                    return { html: text };
                                }
                            });
                        }
                    })
                    .then(data => {
                        console.log('Réponse reçue:', data);
                        // Si c'est du JSON avec progress_key, démarrer le polling
                        if (data && data.progress_key) {
                            console.log('Démarrage du polling avec la clé:', data.progress_key);
                            startProgressPolling(data.progress_key);
                        } else if (data && data.html) {
                            // Fallback : parser le HTML
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(data.html, 'text/html');

                            const successDiv = doc.querySelector('.panel-success');
                            const errorDiv = doc.querySelector('.alert-danger');

                            if (errorDiv) {
                                hideProgressModal();
                                showAppModal({ message: 'Erreur : ' + errorDiv.textContent, type: 'danger' });
                            } else if (successDiv) {
                                hideProgressModal();

                                const successContent = successDiv.outerHTML;
                                document.getElementById('processingSection').style.display = 'none';
                                document.getElementById('uploadSection').style.display = 'none';

                                const container = document.querySelector('.image-processor-container');
                                const resultContainer = document.createElement('div');
                                resultContainer.innerHTML = successContent;
                                container.insertBefore(resultContainer, container.firstChild);

                                window.scrollTo({ top: 0, behavior: 'smooth' });
                            } else {
                                hideProgressModal();
                                window.location.reload();
                            }
                        } else {
                            console.error('Réponse inattendue:', data);
                            console.error('Type de data:', typeof data);
                            console.error('Clés de data:', data ? Object.keys(data) : 'data est null/undefined');
                            // Essayer quand même de trouver progress_key dans la réponse
                            if (data && typeof data === 'object') {
                                const progressKey = data.progress_key || data.progressKey;
                                if (progressKey) {
                                    console.log('Clé de progression trouvée dans data:', progressKey);
                                    startProgressPolling(progressKey);
                                    return;
                                }
                            }
                            hideProgressModal();
                            showAppModal({ message: 'Réponse inattendue du serveur. Vérifiez la console pour plus de détails.', type: 'danger' });
                        }
                    })
                    .catch(error => {
                        console.error('Erreur lors de la requête:', error);
                        console.error('Stack trace:', error.stack);
                        hideProgressModal();
                        showAppModal({ message: 'Une erreur est survenue lors de l\'envoi. Vérifiez la console pour plus de détails.', type: 'danger' });
                    });
                return;
            }

            // Pour les images : utiliser le canvas à pleine résolution
            if (!originalImageFullSize || !fullSizeCanvas) {
                showAppModal({ message: 'Erreur : image non chargée.', type: 'danger' });
                return;
            }

            // Appliquer les traitements au canvas à pleine résolution
            applyProcessingToFullSizeCanvas();

            // Convertir le canvas en blob et l'envoyer
            fullSizeCanvas.toBlob((blob) => {
                if (!blob) {
                    showAppModal({ message: 'Erreur lors de la conversion du canvas.', type: 'danger' });
                    return;
                }

                // Créer un FormData avec l'image traitée
                const formData = new FormData();
                formData.append('processed_image', blob, file.name);
                formData.append('use_canvas', '1'); // Flag pour indiquer qu'on utilise le canvas
                formData.append('original_filename', file.name);

                // Ajouter lib_file_id si on vient de la bibliothèque
                const libFileId = document.getElementById('lib_file_id');
                if (libFileId && libFileId.value) {
                    formData.append('lib_file_id', libFileId.value);
                }

                // Afficher le modal de progression
                showProgressModal();
                document.getElementById('progressMessage').textContent = 'Envoi de l\'image traitée...';
                document.getElementById('progressBar').style.width = '0%';
                document.getElementById('progressBar').textContent = '0%';

                // Envoyer au serveur
                fetch('?image_processor', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.text())
                    .then(html => {
                        // Parser la réponse pour extraire les informations
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');

                        // Vérifier s'il y a des erreurs
                        const errorDiv = doc.querySelector('.alert-danger');
                        if (errorDiv) {
                            hideProgressModal();
                            showAppModal({ message: 'Erreur : ' + errorDiv.textContent, type: 'danger' });
                            return;
                        }

                        // Vérifier le succès
                        const successDiv = doc.querySelector('.panel-success');
                        if (successDiv) {
                            hideProgressModal();

                            // Extraire le contenu du panneau de succès
                            const successContent = successDiv.outerHTML;

                            // Cacher la section de traitement
                            document.getElementById('processingSection').style.display = 'none';
                            document.getElementById('uploadSection').style.display = 'none';

                            // Créer un conteneur pour le résultat et l'insérer avant le container principal
                            const container = document.querySelector('.image-processor-container');
                            const resultContainer = document.createElement('div');
                            resultContainer.innerHTML = successContent;
                            container.insertBefore(resultContainer, container.firstChild);

                            // Faire défiler vers le haut pour voir le résultat
                            window.scrollTo({ top: 0, behavior: 'smooth' });
                        } else {
                            hideProgressModal();
                            // Afficher la réponse pour debug
                            console.error('Réponse inattendue:', html);
                            showAppModal({ message: 'Réponse inattendue du serveur. Vérifiez la console pour plus de détails.', type: 'danger' });
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        hideProgressModal();
                        showAppModal({ message: 'Une erreur est survenue lors de l\'envoi.', type: 'danger' });
                    });
            }, file.type || 'image/png', 1.0); // Qualité maximale
        }

        function applyProcessingToFullSizeCanvas() {
            // Pour les PDFs
            if (isPdf && fullSizeCanvas && fullSizeCtx && pdfRenderedCanvas) {
                // Copier le rendu original
                fullSizeCtx.clearRect(0, 0, fullSizeCanvas.width, fullSizeCanvas.height);
                fullSizeCtx.drawImage(pdfRenderedCanvas, 0, 0);

                // Obtenir les données et appliquer les effets
                let imageData = fullSizeCtx.getImageData(0, 0, fullSizeCanvas.width, fullSizeCanvas.height);
                imageData = applyEffectsToImageData(imageData);
                fullSizeCtx.putImageData(imageData, 0, 0);
                return;
            }

            // Pour les images
            if (!originalImageFullSize || !fullSizeCanvas || !fullSizeCtx) return;

            // Redessiner l'image originale à pleine résolution
            fullSizeCtx.drawImage(originalImageFullSize, 0, 0);
            let imageData = fullSizeCtx.getImageData(0, 0, fullSizeCanvas.width, fullSizeCanvas.height);

            // Appliquer les effets avec la fonction partagée
            imageData = applyEffectsToImageData(imageData);

            // Appliquer à l'image complète
            fullSizeCtx.putImageData(imageData, 0, 0);
        }

        function showProgressModal() {
            const modal = document.getElementById('progressModal');
            modal.style.display = 'block';

            // Démarrer le polling de progression (si on a une clé)
            // Le polling sera démarré après la soumission si nécessaire
        }

        function hideProgressModal() {
            const modal = document.getElementById('progressModal');
            modal.style.display = 'none';
            if (progressInterval) {
                clearInterval(progressInterval);
                progressInterval = null;
            }
        }

        // Initialiser les valeurs des sliders
        updateSliderValues();

        // Fonction pour démarrer le polling de progression
        function startProgressPolling(progressKey) {
            if (!progressKey) {
                console.error('startProgressPolling appelé sans clé');
                return;
            }

            console.log('Démarrage du polling avec la clé:', progressKey);

            // Variable pour suivre si le traitement est terminé
            let isCompleted = false;
            let isProcessingCompleted = false; // Verrou pour éviter les doublons
            let pollInProgress = false; // Verrou pour éviter les requêtes simultanées

            // Fonction pour traiter le statut "completed" ou "error"
            const handleCompletion = (data) => {
                // Vérifier si on a déjà traité le completed ET terminé le traitement
                // Ne bloquer que si vraiment terminé, pas juste si une requête précédente a échoué
                if (isCompleted) {
                    console.log('Traitement déjà terminé, on ignore');
                    return false;
                }

                // Activer les verrous immédiatement pour éviter les doublons
                isProcessingCompleted = true;
                isCompleted = true;

                // Arrêter immédiatement le polling
                if (progressInterval) {
                    clearInterval(progressInterval);
                    progressInterval = null;
                }

                // Fermer la modal
                hideProgressModal();

                if (data.status === 'completed') {
                    // Vérifier si le résultat n'a pas déjà été affiché
                    const existingResult = document.querySelector('.image-processor-container .panel-success');
                    if (existingResult) {
                        console.log('Résultat déjà affiché, on ignore');
                        return true;
                    }

                    // Afficher le résultat une seule fois
                    if (data.download_url) {
                        // Créer un message de succès avec lien de téléchargement
                        const container = document.querySelector('.image-processor-container');
                        const resultDiv = document.createElement('div');
                        resultDiv.className = 'panel panel-success';
                        resultDiv.innerHTML = `
                        <div class="panel-heading">
                            <h3 class="panel-title"><i class="fa fa-check-circle"></i> <?php _ejs('image_processor.processing_success_msg'); ?></h3>
                        </div>
                        <div class="panel-body">
                            <p>${ "<?php echo __js('image_processor.file_processed_success', ['filename' => '']); ?>" .replace(':filename', `<strong>${data.filename || "<?php echo __js('common.completed'); ?>"}</strong>`)}</p>
                            <div class="btn-group">
                                <a href="${data.download_url}" class="btn btn-primary btn-lg" download>
                                    <i class="fa fa-download"></i> <?php _ejs('image_processor.download_file'); ?>
                                </a>
                                <button type="button" class="btn btn-warning btn-lg" onclick="window.location.reload()">
                                    <i class="fa fa-upload"></i> <?php _ejs('image_processor.upload_new'); ?>
                                </button>
                            </div>
                        </div>
                    `;
                        container.insertBefore(resultDiv, container.firstChild);

                        // Cacher les sections
                        const processingSection = document.getElementById('processingSection');
                        const uploadSection = document.getElementById('uploadSection');
                        if (processingSection) processingSection.style.display = 'none';
                        if (uploadSection) uploadSection.style.display = 'none';

                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    } else {
                        window.location.reload();
                    }
                } else {
                    showAppModal({ message: 'Erreur : ' + (data.message || 'Une erreur est survenue'), type: 'danger' });
                }

                return true;
            };

            // Fonction pour faire une requête de polling
            const doPoll = () => {
                // Ne pas continuer si déjà terminé
                if (isCompleted) {
                    return;
                }

                // Ne pas faire de requête si une requête est déjà en cours
                if (pollInProgress) {
                    return;
                }

                // Activer le verrou de requête
                pollInProgress = true;

                // Utiliser l'API légère pour éviter les timeouts et la charge inutile
                fetch('api/check_image_progress.php?key=' + encodeURIComponent(progressKey))
                    .then(response => {
                        // Gérer les timeouts (504) - continuer à poller
                        if (response.status === 504) {
                            console.log('Timeout lors du polling, le traitement continue...');
                            pollInProgress = false; // Libérer le verrou immédiatement

                            // Mettre à jour la modal pour indiquer que le traitement continue malgré le timeout
                            const progressMessage = document.getElementById('progressMessage');
                            if (progressMessage) {
                                const currentText = progressMessage.textContent;
                                // Ne pas changer le message si on a déjà un pourcentage
                                if (!currentText.includes('%') && !currentText.includes('Timeout')) {
                                    progressMessage.textContent = 'Traitement en cours (timeout serveur, mais le traitement continue)...';
                                }
                            }

                            return null; // Retourner null pour ignorer cette requête
                        }

                        if (!response.ok) {
                            throw new Error('HTTP ' + response.status);
                        }
                        return response.json();
                    })
                    .then(data => {
                        // Libérer le verrou de requête
                        pollInProgress = false;

                        // Ignorer si null (timeout)
                        if (!data) {
                            return;
                        }

                        console.log('Données de progression reçues:', data);

                        if (data.status === 'completed' || data.status === 'error') {
                            handleCompletion(data);
                        } else if (data.status === 'processing') {
                            // Mettre à jour la barre de progression
                            const progressBar = document.getElementById('progressBar');
                            const progressMessage = document.getElementById('progressMessage');
                            if (progressBar && progressMessage) {
                                const percentage = Math.min(100, Math.max(0, (data.current / data.total) * 100));
                                progressBar.style.width = percentage + '%';
                                progressBar.textContent = Math.round(percentage) + '%';
                                progressMessage.textContent = data.message || 'Traitement en cours...';
                            }
                        }
                    })
                    .catch(error => {
                        // Libérer le verrou de requête en cas d'erreur
                        pollInProgress = false;

                        // Ne pas arrêter le polling en cas d'erreur réseau (timeout, etc.)
                        // Le traitement continue côté serveur
                        // Mais vérifier si on est déjà terminé
                        if (isCompleted) {
                            return;
                        }

                        if (error.message && (error.message.includes('504') || error.message.includes('timeout'))) {
                            // Timeout - c'est normal pendant le traitement, on continue
                            console.log('Timeout réseau, le traitement continue côté serveur...');
                        } else {
                            console.error('Erreur lors du polling de progression:', error);
                        }
                    });
            };

            // Faire une première requête immédiatement
            doPoll();

            // Continuer le polling toutes les 500ms
            progressInterval = setInterval(() => {
                // Vérifier avant chaque requête si on est terminé
                if (!isCompleted) {
                    doPoll();
                } else {
                    // Nettoyer si terminé
                    if (progressInterval) {
                        clearInterval(progressInterval);
                        progressInterval = null;
                    }
                }
            }, 500);
        }

        // Si on a un résultat avec une clé de progression mais que le traitement n'est pas terminé
        // (cela ne devrait pas arriver car le traitement est synchrone, mais on le gère au cas où)
        <?php if (isset($result['progress_key']) && !$success): ?>
            // Le traitement est en cours, démarrer le polling
            showProgressModal();
            startProgressPolling('<?= htmlspecialchars($result['progress_key']) ?>');
        <?php endif; ?>
    });
</script>