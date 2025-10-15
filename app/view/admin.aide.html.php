<?php
// Inclure Quill.js
?>
<!-- Quill.js CSS -->
<link href="js/quill/quill.snow.css" rel="stylesheet">
<!-- Quill.js JS -->
<script src="js/quill/quill.min.js"></script>

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
            ['clean']
        ]
    },
    placeholder: '<?php _e('admin_aide.default_instructions'); ?>'
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
</script>
