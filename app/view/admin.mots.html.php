<div class="section">
  <div class="container">
    <div class="row">
      <div class="col-md-10 col-md-offset-1">
        <h1 class="text-center"><?php _e('admin_mots.title'); ?></h1>
        <hr>
        
        <!-- Messages de statut -->
        <?php if(isset($message)): ?>
          <div class="alert alert-<?= $message['type'] ?> alert-dismissible" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
            <?= $message['text'] ?>
          </div>
        <?php endif; ?>
        
        <!-- Section Changement de mot de passe -->
        <div class="row">
          <div class="col-md-8">
            <div class="panel panel-warning">
              <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-key"></i> <?php _e('admin_mots.change_password'); ?></h3>
              </div>
              <div class="panel-body">
                <form method="post" id="password-form">
                  <div class="form-group">
                    <label for="current_password"><?php _e('admin_mots.current_password'); ?> :</label>
                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                  </div>
                  
                  <div class="form-group">
                    <label for="new_password"><?php _e('admin_mots.new_password'); ?> :</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                    <small class="text-muted"><?php _e('admin_mots.min_length'); ?></small>
                  </div>
                  
                  <div class="form-group">
                    <label for="confirm_password"><?php _e('admin_mots.confirm_password'); ?> :</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                  </div>
                  
                  <button type="submit" name="change_password" class="btn btn-warning btn-block">
                    <i class="fa fa-save"></i> <?php _e('admin_mots.change_password_btn'); ?>
                  </button>
                </form>
              </div>
            </div>
          </div>
          
          <div class="col-md-4">
            <div class="panel panel-info">
              <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-info-circle"></i> <?php _e('admin_mots.information'); ?></h3>
              </div>
              <div class="panel-body">
                <p><strong><?php _e('admin_mots.security'); ?> :</strong></p>
                <ul>
                  <li>Le mot de passe est stocké de manière sécurisée</li>
                  <li>Utilisation de hachage bcrypt</li>
                  <li>Anciens mots de passe supprimés</li>
                </ul>
                
                <p><strong><?php _e('admin_mots.recommendations'); ?> :</strong></p>
                <ul>
                  <li>Utilisez au moins 8 caractères</li>
                  <li>Mélangez lettres, chiffres et symboles</li>
                  <li>Évitez les mots de passe courants</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
        
        
        <!-- Section Navigation -->
        <div class="row">
          <div class="col-md-12">
            <div class="panel panel-default">
              <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-arrow-left"></i> <?php _e('admin_mots.navigation'); ?></h3>
              </div>
              <div class="panel-body">
                <a href="?admin" class="btn btn-primary">
                  <i class="fa fa-arrow-left"></i> <?php _e('admin_mots.back_to_admin'); ?>
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  
  <script>
  // Validation du formulaire
  document.getElementById('password-form').addEventListener('submit', function(e) {
    var newPassword = document.getElementById('new_password').value;
    var confirmPassword = document.getElementById('confirm_password').value;
    
    if (newPassword !== confirmPassword) {
      e.preventDefault();
      alert('<?php _e('admin_mots.passwords_dont_match'); ?>');
      return false;
    }
    
    if (newPassword.length < 6) {
      e.preventDefault();
      alert('<?php _e('admin_mots.password_too_short'); ?>');
      return false;
    }
  });
  
  // Vérification en temps réel
  document.getElementById('confirm_password').addEventListener('input', function() {
    var newPassword = document.getElementById('new_password').value;
    var confirmPassword = this.value;
    
    if (confirmPassword && newPassword !== confirmPassword) {
      this.setCustomValidity('<?php _e('admin_mots.passwords_dont_match_realtime'); ?>');
    } else {
      this.setCustomValidity('');
    }
  });
  </script>
</div>
