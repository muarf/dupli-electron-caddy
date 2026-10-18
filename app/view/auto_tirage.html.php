<?php
/**
 * Vue Tapis de Tri des Impressions (Remplacement auto_tirage)
 * Interface atelier réactive, simple et rapide :
 * - Cartes visuelles avec miniatures, format, prix calculé
 * - Sélection multi-documents au clic
 * - Barre flottante d'action inférieure (Sticky Bottom Bar)
 * - Modal d'enregistrement direct (Contact libre, Payé/Non payé en espèces)
 * - Possibilité d'intégrer un tirage manuel
 */
?>

<style>
/* --- Styles Modernes du Tapis de Tri --- */
.tapis-container {
    max-width: 1400px;
    margin: 1.5rem auto 100px auto;
    padding: 0 15px;
}

/* En-tête */
.tapis-header {
    background: #ffffff;
    border-radius: 12px;
    padding: 1.5rem 2rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.06);
    margin-bottom: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.tapis-header-titles h1 {
    font-size: 1.8rem;
    margin: 0 0 5px 0;
    font-weight: 600;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 10px;
}

.tapis-header-titles p {
    margin: 0;
    color: #7f8c8d;
    font-size: 0.95rem;
}

.tapis-header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Grille des impressions */
.tapis-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

/* Carte de tirage */
.tapis-card {
    background: #ffffff;
    border: 2px solid #eef2f5;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.165, 0.84, 0.44, 1);
    position: relative;
    display: flex;
    flex-direction: column;
}

.tapis-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.09);
    border-color: #cbd5e1;
}

.tapis-card.selected {
    border-color: #27ae60;
    background: #f6fbf8;
    box-shadow: 0 4px 15px rgba(39, 174, 96, 0.25);
}

.card-selection-check {
    position: absolute;
    top: 10px;
    left: 10px;
    z-index: 5;
    background: rgba(255,255,255,0.9);
    border-radius: 6px;
    padding: 3px 6px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.15);
}

.card-selection-check input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
    margin: 0;
    accent-color: #27ae60;
}

/* Miniature */
.card-thumb-container {
    height: 210px;
    background: #f8fafc;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border-bottom: 1px solid #edf2f7;
    position: relative;
}

.card-thumb {
    max-width: 90%;
    max-height: 90%;
    object-fit: contain;
    transition: transform 0.2s ease;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}

.tapis-card:hover .card-thumb {
    transform: scale(1.04);
}

/* Corps de la carte */
.card-body-content {
    padding: 14px 16px;
    display: flex;
    flex-direction: column;
    flex-grow: 1;
    justify-content: space-between;
}

.card-title-row {
    margin-bottom: 10px;
}

.card-doc-name {
    font-weight: 600;
    color: #2c3e50;
    font-size: 1rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    word-break: break-word;
    line-height: 1.3;
}

.card-badges-row {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 14px;
}

.tapis-badge {
    font-size: 0.75rem;
    padding: 3px 8px;
    border-radius: 6px;
    font-weight: 500;
    background: #edf2f7;
    color: #4a5568;
}

.badge-printer { background: #e2e8f0; color: #334155; }
.badge-color { background: #fee2e2; color: #dc2626; font-weight: 600; }
.badge-mono { background: #f1f5f9; color: #475569; }
.badge-duplex { background: #e0f2fe; color: #0284c7; }
.badge-simplex { background: #f8fafc; color: #64748b; }
.badge-copies { background: #fef3c7; color: #d97706; font-weight: 600; }
.badge-pages { background: #f3e8ff; color: #7e22ce; }

.card-footer-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    border-top: 1px solid #f1f5f9;
    padding-top: 10px;
}

.card-time {
    font-size: 0.8rem;
    color: #94a3b8;
}

.card-price {
    font-size: 1.3rem;
    font-weight: 700;
    color: #27ae60;
}

/* État vide */
.tapis-empty-state {
    background: #ffffff;
    border-radius: 12px;
    padding: 60px 20px;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.04);
}

.tapis-empty-state i {
    font-size: 3.5rem;
    color: #cbd5e1;
    margin-bottom: 15px;
}

.tapis-empty-state h3 {
    color: #475569;
    margin-bottom: 8px;
}

.tapis-empty-state p {
    color: #94a3b8;
}

/* --- Barre Flottante Inférieure (Sticky Bottom Bar) --- */
.bottom-action-bar {
    position: fixed;
    bottom: -90px;
    left: 0;
    right: 0;
    background: #1e293b;
    color: #ffffff;
    padding: 15px 30px;
    box-shadow: 0 -4px 25px rgba(0,0,0,0.25);
    z-index: 1040;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: bottom 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
    flex-wrap: wrap;
    gap: 15px;
}

.bottom-action-bar.visible {
    bottom: 0;
}

.bar-summary-zone {
    display: flex;
    align-items: center;
    gap: 20px;
}

.bar-count-badge {
    background: #3b82f6;
    color: #ffffff;
    border-radius: 20px;
    padding: 4px 14px;
    font-weight: 600;
    font-size: 0.95rem;
}

.bar-total-amount {
    font-size: 1.6rem;
    font-weight: 700;
    color: #4ade80;
}

.bar-buttons-zone {
    display: flex;
    align-items: center;
    gap: 12px;
}

.btn-tapis-save {
    background: #10b981;
    color: white;
    font-size: 1.1rem;
    font-weight: 600;
    padding: 10px 24px;
    border-radius: 8px;
    border: none;
    transition: background 0.2s;
}

.btn-tapis-save:hover {
    background: #059669;
    color: white;
}

.btn-tapis-manual {
    background: #475569;
    color: white;
    font-size: 0.95rem;
    padding: 10px 16px;
    border-radius: 8px;
    border: none;
}

.btn-tapis-manual:hover {
    background: #64748b;
    color: white;
}

.btn-tapis-delete {
    background: transparent;
    color: #ef4444;
    border: 1px solid #ef4444;
    padding: 10px 16px;
    border-radius: 8px;
    font-size: 0.95rem;
}

.btn-tapis-delete:hover {
    background: #ef4444;
    color: white;
}

/* Modals */
.tapis-modal-content {
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

.tapis-modal-header {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 18px 24px;
}

.tapis-modal-header h4 {
    margin: 0;
    font-weight: 600;
    color: #1e293b;
}

.tapis-modal-body {
    padding: 24px;
}

.btn-status-group {
    display: flex;
    gap: 10px;
    margin-top: 8px;
}

.btn-status-group .btn {
    flex: 1;
    padding: 12px;
    font-size: 1.05rem;
    font-weight: 600;
    border-radius: 8px;
}

/* Toasts de feedback */
.tapis-toast {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #1e293b;
    color: #ffffff;
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: 500;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    z-index: 2000;
    opacity: 0;
    transform: translateY(-10px);
    transition: all 0.3s ease;
}

.tapis-toast.visible {
    opacity: 1;
    transform: translateY(0);
}

.tapis-toast.toast-success { background: #059669; }
.tapis-toast.toast-info { background: #0284c7; }
</style>

<div class="tapis-container">
    <!-- En-tête du Tapis de Tri -->
    <div class="tapis-header">
        <div class="tapis-header-titles">
            <h1><i class="fa fa-th-large text-primary"></i> Tapis de Tri des Impressions</h1>
            <p>Sélectionnez les impressions au fur et à mesure pour enregistrer le tirage en direct.</p>
        </div>

        <div class="tapis-header-actions">
            <button class="btn btn-default" onclick="window.tapisRefresh()" title="Actualiser et synchroniser avec les imprimantes">
                <i id="btn-tapis-refresh" class="fa fa-refresh"></i> Actualiser
            </button>
            <button class="btn btn-default" onclick="window.tapisToggleSelectAll()" title="Tout cocher / décocher">
                <i class="fa fa-check-square-o"></i> Tout sélectionner
            </button>
            <a href="?tirage_multimachines" class="btn btn-default" title="Accéder à la saisie manuelle de tirages">
                <i class="fa fa-pencil"></i> Tirage manuel
            </a>
            <a href="?admin&tirages" class="btn btn-default" title="Gestion des tirages et encaissements passés">
                <i class="fa fa-list"></i> Historique / Règlements
            </a>
        </div>
    </div>

    <!-- État vide (quand aucune impression en attente) -->
    <div id="empty-state" class="tapis-empty-state" style="display: none;">
        <i class="fa fa-inbox"></i>
        <h3>Le tapis de tri est vide</h3>
        <p>Les impressions envoyées par les utilisateurs apparaîtront ici automatiquement dès leur arrivée.</p>
    </div>

    <!-- Grille réactive des tirages -->
    <div id="tapis-grid" class="tapis-grid">
        <!-- Rendu dynamique par tapis-tri.js -->
    </div>
</div>

<!-- --- Barre Flottante d'Action Inférieure (Sticky Bottom Bar) --- -->
<div id="bottom-action-bar" class="bottom-action-bar">
    <div class="bar-summary-zone">
        <span class="bar-count-badge"><span id="bar-selected-count">0</span> sélectionné(s)</span>
        <span>Total calculé : <strong id="bar-total-amount" class="bar-total-amount">0.00 €</strong></span>
    </div>

    <div class="bar-buttons-zone">
        <button class="btn-tapis-delete" id="btn-delete-selected" onclick="window.tapisDeleteSelected()" title="Rejeter les impressions cochées (feuille ratée, brouillon)">
            <i class="fa fa-trash"></i> Supprimer
        </button>
        <button class="btn-tapis-save" id="btn-open-save" onclick="window.tapisOpenSaveModal()">
            <i class="fa fa-check-circle"></i> Enregistrer le tirage
        </button>
    </div>
</div>

<!-- --- MODAL D'ENREGISTREMENT DIRECT --- -->
<div class="modal fade" id="saveModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content tapis-modal-content">
            <div class="tapis-modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Fermer"><span aria-hidden="true">&times;</span></button>
                <h4><i class="fa fa-bookmark text-success"></i> Enregistrement du tirage</h4>
            </div>
            <div class="tapis-modal-body">
                <div class="form-group">
                    <label for="modal-contact-input" style="font-weight: 600; font-size: 1rem;">
                        <i class="fa fa-user"></i> Nom du contact *
                    </label>
                    <input type="text" id="modal-contact-input" class="form-control input-lg" placeholder="Ex: Jean, Collectif X, Atelier..." autocomplete="off" required>
                    <small class="text-muted">Inscrivez le nom directement à la volée.</small>
                </div>

                <div class="form-group" style="margin-top: 20px;">
                    <label style="font-weight: 600; font-size: 1rem;">
                        <i class="fa fa-money"></i> Statut du règlement (Espèces)
                    </label>
                    <div class="btn-status-group">
                        <button type="button" id="btn-paye-oui" class="btn btn-success active" onclick="window.setPaymentStatus('oui')">
                            <i class="fa fa-check"></i> Payé (Espèces)
                        </button>
                        <button type="button" id="btn-paye-non" class="btn btn-default" onclick="window.setPaymentStatus('non')">
                            <i class="fa fa-clock-o"></i> Non payé (En attente)
                        </button>
                    </div>
                </div>

                <div class="form-group" id="group-cb-montant" style="margin-top: 15px;">
                    <label for="modal-cb-input" style="font-weight: 600; font-size: 0.95rem;">
                        <i class="fa fa-eur"></i> Montant payé / Combien encaissé (€)
                    </label>
                    <div class="input-group">
                        <input type="number" step="0.01" min="0" id="modal-cb-input" class="form-control input-lg" placeholder="0.00">
                        <span class="input-group-addon">€</span>
                    </div>
                    <small class="text-muted">Prérempli avec le total calculé. Modifiez si la personne donne une autre somme.</small>
                </div>

                <div class="form-group" style="margin-top: 20px;">
                    <label for="modal-mot-input" style="font-weight: 500; color: #64748b;">
                        <i class="fa fa-comment-o"></i> Commentaire / Note (optionnel)
                    </label>
                    <input type="text" id="modal-mot-input" class="form-control" placeholder="Remarque, projet...">
                </div>

                <div style="background: #f8fafc; border-radius: 8px; padding: 15px; margin-top: 20px; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 1.1rem; color: #475569;">Total à enregistrer :</span>
                    <strong id="modal-final-total" style="font-size: 1.6rem; color: #059669;">0.00 €</strong>
                </div>
            </div>
            <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0;">
                <button type="button" class="btn btn-default btn-lg" data-dismiss="modal">Annuler</button>
                <button type="button" id="btn-confirm-save" class="btn btn-success btn-lg" onclick="window.tapisConfirmSave()">
                    <i class="fa fa-check"></i> Valider et enregistrer
                </button>
            </div>
        </div>
    </div>
</div>

<!-- --- MODAL APERÇU MINIATURE --- -->
<div class="modal fade" id="previewModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content tapis-modal-content">
            <div class="tapis-modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Fermer"><span aria-hidden="true">&times;</span></button>
                <h4 id="preview-modal-title">Aperçu du document</h4>
            </div>
            <div class="modal-body text-center" style="background: #334155; padding: 20px;">
                <img id="preview-modal-img" src="" alt="Aperçu" style="max-width: 100%; max-height: 80vh; object-fit: contain; box-shadow: 0 4px 20px rgba(0,0,0,0.5);">
            </div>
        </div>
    </div>
</div>

<!-- Inclusion du contrôleur Tapis de Tri -->
<script src="js/tapis-tri.js" defer></script>
