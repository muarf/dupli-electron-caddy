<?php
/**
 * Modal de sélection/création de session pour impressions détectées
 */
?>
<div class="modal fade" id="session-select-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">
                    <i class="fa fa-print"></i> Impression détectée : <span id="modal-doc"></span>
                </h4>
            </div>
            <div class="modal-body">
                <p class="text-muted">Assigner cette impression à :</p>
                
                <!-- Liste des sessions existantes -->
                <div id="existing-sessions" style="margin-bottom: 20px;">
                    <h5>Sessions actives</h5>
                    <div class="list-group" id="session-list">
                        <!-- Généré dynamiquement par JS -->
                    </div>
                    <p class="text-muted small" id="no-sessions-msg" style="display: none;">
                        <em>Aucune session active pour le moment</em>
                    </p>
                </div>
                
                <hr>
                
                <!-- Ou créer nouvelle session -->
                <div class="new-session-form">
                    <h5>Ou créer une nouvelle session :</h5>
                    <div class="form-group">
                        <label for="new-session-contact">Contact <span class="text-danger">*</span></label>
                        <input type="text" 
                               id="new-session-contact" 
                               class="form-control" 
                               placeholder="Nom ou pseudo du contact"
                               required>
                    </div>
                    <div class="form-group">
                        <label for="new-session-name">Nom de session (optionnel)</label>
                        <input type="text" 
                               id="new-session-name" 
                               class="form-control" 
                               placeholder="Ex: Formation Matin, Commande Client A">
                        <p class="help-block small">Un nom descriptif pour identifier cette session</p>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    <i class="fa fa-times"></i> Ignorer cette impression
                </button>
                <button type="button" class="btn btn-primary">
                    <i class="fa fa-check"></i> Créer et assigner
                </button>
            </div>
        </div>
    </div>
</div>
