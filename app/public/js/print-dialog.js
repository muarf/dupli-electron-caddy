// ============================================================
// print-dialog.js -- Dialogue d'Impression Avancé
// Extrait de print_dialog.html.php
// ============================================================

/* global $, window, console, showAppModal */

(function () {
    'use strict';

    let currentPdfPath = null;
    let printersList = [];
    let currentCapabilities = null;

    function initPrintDialog() {
        const modal = $('#printDialogModal');

        modal.on('show.bs.modal', function () {
            resetPrintDialog();
        });

        $('#printAdvancedToggle').on('click', function () {
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

        $('#printPrinterSelect').on('change', function () {
            const printerName = $(this).val();
            if (printerName) {
                loadPrinterCapabilities(printerName);
            } else {
                clearCapabilities();
            }
        });

        $('#printDialogPrintBtn').on('click', function () {
            printDocument();
        });
    }

    function resetPrintDialog() {
        $('#printDialogForm').hide();
        $('#printDialogLoading').show();
        $('#printDialogError').hide();
        $('#printDialogPrintBtn').prop('disabled', true);
        currentPdfPath = null;
        currentCapabilities = null;

        $('#printCopies').val(1);
        $('#printInputSlot').html('<option value="">Par défaut</option>');
        $('#printPageSize').html('<option value="">Par défaut</option>');
        $('#printColorMode').html('<option value="">Par défaut</option>');
        $('#printDuplex').val('Simplex');
        $('#printResolution').html('<option value="">Par défaut</option>');
        $('#printAdvancedOptions').hide();
        $('#printAdvancedToggle i').removeClass('fa-chevron-up').addClass('fa-chevron-down');
    }

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
                $('#printDialogErrorText').text('Aucune imprimante trouvée sur ce système').show();
                $('#printDialogError').show();
            } else {
                printersList.forEach(function (printer) {
                    const option = $('<option></option>')
                        .attr('value', printer.name)
                        .text(printer.displayName || printer.name);
                    if (printer.isDefault) {
                        option.attr('selected', 'selected');
                    }
                    select.append(option);
                });

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
            $('#printDialogErrorText').text('Erreur: ' + error.message);
            $('#printDialogError').show();
        }
    }

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

            const inputSlotSelect = $('#printInputSlot');
            inputSlotSelect.empty();
            inputSlotSelect.append('<option value="">Par défaut</option>');
            if (currentCapabilities.inputSlots) {
                currentCapabilities.inputSlots.forEach(function (slot) {
                    inputSlotSelect.append($('<option></option>')
                        .attr('value', slot.value)
                        .text(slot.name));
                });
            }

            const pageSizeSelect = $('#printPageSize');
            pageSizeSelect.empty();
            pageSizeSelect.append('<option value="">Par défaut</option>');
            if (currentCapabilities.pageSizes) {
                currentCapabilities.pageSizes.forEach(function (size) {
                    pageSizeSelect.append($('<option></option>')
                        .attr('value', size.value)
                        .text(size.name + (size.width ? ` (${size.width}×${size.height} mm)` : '')));
                });
            }

            const colorModeSelect = $('#printColorMode');
            colorModeSelect.empty();
            colorModeSelect.append('<option value="">Par défaut</option>');
            if (currentCapabilities.colorModes && currentCapabilities.colorModes.length > 0) {
                currentCapabilities.colorModes.forEach(function (mode) {
                    colorModeSelect.append($('<option></option>')
                        .attr('value', mode)
                        .text(mode === 'Color' ? 'Couleur' : 'Noir et blanc'));
                });
            }

            if (currentCapabilities.duplex === false) {
                $('#printDuplex').val('Simplex').prop('disabled', true);
            } else {
                $('#printDuplex').prop('disabled', false);
            }

            const resolutionSelect = $('#printResolution');
            const resolutionGroup = $('#printResolutionGroup');
            if (currentCapabilities.resolutions && currentCapabilities.resolutions.length > 0) {
                resolutionSelect.empty();
                resolutionSelect.append('<option value="">Par défaut</option>');
                currentCapabilities.resolutions.forEach(function (res) {
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

    function clearCapabilities() {
        currentCapabilities = null;
        $('#printInputSlot').html('<option value="">Par défaut</option>');
        $('#printPageSize').html('<option value="">Par défaut</option>');
        $('#printColorMode').html('<option value="">Par défaut</option>');
        $('#printDuplex').val('Simplex');
        $('#printResolution').html('<option value="">Par défaut</option>');
    }

    window.openPrintDialog = function (pdfPath) {
        currentPdfPath = pdfPath;
        resetPrintDialog();
        $('#printDialogModal').modal('show');
        loadPrinters();
    };

    async function printDocument() {
        const printerName = $('#printPrinterSelect').val();
        if (!printerName) {
            if (window.showAppModal) {
                window.showAppModal({ message: 'Veuillez sélectionner une imprimante', type: 'warning' });
            }
            return;
        }

        if (!currentPdfPath) {
            if (window.showAppModal) {
                window.showAppModal({ message: 'Aucun fichier PDF spécifié', type: 'warning' });
            }
            return;
        }

        const btn = $('#printDialogPrintBtn');
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Impression...');

        try {
            const options = {
                printer: printerName,
                copies: parseInt($('#printCopies').val()) || 1
            };

            const inputSlot = $('#printInputSlot').val();
            if (inputSlot) options.inputSlot = inputSlot;

            const pageSize = $('#printPageSize').val();
            if (pageSize) options.pageSize = pageSize;

            const colorMode = $('#printColorMode').val();
            if (colorMode) options.colorMode = colorMode;

            const duplex = $('#printDuplex').val();
            if (duplex && duplex !== 'Simplex') options.duplex = duplex;

            const resolution = $('#printResolution').val();
            if (resolution) options.resolution = resolution;

            const printLogData = {
                timestamp: new Date().toISOString(),
                pdfPath: currentPdfPath,
                options: JSON.parse(JSON.stringify(options))
            };
            console.log('🖨️ [PRINT_DIALOG] Options d\'impression sélectionnées:', JSON.stringify(printLogData, null, 2));

            if (!window.electronAPI || !window.electronAPI.printJob) {
                throw new Error('API Electron non disponible');
            }

            const result = await window.electronAPI.printJob(currentPdfPath, options);

            if (result.success) {
                if (window.showAppModal) {
                    window.showAppModal({
                        message: 'Impression lancée avec succès !\n\n' + (result.message || 'Le document a été envoyé à l\'imprimante.'),
                        type: 'success'
                    });
                }
                $('#printDialogModal').modal('hide');
            } else {
                throw new Error(result.error || 'Erreur lors de l\'impression');
            }
        } catch (error) {
            console.error('Erreur impression:', error);
            if (window.showAppModal) {
                window.showAppModal({ message: 'Erreur lors de l\'impression:\n\n' + error.message, type: 'danger' });
            }
        } finally {
            btn.prop('disabled', false).html('<i class="fa fa-print"></i> Imprimer');
        }
    }

    $(document).ready(function () {
        initPrintDialog();
    });
})();
