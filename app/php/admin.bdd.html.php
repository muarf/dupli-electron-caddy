<div class="section">
  <div class="container">
    <div class="row">
      <div class="col-md-10 col-md-offset-1">
        <h1 class="text-center">Gestion des fichiers de base de données SQLite</h1>
        <hr>
        


        
        <!-- Messages de succès/erreur -->
        <?php if(isset($GLOBALS['model_variables']['db_created'])): ?>
          <div class="alert alert-success">
            <i class="fa fa-check"></i> <?= htmlspecialchars($GLOBALS['model_variables']['db_created']) ?>
          </div>
        <?php endif; ?>
        
            <?php if(isset($GLOBALS['model_variables']['db_switched'])): ?>
      <div class="alert alert-info">
        <i class="fa fa-info"></i> <?= htmlspecialchars($GLOBALS['model_variables']['db_switched']) ?>
      </div>
    <?php endif; ?>
        
        <?php if(isset($GLOBALS['model_variables']['db_deleted'])): ?>
          <div class="alert alert-warning">
            <i class="fa fa-trash"></i> <?= htmlspecialchars($GLOBALS['model_variables']['db_deleted']) ?>
          </div>
        <?php endif; ?>
        
        <?php if(isset($GLOBALS['model_variables']['db_renamed'])): ?>
          <div class="alert alert-success">
            <i class="fa fa-edit"></i> <?= htmlspecialchars($GLOBALS['model_variables']['db_renamed']) ?>
          </div>
        <?php endif; ?>
        
        <?php if(isset($GLOBALS['model_variables']['db_emptied'])): ?>
          <div class="alert alert-warning">
            <i class="fa fa-trash"></i> <?= htmlspecialchars($GLOBALS['model_variables']['db_emptied']) ?>
          </div>
        <?php endif; ?>
        
        <?php if(isset($GLOBALS['model_variables']['db_reset'])): ?>
          <div class="alert alert-danger">
            <i class="fa fa-refresh"></i> <?= htmlspecialchars($GLOBALS['model_variables']['db_reset']) ?>
          </div>
        <?php endif; ?>
        
        <?php if(isset($GLOBALS['model_variables']['db_error'])): ?>
          <div class="alert alert-danger">
            <i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($GLOBALS['model_variables']['db_error']) ?>
          </div>
        <?php endif; ?>
        
        <?php if(isset($GLOBALS['model_variables']['db_backup'])): ?>
          <div class="alert alert-success">
            <i class="fa fa-download"></i> <?= htmlspecialchars($GLOBALS['model_variables']['db_backup']) ?>
          </div>
        <?php endif; ?>
        
        <?php if(isset($GLOBALS['model_variables']['db_restored'])): ?>
          <div class="alert alert-success">
            <i class="fa fa-upload"></i> <?= htmlspecialchars($GLOBALS['model_variables']['db_restored']) ?>
          </div>
        <?php endif; ?>
        
        <?php if(isset($GLOBALS['model_variables']['db_backup_deleted'])): ?>
          <div class="alert alert-success">
            <i class="fa fa-trash"></i> <?= htmlspecialchars($GLOBALS['model_variables']['db_backup_deleted']) ?>
          </div>
        <?php endif; ?>
        
        <?php if(isset($GLOBALS['model_variables']['backup_created'])): ?>
          <div class="alert alert-success">
            <i class="fa fa-download"></i> <?= htmlspecialchars($GLOBALS['model_variables']['backup_created']) ?>
          </div>
        <?php endif; ?>
        
        <?php if(isset($GLOBALS['model_variables']['backup_error'])): ?>
          <div class="alert alert-danger">
            <i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($GLOBALS['model_variables']['backup_error']) ?>
          </div>
        <?php endif; ?>
        
        <?php if(isset($GLOBALS['model_variables']['restore_error'])): ?>
          <div class="alert alert-danger">
            <i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($GLOBALS['model_variables']['restore_error']) ?>
          </div>
        <?php endif; ?>
        
        <?php if(isset($GLOBALS['model_variables']['backup_delete_error'])): ?>
          <div class="alert alert-danger">
            <i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($GLOBALS['model_variables']['backup_delete_error']) ?>
          </div>
        <?php endif; ?>
        
        <?php if(isset($GLOBALS['model_variables']['backup_uploaded'])): ?>
          <div class="alert alert-success">
            <i class="fa fa-upload"></i> <?= htmlspecialchars($GLOBALS['model_variables']['backup_uploaded']) ?>
          </div>
        <?php endif; ?>
        
        <?php if(isset($GLOBALS['model_variables']['upload_error'])): ?>
          <div class="alert alert-danger">
            <i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($GLOBALS['model_variables']['upload_error']) ?>
          </div>
        <?php endif; ?>
        
        <!-- Base de données actuelle -->
        <div class="panel panel-info">
          <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-database"></i> Base de données actuelle</h3>
          </div>
          <div class="panel-body">
            <div class="alert alert-info">
              <strong>Base actuelle :</strong> <?= htmlspecialchars($GLOBALS['model_variables']['current_db'] ?? 'duplinew') ?>
            </div>
          </div>
        </div>
        
        <!-- Bases de données disponibles -->
        <div class="panel panel-primary">
          <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-list"></i> Fichiers de base de données SQLite disponibles</h3>
          </div>
          <div class="panel-body">
            <?php if(isset($GLOBALS['model_variables']['databases']) && count($GLOBALS['model_variables']['databases']) > 0): ?>
              <div class="table-responsive">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th>Nom du fichier</th>
                      <th>Type</th>
                      <th>Taille</th>
                      <th>Dernière modification</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach($GLOBALS['model_variables']['databases'] as $db): ?>
                      <tr>
                        <td>
                          <strong><?= htmlspecialchars($db['name']) ?></strong>
                          <?php if($db['name'] == ($GLOBALS['model_variables']['current_db'] ?? 'duplinew')): ?>
                            <span class="label label-success">Actuelle</span>
                          <?php endif; ?>
                          <br><small class="text-muted"><?= htmlspecialchars($db['file'] ?? $db['name'] . '.sqlite') ?></small>
                        </td>
                        <td>
                          <?php if($db['type'] == 'production'): ?>
                            <span class="label label-danger">Production</span>
                          <?php elseif($db['type'] == 'dev'): ?>
                            <span class="label label-warning">Développement</span>
                          <?php elseif($db['type'] == 'test'): ?>
                            <span class="label label-info">Test</span>
                          <?php elseif($db['type'] == 'staging'): ?>
                            <span class="label label-primary">Staging</span>
                          <?php else: ?>
                            <span class="label label-default"><?= htmlspecialchars($db['type']) ?></span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php if(isset($db['size'])): ?>
                            <?= number_format($db['size'] / 1024, 1) ?> KB
                          <?php else: ?>
                            <span class="text-muted">N/A</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php if(isset($db['modified'])): ?>
                            <?= date('d/m/Y H:i', $db['modified']) ?>
                          <?php else: ?>
                            <span class="text-muted">N/A</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <form method="post" style="display: inline;">
                            <button type="submit" name="switch_db" value="<?= $db['name'] ?>" class="btn btn-primary btn-xs">
                              <i class="fa fa-exchange"></i> Utiliser
                            </button>
                          </form>
                          <button type="button" class="btn btn-warning btn-xs" onclick="showRenameForm('<?= htmlspecialchars($db['name']) ?>')">
                            <i class="fa fa-edit"></i> Renommer
                          </button>
                          <?php if($db['name'] != ($GLOBALS['model_variables']['current_db'] ?? 'duplinew')): ?>
                            <form method="post" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette base de données ?');">
                              <button type="submit" name="delete_db" value="<?= $db['name'] ?>" class="btn btn-danger btn-xs">
                                <i class="fa fa-trash"></i> Supprimer
                              </button>
                            </form>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="alert alert-warning">
                <i class="fa fa-exclamation-triangle"></i> Aucune base de données trouvée.
              </div>
            <?php endif; ?>
          </div>
        </div>
        
        <!-- Base de données actuelle -->
        <div class="panel panel-primary">
          <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-database"></i> Base de données actuelle</h3>
          </div>
          <div class="panel-body">
            <p class="lead">
              <strong>Base actuelle :</strong> 
              <span class="label label-primary"><?= htmlspecialchars($GLOBALS['model_variables']['current_db'] ?? 'duplinew') ?></span>
              <?php 
              $current_db = $GLOBALS['model_variables']['current_db'] ?? 'duplinew';
              if($current_db === 'duplinew'): ?>
                <span class="label label-success">Production</span>
              <?php elseif(strpos($current_db, '_dev') !== false): ?>
                <span class="label label-warning">Développement</span>
              <?php elseif(strpos($current_db, '_test') !== false): ?>
                <span class="label label-info">Test</span>
              <?php endif; ?>
            </p>
            <p class="text-muted">
              <i class="fa fa-info-circle"></i> 
              Cette base de données est actuellement utilisée par l'application.
              <?php if(isset($_SESSION['active_database']) && $_SESSION['active_database'] !== $current_db): ?>
                <br><i class="fa fa-exclamation-triangle text-warning"></i> 
                <strong>Attention :</strong> La session indique une base différente. Rechargez la page pour synchroniser.
              <?php endif; ?>
            </p>
          </div>
        </div>
        
        <!-- Créer une nouvelle base de données SQLite -->
        <div class="panel panel-success">
          <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-plus"></i> Créer un nouveau fichier de base de données SQLite</h3>
          </div>
          <div class="panel-body">
            <div class="alert alert-info">
              <i class="fa fa-info-circle"></i> <strong>Note :</strong> Cette fonctionnalité crée un nouveau fichier SQLite avec la structure complète des tables.
              L'extension .sqlite sera ajoutée automatiquement.
            </div>
            
            <form method="post" class="form-horizontal" action="?admin&bdd">
              <div class="form-group">
                <label class="col-md-3 control-label" for="db_name">Nom du fichier :</label>
                <div class="col-md-9">
                  <input type="text" class="form-control" name="db_name" id="db_name" placeholder="ex: dupli_montreuil, fond_de_la_casse" required>
                  <small class="text-muted">Le nom doit commencer par une lettre et ne contenir que des lettres, chiffres et underscores. L'extension .sqlite sera ajoutée automatiquement.</small>
                </div>
              </div>
              
              <div class="form-group">
                <label class="col-md-3 control-label" for="db_type">Type :</label>
                <div class="col-md-9">
                  <select class="form-control" name="db_type" id="db_type" required>
                    <option value="">Choisir un type</option>
                    <option value="dev">Développement</option>
                    <option value="test">Test</option>
                    <option value="staging">Staging</option>
                    <option value="production">Production</option>
                  </select>
                </div>
              </div>
              
              <div class="form-group">
                <label class="col-md-3 control-label" for="db_template">Template :</label>
                <div class="col-md-9">
                  <select class="form-control" name="db_template" id="db_template">
                    <option value="">Base vide</option>
                    <option value="structure_complete">Structure complète (tables sans données)</option>
                    <option value="duplinew">Copier depuis duplinew.sqlite (production)</option>
                    <option value="duplinew_dev">Copier depuis duplinew_dev.sqlite (développement)</option>
                  </select>
                  <small class="text-muted">Choisir un fichier existant pour copier la structure et les données, ou "Structure complète" pour créer toutes les tables nécessaires</small>
                </div>
              </div>
              
              <div class="form-group">
                <div class="col-md-9 col-md-offset-3">
                  <input type="hidden" name="create_db" value="1">
                  <button type="submit" class="btn btn-success">
                    <i class="fa fa-plus"></i> Créer le fichier SQLite
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>
        
        <!-- Actions sur le fichier SQLite actuel -->
        <div class="panel panel-warning">
          <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-cogs"></i> Actions sur le fichier SQLite actuel</h3>
          </div>
          <div class="panel-body">
            <div class="row">
              <div class="col-md-4">
                <form method="post">
                  <div class="form-group">
                    <input type="text" class="form-control" name="backup_name" placeholder="Nom de la sauvegarde (optionnel)" style="margin-bottom: 10px;">
                    <button type="submit" name="backup_db" class="btn btn-success btn-block">
                      <i class="fa fa-download"></i> Créer une sauvegarde
                    </button>
                  </div>
                  <small class="text-muted">Sauvegarde complète du fichier SQLite actuel</small>
                </form>
              </div>
              <div class="col-md-4">
                <form method="post" onsubmit="return confirm('Êtes-vous sûr de vouloir vider cette base de données ?');">
                  <button type="submit" name="empty_db" class="btn btn-warning btn-block">
                    <i class="fa fa-trash"></i> Vider la base de données
                  </button>
                  <small class="text-muted">Supprime toutes les données mais garde la structure</small>
                </form>
              </div>
              <div class="col-md-4">
                <form method="post" onsubmit="return confirm('Êtes-vous sûr de vouloir réinitialiser cette base de données ?');">
                  <button type="submit" name="reset_db" class="btn btn-danger btn-block">
                    <i class="fa fa-refresh"></i> Réinitialiser complètement
                  </button>
                  <small class="text-muted">Supprime tout et recrée la structure</small>
                </form>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Gestion des sauvegardes existantes -->
        <div class="panel panel-info">
          <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-files-o"></i> Sauvegardes disponibles</h3>
          </div>
          <div class="panel-body">
            
            <!-- Formulaire d'upload de sauvegarde -->
            <div class="row" style="margin-bottom: 20px;">
              <div class="col-md-12">
                <div class="alert alert-info">
                  <i class="fa fa-info-circle"></i> <strong>Charger une sauvegarde :</strong> Vous pouvez uploader un fichier SQLite (.sqlite) pour l'ajouter à la liste des sauvegardes disponibles.
                </div>
                <form method="post" enctype="multipart/form-data" class="form-inline">
                  <div class="form-group">
                    <label for="backup_file" class="sr-only">Fichier de sauvegarde</label>
                    <input type="file" class="form-control" id="backup_file" name="backup_file" accept=".sqlite" required>
                  </div>
                  <button type="submit" class="btn btn-primary">
                    <i class="fa fa-upload"></i> Charger la sauvegarde
                  </button>
                </form>
                <small class="text-muted">Format accepté : .sqlite (taille maximum : 50MB)</small>
              </div>
            </div>
            
            <hr>
            <?php
            if(!empty($backups)): ?>
              <div class="table-responsive">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th>Nom du fichier</th>
                      <th>Taille</th>
                      <th>Date de création</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach($backups as $backup): ?>
                      <tr>
                        <td><?= htmlspecialchars($backup['filename']) ?></td>
                        <td><?= htmlspecialchars($backup['size']) ?></td>
                        <td><?= htmlspecialchars($backup['date']) ?></td>
                        <td>
                          <form method="post" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir restaurer cette sauvegarde ? Cela écrasera toutes les données actuelles !');">
                            <input type="hidden" name="backup_file" value="<?= htmlspecialchars($backup['filename']) ?>">
                            <button type="submit" name="restore_db" class="btn btn-warning btn-xs">
                              <i class="fa fa-upload"></i> Restaurer
                            </button>
                          </form>
                          <a href="sauvegarde/<?= urlencode($backup['filename']) ?>" class="btn btn-info btn-xs" download>
                            <i class="fa fa-download"></i> Télécharger
                          </a>
                          <form method="post" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette sauvegarde ? Cette action est irréversible !');">
                            <input type="hidden" name="backup_file" value="<?= htmlspecialchars($backup['filename']) ?>">
                            <button type="submit" name="delete_backup" class="btn btn-danger btn-xs">
                              <i class="fa fa-trash"></i> Supprimer
                            </button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <p class="text-muted">Aucune sauvegarde disponible.</p>
            <?php endif; ?>
          </div>
        </div>
        
        <!-- Bouton retour -->
        <div class="row">
          <div class="col-md-12">
            <a href="?admin" class="btn btn-default btn-block">
              <i class="fa fa-arrow-left"></i> Retour à l'administration
            </a>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Modal de renommage -->
    <div class="modal fade" id="renameModal" tabindex="-1" role="dialog">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h4 class="modal-title">Renommer la base de données</h4>
          </div>
          <form method="post">
            <div class="modal-body">
              <div class="form-group">
                <label for="old_db_name">Nom actuel :</label>
                <input type="text" class="form-control" id="old_db_name" name="old_db_name" readonly>
              </div>
              <div class="form-group">
                <label for="new_db_name">Nouveau nom :</label>
                <input type="text" class="form-control" id="new_db_name" name="new_db_name" placeholder="ex: dupli_montreuil" required>
                <small class="text-muted">Le nom doit commencer par une lettre et ne contenir que des lettres, chiffres et underscores</small>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-default" data-dismiss="modal">Annuler</button>
              <button type="submit" name="rename_db" class="btn btn-warning">Renommer</button>
            </div>
          </form>
        </div>
      </div>
    </div>
    
    <!-- JavaScript pour le renommage -->
    <script>
      function showRenameForm(dbName) {
        document.getElementById('old_db_name').value = dbName;
        document.getElementById('new_db_name').value = '';
        $('#renameModal').modal('show');
      }
    </script>
  </div>
</div>
