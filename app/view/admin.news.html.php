<?php
// Messages de succès
if(isset($_POST['titre']) && !isset($_POST['id2']))
{
  ?>
  <div class="alert alert-success alert-dismissible fade in">
    <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
    <strong>✅ Succès !</strong> La news a été ajoutée avec succès.
  </div>
  <?php
}
elseif(isset($_POST['id2']))
{
  ?>
  <div class="alert alert-success alert-dismissible fade in">
    <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
    <strong>✅ Succès !</strong> La news a été modifiée avec succès.
  </div>
  <?php
}
elseif(isset($_POST['id']) && isset($_POST['singlebutton']))
{
  ?>
  <div class="alert alert-success alert-dismissible fade in">
    <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
    <strong>✅ Succès !</strong> La news a été supprimée avec succès.
  </div>
  <?php
}

// Formulaire d'édition
if(isset($_POST['id']) && !isset($_POST['singlebutton']))
{
  ?>
  <!-- Quill.js CSS -->
  <link href="js/quill/quill.snow.css" rel="stylesheet">
  <!-- Quill.js JS -->
  <script src="js/quill/quill.min.js"></script>
  
  <div class="section">
    <div class="container">
      <div class="row">
        <div class="col-md-12">
          <div class="panel panel-primary">
            <div class="panel-heading">
              <h3 class="panel-title"><i class="fa fa-edit"></i> Modifier une News</h3>
            </div>
            <div class="panel-body">
              <form method="post" action="?admin&news" id="news-form-edit">
                <input type="hidden" value="<?= $new_edit->id ?>" name="id2" />
                <input type="hidden" name="texte" id="texte-hidden-edit" value="">
                
                <div class="form-group">
                  <label for="titre"><strong>Titre de la news :</strong></label>
                  <input type="text" id="titre" name="titre" value="<?= htmlspecialchars($new_edit->titre) ?>" 
                         class="form-control input-lg" placeholder="Entrez le titre de votre news..." required>
                </div>
                
                <div class="form-group">
                  <label for="editor-edit"><strong>Contenu de la news :</strong></label>
                  <div id="editor-edit" style="height: 400px; margin-bottom: 10px;"><?= $new_edit->news ?></div>
                </div>
                
                <div class="form-group">
                  <button type="submit" name="save" class="btn btn-primary btn-lg">
                    <i class="fa fa-save"></i> Sauvegarder les modifications
                  </button>
                  <a href="?admin&news" class="btn btn-default btn-lg">
                    <i class="fa fa-arrow-left"></i> Retour à la liste
                  </a>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  
  <script>
  // Initialiser Quill.js pour l'édition
  var quillEdit = new Quill('#editor-edit', {
      theme: 'snow',
      modules: {
          toolbar: [
              [{ 'header': [1, 2, 3, false] }],
              ['bold', 'italic', 'underline', 'strike'],
              [{ 'color': [] }, { 'background': [] }],
              [{ 'list': 'ordered'}, { 'list': 'bullet' }],
              [{ 'align': [] }],
              ['link', 'image'],
              ['clean']
          ]
      },
      placeholder: 'Rédigez le contenu de votre news...'
  });
  
  // Gestionnaire d'événement pour synchroniser le contenu après chaque modification
  quillEdit.on('text-change', function() {
      var content = quillEdit.root.innerHTML;
      document.getElementById('texte-hidden-edit').value = content;
  });
  
  // Mettre à jour le champ caché avant soumission
  document.getElementById('news-form-edit').addEventListener('submit', function(e) {
      // Forcer la synchronisation finale
      var content = quillEdit.root.innerHTML;
      document.getElementById('texte-hidden-edit').value = content;
      
      // Debug: vérifier le contenu
      console.log('Contenu à sauvegarder:', content);
  });
  </script>
  <?php
}
// Formulaire de création
elseif($_GET['news'] == "add")
{
  ?>
  <!-- Quill.js CSS -->
  <link href="js/quill/quill.snow.css" rel="stylesheet">
  <!-- Quill.js JS -->
  <script src="js/quill/quill.min.js"></script>
  
  <div class="section">
    <div class="container">
      <div class="row">
        <div class="col-md-12">
          <div class="panel panel-success">
            <div class="panel-heading">
              <h3 class="panel-title"><i class="fa fa-plus"></i> Créer une nouvelle News</h3>
            </div>
            <div class="panel-body">
              <form method="post" action="?admin&news" id="news-form-create">
                <input type="hidden" name="texte" id="texte-hidden-create" value="">
                
                <div class="form-group">
                  <label for="titre"><strong>Titre de la news :</strong></label>
                  <input type="text" id="titre" name="titre" value="" 
                         class="form-control input-lg" placeholder="Entrez le titre de votre news..." required>
                </div>
                
                <div class="form-group">
                  <label for="editor-create"><strong>Contenu de la news :</strong></label>
                  <div id="editor-create" style="height: 400px; margin-bottom: 10px;"></div>
                </div>
                
                <div class="form-group">
                  <button type="submit" name="save" class="btn btn-success btn-lg">
                    <i class="fa fa-plus"></i> Créer la news
                  </button>
                  <a href="?admin&news" class="btn btn-default btn-lg">
                    <i class="fa fa-arrow-left"></i> Retour à la liste
                  </a>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  
  <script>
  // Initialiser Quill.js pour la création
  var quillCreate = new Quill('#editor-create', {
      theme: 'snow',
      modules: {
          toolbar: [
              [{ 'header': [1, 2, 3, false] }],
              ['bold', 'italic', 'underline', 'strike'],
              [{ 'color': [] }, { 'background': [] }],
              [{ 'list': 'ordered'}, { 'list': 'bullet' }],
              [{ 'align': [] }],
              ['link', 'image'],
              ['clean']
          ]
      },
      placeholder: 'Rédigez le contenu de votre news...'
  });
  
  // Gestionnaire d'événement pour synchroniser le contenu après chaque modification
  quillCreate.on('text-change', function() {
      var content = quillCreate.root.innerHTML;
      document.getElementById('texte-hidden-create').value = content;
  });
  
  // Mettre à jour le champ caché avant soumission
  document.getElementById('news-form-create').addEventListener('submit', function(e) {
      // Forcer la synchronisation finale
      var content = quillCreate.root.innerHTML;
      document.getElementById('texte-hidden-create').value = content;
      
      // Debug: vérifier le contenu
      console.log('Contenu à sauvegarder:', content);
  });
  </script>
  <?php
}
// Liste des news
else
{
  ?>
  <div class="section">
    <div class="container">
      <div class="row">
        <div class="col-md-12">
          <div class="panel panel-info">
            <div class="panel-heading">
              <h3 class="panel-title">
                <i class="fa fa-newspaper-o"></i> Gestion des Infos
                <a href="?admin&news=add" class="btn btn-success btn-sm pull-right">
                  <i class="fa fa-plus"></i> Créer une nouvelle News
                </a>
              </h3>
            </div>
            <div class="panel-body">
              <?php if(isset($news) && count($news) > 0): ?>
                <div class="table-responsive">
                  <table class="table table-striped table-hover">
                    <thead>
                      <tr>
                        <th width="15%"><i class="fa fa-calendar"></i> Date</th>
                        <th width="25%"><i class="fa fa-header"></i> Titre</th>
                        <th width="45%"><i class="fa fa-file-text"></i> Contenu</th>
                        <th width="15%"><i class="fa fa-cogs"></i> Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php for($i=0; $i < count($news); $i++): ?>
                        <tr>
                          <td>
                            <span class="label label-info">
                              <i class="fa fa-clock-o"></i> <?= $news[$i]['temps'] ?>
                            </span>
                          </td>
                          <td>
                            <strong><?= htmlspecialchars($news[$i]['titre']) ?></strong>
                          </td>
                          <td>
                            <div class="text-muted">
                              <?= htmlspecialchars(substr(strip_tags($news[$i]['news']), 0, 100)) ?>
                              <?= strlen(strip_tags($news[$i]['news'])) > 100 ? '...' : '' ?>
                            </div>
                          </td>
                          <td>
                            <form method="post" style="display: inline;">
                              <input type="hidden" value="<?= $news[$i]['id'] ?>" name="id"/>
                              <button type="submit" name="edit" class="btn btn-info btn-sm" title="Modifier">
                                <i class="fa fa-edit"></i>
                              </button>
                              <button type="submit" name="singlebutton" class="btn btn-danger btn-sm" 
                                      onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette news ?')" 
                                      title="Supprimer">
                                <i class="fa fa-trash"></i>
                              </button>
                            </form>
                          </td>
                        </tr>
                      <?php endfor; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <div class="text-center text-muted">
                  <i class="fa fa-newspaper-o fa-3x"></i>
                  <h4>Aucune news pour le moment</h4>
                  <p>Créez votre première news pour commencer !</p>
                  <a href="?admin&news=add" class="btn btn-success btn-lg">
                    <i class="fa fa-plus"></i> Créer une news
                  </a>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php
}
?>