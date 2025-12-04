<!-- Modal de dialogue d'impression -->
<div class="modal fade" id="printDialogModal" tabindex="-1" role="dialog" aria-labelledby="printDialogModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white;">
                <h4 class="modal-title" id="printDialogModalLabel">
                    <i class="fa fa-print"></i> Imprimer le document
                </h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: white; opacity: 0.8;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 30px;">
                <!-- Message de chargement -->
                <div id="printDialogLoading" class="text-center" style="display: none;">
                    <i class="fa fa-spinner fa-spin fa-3x" style="color: #007bff;"></i>
                    <p style="margin-top: 15px;">Chargement des imprimantes...</p>
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
                            <i class="fa fa-print"></i> Imprimante
                        </label>
                        <select class="form-control" id="printPrinterSelect" required>
                            <option value="">Chargement...</option>
                        </select>
                        <small class="form-text text-muted">Sélectionnez l'imprimante à utiliser</small>
                    </div>

                    <!-- Nombre de copies -->
                    <div class="form-group">
                        <label for="printCopies">
                            <i class="fa fa-copy"></i> Nombre de copies
                        </label>
                        <input type="number" class="form-control" id="printCopies" min="1" max="99" value="1" required>
                        <small class="form-text text-muted">Nombre de copies à imprimer</small>
                    </div>

                    <!-- Options avancées (masquées par défaut) -->
                    <div class="form-group">
                        <button type="button" class="btn btn-link" id="printAdvancedToggle" style="padding: 0;">
                            <i class="fa fa-chevron-down"></i> Options avancées
                        </button>
                    </div>

                    <div id="printAdvancedOptions" style="display: none;">
                        <!-- Bac à papier -->
                        <div class="form-group">
                            <label for="printInputSlot">
                                <i class="fa fa-inbox"></i> Bac à papier
                            </label>
                            <select class="form-control" id="printInputSlot">
                                <option value="">Par défaut</option>
                            </select>
                            <small class="form-text text-muted">Sélectionnez le bac à utiliser</small>
                        </div>

                        <!-- Format papier -->
                        <div class="form-group">
                            <label for="printPageSize">
                                <i class="fa fa-file-o"></i> Format papier
                            </label>
                            <select class="form-control" id="printPageSize">
                                <option value="">Par défaut</option>
                            </select>
                            <small class="form-text text-muted">Format de papier à utiliser</small>
                        </div>

                        <!-- Mode couleur -->
                        <div class="form-group">
                            <label for="printColorMode">
                                <i class="fa fa-paint-brush"></i> Mode couleur
                            </label>
                            <select class="form-control" id="printColorMode">
                                <option value="">Par défaut</option>
                            </select>
                            <small class="form-text text-muted">Couleur ou noir et blanc</small>
                        </div>

                        <!-- Recto-verso -->
                        <div class="form-group">
                            <label for="printDuplex">
                                <i class="fa fa-file-text-o"></i> Recto-verso
                            </label>
                            <select class="form-control" id="printDuplex">
                                <option value="Simplex">Recto uniquement</option>
                                <option value="DuplexNoTumble">Recto-verso (long bord)</option>
                                <option value="DuplexTumble">Recto-verso (court bord)</option>
                            </select>
                            <small class="form-text text-muted">Mode d'impression recto-verso</small>
                        </div>

                        <!-- Résolution (si disponible) -->
                        <div class="form-group" id="printResolutionGroup" style="display: none;">
                            <label for="printResolution">
                                <i class="fa fa-image"></i> Résolution
                            </label>
                            <select class="form-control" id="printResolution">
                                <option value="">Par défaut</option>
                            </select>
                            <small class="form-text text-muted">Résolution d'impression</small>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fa fa-times"></i> Annuler
                </button>
                <button type="button" class="btn btn-primary" id="printDialogPrintBtn" disabled>
                    <i class="fa fa-print"></i> Imprimer
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    // Variables globales
    let currentPdfPath = null;
    let printersList = [];
    let currentCapabilities = null;

    // Initialiser le dialogue
    function initPrintDialog() {
        const modal = $('#printDialogModal');
        
        // Réinitialiser le formulaire à chaque ouverture
        modal.on('show.bs.modal', function() {
            resetPrintDialog();
        });

        // Toggle options avancées
        $('#printAdvancedToggle').on('click', function() {
            const options = $('#printAdvancedOptions');
            const icon = $(this).find('i');
            if (options.is(':visible')) {
                options.slideUp();
                icon.removeClass('fa-chevron-up').addClass('fa-chevron-down');
            } else {
                options.slideDown();
                icon.removeClass('fa-chevron-down').addClass('fa-chevron-up');
            }
        });

        // Changement d'imprimante
        $('#printPrinterSelect').on('change', function() {
            const printerName = $(this).val();
            if (printerName) {
                loadPrinterCapabilities(printerName);
            } else {
                clearCapabilities();
            }
        });

        // Bouton Imprimer
        $('#printDialogPrintBtn').on('click', function() {
            printDocument();
        });
    }

    // Réinitialiser le dialogue
    function resetPrintDialog() {
        $('#printDialogForm').hide();
        $('#printDialogLoading').show();
        $('#printDialogError').hide();
        $('#printDialogPrintBtn').prop('disabled', true);
        currentPdfPath = null;
        currentCapabilities = null;
        
        // Réinitialiser les champs
        $('#printCopies').val(1);
        $('#printInputSlot').html('<option value="">Par défaut</option>');
        $('#printPageSize').html('<option value="">Par défaut</option>');
        $('#printColorMode').html('<option value="">Par défaut</option>');
        $('#printDuplex').val('Simplex');
        $('#printResolution').html('<option value="">Par défaut</option>');
        $('#printAdvancedOptions').hide();
        $('#printAdvancedToggle i').removeClass('fa-chevron-up').addClass('fa-chevron-down');
    }

    // Charger les imprimantes
    async function loadPrinters() {
        try {
            if (!window.electronAPI || !window.electronAPI.getPrinters) {
                throw new Error('API Electron non disponible');
            }

            const result = await window.electronAPI.getPrinters();
            
            if (!result.success) {
                throw new Error(result.error || 'Erreur lors du chargement des imprimantes');
            }

            printersList = result.printers || [];
            const select = $('#printPrinterSelect');
            select.empty();

            if (printersList.length === 0) {
                select.append('<option value="">Aucune imprimante disponible</option>');
                $('#printDialogError').text('Aucune imprimante trouvée sur ce système').show();
            } else {
                printersList.forEach(function(printer) {
                    const option = $('<option></option>')
                        .attr('value', printer.name)
                        .text(printer.displayName || printer.name);
                    if (printer.isDefault) {
                        option.attr('selected', 'selected');
                    }
                    select.append(option);
                });

                // Charger les capacités de l'imprimante par défaut
                const defaultPrinter = printersList.find(p => p.isDefault) || printersList[0];
                if (defaultPrinter) {
                    await loadPrinterCapabilities(defaultPrinter.name);
                }
            }

            $('#printDialogLoading').hide();
            $('#printDialogForm').show();
            $('#printDialogPrintBtn').prop('disabled', false);
        } catch (error) {
            console.error('Erreur chargement imprimantes:', error);
            $('#printDialogLoading').hide();
            $('#printDialogError').text('Erreur: ' + error.message).show();
        }
    }

    // Charger les capacités d'une imprimante
    async function loadPrinterCapabilities(printerName) {
        try {
            if (!window.electronAPI || !window.electronAPI.getPrinterCapabilities) {
                return;
            }

            const result = await window.electronAPI.getPrinterCapabilities(printerName);
            
            if (!result.success) {
                console.warn('Impossible de charger les capacités:', result.error);
                return;
            }

            currentCapabilities = result.capabilities;

            // Remplir les bacs
            const inputSlotSelect = $('#printInputSlot');
            inputSlotSelect.empty();
            inputSlotSelect.append('<option value="">Par défaut</option>');
            if (currentCapabilities.inputSlots) {
                currentCapabilities.inputSlots.forEach(function(slot) {
                    inputSlotSelect.append($('<option></option>')
                        .attr('value', slot.value)
                        .text(slot.name));
                });
            }

            // Remplir les formats papier
            const pageSizeSelect = $('#printPageSize');
            pageSizeSelect.empty();
            pageSizeSelect.append('<option value="">Par défaut</option>');
            if (currentCapabilities.pageSizes) {
                currentCapabilities.pageSizes.forEach(function(size) {
                    pageSizeSelect.append($('<option></option>')
                        .attr('value', size.value)
                        .text(size.name + (size.width ? ` (${size.width}×${size.height} mm)` : '')));
                });
            }

            // Remplir les modes couleur
            const colorModeSelect = $('#printColorMode');
            colorModeSelect.empty();
            colorModeSelect.append('<option value="">Par défaut</option>');
            if (currentCapabilities.colorModes && currentCapabilities.colorModes.length > 0) {
                currentCapabilities.colorModes.forEach(function(mode) {
                    colorModeSelect.append($('<option></option>')
                        .attr('value', mode)
                        .text(mode === 'Color' ? 'Couleur' : 'Noir et blanc'));
                });
            }

            // Mettre à jour le recto-verso
            if (currentCapabilities.duplex === false) {
                $('#printDuplex').val('Simplex').prop('disabled', true);
            } else {
                $('#printDuplex').prop('disabled', false);
            }

            // Remplir les résolutions
            const resolutionSelect = $('#printResolution');
            const resolutionGroup = $('#printResolutionGroup');
            if (currentCapabilities.resolutions && currentCapabilities.resolutions.length > 0) {
                resolutionSelect.empty();
                resolutionSelect.append('<option value="">Par défaut</option>');
                currentCapabilities.resolutions.forEach(function(res) {
                    resolutionSelect.append($('<option></option>')
                        .attr('value', res)
                        .text(res));
                });
                resolutionGroup.show();
            } else {
                resolutionGroup.hide();
            }
        } catch (error) {
            console.error('Erreur chargement capacités:', error);
        }
    }

    // Effacer les capacités
    function clearCapabilities() {
        currentCapabilities = null;
        $('#printInputSlot').html('<option value="">Par défaut</option>');
        $('#printPageSize').html('<option value="">Par défaut</option>');
        $('#printColorMode').html('<option value="">Par défaut</option>');
        $('#printDuplex').val('Simplex');
        $('#printResolution').html('<option value="">Par défaut</option>');
    }

    // Ouvrir le dialogue d'impression
    window.openPrintDialog = function(pdfPath) {
        currentPdfPath = pdfPath;
        resetPrintDialog();
        $('#printDialogModal').modal('show');
        loadPrinters();
    };

    // Imprimer le document
    async function printDocument() {
        const printerName = $('#printPrinterSelect').val();
        if (!printerName) {
            alert('Veuillez sélectionner une imprimante');
            return;
        }

        if (!currentPdfPath) {
            alert('Aucun fichier PDF spécifié');
            return;
        }

        // Désactiver le bouton pendant l'impression
        const btn = $('#printDialogPrintBtn');
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Impression...');

        try {
            // Construire les options
            const options = {
                printer: printerName,
                copies: parseInt($('#printCopies').val()) || 1
            };

            const inputSlot = $('#printInputSlot').val();
            if (inputSlot) {
                options.inputSlot = inputSlot;
            }

            const pageSize = $('#printPageSize').val();
            if (pageSize) {
                options.pageSize = pageSize;
            }

            const colorMode = $('#printColorMode').val();
            if (colorMode) {
                options.colorMode = colorMode;
            }

            const duplex = $('#printDuplex').val();
            if (duplex && duplex !== 'Simplex') {
                options.duplex = duplex;
            }

            const resolution = $('#printResolution').val();
            if (resolution) {
                options.resolution = resolution;
            }

            // Log détaillé des options sélectionnées
            const printLogData = {
                timestamp: new Date().toISOString(),
                pdfPath: currentPdfPath,
                options: JSON.parse(JSON.stringify(options))
            };
            console.log('🖨️ [PRINT_DIALOG] Options d\'impression sélectionnées:', JSON.stringify(printLogData, null, 2));
            console.log('📄 [PRINT_DIALOG] Document:', currentPdfPath);
            console.log('🖨️ [PRINT_DIALOG] Imprimante:', printerName);
            console.log('📋 [PRINT_DIALOG] Options:', options);

            // Appeler l'API d'impression
            if (!window.electronAPI || !window.electronAPI.printJob) {
                throw new Error('API Electron non disponible');
            }

            const result = await window.electronAPI.printJob(currentPdfPath, options);

            if (result.success) {
                // Succès
                alert('Impression lancée avec succès !\n\n' + (result.message || 'Le document a été envoyé à l\'imprimante.'));
                $('#printDialogModal').modal('hide');
            } else {
                throw new Error(result.error || 'Erreur lors de l\'impression');
            }
        } catch (error) {
            console.error('Erreur impression:', error);
            alert('Erreur lors de l\'impression:\n\n' + error.message);
        } finally {
            btn.prop('disabled', false).html('<i class="fa fa-print"></i> Imprimer');
        }
    }

    // Initialiser au chargement
    $(document).ready(function() {
        initPrintDialog();
    });
})();
</script>

<style>
#printDialogModal .modal-content {
    border-radius: 8px;
    border: none;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
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

