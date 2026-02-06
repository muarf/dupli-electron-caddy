/**
 * JavaScript pour l'édition inline des traductions
 * Utilise l'endpoint admin_translations existant
 */

class InlineTranslationEditor {
    constructor() {
        this.isEditing = false;
        this.currentElement = null;
        this.originalValue = '';
        
        // Par défaut, l'édition est désactivée pour tous les admins à la connexion
        const savedState = localStorage.getItem('inline-editing-enabled');
        
        if (savedState === null) {
            // Pas encore défini : désactiver par défaut
            this.editingEnabled = false;
            localStorage.setItem('inline-editing-enabled', 'false');
            console.log('Édition inline désactivée par défaut');
        } else {
            // Utiliser l'état sauvegardé
            this.editingEnabled = savedState === 'true';
        }
        
        console.log('Constructor: savedState =', savedState, 'editingEnabled =', this.editingEnabled);
        this.init();
    }

    init() {
        // Vérifier si l'utilisateur est admin
        if (!this.isAdmin()) {
            return;
        }

        // Ajouter la classe admin-mode au body
        document.body.classList.add('admin-mode');

        // Attacher les événements
        this.attachEvents();
        
        // Initialiser le bouton de bascule
        this.initToggleButton();
        
        // Appliquer l'état d'édition initial avec un délai pour s'assurer que tous les éléments sont chargés
        setTimeout(() => {
            this.toggleEditingMode();
        }, 100);
    }

    isAdmin() {
        // Vérifier si l'utilisateur est connecté en admin
        // On peut vérifier la présence d'un élément admin ou d'une classe
        return document.body.classList.contains('admin-mode') || 
               document.querySelector('.admin-panel') !== null ||
               window.location.search.includes('admin') ||
               document.querySelector('.translation-editable') !== null;
    }

    attachEvents() {
        // Clic sur les éléments éditables
        document.addEventListener('click', (e) => {
            const editableElement = e.target.closest('.translation-editable');
            if (editableElement) {
                // Vérifier si l'édition est activée
                if (!this.editingEnabled) {
                    console.log('Édition désactivée, clic ignoré. editingEnabled =', this.editingEnabled);
                    // Permettre la navigation normale quand l'édition est désactivée
                    return;
                }
                
                // Édition activée : empêcher la navigation et permettre l'édition
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                
                // Forcer la mise à jour de l'état de l'élément
                editableElement.classList.remove('editing-disabled');
                editableElement.classList.add('editing-enabled');
                editableElement.style.setProperty('cursor', 'pointer', 'important');
                
                console.log('Clic sur élément édiéable:', editableElement);
                this.startEdit(editableElement);
                return false;
            }
        });

        // Sauvegarde avec Ctrl+S
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.key === 's' && this.isEditing) {
                e.preventDefault();
                this.saveTranslation();
            }
        });

        // Annulation avec Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isEditing) {
                e.preventDefault();
                this.cancelEdit();
            }
        });
    }

    initToggleButton() {
        // Attendre que le footer soit chargé dans le DOM
        const tryInit = () => {
            const toggleBtn = document.getElementById('toggle-edit-btn');
            const toggleText = document.getElementById('toggle-edit-text');
            
            if (!toggleBtn || !toggleText) {
                // Réessayer après un court délai si le bouton n'existe pas encore
                setTimeout(tryInit, 50);
                return;
            }

            // Utiliser l'état déjà initialisé dans le constructeur
            this.updateToggleButton(toggleBtn, toggleText);

            // Événement de clic (une seule fois)
            if (!toggleBtn.hasAttribute('data-listener-attached')) {
                toggleBtn.setAttribute('data-listener-attached', 'true');
                toggleBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.editingEnabled = !this.editingEnabled;
                    
                    // Sauvegarder l'état dans localStorage
                    localStorage.setItem('inline-editing-enabled', this.editingEnabled);
                    
                    this.updateToggleButton(toggleBtn, toggleText);
                    this.toggleEditingMode();
                    console.log('Bouton cliqué, édition activée:', this.editingEnabled);
                });
            }
        };
        
        // Commencer immédiatement
        tryInit();
    }

    updateToggleButton(btn, text) {
        if (this.editingEnabled) {
            btn.className = 'btn btn-warning btn-sm';
            // Vérifier la langue actuelle pour afficher le bon texte
            if (document.documentElement.lang === 'fr' || window.location.search.includes('lang=fr')) {
                text.textContent = 'Désactiver l\'édition';
            } else {
                text.textContent = 'Disable editing';
            }
        } else {
            btn.className = 'btn btn-info btn-sm';
            // Vérifier la langue actuelle pour afficher le bon texte
            if (document.documentElement.lang === 'fr' || window.location.search.includes('lang=fr')) {
                text.textContent = 'Activer l\'édition';
            } else {
                text.textContent = 'Enable editing';
            }
        }
    }

    toggleEditingMode() {
        const editables = document.querySelectorAll('.translation-editable');
        console.log('toggleEditingMode appelé, édition activée:', this.editingEnabled, 'éléments trouvés:', editables.length);
        
        editables.forEach(el => {
            const editIcon = el.querySelector('.edit-icon');
            
            if (this.editingEnabled) {
                // Activer l'édition : rendre visible et cliquable
                el.style.display = 'inline-block';
                el.style.opacity = '1';
                el.style.setProperty('cursor', 'pointer', 'important');
                el.classList.add('editing-enabled');
                el.classList.remove('editing-disabled');
                
                // Afficher l'icône d'édition
                if (editIcon) {
                    editIcon.style.display = 'inline';
                }
            } else {
                // Désactiver l'édition : rendre moins visible et non cliquable
                el.style.setProperty('cursor', 'default', 'important');
                el.classList.add('editing-disabled');
                el.classList.remove('editing-enabled');
                
                // Masquer l'icône d'édition
                if (editIcon) {
                    editIcon.style.display = 'none';
                }
            }
        });
    }

    startEdit(element) {
        if (!this.editingEnabled) {
            return;
        }
        
        if (this.isEditing) {
            this.cancelEdit();
        }

        this.currentElement = element;
        this.originalValue = element.textContent.trim();
        
        // Récupérer les données
        const key = element.dataset.key;
        const lang = element.dataset.lang;
        
        if (!key || !lang) {
            console.error('Données manquantes pour l\'édition:', element);
            return;
        }

        // Créer l'input
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'translation-input-inline';
        input.value = this.originalValue;
        input.dataset.key = key;
        input.dataset.lang = lang;

        // Créer les boutons d'action
        const actions = document.createElement('div');
        actions.className = 'translation-actions-inline';
        
        const saveBtn = document.createElement('button');
        saveBtn.className = 'btn-translation btn-save-translation';
        saveBtn.innerHTML = '<i class="fa fa-save"></i> Sauver';
        saveBtn.onclick = () => this.saveTranslation();

        const cancelBtn = document.createElement('button');
        cancelBtn.className = 'btn-translation btn-cancel-translation';
        cancelBtn.innerHTML = '<i class="fa fa-times"></i> Annuler';
        cancelBtn.onclick = () => this.cancelEdit();

        actions.appendChild(saveBtn);
        actions.appendChild(cancelBtn);

        // Remplacer le contenu
        element.classList.add('editing');
        element.innerHTML = '';
        element.appendChild(input);
        element.appendChild(actions);

        // Focus sur l'input
        input.focus();
        input.select();

        this.isEditing = true;

        // Attacher les événements sur l'input
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.saveTranslation();
            }
        });
    }

    async saveTranslation() {
        if (!this.isEditing || !this.currentElement) {
            return;
        }

        const input = this.currentElement.querySelector('.translation-input-inline');
        const saveBtn = this.currentElement.querySelector('.btn-save-translation');
        
        if (!input || !saveBtn) {
            return;
        }

        const key = input.dataset.key;
        const lang = input.dataset.lang;
        const value = input.value.trim();

        // Animation de sauvegarde
        saveBtn.disabled = true;
        saveBtn.classList.add('saving');
        saveBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Sauvegarde...';

        try {
            const response = await fetch('?admin_translations', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    action: 'update_translation',
                    language: lang,
                    key: key,
                    value: value
                })
            });

            const result = await response.json();

            if (result.success) {
                this.showFeedback('Traduction sauvegardée !', 'success');
                saveBtn.classList.remove('saving');
                saveBtn.classList.add('success');
                saveBtn.innerHTML = '<i class="fa fa-check"></i> Sauvé';
                
                // Mettre à jour la valeur originale
                this.originalValue = value;
                
                // Attendre un peu puis fermer l'édition
                setTimeout(() => {
                    this.finishEdit();
                }, 1500);
            } else {
                throw new Error(result.message || 'Erreur de sauvegarde');
            }
        } catch (error) {
            console.error('Erreur de sauvegarde:', error);
            this.showFeedback('Erreur de sauvegarde: ' + error.message, 'error');
            saveBtn.classList.remove('saving');
            saveBtn.classList.add('error');
            saveBtn.innerHTML = '<i class="fa fa-times"></i> Erreur';
            
            // Réactiver le bouton après 2 secondes
            setTimeout(() => {
                saveBtn.disabled = false;
                saveBtn.classList.remove('error');
                saveBtn.innerHTML = '<i class="fa fa-save"></i> Sauver';
            }, 2000);
        }
    }

    cancelEdit() {
        if (!this.isEditing || !this.currentElement) {
            return;
        }

        this.finishEdit();
    }

    finishEdit() {
        if (!this.currentElement) {
            return;
        }

        const key = this.currentElement.dataset.key;
        const lang = this.currentElement.dataset.lang;
        const value = this.originalValue;

        // Restaurer le contenu original
        this.currentElement.classList.remove('editing');
        this.currentElement.innerHTML = value + ' <i class="fa fa-edit edit-icon"></i>';

        this.isEditing = false;
        this.currentElement = null;
        this.originalValue = '';
    }

    showFeedback(message, type = 'success') {
        if (!this.currentElement) {
            return;
        }

        // Supprimer les anciens messages
        const existingFeedback = this.currentElement.querySelector('.translation-feedback');
        if (existingFeedback) {
            existingFeedback.remove();
        }

        // Créer le nouveau message
        const feedback = document.createElement('div');
        feedback.className = `translation-feedback ${type}`;
        feedback.textContent = message;
        
        this.currentElement.style.position = 'relative';
        this.currentElement.appendChild(feedback);

        // Supprimer automatiquement après 2 secondes
        setTimeout(() => {
            if (feedback.parentNode) {
                feedback.remove();
            }
        }, 2000);
    }
}

// Initialiser l'éditeur quand le DOM est prêt
document.addEventListener('DOMContentLoaded', () => {
    new InlineTranslationEditor();
});

// Fonction utilitaire pour activer/désactiver le mode admin
window.toggleAdminMode = function() {
    document.body.classList.toggle('admin-mode');
    
    // Recharger les éléments éditables
    const editables = document.querySelectorAll('.translation-editable');
    editables.forEach(el => {
        if (document.body.classList.contains('admin-mode')) {
            el.style.display = 'inline-block';
        } else {
            el.style.display = 'none';
        }
    });
};

// Fonction pour ajouter des éléments éditables dynamiquement
window.makeEditable = function(element, key, lang) {
    if (!element || !key || !lang) {
        console.error('Paramètres manquants pour makeEditable');
        return;
    }

    element.classList.add('translation-editable');
    element.dataset.key = key;
    element.dataset.lang = lang;
    element.title = 'Cliquer pour éditer';
    
    // Ajouter l'icône d'édition si elle n'existe pas
    if (!element.querySelector('.edit-icon')) {
        const icon = document.createElement('i');
        icon.className = 'fa fa-edit edit-icon';
        element.appendChild(icon);
    }
};
