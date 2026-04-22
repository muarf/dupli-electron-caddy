            <h1 class="text-center"><?php _e('accueil.welcome', [], true); ?></h1>
            <hr>

            <!-- Admin Warning Container -->
            <div id="admin-warning-container"></div>
            <script src="js/admin-warning.js"></script>

            <?php if (isset($health_check) && $health_check['critical_missing']): ?>
                <div class="alert alert-danger" style="margin-top: 20px; border-radius: 10px; box-shadow: 0 4px 10px rgba(220,53,69,0.2);">
                    <h4 style="margin-top: 0;"><i class="fa fa-exclamation-triangle"></i> <?php _e('accueil.health.critical_error_title', [], true); ?></h4>
                    <p><?php _e('accueil.health.critical_error_desc', [], true); ?></p>

                    <?php if (isset($global_install_command)): ?>
                        <div style="background: rgba(0,0,0,0.05); padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px dashed rgba(220,53,69,0.5);">
                            <p style="margin-bottom: 8px; font-weight: bold;"><i class="fa fa-terminal"></i> Commande d'installation groupée :</p>
                            <div class="input-group">
                                <input type="text" class="form-control" value="<?= htmlspecialchars($global_install_command) ?>" readonly id="global-install-cmd">
                                <span class="input-group-btn">
                                    <button class="btn btn-default" type="button" onclick="navigator.clipboard.writeText('<?= addslashes($global_install_command) ?>'); alert('Copié !')">
                                        <i class="fa fa-copy"></i>
                                    </button>
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <ul style="margin-bottom: 10px;">
                        <?php foreach ($health_check['dependencies'] as $dep): ?>
                            <?php if (!$dep['status'] && $dep['critical']): ?>
                                <li><strong><?= $dep['name'] ?></strong>: <?= $dep['help'] ?></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php foreach ($health_check['php_extensions'] as $ext): ?>
                            <?php if (!$ext['status'] && $ext['critical']): ?>
                                <li> Extension PHP <strong><?= $ext['name'] ?></strong> manquante. : <code><?= $ext['help'] ?></code></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php foreach ($health_check['permissions'] as $perm): ?>
                            <?php if (!$perm['status'] && $perm['critical']): ?>
                                <li> 
                                    Permission manquante : <strong><?= $perm['name'] ?></strong> (<?= $perm['path'] ?>) : 
                                    <?= isset($perm['error']) ? $perm['error'] : "n'est pas accessible en écriture." ?>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <script>
                // Afficher l'avertissement admin (dismissible sur la page d'accueil)
                document.addEventListener('DOMContentLoaded', function() {
                    if (window.AdminWarning) {
                        window.AdminWarning.show(true); // true = peut être fermé
                    }
                });
            </script>

          </div>
        </div>
      </div>
    </div>
    <div class="section">
      <div class="container">
        <div class="row">
          <div class="col-md-6 text-center">
            <a href="?auto_tirage" style="text-decoration:none">
              <div class="well" style="padding: 40px; margin: 20px 0; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border: 2px solid #28a745; border-radius: 15px; box-shadow: 0 4px 15px rgba(40,167,69,0.1); transition: all 0.3s ease;">
                <div style="font-size: 48px; color: #28a745; margin-bottom: 20px;">
                  <i class="fa fa-magic"></i>
                </div>
                <h2 style="color: #28a745; margin-bottom: 15px; font-weight: bold;"><?php _e('accueil.auto_tirage', [], true); ?></h2>
                <p style="font-size: 16px; color: #6c757d; margin-bottom: 0;"><?php _e('accueil.auto_tirage_desc', [], true); ?></p>
              </div>
            </a>
          </div>
          <div class="col-md-6 text-center">
            <a href="?tirage_multimachines" style="text-decoration:none">
              <div class="well" style="padding: 40px; margin: 20px 0; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border: 2px solid #007bff; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,123,255,0.1); transition: all 0.3s ease;">
                <div style="font-size: 48px; color: #007bff; margin-bottom: 20px;">
                  <i class="fa fa-print"></i>
                </div>
                <h2 style="color: #007bff; margin-bottom: 15px; font-weight: bold;"><?php _e('accueil.multi_machine_print', [], true); ?></h2>
                <p style="font-size: 16px; color: #6c757d; margin-bottom: 0;"><?php _e('accueil.multi_machine_print_desc', [], true); ?></p>
              </div>
            </a>
          </div>
        </div>
      </div>
    </div>
    <div class="section">
      <div class="container">
        <div class="row">
          <div class="col-md-12" id="info">
            <h1 class="text-center"><?php _e('accueil.useful_info', [], true); ?></h1>
            <hr>
            <?php 
            if (isset($news) && is_array($news)) {
                for ($i = 0;$i < count($news);$i++) 
                {?>
              <div class="well">
                <h3><?= $news[$i]['titre'] ?></h3>
                <div class="text-muted text-right"><small><?= $news[$i]['time'] ?></small></div>
                <div class="news-content"><?= html_entity_decode($news[$i]['news']) ?></div>
              </div>
            <?php 
                }
            }  ?>
          </div>
        </div>
      </div>
    </div>

    <?php if(isset($show_mailing_list) && $show_mailing_list == '1'): ?>
    <div class="section">
      <div class="container">
        <div class="row">
          <div class="col-md-12" id="diffusion">
            <h1 class="text-center"><?php _e('accueil.mailing_list'); ?></h1>
          </div>
        </div>
        <div class="row">
          <div class="col-md-offset-3 col-md-6">
              <?php if(isset($_POST['email'])){ echo $email;}else {?>
            <form role="form" action="#diffusion"method="post">
                
              <div class="form-group">
                <div class="input-group">
                    
                  <input type="email" name = "email" class="form-control" placeholder="<?php echo __('accueil.email_placeholder', [], false); ?>">
                  <span class="input-group-btn">
                    <input class="btn btn-success" type="submit" value="<?php echo __('accueil.submit', [], false); ?>">
                  </span>
                </div>
              </div>
            </form><?php } ?>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

