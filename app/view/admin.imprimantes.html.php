<?php
// Récupérer les machines pour le mapping
require_once __DIR__ . '/../controler/functions/database.php';
$db = pdo_connect();
$photocopieurs_list = $db->query("SELECT id, marque, type_encre FROM photocopieurs WHERE actif = 1 ORDER BY marque")->fetchAll(PDO::FETCH_ASSOC);
$duplicopieurs_list = $db->query("SELECT id, marque, modele FROM duplicopieurs WHERE actif = 1 ORDER BY marque, modele")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="section">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <h1 class="text-center"><i class="fa fa-print"></i> <?php _e('admin_printers.title'); ?></h1>
                <hr>

                <!-- Statut du moniteur -->
                <div class="panel panel-info" id="monitor-status-panel">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fa fa-info-circle"></i> <?php _e('admin_printers.monitor_status'); ?></h3>
                    </div>
                    <div class="panel-body">
                        <div id="monitor-status">
                            <p><i class="fa fa-spinner fa-spin"></i> <?php _e('admin_printers.checking_status'); ?></p>
                        </div>
                        <div id="monitor-actions" style="margin-top: 15px;">
                            <button class="btn btn-success" id="btn-start-monitor" onclick="toggleMonitor(true)"
                                style="display: none;">
                                <i class="fa fa-play"></i> <?php _e('admin_printers.start_monitor'); ?>
                            </button>
                            <button class="btn btn-warning" id="btn-stop-monitor" onclick="toggleMonitor(false)"
                                style="display: none;">
                                <i class="fa fa-stop"></i> <?php _e('admin_printers.stop_monitor'); ?>
                            </button>
                            <button class="btn btn-info" onclick="refreshStatus()">
                                <i class="fa fa-refresh"></i> <?php _e('admin_printers.refresh'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Avertissement droits administrateur (Windows uniquement) -->
                <div class="panel panel-danger" id="admin-warning-panel" style="display: none;">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fa fa-exclamation-triangle"></i> <?php _e('admin_printers.admin_rights_required'); ?>
                        </h3>
                    </div>
                    <div class="panel-body">
                        <p><strong><?php _e('admin_printers.not_admin_msg'); ?></strong></p>
                        <p><?php _e('admin_printers.admin_rights_desc'); ?></p>

                        <div class="row" style="margin-top: 15px;">
                            <div class="col-md-6">
                                <h4><i class="fa fa-magic"></i> <?php _e('admin_printers.quick_solution'); ?></h4>
                                <button class="btn btn-warning btn-lg btn-block" id="btn-restart-admin" onclick="restartAsAdmin()">
                                    <i class="fa fa-refresh"></i> <?php _e('admin_printers.restart_admin'); ?>
                                </button>
                                <p class="text-muted" style="margin-top: 10px; font-size: 12px;">
                                    <i class="fa fa-info-circle"></i> <?php _e('admin_printers.restart_admin_desc'); ?>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <h4><i class="fa fa-graduation-cap"></i> <?php _e('admin_printers.manual_tutorial'); ?></h4>
                                <ol style="font-size: 13px;">
                                    <li><?php _e('admin_printers.step1'); ?></li>
                                    <li><?php _e('admin_printers.step2'); ?></li>
                                    <li><?php _e('admin_printers.step3'); ?></li>
                                    <li><?php _e('admin_printers.step4'); ?></li>
                                </ol>
                                <p class="text-muted" style="font-size: 12px;">
                                    <i class="fa fa-lightbulb-o"></i> <?php _e('admin_printers.admin_tip'); ?>
                                </p>
                            </div>
                        </div>

                        <div class="alert alert-info" style="margin-top: 15px; margin-bottom: 0;">
                            <strong><i class="fa fa-info-circle"></i> <?php _e('admin_printers.what_if_no_admin'); ?></strong><br>
                            <?php _e('admin_printers.what_if_no_admin_desc'); ?>
                        </div>
                    </div>
                </div>

                <!-- Liste des imprimantes -->
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fa fa-list"></i> <?php _e('admin_printers.available_printers'); ?></h3>
                    </div>
                    <div class="panel-body">
                        <div id="printers-list">
                            <p><i class="fa fa-spinner fa-spin"></i> <?php _e('admin_printers.loading_printers'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Configuration Mappings -->
                <div class="panel panel-warning">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fa fa-link"></i> <?php _e('admin_printers.mappings_config'); ?></h3>
                    </div>
                    <div class="panel-body">
                        <p class="text-muted"><?php _e('admin_printers.mappings_desc'); ?></p>
                        <div id="mappings-container">
                            <table class="table table-bordered" id="mappings-table">
                                <thead>
                                    <tr>
                                        <th><?php _e('admin_printers.system_printer'); ?></th>
                                        <th><?php _e('admin_printers.associated_machine'); ?></th>
                                        <th><?php _e('admin_machines.actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="3" class="text-center"><i class="fa fa-spinner fa-spin"></i>
                                            <?php _e('common.loading'); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Statistiques -->
                <div class="panel panel-success">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fa fa-bar-chart"></i> <?php _e('admin_printers.stats_title'); ?></h3>
                    </div>
                    <div class="panel-body">
                        <div id="stats-container">
                            <p><i class="fa fa-spinner fa-spin"></i> <?php _e('admin_printers.loading_stats'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Liste des impressions récentes -->
                <div class="panel panel-primary">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fa fa-history"></i> <?php _e('admin_printers.recent_prints'); ?></h3>
                    </div>
                    <div class="panel-body">
                        <!-- Controls de pagination en haut -->
                        <div class="row" style="margin-bottom: 15px;">
                            <div class="col-sm-6">
                                <label for="items-per-page"><?php _e('admin_printers.items_per_page'); ?></label>
                                <select id="items-per-page" class="form-control"
                                    style="width: auto; display: inline-block;">
                                    <option value="10">10</option>
                                    <option value="20" selected>20</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                                <div class="checkbox"
                                    style="display: inline-block; margin-left: 20px; vertical-align: middle; margin-top: 0;">
                                    <label>
                                        <input type="checkbox" id="show-history" onchange="loadPrintJobs(1)"> <?php _e('admin_printers.show_history'); ?>
                                    </label>
                                </div>
                            </div>

                            <div class="col-sm-6 text-right">
                                <span id="pagination-info" class="text-muted"></span>
                            </div>
                        </div>

                        <div class="row" style="margin-bottom: 15px;">
                            <div class="col-sm-12 text-right">
                                <button class="btn btn-danger" id="btn-delete-selection" onclick="deleteSelectedJobs()"
                                    disabled>
                                    <i class="fa fa-trash"></i> <?php _e('admin_printers.delete_selection'); ?>
                                </button>
                                <button class="btn btn-danger" onclick="purgeAllJobs()">
                                    <i class="fa fa-bomb"></i> <?php _e('admin_printers.purge_history'); ?>
                                </button>
                            </div>
                        </div>

                        <div id="print-jobs-list">
                            <p><i class="fa fa-spinner fa-spin"></i> <?php _e('admin_printers.loading_jobs'); ?></p>
                        </div>

                        <!-- Pagination en bas -->
                        <div id="pagination-controls" class="text-center" style="margin-top: 15px; display: none;">
                            <nav>
                                <ul class="pagination" style="margin: 0;">
                                    <li id="btn-first-page"><a href="#" onclick="goToPage(1); return false;"><i
                                                class="fa fa-angle-double-left"></i></a></li>
                                    <li id="btn-prev-page"><a href="#" onclick="goToPreviousPage(); return false;"><i
                                                class="fa fa-angle-left"></i> <?php _e('common.previous'); ?></a></li>
                                    <li class="active"><a href="#" id="current-page-display">Page 1</a></li>
                                    <li id="btn-next-page"><a href="#" onclick="goToNextPage(); return false;"><?php _e('common.next'); ?>
                                            <i class="fa fa-angle-right"></i></a></li>
                                    <li id="btn-last-page"><a href="#" onclick="goToLastPage(); return false;"><i
                                                class="fa fa-angle-double-right"></i></a></li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Modal Aperçu -->
        <div class="modal fade" id="previewModal" tabindex="-1" role="dialog" aria-labelledby="previewModalLabel">
            <div class="modal-dialog modal-lg" role="document" style="width: 90%;">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                                aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title" id="previewModalLabel"><?php _e('admin_printers.preview_doc'); ?></h4>
                    </div>
                    <div class="modal-body text-center"
                        style="background-color: #f5f5f5; min-height: 400px; display: flex; align-items: center; justify-content: center;">
                        <img id="previewImage" src="" class="img-responsive"
                            style="max-height: 80vh; box-shadow: 0 5px 15px rgba(0,0,0,0.3);">
                        <p id="previewError" class="text-danger" style="display:none;"><i
                                class="fa fa-exclamation-triangle"></i> Impossible de charger l'image</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal"><?php _e('admin_printers.close'); ?></button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            const CONFIG = <?= json_encode([
                'lang' => $lang ?? 'fr',
                'photocopieurs' => $photocopieurs_list,
                'duplicopieurs' => $duplicopieurs_list,
                'strings' => [
                    'electron_api_unavailable' => __js('admin_printers.electron_api_unavailable'),
                    'restart_required' => __js('admin_printers.restart_required'),
                    'restart_admin_confirm' => __js('admin_printers.restart_admin_confirm'),
                    'restarting' => __js('admin_printers.restarting'),
                    'restart_error' => __js('admin_printers.restart_error'),
                    'restart_admin' => __js('admin_printers.restart_admin'),
                    'common_error' => __js('common.error'),
                    'windows_only' => __js('admin_printers.windows_only'),
                    'monitor_active_desc' => __js('admin_printers.monitor_active_desc'),
                    'monitor_inactive_desc' => __js('admin_printers.monitor_inactive_desc'),
                    'no_printers_found' => __js('admin_printers.no_printers_found'),
                    'start' => __js('admin_printers.start'),
                    'name' => __js('admin_printers.name'),
                    'status' => __js('admin_printers.status'),
                    'is_default' => __js('admin_printers.is_default'),
                    'actions' => __js('admin_printers.actions'),
                    'yes' => __js('admin_printers.yes'),
                    'no' => __js('admin_printers.no'),
                    'common_delete' => __js('common.delete'),
                    'invalid_json' => __js('admin_printers.invalid_json'),
                    'total_prints' => __js('admin_tirage.total_prints'),
                    'associated_machine' => __js('admin_printers.associated_machine'),
                    'common_pages' => __js('common.pages'),
                    'no_data' => __js('stats.no_data'),
                    'preview_doc' => __js('admin_printers.preview_doc'),
                    'common_date' => __js('common.date'),
                    'common_document' => __js('common.document'),
                    'common_format' => __js('common.format'),
                    'common_duplex' => __js('common.duplex'),
                    'common_color' => __js('common.color'),
                    'ink_coverage' => __js('admin_printers.ink_coverage'),
                    'common_status' => __js('common.status'),
                    'sheets' => __js('tirage_multimachines.sheets'),
                    'common_bw' => __js('common.bw'),
                    'no_prints_found' => __js('admin_printers.no_prints_found'),
                    'page' => __js('admin_tirage.page'),
                    'of' => __js('admin_tirage.of'),
                    'pagination_info' => __js('admin_printers.pagination_info'),
                    'delete_selection_count' => __js('admin_printers.delete_selection_count'),
                    'delete_selection' => __js('admin_printers.delete_selection'),
                    'confirm_delete_count' => __js('admin_printers.confirm_delete_count'),
                    'delete_printer' => __js('admin_printers.delete_printer'),
                    'delete_printer_confirm' => __js('admin_printers.delete_printer_confirm'),
                    'delete_printer_success' => __js('admin_printers.delete_printer_success'),
                    'electron_api_required' => __js('admin_printers.electron_api_required'),
                    'not_assigned' => __js('admin_printers.not_assigned'),
                    'photocopieur' => __js('tirage_multimachines.photocopieur'),
                    'duplicopieur' => __js('tirage_multimachines.duplicopieur'),
                    'save' => __js('admin_printers.save'),
                ],
            ]) ?>;
        </script>
        <script src="<?= $base_path ?>js/admin-imprimantes.js" defer></script>
    </div>
</div>
