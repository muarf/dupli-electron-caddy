(function() {
  let isInitialized = false;
  let canvas = null;
  let currentTool = 'none';
  let isIdentifyingFont = false;
  let fontCropRect = null;

  function $id(id) { return document.getElementById(id); }

  window.initStudioModification = function() {
    if (isInitialized) {
      syncCanvasSize();
      return;
    }
    
    // Initialize Fabric Canvas on the modificationCanvas
    canvas = new fabric.Canvas('modificationCanvas', {
      selection: true,
      preserveObjectStacking: true,
      backgroundColor: 'transparent'
    });
    
    syncCanvasSize();
    
    // Observe window resize or standard canvas size changes to keep overlay aligned
    window.addEventListener('resize', syncCanvasSize);
    
    const btnIdentifyFont = $id('btnIdentifyFont');
    if (btnIdentifyFont) {
        btnIdentifyFont.addEventListener('click', () => {
            isIdentifyingFont = !isIdentifyingFont;
            if (isIdentifyingFont) {
                btnIdentifyFont.style.background = 'var(--studio-primary)';
                btnIdentifyFont.style.color = 'white';
                canvas.defaultCursor = 'crosshair';
                alert((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.studio_modification.tracez_un_rectangle_serr__auto'] || "Tracez un rectangle serré autour du texte dont vous voulez reconnaître la police."));
            } else {
                btnIdentifyFont.style.background = '';
                btnIdentifyFont.style.color = 'var(--studio-primary)';
                canvas.defaultCursor = (currentTool !== 'none') ? 'crosshair' : 'default';
            }
        });
    }

    const btnCancelFontRec = $id('btnCancelFontRec');
    if (btnCancelFontRec) {
        btnCancelFontRec.addEventListener('click', () => {
            $id('fontRecognitionResults').style.display = 'none';
        });
    }

    function updateBrushSettings() {
      if (!canvas) return;
      if (!canvas.freeDrawingBrush) {
        canvas.freeDrawingBrush = new fabric.PencilBrush(canvas);
      }
      const mode = $id('modifBrushMode') ? $id('modifBrushMode').value : 'erase';
      const size = parseInt($id('sliderBrushSize') ? $id('sliderBrushSize').value : 12, 10);
      const color = mode === 'erase' ? '#ffffff' : ($id('modifBrushColor') ? $id('modifBrushColor').value : '#000000');
      
      canvas.freeDrawingBrush.width = size;
      canvas.freeDrawingBrush.color = color;
      canvas.freeDrawingBrush.strokeLineCap = 'round';
      canvas.freeDrawingBrush.strokeLineJoin = 'round';
    }

    const selBrushMode = $id('modifBrushMode');
    if (selBrushMode) {
      selBrushMode.addEventListener('change', () => {
        const rowColor = $id('rowBrushColor');
        if (rowColor) rowColor.style.display = (selBrushMode.value === 'draw') ? 'flex' : 'none';
        updateBrushSettings();
      });
    }

    const sliderBrush = $id('sliderBrushSize');
    if (sliderBrush) {
      sliderBrush.addEventListener('input', () => {
        const valSpan = $id('valBrushSize');
        if (valSpan) valSpan.textContent = sliderBrush.value + ' px';
        updateBrushSettings();
      });
    }

    const colorBrush = $id('modifBrushColor');
    if (colorBrush) {
      colorBrush.addEventListener('input', () => {
        updateBrushSettings();
      });
    }

    const sliderLine = $id('sliderLineSize');
    if (sliderLine) {
      sliderLine.addEventListener('input', () => {
        const valSpan = $id('valLineSize');
        if (valSpan) valSpan.textContent = sliderLine.value + ' px';
      });
    }

    // Tool selection via buttons
    const toolBtns = document.querySelectorAll('.modif-tool-btn');
    toolBtns.forEach(btn => {
      btn.addEventListener('click', (e) => {
        toolBtns.forEach(b => b.classList.remove('active', 'primary'));
        const targetBtn = e.currentTarget;
        targetBtn.classList.add('active', 'primary');
        
        currentTool = targetBtn.getAttribute('data-tool');
        
        $id('modifToolRedact').style.display = (currentTool === 'redact_text') ? 'block' : 'none';
        $id('modifToolPageNum').style.display = (currentTool === 'page_number') ? 'block' : 'none';
        $id('modifToolStrikeout').style.display = (currentTool === 'strikeout') ? 'block' : 'none';
        if ($id('modifToolBrush')) $id('modifToolBrush').style.display = (currentTool === 'brush') ? 'block' : 'none';
        if ($id('modifToolLine')) $id('modifToolLine').style.display = (currentTool === 'line') ? 'block' : 'none';
        
        canvas.defaultCursor = (currentTool !== 'none') ? 'crosshair' : 'default';
        canvas.isDrawingMode = (currentTool === 'brush');
        canvas.selection = (currentTool === 'none'); // Allow dragging multiple only if not drawing
        
        if (currentTool === 'brush') {
          updateBrushSettings();
        }

        if (currentTool !== 'none') {
            canvas.discardActiveObject();
            canvas.requestRenderAll();
        }
      });
    });

    // Tag brush paths when created
    canvas.on('path:created', function(e) {
      if (e && e.path) {
        e.path.set('modifType', 'brush');
        e.path.set('brushMode', $id('modifBrushMode') ? $id('modifBrushMode').value : 'erase');
      }
    });

    // Drag to draw (Strikeout / Line / Rect)
    let isDrawing = false;
    let isPickingColor = false;
    let colorPickTargetInputId = null;
    let colorPickCallback = null;
    let rect = null;
    let currentLine = null;
    let origX, origY;

    canvas.on('mouse:down', function(o) {
      if (isPickingColor) {
        isPickingColor = false;
        canvas.defaultCursor = (currentTool !== 'none') ? 'crosshair' : 'default';
        const stdCanvas = $id('studioCanvas');
        if (stdCanvas) {
            const ctx = stdCanvas.getContext('2d', { willReadFrequently: true });
            const rectDOM = stdCanvas.getBoundingClientRect();
            const x = (o.e.clientX - rectDOM.left) * (stdCanvas.width / rectDOM.width);
            const y = (o.e.clientY - rectDOM.top) * (stdCanvas.height / rectDOM.height);
            try {
                const pixel = ctx.getImageData(x, y, 1, 1).data;
                const hex = "#" + ("000000" + ((1 << 24) + (pixel[0] << 16) + (pixel[1] << 8) + pixel[2]).toString(16).slice(1)).slice(-6);
                if (colorPickTargetInputId && $id(colorPickTargetInputId)) {
                  $id(colorPickTargetInputId).value = hex;
                } else {
                  $id('modifStrikeColor').value = hex;
                }
                if (colorPickCallback) {
                  colorPickCallback(hex);
                }
            } catch(e) {}
        }
        colorPickTargetInputId = null;
        colorPickCallback = null;
        return;
      }
      
      if (isIdentifyingFont) {
        const pointer = canvas.getPointer(o.e);
        isDrawing = true;
        origX = pointer.x;
        origY = pointer.y;
        fontCropRect = new fabric.Rect({
            left: origX,
            top: origY,
            width: 0,
            height: 0,
            fill: 'rgba(59, 130, 246, 0.2)',
            stroke: '#3b82f6',
            strokeWidth: 1,
            strokeDashArray: [5, 5],
            selectable: false,
            evented: false
        });
        canvas.add(fontCropRect);
        return;
      }

      if (currentTool === 'none') return;
      
      // Si l'utilisateur clique sur un objet existant pour le déplacer/modifier, on ne crée pas de nouvel objet.
      if (o.target) return;

      const pointer = canvas.getPointer(o.e);
      isDrawing = true;
      origX = pointer.x;
      origY = pointer.y;

      if (currentTool === 'redact_text') {
        // Redact uses a text box with optional background
        const txt = $id('modifRedactText').value || 'Texte';
        const fontSize = parseInt($id('modifRedactSize').value || 12, 10);
        const fontFamily = $id('modifRedactFont').value || 'helvetica';
        const bg = $id('modifRedactBg').checked ? 'white' : 'transparent';
        
        const textObj = new fabric.IText(txt, {
          left: origX,
          top: origY,
          fontSize: fontSize * 2, // scale up slightly for visual clarity
          fontFamily: fontFamily,
          backgroundColor: bg,
          fill: 'black',
          originX: 'left',
          originY: 'top',
          padding: 5
        });
        textObj.set('modifType', 'redact_text');
        canvas.add(textObj);
        canvas.setActiveObject(textObj);
        canvas.renderAll();
        isDrawing = false; // Just click to add text
      } else if (currentTool === 'strikeout') {
        const color = $id('modifStrikeColor').value || 'black';
        rect = new fabric.Rect({
          left: origX,
          top: origY,
          originX: 'left',
          originY: 'top',
          width: pointer.x - origX,
          height: pointer.y - origY,
          fill: color,
          transparentCorners: false
        });
        rect.set('modifType', currentTool);
        canvas.add(rect);
        canvas.renderAll();
      } else if (currentTool === 'line') {
        const strokeColor = $id('modifLineColor') ? $id('modifLineColor').value : '#000000';
        const strokeWidth = parseInt($id('sliderLineSize') ? $id('sliderLineSize').value : 2, 10);
        currentLine = new fabric.Line([origX, origY, origX, origY], {
          stroke: strokeColor,
          strokeWidth: strokeWidth,
          strokeLineCap: 'round',
          strokeLineJoin: 'round',
          selectable: true,
          hasControls: true
        });
        currentLine.set('modifType', 'line');
        canvas.add(currentLine);
      }
      else if (currentTool === 'page_number') {
        const format = $id('modifPageNumFormat').value || '{p}';
        const fontSize = parseInt($id('modifPageNumSize').value || 12, 10);
        const fontFamily = $id('modifPageNumFont').value || 'helvetica';
        
        const numObj = new fabric.IText(format, {
          left: origX,
          top: origY,
          fontSize: fontSize * 2, // scale up
          fontFamily: fontFamily,
          fill: 'black',
          originX: 'left',
          originY: 'top',
          padding: 5
        });
        numObj.set('modifType', 'page_number');
        canvas.add(numObj);
        canvas.setActiveObject(numObj);
        canvas.renderAll();
        isDrawing = false;
      }
    });

    canvas.on('mouse:move', function(o) {
      if (!isDrawing) return;
      const pointer = canvas.getPointer(o.e);
      
      if (isIdentifyingFont && fontCropRect) {
          if (origX > pointer.x) { fontCropRect.set({ left: Math.abs(pointer.x) }); }
          if (origY > pointer.y) { fontCropRect.set({ top: Math.abs(pointer.y) }); }
          fontCropRect.set({ width: Math.abs(origX - pointer.x) });
          fontCropRect.set({ height: Math.abs(origY - pointer.y) });
          canvas.renderAll();
          return;
      }

      if (currentTool === 'line' && currentLine) {
        let targetX = pointer.x;
        let targetY = pointer.y;
        
        // Verrouillage avec Shift (horizontal ou vertical)
        if (o.e && o.e.shiftKey) {
          const dx = Math.abs(targetX - origX);
          const dy = Math.abs(targetY - origY);
          if (dx > dy) {
            targetY = origY;
          } else {
            targetX = origX;
          }
        }
        
        currentLine.set({ x2: targetX, y2: targetY });
        currentLine.setCoords();
        canvas.renderAll();
        return;
      }
      
      if (!rect) return;
      if (currentTool === 'strikeout') {
        if (origX > pointer.x) {
          rect.set({ left: Math.abs(pointer.x) });
        }
        if (origY > pointer.y) {
          rect.set({ top: Math.abs(pointer.y) });
        }
        rect.set({ width: Math.abs(origX - pointer.x) });
        rect.set({ height: Math.abs(origY - pointer.y) });
        canvas.renderAll();
      }
    });

    canvas.on('mouse:up', async function(o) {
      isDrawing = false;
      
      if (isIdentifyingFont && fontCropRect) {
          const w = fontCropRect.width;
          const h = fontCropRect.height;
          
          if (w > 10 && h > 10) {
              const stdCanvas = $id('studioCanvas');
              const ctx = stdCanvas.getContext('2d');
              
              const scaleX = stdCanvas.width / canvas.getWidth();
              const scaleY = stdCanvas.height / canvas.getHeight();
              
              const extractCanvas = document.createElement('canvas');
              extractCanvas.width = w * scaleX;
              extractCanvas.height = h * scaleY;
              const eCtx = extractCanvas.getContext('2d');
              
              eCtx.drawImage(
                  stdCanvas, 
                  fontCropRect.left * scaleX, fontCropRect.top * scaleY, w * scaleX, h * scaleY,
                  0, 0, extractCanvas.width, extractCanvas.height
              );
              
              const base64Img = extractCanvas.toDataURL('image/jpeg', 0.9);
              
              canvas.remove(fontCropRect);
              fontCropRect = null;
              isIdentifyingFont = false;
              const btnIdentify = $id('btnIdentifyFont');
              if (btnIdentify) {
                  btnIdentify.style.background = '';
                  btnIdentify.style.color = 'var(--studio-primary)';
              }
              canvas.defaultCursor = (currentTool !== 'none') ? 'crosshair' : 'default';
              
              if (window.showSpinner) window.showSpinner();
              if ($id('spinnerMsg')) $id('spinnerMsg').textContent = (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.studio_modification.l_ia_analyse_la_police'] || "L'IA analyse la police...");
              
              function renderFontRecognitionResults(fonts) {
                  $id('fontRecognitionResults').style.display = 'block';
                  const listCont = $id('fontRecognitionList');
                  listCont.innerHTML = '';
                  fonts.forEach(f => {
                      const conf = (f.score * 100).toFixed(1);
                      const cleanName = f.label.replace(/[-_]/g, ' ');
                      const btn = document.createElement('button');
                      btn.className = 'panel-btn';
                      btn.style.textAlign = 'left';
                      btn.style.fontSize = '10px';
                      btn.style.padding = '4px';
                      btn.innerHTML = `<b>${cleanName}</b> <span style="float:right; opacity:0.6">${conf}%</span>`;
                      
                      btn.onclick = async () => {
                          const fontNameRaw = f.label.split('-')[0].split('_')[0];
                          const cssUrl = `https://fonts.googleapis.com/css2?family=${fontNameRaw}&display=swap`;
                          
                          if (window.showSpinner) {
                              if ($id('spinnerMsg')) $id('spinnerMsg').textContent = (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.studio_modification.installation_de_la_police'] || "Installation de la police...");
                              window.showSpinner();
                          }
                          
                          try {
                              // 1. Load in browser for preview
                              const link = document.createElement('link');
                              link.href = cssUrl;
                              link.rel = 'stylesheet';
                              document.head.appendChild(link);
                              
                              // 2. Ask server to download the TTF
                              const fd = new FormData();
                              fd.append('action', 'download_google_font');
                              fd.append('font_name', fontNameRaw);
                              
                              const res = await fetch('?studio_process', { method: 'POST', body: fd });
                              const json = await res.json();
                              
                              if (json.success && json.font_name) {
                                  addFontToSelects(json.font_name, fontNameRaw);
                                  if ($id('modifRedactFont')) $id('modifRedactFont').value = json.font_name;
                              } else {
                                  if (json.error === 'offline') {
                                      alert((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.studio_modification.pas_de_connexion_internet_s'] || "⚠️ Pas de connexion internet sur le serveur (ou lien direct introuvable).\n\nL'application n'a pas pu télécharger automatiquement le fichier .ttf pour la génération PDF finale.\n\nVeuillez télécharger la police manuellement sur votre ordinateur (ex: depuis Google Fonts) et l(window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.studio_modification.importer_via_le_bouton_avec_l'] || 'importer via le bouton avec l')icône de nuage."));
                                  } else {
                                      alert((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.studio_modification.erreur_lors_de_l_installation'] || "Erreur lors de l'installation sur le serveur : ") + (json.error || "Inconnue"));
                                  }
                                  addFontToSelects(fontNameRaw, fontNameRaw);
                              }
                              
                              // Switch to text redact tool if not already
                              const textToolBtn = document.querySelector('.modif-tool-btn[data-tool="redact_text"]');
                              if (textToolBtn && currentTool !== 'redact_text') {
                                  textToolBtn.click();
                              }
                          } catch (e) {
                              alert((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.studio_modification.erreur_r_seau__t_l_chargement'] || "Erreur réseau (téléchargement police): ") + e.message);
                              addFontToSelects(fontNameRaw, fontNameRaw);
                          }
                          
                          if (window.hideSpinner) window.hideSpinner();
                          $id('fontRecognitionResults').style.display = 'none';
                      };
                      listCont.appendChild(btn);
                  });
              }

              try {
                  const fd = new FormData();
                  fd.append('action', 'recognize_font');
                  fd.append('image', base64Img);
                  
                  const res = await fetch('?studio_process', { method: 'POST', body: fd });
                  const json = await res.json();
                  
                  if (json.success) {
                      if (json.job_id && window.pollStudioTask) {
                          window.pollStudioTask(json, (finalJson) => {
                              if (window.hideSpinner) window.hideSpinner();
                              if (finalJson.fonts) {
                                  renderFontRecognitionResults(finalJson.fonts);
                              } else {
                                  alert((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.studio_modification.aucune_police_d_tect_e_dans_le'] || "Aucune police détectée dans le résultat."));
                              }
                          }, (errJson) => {
                              if (window.hideSpinner) window.hideSpinner();
                              alert((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.studio_modification.erreur_ia'] || "Erreur IA: ") + (errJson.error || errJson.errors?.join(', ') || 'Inconnue'));
                          });
                      } else if (json.fonts) {
                          if (window.hideSpinner) window.hideSpinner();
                          renderFontRecognitionResults(json.fonts);
                      } else {
                          if (window.hideSpinner) window.hideSpinner();
                          alert("Aucune police reconnue.");
                      }
                  } else {
                      if (window.hideSpinner) window.hideSpinner();
                      const errorMsg = json.error || (json.errors ? json.errors.join(', ') : "Inconnue");
                      alert((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.studio_modification.erreur_ia'] || "Erreur IA: ") + errorMsg);
                  }
              } catch (err) {
                  if (window.hideSpinner) window.hideSpinner();
                  alert((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.studio_modification.erreur_r_seau'] || "Erreur réseau: ") + err.message);
              }
          } else {
              canvas.remove(fontCropRect);
              fontCropRect = null;
          }
          return;
      }

      if (currentTool === 'line' && currentLine) {
        const dx = (currentLine.x2 || 0) - (currentLine.x1 || 0);
        const dy = (currentLine.y2 || 0) - (currentLine.y1 || 0);
        const length = Math.sqrt(dx * dx + dy * dy);
        if (length < 3) {
          canvas.remove(currentLine);
        } else {
          currentLine.setCoords();
          canvas.setActiveObject(currentLine);
          canvas.renderAll();
        }
        currentLine = null;
      }

      if (rect) {
        rect.setCoords();
        rect = null;
      }
    });
    
    // Keyboard delete
    window.addEventListener('keydown', (e) => {
      if ($id('panelModification').style.display === 'none') return;
      if (e.key === 'Delete' || e.key === 'Backspace') {
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) return;
        const activeObjects = canvas.getActiveObjects();
        if (activeObjects.length) {
          canvas.discardActiveObject();
          activeObjects.forEach(obj => canvas.remove(obj));
        }
      }
    });

    // Apply Button
    const btnApply = $id('btnModifApply');
    if (btnApply) {
      btnApply.addEventListener('click', applyModification);
    }

    // Clear Button
    const btnClear = $id('btnModifClear');
    if (btnClear) {
      btnClear.addEventListener('click', () => {
        if (canvas) {
          canvas.clear();
          canvas.backgroundColor = 'transparent';
          canvas.renderAll();
        }
      });
    }

    // Universal EyeDropper function
    function setupEyeDropper(btnId, targetColorInputId, onColorSelected) {
      const btn = $id(btnId);
      if (!btn) return;
      btn.addEventListener('click', async () => {
        if (window.EyeDropper) {
          try {
            const eyeDropper = new EyeDropper();
            const result = await eyeDropper.open();
            const input = $id(targetColorInputId);
            if (input) input.value = result.sRGBHex;
            if (onColorSelected) onColorSelected(result.sRGBHex);
          } catch (e) {
            // User canceled
          }
        } else {
          isPickingColor = true;
          colorPickTargetInputId = targetColorInputId;
          colorPickCallback = onColorSelected;
          canvas.defaultCursor = 'crosshair';
          alert((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.studio_modification.pipette_activ_e___cliquez_sur'] || "Pipette activée : cliquez sur l'image du document pour capturer la couleur."));
        }
      });
    }

    setupEyeDropper('btnEyeDropper', 'modifStrikeColor');
    setupEyeDropper('btnBrushEyeDropper', 'modifBrushColor', () => updateBrushSettings());
    setupEyeDropper('btnLineEyeDropper', 'modifLineColor');
    // === FONTS CUSTOM ===
    loadCustomFonts();

    const uploadBtns = document.querySelectorAll('.btn-upload-font');
    const fontInput = $id('customFontUpload');
    
    if (fontInput) {
      uploadBtns.forEach(btn => {
        btn.addEventListener('click', () => fontInput.click());
      });

      fontInput.addEventListener('change', async (e) => {
        if (!e.target.files.length) return;
        const file = e.target.files[0];
        
        const fd = new FormData();
        fd.append('action', 'upload_font');
        fd.append('font', file);
        
        try {
          // Si spinner existe
          if (window.showSpinner) window.showSpinner();
          
          const res = await fetch('?studio_process', { method: 'POST', body: fd });
          const json = await res.json();
          
          if (window.hideSpinner) window.hideSpinner();
          
          if (json.success && json.font_name) {
            await injectFont(json.font_name, json.url);
            addFontToSelects(json.font_name, json.font_name);
            alert(CONFIG.translations['studio_studio-modification.font_added'] || 'Police ajoutée avec succès !');
          } else {
            alert("Erreur: " + (json.error || CONFIG.translations['studio_studio-modification.upload_failed'] || 'Upload échoué'));
          }
        } catch(err) {
          if (window.hideSpinner) window.hideSpinner();
          alert((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.studio_modification.erreur_r_seau'] || "Erreur réseau: ") + err.message);
        }
        fontInput.value = ''; // reset
      });
    }

    isInitialized = true;
  };
  
  async function loadCustomFonts() {
    try {
      const fd = new FormData();
      fd.append('action', 'list_fonts');
      const res = await fetch('?studio_process', { method: 'POST', body: fd });
      const json = await res.json();
      if (json.success && json.fonts) {
        for (const font of json.fonts) {
          await injectFont(font.name, font.url);
          addFontToSelects(font.name, font.name);
        }
      }
    } catch(e) {
      console.error((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.studio_modification.erreur_chargement_polices'] || "Erreur chargement polices"), e);
    }
  }

  async function injectFont(name, url) {
    try {
      const fontFace = new FontFace(name, `url(${url})`);
      const loaded = await fontFace.load();
      document.fonts.add(loaded);
    } catch(e) {
      console.error((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.studio_modification.erreur_injection_police'] || "Erreur injection police"), name, e);
    }
  }

  function addFontToSelects(value, label) {
    ['modifRedactFont', 'modifPageNumFont'].forEach(id => {
      const select = $id(id);
      if (!select) return;
      // Check if already exists
      const exists = Array.from(select.options).some(opt => opt.value === value);
      if (!exists) {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = label + ' (Custom)';
        select.appendChild(option);
      }
      select.value = value; // Select it by default
    });
  }
  
  function syncCanvasSize() {
    const stdCanvas = $id('studioCanvas');
    const container = $id('modificationContainer');
    if (stdCanvas && container && canvas) {
      const rect = stdCanvas.getBoundingClientRect();
      const parentRect = stdCanvas.parentElement.getBoundingClientRect();
      
      // Position relative to parent container
      container.style.top = (rect.top - parentRect.top) + 'px';
      container.style.left = (rect.left - parentRect.left) + 'px';
      
      const w = stdCanvas.clientWidth || stdCanvas.width;
      const h = stdCanvas.clientHeight || stdCanvas.height;
      
      if (canvas.getWidth() !== w || canvas.getHeight() !== h) {
        canvas.setWidth(w);
        canvas.setHeight(h);
        canvas.renderAll();
      }
    }
  }

  function getLineEndpoints(lineObj) {
    try {
      const matrix = lineObj.calcTransformMatrix ? lineObj.calcTransformMatrix() : null;
      if (!matrix) {
        return { x1: lineObj.x1 || 0, y1: lineObj.y1 || 0, x2: lineObj.x2 || 0, y2: lineObj.y2 || 0 };
      }
      let p1Local, p2Local;
      if (typeof lineObj.calcLinePoints === 'function') {
        const pts = lineObj.calcLinePoints();
        p1Local = { x: pts.x1, y: pts.y1 };
        p2Local = { x: pts.x2, y: pts.y2 };
      } else {
        const w = lineObj.width || 0;
        const h = lineObj.height || 0;
        p1Local = { x: (lineObj.x1 || 0) - w / 2, y: (lineObj.y1 || 0) - h / 2 };
        p2Local = { x: (lineObj.x2 || 0) - w / 2, y: (lineObj.y2 || 0) - h / 2 };
      }
      const p1 = fabric.util.transformPoint(p1Local, matrix);
      const p2 = fabric.util.transformPoint(p2Local, matrix);
      return { x1: p1.x, y1: p1.y, x2: p2.x, y2: p2.y };
    } catch (e) {
      return { x1: lineObj.x1 || 0, y1: lineObj.y1 || 0, x2: lineObj.x2 || 0, y2: lineObj.y2 || 0 };
    }
  }

  async function applyModification() {
    if (!window.state || !window.state.file) {
      alert((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.studio_modification.aucun_document_charg'] || "Aucun document chargé."));
      return;
    }
    
    const stdCanvas = $id('studioCanvas');
    if (!stdCanvas) return;
    
    // On a besoin de savoir à quelle échelle le PDF est affiché (px par point)
    // Dans studio.html.php, l'image (pdf) occupe la dimension du canvas.
    // L'échelle est `stdCanvas.width / pdfOriginalWidthPts`.
    // Pour simplifier on va passer des coordonnées relatives (pourcentage) au backend, 
    // ou calculer les points PDF depuis les pixels écran.
    
    const cw = canvas.getWidth();
    const ch = canvas.getHeight();
    
    const ops = [];

    // Si des coups de pinceau sont présents, les exporter en calque haute résolution transparent
    const brushObjects = canvas.getObjects().filter(o => o.modifType === 'brush');
    let brushOverlayDataUrl = null;
    if (brushObjects.length > 0) {
      const allObjects = canvas.getObjects();
      allObjects.forEach(obj => {
        obj._prevVisible = obj.visible;
        obj.visible = (obj.modifType === 'brush');
      });
      canvas.discardActiveObject();
      canvas.renderAll();
      
      brushOverlayDataUrl = canvas.toDataURL({
        format: 'png',
        multiplier: 2
      });
      
      allObjects.forEach(obj => {
        obj.visible = obj._prevVisible;
        delete obj._prevVisible;
      });
      canvas.renderAll();
    }
    
    // 1. Récupérer les éléments vectoriels dessinés sur le Canvas
    canvas.getObjects().forEach(obj => {
      if (obj.modifType === 'brush') {
        // Géré via le calque transparent brushOverlayDataUrl
        return;
      }

      if (obj.modifType === 'line') {
        const ep = getLineEndpoints(obj);
        ops.push({
          type: 'line',
          relX1: ep.x1 / cw,
          relY1: ep.y1 / ch,
          relX2: ep.x2 / cw,
          relY2: ep.y2 / ch,
          relWidth: ((obj.strokeWidth || 2) * (obj.scaleX || 1)) / cw,
          color: obj.stroke || '#000000'
        });
        return;
      }

      const bbox = obj.getBoundingRect();
      // Calcul relatif [0, 1]
      const relX = bbox.left / cw;
      const relY = bbox.top / ch;
      const relW = bbox.width / cw;
      const relH = bbox.height / ch;
      
      if (obj.modifType === 'redact_text') {
        ops.push({
          type: 'redact_text',
          relX, relY, relW, relH,
          text: obj.text,
          font: obj.fontFamily,
          size: obj.fontSize, // Will scale appropriately in backend
          bg: obj.backgroundColor === 'white'
        });
      } else if (obj.modifType === 'strikeout') {
        ops.push({
          type: obj.modifType,
          relX, relY, relW, relH,
          color: obj.fill
        });
      } else if (obj.modifType === 'page_number') {
        ops.push({
          type: 'page_number',
          relX, relY, relW, relH,
          format: obj.text || $id('modifPageNumFormat').value || '{p}',
          font: obj.fontFamily || $id('modifPageNumFont').value || 'helvetica',
          size: obj.fontSize ? (obj.fontSize / 2) : parseInt($id('modifPageNumSize').value || 12, 10),
          rangeStart: parseInt($id('modifPageNumStart').value, 10) || null,
          rangeEnd: parseInt($id('modifPageNumEnd').value, 10) || null,
          firstVal: parseInt($id('modifPageNumFirstVal').value, 10) || 1,
          position: 'custom' // Flag for backend to use relX/relY
        });
      }
    });
    
    // Fallback: si l'outil est page_number mais que l'utilisateur n'a rien dessiné, 
    // on l'ajoute automatiquement via les paramètres globaux (préréglages)
    if (currentTool === 'page_number' && !ops.some(o => o.type === 'page_number')) {
      ops.push({
        type: 'page_number',
        format: $id('modifPageNumFormat').value || '{p}',
        position: $id('modifPageNumPosition').value || 'bottom_center',
        margin: parseFloat($id('modifPageNumMargin').value || 10),
        font: $id('modifPageNumFont').value || 'helvetica',
        size: parseInt($id('modifPageNumSize').value || 12, 10),
        rangeStart: parseInt($id('modifPageNumStart').value, 10) || null,
        rangeEnd: parseInt($id('modifPageNumEnd').value, 10) || null,
        firstVal: parseInt($id('modifPageNumFirstVal').value, 10) || 1
      });
    }

    if (ops.length === 0 && !brushOverlayDataUrl) {
      alert((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.studio_modification.aucune_modification___applique'] || "Aucune modification à appliquer (Dessinez sur le canevas ou activez la numérotation)."));
      return;
    }

    const payload = {
      scope: $id('selModifScope').value || 'current',
      currentPage: window.state ? window.state.currentPage : 1,
      operations: ops,
      brush_overlay: brushOverlayDataUrl
    };

    const formData = new FormData();
    formData.append('action', 'modification');
    formData.append('data', JSON.stringify(payload));

    if (window.currentStudioFile) {
      formData.append('file_id', window.currentStudioFile);
    } else if (window.currentStudioTempFile) {
      formData.append('studio_temp_file', window.currentStudioTempFile);
    } else {
      // It's a local file upload, get the File object from state
      if (window.state.rawFile) {
        formData.append('file', window.state.rawFile);
      } else if (window.state.file && window.state.file instanceof File) {
         formData.append('file', window.state.file);
      } else {
         const fileInput = $id('pdfUpload');
         if (fileInput && fileInput.files[0]) {
             formData.append('file', fileInput.files[0]);
         }
      }
    }

    const spinner = $id('studioSpinner');
    const spinnerMsg = $id('spinnerMsg');
    if (spinner) {
      spinnerMsg.textContent = (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.studio_modification.application_des_modifications'] || "Application des modifications...");
      spinner.style.display = 'flex';
    }

    try {
      const res = await fetch('?studio_process', { method: 'POST', body: formData });
      const json = await res.json();
      if (spinner) spinner.style.display = 'none';

      if (json.success) {
        if (json.job_id && window.pollStudioTask) {
          window.pollStudioTask(json, (finalJson) => {
            if (window.showResultToast) {
              window.showResultToast(finalJson.download_url);
            } else {
              alert(CONFIG.translations['studio_studio-modification.modifications_applied'] || 'Modifications appliquées avec succès !');
              window.location.href = finalJson.download_url;
            }
          }, (errJson) => {
            alert("Erreur: " + (errJson.error || errJson.errors?.join(', ') || (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.studio_modification.erreur_inconnue'] || 'Erreur inconnue')));
          });
        } else {
          if (window.showResultToast) {
            window.showResultToast(json.download_url);
          } else {
            alert(CONFIG.translations['studio_studio-modification.modifications_applied'] || 'Modifications appliquées avec succès !');
            window.location.href = json.download_url;
          }
        }
      } else {
        alert("Erreur: " + (json.error || json.errors.join(', ')));
      }
    } catch(err) {
      if (spinner) spinner.style.display = 'none';
      alert((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.studio_modification.erreur_de_connexion'] || "Erreur de connexion: ") + err.message);
    }
  }

})();
