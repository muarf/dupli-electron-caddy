<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Création du mot de passe administrateur - Dupli</title>
    <link href="public/css/bootstrap.css" rel="stylesheet" type="text/css">
    <link href="public/css/font-awesome.min.css" rel="stylesheet" type="text/css">
    <script src="public/js/jquery.min.js"></script>
    <script src="public/js/bootstrap.min.js"></script>
    <style>
        .password-container {
            max-width: 600px;
            margin: 100px auto;
            padding: 40px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .password-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .password-header h1 {
            color: #333;
            margin-bottom: 10px;
        }
        .password-header p {
            color: #666;
            font-size: 16px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            display: block;
        }
        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        .form-control:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
        }
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,123,255,0.3);
        }
        .alert {
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #007bff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 25px;
        }
        .info-box p {
            margin: 0;
            color: #004085;
        }
    </style>
</head>
<body style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh;">
    <div class="password-container">
        <div class="pull-right">
            <?php echo generateLanguageSelector(); ?>
        </div>
        <div class="password-header">
            <h1>🔐 <?php _e('create_password.title'); ?></h1>
            <p><?php _e('create_password.subtitle'); ?></p>
        </div>

        <?php if (isset($errors) && !empty($errors)): ?>
            <div class="alert alert-danger">
                <h5><i class="fa fa-exclamation-triangle"></i> <?php _e('setup.errors_detected'); ?></h5>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="info-box">
            <p><i class="fa fa-info-circle"></i> <strong><?php _e('create_password.important'); ?></strong> <?php _e('create_password.important_desc'); ?></p>
        </div>

        <form method="POST" action="?create_password">
            <div class="form-group">
                <label for="admin_password">
                    <i class="fa fa-lock"></i> <?php _e('create_password.password_label'); ?>
                </label>
                <input 
                    type="password" 
                    id="admin_password" 
                    name="admin_password" 
                    class="form-control" 
                    required 
                    minlength="6"
                    placeholder="<?php echo __('create_password.min_chars'); ?>"
                    autocomplete="new-password"
                >
            </div>

            <div class="form-group">
                <label for="admin_password_confirm">
                    <i class="fa fa-lock"></i> <?php _e('create_password.confirm_label'); ?>
                </label>
                <input 
                    type="password" 
                    id="admin_password_confirm" 
                    name="admin_password_confirm" 
                    class="form-control" 
                    required 
                    minlength="6"
                    placeholder="<?php echo __('create_password.repeat_password'); ?>"
                    autocomplete="new-password"
                >
            </div>

            <button type="submit" class="btn-submit">
                <i class="fa fa-check"></i> <?php _e('create_password.submit_btn'); ?>
            </button>
        </form>
    </div>

    <?php include __DIR__ . '/components/app-modal.html.php'; ?>

    <script>
        /**
         * Affiche une modale d'information ou de confirmation
         * Version autonome pour create_password.html.php
         */
        function showAppModal(options) {
            if (typeof options === 'string') {
                options = { message: options };
            }

            const modal = $('#app-global-modal');
            const title = options.title ||  "<?php echo __('common.info'); ?>" ;
            const message = options.message || '';
            const type = options.type || 'info'; // info, success, warning, danger
            const confirm = options.confirm || false;
            const okText = options.okText || 'OK';
            const cancelText = options.cancelText ||  "<?php echo __('common.cancel'); ?>" ;

            // Configurer le titre et le message
            $('#app-global-modal-title-text').text(title);
            $('#app-global-modal-body').html(message);

            // Gérer les couleurs selon le type
            const header = modal.find('.modal-header');
            const okBtn = $('#app-global-modal-ok');
            const icon = $('#app-global-modal-icon');

            // Reset classes
            icon.removeClass('text-primary text-success text-warning text-danger');
            okBtn.removeClass('btn-primary btn-success btn-warning btn-danger');

            // Appliquer le type
            let color = '#007bff';
            let iconClass = 'fa-info-circle';

            switch (type) {
                case 'success': color = '#28a745'; iconClass = 'fa-check-circle'; break;
                case 'warning': color = '#ffc107'; iconClass = 'fa-exclamation-triangle'; break;
                case 'danger': color = '#dc3545'; iconClass = 'fa-exclamation-circle'; break;
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
        }

        // Vérification côté client que les mots de passe correspondent
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('admin_password').value;
            const confirm = document.getElementById('admin_password_confirm').value;
            
            if (password !== confirm) {
                e.preventDefault();
                showAppModal( "<?php echo __('create_password.passwords_dont_match'); ?>" );
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                showAppModal( "<?php echo __('create_password.password_too_short'); ?>" );
                return false;
            }
        });
    </script>
</body>
</html>

