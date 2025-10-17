<div class="section">
  <div class="container">
    <div class="row">
      <div class="col-md-10 col-md-offset-1">
        <h1 class="text-center"><?php _e('admin_mot.title'); ?></h1>
        <hr>
        
        <div class="row">
 <div class="col-md-4"><h2><?php _e('admin_mot.photocop_messages'); ?></h2><table class="table table-striped"><thead>
            <tr>
            <th><?php _e('admin_mot.date'); ?></th>
            <th><?php _e('admin_mot.contact'); ?></th>
            <th><?php _e('admin_mot.message'); ?></th>
            <th><?php _e('admin_mot.edit'); ?></th></tr>
            </thead><tbody>

        <?php 
      $nb_mots = count($mots['photocop']);
      for ($i = 0; $i < $nb_mots ;$i++ )
      {?>
           <tr><td><?= $mots['photocop'][$i]['date'] ?></td>
                 <td><?= $mots['photocop'][$i]['contact']?></td>
                <td><?= $mots['photocop'][$i]['mot']?></td>
                <td><a href="?edit=<?= $mots['photocop'][$i]['id'] ?>&table=photocop" class="btn btn-sm btn-primary"><?php _e('admin_mot.edit'); ?></a></td></tr>
      <?php } ?>
                 
       </tbody></table></div><div class="col-md-4"><h2><?php _e('admin_mot.dupli_a4_messages'); ?></h2><table class="table table-striped"><thead>
            <tr>
            <th><?php _e('admin_mot.date'); ?></th>
            <th><?php _e('admin_mot.contact'); ?></th>
            <th><?php _e('admin_mot.message'); ?></th>
            <th><?php _e('admin_mot.edit'); ?></th></tr>
            </thead><tbody>
        <?php 
      $nb_mots = count($mots['A4']);
      for ($i = 0; $i < $nb_mots ;$i++ )
      {?>
           <tr><td><?= $mots['A4'][$i]['date'] ?></td>
                 <td><?= $mots['A4'][$i]['contact']?></td>
                <td><?= $mots['A4'][$i]['mot']?></td>
                <td><a href="?edit=<?= $mots['A4'][$i]['id'] ?>&table=A4" class="btn btn-sm btn-primary"><?php _e('admin_mot.edit'); ?></a></td></tr>
      <?php } ?>
        </tbody></table></div><div class="col-md-4"><h2><?php _e('admin_mot.dupli_a3_messages'); ?></h2><table class="table table-striped"><thead>
            <tr>
            <th><?php _e('admin_mot.date'); ?></th>
            <th><?php _e('admin_mot.contact'); ?></th>
            <th><?php _e('admin_mot.message'); ?></th>
            <th><?php _e('admin_mot.edit'); ?></th></tr>
            </thead><tbody>
      <?php 
      $nb_mots = count($mots['A3']);
      for ($i = 0; $i < $nb_mots ;$i++ )
      {?>
           <tr><td><?= $mots['A3'][$i]['date'] ?></td>
                 <td><?= $mots['A3'][$i]['contact']?></td>
                <td><?= $mots['A3'][$i]['mot']?></td>
                <td><a href="?edit=<?= $mots['A3'][$i]['id'] ?>&table=A3" class="btn btn-sm btn-primary"><?php _e('admin_mot.edit'); ?></a></td></tr>
      <?php } ?>
        </tbody></table></div>
        </div>
      </div>
    </div>
  </div>