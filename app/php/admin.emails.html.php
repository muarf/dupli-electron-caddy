<div class="section">
  <div class="container">
    <div class="row">
      <div class="col-md-10 col-md-offset-1">
        <h1 class="text-center">Gestion des Emails</h1>
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
        
        <!-- Section Paramètres -->
        <div class="row">
          <div class="col-md-12">
            <div class="panel panel-info">
              <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-cog"></i> Paramètres d'affichage</h3>
              </div>
              <div class="panel-body">
                <form method="post">
                  <div class="form-group">
                    <div class="checkbox">
                      <label>
                        <input type="checkbox" name="show_mailing_list" value="1" <?= (isset($show_mailing_list) && $show_mailing_list == '1') ? 'checked' : '' ?>>
                        Afficher la liste de diffusion sur la page d'accueil
                      </label>
                    </div>
                  </div>
                  
                  <button type="submit" name="update_site_settings" class="btn btn-info">
                    <i class="fa fa-save"></i> Sauvegarder les paramètres
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Section Liste des emails -->
        <div class="row">
          <div class="col-md-12">
            <div class="panel panel-primary">
              <div class="panel-heading">
                <h3 class="panel-title">
                  <i class="fa fa-envelope"></i> Liste des emails (<?= count($emails) ?>)
                </h3>
              </div>
              <div class="panel-body">
                <?php if(empty($emails)): ?>
                  <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i> Aucun email enregistré pour le moment.
                  </div>
                <?php else: ?>
                  <div class="table-responsive">
                    <table class="table table-striped table-hover">
                      <thead>
                        <tr>
                          <th>#</th>
                          <th>Adresse email</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach($emails as $index => $email): ?>
                          <tr>
                            <td><?= $index + 1 ?></td>
                            <td>
                              <i class="fa fa-envelope"></i> 
                              <a href="mailto:<?= htmlspecialchars($email) ?>"><?= htmlspecialchars($email) ?></a>
                            </td>
                            <td>
                              <form method="post" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet email ?');">
                                <input type="hidden" name="delmail" value="<?= htmlspecialchars($email) ?>">
                                <button type="submit" class="btn btn-danger btn-sm">
                                  <i class="fa fa-trash"></i> Supprimer
                                </button>
                              </form>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                  
                  <!-- Actions en masse -->
                  <div class="row">
                    <div class="col-md-12">
                      <div class="alert alert-warning">
                        <strong>Attention :</strong> 
                        <form method="post" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer TOUS les emails ? Cette action est irréversible !');">
                          <button type="submit" name="delete_all_emails" class="btn btn-warning btn-sm">
                            <i class="fa fa-trash"></i> Supprimer tous les emails
                          </button>
                        </form>
                      </div>
                    </div>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Section Navigation -->
        <div class="row">
          <div class="col-md-12">
            <div class="panel panel-default">
              <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-arrow-left"></i> Navigation</h3>
              </div>
              <div class="panel-body">
                <a href="?admin" class="btn btn-primary">
                  <i class="fa fa-arrow-left"></i> Retour à l'administration
                </a>
                <a href="?" class="btn btn-info" target="_blank">
                  <i class="fa fa-external-link"></i> Voir la page d'accueil
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
