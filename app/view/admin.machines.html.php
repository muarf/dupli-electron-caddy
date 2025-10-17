<div class="section">
  <div class="container">
    <div class="row">
      <div class="col-md-10 col-md-offset-1">
        <h1 class="text-center"><?php _e('admin.machine_management'); ?></h1>
        <hr>
        
        <!-- Messages de succès/erreur -->
        <?php if(isset($array['machine_created'])): ?>
          <div class="alert alert-success">
            <i class="fa fa-check"></i> <?= htmlspecialchars($array['machine_created']) ?>
          </div>
        <?php endif; ?>
        
        <?php if(isset($array['machine_error'])): ?>
          <div class="alert alert-danger">
            <i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($array['machine_error']) ?>
          </div>
        <?php endif; ?>
        
        <?php 
        // Solution temporaire : définir les machines directement dans le template
        if (!isset($array['machines'])) {
            try {
                $conf = [
                    'dsn' => 'mysql:dbname=d_montreuil_4;host=127.0.0.1',
                    'login' => 'dupli_user',
                    'pass' => 'mot_de_passe_solide'
                ];
                $machineManager = new AdminMachineManager($conf);
                $array['machines'] = $machineManager->getMachines();
            } catch (Exception $e) {
                $array['machines'] = [];
            }
        }
        ?>
        
        <!-- Duplicopieurs -->
        <div class="panel panel-success">
          <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-print"></i> Duplicopieurs installés</h3>
          </div>
          <div class="panel-body">
            <?php if(isset($array['machines']) && !empty($array['machines'])): ?>
              <?php 
              $duplicopieurs = array_filter($array['machines'], function($machine) {
                return $machine['machine_type'] === 'duplicopieur';
              });
              ?>
              
              <?php if(!empty($duplicopieurs)): ?>
                <div class="table-responsive">
                  <table class="table table-striped">
                    <thead>
                      <tr>
                        <th>Nom</th>
                        <th>Type</th>
                        <th>Compteur Master</th>
                        <th>Compteur Passage</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach($duplicopieurs as $machine): ?>
                        <tr>
                          <td><strong><?= htmlspecialchars($machine['name']) ?></strong></td>
                          <td>
                            <span class="label label-<?= $machine['type'] === 'dupli' ? 'warning' : 'info' ?>">
                              <?= strtoupper($machine['type']) ?>
                            </span>
                          </td>
                          <td><?= number_format($machine['master_counter']) ?></td>
                          <td><?= number_format($machine['passage_counter']) ?></td>
                          <td>
                            <a href="?admin&changes&machine=<?= urlencode($machine['name']) ?>" class="btn btn-primary btn-xs">
                              <i class="fa fa-history"></i> Historique
                            </a>
                            <a href="?admin&prix&machine=<?= urlencode($machine['name']) ?>" class="btn btn-info btn-xs">
                              <i class="fa fa-euro"></i> Prix
                            </a>
                            <button type="button" class="btn btn-warning btn-xs edit-tambours" 
                                    data-id="<?= htmlspecialchars($machine['id']) ?>" 
                                    data-name="<?= htmlspecialchars($machine['name']) ?>"
                                    data-tambours="<?= htmlspecialchars($machine['tambours'] ?? '[]') ?>">
                              <i class="fa fa-cog"></i> Tambours
                            </button>
                            <button type="button" class="btn btn-success btn-xs rename-machine" 
                                    data-id="<?= htmlspecialchars($machine['id']) ?>" 
                                    data-type="duplicopieur" 
                                    data-name="<?= htmlspecialchars($machine['name']) ?>">
                              <i class="fa fa-edit"></i> Renommer
                            </button>
                            <button type="button" class="btn btn-danger btn-xs delete-machine" 
                                    data-id="<?= htmlspecialchars($machine['id']) ?>" 
                                    data-type="duplicopieur" 
                                    data-name="<?= htmlspecialchars($machine['name']) ?>">
                              <i class="fa fa-trash"></i> Supprimer
                            </button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <div class="alert alert-info">
                  <i class="fa fa-info-circle"></i> Aucun duplicopieur installé.
                </div>
              <?php endif; ?>
            <?php else: ?>
              <div class="alert alert-info">
                <i class="fa fa-info-circle"></i> Aucune machine trouvée.
              </div>
            <?php endif; ?>
            
            <!-- Formulaire d'ajout de duplicopieur -->
            <hr>
            <h4><i class="fa fa-plus"></i> Ajouter un duplicopieur</h4>
            <form method="post" class="form-horizontal">
              <input type="hidden" name="machine_type" value="duplicopieur" />
              
              <div class="form-group">
                <label class="col-md-3 control-label" for="machine_name">Nom :</label>
                <div class="col-md-9">
                  <input type="text" class="form-control" name="machine_name" id="machine_name" placeholder="ex: Ricoh dx4545" required>
                </div>
              </div>
              
              <div class="form-group">
                <label class="col-md-3 control-label" for="master_counter">Compteur Master :</label>
                <div class="col-md-9">
                  <input type="number" class="form-control" name="master_counter" id="master_counter" placeholder="Ex: 12345" min="0">
                </div>
              </div>
              
              <div class="form-group">
                <label class="col-md-3 control-label" for="passage_counter">Compteur Passage :</label>
                <div class="col-md-9">
                  <input type="number" class="form-control" name="passage_counter" id="passage_counter" placeholder="Ex: 67890" min="0">
                </div>
              </div>
              
              <div class="form-group">
                <label class="col-md-3 control-label" for="prix_master_unite">Prix Master unité (€) :</label>
                <div class="col-md-9">
                  <input type="number" class="form-control" name="prix_master_unite" id="prix_master_unite" placeholder="Ex: 0.40" step="0.01" min="0" value="0.40" required>
                  <span class="help-block">Prix d'un master en euros (utilisé pour les calculs)</span>
                </div>
              </div>
              
              <div class="form-group">
                <label class="col-md-3 control-label" for="prix_master_pack">Prix Master pack (€) :</label>
                <div class="col-md-9">
                  <input type="number" class="form-control" name="prix_master_pack" id="prix_master_pack" placeholder="Ex: 70" step="0.01" min="0" value="70">
                  <span class="help-block">Prix du pack de masters en euros (0 si pas de pack)</span>
                </div>
              </div>
              
              <!-- Section Tambours -->
              <hr>
              <h5><i class="fa fa-cog"></i> Configuration des tambours</h5>
              <div class="form-group">
                <label class="col-md-3 control-label">Tambours :</label>
                <div class="col-md-9">
                  <div id="tambours-container">
                    <!-- Tambour par défaut -->
                    <div class="tambour-item" style="margin-bottom: 10px;">
                      <div class="row">
                        <div class="col-md-4">
                          <input type="text" class="form-control" name="tambours[]" placeholder="ex: tambour_noir" value="tambour_noir" required>
                        </div>
                        <div class="col-md-3">
                          <input type="number" class="form-control" name="prix_tambour_unite[]" placeholder="Prix unité" step="0.001" min="0" value="0.002" required>
                        </div>
                        <div class="col-md-3">
                          <input type="number" class="form-control" name="prix_tambour_pack[]" placeholder="Prix pack" step="0.01" min="0" value="11">
                        </div>
                        <div class="col-md-2">
                          <button type="button" class="btn btn-danger btn-sm remove-tambour" style="display: none;">
                            <i class="fa fa-trash"></i>
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                  <button type="button" class="btn btn-info btn-sm" id="add-tambour">
                    <i class="fa fa-plus"></i> Ajouter un tambour
                  </button>
                  <span class="help-block">Définissez les tambours disponibles pour ce duplicopieur. Chaque tambour peut avoir un nom personnalisé.</span>
                </div>
              </div>
              
              <div class="form-group">
                <div class="col-md-9 col-md-offset-3">
                  <input type="hidden" name="add_machine" value="1">
                  <button type="submit" class="btn btn-success">
                    <i class="fa fa-plus"></i> Ajouter le duplicopieur
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>
        
        <!-- Photocopieurs -->
        <div class="panel panel-info">
          <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-copy"></i> Photocopieurs installés</h3>
          </div>
          <div class="panel-body">
            <?php if(isset($array['machines']) && !empty($array['machines'])): ?>
              <?php 
              $photocopieurs = array_filter($array['machines'], function($machine) {
                return $machine['machine_type'] === 'photocopieur';
              });
              ?>
              
              <?php if(!empty($photocopieurs)): ?>
                <div class="table-responsive">
                  <table class="table table-striped">
                    <thead>
                      <tr>
                        <th>Nom</th>
                        <th>Type</th>
                        <th>Compteur</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach($photocopieurs as $machine): ?>
                        <tr>
                          <td><strong><?= htmlspecialchars($machine['name']) ?></strong></td>
                          <td>
                            <span class="label label-<?= strpos($machine['type'], 'encre') !== false ? 'success' : 'primary' ?>">
                              <?= htmlspecialchars($machine['type']) ?>
                            </span>
                          </td>
                          <td><?= number_format($machine['passage_counter']) ?></td>
                          <td>
                            <a href="?admin&changes&machine=<?= urlencode($machine['name']) ?>" class="btn btn-primary btn-xs">
                              <i class="fa fa-history"></i> Historique
                            </a>
                            <a href="?admin&prix&machine=<?= urlencode($machine['name']) ?>" class="btn btn-info btn-xs">
                              <i class="fa fa-euro"></i> Prix
                            </a>
                            <button type="button" class="btn btn-success btn-xs rename-machine" 
                                    data-id="<?= htmlspecialchars($machine['id']) ?>" 
                                    data-type="photocopieur" 
                                    data-name="<?= htmlspecialchars($machine['name']) ?>">
                              <i class="fa fa-edit"></i> Renommer
                            </button>
                            <button type="button" class="btn btn-danger btn-xs delete-machine" 
                                    data-id="<?= htmlspecialchars($machine['id']) ?>" 
                                    data-type="photocopieur" 
                                    data-name="<?= htmlspecialchars($machine['name']) ?>">
                              <i class="fa fa-trash"></i> Supprimer
                            </button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <div class="alert alert-info">
                  <i class="fa fa-info-circle"></i> Aucun photocopieur installé.
                </div>
              <?php endif; ?>
            <?php else: ?>
              <div class="alert alert-info">
                <i class="fa fa-info-circle"></i> Aucune machine trouvée.
              </div>
            <?php endif; ?>
            
            <!-- Formulaire d'ajout de photocopieur -->
            <hr>
            <h4><i class="fa fa-plus"></i> Ajouter un photocopieur</h4>
            <form method="post" class="form-horizontal">
              <div class="form-group">
                <label class="col-md-3 control-label" for="photocop_type">Type :</label>
                <div class="col-md-9">
                  <select class="form-control" name="machine_type" id="photocop_type" required>
                    <option value="">Choisir un type</option>
                    <option value="photocop_encre">Photocopieur à encre</option>
                    <option value="photocop_toner">Photocopieur à toner</option>
                  </select>
                </div>
              </div>
              
              <div class="form-group">
                <label class="col-md-3 control-label" for="photocop_name">Nom :</label>
                <div class="col-md-9">
                  <input type="text" class="form-control" name="machine_name" id="photocop_name" placeholder="ex: Canon IR2525" required>
                </div>
              </div>
              
              <div class="form-group">
                <label class="col-md-3 control-label" for="photocop_counter">Compteur :</label>
                <div class="col-md-9">
                  <input type="number" class="form-control" name="passage_counter" id="photocop_counter" value="0" min="0">
                </div>
              </div>
              
              <!-- Champs pour photocopieurs à encre -->
              <div id="encre_fields" style="display: none;">
                <hr>
                <h5><i class="fa fa-tint"></i> Configuration des encres</h5>
                
                <!-- Encres couleur -->
                <div class="row">
                  <div class="col-md-6">
                    <h6>Encre Noire :</h6>
                    <div class="form-group">
                      <label class="col-md-4 control-label">Prix unité (€) :</label>
                      <div class="col-md-8">
                        <input type="number" name="noire_unite" class="form-control" value="0.002" step="0.001" min="0">
                        <span class="help-block">Prix par passage</span>
                      </div>
                    </div>
                    <div class="form-group">
                      <label class="col-md-4 control-label">Prix pack (€) :</label>
                      <div class="col-md-8">
                        <input type="number" name="noire_pack" class="form-control" value="140" step="0.01" min="0">
                        <span class="help-block">Prix du pack d'encre</span>
                      </div>
                    </div>
                  </div>
                  
                  <div class="col-md-6">
                    <h6>Encre Bleue :</h6>
                    <div class="form-group">
                      <label class="col-md-4 control-label">Prix unité (€) :</label>
                      <div class="col-md-8">
                        <input type="number" name="bleue_unite" class="form-control" value="0.002" step="0.001" min="0">
                        <span class="help-block">Prix par passage</span>
                      </div>
                    </div>
                    <div class="form-group">
                      <label class="col-md-4 control-label">Prix pack (€) :</label>
                      <div class="col-md-8">
                        <input type="number" name="bleue_pack" class="form-control" value="140" step="0.01" min="0">
                        <span class="help-block">Prix du pack d'encre</span>
                      </div>
                    </div>
                  </div>
                </div>
                
                <div class="row">
                  <div class="col-md-6">
                    <h6>Encre Rouge :</h6>
                    <div class="form-group">
                      <label class="col-md-4 control-label">Prix unité (€) :</label>
                      <div class="col-md-8">
                        <input type="number" name="rouge_unite" class="form-control" value="0.002" step="0.001" min="0">
                        <span class="help-block">Prix par passage</span>
                      </div>
                    </div>
                    <div class="form-group">
                      <label class="col-md-4 control-label">Prix pack (€) :</label>
                      <div class="col-md-8">
                        <input type="number" name="rouge_pack" class="form-control" value="140" step="0.01" min="0">
                        <span class="help-block">Prix du pack d'encre</span>
                      </div>
                    </div>
                  </div>
                  
                  <div class="col-md-6">
                    <h6>Encre Jaune :</h6>
                    <div class="form-group">
                      <label class="col-md-4 control-label">Prix unité (€) :</label>
                      <div class="col-md-8">
                        <input type="number" name="jaune_unite" class="form-control" value="0.002" step="0.001" min="0">
                        <span class="help-block">Prix par passage</span>
                      </div>
                    </div>
                    <div class="form-group">
                      <label class="col-md-4 control-label">Prix pack (€) :</label>
                      <div class="col-md-8">
                        <input type="number" name="jaune_pack" class="form-control" value="140" step="0.01" min="0">
                        <span class="help-block">Prix du pack d'encre</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Champs pour photocopieurs à toner -->
              <div id="toner_fields" style="display: none;">
                <hr>
                <h5><i class="fa fa-tint"></i> Configuration des toners</h5>
                
                <!-- Toners couleur -->
                <div class="row">
                  <div class="col-md-6">
                    <h6>Toner Noir :</h6>
                    <div class="form-group">
                      <label class="col-md-4 control-label">Prix cartouche (€) :</label>
                      <div class="col-md-8">
                        <input type="number" name="toner_noir_prix" class="form-control" value="80" step="0.01" min="0">
                        <span class="help-block">Capacité : 23 000 pages (5% couverture)</span>
                      </div>
                    </div>
                    <div class="form-group">
                      <label class="col-md-4 control-label">Prix par page (€) :</label>
                      <div class="col-md-8">
                        <input type="number" name="toner_noir_prix_copie" class="form-control" value="0.00348" step="0.00001" min="0">
                        <span class="help-block">Calculé : 80€ ÷ 23 000 pages</span>
                      </div>
                    </div>
                  </div>
                  
                  <div class="col-md-6">
                    <h6>Toner Cyan :</h6>
                    <div class="form-group">
                      <label class="col-md-4 control-label">Prix cartouche (€) :</label>
                      <div class="col-md-8">
                        <input type="number" name="toner_cyan_prix" class="form-control" value="80" step="0.01" min="0">
                        <span class="help-block">Capacité : 18 000 pages (5% couverture)</span>
                      </div>
                    </div>
                    <div class="form-group">
                      <label class="col-md-4 control-label">Prix par page (€) :</label>
                      <div class="col-md-8">
                        <input type="number" name="toner_cyan_prix_copie" class="form-control" value="0.00444" step="0.00001" min="0">
                        <span class="help-block">Calculé : 80€ ÷ 18 000 pages</span>
                      </div>
                    </div>
                  </div>
                </div>
                
                <div class="row">
                  <div class="col-md-6">
                    <h6>Toner Magenta :</h6>
                    <div class="form-group">
                      <label class="col-md-4 control-label">Prix cartouche (€) :</label>
                      <div class="col-md-8">
                        <input type="number" name="toner_magenta_prix" class="form-control" value="80" step="0.01" min="0">
                        <span class="help-block">Capacité : 18 000 pages (5% couverture)</span>
                      </div>
                    </div>
                    <div class="form-group">
                      <label class="col-md-4 control-label">Prix par page (€) :</label>
                      <div class="col-md-8">
                        <input type="number" name="toner_magenta_prix_copie" class="form-control" value="0.00444" step="0.00001" min="0">
                        <span class="help-block">Calculé : 80€ ÷ 18 000 pages</span>
                      </div>
                    </div>
                  </div>
                  
                  <div class="col-md-6">
                    <h6>Toner Jaune :</h6>
                    <div class="form-group">
                      <label class="col-md-4 control-label">Prix cartouche (€) :</label>
                      <div class="col-md-8">
                        <input type="number" name="toner_jaune_prix" class="form-control" value="80" step="0.01" min="0">
                        <span class="help-block">Capacité : 18 000 pages (5% couverture)</span>
                      </div>
                    </div>
                    <div class="form-group">
                      <label class="col-md-4 control-label">Prix par page (€) :</label>
                      <div class="col-md-8">
                        <input type="number" name="toner_jaune_prix_copie" class="form-control" value="0.00444" step="0.00001" min="0">
                        <span class="help-block">Calculé : 80€ ÷ 18 000 pages</span>
                      </div>
                    </div>
                  </div>
                </div>
                
                <!-- Tambour et Dev -->
                <hr>
                <h5><i class="fa fa-cog"></i> Tambour et unité de développement</h5>
                <div class="row">
                  <div class="col-md-6">
                    <h6>Tambour :</h6>
                    <div class="form-group">
                      <label class="col-md-4 control-label">Prix (€) :</label>
                      <div class="col-md-8">
                        <input type="number" name="tambour_prix" class="form-control" value="200" step="0.01" min="0">
                        <span class="help-block">Durée de vie : 120 000 pages</span>
                      </div>
                    </div>
                    <div class="form-group">
                      <label class="col-md-4 control-label">Prix par copie (€) :</label>
                      <div class="col-md-8">
                        <input type="number" name="tambour_prix_copie" class="form-control" value="0.00167" step="0.00001" min="0">
                        <span class="help-block">Calculé : 200€ ÷ 120 000 pages</span>
                      </div>
                    </div>
                  </div>
                  
                  <div class="col-md-6">
                    <h6>Unité de développement :</h6>
                    <div class="form-group">
                      <label class="col-md-4 control-label">Prix (€) :</label>
                      <div class="col-md-8">
                        <input type="number" name="dev_prix" class="form-control" value="300" step="0.01" min="0">
                        <span class="help-block">Durée de vie : 120 000 pages</span>
                      </div>
                    </div>
                    <div class="form-group">
                      <label class="col-md-4 control-label">Prix par copie (€) :</label>
                      <div class="col-md-8">
                        <input type="number" name="dev_prix_copie" class="form-control" value="0.00250" step="0.00001" min="0">
                        <span class="help-block">Calculé : 300€ ÷ 120 000 pages</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <div class="form-group">
                <div class="col-md-9 col-md-offset-3">
                  <input type="hidden" name="add_machine" value="1">
                  <button type="submit" class="btn btn-info">
                    <i class="fa fa-plus"></i> Ajouter le photocopieur
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>
        
        <!-- Actions rapides -->
        <div class="panel panel-warning">
          <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-bolt"></i> Actions rapides</h3>
          </div>
          <div class="panel-body">
            <div class="row">
              <div class="col-md-4">
                <a href="?admin&prix" class="btn btn-warning btn-block">
                  <i class="fa fa-euro"></i> Gérer les prix
                </a>
                <small class="text-muted">Tarifs et consommables</small>
              </div>
              <div class="col-md-4">
                <a href="?admin&changes" class="btn btn-danger btn-block">
                  <i class="fa fa-history"></i> Historique des changements
                </a>
                <small class="text-muted">Encre, masters, tambour</small>
              </div>
              <div class="col-md-4">
                <a href="?admin&tirages" class="btn btn-primary btn-block">
                  <i class="fa fa-list"></i> Gestion des tirages
                </a>
                <small class="text-muted">Voir et modifier les tirages</small>
              </div>
            </div>
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
  </div>
</div>

<script>
$(document).ready(function() {
    // Gestion de l'affichage des champs selon le type de photocopieur
    $('#photocop_type').change(function() {
        var selectedType = $(this).val();
        var tonerFields = $('#toner_fields');
        var encreFields = $('#encre_fields');
        
        if (selectedType === 'photocop_toner') {
            tonerFields.show();
            encreFields.hide();
        } else if (selectedType === 'photocop_encre') {
            encreFields.show();
            tonerFields.hide();
        } else {
            tonerFields.hide();
            encreFields.hide();
        }
    });
    
    // Gestion de la suppression de machines
    $('.delete-machine').click(function() {
        var machineId = $(this).data('id');
        var machineType = $(this).data('type');
        var machineName = $(this).data('name');
        
        if (confirm('Êtes-vous sûr de vouloir supprimer la machine "' + machineName + '" ?\n\nCette action supprimera définitivement :\n- La machine\n- Tous ses compteurs\n- Tout son historique de changements\n\nCette action est irréversible !')) {
            
            // Désactiver le bouton pendant le traitement
            $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Suppression...');
            
            $.ajax({
                url: '?ajax_delete_machine',
                type: 'POST',
                data: {
                    machine_id: machineId,
                    machine_type: machineType
                },
                dataType: 'json',
                success: function(response) {
                    console.log('Réponse reçue:', response);
                    if (response.success) {
                        // Afficher le message de succès
                        alert(response.success);
                        // Recharger la page pour mettre à jour la liste
                        location.reload();
                    } else {
                        alert('Erreur : ' + response.error);
                        // Réactiver le bouton
                        $('.delete-machine[data-id="' + machineId + '"]').prop('disabled', false).html('<i class="fa fa-trash"></i> Supprimer');
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Erreur AJAX:', xhr.responseText);
                    console.log('Status:', status);
                    console.log('Error:', error);
                    alert('Erreur lors de la suppression de la machine. Vérifiez la console pour plus de détails.');
                    // Réactiver le bouton
                    $('.delete-machine[data-id="' + machineId + '"]').prop('disabled', false).html('<i class="fa fa-trash"></i> Supprimer');
                }
            });
        }
    });
    
    // Gestion des tambours
    $('#add-tambour').click(function() {
        var tambourHtml = `
            <div class="tambour-item" style="margin-bottom: 10px;">
                <div class="row">
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="tambours[]" placeholder="ex: tambour_bleu" required>
                    </div>
                    <div class="col-md-3">
                        <input type="number" class="form-control" name="prix_tambour_unite[]" placeholder="Prix unité" step="0.001" min="0" required>
                    </div>
                    <div class="col-md-3">
                        <input type="number" class="form-control" name="prix_tambour_pack[]" placeholder="Prix pack" step="0.01" min="0" value="11">
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-danger btn-sm remove-tambour">
                            <i class="fa fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        $('#tambours-container').append(tambourHtml);
        updateRemoveButtons();
    });
    
    // Suppression des tambours
    $(document).on('click', '.remove-tambour', function() {
        $(this).closest('.tambour-item').remove();
        updateRemoveButtons();
    });
    
    // Mettre à jour la visibilité des boutons de suppression
    function updateRemoveButtons() {
        var tambourItems = $('.tambour-item');
        if (tambourItems.length > 1) {
            $('.remove-tambour').show();
        } else {
            $('.remove-tambour').hide();
        }
    }
    
    // Initialiser l'état des boutons
    updateRemoveButtons();
});

// Gestion de l'édition des tambours
$('.edit-tambours').click(function() {
    var machineId = $(this).data('id');
    var machineName = $(this).data('name');
    var tamboursData = $(this).data('tambours');
    
    // Parser les tambours depuis JSON
    console.log('tamboursData reçu:', tamboursData);
    var tambours = [];
    
    // Si c'est déjà un tableau, on l'utilise directement
    if (Array.isArray(tamboursData)) {
        tambours = tamboursData;
        console.log('tambours déjà tableau:', tambours);
    } else {
        // Sinon on essaie de parser comme JSON
        try {
            tambours = JSON.parse(tamboursData);
            console.log('tambours parsés:', tambours);
        } catch (e) {
            console.log('Erreur parsing tambours:', e);
            tambours = ['tambour_noir'];
        }
    }
    
    // Ouvrir le modal d'édition
    $('#edit-tambours-modal .modal-title').text('Éditer les tambours - ' + machineName);
    $('#edit-tambours-modal').data('machine-id', machineId);
    
    // Vider le conteneur des tambours
    $('#edit-tambours-container').empty();
    
    // Récupérer les prix existants via AJAX
    console.log('Début de la récupération des prix pour machine ID:', machineId);
    $.ajax({
        url: 'ajax_get_tambour_prices.php',
        type: 'POST',
        data: { machine_id: machineId },
        dataType: 'text',
        success: function(response) {
            console.log('Réponse AJAX reçue:', response);
            // Parser la réponse même si elle contient des warnings PHP
            try {
                // Extraire le JSON de la réponse si elle contient des warnings PHP
                var jsonMatch = response.match(/\{.*\}/);
                if (jsonMatch) {
                    response = JSON.parse(jsonMatch[0]);
                    console.log('JSON parsé:', response);
                } else {
                    console.log('Aucun JSON trouvé dans la réponse');
                    return;
                }
            } catch (e) {
                console.log('Erreur parsing JSON:', e);
                return;
            }
            
            if (response.success && response.prices) {
                console.log('Tambours à afficher:', tambours);
                console.log('Prix disponibles:', response.prices);
                // Ajouter les tambours avec leurs prix
                tambours.forEach(function(tambour, index) {
                    var prix = response.prices[tambour] || { unite: 0.002, pack: 0 };
                    console.log('Ajout du tambour:', tambour, 'avec prix:', prix);
                    addEditTambourItem(tambour, prix.unite, prix.pack);
                });
                
                // Si aucun tambour, ajouter un par défaut
                if (tambours.length === 0) {
                    addEditTambourItem('tambour_noir', 0.002, 0);
                }
            } else {
                // Fallback si pas de prix
                tambours.forEach(function(tambour, index) {
                    addEditTambourItem(tambour, 0.002, 0);
                });
                
                if (tambours.length === 0) {
                    addEditTambourItem('tambour_noir', 0.002, 0);
                }
            }
            
            updateEditRemoveButtons();
            $('#edit-tambours-modal').modal('show');
            console.log('Modal ouvert, contenu final:', $('#edit-tambours-container').html());
        },
        error: function(xhr, status, error) {
            console.log('Erreur AJAX:', xhr.responseText);
            // Fallback en cas d'erreur
            tambours.forEach(function(tambour, index) {
                addEditTambourItem(tambour, 0.002, 0);
            });
            
            if (tambours.length === 0) {
                addEditTambourItem('tambour_noir', 0.002, 0);
            }
            
            updateEditRemoveButtons();
            $('#edit-tambours-modal').modal('show');
        }
    });
});

// Fonction pour ajouter un item tambour dans le modal d'édition
function addEditTambourItem(tambourName, prixUnite, prixPack) {
    console.log('addEditTambourItem appelée avec:', tambourName, prixUnite, prixPack);
    prixUnite = prixUnite || 0.002;
    prixPack = prixPack || 0;
    
    var tambourHtml = `
        <div class="edit-tambour-item" style="margin-bottom: 10px;">
            <div class="row">
                <div class="col-md-5">
                    <input type="text" class="form-control" name="edit_tambours[]" placeholder="ex: tambour_noir" value="${tambourName}" required>
                </div>
                <div class="col-md-3">
                    <input type="number" class="form-control" name="edit_prix_tambour_unite[]" placeholder="Prix unité" step="0.001" min="0" value="${prixUnite}" required>
                </div>
                <div class="col-md-3">
                    <input type="number" class="form-control" name="edit_prix_tambour_pack[]" placeholder="Prix pack" step="0.01" min="0" value="${prixPack}">
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-danger btn-sm remove-edit-tambour">
                        <i class="fa fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    console.log('HTML à ajouter:', tambourHtml);
    $('#edit-tambours-container').append(tambourHtml);
    console.log('HTML ajouté, nombre d\'éléments dans le conteneur:', $('#edit-tambours-container .edit-tambour-item').length);
}

// Ajouter un tambour dans le modal d'édition (utiliser la délégation d'événements)
$(document).on('click', '#add-edit-tambour', function() {
    addEditTambourItem('', 0.002, 0);
    updateEditRemoveButtons();
});

// Suppression des tambours dans le modal d'édition
$(document).on('click', '.remove-edit-tambour', function() {
    $(this).closest('.edit-tambour-item').remove();
    updateEditRemoveButtons();
});

// Mettre à jour la visibilité des boutons de suppression dans le modal
function updateEditRemoveButtons() {
    var tambourItems = $('.edit-tambour-item');
    if (tambourItems.length > 1) {
        $('.remove-edit-tambour').show();
    } else {
        $('.remove-edit-tambour').hide();
    }
}

// Soumission du formulaire d'édition des tambours (utiliser la délégation d'événements)
$(document).on('submit', '#edit-tambours-form', function(e) {
    console.log('Formulaire soumis !');
    e.preventDefault();
    
    var machineId = $('#edit-tambours-modal').data('machine-id');
    var tambours = [];
    var prixUnite = [];
    var prixPack = [];
    
    $('.edit-tambour-item').each(function() {
        var tambourName = $(this).find('input[name="edit_tambours[]"]').val();
        var prixUniteVal = $(this).find('input[name="edit_prix_tambour_unite[]"]').val();
        var prixPackVal = $(this).find('input[name="edit_prix_tambour_pack[]"]').val();
        
        if (tambourName && prixUniteVal) {
            tambours.push(tambourName);
            prixUnite.push(prixUniteVal);
            prixPack.push(prixPackVal || 0);
        }
    });
    
    if (tambours.length === 0) {
        alert('Veuillez définir au moins un tambour.');
        return;
    }
    
    // Envoyer la requête AJAX
    $.ajax({
        url: 'ajax_edit_tambours.php',
        type: 'POST',
        data: {
            machine_id: machineId,
            tambours: tambours,
            prix_tambour_unite: prixUnite,
            prix_tambour_pack: prixPack
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                alert(response.success);
                location.reload(); // Recharger la page pour voir les changements
            } else {
                alert('Erreur: ' + (response.error || 'Erreur inconnue'));
            }
        },
        error: function() {
            alert('Erreur lors de la sauvegarde des tambours.');
        }
    });
});
</script>

<!-- Modal d'édition des tambours -->
<div class="modal fade" id="edit-tambours-modal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Éditer les tambours</h4>
      </div>
      <div class="modal-body">
        <form id="edit-tambours-form">
          <div class="form-group">
            <label>Tambours :</label>
            <div id="edit-tambours-container">
              <!-- Les tambours seront ajoutés ici dynamiquement -->
            </div>
            <button type="button" class="btn btn-info btn-sm" id="add-edit-tambour">
              <i class="fa fa-plus"></i> Ajouter un tambour
            </button>
            <span class="help-block">Définissez les tambours disponibles pour ce duplicopieur. Chaque tambour peut avoir un nom personnalisé.</span>
          </div>
          <div class="form-group">
            <button type="submit" class="btn btn-success">
              <i class="fa fa-save"></i> Sauvegarder les tambours
            </button>
            <button type="button" class="btn btn-default" data-dismiss="modal">
              <i class="fa fa-times"></i> Annuler
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Modal de renommage de machine -->
<div class="modal fade" id="rename-machine-modal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Renommer la machine</h4>
      </div>
      <div class="modal-body">
        <form id="rename-machine-form">
          <div class="form-group">
            <label>Nom actuel :</label>
            <p class="form-control-static" id="current-machine-name"></p>
          </div>
          <div class="form-group">
            <label for="new-machine-name">Nouveau nom :</label>
            <input type="text" class="form-control" id="new-machine-name" name="new_name" required>
            <span class="help-block">Le nouveau nom sera mis à jour dans toutes les tables (historique, prix, aide, etc.)</span>
          </div>
          <div class="form-group">
            <button type="submit" class="btn btn-success">
              <i class="fa fa-save"></i> Renommer
            </button>
            <button type="button" class="btn btn-default" data-dismiss="modal">
              <i class="fa fa-times"></i> Annuler
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div><script src="js/machine-rename.js"></script>
