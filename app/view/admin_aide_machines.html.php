<!-- CSS pour l'éditeur WYSIWYG -->
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote.min.css" rel="stylesheet">

<div class="section">
  <div class="container">
    <div class="row">
      <div class="col-md-12">
        <h1 class="text-center">Gestion des Questions-Réponses des Machines</h1>
        <hr>
        
        <!-- Messages d'erreur/succès -->
        <?php if(isset($aide_error)): ?>
          <div class="alert alert-danger">
            <strong>Erreur :</strong> <?= htmlspecialchars($aide_error) ?>
          </div>
        <?php endif; ?>
        
        <?php if(isset($aide_success)): ?>
          <div class="alert alert-success">
            <strong>Succès :</strong> <?= htmlspecialchars($aide_success) ?>
          </div>
        <?php endif; ?>
        
        <!-- Section Ajouter une Q&A -->
        <div class="row">
          <div class="col-md-12">
            <div class="panel panel-primary">
              <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-plus"></i> Ajouter une Question-Réponse</h3>
              </div>
              <div class="panel-body">
                <form method="POST" id="add-qa-form">
                  <input type="hidden" name="action" value="add_qa">
                  
                  <div class="row">
                    <div class="col-md-4">
                      <div class="form-group">
                        <label for="machine">Machine :</label>
                        <select class="form-control" id="machine" name="machine" required>
                          <option value="">Sélectionner une machine</option>
                          <?php if(isset($all_machines) && !empty($all_machines)): ?>
                            <?php foreach($all_machines as $machine): ?>
                              <option value="<?= htmlspecialchars($machine) ?>"><?= htmlspecialchars($machine) ?></option>
                            <?php endforeach; ?>
                          <?php endif; ?>
                        </select>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="form-group">
                        <label for="categorie">Catégorie :</label>
                        <select class="form-control" id="categorie" name="categorie" required>
                          <option value="general">Aide générale (page publique)</option>
                          <option value="changement">Aide changement de consommables</option>
                        </select>
                        <small class="text-muted">Type d'aide à créer</small>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="form-group">
                        <label for="ordre">Ordre d'affichage :</label>
                        <input type="number" class="form-control" id="ordre" name="ordre" value="0" min="0">
                        <small class="text-muted">Ordre d'affichage des questions (0 = premier)</small>
                      </div>
                    </div>
                  </div>
                  
                  <div class="row">
                    <div class="col-md-12">
                      <div class="form-group">
                        <label for="question">Question :</label>
                        <input type="text" class="form-control" id="question" name="question" required>
                        <small class="text-muted">Titre de la question qui apparaîtra dans l'accordéon</small>
                      </div>
                    </div>
                  </div>
                  
                  <div class="row">
                    <div class="col-md-12">
                      <div class="form-group">
                        <label for="reponse">Réponse :</label>
                        <textarea class="form-control" id="reponse" name="reponse" rows="10" required></textarea>
                        <small class="text-muted">Vous pouvez utiliser du HTML pour formater le contenu (titres, listes, images, etc.)</small>
                      </div>
                    </div>
                  </div>
                  
                  <div class="row">
                    <div class="col-md-12 text-center">
                      <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fa fa-plus"></i> Ajouter la Q&A
                      </button>
                    </div>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Section Liste des Q&A -->
        <div class="row">
          <div class="col-md-12">
            <div class="panel panel-default">
              <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-list"></i> Questions-Réponses existantes</h3>
              </div>
              <div class="panel-body">
                <?php if(isset($qa_list) && !empty($qa_list)): ?>
                  <div class="table-responsive">
                    <table class="table table-striped table-hover">
                      <thead>
                        <tr>
                          <th>Machine</th>
                          <th>Question</th>
                          <th>Catégorie</th>
                          <th>Ordre</th>
                          <th>Date création</th>
                          <th>Dernière modification</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach($qa_list as $qa): ?>
                          <tr>
                            <td>
                              <strong><?= htmlspecialchars($qa['machine']) ?></strong>
                            </td>
                            <td>
                              <div class="qa-preview">
                                <?= htmlspecialchars($qa['question']) ?>
                              </div>
                            </td>
                            <td>
                              <?php if($qa['categorie'] === 'changement'): ?>
                                <span class="badge badge-warning">Changement</span>
                              <?php else: ?>
                                <span class="badge badge-primary">Générale</span>
                              <?php endif; ?>
                            </td>
                            <td>
                              <span class="badge badge-info"><?= $qa['ordre'] ?></span>
                            </td>
                            <td><?= date('d/m/Y à H:i', strtotime($qa['date_creation'])) ?></td>
                            <td><?= date('d/m/Y à H:i', strtotime($qa['date_modification'])) ?></td>
                            <td>
                              <button class="btn btn-sm btn-info edit-qa" data-id="<?= $qa['id'] ?>">
                                <i class="fa fa-edit"></i> Modifier
                              </button>
                              <button class="btn btn-sm btn-danger delete-qa" data-id="<?= $qa['id'] ?>">
                                <i class="fa fa-trash"></i> Supprimer
                              </button>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php else: ?>
                  <div class="alert alert-info text-center">
                    <i class="fa fa-info-circle"></i> Aucune question-réponse enregistrée pour le moment.
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Navigation -->
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
                <a href="?aide_machines" class="btn btn-success">
                  <i class="fa fa-eye"></i> Voir la page publique
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal d'édition -->
<div class="modal fade" id="editModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Modifier la Question-Réponse</h4>
      </div>
      <div class="modal-body">
        <form id="edit-form">
          <input type="hidden" id="edit_id" name="id">
          
          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <label for="edit_machine">Machine :</label>
                <select class="form-control" id="edit_machine" name="machine" required>
                  <option value="">Sélectionner une machine</option>
                  <?php if(isset($all_machines) && !empty($all_machines)): ?>
                    <?php foreach($all_machines as $machine): ?>
                      <option value="<?= htmlspecialchars($machine) ?>"><?= htmlspecialchars($machine) ?></option>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </select>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label for="edit_categorie">Catégorie :</label>
                <select class="form-control" id="edit_categorie" name="categorie" required>
                  <option value="general">Aide générale (page publique)</option>
                  <option value="changement">Aide changement de consommables</option>
                </select>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label for="edit_ordre">Ordre d'affichage :</label>
                <input type="number" class="form-control" id="edit_ordre" name="ordre" min="0">
              </div>
            </div>
          </div>
          
          <div class="form-group">
            <label for="edit_question">Question :</label>
            <input type="text" class="form-control" id="edit_question" name="question" required>
          </div>
          
          <div class="form-group">
            <label for="edit_reponse">Réponse :</label>
            <textarea class="form-control" id="edit_reponse" name="reponse" rows="10" required></textarea>
            <small class="text-muted">Vous pouvez utiliser du HTML pour formater le contenu</small>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-primary" id="save-edit">Sauvegarder</button>
      </div>
    </div>
  </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/lang/summernote-fr-FR.min.js"></script>

<script>
$(document).ready(function() {
    // Initialiser l'éditeur WYSIWYG
    $('#reponse, #edit_reponse').summernote({
        height: 300,
        lang: 'fr-FR',
        toolbar: [
            ['style', ['style']],
            ['font', ['bold', 'italic', 'underline', 'clear']],
            ['fontname', ['fontname']],
            ['fontsize', ['fontsize']],
            ['color', ['color']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['table', ['table']],
            ['insert', ['link', 'picture', 'video']],
            ['view', ['fullscreen', 'codeview', 'help']]
        ]
    });
    
    // Gestion de l'édition
    $('.edit-qa').click(function() {
        var id = $(this).data('id');
        
        $.get('?admin_aide_machines&ajax=get_qa&id=' + id)
            .done(function(response) {
                if (response.success) {
                    var qa = response.qa;
                    $('#edit_id').val(qa.id);
                    $('#edit_machine').val(qa.machine);
                    $('#edit_question').val(qa.question);
                    $('#edit_ordre').val(qa.ordre);
                    $('#edit_categorie').val(qa.categorie);
                    
                    // Mettre à jour le contenu de l'éditeur
                    $('#edit_reponse').summernote('code', qa.reponse);
                    
                    $('#editModal').modal('show');
                } else {
                    alert('Erreur lors du chargement de la Q&A: ' + (response.error || 'Erreur inconnue'));
                }
            })
            .fail(function(xhr, status, error) {
                alert('Erreur lors du chargement de la Q&A: ' + error);
            });
    });
    
    // Sauvegarde de l'édition
    $('#save-edit').click(function() {
        var id = $('#edit_id').val();
        var formData = {
            action: 'edit_qa',
            id: id,
            machine: $('#edit_machine').val(),
            question: $('#edit_question').val(),
            reponse: $('#edit_reponse').summernote('code'),
            ordre: $('#edit_ordre').val(),
            categorie: $('#edit_categorie').val()
        };
        
        $.post('?admin_aide_machines', formData)
            .done(function(response) {
                location.reload();
            })
            .fail(function(xhr, status, error) {
                alert('Erreur lors de la modification: ' + xhr.responseText);
            });
    });
    
    // Gestion de la suppression
    $('.delete-qa').click(function() {
        if (confirm('Êtes-vous sûr de vouloir supprimer cette Q&A ?')) {
            var id = $(this).data('id');
            
            $.post('?admin_aide_machines', {
                action: 'delete_qa',
                id: id
            }).done(function(response) {
                location.reload();
            });
        }
    });
    
    // Soumission du formulaire d'ajout
    $('#add-qa-form').submit(function(e) {
        e.preventDefault();
        
        // Récupérer le contenu de l'éditeur
        var reponse = $('#reponse').summernote('code');
        
        var formData = {
            action: 'add_qa',
            machine: $('#machine').val(),
            question: $('#question').val(),
            reponse: reponse,
            ordre: $('#ordre').val(),
            categorie: $('#categorie').val()
        };
        
        $.post('?admin_aide_machines', formData)
            .done(function(response) {
                location.reload();
            })
            .fail(function(xhr, status, error) {
                alert('Erreur lors de l\'ajout: ' + xhr.responseText);
            });
    });
    
    // Fermer le modal et réinitialiser l'éditeur
    $('#editModal').on('hidden.bs.modal', function () {
        $('#edit_reponse').summernote('reset');
    });
});
</script>