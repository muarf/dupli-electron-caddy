(function() {
  let isInitialized = false;
  let canvas = null;
  let pdfDocs = []; // Array of { file, name, pdf }
  
  let currentPlancheIdx = 0;
  let planches = [ { format: 'A4', orientation: 'portrait', state: null } ];
  
  const formats = {
    A3: { w: 297, h: 420 },
    A4: { w: 210, h: 297 },
    A5: { w: 148, h: 210 }
  };
  
  const MM_TO_PX = 3; // 1mm = 3px pour l'affichage à l'écran

  window.initStudioMontage = function() {
    if (isInitialized) return;
    
    // Initialiser le canvas Fabric
    canvas = new fabric.Canvas('montageCanvas', {
      preserveObjectStacking: true,
      backgroundColor: '#ffffff'
    });
    window.montageCanvas = canvas;
    
    // Événements d'aimantation (snapping) au centre de la planche
    canvas.on('object:moving', function(e) {
      const obj = e.target;
      const midX = canvas.getWidth() / 2;
      const midY = canvas.getHeight() / 2;
      const snapTolerance = 5; // px
      
      if (Math.abs(obj.left - midX) < snapTolerance) {
        obj.set({ left: midX });
      }
      if (Math.abs(obj.top - midY) < snapTolerance) {
        obj.set({ top: midY });
      }
    });

    // Forcer le rendu des lignes de guidage lors des transformations
    canvas.on('object:scaling', () => canvas.renderAll());
    canvas.on('object:rotating', () => canvas.renderAll());

    // Tracer les traits de guidage dynamiques vers les règles après le rendu
    canvas.on('after:render', function() {
      const obj = canvas.getActiveObject();
      if (!obj) return;
      
      const ctx = canvas.getContext();
      ctx.save();
      
      const midX = canvas.getWidth() / 2;
      const midY = canvas.getHeight() / 2;
      const left = obj.left;
      const top = obj.top;
      
      const isCenteredX = Math.abs(left - midX) <= 1;
      const isCenteredY = Math.abs(top - midY) <= 1;
      
      // Dessiner les traits de guidage en pointillés
      ctx.setLineDash([4, 4]);
      
      // Ligne verticale vers l'axe X (règle du haut) depuis le centre
      ctx.beginPath();
      ctx.moveTo(left, top);
      ctx.lineTo(left, 0);
      ctx.strokeStyle = isCenteredX ? '#2ec4b6' : '#4f6ef7';
      ctx.lineWidth = isCenteredX ? 2 : 1;
      ctx.stroke();
      
      // Ligne horizontale vers l'axe Y (règle de gauche) depuis le centre
      ctx.beginPath();
      ctx.moveTo(left, top);
      ctx.lineTo(0, top);
      ctx.strokeStyle = isCenteredY ? '#2ec4b6' : '#4f6ef7';
      ctx.lineWidth = isCenteredY ? 2 : 1;
      ctx.stroke();
      
      // Dessiner des lignes de projection plus fines pour les bords de la boîte englobante
      const bbox = obj.getBoundingRect(true);
      ctx.setLineDash([2, 4]);
      ctx.strokeStyle = 'rgba(79, 110, 247, 0.4)';
      ctx.lineWidth = 1;
      
      // Projection du bord gauche vers le haut
      ctx.beginPath();
      ctx.moveTo(bbox.left, bbox.top);
      ctx.lineTo(bbox.left, 0);
      ctx.stroke();
      
      // Projection du bord droit vers le haut
      ctx.beginPath();
      ctx.moveTo(bbox.left + bbox.width, bbox.top);
      ctx.lineTo(bbox.left + bbox.width, 0);
      ctx.stroke();
      
      // Projection du bord supérieur vers la gauche
      ctx.beginPath();
      ctx.moveTo(bbox.left, bbox.top);
      ctx.lineTo(0, bbox.top);
      ctx.stroke();
      
      // Projection du bord inférieur vers la gauche
      ctx.beginPath();
      ctx.moveTo(bbox.left, bbox.top + bbox.height);
      ctx.lineTo(0, bbox.top + bbox.height);
      ctx.stroke();
      
      ctx.restore();
    });
    
    // Configurer les poignées de redimensionnement
    fabric.Object.prototype.transparentCorners = false;
    fabric.Object.prototype.cornerColor = '#4f6ef7';
    fabric.Object.prototype.cornerStyle = 'circle';
    fabric.Object.prototype.borderColor = '#4f6ef7';

    // Raccourcis clavier (Supprimer un objet)
    window.addEventListener('keydown', (e) => {
      // Vérifier que le panneau montage est actif
      if (document.getElementById('panelMontage').style.display === 'none') return;
      if (e.key === 'Delete' || e.key === 'Backspace') {
        // Ignorer si on est dans un input
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) return;
        const activeObjects = canvas.getActiveObjects();
        if (activeObjects.length) {
          canvas.discardActiveObject();
          activeObjects.forEach(obj => canvas.remove(obj));
        }
      }
    });

    updateCanvasSize();
    renderPlanchesUI();
    
    // Listeners UI
    document.getElementById('montageFormat').addEventListener('change', () => {
      planches[currentPlancheIdx].format = document.getElementById('montageFormat').value;
      updateCanvasSize();
    });
    document.getElementById('montageOrientation').addEventListener('change', () => {
      planches[currentPlancheIdx].orientation = document.getElementById('montageOrientation').value;
      updateCanvasSize();
    });
    
    document.getElementById('btnAddPlanche').addEventListener('click', () => {
      saveCurrentPlanche();
      planches.push({ format: planches[currentPlancheIdx].format, orientation: planches[currentPlancheIdx].orientation, state: null });
      currentPlancheIdx = planches.length - 1;
      canvas.clear();
      canvas.backgroundColor = '#ffffff';
      document.getElementById('montageFormat').value = planches[currentPlancheIdx].format;
      document.getElementById('montageOrientation').value = planches[currentPlancheIdx].orientation;
      updateCanvasSize();
      renderPlanchesUI();
    });
    
    document.getElementById('btnGenerateMontage').addEventListener('click', generatePdf);
    
    // Upload PDF
    document.getElementById('btnMontageUpload').addEventListener('click', () => {
      document.getElementById('montageUploadPdf').click();
    });
    
    document.getElementById('montageUploadPdf').addEventListener('change', handlePdfUpload);
    
    isInitialized = true;
    console.log('[Montage] Initialisé.');
  };
  
  function updateCanvasSize() {
    const p = planches[currentPlancheIdx];
    let w_mm = formats[p.format].w;
    let h_mm = formats[p.format].h;
    
    if (p.orientation === 'landscape') {
      [w_mm, h_mm] = [h_mm, w_mm];
    }
    
    const w_px = w_mm * MM_TO_PX;
    const h_px = h_mm * MM_TO_PX;
    
    canvas.setWidth(w_px);
    canvas.setHeight(h_px);
    canvas.renderAll();
    
    // Mettre à jour les règles X et Y
    const rulerX = document.getElementById('montageRulerX');
    const rulerY = document.getElementById('montageRulerY');
    if (rulerX && rulerY) {
      rulerX.width = w_px;
      rulerX.height = 30;
      rulerY.width = 30;
      rulerY.height = h_px;
      
      rulerX.style.width = w_px + 'px';
      rulerY.style.height = h_px + 'px';
      
      drawRulerX(rulerX, w_mm);
      drawRulerY(rulerY, h_mm);
    }
    
    // Ajuster le conteneur Grid (canvas + 30px de règle)
    const gridContainer = document.getElementById('montageGridContainer');
    if (gridContainer) {
      gridContainer.style.width = (w_px + 30) + 'px';
      gridContainer.style.minWidth = (w_px + 30) + 'px';
      gridContainer.style.height = (h_px + 30) + 'px';
      gridContainer.style.minHeight = (h_px + 30) + 'px';
    }
  }

  function drawRulerX(canvasEl, max_mm) {
    const ctx = canvasEl.getContext('2d');
    ctx.clearRect(0, 0, canvasEl.width, canvasEl.height);
    
    ctx.fillStyle = '#f8fafc';
    ctx.fillRect(0, 0, canvasEl.width, canvasEl.height);
    
    ctx.strokeStyle = '#cbd5e1';
    ctx.fillStyle = '#64748b';
    ctx.font = '9px Inter, sans-serif';
    ctx.textAlign = 'center';
    
    for (let mm = 0; mm <= max_mm; mm++) {
      const x = mm * MM_TO_PX;
      ctx.beginPath();
      ctx.moveTo(x, 30);
      
      if (mm % 10 === 0) {
        // Graduation en centimètre
        ctx.lineTo(x, 18);
        ctx.stroke();
        const cm = mm / 10;
        if (cm > 0 && x < canvasEl.width - 10) {
          ctx.fillText(cm.toString(), x, 12);
        }
      } else if (mm % 5 === 0) {
        // Demi-centimètre
        ctx.lineTo(x, 22);
        ctx.stroke();
      } else {
        // Millimètre
        ctx.lineTo(x, 25);
        ctx.stroke();
      }
    }
  }

  function drawRulerY(canvasEl, max_mm) {
    const ctx = canvasEl.getContext('2d');
    ctx.clearRect(0, 0, canvasEl.width, canvasEl.height);
    
    ctx.fillStyle = '#f8fafc';
    ctx.fillRect(0, 0, canvasEl.width, canvasEl.height);
    
    ctx.strokeStyle = '#cbd5e1';
    ctx.fillStyle = '#64748b';
    ctx.font = '9px Inter, sans-serif';
    ctx.textAlign = 'right';
    ctx.textBaseline = 'middle';
    
    for (let mm = 0; mm <= max_mm; mm++) {
      const y = mm * MM_TO_PX;
      ctx.beginPath();
      ctx.moveTo(30, y);
      
      if (mm % 10 === 0) {
        // Graduation en centimètre
        ctx.lineTo(18, y);
        ctx.stroke();
        const cm = mm / 10;
        if (cm > 0 && y < canvasEl.height - 10) {
          ctx.fillText(cm.toString(), 14, y);
        }
      } else if (mm % 5 === 0) {
        // Demi-centimètre
        ctx.lineTo(22, y);
        ctx.stroke();
      } else {
        // Millimètre
        ctx.lineTo(25, y);
        ctx.stroke();
      }
    }
  }

  function saveCurrentPlanche() {
    if (canvas) {
      planches[currentPlancheIdx].state = canvas.toJSON(['source_fileId', 'source_pageNum', 'original_width_mm', 'original_height_mm']);
    }
  }

  function renderPlanchesUI() {
    const list = document.getElementById('montagePlanchesList');
    list.innerHTML = '';
    planches.forEach((p, idx) => {
      const btn = document.createElement('button');
      btn.className = 'panel-btn' + (idx === currentPlancheIdx ? ' active' : '');
      btn.style.padding = '4px 8px';
      btn.style.fontSize = '12px';
      if (idx === currentPlancheIdx) {
        btn.style.background = 'var(--studio-primary)';
        btn.style.color = '#fff';
      }
      btn.textContent = `Planche ${idx + 1}`;
      btn.onclick = () => switchPlanche(idx);
      list.appendChild(btn);
    });
  }

  function switchPlanche(idx) {
    if (idx === currentPlancheIdx) return;
    saveCurrentPlanche();
    currentPlancheIdx = idx;
    
    const p = planches[idx];
    document.getElementById('montageFormat').value = p.format;
    document.getElementById('montageOrientation').value = p.orientation;
    updateCanvasSize();
    
    if (p.state) {
      canvas.loadFromJSON(p.state, canvas.renderAll.bind(canvas));
    } else {
      canvas.clear();
      canvas.backgroundColor = '#ffffff';
      canvas.renderAll();
    }
    renderPlanchesUI();
  }

  async function generatePdf() {
    saveCurrentPlanche();
    
    // Verifier s'il y a des objets
    let hasObjects = false;
    for (let p of planches) {
      if (p.state && p.state.objects && p.state.objects.length > 0) hasObjects = true;
    }
    if (!hasObjects) {
      alert("Votre montage est vide !");
      return;
    }

    // Build payload
    const payload = {
      planches: planches.map(p => {
        let w_mm = formats[p.format].w;
        let h_mm = formats[p.format].h;
        if (p.orientation === 'landscape') [w_mm, h_mm] = [h_mm, w_mm];
        
        const objects = (p.state ? p.state.objects : []).map(obj => {
          // Calculate scale taking into account that fabric uses center origin
          // We must send standard coordinates or let backend handle center origin
          return {
            source_fileId: obj.source_fileId,
            page_num: obj.source_pageNum,
            x_px: obj.left,
            y_px: obj.top,
            scale_x: obj.scaleX,
            scale_y: obj.scaleY,
            angle: obj.angle,
            angle: obj.angle,
            original_width_mm: obj.original_width_mm,
            original_height_mm: obj.original_height_mm,
            is_image: obj.is_image ? true : false,
            mm_to_px: MM_TO_PX
          };
        });

        return {
          format: p.format,
          width_mm: w_mm,
          height_mm: h_mm,
          objects: objects
        };
      })
    };

    // Envoyer au serveur (similaire à serverProcess)
    const spinner = document.getElementById('studioSpinner');
    const spinnerMsg = document.getElementById('spinnerMsg');
    if (spinner) {
      spinnerMsg.textContent = "Génération du PDF...";
      spinner.style.display = 'flex';
    }

    const fd = new FormData();
    fd.append('action', 'montage_libre');
    fd.append('payload', JSON.stringify(payload));
    
    // Attacher les fichiers sources nécessaires
    const neededFileIds = new Set();
    payload.planches.forEach(p => {
      p.objects.forEach(obj => neededFileIds.add(obj.source_fileId));
    });
    
    neededFileIds.forEach(id => {
      const docInfo = pdfDocs.find(d => d.id === id);
      if (docInfo) {
        fd.append('file_' + id, docInfo.rawFile);
      }
    });

    try {
      const res = await fetch('?studio_process', { method: 'POST', body: fd });
      const json = await res.json();
      if (spinner) spinner.style.display = 'none';
      if (json.success && json.download_url) {
        window.setPdfReady && window.setPdfReady(json.download_url);
        if (json.preview_url && window.openImpPreview) {
          window.openImpPreview(json.preview_url, json.download_url);
        } else if (window.showResultToast) {
          window.showResultToast(json.download_url);
        } else {
          window.location.href = json.download_url;
        }
      } else {
        alert("Erreur: " + (json.errors || []).join(', '));
      }
    } catch(e) {
      if (spinner) spinner.style.display = 'none';
      alert("Erreur réseau: " + e.message);
    }
  }

  async function handlePdfUpload(e) {
    const files = Array.from(e.target.files);
    if (!files.length) return;
    
    for (const file of files) {
      await window.addFileToMontage(file);
    }
    e.target.value = ''; // Reset
  }

  let loadedFileNames = new Set();

  window.addFileToMontage = async function(file) {
    if (!file) return;
    if (loadedFileNames.has(file.name)) return;
    loadedFileNames.add(file.name);
    
    // Afficher un spinner (utiliser la logique existante de studio)
    const spinner = document.getElementById('studioSpinner');
    const spinnerMsg = document.getElementById('spinnerMsg');
    if (spinner) {
      spinnerMsg.textContent = "Extraction des pages...";
      spinner.style.display = 'flex';
    }

    try {
      const isImage = file.type.startsWith('image/');
      const fileId = pdfDocs.length;
      
      if (isImage) {
        const url = URL.createObjectURL(file);
        const img = new Image();
        await new Promise((res, rej) => {
          img.onload = res;
          img.onerror = rej;
          img.src = url;
        });
        
        pdfDocs.push({
          id: fileId,
          name: file.name,
          isImage: true,
          img: img,
          rawFile: file
        });
        
        await renderImageThumbnail(fileId);
      } else {
        const reader = new FileReader();
        const arrayBuffer = await new Promise((resolve) => {
          reader.onload = (re) => resolve(re.target.result);
          reader.readAsArrayBuffer(file);
        });
        
        const data = new Uint8Array(arrayBuffer);
        const pdf = await pdfjsLib.getDocument({ data }).promise;
        
        pdfDocs.push({
          id: fileId,
          name: file.name,
          pdf: pdf,
          rawFile: file
        });
        
        await renderPdfThumbnails(fileId);
      }
    } catch (err) {
      console.error(err);
      alert('Erreur lors de la lecture du PDF : ' + err.message);
    } finally {
      if (spinner) spinner.style.display = 'none';
    }
  };

  async function renderPdfThumbnails(fileId) {
    const docInfo = pdfDocs[fileId];
    const container = document.getElementById('montageSourceThumbs');
    
    for (let i = 1; i <= docInfo.pdf.numPages; i++) {
      const page = await docInfo.pdf.getPage(i);
      // Récupérer la taille originale pour l'échelle
      const viewport = page.getViewport({ scale: 1 });
      
      // Thumbnail scale
      const thumbScale = 100 / viewport.width;
      const thumbViewport = page.getViewport({ scale: thumbScale });
      
      const thumbCanvas = document.createElement('canvas');
      thumbCanvas.width = thumbViewport.width;
      thumbCanvas.height = thumbViewport.height;
      const ctx = thumbCanvas.getContext('2d');
      
      await page.render({ canvasContext: ctx, viewport: thumbViewport }).promise;
      
      const thumbDiv = document.createElement('div');
      thumbDiv.style.border = '1px solid #e2e5ea';
      thumbDiv.style.borderRadius = '4px';
      thumbDiv.style.overflow = 'hidden';
      thumbDiv.style.cursor = 'pointer';
      thumbDiv.style.position = 'relative';
      thumbDiv.style.background = '#fff';
      thumbDiv.title = `Ajouter au montage (Page ${i})`;
      
      thumbCanvas.style.width = '100%';
      thumbCanvas.style.height = 'auto';
      thumbCanvas.style.display = 'block';
      
      const label = document.createElement('div');
      label.textContent = `P.${i}`;
      label.style.position = 'absolute';
      label.style.bottom = '0';
      label.style.right = '0';
      label.style.background = 'rgba(0,0,0,0.6)';
      label.style.color = 'white';
      label.style.fontSize = '10px';
      label.style.padding = '2px 4px';
      
      thumbDiv.appendChild(thumbCanvas);
      thumbDiv.appendChild(label);
      
      // Au clic, ajouter au canvas principal
      thumbDiv.addEventListener('click', () => addPageToCanvas(fileId, i));
      
      container.appendChild(thumbDiv);
    }
  }

  async function addPageToCanvas(fileId, pageNum) {
    console.log(`[Montage] addPageToCanvas appelé pour fileId=${fileId}, pageNum=${pageNum}`);
    const docInfo = pdfDocs[fileId];
    
    try {
      const page = await docInfo.pdf.getPage(pageNum);
      console.log(`[Montage] getPage ok`);
      
      const scaleFactor = (25.4 / 72) * MM_TO_PX;
      const viewport = page.getViewport({ scale: scaleFactor });
      console.log(`[Montage] viewport: width=${viewport.width}, height=${viewport.height}`);
      
      const tempCanvas = document.createElement('canvas');
      tempCanvas.width = viewport.width;
      tempCanvas.height = viewport.height;
      const ctx = tempCanvas.getContext('2d');
      
      console.log(`[Montage] Lancement du render pdf.js...`);
      await page.render({ canvasContext: ctx, viewport: viewport }).promise;
      console.log(`[Montage] Render pdf.js terminé.`);
      
      const imgUrl = tempCanvas.toDataURL('image/png');
      console.log(`[Montage] toDataURL généré, longueur : ${imgUrl.length}`);
      
      fabric.Image.fromURL(imgUrl, function(img) {
        if (!img) {
          console.error(`[Montage] fabric.Image.fromURL a retourné null`);
          return;
        }
        console.log(`[Montage] fabric.Image instancié, dimensions: ${img.width}x${img.height}`);
        
        img.set({
          left: canvas.getWidth() / 2,
          top: canvas.getHeight() / 2,
          originX: 'center',
          originY: 'center',
          cornerSize: 10,
          transparentCorners: false
        });
        
        img.set('source_fileId', fileId);
        img.set('source_pageNum', pageNum);
        img.set('original_width_mm', viewport.width / MM_TO_PX);
        img.set('original_height_mm', viewport.height / MM_TO_PX);
        
        console.log(`[Montage] Ajout de l'image au canevas Fabric...`);
        canvas.add(img);
        canvas.setActiveObject(img);
        canvas.renderAll();
        console.log(`[Montage] Terminé.`);
      });
    } catch (err) {
      console.error(`[Montage] Erreur dans addPageToCanvas:`, err);
    }
  }

  async function renderImageThumbnail(fileId) {
    const docInfo = pdfDocs[fileId];
    const container = document.getElementById('montageSourceThumbs');
    
    const thumbDiv = document.createElement('div');
    thumbDiv.style.border = '1px solid #e2e5ea';
    thumbDiv.style.borderRadius = '4px';
    thumbDiv.style.overflow = 'hidden';
    thumbDiv.style.cursor = 'pointer';
    thumbDiv.style.position = 'relative';
    thumbDiv.style.background = '#fff';
    thumbDiv.title = `Ajouter au montage (Image)`;
    
    const thumbImg = document.createElement('img');
    thumbImg.src = docInfo.img.src;
    thumbImg.style.width = '100%';
    thumbImg.style.height = 'auto';
    thumbImg.style.display = 'block';
    
    thumbDiv.appendChild(thumbImg);
    thumbDiv.addEventListener('click', () => addImageToCanvas(fileId));
    container.appendChild(thumbDiv);
  }

  async function addImageToCanvas(fileId) {
    const docInfo = pdfDocs[fileId];
    
    // Assume 96 DPI for standard web images to get mm dimensions
    const w_mm = docInfo.img.naturalWidth * (25.4 / 96);
    const h_mm = docInfo.img.naturalHeight * (25.4 / 96);
    
    fabric.Image.fromURL(docInfo.img.src, function(img) {
      if (!img) return;
      
      // Calculate visual scale so it matches the canvas mm scale
      const target_w_px = w_mm * MM_TO_PX;
      const scale = target_w_px / docInfo.img.naturalWidth;
      
      img.set({
        left: canvas.getWidth() / 2,
        top: canvas.getHeight() / 2,
        originX: 'center',
        originY: 'center',
        scaleX: scale,
        scaleY: scale,
        cornerSize: 10,
        transparentCorners: false
      });
      
      img.set('source_fileId', fileId);
      img.set('source_pageNum', 1);
      img.set('original_width_mm', w_mm);
      img.set('original_height_mm', h_mm);
      img.set('is_image', true);
      
      canvas.add(img);
      canvas.setActiveObject(img);
      canvas.renderAll();
    });
  }

})();
