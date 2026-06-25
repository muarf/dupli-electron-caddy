<link rel="stylesheet" href="css/studio.css?v=<?php echo time(); ?>">
<script src="js/build/pdf.js" defer></script>
<script src="js/fabric.min.js" defer></script>
<script src="js/jszip.min.js" defer></script>
<script src="js/riso-tools.js?v=<?php echo time(); ?>" defer></script>
<script src="js/studio-montage.js?v=<?php echo time(); ?>" defer></script>

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
  </aside>

  <!-- === MAIN WORKSPACE === -->
  <main class="studio-workspace">
    <!-- Toolbar -->
    <div class="studio-toolbar">
      <div class="toolbar-title"><i class="fa fa-magic"></i> Dupli Studio</div>
      <span class="file-info-badge" id="fileInfoBadge" style="display:none">
        <i class="fa fa-file"></i> <span id="fileNameDisplay"></span>
        <span id="fileDimsDisplay" style="opacity:0.7;margin-left:6px;font-size:11px"></span>
        <span id="fileInkDisplay" style="margin-left:8px; padding:2px 8px; background:rgba(0,0,0,0.1); border-radius:10px; font-size:11px; font-weight:600; display:none" title="Taux d'encrage moyen (C+M+J+N)"></span>
      </span>
      <div class="toolbar-spacer"></div>
      <button class="toolbar-btn" id="btnNewFile" style="display:none"><i class="fa fa-upload"></i> Nouveau fichier</button>
      <button class="toolbar-btn" id="btnExportPng" style="display:none" title="Exporter le canvas (avec filtres) en PNG"><i class="fa fa-file-image-o"></i> PNG</button>
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

      <canvas id="studioCanvas" style="display:none; box-shadow: 0 4px 12px rgba(0,0,0,0.1); border-radius: 4px; transition: transform 0.2s;"></canvas>
      
      <!-- Montage Canvas Container -->
      <div id="montageCanvasContainer" style="display:none; width:100%; height:100%; position:absolute; top:0; left:0; align-items:center; justify-content:center; overflow:auto; background:var(--studio-bg);">
        <div style="box-shadow: 0 4px 12px rgba(0,0,0,0.1); border-radius:4px; background:white;">
          <canvas id="montageCanvas"></canvas>
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
                <option value="standard">Standard (Autour de chaque pose)</option>
                <option value="spreads" selected>Planches (Autour de chaque paire)</option>
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
          <input type="file" id="orgAddPdfInput" accept=".pdf" style="display:none">
          <button class="toolbar-btn" id="btnOrgAddPdf" style="width:100%"><i class="fa fa-file-pdf-o"></i> Ajouter un PDF</button>
        </div>
        <div class="panel-row" style="margin-top:8px">
          <div class="panel-label">Position d'insertion</div>
          <select class="panel-select" id="selOrgBlankPos">
            <option value="end">À la fin</option>
            <option value="start">Au début</option>
            <option value="before">Avant la page active</option>
            <option value="after">Après la page active</option>
          </select>
        </div>
        <div class="panel-row" style="margin-top:8px">
          <button class="toolbar-btn" id="btnOrgAddBlank" style="width:100%"><i class="fa fa-plus"></i> Insérer page blanche</button>
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
  // === STATE ===
  const state = {
    file: null, isPdf: false, pdfDoc: null, currentPage: 1, totalPages: 0,
    originalImageData: null, rotation: 0, flipH: false, flipV: false,
    dims: null,  // { wPx, hPx, wMm, hMm, label }
    orgSelectedIndex: 0,
    risoLevels: null,
    risoHalftone: null,
    risoShowOriginal: false
  };
  
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

  // === SIDEBAR TOOL SWITCHING ===
  document.querySelectorAll('.tool-btn[data-tool]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const tool = btn.dataset.tool;
      // Show/hide panels
      ['panelFilters','panelImposition','panelGeometry','panelPages','panelRiso','panelMontage'].forEach(p => { if($(p)) $(p).style.display = 'none'; });
      
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
        
        if (state.originalImageData) {
          if (standardCanvas) standardCanvas.style.display = 'block';
          if (uploadZone) uploadZone.style.display = 'none';
        } else {
          if (uploadZone) uploadZone.style.display = 'block';
          if (panel) panel.classList.remove('visible');
        }
        
        if (tool === 'filters') $('panelFilters').style.display = '';
        else if (tool === 'imposition') $('panelImposition').style.display = '';
        else if (tool === 'geometry') $('panelGeometry').style.display = '';
        else if (tool === 'pages') $('panelPages').style.display = '';
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
    const valid = ['image/png','image/jpeg','image/jpg','image/gif','image/webp','application/pdf'];
    if (!valid.includes(file.type)) { alert('Format non supporté'); return; }
    state.file = file;
    state.isPdf = (file.type === 'application/pdf');
    state.rotation = 0; state.flipH = false; state.flipV = false;

    $('fileNameDisplay').textContent = file.name;
    $('fileInfoBadge').style.display = '';
    $('btnNewFile').style.display = '';
    $('btnExportPng').style.display = '';
    $('btnExportPdf').style.display = '';
    uploadZone.style.display = 'none';
    canvas.style.display = 'block';
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
    fd.append('file', state.file);
    fd.append('action', 'analyze_ink');
    
    try {
      const res = await fetch('?studio_process', { method: 'POST', body: fd });
      const json = await res.json();
      if (json.success && json.result) {
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
        try {
          const doc = window.orgDocs[item.file_idx];
          if (doc) {
            const page = await doc.getPage(item.page_num);
            const vp = page.getViewport({scale: 0.2});
            const tc = document.createElement('canvas');
            tc.width = vp.width; tc.height = vp.height;
            await page.render({canvasContext: tc.getContext('2d'), viewport: vp}).promise;
            if (item.rotation) {
              tc.style.transform = `rotate(${item.rotation}deg)`;
            }
            div.appendChild(tc);
          }
        } catch(e) { console.error("Error rendering thumbnail", e); }
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
            state.originalImageData = ctx.getImageData(0, 0, svp.width, svp.height);
            state._dispW = svp.width; state._dispH = svp.height;
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
    thumbsBar.classList.add('visible');
    thumbsBar.appendChild(fragment);
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
      applyFilters();
    });
  });
  $('chkBitmap').addEventListener('change', e => {
    $('bitmapOpts').style.display = e.target.checked ? 'block' : 'none';
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
  $('btnRotateLeft').addEventListener('click', () => rotateCanvas(-90));
  $('btnRotateRight').addEventListener('click', () => rotateCanvas(90));
  $('btnFlipH').addEventListener('click', () => flipCanvas('h'));
  $('btnFlipV').addEventListener('click', () => flipCanvas('v'));

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

  // === EXPORT PNG (Canvas) ===
  $('btnExportPng').addEventListener('click', () => {
    const link = document.createElement('a');
    link.download = (state.file ? state.file.name.replace(/\.[^.]+$/, '') : 'studio') + '_export.png';
    link.href = canvas.toDataURL('image/png');
    link.click();
  });

  // === EXPORT PDF (Canvas → Serveur) ===
  $('btnExportPdf').addEventListener('click', async () => {
    if (state.lastServerResultUrl) {
      // Télécharger le dernier résultat serveur (imposition, fusion, etc.)
      const link = document.createElement('a');
      link.href = state.lastServerResultUrl;
      link.download = '';
      link.click();
      return;
    }
    
    // Si on a un document multi-page, exporter le document complet via l'organiseur
    if (window.orgSequence && window.orgSequence.length > 0) {
      if ($('btnApplyOrg')) {
        $('btnApplyOrg').click();
        return;
      }
    }

    // Sinon, exporter uniquement le canvas actuel
    showSpinner('Génération du PDF...');
    try {
      // Récupérer le canvas courant comme blob PNG
      const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
      const fd = new FormData();
      fd.append('file', blob, (state.file ? state.file.name.replace(/\.[^.]+$/, '') : 'studio') + '_canvas.png');
      fd.append('action', 'to_pdf');
      fd.append('dpi', state.dims ? state.dims.dpi : 96);
      const res = await fetch('?studio_process', { method: 'POST', body: fd });
      const json = await res.json();
      hideSpinner();
      if (json.success && json.download_url) {
        setPdfReady(json.download_url);
        showToast('<i class="fa fa-check-circle" style="color:#10b981"></i> <b>PDF prêt !</b> <a href="' + json.download_url + '" style="color:#4f6ef7;font-weight:600">Télécharger le PDF</a>', false);
      } else {
        showToast('<i class="fa fa-times-circle" style="color:#ef4444"></i> <b>Erreur :</b> ' + (json.errors||[]).join(', '), true);
      }
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
  function showToast(html, isError) {
    const t = $('studioToast');
    t.innerHTML = html;
    t.style.borderLeftColor = isError ? '#ef4444' : '#10b981';
    t.style.borderLeftWidth = '4px';
    t.style.display = 'block';
    clearTimeout(t._tid);
    t._tid = setTimeout(() => t.style.display = 'none', isError ? 8000 : 5000);
  }

  async function serverProcess(action, extraFields, spinnerMsg) {
    if (!state.file) { showToast('<b>Aucun fichier chargé.</b> Déposez un fichier d\'abord.', true); return; }
    showSpinner(spinnerMsg);
    const fd = new FormData();
    fd.append('file', state.file, state.file.name);
    fd.append('action', action);
    Object.entries(extraFields).forEach(([k, v]) => fd.append(k, v));
    try {
      const res = await fetch('?studio_process', { method: 'POST', body: fd });
      const json = await res.json();
      hideSpinner();
      if (json.success && json.download_url) {
        setPdfReady(json.download_url); // Save for contextual export
        if (json.preview_url && (action === 'impose' || action === 'unimpose')) {
          // Ouvrir le modal de preview
          openImpPreview(json.preview_url, json.download_url);
        } else {
          showToast('<i class="fa fa-check-circle" style="color:#10b981"></i> <b>Terminé !</b> <a href="' + json.download_url + '" style="color:#4f6ef7;font-weight:600">Télécharger le fichier</a>', false);
        }
      } else {
        const errs = (json.errors || ['Erreur inconnue']).join('<br>');
        showToast('<i class="fa fa-times-circle" style="color:#ef4444"></i> <b>Erreur :</b><br>' + errs, true);
      }
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
      fd.append('file', state.file, state.file.name); // Inclure le fichier principal s'il y en a un
    }
    for (let i = 0; i < files.length; i++) {
      fd.append('files[]', files[i]);
    }
    try {
      const res = await fetch('?studio_process', { method: 'POST', body: fd });
      const json = await res.json();
      hideSpinner();
      if (json.success && json.download_url) {
        setPdfReady(json.download_url);
        showToast('<i class="fa fa-check-circle" style="color:#10b981"></i> <b>Fusion terminée !</b> <a href="' + json.download_url + '" style="color:#4f6ef7;font-weight:600">Télécharger</a>', false);
      } else {
        const errs = (json.errors || ['Erreur inconnue']).join('<br>');
        showToast('<i class="fa fa-times-circle" style="color:#ef4444"></i> <b>Erreur :</b><br>' + errs, true);
      }
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
          const filename = state.file ? state.file.name.replace(/\.[^.]+$/, '') + '_imposé.pdf' : 'document_imposé.pdf';
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

  // == Brochure: afficher options traits si coché ==
  $('bro_crop_marks').addEventListener('change', () => {
    $('bro_crop_opts').style.display = $('bro_crop_marks').checked ? '' : 'none';
  });

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
        for (let i = 1; i <= doc.numPages; i++) {
          window.orgSequence.push({ file_idx: file_idx, page_num: i, type: 'page', rotation: 0 });
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
      hideSpinner();
      if (json.success && json.download_url) {
        setPdfReady(json.download_url);
        showToast('<i class="fa fa-check-circle" style="color:#10b981"></i> <b>Réorganisation terminée !</b> <a href="' + json.download_url + '" style="color:#4f6ef7;font-weight:600">Télécharger</a>', false);
      } else {
        const errs = (json.errors || ['Erreur inconnue']).join('<br>');
        showToast('<i class="fa fa-times-circle" style="color:#ef4444"></i> <b>Erreur :</b><br>' + errs, true);
      }
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
    const layersData = window.risoChannels[mode];
    if (!layersData) {
      showToast('Aucune donnée Riso à exporter. Veuillez d\'abord choisir un mode de séparation.', true);
      return;
    }
    
    showSpinner('Génération du PDF Riso...');
    const fd = new FormData();
    fd.append('action', 'riso_pdf');
    
    // Utiliser le DPI réel du Master Riso (pour les PDF scale 3.0 = 216 DPI, pour les images = original DPI)
    let exportDpi = 96;
    if (state.isPdf) {
        exportDpi = 216; // 72 * 3.0 scale
    } else if (state.dims && state.dims.dpi) {
        exportDpi = state.dims.dpi;
    }
    fd.append('dpi', exportDpi);
    
    const chanList = $('risoChannelsList').querySelectorAll('select');
    let count = 0;
    
    try {
      for (const sel of chanList) {
        const key = sel.dataset.channel;
        const colorKey = sel.value;
        if (colorKey !== 'none') {
          let imgData = layersData[key];
          if (mode === 'PIPETTE' || mode === 'AUTO_BICHROMIE') imgData = layersData[key].imageData;
          
          if (imgData) {
            if (imgData.width === 0 || imgData.height === 0) {
              console.warn("Calque vide ignoré:", key);
              continue;
            }
            // Créer un canvas temporaire pour exporter le calque en PNG
            const tempCanvas = document.createElement('canvas');
            tempCanvas.width = imgData.width;
            tempCanvas.height = imgData.height;
            tempCanvas.getContext('2d').putImageData(imgData, 0, 0);
            
            const blob = await new Promise(resolve => tempCanvas.toBlob(resolve, 'image/png'));
            fd.append('layers[]', blob, `${key}_${colorKey}.png`);
            fd.append('colors[]', colorKey);
            count++;
          }
        }
      }
      
      if (count === 0) {
        hideSpinner();
        showToast('Aucun calque actif à exporter.', true);
        return;
      }
      
      const res = await fetch('?studio_process', { method: 'POST', body: fd });
      const json = await res.json();
      hideSpinner();
      
      if (json.success && json.download_url) {
        const link = document.createElement('a');
        link.href = json.download_url;
        link.download = '';
        link.click();
        showToast('<i class="fa fa-check-circle" style="color:#10b981"></i> <b>PDF Riso prêt !</b> Le téléchargement a commencé.', false);
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
    uploadZone.style.display = ''; canvas.style.display = 'none';
    panel.classList.remove('visible'); thumbsBar.classList.remove('visible');
    thumbsBar.innerHTML = '';
    $('fileInfoBadge').style.display = 'none';
    $('btnNewFile').style.display = 'none';
    $('btnExportPng').style.display = 'none';
    $('btnExportPdf').style.display = 'none';
    $('fileDimsDisplay').textContent = '';
    fileInput.value = '';
    mergeFilesList = [];
    window.orgSequence = [];
    window.orgDocs = [];
    window.orgFiles = [];
    window.risoChannels = null;
    window.risoBaseImage = null;
    renderMergeList();
  }
});
</script>
