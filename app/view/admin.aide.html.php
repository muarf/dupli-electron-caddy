<?php
// Inclure Quill.js
?>
<!-- Quill.js CSS -->
<link href="js/quill/quill.snow.css" rel="stylesheet">
<!-- Quill.js JS -->
<script src="js/quill/quill.min.js"></script>

<style>
/* Style pour le bouton PDF personnalisé dans Quill.js */
.ql-toolbar .ql-custom-pdf {
    background: #dc3545;
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 3px;
    cursor: pointer;
    font-size: 12px;
    font-weight: bold;
}

.ql-toolbar .ql-custom-pdf:hover {
    background: #c82333;
}

.ql-toolbar .ql-custom-pdf:before {
    content: "PDF";
}

/* Style pour les liens PDF dans l'éditeur */
.ql-editor a[href*=".pdf"] {
    color: #dc3545;
    font-weight: bold;
    text-decoration: underline;
}

.ql-editor a[href*=".pdf"]:before {
    content: "📄 ";
}
</style>

<div class="section">
  <div class="container">
    <div class="row">
      <div class="col-md-10 col-md-offset-1">
        <h1 class="text-center"><?php _e('admin_aide.title'); ?></h1>
        <hr>
        
        <?php if (isset($message)): ?>
          <div class="alert alert-<?= $message['type'] ?>">
            <?= $message['text'] ?>
          </div>
        <?php endif; ?>
        
        <!-- Liste des aides existantes -->
        <div class="panel panel-primary">
          <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-list"></i> <?php _e('admin_aide.existing_aides'); ?></h3>
          </div>
          <div class="panel-body">
            <?php if (isset($aides) && count($aides) > 0): ?>
              <div class="table-responsive">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th><?php _e('admin_aide.machine'); ?></th>
                      <th><?php _e('admin_aide.creation_date'); ?></th>
                      <th><?php _e('admin_aide.modification_date'); ?></th>
                      <th><?php _e('admin_aide.actions'); ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($aides as $aide): ?>
                      <tr>
                        <td><strong><?= htmlspecialchars($aide['machine']) ?></strong></td>
                        <td><?= date('d/m/Y H:i', strtotime($aide['date_creation'])) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($aide['date_modification'])) ?></td>
                        <td>
                          <button class="btn btn-sm btn-info" onclick="editAide(<?= $aide['id'] ?>, '<?= addslashes($aide['machine']) ?>')" data-aide-id="<?= $aide['id'] ?>">
                            <i class="fa fa-edit"></i> <?php _e('admin_aide.edit'); ?>
                          </button>
                          <button class="btn btn-sm btn-danger" onclick="deleteAide(<?= $aide['id'] ?>, '<?= addslashes($aide['machine']) ?>')">
                            <i class="fa fa-trash"></i> <?php _e('admin_aide.delete'); ?>
                          </button>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="alert alert-info">
                <i class="fa fa-info-circle"></i> <?php _e('admin_aide.no_aides'); ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
        
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

        <!-- Formulaire d'ajout/modification -->
        <div class="panel panel-success">
          <div class="panel-heading">
            <h3 class="panel-title">
              <i class="fa fa-plus"></i> 
              <span id="form-title"><?php _e('admin_aide.add_aide'); ?></span>
            </h3>
          </div>
          <div class="panel-body">
            <form method="POST" action="?admin&aide" id="aide-form">
              <input type="hidden" name="action" id="action" value="add">
              <input type="hidden" name="aide_id" id="aide_id" value="">
              <input type="hidden" name="contenu_aide" id="contenu_aide_hidden" value="">
              
              <div class="form-group">
                <label for="machine"><?php _e('admin_aide.machine'); ?> :</label>
                <select name="machine" id="machine" class="form-control" required onchange="loadExistingAide()">
                  <option value=""><?php _e('admin_aide.select_machine'); ?></option>
                  <?php if (isset($machines)): ?>
                    <?php foreach ($machines as $machine): ?>
                      <option value="<?= htmlspecialchars($machine) ?>"><?= htmlspecialchars($machine) ?></option>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </select>
              </div>
              
              <div class="form-group">
                <label for="editor"><?php _e('admin_aide.help_content'); ?> :</label>
                <div id="editor" style="height: 300px; margin-bottom: 10px;">
                  <div class="alert alert-info">
                    <p style="text-align: center;"><?php _e('admin_aide.default_instructions'); ?></p>
                  </div>
                  <div style="text-align: center;">
                    <img src="img/compteur.png" style="width: 80%;">
                  </div>
                </div>
                <small class="text-muted">
                  <i class="fa fa-info-circle"></i> 
                  <?php _e('admin_aide.help_instructions'); ?>
                </small>
              </div>
              
              <div class="form-group">
                <button type="submit" class="btn btn-success">
                  <i class="fa fa-save"></i> <?php _e('admin_aide.save'); ?>
                </button>
                <button type="button" class="btn btn-default" onclick="resetForm()">
                  <i class="fa fa-refresh"></i> <?php _e('admin_aide.reset'); ?>
                </button>
              </div>
            </form>
          </div>
        </div>
        
        <!-- Bouton retour -->
        <div class="row">
          <div class="col-md-12">
            <a href="?admin" class="btn btn-default btn-block">
              <i class="fa fa-arrow-left"></i> <?php _e('admin_aide.back_to_admin'); ?>
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Initialiser Quill.js
var quill = new Quill('#editor', {
    theme: 'snow',
    modules: {
        toolbar: [
            [{ 'header': [1, 2, 3, false] }],
            ['bold', 'italic', 'underline', 'strike'],
            [{ 'color': [] }, { 'background': [] }],
            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
            [{ 'align': [] }],
            ['link', 'image'],
            [{ 'custom-pdf': 'PDF' }],
            ['clean']
        ]
    },
    placeholder: '<?php _e('admin_aide.default_instructions'); ?>'
});

// Ajouter le bouton PDF personnalisé à la toolbar
var toolbar = quill.getModule('toolbar');
toolbar.addHandler('custom-pdf', function() {
    showPdfInsertModal();
});

// Fonction pour mettre à jour le contenu caché avant soumission
document.getElementById('aide-form').addEventListener('submit', function() {
    var content = quill.root.innerHTML;
    document.getElementById('contenu_aide_hidden').value = content;
});

function editAide(id, machine) {
    // Récupérer le contenu via AJAX
    fetch('?admin&aide&get_content=' + id)
        .then(response => response.text())
        .then(content => {
            document.getElementById('form-title').textContent = '<?php _e('admin_aide.edit_aide'); ?> ' + machine;
            document.getElementById('action').value = 'edit';
            document.getElementById('aide_id').value = id;
            document.getElementById('machine').value = machine;
            
            // Mettre à jour Quill.js de manière sûre
            setTimeout(function() {
                if (quill && quill.root) {
                    try {
                        // Utiliser setContents pour éviter les erreurs d'événements
                        quill.setContents(quill.clipboard.convert(content));
                    } catch (e) {
                        // Fallback si setContents échoue
                        quill.root.innerHTML = content;
                    }
                } else {
                    // Fallback si Quill n'est pas prêt
                    document.getElementById('editor').innerHTML = content;
                }
            }, 200);
            
            // Faire défiler vers le formulaire
            document.getElementById('aide-form').scrollIntoView({ behavior: 'smooth' });
        })
        .catch(error => {
            console.error('<?php _e('admin_aide.error_loading_content'); ?>:', error);
            alert('<?php _e('admin_aide.error_loading_content'); ?>');
        });
}

function loadExistingAide() {
    var machine = document.getElementById('machine').value;
    if (!machine) {
        resetForm();
        return;
    }
    
    // Chercher l'aide existante pour cette machine
    fetch('?admin&aide&get_aide_by_machine=' + encodeURIComponent(machine))
        .then(response => response.text())
        .then(content => {
            if (content && content.trim() !== '') {
                // Aide trouvée, passer en mode édition
                document.getElementById('form-title').textContent = '<?php _e('admin_aide.edit_aide'); ?> ' + machine;
                document.getElementById('action').value = 'edit';
                
                // Récupérer l'ID de l'aide
                fetch('?admin&aide&get_aide_id=' + encodeURIComponent(machine))
                    .then(response => response.text())
                    .then(aideId => {
                        if (aideId && aideId.trim() !== '') {
                            document.getElementById('aide_id').value = aideId.trim();
                        }
                    });
                
                // Charger le contenu dans Quill.js de manière sûre
                setTimeout(function() {
                    if (quill && quill.root) {
                        try {
                            // Utiliser setContents pour éviter les erreurs d'événements
                            quill.setContents(quill.clipboard.convert(content));
                        } catch (e) {
                            // Fallback si setContents échoue
                            quill.root.innerHTML = content;
                        }
                    } else {
                        // Fallback si Quill n'est pas prêt
                        document.getElementById('editor').innerHTML = content;
                    }
                }, 200);
            } else {
                // Aucune aide trouvée, passer en mode création
                document.getElementById('form-title').textContent = '<?php _e('admin_aide.add_aide_for'); ?> ' + machine;
                document.getElementById('action').value = 'add';
                document.getElementById('aide_id').value = '';
                
                // Contenu par défaut
                var defaultContent = 
                    '<div class="alert alert-info">' +
                    '  <p style="text-align: center;">Instructions pour ' + machine + '...</p>' +
                    '</div>' +
                    '<div style="text-align: center;">' +
                    '  <img src="img/compteur.png" style="width: 80%;">' +
                    '</div>';
                
                setTimeout(function() {
                    if (quill && quill.root) {
                        try {
                            // Utiliser setContents pour éviter les erreurs d'événements
                            quill.setContents(quill.clipboard.convert(defaultContent));
                        } catch (e) {
                            // Fallback si setContents échoue
                            quill.root.innerHTML = defaultContent;
                        }
                    } else {
                        document.getElementById('editor').innerHTML = defaultContent;
                    }
                }, 200);
            }
        })
        .catch(error => {
            console.error('<?php _e('admin_aide.error_loading_aide'); ?>:', error);
            // En cas d'erreur, passer en mode création
            document.getElementById('form-title').textContent = '<?php _e('admin_aide.add_aide_for'); ?> ' + machine;
            document.getElementById('action').value = 'add';
            document.getElementById('aide_id').value = '';
        });
}

function deleteAide(id, machine) {
    if (confirm('<?php _e('admin_aide.confirm_delete'); ?> ' + machine + ' ?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '?admin&aide';
        
        var fields = {
            'action': 'delete',
            'aide_id': id
        };
        
        for (var key in fields) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = fields[key];
            form.appendChild(input);
        }
        
        document.body.appendChild(form);
        form.submit();
    }
}

function resetForm() {
    document.getElementById('form-title').textContent = '<?php _e('admin_aide.add_aide'); ?>';
    document.getElementById('action').value = 'add';
    document.getElementById('aide_id').value = '';
    document.getElementById('machine').value = '';
    
    // Réinitialiser Quill.js de manière sûre
    var defaultContent = 
        '<div class="alert alert-info">' +
        '  <p style="text-align: center;">Instructions pour cette machine...</p>' +
        '</div>' +
        '<div style="text-align: center;">' +
        '  <img src="img/compteur.png" style="width: 80%;">' +
        '</div>';
    
    setTimeout(function() {
        if (quill && quill.root) {
            try {
                // Utiliser setContents pour éviter les erreurs d'événements
                quill.setContents(quill.clipboard.convert(defaultContent));
            } catch (e) {
                // Fallback si setContents échoue
                quill.root.innerHTML = defaultContent;
            }
        } else {
            document.getElementById('editor').innerHTML = defaultContent;
        }
    }, 200);
}

// Fonctions pour la gestion des PDFs
function uploadPdf() {
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

function showProgress(show) {
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
                      '<th><?php _e('admin_aide.pdf_name'); ?></th>' +
                      '<th><?php _e('admin_aide.upload_date'); ?></th>' +
                      '<th><?php _e('admin_aide.pdf_size'); ?></th>' +
                      '<th>Actions</th>' +
                      '</tr>' +
                      '</thead>' +
                      '<tbody>';
            
            data.pdfs.forEach(function(pdf) {
                var safeName = pdf.name.replace(/'/g, "\\'");
                var safeUrl = pdf.url.replace(/'/g, "\\'");
                var safeFilename = pdf.filename.replace(/'/g, "\\'");
                
                html += '<tr>' +
                       '<td>' + pdf.name + '</td>' +
                       '<td>' + pdf.upload_date + '</td>' +
                       '<td>' + pdf.size + '</td>' +
                       '<td>' +
                       '<button class="btn btn-sm btn-success" onclick="insertPdf(\'' + safeUrl + '\', \'' + safeName + '\')">' +
                       '<i class="fa fa-plus"></i> <?php _e('admin_aide.insert_pdf'); ?>' +
                       '</button> ' +
                       '<button class="btn btn-sm btn-danger" onclick="deletePdf(\'' + safeFilename + '\')">' +
                       '<i class="fa fa-trash"></i> <?php _e('admin_aide.delete_pdf'); ?>' +
                       '</button>' +
                       '</td>' +
                       '</tr>';
            });
            
            html += '</tbody></table>';
            pdfList.innerHTML = html;
        } else {
            pdfList.innerHTML = '<div class="alert alert-info"><i class="fa fa-info-circle"></i> <?php _e('admin_aide.no_pdfs'); ?></div>';
        }
    })
    .catch(error => {
        document.getElementById('pdf-list').innerHTML = '<div class="alert alert-danger">Erreur lors du chargement des PDFs</div>';
    });
}

function insertPdf(url, name) {
    var range = quill.getSelection();
    if (range) {
        // Insérer un lien vers le PDF
        quill.insertText(range.index, '[PDF: ' + name + ']', 'link', url);
        quill.setSelection(range.index + name.length + 7);
    } else {
        // Insérer à la fin
        var length = quill.getLength();
        quill.insertText(length - 1, '[PDF: ' + name + ']', 'link', url);
    }
    
    showMessage('<?php _e('admin_aide.pdf_inserted'); ?>', 'success');
}

function deletePdf(filename) {
    if (confirm('<?php _e('admin_aide.confirm_delete_pdf'); ?>')) {
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

function showPdfInsertModal() {
    // Créer une modal simple pour l'insertion de PDF
    var modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = 'pdfInsertModal';
    modal.innerHTML = 
        '<div class="modal-dialog">' +
        '<div class="modal-content">' +
        '<div class="modal-header">' +
        '<button type="button" class="close" data-dismiss="modal">&times;</button>' +
        '<h4 class="modal-title"><?php _e('admin_aide.insert_pdf'); ?></h4>' +
        '</div>' +
        '<div class="modal-body" id="modal-pdf-list">' +
        '<div class="alert alert-info"><i class="fa fa-spinner fa-spin"></i> Chargement...</div>' +
        '</div>' +
        '<div class="modal-footer">' +
        '<button type="button" class="btn btn-default" data-dismiss="modal">Fermer</button>' +
        '</div>' +
        '</div>' +
        '</div>';
    
    document.body.appendChild(modal);
    
    // Charger la liste des PDFs dans la modal
    fetch('upload_aide_pdf.php?action=list')
    .then(response => response.json())
    .then(data => {
        var modalBody = document.getElementById('modal-pdf-list');
        
        if (data.success && data.pdfs.length > 0) {
            var html = '<div class="list-group">';
            data.pdfs.forEach(function(pdf) {
                var safeName = pdf.name.replace(/'/g, "\\'");
                var safeUrl = pdf.url.replace(/'/g, "\\'");
                
                html += '<a href="#" class="list-group-item" onclick="insertPdfFromModal(\'' + safeUrl + '\', \'' + safeName + '\'); return false;">' +
                       '<h5 class="list-group-item-heading"><i class="fa fa-file-pdf-o"></i> ' + pdf.name + '</h5>' +
                       '<p class="list-group-item-text">Taille: ' + pdf.size + ' - Uploadé le: ' + pdf.upload_date + '</p>' +
                       '</a>';
            });
            html += '</div>';
            modalBody.innerHTML = html;
        } else {
            modalBody.innerHTML = '<div class="alert alert-info"><i class="fa fa-info-circle"></i> <?php _e('admin_aide.no_pdfs'); ?></div>';
        }
    })
    .catch(error => {
        document.getElementById('modal-pdf-list').innerHTML = '<div class="alert alert-danger">Erreur lors du chargement des PDFs</div>';
    });
    
    // Afficher la modal
    $(modal).modal('show');
    
    // Nettoyer la modal après fermeture
    $(modal).on('hidden.bs.modal', function() {
        document.body.removeChild(modal);
    });
}

function insertPdfFromModal(url, name) {
    insertPdf(url, name);
    $('#pdfInsertModal').modal('hide');
}

// Charger la liste des PDFs au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    loadPdfList();
});
</script>
