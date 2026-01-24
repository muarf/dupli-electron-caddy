<!-- Edit Job Modal -->
<div class="modal fade" id="edit-job-modal" tabindex="-1" role="dialog" aria-labelledby="edit-job-modal-title" aria-hidden="true" style="z-index: 10050;">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="edit-job-modal-title">
                    <i class="fa fa-pencil text-primary"></i> Modifier l'impression
                </h4>
            </div>
            <div class="modal-body">
                <form id="edit-job-form">
                    <!-- Common Fields -->
                    <div class="form-group">
                        <label>Document</label>
                        <input type="text" class="form-control" id="edit-document-name" readonly>
                    </div>

                    <!-- Photocopier Specific -->
                    <div id="edit-photocop-fields" style="display:none;">
                         <div class="row">
                            <div class="col-xs-4">
                                <div class="form-group">
                                    <label for="edit-copies">Exemplaires</label>
                                    <input type="number" class="form-control" id="edit-copies" min="1" max="9999">
                                </div>
                            </div>
                            <div class="col-xs-4">
                                <div class="form-group">
                                    <label for="edit-pages">Pages (par ex)</label>
                                    <input type="number" class="form-control" id="edit-pages" min="1" step="1">
                                </div>
                            </div>
                            <div class="col-xs-4">
                                <div class="form-group">
                                    <label for="edit-paper-size">Format</label>
                                    <select class="form-control" id="edit-paper-size">
                                        <option value="A4">A4</option>
                                        <option value="A3">A3</option>
                                        <option value="A5">A5</option>
                                        <option value="SRA3">SRA3</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-xs-6">
                                <div class="form-group">
                                    <label>Couleur</label>
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="edit-color">
                                        <label class="custom-control-label" for="edit-color">Impression Couleur</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xs-6">
                                <div class="form-group">
                                    <label>Recto-Verso</label>
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="edit-duplex">
                                        <label class="custom-control-label" for="edit-duplex">Activer R/V</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                             <label for="edit-fill-rate">Taux de couverture estimé (%)</label>
                             <input type="number" class="form-control" id="edit-fill-rate" min="0" max="100" step="1">
                             <small class="text-muted">Utilisé pour le calcul du coût encre.</small>
                        </div>
                    </div>

                    <!-- Duplicopieur Specific -->
                    <div id="edit-dupli-fields" style="display:none;">
                         <div class="row">
                            <div class="col-xs-6">
                                <div class="form-group">
                                    <label for="edit-masters">Nombre de Masters</label>
                                    <input type="number" class="form-control" id="edit-masters" min="0">
                                </div>
                            </div>
                            <div class="col-xs-6">
                                <div class="form-group">
                                    <label for="edit-passages">Nombre de Passages</label>
                                    <input type="number" class="form-control" id="edit-passages" min="0">
                                </div>
                            </div>
                        </div>
                         <div class="form-group">
                            <label for="edit-tambour">Tambour / Couleur</label>
                            <select class="form-control" id="edit-tambour">
                                <!-- Populated dynamically -->
                            </select>
                        </div>
                        
                         <div class="row">
                            <div class="col-xs-6">
                                <div class="form-group">
                                    <label>Recto-Verso (Manuel)</label>
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="edit-dupli-duplex">
                                        <label class="custom-control-label" for="edit-dupli-duplex">Activer R/V</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" onclick="saveEditedJob()">
                    <i class="fa fa-save"></i> Enregistrer
                </button>
            </div>
        </div>
    </div>
</div>
