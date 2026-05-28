<div class="container mr-top-20">
    <div class="row">
        <div class="col-md-10 col-md-offset-1">
            <div class="panel panel-default" style="border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: none; overflow: hidden;">
                <div class="panel-heading" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border: none;">
                    <h2 class="panel-title" style="font-size: 28px; font-weight: bold; margin-bottom: 10px;">
                        <i class="fa fa-compress" style="margin-right: 15px;"></i>
                        <?php _e('pdf_merge.title'); ?>
                    </h2>
                    <p style="opacity: 0.9; font-size: 16px;"><?php _e('pdf_merge.subtitle'); ?></p>
                </div>
                
                <div class="panel-body" style="padding: 40px; background-color: #fcfcfc;">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger" style="border-radius: 10px; border: none; box-shadow: 0 4px 10px rgba(217, 83, 79, 0.1);">
                            <ul style="margin-bottom: 0;">
                                <?php foreach ($errors as $error): ?>
                                    <li><strong><i class="fa fa-exclamation-circle"></i></strong> <?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success text-center" style="border-radius: 10px; border: none; box-shadow: 0 4px 15px rgba(92, 184, 92, 0.2); padding: 30px;">
                            <div style="font-size: 48px; color: #5cb85c; margin-bottom: 20px;">
                                <i class="fa fa-check-circle"></i>
                            </div>
                            <h3 style="margin-top: 0; color: #3c763d; font-weight: bold;"><?php _e('pdf_merge.merge_success'); ?></h3>
                            <p style="font-size: 16px; margin-bottom: 25px;"><?php _e('pdf_merge.ready_msg'); ?></p>
                            <a href="<?= $download_url ?>" class="btn btn-success btn-lg" style="padding: 12px 35px; border-radius: 30px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 4px 10px rgba(92, 184, 92, 0.3);">
                                <i class="fa fa-download" style="margin-right: 10px;"></i>
                                <?php _e('pdf_merge.download_result'); ?>
                            </a>
                            <div style="margin-top: 20px;">
                                <a href="?pdf_merge" class="btn btn-link" style="color: #666;"><?php _e('pdf_merge.add_more'); ?></a>
                            </div>
                        </div>
                    <?php else: ?>
                        <form id="merge-form" action="?pdf_merge" method="post" enctype="multipart/form-data">
                            <div id="drop-zone" class="text-center" style="border: 3px dashed #ddd; border-radius: 20px; padding: 60px 20px; background: white; transition: all 0.3s ease; cursor: pointer; margin-bottom: 30px; position: relative;">
                                <div style="font-size: 64px; color: #ccc; margin-bottom: 20px;" id="drop-icon">
                                    <i class="fa fa-cloud-upload"></i>
                                </div>
                                <h4 id="drop-text" style="color: #666; font-weight: bold;"><?php _e('pdf_merge.drag_drop'); ?></h4>
                                <p style="color: #999;"><?php _e('pdf_merge.click_select'); ?></p>
                                <p class="small text-muted" style="margin-top: 15px;"><?php _e('pdf_merge.file_info'); ?></p>
                                <input type="file" name="pdfs[]" id="pdfs" multiple accept="application/pdf" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer;">
                            </div>

                            <div id="file-list-container" style="display: none; margin-bottom: 30px;">
                                <h4 style="font-weight: bold; margin-bottom: 15px; color: #444;">
                                    <i class="fa fa-list-ol" style="margin-right: 10px; color: #764ba2;"></i>
                                    <?php _e('pdf_merge.files_selected'); ?>
                                    <span id="file-count" class="badge" style="background-color: #764ba2; margin-left: 5px;">0</span>
                                </h4>
                                <p class="text-muted" style="margin-bottom: 15px; font-size: 13px;">
                                    <i class="fa fa-info-circle"></i> <?php _e('pdf_merge.order_hint'); ?>
                                </p>
                                <div id="file-list" class="list-group shadow-sm" style="border-radius: 10px; overflow: hidden;">
                                    <!-- Files will be listed here -->
                                </div>
                                <div class="text-right">
                                    <button type="button" id="btn-clear" class="btn btn-link btn-sm" style="color: #d9534f;">
                                        <i class="fa fa-trash"></i> <?php _e('pdf_merge.clear_all'); ?>
                                    </button>
                                </div>
                            </div>

                            <input type="hidden" name="file_order" id="file_order" value="">

                            <div class="text-center">
                                <button type="submit" id="btn-merge" class="btn btn-primary btn-lg disabled" style="padding: 15px 50px; border-radius: 40px; font-weight: bold; border: none; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); box-shadow: 0 10px 20px rgba(118, 75, 162, 0.2);">
                                    <i class="fa fa-magic" style="margin-right: 10px;"></i>
                                    <?php _e('pdf_merge.process'); ?>
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="text-center mr-top-30">
                <a href="?accueil" class="btn btn-link" style="color: #764ba2;">
                    <i class="fa fa-arrow-left"></i> Retour à l'accueil
                </a>
            </div>
        </div>
    </div>
</div>

<style>
#drop-zone:hover, #drop-zone.dragover {
    border-color: #764ba2;
    background-color: #f8faff;
}
#drop-zone.dragover #drop-icon {
    color: #764ba2;
    transform: scale(1.1);
}
.file-item {
    cursor: move;
    transition: all 0.2s ease;
    border-left: 4px solid transparent;
}
.file-item:hover {
    background-color: #f5f5f5;
    border-left-color: #764ba2;
}
.file-item.dragging {
    opacity: 0.5;
    background-color: #eee;
}
.file-item .btn-remove {
    color: #ccc;
    transition: color 0.2s;
}
.file-item:hover .btn-remove {
    color: #d9534f;
}
.sort-handle {
    color: #ccc;
    margin-right: 15px;
    cursor: move;
}
#btn-merge.loading {
    pointer-events: none;
    opacity: 0.7;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('pdfs');
    const fileList = document.getElementById('file-list');
    const container = document.getElementById('file-list-container');
    const fileCount = document.getElementById('file-count');
    const mergeForm = document.getElementById('merge-form');
    const btnMerge = document.getElementById('btn-merge');
    const btnClear = document.getElementById('btn-clear');
    const fileOrderInput = document.getElementById('file_order');
    
    let selectedFiles = [];

    if (!fileInput) return; // Exit if in success state

    // Drag and drop handlers
    fileInput.addEventListener('dragenter', () => dropZone.classList.add('dragover'));
    fileInput.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
    fileInput.addEventListener('drop', () => dropZone.classList.remove('dragover'));

    fileInput.addEventListener('change', function(e) {
        handleFiles(this.files);
    });

    function handleFiles(files) {
        const newFiles = Array.from(files);
        
        newFiles.forEach(file => {
            if (file.type === 'application/pdf') {
                selectedFiles.push(file);
            }
        });
        
        updateUI();
    }

    function updateUI() {
        fileList.innerHTML = '';
        
        if (selectedFiles.length > 0) {
            container.style.display = 'block';
            fileCount.textContent = selectedFiles.length;
            
            selectedFiles.forEach((file, index) => {
                const item = document.createElement('div');
                item.className = 'list-group-item file-item d-flex align-items-center';
                item.draggable = true;
                item.dataset.index = index;
                
                // Formater la taille
                const size = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
                
                item.innerHTML = `
                    <div class="row" style="display: flex; align-items: center; width: 100%;">
                        <div class="col-xs-1 text-center">
                            <i class="fa fa-bars sort-handle"></i>
                        </div>
                        <div class="col-xs-1 text-center" style="font-size: 20px; color: #764ba2;">
                            <i class="fa fa-file-pdf-o"></i>
                        </div>
                        <div class="col-xs-7">
                            <div style="font-weight: bold; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${file.name}</div>
                            <small class="text-muted">${size}</small>
                        </div>
                        <div class="col-xs-3 text-right">
                            <button type="button" class="btn btn-link btn-remove" data-index="${index}">
                                <i class="fa fa-times"></i>
                            </button>
                        </div>
                    </div>
                `;
                
                // Add drag events for sorting
                item.addEventListener('dragstart', handleDragStart);
                item.addEventListener('dragover', handleDragOver);
                item.addEventListener('drop', handleDrop);
                item.addEventListener('dragend', handleDragEnd);
                
                fileList.appendChild(item);
            });
            
            if (selectedFiles.length >= 2) {
                btnMerge.classList.remove('disabled');
            } else {
                btnMerge.classList.add('disabled');
            }
        } else {
            container.style.display = 'none';
            btnMerge.classList.add('disabled');
        }
        
        // Update hidden field for order (only base indices for now, 
        // will be complex because we can't send File objects back 
        // via form easily without FormData/AJAX, so we rely on 
        // the original upload order if we use standard POST)
        // WAIT: In a standard form, multiple files are sent in the selection order.
        // If we want custom order, we need to use AJAX or a specific trick.
        // Trick: we don't allow sorting files before upload in a standard form.
        // BUT, I want it premium. Let's use FormData and AJAX.
    }

    // Drag & Drop Sorting logic
    let dragSrcEl = null;

    function handleDragStart(e) {
        this.classList.add('dragging');
        dragSrcEl = this;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/html', this.innerHTML);
    }

    function handleDragOver(e) {
        if (e.preventDefault) {
            e.preventDefault();
        }
        e.dataTransfer.dropEffect = 'move';
        return false;
    }

    function handleDrop(e) {
        if (e.stopPropagation) {
            e.stopPropagation();
        }
        
        if (dragSrcEl !== this) {
            const fromIndex = parseInt(dragSrcEl.dataset.index);
            const toIndex = parseInt(this.dataset.index);
            
            // Reorder the array
            const temp = selectedFiles[fromIndex];
            selectedFiles.splice(fromIndex, 1);
            selectedFiles.splice(toIndex, 0, temp);
            
            updateUI();
        }
        return false;
    }

    function handleDragEnd(e) {
        this.classList.remove('dragging');
    }

    // Remove file
    fileList.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-remove');
        if (btn) {
            const index = parseInt(btn.dataset.index);
            selectedFiles.splice(index, 1);
            updateUI();
        }
    });

    // Clear all
    btnClear.addEventListener('click', function() {
        selectedFiles = [];
        fileInput.value = '';
        updateUI();
    });

    // Form Submission
    mergeForm.addEventListener('submit', function(e) {
        if (selectedFiles.length < 2) {
            e.preventDefault();
            alert("<?php _e('pdf_merge.error_min_files'); ?>");
            return;
        }

        // To preserve order, we must use AJAX with FormData
        e.preventDefault();
        
        btnMerge.classList.add('loading');
        btnMerge.innerHTML = '<i class="fa fa-spinner fa-spin"></i> <?php _e('pdf_merge.merging'); ?>';
        
        const formData = new FormData();
        selectedFiles.forEach((file, index) => {
            formData.append('pdfs[]', file);
        });
        
        // Add current page as base URL
        const url = window.location.href.split('#')[0];
        
        fetch(url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(html => {
            // Replace body content with result page
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newContent = doc.querySelector('.panel-body');
            if (newContent) {
                const panelBody = document.querySelector('.panel-body');
                panelBody.innerHTML = newContent.innerHTML;
            } else {
                // If something went wrong, reload or show error
                window.location.reload();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert("Une erreur est survenue lors de la fusion.");
            btnMerge.classList.remove('loading');
            btnMerge.innerHTML = '<i class="fa fa-magic"></i> <?php _e('pdf_merge.process'); ?>';
        });
    });
});
</script>
