<!-- Modal de dialogue d'impression -->
<div class="modal fade" id="printDialogModal" tabindex="-1" role="dialog" aria-labelledby="printDialogModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header"
                style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white;">
                <h4 class="modal-title" id="printDialogModalLabel">
                    <i class="fa fa-print"></i> <?php _e('print_dialog.title', [], false); ?>
                </h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"
                    style="color: white; opacity: 0.8;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 30px;">
                <!-- Message de chargement -->
                <div id="printDialogLoading" class="text-center" style="display: none;">
                    <i class="fa fa-spinner fa-spin fa-3x" style="color: #007bff;"></i>
                    <p style="margin-top: 15px;"><?php _e('print_dialog.loading_printers', [], false); ?></p>
                </div>

                <!-- Message d'erreur -->
                <div id="printDialogError" class="alert alert-danger" style="display: none;">
                    <i class="fa fa-exclamation-triangle"></i> <span id="printDialogErrorText"></span>
                </div>

                <!-- Formulaire d'impression -->
                <form id="printDialogForm" style="display: none;">
                    <!-- Sélecteur d'imprimante -->
                    <div class="form-group">
                        <label for="printPrinterSelect">
                            <i class="fa fa-print"></i> <?php _e('print_dialog.printer', [], false); ?>
                        </label>
                        <select class="form-control" id="printPrinterSelect" required>
                            <option value=""><?php _e('print_dialog.loading', [], false); ?></option>
                        </select>
                        <small class="form-text text-muted"><?php _e('print_dialog.select_printer_help', [], false); ?></small>
                    </div>

                    <!-- Nombre de copies -->
                    <div class="form-group">
                        <label for="printCopies">
                            <i class="fa fa-copy"></i> <?php _e('print_dialog.copies_count', [], false); ?>
                        </label>
                        <input type="number" class="form-control" id="printCopies" min="1" max="99" value="1" required>
                        <small class="form-text text-muted"><?php _e('print_dialog.copies_help', [], false); ?></small>
                    </div>

                    <!-- Options avancées (masquées par défaut) -->
                    <div class="form-group">
                        <button type="button" class="btn btn-link" id="printAdvancedToggle" style="padding: 0;">
                            <i class="fa fa-chevron-down"></i> <?php _e('print_dialog.advanced_options', [], false); ?>
                        </button>
                    </div>

                    <div id="printAdvancedOptions" style="display: none;">
                        <!-- Bac à papier -->
                        <div class="form-group">
                            <label for="printInputSlot">
                                <i class="fa fa-inbox"></i> <?php _e('print_dialog.paper_tray', [], false); ?>
                            </label>
                            <select class="form-control" id="printInputSlot">
                                <option value=""><?php _e('print_dialog.default', [], false); ?></option>
                            </select>
                            <small class="form-text text-muted"><?php _e('print_dialog.tray_help', [], false); ?></small>
                        </div>

                        <!-- Format papier -->
                        <div class="form-group">
                            <label for="printPageSize">
                                <i class="fa fa-file-o"></i> <?php _e('print_dialog.paper_size', [], false); ?>
                            </label>
                            <select class="form-control" id="printPageSize">
                                <option value=""><?php _e('print_dialog.default', [], false); ?></option>
                            </select>
                            <small class="form-text text-muted"><?php _e('print_dialog.size_help', [], false); ?></small>
                        </div>

                        <!-- Mode couleur -->
                        <div class="form-group">
                            <label for="printColorMode">
                                <i class="fa fa-paint-brush"></i> <?php _e('print_dialog.color_mode', [], false); ?>
                            </label>
                            <select class="form-control" id="printColorMode">
                                <option value=""><?php _e('print_dialog.default', [], false); ?></option>
                            </select>
                            <small class="form-text text-muted"><?php _e('print_dialog.color_help', [], false); ?></small>
                        </div>

                        <!-- Recto-verso -->
                        <div class="form-group">
                            <label for="printDuplex">
                                <i class="fa fa-file-text-o"></i> <?php _e('print_dialog.duplex', [], false); ?>
                            </label>
                            <select class="form-control" id="printDuplex">
                                <option value="Simplex"><?php _e('print_dialog.simplex', [], false); ?></option>
                                <option value="DuplexNoTumble"><?php _e('print_dialog.duplex_long', [], false); ?></option>
                                <option value="DuplexTumble"><?php _e('print_dialog.duplex_short', [], false); ?></option>
                            </select>
                            <small class="form-text text-muted"><?php _e('print_dialog.duplex_help', [], false); ?></small>
                        </div>

                        <!-- Résolution (si disponible) -->
                        <div class="form-group" id="printResolutionGroup" style="display: none;">
                            <label for="printResolution">
                                <i class="fa fa-image"></i> <?php _e('print_dialog.resolution', [], false); ?>
                            </label>
                            <select class="form-control" id="printResolution">
                                <option value=""><?php _e('print_dialog.default', [], false); ?></option>
                            </select>
                            <small class="form-text text-muted"><?php _e('print_dialog.resolution_help', [], false); ?></small>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fa fa-times"></i> <?php _e('print_dialog.cancel', [], false); ?>
                </button>
                <button type="button" class="btn btn-primary" id="printDialogPrintBtn" disabled>
                    <i class="fa fa-print"></i> <?php _e('print_dialog.print_btn', [], false); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script src="js/print-dialog.js" defer></script>


<style>
    #printDialogModal .modal-content {
        border-radius: 8px;
        border: none;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    }

    #printDialogModal .modal-header {
        border-bottom: none;
        border-radius: 8px 8px 0 0;
    }

    #printDialogModal .modal-footer {
        border-top: 1px solid #e9ecef;
        padding: 15px 30px;
    }

    #printDialogModal .form-control {
        border: 2px solid #e9ecef;
        border-radius: 6px;
        padding: 10px 15px;
        transition: border-color 0.3s ease;
    }

    #printDialogModal .form-control:focus {
        border-color: #007bff;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    }

    #printDialogModal .btn-primary {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        border: none;
        padding: 10px 30px;
        font-weight: 500;
    }

    #printDialogModal .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0, 123, 255, 0.3);
    }

    #printDialogModal .btn-link {
        color: #007bff;
        text-decoration: none;
    }

    #printDialogModal .btn-link:hover {
        text-decoration: underline;
    }
</style>
