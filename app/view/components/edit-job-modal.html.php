<!-- Edit Job Modal -->
<div class="modal fade" id="edit-job-modal" tabindex="-1" role="dialog" aria-labelledby="edit-job-modal-title" aria-hidden="true" style="z-index: 10050;">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="edit-job-modal-title">
                    <i class="fa fa-pencil text-primary"></i> <?php _e('edit_job.title'); ?>
                </h4>
            </div>
            <div class="modal-body">
                <form id="edit-job-form">
                    <!-- Common Fields -->
                    <div class="form-group">
                        <label><?php _e('common.document'); ?></label>
                        <input type="text" class="form-control" id="edit-document-name" readonly>
                    </div>

                    <!-- Photocopier Specific -->
                    <div id="edit-photocop-fields" style="display:none;">
                         <div class="row">
                            <div class="col-xs-4">
                                <div class="form-group">
                                    <label for="edit-copies"><?php _e('edit_job.copies'); ?></label>
                                    <input type="number" class="form-control" id="edit-copies" min="1" max="9999">
                                </div>
                            </div>
                            <div class="col-xs-4">
                                <div class="form-group">
                                    <label for="edit-pages"><?php _e('edit_job.pages_per_copy'); ?></label>
                                    <input type="number" class="form-control" id="edit-pages" min="1" step="1">
                                </div>
                            </div>
                            <div class="col-xs-4">
                                <div class="form-group">
                                    <label for="edit-paper-size"><?php _e('common.format'); ?></label>
                                    <select class="form-control" id="edit-paper-size">
                                        <option value="A4">A4</option>
                                        <option value="A3">A3</option>
                                        <option value="A5">A5</option>
                                        <option value="SRA3">SRA3</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-xs-6">
                                <div class="form-group">
                                    <label><?php _e('common.color'); ?></label>
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="edit-color">
                                        <label class="custom-control-label" for="edit-color"><?php _e('edit_job.color_print'); ?></label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xs-6">
                                <div class="form-group">
                                    <label><?php _e('common.duplex'); ?></label>
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="edit-duplex">
                                        <label class="custom-control-label" for="edit-duplex"><?php _e('edit_job.duplex_enable'); ?></label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                             <label for="edit-fill-rate"><?php _e('edit_job.estimated_coverage'); ?></label>
                             <input type="number" class="form-control" id="edit-fill-rate" min="0" max="100" step="1">
                             <small class="text-muted"><?php _e('edit_job.coverage_help'); ?></small>
                        </div>
                    </div>

                    <!-- Duplicopieur Specific -->
                    <div id="edit-dupli-fields" style="display:none;">
                         <div class="row">
                            <div class="col-xs-6">
                                <div class="form-group">
                                    <label for="edit-masters"><?php _e('edit_job.masters_count'); ?></label>
                                    <input type="number" class="form-control" id="edit-masters" min="0">
                                </div>
                            </div>
                            <div class="col-xs-6">
                                <div class="form-group">
                                    <label for="edit-passages"><?php _e('edit_job.passages_count'); ?></label>
                                    <input type="number" class="form-control" id="edit-passages" min="0">
                                </div>
                            </div>
                        </div>
                         <div class="form-group">
                            <label for="edit-tambour"><?php _e('edit_job.drum_color'); ?></label>
                            <select class="form-control" id="edit-tambour">
                                <!-- Populated dynamically -->
                            </select>
                        </div>
                        
                         <div class="row">
                            <div class="col-xs-6">
                                <div class="form-group">
                                    <label><?php _e('common.duplex'); ?> (<?php echo __('common.manual'); ?>)</label>
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="edit-dupli-duplex">
                                        <label class="custom-control-label" for="edit-dupli-duplex"><?php _e('edit_job.duplex_enable'); ?></label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php _e('common.cancel'); ?></button>
                <button type="button" class="btn btn-primary" onclick="saveEditedJob()">
                    <i class="fa fa-save"></i> <?php _e('common.save'); ?>
                </button>
            </div>
        </div>
    </div>
</div>
