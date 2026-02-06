<?php
/**
 * Modal de sélection/création de session pour impressions détectées
 */
?>
<div class="modal fade" id="session-select-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">
                    <i class="fa fa-print"></i> <?php _e('session_modal.title'); ?> <span id="modal-doc"></span>
                </h4>
            </div>
            <div class="modal-body">
                <p class="text-muted"><?php _e('session_modal.assign_to'); ?></p>
                
                <!-- Liste des sessions existantes -->
                <div id="existing-sessions" style="margin-bottom: 20px;">
                    <h5><?php _e('session_modal.active_sessions'); ?></h5>
                    <div class="list-group" id="session-list">
                        <!-- Généré dynamiquement par JS -->
                    </div>
                    <p class="text-muted small" id="no-sessions-msg" style="display: none;">
                        <em><?php _e('session_modal.no_active_sessions'); ?></em>
                    </p>
                </div>
                
                <hr>
                
                <!-- Ou créer nouvelle session -->
                <div class="new-session-form">
                    <h5><?php _e('session_modal.create_new'); ?></h5>
                    <div class="form-group">
                        <label for="new-session-contact"><?php _e('session_modal.contact'); ?> <span class="text-danger">*</span></label>
                        <input type="text" 
                               id="new-session-contact" 
                               class="form-control" 
                               placeholder="<?php echo __('session_modal.contact_placeholder', [], false); ?>"
                               required>
                    </div>
                    <div class="form-group">
                        <label for="new-session-name"><?php _e('session_modal.session_name'); ?></label>
                        <input type="text" 
                               id="new-session-name" 
                               class="form-control" 
                               placeholder="<?php echo __('session_modal.session_name_placeholder', [], false); ?>">
                        <p class="help-block small"><?php _e('session_modal.session_name_help'); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    <i class="fa fa-times"></i> <?php _e('session_modal.ignore_job'); ?>
                </button>
                <button type="button" class="btn btn-primary">
                    <i class="fa fa-check"></i> <?php _e('session_modal.create_assign'); ?>
                </button>
            </div>
        </div>
    </div>
</div>
