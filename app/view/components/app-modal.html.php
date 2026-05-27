<!-- Global App Modal -->
<div class="modal fade" id="app-global-modal" tabindex="-1" role="dialog" aria-labelledby="app-global-modal-title"
    aria-hidden="true" style="z-index: 9999;">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
            <div class="modal-header" style="border-bottom: 1px solid #f0f0f0; padding: 15px 20px;">
                <h4 class="modal-title" id="app-global-modal-title" style="font-weight: 600; color: #333;">
                    <i class="fa fa-info-circle text-primary" id="app-global-modal-icon"></i>
                    <span id="app-global-modal-title-text">Message</span>
                </h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="font-size: 24px;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="app-global-modal-body"
                style="padding: 20px; font-size: 15px; color: #555; white-space: pre-wrap;">
                <!-- Content injected here -->
            </div>
            <div id="app-global-modal-input-container" style="padding: 0 20px 20px 20px; display: none;">
                <input type="text" id="app-global-modal-input" class="form-control"
                    style="border-radius: 8px; border: 2px solid #e0e0e0;">
            </div>
            <div class="modal-footer" style="border-top: 1px solid #f0f0f0; padding: 15px 20px;">
                <button type="button" class="btn btn-default btn-modern" id="app-global-modal-cancel"
                    data-dismiss="modal" style="display: none; border-radius: 8px;"><?php _e('common.cancel'); ?></button>
                <button type="button" class="btn btn-primary btn-modern" id="app-global-modal-ok" data-dismiss="modal"
                    style="border-radius: 8px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">OK</button>
            </div>
        </div>
    </div>
</div>

<style>
    .btn-modern {
        padding: 8px 18px;
        font-weight: 500;
        transition: all 0.2s;
    }

    .btn-modern:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }
</style>
