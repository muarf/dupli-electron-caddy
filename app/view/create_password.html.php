<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Création du mot de passe administrateur - Dupli</title>
    <link href="<?= $base_path ?>css/bootstrap.css" rel="stylesheet" type="text/css">
    <link href="<?= $base_path ?>css/font-awesome.min.css" rel="stylesheet" type="text/css">
    <script src="<?= $base_path ?>js/jquery.min.js"></script>
    <script src="<?= $base_path ?>js/bootstrap.min.js"></script>
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
        const CONFIG = {
            translations: {
                info: <?= json_encode(__('common.info')) ?>,
                cancel: <?= json_encode(__('common.cancel')) ?>,
                passwords_dont_match: <?= json_encode(__('create_password.passwords_dont_match')) ?>,
                password_too_short: <?= json_encode(__('create_password.password_too_short')) ?>
            }
        };
    </script>
    <script src="<?= $base_path ?>js/create-password.js" defer></script>

</body>
</html>

