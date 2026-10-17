/**
 * Admin Warning Component
 * Affiche un avertissement si l'application n'est pas lancée en mode administrateur
 * Avec possibilité de fermer sur la page d'accueil (mémorisé dans localStorage)
 */

(function () {
    'use strict';

    const T = (key, fb) => (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations[key]) || fb;

    const AdminWarning = {
        STORAGE_KEY: 'dupli_admin_warning_dismissed',

        async checkAdminStatus() {
            if (typeof window.electronAPI === 'undefined' ||
                typeof window.electronAPI.checkAdminStatus !== 'function') {
                return { isAdmin: true };
            }

            try {
                const result = await window.electronAPI.checkAdminStatus();
                return result.success ? result : { isAdmin: true };
            } catch (error) {
                console.error(T('js.admin_warning.erreur_verification_admin', 'Erreur vérification admin:'), error);
                return { isAdmin: true };
            }
        },

        isDismissed() {
            try {
                return localStorage.getItem(this.STORAGE_KEY) === 'true';
            } catch (e) {
                return false;
            }
        },

        dismiss() {
            try {
                localStorage.setItem(this.STORAGE_KEY, 'true');
            } catch (e) {
                console.error(T('js.admin_warning.impossible_sauvegarder', 'Impossible de sauvegarder dans localStorage:'), e);
            }
        },

        async show(allowDismiss = false) {
            const result = await this.checkAdminStatus();
            const isAdmin = result.isAdmin;

            if (isAdmin) {
                return;
            }

            if (allowDismiss && this.isDismissed()) {
                return;
            }

            const html = this.createWarningHTML(allowDismiss, result);

            const container = document.querySelector('.container') || document.body;
            const warningDiv = document.createElement('div');
            warningDiv.innerHTML = html;
            container.insertBefore(warningDiv.firstElementChild, container.firstChild);

            this.attachEventListeners();
        },

        createWarningHTML(allowDismiss, result = {}) {
            const dismissButton = allowDismiss ?
                '<button type="button" class="close" onclick="AdminWarning.handleDismiss()" aria-label="' + T('common.close', 'Fermer') + '">' +
                    '<span aria-hidden="true">&times;</span>' +
                '</button>' : '';

            if (result.platform === 'linux') {
                const user = result.user || 'utilisateur';
                return '' +
                '<div class="alert alert-warning alert-dismissible" id="admin-warning-banner" style="margin-bottom: 20px; border-left: 4px solid #f0ad4e;">' +
                    dismissButton +
                    '<h4><i class="fa fa-exclamation-triangle"></i> ' + T('js.admin_warning.permissions_impression_manquantes', 'Permissions d\'impression manquantes') + '</h4>' +
                    '<p>' +
                        T('js.admin_warning.impression_sur_linux', 'Pour analyser les impressions (taux de remplissage et couleurs), l\'application a besoin d\'accéder aux données d\'impression. Sur Linux, cela nécessite d\'appartenir au groupe système de l\'impression.') + '<br>' +
                        '<strong>' + T('js.admin_warning.solution_recommandee', 'Solution recommandée :') + '</strong> ' + T('js.admin_warning.ajoutez_utilisateur_groupe_lp', 'Ajoutez votre utilisateur au groupe') + ' <code>lp</code> ' + T('js.admin_warning.en_executant_commande', 'en exécutant cette commande dans un terminal :') +
                    '</p>' +
                    '<pre style="background: #fff3cd; border: 1px solid #ffeeba; padding: 10px; margin: 10px 0;"><code>sudo usermod -aG lp ' + user + '</code></pre>' +
                    '<p>' +
                        '<em>' + T('js.admin_warning.note_fermer_session', 'Note : Vous devrez obligatoirement fermer et réouvrir votre session (ou redémarrer l\'ordinateur) pour que ce changement de groupe prenne effet.') + '</em>' +
                    '</p>' +
                    '<div style="margin-top: 15px;">' +
                        '<a href="?admin&imprimantes" class="btn btn-sm btn-default">' +
                            '<i class="fa fa-info-circle"></i> ' + T('js.admin_warning.plus_d_infos', 'Plus d\'infos') +
                        '</a>' +
                    '</div>' +
                    (allowDismiss ? '<p class="text-muted" style="margin-top: 10px; margin-bottom: 0; font-size: 12px;"><i class="fa fa-info-circle"></i> ' + T('js.admin_warning.fermer_avertissement', 'Vous pouvez fermer cet avertissement, il ne s\'affichera plus.') + '</p>' : '') +
                '</div>';
            }

            return '' +
                '<div class="alert alert-warning alert-dismissible" id="admin-warning-banner" style="margin-bottom: 20px; border-left: 4px solid #f0ad4e;">' +
                    dismissButton +
                    '<h4><i class="fa fa-exclamation-triangle"></i> ' + T('js.admin_warning.droits_admin_non_detectes', 'Droits Administrateur Non Détectés') + '</h4>' +
                    '<p>' +
                        T('js.admin_warning.app_pas_mode_admin', 'L\'application n\'est pas lancée en mode administrateur. ') +
                        T('js.admin_warning.taux_remplissage_non_calcul', 'Le <strong>taux de remplissage (fill rate)</strong> ne pourra pas être calculé pour les impressions.') +
                    '</p>' +
                    '<div style="margin-top: 15px;">' +
                        '<button class="btn btn-sm btn-warning" onclick="AdminWarning.restartAsAdmin()">' +
                            '<i class="fa fa-refresh"></i> ' + T('js.admin_warning.relancer_en_admin', 'Relancer en Administrateur') +
                        '</button>' +
                        '<a href="?admin&imprimantes" class="btn btn-sm btn-default">' +
                            '<i class="fa fa-info-circle"></i> ' + T('js.admin_warning.plus_d_infos', 'Plus d\'infos') +
                        '</a>' +
                    '</div>' +
                    (allowDismiss ? '<p class="text-muted" style="margin-top: 10px; margin-bottom: 0; font-size: 12px;"><i class="fa fa-info-circle"></i> ' + T('js.admin_warning.fermer_avertissement', 'Vous pouvez fermer cet avertissement, il ne s\'affichera plus.') + '</p>' : '') +
                '</div>';
        },

        attachEventListeners() {
        },

        handleDismiss() {
            this.dismiss();
            const banner = document.getElementById('admin-warning-banner');
            if (banner) {
                banner.remove();
            }
        },

        async restartAsAdmin() {
            if (typeof window.electronAPI === 'undefined' ||
                typeof window.electronAPI.restartAsAdmin !== 'function') {
                if (window.showAppModal) {
                    window.showAppModal(T('js.admin_warning.fonction_non_disponible', 'Fonction non disponible'));
                } else {
                    alert(T('js.admin_warning.fonction_non_disponible', 'Fonction non disponible'));
                }
                return;
            }

            const confirmed = await new Promise(resolve => {
                if (window.showAppModal) {
                    window.showAppModal({
                        type: 'warning',
                        title: T('js.admin_warning.redemarrage_admin', 'Redémarrage Administrateur'),
                        message: T('js.admin_warning.redemarrer_admin_confirm_html', 'L\'application va se fermer et redémarrer avec les droits administrateur.<br><br>Continuer ?'),
                        confirm: true,
                        onConfirm: () => resolve(true),
                        onClose: () => resolve(false)
                    });
                } else {
                    resolve(confirm(T('js.admin_warning.redemarrer_admin_confirm', 'L\'application va se fermer et redémarrer avec les droits administrateur.\n\nContinuer ?')));
                }
            });

            if (!confirmed) {
                return;
            }

            try {
                await window.electronAPI.restartAsAdmin();
            } catch (error) {
                if (window.showAppModal) {
                    window.showAppModal({
                        type: 'danger',
                        message: T('js.admin_warning.erreur_redemarrage', 'Erreur lors du redémarrage : ') + error.message
                    });
                } else {
                    alert(T('js.admin_warning.erreur_redemarrage', 'Erreur lors du redémarrage : ') + error.message);
                }
            }
        }
    };

    window.AdminWarning = AdminWarning;
})();
