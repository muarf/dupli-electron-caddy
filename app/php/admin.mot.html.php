<div class="section">
  <div class="container">
    <div class="row">
      <div class="col-md-10 col-md-offset-1">
        <div class="row">
 <div class="col-md-4"><h2> Mots photocop</h2><table class="table"><thead>
            <tr>
            <th>contact</th>
            <th>contact</th>
            <th>mot</th>
            <th>edit</th></tr>
            </thead><tbody>

        <?php 
      $nb_mots = count($mots['photocop']);
      for ($i = 0; $i < $nb_mots ;$i++ )
      {?>
           <tr><td><?= $mots['photocop'][$i]['date'] ?></td>
                 <td><?= $mots['photocop'][$i]['contact']?></td>
                <td><?= $mots['photocop'][$i]['mot']?></td>
                <td><a href="?edit=<?= $mots['photocop'][$i]['id'] ?>&table=photocop">Edit</a></td></tr>
      <?php } ?>
                 
       </tbody></table></div><div class="col-md-4"><h2> Mots dupli A4</h2><table class="table"><thead>
            <tr>
            <th>contact</th>
            <th>contact</th>
            <th>mot</th>
            <th>edit</th></tr>
            </thead><tbody>
        <?php 
      $nb_mots = count($mots['A4']);
      for ($i = 0; $i < $nb_mots ;$i++ )
      {?>
           <tr><td><?= $mots['A4'][$i]['date'] ?></td>
                 <td><?= $mots['A4'][$i]['contact']?></td>
                <td><?= $mots['A4'][$i]['mot']?></td>
                <td><a href="?edit=<?= $mots['A4'][$i]['id'] ?>&table=A4">Edit</a></td></tr>
      <?php } ?>
        </tbody></table></div><div class="col-md-4"><h2> Mots dupli A3</h2><table class="table"><thead>
            <tr>
            <th>contact</th>
            <th>contact</th>
            <th>mot</th>
            <th>edit</th></tr>
            </thead><tbody>
      <?php 
      $nb_mots = count($mots['A3']);
      for ($i = 0; $i < $nb_mots ;$i++ )
      {?>
           <tr><td><?= $mots['A3'][$i]['date'] ?></td>
                 <td><?= $mots['A3'][$i]['contact']?></td>
                <td><?= $mots['A3'][$i]['mot']?></td>
                <td><a href="?edit=<?= $mots['A3'][$i]['id'] ?>&table=A3">Edit</a></td></tr>
      <?php } ?>
        </tbody></table></div>
        </div>
      </div>
    </div>
  </div>