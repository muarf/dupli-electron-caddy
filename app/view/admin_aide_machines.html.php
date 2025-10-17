<!-- CSS pour l'éditeur WYSIWYG -->
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote.min.css" rel="stylesheet">

<div class="section">
  <div class="container">
    <div class="row">
      <div class="col-md-12">
        <h1 class="text-center"><?php _e('admin_aide_machines.title'); ?></h1>
        <hr>
        
        <!-- Messages d'erreur/succès -->
        <?php if(isset($aide_error)): ?>
          <div class="alert alert-danger">
            <strong><?php _e('admin_aide_machines.error'); ?> :</strong> <?= htmlspecialchars($aide_error) ?>
          </div>
        <?php endif; ?>
        
        <?php if(isset($aide_success)): ?>
          <div class="alert alert-success">
            <strong><?php _e('admin_aide_machines.success'); ?> :</strong> <?= htmlspecialchars($aide_success) ?>
          </div>
        <?php endif; ?>
        
        <!-- Section Upload de PDFs -->
        <div class="panel panel-info">
          <div class="panel-heading">
            <h3 class="panel-title">
              <i class="fa fa-upload"></i> 
              <?php _e('admin_aide.pdf_upload'); ?>
            </h3>
          </div>
          <div class="panel-body">
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label><?php _e('admin_aide.select_pdf'); ?> :</label>
                  <input type="file" id="pdf-file-input" class="form-control" accept=".pdf" />
                  <small class="text-muted">Maximum 10MB, format PDF uniquement</small>
                </div>
                <button type="button" class="btn btn-primary" onclick="uploadPdf()">
                  <i class="fa fa-upload"></i> <?php _e('admin_aide.upload_pdf'); ?>
                </button>
              </div>
              <div class="col-md-6">
                <div id="upload-progress" class="progress" style="display: none;">
                  <div class="progress-bar progress-bar-striped active" role="progressbar" style="width: 0%"></div>
                </div>
                <div id="upload-message" class="alert" style="display: none;"></div>
              </div>
            </div>
            
            <!-- Liste des PDFs disponibles -->
            <hr>
            <h4><?php _e('admin_aide.uploaded_pdfs'); ?></h4>
            <div id="pdf-list" class="table-responsive">
              <div class="alert alert-info">
                <i class="fa fa-spinner fa-spin"></i> Chargement des PDFs...
              </div>
            </div>
          </div>
        </div>
        
        <!-- Section Ajouter une Q&A -->
        <div class="row">
          <div class="col-md-12">
            <div class="panel panel-primary">
              <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-plus"></i> <?php _e('admin_aide_machines.add_qa'); ?></h3>
              </div>
              <div class="panel-body">
                <form method="POST" id="add-qa-form">
                  <input type="hidden" name="action" value="add_qa">
                  
                  <div class="row">
                    <div class="col-md-4">
                      <div class="form-group">
                        <label for="machine"><?php _e('admin_aide_machines.machine'); ?> :</label>
                        <select class="form-control" id="machine" name="machine" required>
                          <option value=""><?php _e('admin_aide_machines.select_machine'); ?></option>
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
                        <label for="categorie"><?php _e('admin_aide_machines.categorie'); ?> :</label>
                        <select class="form-control" id="categorie" name="categorie" required>
                          <option value="general"><?php _e('admin_aide_machines.general_help'); ?></option>
                          <option value="changement"><?php _e('admin_aide_machines.change_help'); ?></option>
                        </select>
                        <small class="text-muted"><?php _e('admin_aide_machines.help_type'); ?></small>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="form-group">
                        <label for="ordre"><?php _e('admin_aide_machines.display_order'); ?> :</label>
                        <input type="number" class="form-control" id="ordre" name="ordre" value="0" min="0">
                        <small class="text-muted"><?php _e('admin_aide_machines.display_order_help'); ?></small>
                      </div>
                    </div>
                  </div>
                  
                  <div class="row">
                    <div class="col-md-12">
                      <div class="form-group">
                        <label for="question"><?php _e('admin_aide_machines.question'); ?> :</label>
                        <input type="text" class="form-control" id="question" name="question" required>
                        <small class="text-muted"><?php _e('admin_aide_machines.question_help'); ?></small>
                      </div>
                    </div>
                  </div>
                  
                  <div class="row">
                    <div class="col-md-12">
                      <div class="form-group">
                        <label for="reponse"><?php _e('admin_aide_machines.answer'); ?> :</label>
                        <textarea class="form-control" id="reponse" name="reponse" rows="10" required></textarea>
                        <small class="text-muted"><?php _e('admin_aide_machines.answer_help'); ?></small>
                      </div>
                    </div>
                  </div>
                  
                  <div class="row">
                    <div class="col-md-12 text-center">
                      <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fa fa-plus"></i> <?php _e('admin_aide_machines.add_qa_btn'); ?>
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
                <h3 class="panel-title"><i class="fa fa-list"></i> <?php _e('admin_aide_machines.existing_qa'); ?></h3>
              </div>
              <div class="panel-body">
                <?php if(isset($qa_list) && !empty($qa_list)): ?>
                  <div class="table-responsive">
                    <table class="table table-striped table-hover">
                      <thead>
                        <tr>
                          <th><?php _e('admin_aide_machines.machine'); ?></th>
                          <th><?php _e('admin_aide_machines.question'); ?></th>
                          <th><?php _e('admin_aide_machines.categorie'); ?></th>
                          <th><?php _e('admin_aide_machines.display_order'); ?></th>
                          <th><?php _e('admin_aide_machines.creation_date'); ?></th>
                          <th><?php _e('admin_aide_machines.modification_date'); ?></th>
                          <th><?php _e('admin_aide_machines.actions'); ?></th>
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
                                <span class="badge badge-warning"><?php _e('admin_aide_machines.change'); ?></span>
                              <?php else: ?>
                                <span class="badge badge-primary"><?php _e('admin_aide_machines.general'); ?></span>
                              <?php endif; ?>
                            </td>
                            <td>
                              <span class="badge badge-info"><?= $qa['ordre'] ?></span>
                            </td>
                            <td><?= date('d/m/Y à H:i', strtotime($qa['date_creation'])) ?></td>
                            <td><?= date('d/m/Y à H:i', strtotime($qa['date_modification'])) ?></td>
                            <td>
                              <button class="btn btn-sm btn-info edit-qa" data-id="<?= $qa['id'] ?>">
                                <i class="fa fa-edit"></i> <?php _e('admin_aide_machines.edit'); ?>
                              </button>
                              <button class="btn btn-sm btn-danger delete-qa" data-id="<?= $qa['id'] ?>">
                                <i class="fa fa-trash"></i> <?php _e('admin_aide_machines.delete'); ?>
                              </button>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php else: ?>
                  <div class="alert alert-info text-center">
                    <i class="fa fa-info-circle"></i> <?php _e('admin_aide_machines.no_qa'); ?>
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
                <h3 class="panel-title"><i class="fa fa-arrow-left"></i> <?php _e('admin_aide_machines.navigation'); ?></h3>
              </div>
              <div class="panel-body">
                <a href="?admin" class="btn btn-primary">
                  <i class="fa fa-arrow-left"></i> <?php _e('admin_aide_machines.back_to_admin'); ?>
                </a>
                <a href="?aide_machines" class="btn btn-success">
                  <i class="fa fa-eye"></i> <?php _e('admin_aide_machines.view_public_page'); ?>
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
        <h4 class="modal-title"><?php _e('admin_aide_machines.edit_qa_modal'); ?></h4>
      </div>
      <div class="modal-body">
        <form id="edit-form">
          <input type="hidden" id="edit_id" name="id">
          
          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <label for="edit_machine"><?php _e('admin_aide_machines.machine'); ?> :</label>
                <select class="form-control" id="edit_machine" name="machine" required>
                  <option value=""><?php _e('admin_aide_machines.select_machine'); ?></option>
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
                <label for="edit_categorie"><?php _e('admin_aide_machines.categorie'); ?> :</label>
                <select class="form-control" id="edit_categorie" name="categorie" required>
                  <option value="general"><?php _e('admin_aide_machines.general_help'); ?></option>
                  <option value="changement"><?php _e('admin_aide_machines.change_help'); ?></option>
                </select>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label for="edit_ordre"><?php _e('admin_aide_machines.display_order'); ?> :</label>
                <input type="number" class="form-control" id="edit_ordre" name="ordre" min="0">
              </div>
            </div>
          </div>
          
          <div class="form-group">
            <label for="edit_question"><?php _e('admin_aide_machines.question'); ?> :</label>
            <input type="text" class="form-control" id="edit_question" name="question" required>
          </div>
          
          <div class="form-group">
            <label for="edit_reponse"><?php _e('admin_aide_machines.answer'); ?> :</label>
            <textarea class="form-control" id="edit_reponse" name="reponse" rows="10" required></textarea>
            <small class="text-muted"><?php _e('admin_aide_machines.answer_help'); ?></small>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal"><?php _e('admin_aide_machines.cancel'); ?></button>
        <button type="button" class="btn btn-primary" id="save-edit"><?php _e('admin_aide_machines.save'); ?></button>
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
                    alert('<?php _e('admin_aide_machines.error_loading_qa'); ?>: ' + (response.error || '<?php _e('admin_aide_machines.unknown_error'); ?>'));
                }
            })
            .fail(function(xhr, status, error) {
                alert('<?php _e('admin_aide_machines.error_loading'); ?>: ' + error);
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
                alert('<?php _e('admin_aide_machines.error_modification'); ?>: ' + xhr.responseText);
            });
    });
    
    // Gestion de la suppression
    $('.delete-qa').click(function() {
        if (confirm('<?php _e('admin_aide_machines.confirm_delete'); ?>')) {
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
    
    // Charger la liste des PDFs au chargement de la page
    loadPdfList();
});

// Fonctions pour la gestion des PDFs (globales)
window.uploadPdf = function() {
    var fileInput = document.getElementById('pdf-file-input');
    var file = fileInput.files[0];
    
    if (!file) {
        showMessage('Veuillez sélectionner un fichier PDF.', 'danger');
        return;
    }
    
    if (file.type !== 'application/pdf') {
        showMessage('Veuillez sélectionner un fichier PDF valide.', 'danger');
        return;
    }
    
    if (file.size > 10 * 1024 * 1024) {
        showMessage('Le fichier est trop volumineux (maximum 10MB).', 'danger');
        return;
    }
    
    var formData = new FormData();
    formData.append('pdf_file', file);
    formData.append('action', 'upload');
    
    showProgress(true);
    
    fetch('upload_aide_pdf.php?action=upload', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        showProgress(false);
        if (data.success) {
            showMessage(data.message, 'success');
            fileInput.value = '';
            loadPdfList();
        } else {
            showMessage(data.message, 'danger');
        }
    })
    .catch(error => {
        showProgress(false);
        showMessage('Erreur lors de l\'upload: ' + error.message, 'danger');
    });
}

window.showProgress = function(show) {
    var progress = document.getElementById('upload-progress');
    progress.style.display = show ? 'block' : 'none';
    if (show) {
        var bar = progress.querySelector('.progress-bar');
        bar.style.width = '100%';
    }
}

function showMessage(message, type) {
    var messageDiv = document.getElementById('upload-message');
    messageDiv.className = 'alert alert-' + type;
    messageDiv.textContent = message;
    messageDiv.style.display = 'block';
    
    setTimeout(function() {
        messageDiv.style.display = 'none';
    }, 5000);
}

function loadPdfList() {
    fetch('upload_aide_pdf.php?action=list')
    .then(response => response.json())
    .then(data => {
        var pdfList = document.getElementById('pdf-list');
        
        if (data.success && data.pdfs.length > 0) {
            var html = '<table class="table table-striped table-hover">' +
                      '<thead>' +
                      '<tr>' +
                      '<th>Nom du PDF</th>' +
                      '<th>Date d\'upload</th>' +
                      '<th>Taille</th>' +
                      '<th>Actions</th>' +
                      '</tr>' +
                      '</thead>' +
                      '<tbody>';
            
            data.pdfs.forEach(function(pdf, index) {
                html += '<tr>' +
                       '<td>' + pdf.name + '</td>' +
                       '<td>' + pdf.upload_date + '</td>' +
                       '<td>' + pdf.size + '</td>' +
                       '<td>' +
                       '<button class="btn btn-sm btn-success insert-pdf-btn" data-url="' + pdf.url + '" data-name="' + pdf.name + '">' +
                       '<i class="fa fa-plus"></i> <?php _e('admin_aide.insert_pdf'); ?>' +
                       '</button> ' +
                       '<button class="btn btn-sm btn-danger delete-pdf-btn" data-filename="' + pdf.filename + '">' +
                       '<i class="fa fa-trash"></i> <?php _e('admin_aide.delete_pdf'); ?>' +
                       '</button>' +
                       '</td>' +
                       '</tr>';
            });
            
            html += '</tbody></table>';
            pdfList.innerHTML = html;
            
            // Ajouter les event listeners pour les nouveaux boutons
            $(document).off('click', '.insert-pdf-btn').on('click', '.insert-pdf-btn', function() {
                var url = $(this).data('url');
                var name = $(this).data('name');
                insertPdfIntoSummernote(url, name);
            });
            
            $(document).off('click', '.delete-pdf-btn').on('click', '.delete-pdf-btn', function() {
                var filename = $(this).data('filename');
                deletePdf(filename);
            });
        } else {
            pdfList.innerHTML = '<div class="alert alert-info"><i class="fa fa-info-circle"></i> <?php _e('admin_aide.no_pdfs'); ?></div>';
        }
    })
    .catch(error => {
        document.getElementById('pdf-list').innerHTML = '<div class="alert alert-danger">Erreur lors du chargement des PDFs</div>';
    });
}

function insertPdfIntoSummernote(url, name) {
    // Insérer le preview PDF dans l'éditeur Summernote actif
    var activeEditor = $('#reponse').summernote('code') ? $('#reponse') : $('#edit_reponse');
    
    if (activeEditor.length > 0) {
        var currentContent = activeEditor.summernote('code');
        var pdfPreview = '<div class="pdf-preview-container" style="margin: 20px 0; border: 1px solid #ddd; border-radius: 5px; overflow: hidden;">' +
                        '<div class="pdf-header" style="background: #f5f5f5; padding: 10px; border-bottom: 1px solid #ddd;">' +
                        '<i class="fa fa-file-pdf-o" style="color: #d32f2f; margin-right: 5px;"></i>' +
                        '<strong>' + name + '</strong>' +
                        '<a href="' + url + '" target="_blank" style="float: right; color: #1976d2; text-decoration: none;">' +
                        '<i class="fa fa-external-link"></i> Ouvrir dans un nouvel onglet</a>' +
                        '</div>' +
                        '<iframe src="' + url + '" width="100%" height="500px" style="border: none;"></iframe>' +
                        '</div>';
        
        activeEditor.summernote('code', currentContent + pdfPreview);
        showMessage('Aperçu PDF inséré dans l\'aide', 'success');
    } else {
        showMessage('Veuillez d\'abord sélectionner un éditeur de texte.', 'warning');
    }
}

function deletePdf(filename) {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce PDF ?')) {
        var formData = new FormData();
        formData.append('filename', filename);
        formData.append('action', 'delete');
        
        fetch('upload_aide_pdf.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage('PDF supprimé avec succès.', 'success');
                loadPdfList();
            } else {
                showMessage(data.message, 'danger');
            }
        })
        .catch(error => {
            showMessage('Erreur lors de la suppression: ' + error.message, 'danger');
        });
    }
}
</script>

<style>
/* Style pour les liens PDF */
.pdf-link {
    color: #dc3545 !important;
    font-weight: bold;
    text-decoration: underline;
}

.pdf-link:hover {
    color: #c82333 !important;
}
</style>