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
                                    <option value="Letter">Letter (US)</option>
                                    <option value="Legal">Legal (US)</option>
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
    (function () {
        // État interne
        let currentFileUrl = null;
        let currentFileId = null;
        let currentFileType = null;
        let isElectron = typeof window.electronAPI !== 'undefined';

        // Fonctions helper globales
        window.incrementCopies = function () {
            let el = document.getElementById('print-copies');
            el.value = parseInt(el.value || 0) + 1;
        };

        window.decrementCopies = function () {
            let el = document.getElementById('print-copies');
            if (parseInt(el.value) > 1) el.value = parseInt(el.value) - 1;
        };

        /**
         * Ouvre la modale d'impression
         * @param {string} fileUrl - URL complète du fichier à imprimer
         * @param {string|number} fileId - ID du fichier (optionnel, pour l'imposition)
         * @param {string} fileType - Type du fichier (pdf, png...)
         * @param {string} fileName - Nom affiché du fichier
         */
        window.openPrintModal = function (fileUrl, fileId, fileType, fileName) {
            if (!isElectron) {
                // Fallback web standard
                window.open(fileUrl, '_blank');
                return;
            }

            currentFileUrl = fileUrl;
            currentFileId = fileId;
            currentFileType = fileType;

            // Reset UI
            $('#print-modal-loading').show();
            $('#print-modal-form').hide();
            $('#print-modal-error').hide();
            $('#print-confirm-btn').prop('disabled', true);

            // Set file info
            $('#print-filename').text(fileName || 'Document');

            // Show impose dropdown only if we have an ID and it's a PDF/Queue item
            if (fileId && (!fileType || fileType === 'pdf')) {
                $('#print-impose-group').show();
            } else {
                $('#print-impose-group').hide();
            }

            // Show modal
            $('#app-print-modal').modal('show');

            // Load printers
            window.electronAPI.getPrinters()
                .then(result => {
                    $('#print-modal-loading').hide();

                    if (result.success) {
                        $('#print-modal-form').show();
                        const $select = $('#print-printer-select');
                        $select.empty();

                        if (result.printers.length === 0) {
                            $select.append('<option disabled selected><?php echo __js('print_modal.no_printers_found'); ?></option>');
                        } else {
                            result.printers.forEach(p => {
                                const isDefault = p.isDefault ? ' selected' : '';
                                const text = p.name + (p.isDefault ? ' <?php echo __js('print_modal.default_suffix'); ?>' : '');
                                $select.append(`<option value="${p.name}"${isDefault}>${text}</option>`);
                            });
                            $('#print-confirm-btn').prop('disabled', false);
                        }
                    } else {
                        showError('<?php echo __js('admin_printers.error_loading'); ?> : ' + result.error);
                    }
                })
                .catch(err => {
                    $('#print-modal-loading').hide();
                    showError('<?php echo __js('common.error'); ?> : ' + err.message);
                });
        };

        window.executePrint = function () {
            const printerName = $('#print-printer-select').val();
            const copies = parseInt($('#print-copies').val()) || 1;
            const colorMode = $('#print-color-mode').val();
            const duplexMode = $('#print-duplex-mode').val();
            const paperSize = $('#print-paper-size').val();
            const orientation = $('#print-orientation').val();
            const pageSubset = $('#print-page-subset').val();
            const scaling = $('#print-scaling').val();
            const pageRange = $('#print-page-range').val();

            if (!printerName) return;

            $('#print-confirm-btn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> <?php echo __js('print_modal.sending'); ?>');

            const options = {
                printer: printerName,
                copies: copies,
                colorMode: colorMode,
                duplex: duplexMode,
                paperSize: paperSize,
                orientation: orientation,
                pageSubset: pageSubset,
                scaling: scaling,
                fileName: $('#print-filename').text()
            };

            // Ajouter la plage de pages si mode personnalisé
            if (pageSubset === 'custom' && pageRange && pageRange.trim()) {
                options.pageRange = pageRange.trim();
            }

            console.log('🖨️ Impression avec SumatraPDF, options:', options);

            // Nous avons mis à jour le backend pour accepter un objet options en 2ème argument
            window.electronAPI.printFile(currentFileUrl, options)
                .then(result => {
                    $('#print-confirm-btn').prop('disabled', false).html('<i class="fa fa-print"></i> <?php echo __js('print_modal.title'); ?>');
                    $('#app-print-modal').modal('hide');

                    if (result.success) {
                        if (window.showAppModal) {
                            window.showAppModal({ message: '<?php echo __js('print_modal.success'); ?>', type: 'success' });
                        }
                    } else {
                        if (window.showAppModal) {
                            window.showAppModal({ message: '<?php echo __js('common.error'); ?> : ' + (result.error || 'Inconnue'), type: 'danger' });
                        } else {
                            console.error('Erreur impression:', result.error);
                        }
                    }
                })
                .catch(err => {
                    $('#print-confirm-btn').prop('disabled', false).html('<i class="fa fa-print"></i> <?php echo __js('print_modal.title'); ?>');
                    $('#app-print-modal').modal('hide');
                    if (window.showAppModal) {
                        window.showAppModal({ title: '<?php echo __js('common.error'); ?>', message: err.message, type: 'danger' });
                    } else {
                        console.error('Erreur critique:', err);
                    }
                });
        };

        // Toggle affichage du champ plage de pages personnalisée
        $(document).ready(function() {
            $('#print-page-subset').on('change', function() {
                if ($(this).val() === 'custom') {
                    $('#print-page-range-group').show();
                    $('#print-page-range').focus();
                } else {
                    $('#print-page-range-group').hide();
                    $('#print-page-range').val('');
                }
            });
        });

        function showError(msg) {
            $('#print-modal-error').show();
            $('#print-error-text').text(msg);
        }

        window.openImposition = function (type) {
            if (!currentFileId) return;

            $('#app-print-modal').modal('hide');

            let url = '';
            switch (type) {
                case 'brochure':
                    url = '?imposition_brochure&from_lib=' + encodeURIComponent(currentFileId);
                    break;
                case 'livre':
                    url = '?imposition_livre&from_lib=' + encodeURIComponent(currentFileId);
                    break;
                case 'tracts':
                    url = '?imposition_tracts&from_lib=' + encodeURIComponent(currentFileId);
                    break;
            }

            if (url) {
                window.location.href = url;
            }
        };
    })();
</script>
