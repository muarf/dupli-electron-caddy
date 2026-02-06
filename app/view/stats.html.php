<div class="section">
  <div class="row">
    <div class="col-md-12">
        <h1 class="text-center"><?php _e('stats.title'); ?></h1>
        <div class="alert alert-info">
        <?php 
        $stats_text = get_site_setting('stats_intro_text', __('stats.intro_text'));
        
        $stats_text = str_replace('{nb_f}', '<strong>' . ceil($stats['nb_f'] ?? 0) . ' ' . __('stats.pages') . '</strong>', $stats_text);
        $stats_text = str_replace('{nb_t}', '<strong>' . ($stats['nb_t'] ?? 0) . ' ' . __('stats.times') . '</strong>', $stats_text);
        $stats_text = str_replace('{nb_t_par_mois}', '<strong>' . ($stats['nb_t_par_mois'] ?? 0) . ' ' . __('stats.printings') . '</strong>', $stats_text);
        $stats_text = str_replace('{nbf_par_mois}', '<strong>' . ceil($stats['nbf_par_mois'] ?? 0) . ' ' . __('stats.sheets') . '</strong>', $stats_text);
        $stats_text = str_replace('{nb_moy_par_mois}', '<strong>' . ($stats['nb_moy_par_mois'] ?? 0) . ' ' . __('stats.copies') . '</strong>', $stats_text);
        $stats_text = str_replace('{ca}', round($stats['ca'] ?? 0) . ' euros', $stats_text);
        $stats_text = str_replace('{doit}', '<strong><font style="color:red;">' . round($stats['doit'] ?? 0) . ' ' . __('stats.euros') . '</font></strong>', $stats_text);
        $stats_text = str_replace('{ca2}', round($stats['ca2'] ?? 0) . ' euros', $stats_text);
        $stats_text = str_replace('{ca1}', round($stats['ca1'] ?? 0) . ' euros', $stats_text);
        $stats_text = str_replace('{benf}', ($stats['benf'] ?? 0) . '€', $stats_text);
        
        echo $stats_text;
        ?>
        </div>
        

<?php if(isset($duplicopieurs_installes) && !empty($duplicopieurs_installes)): ?>
  <?php foreach($duplicopieurs_installes as $duplicop): ?>
    <?php 
    $machine_name = $duplicop['marque'];
    if ($duplicop['marque'] !== $duplicop['modele']) {
        $machine_name = $duplicop['marque'] . ' ' . $duplicop['modele'];
    }
    ?>
    <div class="col-md-6">
      <div class="panel panel-info">
        <div class="panel-heading">
          <h3 class="panel-title text-center">
            <i class="fa fa-bar-chart"></i> <?php _e('stats.monthly_stats_for'); ?> <?= htmlspecialchars($machine_name) ?>
          </h3>
        </div>
        <div class="panel-body">
          <table class="table table-striped table-hover table-bordered">
        <thead class="thead-dark">
          <tr>
            <th><i class="fa fa-calendar"></i> <?php _e('stats.date'); ?></th>
            <th><i class="fa fa-file-o"></i> <?php _e('stats.sheets_column'); ?></th>
            <th><i class="fa fa-print"></i> <?php _e('stats.printings_column'); ?></th>
            <th><i class="fa fa-calculator"></i> <?php _e('stats.average'); ?></th>
            <th><i class="fa fa-euro"></i> <?php _e('stats.price'); ?></th>
            <th><i class="fa fa-money"></i> <?php _e('stats.price_paid'); ?></th>
            <th><i class="fa fa-chart-line"></i> <?php _e('stats.difference'); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php
          $page_param = 'page' . strtolower(str_replace(' ', '_', $machine_name));
          if(isset($stat['duplicopieurs'][$machine_name]['stat']) && !empty($stat['duplicopieurs'][$machine_name]['stat'])):
            for($i = $stat['duplicopieurs'][$machine_name]['ii'];$i < $stat['duplicopieurs'][$machine_name]['fin']  ; $i++ )
            { 
                if(isset($stat['duplicopieurs'][$machine_name]['stat'][$i]['benef']) && $stat['duplicopieurs'][$machine_name]['stat'][$i]['benef']>=0)  { $class = "success";}else{$class = "danger";}
                $date = date('m.y',$stat['duplicopieurs'][$machine_name]['stat'][$i]['ago']); ?>
               <tr class="<?= $class ?>">
                        <td><?=$date?></td>
                        
                        <td rel="tooltip" title="<?php echo __('stats.pages'); ?>"><?=ceil($stat['duplicopieurs'][$machine_name]['stat'][$i]['nbf'] ?? 0)?></td>
                        <td rel="tooltip" title="<?php echo __('stats.printings'); ?>"><?=$stat['duplicopieurs'][$machine_name]['stat'][$i]['nbt'] ?? 0?></td>
                        <td rel="tooltip" title="<?php echo __('stats.average'); ?>"><?=ceil($stat['duplicopieurs'][$machine_name]['stat'][$i]['moy'] ?? 0)?></td>
                        <td rel="tooltip" title="<?php echo __('stats.cost_price'); ?>"><?=ceil($stat['duplicopieurs'][$machine_name]['stat'][$i]['prix'] ?? 0)?>€</td>
                        <td rel="tooltip" title="<?php echo __('stats.user_paid'); ?>"><?=ceil($stat['duplicopieurs'][$machine_name]['stat'][$i]['prixpaye'] ?? 0)?>€</td>
                        <td rel="tooltip" title="<?php echo __('stats.gain_for_month'); ?>"><?=ceil($stat['duplicopieurs'][$machine_name]['stat'][$i]['benef'] ?? 0)?>€</td>
                     </tr>
            <?php
            }
          else:
            echo '<tr><td colspan="7" class="text-center">' . __('stats.no_data') . '</td></tr>';
          endif;
          $iii = 1; ?>
            <ul class="pagination">
              <?php while($iii < ($stat['duplicopieurs'][$machine_name]['nb_page'] ?? 0) ) {?>
              <li><a href="?stats&<?=$page_param?>=<?=$iii?>"><?= $iii ?></a></li>
              <?php $iii++;}?>
            </ul>
            </tbody>
          </table>
          <?php $iii = 1;?>
          <nav aria-label="Navigation des pages">
            <ul class="pagination pagination-sm">
            <?php while($iii < ($stat['duplicopieurs'][$machine_name]['nb_page'] ?? 0)) { ?>
              <li><a href="?stats&<?=$page_param?>=<?= $iii?>"><?= $iii?></a></li>
            <?php $iii++;} ?>
            </ul>
          </nav>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
<?php else: ?>
  <div class="col-md-6">
    <div class="panel panel-warning">
      <div class="panel-heading">
        <h3 class="panel-title text-center">
          <i class="fa fa-exclamation-triangle"></i> <?php _e('stats.monthly_stats'); ?> <?php _e('tirage_multimachines.duplicopieur'); ?>
        </h3>
      </div>
      <div class="panel-body">
        <table class="table table-striped table-bordered">
          <thead class="thead-dark">
            <tr>
              <th><i class="fa fa-calendar"></i> <?php _e('stats.date'); ?></th>
              <th><i class="fa fa-file-o"></i> <?php _e('stats.sheets_column'); ?></th>
              <th><i class="fa fa-print"></i> <?php _e('stats.printings_column'); ?></th>
              <th><i class="fa fa-calculator"></i> <?php _e('stats.average'); ?></th>
              <th><i class="fa fa-euro"></i> <?php _e('stats.price'); ?></th>
              <th><i class="fa fa-money"></i> <?php _e('stats.price_paid'); ?></th>
              <th><i class="fa fa-chart-line"></i> <?php _e('stats.difference'); ?></th>
            </tr>
          </thead>
          <tbody>
            <tr class="warning"><td colspan="7" class="text-center"><i class="fa fa-info-circle"></i> <?php _e('stats.no_duplicator_installed'); ?></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php if(isset($photocopiers_installes) && !empty($photocopiers_installes)): ?>
  <?php foreach($photocopiers_installes as $photocop_name): ?>
    <div class="col-md-6">
      <div class="panel panel-success">
        <div class="panel-heading">
          <h3 class="panel-title text-center">
            <i class="fa fa-print"></i> <?php _e('stats.monthly_stats'); ?> <?= htmlspecialchars($photocop_name) ?>
          </h3>
        </div>
        <div class="panel-body">
          <table class="table table-striped table-hover table-bordered">
        <thead class="thead-dark">
          <tr>
            <th><i class="fa fa-calendar"></i> <?php _e('stats.date'); ?></th>
            <th><i class="fa fa-file-o"></i> <?php _e('stats.sheets_column'); ?></th>
            <th><i class="fa fa-print"></i> <?php _e('stats.printings_column'); ?></th>
            <th><i class="fa fa-calculator"></i> <?php _e('stats.average'); ?></th>
            <th><i class="fa fa-euro"></i> <?php _e('stats.price'); ?></th>
            <th><i class="fa fa-money"></i> <?php _e('stats.price_paid'); ?></th>
            <th><i class="fa fa-chart-line"></i> <?php _e('stats.difference'); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php
          $page_param = 'page' . strtolower(str_replace(' ', '_', $photocop_name));
          if(isset($stat['photocopiers'][$photocop_name]['stat']) && !empty($stat['photocopiers'][$photocop_name]['stat'])):
            for($i = $stat['photocopiers'][$photocop_name]['ii'];$i < $stat['photocopiers'][$photocop_name]['fin']  ; $i++ )
            { 
                if(isset($stat['photocopiers'][$photocop_name]['stat'][$i]['benef']) && $stat['photocopiers'][$photocop_name]['stat'][$i]['benef']>=0)  { $class = "success";}else{$class = "danger";}
                $date = date('m.y',$stat['photocopiers'][$photocop_name]['stat'][$i]['ago']); ?>
               <tr class="<?= $class ?>">
                        <td><?=$date?></td>
                        
                        <td rel="tooltip" title="<?php echo __('stats.pages'); ?>"><?=ceil($stat['photocopiers'][$photocop_name]['stat'][$i]['nbf'] ?? 0)?></td>
                        <td rel="tooltip" title="<?php echo __('stats.printings'); ?>"><?=$stat['photocopiers'][$photocop_name]['stat'][$i]['nbt'] ?? 0?></td>
                        <td rel="tooltip" title="<?php echo __('stats.average'); ?>"><?=ceil($stat['photocopiers'][$photocop_name]['stat'][$i]['moy'] ?? 0)?></td>
                        <td rel="tooltip" title="<?php echo __('stats.cost_price'); ?>"><?=ceil($stat['photocopiers'][$photocop_name]['stat'][$i]['prix'] ?? 0)?>€</td>
                        <td rel="tooltip" title="<?php echo __('stats.user_paid'); ?>"><?=ceil($stat['photocopiers'][$photocop_name]['stat'][$i]['prixpaye'] ?? 0)?>€</td>
                        <td rel="tooltip" title="<?php echo __('stats.gain_for_month'); ?>"><?=ceil($stat['photocopiers'][$photocop_name]['stat'][$i]['benef'] ?? 0)?>€</td>
                     </tr>
            <?php
            }
          else:
            echo '<tr><td colspan="7" class="text-center">' . __('stats.no_data') . '</td></tr>';
          endif;
          $iii = 1; ?>
            <ul class="pagination">
              <?php while($iii < ($stat['photocopiers'][$photocop_name]['nb_page'] ?? 0) ) {?>
              <li><a href="?stats&<?=$page_param?>=<?=$iii?>"><?= $iii ?></a></li>
              <?php $iii++;}?>
            </ul>
            </tbody>
          </table>
          <?php $iii = 1;?>
          <nav aria-label="Navigation des pages">
            <ul class="pagination pagination-sm">
            <?php while($iii < ($stat['photocopiers'][$photocop_name]['nb_page'] ?? 0)) { ?>
              <li><a href="?stats&<?=$page_param?>=<?= $iii?>"><?= $iii?></a></li>
            <?php $iii++;} ?>
            </ul>
          </nav>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

    </div>
  </div>


