<style>
  /* Amélioration de la lisibilité des champs de prix */
  .prix-input {
    font-size: 16px !important;
    font-weight: bold;
    padding: 8px 12px !important;
    height: auto !important;
    min-width: 100px;
    text-align: right;
  }
  .prix-table th {
    font-size: 13px;
    background-color: #f5f5f5;
    font-weight: bold;
  }
  .prix-table td {
    vertical-align: middle !important;
    padding: 12px 8px !important;
  }
  .prix-calcule {
    font-size: 16px;
    font-weight: bold;
  }
  .btn-change-prix {
    padding: 8px 20px;
    font-size: 14px;
  }
</style>

<div class="section">
  <div class="container">
    <div class="row">
      <div class="col-md-12">
        
        <div class="page-header">
          <h1><i class="fa fa-euro"></i> <?php _e('admin.price_management'); ?></h1>
          <p class="lead"><?php _e('admin.price_management_desc'); ?></p>
        </div>
        
        <?php 
        // Afficher un tableau pour chaque machine (duplicopieur ou photocopieuse)
        foreach ($machines as $key => $val) 
        {
          if(count($val, COUNT_RECURSIVE)> 1)
          {
            // C'est un duplicopieur, créer un tableau séparé
            ?>
            <legend align="center"><?php _e('admin_prices.dupli_price'); ?> - <?= htmlspecialchars($key) ?></legend>
            
            <table id="example-<?= preg_replace('/[^a-zA-Z0-9]/', '_', $key) ?>" class="table table-striped table-bordered prix-table" cellspacing="0" width="100%">
              <thead>
                <th style="width:10%;"><?php _e('admin_prices.change'); ?></th><th style="width:13%;"><?php _e('admin_prices.current_nb'); ?></th><th style="width:13%;"><?php _e('admin_prices.average'); ?></th><th style="width:10%;"><?php _e('admin_prices.average_time'); ?></th><th style="width:10%;"><?php _e('admin_prices.next'); ?></th><th style="width:10%;"><?php _e('admin_prices.last'); ?></th><th style="width:10%;"><?php _e('admin_prices.calculated_price'); ?></th><th style="width:7%;"><?php _e('admin_prices.used_price'); ?></th><th style="width:7%;"><?php _e('admin_prices.purchase_price'); ?></th><th><?php _e('common.edit'); ?></th>
              </thead>
              <tbody>
                <?php 
                foreach ($val as $type) 
                {
                   if($type == 'encre'){ $mot =__("tirage_multimachines.passes_label"); $nb = 'nb_p';}
                   if($type == 'master'){ $mot =__("tirage_multimachines.masters_label"); $nb = 'nb_m';}
                   // Gérer les tambours spécifiques
                   if(strpos($type, 'tambour_') === 0){ $mot =__("tirage_multimachines.passes_label"); $nb = 'nb_p';}
                    ?>
                      <tr class="<?= isset($cons[$key][$type]['class']) ? $cons[$key][$type]['class'] : 'info' ?>">
                        <td><?=$type?> <?=$key?></td>
                        <td><?= isset($cons[$key][$type]['nb_actuel']) ? ceil($cons[$key][$type]['nb_actuel']) : 0 ?> <?php if(isset($mot)){ echo $mot;}?></td>
                        <td><?= isset($cons[$key][$type]['moyenne_totale'][$nb]) ? ceil($cons[$key][$type]['moyenne_totale'][$nb]) : 0 ?> <?=$mot?></td>
                        <td><?= isset($cons[$key][$type]['moyenne_totale']['temps']) ? ceil($cons[$key][$type]['moyenne_totale']['temps']/86400) : 0 ?> <?php _e('common.days'); ?></td>
                        <td><?= isset($cons[$key][$type]['temps_jusqua']) ? ceil($cons[$key][$type]['temps_jusqua']/86400) : 0 ?> <?php _e('common.days'); ?></td>
                        <td><?= isset($cons[$key][$type]['temps_depuis']) ? ceil($cons[$key][$type]['temps_depuis']/86400) : 0 ?> <?php _e('common.days'); ?></td>
                        <td style="color:<?= isset($cons[$key][$type]['color']) ? $cons[$key][$type]['color'] : 'black' ?>" class="prix-calcule"><?=  isset($cons[$key][$type]['prix_calcule']) ? round($cons[$key][$type]['prix_calcule'],4) : 0 ?> €</td>
                        <td><form method="post"><div class="form-group"><input class="form-control prix-input" name="prix_unite" value="<?= isset($prix[$key][$type]['unite']) ? round($prix[$key][$type]['unite'],4) : 0 ?>" /> </div></td>
                        <td><div class="form-group"><input class="form-control prix-input" name="prix_pack" value="<?= isset($prix[$key][$type]['pack']) ? round($prix[$key][$type]['pack'],4) : 0 ?>" /> </div></td>
                        <td><input type="hidden" value="<?=$key?>" name="machine" /><input type="hidden" value="<?=$type?>" name="type" /><button type="submit" class="btn btn-warning btn-change-prix"><i class="fa fa-check"></i> <?php _e('common.modify'); ?></button></form></td>
                      </tr>
                <?php 
                }
                ?>
              </tbody>
            </table>
            
            <br><br> <!-- Espacement entre les tableaux -->
            <?php
          }
          else 
          { 
            // C'est une photocopieuse, garder l'ancienne logique
            $mot = "passages";?>
            <legend align="center"><?php _e('admin_prices.photocop_price'); ?> - <?= htmlspecialchars($key) ?></legend>
            
            <table id="example-<?= preg_replace('/[^a-zA-Z0-9]/', '_', $key) ?>" class="table table-striped table-bordered prix-table" cellspacing="0" width="100%">
              <thead>
                <th style="width:10%;"><?php _e('admin_prices.change'); ?></th><th style="width:13%;"><?php _e('admin_prices.current_nb'); ?></th><th style="width:13%;"><?php _e('admin_prices.average'); ?></th><th style="width:10%;"><?php _e('admin_prices.average_time'); ?></th><th style="width:10%;"><?php _e('admin_prices.next'); ?></th><th style="width:10%;"><?php _e('admin_prices.last'); ?></th><th style="width:10%;"><?php _e('admin_prices.calculated_price'); ?></th><th style="width:7%;"><?php _e('admin_prices.used_price'); ?></th><th style="width:7%;"><?php _e('admin_prices.purchase_price'); ?></th><th><?php _e('common.edit'); ?></th>
              </thead>
              <tbody>
                <tr class="<?= $cons[$key][$key]['class'] ?>">
                  <td><?=$key?></td>
                  <td><?= ceil($cons[$key][$key]['nb_actuel'])?> </td>
                  <td><?= ceil($cons[$key][$key]['moyenne_total']['nb_p'])?> <?=$mot?></td>
                  <td><?= ceil($cons[$key][$key]['moyenne_total']['temps']/86400) ?> <?php _e('common.days'); ?></td>
                  <td><?= ceil($cons[$key][$key]['temps_jusqua']/86400)?> <?php _e('common.days'); ?></td>
                  <td><?= ceil($cons[$key][$key]['temps_depuis']/86400)?> <?php _e('common.days'); ?></td>
                  <td style="color:<?= isset($cons[$key][$key]['color']) ? $cons[$key][$key]['color'] : 'black' ?>;" class="prix-calcule"><?=  isset($cons[$key][$key]['prix_calcule']) ? round($cons[$key][$key]['prix_calcule'],4) : 0 ?> €</td>
                  <td><form method="post"><div class="form-group"><input class="form-control prix-input" name="prix_unite" value="<?= round(floatval($prix[$key]['encre']['unite'] ?? 0), 4)?>" /> </div></td>
                  <td><div class="form-group"><input type="hidden" value="encre" name="type" /><input type="hidden" value="photocop" name="machine" />  <input class="form-control prix-input" name="prix_pack" value="<?= round(floatval($prix[$key]['encre']['pack'] ?? 0), 4)?>" /> </div></td>
                  <td><button type="submit" class="btn btn-warning btn-change-prix"><i class="fa fa-check"></i> <?php _e('common.modify'); ?></button></form></td>
                </tr>
              </tbody>
            </table>
            
            <br><br> <!-- Espacement entre les tableaux -->
            <?php 
          } 
        } 
        ?>

        <!-- Photocopieurs avec consommables multiples -->
        <?php if(isset($cons['photocopieurs'])): ?>
          <?php foreach($cons['photocopieurs'] as $photocop_name => $photocop_data): ?>
            <legend align="center"><?php _e('admin_prices.photocop_price'); ?> - <?= htmlspecialchars($photocop_name) ?></legend>
            
            <table id="example-<?= preg_replace('/[^a-zA-Z0-9]/', '_', $photocop_name) ?>" class="table table-striped table-bordered prix-table" cellspacing="0" width="100%">
              <thead>
                <th style="width:10%;"><?php _e('admin_prices.change'); ?></th><th style="width:13%;"><?php _e('admin_prices.current_nb'); ?></th><th style="width:13%;"><?php _e('admin_prices.average'); ?></th><th style="width:10%;"><?php _e('admin_prices.average_time'); ?></th><th style="width:10%;"><?php _e('admin_prices.next'); ?></th><th style="width:10%;"><?php _e('admin_prices.last'); ?></th><th style="width:10%;"><?php _e('admin_prices.calculated_price'); ?></th><th style="width:7%;"><?php _e('admin_prices.used_price'); ?></th><th style="width:7%;"><?php _e('admin_prices.purchase_price'); ?></th><th><?php _e('common.edit'); ?></th>
              </thead>
              <tbody>
                <?php foreach($photocop_data as $color => $data): ?>
                  <tr class="<?= isset($data['class']) ? $data['class'] : 'info' ?>">
                    <td><?= htmlspecialchars($color) ?> <?= htmlspecialchars($photocop_name) ?></td>
                    <td><?= isset($data['nb_actuel']) ? ceil($data['nb_actuel']) : 0 ?></td>
                    <td><?= isset($data['moyenne_total']['nb_p']) ? ceil($data['moyenne_total']['nb_p']) : 0 ?> <?php _e('tirage_multimachines.passes_label'); ?></td>
                    <td><?= isset($data['moyenne_total']['temps']) ? ceil($data['moyenne_total']['temps']/86400) : 0 ?> <?php _e('common.days'); ?></td>
                    <td><?= isset($data['temps_jusqua']) ? ceil($data['temps_jusqua']/86400) : 0 ?> <?php _e('common.days'); ?></td>
                    <td><?= isset($data['temps_depuis']) ? ceil($data['temps_depuis']/86400) : 0 ?> <?php _e('common.days'); ?></td>
                    <td style="color:<?= isset($data['color']) ? $data['color'] : 'black' ?>;" class="prix-calcule"><?= isset($data['prix_calcule']) ? round($data['prix_calcule'],4) : 0 ?> €</td>
                    <td><form method="post"><div class="form-group"><input class="form-control prix-input" name="prix_unite" value="<?= round(floatval($prix[$photocop_name][$color]['unite'] ?? 0), 4)?>" /> </div></td>
                    <td><div class="form-group"><input type="hidden" value="<?= $color ?>" name="type" /><input type="hidden" value="photocop" name="machine" />  <input class="form-control prix-input" name="prix_pack" value="<?= round(floatval($prix[$photocop_name][$color]['pack'] ?? 0), 4)?>" /> </div></td>
                    <td><button type="submit" class="btn btn-warning btn-change-prix"><i class="fa fa-check"></i> <?php _e('common.modify'); ?></button></form></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            
            <br><br> <!-- Espacement entre les tableaux -->
          <?php endforeach; ?>
        <?php endif; ?>

        <!-- Section Prix du Papier -->
        <div class="row">
          <div class="col-md-10 col-md-offset-1">
            <legend align="center"><?php _e('admin_prices.paper_price'); ?></legend>
            
            <table class="table table-striped table-bordered prix-table" cellspacing="0" width="100%">
              <thead>
                <th style="width:20%;"><?php _e('admin_machines.type'); ?></th>
                <th style="width:20%;"><?php _e('admin_prices.current_price_label'); ?></th>
                <th style="width:20%;"><?php _e('admin_prices.new_price_label'); ?></th>
                <th style="width:20%;"><?php _e('admin_prices.ramette_price'); ?></th>
                <th style="width:20%;"><?php _e('admin_machines.actions'); ?></th>
              </thead>
              <tbody>
                <tr>
                  <td>A4 (<?php _e('tirage_multimachines.sheets'); ?>)</td>
                  <td><?= isset($prix['papier']['A4']) ? number_format($prix['papier']['A4'], 3) : '0.000' ?></td>
                  <td>
                    <form method="post" style="display: inline;">
                      <div class="form-group">
                        <input type="number" step="0.001" class="form-control prix-input" name="papier_A4" id="papier_A4"
                               value="<?= isset($prix['papier']['A4']) ? $prix['papier']['A4'] : '0.01' ?>" 
                               min="0" max="1" required>
                      </div>
                  </td>
                  <td>
                      <div class="form-group">
                        <input type="number" step="0.01" class="form-control prix-input" id="ramette_A4"
                               value="<?= isset($prix['papier']['A4']) ? number_format($prix['papier']['A4'] * 500, 2, '.', '') : '5.00' ?>" 
                               min="0" required>
                      </div>
                  </td>
                  <td>
                    <button type="submit" class="btn btn-warning btn-sm"><?php _e('common.modify'); ?></button>
                    </form>
                  </td>
                </tr>
                <tr>
                  <td>A3 (<?php _e('tirage_multimachines.sheets'); ?>)</td>
                  <td><?= isset($prix['papier']['A3']) ? number_format($prix['papier']['A3'], 3) : '0.000' ?></td>
                  <td>
                    <form method="post" style="display: inline;">
                      <div class="form-group">
                        <input type="number" step="0.001" class="form-control prix-input" name="papier_A3" id="papier_A3"
                               value="<?= isset($prix['papier']['A3']) ? $prix['papier']['A3'] : '0.02' ?>" 
                               min="0" max="1" required>
                      </div>
                  </td>
                  <td>
                      <div class="form-group">
                        <input type="number" step="0.01" class="form-control prix-input" id="ramette_A3"
                               value="<?= isset($prix['papier']['A3']) ? number_format($prix['papier']['A3'] * 500, 2, '.', '') : '10.00' ?>" 
                               min="0" required>
                      </div>
                  </td>
                  <td>
                    <button type="submit" class="btn btn-warning btn-sm"><?php _e('common.modify'); ?></button>
                    </form>
                  </td>
                </tr>
              </tbody>
            </table>
            
            <div class="alert alert-info">
              <strong>Info:</strong> <?php _e('admin_prices.paper_price_info'); ?>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    function setupSync(idFeuille, idRamette) {
      var inputFeuille = document.getElementById(idFeuille);
      var inputRamette = document.getElementById(idRamette);
      if(inputFeuille && inputRamette) {
        inputFeuille.addEventListener('input', function() {
          var val = parseFloat(this.value);
          if(!isNaN(val)) {
            inputRamette.value = (val * 500).toFixed(2);
          } else {
            inputRamette.value = '';
          }
        });
        inputRamette.addEventListener('input', function() {
          var val = parseFloat(this.value);
          if(!isNaN(val)) {
            inputFeuille.value = (val / 500).toFixed(3);
          } else {
            inputFeuille.value = '';
          }
        });
      }
    }
    setupSync('papier_A4', 'ramette_A4');
    setupSync('papier_A3', 'ramette_A3');
  });
</script>
