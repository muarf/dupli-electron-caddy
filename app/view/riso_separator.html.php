<!-- JSZip pour export multiple (local) -->
<script src="public/js/jszip.min.js"></script>
<!-- Riso Tools - Fonctions avancées -->
<script src="public/js/riso-tools.js"></script>

<style>
    .riso-container {
        max-width: 1200px;
        margin: 20px auto;
        padding: 20px;
    }

    .riso-header {
        background: linear-gradient(135deg, #ff6b9d 0%, #c06c84 100%);
        color: white;
        padding: 30px;
        border-radius: 10px;
        text-align: center;
        margin-bottom: 30px;
    }

    .upload-zone {
        border: 3px dashed #ff6b9d;
        border-radius: 15px;
        padding: 50px;
        text-align: center;
        background: #fff5f8;
        cursor: pointer;
        transition: all 0.3s;
    }

    .upload-zone:hover {
        border-color: #c06c84;
        background: #ffe8f0;
    }

    .channel-panel {
        background: white;
        border: 2px solid #e0e0e0;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 15px;
    }

    .canvas-container {
        display: inline-block;
        margin: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        overflow: hidden;
    }

    canvas {
        display: block;
        max-width: 100%;
        height: auto;
    }

    .layer-controls {
        margin-top: 15px;
    }

    .preview-canvas {
        border: 2px solid #333;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
    }

    .nav-tabs {
        margin-bottom: 20px;
    }

    .tab-content {
        margin-bottom: 20px;
    }
</style>

<div class="riso-container">
    <!-- En-tête -->
    <div class="riso-header">
        <h1><i class="fa fa-palette"></i> <?php _e('riso_separator.title'); ?></h1>
        <p><?php _e('riso_separator.subtitle'); ?></p>
    </div>

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
            <div style="font-size: 64px; color: #ff6b9d; margin-bottom: 20px;">
                <i class="fa fa-cloud-upload"></i>
            </div>
            <h3><?php _e('riso_separator.drag_drop'); ?></h3>
            <p class="text-muted"><?php _e('riso_separator.click_select'); ?></p>
            <input type="file" id="imageInput" accept="image/png,image/jpeg,image/jpg" style="display: none;">
            <button type="button" class="btn btn-lg"
                style="background: #ff6b9d; color: white; border: none; padding: 12px 30px; border-radius: 25px; margin-top: 15px;">
                <i class="fa fa-upload"></i> <?php _e('riso_separator.select_image'); ?>
            </button>
        </div>
    </div>

    <!-- Contrôles et Prévisualisation -->
    <div id="separatorSection" style="display: none;">
        <!-- Image originale -->
        <div class="panel panel-default">
            <div class="panel-heading">
                <h4><i class="fa fa-image"></i> <?php _e('riso_separator.original_image'); ?></h4>
            </div>
            <div class="panel-body text-center">
                <canvas id="originalCanvas" style="cursor: crosshair;"></canvas>
                <p class="text-muted" style="margin-top: 10px;">
                    <i class="fa fa-info-circle"></i> <?php _e('riso_separator.click_to_isolate_color'); ?>
                </p>
            </div>
        </div>

        <!-- Outils Communs (Effets) -->
        <div class="panel panel-default">
            <div class="panel-heading">
                <h4 class="panel-title">
                    <a data-toggle="collapse" href="#commonTools">
                        <i class="fa fa-cogs"></i> <?php _e('riso_separator.tools'); ?> &
                        <?php _e('riso_separator.effects'); ?> <small>(S'applique au mode actif)</small> <i
                            class="fa fa-caret-down"></i>
                    </a>
                </h4>
            </div>
            <div id="commonTools" class="panel-collapse collapse in">
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-4">
                            <button class="btn btn-warning btn-block" onclick="resetChannels()">
                                <i class="fa fa-undo"></i> <?php _e('riso_separator.reset_channels'); ?>
                            </button>
                        </div>
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-addon"><?php _e('riso_separator.levels'); ?></span>
                                <input type="number" class="form-control" id="posterLevels" value="4" min="2" max="10">
                                <span class="input-group-btn">
                                    <button class="btn btn-info" onclick="applyPosterization()"><i class="fa fa-th"></i>
                                        <?php _e('riso_separator.posterize_button'); ?></button>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-addon"><?php _e('common.size'); ?></span>
                                <input type="number" class="form-control" id="halftoneSize" value="3" min="1" max="10">
                                <span class="input-group-btn">
                                    <button class="btn btn-info" onclick="applyHalftoneEffect()"><i
                                            class="fa fa-th-large"></i>
                                        <?php _e('riso_separator.halftone_trames'); ?></button>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Onglets -->
        <ul class="nav nav-tabs" role="tablist" id="modeTabs">
            <li role="presentation" class="active"><a href="#pane-rgb" aria-controls="pane-rgb" role="tab"
                    data-toggle="tab" onclick="switchTab('RGB')"><?php _e('riso_separator.rgb_3_channels'); ?></a></li>
            <li role="presentation"><a href="#pane-cmyk" aria-controls="pane-cmyk" role="tab" data-toggle="tab"
                    onclick="switchTab('CMYK')"><?php _e('riso_separator.cmyk_4_channels'); ?></a></li>
            <li role="presentation"><a href="#pane-2color" aria-controls="pane-2color" role="tab" data-toggle="tab"
                    onclick="switchTab('2COLOR')"><?php _e('riso_separator.2_drums_nb'); ?></a></li>
            <li role="presentation"><a href="#pane-pipette" aria-controls="pane-pipette" role="tab" data-toggle="tab"
                    onclick="switchTab('PIPETTE')"><?php _e('riso_separator.pipette_isolate_color'); ?></a></li>
        </ul>

        <div class="tab-content">
            <!-- RGB PANE -->
            <div role="tabpanel" class="tab-pane active" id="pane-rgb">
                <div class="row">
                    <?php
                    $tambours = [
                        'red' => __('riso_separator.red'),
                        'black' => __('riso_separator.black'),
                        'blue' => __('riso_separator.blue'),
                        'yellow' => __('riso_separator.yellow'),
                        'green' => __('riso_separator.green'),
                        'violet' => __('riso_separator.violet'),
                        'none' => __('riso_separator.none')
                    ];

                    $rgbChannels = [
                        ['id' => 'rgbRed', 'name' => __('riso_separator.red_channel'), 'color' => '#ff0000', 'default' => 'red'],
                        ['id' => 'rgbGreen', 'name' => __('riso_separator.green_channel'), 'color' => '#ff0000', 'default' => 'green'],
                        ['id' => 'rgbBlue', 'name' => __('riso_separator.blue_channel'), 'color' => '#0000ff', 'default' => 'blue']
                    ];

                    foreach ($rgbChannels as $chan) { ?>
                        <div class="col-md-4">
                            <div class="channel-panel">
                                <h5><i class="fa fa-circle" style="color: <?php echo $chan['color']; ?>;"></i>
                                    <?php echo $chan['name']; ?></h5>
                                <canvas id="<?php echo $chan['id']; ?>Canvas" class="img-thumbnail"></canvas>
                                <div class="layer-controls">
                                    <label><?php _e('riso_separator.drum'); ?></label>
                                    <select class="form-control tambour-select" id="<?php echo $chan['id']; ?>Tambour"
                                        onchange="updatePreview()">
                                        <?php foreach ($tambours as $val => $label) {
                                            $selected = ($val == $chan['default']) ? 'selected' : '';
                                            echo "<option value="$val" $selected>$label</option>";
                                        } ?>
                                    </select>
                                    <label style="margin-top: 10px;"><?php _e('riso_separator.opacity_label'); ?> <span
                                            id="<?php echo $chan['id']; ?>Opacity">100</span>%</label>
                                    <input type="range" class="form-control" id="<?php echo $chan['id']; ?>OpacitySlider"
                                        min="0" max="100" value="100"
                                        oninput="updateOpacityLabel('<?php echo $chan['id']; ?>'); updatePreview()">
                                    <button class="btn btn-sm btn-success btn-block" style="margin-top: 10px;"
                                        onclick="exportChannel('<?php echo $chan['id']; ?>')">
                                        <i class="fa fa-download"></i> <?php _e('riso_separator.export_png'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <!-- CMYK PANE -->
            <div role="tabpanel" class="tab-pane" id="pane-cmyk">
                <div class="row">
                    <?php
                    $cmykChannels = [
                        ['id' => 'cmykCyan', 'name' => __('riso_separator.cyan_channel'), 'color' => 'cyan', 'default' => 'blue'],
                        ['id' => 'cmykMagenta', 'name' => __('riso_separator.magenta_channel'), 'color' => 'magenta', 'default' => 'red'],
                        ['id' => 'cmykYellow', 'name' => __('riso_separator.yellow_channel'), 'color' => 'yellow', 'default' => 'yellow'],
                        ['id' => 'cmykBlack', 'name' => __('riso_separator.black_channel'), 'color' => 'black', 'default' => 'black']
                    ];

                    foreach ($cmykChannels as $chan) { ?>
                        <div class="col-md-3">
                            <div class="channel-panel">
                                <h5><i class="fa fa-circle" style="color: <?php echo $chan['color']; ?>;"></i>
                                    <?php echo $chan['name']; ?></h5>
                                <canvas id="<?php echo $chan['id']; ?>Canvas" class="img-thumbnail"></canvas>
                                <div class="layer-controls">
                                    <label><?php _e('riso_separator.drum'); ?></label>
                                    <select class="form-control tambour-select" id="<?php echo $chan['id']; ?>Tambour"
                                        onchange="updatePreview()">
                                        <?php foreach ($tambours as $val => $label) {
                                            $selected = ($val == $chan['default']) ? 'selected' : '';
                                            echo "<option value="$val" $selected>$label</option>";
                                        } ?>
                                    </select>
                                    <label style="margin-top: 10px;"><?php _e('riso_separator.opacity_label'); ?> <span
                                            id="<?php echo $chan['id']; ?>Opacity">100</span>%</label>
                                    <input type="range" class="form-control" id="<?php echo $chan['id']; ?>OpacitySlider"
                                        min="0" max="100" value="100"
                                        oninput="updateOpacityLabel('<?php echo $chan['id']; ?>'); updatePreview()">
                                    <button class="btn btn-sm btn-success btn-block" style="margin-top: 10px;"
                                        onclick="exportChannel('<?php echo $chan['id']; ?>')">
                                        <i class="fa fa-download"></i> <?php _e('riso_separator.export_png'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <!-- 2COLOR PANE -->
            <div role="tabpanel" class="tab-pane" id="pane-2color">
                <div class="row">
                    <?php
                    $twoChannels = [
                        ['id' => 'twoDark', 'name' => __('riso_separator.dark_tones'), 'color' => 'black', 'default' => 'black'],
                        ['id' => 'twoLight', 'name' => __('riso_separator.light_tones'), 'color' => 'gray', 'default' => 'red']
                    ];

                    foreach ($twoChannels as $chan) { ?>
                        <div class="col-md-6">
                            <div class="channel-panel">
                                <h5><i class="fa fa-circle" style="color: <?php echo $chan['color']; ?>;"></i>
                                    <?php echo $chan['name']; ?></h5>
                                <canvas id="<?php echo $chan['id']; ?>Canvas" class="img-thumbnail"></canvas>
                                <div class="layer-controls">
                                    <label><?php _e('riso_separator.drum'); ?></label>
                                    <select class="form-control tambour-select" id="<?php echo $chan['id']; ?>Tambour"
                                        onchange="updatePreview()">
                                        <?php foreach ($tambours as $val => $label) {
                                            $selected = ($val == $chan['default']) ? 'selected' : '';
                                            echo "<option value="$val" $selected>$label</option>";
                                        } ?>
                                    </select>
                                    <label style="margin-top: 10px;"><?php _e('riso_separator.opacity_label'); ?> <span
                                            id="<?php echo $chan['id']; ?>Opacity">100</span>%</label>
                                    <input type="range" class="form-control" id="<?php echo $chan['id']; ?>OpacitySlider"
                                        min="0" max="100" value="100"
                                        oninput="updateOpacityLabel('<?php echo $chan['id']; ?>'); updatePreview()">
                                    <button class="btn btn-sm btn-success btn-block" style="margin-top: 10px;"
                                        onclick="exportChannel('<?php echo $chan['id']; ?>')">
                                        <i class="fa fa-download"></i> <?php _e('riso_separator.export_png'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <!-- PIPETTE PANE -->
            <div role="tabpanel" class="tab-pane" id="pane-pipette">
                <div class="panel panel-warning">
                    <div class="panel-heading">
                        <h4><i class="fa fa-eyedropper"></i> <?php _e('riso_separator.pipette_isolate_color'); ?></h4>
                    </div>
                    <div class="panel-body">
                        <button class="btn btn-primary btn-block btn-lg" id="pipetteBtn" onclick="togglePipette()">
                            <i class="fa fa-eyedropper"></i> <?php _e('riso_separator.activate_pipette'); ?>
                        </button>

                        <div id="pipetteInfo" style="display: none; margin-top: 15px;">
                            <div class="alert alert-info">
                                <div class="row">
                                    <div class="col-md-6">
                                        <small><?php _e('riso_separator.selected_color'); ?> <span id="pickedColor"
                                                style="display: inline-block; width: 30px; height: 30px; border: 1px solid #000; vertical-align: middle;"></span></small>
                                    </div>
                                    <div class="col-md-6">
                                        <label><?php _e('riso_separator.tolerance'); ?> <span
                                                id="toleranceValue">30</span></label>
                                        <input type="range" class="form-control" id="toleranceSlider" min="0" max="100"
                                            value="30">
                                    </div>
                                </div>
                                <button class="btn btn-success btn-block" style="margin-top: 10px;"
                                    onclick="applyPipette()">
                                    <i class="fa fa-check"></i> <?php _e('riso_separator.validate_create_layer'); ?>
                                </button>
                            </div>
                        </div>

                        <div id="isolatedLayersContainer" style="margin-top: 20px;"></div>
                    </div>
                </div>

                <div class="panel panel-success">
                    <div class="panel-heading">
                        <h4><i class="fa fa-eye"></i> <?php _e('riso_separator.preview_isolated_layers'); ?></h4>
                    </div>
                    <div class="panel-body text-center">
                        <canvas id="isolatedPreviewCanvas" class="preview-canvas"></canvas>
                        <div style="margin-top: 20px;">
                            <button class="btn btn-success btn-lg" onclick="exportAllIsolated()">
                                <i class="fa fa-download"></i> <?php _e('riso_separator.export_all_layers_zip'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Prévisualisation superposition (Partagée pour RGB/CMYK/2COLOR) -->
        <div class="panel panel-success" id="mainPreviewPanel">
            <div class="panel-heading">
                <h4><i class="fa fa-eye"></i> <?php _e('riso_separator.preview_layer_superposition'); ?></h4>
            </div>
            <div class="panel-body text-center">
                <canvas id="previewCanvas" class="preview-canvas"></canvas>
                <div style="margin-top: 20px;">
                    <button class="btn btn-primary btn-lg" onclick="updatePreview()">
                        <i class="fa fa-refresh"></i> <?php _e('riso_separator.refresh_preview'); ?>
                    </button>
                    <button class="btn btn-success btn-lg" onclick="exportAll()">
                        <i class="fa fa-download"></i> <?php _e('riso_separator.export_all_layers_zip'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Boutons d'action -->
        <div class="text-center" style="margin-top: 30px;">
            <button class="btn btn-default btn-lg" onclick="resetSeparator()">
                <i class="fa fa-refresh"></i> <?php _e('riso_separator.new_image'); ?>
            </button>
            <a href="?accueil" class="btn btn-default btn-lg">
                <i class="fa fa-home"></i> <?php _e('riso_separator.back_to_home'); ?>
            </a>
        </div>
    </div>

    <!-- Panneau d'information -->
    <div class="panel panel-info" style="margin-top: 30px;">
        <div class="panel-heading">
            <h4><i class="fa fa-info-circle"></i> <?php _e('riso_separator.usage_guide'); ?></h4>
        </div>
        <div class="panel-body">
            <h5><i class="fa fa-book"></i> <?php _e('riso_separator.basic_workflow'); ?></h5>
            <ol>
                <li><?php _e('riso_separator.step1'); ?></li>
                <li><?php _e('riso_separator.step2'); ?></li>
                <li><?php _e('riso_separator.step3'); ?></li>
                <li><?php _e('riso_separator.step4'); ?></li>
                <li><?php _e('riso_separator.step5'); ?></li>
                <li><?php _e('riso_separator.step6'); ?></li>
            </ol>

            <h5><i class="fa fa-magic"></i> <?php _e('riso_separator.advanced_features'); ?></h5>
            <ul>
                <li><?php _e('riso_separator.rgb_mode'); ?></li>
                <li><?php _e('riso_separator.cmyk_mode'); ?></li>
                <li><?php _e('riso_separator.two_drums_mode'); ?></li>
                <li><?php _e('riso_separator.pipette'); ?></li>
                <li><?php _e('riso_separator.posterize'); ?></li>
                <li><?php _e('riso_separator.halftone'); ?></li>
            </ul>
        </div>
    </div>
</div>

<script>
    // Les couleurs Riso sont définies dans riso-tools.js
    if (typeof RISO_COLORS !== 'undefined') {
        RISO_COLORS['none'] = null;
    }

    // Variables globales
    let originalImage = null;
    let originalImageData = null;
    let currentMode = 'RGB'; // RGB, CMYK, 2COLOR, PIPETTE
    let pipetteActive = false;
    let pickedColorRGB = null;

    // Stockage des canaux par mode
    let channels = {
        rgb: { red: null, green: null, blue: null },
        cmyk: { cyan: null, magenta: null, yellow: null, black: null },
        two: { dark: null, light: null },
        isolated: null // Pour pipette
    };

    // Sauvegarde des canaux originaux (avant effets)
    let originalChannels = {
        rgb: { red: null, green: null, blue: null },
        cmyk: { cyan: null, magenta: null, yellow: null, black: null },
        two: { dark: null, light: null }
    };

    // Variables pour les couches isolées par pipette
    let isolatedLayers = [];
    let currentWorkingImageData = null;
    let posterizedSelection = null;
    let halftonedSelection = null;
    let originalFileName = 'riso';

    // Initialisation
    document.addEventListener('DOMContentLoaded', function () {
        const uploadZone = document.getElementById('uploadZone');
        const imageInput = document.getElementById('imageInput');

        // Click sur zone d'upload
        uploadZone.addEventListener('click', () => imageInput.click());
        document.querySelector('.upload-zone button').addEventListener('click', (e) => {
            e.stopPropagation();
            imageInput.click();
        });

        // Sélection fichier
        imageInput.addEventListener('change', function (e) {
            if (this.files && this.files[0]) {
                loadImage(this.files[0]);
            }
        });

        <?php if (isset($from_lib_file)): ?>
                // Charger automatiquement le fichier depuis la bibliothèque
                (async function () {
                    const fileUrl = '?get_bibliotheque_file&id=' + encodeURIComponent(<?= $from_lib_file['id'] ?>);
                    const fileName = <?= json_encode($from_lib_file['filename']) ?>;
                    const imageInput = document.getElementById('imageInput');

                    try {
                        const response = await fetch(fileUrl);
                        if (!response.ok) throw new Error('Erreur lors du chargement du fichier');

                        const blob = await response.blob();
                        const file = new File([blob], fileName, { type: 'image/png' });

                        // Assigner le fichier au fileInput pour cohérence
                        if (imageInput) {
                            const dataTransfer = new DataTransfer();
                            dataTransfer.items.add(file);
                            imageInput.files = dataTransfer.files;
                        }

                        loadImage(file);
                    } catch (error) {
                        console.error('Erreur chargement fichier bibliothèque:', error);
                        showAppModal({ message: 'Erreur lors du chargement du fichier depuis la bibliothèque: ' + error.message, type: 'danger' });
                    }
                })();
        <?php endif; ?>

        // Drag & drop
        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.style.borderColor = '#c06c84';
            uploadZone.style.background = '#ffe8f0';
        });

        uploadZone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            uploadZone.style.borderColor = '#ff6b9d';
            uploadZone.style.background = '#fff5f8';
        });

        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.style.borderColor = '#ff6b9d';
            uploadZone.style.background = '#fff5f8';

            if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                const file = e.dataTransfer.files[0];
                if (file.type.startsWith('image/')) {
                    loadImage(file);
                } else {
                    showAppModal({ message: 'Veuillez sélectionner une image valide (PNG ou JPG).', type: 'warning' });
                }
            }
        });

        // Slider tolérance pipette
        document.getElementById('toleranceSlider').addEventListener('input', function () {
            document.getElementById('toleranceValue').textContent = this.value;
            if (pipetteActive && pickedColorRGB) {
                updatePipettePreview();
            }
        });
    });

    function updateOpacityLabel(id) {
        document.getElementById(id + 'Opacity').textContent = document.getElementById(id + 'OpacitySlider').value;
    }

    // Charger et afficher l'image
    function loadImage(file) {
        originalFileName = file.name.replace(/\.[^/.]+$/, "").replace(/[^a-zA-Z0-9_-]/g, '_');

        const reader = new FileReader();
        reader.onload = function (e) {
            const img = new Image();
            img.onload = function () {
                originalImage = img;
                processImage(img);
                document.getElementById('uploadSection').style.display = 'none';
                document.getElementById('separatorSection').style.display = 'block';
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }

    // Traiter l'image et préparer tous les canaux
    function processImage(img) {
        // Afficher l'image originale
        const originalCanvas = document.getElementById('originalCanvas');
        const maxWidth = 600;
        const scale = Math.min(1, maxWidth / img.width);
        originalCanvas.width = img.width * scale;
        originalCanvas.height = img.height * scale;
        const ctx = originalCanvas.getContext('2d', { willReadFrequently: true });
        ctx.drawImage(img, 0, 0, originalCanvas.width, originalCanvas.height);

        // Sauvegarder l'ImageData original
        const fullCanvas = document.createElement('canvas');
        fullCanvas.width = img.width;
        fullCanvas.height = img.height;
        const fullCtx = fullCanvas.getContext('2d');
        fullCtx.drawImage(img, 0, 0);
        originalImageData = fullCtx.getImageData(0, 0, img.width, img.height);

        // Initialiser l'image de travail pour pipette
        currentWorkingImageData = new ImageData(
            new Uint8ClampedArray(originalImageData.data),
            originalImageData.width,
            originalImageData.height
        );

        // Reset pipette layers
        isolatedLayers = [];
        document.getElementById('isolatedLayersContainer').innerHTML = '';

        // --- GENERATION DE TOUS LES CANAUX ---

        // 1. RGB
        separateChannelsRGB(img);

        // 2. CMYK
        separateChannelsCMYK(img);

        // 3. 2 Colors
        separateChannels2Color(img);

        // Update preview (starts on RGB)
        updatePreview();
    }

    // Séparer RGB
    function separateChannelsRGB(img) {
        const canvas = document.createElement('canvas');
        canvas.width = img.width;
        canvas.height = img.height;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0);

        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const data = imageData.data;

        const redData = ctx.createImageData(canvas.width, canvas.height);
        const greenData = ctx.createImageData(canvas.width, canvas.height);
        const blueData = ctx.createImageData(canvas.width, canvas.height);

        for (let i = 0; i < data.length; i += 4) {
            // Red
            redData.data[i] = data[i]; redData.data[i + 1] = data[i]; redData.data[i + 2] = data[i]; redData.data[i + 3] = 255;
            // Green
            greenData.data[i] = data[i + 1]; greenData.data[i + 1] = data[i + 1]; greenData.data[i + 2] = data[i + 1]; greenData.data[i + 3] = 255;
            // Blue
            blueData.data[i] = data[i + 2]; blueData.data[i + 1] = data[i + 2]; blueData.data[i + 2] = data[i + 2]; blueData.data[i + 3] = 255;
        }

        displayChannel('rgbRedCanvas', redData, canvas.width, canvas.height);
        displayChannel('rgbGreenCanvas', greenData, canvas.width, canvas.height);
        displayChannel('rgbBlueCanvas', blueData, canvas.width, canvas.height);

        channels.rgb.red = redData;
        channels.rgb.green = greenData;
        channels.rgb.blue = blueData;

        originalChannels.rgb.red = cloneImageData(redData);
        originalChannels.rgb.green = cloneImageData(greenData);
        originalChannels.rgb.blue = cloneImageData(blueData);
    }

    // Séparer CMYK
    function separateChannelsCMYK(img) {
        const channelData = extractCMYKChannels(img); // from riso-tools.js

        displayChannel('cmykCyanCanvas', channelData.cyan, img.width, img.height);
        displayChannel('cmykMagentaCanvas', channelData.magenta, img.width, img.height);
        displayChannel('cmykYellowCanvas', channelData.yellow, img.width, img.height);
        displayChannel('cmykBlackCanvas', channelData.black, img.width, img.height);

        channels.cmyk.cyan = channelData.cyan;
        channels.cmyk.magenta = channelData.magenta;
        channels.cmyk.yellow = channelData.yellow;
        channels.cmyk.black = channelData.black;

        originalChannels.cmyk.cyan = cloneImageData(channelData.cyan);
        originalChannels.cmyk.magenta = cloneImageData(channelData.magenta);
        originalChannels.cmyk.yellow = cloneImageData(channelData.yellow);
        originalChannels.cmyk.black = cloneImageData(channelData.black);
    }

    // Séparer 2 Colors
    function separateChannels2Color(img) {
        const grayscale = toGrayscale(originalImageData); // from riso-tools.js
        const split = splitGrayscaleInTwo(grayscale, 128); // from riso-tools.js

        displayChannel('twoDarkCanvas', split.dark, img.width, img.height);
        displayChannel('twoLightCanvas', split.light, img.width, img.height);

        channels.two.dark = split.dark;
        channels.two.light = split.light;

        originalChannels.two.dark = cloneImageData(split.dark);
        originalChannels.two.light = cloneImageData(split.light);
    }

    // Afficher un canal
    function displayChannel(canvasId, imageData, width, height) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;
        const maxWidth = 250;
        const scale = Math.min(1, maxWidth / width);
        canvas.width = width * scale;
        canvas.height = height * scale;

        const tempCanvas = document.createElement('canvas');
        tempCanvas.width = width;
        tempCanvas.height = height;
        const tempCtx = tempCanvas.getContext('2d');
        tempCtx.putImageData(imageData, 0, 0);

        const ctx = canvas.getContext('2d');
        ctx.drawImage(tempCanvas, 0, 0, canvas.width, canvas.height);
    }

    // Changer d'onglet
    function switchTab(mode) {
        currentMode = mode;

        // Si on sort du mode pipette, désactiver la pipette
        if (mode !== 'PIPETTE' && pipetteActive) {
            togglePipette();
        }

        // Gérer visibilité preview principale vs pipette
        if (mode === 'PIPETTE') {
            document.getElementById('mainPreviewPanel').style.display = 'none';
        } else {
            document.getElementById('mainPreviewPanel').style.display = 'block';
            updatePreview(); // Rafraîchir avec les réglages du mode actif
        }
    }

    // Update Preview
    function updatePreview() {
        if (!originalImage || currentMode === 'PIPETTE') return;

        const previewCanvas = document.getElementById('previewCanvas');
        const originalCanvas = document.getElementById('originalCanvas');
        previewCanvas.width = originalCanvas.width;
        previewCanvas.height = originalCanvas.height;

        const ctx = previewCanvas.getContext('2d');
        ctx.fillStyle = 'white';
        ctx.fillRect(0, 0, previewCanvas.width, previewCanvas.height);

        let layersToRender = [];

        if (currentMode === 'RGB') {
            layersToRender = [
                { data: channels.rgb.red, id: 'rgbRed' },
                { data: channels.rgb.green, id: 'rgbGreen' },
                { data: channels.rgb.blue, id: 'rgbBlue' }
            ];
        } else if (currentMode === 'CMYK') {
            layersToRender = [
                { data: channels.cmyk.cyan, id: 'cmykCyan' },
                { data: channels.cmyk.magenta, id: 'cmykMagenta' },
                { data: channels.cmyk.yellow, id: 'cmykYellow' },
                { data: channels.cmyk.black, id: 'cmykBlack' }
            ];
        } else if (currentMode === '2COLOR') {
            layersToRender = [
                { data: channels.two.dark, id: 'twoDark' },
                { data: channels.two.light, id: 'twoLight' }
            ];
        }

        layersToRender.forEach(layer => {
            if (!layer.data) return;

            const tambourSelect = document.getElementById(layer.id + 'Tambour');
            const opacitySlider = document.getElementById(layer.id + 'OpacitySlider');

            const tambour = tambourSelect.value;
            if (tambour === 'none') return;

            const opacity = parseInt(opacitySlider.value) / 100;
            const colorObj = RISO_COLORS[tambour];
            const color = colorObj ? (colorObj.hex || colorObj) : null;

            if (color) {
                applyChannelToPreview(ctx, layer.data, color, opacity, previewCanvas);
            }
        });
    }

    function hexToRgb(hex) {
        const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? {
            r: parseInt(result[1], 16),
            g: parseInt(result[2], 16),
            b: parseInt(result[3], 16)
        } : null;
    }

    function applyChannelToPreview(ctx, channelData, color, opacity, previewCanvas) {
        const tempCanvas = document.createElement('canvas');
        tempCanvas.width = originalImage.width;
        tempCanvas.height = originalImage.height;
        const tempCtx = tempCanvas.getContext('2d');

        tempCtx.putImageData(channelData, 0, 0);

        const coloredData = tempCtx.getImageData(0, 0, tempCanvas.width, tempCanvas.height);
        const rgb = hexToRgb(color);

        for (let i = 0; i < coloredData.data.length; i += 4) {
            // Calculer l'intensité du pixel original (0 = noir/encre max, 1 = blanc/pas d'encre)
            // On inverse car dans le channelData, 0=noir et 255=blanc
            // Donc (255 - valeur) / 255 donne : 0 pour blanc, 1 pour noir
            const grayVal = coloredData.data[i]; // R, G et B sont identiques en gris
            const inkDensity = (255 - grayVal) / 255;

            // Couleur du pixel = Couleur du tambour
            coloredData.data[i] = rgb.r;
            coloredData.data[i + 1] = rgb.g;
            coloredData.data[i + 2] = rgb.b;

            // Alpha = Densité de l'encre * Opacité globale du calque
            // Si le pixel est blanc (inkDensity 0), alpha sera 0 (transparent)
            // Si le pixel est noir (inkDensity 1), alpha sera max (selon l'opacité du slider)
            coloredData.data[i + 3] = 255 * inkDensity * opacity;
        }

        tempCtx.putImageData(coloredData, 0, 0);

        // Mode 'multiply' pour simuler la superposition d'encres
        ctx.globalCompositeOperation = 'multiply';
        ctx.globalAlpha = 1;
        ctx.drawImage(tempCanvas, 0, 0, previewCanvas.width, previewCanvas.height);

        // Remettre en mode normal pour les prochains dessins hors superposition
        ctx.globalCompositeOperation = 'source-over';
    }

    // Export simple
    function exportChannel(id) {
        // id est ex: 'rgbRed'
        // Trouver le channelData correspondant
        let channelData = null;

        if (id.startsWith('rgb')) channelData = channels.rgb[id.replace('rgb', '').toLowerCase()];
        else if (id.startsWith('cmyk')) channelData = channels.cmyk[id.replace('cmyk', '').toLowerCase()];
        else if (id.startsWith('two')) channelData = channels.two[id.replace('two', '').toLowerCase()];

        if (!channelData || !originalImage) return;

        const tambour = document.getElementById(id + 'Tambour').value;
        if (tambour === 'none') {
            showAppModal({ message: 'Veuillez sélectionner un tambour.', type: 'warning' });
            return;
        }

        const canvas = document.createElement('canvas');
        canvas.width = originalImage.width;
        canvas.height = originalImage.height;
        const ctx = canvas.getContext('2d');
        ctx.putImageData(channelData, 0, 0);

        canvas.toBlob(function (blob) {
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `riso_${id}_${tambour}.png`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        });
    }

    // Export ZIP
    async function exportAll() {
        if (typeof JSZip === 'undefined') {
            showAppModal({ message: 'JSZip non chargé.', type: 'danger' });
            return;
        }

        const zip = new JSZip();
        let exportCount = 0;
        let layersToExport = [];

        if (currentMode === 'RGB') {
            layersToExport = [
                { data: channels.rgb.red, id: 'rgbRed' },
                { data: channels.rgb.green, id: 'rgbGreen' },
                { data: channels.rgb.blue, id: 'rgbBlue' }
            ];
        } else if (currentMode === 'CMYK') {
            layersToExport = [
                { data: channels.cmyk.cyan, id: 'cmykCyan' },
                { data: channels.cmyk.magenta, id: 'cmykMagenta' },
                { data: channels.cmyk.yellow, id: 'cmykYellow' },
                { data: channels.cmyk.black, id: 'cmykBlack' }
            ];
        } else if (currentMode === '2COLOR') {
            layersToExport = [
                { data: channels.two.dark, id: 'twoDark' },
                { data: channels.two.light, id: 'twoLight' }
            ];
        }

        for (const layer of layersToExport) {
            if (!layer.data) continue;
            const tambour = document.getElementById(layer.id + 'Tambour').value;
            if (tambour === 'none') continue;

            const canvas = document.createElement('canvas');
            canvas.width = originalImage.width;
            canvas.height = originalImage.height;
            const ctx = canvas.getContext('2d');
            ctx.putImageData(layer.data, 0, 0);

            const blob = await new Promise(resolve => canvas.toBlob(resolve));
            zip.file(`riso_${layer.id}_${tambour}.png`, blob);
            exportCount++;
        }

        if (exportCount === 0) {
            showAppModal({ message: 'Aucune couche à exporter.', type: 'warning' });
            return;
        }

        const content = await zip.generateAsync({ type: 'blob' });
        const url = URL.createObjectURL(content);
        const a = document.createElement('a');
        a.href = url;
        a.download = originalFileName + '_riso.zip';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    // --- PIPETTE LOGIC ---
    function togglePipette() {
        pipetteActive = !pipetteActive;
        const btn = document.getElementById('pipetteBtn');
        const info = document.getElementById('pipetteInfo');

        if (pipetteActive) {
            btn.className = 'btn btn-success btn-block btn-lg';
            btn.innerHTML = '<i class="fa fa-eyedropper"></i> Pipette ACTIVE - Cliquez sur l\'image';
            info.style.display = 'block';
            document.getElementById('originalCanvas').addEventListener('click', handlePipetteClick);
        } else {
            btn.className = 'btn btn-primary btn-block btn-lg';
            btn.innerHTML = '<i class="fa fa-eyedropper"></i> Activer la pipette';
            info.style.display = 'none';
            document.getElementById('originalCanvas').removeEventListener('click', handlePipetteClick);
        }
    }

    function handlePipetteClick(e) {
        if (!pipetteActive) return;
        const canvas = e.target;
        const rect = canvas.getBoundingClientRect();
        const scaleX = originalImage.width / rect.width;
        const scaleY = originalImage.height / rect.height;
        const x = Math.floor((e.clientX - rect.left) * scaleX);
        const y = Math.floor((e.clientY - rect.top) * scaleY);

        const ctx = canvas.getContext('2d');
        const imageData = ctx.getImageData(x / (originalImage.width / canvas.width), y / (originalImage.height / canvas.height), 1, 1);
        pickedColorRGB = {
            r: imageData.data[0], g: imageData.data[1], b: imageData.data[2]
        };

        document.getElementById('pickedColor').style.background = `rgb(${pickedColorRGB.r}, ${pickedColorRGB.g}, ${pickedColorRGB.b})`;
        updatePipettePreview();
    }

    function updatePipettePreview() {
        if (!pickedColorRGB || !currentWorkingImageData) return;

        const tolerance = parseInt(document.getElementById('toleranceSlider').value);
        const isolatedWith = isolateColor(currentWorkingImageData, pickedColorRGB.r, pickedColorRGB.g, pickedColorRGB.b, tolerance);

        const previewCanvas = document.getElementById('isolatedPreviewCanvas');
        const originalCanvas = document.getElementById('originalCanvas');
        previewCanvas.width = originalCanvas.width;
        previewCanvas.height = originalCanvas.height;

        const ctx = previewCanvas.getContext('2d');
        ctx.fillStyle = 'white';
        ctx.fillRect(0, 0, previewCanvas.width, previewCanvas.height);

        const tempCanvas = document.createElement('canvas');
        tempCanvas.width = originalImage.width;
        tempCanvas.height = originalImage.height;
        const tempCtx = tempCanvas.getContext('2d');
        tempCtx.putImageData(isolatedWith, 0, 0);

        ctx.drawImage(tempCanvas, 0, 0, previewCanvas.width, previewCanvas.height);
    }

    function applyPipette() {
        if (!pickedColorRGB || !currentWorkingImageData) return;
        const tolerance = parseInt(document.getElementById('toleranceSlider').value);

        let isolatedWith = isolateColor(currentWorkingImageData, pickedColorRGB.r, pickedColorRGB.g, pickedColorRGB.b, tolerance);
        let isolatedWithout = createWithoutColorLayer(currentWorkingImageData, pickedColorRGB.r, pickedColorRGB.g, pickedColorRGB.b, tolerance);

        const layerId = Date.now();
        isolatedLayers.push({
            id: layerId,
            color: pickedColorRGB,
            tolerance: tolerance,
            withColor: isolatedWith,
            withoutColor: isolatedWithout
        });

        displayIsolatedLayers();
        updateWorkingImage(isolatedWith);

        // Reset pipette
        togglePipette();
        showAppModal({ message: '<?php echo __('riso_separator.layer_created'); ?>', type: 'success' });
    }

    function createWithoutColorLayer(imageData, targetR, targetG, targetB, tolerance) {
        const width = imageData.width;
        const height = imageData.height;
        const result = new ImageData(width, height);

        for (let i = 0; i < imageData.data.length; i += 4) {
            const r = imageData.data[i];
            const g = imageData.data[i + 1];
            const b = imageData.data[i + 2];

            const distance = Math.sqrt(Math.pow(r - targetR, 2) + Math.pow(g - targetG, 2) + Math.pow(b - targetB, 2));

            if (distance <= tolerance) {
                result.data[i + 3] = 0; // Transparent
            } else {
                result.data[i] = r; result.data[i + 1] = g; result.data[i + 2] = b; result.data[i + 3] = 255;
            }
        }
        return result;
    }

    function updateWorkingImage(isolatedWithData) {
        for (let i = 0; i < currentWorkingImageData.data.length; i += 4) {
            if (isolatedWithData.data[i + 3] !== 0) {
                // Si pixel present dans la couche isolée, le retirer de l'image de travail
                currentWorkingImageData.data[i + 3] = 0;
            }
        }
    }

    function displayIsolatedLayers() {
        const container = document.getElementById('isolatedLayersContainer');
        container.innerHTML = '';

        isolatedLayers.forEach((layer, index) => {
            const layerDiv = document.createElement('div');
            layerDiv.className = 'panel panel-default';
            layerDiv.style.marginBottom = '10px';

            const colorHex = `rgb(${layer.color.r},${layer.color.g},${layer.color.b})`;

            layerDiv.innerHTML = `
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-1" style="background: ${colorHex}; height: 50px;"></div>
                    <div class="col-md-7">
                        <strong><?php _e('riso_separator.selection'); ?> ${index + 1}</strong><br>
                        <?php _e('riso_separator.tolerance'); ?>: ${layer.tolerance}
                    </div>
                    <div class="col-md-4 text-right">
                         <button class="btn btn-sm btn-success" onclick="downloadIsolatedLayer(${layer.id}, 'with')"><i class="fa fa-download"></i> <?php _e('riso_separator.with'); ?></button>
                         <button class="btn btn-sm btn-default" onclick="downloadIsolatedLayer(${layer.id}, 'without')"><i class="fa fa-download"></i> <?php _e('riso_separator.without'); ?></button>
                    </div>
                </div>
            </div>
        `;
            container.appendChild(layerDiv);
        });
    }

    function downloadIsolatedLayer(layerId, type) {
        const layer = isolatedLayers.find(l => l.id === layerId);
        if (!layer) return;

        const imageData = type === 'with' ? layer.withColor : layer.withoutColor;
        const canvas = document.createElement('canvas');
        canvas.width = imageData.width;
        canvas.height = imageData.height;
        const ctx = canvas.getContext('2d');
        ctx.putImageData(imageData, 0, 0);

        canvas.toBlob(function (blob) {
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `riso_layer_${layerId}_${type}.png`;
            a.click();
        });
    }

    function exportAllIsolated() {
        if (typeof JSZip === 'undefined') return;
        const zip = new JSZip();
        isolatedLayers.forEach((layer, i) => {
            const c1 = document.createElement('canvas'); c1.width = layer.withColor.width; c1.height = layer.withColor.height;
            c1.getContext('2d').putImageData(layer.withColor, 0, 0);
            zip.file(`layer_${i + 1}_with.png`, c1.toDataURL().split(',')[1], { base64: true });

            const c2 = document.createElement('canvas'); c2.width = layer.withoutColor.width; c2.height = layer.withoutColor.height;
            c2.getContext('2d').putImageData(layer.withoutColor, 0, 0);
            zip.file(`layer_${i + 1}_without.png`, c2.toDataURL().split(',')[1], { base64: true });
        });

        zip.generateAsync({ type: "blob" }).then(content => {
            const a = document.createElement('a');
            a.href = URL.createObjectURL(content);
            a.download = originalFileName + '_isolated.zip';
            a.click();
        });
    }

    // --- EFFECTS ---
    function resetChannels() {
        if (!originalImage) return;

        if (originalChannels.rgb.red) {
            channels.rgb.red = cloneImageData(originalChannels.rgb.red);
            channels.rgb.green = cloneImageData(originalChannels.rgb.green);
            channels.rgb.blue = cloneImageData(originalChannels.rgb.blue);
            displayChannel('rgbRedCanvas', channels.rgb.red, originalImage.width, originalImage.height);
            displayChannel('rgbGreenCanvas', channels.rgb.green, originalImage.width, originalImage.height);
            displayChannel('rgbBlueCanvas', channels.rgb.blue, originalImage.width, originalImage.height);
        }

        if (originalChannels.cmyk.cyan) {
            channels.cmyk.cyan = cloneImageData(originalChannels.cmyk.cyan);
            channels.cmyk.magenta = cloneImageData(originalChannels.cmyk.magenta);
            channels.cmyk.yellow = cloneImageData(originalChannels.cmyk.yellow);
            channels.cmyk.black = cloneImageData(originalChannels.cmyk.black);
            displayChannel('cmykCyanCanvas', channels.cmyk.cyan, originalImage.width, originalImage.height);
            displayChannel('cmykMagentaCanvas', channels.cmyk.magenta, originalImage.width, originalImage.height);
            displayChannel('cmykYellowCanvas', channels.cmyk.yellow, originalImage.width, originalImage.height);
            displayChannel('cmykBlackCanvas', channels.cmyk.black, originalImage.width, originalImage.height);
        }

        if (originalChannels.two.dark) {
            channels.two.dark = cloneImageData(originalChannels.two.dark);
            channels.two.light = cloneImageData(originalChannels.two.light);
            displayChannel('twoDarkCanvas', channels.two.dark, originalImage.width, originalImage.height);
            displayChannel('twoLightCanvas', channels.two.light, originalImage.width, originalImage.height);
        }

        updatePreview();
    }

    function cloneImageData(imageData) {
        if (!imageData) return null;
        const c = document.createElement('canvas');
        c.width = imageData.width;
        c.height = imageData.height;
        const ctx = c.getContext('2d');
        const d = ctx.createImageData(imageData.width, imageData.height);
        d.data.set(imageData.data);
        return d;
    }

    function applyPosterization() {
        const levels = parseInt(document.getElementById('posterLevels').value);
        // Appliquer sur tous les canaux existants

        const applyToSet = (set, displayPrefix) => {
            for (let key in set) {
                if (set[key]) {
                    set[key] = posterizeImage(set[key], levels);
                    displayChannel(displayPrefix + key.charAt(0).toUpperCase() + key.slice(1) + 'Canvas', set[key], originalImage.width, originalImage.height);
                }
            }
        };

        applyToSet(channels.rgb, 'rgb');
        applyToSet(channels.cmyk, 'cmyk');
        applyToSet(channels.two, 'two'); // Note: key 'dark' -> twoDarkCanvas matches ID

        // Pour twoDarkCanvas le prefix 'two' + 'Dark' = 'twoDark' OK.

        updatePreview();
        showAppModal({ message: '<?php echo __('riso_separator.posterize_applied'); ?>', type: 'success' });
    }

    function applyHalftoneEffect() {
        const size = parseInt(document.getElementById('halftoneSize').value);

        // RGB
        channels.rgb.red = applyHalftone(channels.rgb.red, size, 15);
        channels.rgb.green = applyHalftone(channels.rgb.green, size, 45);
        channels.rgb.blue = applyHalftone(channels.rgb.blue, size, 75);
        displayChannel('rgbRedCanvas', channels.rgb.red, originalImage.width, originalImage.height);
        displayChannel('rgbGreenCanvas', channels.rgb.green, originalImage.width, originalImage.height);
        displayChannel('rgbBlueCanvas', channels.rgb.blue, originalImage.width, originalImage.height);

        // CMYK
        channels.cmyk.cyan = applyHalftone(channels.cmyk.cyan, size, 15);
        channels.cmyk.magenta = applyHalftone(channels.cmyk.magenta, size, 75);
        channels.cmyk.yellow = applyHalftone(channels.cmyk.yellow, size, 0);
        channels.cmyk.black = applyHalftone(channels.cmyk.black, size, 45);
        displayChannel('cmykCyanCanvas', channels.cmyk.cyan, originalImage.width, originalImage.height);
        displayChannel('cmykMagentaCanvas', channels.cmyk.magenta, originalImage.width, originalImage.height);
        displayChannel('cmykYellowCanvas', channels.cmyk.yellow, originalImage.width, originalImage.height);
        displayChannel('cmykBlackCanvas', channels.cmyk.black, originalImage.width, originalImage.height);

        // 2COLOR
        channels.two.light = applyHalftone(channels.two.light, size, 15);
        channels.two.dark = applyHalftone(channels.two.dark, size, 45);
        displayChannel('twoLightCanvas', channels.two.light, originalImage.width, originalImage.height);
        displayChannel('twoDarkCanvas', channels.two.dark, originalImage.width, originalImage.height);

        updatePreview();
        showAppModal({ message: '<?php echo __('riso_separator.halftone_applied'); ?>', type: 'success' });
    }

    function resetSeparator() {
        document.getElementById('uploadSection').style.display = 'block';
        document.getElementById('separatorSection').style.display = 'none';
        document.getElementById('imageInput').value = '';
        originalImage = null;
        originalImageData = null;
    }
</script>