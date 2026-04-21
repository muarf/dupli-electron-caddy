<?php
/**
 * Vue : Redimensionnement A4/A3
 */
?>

<div class="container mr-top-20">
    <div class="row">
        <div class="col-md-10 col-md-offset-1">
            <div class="panel panel-default" style="border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: none; overflow: hidden;">
                <div class="panel-heading" style="background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); color: white; padding: 30px; border: none;">
                    <h2 class="panel-title" style="font-size: 28px; font-weight: bold; margin-bottom: 10px;">
                        <i class="fa fa-expand" style="margin-right: 15px;"></i>
                        <?php _e('resizer.title'); ?>
                    </h2>
                    <p style="opacity: 0.9; font-size: 16px;"><?php _e('resizer.subtitle'); ?></p>
                </div>

                <div class="panel-body" style="padding: 40px; background-color: #fcfcfc;">
                    <form id="resizer-form" enctype="multipart/form-data">
                        <div id="drop-zone" class="text-center" style="border: 3px dashed #ddd; border-radius: 20px; padding: 60px 20px; background: white; transition: all 0.3s ease; cursor: pointer; margin-bottom: 30px; position: relative;">
                            <div style="font-size: 64px; color: #6366f1; margin-bottom: 20px;" id="drop-icon">
                                <i class="fa fa-cloud-upload"></i>
                            </div>
                            <h4 id="drop-text" style="color: #666; font-weight: bold;"><?php _e('resizer.drag_drop'); ?></h4>
                            <p style="color: #999;"><?php _e('resizer.click_select'); ?></p>
                            <input type="file" name="file" id="file-input" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer;" accept=".pdf,.png,.jpg,.jpeg">
                            <div id="file-list" class="mt-20"></div>
                        </div>

                        <div class="row" style="margin-bottom: 30px;">
                            <div class="col-md-6" style="margin-bottom: 20px;">
                                <label class="control-label" style="font-weight: bold; color: #444; margin-bottom: 8px;">
                                    <i class="fa fa-file-pdf-o" style="color: #6366f1;"></i> <?php _e('resizer.target_format'); ?>
                                </label>
                                <select name="target_format" class="form-control input-lg" style="border-radius: 10px; border: 1px solid #e2e8f0; box-shadow: none;">
                                    <option value="A4" selected>A4 (210 x 297 mm)</option>
                                    <option value="A3">A3 (297 x 420 mm)</option>
                                </select>
                            </div>
                            <div class="col-md-6" style="margin-bottom: 20px;">
                                <label class="control-label" style="font-weight: bold; color: #444; margin-bottom: 8px;">
                                    <i class="fa fa-arrows-alt" style="color: #6366f1;"></i> <?php _e('resizer.mode'); ?>
                                </label>
                                <select name="mode" class="form-control input-lg" style="border-radius: 10px; border: 1px solid #e2e8f0; box-shadow: none;">
                                    <option value="fit" selected><?php _e('resizer.fit'); ?></option>
                                    <option value="fill"><?php _e('resizer.fill'); ?></option>
                                    <option value="center"><?php _e('resizer.center'); ?></option>
                                </select>
                            </div>
                            <div class="col-md-6" style="margin-bottom: 20px;">
                                <label class="control-label" style="font-weight: bold; color: #444; margin-bottom: 8px;">
                                    <i class="fa fa-refresh" style="color: #6366f1;"></i> <?php _e('resizer.orientation'); ?>
                                </label>
                                <select name="orientation" class="form-control input-lg" style="border-radius: 10px; border: 1px solid #e2e8f0; box-shadow: none;">
                                    <option value="auto" selected><?php _e('resizer.auto'); ?></option>
                                    <option value="portrait"><?php _e('resizer.portrait'); ?></option>
                                    <option value="landscape"><?php _e('resizer.landscape'); ?></option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="control-label" style="font-weight: bold; color: #444; margin-bottom: 8px;">
                                    <i class="fa fa-align-center" style="color: #6366f1;"></i> <?php _e('resizer.alignment'); ?>
                                </label>
                                <select name="alignment" class="form-control input-lg" style="border-radius: 10px; border: 1px solid #e2e8f0; box-shadow: none;">
                                    <option value="centered" selected><?php _e('resizer.centered'); ?></option>
                                    <option value="top_left"><?php _e('resizer.top_left'); ?></option>
                                    <option value="bottom_right"><?php _e('resizer.bottom_right'); ?></option>
                                </select>
                            </div>
                        </div>

                        <div class="text-center">
                            <button type="submit" class="btn btn-primary btn-lg" id="process-btn" style="padding: 15px 60px; border-radius: 40px; font-weight: bold; border: none; background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); box-shadow: 0 10px 20px rgba(99, 102, 241, 0.2);">
                                <i class="fa fa-cogs" style="margin-right: 10px;"></i>
                                <?php _e('resizer.process'); ?>
                            </button>
                        </div>
                    </form>

                    <div id="status-container" class="text-center" style="display: none; margin-top: 30px;">
                        <i class="fa fa-spinner fa-spin fa-3x" style="color: #6366f1; margin-bottom: 15px;"></i>
                        <p style="font-weight: bold; color: #6366f1; font-size: 18px;"><?php _e('resizer.processing'); ?></p>
                    </div>

                    <div id="result-container" style="display: none; margin-top: 30px;">
                        <div class="alert alert-success text-center" style="border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(92, 184, 92, 0.2); padding: 30px;">
                            <div style="font-size: 48px; color: #5cb85c; margin-bottom: 20px;">
                                <i class="fa fa-check-circle"></i>
                            </div>
                            <h3 style="margin-top: 0; color: #3c763d; font-weight: bold;"><?php _e('resizer.success'); ?></h3>
                            <hr style="border-top: 1px solid rgba(0,0,0,0.05); margin: 25px 0;">
                            <a href="#" id="download-link" class="btn btn-success btn-lg" style="padding: 12px 40px; border-radius: 30px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 4px 10px rgba(92, 184, 92, 0.3);">
                                <i class="fa fa-download" style="margin-right: 10px;"></i>
                                <?php _e('resizer.download_result'); ?>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="panel-footer" style="padding: 40px; background-color: #f8f9fa; border: none;">
                    <h4 style="font-weight: bold; margin-bottom: 20px; color: #444;">
                        <i class="fa fa-info-circle" style="color: #6366f1; margin-right: 10px;"></i> <?php _e('resizer.how_it_works'); ?>
                    </h4>
                    <div class="row">
                        <div class="col-md-4">
                            <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); height: 100%;">
                                <h5 style="font-weight: bold; color: #6366f1; margin-top: 0;">100% pages</h5>
                                <p class="small text-muted" style="margin-bottom: 0;"><?php _e('resizer.all_pages'); ?></p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); height: 100%;">
                                <h5 style="font-weight: bold; color: #6366f1; margin-top: 0;"><?php _e('resizer.centered'); ?></h5>
                                <p class="small text-muted" style="margin-bottom: 0;"><?php _e('resizer.margins'); ?></p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); height: 100%;">
                                <h5 style="font-weight: bold; color: #6366f1; margin-top: 0;">HD Quality</h5>
                                <p class="small text-muted" style="margin-bottom: 0;"><?php _e('resizer.quality'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center" style="margin-top: 30px; margin-bottom: 50px;">
                <a href="?accueil" style="color: #666; text-decoration: none; font-weight: bold; transition: color 0.2s;">
                    <i class="fa fa-arrow-left"></i> <?php _e('accueil.back_to_home'); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<style>
#drop-zone:hover, #drop-zone.dragover {
    border-color: #6366f1;
    background-color: #f8faff;
}
.mt-20 { margin-top: 20px; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('file-input');
    const fileList = document.getElementById('file-list');
    const form = document.getElementById('resizer-form');
    const statusContainer = document.getElementById('status-container');
    const resultContainer = document.getElementById('result-container');
    const downloadLink = document.getElementById('download-link');
    const processBtn = document.getElementById('process-btn');

    fileInput.addEventListener('dragenter', () => dropZone.classList.add('dragover'));
    fileInput.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
    fileInput.addEventListener('drop', () => dropZone.classList.remove('dragover'));

    fileInput.addEventListener('change', function() {
        if (this.files.length) {
            fileList.innerHTML = '<span class="label label-primary" style="font-size: 14px; padding: 8px 15px; border-radius: 20px;"><i class="fa fa-file" style="margin-right: 8px;"></i>' + this.files[0].name + '</span>';
        }
    });

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        if (!fileInput.files.length) {
            alert('Veuillez sélectionner un fichier.');
            return;
        }

        const formData = new FormData(form);
        statusContainer.style.display = 'block';
        resultContainer.style.display = 'none';
        processBtn.disabled = true;

        fetch('?resizer', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            statusContainer.style.display = 'none';
            processBtn.disabled = false;
            
            if (data.success) {
                resultContainer.style.display = 'block';
                downloadLink.href = data.result.download_url;
                form.reset();
                fileList.innerHTML = '';
            } else {
                alert('Erreur: ' + data.errors.join('\n'));
            }
        })
        .catch(error => {
            statusContainer.style.display = 'none';
            processBtn.disabled = false;
            console.error('Error:', error);
            alert('Une erreur est survenue lors du traitement.');
        });
    });
});
</script>
