<link rel="stylesheet" href="css/studio.css?v=<?php echo time(); ?>">
<script src="js/build/pdf.js" defer></script>
<script src="js/fabric.min.js" defer></script>
<script src="js/jszip.min.js" defer></script>
<style>
  #modificationContainer,
  #modificationContainer .canvas-container,
  #modificationContainer canvas {
    background: transparent !important;
    background-color: transparent !important;
    box-shadow: none !important;
  }
</style>
<script>
window.addEventListener('error', function(e) {
  const errDiv = document.createElement('div');
  errDiv.style.position = 'fixed'; errDiv.style.top = '10px'; errDiv.style.left = '10px'; errDiv.style.zIndex = '9999'; errDiv.style.background = 'red'; errDiv.style.color = 'white'; errDiv.style.padding = '10px'; errDiv.style.fontSize = '12px';
  errDiv.textContent = 'JS Error: ' + e.message + ' at ' + e.filename + ':' + e.lineno;
  document.body.appendChild(errDiv);
});
window.addEventListener('unhandledrejection', function(e) {
  const errDiv = document.createElement('div');
  errDiv.style.position = 'fixed'; errDiv.style.top = '40px'; errDiv.style.left = '10px'; errDiv.style.zIndex = '9999'; errDiv.style.background = 'orange'; errDiv.style.color = 'white'; errDiv.style.padding = '10px'; errDiv.style.fontSize = '12px';
  const reason = e.reason ? (e.reason.stack || e.reason.message || e.reason) : 'Unknown Promise Rejection';
  errDiv.textContent = 'Promise Error: ' + reason;
  document.body.appendChild(errDiv);
});
</script>
<script src="js/riso-tools.js?v=<?php echo time(); ?>" defer></script>
<script src="js/studio-montage.js?v=<?php echo time(); ?>" defer></script>
<script src="js/studio-modification.js?v=<?php echo time(); ?>" defer></script>
<script src="js/studio-metadata.js?v=<?php echo time(); ?>" defer></script>

<div class="studio-layout" id="studioApp">

  <!-- === SIDEBAR === -->
  <aside class="studio-sidebar">
    <button class="tool-btn active" data-tool="filters" title="Filtres"><i class="fa fa-sliders"></i>Filtres</button>
    <button class="tool-btn" data-tool="geometry" title="Géométrie"><i class="fa fa-crop"></i>Géométrie</button>
    <div class="sidebar-divider"></div>
    <button class="tool-btn" data-tool="imposition" title="Imposition"><i class="fa fa-book"></i>Imposition</button>
    <button class="tool-btn" data-tool="montage" title="Montage Libre"><i class="fa fa-object-group"></i>Montage</button>
    <button class="tool-btn" data-tool="pages" title="Pages"><i class="fa fa-files-o"></i>Pages</button>
    <div class="sidebar-divider"></div>
    <button class="tool-btn" data-tool="riso" title="Riso"><i class="fa fa-adjust"></i>Riso</button>
    <button class="tool-btn" data-tool="ocr" title="OCR & Scan"><i class="fa fa-font"></i>OCR & Scan</button>
    <button class="tool-btn" data-tool="modification" title="Modification PDF"><i class="fa fa-edit"></i>Modification</button>
    <button class="tool-btn" data-tool="metadata" title="Métadonnées"><i class="fa fa-tags"></i>Métadonnées</button>
  </aside>

  <!-- === MAIN WORKSPACE === -->
  <main class="studio-workspace">
    <!-- Toolbar -->
    <div class="studio-toolbar">
      <div class="toolbar-title"><i class="fa fa-magic"></i> Dupli Studio</div>
      <span class="file-info-badge" id="fileInfoBadge" style="display:none">
        <i class="fa fa-file"></i> <input type="text" id="fileNameDisplay" style="background:transparent; border:none; outline:none; border-bottom:1px dashed rgba(255,255,255,0.5); color:inherit; font-family:inherit; font-size:inherit; font-weight:inherit; min-width: 150px; max-width:250px;">
        <span id="fileDimsDisplay" style="opacity:0.7;margin-left:6px;font-size:11px"></span>
        <span id="fileInkDisplay" style="margin-left:8px; padding:2px 8px; background:rgba(0,0,0,0.1); border-radius:10px; font-size:11px; font-weight:600; display:none" title="Taux d'encrage moyen (C+M+J+N)"></span>
      </span>
      <div class="toolbar-spacer"></div>
      <button class="toolbar-btn" id="btnNewFile" style="display:none"><i class="fa fa-upload"></i> Nouveau fichier</button>
      <button class="toolbar-btn" id="btnExportPng" style="display:none" title="Exporter le canvas (avec filtres) en PNG"><i class="fa fa-file-image-o"></i> PNG</button>
      <button class="toolbar-btn" id="btnSaveToLibrary" style="display:none" title="Enregistrer dans la Bibliothèque">
        <i class="fa fa-bookmark"></i> Bibliothèque
      </button>
      <button class="toolbar-btn primary" id="btnExportPdf" style="display:none; position: relative;" title="Exporter en PDF via serveur">
        <i class="fa fa-file-pdf-o"></i> PDF
        <span id="pdfReadyBadge" style="display:none; position: absolute; top: -5px; right: -5px; background: #10b981; color: white; border-radius: 50%; width: 16px; height: 16px; font-size: 10px; align-items: center; justify-content: center; box-shadow: 0 0 0 2px var(--studio-surface);"><i class="fa fa-check"></i></span>
      </button>
    </div>

    <!-- Canvas / Upload Area -->
    <div class="studio-canvas-area" id="canvasArea">
      <!-- Upload Zone (visible par défaut) -->
      <div class="studio-upload-zone" id="uploadZone">
        <div class="upload-icon"><i class="fa fa-cloud-upload"></i></div>
        <div class="upload-title">Déposez votre fichier ici</div>
        <div class="upload-subtitle">ou cliquez pour parcourir</div>
        <div class="upload-formats">
          <span>PDF</span><span>PNG</span><span>JPG</span><span>GIF</span><span>WebP</span>
        </div>
        <input type="file" id="studioFileInput" accept=".pdf,.png,.jpg,.jpeg,.gif,.webp" style="display:none">
      </div>

      <!-- Preview canvas (hidden until file loaded) -->
      <div id="studioSpinner" class="studio-spinner" style="display:none">
        <i class="fa fa-spinner fa-spin fa-3x" style="color:var(--studio-primary); margin-bottom:16px;"></i>
        <div id="spinnerMsg" style="font-weight:600">Traitement en cours...</div>
      </div>
      
      <!-- Delete page button overlaid on canvas -->
      <div id="mainCanvasDeleteBtn" style="display:none; position:absolute; top:10px; right:10px; background:rgba(239,68,68,0.9); color:white; width:36px; height:36px; border-radius:18px; align-items:center; justify-content:center; cursor:pointer; z-index:20; box-shadow:0 2px 5px rgba(0,0,0,0.2); transition:transform 0.2s;" title="Supprimer cette page" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
        <i class="fa fa-trash"></i>
      </div>

      <!-- Lightbox (hidden) -->
      <div id="studioLightbox" class="studio-lightbox">
        <div class="lightbox-header">
          <div class="lightbox-title"><i class="fa fa-eye"></i> Aperçu Taille Réelle (100%)</div>
          <button class="btn-close-lightbox" id="btnCloseLightbox"><i class="fa fa-times"></i></button>
        </div>
        <div class="lightbox-content">
          <canvas id="lightboxCanvas"></canvas>
        </div>
      </div>

      <!-- Canvas standard (rendu du PDF/image) -->
      <canvas id="studioCanvas" style="display:none; box-shadow: 0 4px 12px rgba(0,0,0,0.1); border-radius: 4px; transition: transform 0.2s;"></canvas>
      
      <!-- Crop overlay container (position:absolute par-dessus le canvas, seulement en mode crop) -->
      <div id="cropContainer" style="display:none; position:absolute; pointer-events:none; z-index:15;">
        <!-- Coin spacer -->
        <div id="cropCorner" style="position:absolute; top:0; left:0; width:30px; height:30px; background:#f0f2f5; border-bottom:1px solid #ccc; border-right:1px solid #ccc; z-index:5;"></div>
        <!-- Règle X (haut) -->
        <canvas id="cropRulerX" style="position:absolute; top:0; left:30px; height:30px; display:block; background:#f0f2f5; border-bottom:1px solid #ccc; max-width:none !important; max-height:none !important; object-fit:fill !important; box-shadow:none !important; z-index:5;"></canvas>
        <!-- Règle Y (gauche) -->
        <canvas id="cropRulerY" style="position:absolute; top:30px; left:0; width:30px; display:block; background:#f0f2f5; border-right:1px solid #ccc; max-width:none !important; max-height:none !important; object-fit:fill !important; box-shadow:none !important; z-index:5;"></canvas>
        <!-- Overlay de crop (poignées + zones rouges) - pointer-events actif -->
        <canvas id="cropOverlay" style="position:absolute; top:30px; left:30px; cursor:crosshair; z-index:10; pointer-events:auto; background:transparent !important; box-shadow:none !important;"></canvas>
      </div>

      <!-- Container pour l'édition de Modification (Fabric.js) -->
      <div id="modificationContainer" style="display:none; position:absolute; z-index:20; background:transparent !important; box-shadow:none !important;">
        <canvas id="modificationCanvas" style="background:transparent !important; box-shadow:none !important;"></canvas>
      </div>
      
      <!-- Montage Canvas Container -->
      <div id="montageCanvasContainer" style="display:none; width:100%; height:100%; position:absolute; top:0; left:0; align-items:flex-start; justify-content:center; overflow:auto; background:var(--studio-bg); padding: 40px; box-sizing: border-box;">
        <div id="montageGridContainer" style="box-shadow: 0 4px 12px rgba(0,0,0,0.1); border-radius:4px; background:white; display: grid; grid-template-columns: 30px 1fr; grid-template-rows: 30px 1fr; overflow: hidden; user-select: none; flex-shrink: 0;">
          <!-- Corner spacer -->
          <div style="width:30px; height:30px; background:#f0f2f5; border-bottom:1px solid #ccc; border-right:1px solid #ccc;"></div>
          <!-- X Ruler -->
          <canvas id="montageRulerX" style="height:30px; display:block; background:#f0f2f5; border-bottom:1px solid #ccc; max-width:none !important; max-height:none !important; object-fit:fill !important; box-shadow:none !important;"></canvas>
          <!-- Y Ruler -->
          <canvas id="montageRulerY" style="width:30px; display:block; background:#f0f2f5; border-right:1px solid #ccc; max-width:none !important; max-height:none !important; object-fit:fill !important; box-shadow:none !important;"></canvas>
          <!-- Canvas wrapper -->
          <div id="fabricCanvasWrapper" style="position:relative;">
            <canvas id="montageCanvas"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- Thumbnails Bar -->
    <div class="studio-thumbs" id="thumbsBar"></div>
  </main>

  <!-- === RIGHT PANEL === -->
  <aside class="studio-panel" id="studioPanel">
    <!-- Filters Panel -->
    <div id="panelFilters">
      <div class="panel-section">
        <div class="panel-section-title">Réglages d'image</div>
        <div class="panel-row">
          <div class="panel-label">Contraste <span class="panel-value" id="valContrast">0</span></div>
          <input type="range" class="panel-slider" id="sliderContrast" min="-100" max="100" value="0">
        </div>
        <div class="panel-row">
          <div class="panel-label">Luminosité <span class="panel-value" id="valBrightness">0</span></div>
          <input type="range" class="panel-slider" id="sliderBrightness" min="-100" max="100" value="0">
        </div>
        <div class="panel-row">
          <div class="panel-label">Gamma <span class="panel-value" id="valGamma">1.0</span></div>
          <input type="range" class="panel-slider" id="sliderGamma" min="0.1" max="3.0" value="1.0" step="0.1">
        </div>
        <div class="panel-row">
          <div class="panel-label">Saturation <span class="panel-value" id="valSaturation">0</span></div>
          <input type="range" class="panel-slider" id="sliderSaturation" min="-100" max="100" value="0">
        </div>
      </div>
      <div class="panel-section">
        <div class="panel-section-title">Bitmap</div>
        <label class="panel-checkbox">
          <input type="checkbox" id="chkBitmap"> Activer le mode Bitmap
        </label>
        <div id="bitmapOpts" style="display:none;margin-top:12px">
          <div class="panel-row">
            <div class="panel-label">Méthode</div>
            <select class="panel-select" id="selBitmapMethod">
              <option value="threshold">Seuil simple</option>
              <option value="dithering">Tramage (Floyd-Steinberg)</option>
            </select>
          </div>
          <div class="panel-row" id="thresholdRow">
            <div class="panel-label">Seuil <span class="panel-value" id="valThreshold">128</span></div>
            <input type="range" class="panel-slider" id="sliderThreshold" min="0" max="255" value="128">
          </div>
        </div>
      </div>
      <div class="panel-section" style="text-align:center">
        <button class="toolbar-btn" id="btnReset" style="width:100%;margin-bottom:8px"><i class="fa fa-undo"></i> Réinitialiser</button>
      </div>
    </div>

    <!-- Imposition Panel (hidden) -->
    <div id="panelImposition" style="display:none">
      <!-- Tabs -->
      <div style="display:flex;border-bottom:1px solid var(--studio-border);margin-bottom:0">
        <button class="imp-tab active" data-tab="brochure" style="flex:1;padding:10px 4px;border:none;background:transparent;font-size:11px;font-weight:600;cursor:pointer;color:var(--studio-primary);border-bottom:2px solid var(--studio-primary)">Brochure</button>
        <button class="imp-tab" data-tab="livre" style="flex:1;padding:10px 4px;border:none;background:transparent;font-size:11px;font-weight:600;cursor:pointer;color:var(--studio-text-muted);border-bottom:2px solid transparent">Livre</button>
        <button class="imp-tab" data-tab="tracts" style="flex:1;padding:10px 4px;border:none;background:transparent;font-size:11px;font-weight:600;cursor:pointer;color:var(--studio-text-muted);border-bottom:2px solid transparent">Tracts N-up</button>
      </div>

      <!-- == TAB BROCHURE == -->
      <div id="impTabBrochure" class="imp-tab-content" style="padding:0">
        <div class="panel-section">
          <div class="panel-section-title">Format & Poses</div>
          <div class="panel-row">
            <div class="panel-label">Sortie</div>
            <select class="panel-select" id="bro_output_format"><option value="A3">A3 (défaut)</option><option value="A4">A4</option></select>
          </div>
          <div class="panel-row">
            <div class="panel-label">N-up (poses)</div>
            <select class="panel-select" id="bro_n_up">
              <option value="2">2 pages / feuille</option>
              <option value="4">4 pages / feuille</option>
              <option value="8">8 pages / feuille</option>
            </select>
          </div>
        </div>

        <div class="panel-section">
          <div class="panel-section-title">Redimensionnement</div>
          <div class="panel-row">
            <label style="font-size:11px; cursor:pointer"><input type="radio" name="bro_resize_mode" value="percent" checked> Échelle %</label>
            <label style="font-size:11px; cursor:pointer; margin-left:10px"><input type="radio" name="bro_resize_mode" value="mm"> Taille cible</label>
          </div>
          <div id="bro_resize_percent_block">
            <div class="panel-row">
              <div class="panel-label">Échelle (%)</div>
              <input type="number" class="panel-select" id="bro_scale" value="100" min="10" max="400">
            </div>
          </div>
          <div id="bro_resize_mm_block" style="display:none">
            <div class="panel-row">
              <div class="panel-label">Largeur (mm)</div>
              <input type="number" class="panel-select" id="bro_target_w" placeholder="ex: 105" step="0.1">
            </div>
            <div class="panel-row">
              <div class="panel-label">Hauteur (mm)</div>
              <input type="number" class="panel-select" id="bro_target_h" placeholder="ex: 148" step="0.1">
            </div>
          </div>
        </div>

        <div class="panel-section">
          <div class="panel-section-title">Gouttières (mm)</div>
          <div class="panel-row">
            <div class="panel-label">Horiz. (X)</div>
            <input type="number" class="panel-select" id="bro_gutter_x" value="0" step="0.5">
          </div>
          <div class="panel-row">
            <div class="panel-label">Vert. (Y)</div>
            <input type="number" class="panel-select" id="bro_gutter_y" value="0" step="0.5">
          </div>
          <div class="panel-row">
            <div class="panel-label">Stratégie</div>
            <select class="panel-select" id="bro_gutter_strategy">
              <option value="reduce">Réduire échelle</option>
              <option value="crop">Rogner (Crop)</option>
            </select>
          </div>
        </div>

        <div class="panel-section">
          <div class="panel-section-title">Repères & Folios</div>
          <div class="panel-row">
            <label style="font-size:11px; cursor:pointer"><input type="checkbox" id="bro_crop_marks"> Traits de coupe</label>
          </div>
          <div id="bro_crop_settings" style="display:none; padding-left:10px; border-left:2px solid var(--studio-border); margin-top:5px">
            <div class="panel-row">
              <div class="panel-label">Style</div>
              <select class="panel-select" id="bro_crop_style" style="font-size:10px">
                <option value="standard">Standard</option>
                <option value="spreads" selected>Planches (spreads)</option>
                <option value="booklet">Livret</option>
              </select>
            </div>
            <div class="panel-row">
              <div class="panel-label">Long. (mm)</div>
              <input type="number" class="panel-select" id="bro_crop_len" value="5" min="1" style="width:100px">
            </div>
          </div>
          
          <div class="panel-row" style="margin-top:10px">
            <label style="font-size:11px; cursor:pointer"><input type="checkbox" id="bro_page_nums"> Numéros de pages</label>
          </div>
          <div id="bro_folio_settings" style="display:none; padding-left:10px; border-left:2px solid var(--studio-border); margin-top:5px; margin-bottom:10px">
             <div class="panel-row" id="bro_folio_position_row" style="margin-top:5px; margin-bottom:5px;">
               <div class="panel-label">Position auto</div>
               <select class="panel-select" id="bro_folio_position" style="font-size:10px">
                 <option value="margins" selected>Marges (Extérieur)</option>
                 <option value="gutters">Tranches (Intérieur)</option>
               </select>
             </div>
             <div class="panel-row" style="margin-top:5px; margin-bottom:5px;">
               <label style="font-size:11px; cursor:pointer"><input type="checkbox" id="bro_page_nums_manual"> Décalage manuel</label>
             </div>
             <div id="bro_folio_manual_settings" style="display:none;">
                 <div class="panel-row">
                   <div class="panel-label">Décalage X (mm)</div>
                    <input type="number" class="panel-select" id="bro_folio_x" value="0" step="0.5" style="width:100px">
                 </div>
                 <div style="font-size:9px; color:var(--studio-text-muted); margin-bottom:6px; margin-top:-2px">
                   Positif = vers la droite, Négatif = vers la gauche
                 </div>
                 <div class="panel-row">
                   <div class="panel-label">Décalage Y (mm)</div>
                    <input type="number" class="panel-select" id="bro_folio_y" value="-2" step="0.5" style="width:100px">
                 </div>
                 <div style="font-size:9px; color:var(--studio-text-muted); margin-bottom:6px; margin-top:-2px">
                   Positif = vers le bas, Négatif = vers le haut
                 </div>
             </div>
          </div>

          <div class="panel-row" style="margin-top:10px">
            <label style="font-size:11px; cursor:pointer"><input type="checkbox" id="bro_tumble"> Tête-bêche</label>
          </div>
          

        </div>
      </div>

      <!-- == TAB LIVRE == -->
      <div id="impTabLivre" class="imp-tab-content" style="display:none;padding:0">
        <div class="panel-section">
          <div class="panel-section-title">Format & Poses</div>
          <div class="panel-row">
            <div class="panel-label">Sortie</div>
            <select class="panel-select" id="liv_output_format"><option value="A3">A3</option><option value="A4">A4</option></select>
          </div>
          <div class="panel-row">
            <div class="panel-label">N-up</div>
            <select class="panel-select" id="liv_n_up">
              <option value="2">2 poses</option>
              <option value="4">4 poses</option>
              <option value="8">8 poses</option>
            </select>
          </div>
        </div>

        <div class="panel-section">
          <div class="panel-section-title">Redimensionnement</div>
          <div class="panel-row">
            <label style="font-size:11px; cursor:pointer"><input type="radio" name="liv_resize_mode" value="percent" checked> Échelle %</label>
            <label style="font-size:11px; cursor:pointer; margin-left:10px"><input type="radio" name="liv_resize_mode" value="mm"> Taille cible</label>
          </div>
          <div id="liv_resize_percent_block">
            <div class="panel-row">
              <div class="panel-label">Échelle (%)</div>
              <input type="number" class="panel-select" id="liv_scale" value="100">
            </div>
          </div>
          <div id="liv_resize_mm_block" style="display:none">
            <div class="panel-row">
              <div class="panel-label">Larg (mm)</div>
              <input type="number" class="panel-select" id="liv_target_w" placeholder="mm">
            </div>
            <div class="panel-row">
              <div class="panel-label">Haut (mm)</div>
              <input type="number" class="panel-select" id="liv_target_h" placeholder="mm">
            </div>
          </div>
        </div>

        <div class="panel-section">
          <div class="panel-section-title">Gouttières (mm)</div>
          <div class="panel-row">
            <div style="width:30%">
              <div class="panel-label" style="font-size:10px; margin-bottom:2px">Horizontale X</div>
              <input type="number" class="panel-select" id="liv_gutter_x" value="0" step="0.5" style="width:100%" placeholder="0">
            </div>
            <div style="width:30%">
              <div class="panel-label" style="font-size:10px; margin-bottom:2px">Verticale Y</div>
              <input type="number" class="panel-select" id="liv_gutter_y" value="0" step="0.5" style="width:100%" placeholder="0">
            </div>
            <div style="width:35%">
              <div class="panel-label" style="font-size:10px; margin-bottom:2px">Stratégie</div>
              <select class="panel-select" id="liv_gutter_strategy" style="width:100%">
                <option value="reduce">Réduire échelle</option>
                <option value="crop">Rogner (Crop)</option>
              </select>
            </div>
          </div>
        </div>

        <div class="panel-section">
          <div class="panel-section-title">Options de repères & Folio</div>
          <div class="panel-row">
            <label style="font-size:11px; cursor:pointer"><input type="checkbox" id="liv_crop_marks"> Traits de coupe</label>
          </div>
          <div id="liv_crop_settings" style="display:none; padding-left:10px; border-left:2px solid var(--studio-border); margin-top:5px; margin-bottom:10px">
            <div class="panel-row">
              <div class="panel-label">Style de repères</div>
              <select class="panel-select" id="liv_crop_style" style="font-size:10px">
                <option value="standard" selected>Standard (Autour de chaque pose)</option>
                <option value="spreads">Planches (Autour de chaque paire)</option>
                <option value="booklet">Livret (Coins extérieurs)</option>
              </select>
            </div>
            <div class="panel-row">
              <div class="panel-label">Longueur (mm)</div>
              <input type="number" class="panel-select" id="liv_crop_len" value="5" min="1" style="width:100px">
            </div>
          </div>
          
          <div class="panel-row">
            <label style="font-size:11px; cursor:pointer"><input type="checkbox" id="liv_page_nums"> Numéros de pages</label>
          </div>
          <div id="liv_folio_settings" style="display:none; padding-left:10px; border-left:2px solid var(--studio-border); margin-top:5px; margin-bottom:10px">
             <div class="panel-row" id="liv_folio_position_row" style="margin-top:5px; margin-bottom:5px;">
               <div class="panel-label">Position auto</div>
               <select class="panel-select" id="liv_folio_position" style="font-size:10px">
                 <option value="margins" selected>Marges (Extérieur)</option>
                 <option value="gutters">Tranches (Intérieur)</option>
               </select>
             </div>
             <div class="panel-row" style="margin-top:5px; margin-bottom:5px;">
               <label style="font-size:11px; cursor:pointer"><input type="checkbox" id="liv_page_nums_manual"> Décalage manuel</label>
             </div>
             <div id="liv_folio_manual_settings" style="display:none;">
                 <div class="panel-row">
                   <div class="panel-label">Décalage X (mm)</div>
                    <input type="number" class="panel-select" id="liv_folio_x" value="0" step="0.5" style="width:100px">
                 </div>
                 <div style="font-size:9px; color:var(--studio-text-muted); margin-bottom:6px; margin-top:-2px">
                   Positif = vers la droite, Négatif = vers la gauche
                 </div>
                 <div class="panel-row">
                   <div class="panel-label">Décalage Y (mm)</div>
                    <input type="number" class="panel-select" id="liv_folio_y" value="-2" step="0.5" style="width:100px">
                 </div>
                 <div style="font-size:9px; color:var(--studio-text-muted); margin-bottom:6px; margin-top:-2px">
                   Positif = vers le bas, Négatif = vers le haut
                 </div>
             </div>
          </div>
          
          <div class="panel-row" style="margin-top:10px">
            <label style="font-size:11px; cursor:pointer"><input type="checkbox" id="liv_tete_beche"> Tête-bêche</label>
          </div>
          <div class="panel-row" style="margin-top:10px">
            <label style="font-size:11px; cursor:pointer"><input type="checkbox" id="liv_collation_marks"> Témoins d'assemblage</label>
          </div>
          

        </div>
      </div>

      <!-- == TAB TRACTS == -->
      <div id="impTabTracts" class="imp-tab-content" style="display:none;padding:0">
        <div class="panel-section">
          <div class="panel-section-title">Format</div>
          <div class="panel-row">
            <div class="panel-label">Sortie</div>
            <select class="panel-select" id="tra_output_format"><option value="A3">A3</option><option value="A4">A4</option></select>
          </div>
          <div class="panel-row">
            <div class="panel-label">Format source</div>
            <select class="panel-select" id="tra_manual_format">
              <option value="auto">Détection auto</option>
              <option value="A4">A4 → 2 sur A3</option>
              <option value="A5">A5 → 4 sur A3</option>
              <option value="A6">A6 → 8 sur A3</option>
            </select>
          </div>
          <div class="panel-row">
            <div class="panel-label">Orientation</div>
            <select class="panel-select" id="tra_orientation">
              <option value="auto">Auto</option>
              <option value="portrait">Portrait</option>
              <option value="landscape">Paysage</option>
            </select>
          </div>
        </div>
        <div class="panel-section">
          <div class="panel-section-title">Options</div>
          <div class="panel-row"><label class="panel-checkbox"><input type="checkbox" id="tra_crop_marks"> Traits de coupe</label></div>
          <div class="panel-row"><label class="panel-checkbox"><input type="checkbox" id="tra_keep_size"> Garder taille originale</label></div>
          <div class="panel-row"><label class="panel-checkbox"><input type="checkbox" id="tra_force_resize"> Forcer redim.</label></div>
          <div class="panel-row">
            <div class="panel-label">Mode duplex</div>
            <select class="panel-select" id="tra_duplex_mode">
              <option value="none">Non (simple)</option>
              <option value="manuel">Duplex manuel</option>
            </select>
          </div>
        </div>
      </div>

      <div style="padding:12px">
        <button class="toolbar-btn primary" id="btnApplyImposition" style="width:100%">
          <i class="fa fa-magic"></i> Appliquer l'imposition
        </button>
      </div>
    </div>

    <!-- Geometry Panel (hidden) -->
    <div id="panelGeometry" style="display:none">
      <div class="panel-section">
        <div class="panel-section-title">Transformation</div>
        <div class="panel-row">
          <button class="toolbar-btn" id="btnRotateLeft" style="width:48%"><i class="fa fa-rotate-left"></i> -90°</button>
          <button class="toolbar-btn" id="btnRotateRight" style="width:48%;float:right"><i class="fa fa-rotate-right"></i> +90°</button>
        </div>
        <div class="panel-row" style="margin-top:12px">
          <button class="toolbar-btn" id="btnFlipH" style="width:48%"><i class="fa fa-arrows-h"></i> Flip H</button>
          <button class="toolbar-btn" id="btnFlipV" style="width:48%;float:right"><i class="fa fa-arrows-v"></i> Flip V</button>
        </div>
        <div class="panel-row" style="margin-top:12px; border-top: 1px solid var(--studio-border); padding-top: 12px;">
          <div class="panel-label">Redressement (Deskew) <span class="panel-value" id="valDeskew">0°</span></div>
          <input type="range" class="panel-slider" id="sliderDeskew" min="-15" max="15" step="0.1" value="0">
        </div>
        <div class="panel-row" style="margin-top:4px">
          <button class="toolbar-btn primary" id="btnApplyDeskew" style="width:100%"><i class="fa fa-check"></i> Valider l'angle</button>
        </div>
        <div class="panel-row" style="margin-top:12px; font-size:11px; border-top: 1px solid var(--studio-border); padding-top: 12px;">
          <label style="cursor:pointer;display:flex;align-items:center;gap:6px">
             <input type="checkbox" id="chkGeomApplyAll"> Appliquer à toutes les pages
          </label>
        </div>
      </div>
      <div class="panel-section">
        <div class="panel-section-title">Redimensionner</div>
        <div class="panel-row">
          <select class="panel-select" id="selResizeFormat">
            <option value="A4">A4 (210×297mm)</option>
            <option value="A3">A3 (297×420mm)</option>
            <option value="A5">A5 (148×210mm)</option>
          </select>
        </div>
        <div class="panel-row" style="margin-top:8px">
          <button class="toolbar-btn primary" id="btnApplyResize" style="width:100%"><i class="fa fa-expand"></i> Redimensionner</button>
        </div>
      </div>
      <!-- Section Crop -->
      <div class="panel-section">
        <div class="panel-section-title">Rogner (Crop)</div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:10px;">
          <div>
            <div class="panel-label" style="font-size:11px;">Haut (mm)</div>
            <input type="number" class="panel-select" id="cropTop" value="0" min="0" step="0.5" style="padding:6px 8px;">
          </div>
          <div>
            <div class="panel-label" style="font-size:11px;">Bas (mm)</div>
            <input type="number" class="panel-select" id="cropBottom" value="0" min="0" step="0.5" style="padding:6px 8px;">
          </div>
          <div>
            <div class="panel-label" style="font-size:11px;">Gauche (mm)</div>
            <input type="number" class="panel-select" id="cropLeft" value="0" min="0" step="0.5" style="padding:6px 8px;">
          </div>
          <div>
            <div class="panel-label" style="font-size:11px;">Droite (mm)</div>
            <input type="number" class="panel-select" id="cropRight" value="0" min="0" step="0.5" style="padding:6px 8px;">
          </div>
        </div>
        <div id="cropSizeInfo" style="font-size:11px; color:var(--studio-text-muted); text-align:center; margin-bottom:10px; font-weight:500;">—</div>
        <button class="toolbar-btn primary" id="btnActivateCrop" style="width:100%; margin-bottom:8px;"><i class="fa fa-crop"></i> Activer l'aperçu crop</button>
        <button class="toolbar-btn" id="btnResetCrop" style="width:100%; margin-bottom:8px;"><i class="fa fa-undo"></i> Réinitialiser</button>
        <button class="toolbar-btn primary" id="btnApplyCropExport" style="width:100%; background:#10b981; border-color:#10b981;"><i class="fa fa-scissors"></i> Appliquer & Exporter</button>
      </div>
    </div>

    <!-- Panel Montage Libre -->
    <div id="panelMontage" style="display:none">
      <div class="panel-section">
        <div class="panel-section-title">Format du Canva</div>
        <div class="panel-row">
          <div class="panel-label">Format</div>
          <select class="panel-select" id="montageFormat">
            <option value="A3">A3</option>
            <option value="A4" selected>A4</option>
            <option value="A5">A5</option>
          </select>
        </div>
        <div class="panel-row">
          <div class="panel-label">Orientation</div>
          <select class="panel-select" id="montageOrientation">
            <option value="portrait">Portrait</option>
            <option value="landscape">Paysage</option>
          </select>
        </div>
      </div>
      
      <div class="panel-section">
        <div class="panel-section-title">Planches</div>
        <div id="montagePlanchesList" style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:10px;">
          <!-- Planches boutons -->
        </div>
        <button class="panel-btn" id="btnAddPlanche"><i class="fa fa-plus"></i> Nouvelle Planche</button>
      </div>

      <div class="panel-section">
        <div class="panel-section-title">Fichiers Sources</div>
        <input type="file" id="montageUploadPdf" accept=".pdf,.jpg,.jpeg,.png,.webp" multiple style="display:none">
        <button class="panel-btn" id="btnMontageUpload"><i class="fa fa-upload"></i> Importer un PDF ou Image</button>
        <div id="montageSourceThumbs" style="margin-top:10px; display:grid; grid-template-columns: 1fr 1fr; gap:5px; max-height:300px; overflow-y:auto; padding-right:4px;">
          <!-- Thumbnails of uploaded PDFs -->
        </div>
      </div>

      <div class="panel-section" style="text-align:center">
        <button class="panel-btn" id="btnGenerateMontage" style="background:var(--studio-primary);color:white;width:100%;font-weight:600;margin-top:10px;">
          <i class="fa fa-magic"></i> Générer le Montage PDF
        </button>
      </div>
    </div>

    <!-- Pages Panel (hidden) -->
    <div id="panelPages" style="display:none">
      <div class="panel-section">
        <div class="panel-section-title">PDF vers Images</div>
        <div class="panel-row">
          <div class="panel-label">Qualité (DPI) <span class="panel-value" id="valDpi">150</span></div>
          <input type="range" class="panel-slider" id="sliderDpi" min="72" max="300" value="150" step="1">
        </div>
        <div class="panel-row" style="margin-top:12px">
          <button class="toolbar-btn" id="btnPdfToImg" style="width:100%"><i class="fa fa-file-image-o"></i> Extraire en PNG</button>
        </div>
      </div>


      <div class="panel-section">
        <div class="panel-section-title">Organiser (Glisser-Déposer)</div>
        <p style="font-size:12px;color:#6b7280;margin-bottom:8px;">Utilisez la barre du bas pour réorganiser les pages.</p>
        <div class="panel-row">
          <div class="panel-label">Position d'insertion</div>
          <select class="panel-select" id="selOrgBlankPos">
            <option value="end">À la fin</option>
            <option value="start">Au début</option>
            <option value="before">Avant la page active</option>
            <option value="after">Après la page active</option>
          </select>
        </div>
        <div class="panel-row" style="margin-top:8px">
          <input type="file" id="orgAddPdfInput" accept=".pdf" style="display:none">
          <button class="toolbar-btn" id="btnOrgAddPdf" style="width:100%"><i class="fa fa-file-pdf-o"></i> Ajouter un PDF</button>
        </div>
        <div class="panel-row" style="margin-top:8px">
          <button class="toolbar-btn" id="btnOrgAddBlank" style="width:100%"><i class="fa fa-plus"></i> Insérer page blanche</button>
        </div>
        <div class="panel-row" style="margin-top:8px">
          <button class="toolbar-btn" id="btnOrgReverse" style="width:100%"><i class="fa fa-sort-numeric-desc"></i> Inverser l'ordre des pages</button>
        </div>
        <div class="panel-row" style="margin-top:12px">
          <button class="toolbar-btn primary" id="btnApplyOrg" style="width:100%"><i class="fa fa-magic"></i> Appliquer l'ordre</button>
        </div>
      </div>

      <div class="panel-section">
        <div class="panel-section-title">Désimposer</div>
        <div class="panel-row">
          <div class="panel-label">Mode</div>
          <select class="panel-select" id="selUnimposeMode">
            <option value="booklet">Livret classique</option>
            <option value="doubles">Doubles pages (couv + doubles)</option>
            <option value="sequential">Coupe Séquentielle (1g, 1d, 2g, 2d...)</option>
          </select>
        </div>
        <div class="panel-row" style="margin-top:12px">
          <button class="toolbar-btn" id="btnApplyUnimpose" style="width:100%"><i class="fa fa-scissors"></i> Désimposer</button>
        </div>
      </div>
    </div>

    <!-- Riso Panel (hidden) -->
    <div id="panelRiso" style="display:none">
      <div class="panel-section">
        <div class="panel-section-title">Mode de Séparation</div>
        <div class="panel-row">
          <select class="panel-select" id="selRisoMode">
            <option value="AUTO_BICHROMIE">Bichromie Auto (Détection 2 couleurs)</option>
            <option value="RGB">Soustraction RGB (3 couches)</option>
            <option value="CMYK">Soustraction CMJN (4 couches)</option>
            <option value="2COLOR">Séparation Luminosité (Sombre/Clair)</option>
            <option value="PIPETTE">Pipette (Couleur précise)</option>
          </select>
        </div>
      </div>
      
      <div class="panel-section">
        <div class="panel-section-title">Effets Communs</div>
        <div class="panel-row">
          <div class="panel-label">Postériser (Niv.) <span class="panel-value" id="valRisoLevels">4</span></div>
          <input type="range" class="panel-slider" id="sliderRisoLevels" min="2" max="10" value="4">
        </div>
        <div class="panel-row" style="margin-top:4px">
          <button class="toolbar-btn" id="btnRisoPosterize" style="width:100%"><i class="fa fa-th"></i> Appliquer Postérisation</button>
        </div>
        
        <div class="panel-row" style="margin-top:12px">
          <div class="panel-label">Taille Trame <span class="panel-value" id="valRisoHalftone">3</span></div>
          <input type="range" class="panel-slider" id="sliderRisoHalftone" min="1" max="10" value="3">
        </div>
        <div class="panel-row" style="margin-top:4px">
          <button class="toolbar-btn" id="btnRisoHalftone" style="width:100%"><i class="fa fa-th-large"></i> Appliquer Trame</button>
        </div>
        
        <div class="panel-row" style="margin-top:12px">
          <button class="toolbar-btn" id="btnRisoReset" style="width:100%;color:#ef4444"><i class="fa fa-undo"></i> Réinitialiser l'image</button>
        </div>
      </div>

      <div class="panel-section" id="risoChannelsSection">
        <div class="panel-section-title">Couches Riso</div>
        <div id="risoChannelsList">
          <!-- Dynamically populated via JS -->
        </div>
        <div class="panel-row" style="margin-top:12px; gap:8px;">
          <button class="panel-btn" id="btnRisoExportZip" style="flex:1">
            <i class="fa fa-file-archive"></i> Exporter le ZIP
          </button>
          <button class="panel-btn primary" id="btnRisoExportPdf" style="flex:1">
            <i class="fa fa-file-pdf"></i> Exporter PDF Riso
          </button>
        </div>
      </div>
    </div>
    
    <!-- Nouveau Panneau: OCR & Nettoyage de Scan -->
    <div id="panelOcr" style="display:none">
      <div class="panel-section">
        <div class="panel-section-title">Langue du document</div>
        <div class="panel-row">
          <select class="panel-select" id="selOcrLang">
            <option value="fra">Français</option>
            <option value="eng">Anglais</option>
          </select>
        </div>
      </div>
      
      <div class="panel-section">
        <div class="panel-section-title">Type de Traitement</div>
        <div class="panel-row">
          <select class="panel-select" id="selOcrType">
            <option value="skip_text">OCR Classique (Ignore le texte existant)</option>
            <option value="force_ocr">Forcer l'OCR (Rastérise d'abord)</option>
          </select>
        </div>
      </div>

      <div class="panel-section">
        <div class="panel-section-title">Nettoyage (Clean & Deskew)</div>
        <label style="display:flex;align-items:center;gap:8px;font-size:12px;color:#374151;margin-bottom:8px;cursor:pointer">
          <input type="checkbox" id="chkOcrDeskew" checked>
          Redresser la page (Deskew)
        </label>
        <label style="display:flex;align-items:center;gap:8px;font-size:12px;color:#374151;margin-bottom:8px;cursor:pointer">
          <input type="checkbox" id="chkOcrClean" checked>
          Nettoyer les parasites (Despeckle)
        </label>
        <label style="display:flex;align-items:center;gap:8px;font-size:12px;color:#374151;margin-bottom:12px;cursor:pointer">
          <input type="checkbox" id="chkOcrOptimize">
          Optimiser la taille du fichier
        </label>
        <div style="height:1px;background:#e2e5ea;margin:12px 0;"></div>
        <div class="panel-section-title">Format de sortie</div>
        <div class="panel-row" style="margin-bottom:12px;">
          <select class="panel-select" id="selOcrOutputFormat">
            <option value="pdf">PDF ocrisé</option>
            <option value="docx_linear">DOCX linéaire (Texte pur avec paragraphes, sans mise en page)</option>
            <option value="docx_ia">DOCX IA Docling (Structure native Word reconstituée)</option>
            <option value="docx_layout">DOCX (Tente de garder la mise en page originale)</option>
          </select>
        </div>
        <div class="panel-row">
          <button class="panel-btn primary" id="btnOcrRun" style="width:100%">
            <i class="fa fa-magic"></i> Lancer le traitement OCR
          </button>
        </div>
      </div>
    </div>

    <!-- Nouveau Panneau: Modification PDF -->
    <div id="panelModification" style="display:none">
      <div class="panel-section">
        <div class="panel-section-title">Outils de modification</div>
        <div class="panel-row" style="display: flex; flex-direction: column; gap: 5px;">
          <button class="panel-btn modif-tool-btn active" data-tool="none"><i class="fa fa-mouse-pointer" style="width: 20px;"></i> Sélectionner</button>
          <button class="panel-btn modif-tool-btn" data-tool="redact_text"><i class="fa fa-font" style="width: 20px;"></i> Texte & Carré blanc</button>
          <button class="panel-btn modif-tool-btn" data-tool="page_number"><i class="fa fa-hashtag" style="width: 20px;"></i> Numérotation</button>
          <button class="panel-btn modif-tool-btn" data-tool="strikeout"><i class="fa fa-strikethrough" style="width: 20px;"></i> Biffer & Surligner</button>
        </div>
      </div>
      
      <!-- Outil : Carré blanc + Texte -->
      <div id="modifToolRedact" class="panel-section" style="display:none">
        <div class="panel-section-title">Texte</div>
        <div class="panel-row">
          <input type="text" class="panel-select" id="modifRedactText" placeholder="Texte de remplacement..." style="width:100%">
        </div>
        <div class="panel-row" style="margin-top:8px; align-items:center;">
          <div class="panel-label">Police</div>
          <select class="panel-select" id="modifRedactFont" style="flex:1">
            <option value="helvetica">Helvetica (Sans serif)</option>
            <option value="times">Times (Serif)</option>
            <option value="courier">Courier (Monospace)</option>
          </select>
          <button class="panel-btn" id="btnIdentifyFont" title="Reconnaître la police depuis l'image" style="padding:4px 8px; margin-left:4px; color: var(--studio-primary);"><i class="fa fa-magic"></i></button>
          <button class="panel-btn btn-upload-font" title="Importer une police" style="padding:4px 8px; margin-left:4px"><i class="fa fa-upload"></i></button>
        </div>
        
        <!-- Modal inline pour les résultats de la reconnaissance de police -->
        <div id="fontRecognitionResults" style="display:none; margin-top:8px; background:white; border:1px solid var(--studio-border); border-radius:4px; padding:8px;">
          <div style="font-size:11px; font-weight:bold; margin-bottom:4px; color:var(--studio-primary);">Polices détectées :</div>
          <div id="fontRecognitionList" style="display:flex; flex-direction:column; gap:4px;"></div>
          <button class="panel-btn" id="btnCancelFontRec" style="width:100%; margin-top:6px; font-size:10px;">Fermer</button>
        </div>
        <div class="panel-row" style="margin-top:8px">
          <div class="panel-label">Taille</div>
          <input type="number" class="panel-select" id="modifRedactSize" value="12" min="6" max="72">
        </div>
        <div class="panel-row" style="margin-top:8px">
          <label style="font-size:11px; cursor:pointer"><input type="checkbox" id="modifRedactBg" checked> Avec fond blanc</label>
        </div>
        <p style="font-size:10px; color:#6b7280; margin-top:8px">Cliquez-glissez sur le PDF pour dessiner la zone.</p>
      </div>

      <!-- Outil : Numéro de page -->
      <div id="modifToolPageNum" class="panel-section" style="display:none">
        <div class="panel-section-title">Paramètres de Numérotation</div>
        <div class="panel-row">
          <div class="panel-label">Format</div>
          <input type="text" class="panel-select" id="modifPageNumFormat" value="{p}" placeholder="{p} pour page courante, {t} pour total">
        </div>
        <p style="font-size:10px; color:#6b7280; margin-top:4px; margin-bottom:8px">Ex: <b>Page {p} sur {t}</b> affichera "Page 1 sur 12"</p>
        <div class="panel-row" style="margin-top:8px">
          <div class="panel-label">Position</div>
          <select class="panel-select" id="modifPageNumPosition">
            <option value="bottom_center">Bas Centre</option>
            <option value="bottom_left">Bas Gauche</option>
            <option value="bottom_right">Bas Droite</option>
            <option value="top_center">Haut Centre</option>
            <option value="top_left">Haut Gauche</option>
            <option value="top_right">Haut Droite</option>
          </select>
        </div>
        <div class="panel-row" style="margin-top:8px">
          <div class="panel-label">Marge (mm)</div>
          <input type="number" class="panel-select" id="modifPageNumMargin" value="10" min="0" max="100">
        </div>
        <div class="panel-row" style="margin-top:8px; align-items:center;">
          <div class="panel-label">Police</div>
          <select class="panel-select" id="modifPageNumFont" style="flex:1">
            <option value="helvetica">Helvetica</option>
            <option value="times">Times</option>
          </select>
          <button class="panel-btn btn-upload-font" title="Importer une police" style="padding:4px 8px; margin-left:4px"><i class="fa fa-upload"></i></button>
        </div>
        <input type="file" id="customFontUpload" accept=".ttf,.otf" style="display:none">
        <div class="panel-row" style="margin-top:8px">
          <div class="panel-label">Taille</div>
          <input type="number" class="panel-select" id="modifPageNumSize" value="12" min="6" max="72">
        </div>
        <div class="panel-row" style="margin-top:8px">
          <div class="panel-label">Pages</div>
          <input type="number" class="panel-select" id="modifPageNumStart" placeholder="Début (1)" style="width:48%">
          <input type="number" class="panel-select" id="modifPageNumEnd" placeholder="Fin" style="width:48%; margin-left:4%">
        </div>
        <div class="panel-row" style="margin-top:8px">
          <div class="panel-label">Débuter à</div>
          <input type="number" class="panel-select" id="modifPageNumFirstVal" value="1" placeholder="Numéro de départ" style="width:100%">
        </div>
        <p style="font-size:10px; color:#6b7280; margin-top:8px">La numérotation sera placée automatiquement selon la position choisie.</p>
      </div>

      <!-- Outil : Biffer -->
      <div id="modifToolStrikeout" class="panel-section" style="display:none">
        <div class="panel-section-title">Couleur de biffure</div>
        <div class="panel-row" style="display: flex; flex-direction: row; align-items: stretch; gap: 5px;">
          <input type="color" class="panel-select" id="modifStrikeColor" value="#000000" style="padding: 0; height: 32px; cursor: pointer; flex: 1; border-radius: 4px; border: 1px solid var(--studio-border);">
          <button class="panel-btn" id="btnEyeDropper" title="Pipette" style="padding: 0; height: 32px; width: 40px; flex-shrink: 0; display: flex; align-items: center; justify-content: center;"><i class="fa fa-eyedropper"></i></button>
        </div>
        <p style="font-size:10px; color:#6b7280; margin-top:8px">Cliquez-glissez sur le PDF pour biffer la zone.</p>
      </div>

      <div class="panel-section">
        <div class="panel-section-title">Portée</div>
        <div class="panel-row">
          <select class="panel-select" id="selModifScope">
            <option value="current">Cette page uniquement</option>
            <option value="all">Toutes les pages</option>
            <option value="even">Pages paires</option>
            <option value="odd">Pages impaires</option>
          </select>
        </div>
        <div class="panel-row" style="margin-top:12px; gap:8px">
          <button class="panel-btn" id="btnModifClear" style="flex:1"><i class="fa fa-eraser"></i> Effacer</button>
          <button class="panel-btn primary" id="btnModifApply" style="flex:1"><i class="fa fa-check"></i> Appliquer au PDF</button>
        </div>
      </div>
    </div>

    <!-- Nouveau Panneau: Métadonnées -->
    <div id="panelMetadata" style="display:none">
      <div class="panel-section">
        <div class="panel-section-title">Éditer les Métadonnées</div>
        
        <div class="panel-row">
          <div class="panel-label">Titre</div>
          <input type="text" class="panel-select" id="metaTitle" style="width:100%">
        </div>
        <div class="panel-row" style="margin-top:8px">
          <div class="panel-label">Auteur</div>
          <input type="text" class="panel-select" id="metaAuthor" style="width:100%">
        </div>
        <div class="panel-row" style="margin-top:8px">
          <div class="panel-label">Sujet</div>
          <input type="text" class="panel-select" id="metaSubject" style="width:100%">
        </div>
        <div class="panel-row" style="margin-top:8px">
          <div class="panel-label">Mots-clés</div>
          <input type="text" class="panel-select" id="metaKeywords" style="width:100%">
        </div>
        <div class="panel-row" style="margin-top:8px">
          <div class="panel-label">Créateur</div>
          <input type="text" class="panel-select" id="metaCreator" style="width:100%">
        </div>
        <div class="panel-row" style="margin-top:8px">
          <div class="panel-label">Producteur</div>
          <input type="text" class="panel-select" id="metaProducer" style="width:100%">
        </div>
        <div class="panel-row" style="margin-top:8px">
          <div class="panel-label" title="Format: YYYY:MM:DD HH:MM:SS">Création</div>
          <input type="text" class="panel-select" id="metaCreationDate" placeholder="YYYY:MM:DD HH:MM:SS" style="width:100%">
        </div>
        <div class="panel-row" style="margin-top:8px">
          <div class="panel-label" title="Format: YYYY:MM:DD HH:MM:SS">Modification</div>
          <input type="text" class="panel-select" id="metaModDate" placeholder="YYYY:MM:DD HH:MM:SS" style="width:100%">
        </div>
        
        <div class="panel-row" style="margin-top:16px; gap:8px; display:flex">
          <button class="panel-btn primary" id="btnApplyMetadata" style="flex:1"><i class="fa fa-save"></i> Appliquer</button>
          <button class="panel-btn" id="btnClearMetadata" style="flex:1" title="Effacer toutes les métadonnées de ce fichier"><i class="fa fa-trash"></i> Effacer tout</button>
        </div>
        <p style="font-size:10px; color:#6b7280; margin-top:8px">L'enregistrement de métadonnées s'effectue sans perturber le contenu du PDF d'origine.</p>
        
        <div class="panel-section-title" style="margin-top: 24px;">Toutes les informations</div>
        <div style="background: #f8fafc; border: 1px solid var(--studio-border); border-radius: 4px; padding: 8px; max-height: 300px; overflow-y: auto;">
          <pre id="metaRawInfo" style="font-size: 10px; color: #475569; margin: 0; white-space: pre-wrap; word-break: break-all;">Chargement des informations...</pre>
        </div>
      </div>
    </div>

  </aside>
</div>

<!-- Spinner Overlay -->
<div id="studioSpinner" style="display:none;position:fixed;inset:0;background:rgba(255,255,255,0.75);z-index:9999;display:none;align-items:center;justify-content:center;flex-direction:column;gap:12px">
  <div style="width:48px;height:48px;border:4px solid #e2e5ea;border-top-color:#4f6ef7;border-radius:50%;animation:spin 0.8s linear infinite"></div>
  <div style="font-family:Inter,sans-serif;font-size:14px;font-weight:500;color:#1a1d23" id="spinnerMsg">Traitement en cours...</div>
</div>
<!-- Toast -->
<div id="studioToast" style="display:none;position:fixed;bottom:24px;right:24px;z-index:10000;background:#fff;border:1px solid #e2e5ea;border-radius:12px;padding:16px 20px;box-shadow:0 4px 20px rgba(0,0,0,0.12);font-family:Inter,sans-serif;font-size:13px;max-width:340px"></div>
<style>@keyframes spin{to{transform:rotate(360deg)}}</style>

<!-- Result Modal -->
<div id="resultModal" style="display:none;position:fixed;inset:0;z-index:10001;background:rgba(10,12,20,0.65);backdrop-filter:blur(4px);align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,0.3);width:400px;max-width:90vw;overflow:hidden;display:flex;flex-direction:column;animation:popIn 0.3s ease-out">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #e2e5ea">
      <div style="font-family:Inter,sans-serif;font-weight:700;font-size:15px;color:#10b981">
        <i class="fa fa-check-circle" style="margin-right:8px"></i>Fichier prêt !
      </div>
      <button onclick="document.getElementById('resultModal').style.display='none'" style="border:none;background:transparent;cursor:pointer;font-size:20px;color:#6b7280;line-height:1">×</button>
    </div>
    <div style="padding:24px 20px;display:flex;flex-direction:column;gap:12px;align-items:center;text-align:center">
      <p style="margin:0;font-family:Inter,sans-serif;font-size:14px;color:#374151">Le traitement de votre fichier s'est terminé avec succès.</p>
      <div style="font-family:Inter,sans-serif;font-weight:600;font-size:13px;color:#6b7280;word-break:break-all" id="resultModalFilename"></div>
      
      <div style="display:flex;gap:12px;margin-top:8px;width:100%">
        <a id="resultModalDownloadBtn" href="#" style="flex:1;padding:12px;border-radius:8px;background:linear-gradient(135deg,#4f6ef7,#6f42c1);color:#fff;text-decoration:none;font-family:Inter,sans-serif;font-size:13px;font-weight:600;display:flex;align-items:center;justify-content:center;gap:8px">
          <i class="fa fa-download"></i> Télécharger
        </a>
        <button id="resultModalReopenBtn" style="flex:1;padding:12px;border:none;border-radius:8px;background:linear-gradient(135deg,#10b981,#059669);color:#fff;cursor:pointer;font-family:Inter,sans-serif;font-size:13px;font-weight:600;display:flex;align-items:center;justify-content:center;gap:8px">
          <i class="fa fa-folder-open"></i> Rouvrir
        </button>
      </div>
    </div>
  </div>
</div>
<style>
@keyframes popIn { 0% { transform: scale(0.9); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
</style>

<!-- Preview Modal Imposition -->
<div id="impPreviewModal" style="display:none;position:fixed;inset:0;z-index:10001;background:rgba(10,12,20,0.65);backdrop-filter:blur(4px);align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,0.3);max-width:720px;width:90vw;max-height:90vh;overflow:hidden;display:flex;flex-direction:column">
    <!-- Header -->
    <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #e2e5ea">
      <div style="font-family:Inter,sans-serif;font-weight:700;font-size:15px;color:#1a1d23">
        <i class="fa fa-eye" style="color:#4f6ef7;margin-right:8px"></i>Aperçu de l'imposition
        <span id="impPreviewPageLabel" style="font-weight:400;font-size:12px;color:#6b7280;margin-left:8px"></span>
      </div>
      <button id="impPreviewClose" style="border:none;background:transparent;cursor:pointer;font-size:20px;color:#6b7280;line-height:1">×</button>
    </div>
    <!-- Image preview -->
    <div style="flex:1;overflow:auto;background:#f3f4f6;display:flex;align-items:center;justify-content:center;padding:20px;min-height:300px">
      <img id="impPreviewImg" src="" alt="Aperçu" style="max-width:100%;max-height:60vh;border-radius:6px;box-shadow:0 4px 20px rgba(0,0,0,0.15);display:none">
      <div id="impPreviewLoading" style="text-align:center;color:#6b7280;font-family:Inter,sans-serif;font-size:13px">
        <div style="width:36px;height:36px;border:3px solid #e2e5ea;border-top-color:#4f6ef7;border-radius:50%;animation:spin 0.8s linear infinite;margin:0 auto 12px"></div>
        Génération de l'aperçu...
      </div>
    </div>
    <!-- Footer -->
    <div style="padding:16px 20px;border-top:1px solid #e2e5ea;display:flex;gap:10px;justify-content:flex-end">
      <button id="impPreviewCloseBtn" style="padding:10px 20px;border:1px solid #e2e5ea;border-radius:8px;background:#fff;cursor:pointer;font-family:Inter,sans-serif;font-size:13px;font-weight:500;color:#374151">Fermer</button>
      <button id="impPreviewLoadApp" style="padding:10px 20px;border:none;border-radius:8px;background:linear-gradient(135deg,#10b981,#059669);color:#fff;cursor:pointer;font-family:Inter,sans-serif;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:6px">
        <i class="fa fa-folder-open"></i> Charger dans le Studio
      </button>
      <a id="impPreviewDownload" href="#" style="padding:10px 20px;border:none;border-radius:8px;background:linear-gradient(135deg,#4f6ef7,#6f42c1);color:#fff;cursor:pointer;font-family:Inter,sans-serif;font-size:13px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:6px">
        <i class="fa fa-download"></i> Télécharger le PDF
      </a>
    </div>
  </div>
</div>

<script src="<?= $base_path ?>js/studio.js" defer></script>
</body>
</html>
