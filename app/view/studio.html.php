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
    <button class="tool-btn active" data-tool="filters" title="<?php _e('studio.filters', [], false); ?>"><i class="fa fa-sliders"></i><?php _e('studio.filters', [], false); ?></button>
    <button class="tool-btn" data-tool="geometry" title="<?php _e('studio.geometry', [], false); ?>"><i class="fa fa-crop"></i><?php _e('studio.geometry', [], false); ?></button>
    <div class="sidebar-divider"></div>
    <button class="tool-btn" data-tool="imposition" title="<?php _e('studio.imposition', [], false); ?>"><i class="fa fa-book"></i><?php _e('studio.imposition', [], false); ?></button>
    <button class="tool-btn" data-tool="montage" title="<?php _e('studio.montage', [], false); ?>"><i class="fa fa-object-group"></i><?php _e('studio.montage', [], false); ?></button>
    <button class="tool-btn" data-tool="pages" title="<?php _e('studio.pages', [], false); ?>"><i class="fa fa-files-o"></i><?php _e('studio.pages', [], false); ?></button>
    <div class="sidebar-divider"></div>
    <button class="tool-btn" data-tool="riso" title="<?php _e('studio.riso', [], false); ?>"><i class="fa fa-adjust"></i><?php _e('studio.riso', [], false); ?></button>
    <button class="tool-btn" data-tool="ocr" title="<?php _e('studio.ocr_scan', [], false); ?>"><i class="fa fa-font"></i><?php _e('studio.ocr_scan', [], false); ?></button>
    <button class="tool-btn" data-tool="modification" title="<?php _e('studio.modification', [], false); ?>"><i class="fa fa-edit"></i><?php _e('studio.modification', [], false); ?></button>
    <button class="tool-btn" data-tool="metadata" title="<?php _e('studio.metadata', [], false); ?>"><i class="fa fa-tags"></i><?php _e('studio.metadata', [], false); ?></button>
  </aside>

  <!-- === MAIN WORKSPACE === -->
  <main class="studio-workspace">
    <!-- Toolbar -->
    <div class="studio-toolbar">
      <div class="toolbar-title"><i class="fa fa-magic"></i><?php _e("studio.studio_title", [], false); ?></div>
      <span class="file-info-badge" id="fileInfoBadge" style="display:none">
        <i class="fa fa-file"></i> <input type="text" id="fileNameDisplay" style="background:transparent; border:none; outline:none; border-bottom:1px dashed rgba(255,255,255,0.5); color:inherit; font-family:inherit; font-size:inherit; font-weight:inherit; min-width: 150px; max-width:250px;">
        <span id="fileDimsDisplay" style="opacity:0.7;margin-left:6px;font-size:11px"></span>
        <span id="fileInkDisplay" style="margin-left:8px; padding:2px 8px; background:rgba(0,0,0,0.1); border-radius:10px; font-size:11px; font-weight:600; display:none" title="<?= __('studio.ink_coverage_title') ?>"></span>
      </span>
      <div class="toolbar-spacer"></div>
      <button class="toolbar-btn" id="btnNewFile" style="display:none"><i class="fa fa-upload"></i> <?php _e('studio.new_file', [], false); ?></button>
      <button class="toolbar-btn" id="btnExportPng" style="display:none" title="<?= __('studio.export_png_title') ?>"><i class="fa fa-file-image-o"></i><?php _e("studio.export_png", [], false); ?></button>
      <button class="toolbar-btn" id="btnSaveToLibrary" style="display:none" title="<?= __('studio.save_to_library_title') ?>">
        <i class="fa fa-bookmark"></i> <?php _e('header.library', [], false); ?>
      </button>
      <button class="toolbar-btn primary" id="btnExportPdf" style="display:none; position: relative;" title="<?= __('studio.export_pdf_title') ?>">
        <i class="fa fa-file-pdf-o"></i> PDF
        <span id="pdfReadyBadge" style="display:none; position: absolute; top: -5px; right: -5px; background: #10b981; color: white; border-radius: 50%; width: 16px; height: 16px; font-size: 10px; align-items: center; justify-content: center; box-shadow: 0 0 0 2px var(--studio-surface);"><i class="fa fa-check"></i></span>
      </button>
    </div>

    <!-- Canvas / Upload Area -->
    <div class="studio-canvas-area" id="canvasArea">
      <!-- Upload Zone (visible par défaut) -->
      <div class="studio-upload-zone" id="uploadZone">
        <div class="upload-icon"><i class="fa fa-cloud-upload"></i></div>
        <div class="upload-title"><?php _e('studio.drop_file_here', [], false); ?></div>
        <div class="upload-subtitle"><?php _e('studio.click_to_browse', [], false); ?></div>
        <div class="upload-formats">
          <span>PDF</span><span>PNG</span><span>JPG</span><span>GIF</span><span>WEBP</span>
        </div>
        <input type="file" id="studioFileInput" accept=".pdf,.png,.jpg,.jpeg,.gif,.webp" style="display:none">
      </div>

      <!-- Preview canvas (hidden until file loaded) -->
      <div id="studioSpinner" class="studio-spinner" style="display:none">
        <i class="fa fa-spinner fa-spin fa-3x" style="color:var(--studio-primary); margin-bottom:16px;"></i>
        <div id="spinnerMsg" style="font-weight:600"><?php _e("studio.loading", [], false); ?></div>
      </div>
      
      <!-- Delete page button overlaid on canvas -->
      <div id="mainCanvasDeleteBtn" style="display:none; position:absolute; top:10px; right:10px; background:rgba(239,68,68,0.9); color:white; width:36px; height:36px; border-radius:18px; align-items:center; justify-content:center; cursor:pointer; z-index:20; box-shadow:0 2px 5px rgba(0,0,0,0.2); transition:transform 0.2s;" title="<?= __('studio.delete_page_title') ?>" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
        <i class="fa fa-trash"></i>
      </div>

      <!-- Lightbox (hidden) -->
      <div id="studioLightbox" class="studio-lightbox">
        <div class="lightbox-header">
          <div class="lightbox-title"><i class="fa fa-eye"></i><?php _e("studio.lightbox_title", [], false); ?></div>
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
        <div class="panel-section-title"><?php _e("studio.filters_image_adjustments", [], false); ?></div>
        <div class="panel-row">
          <div class="panel-label"><?php _e("studio.filters_contrast", [], false); ?><span class="panel-value" id="valContrast">0</span></div>
          <input type="range" class="panel-slider" id="sliderContrast" min="-100" max="100" value="0">
        </div>
        <div class="panel-row">
          <div class="panel-label"><?php _e("studio.filters_brightness", [], false); ?><span class="panel-value" id="valBrightness">0</span></div>
          <input type="range" class="panel-slider" id="sliderBrightness" min="-100" max="100" value="0">
        </div>
        <div class="panel-row">
          <div class="panel-label"><?php _e("studio.filters_gamma", [], false); ?><span class="panel-value" id="valGamma"><?php _e("studio.filters_gamma_default", [], false); ?></span></div>
          <input type="range" class="panel-slider" id="sliderGamma" min="0.1" max="3.0" value="1.0" step="0.1">
        </div>
        <div class="panel-row">
          <div class="panel-label"><?php _e("studio.filters_saturation", [], false); ?><span class="panel-value" id="valSaturation">0</span></div>
          <input type="range" class="panel-slider" id="sliderSaturation" min="-100" max="100" value="0">
        </div>
      </div>
      <div class="panel-section">
        <div class="panel-section-title"><?php _e("studio.filters_bitmap", [], false); ?></div>
        <label class="panel-checkbox">
          <input type="checkbox" id="chkBitmap"> <?php _e("studio.filters_enable_bitmap_mode", [], false); ?>
        </label>
        <div id="bitmapOpts" style="display:none;margin-top:12px">
          <div class="panel-row">
            <div class="panel-label"><?php _e("studio.filters_bitmap_method", [], false); ?></div>
            <select class="panel-select" id="selBitmapMethod">
              <option value="threshold"><?php _e("studio.filters_bitmap_threshold", [], false); ?></option>
              <option value="dithering"><?php _e("studio.filters_bitmap_dithering", [], false); ?></option>
            </select>
          </div>
          <div class="panel-row" id="thresholdRow">
            <div class="panel-label"><?php _e("studio.filters_bitmap_threshold_value", [], false); ?><span class="panel-value" id="valThreshold">128</span></div>
            <input type="range" class="panel-slider" id="sliderThreshold" min="0" max="255" value="128">
          </div>
        </div>
      </div>
      <div class="panel-section" style="text-align:center">
        <button class="toolbar-btn" id="btnReset" style="width:100%;margin-bottom:8px"><i class="fa fa-undo"></i><?php _e("studio.filters_reset", [], false); ?></button>
      </div>
    </div>

    <!-- Imposition Panel (hidden) -->
    <div id="panelImposition" style="display:none">
      <!-- Tabs -->
      <div style="display:flex;border-bottom:1px solid var(--studio-border);margin-bottom:0">
        <button class="imp-tab active" data-tab="brochure" style="flex:1;padding:10px 4px;border:none;background:transparent;font-size:11px;font-weight:600;cursor:pointer;color:var(--studio-primary);border-bottom:2px solid var(--studio-primary)"><?php _e("studio.imposition_tab_brochure", [], false); ?></button>
        <button class="imp-tab" data-tab="livre" style="flex:1;padding:10px 4px;border:none;background:transparent;font-size:11px;font-weight:600;cursor:pointer;color:var(--studio-text-muted);border-bottom:2px solid transparent"><?php _e("studio.imposition_tab_book", [], false); ?></button>
        <button class="imp-tab" data-tab="tracts" style="flex:1;padding:10px 4px;border:none;background:transparent;font-size:11px;font-weight:600;cursor:pointer;color:var(--studio-text-muted);border-bottom:2px solid transparent"><?php _e("studio.imposition_tab_tracts", [], false); ?></button>
      </div>

      <!-- == TAB BROCHURE == -->
      <div id="impTabBrochure" class="imp-tab-content" style="padding:0">
        <div class="panel-section">
          <div class="panel-section-title"><?php _e("studio.imposition_format_and_impose", [], false); ?></div>
          <div class="panel-row">
            <div class="panel-label"><?php _e("studio.imposition_output_format", [], false); ?></div>
            <select class="panel-select" id="bro_output_format"><option value="A3"><?php _e("studio.imposition_format_a3", [], false); ?></option><option value="A4">A4</option></select>
          </div>
          <div class="panel-row">
            <div class="panel-label"><?php _e("studio.imposition_pages_per_sheet", [], false); ?></div>
            <select class="panel-select" id="bro_n_up">
              <option value="2"><?php _e("studio.imposition_nup_2", [], false); ?></option>
              <option value="4"><?php _e("studio.imposition_nup_4", [], false); ?></option>
              <option value="8"><?php _e("studio.imposition_nup_8", [], false); ?></option>
            </select>
          </div>
          <div class="panel-row" style="margin-top:8px">
            <div class="panel-label"><?php _e("studio.imposition_signature_size", [], false); ?></div>
            <select class="panel-select" id="bro_signature_size">
              <option value="0" selected><?php _e("studio.imposition_signature_0", [], false); ?></option>
              <option value="8">8</option>
              <option value="16">16</option>
              <option value="32">32</option>
            </select>
          </div>
          <div style="font-size:9px; color:var(--studio-text-muted); margin-top:2px">
            <?php _e("studio.imposition_signature_hint", [], false); ?>
          </div>
        </div>

        <div class="panel-section">
          <div class="panel-section-title"><?php _e("studio.imposition_resize", [], false); ?></div>
          <div class="panel-row">
            <label style="font-size:11px; cursor:pointer"><input type="radio" name="bro_resize_mode" value="percent" checked><?php _e("studio.imposition_resize_percent", [], false); ?></label>
            <label style="font-size:11px; cursor:pointer; margin-left:10px"><input type="radio" name="bro_resize_mode" value="mm"><?php _e("studio.imposition_resize_mm", [], false); ?></label>
          </div>
          <div id="bro_resize_percent_block">
            <div class="panel-row">
              <div class="panel-label"><?php _e("studio.imposition_scale", [], false); ?></div>
              <input type="number" class="panel-select" id="bro_scale" value="100" min="10" max="400">
            </div>
          </div>
          <div id="bro_resize_mm_block" style="display:none">
            <div class="panel-row">
              <div class="panel-label"><?php _e("studio.imposition_target_width", [], false); ?></div>
              <input type="number" class="panel-select" id="bro_target_w" placeholder="ex: 105" step="0.1">
            </div>
            <div class="panel-row">
              <div class="panel-label"><?php _e("studio.imposition_target_height", [], false); ?></div>
              <input type="number" class="panel-select" id="bro_target_h" placeholder="ex: 148" step="0.1">
            </div>
          </div>
        </div>

        <div class="panel-section">
          <div class="panel-section-title"><?php _e("studio.imposition_gutters", [], false); ?></div>
          <div class="panel-row">
            <div class="panel-label"><?php _e("studio.imposition_gutter_horizontal", [], false); ?></div>
            <input type="number" class="panel-select" id="bro_gutter_x" value="0" step="0.5">
          </div>
          <div class="panel-row">
            <div class="panel-label"><?php _e("studio.imposition_gutter_vertical", [], false); ?></div>
            <input type="number" class="panel-select" id="bro_gutter_y" value="0" step="0.5">
          </div>
          <div class="panel-row">
            <div class="panel-label"><?php _e("studio.imposition_gutter_strategy", [], false); ?></div>
            <select class="panel-select" id="bro_gutter_strategy">
              <option value="reduce"><?php _e("studio.imposition_gutter_strategy_reduce", [], false); ?></option>
              <option value="crop"><?php _e("studio.imposition_gutter_strategy_crop", [], false); ?></option>
            </select>
          </div>
        </div>

        <div class="panel-section">
          <div class="panel-section-title"><?php _e("studio.imposition_marks_and_folios", [], false); ?></div>
          <div class="panel-row">
            <label style="font-size:11px; cursor:pointer"><input type="checkbox" id="bro_crop_marks"><?php _e("studio.imposition_show_crop_marks", [], false); ?></label>
          </div>
          <div id="bro_crop_settings" style="display:none; padding-left:10px; border-left:2px solid var(--studio-border); margin-top:5px">
            <div class="panel-row">
              <div class="panel-label"><?php _e("studio.imposition_crop_mark_style", [], false); ?></div>
              <select class="panel-select" id="bro_crop_style" style="font-size:10px">
                <option value="standard"><?php _e("studio.imposition_crop_style_standard", [], false); ?></option>
                <option value="spreads" selected><?php _e("studio.imposition_crop_style_spreads", [], false); ?></option>
                <option value="booklet"><?php _e("studio.imposition_crop_style_booklet", [], false); ?></option>
              </select>
            </div>
            <div class="panel-row">
              <div class="panel-label"><?php _e("studio.imposition_crop_mark_length", [], false); ?></div>
              <input type="number" class="panel-select" id="bro_crop_len" value="5" min="1" style="width:100px">
            </div>
          </div>
          
          <div class="panel-row" style="margin-top:10px">
            <label style="font-size:11px; cursor:pointer"><input type="checkbox" id="bro_page_nums"><?php _e("studio.imposition_show_page_numbers", [], false); ?></label>
          </div>
          <div id="bro_folio_settings" style="display:none; padding-left:10px; border-left:2px solid var(--studio-border); margin-top:5px; margin-bottom:10px">
             <div class="panel-row" id="bro_folio_position_row" style="margin-top:5px; margin-bottom:5px;">
               <div class="panel-label"><?php _e("studio.imposition_folio_position", [], false); ?></div>
               <select class="panel-select" id="bro_folio_position" style="font-size:10px">
                 <option value="margins" selected><?php _e("studio.imposition_folio_in_margins", [], false); ?></option>
                 <option value="gutters"><?php _e("studio.imposition_folio_in_gutters", [], false); ?></option>
               </select>
             </div>
             <div class="panel-row" style="margin-top:5px; margin-bottom:5px;">
               <label style="font-size:11px; cursor:pointer"><input type="checkbox" id="bro_page_nums_manual"><?php _e("studio.imposition_folio_manual_position", [], false); ?></label>
             </div>
             <div id="bro_folio_manual_settings" style="display:none;">
                 <div class="panel-row">
                   <div class="panel-label"><?php _e("studio.imposition_folio_offset_x", [], false); ?></div>
                    <input type="number" class="panel-select" id="bro_folio_x" value="0" step="0.5" style="width:100px">
                 </div>
                 <div style="font-size:9px; color:var(--studio-text-muted); margin-bottom:6px; margin-top:-2px">
                   <?php _e("studio.imposition_folio_offset_x_hint", [], false); ?>
                 </div>
                 <div class="panel-row">
                   <div class="panel-label"><?php _e("studio.imposition_folio_offset_y", [], false); ?></div>
                    <input type="number" class="panel-select" id="bro_folio_y" value="-2" step="0.5" style="width:100px">
                 </div>
                 <div style="font-size:9px; color:var(--studio-text-muted); margin-bottom:6px; margin-top:-2px">
                   <?php _e("studio.imposition_folio_offset_y_hint", [], false); ?>
                 </div>
             </div>
          </div>

          <div class="panel-row" style="margin-top:10px">
            <label style="font-size:11px; cursor:pointer"><input type="checkbox" id="bro_tumble"><?php _e("studio.imposition_tete_beche", [], false); ?></label>
          </div>

          <div class="panel-row" style="margin-top:10px">
            <label style="font-size:11px; cursor:pointer"><input type="checkbox" id="bro_signature_marks"><?php _e("studio.imposition_signature_marks", [], false); ?></label>
          </div>
          <div style="font-size:9px; color:var(--studio-text-muted); margin-bottom:6px; margin-top:-2px">
            <?php _e("studio.imposition_signature_marks_hint", [], false); ?>
          </div>
          

        </div>
      </div>

      <!-- == TAB LIVRE == -->
      <div id="impTabLivre" class="imp-tab-content" style="display:none;padding:0">
        <div class="panel-section">
          <div class="panel-section-title"><?php _e("studio.imposition_format_and_impose", [], false); ?></div>
          <div class="panel-row">
            <div class="panel-label"><?php _e("studio.imposition_output_format", [], false); ?></div>
            <select class="panel-select" id="liv_output_format"><option value="A3">A3</option><option value="A4">A4</option></select>
          </div>
          <div class="panel-row">
            <div class="panel-label"><?php _e("studio.imposition_pages_per_sheet", [], false); ?></div>
            <select class="panel-select" id="liv_n_up">
              <option value="2"><?php _e("studio.imposition_nup_2", [], false); ?></option>
              <option value="4"><?php _e("studio.imposition_nup_4", [], false); ?></option>
              <option value="8"><?php _e("studio.imposition_nup_8", [], false); ?></option>
            </select>
          </div>
        </div>

        <div class="panel-section">
          <div class="panel-section-title"><?php _e("studio.imposition_resize", [], false); ?></div>
          <div class="panel-row">
            <label style="font-size:11px; cursor:pointer"><input type="radio" name="liv_resize_mode" value="percent" checked><?php _e("studio.imposition_resize_percent", [], false); ?></label>
            <label style="font-size:11px; cursor:pointer; margin-left:10px"><input type="radio" name="liv_resize_mode" value="mm"><?php _e("studio.imposition_resize_mm", [], false); ?></label>
          </div>
          <div id="liv_resize_percent_block">
            <div class="panel-row">
              <div class="panel-label"><?php _e("studio.imposition_scale", [], false); ?></div>
              <input type="number" class="panel-select" id="liv_scale" value="100">
            </div>
          </div>
          <div id="liv_resize_mm_block" style="display:none">
            <div class="panel-row">
              <div class="panel-label"><?php _e("studio.imposition_target_width", [], false); ?></div>
              <input type="number" class="panel-select" id="liv_target_w" placeholder="mm">
            </div>
            <div class="panel-row">
              <div class="panel-label"><?php _e("studio.imposition_target_height", [], false); ?></div>
              <input type="number" class="panel-select" id="liv_target_h" placeholder="mm">
            </div>
          </div>
        </div>

        <div class="panel-section">
          <div class="panel-section-title"><?php _e("studio.imposition_gutters", [], false); ?></div>
          <div class="panel-row">
            <div style="width:30%">
              <div class="panel-label" style="font-size:10px; margin-bottom:2px"><?php _e("studio.imposition_gutter_horizontal", [], false); ?></div>
              <input type="number" class="panel-select" id="liv_gutter_x" value="0" step="0.5" style="width:100%" placeholder="0">
            </div>
            <div style="width:30%">
              <div class="panel-label" style="font-size:10px; margin-bottom:2px"><?php _e("studio.imposition_gutter_vertical", [], false); ?></div>
              <input type="number" class="panel-select" id="liv_gutter_y" value="0" step="0.5" style="width:100%" placeholder="0">
            </div>
            <div style="width:35%">
              <div class="panel-label" style="font-size:10px; margin-bottom:2px"><?php _e("studio.imposition_gutter_strategy", [], false); ?></div>
              <select class="panel-select" id="liv_gutter_strategy" style="width:100%">
                <option value="reduce"><?php _e("studio.imposition_gutter_strategy_reduce", [], false); ?></option>
                <option value="crop"><?php _e("studio.imposition_gutter_strategy_crop", [], false); ?></option>
              </select>
            </div>
          </div>
        </div>

        <div class="panel-section">
          <div class="panel-section-title"><?php _e("studio.imposition_marks_and_folios", [], false); ?></div>
          <div class="panel-row">
            <label style="font-size:11px; cursor:pointer"><input type="checkbox" id="liv_crop_marks"><?php _e("studio.imposition_show_crop_marks", [], false); ?></label>
          </div>
          <div id="liv_crop_settings" style="display:none; padding-left:10px; border-left:2px solid var(--studio-border); margin-top:5px; margin-bottom:10px">
            <div class="panel-row">
              <div class="panel-label"><?php _e("studio.imposition_crop_mark_style", [], false); ?></div>
              <select class="panel-select" id="liv_crop_style" style="font-size:10px">
                <option value="standard" selected><?php _e("studio.imposition_crop_style_standard", [], false); ?></option>
                <option value="spreads"><?php _e("studio.imposition_crop_style_spreads", [], false); ?></option>
                <option value="booklet"><?php _e("studio.imposition_crop_style_booklet", [], false); ?></option>
              </select>
            </div>
            <div class="panel-row">
              <div class="panel-label"><?php _e("studio.imposition_crop_mark_length", [], false); ?></div>
              <input type="number" class="panel-select" id="liv_crop_len" value="5" min="1" style="width:100px">
            </div>
          </div>
          
          <div class="panel-row">
            <label style="font-size:11px; cursor:pointer"><input type="checkbox" id="liv_page_nums"><?php _e("studio.imposition_show_page_numbers", [], false); ?></label>
          </div>
          <div id="liv_folio_settings" style="display:none; padding-left:10px; border-left:2px solid var(--studio-border); margin-top:5px; margin-bottom:10px">
             <div class="panel-row" id="liv_folio_position_row" style="margin-top:5px; margin-bottom:5px;">
               <div class="panel-label"><?php _e("studio.imposition_folio_position", [], false); ?></div>
               <select class="panel-select" id="liv_folio_position" style="font-size:10px">
                 <option value="margins" selected><?php _e("studio.imposition_folio_in_margins", [], false); ?></option>
                 <option value="gutters"><?php _e("studio.imposition_folio_in_gutters", [], false); ?></option>
               </select>
             </div>
             <div class="panel-row" style="margin-top:5px; margin-bottom:5px;">
               <label style="font-size:11px; cursor:pointer"><input type="checkbox" id="liv_page_nums_manual"><?php _e("studio.imposition_folio_manual_position", [], false); ?></label>
             </div>
             <div id="liv_folio_manual_settings" style="display:none;">
                 <div class="panel-row">
                   <div class="panel-label"><?php _e("studio.imposition_folio_offset_x", [], false); ?></div>
                    <input type="number" class="panel-select" id="liv_folio_x" value="0" step="0.5" style="width:100px">
                 </div>
                 <div style="font-size:9px; color:var(--studio-text-muted); margin-bottom:6px; margin-top:-2px">
                   <?php _e("studio.imposition_folio_offset_x_hint", [], false); ?>
                 </div>
                 <div class="panel-row">
                   <div class="panel-label"><?php _e("studio.imposition_folio_offset_y", [], false); ?></div>
                    <input type="number" class="panel-select" id="liv_folio_y" value="-2" step="0.5" style="width:100px">
                 </div>
                 <div style="font-size:9px; color:var(--studio-text-muted); margin-bottom:6px; margin-top:-2px">
                   <?php _e("studio.imposition_folio_offset_y_hint", [], false); ?>
                 </div>
             </div>
          </div>
          
          <div class="panel-row" style="margin-top:10px">
            <label style="font-size:11px; cursor:pointer"><input type="checkbox" id="liv_tete_beche"><?php _e("studio.imposition_tete_beche", [], false); ?></label>
          </div>
          <div class="panel-row" style="margin-top:10px">
            <label style="font-size:11px; cursor:pointer"><input type="checkbox" id="liv_collation_marks"><?php _e("studio.imposition_collation_marks", [], false); ?></label>
          </div>
          

        </div>
      </div>

      <!-- == TAB TRACTS == -->
      <div id="impTabTracts" class="imp-tab-content" style="display:none;padding:0">
        <div class="panel-section">
          <div class="panel-section-title"><?php _e("studio.imposition_format_and_impose", [], false); ?></div>
          <div class="panel-row">
            <div class="panel-label"><?php _e("studio.imposition_output_format", [], false); ?></div>
            <select class="panel-select" id="tra_output_format"><option value="A3">A3</option><option value="A4">A4</option></select>
          </div>
          <div class="panel-row">
            <div class="panel-label"><?php _e("studio.imposition_source_format", [], false); ?></div>
            <select class="panel-select" id="tra_manual_format">
              <option value="auto"><?php _e("studio.imposition_source_format_auto", [], false); ?></option>
              <option value="A4">A4 → 2 sur A3</option>
              <option value="A5">A5 → 4 sur A3</option>
              <option value="A6">A6 → 8 sur A3</option>
            </select>
          </div>
          <div class="panel-row">
            <div class="panel-label"><?php _e("studio.imposition_orientation", [], false); ?></div>
            <select class="panel-select" id="tra_orientation">
              <option value="auto"><?php _e("studio.imposition_orientation_auto", [], false); ?></option>
              <option value="portrait"><?php _e("studio.imposition_orientation_portrait", [], false); ?></option>
              <option value="landscape"><?php _e("studio.imposition_orientation_landscape", [], false); ?></option>
            </select>
          </div>
        </div>
        <div class="panel-section">
          <div class="panel-section-title"><?php _e("studio.imposition_options", [], false); ?></div>
          <div class="panel-row"><label class="panel-checkbox"><input type="checkbox" id="tra_crop_marks"><?php _e("studio.imposition_show_crop_marks", [], false); ?></label></div>
          <div class="panel-row"><label class="panel-checkbox"><input type="checkbox" id="tra_keep_size"><?php _e("studio.imposition_keep_original_size", [], false); ?></label></div>
          <div class="panel-row"><label class="panel-checkbox"><input type="checkbox" id="tra_force_resize"><?php _e("studio.imposition_force_resize", [], false); ?></label></div>
          <div class="panel-row">
            <div class="panel-label"><?php _e("studio.imposition_duplex_mode", [], false); ?></div>
            <select class="panel-select" id="tra_duplex_mode">
              <option value="none"><?php _e("studio.imposition_duplex_none", [], false); ?></option>
              <option value="manuel"><?php _e("studio.imposition_duplex_manual", [], false); ?></option>
            </select>
          </div>
        </div>
      </div>

      <div style="padding:12px">
        <button class="toolbar-btn primary" id="btnApplyImposition" style="width:100%">
          <i class="fa fa-magic"></i> <?php _e("studio.imposition_apply", [], false); ?>
        </button>
      </div>
    </div>

    <!-- Geometry Panel (hidden) -->
    <div id="panelGeometry" style="display:none">
      <div class="panel-section">
        <div class="panel-section-title"><?php _e("studio.geometry_rotate_and_flip", [], false); ?></div>
        <div class="panel-row">
          <button class="toolbar-btn" id="btnRotateLeft" style="width:48%"><i class="fa fa-rotate-left"></i> -90°</button>
          <button class="toolbar-btn" id="btnRotateRight" style="width:48%;float:right"><i class="fa fa-rotate-right"></i> +90°</button>
        </div>
        <div class="panel-row" style="margin-top:12px">
          <button class="toolbar-btn" id="btnFlipH" style="width:48%"><i class="fa fa-arrows-h"></i><?php _e("studio.geometry_flip_horizontal", [], false); ?></button>
          <button class="toolbar-btn" id="btnFlipV" style="width:48%;float:right"><i class="fa fa-arrows-v"></i><?php _e("studio.geometry_flip_vertical", [], false); ?></button>
        </div>
        <div class="panel-row" style="margin-top:12px; border-top: 1px solid var(--studio-border); padding-top: 12px;">
          <div class="panel-label"><?php _e("studio.geometry_deskew", [], false); ?><span class="panel-value" id="valDeskew">0°</span></div>
          <input type="range" class="panel-slider" id="sliderDeskew" min="-15" max="15" step="0.1" value="0">
        </div>
        <div class="panel-row" style="margin-top:4px">
          <button class="toolbar-btn primary" id="btnApplyDeskew" style="width:100%"><i class="fa fa-check"></i><?php _e("studio.geometry_apply_deskew", [], false); ?></button>
        </div>
        <div class="panel-row" style="margin-top:12px; font-size:11px; border-top: 1px solid var(--studio-border); padding-top: 12px;">
          <label style="cursor:pointer;display:flex;align-items:center;gap:6px">
             <input type="checkbox" id="chkGeomApplyAll"> <?php _e("studio.geometry_apply_to_all_pages", [], false); ?>
          </label>
        </div>
      </div>
      <div class="panel-section">
        <div class="panel-section-title"><?php _e("studio.geometry_resize_to_format", [], false); ?></div>
        <div class="panel-row">
          <select class="panel-select" id="selResizeFormat">
            <option value="A4"><?php _e("studio.geometry_format_a4", [], false); ?></option>
            <option value="A3"><?php _e("studio.geometry_format_a3", [], false); ?></option>
            <option value="A5"><?php _e("studio.geometry_format_a5", [], false); ?></option>
          </select>
        </div>
        <div class="panel-row" style="margin-top:8px">
          <button class="toolbar-btn primary" id="btnApplyResize" style="width:100%"><i class="fa fa-expand"></i><?php _e("studio.geometry_apply_resize", [], false); ?></button>
        </div>
      </div>
      <!-- Section Crop -->
      <div class="panel-section">
        <div class="panel-section-title"><?php _e("studio.geometry_crop", [], false); ?></div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:10px;">
          <div>
            <div class="panel-label" style="font-size:11px;"><?php _e("studio.geometry_crop_top", [], false); ?></div>
            <input type="number" class="panel-select" id="cropTop" value="0" min="0" step="0.5" style="padding:6px 8px;">
          </div>
          <div>
            <div class="panel-label" style="font-size:11px;"><?php _e("studio.geometry_crop_bottom", [], false); ?></div>
            <input type="number" class="panel-select" id="cropBottom" value="0" min="0" step="0.5" style="padding:6px 8px;">
          </div>
          <div>
            <div class="panel-label" style="font-size:11px;"><?php _e("studio.geometry_crop_left", [], false); ?></div>
            <input type="number" class="panel-select" id="cropLeft" value="0" min="0" step="0.5" style="padding:6px 8px;">
          </div>
          <div>
            <div class="panel-label" style="font-size:11px;"><?php _e("studio.geometry_crop_right", [], false); ?></div>
            <input type="number" class="panel-select" id="cropRight" value="0" min="0" step="0.5" style="padding:6px 8px;">
          </div>
        </div>
        <div id="cropSizeInfo" style="font-size:11px; color:var(--studio-text-muted); text-align:center; margin-bottom:10px; font-weight:500;">—</div>
        <button class="toolbar-btn primary" id="btnActivateCrop" style="width:100%; margin-bottom:8px;"><i class="fa fa-crop"></i><?php _e("studio.geometry_apply_crop", [], false); ?></button>
        <button class="toolbar-btn" id="btnResetCrop" style="width:100%; margin-bottom:8px;"><i class="fa fa-undo"></i><?php _e("studio.geometry_reset_crop", [], false); ?></button>
        <button class="toolbar-btn primary" id="btnApplyCropExport" style="width:100%; background:#10b981; border-color:#10b981;"><i class="fa fa-scissors"></i> <?php _e("studio.geometry_crop_and_export", [], false); ?></button>
      </div>
    </div>

    <!-- Panel Montage Libre -->
    <div id="panelMontage" style="display:none">
      <div class="panel-section">
        <div class="panel-section-title"><?php _e("studio.montage_canvas_settings", [], false); ?></div>
        <div class="panel-row">
          <div class="panel-label"><?php _e("studio.montage_output_format", [], false); ?></div>
          <select class="panel-select" id="montageFormat">
            <option value="A3">A3</option>
            <option value="A4" selected>A4</option>
            <option value="A5">A5</option>
          </select>
        </div>
        <div class="panel-row">
          <div class="panel-label"><?php _e("studio.montage_orientation", [], false); ?></div>
          <select class="panel-select" id="montageOrientation">
            <option value="portrait"><?php _e("studio.montage_orientation_portrait", [], false); ?></option>
            <option value="landscape"><?php _e("studio.montage_orientation_landscape", [], false); ?></option>
          </select>
        </div>
      </div>
      
      <div class="panel-section">
        <div class="panel-section-title"><?php _e("studio.montage_boards", [], false); ?></div>
        <div id="montagePlanchesList" style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:10px;">
          <!-- Planches boutons -->
        </div>
        <button class="panel-btn" id="btnAddPlanche"><i class="fa fa-plus"></i><?php _e("studio.montage_add_board", [], false); ?></button>
      </div>

      <div class="panel-section">
        <div class="panel-section-title"><?php _e("studio.montage_sources", [], false); ?></div>
        <input type="file" id="montageUploadPdf" accept=".pdf,.jpg,.jpeg,.png,.webp" multiple style="display:none">
        <button class="panel-btn" id="btnMontageUpload"><i class="fa fa-upload"></i><?php _e("studio.montage_upload_sources", [], false); ?></button>
        <div id="montageSourceThumbs" style="margin-top:10px; display:grid; grid-template-columns: 1fr 1fr; gap:5px; max-height:300px; overflow-y:auto; padding-right:4px;">
          <!-- Thumbnails of uploaded PDFs -->
        </div>
      </div>

      <div class="panel-section" style="text-align:center">
        <button class="panel-btn" id="btnGenerateMontage" style="background:var(--studio-primary);color:white;width:100%;font-weight:600;margin-top:10px;">
          <i class="fa fa-magic"></i> <?php _e("studio.montage_generate", [], false); ?>
        </button>
      </div>
    </div>

    <!-- Pages Panel (hidden) -->
    <div id="panelPages" style="display:none">
      <div class="panel-section">
        <div class="panel-section-title"><?php _e("studio.pages_pdf_to_images", [], false); ?></div>
        <div class="panel-row">
          <div class="panel-label"><?php _e("studio.pages_dpi", [], false); ?><span class="panel-value" id="valDpi">150</span></div>
          <input type="range" class="panel-slider" id="sliderDpi" min="72" max="300" value="150" step="1">
        </div>
        <div class="panel-row" style="margin-top:12px">
          <button class="toolbar-btn" id="btnPdfToImg" style="width:100%"><i class="fa fa-file-image-o"></i><?php _e("studio.pages_convert_to_images", [], false); ?></button>
        </div>
      </div>


      <div class="panel-section">
        <div class="panel-section-title"><?php _e("studio.pages_organize", [], false); ?></div>
        <p style="font-size:12px;color:#6b7280;margin-bottom:8px;"><?php _e("studio.pages_organize_hint", [], false); ?></p>
        <div class="panel-row">
          <div class="panel-label"><?php _e("studio.pages_insert_position", [], false); ?></div>
          <select class="panel-select" id="selOrgBlankPos">
            <option value="end"><?php _e("studio.pages_insert_at_end", [], false); ?></option>
            <option value="start"><?php _e("studio.pages_insert_at_start", [], false); ?></option>
            <option value="before"><?php _e("studio.pages_insert_before_current", [], false); ?></option>
            <option value="after"><?php _e("studio.pages_insert_after_current", [], false); ?></option>
          </select>
        </div>
        <div class="panel-row" style="margin-top:8px">
          <input type="file" id="orgAddPdfInput" accept=".pdf" style="display:none">
          <button class="toolbar-btn" id="btnOrgAddPdf" style="width:100%"><i class="fa fa-file-pdf-o"></i><?php _e("studio.pages_add_pdf", [], false); ?></button>
        </div>
        <div class="panel-row" style="margin-top:8px">
          <button class="toolbar-btn" id="btnOrgAddBlank" style="width:100%"><i class="fa fa-plus"></i><?php _e("studio.pages_add_blank_page", [], false); ?></button>
        </div>
        <div class="panel-row" style="margin-top:8px">
          <button class="toolbar-btn" id="btnOrgReverse" style="width:100%"><i class="fa fa-sort-numeric-desc"></i><?php _e("studio.pages_reverse_order", [], false); ?></button>
        </div>
        <div class="panel-row" style="margin-top:12px">
          <button class="toolbar-btn primary" id="btnApplyOrg" style="width:100%"><i class="fa fa-magic"></i><?php _e("studio.pages_apply_organization", [], false); ?></button>
        </div>
      </div>

      <div class="panel-section">
        <div class="panel-section-title"><?php _e("studio.pages_unimpose", [], false); ?></div>
        <div class="panel-row">
          <div class="panel-label"><?php _e("studio.pages_unimpose_mode", [], false); ?></div>
          <select class="panel-select" id="selUnimposeMode">
            <option value="booklet"><?php _e("studio.pages_unimpose_booklet", [], false); ?></option>
            <option value="doubles"><?php _e("studio.pages_unimpose_doubles", [], false); ?></option>
            <option value="sequential"><?php _e("studio.pages_unimpose_sequential", [], false); ?></option>
          </select>
        </div>
        <div class="panel-row" style="margin-top:12px">
          <button class="toolbar-btn" id="btnApplyUnimpose" style="width:100%"><i class="fa fa-scissors"></i><?php _e("studio.pages_apply_unimpose", [], false); ?></button>
        </div>
      </div>
    </div>

    <!-- Riso Panel (hidden) -->
    <div id="panelRiso" style="display:none">
      <div class="panel-section">
        <div class="panel-section-title"><?php _e("studio.riso_color_mode", [], false); ?></div>
        <div class="panel-row">
          <select class="panel-select" id="selRisoMode">
            <option value="AUTO_BICHROMIE"><?php _e("studio.riso_mode_auto_bichromie", [], false); ?></option>
            <option value="RGB"><?php _e("studio.riso_mode_rgb", [], false); ?></option>
            <option value="CMYK"><?php _e("studio.riso_mode_cmyk", [], false); ?></option>
            <option value="2COLOR"><?php _e("studio.riso_mode_2color", [], false); ?></option>
            <option value="PIPETTE"><?php _e("studio.riso_mode_pipette", [], false); ?></option>
          </select>
        </div>
      </div>
      
      <div class="panel-section">
        <div class="panel-section-title"><?php _e("studio.riso_effects", [], false); ?></div>
        <div class="panel-row">
          <div class="panel-label"><?php _e("studio.riso_posterize_levels", [], false); ?><span class="panel-value" id="valRisoLevels">4</span></div>
          <input type="range" class="panel-slider" id="sliderRisoLevels" min="2" max="10" value="4">
        </div>
        <div class="panel-row" style="margin-top:4px">
          <button class="toolbar-btn" id="btnRisoPosterize" style="width:100%"><i class="fa fa-th"></i><?php _e("studio.riso_apply_posterize", [], false); ?></button>
        </div>
        
        <div class="panel-row" style="margin-top:12px">
          <div class="panel-label"><?php _e("studio.riso_halftone_size", [], false); ?><span class="panel-value" id="valRisoHalftone">3</span></div>
          <input type="range" class="panel-slider" id="sliderRisoHalftone" min="1" max="10" value="3">
        </div>
        <div class="panel-row" style="margin-top:4px">
          <button class="toolbar-btn" id="btnRisoHalftone" style="width:100%"><i class="fa fa-th-large"></i><?php _e("studio.riso_apply_halftone", [], false); ?></button>
        </div>
        
        <div class="panel-row" style="margin-top:12px">
          <button class="toolbar-btn" id="btnRisoReset" style="width:100%;color:#ef4444"><i class="fa fa-undo"></i><?php _e("studio.riso_reset", [], false); ?></button>
        </div>
      </div>

      <div class="panel-section" id="risoChannelsSection">
        <div class="panel-section-title"><?php _e("studio.riso_channels", [], false); ?></div>
        <div id="risoChannelsList">
          <!-- Dynamically populated via JS -->
        </div>
        <div class="panel-row" style="margin-top:12px; gap:8px;">
          <button class="panel-btn" id="btnRisoExportZip" style="flex:1">
            <i class="fa fa-file-archive"></i> <?php _e("studio.riso_export_zip", [], false); ?>
          </button>
          <button class="panel-btn primary" id="btnRisoExportPdf" style="flex:1">
            <i class="fa fa-file-pdf"></i> <?php _e("studio.riso_export_pdf", [], false); ?>
          </button>
        </div>
      </div>
    </div>
    
    <!-- Nouveau Panneau: OCR & Nettoyage de Scan -->
    <div id="panelOcr" style="display:none">
      <div class="panel-section">
        <div class="panel-section-title"><?php _e("studio.ocr_language", [], false); ?></div>
        <div class="panel-row">
          <select class="panel-select" id="selOcrLang">
            <option value="fra"><?php _e("studio.ocr_lang_french", [], false); ?></option>
            <option value="eng"><?php _e("studio.ocr_lang_english", [], false); ?></option>
          </select>
        </div>
      </div>
      
      <div class="panel-section">
        <div class="panel-section-title"><?php _e("studio.ocr_type", [], false); ?></div>
        <div class="panel-row">
          <select class="panel-select" id="selOcrType">
            <option value="skip_text"><?php _e("studio.ocr_type_skip_text", [], false); ?></option>
            <option value="force_ocr"><?php _e("studio.ocr_type_force_ocr", [], false); ?></option>
          </select>
        </div>
      </div>

      <div class="panel-section">
        <div class="panel-section-title"><?php _e("studio.ocr_clean_and_deskew", [], false); ?></div>
        <label style="display:flex;align-items:center;gap:8px;font-size:12px;color:#374151;margin-bottom:8px;cursor:pointer">
          <input type="checkbox" id="chkOcrDeskew" checked>
          <?php _e("studio.ocr_deskew_page", [], false); ?>
        </label>
        <label style="display:flex;align-items:center;gap:8px;font-size:12px;color:#374151;margin-bottom:8px;cursor:pointer">
          <input type="checkbox" id="chkOcrClean" checked>
          <?php _e("studio.ocr_clean_despeckle", [], false); ?>
        </label>
        <label style="display:flex;align-items:center;gap:8px;font-size:12px;color:#374151;margin-bottom:12px;cursor:pointer">
          <input type="checkbox" id="chkOcrOptimize">
          <?php _e("studio.ocr_optimize_file_size", [], false); ?>
        </label>
        <div style="height:1px;background:#e2e5ea;margin:12px 0;"></div>
        <div class="panel-section-title"><?php _e("studio.ocr_output_format", [], false); ?></div>
        <div class="panel-row" style="margin-bottom:12px;">
          <select class="panel-select" id="selOcrOutputFormat">
            <option value="pdf"><?php _e("studio.ocr_output_pdf", [], false); ?></option>
            <option value="docx_linear"><?php _e("studio.ocr_output_docx_linear", [], false); ?></option>
            <option value="docx_ia"><?php _e("studio.ocr_output_docx_ia", [], false); ?></option>
            <option value="docx_layout"><?php _e("studio.ocr_output_docx_layout", [], false); ?></option>
          </select>
        </div>
        <div class="panel-row">
          <button class="panel-btn primary" id="btnOcrRun" style="width:100%">
            <i class="fa fa-magic"></i> <?php _e("studio.ocr_run", [], false); ?>
          </button>
        </div>
      </div>
    </div>

    <!-- Nouveau Panneau: Modification PDF -->
    <div id="panelModification" style="display:none">
      <div class="panel-section">
        <div class="panel-section-title"><?php _e("studio.modification_tools", [], false); ?></div>
        <div class="panel-row" style="display: flex; flex-direction: column; gap: 5px;">
          <button class="panel-btn modif-tool-btn active" data-tool="none"><i class="fa fa-mouse-pointer" style="width: 20px;"></i><?php _e("studio.modification_tool_select", [], false); ?></button>
          <button class="panel-btn modif-tool-btn" data-tool="redact_text"><i class="fa fa-font" style="width: 20px;"></i><?php _e("studio.modification_tool_redact", [], false); ?></button>
          <button class="panel-btn modif-tool-btn" data-tool="page_number"><i class="fa fa-hashtag" style="width: 20px;"></i><?php _e("studio.modification_tool_page_number", [], false); ?></button>
          <button class="panel-btn modif-tool-btn" data-tool="strikeout"><i class="fa fa-strikethrough" style="width: 20px;"></i><?php _e("studio.modification_tool_strikeout", [], false); ?></button>
        </div>
      </div>
      
      <!-- Outil : Carré blanc + Texte -->
      <div id="modifToolRedact" class="panel-section" style="display:none">
        <div class="panel-section-title"><?php _e("studio.modification_redact", [], false); ?></div>
        <div class="panel-row">
          <input type="text" class="panel-select" id="modifRedactText" placeholder="<?= __('studio.modification_redact_placeholder') ?>" style="width:100%">
        </div>
        <div class="panel-row" style="margin-top:8px; align-items:center;">
          <div class="panel-label"><?php _e("studio.modification_redact_font", [], false); ?></div>
          <select class="panel-select" id="modifRedactFont" style="flex:1">
            <option value="helvetica"><?php _e("studio.modification_font_helvetica", [], false); ?></option>
            <option value="times"><?php _e("studio.modification_font_times", [], false); ?></option>
            <option value="courier"><?php _e("studio.modification_font_courier", [], false); ?></option>
          </select>
          <button class="panel-btn" id="btnIdentifyFont" title="<?= __('studio.identify_font_title') ?>" style="padding:4px 8px; margin-left:4px; color: var(--studio-primary);"><i class="fa fa-magic"></i></button>
          <button class="panel-btn btn-upload-font" title="<?= __('studio.import_font_title') ?>" style="padding:4px 8px; margin-left:4px"><i class="fa fa-upload"></i></button>
        </div>
        
        <!-- Modal inline pour les résultats de la reconnaissance de police -->
        <div id="fontRecognitionResults" style="display:none; margin-top:8px; background:white; border:1px solid var(--studio-border); border-radius:4px; padding:8px;">
          <div style="font-size:11px; font-weight:bold; margin-bottom:4px; color:var(--studio-primary);"><?php _e("studio.modification_font_recognition_title", [], false); ?></div>
          <div id="fontRecognitionList" style="display:flex; flex-direction:column; gap:4px;"></div>
          <button class="panel-btn" id="btnCancelFontRec" style="width:100%; margin-top:6px; font-size:10px;"><?php _e("studio.modification_cancel", [], false); ?></button>
        </div>
        <div class="panel-row" style="margin-top:8px">
          <div class="panel-label"><?php _e("studio.modification_redact_size", [], false); ?></div>
          <input type="number" class="panel-select" id="modifRedactSize" value="12" min="6" max="72">
        </div>
        <div class="panel-row" style="margin-top:8px">
          <label style="font-size:11px; cursor:pointer"><input type="checkbox" id="modifRedactBg" checked><?php _e("studio.modification_redact_white_bg", [], false); ?></label>
        </div>
        <p style="font-size:10px; color:#6b7280; margin-top:8px"><?php _e("studio.modification_redact_hint", [], false); ?></p>
      </div>

      <!-- Outil : Numéro de page -->
      <div id="modifToolPageNum" class="panel-section" style="display:none">
        <div class="panel-section-title"><?php _e("studio.modification_page_number", [], false); ?></div>
        <div class="panel-row">
          <div class="panel-label"><?php _e("studio.modification_page_num_format", [], false); ?></div>
          <input type="text" class="panel-select" id="modifPageNumFormat" value="{p}" placeholder="<?= __('studio.modification_page_num_placeholder') ?>">
        </div>
        <p style="font-size:10px; color:#6b7280; margin-top:4px; margin-bottom:8px"><?php _e("studio.modification_page_num_example_prefix", [], false); ?><b>Page {p} sur {t}</b><?php _e("studio.modification_page_num_example_suffix", [], false); ?></p>
        <div class="panel-row" style="margin-top:8px">
          <div class="panel-label"><?php _e("studio.modification_page_num_position", [], false); ?></div>
          <select class="panel-select" id="modifPageNumPosition">
            <option value="bottom_center"><?php _e("studio.modification_position_bottom_center", [], false); ?></option>
            <option value="bottom_left"><?php _e("studio.modification_position_bottom_left", [], false); ?></option>
            <option value="bottom_right"><?php _e("studio.modification_position_bottom_right", [], false); ?></option>
            <option value="top_center"><?php _e("studio.modification_position_top_center", [], false); ?></option>
            <option value="top_left"><?php _e("studio.modification_position_top_left", [], false); ?></option>
            <option value="top_right"><?php _e("studio.modification_position_top_right", [], false); ?></option>
          </select>
        </div>
        <div class="panel-row" style="margin-top:8px">
          <div class="panel-label"><?php _e("studio.modification_page_num_margin", [], false); ?></div>
          <input type="number" class="panel-select" id="modifPageNumMargin" value="10" min="0" max="100">
        </div>
        <div class="panel-row" style="margin-top:8px; align-items:center;">
          <div class="panel-label"><?php _e("studio.modification_page_num_font", [], false); ?></div>
          <select class="panel-select" id="modifPageNumFont" style="flex:1">
            <option value="helvetica"><?php _e("studio.modification_font_helvetica", [], false); ?></option>
            <option value="times"><?php _e("studio.modification_font_times", [], false); ?></option>
          </select>
          <button class="panel-btn btn-upload-font" title="<?= __('studio.import_font_title') ?>" style="padding:4px 8px; margin-left:4px"><i class="fa fa-upload"></i></button>
        </div>
        <input type="file" id="customFontUpload" accept=".ttf,.otf" style="display:none">
        <div class="panel-row" style="margin-top:8px">
          <div class="panel-label"><?php _e("studio.modification_page_num_size", [], false); ?></div>
          <input type="number" class="panel-select" id="modifPageNumSize" value="12" min="6" max="72">
        </div>
        <div class="panel-row" style="margin-top:8px">
          <div class="panel-label"><?php _e("studio.modification_page_num_range", [], false); ?></div>
          <input type="number" class="panel-select" id="modifPageNumStart" placeholder="<?= __('studio.modification_page_num_start') ?>" style="width:48%">
          <input type="number" class="panel-select" id="modifPageNumEnd" placeholder="<?= __('studio.modification_page_num_end') ?>" style="width:48%; margin-left:4%">
        </div>
        <div class="panel-row" style="margin-top:8px">
          <div class="panel-label"><?php _e("studio.modification_page_num_first_value", [], false); ?></div>
          <input type="number" class="panel-select" id="modifPageNumFirstVal" value="1" placeholder="<?= __('studio.modification_page_num_start_number') ?>" style="width:100%">
        </div>
        <p style="font-size:10px; color:#6b7280; margin-top:8px"><?php _e("studio.modification_page_num_hint", [], false); ?></p>
      </div>

      <!-- Outil : Biffer -->
      <div id="modifToolStrikeout" class="panel-section" style="display:none">
        <div class="panel-section-title"><?php _e("studio.modification_strikeout", [], false); ?></div>
        <div class="panel-row" style="display: flex; flex-direction: row; align-items: stretch; gap: 5px;">
          <input type="color" class="panel-select" id="modifStrikeColor" value="#000000" style="padding: 0; height: 32px; cursor: pointer; flex: 1; border-radius: 4px; border: 1px solid var(--studio-border);">
          <button class="panel-btn" id="btnEyeDropper" title="<?= __('studio.modification_eyedropper') ?>" style="padding: 0; height: 32px; width: 40px; flex-shrink: 0; display: flex; align-items: center; justify-content: center;"><i class="fa fa-eyedropper"></i></button>
        </div>
        <p style="font-size:10px; color:#6b7280; margin-top:8px"><?php _e("studio.modification_strikeout_hint", [], false); ?></p>
      </div>

      <div class="panel-section">
        <div class="panel-section-title"><?php _e("studio.modification_scope", [], false); ?></div>
        <div class="panel-row">
          <select class="panel-select" id="selModifScope">
            <option value="current"><?php _e("studio.modification_scope_current", [], false); ?></option>
            <option value="all"><?php _e("studio.modification_scope_all", [], false); ?></option>
            <option value="even"><?php _e("studio.modification_scope_even", [], false); ?></option>
            <option value="odd"><?php _e("studio.modification_scope_odd", [], false); ?></option>
          </select>
        </div>
        <div class="panel-row" style="margin-top:12px; gap:8px">
          <button class="panel-btn" id="btnModifClear" style="flex:1"><i class="fa fa-eraser"></i><?php _e("studio.modification_clear", [], false); ?></button>
          <button class="panel-btn primary" id="btnModifApply" style="flex:1"><i class="fa fa-check"></i><?php _e("studio.modification_apply", [], false); ?></button>
        </div>
      </div>
    </div>

    <!-- Nouveau Panneau: Métadonnées -->
    <div id="panelMetadata" style="display:none">
      <div class="panel-section">
        <div class="panel-section-title"><?php _e("studio.metadata_metadata", [], false); ?></div>
        
        <div class="panel-row">
          <div class="panel-label"><?php _e("studio.metadata_title", [], false); ?></div>
          <input type="text" class="panel-select" id="metaTitle" style="width:100%">
        </div>
        <div class="panel-row" style="margin-top:8px">
          <div class="panel-label"><?php _e("studio.metadata_author", [], false); ?></div>
          <input type="text" class="panel-select" id="metaAuthor" style="width:100%">
        </div>
        <div class="panel-row" style="margin-top:8px">
          <div class="panel-label"><?php _e("studio.metadata_subject", [], false); ?></div>
          <input type="text" class="panel-select" id="metaSubject" style="width:100%">
        </div>
        <div class="panel-row" style="margin-top:8px">
          <div class="panel-label"><?php _e("studio.metadata_keywords", [], false); ?></div>
          <input type="text" class="panel-select" id="metaKeywords" style="width:100%">
        </div>
        <div class="panel-row" style="margin-top:8px">
          <div class="panel-label"><?php _e("studio.metadata_creator", [], false); ?></div>
          <input type="text" class="panel-select" id="metaCreator" style="width:100%">
        </div>
        <div class="panel-row" style="margin-top:8px">
          <div class="panel-label"><?php _e("studio.metadata_producer", [], false); ?></div>
          <input type="text" class="panel-select" id="metaProducer" style="width:100%">
        </div>
        <div class="panel-row" style="margin-top:8px">
          <div class="panel-label" title="<?= __('studio.metadata_date_format_hint') ?>"><?php _e("studio.metadata_creation_date", [], false); ?></div>
          <input type="text" class="panel-select" id="metaCreationDate" placeholder="YYYY:MM:DD HH:MM:SS" style="width:100%">
        </div>
        <div class="panel-row" style="margin-top:8px">
          <div class="panel-label" title="<?= __('studio.metadata_date_format_hint') ?>"><?php _e("studio.metadata_modification_date", [], false); ?></div>
          <input type="text" class="panel-select" id="metaModDate" placeholder="YYYY:MM:DD HH:MM:SS" style="width:100%">
        </div>
        
        <div class="panel-row" style="margin-top:16px; gap:8px; display:flex">
          <button class="panel-btn primary" id="btnApplyMetadata" style="flex:1"><i class="fa fa-save"></i><?php _e("studio.metadata_apply", [], false); ?></button>
          <button class="panel-btn" id="btnClearMetadata" style="flex:1" title="<?php _e('studio.clear_all_metadata'); ?>"><i class="fa fa-trash"></i><?php _e("studio.metadata_clear", [], false); ?></button>
        </div>
        <p style="font-size:10px; color:#6b7280; margin-top:8px"><?php _e("studio.metadata_metadata_hint", [], false); ?></p>
        
        <div class="panel-section-title" style="margin-top: 24px;"><?php _e("studio.metadata_raw_metadata", [], false); ?></div>
        <div style="background: #f8fafc; border: 1px solid var(--studio-border); border-radius: 4px; padding: 8px; max-height: 300px; overflow-y: auto;">
          <pre id="metaRawInfo" style="font-size: 10px; color: #475569; margin: 0; white-space: pre-wrap; word-break: break-all;"><?php _e("studio.metadata_loading", [], false); ?></pre>
        </div>
      </div>
    </div>

  </aside>
</div>

<!-- Spinner Overlay -->
<div id="studioSpinner" style="display:none;position:fixed;inset:0;background:rgba(255,255,255,0.75);z-index:9999;display:none;align-items:center;justify-content:center;flex-direction:column;gap:12px">
  <div style="width:48px;height:48px;border:4px solid #e2e5ea;border-top-color:#4f6ef7;border-radius:50%;animation:spin 0.8s linear infinite"></div>
  <div style="font-family:Inter,sans-serif;font-size:14px;font-weight:500;color:#1a1d23" id="spinnerMsg"><?php _e("studio.spinner_loading", [], false); ?></div>
</div>
<!-- Toast -->
<div id="studioToast" style="display:none;position:fixed;bottom:24px;right:24px;z-index:10000;background:#fff;border:1px solid #e2e5ea;border-radius:12px;padding:16px 20px;box-shadow:0 4px 20px rgba(0,0,0,0.12);font-family:Inter,sans-serif;font-size:13px;max-width:340px"></div>
<style>@keyframes spin{to{transform:rotate(360deg)}}</style>

<!-- Result Modal -->
<div id="resultModal" style="display:none;position:fixed;inset:0;z-index:10001;background:rgba(10,12,20,0.65);backdrop-filter:blur(4px);align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,0.3);width:400px;max-width:90vw;overflow:hidden;display:flex;flex-direction:column;animation:popIn 0.3s ease-out">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #e2e5ea">
      <div style="font-family:Inter,sans-serif;font-weight:700;font-size:15px;color:#10b981">
        <i class="fa fa-check-circle" style="margin-right:8px"></i><?php _e("studio.result_file_ready", [], false); ?>
      </div>
      <button onclick="document.getElementById('resultModal').style.display='none'" style="border:none;background:transparent;cursor:pointer;font-size:20px;color:#6b7280;line-height:1">×</button>
    </div>
    <div style="padding:24px 20px;display:flex;flex-direction:column;gap:12px;align-items:center;text-align:center">
      <p style="margin:0;font-family:Inter,sans-serif;font-size:14px;color:#374151"><?php _e("studio.result_file_saved_as", [], false); ?></p>
      <div style="font-family:Inter,sans-serif;font-weight:600;font-size:13px;color:#6b7280;word-break:break-all" id="resultModalFilename"></div>
      
      <div style="display:flex;gap:12px;margin-top:8px;width:100%">
        <a id="resultModalDownloadBtn" href="#" style="flex:1;padding:12px;border-radius:8px;background:linear-gradient(135deg,#4f6ef7,#6f42c1);color:#fff;text-decoration:none;font-family:Inter,sans-serif;font-size:13px;font-weight:600;display:flex;align-items:center;justify-content:center;gap:8px">
            <i class="fa fa-download"></i> <?php _e("studio.result_download", [], false); ?>
          </a>
          <button id="resultModalReopenBtn" style="flex:1;padding:12px;border:none;border-radius:8px;background:linear-gradient(135deg,#10b981,#059669);color:#fff;cursor:pointer;font-family:Inter,sans-serif;font-size:13px;font-weight:600;display:flex;align-items:center;justify-content:center;gap:8px">
            <i class="fa fa-folder-open"></i> <?php _e("studio.result_reopen", [], false); ?>
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
        <i class="fa fa-eye" style="color:#4f6ef7;margin-right:8px"></i><?php _e('studio.imposition_preview'); ?>
        <span id="impPreviewPageLabel" style="font-weight:400;font-size:12px;color:#6b7280;margin-left:8px"></span>
      </div>
      <button id="impPreviewClose" style="border:none;background:transparent;cursor:pointer;font-size:20px;color:#6b7280;line-height:1">×</button>
    </div>
    <!-- Image preview -->
    <div style="flex:1;overflow:auto;background:#f3f4f6;display:flex;align-items:center;justify-content:center;padding:20px;min-height:300px">
      <img id="impPreviewImg" src="" alt="<?= __('studio.preview_alt') ?>" style="max-width:100%;max-height:60vh;border-radius:6px;box-shadow:0 4px 20px rgba(0,0,0,0.15);display:none">
      <div id="impPreviewLoading" style="text-align:center;color:#6b7280;font-family:Inter,sans-serif;font-size:13px">
        <div style="width:36px;height:36px;border:3px solid #e2e5ea;border-top-color:#4f6ef7;border-radius:50%;animation:spin 0.8s linear infinite;margin:0 auto 12px"></div>
        <?php _e("studio.preview_generating", [], false); ?>
      </div>
    </div>
    <!-- Footer -->
    <div style="padding:16px 20px;border-top:1px solid #e2e5ea;display:flex;gap:10px;justify-content:flex-end">
      <button id="impPreviewCloseBtn" style="padding:10px 20px;border:1px solid #e2e5ea;border-radius:8px;background:#fff;cursor:pointer;font-family:Inter,sans-serif;font-size:13px;font-weight:500;color:#374151"><?php _e("studio.preview_close", [], false); ?></button>
      <button id="impPreviewLoadApp" style="padding:10px 20px;border:none;border-radius:8px;background:linear-gradient(135deg,#10b981,#059669);color:#fff;cursor:pointer;font-family:Inter,sans-serif;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:6px">
        <i class="fa fa-folder-open"></i> <?php _e("studio.preview_load_in_studio", [], false); ?>
      </button>
      <a id="impPreviewDownload" href="#" style="padding:10px 20px;border:none;border-radius:8px;background:linear-gradient(135deg,#4f6ef7,#6f42c1);color:#fff;cursor:pointer;font-family:Inter,sans-serif;font-size:13px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:6px">
        <i class="fa fa-download"></i> <?php _e("studio.preview_download_pdf", [], false); ?>
      </a>
    </div>
  </div>
</div>

<script src="<?= $base_path ?>js/studio.js" defer></script>
</body>
</html>
