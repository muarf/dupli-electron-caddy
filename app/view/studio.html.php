<link rel="stylesheet" href="css/studio.css">
<script src="js/build/pdf.js" defer></script>
<script src="js/jszip.min.js" defer></script>
<script src="js/riso-tools.js" defer></script>

<div class="studio-layout" id="studioApp">

  <!-- === SIDEBAR === -->
  <aside class="studio-sidebar">
    <button class="tool-btn active" data-tool="filters" title="Filtres"><i class="fa fa-sliders"></i>Filtres</button>
    <button class="tool-btn" data-tool="geometry" title="Géométrie"><i class="fa fa-crop"></i>Géométrie</button>
    <div class="sidebar-divider"></div>
    <button class="tool-btn" data-tool="imposition" title="Imposition"><i class="fa fa-book"></i>Imposition</button>
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
      </span>
      <div class="toolbar-spacer"></div>
      <button class="toolbar-btn" id="btnNewFile" style="display:none"><i class="fa fa-upload"></i> Nouveau fichier</button>
      <button class="toolbar-btn" id="btnExportPng" style="display:none" title="Exporter le canvas (avec filtres) en PNG"><i class="fa fa-file-image-o"></i> PNG</button>
      <button class="toolbar-btn primary" id="btnExportPdf" style="display:none" title="Exporter en PDF via serveur"><i class="fa fa-file-pdf-o"></i> PDF</button>
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
      <canvas id="studioCanvas" style="display:none"></canvas>
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
          <div class="panel-section-title">Format</div>
          <div class="panel-row">
            <div class="panel-label">Sortie</div>
            <select class="panel-select" id="bro_output_format"><option value="A3">A3 (défaut)</option><option value="A4">A4</option></select>
          </div>
          <div class="panel-row">
            <div class="panel-label">N-up (poses)</div>
            <select class="panel-select" id="bro_n_up">
              <option value="2">2 pages / feuille (A5→A4, A4→A3)</option>
              <option value="4">4 pages / feuille</option>
              <option value="8">8 pages / feuille</option>
            </select>
          </div>
        </div>
        <div class="panel-section">
          <div class="panel-section-title">Échelle</div>
          <div class="panel-row">
            <div style="display:flex;gap:8px;margin-bottom:8px">
              <label style="font-size:12px;cursor:pointer"><input type="radio" name="bro_resize" value="percent" checked> %</label>
              <label style="font-size:12px;cursor:pointer"><input type="radio" name="bro_resize" value="mm"> mm</label>
            </div>
          </div>
          <div id="bro_block_percent" class="panel-row">
            <div class="panel-label">Échelle <span class="panel-value" id="bro_scale_val">100</span>%</div>
            <input type="range" class="panel-slider" id="bro_scale" min="10" max="120" value="100">
          </div>
          <div id="bro_block_mm" class="panel-row" style="display:none">
            <div style="display:flex;gap:6px">
              <input type="number" class="panel-select" id="bro_target_w" placeholder="L (mm)" style="width:48%">
              <input type="number" class="panel-select" id="bro_target_h" placeholder="H (mm)" style="width:48%">
            </div>
          </div>
        </div>
        <div class="panel-section">
          <div class="panel-section-title">Gouttières (mm)</div>
          <div style="display:flex;gap:6px" class="panel-row">
            <input type="number" class="panel-select" id="bro_gutter_x" value="0" min="0" step="0.5" style="width:48%" placeholder="X (mm)">
            <input type="number" class="panel-select" id="bro_gutter_y" value="0" min="0" step="0.5" style="width:48%" placeholder="Y (mm)">
          </div>
          <div class="panel-row">
            <div class="panel-label">Si manque de place</div>
            <select class="panel-select" id="bro_gutter_strategy">
              <option value="reduce">Réduire l'échelle</option>
              <option value="crop">Rogner</option>
            </select>
          </div>
        </div>
        <div class="panel-section">
          <div class="panel-section-title">Options</div>
          <div class="panel-row"><label class="panel-checkbox"><input type="checkbox" id="bro_crop_marks"> Traits de coupe</label></div>
          <div id="bro_crop_opts" style="display:none;margin-top:8px">
            <div class="panel-row">
              <div class="panel-label">Style</div>
              <select class="panel-select" id="bro_crop_style">
                <option value="standard">Standard</option>
                <option value="spreads">Spreads</option>
                <option value="booklet">Booklet</option>
              </select>
            </div>
          </div>
          <div class="panel-row"><label class="panel-checkbox"><input type="checkbox" id="bro_page_nums"> Numéros dans gouttières</label></div>
        </div>
      </div>

      <!-- == TAB LIVRE == -->
      <div id="impTabLivre" class="imp-tab-content" style="display:none;padding:0">
        <div class="panel-section">
          <div class="panel-section-title">Format</div>
          <div class="panel-row">
            <div class="panel-label">Sortie</div>
            <select class="panel-select" id="liv_output_format"><option value="A3">A3</option><option value="A4">A4</option></select>
          </div>
          <div class="panel-row">
            <div class="panel-label">N-up</div>
            <select class="panel-select" id="liv_n_up">
              <option value="2">2 poses</option>
              <option value="4">4 poses</option>
            </select>
          </div>
        </div>
        <div class="panel-section">
          <div class="panel-section-title">Échelle</div>
          <div class="panel-row">
            <div class="panel-label">Échelle <span class="panel-value" id="liv_scale_val">100</span>%</div>
            <input type="range" class="panel-slider" id="liv_scale" min="10" max="120" value="100">
          </div>
          <div style="display:flex;gap:6px" class="panel-row">
            <input type="number" class="panel-select" id="liv_gutter_x" value="0" min="0" step="0.5" style="width:48%" placeholder="Gouttière X">
            <input type="number" class="panel-select" id="liv_gutter_y" value="0" min="0" step="0.5" style="width:48%" placeholder="Gouttière Y">
          </div>
        </div>
        <div class="panel-section">
          <div class="panel-section-title">Options</div>
          <div class="panel-row"><label class="panel-checkbox"><input type="checkbox" id="liv_duplex" checked> Recto-Verso (duplex)</label></div>
          <div class="panel-row"><label class="panel-checkbox"><input type="checkbox" id="liv_tete_beche"> Tête-bêche</label></div>
          <div class="panel-row"><label class="panel-checkbox"><input type="checkbox" id="liv_crop_marks"> Traits de coupe</label></div>
          <div class="panel-row"><label class="panel-checkbox"><input type="checkbox" id="liv_page_nums"> Numéros dans gouttières</label></div>
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
        <div class="panel-section-title">Fusionner des PDFs</div>
        <div class="panel-row">
          <input type="file" id="mergeFileInput" accept=".pdf" multiple style="display:none">
          <button class="toolbar-btn" id="btnSelectMergeFiles" style="width:100%"><i class="fa fa-plus"></i> Ajouter des PDFs...</button>
        </div>
        <div id="mergeFileList" style="margin-top:8px;font-size:12px;color:#6b7280;max-height:100px;overflow-y:auto;">
          <!-- List of files to merge -->
        </div>
        <div class="panel-row" style="margin-top:12px">
          <button class="toolbar-btn primary" id="btnApplyMerge" style="width:100%" disabled><i class="fa fa-compress"></i> Fusionner</button>
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
            <option value="RGB">RGB (3 Couleurs)</option>
            <option value="CMYK">CMJN (4 Couleurs)</option>
            <option value="2COLOR">2 Tambours (Clair/Foncé)</option>
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
        <div class="panel-row" style="margin-top:12px">
          <button class="toolbar-btn primary" id="btnRisoExportZip" style="width:100%"><i class="fa fa-file-archive-o"></i> Exporter le ZIP</button>
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
    dims: null  // { wPx, hPx, wMm, hMm, label }
  };
  
  window.orgSequence = [];
  window.orgDocs = [];
  window.orgFiles = [];
  
  // === DOM REFS ===
  const $ = id => document.getElementById(id);
  const uploadZone = $('uploadZone'), fileInput = $('studioFileInput');
  const canvas = $('studioCanvas'), ctx = canvas.getContext('2d');
  const panel = $('studioPanel'), thumbsBar = $('thumbsBar');
  const canvasArea = $('canvasArea');

  // === SIDEBAR TOOL SWITCHING ===
  document.querySelectorAll('.tool-btn[data-tool]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const tool = btn.dataset.tool;
      // Show/hide panels
      ['panelFilters','panelImposition','panelGeometry','panelPages','panelRiso'].forEach(p => { if($(p)) $(p).style.display = 'none'; });
      if (tool === 'filters') $('panelFilters').style.display = '';
      else if (tool === 'imposition') $('panelImposition').style.display = '';
      else if (tool === 'geometry') $('panelGeometry').style.display = '';
      else if (tool === 'pages') $('panelPages').style.display = '';
      else if (tool === 'riso') {
        $('panelRiso').style.display = '';
        if (state.originalImageData && !window.risoChannels) initRisoChannels();
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
        $('fileDimsDisplay').textContent = img.naturalWidth + '×' + img.naturalHeight + 'px (' + wMm + '×' + hMm + 'mm)';
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
        $('fileDimsDisplay').textContent = state.totalPages + 'p. — ' + wMm + '×' + hMm + 'mm';
        renderThumbnails();
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
    canvas.width = svp.width; canvas.height = svp.height;
    await page.render({canvasContext: ctx, viewport: svp}).promise;
    state.originalImageData = ctx.getImageData(0, 0, svp.width, svp.height);
    state._dispW = svp.width; state._dispH = svp.height;
    // Update page nav
    const nav = thumbsBar.querySelector('.page-nav');
    if (nav) nav.textContent = num + ' / ' + state.totalPages;
    // Highlight active thumb
    thumbsBar.querySelectorAll('.thumb-item').forEach((t,i) => {
      t.classList.toggle('active', i === num - 1);
    });
  }

  async function renderThumbnails() {
    if (orgSequence.length === 0) {
      thumbsBar.innerHTML = '';
      thumbsBar.classList.remove('visible');
      return;
    }
    thumbsBar.innerHTML = '';
    thumbsBar.classList.add('visible');

    // Add info span
    const navSpan = document.createElement('span');
    navSpan.className = 'page-nav';
    navSpan.textContent = orgSequence.length + ' page(s)';
    thumbsBar.appendChild(navSpan);

    for (let i = 0; i < orgSequence.length; i++) {
      const item = orgSequence[i];
      const div = document.createElement('div');
      div.className = 'thumb-item' + (i === state.currentPage - 1 ? ' active' : '');
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
          const doc = orgDocs[item.file_idx];
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

      // Actions hover overlay
      const acts = document.createElement('div');
      acts.className = 'thumb-actions';
      acts.innerHTML = `
        <i class="fa fa-rotate-right" onclick="event.stopPropagation(); orgRotate(${i}, 90)" title="Pivoter"></i>
        <i class="fa fa-trash" style="color:#ef4444" onclick="event.stopPropagation(); orgDelete(${i})" title="Supprimer"></i>
      `;
      div.appendChild(acts);

      // Click to view (if main file)
      if (item.type === 'page' && item.file_idx === 0) {
        div.addEventListener('click', () => { state.currentPage = item.page_num; renderPdfPage(item.page_num); applyFilters(); });
      }

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
          const moved = orgSequence.splice(fromIdx, 1)[0];
          orgSequence.splice(toIdx, 0, moved);
          renderThumbnails();
        }
      });

      thumbsBar.appendChild(div);
    }
  }

  window.orgRotate = function(idx, angle) {
    if (orgSequence[idx]) {
      orgSequence[idx].rotation = ((orgSequence[idx].rotation || 0) + angle) % 360;
      renderThumbnails();
    }
  };

  window.orgDelete = function(idx) {
    orgSequence.splice(idx, 1);
    renderThumbnails();
  };

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
        if (json.preview_url && action === 'impose') {
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

    // Reset
    img.style.display = 'none';
    loading.style.display = 'block';
    $('impPreviewPageLabel').textContent = '(page 1)';
    dlBtn.href = downloadUrl;

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
        add_page_numbers_in_gutters: $('bro_page_nums').checked ? '1' : '0',
      };
    } else if (activeTab === 'livre') {
      fields = { ...fields,
        impose_type:    'livre',
        output_format:  $('liv_output_format').value,
        n_up:           $('liv_n_up').value,
        scale:          $('liv_scale').value,
        gutter_x:       $('liv_gutter_x').value,
        gutter_y:       $('liv_gutter_y').value,
        duplex:         $('liv_duplex').checked ? '1' : '0',
        tete_beche:     $('liv_tete_beche').checked ? '1' : '0',
        crop_marks:     $('liv_crop_marks').checked ? '1' : '0',
        add_page_numbers_in_gutters: $('liv_page_nums').checked ? '1' : '0',
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
  $('btnSelectMergeFiles').addEventListener('click', () => $('mergeFileInput').click());
  $('mergeFileInput').addEventListener('change', (e) => {
    const files = Array.from(e.target.files);
    if (!files.length) return;
    mergeFilesList = mergeFilesList.concat(files);
    $('mergeFileInput').value = ''; // Reset
    renderMergeList();
  });

  function renderMergeList() {
    const list = $('mergeFileList');
    list.innerHTML = '';
    if (mergeFilesList.length === 0) {
      list.innerHTML = '<div style="padding:4px;color:#9ca3af;font-style:italic">Aucun fichier sélectionné</div>';
      $('btnApplyMerge').disabled = true;
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
    $('btnApplyMerge').disabled = false;
  }

  $('btnApplyMerge').addEventListener('click', () => {
    if (mergeFilesList.length === 0) return;
    serverProcessMerge(mergeFilesList, 'Fusion des PDF en cours...');
  });

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

  $('btnOrgAddBlank').addEventListener('click', () => {
    window.orgSequence.push({ file_idx: null, page_num: null, type: 'blank', rotation: 0 });
    renderThumbnails();
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

  // === RISO ===
  window.risoChannels = null;
  
  function initRisoChannels() {
    if (!state.originalImageData || typeof extractRGBChannels === 'undefined') return;
    
    // Create an offscreen canvas to hold the current image data
    const tempCanvas = document.createElement('canvas');
    tempCanvas.width = state.originalImageData.width;
    tempCanvas.height = state.originalImageData.height;
    const tCtx = tempCanvas.getContext('2d');
    tCtx.putImageData(state.originalImageData, 0, 0);
    
    const imgObj = new Image();
    imgObj.src = tempCanvas.toDataURL();
    imgObj.onload = () => {
      window.risoBaseImage = imgObj;
      window.risoChannels = {
        RGB: extractRGBChannels(imgObj),
        CMYK: extractCMYKChannels(imgObj),
        '2COLOR': splitGrayscaleInTwo(toGrayscale(state.originalImageData), 128)
      };
      // Map names
      window.risoChannels['2COLOR'] = { dark: window.risoChannels['2COLOR'].dark, light: window.risoChannels['2COLOR'].light };
      
      renderRisoUI();
    };
  }

  function renderRisoUI() {
    const mode = $('selRisoMode').value;
    const list = $('risoChannelsList');
    list.innerHTML = '';
    if (!window.risoChannels) return;
    
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
    } else if (mode === 'PIPETTE') {
      // Special interface for PIPETTE
      // 1. Controls for picking a new color
      const pipDiv = document.createElement('div');
      pipDiv.style.marginBottom = '8px';
      pipDiv.style.padding = '8px';
      pipDiv.style.background = '#f9fafb';
      pipDiv.style.borderRadius = '6px';

      const btn = document.createElement('button');
      btn.className = 'toolbar-btn';
      btn.style.width = '100%';
      btn.style.marginBottom = '12px';
      btn.id = 'btnRisoPipetteToggle';
      btn.innerHTML = '<i class="fa fa-eyedropper"></i> Activer Pipette';
      
      const infoDiv = document.createElement('div');
      infoDiv.style.display = 'none';
      infoDiv.id = 'risoPipetteInfo';

      // Picked color display
      const colorRow = document.createElement('div');
      colorRow.style.display = 'flex';
      colorRow.style.alignItems = 'center';
      colorRow.style.gap = '8px';
      colorRow.style.marginBottom = '8px';
      const colorLbl = document.createElement('span');
      colorLbl.style.fontSize = '12px';
      colorLbl.textContent = 'Couleur sélectionnée :';
      const colorBox = document.createElement('div');
      colorBox.id = 'risoPipetteColorBox';
      colorBox.style.width = '24px';
      colorBox.style.height = '24px';
      colorBox.style.border = '1px solid #ccc';
      colorRow.appendChild(colorLbl);
      colorRow.appendChild(colorBox);
      infoDiv.appendChild(colorRow);

      // Tolerance slider
      const tolDiv = document.createElement('div');
      tolDiv.style.marginBottom = '8px';
      const tolLbl = document.createElement('div');
      tolLbl.style.fontSize = '12px';
      tolLbl.innerHTML = 'Tolérance: <span id="valRisoPipetteTol">30</span>';
      const tolSlider = document.createElement('input');
      tolSlider.type = 'range';
      tolSlider.className = 'panel-slider';
      tolSlider.id = 'sliderRisoPipetteTol';
      tolSlider.min = '5'; tolSlider.max = '100'; tolSlider.value = '30';
      tolSlider.addEventListener('input', () => {
        $('valRisoPipetteTol').textContent = tolSlider.value;
        if (window.risoPickedColor) window.performPipetteIsolation(true);
      });
      tolDiv.appendChild(tolLbl);
      tolDiv.appendChild(tolSlider);
      infoDiv.appendChild(tolDiv);

      // Add as layer button
      const btnAddLayer = document.createElement('button');
      btnAddLayer.className = 'toolbar-btn primary';
      btnAddLayer.style.width = '100%';
      btnAddLayer.innerHTML = '<i class="fa fa-plus"></i> Ajouter comme couche';
      btnAddLayer.addEventListener('click', () => {
        window.commitPipetteLayer();
      });
      infoDiv.appendChild(btnAddLayer);

      pipDiv.appendChild(btn);
      pipDiv.appendChild(infoDiv);
      list.appendChild(pipDiv);

      // Pipette toggle logic
      window.risoPipetteActive = false;
      btn.addEventListener('click', () => {
        window.risoPipetteActive = !window.risoPipetteActive;
        if (window.risoPipetteActive) {
          btn.style.background = '#10b981';
          btn.style.color = '#fff';
          btn.innerHTML = '<i class="fa fa-eyedropper"></i> Pipette Active';
          infoDiv.style.display = 'block';
          canvas.style.cursor = 'crosshair';
          canvas.addEventListener('click', window.handleRisoPipetteClick);
        } else {
          btn.style.background = '';
          btn.style.color = '';
          btn.innerHTML = '<i class="fa fa-eyedropper"></i> Activer Pipette';
          canvas.style.cursor = '';
          canvas.removeEventListener('click', window.handleRisoPipetteClick);
        }
      });

      // 2. Render committed layers
      if (!window.risoChannels['PIPETTE']) window.risoChannels['PIPETTE'] = {};
      activeChans = window.risoChannels['PIPETTE'];
      // The rest of the function will render activeChans (the committed layers)
    }

    Object.keys(activeChans).forEach(key => {
      // For PIPETTE, key is 'layer_1', 'layer_2', etc.
      let name = activeChans[key].name || key;
      if (mode !== 'PIPETTE') name = activeChans[key]; // Because for RGB, activeChans is {red: 'Rouge'}
      
      const div = document.createElement('div');
      div.style.marginBottom = '8px';
      div.style.padding = '8px';
      div.style.background = '#f9fafb';
      div.style.borderRadius = '6px';
      
      const lbl = document.createElement('div');
      lbl.style.fontWeight = '600';
      lbl.style.fontSize = '12px';
      lbl.style.marginBottom = '4px';
      lbl.textContent = name;
      div.appendChild(lbl);
      
      const sel = document.createElement('select');
      sel.className = 'panel-select';
      sel.style.marginBottom = '4px';
      sel.dataset.channel = key;
      Object.keys(RISO_COLORS).forEach(cKey => {
        const opt = document.createElement('option');
        opt.value = cKey;
        opt.textContent = RISO_COLORS[cKey] ? RISO_COLORS[cKey].name : 'Aucun';
        if (mode !== 'PIPETTE' && cKey === defaults[key]) opt.selected = true;
        if (mode === 'PIPETTE' && activeChans[key] && activeChans[key].color === cKey) opt.selected = true;
      });
      sel.addEventListener('change', (e) => {
        if (mode === 'PIPETTE') activeChans[key].color = e.target.value;
        applyRisoPreview();
      });
      div.appendChild(sel);
      
      const opcContainer = document.createElement('div');
      opcContainer.style.display = 'flex';
      opcContainer.style.alignItems = 'center';
      opcContainer.style.gap = '8px';
      const opc = document.createElement('input');
      opc.type = 'range';
      opc.className = 'panel-slider';
      opc.min = '0'; opc.max = '100'; opc.value = '100';
      opc.dataset.channel = key;
      opc.addEventListener('input', applyRisoPreview);
      const opcLbl = document.createElement('span');
      opcLbl.style.fontSize = '11px';
      opcLbl.textContent = '100%';
      opc.addEventListener('input', () => opcLbl.textContent = opc.value + '%');
      opcContainer.appendChild(opc);
      opcContainer.appendChild(opcLbl);
      div.appendChild(opcContainer);
      
      // Delete button for PIPETTE layers
      if (mode === 'PIPETTE') {
        const delBtn = document.createElement('button');
        delBtn.innerHTML = '<i class="fa fa-trash"></i>';
        delBtn.style.color = '#ef4444';
        delBtn.style.background = 'transparent';
        delBtn.style.border = 'none';
        delBtn.style.cursor = 'pointer';
        delBtn.style.marginTop = '4px';
        delBtn.addEventListener('click', () => {
          delete window.risoChannels['PIPETTE'][key];
          renderRisoUI();
        });
        div.appendChild(delBtn);
      }
      
      list.appendChild(div);
    });

    applyRisoPreview();
  }

  $('selRisoMode').addEventListener('change', renderRisoUI);

  // PIPETTE Logic
  window.risoPickedColor = null;
  window.handleRisoPipetteClick = function(e) {
    if (!window.risoPipetteActive || !state.originalImageData) return;
    const rect = canvas.getBoundingClientRect();
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    const x = Math.floor((e.clientX - rect.left) * scaleX);
    const y = Math.floor((e.clientY - rect.top) * scaleY);

    const i = (y * canvas.width + x) * 4;
    window.risoPickedColor = {
      r: state.originalImageData.data[i],
      g: state.originalImageData.data[i+1],
      b: state.originalImageData.data[i+2]
    };

    $('risoPipetteColorBox').style.background = `rgb(${window.risoPickedColor.r}, ${window.risoPickedColor.g}, ${window.risoPickedColor.b})`;
    window.performPipetteIsolation(true);
  };

  window.performPipetteIsolation = function(previewOnly = false) {
    if (!window.risoPickedColor || !state.originalImageData) return null;
    const tol = parseInt($('sliderRisoPipetteTol').value);
    // Isolate color from originalImageData
    const isolated = isolateColor(state.originalImageData, window.risoPickedColor.r, window.risoPickedColor.g, window.risoPickedColor.b, tol);
    
    if (previewOnly) {
      // Show preview of what is selected
      const tempCanvas = document.createElement('canvas');
      tempCanvas.width = state.originalImageData.width;
      tempCanvas.height = state.originalImageData.height;
      const tCtx = tempCanvas.getContext('2d');
      // Create a red mask to show selection
      const mask = new ImageData(new Uint8ClampedArray(isolated.data), isolated.width, isolated.height);
      for(let i=0; i<mask.data.length; i+=4){
        if (mask.data[i+3] > 0) {
          mask.data[i] = 239; mask.data[i+1] = 68; mask.data[i+2] = 68; mask.data[i+3] = 180;
        }
      }
      tCtx.putImageData(state.originalImageData, 0, 0);
      
      const overlay = document.createElement('canvas');
      overlay.width = mask.width; overlay.height = mask.height;
      overlay.getContext('2d').putImageData(mask, 0, 0);
      tCtx.drawImage(overlay, 0, 0);
      
      ctx.putImageData(tCtx.getImageData(0,0,tempCanvas.width,tempCanvas.height), 0, 0);
      return null;
    }
    return isolated;
  };

  window.commitPipetteLayer = function() {
    if (!window.risoPickedColor) return;
    const isolated = window.performPipetteIsolation(false);
    if (!isolated) return;
    
    if (!window.risoChannels) window.risoChannels = {};
    if (!window.risoChannels['PIPETTE']) window.risoChannels['PIPETTE'] = {};
    
    const layerId = 'layer_' + Date.now();
    window.risoChannels['PIPETTE'][layerId] = {
      imageData: isolated,
      color: 'red',
      name: 'Couleur R=' + window.risoPickedColor.r + ' G=' + window.risoPickedColor.g + ' B=' + window.risoPickedColor.b
    };
    
    // Reset picker
    window.risoPickedColor = null;
    $('risoPipetteColorBox').style.background = '';
    
    renderRisoUI();
  };

  function applyRisoPreview() {
    if (!window.risoChannels) return;
    const mode = $('selRisoMode').value;
    const layersData = window.risoChannels[mode];
    if (!layersData) {
      if (state.originalImageData) ctx.putImageData(state.originalImageData, 0, 0);
      return;
    }

    let layersToBlend = [];
    $('risoChannelsList').querySelectorAll('select').forEach(sel => {
      const key = sel.dataset.channel;
      const colorKey = sel.value;
      const opcInput = $('risoChannelsList').querySelector(`input[type="range"][data-channel="${key}"]`);
      const opacity = parseInt(opcInput.value) / 100;
      
      if (colorKey !== 'none' && RISO_COLORS[colorKey]) {
        const colorHex = RISO_COLORS[colorKey].hex;
        const imgData = mode === 'PIPETTE' ? layersData[key].imageData : layersData[key];
        if (imgData) {
          const colorized = colorizeWithRiso(imgData, colorHex, 1.0);
          layersToBlend.push({ imageData: colorized, opacity: opacity });
        }
      }
    });

    if (layersToBlend.length > 0) {
      const blended = blendLayers(layersToBlend, state.originalImageData.width, state.originalImageData.height);
      ctx.putImageData(blended, 0, 0);
    } else {
      ctx.putImageData(state.originalImageData, 0, 0);
    }
  }

  $('btnRisoPosterize').addEventListener('click', () => {
    if (!window.risoChannels) return;
    const levels = parseInt($('sliderRisoLevels').value);
    const mode = $('selRisoMode').value;
    const layersData = window.risoChannels[mode];
    if (!layersData) return;
    Object.keys(layersData).forEach(key => {
      const imgData = mode === 'PIPETTE' ? layersData[key].imageData : layersData[key];
      if (imgData) {
        const processed = posterizeImage(imgData, levels);
        if (mode === 'PIPETTE') layersData[key].imageData = processed;
        else layersData[key] = processed;
      }
    });
    applyRisoPreview();
  });

  $('sliderRisoLevels').addEventListener('input', () => $('valRisoLevels').textContent = $('sliderRisoLevels').value);

  $('btnRisoHalftone').addEventListener('click', () => {
    if (!window.risoChannels) return;
    const size = parseInt($('sliderRisoHalftone').value);
    const mode = $('selRisoMode').value;
    const layersData = window.risoChannels[mode];
    if (!layersData) return;
    Object.keys(layersData).forEach(key => {
      const imgData = mode === 'PIPETTE' ? layersData[key].imageData : layersData[key];
      if (imgData) {
        const processed = applyHalftone(imgData, size);
        if (mode === 'PIPETTE') layersData[key].imageData = processed;
        else layersData[key] = processed;
      }
    });
    applyRisoPreview();
  });

  $('sliderRisoHalftone').addEventListener('input', () => $('valRisoHalftone').textContent = $('sliderRisoHalftone').value);

  $('btnRisoReset').addEventListener('click', () => {
    initRisoChannels();
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
        const imgData = mode === 'PIPETTE' ? layersData[key].imageData : layersData[key];
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

  // === RESET STUDIO ===
  function resetStudio() {
    state.file = null; state.pdfDoc = null; state.originalImageData = null;
    state.totalPages = 0; state.currentPage = 1;
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
