$(document).ready(function() {
    const T = (key, fb) => (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations[key]) || fb;

    $('.rename-machine').click(function() {
        var machineName = $(this).data('name');
        var machineType = $(this).data('type');
        
        $('#current-machine-name').text(machineName);
        $('#new-machine-name').val(machineName);
        $('#rename-machine-modal').modal('show');
    });
    
    $('#rename-machine-form').submit(function(e) {
        e.preventDefault();
        
        var oldName = $('#current-machine-name').text();
        var newName = $('#new-machine-name').val().trim();
        
        if (newName === '' || newName === oldName) {
            alert(T('js.machine_rename.veuillez_saisir_nom', 'Veuillez saisir un nouveau nom différent de l\'ancien.'));
            return;
        }
        
        if (!confirm(T('js.machine_rename.confirmer_renommage', 'Êtes-vous sûr de vouloir renommer "' + oldName + '" en "' + newName + '" ?\n\nCette action mettra à jour toutes les références dans la base de données.').replace('{oldName}', oldName).replace('{newName}', newName))) {
            return;
        }
        
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
                    alert(T('js.machine_rename.succes', 'Machine renommée avec succès !'));
                    location.reload();
                } else {
                    alert(T('js.machine_rename.erreur', 'Erreur : ') + (response.error || T('js.machine_rename.erreur_inconnue', 'Erreur inconnue')));
                }
            },
            error: function() {
                alert(T('js.machine_rename.erreur_communication', 'Erreur lors de la communication avec le serveur.'));
            }
        });
    });
});
