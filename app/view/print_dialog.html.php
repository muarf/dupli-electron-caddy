<!-- Modal de dialogue d'impression -->
<div class="modal fade" id="printDialogModal" tabindex="-1" role="dialog" aria-labelledby="printDialogModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header"
                style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white;">
                <h4 class="modal-title" id="printDialogModalLabel">
                    <i class="fa fa-print"></i> Imprimer le document
                </h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"
                    style="color: white; opacity: 0.8;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 30px;">
                <!-- Message de chargement -->
                <div id="printDialogLoading" class="text-center" style="display: none;">
                    <i class="fa fa-spinner fa-spin fa-3x" style="color: #007bff;"></i>
                    <p style="margin-top: 15px;">Chargement des imprimantes...</p>
                </div>

                <!-- Message d'erreur -->
                <div id="printDialogError" class="alert alert-danger" style="display: none;">
                    <i class="fa fa-exclamation-triangle"></i> <span id="printDialogErrorText"></span>
                </div>

                <!-- Formulaire d'impression -->
                <form id="printDialogForm" style="display: none;">
                    <!-- Sélecteur d'imprimante -->
                    <div class="form-group">
                        <label for="printPrinterSelect">
                            <i class="fa fa-print"></i> Imprimante
                        </label>
                        <select class="form-control" id="printPrinterSelect" required>
                            <option value="">Chargement...</option>
                        </select>
                        <small class="form-text text-muted">Sélectionnez l'imprimante à utiliser</small>
                    </div>

                    <!-- Nombre de copies -->
                    <div class="form-group">
                        <label for="printCopies">
                            <i class="fa fa-copy"></i> Nombre de copies
                        </label>
                        <input type="number" class="form-control" id="printCopies" min="1" max="99" value="1" required>
                        <small class="form-text text-muted">Nombre de copies à imprimer</small>
                    </div>

                    <!-- Options avancées (masquées par défaut) -->
                    <div class="form-group">
                        <button type="button" class="btn btn-link" id="printAdvancedToggle" style="padding: 0;">
                            <i class="fa fa-chevron-down"></i> Options avancées
                        </button>
                    </div>

                    <div id="printAdvancedOptions" style="display: none;">
                        <!-- Bac à papier -->
                        <div class="form-group">
                            <label for="printInputSlot">
                                <i class="fa fa-inbox"></i> Bac à papier
                            </label>
                            <select class="form-control" id="printInputSlot">
                                <option value="">Par défaut</option>
                            </select>
                            <small class="form-text text-muted">Sélectionnez le bac à utiliser</small>
                        </div>

                        <!-- Format papier -->
                        <div class="form-group">
                            <label for="printPageSize">
                                <i class="fa fa-file-o"></i> Format papier
                            </label>
                            <select class="form-control" id="printPageSize">
                                <option value="">Par défaut</option>
                            </select>
                            <small class="form-text text-muted">Format de papier à utiliser</small>
                        </div>

                        <!-- Mode couleur -->
                        <div class="form-group">
                            <label for="printColorMode">
                                <i class="fa fa-paint-brush"></i> Mode couleur
                            </label>
                            <select class="form-control" id="printColorMode">
                                <option value="">Par défaut</option>
                            </select>
                            <small class="form-text text-muted">Couleur ou noir et blanc</small>
                        </div>

                        <!-- Recto-verso -->
                        <div class="form-group">
                            <label for="printDuplex">
                                <i class="fa fa-file-text-o"></i> Recto-verso
                            </label>
                            <select class="form-control" id="printDuplex">
                                <option value="Simplex">Recto uniquement</option>
                                <option value="DuplexNoTumble">Recto-verso (long bord)</option>
                                <option value="DuplexTumble">Recto-verso (court bord)</option>
                            </select>
                            <small class="form-text text-muted">Mode d'impression recto-verso</small>
                        </div>

                        <!-- Résolution (si disponible) -->
                        <div class="form-group" id="printResolutionGroup" style="display: none;">
                            <label for="printResolution">
                                <i class="fa fa-image"></i> Résolution
                            </label>
                            <select class="form-control" id="printResolution">
                                <option value="">Par défaut</option>
                            </select>
                            <small class="form-text text-muted">Résolution d'impression</small>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fa fa-times"></i> Annuler
                </button>
                <button type="button" class="btn btn-primary" id="printDialogPrintBtn" disabled>
                    <i class="fa fa-print"></i> Imprimer
                </button>
            </div>
        </div>
    </div>
</div>

<script src="js/print-dialog.js" defer></script>


<style>
    #printDialogModal .modal-content {
        border-radius: 8px;
        border: none;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    }

    #printDialogModal .modal-header {
        border-bottom: none;
        border-radius: 8px 8px 0 0;
    }

    #printDialogModal .modal-footer {
        border-top: 1px solid #e9ecef;
        padding: 15px 30px;
    }

    #printDialogModal .form-control {
        border: 2px solid #e9ecef;
        border-radius: 6px;
        padding: 10px 15px;
        transition: border-color 0.3s ease;
    }

    #printDialogModal .form-control:focus {
        border-color: #007bff;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    }

    #printDialogModal .btn-primary {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        border: none;
        padding: 10px 30px;
        font-weight: 500;
    }

    #printDialogModal .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0, 123, 255, 0.3);
    }

    #printDialogModal .btn-link {
        color: #007bff;
        text-decoration: none;
    }

    #printDialogModal .btn-link:hover {
        text-decoration: underline;
    }
</style>
