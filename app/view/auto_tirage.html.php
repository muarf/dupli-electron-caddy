<?php
$base_path = $base_path ?? '';
require_once __DIR__ . '/../controler/functions/database.php';
$db = create_database_manager();
$photocopieurs = $db->select("SELECT id, marque, modele FROM photocopieurs WHERE actif = 1");
$duplicopieurs = $db->select("SELECT id, marque, modele FROM duplicopieurs WHERE actif = 1");
?>

<link rel="stylesheet" href="<?= $base_path ?>css/tapis-tri.css">

<div class="tapis-container">
    <!-- Bannière d'en-tête du Tapis de Tri -->
    <div class="tapis-header-banner">
        <div>
            <h2>
                <i class="fa fa-th-large text-primary"></i> 
                <?php _e('auto_tirage.tapis_tri_title'); ?>
                <span id="tapis-badge-count" class="badge badge-light" style="font-size: 1rem; color: #1e293b;">0</span>
            </h2>
            <p><?php _e('auto_tirage.tapis_tri_subtitle'); ?></p>
        </div>
        <div class="tapis-header-status">
            <span class="tapis-live-indicator">
                <span class="tapis-live-dot"></span>
                Écoute active
            </span>
            <button class="btn btn-sm btn-outline-light" onclick="window.tapisRefresh()" title="Actualiser le tapis de tri">
                <i id="btn-tapis-refresh" class="fa fa-refresh"></i> Actualiser
            </button>
            <button class="btn btn-sm btn-outline-light" onclick="window.tapisToggleSelectAll()" title="Tout cocher / décocher">
                <i class="fa fa-check-square-o"></i> Tout sélectionner
            </button>
            <button class="btn btn-sm btn-outline-light" onclick="tapisOpenManualModal()">
                <i class="fa fa-plus"></i> <?php _e('auto_tirage.add_manual_print'); ?>
            </button>
            <a href="?tirage_multimachines" class="btn btn-sm btn-outline-light" title="Accéder à la saisie manuelle traditionnelle">
                <i class="fa fa-print"></i> Saisie manuelle
            </a>
        </div>
    </div>

    <!-- État vide : lorsque le tapis est vide -->
    <div id="tapis-empty-state" class="tapis-empty-state" style="display: none;">
        <div class="tapis-empty-icon">
            <i class="fa fa-inbox"></i>
        </div>
        <h3><?php _e('auto_tirage.tapis_empty_title'); ?></h3>
        <p><?php _e('auto_tirage.tapis_empty_desc'); ?></p>
        <div class="mt-4">
            <button class="btn btn-primary" onclick="tapisOpenManualModal()">
                <i class="fa fa-plus-circle"></i> <?php _e('auto_tirage.add_manual_print'); ?>
            </button>
        </div>
    </div>

    <!-- Grille de cartes d'impressions du Tapis de Tri -->
    <div id="tapis-grid" class="tapis-grid">
        <!-- Les cartes d'impression seront injectées dynamiquement par tapis-tri.js -->
    </div>
</div>

<!-- Barre d'action inférieure flottante (Sticky Bottom Bar) -->
<div id="tapis-bottom-bar" class="tapis-bottom-bar">
    <div class="tapis-bar-left">
        <label class="tapis-checkbox-container" title="Tout sélectionner / désélectionner">
            <input type="checkbox" id="tapis-select-all">
            <span class="tapis-checkbox-checkmark"></span>
        </label>
        <div class="tapis-bar-selection-summary">
            <span id="tapis-selected-count">0 document sélectionné</span>
            <span id="tapis-selected-total" class="tapis-bar-total-badge">0.00 €</span>
        </div>
    </div>
    <div class="tapis-bar-actions">
        <button class="btn btn-outline-primary btn-sm" onclick="tapisOpenManualModal()">
            <i class="fa fa-plus"></i> <?php _e('auto_tirage.add_manual_print'); ?>
        </button>
        <button class="btn btn-outline-secondary btn-sm" onclick="tapisOpenAssignModal()">
            <i class="fa fa-folder-open"></i> <?php _e('auto_tirage.assign_to_account'); ?>
        </button>
        <button class="btn btn-outline-danger btn-sm" onclick="tapisDeleteSelected()">
            <i class="fa fa-trash"></i> <?php _e('common.delete'); ?>
        </button>
        <button id="tapis-btn-checkout" class="btn tapis-btn-checkout" onclick="tapisOpenCheckoutModal()">
            <i class="fa fa-credit-card"></i> <strong>ENCAISSER DIRECT (0.00 €)</strong>
        </button>
    </div>
</div>

<!-- Modale 1 : Encaissement Express Direct au Comptoir -->
<div class="modal fade" id="modal-checkout-direct" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content" style="border-radius: 16px; overflow: hidden; box-shadow: 0 15px 35px rgba(0,0,0,0.2);">
            <div class="modal-header bg-dark text-white" style="padding: 1.25rem 1.5rem;">
                <h5 class="modal-title font-weight-bold">
                    <i class="fa fa-money text-success"></i> <?php _e('auto_tirage.checkout_express'); ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 1.5rem;">
                <!-- Total à payer en grand -->
                <div class="text-center p-3 mb-3" style="background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0;">
                    <span class="text-muted text-uppercase" style="font-size: 0.8rem; letter-spacing: 1px; font-weight: 600;">Total à régler</span>
                    <div id="checkout-modal-total" style="font-size: 2.5rem; font-weight: 800; color: #10b981; line-height: 1.2;">
                        0.00 €
                    </div>
                </div>

                <!-- Récapitulatif condensé des documents -->
                <div id="checkout-modal-items-list" class="mb-3" style="max-height: 140px; overflow-y: auto; background: #fff; border: 1px solid #edf2f7; border-radius: 8px;">
                    <!-- Rempli en JS -->
                </div>

                <!-- Nom du contact / Client de passage -->
                <div class="form-group mb-3">
                    <label class="font-weight-bold" style="font-size: 0.9rem;"><?php _e('auto_tirage.client_name'); ?></label>
                    <input type="text" id="checkout-client-name" class="form-control" value="Passage" placeholder="Client de passage ou nom...">
                </div>

                <!-- As-tu payé ? (Comme sur tirage_multimachine) -->
                <div class="form-group mb-3">
                    <label class="font-weight-bold" style="font-size: 0.95rem;">
                        <strong><?php _e('tirage_multimachines.have_you_paid'); ?></strong>
                    </label>
                    <div class="d-flex align-items-center" style="gap: 25px; margin-top: 6px;">
                        <label class="radio-inline mb-0" style="font-size: 1.1rem; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                            <input type="radio" name="checkout_paye" value="oui" id="checkout-paye-oui" checked onchange="tapisUpdatePaymentAmount()">
                            <span class="font-weight-bold text-success"><?php _e('tirage_multimachines.yes'); ?></span>
                        </label>
                        <label class="radio-inline mb-0" style="font-size: 1.1rem; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                            <input type="radio" name="checkout_paye" value="non" id="checkout-paye-non" onchange="tapisUpdatePaymentAmount()">
                            <span class="font-weight-bold text-danger"><?php _e('tirage_multimachines.no'); ?></span>
                        </label>
                    </div>
                </div>

                <!-- Montant payé (Combien) -->
                <div class="form-group mb-3" id="checkout-amount-group">
                    <label class="font-weight-bold" for="checkout-amount-paid" style="font-size: 0.9rem;">
                        <?php _e('tirage_multimachines.amount_paid'); ?>
                    </label>
                    <div class="input-group">
                        <input type="number" step="0.01" min="0" id="checkout-amount-paid" class="form-control form-control-lg font-weight-bold text-center" style="font-size: 1.4rem;" placeholder="0.00">
                        <div class="input-group-append">
                            <span class="input-group-text font-weight-bold">€</span>
                        </div>
                    </div>
                    <small class="form-text text-muted"><?php _e('tirage_multimachines.amount_in_euros'); ?></small>
                </div>

                <!-- Un petit mot -->
                <div class="form-group mb-3">
                    <label class="font-weight-bold" for="checkout-mot" style="font-size: 0.9rem;">
                        <?php _e('tirage_multimachines.message_placeholder'); ?>
                    </label>
                    <textarea id="checkout-mot" class="form-control" rows="2" placeholder="<?php _e('tirage_multimachines.message_placeholder'); ?>"></textarea>
                </div>
            </div>
            <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 1rem 1.5rem;">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Annuler</button>
                <button type="button" id="btn-submit-checkout" class="btn btn-success btn-lg px-4" onclick="tapisSubmitCheckout()">
                    <i class="fa fa-check"></i> <?php _e('auto_tirage.validate_payment'); ?> (Entrée)
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modale 2 : Ajout Tirage Manuel Express -->
<div class="modal fade" id="modal-add-manual" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content" style="border-radius: 14px;">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold"><i class="fa fa-plus-circle"></i> Ajouter un tirage manuel au panier</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="font-weight-bold">Machine</label>
                    <select id="manual-machine-select" class="form-control">
                        <?php foreach ($photocopieurs as $p): ?>
                            <option value="<?= htmlspecialchars($p['marque']) ?>">Photocopieur: <?= htmlspecialchars($p['marque'] . ' ' . $p['modele']) ?></option>
                        <?php endforeach; ?>
                        <?php foreach ($duplicopieurs as $d): ?>
                            <option value="<?= htmlspecialchars($d['marque']) ?>">Duplicopieur: <?= htmlspecialchars($d['marque'] . ' ' . $d['modele']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row">
                    <div class="col-6 form-group">
                        <label class="font-weight-bold">Format</label>
                        <select id="manual-taille-select" class="form-control">
                            <option value="A4">A4</option>
                            <option value="A3">A3</option>
                        </select>
                    </div>
                    <div class="col-6 form-group">
                        <label class="font-weight-bold">Couleur / Noir</label>
                        <select id="manual-couleur-select" class="form-control">
                            <option value="non">Noir & Blanc</option>
                            <option value="oui">Couleur</option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6 form-group">
                        <label class="font-weight-bold">Recto-Verso</label>
                        <select id="manual-rv-select" class="form-control">
                            <option value="non">Recto</option>
                            <option value="oui">Recto-Verso</option>
                        </select>
                    </div>
                    <div class="col-6 form-group">
                        <label class="font-weight-bold">Nombre de feuilles</label>
                        <input type="number" id="manual-nbf-input" class="form-control" value="1" min="1">
                    </div>
                </div>
                <div class="form-group">
                    <label class="font-weight-bold">Prix calculé (€)</label>
                    <input type="number" id="manual-prix-input" class="form-control font-weight-bold" value="0.20" step="0.05" min="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" onclick="tapisSubmitManual()">
                    <i class="fa fa-cart-plus"></i> Ajouter au panier
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modale 3 : Assignation à un compte / session client -->
<div class="modal fade" id="modal-assign-session" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content" style="border-radius: 14px;">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title font-weight-bold"><i class="fa fa-folder-open"></i> Mettre sur un compte client</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="font-weight-bold">Choisir une session active existante</label>
                    <select id="assign-session-select" class="form-control">
                        <!-- Rempli en JS -->
                    </select>
                </div>
                <div class="text-center font-weight-bold text-muted my-2">— OU —</div>
                <div class="form-group">
                    <label class="font-weight-bold">Créer un nouveau compte / contact</label>
                    <input type="text" id="assign-new-contact-name" class="form-control" placeholder="Nom ou pseudo du contact...">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-info text-white font-weight-bold" onclick="tapisSubmitAssign()">
                    <i class="fa fa-check"></i> Assigner
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modale 4 : Zoom aperçu miniature -->
<div class="modal fade" id="modal-tapis-preview" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius: 14px;">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold" id="tapis-preview-title"><?php _e('auto_tirage.document_preview'); ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center p-4">
                <img id="tapis-preview-img" src="" style="max-width: 100%; max-height: 80vh; object-fit: contain; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.15);">
            </div>
        </div>
    </div>
</div>

<!-- Modale 5 : Éditer le tirage -->
<div class="modal fade" id="modal-edit-job" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content" style="border-radius: 14px;">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold">
                    <i class="fa fa-pencil"></i> Éditer le tirage
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit-job-id" value="">
                
                <div class="form-group">
                    <label class="font-weight-bold">Nom du document</label>
                    <input type="text" id="edit-job-docname" class="form-control">
                </div>

                <div class="form-group">
                    <label class="font-weight-bold">Machine</label>
                    <select id="edit-job-machine" class="form-control">
                        <optgroup label="Photocopieurs">
                            <?php foreach ($photocopieurs as $p): ?>
                                <option value="photocop_<?= $p['id'] ?>" data-type="photocop" data-id="<?= $p['id'] ?>" data-name="<?= htmlspecialchars($p['marque']) ?>">
                                    Photocopieur : <?= htmlspecialchars($p['marque'] . ' ' . $p['modele']) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Duplicopieurs">
                            <?php foreach ($duplicopieurs as $d): ?>
                                <option value="dupli_<?= $d['id'] ?>" data-type="dupli" data-id="<?= $d['id'] ?>" data-name="<?= htmlspecialchars($d['marque']) ?>">
                                    Duplicopieur : <?= htmlspecialchars($d['marque'] . ' ' . $d['modele']) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>

                <div class="row">
                    <div class="col-6 form-group">
                        <label class="font-weight-bold">Format</label>
                        <select id="edit-job-taille" class="form-control">
                            <option value="A4">A4</option>
                            <option value="A3">A3</option>
                        </select>
                    </div>
                    <div class="col-6 form-group">
                        <label class="font-weight-bold">Couleur / Noir</label>
                        <select id="edit-job-couleur" class="form-control">
                            <option value="non">Noir & Blanc</option>
                            <option value="oui">Couleur</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-6 form-group">
                        <label class="font-weight-bold">Recto-Verso</label>
                        <select id="edit-job-rv" class="form-control">
                            <option value="non">Recto (1 face)</option>
                            <option value="oui">Recto / Verso</option>
                        </select>
                    </div>
                    <div class="col-6 form-group">
                        <label class="font-weight-bold">Nombre d'exemplaires</label>
                        <input type="number" id="edit-job-copies" class="form-control" value="1" min="1">
                    </div>
                </div>

                <div class="row">
                    <div class="col-6 form-group">
                        <label class="font-weight-bold">Pages par exemplaire</label>
                        <input type="number" id="edit-job-pages" class="form-control" value="1" min="1">
                    </div>
                    <div class="col-6 form-group">
                        <label class="font-weight-bold">Prix forcé (€)</label>
                        <input type="number" step="0.01" min="0" id="edit-job-prix" class="form-control font-weight-bold" placeholder="Auto">
                    </div>
                </div>
                <small class="text-muted">Si le prix forcé est laissé vide, il sera automatiquement recalculé d'après les tarifs.</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary font-weight-bold" onclick="tapisSubmitEditJob()">
                    <i class="fa fa-save"></i> Enregistrer les modifications
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $base_path ?>js/tapis-tri.js" defer></script>
<script>
    const CONFIG = <?= json_encode([
        'strings' => [
            'auto_tirage.tapis_tri_title' => __js('auto_tirage.tapis_tri_title'),
            'auto_tirage.tapis_tri_subtitle' => __js('auto_tirage.tapis_tri_subtitle'),
            'auto_tirage.tapis_empty_title' => __js('auto_tirage.tapis_empty_title'),
            'auto_tirage.tapis_empty_desc' => __js('auto_tirage.tapis_empty_desc'),
            'auto_tirage.checkout_direct' => __js('auto_tirage.checkout_direct'),
            'auto_tirage.checkout_express' => __js('auto_tirage.checkout_express'),
            'auto_tirage.assign_to_account' => __js('auto_tirage.assign_to_account'),
            'auto_tirage.add_manual_print' => __js('auto_tirage.add_manual_print'),
            'auto_tirage.selected_docs' => __js('auto_tirage.selected_docs'),
            'auto_tirage.amount_received' => __js('auto_tirage.amount_received'),
            'auto_tirage.change_due' => __js('auto_tirage.change_due'),
            'auto_tirage.client_name' => __js('auto_tirage.client_name'),
            'auto_tirage.validate_payment' => __js('auto_tirage.validate_payment'),
            'auto_tirage.payment_cash' => __js('auto_tirage.payment_cash'),
            'auto_tirage.payment_card' => __js('auto_tirage.payment_card'),
            'auto_tirage.payment_check' => __js('auto_tirage.payment_check'),
            'common.delete' => __js('common.delete'),
        ]
    ]) ?>;
</script>
