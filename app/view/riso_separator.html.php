<!-- JSZip pour export multiple (local) -->
<script src="js/jszip.min.js"></script>
<!-- Riso Tools - Fonctions avancées -->
<script src="js/riso-tools.js"></script>

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
    box-shadow: 0 4px 10px rgba(0,0,0,0.2);
}
</style>

<div class="riso-container">
    <!-- En-tête -->
    <div class="riso-header">
        <h1><i class="fa fa-palette"></i> Séparateur de Couleur Riso</h1>
        <p>Séparez vos images couleur en couches pour impression multi-tambours</p>
    </div>

    <!-- Zone d'upload -->
    <div id="uploadSection">
        <div class="upload-zone" id="uploadZone">
            <div style="font-size: 64px; color: #ff6b9d; margin-bottom: 20px;">
                <i class="fa fa-cloud-upload"></i>
            </div>
            <h3>Glissez votre image ici</h3>
            <p class="text-muted">ou cliquez pour sélectionner</p>
            <input type="file" id="imageInput" accept="image/png,image/jpeg,image/jpg" style="display: none;">
            <button type="button" class="btn btn-lg" style="background: #ff6b9d; color: white; border: none; padding: 12px 30px; border-radius: 25px; margin-top: 15px;">
                <i class="fa fa-upload"></i> Sélectionner une image
            </button>
        </div>
    </div>

    <!-- Contrôles et Prévisualisation -->
    <div id="separatorSection" style="display: none;">
        <!-- Image originale -->
        <div class="panel panel-default">
            <div class="panel-heading">
                <h4><i class="fa fa-image"></i> Image originale</h4>
            </div>
            <div class="panel-body text-center">
                <canvas id="originalCanvas" style="cursor: crosshair;"></canvas>
                <p class="text-muted" style="margin-top: 10px;">
                    <i class="fa fa-info-circle"></i> Cliquez sur l'image pour isoler une couleur avec la pipette
                </p>
            </div>
        </div>

        <!-- Modes et Outils avancés -->
        <div class="panel panel-warning">
            <div class="panel-heading">
                <h4><i class="fa fa-magic"></i> Modes et Outils</h4>
            </div>
            <div class="panel-body">
                <div class="row">
                    <!-- Mode de séparation -->
                    <div class="col-md-4">
                        <h5><i class="fa fa-cogs"></i> Mode de séparation</h5>
                        <div class="btn-group-vertical btn-block">
                            <button class="btn btn-default" id="modeRGB" onclick="switchMode('RGB')">
                                <i class="fa fa-circle-o"></i> RGB (3 canaux)
                            </button>
                            <button class="btn btn-default" id="modeCMYK" onclick="switchMode('CMYK')">
                                <i class="fa fa-circle-o"></i> CMYK (4 canaux)
                            </button>
                            <button class="btn btn-default" id="mode2Color" onclick="switchMode('2COLOR')">
                                <i class="fa fa-circle-o"></i> 2 Tambours (N&B)
                            </button>
                        </div>
                    </div>

                    <!-- Outils -->
                    <div class="col-md-4">
                        <h5><i class="fa fa-wrench"></i> Outils</h5>
                        <button class="btn btn-primary btn-block" id="pipetteBtn" onclick="togglePipette()">
                            <i class="fa fa-eyedropper"></i> Pipette (Isoler couleur)
                        </button>
                        <div id="pipetteInfo" style="display: none; margin-top: 10px;">
                            <div class="alert alert-info">
                                <small>Couleur sélectionnée: <span id="pickedColor" style="display: inline-block; width: 30px; height: 30px; border: 1px solid #000; vertical-align: middle;"></span></small>
                                <br>
                                <label>Tolérance: <span id="toleranceValue">30</span></label>
                                <input type="range" class="form-control" id="toleranceSlider" min="0" max="100" value="30">
                                <button class="btn btn-sm btn-success btn-block" style="margin-top: 10px;" onclick="applyPipette()">
                                    <i class="fa fa-check"></i> Valider et créer la couche
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Effets -->
                    <div class="col-md-4">
                        <h5><i class="fa fa-adjust"></i> Effets</h5>
                        <button class="btn btn-warning btn-block" onclick="resetChannels()">
                            <i class="fa fa-undo"></i> Réinitialiser les canaux
                        </button>
                        <hr>
                        <button class="btn btn-info btn-block" onclick="applyPosterization()">
                            <i class="fa fa-th"></i> Postériser
                        </button>
                        <div style="margin-top: 10px;">
                            <label>Niveaux: <span id="posterLevels">4</span></label>
                            <input type="range" class="form-control" id="posterSlider" min="2" max="10" value="4">
                        </div>
                        <div style="margin-top: 10px;">
                            <label>Taille des points de trame: <span id="halftoneSize">3</span> px</label>
                            <input type="range" class="form-control" id="halftoneSlider" min="1" max="10" value="3">
                            <small class="text-muted">1=trame fine (400 DPI), 10=trame grossière</small>
                        </div>
                        <button class="btn btn-info btn-block" style="margin-top: 10px;" onclick="applyHalftoneEffect()">
                            <i class="fa fa-th-large"></i> Halftone (trames)
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contrôles de séparation (affiché seulement si pas de couches isolées) -->
        <div id="normalChannelsSection">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h4><i class="fa fa-sliders"></i> Configuration des couches</h4>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <!-- Canal Rouge -->
                        <div class="col-md-4">
                            <div class="channel-panel">
                                <h5><i class="fa fa-circle" style="color: #ff0000;"></i> Canal Rouge</h5>
                                <canvas id="redCanvas" class="img-thumbnail"></canvas>
                                <div class="layer-controls">
                                    <label>Tambour:</label>
                                    <select class="form-control tambour-select" data-channel="red">
                                        <option value="red">Rouge</option>
                                        <option value="black">Noir</option>
                                        <option value="blue">Bleu</option>
                                        <option value="yellow">Jaune</option>
                                        <option value="green">Vert</option>
                                        <option value="violet">Violet</option>
                                        <option value="none">Aucun</option>
                                    </select>
                                    <label style="margin-top: 10px;">Opacité: <span id="redOpacity">100</span>%</label>
                                    <input type="range" class="form-control" id="redOpacitySlider" min="0" max="100" value="100">
                                    <button class="btn btn-sm btn-success btn-block" style="margin-top: 10px;" onclick="exportChannel('red')">
                                        <i class="fa fa-download"></i> Export PNG
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Canal Vert -->
                        <div class="col-md-4">
                            <div class="channel-panel">
                                <h5><i class="fa fa-circle" style="color: #00ff00;"></i> Canal Vert</h5>
                                <canvas id="greenCanvas" class="img-thumbnail"></canvas>
                                <div class="layer-controls">
                                    <label>Tambour:</label>
                                    <select class="form-control tambour-select" data-channel="green">
                                        <option value="green">Vert</option>
                                        <option value="black">Noir</option>
                                        <option value="blue">Bleu</option>
                                        <option value="yellow">Jaune</option>
                                        <option value="red">Rouge</option>
                                        <option value="violet">Violet</option>
                                        <option value="none">Aucun</option>
                                    </select>
                                    <label style="margin-top: 10px;">Opacité: <span id="greenOpacity">100</span>%</label>
                                    <input type="range" class="form-control" id="greenOpacitySlider" min="0" max="100" value="100">
                                    <button class="btn btn-sm btn-success btn-block" style="margin-top: 10px;" onclick="exportChannel('green')">
                                        <i class="fa fa-download"></i> Export PNG
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Canal Bleu -->
                        <div class="col-md-4">
                            <div class="channel-panel">
                                <h5><i class="fa fa-circle" style="color: #0000ff;"></i> Canal Bleu</h5>
                                <canvas id="blueCanvas" class="img-thumbnail"></canvas>
                                <div class="layer-controls">
                                    <label>Tambour:</label>
                                    <select class="form-control tambour-select" data-channel="blue">
                                        <option value="blue">Bleu</option>
                                        <option value="black">Noir</option>
                                        <option value="red">Rouge</option>
                                        <option value="yellow">Jaune</option>
                                        <option value="green">Vert</option>
                                        <option value="violet">Violet</option>
                                        <option value="none">Aucun</option>
                                    </select>
                                    <label style="margin-top: 10px;">Opacité: <span id="blueOpacity">100</span>%</label>
                                    <input type="range" class="form-control" id="blueOpacitySlider" min="0" max="100" value="100">
                                    <button class="btn btn-sm btn-success btn-block" style="margin-top: 10px;" onclick="exportChannel('blue')">
                                        <i class="fa fa-download"></i> Export PNG
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Canal Black (4ème canal pour CMYK) - Caché par défaut -->
                        <div class="col-md-4" id="blackChannelPanel" style="display: none;">
                            <div class="channel-panel">
                                <h5><i class="fa fa-circle" style="color: #000000;"></i> Canal Black</h5>
                                <canvas id="blackCanvas" class="img-thumbnail"></canvas>
                                <div class="layer-controls">
                                    <label>Tambour:</label>
                                    <select class="form-control tambour-select" data-channel="black">
                                        <option value="black">Noir</option>
                                        <option value="red">Rouge</option>
                                        <option value="blue">Bleu</option>
                                        <option value="yellow">Jaune</option>
                                        <option value="green">Vert</option>
                                        <option value="violet">Violet</option>
                                        <option value="none">Aucun</option>
                                    </select>
                                    <label style="margin-top: 10px;">Opacité: <span id="blackOpacity">100</span>%</label>
                                    <input type="range" class="form-control" id="blackOpacitySlider" min="0" max="100" value="100">
                                    <button class="btn btn-sm btn-success btn-block" style="margin-top: 10px;" onclick="exportChannel('black')">
                                        <i class="fa fa-download"></i> Export PNG
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Prévisualisation superposition -->
            <div class="panel panel-success">
                <div class="panel-heading">
                    <h4><i class="fa fa-eye"></i> Prévisualisation - Superposition des couches</h4>
                </div>
                <div class="panel-body text-center">
                    <canvas id="previewCanvas" class="preview-canvas"></canvas>
                    <div style="margin-top: 20px;">
                        <button class="btn btn-primary btn-lg" onclick="updatePreview()">
                            <i class="fa fa-refresh"></i> Actualiser la prévisualisation
                        </button>
                        <button class="btn btn-success btn-lg" onclick="exportAll()">
                            <i class="fa fa-download"></i> Exporter toutes les couches (ZIP)
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Couches isolées par pipette -->
        <div id="isolatedLayersSection" style="display: none;">
            <!-- Prévisualisation des couches isolées -->
            <div class="panel panel-success">
                <div class="panel-heading">
                    <h4><i class="fa fa-eye"></i> Prévisualisation - Couches isolées</h4>
                </div>
                <div class="panel-body text-center">
                    <canvas id="isolatedPreviewCanvas" class="preview-canvas"></canvas>
                    <div style="margin-top: 20px;">
                        <button class="btn btn-success btn-lg" onclick="exportAllIsolated()">
                            <i class="fa fa-download"></i> Exporter toutes les couches (ZIP)
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Liste des couches isolées -->
            <div class="panel panel-warning">
                <div class="panel-heading">
                    <h4><i class="fa fa-eyedropper"></i> Couches isolées par pipette</h4>
                </div>
                <div class="panel-body">
                    <div id="isolatedLayersContainer">
                        <!-- Les couches isolées seront ajoutées ici dynamiquement -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Boutons d'action -->
        <div class="text-center">
            <button class="btn btn-default btn-lg" onclick="resetSeparator()">
                <i class="fa fa-refresh"></i> Nouvelle image
            </button>
            <a href="?accueil" class="btn btn-default btn-lg">
                <i class="fa fa-home"></i> Retour à l'accueil
            </a>
        </div>
    </div>

    <!-- Panneau d'information -->
    <div class="panel panel-info">
        <div class="panel-heading">
            <h4><i class="fa fa-info-circle"></i> Guide d'utilisation</h4>
        </div>
        <div class="panel-body">
            <h5><i class="fa fa-book"></i> Workflow de base</h5>
            <ol>
                <li><strong>Uploadez</strong> une image couleur (PNG ou JPG)</li>
                <li><strong>Choisissez un mode</strong> : RGB (3 canaux), CMYK (4 canaux), ou 2 Tambours (N&B)</li>
                <li><strong>Assignez un tambour</strong> à chaque canal</li>
                <li><strong>Ajustez l'opacité</strong> de chaque couche</li>
                <li><strong>Prévisualisez</strong> le résultat final</li>
                <li><strong>Exportez</strong> les couches pour impression</li>
            </ol>
            
            <h5><i class="fa fa-magic"></i> Fonctionnalités avancées</h5>
            <ul>
                <li><strong>Mode RGB</strong> : Sépare en Rouge, Vert, Bleu (standard)</li>
                <li><strong>Mode CMYK</strong> : Sépare en Cyan, Magenta, Jaune, Noir (imprimerie)</li>
                <li><strong>Mode 2 Tambours</strong> : Pour images N&B, sépare tons clairs/foncés</li>
                <li><strong>Pipette</strong> : Cliquez sur une couleur pour l'isoler avec tolérance réglable</li>
                <li><strong>Postériser</strong> : Réduit les niveaux de gris (effet sérigraphie)</li>
                <li><strong>Halftone</strong> : Applique des trames de points (effet Riso authentique)</li>
            </ul>
            
            <div class="alert alert-success">
                <i class="fa fa-lightbulb-o"></i> <strong>Astuce:</strong> 
                Les couches sont exportées en niveaux de gris. Sur la Riso, imprimez chaque couche 
                avec le tambour correspondant. La superposition créera l'image couleur finale !
            </div>
            
            <div class="alert alert-warning">
                <i class="fa fa-flask"></i> <strong>Expérimentez !</strong> 
                Essayez différentes combinaisons de tambours, postérisation, et halftone pour créer 
                des effets uniques. Le mode 2 tambours est parfait pour affiches bicolores impactantes.
            </div>
        </div>
    </div>
</div>

<script>
// Les couleurs Riso sont définies dans riso-tools.js
// Ajouter 'none' pour l'option "Aucun tambour"
if (typeof RISO_COLORS !== 'undefined') {
    RISO_COLORS['none'] = null;
}

// Variables globales
let originalImage = null;
let originalImageData = null;
let currentMode = 'RGB'; // RGB, CMYK, 2COLOR
let pipetteActive = false;
let pickedColorRGB = null;
let channels = {
    red: null,
    green: null,
    blue: null,
    cyan: null,
    magenta: null,
    yellow: null,
    black: null,
    light: null,
    dark: null,
    isolated: null
};

// Sauvegarde des canaux originaux (avant effets)
let originalChannels = {
    red: null,
    green: null,
    blue: null,
    cyan: null,
    magenta: null,
    yellow: null,
    black: null,
    light: null,
    dark: null
};

// Variables pour les couches isolées par pipette
let isolatedLayers = [];
let currentWorkingImageData = null; // Image de travail (diminuée à chaque sélection)
let posterizedSelection = null; // Stocke la sélection postérisée
let halftonedSelection = null; // Stocke la sélection tramée

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    const uploadZone = document.getElementById('uploadZone');
    const imageInput = document.getElementById('imageInput');
    const uploadSection = document.getElementById('uploadSection');
    const separatorSection = document.getElementById('separatorSection');

    // Click sur zone d'upload
    uploadZone.addEventListener('click', () => imageInput.click());
    document.querySelector('.upload-zone button').addEventListener('click', (e) => {
        e.stopPropagation();
        imageInput.click();
    });

    // Sélection fichier
    imageInput.addEventListener('change', function(e) {
        if (this.files && this.files[0]) {
            loadImage(this.files[0]);
        }
    });

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
                alert('Veuillez sélectionner une image valide (PNG ou JPG).');
            }
        }
    });

    // Debounce pour optimiser les performances
    let previewTimeout = null;
    function debouncedPreview() {
        if (previewTimeout) clearTimeout(previewTimeout);
        previewTimeout = setTimeout(updatePreview, 150);
    }
    
    // Event listeners pour les sliders d'opacité
    document.getElementById('redOpacitySlider').addEventListener('input', function() {
        document.getElementById('redOpacity').textContent = this.value;
        debouncedPreview();
    });
    document.getElementById('greenOpacitySlider').addEventListener('input', function() {
        document.getElementById('greenOpacity').textContent = this.value;
        debouncedPreview();
    });
    document.getElementById('blueOpacitySlider').addEventListener('input', function() {
        document.getElementById('blueOpacity').textContent = this.value;
        debouncedPreview();
    });
    document.getElementById('blackOpacitySlider').addEventListener('input', function() {
        document.getElementById('blackOpacity').textContent = this.value;
        debouncedPreview();
    });
    
    // Event listener pour le slider de tolérance de la pipette
    document.getElementById('toleranceSlider').addEventListener('input', function() {
        document.getElementById('toleranceValue').textContent = this.value;
        if (pipetteActive && pickedColorRGB) {
            updatePipettePreview();
        }
    });
    

    // Event listeners pour les sélecteurs de tambours
    document.querySelectorAll('.tambour-select').forEach(select => {
        select.addEventListener('change', updatePreview);
    });
});

// Charger et afficher l'image
function loadImage(file) {
    const reader = new FileReader();
    reader.onload = function(e) {
        const img = new Image();
        img.onload = function() {
            originalImage = img;
            processImage(img);
            document.getElementById('uploadSection').style.display = 'none';
            document.getElementById('separatorSection').style.display = 'block';
        };
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);
}

// Traiter l'image et séparer les canaux
function processImage(img) {
    // Afficher l'image originale
    const originalCanvas = document.getElementById('originalCanvas');
    const maxWidth = 600;
    const scale = Math.min(1, maxWidth / img.width);
    originalCanvas.width = img.width * scale;
    originalCanvas.height = img.height * scale;
    const ctx = originalCanvas.getContext('2d', { willReadFrequently: true });
    ctx.drawImage(img, 0, 0, originalCanvas.width, originalCanvas.height);
    
    // Sauvegarder l'ImageData original (pleine taille pour traitement)
    const fullCanvas = document.createElement('canvas');
    fullCanvas.width = img.width;
    fullCanvas.height = img.height;
    const fullCtx = fullCanvas.getContext('2d');
    fullCtx.drawImage(img, 0, 0);
    originalImageData = fullCtx.getImageData(0, 0, img.width, img.height);
    
    // Initialiser l'image de travail avec l'image originale
    currentWorkingImageData = new ImageData(
        new Uint8ClampedArray(originalImageData.data),
        originalImageData.width,
        originalImageData.height
    );
    
    // Réinitialiser les couches isolées
    isolatedLayers = [];
    document.getElementById('isolatedLayersSection').style.display = 'none';
    document.getElementById('isolatedLayersContainer').innerHTML = '';

    // Séparer les canaux RGB par défaut
    separateChannels(img);
    
    // Mettre à jour la prévisualisation
    updatePreview();
}

// Séparer l'image en canaux RGB (niveaux de gris)
function separateChannels(img) {
    const canvas = document.createElement('canvas');
    canvas.width = img.width;
    canvas.height = img.height;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(img, 0, 0);
    
    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const data = imageData.data;

    // Créer 3 canvas pour les canaux
    const redData = ctx.createImageData(canvas.width, canvas.height);
    const greenData = ctx.createImageData(canvas.width, canvas.height);
    const blueData = ctx.createImageData(canvas.width, canvas.height);

    // Séparer les canaux
    for (let i = 0; i < data.length; i += 4) {
        // Canal rouge -> niveaux de gris basés sur valeur rouge
        redData.data[i] = data[i];     // R
        redData.data[i+1] = data[i];   // G
        redData.data[i+2] = data[i];   // B
        redData.data[i+3] = 255;       // A

        // Canal vert -> niveaux de gris basés sur valeur verte
        greenData.data[i] = data[i+1];
        greenData.data[i+1] = data[i+1];
        greenData.data[i+2] = data[i+1];
        greenData.data[i+3] = 255;

        // Canal bleu -> niveaux de gris basés sur valeur bleue
        blueData.data[i] = data[i+2];
        blueData.data[i+1] = data[i+2];
        blueData.data[i+2] = data[i+2];
        blueData.data[i+3] = 255;
    }

    // Afficher les canaux séparés
    displayChannel('redCanvas', redData, canvas.width, canvas.height);
    displayChannel('greenCanvas', greenData, canvas.width, canvas.height);
    displayChannel('blueCanvas', blueData, canvas.width, canvas.height);

    // Stocker les données des canaux
    channels.red = redData;
    channels.green = greenData;
    channels.blue = blueData;
    
    // Sauvegarder les canaux originaux (copie profonde)
    originalChannels.red = cloneImageData(redData);
    originalChannels.green = cloneImageData(greenData);
    originalChannels.blue = cloneImageData(blueData);
}

// Afficher un canal sur un canvas
function displayChannel(canvasId, imageData, width, height) {
    const canvas = document.getElementById(canvasId);
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

// Mettre à jour la prévisualisation avec superposition
function updatePreview() {
    if (!originalImage) return;
    
    // Ne pas mettre à jour si la pipette est active (pour ne pas écraser la prévisualisation de sélection)
    if (pipetteActive && pickedColorRGB) return;
    
    // Ne pas mettre à jour si on est en train de prévisualiser une sélection de pipette
    if (document.getElementById('pipetteInfo').style.display !== 'none' && pickedColorRGB) return;
    
    // Ne pas mettre à jour si on vient de faire une prévisualisation pipette
    if (window.justDidPipettePreview) return;

    const previewCanvas = document.getElementById('previewCanvas');
    const originalCanvas = document.getElementById('originalCanvas');
    previewCanvas.width = originalCanvas.width;
    previewCanvas.height = originalCanvas.height;
    
    const ctx = previewCanvas.getContext('2d');
    ctx.fillStyle = 'white';
    ctx.fillRect(0, 0, previewCanvas.width, previewCanvas.height);

    // Si des couches isolées existent ET qu'on est dans la section des couches isolées ET qu'on n'est pas en train de sélectionner une nouvelle couleur
    if (isolatedLayers.length > 0 && document.getElementById('isolatedLayersSection').style.display !== 'none' && !pipetteActive) {
        const lastLayer = isolatedLayers[isolatedLayers.length - 1];
        
        // Afficher directement la sélection avec ses couleurs originales (pas de colorisation)
        const tempCanvas = document.createElement('canvas');
        tempCanvas.width = originalImage.width;
        tempCanvas.height = originalImage.height;
        const tempCtx = tempCanvas.getContext('2d');
        tempCtx.putImageData(lastLayer.withColor, 0, 0);
        
        // Dessiner sur le canvas de prévisualisation
        ctx.globalCompositeOperation = 'source-over';
        ctx.globalAlpha = 1;
        ctx.drawImage(tempCanvas, 0, 0, previewCanvas.width, previewCanvas.height);
        ctx.globalAlpha = 1;
        ctx.globalCompositeOperation = 'source-over';
        return;
    }

    // Sinon, utiliser la logique normale des canaux RGB/CMYK/2COLOR
    let channelNames = ['red', 'green', 'blue'];
    
    if (currentMode === 'CMYK') {
        // En mode CMYK, mapper les canaux aux panneaux (4 canaux: C, M, Y, K)
        channelNames = ['cyan', 'magenta', 'yellow', 'black'];
        
        // Mapper les sélecteurs data-channel vers les canaux CMYK
        ['red', 'green', 'blue', 'black'].forEach((panelName, index) => {
            const cmykChannelName = ['cyan', 'magenta', 'yellow', 'black'][index];
            const tambour = document.querySelector(`select[data-channel="${panelName}"]`).value;
            if (tambour === 'none' || !channels[cmykChannelName]) return;
            
            const opacity = parseInt(document.getElementById(`${panelName}OpacitySlider`).value) / 100;
            const colorObj = RISO_COLORS[tambour];
            const color = colorObj ? (colorObj.hex || colorObj) : null;
            
            if (!color) return;
            
            applyChannelToPreview(ctx, channels[cmykChannelName], color, opacity, previewCanvas);
        });
    } else if (currentMode === '2COLOR') {
        // En mode 2 couleurs
        const channelMap = { 'red': 'dark', 'green': 'light' };
        
        ['red', 'green'].forEach(panelName => {
            const channelName = channelMap[panelName];
            const tambour = document.querySelector(`select[data-channel="${panelName}"]`).value;
            if (tambour === 'none' || !channels[channelName]) return;
            
            const opacity = parseInt(document.getElementById(`${panelName}OpacitySlider`).value) / 100;
            const colorObj = RISO_COLORS[tambour];
            const color = colorObj ? (colorObj.hex || colorObj) : null;
            
            if (!color) return;
            
            applyChannelToPreview(ctx, channels[channelName], color, opacity, previewCanvas);
        });
    } else {
        // Mode RGB standard
        channelNames.forEach(channelName => {
            const tambour = document.querySelector(`select[data-channel="${channelName}"]`).value;
            if (tambour === 'none' || !channels[channelName]) return;
            
            const opacity = parseInt(document.getElementById(`${channelName}OpacitySlider`).value) / 100;
            const colorObj = RISO_COLORS[tambour];
            const color = colorObj ? (colorObj.hex || colorObj) : null;
            
            if (!color) return;
            
            applyChannelToPreview(ctx, channels[channelName], color, opacity, previewCanvas);
        });
    }
}

// Fonction helper pour convertir hex en RGB
function hexToRgb(hex) {
    const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    return result ? {
        r: parseInt(result[1], 16),
        g: parseInt(result[2], 16),
        b: parseInt(result[3], 16)
    } : null;
}

// Fonction helper pour appliquer un canal à la prévisualisation
function applyChannelToPreview(ctx, channelData, color, opacity, previewCanvas) {
    // Créer canvas temporaire pour la couche colorisée
    const tempCanvas = document.createElement('canvas');
    tempCanvas.width = originalImage.width;
    tempCanvas.height = originalImage.height;
    const tempCtx = tempCanvas.getContext('2d');
    
    // Dessiner le canal en niveaux de gris
    tempCtx.putImageData(channelData, 0, 0);
    
    // Appliquer la couleur du tambour
    const coloredData = tempCtx.getImageData(0, 0, tempCanvas.width, tempCanvas.height);
    const rgb = hexToRgb(color);
    
    for (let i = 0; i < coloredData.data.length; i += 4) {
        const intensity = coloredData.data[i] / 255; // Utiliser le niveau de gris comme intensité
        
        // Logique différente selon le mode
        let shouldBeTransparent = false;
        
        if (currentMode === '2COLOR') {
            // En mode 2 tambours, déterminer selon le canal
            // Canal tons foncés (dark) : blanc = transparent, noir = couleur
            // Canal tons clairs (light) : noir = transparent, blanc = couleur
            if (channelData === channels.dark) {
                // Canal tons foncés : blanc = transparent
                shouldBeTransparent = intensity > 0.95;
            } else if (channelData === channels.light) {
                // Canal tons clairs : noir = transparent
                shouldBeTransparent = intensity < 0.05;
            } else {
                // Fallback pour autres canaux
                shouldBeTransparent = intensity > 0.95;
            }
        } else {
            // Modes RGB/CMYK : blanc = transparent
            shouldBeTransparent = intensity > 0.95;
        }
        
        if (shouldBeTransparent) {
            // Transparent
            coloredData.data[i] = 255;     // R
            coloredData.data[i+1] = 255;   // G
            coloredData.data[i+2] = 255;   // B
            coloredData.data[i+3] = 0;     // A = transparent
        } else {
            // Couleur du tambour
            coloredData.data[i] = rgb.r;     // R
            coloredData.data[i+1] = rgb.g;   // G
            coloredData.data[i+2] = rgb.b;   // B
            coloredData.data[i+3] = 255 * opacity; // A
        }
    }
    
    tempCtx.putImageData(coloredData, 0, 0);
    
    // Dessiner sur le canvas de prévisualisation avec blend mode normal
    ctx.globalCompositeOperation = 'source-over'; // Au lieu de 'multiply'
    ctx.globalAlpha = 1;
    ctx.drawImage(tempCanvas, 0, 0, previewCanvas.width, previewCanvas.height);
    ctx.globalAlpha = 1;
    ctx.globalCompositeOperation = 'source-over';
}

// hexToRgb() est défini dans riso-tools.js

// Exporter un canal spécifique
function exportChannel(panelName) {
    // Mapper le nom du panneau au canal réel selon le mode
    let actualChannelName = panelName;
    if (currentMode === 'CMYK') {
        const mapping = { 'red': 'cyan', 'green': 'magenta', 'blue': 'yellow', 'black': 'black' };
        actualChannelName = mapping[panelName];
    } else if (currentMode === '2COLOR') {
        const mapping = { 'red': 'dark', 'green': 'light' };
        actualChannelName = mapping[panelName];
    }
    
    if (!channels[actualChannelName] || !originalImage) return;

    const tambour = document.querySelector(`select[data-channel="${panelName}"]`).value;
    if (tambour === 'none') {
        alert('Veuillez sélectionner un tambour pour ce canal avant d\'exporter.');
        return;
    }

    // Créer canvas pour export
    const canvas = document.createElement('canvas');
    canvas.width = originalImage.width;
    canvas.height = originalImage.height;
    const ctx = canvas.getContext('2d');
    
    // Mettre le canal en niveaux de gris
    ctx.putImageData(channels[actualChannelName], 0, 0);

    // Télécharger
    canvas.toBlob(function(blob) {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `riso_${actualChannelName}_${tambour}.png`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });
}

// Exporter toutes les couches actives en ZIP
async function exportAll() {
    if (typeof JSZip === 'undefined') {
        alert('Fonction ZIP non disponible. Exportez les couches individuellement.');
        return;
    }

    const zip = new JSZip();
    let panelNames = ['red', 'green', 'blue'];
    if (currentMode === '2COLOR') {
        panelNames = ['red', 'green'];
    } else if (currentMode === 'CMYK') {
        panelNames = ['red', 'green', 'blue', 'black'];
    }
    let exportCount = 0;

    for (const panelName of panelNames) {
        // Mapper le nom du panneau au canal réel selon le mode
        let actualChannelName = panelName;
        if (currentMode === 'CMYK') {
            const mapping = { 'red': 'cyan', 'green': 'magenta', 'blue': 'yellow', 'black': 'black' };
            actualChannelName = mapping[panelName];
        } else if (currentMode === '2COLOR') {
            const mapping = { 'red': 'dark', 'green': 'light' };
            actualChannelName = mapping[panelName];
        }
        
        const tambour = document.querySelector(`select[data-channel="${panelName}"]`).value;
        if (tambour === 'none' || !channels[actualChannelName]) continue;

        const canvas = document.createElement('canvas');
        canvas.width = originalImage.width;
        canvas.height = originalImage.height;
        const ctx = canvas.getContext('2d');
        ctx.putImageData(channels[actualChannelName], 0, 0);

        const blob = await new Promise(resolve => canvas.toBlob(resolve));
        zip.file(`riso_${actualChannelName}_${tambour}.png`, blob);
        exportCount++;
    }

    if (exportCount === 0) {
        alert('Aucune couche active à exporter. Assignez des tambours d\'abord.');
        return;
    }

    // Générer et télécharger le ZIP
    const content = await zip.generateAsync({type: 'blob'});
    const url = URL.createObjectURL(content);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'riso_layers.zip';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

// ===== NOUVELLES FONCTIONS AVANCÉES =====

// Basculer entre les modes RGB / CMYK / 2COLOR
function switchMode(mode) {
    currentMode = mode;
    
    // Mettre à jour les boutons
    document.getElementById('modeRGB').className = mode === 'RGB' ? 'btn btn-primary' : 'btn btn-default';
    document.getElementById('modeCMYK').className = mode === 'CMYK' ? 'btn btn-primary' : 'btn btn-default';
    document.getElementById('mode2Color').className = mode === '2COLOR' ? 'btn btn-primary' : 'btn btn-default';
    
    // Désactiver la pipette et réafficher la configuration des couches
    if (pipetteActive) {
        pipetteActive = false;
        const btn = document.getElementById('pipetteBtn');
        const info = document.getElementById('pipetteInfo');
        const normalSection = document.getElementById('normalChannelsSection');
        
        btn.className = 'btn btn-primary btn-block';
        btn.innerHTML = '<i class="fa fa-eyedropper"></i> Pipette (Isoler couleur)';
        info.style.display = 'none';
        normalSection.style.display = 'block';
        
        // Masquer la section des couches isolées si aucune couche n'a été créée
        if (isolatedLayers.length === 0) {
            const isolatedSection = document.getElementById('isolatedLayersSection');
            isolatedSection.style.display = 'none';
        }
        
        document.getElementById('originalCanvas').removeEventListener('click', handlePipetteClick);
    }
    
    if (!originalImage) return;
    
    // Réinitialiser l'affichage de tous les panneaux
    document.querySelectorAll('.channel-panel').forEach((panel, index) => {
        if (index < 3) {
            panel.parentElement.style.display = 'block'; // Réafficher les 3 premiers panneaux (col-md-4)
        }
    });
    
    // Gérer le 4ème panneau (black) pour CMYK
    const blackPanel = document.getElementById('blackChannelPanel');
    if (mode === 'CMYK') {
        blackPanel.style.display = 'block';
    } else {
        blackPanel.style.display = 'none';
    }
    
    if (mode === 'RGB') {
        // Réinitialiser les titres pour RGB
        document.querySelectorAll('.channel-panel h5')[0].innerHTML = '<i class="fa fa-circle" style="color: #ff0000;"></i> Canal Rouge';
        document.querySelectorAll('.channel-panel h5')[1].innerHTML = '<i class="fa fa-circle" style="color: #00ff00;"></i> Canal Vert';
        document.querySelectorAll('.channel-panel h5')[2].innerHTML = '<i class="fa fa-circle" style="color: #0000ff;"></i> Canal Bleu';
        
        // Séparation RGB standard
        separateChannels(originalImage);
    } else if (mode === 'CMYK') {
        // Séparation CMYK
        separateChannelsCMYK(originalImage);
    } else if (mode === '2COLOR') {
        // Mode 2 tambours pour images N&B
        separate2Color(originalImage);
    }
    
    updatePreview();
}

// Séparer en CMYK
function separateChannelsCMYK(img) {
    const channelData = extractCMYKChannels(img);
    
    // Afficher les 4 canaux
    displayChannel('redCanvas', channelData.cyan, img.width, img.height);
    displayChannel('greenCanvas', channelData.magenta, img.width, img.height);
    displayChannel('blueCanvas', channelData.yellow, img.width, img.height);
    displayChannel('blackCanvas', channelData.black, img.width, img.height);
    
    // Stocker
    channels.cyan = channelData.cyan;
    channels.magenta = channelData.magenta;
    channels.yellow = channelData.yellow;
    channels.black = channelData.black;
    
    // Sauvegarder les canaux originaux (copie profonde)
    originalChannels.cyan = cloneImageData(channelData.cyan);
    originalChannels.magenta = cloneImageData(channelData.magenta);
    originalChannels.yellow = cloneImageData(channelData.yellow);
    originalChannels.black = cloneImageData(channelData.black);
    
    // Mettre à jour les labels
    document.querySelectorAll('.channel-panel h5')[0].innerHTML = '<i class="fa fa-circle" style="color: #00FFFF;"></i> Canal Cyan';
    document.querySelectorAll('.channel-panel h5')[1].innerHTML = '<i class="fa fa-circle" style="color: #FF00FF;"></i> Canal Magenta';
    document.querySelectorAll('.channel-panel h5')[2].innerHTML = '<i class="fa fa-circle" style="color: #FFFF00;"></i> Canal Yellow';
    document.querySelectorAll('.channel-panel h5')[3].innerHTML = '<i class="fa fa-circle" style="color: #000000;"></i> Canal Black';
}

// Mode 2 tambours (séparer tons clairs / tons foncés)
function separate2Color(img) {
    // Convertir en N&B d'abord
    const grayscale = toGrayscale(originalImageData);
    
    // Séparer en 2 couches avec seuil
    const threshold = 128;
    const split = splitGrayscaleInTwo(grayscale, threshold);
    
    // Afficher
    displayChannel('redCanvas', split.dark, img.width, img.height);
    displayChannel('greenCanvas', split.light, img.width, img.height);
    
    // Stocker
    channels.dark = split.dark;
    channels.light = split.light;
    
    // Sauvegarder les canaux originaux (copie profonde)
    originalChannels.dark = cloneImageData(split.dark);
    originalChannels.light = cloneImageData(split.light);
    
    // Mettre à jour les labels
    document.querySelectorAll('.channel-panel h5')[0].innerHTML = '<i class="fa fa-circle" style="color: #000;"></i> Tons Foncés';
    document.querySelectorAll('.channel-panel h5')[1].innerHTML = '<i class="fa fa-circle" style="color: #fff; border: 1px solid #000;"></i> Tons Clairs';
    
    // Cacher les panneaux 3 et 4
    document.querySelectorAll('.channel-panel')[2].parentElement.style.display = 'none';
}

// Toggle pipette
function togglePipette() {
    pipetteActive = !pipetteActive;
    const btn = document.getElementById('pipetteBtn');
    const info = document.getElementById('pipetteInfo');
    const normalSection = document.getElementById('normalChannelsSection');
    
    if (pipetteActive) {
        btn.className = 'btn btn-success btn-block';
        btn.innerHTML = '<i class="fa fa-eyedropper"></i> Pipette ACTIVE - Cliquez sur l\'image';
        info.style.display = 'block';
        
        // Masquer la configuration des couches quand la pipette est active
        normalSection.style.display = 'none';
        
        // Afficher la section des couches isolées
        const isolatedSection = document.getElementById('isolatedLayersSection');
        isolatedSection.style.display = 'block';
        
        // Ajouter listener sur le canvas
        document.getElementById('originalCanvas').addEventListener('click', handlePipetteClick);
    } else {
        btn.className = 'btn btn-primary btn-block';
        btn.innerHTML = '<i class="fa fa-eyedropper"></i> Pipette (Isoler couleur)';
        info.style.display = 'none';
        
        // Réafficher la configuration des couches quand la pipette est désactivée
        normalSection.style.display = 'block';
        
        // Masquer la section des couches isolées si aucune couche n'a été créée
        if (isolatedLayers.length === 0) {
            const isolatedSection = document.getElementById('isolatedLayersSection');
            isolatedSection.style.display = 'none';
        }
        
        document.getElementById('originalCanvas').removeEventListener('click', handlePipetteClick);
    }
}

// Gérer le clic pipette
function handlePipetteClick(e) {
    if (!pipetteActive) return;
    
    const canvas = e.target;
    const rect = canvas.getBoundingClientRect();
    const scaleX = originalImage.width / rect.width;
    const scaleY = originalImage.height / rect.height;
    const x = Math.floor((e.clientX - rect.left) * scaleX);
    const y = Math.floor((e.clientY - rect.top) * scaleY);
    
    // Obtenir la couleur du pixel
    const ctx = canvas.getContext('2d');
    const imageData = ctx.getImageData(x / (originalImage.width / canvas.width), y / (originalImage.height / canvas.height), 1, 1);
    pickedColorRGB = {
        r: imageData.data[0],
        g: imageData.data[1],
        b: imageData.data[2]
    };
    
    // Afficher la couleur
    const colorDisplay = document.getElementById('pickedColor');
    colorDisplay.style.background = `rgb(${pickedColorRGB.r}, ${pickedColorRGB.g}, ${pickedColorRGB.b})`;
    
    // Mettre à jour la valeur de tolérance
    document.getElementById('toleranceValue').textContent = document.getElementById('toleranceSlider').value;
    
    // Afficher la prévisualisation de la sélection (sans créer de couche)
    updatePipettePreview();
}

// Mettre à jour la prévisualisation de la pipette en temps réel
function updatePipettePreview() {
    console.log('updatePipettePreview appelée');
    
    if (!pickedColorRGB || !currentWorkingImageData) {
        console.log('updatePipettePreview: manque pickedColorRGB ou currentWorkingImageData');
        return;
    }
    
    console.log('updatePipettePreview: couleur sélectionnée', pickedColorRGB);
    
    const tolerance = parseInt(document.getElementById('toleranceSlider').value);
    
    // Vérifier si isolateColor existe
    if (typeof isolateColor !== 'function') {
        console.error('isolateColor n\'est pas définie!');
        return;
    }
    
    // Créer la couche "avec couleur" pour la prévisualisation (pixels sélectionnés)
    const isolatedWith = isolateColor(currentWorkingImageData, pickedColorRGB.r, pickedColorRGB.g, pickedColorRGB.b, tolerance);
    console.log('updatePipettePreview: isolatedWith créé');
    
    // Afficher dans le canvas de prévisualisation approprié selon le contexte
    let previewCanvas = document.getElementById('previewCanvas');
    let originalCanvas = document.getElementById('originalCanvas');
    
    // Si on est dans la section des couches isolées, utiliser le bon canvas
    if (document.getElementById('isolatedLayersSection').style.display !== 'none') {
        previewCanvas = document.getElementById('isolatedPreviewCanvas');
        console.log('updatePipettePreview: utilisation du canvas des couches isolées');
    } else {
        console.log('updatePipettePreview: utilisation du canvas normal');
    }
    
    console.log('updatePipettePreview: canvas trouvés', !!previewCanvas, !!originalCanvas);
    
    if (!previewCanvas || !originalCanvas) return;
    
    previewCanvas.width = originalCanvas.width;
    previewCanvas.height = originalCanvas.height;
    
    const ctx = previewCanvas.getContext('2d');
    ctx.fillStyle = 'white';
    ctx.fillRect(0, 0, previewCanvas.width, previewCanvas.height);
    
    // Afficher directement la sélection (sans colorisation, juste les pixels sélectionnés)
    const tempCanvas = document.createElement('canvas');
    tempCanvas.width = originalImage.width;
    tempCanvas.height = originalImage.height;
    const tempCtx = tempCanvas.getContext('2d');
    tempCtx.putImageData(isolatedWith, 0, 0);
    
    console.log('updatePipettePreview: dessin sur previewCanvas');
    ctx.globalCompositeOperation = 'source-over';
    ctx.globalAlpha = 1;
    ctx.drawImage(tempCanvas, 0, 0, previewCanvas.width, previewCanvas.height);
    ctx.globalAlpha = 1;
    ctx.globalCompositeOperation = 'source-over';
    
    // Marquer qu'on vient de faire une prévisualisation pipette pour empêcher updatePreview de s'exécuter
    window.justDidPipettePreview = true;
    setTimeout(() => {
        window.justDidPipettePreview = false;
    }, 100);
    
    console.log('updatePipettePreview terminée');
}


// Mettre à jour la prévisualisation avec la sélection postérisée
function updatePipettePreviewWithPosterized() {
    if (!posterizedSelection) return;
    
    // Afficher dans le canvas de prévisualisation approprié selon le contexte
    let previewCanvas = document.getElementById('previewCanvas');
    let originalCanvas = document.getElementById('originalCanvas');
    
    // Si on est dans la section des couches isolées, utiliser le bon canvas
    if (document.getElementById('isolatedLayersSection').style.display !== 'none') {
        previewCanvas = document.getElementById('isolatedPreviewCanvas');
    }
    
    if (!previewCanvas || !originalCanvas) return;
    
    previewCanvas.width = originalCanvas.width;
    previewCanvas.height = originalCanvas.height;
    
    const ctx = previewCanvas.getContext('2d');
    ctx.fillStyle = 'white';
    ctx.fillRect(0, 0, previewCanvas.width, previewCanvas.height);
    
    // Afficher la sélection postérisée
    const tempCanvas = document.createElement('canvas');
    tempCanvas.width = originalImage.width;
    tempCanvas.height = originalImage.height;
    const tempCtx = tempCanvas.getContext('2d');
    tempCtx.putImageData(posterizedSelection, 0, 0);
    
    ctx.globalCompositeOperation = 'source-over';
    ctx.globalAlpha = 1;
    ctx.drawImage(tempCanvas, 0, 0, previewCanvas.width, previewCanvas.height);
    ctx.globalAlpha = 1;
    ctx.globalCompositeOperation = 'source-over';
    
    // Marquer qu'on vient de faire une prévisualisation pipette pour empêcher updatePreview de s'exécuter
    window.justDidPipettePreview = true;
    setTimeout(() => {
        window.justDidPipettePreview = false;
    }, 100);
}

// Mettre à jour la prévisualisation avec la sélection tramée
function updatePipettePreviewWithHalftoned() {
    if (!halftonedSelection) return;
    
    // Afficher dans le canvas de prévisualisation approprié selon le contexte
    let previewCanvas = document.getElementById('previewCanvas');
    let originalCanvas = document.getElementById('originalCanvas');
    
    // Si on est dans la section des couches isolées, utiliser le bon canvas
    if (document.getElementById('isolatedLayersSection').style.display !== 'none') {
        previewCanvas = document.getElementById('isolatedPreviewCanvas');
    }
    
    if (!previewCanvas || !originalCanvas) return;
    
    previewCanvas.width = originalCanvas.width;
    previewCanvas.height = originalCanvas.height;
    
    const ctx = previewCanvas.getContext('2d');
    ctx.fillStyle = 'white';
    ctx.fillRect(0, 0, previewCanvas.width, previewCanvas.height);
    
    // Afficher la sélection tramée
    const tempCanvas = document.createElement('canvas');
    tempCanvas.width = originalImage.width;
    tempCanvas.height = originalImage.height;
    const tempCtx = tempCanvas.getContext('2d');
    tempCtx.putImageData(halftonedSelection, 0, 0);
    
    ctx.globalCompositeOperation = 'source-over';
    ctx.globalAlpha = 1;
    ctx.drawImage(tempCanvas, 0, 0, previewCanvas.width, previewCanvas.height);
    ctx.globalAlpha = 1;
    ctx.globalCompositeOperation = 'source-over';
    
    // Marquer qu'on vient de faire une prévisualisation pipette pour empêcher updatePreview de s'exécuter
    window.justDidPipettePreview = true;
    setTimeout(() => {
        window.justDidPipettePreview = false;
    }, 100);
}

// Postériser une ImageData
function posterizeImageData(imageData, levels) {
    const width = imageData.width;
    const height = imageData.height;
    const result = new ImageData(width, height);
    
    const step = 255 / (levels - 1);
    
    for (let i = 0; i < imageData.data.length; i += 4) {
        const r = imageData.data[i];
        const g = imageData.data[i+1];
        const b = imageData.data[i+2];
        const a = imageData.data[i+3];
        
        if (a > 0) { // Si le pixel n'est pas transparent
            // Calculer le niveau de gris
            const gray = Math.round((r + g + b) / 3);
            
            // Postériser
            const posterizedGray = Math.round(gray / step) * step;
            
            result.data[i] = posterizedGray;
            result.data[i+1] = posterizedGray;
            result.data[i+2] = posterizedGray;
            result.data[i+3] = a;
        } else {
            // Pixel transparent
            result.data[i] = 255;
            result.data[i+1] = 255;
            result.data[i+2] = 255;
            result.data[i+3] = 0;
        }
    }
    
    return result;
}

// Appliquer l'isolation de couleur
function applyPipette() {
    if (!pickedColorRGB || !currentWorkingImageData) return;
    
    const tolerance = parseInt(document.getElementById('toleranceSlider').value);
    
    // Créer la couche "avec couleur" (pixels sélectionnés)
    let isolatedWith;
    if (halftonedSelection) {
        // Utiliser la sélection tramée si disponible
        isolatedWith = halftonedSelection;
    } else if (posterizedSelection) {
        // Utiliser la sélection postérisée si disponible
        isolatedWith = posterizedSelection;
    } else {
        // Sinon créer une nouvelle sélection
        isolatedWith = isolateColor(currentWorkingImageData, pickedColorRGB.r, pickedColorRGB.g, pickedColorRGB.b, tolerance);
    }
    
    // Créer la couche "sans couleur" (image moins les pixels sélectionnés)
    const isolatedWithout = createWithoutColorLayer(currentWorkingImageData, pickedColorRGB.r, pickedColorRGB.g, pickedColorRGB.b, tolerance);
    
    // Ajouter les couches à la liste
    const layerId = Date.now(); // ID unique pour cette sélection
    isolatedLayers.push({
        id: layerId,
        color: pickedColorRGB,
        tolerance: tolerance,
        withColor: isolatedWith,
        withoutColor: isolatedWithout,
        timestamp: new Date()
    });
    
    // Afficher les couches dans l'interface
    displayIsolatedLayers();
    
    // Mettre à jour l'image de travail (enlever les pixels sélectionnés)
    updateWorkingImage(isolatedWith);
    
    // Mettre à jour la prévisualisation des couches isolées
    updateIsolatedPreview();
    
    // Réinitialiser la sélection postérisée
    posterizedSelection = null;
    halftonedSelection = null;
    
    // Désactiver la pipette
    togglePipette();
    
    alert('Couches isolées créées ! Vous pouvez continuer à sélectionner d\'autres couleurs.');
}

// Créer une couche "sans couleur" (image moins les pixels sélectionnés)
function createWithoutColorLayer(imageData, targetR, targetG, targetB, tolerance) {
    const width = imageData.width;
    const height = imageData.height;
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');
    
    const result = ctx.createImageData(width, height);
    const data = imageData.data;
    
    for (let i = 0; i < data.length; i += 4) {
        const r = data[i];
        const g = data[i+1];
        const b = data[i+2];
        
        // Calculer la distance avec la couleur cible
        const distance = Math.sqrt(
            Math.pow(r - targetR, 2) + 
            Math.pow(g - targetG, 2) + 
            Math.pow(b - targetB, 2)
        );
        
        if (distance <= tolerance) {
            // Pixel sélectionné = transparent
            result.data[i] = 255;     // R
            result.data[i+1] = 255;   // G
            result.data[i+2] = 255;   // B
            result.data[i+3] = 0;     // A = transparent
        } else {
            // Pixel non sélectionné = garder la couleur originale
            result.data[i] = r;       // R
            result.data[i+1] = g;     // G
            result.data[i+2] = b;     // B
            result.data[i+3] = 255;   // A
        }
    }
    
    return result;
}

// Afficher les couches isolées dans l'interface
function displayIsolatedLayers() {
    const container = document.getElementById('isolatedLayersContainer');
    const section = document.getElementById('isolatedLayersSection');
    const normalSection = document.getElementById('normalChannelsSection');
    
    if (isolatedLayers.length === 0) {
        section.style.display = 'none';
        normalSection.style.display = 'block';
        return;
    }
    
    // Masquer les sections normales et afficher les couches isolées
    section.style.display = 'block';
    normalSection.style.display = 'none';
    container.innerHTML = '';
    
    isolatedLayers.forEach((layer, index) => {
        const layerDiv = document.createElement('div');
        layerDiv.className = 'panel panel-default';
        layerDiv.style.marginBottom = '20px';
        
        const colorHex = `#${layer.color.r.toString(16).padStart(2, '0')}${layer.color.g.toString(16).padStart(2, '0')}${layer.color.b.toString(16).padStart(2, '0')}`;
        
        layerDiv.innerHTML = `
            <div class="panel-heading">
                <h5><i class="fa fa-eyedropper"></i> Sélection ${index + 1} - Couleur: <span style="display: inline-block; width: 20px; height: 20px; background: ${colorHex}; border: 1px solid #000; vertical-align: middle;"></span> (Tolérance: ${layer.tolerance})</h5>
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fa fa-plus"></i> Avec couleur</h6>
                        <canvas id="isolatedWith_${layer.id}" class="img-thumbnail" style="max-width: 300px;"></canvas>
                        <br>
                        <button class="btn btn-sm btn-success" onclick="downloadIsolatedLayer(${layer.id}, 'with')" style="margin-top: 10px;">
                            <i class="fa fa-download"></i> Télécharger
                        </button>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fa fa-minus"></i> Sans couleur</h6>
                        <canvas id="isolatedWithout_${layer.id}" class="img-thumbnail" style="max-width: 300px;"></canvas>
                        <br>
                        <button class="btn btn-sm btn-success" onclick="downloadIsolatedLayer(${layer.id}, 'without')" style="margin-top: 10px;">
                            <i class="fa fa-download"></i> Télécharger
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        container.appendChild(layerDiv);
        
        // Afficher les couches sur les canvas
        displayIsolatedLayerCanvas(`isolatedWith_${layer.id}`, layer.withColor);
        displayIsolatedLayerCanvas(`isolatedWithout_${layer.id}`, layer.withoutColor);
    });
}

// Afficher une couche isolée sur un canvas
function displayIsolatedLayerCanvas(canvasId, imageData) {
    const canvas = document.getElementById(canvasId);
    const maxWidth = 300;
    const scale = Math.min(1, maxWidth / imageData.width);
    canvas.width = imageData.width * scale;
    canvas.height = imageData.height * scale;
    
    const tempCanvas = document.createElement('canvas');
    tempCanvas.width = imageData.width;
    tempCanvas.height = imageData.height;
    const tempCtx = tempCanvas.getContext('2d');
    tempCtx.putImageData(imageData, 0, 0);
    
    const ctx = canvas.getContext('2d');
    ctx.drawImage(tempCanvas, 0, 0, canvas.width, canvas.height);
}

// Télécharger une couche isolée
function downloadIsolatedLayer(layerId, type) {
    const layer = isolatedLayers.find(l => l.id === layerId);
    if (!layer) return;
    
    const imageData = type === 'with' ? layer.withColor : layer.withoutColor;
    const suffix = type === 'with' ? 'avec' : 'sans';
    
    const canvas = document.createElement('canvas');
    canvas.width = imageData.width;
    canvas.height = imageData.height;
    const ctx = canvas.getContext('2d');
    ctx.putImageData(imageData, 0, 0);
    
    canvas.toBlob(function(blob) {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `riso_pipette_${suffix}_${layerId}.png`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });
}

// Mettre à jour l'image de travail (enlever les pixels sélectionnés)
function updateWorkingImage(isolatedWithData) {
    const width = currentWorkingImageData.width;
    const height = currentWorkingImageData.height;
    
    for (let i = 0; i < currentWorkingImageData.data.length; i += 4) {
        // Si le pixel est transparent dans la couche isolée, le rendre transparent dans l'image de travail
        if (isolatedWithData.data[i+3] === 0) {
            currentWorkingImageData.data[i+3] = 0; // Alpha = transparent
        }
    }
}

// Mettre à jour la prévisualisation des couches isolées
function updateIsolatedPreview() {
    if (isolatedLayers.length === 0) return;
    
    const previewCanvas = document.getElementById('isolatedPreviewCanvas');
    const originalCanvas = document.getElementById('originalCanvas');
    previewCanvas.width = originalCanvas.width;
    previewCanvas.height = originalCanvas.height;
    
    const ctx = previewCanvas.getContext('2d');
    ctx.fillStyle = 'white';
    ctx.fillRect(0, 0, previewCanvas.width, previewCanvas.height);
    
    // Afficher seulement la dernière couche créée (pas d'accumulation)
    const lastLayer = isolatedLayers[isolatedLayers.length - 1];
    
    // Afficher directement la sélection avec ses couleurs originales (pas de colorisation)
    const tempCanvas = document.createElement('canvas');
    tempCanvas.width = originalImage.width;
    tempCanvas.height = originalImage.height;
    const tempCtx = tempCanvas.getContext('2d');
    tempCtx.putImageData(lastLayer.withColor, 0, 0);
    
    ctx.globalCompositeOperation = 'source-over';
    ctx.globalAlpha = 1;
    ctx.drawImage(tempCanvas, 0, 0, previewCanvas.width, previewCanvas.height);
    ctx.globalAlpha = 1;
    ctx.globalCompositeOperation = 'source-over';
}

// Exporter toutes les couches isolées en ZIP
async function exportAllIsolated() {
    if (typeof JSZip === 'undefined') {
        alert('Fonction ZIP non disponible. Exportez les couches individuellement.');
        return;
    }

    const zip = new JSZip();
    let exportCount = 0;

    isolatedLayers.forEach((layer, index) => {
        // Exporter la couche "avec couleur"
        const canvasWith = document.createElement('canvas');
        canvasWith.width = layer.withColor.width;
        canvasWith.height = layer.withColor.height;
        const ctxWith = canvasWith.getContext('2d');
        ctxWith.putImageData(layer.withColor, 0, 0);

        const blobWith = canvasWith.toBlob(function(blob) {
            zip.file(`riso_pipette_avec_${index + 1}.png`, blob);
        });

        // Exporter la couche "sans couleur"
        const canvasWithout = document.createElement('canvas');
        canvasWithout.width = layer.withoutColor.width;
        canvasWithout.height = layer.withoutColor.height;
        const ctxWithout = canvasWithout.getContext('2d');
        ctxWithout.putImageData(layer.withoutColor, 0, 0);

        const blobWithout = canvasWithout.toBlob(function(blob) {
            zip.file(`riso_pipette_sans_${index + 1}.png`, blob);
        });

        exportCount += 2;
    });

    if (exportCount === 0) {
        alert('Aucune couche isolée à exporter.');
        return;
    }

    // Générer et télécharger le ZIP
    const content = await zip.generateAsync({type: 'blob'});
    const url = URL.createObjectURL(content);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'riso_pipette_layers.zip';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

// Appliquer postérisation
function applyPosterization() {
    // Si la pipette est active et qu'une couleur est sélectionnée, postériser la sélection
    if (pipetteActive && pickedColorRGB && currentWorkingImageData) {
        const tolerance = parseInt(document.getElementById('toleranceSlider').value);
        const levels = parseInt(document.getElementById('posterSlider').value);
        
        // Créer la sélection de base
        const isolatedWith = isolateColor(currentWorkingImageData, pickedColorRGB.r, pickedColorRGB.g, pickedColorRGB.b, tolerance);
        
        // Appliquer la postérisation
        posterizedSelection = posterizeImageData(isolatedWith, levels);
        
        // Mettre à jour la prévisualisation avec la sélection postérisée
        updatePipettePreviewWithPosterized();
        
        alert(`Sélection postérisée avec ${levels} niveaux !`);
        return;
    }
    
    // Sinon, postériser l'image complète comme avant
    if (!originalImageData) return;
    
    const levels = parseInt(document.getElementById('posterSlider').value);
    const posterized = posterizeImage(originalImageData, levels);
    
    // Re-séparer les canaux avec l'image postérisée
    const tempCanvas = document.createElement('canvas');
    tempCanvas.width = originalImage.width;
    tempCanvas.height = originalImage.height;
    const tempCtx = tempCanvas.getContext('2d');
    tempCtx.putImageData(posterized, 0, 0);
    
    const tempImg = new Image();
    tempImg.onload = function() {
        separateChannels(tempImg);
        updatePreview();
    };
    tempImg.src = tempCanvas.toDataURL();
}

// Appliquer effet halftone
function applyHalftoneEffect() {
    const dotSize = parseInt(document.getElementById('halftoneSlider').value);
    
    // Si la pipette est active et qu'une couleur est sélectionnée, tramer la sélection
    if (pipetteActive && pickedColorRGB && currentWorkingImageData) {
        const tolerance = parseInt(document.getElementById('toleranceSlider').value);
        
        // Créer la sélection de base
        const isolatedWith = isolateColor(currentWorkingImageData, pickedColorRGB.r, pickedColorRGB.g, pickedColorRGB.b, tolerance);
        
        // Appliquer l'effet halftone
        halftonedSelection = applyHalftone(isolatedWith, dotSize, 45);
        
        // Mettre à jour la prévisualisation avec la sélection tramée
        updatePipettePreviewWithHalftoned();
        
        alert('Sélection tramée !');
        return;
    }
    
    // Sinon, appliquer l'effet halftone à TOUS les canaux selon le mode actuel
    if (!originalImageData) return;
    
    if (currentMode === 'RGB') {
        // Tramer les 3 canaux RGB
        const halftoned_r = applyHalftone(channels.red, dotSize, 15);
        const halftoned_g = applyHalftone(channels.green, dotSize, 45);
        const halftoned_b = applyHalftone(channels.blue, dotSize, 75);
        
        displayChannel('redCanvas', halftoned_r, originalImage.width, originalImage.height);
        displayChannel('greenCanvas', halftoned_g, originalImage.width, originalImage.height);
        displayChannel('blueCanvas', halftoned_b, originalImage.width, originalImage.height);
        
        channels.red = halftoned_r;
        channels.green = halftoned_g;
        channels.blue = halftoned_b;
        
        alert('Effet halftone appliqué aux 3 canaux RGB !');
    } else if (currentMode === 'CMYK') {
        // Tramer les 4 canaux CMYK avec des angles différents
        const halftoned_c = applyHalftone(channels.cyan, dotSize, 15);
        const halftoned_m = applyHalftone(channels.magenta, dotSize, 75);
        const halftoned_y = applyHalftone(channels.yellow, dotSize, 0);
        const halftoned_k = applyHalftone(channels.black, dotSize, 45);
        
        displayChannel('redCanvas', halftoned_c, originalImage.width, originalImage.height);
        displayChannel('greenCanvas', halftoned_m, originalImage.width, originalImage.height);
        displayChannel('blueCanvas', halftoned_y, originalImage.width, originalImage.height);
        displayChannel('blackCanvas', halftoned_k, originalImage.width, originalImage.height);
        
        channels.cyan = halftoned_c;
        channels.magenta = halftoned_m;
        channels.yellow = halftoned_y;
        channels.black = halftoned_k;
        
        alert('Effet halftone appliqué aux 4 canaux CMYK !');
    } else if (currentMode === '2COLOR') {
        // Tramer les 2 canaux
        const halftoned_light = applyHalftone(channels.light, dotSize, 15);
        const halftoned_dark = applyHalftone(channels.dark, dotSize, 45);
        
        displayChannel('redCanvas', halftoned_light, originalImage.width, originalImage.height);
        displayChannel('greenCanvas', halftoned_dark, originalImage.width, originalImage.height);
        
        channels.light = halftoned_light;
        channels.dark = halftoned_dark;
        
        alert('Effet halftone appliqué aux 2 tambours !');
    }
    
    updatePreview();
}

// Mise à jour slider postérisation
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('posterSlider')) {
        document.getElementById('posterSlider').addEventListener('input', function() {
            document.getElementById('posterLevels').textContent = this.value;
        });
    }
    
    if (document.getElementById('toleranceSlider')) {
        document.getElementById('toleranceSlider').addEventListener('input', function() {
            document.getElementById('toleranceValue').textContent = this.value;
        });
    }
    
    if (document.getElementById('halftoneSlider')) {
        document.getElementById('halftoneSlider').addEventListener('input', function() {
            document.getElementById('halftoneSize').textContent = this.value;
        });
    }
    
    // Activer mode RGB par défaut
    document.getElementById('modeRGB').className = 'btn btn-primary';
});

// Cloner un ImageData (copie profonde)
function cloneImageData(imageData) {
    if (!imageData) return null;
    const canvas = document.createElement('canvas');
    canvas.width = imageData.width;
    canvas.height = imageData.height;
    const ctx = canvas.getContext('2d');
    const cloned = ctx.createImageData(imageData.width, imageData.height);
    cloned.data.set(imageData.data);
    return cloned;
}

// Réinitialiser les canaux (restaurer les canaux originaux avant effets)
function resetChannels() {
    if (!originalImage) {
        alert('Aucune image chargée !');
        return;
    }
    
    if (currentMode === 'RGB') {
        if (originalChannels.red && originalChannels.green && originalChannels.blue) {
            channels.red = cloneImageData(originalChannels.red);
            channels.green = cloneImageData(originalChannels.green);
            channels.blue = cloneImageData(originalChannels.blue);
            
            displayChannel('redCanvas', channels.red, originalImage.width, originalImage.height);
            displayChannel('greenCanvas', channels.green, originalImage.width, originalImage.height);
            displayChannel('blueCanvas', channels.blue, originalImage.width, originalImage.height);
            
            updatePreview();
            alert('Canaux RGB réinitialisés !');
        }
    } else if (currentMode === 'CMYK') {
        if (originalChannels.cyan && originalChannels.magenta && originalChannels.yellow && originalChannels.black) {
            channels.cyan = cloneImageData(originalChannels.cyan);
            channels.magenta = cloneImageData(originalChannels.magenta);
            channels.yellow = cloneImageData(originalChannels.yellow);
            channels.black = cloneImageData(originalChannels.black);
            
            displayChannel('redCanvas', channels.cyan, originalImage.width, originalImage.height);
            displayChannel('greenCanvas', channels.magenta, originalImage.width, originalImage.height);
            displayChannel('blueCanvas', channels.yellow, originalImage.width, originalImage.height);
            displayChannel('blackCanvas', channels.black, originalImage.width, originalImage.height);
            
            updatePreview();
            alert('Canaux CMYK réinitialisés !');
        }
    } else if (currentMode === '2COLOR') {
        if (originalChannels.dark && originalChannels.light) {
            channels.dark = cloneImageData(originalChannels.dark);
            channels.light = cloneImageData(originalChannels.light);
            
            displayChannel('redCanvas', channels.dark, originalImage.width, originalImage.height);
            displayChannel('greenCanvas', channels.light, originalImage.width, originalImage.height);
            
            updatePreview();
            alert('Canaux 2 tambours réinitialisés !');
        }
    }
}

// Réinitialiser le séparateur
function resetSeparator() {
    document.getElementById('uploadSection').style.display = 'block';
    document.getElementById('separatorSection').style.display = 'none';
    document.getElementById('imageInput').value = '';
    originalImage = null;
    originalImageData = null;
    currentMode = 'RGB';
    pipetteActive = false;
    pickedColorRGB = null;
    channels = {
        red: null, green: null, blue: null,
        cyan: null, magenta: null, yellow: null, black: null,
        light: null, dark: null, isolated: null
    };
}
</script>
