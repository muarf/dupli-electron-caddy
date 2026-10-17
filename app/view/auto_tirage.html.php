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
                <div id="checkout-modal-items-list" class="mb-3" style="max-height: 120px; overflow-y: auto; background: #fff; border: 1px solid #edf2f7; border-radius: 8px;">
                    <!-- Rempli en JS -->
                </div>

                <!-- Nom du contact / Client de passage -->
                <div class="form-group mb-3">
                    <label class="font-weight-bold" style="font-size: 0.9rem;"><?php _e('auto_tirage.client_name'); ?></label>
                    <input type="text" id="checkout-client-name" class="form-control" value="Passage" placeholder="Client de passage ou nom...">
                </div>

                <!-- Choix du moyen de paiement -->
                <div class="form-group mb-3">
                    <label class="font-weight-bold" style="font-size: 0.9rem;">Moyen de règlement</label>
                    <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
                        <button type="button" class="btn btn-payment-method btn-primary active w-33" data-method="especes" onclick="tapisSetPaymentMethod('especes')">
                            <i class="fa fa-money"></i> <?php _e('auto_tirage.payment_cash'); ?>
                        </button>
                        <button type="button" class="btn btn-payment-method btn-outline-secondary w-33" data-method="cb" onclick="tapisSetPaymentMethod('cb')">
                            <i class="fa fa-credit-card"></i> <?php _e('auto_tirage.payment_card'); ?>
                        </button>
                        <button type="button" class="btn btn-payment-method btn-outline-secondary w-33" data-method="cheque" onclick="tapisSetPaymentMethod('cheque')">
                            <i class="fa fa-pencil-square-o"></i> <?php _e('auto_tirage.payment_check'); ?>
                        </button>
                    </div>
                </div>

                <!-- Zone Espèces : Billets rapides & Rendu de monnaie -->
                <div id="checkout-cash-details" class="p-3 mb-3" style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px;">
                    <label class="font-weight-bold text-success" style="font-size: 0.85rem;"><?php _e('auto_tirage.amount_received'); ?></label>
                    <div class="input-group mb-2">
                        <input type="text" id="checkout-amount-received" class="form-control form-control-lg font-weight-bold text-center" style="font-size: 1.4rem;" placeholder="0.00">
                        <div class="input-group-append">
                            <span class="input-group-text font-weight-bold">€</span>
                        </div>
                    </div>
                    
                    <!-- Boutons billets rapides -->
                    <div class="d-flex justify-content-between gap-1 mb-2">
                        <button type="button" class="btn btn-sm btn-outline-success quick-amount-btn" onclick="tapisSetQuickAmount('exact')">Exact</button>
                        <button type="button" class="btn btn-sm btn-outline-success quick-amount-btn" onclick="tapisSetQuickAmount(5)">5 €</button>
                        <button type="button" class="btn btn-sm btn-outline-success quick-amount-btn" onclick="tapisSetQuickAmount(10)">10 €</button>
                        <button type="button" class="btn btn-sm btn-outline-success quick-amount-btn" onclick="tapisSetQuickAmount(20)">20 €</button>
                        <button type="button" class="btn btn-sm btn-outline-success quick-amount-btn" onclick="tapisSetQuickAmount(50)">50 €</button>
                    </div>

                    <!-- Affichage du rendu -->
                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                        <span class="font-weight-bold"><?php _e('auto_tirage.change_due'); ?> :</span>
                        <span id="checkout-change-due" style="font-size: 1.4rem; font-weight: 800;" class="text-success">0.00 €</span>
                    </div>
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
