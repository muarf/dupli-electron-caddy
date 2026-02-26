/**
 * Admin Warning Component
 * Affiche un avertissement si l'application n'est pas lancée en mode administrateur
 * Avec possibilité de fermer sur la page d'accueil (mémorisé dans localStorage)
 */

(function () {
    'use strict';

    const AdminWarning = {
        // Clé localStorage pour mémoriser le choix de l'utilisateur
        STORAGE_KEY: 'dupli_admin_warning_dismissed',

        /**
         * Vérifie si l'application a les droits admin
         */
        async checkAdminStatus() {
            if (typeof window.electronAPI === 'undefined' ||
                typeof window.electronAPI.checkAdminStatus !== 'function') {
                return { isAdmin: true }; // Pas d'API = pas de vérification
            }

            try {
                const result = await window.electronAPI.checkAdminStatus();
                return result.success ? result : { isAdmin: true };
            } catch (error) {
                console.error('Erreur vérification admin:', error);
                return { isAdmin: true }; // En cas d'erreur, ne pas afficher l'avertissement
            }
        },

        /**
         * Vérifie si l'utilisateur a fermé l'avertissement
         */
        isDismissed() {
            try {
                return localStorage.getItem(this.STORAGE_KEY) === 'true';
            } catch (e) {
                return false;
            }
        },

        /**
         * Marquer l'avertissement comme fermé
         */
        dismiss() {
            try {
                localStorage.setItem(this.STORAGE_KEY, 'true');
            } catch (e) {
                console.error('Impossible de sauvegarder dans localStorage:', e);
            }
        },

        /**
         * Affiche l'avertissement
         * @param {boolean} allowDismiss - Autoriser la fermeture (page d'accueil)
         */
        async show(allowDismiss = false) {
            // Vérifier les droits admin
            const result = await this.checkAdminStatus();
            const isAdmin = result.isAdmin;

            if (isAdmin) {
                return; // Admin détecté, pas d'avertissement
            }

            // Si dismissable et déjà fermé, ne rien afficher
            if (allowDismiss && this.isDismissed()) {
                return;
            }

            // Créer le HTML de l'avertissement
            const html = this.createWarningHTML(allowDismiss, result);

            // Insérer dans la page
            const container = document.querySelector('.container') || document.body;
            const warningDiv = document.createElement('div');
            warningDiv.innerHTML = html;
            container.insertBefore(warningDiv.firstElementChild, container.firstChild);

            // Ajouter les event listeners
            this.attachEventListeners();
        },

        /**
         * Crée le HTML de l'avertissement
         */
        createWarningHTML(allowDismiss, result = {}) {
            const dismissButton = allowDismiss ? `
                <button type="button" class="close" onclick="AdminWarning.handleDismiss()" aria-label="Fermer">
                    <span aria-hidden="true">&times;</span>
                </button>
            ` : '';

            if (result.platform === 'linux') {
                const user = result.user || 'utilisateur';
                return `
                <div class="alert alert-warning alert-dismissible" id="admin-warning-banner" style="margin-bottom: 20px; border-left: 4px solid #f0ad4e;">
                    ${dismissButton}
                    <h4><i class="fa fa-exclamation-triangle"></i> Permissions d'impression manquantes</h4>
                    <p>
                        Pour analyser les impressions (taux de remplissage et couleurs), l'application a besoin d'accéder aux données d'impression. Sur Linux, cela nécessite d'appartenir au groupe système de l'impression.<br>
                        <strong>Solution recommandée :</strong> Ajoutez votre utilisateur au groupe <code>lp</code> en exécutant cette commande dans un terminal :
                    </p>
                    <pre style="background: #fff3cd; border: 1px solid #ffeeba; padding: 10px; margin: 10px 0;"><code>sudo usermod -aG lp ${user}</code></pre>
                    <p>
                        <em>Note : Vous devrez obligatoirement fermer et réouvrir votre session (ou redémarrer l'ordinateur) pour que ce changement de groupe prenne effet.</em>
                    </p>
                    <div style="margin-top: 15px;">
                        <a href="?admin&imprimantes" class="btn btn-sm btn-default">
                            <i class="fa fa-info-circle"></i> Plus d'infos
                        </a>
                    </div>
                    ${allowDismiss ? '<p class="text-muted" style="margin-top: 10px; margin-bottom: 0; font-size: 12px;"><i class="fa fa-info-circle"></i> Vous pouvez fermer cet avertissement, il ne s\'affichera plus.</p>' : ''}
                </div>
                `;
            }

            // Windows/Default Fallback
            return `
                <div class="alert alert-warning alert-dismissible" id="admin-warning-banner" style="margin-bottom: 20px; border-left: 4px solid #f0ad4e;">
                    ${dismissButton}
                    <h4><i class="fa fa-exclamation-triangle"></i> Droits Administrateur Non Détectés</h4>
                    <p>
                        L'application n'est pas lancée en mode administrateur. 
                        Le <strong>taux de remplissage (fill rate)</strong> ne pourra pas être calculé pour les impressions.
                    </p>
                    <div style="margin-top: 15px;">
                        <button class="btn btn-sm btn-warning" onclick="AdminWarning.restartAsAdmin()">
                            <i class="fa fa-refresh"></i> Relancer en Administrateur
                        </button>
                        <a href="?admin&imprimantes" class="btn btn-sm btn-default">
                            <i class="fa fa-info-circle"></i> Plus d'infos
                        </a>
                    </div>
                    ${allowDismiss ? '<p class="text-muted" style="margin-top: 10px; margin-bottom: 0; font-size: 12px;"><i class="fa fa-info-circle"></i> Vous pouvez fermer cet avertissement, il ne s\'affichera plus.</p>' : ''}
                </div>
            `;
        },

        /**
         * Attache les event listeners
         */
        attachEventListeners() {
            // Pas besoin d'event listeners supplémentaires car on utilise onclick
        },

        /**
         * Gère la fermeture de l'avertissement
         */
        handleDismiss() {
            this.dismiss();
            const banner = document.getElementById('admin-warning-banner');
            if (banner) {
                banner.remove();
            }
        },

        /**
         * Redémarre l'application en admin
         */
        async restartAsAdmin() {
            if (typeof window.electronAPI === 'undefined' ||
                typeof window.electronAPI.restartAsAdmin !== 'function') {
                if (window.showAppModal) {
                    window.showAppModal('Fonction non disponible');
                } else {
                    alert('Fonction non disponible');
                }
                return;
            }

            const confirmed = await new Promise(resolve => {
                if (window.showAppModal) {
                    window.showAppModal({
                        type: 'warning',
                        title: 'Redémarrage Administrateur',
                        message: 'L\'application va se fermer et redémarrer avec les droits administrateur.<br><br>Continuer ?',
                        confirm: true,
                        onConfirm: () => resolve(true),
                        onClose: () => resolve(false)
                    });
                } else {
                    resolve(confirm('L\'application va se fermer et redémarrer avec les droits administrateur.\n\nContinuer ?'));
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
                        message: 'Erreur lors du redémarrage : ' + error.message
                    });
                } else {
                    alert('Erreur lors du redémarrage : ' + error.message);
                }
            }
        }
    };

    // Exposer globalement
    window.AdminWarning = AdminWarning;
})();
