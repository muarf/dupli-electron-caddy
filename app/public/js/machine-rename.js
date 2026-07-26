$(document).ready(function() {
    // Gestion du renommage de machines
    $('.rename-machine').click(function() {
        var machineName = $(this).data('name');
        var machineType = $(this).data('type');
        
        $('#current-machine-name').text(machineName);
        $('#new-machine-name').val(machineName);
        $('#rename-machine-modal').modal('show');
    });
    
    // Soumission du formulaire de renommage
    $('#rename-machine-form').submit(function(e) {
        e.preventDefault();
        
        var oldName = $('#current-machine-name').text();
        var newName = $('#new-machine-name').val().trim();
        
        if (newName === '' || newName === oldName) {
            alert('Veuillez saisir un nouveau nom différent de l\'ancien.');
            return;
        }
        
        // Confirmation
        if (!confirm('Êtes-vous sûr de vouloir renommer "' + oldName + '" en "' + newName + '" ?\n\nCette action mettra à jour toutes les références dans la base de données.')) {
            return;
        }
        
        // Envoyer la requête AJAX
        $.ajax({
            url: '?admin&machines&action=rename',
            type: 'POST',
            data: {
                old_name: oldName,
                new_name: newName
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('Machine renommée avec succès !');
                    location.reload();
                } else {
                    alert((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.machine_rename.erreur'] || 'Erreur : ') + (response.error || (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.machine_rename.erreur_inconnue'] || 'Erreur inconnue')));
                }
            },
            error: function() {
                alert((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.machine_rename.erreur_lors_de_la_communicatio'] || 'Erreur lors de la communication avec le serveur.'));
            }
        });
    });
});
