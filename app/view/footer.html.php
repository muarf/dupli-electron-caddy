<div class="navbar navbar-default navbar-fixed-bottom">
    <div class="container" style="display: flex; align-items: center; justify-content: space-between; height: 100%;">
        <button type="button" class="btn btn-default btn-sm" onclick="history.back()"
            style="display: flex; align-items: center; margin-top: 6px;">
            <span class="glyphicon glyphicon-chevron-left" aria-hidden="true"></span> <?php _e('footer.previous'); ?>
        </button>
        <p class="navbar-text text-center" style="margin: 0; flex: 1;"><?php _e('footer.coded_with_love'); ?> <a
                href="https://github.com/muarf/dupli-electron-caddy/"> <?php _e('footer.github'); ?> </a></p>
        <?php if (isset($_SESSION['admin']) && $_SESSION['admin']): ?>
            <button type="button" class="btn btn-info btn-sm" id="toggle-edit-btn"
                style="display: flex; align-items: center; margin-top: 6px;">
                <span class="glyphicon glyphicon-edit" aria-hidden="true"></span> <span
                    id="toggle-edit-text"><?php _e('footer.toggle_edit'); ?></span>
            </button>
        <?php else: ?>
            <div style="width: 80px;"></div> <!-- Espace pour équilibrer -->
        <?php endif; ?>
    </div>
</div>

<?php
// Inclure le modal de sélection de session (pour détection impression)
$modal_path = __DIR__ . '/components/session-modal.html.php';
if (file_exists($modal_path)) {
    include $modal_path;
}

// Inclure la modale globale pour remplacer alert/confirm
$app_modal_path = __DIR__ . '/components/app-modal.html.php';
if (file_exists($app_modal_path)) {
    include $app_modal_path;
}

// Inclure la modale d'impression globale
$print_modal_path = __DIR__ . '/components/print-modal.html.php';
if (file_exists($print_modal_path)) {
    include $print_modal_path;
}
?>

<!-- Print Session Manager - Toast Notifications CSS -->
<link href="css/toast-notifications.css" rel="stylesheet" type="text/css">

<!-- Global Utility & Print Session Manager -->
<script>
    // Feature detection pour mode Electron vs Standalone
    window.isElectronMode = typeof window.electronAPI !== 'undefined';

    if (!window.isElectronMode) {
        console.log('[App] Mode standalone PHP - Pas de détection auto d\'impressions');
    }

    /**
     * Remplace native alert() et confirm() par une modale Bootstrap
     * @param {Object|String} options - Message ou objet d'options
     * @param {Function} callback - Appelé au clic sur OK (ou Cancel)
     */
    window.showAppModal = function (options, callback) {
        var isString = typeof options === 'string';
        var msg = isString ? options : options.message;
        var title = (!isString && options.title) ? options.title : "Message";
        var type = (!isString && options.type) ? options.type : "info"; // info, success, warning, danger
        var isConfirm = (!isString && options.confirm === true);
        var isPrompt = (!isString && options.prompt === true);

        // Support pour onConfirm/onClose dans l'objet options (en plus du callback)
        var onConfirmFn = (!isString && typeof options.onConfirm === 'function') ? options.onConfirm : null;
        var onCloseFn = (!isString && typeof options.onClose === 'function') ? options.onClose : null;

        var $modal = $('#app-global-modal');
        $modal.find('#app-global-modal-title-text').text(title);
        $modal.find('#app-global-modal-body').html(msg); // Changed to .html() to support <br> tags

        // Input pour prompt
        var $inputContainer = $modal.find('#app-global-modal-input-container');
        var $input = $modal.find('#app-global-modal-input');
        if (isPrompt) {
            $inputContainer.show();
            $input.val(options.defaultValue || "");
            // Focus après animation de la modale
            setTimeout(function () { $input.focus(); }, 500);
        } else {
            $inputContainer.hide();
        }

        // Icone
        var iconClass = {
            'info': 'fa-info-circle text-primary',
            'success': 'fa-check-circle text-success',
            'warning': 'fa-exclamation-triangle text-warning',
            'danger': 'fa-times-circle text-danger'
        }[type] || 'fa-info-circle text-primary';

        $modal.find('#app-global-modal-icon').attr('class', 'fa ' + iconClass);

        // Boutons
        if (isConfirm || isPrompt) {
            $modal.find('#app-global-modal-cancel').show();
            $modal.find('#app-global-modal-ok').text(options.okText || (isPrompt ? "Valider" : "Confirmer"));
        } else {
            $modal.find('#app-global-modal-cancel').hide();
            $modal.find('#app-global-modal-ok').text("OK");
        }

        // Gestion du callback - Support onConfirm dans options OU callback en 2ème argument
        $modal.find('#app-global-modal-ok').off('click').on('click', function () {
            $modal.modal('hide');
            if (onConfirmFn) {
                onConfirmFn(isPrompt ? $input.val() : true);
            } else if (callback) {
                if (isPrompt) callback($input.val());
                else callback(true);
            }
        });

        $modal.find('#app-global-modal-cancel').off('click').on('click', function () {
            $modal.modal('hide');
            if (onCloseFn) {
                onCloseFn();
            } else if (callback) {
                if (isPrompt) callback(null);
                else callback(false);
            }
        });

        // Validation par "Entrée" dans le champ prompt
        if (isPrompt) {
            $input.off('keypress').on('keypress', function (e) {
                if (e.which == 13) {
                    $modal.find('#app-global-modal-ok').click();
                }
            });
        }

        $modal.modal('show');
    };

    // DEBUG: Écouter les logs du processus principal
    if (window.isElectronMode && window.electronAPI.onConsoleLog) {
        window.electronAPI.onConsoleLog(function (payload) {
            var msg = payload.message;
            var type = payload.type || 'info';
            var styles = {
                'success': 'color: #28a745; font-weight: bold;',
                'warning': 'color: #ffc107; font-weight: bold;',
                'danger': 'color: #dc3545; font-weight: bold;',
                'info': 'color: #17a2b8; font-weight: bold;'
            };
            console.log('%c' + msg, styles[type] || styles.info);
        });
    }
</script>
<script src="js/print-session-manager.js"></script>