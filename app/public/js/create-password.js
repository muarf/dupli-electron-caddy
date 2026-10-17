// ============================================================
// create-password.js -- Création de mot de passe administrateur
// Extrait de create_password.html.php
// ============================================================

/* global $, CONFIG, window, document */

window.showAppModal = function (options) {
    if (typeof options === 'string') {
        options = { message: options };
    }

    const modal = $('#app-global-modal');
    const translations = (typeof CONFIG !== 'undefined' && CONFIG.translations) ? CONFIG.translations : {};
    const title = options.title || translations.info || 'Information';
    const message = options.message || '';
    const type = options.type || 'info'; // info, success, warning, danger
    const confirm = options.confirm || false;
    const okText = options.okText || 'OK';
    const cancelText = options.cancelText || translations.cancel || 'Annuler';

    // Configurer le titre et le message
    $('#app-global-modal-title-text').text(title);
    $('#app-global-modal-body').html(message);

    const okBtn = $('#app-global-modal-ok');
    const icon = $('#app-global-modal-icon');

    // Reset classes
    icon.removeClass('text-primary text-success text-warning text-danger');
    okBtn.removeClass('btn-primary btn-success btn-warning btn-danger');

    // Appliquer le type
    let iconClass = 'fa-info-circle';

    switch (type) {
        case 'success': iconClass = 'fa-check-circle'; break;
        case 'warning': iconClass = 'fa-exclamation-triangle'; break;
        case 'danger': iconClass = 'fa-exclamation-circle'; break;
    }

    icon.addClass('text-' + (type === 'info' ? 'primary' : type)).addClass(iconClass);
    okBtn.addClass('btn-' + (type === 'info' ? 'primary' : type));
    okBtn.text(okText);

    // Gérer le bouton Annuler et Confirmation
    if (confirm) {
        $('#app-global-modal-cancel').show().text(cancelText);
    } else {
        $('#app-global-modal-cancel').hide();
    }

    // Callbacks
    okBtn.off('click').on('click', function() {
        if (options.onConfirm) options.onConfirm();
        if (options.onClose) options.onClose();
    });

    $('#app-global-modal-cancel, .close').off('click').on('click', function() {
        if (options.onCancel) options.onCancel();
        if (options.onClose) options.onClose();
    });

    modal.modal('show');
};

document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const password = document.getElementById('admin_password').value;
            const confirmVal = document.getElementById('admin_password_confirm').value;
            const translations = (typeof CONFIG !== 'undefined' && CONFIG.translations) ? CONFIG.translations : {};
            
            if (password !== confirmVal) {
                e.preventDefault();
                window.showAppModal(translations.passwords_dont_match || 'Les mots de passe ne correspondent pas.');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                window.showAppModal(translations.password_too_short || 'Le mot de passe doit faire au moins 6 caractères.');
                return false;
            }
        });
    }
});
