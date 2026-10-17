// ============================================================
// print-modal.js -- Composant Modale d'Impression
// Extrait de components/print-modal.html.php
// ============================================================

/* global $, CONFIG, window, document */

(function () {
    // État interne
    let currentFileUrl = null;
    let currentFileId = null;
    let currentFileType = null;
    let isElectron = typeof window.electronAPI !== 'undefined';

    function getTranslations() {
        return (typeof CONFIG !== 'undefined' && CONFIG.translations) ? CONFIG.translations : {};
    }

    // Fonctions helper globales
    window.incrementCopies = function () {
        let el = document.getElementById('print-copies');
        if (el) el.value = parseInt(el.value || 0) + 1;
    };

    window.decrementCopies = function () {
        let el = document.getElementById('print-copies');
        if (el && parseInt(el.value) > 1) el.value = parseInt(el.value) - 1;
    };

    function showError(msg) {
        $('#print-modal-error').show();
        $('#print-error-text').text(msg);
    }

    /**
     * Ouvre la modale d'impression
     * @param {string} fileUrl - URL complète du fichier à imprimer
     * @param {string|number} fileId - ID du fichier (optionnel, pour l'imposition)
     * @param {string} fileType - Type du fichier (pdf, png...)
     * @param {string} fileName - Nom affiché du fichier
     */
    window.openPrintModal = function (fileUrl, fileId, fileType, fileName) {
        const trans = getTranslations();

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
                        $select.append(`<option disabled selected>${trans.no_printers_found || 'Aucune imprimante trouvée'}</option>`);
                    } else {
                        result.printers.forEach(p => {
                            const isDefault = p.isDefault ? ' selected' : '';
                            const text = p.name + (p.isDefault ? ` ${trans.default_suffix || '(par défaut)'}` : '');
                            $select.append(`<option value="${p.name}"${isDefault}>${text}</option>`);
                        });
                        $('#print-confirm-btn').prop('disabled', false);
                    }
                } else {
                    showError((trans.error_loading || (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.print_modal.erreur_lors_du_chargement'] || 'Erreur lors du chargement')) + ' : ' + result.error);
                }
            })
            .catch(err => {
                $('#print-modal-loading').hide();
                showError((trans.error || 'Erreur') + ' : ' + err.message);
            });
    };

    window.executePrint = function () {
        const trans = getTranslations();
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

        const sendingText = trans.sending || 'Envoi en cours...';
        const titleText = trans.title || 'Imprimer';

        $('#print-confirm-btn').prop('disabled', true).html(`<i class="fa fa-spinner fa-spin"></i> ${sendingText}`);

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

        window.electronAPI.printFile(currentFileUrl, options)
            .then(result => {
                $('#print-confirm-btn').prop('disabled', false).html(`<i class="fa fa-print"></i> ${titleText}`);
                $('#app-print-modal').modal('hide');

                if (result.success) {
                    if (window.showAppModal) {
                        window.showAppModal({ message: trans.success || 'Impression lancée avec succès', type: 'success' });
                    }
                } else {
                    if (window.showAppModal) {
                        window.showAppModal({ message: (trans.error || 'Erreur') + ' : ' + (result.error || 'Inconnue'), type: 'danger' });
                    } else {
                        console.error((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.print_modal.erreur_impression'] || 'Erreur impression:'), result.error);
                    }
                }
            })
            .catch(err => {
                $('#print-confirm-btn').prop('disabled', false).html(`<i class="fa fa-print"></i> ${titleText}`);
                $('#app-print-modal').modal('hide');
                if (window.showAppModal) {
                    window.showAppModal({ title: trans.error || 'Erreur', message: err.message, type: 'danger' });
                } else {
                    console.error((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.print_modal.erreur_critique'] || 'Erreur critique:'), err);
                }
            });
    };

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
})();
