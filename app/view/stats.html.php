<div class="section">
  <div class="container">
    <div class="row">
      <div class="col-md-10 col-md-offset-1">
        <h1 class="text-center">Statistiques</h1>
        <div class="alert alert-info">
        <?php 
        $stats_text = get_site_setting('stats_intro_text', 'Depuis le debut de l\'aventure dupli en 2011, nous avons tire un total de {nb_f} pages en plus de {nb_t} fois. Si l\'on regarde de plus pret ca nous fait une moyenne de {nb_t_par_mois} tirages par mois, avec environ {nbf_par_mois} feuilles en moyenne. Vous tirez {nb_moy_par_mois} copies par tirage. Je ne vous epargne pas le chiffre d\'affaire : {ca} euros depuis le debut, si l\'on enleve les {doit} euros que l\'on nous doit : {ca2} euros. Vous nous avez donne {ca1} euros. Nous sommes donc {benf} euros, mais c\'est sans compter le prix du loyer des condos qui a raison de 50 euros par mois nous ferai... ! Le big data est la, je vous laisse regarder :) ps : et c\'est aussi 1800 lignes de code !');
        
        $stats_text = str_replace('{nb_f}', '<strong>' . ceil($stats['nb_f'] ?? 0) . ' pages</strong>', $stats_text);
        $stats_text = str_replace('{nb_t}', '<strong>' . ($stats['nb_t'] ?? 0) . ' fois</strong>', $stats_text);
        $stats_text = str_replace('{nb_t_par_mois}', '<strong>' . ($stats['nb_t_par_mois'] ?? 0) . ' tirages</strong>', $stats_text);
        $stats_text = str_replace('{nbf_par_mois}', '<strong>' . ceil($stats['nbf_par_mois'] ?? 0) . ' feuilles</strong>', $stats_text);
        $stats_text = str_replace('{nb_moy_par_mois}', '<strong>' . ($stats['nb_moy_par_mois'] ?? 0) . ' copies</strong>', $stats_text);
        $stats_text = str_replace('{ca}', round($stats['ca'] ?? 0) . ' euros', $stats_text);
        $stats_text = str_replace('{doit}', '<strong><font style="color:red;">' . round($stats['doit'] ?? 0) . ' euros</font></strong>', $stats_text);
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
      <h3 class="well well-sm"><center> Statistiques par mois <?= htmlspecialchars($machine_name) ?></center></h3>
      <table class="table">
        <thead>
          <tr>
            <th>date</th>
            <th>feuilles </th>
            <th>tirages</th>
            <th>moyenne</th>
            <th>Prix</th>
            <th>Prix payé</th>
            <th>difference</th>
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
                        
                        <td rel="tooltip" title="nombre de feuilles"><?=ceil($stat['duplicopieurs'][$machine_name]['stat'][$i]['nbf'] ?? 0)?></td>
                        <td rel="tooltip" title="nombre de tirages"><?=$stat['duplicopieurs'][$machine_name]['stat'][$i]['nbt'] ?? 0?></td>
                        <td rel="tooltip" title="nombre de feuilles par tirage"><?=ceil($stat['duplicopieurs'][$machine_name]['stat'][$i]['moy'] ?? 0)?></td>
                        <td rel="tooltip" title="Prix coutant"><?=ceil($stat['duplicopieurs'][$machine_name]['stat'][$i]['prix'] ?? 0)?>€</td>
                        <td rel="tooltip" title="Combien l'utilisateur a payé"><?=ceil($stat['duplicopieurs'][$machine_name]['stat'][$i]['prixpaye'] ?? 0)?>€</td>
                        <td rel="tooltip" title="Gain pour ce mois"><?=ceil($stat['duplicopieurs'][$machine_name]['stat'][$i]['benef'] ?? 0)?>€</td>
                     </tr>
            <?php
            }
          else:
            echo '<tr><td colspan="7" class="text-center">Aucune donnée disponible</td></tr>';
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
          <ul class="pagination">
          <?php while($iii < ($stat['duplicopieurs'][$machine_name]['nb_page'] ?? 0)) { ?>
            <li><a href="?stats&<?=$page_param?>=<?= $iii?>"><?= $iii?></a></li>
          <?php $iii++;} ?>
          </ul>
        </div>
  <?php endforeach; ?>
<?php else: ?>
  <div class="col-md-6">
    <h3 class="well well-sm"><center> Statistiques par mois Duplicopieur</center></h3>
    <table class="table">
      <thead>
        <tr>
          <th>date</th>
          <th>feuilles </th>
          <th>tirages</th>
          <th>moyenne</th>
          <th>Prix</th>
          <th>Prix payé</th>
          <th>difference</th>
        </tr>
      </thead>
      <tbody>
        <tr><td colspan="7" class="text-center">Aucun duplicopieur installé</td></tr>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php if(isset($photocopiers_installes) && !empty($photocopiers_installes)): ?>
  <?php foreach($photocopiers_installes as $photocop_name): ?>
    <div class="col-md-6">
      <h3 class="well well-sm"><center> Statistiques par mois <?= htmlspecialchars($photocop_name) ?></center></h3>
      <table class="table">
        <thead>
          <tr>
            <th>date</th>
            <th>feuilles </th>
            <th>tirages</th>
            <th>moyenne</th>
            <th>Prix</th>
            <th>Prix payé</th>
            <th>difference</th>
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
                        
                        <td rel="tooltip" title="nombre de feuilles"><?=ceil($stat['photocopiers'][$photocop_name]['stat'][$i]['nbf'] ?? 0)?></td>
                        <td rel="tooltip" title="nombre de tirages"><?=$stat['photocopiers'][$photocop_name]['stat'][$i]['nbt'] ?? 0?></td>
                        <td rel="tooltip" title="nombre de feuilles par tirage"><?=ceil($stat['photocopiers'][$photocop_name]['stat'][$i]['moy'] ?? 0)?></td>
                        <td rel="tooltip" title="Prix coutant"><?=ceil($stat['photocopiers'][$photocop_name]['stat'][$i]['prix'] ?? 0)?>€</td>
                        <td rel="tooltip" title="Combien l'utilisateur a payé"><?=ceil($stat['photocopiers'][$photocop_name]['stat'][$i]['prixpaye'] ?? 0)?>€</td>
                        <td rel="tooltip" title="Gain pour ce mois"><?=ceil($stat['photocopiers'][$photocop_name]['stat'][$i]['benef'] ?? 0)?>€</td>
                     </tr>
            <?php
            }
          else:
            echo '<tr><td colspan="7" class="text-center">Aucune donnée disponible</td></tr>';
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
          <ul class="pagination">
          <?php while($iii < ($stat['photocopiers'][$photocop_name]['nb_page'] ?? 0)) { ?>
            <li><a href="?stats&<?=$page_param?>=<?= $iii?>"><?= $iii?></a></li>
          <?php $iii++;} ?>
          </ul>
        </div>
  <?php endforeach; ?>
<?php endif; ?>

      </div>
    </div>
  </div>


