<?php 
require_once __DIR__ . '/../controler/functions/database.php';

// Fonction pour déterminer le type de machine
function getTableForMachine($machine) {
    $db = pdo_connect();
    $db = pdo_connect();
    
    // Vérifier si c'est un duplicopieur
    $query = $db->prepare('SELECT COUNT(*) FROM duplicopieurs WHERE actif = 1 AND CONCAT(marque, " ", modele) = ?');
    $query->execute([$machine]);
    
    if ($query->fetchColumn() > 0) {
        // Pour les duplicopieurs, retourner le type
        return 'duplicopieur';
    } else {
        // Pour les photocopieurs, retourner le type
        return 'photocopieur';
    }
}

// Récupérer les données du tirage directement si la variable n'est pas disponible
if (!isset($array) || !isset($array['tirage'])) {
    $db = pdo_connect();
    $machine = $_GET['table'];
    $id = $_GET['edit'];
    
    $tirage = get_tirage($id, $machine);
    if(!$tirage) {
        echo '<div class="alert alert-danger">';
        echo '<strong>Erreur !</strong> Le tirage n\'a pas été trouvé.';
        echo '<br><a href="?admin&tirages" class="btn btn-primary">Retour aux tirages</a>';
        echo '</div>';
        return;
    }
} else {
    $tirage = $array['tirage'];
    
}

if(isset($array['tirage_updated']) && $array['tirage_updated']){?>
<div class="alert alert-success alert-dismissible fade in" role="alert">
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
    <strong><i class="glyphicon glyphicon-ok"></i> Succès!</strong> Le tirage a été mis à jour avec succès !
</div>
<?php }

if(isset($_POST['save'])){?>
<div class="alert alert-success alert-dismissible fade in" role="alert">
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
    <strong><i class="glyphicon glyphicon-ok"></i> Succès!</strong> Le tirage a été mis à jour avec succès !
</div>
<?php }

if(isset($array['tirage_deleted']) && $array['tirage_deleted']){?>
<div class="alert alert-success alert-dismissible fade in" role="alert">
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
    <strong><i class="glyphicon glyphicon-ok"></i> Succès!</strong> Le tirage a été supprimé avec succès !
</div>
<?php }

if(isset($_POST['delete'])){?>
<div class="alert alert-success alert-dismissible fade in" role="alert">
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
    <strong><i class="glyphicon glyphicon-ok"></i> Succès!</strong> Le tirage a été supprimé avec succès !
</div>
<div class="section">
      <div class="container">
        <div class="row">
            <div class="col-md-12">
                <a href="?accueil" class="btn btn-success btn-lg btn-block">
                    <i class="glyphicon glyphicon-home"></i> Retour à l'accueil
                </a>
            </div>
        </div>
      </div>
</div>
<?php } else { ?>
 <div class="section">
      <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-primary">
                    <div class="panel-heading">
                        <h3 class="panel-title">
                            <i class="glyphicon glyphicon-edit"></i> 
                            Édition du tirage #<?= $tirage['id'] ?> 
                            <span class="label label-info"><?= $tirage['machine'] ?></span>
                        </h3>
                    </div>
                    <div class="panel-body">
                        <form method="post" id="editForm" class="form-horizontal">
            <input type='hidden' value="<?= $tirage['date'] ?>" name="date" />
            <input type='hidden' value="<?= $tirage['machine'] ?>" name="machine" />            
            <input type='hidden' value="<?= $tirage['type'] ?>" name="type" />
            <input type='hidden' value="<?= $tirage['id'] ?>" name="id" />
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="contact" class="col-sm-4 control-label">
                                            <i class="glyphicon glyphicon-user"></i> Contact
                                        </label>
                                        <div class="col-sm-8">
                                            <input id="contact" name="contact" value="<?= htmlspecialchars($tirage['contact']) ?>" 
                                                   class="form-control" required type="text" 
                                                   data-toggle="tooltip" data-placement="top" 
                                                   title="Nom du client ou contact">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="col-sm-4 control-label">
                                            <i class="glyphicon glyphicon-calendar"></i> Date
                                        </label>
                                        <div class="col-sm-8">
                                            <p class="form-control-static">
                                                <span class="label label-default">
                                                    <?= date('d.m.Y H:i', $tirage['date']) ?>
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php 
                            // Déterminer le type de machine
                            $machine_type = getTableForMachine($tirage['machine']);
                            if($machine_type == 'duplicopieur'){?>
                            <!-- Duplicopieur -->
                            <div class="row">
                                <div class="col-md-12">
                                    <h4><i class="glyphicon glyphicon-print"></i> Paramètres du duplicopieur</h4>
                                    <hr>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="passage_av" class="col-sm-6 control-label">Passage avant</label>
                                        <div class="col-sm-6">
                                            <input id="passage_av" name="passage_av" value="<?= $tirage['passage_av'] ?>" 
                                                   class="form-control" required type="number" min="0"
                                                   data-toggle="tooltip" data-placement="top" 
                                                   title="Nombre de passages avant">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="passage_ap" class="col-sm-6 control-label">Passage après</label>
                                        <div class="col-sm-6">
                                            <input id="passage_ap" name="passage_ap" value="<?= $tirage['passage_ap'] ?>" 
                                                   class="form-control" required type="number" min="0"
                                                   data-toggle="tooltip" data-placement="top" 
                                                   title="Nombre de passages après">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="master_av" class="col-sm-6 control-label">Master avant</label>
                                        <div class="col-sm-6">
                                            <input id="master_av" name="master_av" value="<?= $tirage['master_av'] ?>" 
                                                   class="form-control" required type="number" min="0"
                                                   data-toggle="tooltip" data-placement="top" 
                                                   title="Nombre de masters avant">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="master_ap" class="col-sm-6 control-label">Master après</label>
                                        <div class="col-sm-6">
                                            <input id="master_ap" name="master_ap" value="<?= $tirage['master_ap'] ?>" 
                                                   class="form-control" required type="number" min="0"
                                                   data-toggle="tooltip" data-placement="top" 
                                                   title="Nombre de masters après">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php } else {?>
                            <!-- Photocopieur -->
                            <div class="row">
                                <div class="col-md-12">
                                    <h4><i class="glyphicon glyphicon-print"></i> Paramètres du photocopieur</h4>
                                    <hr>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="nb_f" class="col-sm-4 control-label">
                                            <i class="glyphicon glyphicon-file"></i> Nombre de feuilles
                                        </label>
                                        <div class="col-sm-8">
                                            <input id="nb_f" name="nb_f" value="<?= isset($tirage['nb_f']) ? $tirage['nb_f'] : (intval($tirage['passage_ap']) - intval($tirage['passage_av'])) ?>" 
                                                   class="form-control" required type="number" min="1"
                                                   data-toggle="tooltip" data-placement="top" 
                                                   title="Nombre total de feuilles imprimées">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php } ?>

                            <div class="row">
                                <div class="col-md-12">
                                    <h4><i class="glyphicon glyphicon-euro"></i> Informations de facturation</h4>
                                    <hr>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="rv" class="col-sm-4 control-label">
                                            <i class="glyphicon glyphicon-refresh"></i> Recto/Verso
                                        </label>
                                        <div class="col-sm-8">
                                            <select id="rv" name="rv" class="form-control" required>
                                                <?php 
                                                // Logique pour déterminer le recto-verso
                                                $is_recto_seulement = false;
                                                $is_recto_verso = false;
                                                
                                                if (in_array($tirage['rv'], ['non', '0', 'recto', 'recto seulement'])) {
                                                    $is_recto_seulement = true;
                                                } elseif (in_array($tirage['rv'], ['oui', 'RV', '1', 'recto-verso', 'recto/verso', 'recto verso'])) {
                                                    $is_recto_verso = true;
                                                } elseif (is_numeric($tirage['rv']) && isset($tirage['nb_f']) && is_numeric($tirage['nb_f'])) {
                                                    // Si rv est numérique, comparer avec nb_f
                                                    if ($tirage['rv'] == $tirage['nb_f'] * 2) {
                                                        $is_recto_verso = true;
                                                    } else {
                                                        $is_recto_seulement = true;
                                                    }
                                                }
                                                ?>
                                                <option value="non" <?= $is_recto_seulement ? 'selected' : '' ?>>Recto seulement</option>
                                                <option value="oui" <?= $is_recto_verso ? 'selected' : '' ?>>Recto-Verso</option>
                                                <?php if (!$is_recto_seulement && !$is_recto_verso): ?>
                                                <option value="non" selected>Recto seulement (valeur non reconnue: <?= htmlspecialchars($tirage['rv']) ?>)</option>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="prix" class="col-sm-4 control-label">
                                            <i class="glyphicon glyphicon-euro"></i> Prix
                                        </label>
                                        <div class="col-sm-8">
                                            <div class="input-group">
                                                <input id="prix" name="prix" value="<?= $tirage['prix'] ?>" 
                                                       class="form-control" required type="number" step="0.01" min="0"
                                                       data-toggle="tooltip" data-placement="top" 
                                                       title="Prix total en euros">
                                                <span class="input-group-addon">€</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="paye" class="col-sm-4 control-label">
                                            <i class="glyphicon glyphicon-credit-card"></i> Payé
                                        </label>
                                        <div class="col-sm-8">
                                            <select id="paye" name="paye" class="form-control" required>
                                                <option value="oui" <?= ($tirage['paye'] == 'oui') ? 'selected' : '' ?>>Oui</option>
                                                <option value="non" <?= ($tirage['paye'] == 'non') ? 'selected' : '' ?>>Non</option>
                                                <option value="partiel" <?= ($tirage['paye'] == 'partiel') ? 'selected' : '' ?>>Partiel</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="cb" class="col-sm-4 control-label">
                                            <i class="glyphicon glyphicon-credit-card"></i> Montant payé
                                        </label>
                                        <div class="col-sm-8">
                                            <div class="input-group">
                                                <input id="cb" name="cb" value="<?= $tirage['cb'] ?>" 
                                                       class="form-control" type="number" step="0.01" min="0"
                                                       data-toggle="tooltip" data-placement="top" 
                                                       title="Montant déjà payé (optionnel)">
                                                <span class="input-group-addon">€</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="mot" class="col-sm-2 control-label">
                                            <i class="glyphicon glyphicon-comment"></i> Message
                                        </label>
                                        <div class="col-sm-10">
                                            <textarea id="mot" name="mot" class="form-control" rows="3" 
                                                      placeholder="Message ou notes supplémentaires..."
                                                      data-toggle="tooltip" data-placement="top" 
                                                      title="Message ou notes optionnelles"><?= htmlspecialchars($tirage['mot']) ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <hr>
                                    <div class="btn-group btn-group-justified" role="group">
                                        <div class="btn-group" role="group">
                                            <button type="submit" id="saveBtn" name="save" class="btn btn-success btn-lg">
                                                <i class="glyphicon glyphicon-floppy-save"></i> Sauvegarder
                                            </button>
                                        </div>
                                        <div class="btn-group" role="group">
                                            <button type="submit" id="deleteBtn" name="delete" class="btn btn-danger btn-lg"
                                                    onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce tirage ? Cette action est irréversible.');">
                                                <i class="glyphicon glyphicon-trash"></i> Supprimer
                                            </button>
                                        </div>
                                        <div class="btn-group" role="group">
                                            <a href="?admin&tirages" class="btn btn-default btn-lg">
                                                <i class="glyphicon glyphicon-arrow-left"></i> Annuler
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialiser les tooltips
    $('[data-toggle="tooltip"]').tooltip();
    
    // Validation côté client - DÉSACTIVÉE TEMPORAIREMENT POUR DEBUG
    /*
    $('#editForm').on('submit', function(e) {
        var isValid = true;
        
        // Vérifier que le prix est positif
        var prix = parseFloat($('#prix').val());
        if (prix < 0) {
            alert('Le prix doit être positif');
            isValid = false;
        }
        
        // Le montant payé peut dépasser le prix total (pour les pourboires)
        var cb = parseFloat($('#cb').val()) || 0;
        if (cb < 0) {
            alert('Le montant payé ne peut pas être négatif');
            isValid = false;
        }
        
        // Vérifier les champs numériques pour les duplicateurs
                                    <?php if($tirage['machine'] == "A3" || $tirage['machine'] == "A4"){?>
        var passage_av = parseInt($('#passage_av').val());
        var passage_ap = parseInt($('#passage_ap').val());
        var master_av = parseInt($('#master_av').val());
        var master_ap = parseInt($('#master_ap').val());
        
        if (passage_av < 0 || passage_ap < 0 || master_av < 0 || master_ap < 0) {
            alert('Les valeurs de passage et master doivent être positives');
            isValid = false;
        }
            <?php } else {?>
        var nb_f = parseInt($('#nb_f').val());
        if (nb_f < 1) {
            alert('Le nombre de feuilles doit être au moins de 1');
            isValid = false;
        }
        <?php } ?>
        
        if (!isValid) {
            e.preventDefault();
        }
    });
    */
    
    // Animation des boutons
    $('.btn').hover(
        function() { $(this).addClass('pulse'); },
        function() { $(this).removeClass('pulse'); }
    );
    
    // Calcul automatique du solde restant
    $('#prix, #cb').on('input', function() {
        var prix = parseFloat($('#prix').val()) || 0;
        var cb = parseFloat($('#cb').val()) || 0;
        var solde = prix - cb;
        
        if (solde > 0) {
            $('#cb').parent().find('.input-group-addon').after(
                '<span class="help-block text-info">Solde restant: ' + solde.toFixed(2) + '€</span>'
            );
        }
    });
});
</script>

<style>
.panel-primary {
    border: none;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.panel-heading {
    background: linear-gradient(135deg, #337ab7 0%, #286090 100%);
    border: none;
}

.panel-title {
    font-weight: bold;
}

.form-control:focus {
    border-color: #337ab7;
    box-shadow: 0 0 0 0.2rem rgba(51, 122, 183, 0.25);
}

.btn {
    transition: all 0.3s ease;
    border-radius: 6px;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.pulse {
    animation: pulse 0.6s;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.help-block {
    font-size: 12px;
    margin-top: 5px;
}

.form-group {
    margin-bottom: 20px;
}

.control-label {
    font-weight: 600;
    color: #555;
}

hr {
    border-color: #ddd;
    margin: 20px 0;
}

.label {
    font-size: 12px;
    padding: 4px 8px;
}
</style>
<?php } ?>