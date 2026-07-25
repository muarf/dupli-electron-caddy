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

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Check if we need to auto-load a file from the library
  const urlParams = new URLSearchParams(window.location.search);
  const fileId = urlParams.get('file_id');
  if (fileId) {
    if (window._reopenInStudio) {
      window._reopenInStudio('?get_bibliotheque_file&id=' + fileId);
    } else {
      // Fallback si _reopenInStudio n'est pas encore prêt (normalement impossible car défini globalement)
      setTimeout(() => { if (window._reopenInStudio) window._reopenInStudio('?get_bibliotheque_file&id=' + fileId); }, 500);
    }
  }

  // === STATE ===
  const state = {
    libraryId: null,
    file: null, isPdf: false, pdfDoc: null, currentPage: 1, totalPages: 0,
    originalImageData: null, rotation: 0, flipH: false, flipV: false,
    dims: null,  // { wPx, hPx, wMm, hMm, label }
    orgSelectedIndex: 0,
    risoLevels: null,
    risoHalftone: null,
    risoShowOriginal: false,
    filtersModified: false   // true si l'utilisateur a modifié des filtres image
  };

  function appendStudioFile(fd, defaultName) {
    if (state.libraryId) {
      fd.append('file_id', state.libraryId);
      if (defaultName) fd.append('filename', defaultName);
    } else {
      fd.append('file', state.file, defaultName || state.file.name);
    }
  }

  // Retourne true si des modifications image ont été faites (filtres, bitmap…)
  function hasFiltersModified() {
    if (state.filtersModified) return true;
    if ($('chkBitmap') && $('chkBitmap').checked) return true;
    const defaults = { contrast: 0, brightness: 0, gamma: 1, saturation: 0 };
    for (const [k, def] of Object.entries(defaults)) {
      if (sliders && sliders[k] && parseFloat(sliders[k].el.value) !== def) return true;
    }
    return false;
  }
  window.state = state;
  
  window.orgSequence = [];
  window.orgDocs = [];
  window.orgFiles = [];

  function setPdfReady(url) {
    state.lastServerResultUrl = url;
    if ($('pdfReadyBadge')) {
      $('pdfReadyBadge').style.display = url ? 'flex' : 'none';
    }
  }

  // Helper pour identifier le format papier
  function getPaperFormat(w, h) {
    const formats = {
      'A0': [841, 1189], 'A1': [594, 841], 'A2': [420, 594],
      'A3': [297, 420],  'A4': [210, 297], 'A5': [148, 210],
      'A6': [105, 148]
    };
    const min = Math.min(w, h), max = Math.max(w, h);
    for (let f in formats) {
      const [fMin, fMax] = formats[f];
      if (Math.abs(min - fMin) <= 3 && Math.abs(max - fMax) <= 3) return f;
    }
    return null;
  }
  
  // === DOM REFS ===
  const $ = id => document.getElementById(id);
  const uploadZone = $('uploadZone'), fileInput = $('studioFileInput');
  const canvas = $('studioCanvas'), ctx = canvas.getContext('2d', { willReadFrequently: true });
  const panel = $('studioPanel'), thumbsBar = $('thumbsBar');
  const canvasArea = $('canvasArea');
  // Helper : affiche/cache le canvas (dans cropContainer)
  function showCanvas(visible) {
    canvas.style.display = visible ? 'block' : 'none';
    // Si on cache le canvas, on cache aussi l'overlay crop
    if (!visible) {
      const cc = $('cropContainer');
      if (cc) cc.style.display = 'none';
      state.cropMode = false;
    }
  }

  // === SIDEBAR TOOL SWITCHING ===
  document.querySelectorAll('.tool-btn[data-tool]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const tool = btn.dataset.tool;
      // Show/hide panels
      ['panelFilters','panelImposition','panelGeometry','panelPages','panelRiso','panelMontage','panelOcr','panelModification','panelMetadata'].forEach(p => { if($(p)) $(p).style.display = 'none'; });
      
      const standardCanvas = $('studioCanvas');
      const montageContainer = $('montageCanvasContainer');
      const stdThumbs = $('thumbsBar');
      
      if (tool === 'montage') {
        $('panelMontage').style.display = '';
        if (standardCanvas) standardCanvas.style.display = 'none';
        if (stdThumbs) stdThumbs.style.display = 'none';
        if (uploadZone) uploadZone.style.display = 'none';
        if (panel) panel.classList.add('visible');
        if (montageContainer) montageContainer.style.display = 'flex';
        // Initialize montage if not done yet
        if (window.initStudioMontage) window.initStudioMontage();
        
        // Auto-load global file if exists
        if (state && state.file && window.addFileToMontage) {
          window.addFileToMontage(state.file);
        }
      } else {
        if (montageContainer) montageContainer.style.display = 'none';
        if (stdThumbs && window.orgSequence && window.orgSequence.length > 0) stdThumbs.style.display = '';
        
        if (state.originalImageData || state.isPdf || state.file) {
          showCanvas(true);
          if (uploadZone) uploadZone.style.display = 'none';
        } else {
          if (uploadZone) uploadZone.style.display = 'block';
          if (panel) panel.classList.remove('visible');
        }
        
        if (tool === 'modification') {
          $('panelModification').style.display = '';
          if ($('modificationContainer')) $('modificationContainer').style.display = 'block';
          if (window.initStudioModification) window.initStudioModification();
        } else if (tool === 'metadata') {
          $('panelMetadata').style.display = '';
          if (panel) panel.classList.add('visible');
        } else {
          if ($('modificationContainer')) $('modificationContainer').style.display = 'none';
        }
        
        if (tool === 'filters') $('panelFilters').style.display = '';
        else if (tool === 'imposition') $('panelImposition').style.display = '';
        else if (tool === 'geometry') $('panelGeometry').style.display = '';
        else if (tool === 'pages') $('panelPages').style.display = '';
        else if (tool === 'ocr') $('panelOcr').style.display = '';
        else if (tool === 'riso') {
          $('panelRiso').style.display = '';
          if (state.originalImageData && !window.risoChannels) initRisoChannels();
        }
      }
    });
  });

  // === FILE UPLOAD ===
  uploadZone.addEventListener('click', () => fileInput.click());
  uploadZone.addEventListener('dragover', e => { e.preventDefault(); uploadZone.classList.add('dragover'); });
  uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('dragover'));
  uploadZone.addEventListener('drop', e => {
    e.preventDefault(); uploadZone.classList.remove('dragover');
    if (e.dataTransfer.files.length > 0) loadFile(e.dataTransfer.files[0]);
  });
  fileInput.addEventListener('change', e => { if (e.target.files.length > 0) loadFile(e.target.files[0]); });
  document.addEventListener('dragover', e => e.preventDefault());
  document.addEventListener('drop', e => e.preventDefault());
  
  $('btnNewFile').addEventListener('click', () => {
    resetStudio();
  });

  // === LOAD FILE ===
  function loadFile(file) {
    state.libraryId = null; // Reset unless explicitly set after
    const valid = ['image/png','image/jpeg','image/jpg','image/gif','image/webp','application/pdf'];
    if (!valid.includes(file.type)) { alert('Format non supporté'); return; }
    state.file = file;
    state.isPdf = (file.type === 'application/pdf');
    state.rotation = 0; state.flipH = false; state.flipV = false;

    $('fileNameDisplay').value = file.name;
    $('fileInfoBadge').style.display = '';
    $('btnNewFile').style.display = '';
    $('btnExportPng').style.display = '';
    $('btnSaveToLibrary').style.display = '';
    $('btnExportPdf').style.display = '';
    uploadZone.style.display = 'none';
    showCanvas(true);
    panel.classList.add('visible');

    if (state.isPdf) loadPdf(file);
    else loadImage(file);
    
    analyzeInk();
  }

  async function analyzeInk() {
    if (!state.file) return;
    const badge = $('fileInkDisplay');
    badge.style.display = 'inline-flex';
    badge.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
    badge.style.color = 'var(--studio-text-muted)';
    
    const fd = new FormData();
    appendStudioFile(fd);
    fd.append('action', 'analyze_ink');
    
    try {
      const res = await fetch('?studio_process', { method: 'POST', body: fd });
      const json = await res.json();
      
      if (json.success && json.job_id) {
        const checkInterval = setInterval(async () => {
          const stFd = new FormData();
          stFd.append('action', 'analyze_ink_status');
          stFd.append('job_id', json.job_id);
          try {
            const stRes = await fetch('?studio_process', { method: 'POST', body: stFd });
            const stData = await stRes.json();
            if (stData.success) {
              if (stData.status === 'done') {
                clearInterval(checkInterval);
                state.inkData = stData.result;
                badge.innerHTML = '<i class="fa fa-tint" style="color:var(--studio-primary)"></i> Enc: ' + stData.result.fill_rate + '%';
                badge.style.color = 'var(--studio-primary)';
                renderThumbnails(); // Refresh thumbs to show per-page ink
                if (typeof fetchActiveJobs === 'function') fetchActiveJobs(); // Update task manager
              } else if (stData.status === 'error') {
                clearInterval(checkInterval);
                console.error("Ink analysis failed: ", stData.error);
                badge.style.display = 'none';
                if (typeof fetchActiveJobs === 'function') fetchActiveJobs();
              }
            } else {
              clearInterval(checkInterval);
              console.error("Ink analysis error:", stData.error);
              badge.style.display = 'none';
            }
          } catch(err) {
            console.error("Erreur polling ink", err);
          }
        }, 3000);
        
        if (typeof fetchActiveJobs === 'function') fetchActiveJobs(); // Ajouter le job au manager
      } else if (json.success && json.result) {
        state.inkData = json.result;
        badge.innerHTML = '<i class="fa fa-tint" style="color:var(--studio-primary)"></i> Enc: ' + json.result.fill_rate + '%';
        badge.style.color = 'var(--studio-primary)';
        renderThumbnails(); // Refresh thumbs to show per-page ink
      } else {
        badge.style.display = 'none';
      }
    } catch(e) {
      console.error("Ink analysis failed", e);
      badge.style.display = 'none';
    }
  }

  function loadImage(file) {
    const reader = new FileReader();
    reader.onload = e => {
      const img = new Image();
      img.onload = () => {
        // Fit to canvas area
        const maxW = canvasArea.clientWidth - 48;
        const maxH = canvasArea.clientHeight - 48;
        let w = img.width, h = img.height;
        if (w > maxW) { h = h * maxW / w; w = maxW; }
        if (h > maxH) { w = w * maxH / h; h = maxH; }
        canvas.width = w; canvas.height = h;
        ctx.drawImage(img, 0, 0, w, h);
        state.originalImageData = ctx.getImageData(0, 0, w, h);
        state.totalPages = 1; state.currentPage = 1;
        state._img = img; state._dispW = w; state._dispH = h;
        // Dimensions
        const wMm = Math.round(img.naturalWidth * 25.4 / 96);
        const hMm = Math.round(img.naturalHeight * 25.4 / 96);
        state.dims = { wPx: img.naturalWidth, hPx: img.naturalHeight, wMm, hMm, dpi: 96 };
        const fmt = getPaperFormat(wMm, hMm);
        $('fileDimsDisplay').textContent = img.naturalWidth + '×' + img.naturalHeight + 'px (' + wMm + '×' + hMm + 'mm)' + (fmt ? ' [' + fmt + ']' : '');
        
        // Reset organize sequence for simple images
        window.orgSequence = [];
        window.orgDocs = [];
        window.orgFiles = [];
        renderThumbnails();
      };
      img.src = e.target.result;
    };
    reader.readAsDataURL(file);
  }

  function loadPdf(file) {
    const reader = new FileReader();
    reader.onload = async e => {
      try {
        if (typeof pdfjsLib === 'undefined') { alert('PDF.js non chargé'); return; }
        if (!pdfjsLib.GlobalWorkerOptions.workerSrc) pdfjsLib.GlobalWorkerOptions.workerSrc = 'js/build/pdf.worker.js';
        const data = new Uint8Array(e.target.result);
        state.pdfDoc = await pdfjsLib.getDocument({data}).promise;
        state.totalPages = state.pdfDoc.numPages;
        state.currentPage = 1;
        
        window.orgFiles = [file];
        window.orgDocs = [state.pdfDoc];
        window.orgSequence = [];
        for (let i = 1; i <= state.totalPages; i++) {
          window.orgSequence.push({ file_idx: 0, page_num: i, type: 'page', rotation: 0 });
        }

        await renderPdfPage(1);
        // Dimensions from first page (PDF points → mm: 1pt = 0.352778mm)
        const firstPage = await state.pdfDoc.getPage(1);
        const vp = firstPage.getViewport({scale: 1});
        const wMm = Math.round(vp.width * 0.352778);
        const hMm = Math.round(vp.height * 0.352778);
        state.dims = { wPx: Math.round(vp.width), hPx: Math.round(vp.height), wMm, hMm, dpi: 72 };
        const fmt = getPaperFormat(wMm, hMm);
        $('fileDimsDisplay').textContent = state.totalPages + 'p. — ' + wMm + '×' + hMm + 'mm' + (fmt ? ' [' + fmt + ']' : '');
        renderThumbnails();
        
        $('uploadZone').style.display = 'none';
        canvas.style.display = 'block';
        if ($('mainCanvasDeleteBtn')) $('mainCanvasDeleteBtn').style.display = 'flex';
        state.orgSelectedIndex = 0;
      } catch(err) { alert('Erreur PDF: ' + err.message); }
    };
    reader.readAsArrayBuffer(file);
  }

  async function renderPdfPage(num) {
    if (!state.pdfDoc) return;
    const page = await state.pdfDoc.getPage(num);
    const vp = page.getViewport({scale: 1});
    const maxW = canvasArea.clientWidth - 48;
    const maxH = canvasArea.clientHeight - 48;
    const scale = Math.min(maxW / vp.width, maxH / vp.height, 2);
    const svp = page.getViewport({scale});
    
    canvas.width = svp.width; 
    canvas.height = svp.height;
    
    await page.render({canvasContext: ctx, viewport: svp}).promise;
    
    if (canvas.width > 0 && canvas.height > 0) {
      state.originalImageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    }
    state._dispW = svp.width; state._dispH = svp.height;
    window.risoChannels = null; // Force re-init on next Riso tab click
    // Update page nav
    const nav = thumbsBar.querySelector('.page-nav');
    if (nav) nav.textContent = window.orgSequence.length + ' page(s)';
    // Highlight active thumb based on orgSelectedIndex
    thumbsBar.querySelectorAll('.thumb-item').forEach((t,i) => {
      t.classList.toggle('active', i === state.orgSelectedIndex);
    });
  }

  async function renderThumbnails() {
    try {
      if (window.orgSequence.length === 0) {
        thumbsBar.innerHTML = '';
        thumbsBar.classList.remove('visible');
        return;
      }
      const fragment = document.createDocumentFragment();

      // Add info span
      const navSpan = document.createElement('span');
      navSpan.className = 'page-nav';
      navSpan.textContent = window.orgSequence.length + ' page(s)';
      fragment.appendChild(navSpan);

      // Tableau pour stocker les promesses de rendu asynchrone
      const renderTasks = [];

      for (let i = 0; i < window.orgSequence.length; i++) {
        const item = window.orgSequence[i];
        const div = document.createElement('div');
        div.className = 'thumb-item' + (i === state.orgSelectedIndex ? ' active' : '');
        div.draggable = true;
        div.dataset.index = i;

        if (item.type === 'blank') {
          const tc = document.createElement('div');
          tc.style.width = '60px'; tc.style.height = '85px'; tc.style.background = '#fff'; tc.style.border = '1px solid #ccc';
          tc.style.display = 'flex'; tc.style.alignItems = 'center'; tc.style.justifyContent = 'center'; tc.style.fontSize = '10px'; tc.style.color = '#999';
          tc.textContent = 'BLANK';
          div.appendChild(tc);
        } else {
          // Créer le canvas vide immédiatement
          const tc = document.createElement('canvas');
          tc.width = 60; tc.height = 85; // Placeholder size
          div.appendChild(tc);

          // Ajouter la tâche de rendu en arrière-plan
          renderTasks.push(async () => {
            try {
              const doc = window.orgDocs[item.file_idx];
              if (doc) {
                const page = await doc.getPage(item.page_num);
                const vp = page.getViewport({scale: 0.2});
                tc.width = vp.width; tc.height = vp.height;
                await page.render({canvasContext: tc.getContext('2d'), viewport: vp}).promise;
                let transforms = [];
                if (item.rotation) transforms.push(`rotate(${item.rotation}deg)`);
                if (item.flipH) transforms.push('scaleX(-1)');
                if (item.flipV) transforms.push('scaleY(-1)');
                if (transforms.length) {
                  tc.style.transform = transforms.join(' ');
                }
              }
            } catch(e) { console.error("Error rendering thumbnail", e); }
          });
        }

        const lbl = document.createElement('div');
        lbl.className = 'thumb-label'; lbl.textContent = i + 1;
        div.appendChild(lbl);

        // Ink Badge for page
        if (state.inkData && state.inkData.pages) {
          const pageInfo = state.inkData.pages.find(p => p.page === item.page_num);
          if (pageInfo) {
            const ink = pageInfo.fill_rate;
            const inkBadge = document.createElement('div');
            inkBadge.style = "position:absolute; top:2px; left:2px; background:rgba(0,0,0,0.6); color:white; font-size:9px; padding:1px 3px; border-radius:3px; z-index:5;";
            inkBadge.textContent = ink + "%";
            div.appendChild(inkBadge);
          }
        } else if (state.inkData && !state.inkData.pages && i === 0) {
          // Simple image (only one page)
          const ink = state.inkData.fill_rate;
          const inkBadge = document.createElement('div');
          inkBadge.style = "position:absolute; top:2px; left:2px; background:rgba(0,0,0,0.6); color:white; font-size:9px; padding:1px 3px; border-radius:3px; z-index:5;";
          inkBadge.textContent = ink + "%";
          div.appendChild(inkBadge);
        }

        // Actions hover overlay
        const acts = document.createElement('div');
        acts.className = 'thumb-actions';
        acts.innerHTML = `
          <i class="fa fa-rotate-right" onclick="event.stopPropagation(); orgRotate(${i}, 90)" title="Pivoter"></i>
          <i class="fa fa-trash" style="color:#ef4444" onclick="event.stopPropagation(); orgDelete(${i})" title="Supprimer"></i>
        `;
        div.appendChild(acts);

        // Click to view
        div.addEventListener('click', async () => { 
          state.orgSelectedIndex = i; // Suivre l'index sélectionné dans l'organiseur
          console.log('[Studio] Thumb clicked:', i, '| type:', item.type, '| file_idx:', item.file_idx);
          if (item.type === 'page') {
            const doc = window.orgDocs[item.file_idx];
            if (doc) {
              state.currentPage = item.page_num;
              const page = await doc.getPage(item.page_num);
              const vp = page.getViewport({scale: 1});
              const maxW = canvasArea.clientWidth - 48;
              const maxH = canvasArea.clientHeight - 48;
              const scale = Math.min(maxW / vp.width, maxH / vp.height, 2);
              const svp = page.getViewport({scale});
              canvas.width = svp.width; canvas.height = svp.height;
              await page.render({canvasContext: ctx, viewport: svp}).promise;
              
              // Appliquer les transformations
              if (item.flipH || item.flipV || item.rotation) {
                  const off = document.createElement('canvas'); off.width = svp.width; off.height = svp.height;
                  off.getContext('2d').drawImage(canvas, 0, 0);
                  
                  let cw = svp.width, ch = svp.height;
                  const r = item.rotation || 0;
                  if (r === 90 || r === 270 || r === -90 || r === -270) {
                      cw = svp.height; ch = svp.width;
                  }
                  canvas.width = cw; canvas.height = ch;
                  ctx.clearRect(0,0,cw,ch);
                  ctx.save();
                  ctx.translate(cw/2, ch/2);
                  if (r) ctx.rotate(r * Math.PI / 180);
                  let sx = item.flipH ? -1 : 1;
                  let sy = item.flipV ? -1 : 1;
                  ctx.scale(sx, sy);
                  ctx.drawImage(off, -svp.width/2, -svp.height/2);
                  ctx.restore();
              }

              state.originalImageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
              state._dispW = canvas.width; state._dispH = canvas.height;
              applyFilters();
            }
          } else if (item.type === 'blank') {
            // Afficher une page blanche dans le canvas
            const w = (state.dims && state.dims.wPx) ? state.dims.wPx : 1240;
            const h = (state.dims && state.dims.hPx) ? state.dims.hPx : 1754;
            canvas.width = w; canvas.height = h;
            ctx.fillStyle = 'white';
            ctx.fillRect(0, 0, w, h);
            state.originalImageData = ctx.getImageData(0, 0, w, h);
            state._dispW = w; state._dispH = h;
          }
          // S'assurer que le canvas est visible
          canvas.style.display = 'block';
          if ($('mainCanvasDeleteBtn')) $('mainCanvasDeleteBtn').style.display = 'flex';
          $('uploadZone').style.display = 'none';
          // Highlight active thumb
          thumbsBar.querySelectorAll('.thumb-item').forEach((t,idx) => t.classList.toggle('active', idx === i));
        });

        // Drag and Drop
        div.addEventListener('dragstart', (e) => {
          e.dataTransfer.setData('text/plain', i);
          div.style.opacity = '0.5';
        });
        div.addEventListener('dragend', () => div.style.opacity = '1');
        div.addEventListener('dragover', (e) => { e.preventDefault(); div.style.border = '2px dashed #4f46e5'; });
        div.addEventListener('dragleave', () => div.style.border = '');
        div.addEventListener('drop', (e) => {
          e.preventDefault();
          div.style.border = '';
          const fromIdx = parseInt(e.dataTransfer.getData('text/plain'));
          const toIdx = i;
          if (fromIdx !== toIdx) {
            const moved = window.orgSequence.splice(fromIdx, 1)[0];
            window.orgSequence.splice(toIdx, 0, moved);
            renderThumbnails();
          }
        });

        fragment.appendChild(div);
      }

      // Appliquer le fragment d'un coup pour éviter les duplications (race condition)
      thumbsBar.innerHTML = '';
      thumbsBar.style.display = 'flex'; // FORCE L'AFFICHAGE (contourne les éventuels style.display = 'none')
      thumbsBar.classList.add('visible');
      thumbsBar.appendChild(fragment);
      console.log('[Studio] Thumbnails rendus et affichés', window.orgSequence.length);

      // Lancer les rendus en arrière-plan sans bloquer
      setTimeout(async () => {
        for (const task of renderTasks) {
          await task();
        }
      }, 50);
    } catch (err) {
      console.error("Erreur critique lors du rendu des vignettes", err);
    }
  }

  window.orgRotate = function(idx, angle) {
    if (window.orgSequence[idx]) {
      window.orgSequence[idx].rotation = ((window.orgSequence[idx].rotation || 0) + angle) % 360;
      renderThumbnails();
    }
  };

  window.orgDelete = function(idx) {
    if (idx === undefined || idx === null || idx < 0 || idx >= window.orgSequence.length) return;
    window.orgSequence.splice(idx, 1);
    
    // Mettre à jour l'index sélectionné si nécessaire
    if (state.orgSelectedIndex === idx) {
      // Si on supprime la page active, on sélectionne la précédente (ou la suivante si on était à 0)
      state.orgSelectedIndex = Math.max(0, idx - 1);
    } else if (state.orgSelectedIndex > idx) {
      // Si on supprime une page avant la sélection, l'index de sélection recule de 1
      state.orgSelectedIndex--;
    }
    
    renderThumbnails().then(() => {
      // Rafraîchir l'affichage du canvas
      if (window.orgSequence.length > 0) {
        const thumbs = thumbsBar.querySelectorAll('.thumb-item');
        if (thumbs[state.orgSelectedIndex]) thumbs[state.orgSelectedIndex].click();
      } else {
        // Plus aucune page
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        canvas.style.display = 'none';
        $('cropContainer').style.display = 'none';
        $('mainCanvasDeleteBtn').style.display = 'none';
        $('uploadZone').style.display = 'flex'; // Remettre l'upload zone
      }
    });
  };

  $('mainCanvasDeleteBtn').addEventListener('click', () => {
    if (state.orgSelectedIndex !== undefined && state.orgSelectedIndex !== null) {
      window.orgDelete(state.orgSelectedIndex);
    }
  });

  // === FILTERS ===
  const sliders = {
    contrast: {el: $('sliderContrast'), val: $('valContrast')},
    brightness: {el: $('sliderBrightness'), val: $('valBrightness')},
    gamma: {el: $('sliderGamma'), val: $('valGamma')},
    saturation: {el: $('sliderSaturation'), val: $('valSaturation')},
    threshold: {el: $('sliderThreshold'), val: $('valThreshold')}
  };
  Object.keys(sliders).forEach(k => {
    sliders[k].el.addEventListener('input', () => {
      const v = parseFloat(sliders[k].el.value);
      sliders[k].val.textContent = k === 'gamma' ? v.toFixed(1) : Math.round(v);
      state.filtersModified = true;
      applyFilters();
    });
  });
  $('chkBitmap').addEventListener('change', e => {
    $('bitmapOpts').style.display = e.target.checked ? 'block' : 'none';
    state.filtersModified = true;
    applyFilters();
  });
  $('selBitmapMethod').addEventListener('change', () => {
    $('thresholdRow').style.display = $('selBitmapMethod').value === 'threshold' ? '' : 'none';
    applyFilters();
  });

  function applyFilters() {
    if (!state.originalImageData) return;
    const d = new ImageData(new Uint8ClampedArray(state.originalImageData.data), state.originalImageData.width, state.originalImageData.height);
    const contrast = parseFloat(sliders.contrast.el.value);
    const brightness = parseFloat(sliders.brightness.el.value);
    const gamma = parseFloat(sliders.gamma.el.value);
    const saturation = parseFloat(sliders.saturation.el.value);
    const bitmapOn = $('chkBitmap').checked;
    const threshold = parseInt(sliders.threshold.el.value);
    const px = d.data;
    for (let i = 0; i < px.length; i += 4) {
      let r = px[i], g = px[i+1], b = px[i+2];
      // Contrast
      if (contrast !== 0) {
        const cv = contrast * 2.55;
        const f = (259*(cv+255))/(255*(259-cv));
        r = Math.min(255,Math.max(0,f*(r-128)+128));
        g = Math.min(255,Math.max(0,f*(g-128)+128));
        b = Math.min(255,Math.max(0,f*(b-128)+128));
      }
      // Brightness
      if (brightness !== 0) { r = Math.min(255,Math.max(0,r+brightness)); g = Math.min(255,Math.max(0,g+brightness)); b = Math.min(255,Math.max(0,b+brightness)); }
      // Gamma
      if (gamma !== 1.0) { r = Math.pow(r/255,1/gamma)*255; g = Math.pow(g/255,1/gamma)*255; b = Math.pow(b/255,1/gamma)*255; }
      // Saturation
      if (saturation !== 0) {
        const sf = 1 + saturation/100;
        const gray = (r+g+b)/3;
        r = Math.min(255,Math.max(0,gray+(r-gray)*sf));
        g = Math.min(255,Math.max(0,gray+(g-gray)*sf));
        b = Math.min(255,Math.max(0,gray+(b-gray)*sf));
      }
      // Bitmap
      if (bitmapOn) { const gr = (r+g+b)/3; const v = gr < threshold ? 0 : 255; r=g=b=v; }
      px[i]=r; px[i+1]=g; px[i+2]=b;
    }
    ctx.putImageData(d, 0, 0);
    setPdfReady(null);

    // Update Riso channels if Riso panel is active
    if (document.querySelector('.tool-btn[data-tool="riso"]').classList.contains('active')) {
      initRisoChannels();
    }
  }

  // === RESET ===
  $('btnReset').addEventListener('click', () => {
    Object.keys(sliders).forEach(k => {
      sliders[k].el.value = k === 'gamma' ? 1.0 : (k === 'threshold' ? 128 : 0);
      sliders[k].val.textContent = k === 'gamma' ? '1.0' : (k === 'threshold' ? '128' : '0');
    });
    $('chkBitmap').checked = false;
    $('bitmapOpts').style.display = 'none';
    if (state.originalImageData) ctx.putImageData(state.originalImageData, 0, 0);
  });

  // === GEOMETRY ===
  $('btnRotateLeft').addEventListener('click', () => applyGeometry('rotate', -90));
  $('btnRotateRight').addEventListener('click', () => applyGeometry('rotate', 90));
  $('btnFlipH').addEventListener('click', () => applyGeometry('flipH'));
  $('btnFlipV').addEventListener('click', () => applyGeometry('flipV'));

  if ($('sliderDeskew')) {
    $('sliderDeskew').addEventListener('input', (e) => {
      const val = parseFloat(e.target.value);
      $('valDeskew').textContent = val + '°';
      canvas.style.transform = 'rotate(' + val + 'deg)';
    });

    $('btnApplyDeskew').addEventListener('click', () => {
      const val = parseFloat($('sliderDeskew').value);
      if (val !== 0) {
        applyGeometry('rotateFine', val);
        $('sliderDeskew').value = 0;
        $('valDeskew').textContent = '0°';
        canvas.style.transform = '';
      }
    });
  }

  function applyGeometry(action, val) {
    const applyAll = $('chkGeomApplyAll') && $('chkGeomApplyAll').checked;
    
    if (applyAll) {
       for (let i = 0; i < window.orgSequence.length; i++) {
          let it = window.orgSequence[i];
          if (action === 'rotate' || action === 'rotateFine') it.rotation = ((it.rotation || 0) + val) % 360;
          if (action === 'flipH') it.flipH = !it.flipH;
          if (action === 'flipV') it.flipV = !it.flipV;
       }
       renderThumbnails();
       if (action === 'rotate' || action === 'rotateFine') rotateCanvas(val);
       if (action === 'flipH') flipCanvas('h');
       if (action === 'flipV') flipCanvas('v');
    } else {
       if (state.orgSelectedIndex !== undefined && window.orgSequence[state.orgSelectedIndex]) {
          const it = window.orgSequence[state.orgSelectedIndex];
          if (action === 'rotate' || action === 'rotateFine') it.rotation = ((it.rotation || 0) + val) % 360;
          if (action === 'flipH') it.flipH = !it.flipH;
          if (action === 'flipV') it.flipV = !it.flipV;
          renderThumbnails();
       }
       if (action === 'rotate' || action === 'rotateFine') rotateCanvas(val);
       if (action === 'flipH') flipCanvas('h');
       if (action === 'flipV') flipCanvas('v');
    }
  }

  function rotateCanvas(deg) {
    if (!state.originalImageData) return;
    const src = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const w = canvas.width, h = canvas.height;
    canvas.width = h; canvas.height = w;
    ctx.save(); ctx.translate(h/2, w/2); ctx.rotate(deg * Math.PI / 180);
    ctx.drawImage(canvas, 0, 0); // temp
    ctx.restore();
    // Simpler: use offscreen
    const off = document.createElement('canvas'); off.width = w; off.height = h;
    off.getContext('2d').putImageData(src, 0, 0);
    canvas.width = h; canvas.height = w;
    ctx.save(); ctx.translate(canvas.width/2, canvas.height/2);
    ctx.rotate(deg * Math.PI / 180);
    ctx.drawImage(off, -w/2, -h/2);
    ctx.restore();
    state.originalImageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    setPdfReady(null);
  }

  function flipCanvas(axis) {
    if (!state.originalImageData) return;
    const src = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const off = document.createElement('canvas'); off.width = canvas.width; off.height = canvas.height;
    off.getContext('2d').putImageData(src, 0, 0);
    ctx.save();
    if (axis === 'h') { ctx.translate(canvas.width, 0); ctx.scale(-1, 1); }
    else { ctx.translate(0, canvas.height); ctx.scale(1, -1); }
    ctx.drawImage(off, 0, 0);
    ctx.restore();
    state.originalImageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    setPdfReady(null);
  }

  // === CROP ===
  // État du crop (marges en mm depuis chaque bord)
  state.crop = { top: 0, bottom: 0, left: 0, right: 0 };
  state.cropMode = false; // true quand l'overlay est visible

  // Référence aux éléments de l'overlay crop
  const cropContainer   = $('cropContainer');
  const cropOverlay     = $('cropOverlay');
  const cropOverlayCtx  = cropOverlay ? cropOverlay.getContext('2d') : null;
  const cropRulerX      = $('cropRulerX');
  const cropRulerY      = $('cropRulerY');

  // Convertit des px canvas → mm réels
  function canvasPxToMm(px, axis) {
    if (!state.dims) return 0;
    const totalPx = axis === 'x' ? canvas.width : canvas.height;
    const totalMm = axis === 'x' ? state.dims.wMm : state.dims.hMm;
    return (px / totalPx) * totalMm;
  }
  // Convertit des mm réels → px canvas
  function mmToCanvasPx(mm, axis) {
    if (!state.dims) return 0;
    const totalPx = axis === 'x' ? canvas.width : canvas.height;
    const totalMm = axis === 'x' ? state.dims.wMm : state.dims.hMm;
    return (mm / totalMm) * totalPx;
  }

  // Dessine une règle graduée en cm sur un canvas
  function drawCropRuler(rulerCanvas, totalMm, isVertical) {
    if (!rulerCanvas) return;
    const thickness = 30;
    const length = isVertical ? canvas.height : canvas.width;
    if (isVertical) { rulerCanvas.width = thickness; rulerCanvas.height = length; }
    else             { rulerCanvas.width = length;    rulerCanvas.height = thickness; }

    const rc = rulerCanvas.getContext('2d');
    rc.clearRect(0, 0, rulerCanvas.width, rulerCanvas.height);
    rc.fillStyle = '#f0f2f5';
    rc.fillRect(0, 0, rulerCanvas.width, rulerCanvas.height);

    rc.fillStyle = '#555';
    rc.font = '9px Inter, sans-serif';
    rc.textAlign = 'center';

    const pxPerMm = length / totalMm;
    const stepMm = totalMm > 200 ? 10 : (totalMm > 80 ? 5 : 2);

    for (let mm = 0; mm <= totalMm; mm += stepMm) {
      const pos = mm * pxPerMm;
      const isCm = (mm % 10 === 0);
      const tickLen = isCm ? 10 : (mm % 5 === 0 ? 7 : 4);

      rc.beginPath();
      rc.strokeStyle = '#999';
      rc.lineWidth = 1;
      if (!isVertical) {
        rc.moveTo(pos, thickness); rc.lineTo(pos, thickness - tickLen);
      } else {
        rc.moveTo(thickness, pos); rc.lineTo(thickness - tickLen, pos);
      }
      rc.stroke();

      if (isCm && mm > 0) {
        const label = (mm / 10) + 'cm';
        if (!isVertical) {
          rc.fillText(label, pos, thickness - tickLen - 2);
        } else {
          rc.save();
          rc.translate(thickness - tickLen - 2, pos);
          rc.rotate(-Math.PI / 2);
          rc.fillText(label, 0, 0);
          rc.restore();
        }
      }
    }
  }

  // Dessine l'overlay de crop (zones rouges + lignes pointillées + poignées)
  function drawCropOverlay() {
    if (!cropOverlayCtx || !canvas.width || !canvas.height) return;
    const w = canvas.width, h = canvas.height;
    cropOverlay.width = w; cropOverlay.height = h;

    const c = state.crop;
    const topPx    = mmToCanvasPx(c.top,    'y');
    const bottomPx = mmToCanvasPx(c.bottom, 'y');
    const leftPx   = mmToCanvasPx(c.left,   'x');
    const rightPx  = mmToCanvasPx(c.right,  'x');

    const x0 = leftPx, y0 = topPx;
    const x1 = w - rightPx, y1 = h - bottomPx;
    const cw = x1 - x0, ch = y1 - y0;

    cropOverlayCtx.clearRect(0, 0, w, h);

    // ── 1. Masque sombre sur les zones exclues (style éditeur pro)
    cropOverlayCtx.fillStyle = 'rgba(0, 0, 0, 0.60)';
    cropOverlayCtx.fillRect(0,  0,  w,  y0);          // Haut
    cropOverlayCtx.fillRect(0,  y1, w,  h - y1);       // Bas
    cropOverlayCtx.fillRect(0,  y0, x0, ch);           // Gauche
    cropOverlayCtx.fillRect(x1, y0, w - x1, ch);       // Droite

    // ── 2. Bordure extérieure (blanc opaque) + intérieure (noir fin)
    cropOverlayCtx.strokeStyle = 'rgba(0,0,0,0.6)';
    cropOverlayCtx.lineWidth = 1;
    cropOverlayCtx.setLineDash([]);
    cropOverlayCtx.strokeRect(x0 - 1, y0 - 1, cw + 2, ch + 2);

    cropOverlayCtx.strokeStyle = '#ffffff';
    cropOverlayCtx.lineWidth = 2;
    cropOverlayCtx.strokeRect(x0, y0, cw, ch);

    // ── 3. Lignes de tiers (règle des tiers - 3×3 grille)
    if (cw > 40 && ch > 40) {
      cropOverlayCtx.strokeStyle = 'rgba(255,255,255,0.25)';
      cropOverlayCtx.lineWidth = 1;
      cropOverlayCtx.setLineDash([]);
      // Verticales
      for (let i = 1; i <= 2; i++) {
        const x = x0 + (cw / 3) * i;
        cropOverlayCtx.beginPath(); cropOverlayCtx.moveTo(x, y0); cropOverlayCtx.lineTo(x, y1); cropOverlayCtx.stroke();
      }
      // Horizontales
      for (let i = 1; i <= 2; i++) {
        const y = y0 + (ch / 3) * i;
        cropOverlayCtx.beginPath(); cropOverlayCtx.moveTo(x0, y); cropOverlayCtx.lineTo(x1, y); cropOverlayCtx.stroke();
      }
    }

    // ── 4. Poignées circulaires sur les 4 bords (avec ombre)
    const handles = getCropHandles(x0, y0, x1, y1);
    handles.forEach(hnd => {
      cropOverlayCtx.save();
      cropOverlayCtx.shadowColor = 'rgba(0,0,0,0.5)';
      cropOverlayCtx.shadowBlur = 4;
      cropOverlayCtx.beginPath();
      cropOverlayCtx.arc(hnd.x, hnd.y, 8, 0, Math.PI * 2);
      cropOverlayCtx.fillStyle = '#ffffff';
      cropOverlayCtx.fill();
      cropOverlayCtx.shadowBlur = 0;
      cropOverlayCtx.strokeStyle = 'rgba(0,0,0,0.4)';
      cropOverlayCtx.lineWidth = 1.5;
      cropOverlayCtx.stroke();
      cropOverlayCtx.restore();
    });

    // ── 5. Étiquette dimensions (mm) au centre de la zone conservée
    if (state.dims && cw > 60 && ch > 30) {
      const wFinal = Math.max(0, state.dims.wMm - c.left - c.right);
      const hFinal = Math.max(0, state.dims.hMm - c.top  - c.bottom);
      const label = wFinal.toFixed(1) + ' × ' + hFinal.toFixed(1) + ' mm';
      const cx = x0 + cw / 2, cy = y0 + ch / 2;

      cropOverlayCtx.save();
      cropOverlayCtx.font = 'bold 13px Inter, sans-serif';
      cropOverlayCtx.textAlign = 'center';
      cropOverlayCtx.textBaseline = 'middle';
      const tw = cropOverlayCtx.measureText(label).width + 20;
      // Fond de l'étiquette
      cropOverlayCtx.fillStyle = 'rgba(0,0,0,0.55)';
      const rr = 6;
      const rx = cx - tw / 2, ry = cy - 13;
      cropOverlayCtx.beginPath();
      cropOverlayCtx.roundRect(rx, ry, tw, 26, rr);
      cropOverlayCtx.fill();
      // Texte blanc
      cropOverlayCtx.fillStyle = '#ffffff';
      cropOverlayCtx.fillText(label, cx, cy);
      cropOverlayCtx.restore();
    }
  }

  // Retourne les positions des 4 poignées (centres des 4 bords)
  function getCropHandles(x0, y0, x1, y1) {
    const mx = (x0 + x1) / 2, my = (y0 + y1) / 2;
    return [
      { id: 'top',    x: mx, y: y0 },
      { id: 'bottom', x: mx, y: y1 },
      { id: 'left',   x: x0, y: my },
      { id: 'right',  x: x1, y: my },
    ];
  }

  // Initialise l'overlay de crop (taille + règles + rendu)
  function initCropOverlay() {
    if (!canvas.width || !canvas.height || !state.dims) return;

    // Positionner le cropContainer par-dessus le canvas
    const canvasRect = canvas.getBoundingClientRect();
    const areaRect   = canvasArea.getBoundingClientRect();
    const relLeft = canvasRect.left - areaRect.left;
    const relTop  = canvasRect.top  - areaRect.top;

    cropContainer.style.left   = (relLeft - 30) + 'px'; // -30 pour la règle Y
    cropContainer.style.top    = (relTop  - 30) + 'px'; // -30 pour la règle X
    cropContainer.style.width  = (canvas.width  + 30) + 'px';
    cropContainer.style.height = (canvas.height + 30) + 'px';
    cropContainer.style.display = 'block';

    // Dimensionner les règles
    if (cropRulerX) {
      cropRulerX.style.width = canvas.width + 'px';
      cropRulerX.width  = canvas.width;
      cropRulerX.height = 30;
    }
    if (cropRulerY) {
      cropRulerY.style.height = canvas.height + 'px';
      cropRulerY.width  = 30;
      cropRulerY.height = canvas.height;
    }
    // Dimensionner l'overlay
    if (cropOverlay) {
      cropOverlay.style.width  = canvas.width  + 'px';
      cropOverlay.style.height = canvas.height + 'px';
    }

    drawCropRuler(cropRulerX, state.dims.wMm, false);
    drawCropRuler(cropRulerY, state.dims.hMm, true);
    drawCropOverlay();
    updateCropSizeInfo();
  }

  // Met à jour l'indicateur de taille finale après crop
  function updateCropSizeInfo() {
    if (!state.dims) { $('cropSizeInfo').textContent = '—'; return; }
    const c = state.crop;
    const wFinal = Math.max(0, state.dims.wMm - c.left - c.right);
    const hFinal = Math.max(0, state.dims.hMm - c.top - c.bottom);
    $('cropSizeInfo').textContent = '→ ' + wFinal.toFixed(1) + ' × ' + hFinal.toFixed(1) + ' mm';
  }

  // Lit les inputs mm et met à jour state.crop
  function updateCropFromInputs() {
    state.crop.top    = Math.max(0, parseFloat($('cropTop').value)    || 0);
    state.crop.bottom = Math.max(0, parseFloat($('cropBottom').value) || 0);
    state.crop.left   = Math.max(0, parseFloat($('cropLeft').value)   || 0);
    state.crop.right  = Math.max(0, parseFloat($('cropRight').value)  || 0);
    drawCropOverlay();
    updateCropSizeInfo();
  }

  // Met à jour les inputs depuis state.crop
  function updateInputsFromCrop() {
    $('cropTop').value    = state.crop.top.toFixed(1);
    $('cropBottom').value = state.crop.bottom.toFixed(1);
    $('cropLeft').value   = state.crop.left.toFixed(1);
    $('cropRight').value  = state.crop.right.toFixed(1);
    updateCropSizeInfo();
  }

  // Écoute les inputs
  ['cropTop','cropBottom','cropLeft','cropRight'].forEach(id => {
    const el = $(id);
    if (el) el.addEventListener('input', updateCropFromInputs);
  });

  // Drag des poignées sur l'overlay
  let _cropDrag = null; // { id, startX, startY, startCrop }

  if (cropOverlay) {
    cropOverlay.addEventListener('mousedown', e => {
      if (!state.cropMode || !state.dims) return;
      const rect = cropOverlay.getBoundingClientRect();
      const mx = e.clientX - rect.left, my = e.clientY - rect.top;
      const c = state.crop;
      const topPx    = mmToCanvasPx(c.top,    'y');
      const bottomPx = mmToCanvasPx(c.bottom, 'y');
      const leftPx   = mmToCanvasPx(c.left,   'x');
      const rightPx  = mmToCanvasPx(c.right,  'x');
      const x0 = leftPx, y0 = topPx;
      const x1 = canvas.width - rightPx, y1 = canvas.height - bottomPx;
      const handles = getCropHandles(x0, y0, x1, y1);
      const HIT = 12;
      const hit = handles.find(h => Math.abs(mx - h.x) < HIT && Math.abs(my - h.y) < HIT);
      if (hit) {
        _cropDrag = { id: hit.id, startMx: mx, startMy: my, startCrop: {...state.crop} };
        e.preventDefault();
      }
    });

    document.addEventListener('mousemove', e => {
      if (!_cropDrag || !state.dims) return;
      const rect = cropOverlay.getBoundingClientRect();
      const mx = e.clientX - rect.left, my = e.clientY - rect.top;
      const dx = mx - _cropDrag.startMx, dy = my - _cropDrag.startMy;
      const sc = _cropDrag.startCrop;
      const c = state.crop;
      if (_cropDrag.id === 'top') {
        c.top = Math.max(0, sc.top + canvasPxToMm(dy, 'y'));
      } else if (_cropDrag.id === 'bottom') {
        c.bottom = Math.max(0, sc.bottom - canvasPxToMm(dy, 'y'));
      } else if (_cropDrag.id === 'left') {
        c.left = Math.max(0, sc.left + canvasPxToMm(dx, 'x'));
      } else if (_cropDrag.id === 'right') {
        c.right = Math.max(0, sc.right - canvasPxToMm(dx, 'x'));
      }
      // Sécurité : ne pas dépasser les bords opposés
      const safeMargin = 5; // mm min
      if (c.top + c.bottom >= state.dims.hMm - safeMargin) { c.top = sc.top; c.bottom = sc.bottom; }
      if (c.left + c.right >= state.dims.wMm - safeMargin)  { c.left = sc.left; c.right = sc.right; }
      drawCropOverlay();
      updateInputsFromCrop();
    });

    document.addEventListener('mouseup', () => { _cropDrag = null; });
  }

  // Activer l'aperçu crop
  $('btnActivateCrop') && $('btnActivateCrop').addEventListener('click', () => {
    if (!state.originalImageData) { showToast('<i class="fa fa-exclamation-circle" style="color:#f59e0b"></i> Chargez d\'abord un fichier.', true); return; }
    state.cropMode = true;
    updateCropFromInputs();
    initCropOverlay(); // Positionne et affiche cropContainer par-dessus le canvas
    $('btnActivateCrop').innerHTML = '<i class="fa fa-eye"></i> Aperçu crop actif';
    $('btnActivateCrop').style.background = '#059669';
  });

  // Réinitialiser le crop
  $('btnResetCrop') && $('btnResetCrop').addEventListener('click', () => {
    state.crop = { top: 0, bottom: 0, left: 0, right: 0 };
    updateInputsFromCrop();
    state.cropMode = false;
    // Cacher l'overlay crop
    const cc = $('cropContainer');
    if (cc) cc.style.display = 'none';
    if (cropOverlayCtx) cropOverlayCtx.clearRect(0, 0, cropOverlay.width, cropOverlay.height);
    $('btnActivateCrop').innerHTML = '<i class="fa fa-crop"></i> Activer l\'aperçu crop';
    $('btnActivateCrop').style.background = '';
  });

  // Appliquer & Exporter (crop export)
  $('btnApplyCropExport') && $('btnApplyCropExport').addEventListener('click', async () => {
    if (!state.file) { showToast('<i class="fa fa-exclamation-circle" style="color:#f59e0b"></i> Chargez d\'abord un fichier.', true); return; }
    const c = state.crop;
    const hasAnyCrop = c.top > 0 || c.bottom > 0 || c.left > 0 || c.right > 0;
    if (!hasAnyCrop) { showToast('<i class="fa fa-exclamation-circle" style="color:#f59e0b"></i> Aucune marge de crop définie.', true); return; }

    showSpinner('Rognage en cours...');
    const fd = new FormData();
    fd.append('action', 'crop_pdf');
    fd.append('file', state.file, $('fileNameDisplay').value || state.file.name);
    fd.append('crop', JSON.stringify(c));
    try {
      const res = await fetch('?studio_process', { method: 'POST', body: fd });
      const json = await res.json();
      window.pollStudioTask(json, (finalJson) => {
        if (finalJson.download_url) {
          setPdfReady(finalJson.download_url);
          showResultToast(finalJson.download_url);
        }
      }, (errJson) => {
        showToast('<i class="fa fa-times-circle" style="color:#ef4444"></i> <b>Erreur :</b> ' + (errJson.error || errJson.errors?.join(', ') || 'Erreur inconnue'), true);
      });
    } catch(e) {
      hideSpinner();
      showToast('<i class="fa fa-times-circle" style="color:#ef4444"></i> <b>Erreur réseau :</b> ' + e.message, true);
    }
  });

  // === EXPORT PNG (Canvas) ===
  $('btnExportPng').addEventListener('click', () => {
    const link = document.createElement('a');
    link.download = (state.file ? ($('fileNameDisplay').value || state.file.name).replace(/\.[^.]+$/, '') : 'studio') + '_export.png';
    const c = state.crop;
    const hasAnyCrop = c && (c.top > 0 || c.bottom > 0 || c.left > 0 || c.right > 0);
    if (hasAnyCrop && state.dims) {
      // Appliquer le crop directement sur un canvas temporaire
      const leftPx   = Math.round(mmToCanvasPx(c.left,   'x'));
      const topPx    = Math.round(mmToCanvasPx(c.top,    'y'));
      const rightPx  = Math.round(mmToCanvasPx(c.right,  'x'));
      const bottomPx = Math.round(mmToCanvasPx(c.bottom, 'y'));
      const newW = canvas.width - leftPx - rightPx;
      const newH = canvas.height - topPx - bottomPx;
      if (newW > 0 && newH > 0) {
        const tmp = document.createElement('canvas');
        tmp.width = newW; tmp.height = newH;
        tmp.getContext('2d').drawImage(canvas, leftPx, topPx, newW, newH, 0, 0, newW, newH);
        link.href = tmp.toDataURL('image/png');
        link.click(); return;
      }
    }
    link.href = canvas.toDataURL('image/png');
    link.click();
  });

  // === ENREGISTRER DANS LA BIBLIOTHEQUE ===
  $('btnSaveToLibrary') && $('btnSaveToLibrary').addEventListener('click', async () => {
    if (!state.file && !state.lastServerResultUrl) {
      showToast('<i class="fa fa-exclamation-circle" style="color:#f59e0b"></i> Aucun fichier à enregistrer.', true);
      return;
    }
    
    // Si on est en mode montage, inviter à générer le PDF d'abord si pas déjà fait
    if ($('panelMontage') && $('panelMontage').style.display !== 'none' && !state.lastServerResultUrl) {
      showToast('<i class="fa fa-exclamation-circle" style="color:#f59e0b"></i> Veuillez générer le PDF du montage d\'abord.', true);
      return;
    }

    // === Détection OCR / Texte ===
    // Vérifier si le PDF a du texte extractible (couche OCR)
    if (state.isPdf && state.pdfDoc) {
      showSpinner('Vérification OCR...');
      let hasText = false;
      const maxPages = Math.min(state.pdfDoc.numPages, 3); // Vérifier les 3 premières pages
      for (let p = 1; p <= maxPages; p++) {
        try {
          const page = await state.pdfDoc.getPage(p);
          const tc = await page.getTextContent();
          if (tc.items && tc.items.some(item => (item.str || '').trim().length > 3)) {
            hasText = true;
            break;
          }
        } catch(e) {}
      }
      hideSpinner();
      if (!hasText) {
        const proceed = await new Promise(resolve => {
          const msg = '<i class="fa fa-exclamation-triangle" style="color:#f59e0b"></i> <b>Ce PDF ne contient pas de texte (pas de couche OCR détectée).</b><br>Pour une meilleure indexation, pensez à l\'OCRiser d\'abord (onglet Texte › OCR).<br><br><button id="btnLibConfirmOk" class="toolbar-btn primary" style="margin-right:8px">Ajouter quand même</button><button id="btnLibConfirmCancel" class="toolbar-btn">Annuler</button>';
          showToast(msg, false, 15000);
          document.getElementById('btnLibConfirmOk').onclick = () => { resolve(true); };
          document.getElementById('btnLibConfirmCancel').onclick = () => { resolve(false); };
        });
        if (!proceed) return;
      }
    }

    showSpinner('Enregistrement dans la bibliothèque...');
    try {
      const fd = new FormData();
      let filename = $('fileNameDisplay').value || (state.file ? state.file.name : 'studio_export.pdf');
      if (!filename.toLowerCase().endsWith('.pdf') && !filename.toLowerCase().endsWith('.png')) {
          filename += '.pdf';
      }

      // Si PDF non modifié, passer directement le fichier original (préserve l'OCR)
      const useOriginalFile = state.isPdf && state.file && !hasFiltersModified() && !state.lastServerResultUrl;

      if (state.lastServerResultUrl) {
        // Récupérer le dernier résultat serveur (imposition, fusion, crop, montage...)
        const resp = await fetch(state.lastServerResultUrl);
        const blob = await resp.blob();
        
        // Si l'utilisateur n'a pas tapé de nom personnalisé, on prend celui généré
        if (!$('fileNameDisplay').value) {
            filename = (state.lastServerResultUrl.split('file=').pop() || filename).replace(/%20/g, '_');
            filename = decodeURIComponent(filename);
            if (!filename.toLowerCase().endsWith('.pdf') && !filename.toLowerCase().endsWith('.png')) filename += '.pdf';
        }
        
        fileToUpload = new File([blob], filename, { type: blob.type || 'application/pdf' });
      } else if (useOriginalFile) {
        // PDF non modifié : uploader directement le fichier original pour préserver l'OCR
        fileToUpload = new File([state.file], filename, { type: state.file.type || 'application/pdf' });
      } else {
        // Enregistrer le fichier original (image ou PDF modifié via canvas)
        fileToUpload = state.file;
      }

      fd.append('file', fileToUpload, filename);
      
      const res = await fetch('?upload_bibliotheque', { 
        method: 'POST', 
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });

      if (res.status === 403) {
        hideSpinner();
        if (typeof showBibPasswordModal === 'function') {
            showBibPasswordModal();
            showToast('<i class="fa fa-info-circle" style="color:#3b82f6"></i> <b>Authentification requise</b> Veuillez entrer le mot de passe puis réessayer.', false);
        } else {
            showToast('<i class="fa fa-lock" style="color:#f59e0b"></i> <b>Accès refusé</b> Vous devez être connecté à la bibliothèque.', true);
        }
        return;
      }

      const json = await res.json();
      hideSpinner();
      
      if (json.success) {
        showToast('<i class="fa fa-check-circle" style="color:#10b981"></i> <b>Enregistré !</b> Le fichier a été ajouté à la bibliothèque.', false);
      } else {
        showToast('<i class="fa fa-times-circle" style="color:#ef4444"></i> <b>Erreur :</b> ' + (json.error || 'Erreur inconnue'), true);
      }
    } catch(e) {
      hideSpinner();
      showToast('<i class="fa fa-times-circle" style="color:#ef4444"></i> <b>Erreur réseau :</b> ' + e.message, true);
    }
  });

  // === EXPORT PDF (Canvas → Serveur) ===
  $('btnExportPdf').addEventListener('click', async () => {
    // En mode montage, déléguer au bouton de génération du montage
    if ($('panelMontage') && $('panelMontage').style.display !== 'none') {
      const bm = $('btnGenerateMontage');
      if (bm) { bm.click(); return; }
    }

    if (state.lastServerResultUrl) {
      // Proposer téléchargement + réouverture du dernier résultat serveur
      showResultToast(state.lastServerResultUrl);
      return;
    }
    
    // Si on a un document multi-page, exporter le document complet via l'organiseur
    if (window.orgSequence && window.orgSequence.length > 0) {
      if ($('btnApplyOrg')) {
        $('btnApplyOrg').click();
        return;
      }
    }

    // Si c'est un PDF sans modifications image : proposer directement le fichier original
    // pour préserver la couche OCR, les hyperliens, etc.
    if (state.isPdf && state.file && !hasFiltersModified()) {
      const customName = $('fileNameDisplay').value || state.file.name;
      const fd = new FormData();
      appendStudioFile(fd, customName);
      fd.append('action', 'passthrough_pdf');
      showSpinner('Préparation du PDF...');
      try {
        const res = await fetch('?studio_process', { method: 'POST', body: fd });
        const json = await res.json();
        window.pollStudioTask(json, (finalJson) => {
          if (finalJson.download_url) {
            setPdfReady(finalJson.download_url);
            showResultToast(finalJson.download_url);
          }
        }, (errJson) => {
          showToast('<i class="fa fa-times-circle" style="color:#ef4444"></i> <b>Erreur :</b> ' + (errJson.error || errJson.errors?.join(', ') || 'Erreur inconnue'), true);
        });
      } catch(e) {
        hideSpinner();
        showToast('<i class="fa fa-times-circle" style="color:#ef4444"></i> <b>Erreur réseau :</b> ' + e.message, true);
      }
      return;
    }

    // Sinon, exporter le canvas (avec filtres appliqués)
    showSpinner('Génération du PDF...');
    try {
      const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
      const fd = new FormData();
      fd.append('file', blob, (state.file ? ($('fileNameDisplay').value || state.file.name).replace(/\.[^.]+$/, '') : 'studio') + '_canvas.png');
      fd.append('action', 'to_pdf');
      fd.append('dpi', state.dims ? state.dims.dpi : 96);
      const res = await fetch('?studio_process', { method: 'POST', body: fd });
        const json = await res.json();
        window.pollStudioTask(json, (finalJson) => {
          if (finalJson.download_url) {
            setPdfReady(finalJson.download_url);
            showResultToast(finalJson.download_url);
          }
        }, (errJson) => {
          showToast('<i class="fa fa-times-circle" style="color:#ef4444"></i> <b>Erreur :</b> ' + (errJson.error || errJson.errors?.join(', ') || 'Erreur inconnue'), true);
        });
    } catch(e) {
      hideSpinner();
      showToast('<i class="fa fa-times-circle" style="color:#ef4444"></i> <b>Erreur réseau :</b> ' + e.message, true);
    }
  });

  // === SERVER PROCESS (Imposition & Resize) ===
  function showSpinner(msg) {
    const el = $('studioSpinner');
    $('spinnerMsg').textContent = msg || 'Traitement en cours...';
    el.style.display = 'flex';
  }
  function hideSpinner() { $('studioSpinner').style.display = 'none'; }

  // Affiche un modal persistant avec les boutons
  function showResultToast(downloadUrl, filename) {
    let customName = $('fileNameDisplay') ? $('fileNameDisplay').value : '';
    let targetFile = filename || downloadUrl.split('file=').pop() || '';
    let targetExt = 'pdf';
    
    if (targetFile) {
        let m = targetFile.match(/\.([a-z0-9]+)$/i);
        if (m) targetExt = m[1].toLowerCase();
    }

    let fname = filename || (customName ? customName.replace(/\.[^.]+$/, '') + '.' + targetExt : targetFile || 'fichier');
    fname = decodeURIComponent(fname);
    
    if (fname && !fname.toLowerCase().endsWith('.' + targetExt)) {
        fname += '.' + targetExt;
    }

    if (customName && downloadUrl.indexOf('dl_name=') === -1) {
        downloadUrl += (downloadUrl.indexOf('?') !== -1 ? '&' : '?') + 'dl_name=' + encodeURIComponent(fname);
    }

    const modal = document.getElementById('resultModal');
    document.getElementById('resultModalFilename').textContent = fname;
    document.getElementById('resultModalDownloadBtn').href = downloadUrl;

    const reopenBtn = document.getElementById('resultModalReopenBtn');
      if (fname.toLowerCase().endsWith('.docx') || fname.toLowerCase().endsWith('.odt')) {
        reopenBtn.style.display = 'none';
    } else {
        reopenBtn.style.display = '';
    }

    reopenBtn.onclick = function() {
      modal.style.display = 'none';
      window._reopenInStudio(downloadUrl);
    };
    modal.style.display = 'flex';
  }
  window.showResultToast = showResultToast; // exposé pour studio-montage.js

  // Déduit le type MIME depuis l'extension de fichier
  function mimeFromExt(filename) {
    const ext = (filename.split('.').pop() || '').toLowerCase();
    const map = { pdf: 'application/pdf', png: 'image/png', jpg: 'image/jpeg', jpeg: 'image/jpeg', gif: 'image/gif', webp: 'image/webp' };
    return map[ext] || null;
  }

  // Charge un PDF/image depuis une URL dans le studio
  window._reopenInStudio = async function(url) {
    try {
      showSpinner('Chargement dans le Studio...');
      const resp = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });

      if (resp.status === 403) {
        hideSpinner();
        // Demander le mot de passe bibliothèque
        showBibPasswordModal(url);
        return;
      }
      
      if (!resp.ok) {
        hideSpinner();
        const text = await resp.text();
        alert('Erreur lors du chargement du fichier: ' + (text || resp.statusText));
        return;
      }

      const blob = await resp.blob();

      let fname = 'Document.pdf';
      const cd = resp.headers.get('content-disposition');
      if (cd && cd.includes('filename=')) {
        const match = cd.match(/filename="?([^"]+)"?/);
        if (match) fname = match[1];
      } else {
        fname = (url.split('file=').pop() || 'result.pdf').replace(/%20/g, '_');
      }

      // Forcer le type MIME depuis l'extension plutôt que de faire confiance à blob.type
      const forcedType = mimeFromExt(fname) || blob.type || 'application/pdf';
      const file = new File([blob], decodeURIComponent(fname), { type: forcedType });
      hideSpinner();
      const filtersBtn = document.querySelector('.tool-btn[data-tool="filters"]');
      if (filtersBtn) filtersBtn.click();
      loadFile(file);
      // Mémoriser l'ID bibliothèque APRÈS loadFile (qui reset libraryId)
      const urlParams = new URLSearchParams(url.split('?')[1]);
      const libId = urlParams.get('id');
      if (libId && url.includes('get_bibliotheque_file')) {
        state.libraryId = libId;
      }
    } catch(e) {
      hideSpinner();
      showToast('<i class="fa fa-times-circle" style="color:#ef4444"></i> Erreur lors du rechargement : ' + e.message, true);
    }
  };

  // === MODAL MOT DE PASSE BIBLIOTHÈQUE ===
  function showBibPasswordModal(retryUrl) {
    let modal = document.getElementById('bibPasswordModal');
    if (!modal) {
      modal = document.createElement('div');
      modal.id = 'bibPasswordModal';
      modal.style.cssText = 'display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,0.55);align-items:center;justify-content:center;';
      modal.innerHTML = `
        <div style="background:#fff;border-radius:16px;padding:32px;max-width:360px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.3);font-family:Inter,sans-serif;">
          <div style="font-size:20px;font-weight:700;color:#1e293b;margin-bottom:8px;"><i class="fa fa-lock" style="color:#6366f1;margin-right:8px;"></i>Bibliothèque protégée</div>
          <p style="color:#64748b;font-size:14px;margin-bottom:20px;">Entrez le mot de passe pour accéder aux fichiers de la bibliothèque.</p>
          <input id="bibPasswordInput" type="password" placeholder="Mot de passe" style="width:100%;box-sizing:border-box;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;margin-bottom:8px;outline:none;">
          <div id="bibPasswordError" style="color:#ef4444;font-size:13px;min-height:18px;margin-bottom:12px;"></div>
          <div style="display:flex;gap:8px;">
            <button id="bibPasswordCancel" style="flex:1;padding:10px;border:1.5px solid #e2e8f0;border-radius:8px;background:#fff;cursor:pointer;font-size:14px;">Annuler</button>
            <button id="bibPasswordSubmit" style="flex:2;padding:10px;background:#6366f1;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:14px;font-weight:600;">Confirmer</button>
          </div>
        </div>`;
      document.body.appendChild(modal);
    }
    modal.style.display = 'flex';
    const input = document.getElementById('bibPasswordInput');
    const errDiv = document.getElementById('bibPasswordError');
    const submitBtn = document.getElementById('bibPasswordSubmit');
    const cancelBtn = document.getElementById('bibPasswordCancel');
    input.value = '';
    errDiv.textContent = '';
    setTimeout(() => input.focus(), 50);

    const close = () => { modal.style.display = 'none'; };
    cancelBtn.onclick = close;

    const doSubmit = async () => {
      const pass = input.value;
      if (!pass) return;
      submitBtn.disabled = true;
      submitBtn.textContent = '...';
      errDiv.textContent = '';
      try {
        const fd = new FormData();
        fd.append('bib_pass', pass);
        // POST vers ?bibliotheque, en suivant les redirections manuellement
        const r = await fetch('?bibliotheque', { method: 'POST', body: fd, redirect: 'manual' });
        // status 0 = redirection opaque (fetch redirect:manual) = auth réussie
        if (r.status === 0 || r.status === 200 || r.status === 302) {
          close();
          window._reopenInStudio(retryUrl);
        } else {
          errDiv.textContent = 'Mot de passe incorrect.';
        }
      } catch(e) {
        // Une opaque redirect peut throw selon le navigateur — on considère succès
        close();
        window._reopenInStudio(retryUrl);
      }
      submitBtn.disabled = false;
      submitBtn.textContent = 'Confirmer';
    };
    submitBtn.onclick = doSubmit;
    input.onkeydown = e => { if (e.key === 'Enter') doSubmit(); };
  }

  



  window.pollStudioTask = async function(json, onSuccess, onError) {
    if (json.job_id && (json.status === 'pending' || !json.download_url)) {
      hideSpinner();
      showToast('<i class="fa fa-spinner fa-spin" style="color:#4f46e5"></i> <b>Traitement en arrière-plan :</b> En cours...', false);
      const interval = setInterval(async () => {
        try {
          const stFd = new FormData();
          stFd.append('action', 'task_status');
          stFd.append('job_id', json.job_id);
          const res = await fetch('?studio_process', { method: 'POST', body: stFd });
          const stData = await res.json();
          if (stData.success && stData.job) {
            const job = stData.job;
            if (job.status === 'done') {
              clearInterval(interval);
              onSuccess(job);
            } else if (job.status === 'error') {
              clearInterval(interval);
              onError(job);
            } else if (job.last_log) {
              let txt = job.last_log.substring(0, 60);
              if (job.last_log.length > 60) txt += '...';
              showToast('<i class="fa fa-spinner fa-spin" style="color:#4f46e5"></i> <b>En arrière-plan :</b> ' + txt, false);
            }
          } else if (!stData.success) {
            clearInterval(interval);
            onError(stData);
          }
        } catch (e) {
          clearInterval(interval);
          onError({error: "Erreur réseau lors de la vérification du statut"});
        }
      }, 2000);
      return;
    }

    // Si c'est déjà success ou error et pas de job en arrière-plan
    hideSpinner();
    if (json.success || json.status === 'done') {
      onSuccess(json);
    } else {
      onError(json);
    }
  };

  window.showToast = function showToast(html, isError) {
    if (isError) {
      const existing = document.getElementById('errorModalOverlay');
      if (existing) existing.remove();
      const modalHtml = `
        <div id="errorModalOverlay" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:100000;display:flex;align-items:center;justify-content:center;">
          <div style="background:#fff;border-radius:12px;width:90%;max-width:600px;box-shadow:0 10px 30px rgba(0,0,0,0.2);overflow:hidden;font-family:Inter,sans-serif;">
            <div style="background:#fef2f2;border-bottom:1px solid #fee2e2;padding:16px 20px;display:flex;align-items:center;gap:12px;">
              <i class="fa fa-exclamation-triangle" style="color:#ef4444;font-size:20px;"></i>
              <h3 style="margin:0;color:#991b1b;font-size:16px;font-weight:600;">Information / Erreur</h3>
            </div>
            <div style="padding:20px;max-height:60vh;overflow-y:auto;">
              <div id="errorModalContent" style="color:#374151;font-size:13px;line-height:1.6;background:#f9fafb;padding:16px;border-radius:8px;border:1px solid #e5e7eb;user-select:text;word-break:break-word;">
                ${html}
              </div>
            </div>
            <div style="padding:16px 20px;border-top:1px solid #e2e5ea;display:flex;justify-content:flex-end;gap:12px;background:#f8fafc;">
              <button onclick="navigator.clipboard.writeText(document.getElementById('errorModalContent').innerText); showToast('<i class=\\'fa fa-check\\'></i> Message copié', false);" style="padding:8px 16px;background:#fff;border:1px solid #d1d5db;border-radius:6px;cursor:pointer;font-size:13px;font-weight:500;color:#374151;display:flex;align-items:center;gap:6px;"><i class="fa fa-copy"></i> Copier le message</button>
              <button onclick="document.getElementById('errorModalOverlay').remove()" style="padding:8px 16px;background:#ef4444;border:none;border-radius:6px;color:#fff;cursor:pointer;font-size:13px;font-weight:500;">Fermer</button>
            </div>
          </div>
        </div>
      `;
      document.body.insertAdjacentHTML('beforeend', modalHtml);
      return;
    }

    const t = $('studioToast');
    t.innerHTML = html;
    t.style.borderLeftColor = '#10b981';
    t.style.borderLeftWidth = '4px';
    t.style.display = 'block';
    clearTimeout(t._tid);
    t._tid = setTimeout(() => t.style.display = 'none', 5000);
  }

  async function serverProcess(action, extraFields, spinnerMsg) {
    if (!state.file) { showToast('<b>Aucun fichier chargé.</b> Déposez un fichier d\'abord.', true); return; }
    showSpinner(spinnerMsg);
    const fd = new FormData();
    fd.append('file', state.file, $('fileNameDisplay').value || state.file.name);
    fd.append('action', action);
    Object.entries(extraFields).forEach(([k, v]) => fd.append(k, v));
    try {
      const res = await fetch('?studio_process', { method: 'POST', body: fd });
      const json = await res.json();
      window.pollStudioTask(json, (finalJson) => {
        if (finalJson.download_url) {
          setPdfReady(finalJson.download_url);
          if (finalJson.preview_url && (action === 'impose' || action === 'unimpose')) {
            openImpPreview(finalJson.preview_url, finalJson.download_url);
          } else {
            showResultToast(finalJson.download_url);
          }
        }
      }, (errJson) => {
        const errs = errJson.error || (errJson.errors && errJson.errors.join('<br>')) || 'Erreur inconnue';
        showToast('<i class="fa fa-times-circle" style="color:#ef4444"></i> <b>Erreur :</b><br>' + errs, true);
      });
    } catch(e) {
      hideSpinner();
      showToast('<i class="fa fa-times-circle" style="color:#ef4444"></i> <b>Erreur réseau :</b> ' + e.message, true);
    }
  }

  async function serverProcessMerge(files, spinnerMsg) {
    showSpinner(spinnerMsg);
    const fd = new FormData();
    fd.append('action', 'merge');
    if (state.file) {
      fd.append('file', state.file, $('fileNameDisplay').value || state.file.name); // Inclure le fichier principal s'il y en a un
    }
    for (let i = 0; i < files.length; i++) {
      fd.append('files[]', files[i]);
    }
    try {
      const res = await fetch('?studio_process', { method: 'POST', body: fd });
      const json = await res.json();
      window.pollStudioTask(json, (finalJson) => {
        if (finalJson.download_url) {
          setPdfReady(finalJson.download_url);
          showResultToast(finalJson.download_url);
        }
      }, (errJson) => {
        const errs = errJson.error || (errJson.errors && errJson.errors.join('<br>')) || 'Erreur inconnue';
        showToast('<i class="fa fa-times-circle" style="color:#ef4444"></i> <b>Erreur :</b><br>' + errs, true);
      });
    } catch(e) {
      hideSpinner();
      showToast('<i class="fa fa-times-circle" style="color:#ef4444"></i> <b>Erreur réseau :</b> ' + e.message, true);
    }
  }

  // === MODAL PREVIEW IMPOSITION ===
  function openImpPreview(previewUrl, downloadUrl) {
    const modal   = $('impPreviewModal');
    const img     = $('impPreviewImg');
    const loading = $('impPreviewLoading');
    const dlBtn   = $('impPreviewDownload');
    const loadAppBtn = $('impPreviewLoadApp');

    // Reset
    img.style.display = 'none';
    loading.style.display = 'block';
    $('impPreviewPageLabel').textContent = '(page 1)';
    dlBtn.href = downloadUrl;

    if (loadAppBtn) {
      loadAppBtn.onclick = async () => {
        const originalText = loadAppBtn.innerHTML;
        loadAppBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Chargement...';
        loadAppBtn.disabled = true;
        try {
          const response = await fetch(downloadUrl);
          if (!response.ok) throw new Error('Network error');
          const blob = await response.blob();
          const filename = state.file ? ($('fileNameDisplay').value || state.file.name).replace(/\.[^.]+$/, '') + '_imposé.pdf' : 'document_imposé.pdf';
          const newFile = new File([blob], filename, { type: 'application/pdf' });
          
          $('impPreviewModal').style.display = 'none';
          $('impPreviewImg').src = '';
          
          loadFile(newFile);
          
          // Switch back to standard view to actually see the loaded file
          const btnFilters = document.querySelector('.tool-btn[data-tool="filters"]');
          if (btnFilters) btnFilters.click();
          
          showToast('<i class="fa fa-check-circle" style="color:#10b981"></i> <b>Fichier chargé dans le Studio avec succès.</b>', false);
        } catch (e) {
          showToast('<i class="fa fa-times-circle" style="color:#ef4444"></i> <b>Erreur lors du chargement :</b> ' + e.message, true);
        } finally {
          loadAppBtn.innerHTML = originalText;
          loadAppBtn.disabled = false;
        }
      };
    }

    modal.style.display = 'flex';

    // Charger l'image
    img.onload  = () => { loading.style.display = 'none'; img.style.display = 'block'; };
    img.onerror = () => {
      loading.innerHTML = '<i class="fa fa-exclamation-triangle" style="color:#f59e0b;font-size:24px"></i><br>Aperçu indisponible';
    };
    img.src = previewUrl + '&_t=' + Date.now();
  }

  [$('impPreviewClose'), $('impPreviewCloseBtn')].forEach(btn => btn && btn.addEventListener('click', () => {
    $('impPreviewModal').style.display = 'none';
    $('impPreviewImg').src = '';
  }));
  $('impPreviewModal').addEventListener('click', e => {
    if (e.target === $('impPreviewModal')) {
      $('impPreviewModal').style.display = 'none';
      $('impPreviewImg').src = '';
    }
  });

  $('btnApplyImposition').addEventListener('click', () => {
    if (!state.file || !state.isPdf) {
      showToast('<b>L\'imposition nécessite un PDF.</b>', true); return;
    }
    // Détecter quel onglet est actif
    const activeTab = document.querySelector('.imp-tab.active')?.dataset?.tab || 'brochure';
    let fields = { impose_type: activeTab };

    if (activeTab === 'brochure') {
      const resizeMode = document.querySelector('input[name="bro_resize"]:checked')?.value || 'percent';
      fields = { ...fields,
        impose_type:    'brochure',
        output_format:  $('bro_output_format').value,
        n_up:           $('bro_n_up').value,
        resize_mode:    resizeMode,
        scale:          $('bro_scale').value,
        target_width:   $('bro_target_w').value || '0',
        target_height:  $('bro_target_h').value || '0',
        gutter_x:       $('bro_gutter_x').value,
        gutter_y:       $('bro_gutter_y').value,
        gutter_strategy:$('bro_gutter_strategy').value,
        crop_marks:     $('bro_crop_marks').checked ? '1' : '0',
        crop_style:     $('bro_crop_style').value,
        crop_mark_len:  $('bro_crop_len').value,
        add_page_numbers_in_gutters: $('bro_page_nums').checked ? '1' : '0',
        add_page_numbers_position: $('bro_folio_position').value,
        add_page_numbers_manual_offset: $('bro_page_nums_manual').checked ? '1' : '0',
        gutter_num_offset_x: $('bro_folio_x').value,
        gutter_num_offset_y: $('bro_folio_y').value,
        tete_beche:     $('bro_tumble').checked ? '1' : '0',
      };
    } else if (activeTab === 'livre') {
      const resizeMode = document.querySelector('input[name="liv_resize_mode"]:checked')?.value || 'percent';
      fields = { ...fields,
        impose_type:    'livre',
        output_format:  $('liv_output_format').value,
        n_up:           $('liv_n_up').value,
        resize_mode:    resizeMode,
        scale:          $('liv_scale').value,
        target_width:   $('liv_target_w').value || '0',
        target_height:  $('liv_target_h').value || '0',
        gutter_x:       $('liv_gutter_x').value,
        gutter_y:       $('liv_gutter_y').value,
        gutter_strategy:$('liv_gutter_strategy').value,
        duplex:         '1',
        tete_beche:     $('liv_tete_beche').checked ? '1' : '0',
        crop_marks:     $('liv_crop_marks').checked ? '1' : '0',
        crop_style:     $('liv_crop_style').value,
        crop_mark_len:  $('liv_crop_len').value,
        add_page_numbers_in_gutters: $('liv_page_nums').checked ? '1' : '0',
        add_page_numbers_position: $('liv_folio_position').value,
        add_page_numbers_manual_offset: $('liv_page_nums_manual').checked ? '1' : '0',
        gutter_num_offset_x: $('liv_folio_x').value,
        gutter_num_offset_y: $('liv_folio_y').value,
      };
    } else { // tracts
      fields = { ...fields,
        impose_type:      'tracts',
        output_format:    $('tra_output_format').value,
        manual_format:    $('tra_manual_format').value,
        orientation:      $('tra_orientation').value,
        draw_crop_marks:  $('tra_crop_marks').checked ? '1' : '0',
        keep_original_size: $('tra_keep_size').checked ? '1' : '0',
        force_resize:     $('tra_force_resize').checked ? '1' : '0',
        duplex_mode:      $('tra_duplex_mode').value,
      };
    }
    serverProcess('impose', fields, 'Imposition en cours...');
  });

  // == Tabs imposition ==
  document.querySelectorAll('.imp-tab').forEach(btn => {
    btn.addEventListener('click', () => {
      const tab = btn.dataset.tab;
      document.querySelectorAll('.imp-tab').forEach(b => {
        b.style.color = 'var(--studio-text-muted)';
        b.style.borderBottomColor = 'transparent';
        b.classList.remove('active');
      });
      btn.style.color = 'var(--studio-primary)';
      btn.style.borderBottomColor = 'var(--studio-primary)';
      btn.classList.add('active');
      ['impTabBrochure','impTabLivre','impTabTracts'].forEach(id => $(''+id) && ($(''+id).style.display = 'none'));
      const map = { brochure:'impTabBrochure', livre:'impTabLivre', tracts:'impTabTracts' };
      if ($(''+map[tab])) $(''+map[tab]).style.display = '';
    });
  });

  // == Brochure: toggle échelle % / mm ==
  document.querySelectorAll('input[name="bro_resize"]').forEach(r => {
    r.addEventListener('change', () => {
      const isMm = document.querySelector('input[name="bro_resize"]:checked')?.value === 'mm';
      $('bro_block_percent').style.display = isMm ? 'none' : '';
      $('bro_block_mm').style.display = isMm ? '' : 'none';
    });
  });
  $('bro_scale').addEventListener('input', () => $('bro_scale_val').textContent = $('bro_scale').value);
  $('liv_scale').addEventListener('input', () => $('liv_scale_val').textContent = $('liv_scale').value);


  $('btnApplyResize').addEventListener('click', () => {
    serverProcess('resize', {
      resize_format: $('selResizeFormat').value,
    }, 'Redimensionnement en cours...');
  });

  // === PAGES PANEL ===
  $('sliderDpi').addEventListener('input', () => $('valDpi').textContent = $('sliderDpi').value);
  $('btnPdfToImg').addEventListener('click', () => {
    serverProcess('pdf_to_images', { dpi: $('sliderDpi').value }, 'Extraction des images en cours...');
  });

  let mergeFilesList = [];
  if ($('btnSelectMergeFiles')) {
    $('btnSelectMergeFiles').addEventListener('click', () => $('mergeFileInput').click());
  }
  if ($('mergeFileInput')) {
    $('mergeFileInput').addEventListener('change', (e) => {
      const files = Array.from(e.target.files);
      if (!files.length) return;
      mergeFilesList = mergeFilesList.concat(files);
      $('mergeFileInput').value = ''; // Reset
      renderMergeList();
    });
  }

  function renderMergeList() {
    const list = $('mergeFileList');
    if (!list) return;
    list.innerHTML = '';
    if (mergeFilesList.length === 0) {
      list.innerHTML = '<div style="padding:4px;color:#9ca3af;font-style:italic">Aucun fichier sélectionné</div>';
      if ($('btnApplyMerge')) $('btnApplyMerge').disabled = true;
      return;
    }
    mergeFilesList.forEach((f, i) => {
      const item = document.createElement('div');
      item.style.display = 'flex';
      item.style.justifyContent = 'space-between';
      item.style.padding = '4px 0';
      item.style.borderBottom = '1px solid #f3f4f6';
      
      const name = document.createElement('span');
      name.textContent = f.name;
      name.style.overflow = 'hidden';
      name.style.textOverflow = 'ellipsis';
      name.style.whiteSpace = 'nowrap';
      name.style.maxWidth = '180px';
      
      const remove = document.createElement('span');
      remove.innerHTML = '&times;';
      remove.style.cursor = 'pointer';
      remove.style.color = '#ef4444';
      remove.style.fontWeight = 'bold';
      remove.onclick = () => {
        mergeFilesList.splice(i, 1);
        renderMergeList();
      };
      
      item.appendChild(name);
      item.appendChild(remove);
      list.appendChild(item);
    });
    if ($('btnApplyMerge')) $('btnApplyMerge').disabled = false;
  }

  if ($('btnApplyMerge')) {
    $('btnApplyMerge').addEventListener('click', () => {
      if (mergeFilesList.length === 0) return;
      serverProcessMerge(mergeFilesList, 'Fusion des PDF en cours...');
    });
  }

  $('btnApplyUnimpose').addEventListener('click', () => {
    serverProcess('unimpose', { unimpose_mode: $('selUnimposeMode').value }, 'Désimposition en cours...');
  });

  // Organizer Events
  $('btnOrgAddPdf').addEventListener('click', () => $('orgAddPdfInput').click());
  $('orgAddPdfInput').addEventListener('change', async (e) => {
    if (e.target.files.length > 0) {
      const file = e.target.files[0];
      const reader = new FileReader();
      reader.onload = async (re) => {
        const data = new Uint8Array(re.target.result);
        const doc = await pdfjsLib.getDocument({data}).promise;
        const file_idx = window.orgFiles.length;
        window.orgFiles.push(file);
        window.orgDocs.push(doc);
        const newPages = [];
        for (let i = 1; i <= doc.numPages; i++) {
          newPages.push({ file_idx: file_idx, page_num: i, type: 'page', rotation: 0 });
        }
        
        const pos = $('selOrgBlankPos').value;
        let selIdx = state.orgSelectedIndex;
        if (selIdx === undefined || selIdx === null || selIdx < 0) {
          selIdx = Math.max(0, window.orgSequence.length - 1);
        }

        if (pos === 'end') {
          window.orgSequence.push(...newPages);
        } else if (pos === 'start') {
          window.orgSequence.unshift(...newPages);
        } else if (pos === 'after') {
          window.orgSequence.splice(selIdx + 1, 0, ...newPages);
        } else if (pos === 'before') {
          window.orgSequence.splice(selIdx, 0, ...newPages);
        }
        
        renderThumbnails();
      };
      reader.readAsArrayBuffer(file);
    }
  });

  $('btnOrgAddBlank').addEventListener('click', async () => {
    const pos = $('selOrgBlankPos').value;
    
    // Déterminer l'index de référence (sélectionné ou dernier)
    let selIdx = state.orgSelectedIndex;
    if (selIdx === undefined || selIdx === null || selIdx < 0) {
      selIdx = Math.max(0, window.orgSequence.length - 1);
    }
    let targetIdx = 0;

    // Créer un nouvel objet à chaque insertion (pas de référence partagée)
    if (pos === 'start') {
      window.orgSequence.unshift({ file_idx: null, page_num: null, type: 'blank', rotation: 0 });
      targetIdx = 0;
    } else if (pos === 'before') {
      targetIdx = Math.max(0, selIdx);
      window.orgSequence.splice(targetIdx, 0, { file_idx: null, page_num: null, type: 'blank', rotation: 0 });
    } else if (pos === 'after') {
      targetIdx = Math.min(window.orgSequence.length, selIdx + 1);
      window.orgSequence.splice(targetIdx, 0, { file_idx: null, page_num: null, type: 'blank', rotation: 0 });
    } else { // end
      window.orgSequence.push({ file_idx: null, page_num: null, type: 'blank', rotation: 0 });
      targetIdx = window.orgSequence.length - 1;
    }

    console.log('[Studio] Blank page inserted at index', targetIdx, '| orgSequence length:', window.orgSequence.length, '| position:', pos);

    // Actualiser les vignettes
    await renderThumbnails();
    
    // Forcer l'affichage du canvas et du panneau si c'était vide
    canvas.style.display = 'block';
    $('uploadZone').style.display = 'none';
    panel.classList.add('visible');

    // Sélectionner et afficher la nouvelle page blanche
    state.orgSelectedIndex = targetIdx;
    const thumbs = thumbsBar.querySelectorAll('.thumb-item');
    console.log('[Studio] Thumbs found:', thumbs.length, '| clicking index:', targetIdx);
    if (thumbs[targetIdx]) {
      thumbs[targetIdx].click();
    } else {
      console.warn('[Studio] Thumb not found at index', targetIdx);
      // Fallback: dessiner manuellement la page blanche
      const w = (state.dims && state.dims.wPx) ? state.dims.wPx : 1240;
      const h = (state.dims && state.dims.hPx) ? state.dims.hPx : 1754;
      canvas.width = w; canvas.height = h;
      ctx.fillStyle = 'white';
      ctx.fillRect(0, 0, w, h);
      state.originalImageData = ctx.getImageData(0, 0, w, h);
    }
  });

  if ($('btnOrgReverse')) {
    $('btnOrgReverse').addEventListener('click', async () => {
      window.orgSequence.reverse();
      await renderThumbnails();
      // Click first thumbnail to update canvas
      const thumbs = thumbsBar.querySelectorAll('.thumb-item');
      if (thumbs.length > 0) thumbs[0].click();
    });
  }

  $('btnApplyOrg').addEventListener('click', async () => {
    if (window.orgSequence.length === 0) return;
    showSpinner("Génération du PDF réorganisé...");
    const fd = new FormData();
    fd.append('action', 'organize_pages');
    fd.append('structure', JSON.stringify(window.orgSequence));
    // Append all needed files
    let neededIdxs = new Set(window.orgSequence.filter(s => s.type === 'page').map(s => s.file_idx));
    neededIdxs.forEach(idx => {
      fd.append('file_' + idx, window.orgFiles[idx]);
    });

    try {
      const res = await fetch('?studio_process', { method: 'POST', body: fd });
      const json = await res.json();
      window.pollStudioTask(json, (finalJson) => {
        if (finalJson.download_url) {
          setPdfReady(finalJson.download_url);
          showResultToast(finalJson.download_url);
        }
      }, (errJson) => {
        const errs = errJson.error || (errJson.errors && errJson.errors.join('<br>')) || 'Erreur inconnue';
        showToast('<i class="fa fa-times-circle" style="color:#ef4444"></i> <b>Erreur :</b><br>' + errs, true);
      });
    } catch(e) {
      hideSpinner();
      showToast('<i class="fa fa-times-circle" style="color:#ef4444"></i> <b>Erreur réseau :</b> ' + e.message, true);
    }
  });

  // === LIGHTBOX LOGIC ===
  canvas.addEventListener('click', () => {
    if (window.risoPipetteActive) return; // Don't trigger if pipette is active
    openLightbox();
  });

  async function openLightbox() {
    const lb = $('studioLightbox');
    const lbCanvas = $('lightboxCanvas');
    const lbCtx = lbCanvas.getContext('2d');
    
    showSpinner("Préparation de l'aperçu haute résolution...");
    
    try {
      let renderW, renderH;
      const targetScale = 2.0; // Render at 2x for high quality inspection

      if (state.isPdf && state.pdfDoc) {
        const page = await state.pdfDoc.getPage(state.currentPage);
        const viewport = page.getViewport({ scale: targetScale });
        lbCanvas.width = viewport.width;
        lbCanvas.height = viewport.height;
        await page.render({ canvasContext: lbCtx, viewport: viewport }).promise;
      } else if (state._img) {
        lbCanvas.width = state._img.naturalWidth;
        lbCanvas.height = state._img.naturalHeight;
        lbCtx.drawImage(state._img, 0, 0);
      } else {
        // Fallback to current canvas content
        lbCanvas.width = canvas.width * 2;
        lbCanvas.height = canvas.height * 2;
        lbCtx.drawImage(canvas, 0, 0, lbCanvas.width, lbCanvas.height);
      }

      if (lbCanvas.width === 0 || lbCanvas.height === 0) {
        throw new Error("Impossible de générer l'aperçu : dimensions nulles.");
      }

      hideSpinner();
      lb.style.display = 'flex';
      document.body.style.overflow = 'hidden'; // Block background scroll
    } catch(e) {
      console.error("Lightbox error", e);
      hideSpinner();
    }
  }

  $('btnCloseLightbox').addEventListener('click', () => {
    $('studioLightbox').style.display = 'none';
    document.body.style.overflow = '';
  });

  // === IMPOSITION UI ===
  document.querySelectorAll('input[name="bro_resize_mode"]').forEach(radio => {
    radio.addEventListener('change', e => {
      $('bro_resize_percent_block').style.display = e.target.value === 'percent' ? '' : 'none';
      $('bro_resize_mm_block').style.display = e.target.value === 'mm' ? '' : 'none';
    });
  });
  $('bro_crop_marks').addEventListener('change', e => {
    $('bro_crop_settings').style.display = e.target.checked ? '' : 'none';
  });
  $('bro_page_nums').addEventListener('change', e => {
    $('bro_folio_settings').style.display = e.target.checked ? 'block' : 'none';
    requestStudioPreview();
  });
  $('bro_page_nums_manual').addEventListener('change', e => {
    $('bro_folio_manual_settings').style.display = e.target.checked ? 'block' : 'none';
    $('bro_folio_position_row').style.display = e.target.checked ? 'none' : '';
    $('bro_folio_position').addEventListener('change', requestStudioPreview);
    requestStudioPreview();
  });

  document.querySelectorAll('input[name="liv_resize_mode"]').forEach(radio => {
    radio.addEventListener('change', e => {
      $('liv_resize_percent_block').style.display = e.target.value === 'percent' ? '' : 'none';
      $('liv_resize_mm_block').style.display = e.target.value === 'mm' ? '' : 'none';
    });
  });
  $('liv_crop_marks').addEventListener('change', e => {
    $('liv_crop_settings').style.display = e.target.checked ? '' : 'none';
  });
  $('liv_page_nums').addEventListener('change', e => {
    $('liv_folio_settings').style.display = e.target.checked ? 'block' : 'none';
    requestStudioPreview();
  });
  $('liv_page_nums_manual').addEventListener('change', e => {
    $('liv_folio_manual_settings').style.display = e.target.checked ? 'block' : 'none';
    $('liv_folio_position_row').style.display = e.target.checked ? 'none' : '';
    $('liv_folio_position').addEventListener('change', requestStudioPreview);
    requestStudioPreview();
  });

  // Imposition Tab switching
  document.querySelectorAll('.imp-tab').forEach(btn => {
    btn.addEventListener('click', () => {
      const tab = btn.dataset.tab;
      document.querySelectorAll('.imp-tab').forEach(b => {
        b.classList.toggle('active', b === btn);
        b.style.borderBottomColor = (b === btn) ? 'var(--studio-primary)' : 'transparent';
        b.style.color = (b === btn) ? 'var(--studio-primary)' : 'var(--studio-text-muted)';
      });
      document.querySelectorAll('.imp-tab-content').forEach(c => {
        c.style.display = (c.id === 'impTab' + tab.charAt(0).toUpperCase() + tab.slice(1)) ? 'block' : 'none';
      });
    });
  });

  // === RISO ===
  window.risoChannels = null;
  
  async function initRisoChannels() {
    showSpinner();
    console.log('[Studio] Initializing Riso channels (High-Res)...');
    
    let hiResCanvas = document.createElement('canvas');
    let hiResCtx = hiResCanvas.getContext('2d');
    
    try {
      if (state.isPdf && state.pdfDoc) {
        // Re-render PDF page at high resolution (scale 3.0 for sharp text)
        const page = await state.pdfDoc.getPage(state.currentPage);
        const viewport = page.getViewport({ scale: 3.0 });
        hiResCanvas.width = viewport.width;
        hiResCanvas.height = viewport.height;
        await page.render({ canvasContext: hiResCtx, viewport: viewport }).promise;
      } else if (state._img) {
        // Use natural image resolution
        hiResCanvas.width = state._img.naturalWidth;
        hiResCanvas.height = state._img.naturalHeight;
        hiResCtx.drawImage(state._img, 0, 0);
      } else {
        // Fallback to display canvas
        hiResCanvas.width = canvas.width;
        hiResCanvas.height = canvas.height;
        hiResCtx.drawImage(canvas, 0, 0);
      }

      if (hiResCanvas.width === 0 || hiResCanvas.height === 0) {
        throw new Error("Dimensions source invalides (0x0). Attendez le chargement complet.");
      }

      state.filteredImageData = hiResCtx.getImageData(0, 0, hiResCanvas.width, hiResCanvas.height);
      
      // We still need an Image object for some riso-tools functions
      const imgObj = new Image();
      imgObj.src = hiResCanvas.toDataURL();
      imgObj.onload = () => {
        window.risoBaseImage = imgObj;
        window.risoChannels = {
          RGB: extractRGBChannels(imgObj),
          CMYK: extractCMYKChannels(imgObj),
          '2COLOR': splitGrayscaleInTwo(toGrayscale(state.filteredImageData), 128),
          'AUTO_BICHROMIE': autoBichromieSeparation(state.filteredImageData)
        };
        window.risoChannels['2COLOR'] = { dark: window.risoChannels['2COLOR'].dark, light: window.risoChannels['2COLOR'].light };
        
        console.log('[Studio] Riso channels initialized at ' + hiResCanvas.width + 'x' + hiResCanvas.height);
        renderRisoUI();
        hideSpinner();
      };
    } catch (err) {
      console.error('[Studio] Riso Init Error:', err);
      hideSpinner();
      showToast('Erreur initialisation Riso: ' + err.message, true);
    }
  }

  function renderRisoUI() {
    const mode = $('selRisoMode').value;
    const list = $('risoChannelsList');
    list.innerHTML = '';
    if (!window.risoChannels) return;
    if (!window.risoVisibility) window.risoVisibility = {};
    if (!window.risoVisibility[mode]) window.risoVisibility[mode] = {};
    
    let activeChans = {};
    let defaults = {};
    if (mode === 'RGB') {
      activeChans = { red: 'Rouge', green: 'Vert', blue: 'Bleu' };
      defaults = { red: 'red', green: 'green', blue: 'blue' };
    } else if (mode === 'CMYK') {
      activeChans = { cyan: 'Cyan', magenta: 'Magenta', yellow: 'Jaune', black: 'Noir' };
      defaults = { cyan: 'blue', magenta: 'red', yellow: 'yellow', black: 'black' };
    } else if (mode === '2COLOR') {
      activeChans = { dark: 'Tons Foncés', light: 'Tons Clairs' };
      defaults = { dark: 'black', light: 'red' };
    } else if (mode === 'AUTO_BICHROMIE') {
      const data = window.risoChannels['AUTO_BICHROMIE'];
      if (data) {
        const c1Name = findClosestRisoColor(data.color1.rgb.r, data.color1.rgb.g, data.color1.rgb.b);
        const c2Name = findClosestRisoColor(data.color2.rgb.r, data.color2.rgb.g, data.color2.rgb.b);
        activeChans = { color1: 'Couleur Dominante 1', color2: 'Couleur Dominante 2' };
        defaults = { color1: c1Name, color2: c2Name };
      }
    } else if (mode === 'PIPETTE') {
      const pipDiv = document.createElement('div');
      pipDiv.className = 'riso-channel-item';
      pipDiv.style.background = 'var(--studio-primary-light)';
      pipDiv.style.borderColor = 'var(--studio-primary)';

      const btn = document.createElement('button');
      btn.className = 'panel-btn';
      btn.style.marginBottom = '12px';
      btn.id = 'btnRisoPipetteToggle';
      btn.innerHTML = '<i class="fa fa-eyedropper"></i> Activer Pipette';
      
      const infoDiv = document.createElement('div');
      infoDiv.style.display = 'none';
      infoDiv.id = 'risoPipetteInfo';

      const colorRow = document.createElement('div');
      colorRow.style.display = 'flex'; colorRow.style.alignItems = 'center'; colorRow.style.gap = '8px'; colorRow.style.marginBottom = '8px';
      const colorLbl = document.createElement('span'); colorLbl.style.fontSize = '12px'; colorLbl.textContent = 'Couleur sélectionnée :';
      const colorBox = document.createElement('div'); colorBox.id = 'risoPipetteColorBox'; colorBox.style.width = '24px'; colorBox.style.height = '24px'; colorBox.style.borderRadius = '50%'; colorBox.style.border = '1px solid #ccc';
      colorRow.appendChild(colorLbl); colorRow.appendChild(colorBox);
      infoDiv.appendChild(colorRow);

      const tolDiv = document.createElement('div');
      const tolLbl = document.createElement('div'); tolLbl.style.fontSize = '12px'; tolLbl.innerHTML = 'Tolérance: <span id="valRisoPipetteTol">60</span>';
      const tolSlider = document.createElement('input'); tolSlider.type = 'range'; tolSlider.className = 'panel-slider'; tolSlider.id = 'sliderRisoPipetteTol'; tolSlider.min = '5'; tolSlider.max = '200'; tolSlider.value = '60';
      tolSlider.addEventListener('input', () => { $('valRisoPipetteTol').textContent = tolSlider.value; if (window.risoPickedColor) window.performPipetteIsolation(true); });
      tolDiv.appendChild(tolLbl); tolDiv.appendChild(tolSlider);
      infoDiv.appendChild(tolDiv);

      const extraDiv = document.createElement('div');
      extraDiv.style.margin = '8px 0';
      extraDiv.innerHTML = `
        <div style="font-size:11px;">Contraste: <span id="valRisoPipetteCst">0</span></div>
        <input type="range" id="sliderRisoPipetteCst" min="-100" max="100" value="0" class="panel-slider">
        <div style="font-size:11px;">Luminosité: <span id="valRisoPipetteBrt">0</span></div>
        <input type="range" id="sliderRisoPipetteBrt" min="-100" max="100" value="0" class="panel-slider">
      `;
      infoDiv.appendChild(extraDiv);
      extraDiv.querySelector('#sliderRisoPipetteCst').addEventListener('input', (e) => { $('valRisoPipetteCst').textContent = e.target.value; if (window.risoPickedColor) window.performPipetteIsolation(true); });
      extraDiv.querySelector('#sliderRisoPipetteBrt').addEventListener('input', (e) => { $('valRisoPipetteBrt').textContent = e.target.value; if (window.risoPickedColor) window.performPipetteIsolation(true); });

      const btnAddLayer = document.createElement('button');
      btnAddLayer.className = 'panel-btn primary';
      btnAddLayer.innerHTML = '<i class="fa fa-plus"></i> Ajouter comme couche';
      btnAddLayer.addEventListener('click', () => { window.commitPipetteLayer(); });
      infoDiv.appendChild(btnAddLayer);

      pipDiv.appendChild(btn);
      pipDiv.appendChild(infoDiv);
      list.appendChild(pipDiv);

      window.risoPipetteActive = false;
      btn.addEventListener('click', () => {
        window.risoPipetteActive = !window.risoPipetteActive;
        state.risoShowOriginal = window.risoPipetteActive;
        applyRisoPreview();
        if (window.risoPipetteActive) {
          btn.classList.add('primary');
          btn.innerHTML = '<i class="fa fa-eyedropper"></i> Pipette Active';
          infoDiv.style.display = 'block';
          canvas.style.cursor = 'crosshair';
          canvas.addEventListener('click', window.handleRisoPipetteClick);
        } else {
          btn.classList.remove('primary');
          btn.innerHTML = '<i class="fa fa-eyedropper"></i> Activer Pipette';
          canvas.style.cursor = '';
          canvas.removeEventListener('click', window.handleRisoPipetteClick);
        }
      });

      if (!window.risoChannels['PIPETTE']) window.risoChannels['PIPETTE'] = {};
      activeChans = window.risoChannels['PIPETTE'];
    }

    Object.keys(activeChans).forEach(key => {
      let name = (mode === 'PIPETTE') ? (activeChans[key].name || key) : activeChans[key];
      const isHidden = window.risoVisibility[mode][key] === false;
      
      const item = document.createElement('div');
      item.className = 'riso-channel-item' + (isHidden ? ' is-hidden' : '');
      item.dataset.channel = key;

      const header = document.createElement('div');
      header.className = 'riso-channel-header';
      
      const title = document.createElement('div');
      title.className = 'riso-channel-title';
      const dot = document.createElement('div');
      dot.className = 'riso-channel-color-dot';
      
      const initialColor = (mode === 'PIPETTE' || mode === 'AUTO_BICHROMIE') ? activeChans[key].color : defaults[key];
      dot.style.background = RISO_COLORS[initialColor] ? RISO_COLORS[initialColor].hex : '#ccc';
      
      title.appendChild(dot);
      const nameSpan = document.createElement('span');
      nameSpan.textContent = name;
      title.appendChild(nameSpan);
      header.appendChild(title);

      const visBtn = document.createElement('button');
      visBtn.className = 'riso-visibility-btn' + (isHidden ? ' is-hidden' : '');
      visBtn.innerHTML = `<i class="fa ${isHidden ? 'fa-eye-slash' : 'fa-eye'}"></i>`;
      visBtn.addEventListener('click', () => {
        if (window.risoVisibility[mode][key] === undefined) window.risoVisibility[mode][key] = true;
        window.risoVisibility[mode][key] = !window.risoVisibility[mode][key];
        renderRisoUI();
        applyRisoPreview();
      });
      header.appendChild(visBtn);
      item.appendChild(header);

      const sel = document.createElement('select');
      sel.className = 'panel-select';
      sel.dataset.channel = key;
      Object.keys(RISO_COLORS).forEach(cKey => {
        const opt = document.createElement('option');
        opt.value = cKey;
        opt.textContent = RISO_COLORS[cKey] ? RISO_COLORS[cKey].name : 'Aucun';
        if (mode !== 'PIPETTE' && cKey === defaults[key]) opt.selected = true;
        if (mode === 'PIPETTE' && activeChans[key].color === cKey) opt.selected = true;
        sel.appendChild(opt);
      });
      sel.addEventListener('change', (e) => {
        if (mode === 'PIPETTE' || mode === 'AUTO_BICHROMIE') activeChans[key].color = e.target.value;
        const colorHex = RISO_COLORS[e.target.value] ? RISO_COLORS[e.target.value].hex : '#ccc';
        dot.style.background = colorHex;
        applyRisoPreview();
      });
      item.appendChild(sel);

      const sliders = document.createElement('div');
      sliders.style.display = 'flex'; sliders.style.flexDirection = 'column'; sliders.style.gap = '4px';
      
      const opcBox = document.createElement('div');
      opcBox.innerHTML = `<div style="font-size:10px;">Opacité: <span class="val">100%</span></div>`;
      const opc = document.createElement('input');
      opc.type = 'range'; opc.className = 'panel-slider'; opc.min = '0'; opc.max = '100'; opc.value = '100';
      opc.dataset.channel = key;
      opc.addEventListener('input', (e) => { opcBox.querySelector('.val').textContent = e.target.value + '%'; applyRisoPreview(); });
      opcBox.appendChild(opc);
      sliders.appendChild(opcBox);

      const cstBox = document.createElement('div');
      cstBox.innerHTML = `<div style="font-size:10px;">Contraste: <span class="val">${activeChans[key].contrast || 0}</span></div>`;
      const cst = document.createElement('input');
      cst.type = 'range'; cst.className = 'panel-slider'; cst.min = '-100'; cst.max = '100'; cst.value = activeChans[key].contrast || 0;
      cst.addEventListener('input', (e) => { activeChans[key].contrast = parseInt(e.target.value); cstBox.querySelector('.val').textContent = e.target.value; applyRisoPreview(); });
      cstBox.appendChild(cst);
      sliders.appendChild(cstBox);

      item.appendChild(sliders);

      if (mode === 'PIPETTE') {
        const delBtn = document.createElement('button');
        delBtn.className = 'panel-btn';
        delBtn.style.marginTop = '4px';
        delBtn.style.color = '#ef4444';
        delBtn.innerHTML = '<i class="fa fa-trash"></i> Supprimer';
        delBtn.addEventListener('click', () => { delete window.risoChannels['PIPETTE'][key]; renderRisoUI(); applyRisoPreview(); });
        item.appendChild(delBtn);
      }
      
      list.appendChild(item);
    });

    const simToggle = document.createElement('div');
    simToggle.className = 'riso-channel-item';
    simToggle.style.background = 'var(--studio-primary-light)';
    simToggle.innerHTML = `
      <label style="cursor:pointer; display:flex; align-items:center; justify-content:center; gap:10px; font-weight:600; font-size:13px; color:var(--studio-primary);">
        <input type="checkbox" id="chkRisoSim" ${!state.risoShowOriginal ? 'checked' : ''} style="width:16px; height:16px;"> 
        <i class="fa ${state.risoShowOriginal ? 'fa-eye-slash' : 'fa-eye'}"></i> Simulation Riso Active
      </label>
    `;
    simToggle.querySelector('input').addEventListener('change', (e) => {
      state.risoShowOriginal = !e.target.checked;
      applyRisoPreview();
      renderRisoUI();
    });
    list.prepend(simToggle);
    applyRisoPreview();
  }

  $('selRisoMode').addEventListener('change', () => {
    renderRisoUI();
    applyRisoPreview();
  });

  // PIPETTE Logic
  window.risoPickedColor = null;
  window.handleRisoPipetteClick = function(e) {
    const imgData = state.filteredImageData || state.originalImageData;
    if (!window.risoPipetteActive || !imgData) return;
    const rect = canvas.getBoundingClientRect();
    const scaleX = imgData.width / rect.width;
    const scaleY = imgData.height / rect.height;
    const x = Math.floor((e.clientX - rect.left) * scaleX);
    const y = Math.floor((e.clientY - rect.top) * scaleY);

    const i = (y * imgData.width + x) * 4;
    window.risoPickedColor = {
      r: imgData.data[i],
      g: imgData.data[i+1],
      b: imgData.data[i+2]
    };

    $('risoPipetteColorBox').style.background = `rgb(${window.risoPickedColor.r}, ${window.risoPickedColor.g}, ${window.risoPickedColor.b})`;
    window.performPipetteIsolation(true);
  };

  window.performPipetteIsolation = function(previewOnly = false) {
    if (!window.risoPickedColor || (!state.filteredImageData && !state.originalImageData)) return null;
    const tol = parseInt($('sliderRisoPipetteTol').value);
    const imgData = state.filteredImageData || state.originalImageData;
    // Isolate color from filtered image
    const isolated = isolateColor(imgData, window.risoPickedColor.r, window.risoPickedColor.g, window.risoPickedColor.b, tol);
    
    if (previewOnly) {
      const cst = parseInt($('sliderRisoPipetteCst').value) + 20; // Petit boost de contraste pour la lisibilité
      const brt = parseInt($('sliderRisoPipetteBrt').value);
      const suggestedColor = findClosestRisoColor(window.risoPickedColor.r, window.risoPickedColor.g, window.risoPickedColor.b);
      const colorHex = RISO_COLORS[suggestedColor].hex;

      let processed = isolated;
      processed = applyContrastBrightness(isolated, cst, brt);
      
      const previewData = colorizeWithRiso(processed, colorHex, 1.0);
      
      const tempCanvas = document.createElement('canvas');
      tempCanvas.width = imgData.width;
      tempCanvas.height = imgData.height;
      const tCtx = tempCanvas.getContext('2d');
      tCtx.fillStyle = 'white';
      tCtx.fillRect(0, 0, tempCanvas.width, tempCanvas.height);
      
      const overlay = document.createElement('canvas');
      overlay.width = previewData.width; overlay.height = previewData.height;
      overlay.getContext('2d').putImageData(previewData, 0, 0);
      tCtx.globalCompositeOperation = 'multiply';
      tCtx.drawImage(overlay, 0, 0);
      
      ctx.clearRect(0,0,canvas.width,canvas.height);
      ctx.drawImage(tempCanvas, 0, 0, canvas.width, canvas.height);
      return null;
    }
    return isolated;
  };

  function findClosestRisoColor(r, g, b) {
    let closest = 'black';
    let minDist = Infinity;
    Object.keys(RISO_COLORS).forEach(key => {
      const c = RISO_COLORS[key];
      if (!c.hex) return;
      const cr = parseInt(c.hex.substring(1,3), 16);
      const cg = parseInt(c.hex.substring(3,5), 16);
      const cb = parseInt(c.hex.substring(5,7), 16);
      const dist = Math.sqrt(Math.pow(r-cr,2) + Math.pow(g-cg,2) + Math.pow(b-cb,2));
      if (dist < minDist) {
        minDist = dist;
        closest = key;
      }
    });
    return closest;
  }

  window.commitPipetteLayer = function() {
    if (!window.risoPickedColor) return;
    const isolated = window.performPipetteIsolation(false);
    if (!isolated) return;
    
    // Hide original after commit
    state.risoShowOriginal = false;
    window.risoPipetteActive = false;
    canvas.removeEventListener('click', window.handleRisoPipetteClick);

    if (!window.risoChannels) window.risoChannels = {};
    if (!window.risoChannels['PIPETTE']) window.risoChannels['PIPETTE'] = {};
    
    const layerId = 'layer_' + Date.now();
    const suggestedColor = findClosestRisoColor(window.risoPickedColor.r, window.risoPickedColor.g, window.risoPickedColor.b);
    
    const cst = parseInt($('sliderRisoPipetteCst').value);
    const brt = parseInt($('sliderRisoPipetteBrt').value);
    
    window.risoChannels['PIPETTE'][layerId] = {
      imageData: isolated,
      color: suggestedColor,
      name: 'Couleur R=' + window.risoPickedColor.r + ' G=' + window.risoPickedColor.g + ' B=' + window.risoPickedColor.b,
      contrast: cst,
      brightness: brt
    };
    
    // Reset picker
    window.risoPickedColor = null;
    $('risoPipetteColorBox').style.background = '';
    
    renderRisoUI();
  };

  function applyRisoPreview() {
    console.log('[Studio] applyRisoPreview called');
    if (!window.risoChannels) return;
    const mode = $('selRisoMode').value;
    const layersData = window.risoChannels[mode];
    if (!layersData) {
      const imgData = state.filteredImageData || state.originalImageData;
      if (imgData) ctx.putImageData(imgData, 0, 0);
      return;
    }

    let layersToBlend = [];
    const list = $('risoChannelsList');
    list.querySelectorAll('select').forEach(sel => {
      const key = sel.dataset.channel;
      
      // Respect visibility
      if (window.risoVisibility && window.risoVisibility[mode] && window.risoVisibility[mode][key] === false) {
        return;
      }

      const colorKey = sel.value;
      const opcInput = list.querySelector(`input[type="range"][data-channel="${key}"]`);
      const opacity = parseInt(opcInput ? opcInput.value : 100) / 100;
      
      if (colorKey !== 'none' && RISO_COLORS[colorKey]) {
        const colorHex = RISO_COLORS[colorKey].hex;
        const chanData = layersData[key];
        const imgData = (mode === 'PIPETTE' || mode === 'AUTO_BICHROMIE') ? chanData.imageData : chanData;
        
        if (imgData) {
          let processed = imgData;

          // Per-layer adjustments
          if ((mode === 'PIPETTE' || mode === 'AUTO_BICHROMIE') && chanData) {
            if (chanData.contrast || chanData.brightness) {
              processed = applyContrastBrightness(processed, chanData.contrast || 0, chanData.brightness || 0);
            }
          }

          if (state.risoLevels) {
            processed = posterizeImage(processed, state.risoLevels);
          }
          if (state.risoHalftone) {
            processed = applyHalftone(processed, state.risoHalftone);
          }
          const colorized = colorizeWithRiso(processed, colorHex, 1.0);
          layersToBlend.push({ imageData: colorized, opacity: opacity });
        }
      }
    });

    if (window.risoPipetteActive && window.risoPickedColor) {
      window.performPipetteIsolation(true);
      return;
    }

    if (state.risoShowOriginal) {
      const base = state.filteredImageData || state.originalImageData;
      if (base) {
        // Create a temporary canvas to use drawImage (scaling)
        const temp = document.createElement('canvas');
        temp.width = base.width; temp.height = base.height;
        temp.getContext('2d').putImageData(base, 0, 0);
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(temp, 0, 0, canvas.width, canvas.height);
      }
      return;
    }

    if (layersToBlend.length > 0) {
      const blended = blendLayers(layersToBlend, window.risoBaseImage.width, window.risoBaseImage.height);
      // Scaled display
      const temp = document.createElement('canvas');
      temp.width = blended.width; temp.height = blended.height;
      temp.getContext('2d').putImageData(blended, 0, 0);
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      ctx.drawImage(temp, 0, 0, canvas.width, canvas.height);
    } else {
      const base = state.filteredImageData || state.originalImageData;
      if (base) {
        const temp = document.createElement('canvas');
        temp.width = base.width; temp.height = base.height;
        temp.getContext('2d').putImageData(base, 0, 0);
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(temp, 0, 0, canvas.width, canvas.height);
      }
    }
    setPdfReady(null);
  }

  $('btnRisoPosterize').addEventListener('click', () => {
    console.log('[Studio] Posterize clicked');
    state.risoLevels = parseInt($('sliderRisoLevels').value);
    applyRisoPreview();
  });

  $('sliderRisoLevels').addEventListener('input', () => $('valRisoLevels').textContent = $('sliderRisoLevels').value);

  $('btnRisoHalftone').addEventListener('click', () => {
    console.log('[Studio] Halftone clicked');
    state.risoHalftone = parseInt($('sliderRisoHalftone').value);
    applyRisoPreview();
  });

  $('sliderRisoHalftone').addEventListener('input', () => $('valRisoHalftone').textContent = $('sliderRisoHalftone').value);

  $('btnRisoReset').addEventListener('click', () => {
    state.risoLevels = null;
    state.risoHalftone = null;
    applyFilters();
  });

  $('btnRisoExportZip').addEventListener('click', () => {
    if (!window.risoChannels) return;
    const mode = $('selRisoMode').value;
    const layersData = window.risoChannels[mode];
    if (!layersData) return;
    let toExport = [];
    
    $('risoChannelsList').querySelectorAll('select').forEach(sel => {
      const key = sel.dataset.channel;
      const colorKey = sel.value;
      if (colorKey !== 'none') {
        let imgData = layersData[key];
        if (mode === 'PIPETTE' || mode === 'AUTO_BICHROMIE') {
          imgData = layersData[key].imageData;
        }
        if (imgData) {
          toExport.push({
            name: key + '_' + colorKey,
            imageData: imgData
          });
        }
      }
    });
    
    if (toExport.length > 0) {
      exportLayersAsZip(toExport, 'riso_' + mode.toLowerCase());
    }
  });

  $('btnRisoExportPdf').addEventListener('click', async () => {
    const mode = $('selRisoMode').value;
    const chanList = $('risoChannelsList').querySelectorAll('select');
    let hasActiveLayer = false;
    chanList.forEach(sel => { if(sel.value !== 'none') hasActiveLayer = true; });

    if (!window.risoChannels || !hasActiveLayer) {
      showToast('Aucune donnée Riso à exporter. Veuillez d\'abord choisir un mode de séparation.', true);
      return;
    }
    
    showSpinner('Génération du PDF Riso...');
    const fd = new FormData();
    fd.append('action', 'riso_pdf');
    
    // Utiliser le DPI réel du Master Riso (pour les PDF scale 3.0 = 216 DPI, pour les images = original DPI)
    let exportDpi = state.isPdf ? 216 : (state.dims && state.dims.dpi ? state.dims.dpi : 96);
    fd.append('dpi', exportDpi);
    
    try {
      let pagesToProcess = state.isPdf ? state.totalPages : 1;
      let count = 0;
      
      for (let p = 1; p <= pagesToProcess; p++) {
        if (pagesToProcess > 1) {
          showSpinner(`Génération du PDF Riso... (Page ${p}/${pagesToProcess})`);
        }
        
        let pChannels;
        // Pour la page courante ou si ce n'est pas un PDF, utiliser les calques déjà générés (et la Pipette)
        if (p === state.currentPage || !state.isPdf || mode === 'PIPETTE') {
          pChannels = window.risoChannels[mode];
        } else {
          // Rendu de la page PDF
          const tempCanvas = document.createElement('canvas');
          const tempCtx = tempCanvas.getContext('2d');
          const page = await state.pdfDoc.getPage(p);
          const viewport = page.getViewport({ scale: 3.0 });
          tempCanvas.width = viewport.width;
          tempCanvas.height = viewport.height;
          await page.render({ canvasContext: tempCtx, viewport: viewport }).promise;
          const pImgData = tempCtx.getImageData(0, 0, tempCanvas.width, tempCanvas.height);
          
          if (mode === 'RGB') pChannels = extractRGBChannels(pImgData);
          else if (mode === 'CMYK') pChannels = extractCMYKChannels(pImgData);
          else if (mode === '2COLOR') {
            const raw2c = splitGrayscaleInTwo(toGrayscale(pImgData), 128);
            pChannels = { dark: raw2c.dark, light: raw2c.light };
          }
          else if (mode === 'AUTO_BICHROMIE') {
            const rawAuto = autoBichromieSeparation(pImgData);
            if (rawAuto) pChannels = { color1: rawAuto.color1, color2: rawAuto.color2 };
            else pChannels = {};
          }
        }

        if (!pChannels) continue;

        for (const sel of chanList) {
          const key = sel.dataset.channel;
          const colorKey = sel.value;
          if (colorKey !== 'none') {
            let imgData = pChannels[key];
            if (mode === 'PIPETTE' || mode === 'AUTO_BICHROMIE') imgData = pChannels[key]?.imageData;
            
            if (imgData) {
              if (imgData.width === 0 || imgData.height === 0) {
                console.warn(`Calque vide ignoré: ${key} sur page ${p}`);
                continue;
              }
              
              // Appliquer les effets Riso (contraste, postérisation, trame) sur le calque avant export
              let processed = imgData;
              if ((mode === 'PIPETTE' || mode === 'AUTO_BICHROMIE') && pChannels[key]) {
                if (pChannels[key].contrast || pChannels[key].brightness) {
                  processed = applyContrastBrightness(processed, pChannels[key].contrast || 0, pChannels[key].brightness || 0);
                }
              }
              if (state.risoLevels) processed = posterizeImage(processed, state.risoLevels);
              if (state.risoHalftone) processed = applyHalftone(processed, state.risoHalftone, 45);

              // Créer un canvas temporaire pour exporter le calque en PNG
              const tempCanvas = document.createElement('canvas');
              tempCanvas.width = processed.width;
              tempCanvas.height = processed.height;
              tempCanvas.getContext('2d').putImageData(processed, 0, 0);
              
              const blob = await new Promise(resolve => tempCanvas.toBlob(resolve, 'image/png'));
              fd.append('layers[]', blob, `${key}_${colorKey}_p${p}.png`);
              fd.append('colors[]', colorKey);
              count++;
            }
          }
        }
      }
      
      if (count === 0) {
        hideSpinner();
        showToast('Aucun calque actif à exporter.', true);
        return;
      }
      
      showSpinner('Envoi au serveur...');
      const res = await fetch('?studio_process', { method: 'POST', body: fd });
      const text = await res.text();
      let json;
      try {
        json = JSON.parse(text);
      } catch (err) {
        throw new Error("Réponse serveur invalide: " + text.substring(0, 100));
      }
      hideSpinner();
      
      if (json.success && json.download_url) {
        setPdfReady(json.download_url);
        showResultToast(json.download_url);
      } else {
        showToast('<i class="fa fa-times-circle" style="color:#ef4444"></i> <b>Erreur :</b> ' + (json.errors||[]).join(', '), true);
      }
    } catch(e) {
      hideSpinner();
      showToast('<i class="fa fa-times-circle" style="color:#ef4444"></i> <b>Erreur réseau :</b> ' + e.message, true);
    }
  });

  // === RESET STUDIO ===
  function resetStudio() {
    state.file = null; state.pdfDoc = null; state.originalImageData = null;
    state.totalPages = 0; state.currentPage = 1; state.orgSelectedIndex = 0;
    state.crop = { top: 0, bottom: 0, left: 0, right: 0 };
    state.cropMode = false;
    uploadZone.style.display = '';
    showCanvas(false);
    panel.classList.remove('visible'); thumbsBar.classList.remove('visible');
    thumbsBar.innerHTML = '';
    $('fileInfoBadge').style.display = 'none';
    $('btnNewFile').style.display = 'none';
    $('btnExportPng').style.display = 'none';
    $('btnSaveToLibrary').style.display = 'none';
    $('btnExportPdf').style.display = 'none';
    $('fileDimsDisplay').textContent = '';
    // Reset crop inputs & info
    ['cropTop','cropBottom','cropLeft','cropRight'].forEach(id => { const el = $(id); if(el) el.value = '0'; });
    if ($('cropSizeInfo')) $('cropSizeInfo').textContent = '—';
    if ($('btnActivateCrop')) { $('btnActivateCrop').innerHTML = '<i class="fa fa-crop"></i> Activer l\'aperçu crop'; $('btnActivateCrop').style.background = ''; }
    const co = $('cropOverlay'); if (co) co.getContext('2d').clearRect(0, 0, co.width, co.height);
    fileInput.value = '';
    mergeFilesList = [];
    window.orgSequence = [];
    window.orgDocs = [];
    window.orgFiles = [];
    window.risoChannels = null;
    window.risoBaseImage = null;
    renderMergeList();
  }
  // === OCR & SCAN ===
  $('btnOcrRun').addEventListener('click', async () => {
    if (!state.file || !state.isPdf) {
      showToast('<i class="fa fa-exclamation-triangle"></i> Veuillez d\'abord charger un fichier PDF.', true);
      return;
    }
    const formData = new FormData();
    formData.append('action', 'ocr_cleanup');
    appendStudioFile(formData);
    formData.append('lang', $('selOcrLang').value);
    formData.append('type', $('selOcrType').value);
    formData.append('deskew', $('chkOcrDeskew').checked ? '1' : '0');
    formData.append('clean', $('chkOcrClean').checked ? '1' : '0');
    formData.append('optimize', $('chkOcrOptimize').checked ? '1' : '0');
    const outFormat = $('selOcrOutputFormat').value;
    formData.append('to_docx_flow', outFormat === 'docx_linear' ? '1' : '0');
    formData.append('to_docx_docling', outFormat === 'docx_ia' ? '1' : '0');
    formData.append('to_docx', outFormat === 'docx_layout' ? '1' : '0');
    formData.append('to_odt', '0'); // Plus d'option ODT dans la nouvelle liste

    showSpinner('Traitement OCR en cours... (peut prendre plusieurs minutes)');
    try {
      const res = await fetch('?studio_process', { method: 'POST', body: formData });
      const data = await res.json();
      hideSpinner();
      if (data.success) {
        if (data.job_id) {
          if (typeof closeOcrModal === 'function') closeOcrModal();
          
          showToast('<i class="fa fa-spinner fa-spin" style="color:#4f46e5"></i> OCR en cours... Initialisation', false);
          
          const checkInterval = setInterval(async () => {
            const stFd = new FormData();
            stFd.append('action', 'ocr_status');
            stFd.append('job_id', data.job_id);
            try {
              const stRes = await fetch('?studio_process', { method: 'POST', body: stFd });
              const stData = await stRes.json();
              if (stData.success && stData.job) {
                const job = stData.job;
                if (job.status === 'done') {
                  clearInterval(checkInterval);
                  let dlName = data.filename;
                  if (job.download_url) {
                    try {
                      const urlParams = new URLSearchParams(job.download_url.split('?')[1]);
                      dlName = urlParams.get('dl_name') || urlParams.get('file') || data.filename;
                    } catch(e) {}
                    showResultToast(job.download_url, dlName);
                  } else {
                    showToast('<i class="fa fa-check"></i> Terminé', false);
                  }
                } else if (job.status === 'error') {
                  clearInterval(checkInterval);
                  showToast('<i class="fa fa-times-circle" style="color:#ef4444"></i> Erreur: ' + (job.error || 'inconnue'), true);
                } else {
                  if (job.last_log) {
                    // Limiter la taille du log pour le toast
                    let txt = job.last_log.substring(0, 60);
                    if (job.last_log.length > 60) txt += '...';
                    showToast('<i class="fa fa-spinner fa-spin" style="color:#4f46e5"></i> ' + txt, false);
                  }
                }
              }
            } catch(e) {}
          }, 3000);
          
        } else if (data.download_url) {
          showResultToast(data.download_url, data.filename);
        }
      } else {
        const errorMsg = data.error || (data.errors && data.errors[0]) || 'Erreur inconnue';
        showToast('<i class="fa fa-times-circle" style="color:#ef4444"></i> ' + errorMsg, true);
      }
    } catch (e) {
      hideSpinner();
      showToast('<i class="fa fa-times-circle" style="color:#ef4444"></i> Erreur réseau : ' + e.message, true);
    }
  });

});
</script>
</body>
</html>
