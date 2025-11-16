<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Création du mot de passe administrateur - Dupli</title>
    <link href="css/bootstrap.css" rel="stylesheet" type="text/css">
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
        <div class="password-header">
            <h1>🔐 Création du mot de passe administrateur</h1>
            <p>Veuillez définir un mot de passe pour accéder à l'administration</p>
        </div>

        <?php if (isset($errors) && !empty($errors)): ?>
            <div class="alert alert-danger">
                <h5><i class="fa fa-exclamation-triangle"></i> Erreurs détectées :</h5>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="info-box">
            <p><i class="fa fa-info-circle"></i> <strong>Important :</strong> Ce mot de passe vous permettra d'accéder à toutes les fonctionnalités d'administration de l'application.</p>
        </div>

        <form method="POST" action="?create_password">
            <div class="form-group">
                <label for="admin_password">
                    <i class="fa fa-lock"></i> Mot de passe administrateur
                </label>
                <input 
                    type="password" 
                    id="admin_password" 
                    name="admin_password" 
                    class="form-control" 
                    required 
                    minlength="6"
                    placeholder="Minimum 6 caractères"
                    autocomplete="new-password"
                >
            </div>

            <div class="form-group">
                <label for="admin_password_confirm">
                    <i class="fa fa-lock"></i> Confirmer le mot de passe
                </label>
                <input 
                    type="password" 
                    id="admin_password_confirm" 
                    name="admin_password_confirm" 
                    class="form-control" 
                    required 
                    minlength="6"
                    placeholder="Répétez le mot de passe"
                    autocomplete="new-password"
                >
            </div>

            <button type="submit" class="btn-submit">
                <i class="fa fa-check"></i> Créer le mot de passe
            </button>
        </form>
    </div>

    <script>
        // Vérification côté client que les mots de passe correspondent
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('admin_password').value;
            const confirm = document.getElementById('admin_password_confirm').value;
            
            if (password !== confirm) {
                e.preventDefault();
                alert('Les mots de passe ne correspondent pas.');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Le mot de passe doit contenir au moins 6 caractères.');
                return false;
            }
        });
    </script>
</body>
</html>

