<!-- Global Print Modal -->
<div class="modal fade" id="app-print-modal" tabindex="-1" role="dialog" aria-labelledby="app-print-modal-title"
    aria-hidden="true" style="z-index: 10050;">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="app-print-modal-title">
                    <i class="fa fa-print text-primary"></i> <?php _e('print_modal.title'); ?>
                </h4>
            </div>
            <div class="modal-body">
                <div id="print-modal-loading" class="text-center py-4">
                    <i class="fa fa-spinner fa-spin fa-2x text-primary"></i>
                    <p class="mt-2 text-muted"><?php _e('print_modal.loading_printers'); ?></p>
                </div>

                <div id="print-modal-form" style="display: none;">
                    <div class="form-group">
                        <label for="print-printer-select"><?php _e('print_modal.printer'); ?></label>
                        <select class="form-control" id="print-printer-select">
                            <option value=""><?php _e('common.loading'); ?></option>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-xs-6">
                            <div class="form-group">
                                <label for="print-copies"><?php _e('print_modal.copies'); ?></label>
                                <div class="input-group">
                                    <span class="input-group-btn">
                                        <button class="btn btn-default" type="button"
                                            onclick="decrementCopies()">-</button>
                                    </span>
                                    <input type="number" class="form-control text-center" id="print-copies" value="1"
                                        min="1" max="999">
                                    <span class="input-group-btn">
                                        <button class="btn btn-default" type="button"
                                            onclick="incrementCopies()">+</button>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-xs-6">
                            <div class="form-group">
                                <label for="print-color-mode"><?php _e('common.color'); ?></label>
                                <select class="form-control" id="print-color-mode">
                                    <option value="color"><?php _e('common.color'); ?></option>
                                    <option value="monochrome"><?php _e('common.bw'); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-xs-6">
                            <div class="form-group">
                                <label for="print-duplex-mode"><?php _e('common.duplex'); ?></label>
                                <select class="form-control" id="print-duplex-mode">
                                    <option value="simplex"><?php _e('print_modal.simplex'); ?></option>
                                    <option value="duplex"><?php _e('print_modal.duplex_long'); ?></option>
                                    <option value="tumble"><?php _e('print_modal.duplex_short'); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-xs-6">
                            <div class="form-group">
                                <label for="print-scaling"><?php _e('print_modal.scaling'); ?></label>
                                <select class="form-control" id="print-scaling">
                                    <option value="fit" selected><?php _e('print_modal.fit_page'); ?></option>
                                    <option value="shrink"><?php _e('print_modal.shrink_overflow'); ?></option>
                                    <option value="noscale"><?php _e('print_modal.no_scale'); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-xs-6">
                            <div class="form-group">
                                <label for="print-paper-size"><?php _e('common.format'); ?></label>
                                <select class="form-control" id="print-paper-size">
                                    <option value="A4" selected>A4</option>
                                    <option value="A3">A3</option>
                                    <option value="A5">A5</option>
                                    <option value="A6">A6</option>
                                    <option value="A2">A2</option>
                                    <option value="Letter"><?php _e("auto_clean.components_print-modal_html_php_1", [], false); ?></option>
                                    <option value="Legal"><?php _e("auto_clean.components_print-modal_html_php_2", [], false); ?></option>
                                    <option value="Tabloid">Tabloid</option>
                                    <option value="Statement">Statement</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-xs-6">
                            <div class="form-group">
                                <label for="print-orientation"><?php _e('print_modal.orientation'); ?></label>
                                <select class="form-control" id="print-orientation">
                                    <option value="portrait" selected><?php _e('common.portrait'); ?></option>
                                    <option value="landscape"><?php _e('common.landscape'); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-xs-6">
                            <div class="form-group">
                                <label for="print-page-subset"><?php _e('print_modal.page_subset'); ?></label>
                                <select class="form-control" id="print-page-subset">
                                    <option value="all" selected><?php _e('print_modal.all_pages'); ?></option>
                                    <option value="odd"><?php _e('print_modal.odd_pages'); ?></option>
                                    <option value="even"><?php _e('print_modal.even_pages'); ?></option>
                                    <option value="custom"><?php _e('print_modal.custom_range'); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-xs-6">
                            <div class="form-group" id="print-page-range-group" style="display: none;">
                                <label for="print-page-range"><?php _e('print_modal.page_range'); ?></label>
                                <input type="text" class="form-control" id="print-page-range" 
                                    placeholder="<?php echo __('print_modal.page_range_placeholder', [], false); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info" id="print-file-info">
                        <small><i class="fa fa-file-o"></i> <?php _e('print_modal.file'); ?> <strong
                                id="print-filename">document.pdf</strong></small>
                    </div>

                    <div id="print-status-msg" class="text-danger small mt-2"></div>
                </div>

                <div id="print-modal-error" class="alert alert-danger" style="display: none;">
                    <i class="fa fa-exclamation-triangle"></i> <span id="print-error-text"></span>
                </div>
            </div>
            <div class="modal-footer">
                <div class="btn-group dropup pull-left" id="print-impose-group">
                    <button type="button" class="btn btn-warning dropdown-toggle" data-toggle="dropdown"
                        aria-haspopup="true" aria-expanded="false" title="<?php echo __('print_modal.impose'); ?>">
                        <i class="fa fa-magic"></i> <?php _e('print_modal.impose'); ?> <span class="caret"></span>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a href="#" onclick="openImposition('brochure'); return false;"><i
                                    class="fa fa-book text-success"></i> <?php _e('print_modal.imposition_brochure'); ?></a></li>
                        <li><a href="#" onclick="openImposition('livre'); return false;"><i
                                    class="fa fa-book text-primary"></i> <?php _e('print_modal.imposition_livre'); ?></a></li>
                        <li><a href="#" onclick="openImposition('tracts'); return false;"><i
                                    class="fa fa-copy text-warning"></i> <?php _e('print_modal.imposition_tracts'); ?></a></li>
                    </ul>
                </div>
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php _e('common.cancel'); ?></button>
                <button type="button" class="btn btn-primary" id="print-confirm-btn" onclick="executePrint()">
                    <i class="fa fa-print"></i> <?php _e('print_modal.title'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    window.CONFIG = window.CONFIG || {};
    window.CONFIG.translations = Object.assign(window.CONFIG.translations || {}, {
        no_printers_found: <?= json_encode(__('print_modal.no_printers_found')) ?>,
        default_suffix: <?= json_encode(__('print_modal.default_suffix')) ?>,
        error_loading: <?= json_encode(__('admin_printers.error_loading')) ?>,
        error: <?= json_encode(__('common.error')) ?>,
        sending: <?= json_encode(__('print_modal.sending')) ?>,
        title: <?= json_encode(__('print_modal.title')) ?>,
        success: <?= json_encode(__('print_modal.success')) ?>
    });
</script>
<script src="js/components/print-modal.js" defer></script>

