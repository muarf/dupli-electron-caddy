<div class="section">
  <div class="container">
    <div class="row">
      <div class="col-md-10 col-md-offset-1">
        <h1 class="text-center"><?php _e('admin.machine_management'); ?></h1>
        <hr>

        <!-- Messages de succès/erreur -->
        <?php if (isset($array['machine_created'])): ?>
          <div class="alert alert-success">
            <i class="fa fa-check"></i> <?= htmlspecialchars($array['machine_created']) ?>
          </div>
        <?php endif; ?>

        <?php if (isset($array['machine_error'])): ?>
          <div class="alert alert-danger">
            <i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($array['machine_error']) ?>
          </div>
        <?php endif; ?>

        <!-- Duplicopieurs -->
        <div class="panel panel-success">
          <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-print"></i> <?php _e('admin_machines.installed_duplicopieurs'); ?></h3>
          </div>
          <div class="panel-body">
            <?php if (isset($array['machines']) && !empty($array['machines'])): ?>
              <?php
              $duplicopieurs = array_filter($array['machines'], function ($machine) {
                return $machine['machine_type'] === 'duplicopieur';
              });
              ?>

              <?php if (!empty($duplicopieurs)): ?>
                <div class="table-responsive">
                  <table class="table table-striped">
                    <thead>
                      <tr>
                        <th><?php _e('admin_machines.name'); ?></th>
                        <th><?php _e('admin_machines.type'); ?></th>
                        <th><?php _e('admin_machines.master_counter'); ?></th>
                        <th><?php _e('admin_machines.passage_counter'); ?></th>
                        <th><?php _e('admin_machines.actions'); ?></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($duplicopieurs as $machine): ?>
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
                            <a href="?admin&changes&machine=<?= urlencode($machine['name']) ?>"
                              class="btn btn-primary btn-xs">
                              <i class="fa fa-history"></i> <?php _e('admin_machines.history'); ?>
                            </a>
                            <a href="?admin&prix&machine=<?= urlencode($machine['name']) ?>" class="btn btn-info btn-xs">
                              <i class="fa fa-euro"></i> <?php _e('admin_machines.price'); ?>
                            </a>
                            <button type="button" class="btn btn-warning btn-xs edit-tambours"
                              data-id="<?= htmlspecialchars($machine['id']) ?>"
                              data-name="<?= htmlspecialchars($machine['name']) ?>"
                              data-tambours="<?= htmlspecialchars($machine['tambours'] ?? '[]') ?>">
                              <i class="fa fa-cog"></i> <?php _e('admin_machines.tambours'); ?>
                            </button>
                            <button type="button" class="btn btn-success btn-xs rename-machine"
                              data-id="<?= htmlspecialchars($machine['id']) ?>" data-type="duplicopieur"
                              data-name="<?= htmlspecialchars($machine['name']) ?>">
                              <i class="fa fa-edit"></i> <?php _e('admin_machines.rename'); ?>
                            </button>
                            <button type="button" class="btn btn-danger btn-xs delete-machine"
                              data-id="<?= htmlspecialchars($machine['id']) ?>" data-type="duplicopieur"
                              data-name="<?= htmlspecialchars($machine['name']) ?>">
                              <i class="fa fa-trash"></i> <?php _e('admin_machines.delete'); ?>
                            </button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <div class="alert alert-info">
                  <i class="fa fa-info-circle"></i> <?php _e('admin_machines.no_duplicopieur'); ?>
                </div>
              <?php endif; ?>
            <?php else: ?>
              <div class="alert alert-info">
                <i class="fa fa-info-circle"></i> <?php _e('admin_machines.no_machine'); ?>
              </div>
            <?php endif; ?>

            <!-- Formulaire d'ajout de duplicopieur -->
            <hr>
            <h4><i class="fa fa-plus"></i> <?php _e('admin_machines.add_duplicopieur'); ?></h4>
            <form method="post" class="form-horizontal">
              <input type="hidden" name="machine_type" value="duplicopieur" />

              <div class="form-group">
                <label class="col-md-3 control-label" for="machine_name"><?php _e('admin_machines.name'); ?> :</label>
                <div class="col-md-9">
                  <input type="text" class="form-control" name="machine_name" id="machine_name"
                    placeholder="ex: Ricoh dx4545" required>
                </div>
              </div>

              <div class="form-group">
                <label class="col-md-3 control-label" for="master_counter"><?php _e('admin_machines.master_counter'); ?> :</label>
                <div class="col-md-9">
                  <input type="number" class="form-control" name="master_counter" id="master_counter"
                    placeholder="Ex: 12345" min="0">
                </div>
              </div>

              <div class="form-group">
                <label class="col-md-3 control-label" for="passage_counter"><?php _e('admin_machines.passage_counter'); ?> :</label>
                <div class="col-md-9">
                  <input type="number" class="form-control" name="passage_counter" id="passage_counter"
                    placeholder="Ex: 67890" min="0">
                </div>
              </div>

              <div class="form-group">
                <label class="col-md-3 control-label" for="prix_master_unite"><?php _e('admin_machines.price_master_unit'); ?></label>
                <div class="col-md-9">
                  <input type="number" class="form-control" name="prix_master_unite" id="prix_master_unite"
                    placeholder="Ex: 0.40" step="0.01" min="0" value="0.40" required>
                  <span class="help-block"><?php _e('admin_machines.price_master_unit_help'); ?></span>
                </div>
              </div>

              <div class="form-group">
                <label class="col-md-3 control-label" for="prix_master_pack"><?php _e('admin_machines.price_master_pack'); ?></label>
                <div class="col-md-9">
                  <input type="number" class="form-control" name="prix_master_pack" id="prix_master_pack"
                    placeholder="Ex: 70" step="0.01" min="0" value="70">
                  <span class="help-block"><?php _e('admin_machines.price_master_pack_help'); ?></span>
                </div>
              </div>

              <!-- Section Tambours -->
              <hr>
              <h5><i class="fa fa-cog"></i> <?php _e('admin_machines.tambour_config'); ?></h5>
              <div class="form-group">
                <label class="col-md-3 control-label"><?php _e('admin_machines.tambours'); ?> :</label>
                <div class="col-md-9">
                  <div id="tambours-container">
                    <!-- Tambour par défaut -->
                    <div class="tambour-item" style="margin-bottom: 10px;">
                      <div class="row">
                        <div class="col-md-4">
                          <input type="text" class="form-control" name="tambours[]" placeholder="ex: tambour_noir"
                            value="tambour_noir" required>
                        </div>
                        <div class="col-md-3">
                          <input type="number" class="form-control" name="prix_tambour_unite[]" placeholder="<?php echo __('common.unit_price'); ?>" 
                            step="0.001" min="0" value="0.002" required>
                        </div>
                        <div class="col-md-3">
                          <input type="number" class="form-control" name="prix_tambour_pack[]" placeholder="<?php echo __('admin_machines.price_pack'); ?>"
                            step="0.01" min="0" value="11">
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
                    <i class="fa fa-plus"></i> <?php _e('admin_machines.add_tambour'); ?>
                  </button>
                  <span class="help-block"><?php _e('admin_machines.tambour_help'); ?></span>
                </div>
              </div>

              <div class="form-group">
                <div class="col-md-9 col-md-offset-3">
                  <input type="hidden" name="add_machine" value="1">
                  <button type="submit" class="btn btn-success">
                    <i class="fa fa-plus"></i> <?php _e('admin_machines.add_duplicopieur_btn'); ?>
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>

        <!-- Photocopieurs -->
        <div class="panel panel-info">
          <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-copy"></i> <?php _e('admin_machines.installed_photocopieurs'); ?></h3>
          </div>
          <div class="panel-body">
            <?php if (isset($array['machines']) && !empty($array['machines'])): ?>
              <?php
              $photocopieurs = array_filter($array['machines'], function ($machine) {
                return $machine['machine_type'] === 'photocopieur';
              });
              ?>

              <?php if (!empty($photocopieurs)): ?>
                <div class="table-responsive">
                  <table class="table table-striped">
                    <thead>
                      <tr>
                        <th><?php _e('admin_machines.name'); ?></th>
                        <th><?php _e('admin_machines.type'); ?></th>
                        <th><?php _e('admin_machines.counter'); ?></th>
                        <th><?php _e('admin_machines.actions'); ?></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($photocopieurs as $machine): ?>
                        <tr>
                          <td><strong><?= htmlspecialchars($machine['name']) ?></strong></td>
                          <td>
                            <span
                              class="label label-<?= strpos($machine['type'], 'encre') !== false ? 'success' : 'primary' ?>">
                              <?= htmlspecialchars($machine['type']) ?>
                            </span>
                          </td>
                          <td><?= number_format($machine['passage_counter']) ?></td>
                          <td>
                            <a href="?admin&changes&machine=<?= urlencode($machine['name']) ?>"
                              class="btn btn-primary btn-xs">
                              <i class="fa fa-history"></i> <?php _e('admin_machines.history'); ?>
                            </a>
                            <a href="?admin&prix&machine=<?= urlencode($machine['name']) ?>" class="btn btn-info btn-xs">
                              <i class="fa fa-euro"></i> <?php _e('admin_machines.price'); ?>
                            </a>
                            <button type="button" class="btn btn-success btn-xs rename-machine"
                              data-id="<?= htmlspecialchars($machine['id']) ?>" data-type="photocopieur"
                              data-name="<?= htmlspecialchars($machine['name']) ?>">
                              <i class="fa fa-edit"></i> <?php _e('admin_machines.rename'); ?>
                            </button>
                            <button type="button" class="btn btn-danger btn-xs delete-machine"
                              data-id="<?= htmlspecialchars($machine['id']) ?>" data-type="photocopieur"
                              data-name="<?= htmlspecialchars($machine['name']) ?>">
                              <i class="fa fa-trash"></i> <?php _e('admin_machines.delete'); ?>
                            </button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <div class="alert alert-info">
                  <i class="fa fa-info-circle"></i> <?php _e('admin_machines.no_photocopieur'); ?>
                </div>
              <?php endif; ?>
            <?php else: ?>
              <div class="alert alert-info">
                <i class="fa fa-info-circle"></i> <?php _e('admin_machines.no_machine'); ?>
              </div>
            <?php endif; ?>

            <!-- Formulaire d'ajout de photocopieur -->
            <hr>
            <h4><i class="fa fa-plus"></i> <?php _e('admin_machines.add_photocopieur'); ?></h4>
            <form method="post" class="form-horizontal">
              <div class="form-group">
                <label class="col-md-3 control-label" for="photocop_type"><?php _e('admin_machines.type'); ?> :</label>
                <div class="col-md-9">
                  <select class="form-control" name="machine_type" id="photocop_type" required>
                    <option value=""><?php _e('admin_machines.choose_type'); ?></option>
                    <option value="photocop_encre"><?php _e('admin_machines.photocop_ink'); ?></option>
                    <option value="photocop_toner"><?php _e('admin_machines.photocop_toner'); ?></option>
                  </select>
                </div>
              </div>

              <div class="form-group">
                <label class="col-md-3 control-label" for="photocop_name"><?php _e('admin_machines.name'); ?> :</label>
                <div class="col-md-9">
                  <input type="text" class="form-control" name="machine_name" id="photocop_name"
                    placeholder="ex: Canon IR2525" required>
                </div>
              </div>

              <div class="form-group">
                <label class="col-md-3 control-label" for="photocop_counter"><?php _e('admin_machines.counter'); ?> :</label>
                <div class="col-md-9">
                  <input type="number" class="form-control" name="passage_counter" id="photocop_counter" value="0"
                    min="0">
                </div>
              </div>

              <!-- Champs pour photocopieurs à encre -->
              <div id="encre_fields" style="display: none;">
                <hr>
                <h5><i class="fa fa-tint"></i> <?php _e('admin_machines.ink_config'); ?></h5>

                <!-- Encres couleur -->
                <div class="row">
                  <div class="col-md-6">
                    <h6><?php _e('admin_machines.black_ink'); ?></h6>
                    <div class="form-group">
                      <label class="col-md-4 control-label"><?php _e('admin_machines.price_unit'); ?></label>
                      <div class="col-md-8">
                        <input type="number" name="noire_unite" class="form-control" value="0.002" step="0.001" min="0">
                        <span class="help-block"><?php _e('admin_machines.price_per_pass'); ?></span>
                      </div>
                    </div>
                    <div class="form-group">
                      <label class="col-md-4 control-label"><?php _e('admin_machines.price_pack'); ?></label>
                      <div class="col-md-8">
                        <input type="number" name="noire_pack" class="form-control" value="140" step="0.01" min="0">
                        <span class="help-block"><?php _e('admin_machines.price_pack_help'); ?></span>
                      </div>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <h6><?php _e('admin_machines.blue_ink'); ?></h6>
                    <div class="form-group">
                      <label class="col-md-4 control-label"><?php _e('admin_machines.price_unit'); ?></label>
                      <div class="col-md-8">
                        <input type="number" name="bleue_unite" class="form-control" value="0.002" step="0.001" min="0">
                        <span class="help-block"><?php _e('admin_machines.price_per_pass'); ?></span>
                      </div>
                    </div>
                    <div class="form-group">
                      <label class="col-md-4 control-label"><?php _e('admin_machines.price_pack'); ?></label>
                      <div class="col-md-8">
                        <input type="number" name="bleue_pack" class="form-control" value="140" step="0.01" min="0">
                        <span class="help-block"><?php _e('admin_machines.price_pack_help'); ?></span>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="row">
                  <div class="col-md-6">
                    <h6><?php _e('admin_machines.red_ink'); ?></h6>
                    <div class="form-group">
                      <label class="col-md-4 control-label"><?php _e('admin_machines.price_unit'); ?></label>
                      <div class="col-md-8">
                        <input type="number" name="rouge_unite" class="form-control" value="0.002" step="0.001" min="0">
                        <span class="help-block"><?php _e('admin_machines.price_per_pass'); ?></span>
                      </div>
                    </div>
                    <div class="form-group">
                      <label class="col-md-4 control-label"><?php _e('admin_machines.price_pack'); ?></label>
                      <div class="col-md-8">
                        <input type="number" name="rouge_pack" class="form-control" value="140" step="0.01" min="0">
                        <span class="help-block"><?php _e('admin_machines.price_pack_help'); ?></span>
                      </div>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <h6><?php _e('admin_machines.yellow_ink'); ?></h6>
                    <div class="form-group">
                      <label class="col-md-4 control-label"><?php _e('admin_machines.price_unit'); ?></label>
                      <div class="col-md-8">
                        <input type="number" name="jaune_unite" class="form-control" value="0.002" step="0.001" min="0">
                        <span class="help-block"><?php _e('admin_machines.price_per_pass'); ?></span>
                      </div>
                    </div>
                    <div class="form-group">
                      <label class="col-md-4 control-label"><?php _e('admin_machines.price_pack'); ?></label>
                      <div class="col-md-8">
                        <input type="number" name="jaune_pack" class="form-control" value="140" step="0.01" min="0">
                        <span class="help-block"><?php _e('admin_machines.price_pack_help'); ?></span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Champs pour photocopieurs à toner -->
              <div id="toner_fields" style="display: none;">
                <hr>
                <h5><i class="fa fa-tint"></i> <?php _e('admin_machines.toner_config'); ?></h5>

                <!-- Toners couleur -->
                <div class="row">
                  <div class="col-md-6">
                    <h6><?php _e('admin_machines.black_toner'); ?></h6>
                    <div class="form-group">
                      <label class="col-md-4 control-label"><?php _e('admin_machines.price_cartridge'); ?></label>
                      <div class="col-md-8">
                        <input type="number" name="toner_noir_prix" class="form-control" value="80" step="0.01" min="0">
                        <span class="help-block">Capacité : 23 000 pages (5% couverture)</span>
                      </div>
                    </div>
                    <div class="form-group">
                      <label class="col-md-4 control-label"><?php _e('admin_machines.price_per_page'); ?></label>
                      <div class="col-md-8">
                        <input type="number" name="toner_noir_prix_copie" class="form-control" value="0.00348"
                          step="0.00001" min="0">
                        <span class="help-block"><?php _e('admin_machines.calculated_help', ['amount' => '80', 'pages' => '23 000']); ?></span>
                      </div>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <h6><?php _e('admin_machines.cyan_toner'); ?></h6>
                    <div class="form-group">
                      <label class="col-md-4 control-label"><?php _e('admin_machines.price_cartridge'); ?></label>
                      <div class="col-md-8">
                        <input type="number" name="toner_cyan_prix" class="form-control" value="80" step="0.01" min="0">
                        <span class="help-block">Capacité : 18 000 pages (5% couverture)</span>
                      </div>
                    </div>
                    <div class="form-group">
                      <label class="col-md-4 control-label"><?php _e('admin_machines.price_per_page'); ?></label>
                      <div class="col-md-8">
                        <input type="number" name="toner_cyan_prix_copie" class="form-control" value="0.00444"
                          step="0.00001" min="0">
                        <span class="help-block"><?php _e('admin_machines.calculated_help', ['amount' => '80', 'pages' => '18 000']); ?></span>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="row">
                  <div class="col-md-6">
                    <h6><?php _e('admin_machines.magenta_toner'); ?></h6>
                    <div class="form-group">
                      <label class="col-md-4 control-label"><?php _e('admin_machines.price_cartridge'); ?></label>
                      <div class="col-md-8">
                        <input type="number" name="toner_magenta_prix" class="form-control" value="80" step="0.01"
                          min="0">
                        <span class="help-block">Capacité : 18 000 pages (5% couverture)</span>
                      </div>
                    </div>
                    <div class="form-group">
                      <label class="col-md-4 control-label"><?php _e('admin_machines.price_per_page'); ?></label>
                      <div class="col-md-8">
                        <input type="number" name="toner_magenta_prix_copie" class="form-control" value="0.00444"
                          step="0.00001" min="0">
                        <span class="help-block"><?php _e('admin_machines.calculated_help', ['amount' => '80', 'pages' => '18 000']); ?></span>
                      </div>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <h6><?php _e('admin_machines.yellow_toner'); ?></h6>
                    <div class="form-group">
                      <label class="col-md-4 control-label"><?php _e('admin_machines.price_cartridge'); ?></label>
                      <div class="col-md-8">
                        <input type="number" name="toner_jaune_prix" class="form-control" value="80" step="0.01"
                          min="0">
                        <span class="help-block">Capacité : 18 000 pages (5% couverture)</span>
                      </div>
                    </div>
                    <div class="form-group">
                      <label class="col-md-4 control-label"><?php _e('admin_machines.price_per_page'); ?></label>
                      <div class="col-md-8">
                        <input type="number" name="toner_jaune_prix_copie" class="form-control" value="0.00444"
                          step="0.00001" min="0">
                        <span class="help-block"><?php _e('admin_machines.calculated_help', ['amount' => '80', 'pages' => '18 000']); ?></span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Tambour et Dev -->
                <hr>
                <h5><i class="fa fa-cog"></i> <?php _e('admin_machines.drum_dev_unit'); ?></h5>
                <div class="row">
                  <div class="col-md-6">
                    <h6><?php _e('admin_machines.drum', [], false); ?> :</h6>
                    <div class="form-group">
                      <label class="col-md-4 control-label"><?php _e('common.price', [], false); ?> (€) :</label>
                      <div class="col-md-8">
                        <input type="number" name="tambour_prix" class="form-control" value="200" step="0.01" min="0">
                        <span class="help-block"><?php _e('admin_machines.lifespan_pages', ['count' => '120 000'], false); ?></span>
                      </div>
                    </div>
                    <div class="form-group">
                      <label class="col-md-4 control-label"><?php _e('admin_machines.price_per_copy', [], false); ?> (€) :</label>
                      <div class="col-md-8">
                        <input type="number" name="tambour_prix_copie" class="form-control" value="0.00167"
                          step="0.00001" min="0">
                        <span class="help-block"><?php _e('admin_machines.calculated_help', ['amount' => '200', 'pages' => '120 000'], false); ?></span>
                      </div>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <h6><?php _e('admin_machines.dev_unit'); ?></h6>
                    <div class="form-group">
                      <label class="col-md-4 control-label"><?php _e('common.price', [], false); ?> (€) :</label>
                      <div class="col-md-8">
                        <input type="number" name="dev_prix" class="form-control" value="300" step="0.01" min="0">
                        <span class="help-block"><?php _e('admin_machines.lifespan_pages', ['count' => '120 000'], false); ?></span>
                      </div>
                    </div>
                    <div class="form-group">
                      <label class="col-md-4 control-label"><?php _e('admin_machines.price_per_copy', [], false); ?> (€) :</label>
                      <div class="col-md-8">
                        <input type="number" name="dev_prix_copie" class="form-control" value="0.00250" step="0.00001"
                          min="0">
                        <span class="help-block"><?php _e('admin_machines.calculated_help', ['amount' => '300', 'pages' => '120 000'], false); ?></span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="form-group">
                <div class="col-md-9 col-md-offset-3">
                  <input type="hidden" name="add_machine" value="1">
                  <button type="submit" class="btn btn-info">
                    <i class="fa fa-plus"></i> <?php _e('admin_machines.add_photocopieur_btn'); ?>
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>

        <!-- Actions rapides -->
        <div class="panel panel-warning">
          <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-bolt"></i> <?php _e('admin_machines.quick_actions'); ?></h3>
          </div>
          <div class="panel-body">
            <div class="row">
              <div class="col-md-4">
                <a href="?admin&prix" class="btn btn-warning btn-block">
                  <i class="fa fa-euro"></i> <?php _e('admin_machines.manage_prices'); ?>
                </a>
                <small class="text-muted"><?php _e('admin_machines.prices_desc'); ?></small>
              </div>
              <div class="col-md-4">
                <a href="?admin&changes" class="btn btn-danger btn-block">
                  <i class="fa fa-history"></i> <?php _e('admin_machines.change_history'); ?>
                </a>
                <small class="text-muted"><?php _e('admin_machines.changes_desc'); ?></small>
              </div>
              <div class="col-md-4">
                <a href="?admin&tirages" class="btn btn-primary btn-block">
                  <i class="fa fa-list"></i> <?php _e('admin_machines.manage_prints'); ?>
                </a>
                <small class="text-muted"><?php _e('admin_machines.prints_desc'); ?></small>
              </div>
            </div>
          </div>
        </div>

        <!-- Bouton retour -->
        <div class="row">
          <div class="col-md-12">
            <a href="?admin" class="btn btn-default btn-block">
              <i class="fa fa-arrow-left"></i> <?php _e('admin_machines.back_to_admin'); ?>
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  var CONFIG = {
    translations: {
      delete_confirm_title: "<?php echo __js('admin_machines.delete_confirm_title'); ?>",
      delete_confirm_msg: "<?php echo __js('admin_machines.delete_confirm_msg', ['name' => '']); ?>",
      deleting: "<?php echo __js('admin_machines.deleting'); ?>",
      delete: "<?php echo __js('admin_machines.delete'); ?>",
      edit_tambours: "<?php echo __js('admin_machines.edit_tambours'); ?>",
      unit_price: "<?php echo __js('common.unit_price'); ?>",
      price_pack: "<?php echo __js('admin_machines.price_pack'); ?>"
    }
  };
</script>
<script src="js/admin-machines.js"></script>

<!-- Modal d'édition des tambours -->
<div class="modal fade" id="edit-tambours-modal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title"><?php _e('admin_machines.edit_tambours'); ?></h4>
      </div>
      <div class="modal-body">
        <form id="edit-tambours-form">
          <div class="form-group">
            <label><?php _e('admin_machines.tambours'); ?> :</label>
            <div id="edit-tambours-container">
              <!-- Les tambours seront ajoutés ici dynamiquement -->
            </div>
            <button type="button" class="btn btn-info btn-sm" id="add-edit-tambour">
              <i class="fa fa-plus"></i> <?php _e('admin_machines.add_tambour'); ?>
            </button>
            <span class="help-block"><?php _e('admin_machines.tambour_help'); ?></span>
          </div>
          <div class="form-group">
            <button type="submit" class="btn btn-success">
              <i class="fa fa-save"></i> <?php _e('admin_machines.save_tambours'); ?>
            </button>
            <button type="button" class="btn btn-default" data-dismiss="modal">
              <i class="fa fa-times"></i> <?php _e('common.cancel'); ?>
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
        <h4 class="modal-title"><?php _e('admin_machines.rename_machine'); ?></h4>
      </div>
      <div class="modal-body">
        <form id="rename-machine-form">
          <div class="form-group">
            <label><?php _e('admin_machines.current_name'); ?></label>
            <p class="form-control-static" id="current-machine-name"></p>
          </div>
          <div class="form-group">
            <label for="new-machine-name"><?php _e('admin_machines.new_name'); ?></label>
            <input type="text" class="form-control" id="new-machine-name" name="new_name" required>
            <span class="help-block"><?php _e('admin_machines.rename_help'); ?></span>
          </div>
          <div class="form-group">
            <button type="submit" class="btn btn-success">
              <i class="fa fa-save"></i> <?php _e('admin_machines.rename'); ?>
            </button>
            <button type="button" class="btn btn-default" data-dismiss="modal">
              <i class="fa fa-times"></i> <?php _e('common.cancel'); ?>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<script src="js/machine-rename.js"></script>
