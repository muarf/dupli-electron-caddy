<style>
    .login-container {
        max-width: 400px;
        margin: 50px auto;
        background: white;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 0 20px rgba(0,0,0,0.1);
    }
    .login-header {
        text-align: center;
        margin-bottom: 30px;
    }
    .login-header h2 {
        color: #333;
        margin-bottom: 10px;
    }
    .form-group {
        margin-bottom: 20px;
    }
    .btn-login {
        width: 100%;
        background-color: #007bff;
        border-color: #007bff;
        padding: 12px;
        font-size: 16px;
    }
    .btn-login:hover {
        background-color: #0056b3;
        border-color: #0056b3;
    }
</style>

<div class="login-container">
    <div class="login-header">
        <h2>🔐 Administration</h2>
        <p class="text-muted">Connexion requise</p>
    </div>
    
    <?php if (!empty($login_error)): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($login_error) ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="?admin">
        <div class="form-group">
            <label for="password">Mot de passe :</label>
            <input type="password" class="form-control" id="password" name="password" required autofocus>
        </div>
        
        <button type="submit" class="btn btn-primary btn-login">
            <i class="fa fa-sign-in"></i> Se connecter
        </button>
    </form>
    
    <div class="text-center mt-3">
        <a href="?accueil" class="text-muted">
            <i class="fa fa-arrow-left"></i> Retour à l'accueil
        </a>
    </div>
</div>