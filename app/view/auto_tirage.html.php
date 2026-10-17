<style>
    .main-container {
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        margin: 1rem auto;
        overflow: hidden;
    }

    .header-section {
        background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
        color: #424242;
        padding: 1.5rem;
        text-align: center;
        border-bottom: 1px solid #e0e0e0;
    }

    .header-section h1 {
        margin: 0;
        font-weight: 400;
        font-size: 2.2rem;
        color: #616161;
    }

    .form-section {
        padding: 1.5rem;
    }

    .btn-modern {
        border-radius: 10px;
        padding: 0.75rem 1.5rem;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .btn-primary-modern {
        background: linear-gradient(135deg, #81c784, #a5d6a7);
        border: none;
        color: white;
    }

    .btn-success-modern {
        background: linear-gradient(135deg, #a5d6a7, #c8e6c9);
        border: none;
        color: #2e7d32;
    }

    /* Styles pour les onglets de session */
    .session-tabs-container {
        display: flex;
        align-items: center;
        margin-bottom: 1.5rem;
        border-bottom: 2px solid #eee;
        padding: 0 10px;
        background: #f8f9fa;
        border-top-left-radius: 8px;
        border-top-right-radius: 8px;
        flex-wrap: wrap;
    }

    .session-tab {
        padding: 10px 15px;
        margin-right: 5px;
        cursor: pointer;
        border-top-left-radius: 8px;
        border-top-right-radius: 8px;
        background: #e9ecef;
        color: #495057;
        font-weight: 500;
        transition: all 0.2s;
        border: 1px solid transparent;
        border-bottom: none;
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: -2px;
    }

    .session-tab:hover {
        background: #dee2e6;
    }

    .session-tab.active {
        background: white;
        color: #007bff;
        border-color: #eee;
        border-bottom: 2px solid white;
        box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
    }

    .session-tab .close-tab {
        font-size: 14px;
        opacity: 0.5;
        transition: opacity 0.2s;
        padding: 2px 5px;
        border-radius: 4px;
    }

    .session-tab .close-tab:hover {
        opacity: 1;
        background: rgba(255, 0, 0, 0.1);
        color: #dc3545;
    }

    .add-session-tab {
        padding: 10px 15px;
        cursor: pointer;
        color: #28a745;
        font-size: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: transparent;
        border: none;
        margin-bottom: -2px;
    }

    .add-session-tab:hover {
        color: #218838;
        transform: scale(1.1);
    }

    /* Fancy Creation Card */
    .fancy-creation-card {
        background: white;
        border-radius: 15px;
        padding: 3rem;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        border: 1px solid #f0f0f0;
        max-width: 500px;
        margin: 2rem auto;
        text-align: center;
    }

    .fancy-creation-card .icon-header {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #e3f2fd, #f3e5f5);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        font-size: 30px;
        color: #007bff;
    }

    .fancy-creation-card h4 {
        margin-bottom: 1.5rem;
        font-weight: 600;
        color: #333;
    }

    .fancy-input {
        border: 2px solid #eee;
        border-radius: 10px;
        padding: 12px 15px;
        font-size: 16px;
        transition: border-color 0.3s;
        margin-bottom: 1rem;
    }

    .fancy-input:focus {
        border-color: #80bdff;
        box-shadow: none;
    }

    /* Styles pour les lignes de session modifiables */
    .editable-job-row {
        cursor: pointer;
        transition: all 0.2s ease;
        position: relative;
    }

    .editable-job-row:hover {
        background-color: #f0f8ff !important;
        box-shadow: 0 2px 8px rgba(0, 123, 255, 0.15);
        transform: translateY(-1px);
    }

</style>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-12 col-xl-10">
            <div class="main-container">
                <div class="header-section">
                    <h1><i class="fa fa-magic"></i> <?php _e('auto_tirage.title'); ?></h1>
                    <p><?php _e('auto_tirage.subtitle'); ?></p>
                </div>

                <div class="form-section">
                    <!-- Interface par onglets de Session -->
                    <div id="session-tabs-container" class="session-tabs-container">
                        <!-- Les onglets seront injectés ici par JS -->
                        <button class="add-session-tab" onclick="createNewSessionClick()" title="<?php echo __('auto_tirage.new_session'); ?>">
                            <i class="fa fa-plus-circle"></i>
                        </button>
                    </div>

                    <!-- Étape 1: Identification (Formulaire Fancy) -->
                    <div id="step-identity" style="display:none;">
                        <div class="fancy-creation-card">
                            <div class="icon-header">
                                <i class="fa fa-user-plus"></i>
                            </div>
                            <h4><?php _e('auto_tirage.start_new_session'); ?></h4>
                            <div class="form-group">
                                <input type="text" id="pseudo-input" class="form-control fancy-input"
                                    placeholder="<?php echo __('auto_tirage.who_are_you'); ?>" onkeypress="if(event.key === 'Enter') startSession()">
                                <input type="text" id="session-name-input" class="form-control fancy-input"
                                    placeholder="<?php echo __('auto_tirage.session_name_optional'); ?>"
                                    onkeypress="if(event.key === 'Enter') startSession()">
                            </div>
                            <button class="btn btn-primary-modern btn-block btn-lg" onclick="startSession()">
                                <?php _e('auto_tirage.lets_go'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Étape 2: Écoute active -->
                    <div id="step-listening" style="display:none;">
                        <!-- BUFFER ZONE: Impressions en attente -->
                        <div id="buffer-zone" class="card mb-4"
                            style="display:none; border: 2px dashed #3498db; background: #f8fbff; margin-bottom: 30px; padding: 15px;">
                            <div class="card-header bg-primary text-white">
                                <h4 class="mb-0"><i class="fa fa-inbox"></i> <?php _e('auto_tirage.pending_jobs'); ?></h4>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <p class="text-muted mb-0"><?php _e('auto_tirage.buffer_description'); ?></p>
                                    <div id="buffer-bulk-actions" style="display: none;">
                                        <button class="btn btn-primary btn-sm mr-2" onclick="bulkMoveBufferToSession()">
                                            <i class="fa fa-plus"></i> <?php _e('auto_tirage.add_selected'); ?>
                                        </button>
                                        <button class="btn btn-outline-danger btn-sm" onclick="bulkDeleteBufferJob()">
                                            <i class="fa fa-trash"></i> <?php _e('auto_tirage.delete_selected'); ?>
                                        </button>
                                    </div>
                                </div>
                                <table class="table table-striped table-hover" id="buffer-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 40px;"><input type="checkbox" id="select-all-buffer"
                                                    onclick="toggleAllBuffer(this)"></th>
                                            <th><?php _e('auto_tirage.preview'); ?></th>
                                            <th><?php _e('auto_tirage.date'); ?></th>
                                            <th><?php _e('auto_tirage.machine'); ?></th>
                                            <th><?php _e('auto_tirage.document'); ?></th>
                                            <th><?php _e('auto_tirage.details'); ?></th>
                                            <th><?php _e('auto_tirage.ink_coverage'); ?></th>
                                            <th><?php _e('auto_tirage.action'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Jobs will be injected here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Session Actuelle -->
                        <div id="session-zone">
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                                <small class="text-muted"><i class="fa fa-clock-o"></i> <span
                                        id="session-status-text"><?php _e('auto_tirage.waiting_jobs'); ?></span></small>
                                <button class="btn btn-link btn-sm text-muted" type="button" onclick="toggleLogs()">
                                    <i class="fa fa-list"></i> <?php _e('auto_tirage.view_activity'); ?>
                                </button>
                            </div>

                            <!-- Zone de logs / Status -->
                            <div id="activity-log" class="mb-4" style="display: none;">
                                <!-- Les cartes de détection apparaîtront ici -->
                            </div>

                            <!-- Liste des jobs en attente de validation -->
                            <div id="pending-list-container" style="display:none;">
                                <h5 class="border-bottom pb-2 mb-3"><i class="fa fa-list"></i> <?php _e('auto_tirage.active_jobs'); ?></h5>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th><?php _e('auto_tirage.machine'); ?></th>
                                                <th><?php _e('auto_tirage.document'); ?></th>
                                                <th><?php _e('auto_tirage.details'); ?></th>
                                                <th><?php _e('tirage_multimachines.paper'); ?></th>
                                                <th><?php _e('tirage_multimachines.ink_toner'); ?></th>
                                                <th><?php _e('auto_tirage.total_price'); ?></th>
                                                <th><?php _e('auto_tirage.paper_paid'); ?></th>
                                                <th><?php _e('auto_tirage.action'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody id="pending-jobs-body">
                                            <!-- Rows generated by JS -->
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-info">
                                                <td colspan="5" class="text-right"><strong><?php _e('auto_tirage.session_total'); ?></strong></td>
                                                <td colspan="3"><strong><span id="session-total">0.00</span> €</strong>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                <div class="text-right mt-4">
                                    <button class="btn btn-success-modern btn-lg" onclick="finishSession()">
                                        <i class="fa fa-check"></i> <?php _e('auto_tirage.finish_validate'); ?> <span id="finish-badge"
                                            class="badge badge-light">0</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modale pour l'aperçu de la vignette -->
    <div class="modal fade" id="thumbnail-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="thumbnail-modal-title"><?php _e('auto_tirage.document_preview'); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body text-center">
                    <img id="modal-thumbnail-img" src=""
                        style="max-width: 100%; max-height: 80vh; object-fit: contain;">
                </div>
            </div>
        </div>
    </div>


    <!-- Inclure la modale de sélection de session -->
    <?php include __DIR__ . '/components/session-modal.html.php'; ?>
    <!-- Inclure la modale d'édition de job -->
    <?php include __DIR__ . '/components/edit-job-modal.html.php'; ?>

    <script src="<?= $base_path ?>js/auto-tirage.js" defer></script>
    <script>
        const CONFIG = <?= json_encode([
            'strings' => [
                'auto_tirage.system_ready' => __js('auto_tirage.system_ready'),
                'auto_tirage.job_detected' => __js('auto_tirage.job_detected'),
                'auto_tirage.stabilizing' => __js('auto_tirage.stabilizing'),
                'auto_tirage.page_update' => __js('auto_tirage.page_update'),
                'auto_tirage.stabilization' => __js('auto_tirage.stabilization'),
                'auto_tirage.add_selected' => __js('auto_tirage.add_selected'),
                'auto_tirage.job_assigned' => __js('auto_tirage.job_assigned'),
                'auto_tirage.job_waiting' => __js('auto_tirage.job_waiting'),
                'auto_tirage.analyzing_job' => __js('auto_tirage.analyzing_job'),
                'auto_tirage.already_recorded' => __js('auto_tirage.already_recorded'),
                'auto_tirage.internal_error' => __js('auto_tirage.internal_error'),
                'auto_tirage.updating_job' => __js('auto_tirage.updating_job'),
                'auto_tirage.delete_spooler' => __js('auto_tirage.delete_spooler'),
                'auto_tirage.spooler_cleaned' => __js('auto_tirage.spooler_cleaned'),
                'auto_tirage.communication_error' => __js('auto_tirage.communication_error'),
                'auto_tirage.job_deleted' => __js('auto_tirage.job_deleted'),
                'auto_tirage.delete_error' => __js('auto_tirage.delete_error'),
                'auto_tirage.adding_jobs' => __js('auto_tirage.adding_jobs'),
                'auto_tirage.jobs_deleted' => __js('auto_tirage.jobs_deleted'),
                'auto_tirage.confirm_delete' => __js('auto_tirage.confirm_delete'),
                'auto_tirage.confirm_delete_many' => __js('auto_tirage.confirm_delete_many'),
                'auto_tirage.fill_rate' => __js('auto_tirage.fill_rate'),
                'auto_tirage.paper_paid' => __js('auto_tirage.paper_paid'),
                'auto_tirage.preview' => __js('auto_tirage.preview'),
                'auto_tirage.document_preview' => __js('auto_tirage.document_preview'),
                'admin_tirage.no_prints_selected' => __js('admin_tirage.no_prints_selected'),
                'tirage_multimachines.color' => __js('tirage_multimachines.color'),
                'tirage_multimachines.photocopieur' => __js('tirage_multimachines.photocopieur'),
                'tirage_multimachines.duplicopieur' => __js('tirage_multimachines.duplicopieur'),
                'tirage_multimachines.tambour_used' => __js('tirage_multimachines.tambour_used'),
                'library.file' => __js('library.file'),
                'common.refresh' => __js('common.refresh'),
                'common.duplex' => __js('common.duplex'),
                'common.simplex' => __js('common.simplex'),
                'common.delete' => __js('common.delete'),
                'common.edit' => __js('common.edit'),
                'common.before' => __js('common.before'),
                'common.after' => __js('common.after'),
                'common.total' => __js('common.total'),
                'admin_machines.counter' => __js('admin_machines.counter'),
            ],
        ]) ?>;
    </script>
