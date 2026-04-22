<div class="container mr-top-20">
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default" style="border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: none; overflow: visible;">
                <div class="panel-heading" style="background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: white; padding: 25px; border: none; border-radius: 15px 15px 0 0;">
                    <h2 class="panel-title" style="font-size: 26px; font-weight: bold;">
                        <i class="fa fa-th-large" style="margin-right: 15px;"></i>
                        <?php _e('pdf_organizer.title'); ?>
                    </h2>
                    <p style="opacity: 0.8; margin-top: 5px;"><?php _e('pdf_organizer.subtitle'); ?></p>
                </div>
                
                <div class="panel-body" style="padding: 0; background-color: #f8f9fa; min-height: 500px; position: relative;">
                    
                    <!-- Upload Zone (Hidden once files are loaded) -->
                    <div id="upload-container" style="padding: 80px 40px; text-align: center;">
                        <div id="drop-zone" style="border: 3px dashed #cbd5e0; border-radius: 20px; padding: 60px; background: white; transition: all 0.3s ease; position: relative; cursor: pointer;">
                            <div style="font-size: 70px; color: #a0aec0; margin-bottom: 20px;">
                                <i class="fa fa-files-o"></i>
                            </div>
                            <h3 style="color: #4a5568; font-weight: bold; position: relative; z-index: 2; pointer-events: none;"><?php _e('pdf_organizer.empty_msg'); ?></h3>
                            <button class="btn btn-primary btn-lg" style="margin-top: 20px; border-radius: 30px; padding: 12px 40px; background: #2a5298; border: none; box-shadow: 0 4px 10px rgba(42, 82, 152, 0.3); position: relative; z-index: 2; pointer-events: none;">
                                <i class="fa fa-plus-circle"></i> <?php _e('pdf_organizer.add_files'); ?>
                            </button>
                            <input type="file" id="pdf-input" multiple accept="application/pdf" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 5;">
                        </div>
                    </div>

                    <!-- Main Organizer Interface (Hidden until upload) -->
                    <div id="organizer-interface" style="display: none; padding: 30px;">
                        <div id="pages-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 25px;">
                            <!-- Pages will be injected here -->
                        </div>
                    </div>

                    <!-- Fixed Bottom Toolbar -->
                    <div id="toolbar" style="display: none; position: sticky; bottom: 0; left: 0; right: 0; background: white; padding: 15px 30px; box-shadow: 0 -10px 25px rgba(0,0,0,0.05); border-top: 1px solid #edf2f7; z-index: 100; display: flex; justify-content: space-between; align-items: center; border-radius: 0 0 15px 15px;">
                        <div style="color: #718096; font-weight: 500;">
                            <span id="total-pages">0</span> <?php _e('pdf_organizer.page'); ?>(s)
                        </div>
                        <div class="btn-group">
                            <button id="add-pdf" class="btn btn-default" style="border-radius: 20px 0 0 20px; padding: 8px 15px;">
                                <i class="fa fa-file-pdf-o" style="color: #e53e3e;"></i> <?php _e('pdf_organizer.add_pdf'); ?>
                            </button>
                            <button id="add-blank" class="btn btn-default" style="padding: 8px 15px;">
                                <i class="fa fa-plus" style="color: #48bb78;"></i> <?php _e('pdf_organizer.insert_blank'); ?>
                            </button>
                            <button id="clear-all" class="btn btn-default" style="border-radius: 0 20px 20px 0; padding: 8px 15px; color: #f56565;">
                                <i class="fa fa-trash-o"></i> <?php _e('pdf_organizer.clear_all'); ?>
                            </button>
                        </div>
                        <button id="btn-generate" class="btn btn-success btn-lg" style="border-radius: 30px; padding: 10px 35px; font-weight: bold; background: #38a169; border: none; box-shadow: 0 4px 12px rgba(56, 161, 105, 0.3);">
                            <i class="fa fa-magic"></i> <?php _e('pdf_organizer.generate'); ?>
                        </button>
                    </div>

                    <!-- Loading Overlay -->
                    <div id="loading-overlay" style="display: none; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.9); z-index: 1000; flex-direction: column; align-items: center; justify-content: center; border-radius: 0 0 15px 15px;">
                        <div class="spinner-border" style="width: 3rem; height: 3rem; color: #2a5298;" role="status">
                            <i class="fa fa-circle-o-notch fa-spin fa-3x"></i>
                        </div>
                        <p id="loading-text" style="margin-top: 20px; font-weight: bold; color: #2a5298;"></p>
                    </div>

                </div>
            </div>
            
            <div class="text-center" style="margin-top: 20px;">
                <a href="?accueil" class="btn btn-link" style="color: #2a5298;">
                    <i class="fa fa-arrow-left"></i> Précédent
                </a>
            </div>
        </div>
    </div>
</div>

<style>
#drop-zone:hover { border-color: #2a5298; background: #ebf4ff; }
.page-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    padding: 10px;
    position: relative;
    transition: all 0.2s ease;
    cursor: grab;
    border: 2px solid transparent;
}
.page-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
    border-color: #2a5298;
}
.page-thumb-container {
    width: 100%;
    aspect-ratio: 1 / 1.41;
    background: #edf2f7;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    position: relative;
}
.page-thumb-container img {
    max-width: 100%;
    max-height: 100%;
    transition: transform 0.3s ease;
}
.page-info {
    margin-top: 10px;
    font-size: 11px;
    color: #a0aec0;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.page-number {
    position: absolute;
    top: -10px;
    left: -10px;
    background: #2a5298;
    color: white;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: bold;
    z-index: 5;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}
.page-actions {
    position: absolute;
    top: 5px;
    right: 5px;
    display: flex;
    flex-direction: column;
    gap: 5px;
    opacity: 0;
    transition: opacity 0.2s ease;
    z-index: 10;
}
.page-card:hover .page-actions { opacity: 1; }
.action-btn {
    width: 28px;
    height: 28px;
    background: white;
    border: none;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    color: #4a5568;
    cursor: pointer;
    transition: all 0.2s;
}
.action-btn:hover { background: #2a5298; color: white; }
.action-btn.delete:hover { background: #f56565; color: white; }

/* Ghost element during drag */
.dragging { opacity: 0.4; }
.drag-over { border: 2px dashed #2a5298; }

#btn-generate.disabled { background: #a0aec0; box-shadow: none; pointer-events: none; }

/* Allow clicking translation editable spans when editing is enabled */
.translation-editable.editing-enabled {
    position: relative !important;
    z-index: 20 !important;
    pointer-events: auto !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('drop-zone');
    const pdfInput = document.getElementById('pdf-input');
    const interface = document.getElementById('organizer-interface');
    const uploadContainer = document.getElementById('upload-container');
    const pagesGrid = document.getElementById('pages-grid');
    const toolbar = document.getElementById('toolbar');
    const loadingOverlay = document.getElementById('loading-overlay');
    const loadingText = document.getElementById('loading-text');
    const totalPagesLabel = document.getElementById('total-pages');
    
    let sessionId = null;
    let pageSequence = []; // List of {type, file_id, page_num, rotation}
    let justDropped = false; // Global flag to prevent click after drop

    // Initialize
    pdfInput.addEventListener('change', (e) => handleFiles(e.target.files));
    
    // Add PDF button
    document.getElementById('add-pdf').addEventListener('click', () => pdfInput.click());

    // Drag and drop events for file upload - handle via input as it covers the area
    pdfInput.addEventListener('dragenter', () => dropZone.style.borderColor = '#2a5298');
    pdfInput.addEventListener('dragleave', () => dropZone.style.borderColor = '#cbd5e0');
    pdfInput.addEventListener('drop', () => dropZone.style.borderColor = '#cbd5e0');

    // Global window events for generic drop
    window.addEventListener('dragover', (e) => e.preventDefault());
    window.addEventListener('drop', (e) => {
        if (e.target === pdfInput) return; // Handled by input event
        e.preventDefault();
        if (e.dataTransfer.files.length) handleFiles(e.dataTransfer.files);
    });

    function handleFiles(files) {
        if (!files.length) return;
        
        showLoading("<?php _ejs('pdf_organizer.processing_files'); ?>");
        
        const formData = new FormData();
        for (let file of files) formData.append('pdfs[]', file);
        formData.append('action', 'upload');
        if (sessionId) formData.append('session_id', sessionId);

        fetch('?pdf_organizer', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                sessionId = data.session_id;
                addPagesToSequence(data.pages);
                renderUI();
            } else {
                alert("Erreur: " + data.error);
            }
        })
        .catch(err => {
            console.error(err);
            alert("Erreur lors de l'upload.");
        })
        .finally(() => hideLoading());
    }

    function addPagesToSequence(newPages) {
        newPages.forEach(p => {
            pageSequence.push({
                id: Math.random().toString(36).substr(2, 9),
                type: 'page',
                file_id: p.file_id,
                file_name: p.file_name,
                page_num: p.page_num,
                thumb_url: p.thumb_url,
                rotation: 0
            });
        });
        // Hide upload container after first file
        if (pageSequence.length > 0) {
            uploadContainer.style.display = 'none';
            interface.style.display = 'block';
            toolbar.style.display = 'flex';
        }
    }

    function renderUI() {
        console.log('renderUI: pages=' + pageSequence.length);
        pagesGrid.innerHTML = '';
        
        pageSequence.forEach((p, idx) => {
            const card = createPageCard(p, idx);
            pagesGrid.appendChild(card);
        });
        
        totalPagesLabel.textContent = pageSequence.length;
        initDragAndDrop();
    }

    function createPageCard(p, idx) {
        const div = document.createElement('div');
        div.className = 'page-card';
        div.draggable = true;
        div.dataset.id = p.id;
        
        // Prevent click after drop
        div.addEventListener('mousedown', (e) => {
            if (justDropped) {
                e.stopPropagation();
                e.preventDefault();
                console.log('CLICK blocked due to justDropped');
            }
        });

        const isBlank = p.type === 'blank';
        const thumbStyle = p.rotation ? `transform: rotate(${p.rotation}deg)` : '';

        div.innerHTML = `
            <div class="page-number">${idx + 1}</div>
            <div class="page-actions">
                ${!isBlank ? `
                <button class="action-btn" onclick="rotatePage('${p.id}', -90)" title="<?php echo htmlspecialchars(__('pdf_organizer.rotate_l')); ?>"><i class="fa fa-undo"></i></button>
                <button class="action-btn" onclick="rotatePage('${p.id}', 90)" title="<?php echo htmlspecialchars(__('pdf_organizer.rotate_r')); ?>"><i class="fa fa-repeat"></i></button>
                <button class="action-btn" onclick="duplicatePage('${p.id}')" title="<?php echo htmlspecialchars(__('pdf_organizer.duplicate')); ?>"><i class="fa fa-copy"></i></button>
                ` : ''}
                <button class="action-btn delete" onclick="deletePage('${p.id}')" title="<?php echo htmlspecialchars(__('pdf_organizer.delete')); ?>"><i class="fa fa-trash"></i></button>
            </div>
            <div class="page-thumb-container">
                ${isBlank 
                    ? `<div style="color: #cbd5e0; font-size: 12px; font-weight: bold; text-transform: uppercase;"><?php echo htmlspecialchars(__('pdf_organizer.blank')); ?></div>`
                    : `<img src="${p.thumb_url}" style="${thumbStyle}">`
                }
            </div>
            <div class="page-info">${isBlank ? '—' : p.file_name}</div>
        `;
        return div;
    }

    // Page Operations
    window.rotatePage = function(id, angle) {
        const p = pageSequence.find(x => x.id === id);
        if (p) {
            p.rotation = (p.rotation + angle) % 360;
            if (p.rotation < 0) p.rotation += 360;
            renderUI();
        }
    };

    window.duplicatePage = function(id) {
        const idx = pageSequence.findIndex(x => x.id === id);
        if (idx !== -1) {
            const copy = JSON.parse(JSON.stringify(pageSequence[idx]));
            copy.id = Math.random().toString(36).substr(2, 9);
            pageSequence.splice(idx + 1, 0, copy);
            renderUI();
        }
    };

    window.deletePage = function(id) {
        pageSequence = pageSequence.filter(x => x.id !== id);
        if (pageSequence.length === 0) {
            uploadContainer.style.display = 'block';
            interface.style.display = 'none';
            toolbar.style.display = 'none';
        }
        renderUI();
    };

    document.getElementById('add-blank').addEventListener('click', () => {
        pageSequence.push({
            id: Math.random().toString(36).substr(2, 9),
            type: 'blank'
        });
        renderUI();
    });

    document.getElementById('clear-all').addEventListener('click', () => {
        if (confirm("Effacer toutes les pages ?")) {
            pageSequence = [];
            uploadContainer.style.display = 'block';
            interface.style.display = 'none';
            toolbar.style.display = 'none';
            renderUI();
        }
    });

    // Native Drag and Drop Logic
    function initDragAndDrop() {
        const cards = document.querySelectorAll('.page-card');
        cards.forEach(card => {
            card.addEventListener('dragstart', (e) => {
                e.dataTransfer.setData('text/plain', card.dataset.id);
                card.classList.add('dragging');
            });

            card.addEventListener('dragend', () => {
                card.classList.remove('dragging');
                document.querySelectorAll('.page-card').forEach(c => c.classList.remove('drag-over'));
            });

            card.addEventListener('dragover', (e) => {
                e.preventDefault();
                const draggingId = e.dataTransfer.getData('text/plain');
                if (card.dataset.id !== draggingId) {
                    card.classList.add('drag-over');
                }
            });

            card.addEventListener('dragleave', () => {
                card.classList.remove('drag-over');
            });

            card.addEventListener('drop', (e) => {
                e.preventDefault();
                const id = e.dataTransfer.getData('text/plain');
                const targetId = card.dataset.id;
                console.log('DROP: id=' + id + ' targetId=' + targetId + ' same=' + (id === targetId));
                
                if (id === targetId) return;

                const fromIdx = pageSequence.findIndex(p => p.id === id);
                const toIdx = pageSequence.findIndex(p => p.id === targetId);
                console.log('MOVE: fromIdx=' + fromIdx + ' toIdx=' + toIdx + ' pages=' + pageSequence.length);

                const item = pageSequence.splice(fromIdx, 1)[0];
                pageSequence.splice(toIdx, 0, item);
                
                justDropped = true;
                setTimeout(() => { justDropped = false; console.log('justDropped reset'); }, 200);
                
                renderUI();
            });
            
            card.addEventListener('click', (e) => {
                console.log('CLICK on card:', card.dataset.id, 'justDropped=', justDropped);
            });
        });
    }

    // Generate Final PDF
    document.getElementById('btn-generate').addEventListener('click', function() {
        const btn = this;
        showLoading("<?php _ejs('pdf_organizer.generating'); ?>");
        btn.classList.add('disabled');

        const formData = new FormData();
        formData.append('action', 'generate');
        formData.append('session_id', sessionId);
        formData.append('structure', JSON.stringify(pageSequence));

        fetch('?pdf_organizer', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showSuccess(data.download_url);
            } else {
                alert("Erreur: " + data.error);
                btn.classList.remove('disabled');
            }
        })
        .catch(err => {
            console.error(err);
            alert("Erreur lors de la génération.");
            btn.classList.remove('disabled');
        })
        .finally(() => hideLoading());
    });

    // Success Screen
    function showSuccess(url) {
        const body = document.querySelector('.panel-body');
        body.innerHTML = `
            <div style="padding: 100px 40px; text-align: center;">
                <div style="font-size: 80px; color: #38a169; margin-bottom: 30px;">
                    <i class="fa fa-check-circle"></i>
                </div>
                <h2 style="font-weight: bold; color: #2d3748;"><?php _e('pdf_organizer.success'); ?></h2>
                <div style="margin-top: 40px;">
                    <a href="${url}" class="btn btn-success btn-lg" style="padding: 15px 45px; border-radius: 40px; font-weight: bold;">
                        <i class="fa fa-download"></i> <?php _e('pdf_organizer.download'); ?>
                    </a>
                </div>
                <div style="margin-top: 30px;">
                    <a href="?pdf_organizer" class="btn btn-link" style="color: #4a5568;">Continuer avec un autre fichier</a>
                </div>
            </div>
        `;
        toolbar.style.display = 'none';
    }

    // Helpers
    function showLoading(text) {
        loadingText.textContent = text;
        loadingOverlay.style.display = 'flex';
    }

    function hideLoading() {
        loadingOverlay.style.display = 'none';
    }
});
</script>
