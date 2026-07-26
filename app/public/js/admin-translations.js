// ============================================================
// admin-translations.js -- Gestion des Traductions (Admin)
// Extrait de admin_translations.html.php
// ============================================================

/* global $, window, document */

// Fonction pour basculer les accordéons
window.toggleAccordion = function (language, page) {
    var content = $('#content-' + language + '-' + page);
    var arrow = $('#arrow-' + language + '-' + page);
    var header = arrow.closest('.page-accordion-header');
    
    if (content.hasClass('active')) {
        content.removeClass('active');
        header.removeClass('active');
        arrow.removeClass('fa-chevron-up').addClass('fa-chevron-down');
    } else {
        // Fermer tous les autres accordéons de cette langue
        $('#accordions-' + language + ' .page-accordion-content.active').removeClass('active');
        $('#accordions-' + language + ' .page-accordion-header.active').removeClass('active');
        $('#accordions-' + language + ' .accordion-arrow').removeClass('fa-chevron-up').addClass('fa-chevron-down');
        
        // Ouvrir celui-ci
        content.addClass('active');
        header.addClass('active');
        arrow.removeClass('fa-chevron-down').addClass('fa-chevron-up');
    }
};

$(document).ready(function() {
    // Gestion des onglets de langue
    $('.language-tab').on('click', function() {
        var language = $(this).data('language');
        
        // Changer l'URL sans recharger la page
        var url = new URL(window.location);
        url.searchParams.set('lang', language);
        window.history.pushState({}, '', url);
        
        // Mettre à jour l'affichage
        $('.language-tab').removeClass('active');
        $('.language-content').removeClass('active');
        
        $(this).addClass('active');
        $('#content-' + language).addClass('active');
    });
    
    // Recherche dans les traductions
    $('.search-box input').on('input', function() {
        var searchTerm = $(this).val().toLowerCase();
        var language = $(this).attr('id').replace('search-', '');
        
        $('.language-content[data-language="' + language + '"] .translation-item').each(function() {
            var key = $(this).find('.translation-key').text().toLowerCase();
            var value = $(this).find('.translation-value input').val().toLowerCase();
            
            if (key.includes(searchTerm) || value.includes(searchTerm)) {
                $(this).show();
                $(this).closest('.page-accordion-content').show();
                $(this).closest('.page-accordion').show();
            } else {
                $(this).hide();
            }
        });
        
        // Masquer les accordéons vides
        $('.page-accordion-content').each(function() {
            var visibleItems = $(this).find('.translation-item:visible').length;
            if (visibleItems === 0 && searchTerm !== '') {
                $(this).hide();
                $(this).closest('.page-accordion').hide();
            } else {
                $(this).closest('.page-accordion').show();
            }
        });
    });
    
    // Sauvegarde des traductions
    $('.btn-save').on('click', function() {
        var key = $(this).data('key');
        var language = $(this).data('language');
        var value = $(this).closest('.translation-item').find('.translation-input').val();
        var button = $(this);
        
        // Animation de sauvegarde
        button.removeClass('btn-save success error').addClass('btn-save saving');
        button.html('<i class="fa fa-spinner fa-spin"></i> Sauvegarde...');
        button.prop('disabled', true);
        
        $.ajax({
            url: '?admin_translations',
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            data: {
                action: 'update_translation',
                language: language,
                key: key,
                value: value
            },
            success: function(response) {
                console.log('Sauvegarde réussie:', response);
                button.removeClass('saving').addClass('success');
                button.html('<i class="fa fa-check"></i> Sauvé');
                
                setTimeout(function() {
                    button.removeClass('success').addClass('btn-save');
                    button.html('<i class="fa fa-save"></i> Sauver');
                    button.prop('disabled', false);
                }, 2000);
            },
            error: function(xhr, status, error) {
                console.error((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.admin_translations.erreur_de_sauvegarde'] || 'Erreur de sauvegarde:'), xhr.responseText, status, error);
                button.removeClass('saving').addClass('error');
                button.html('<i class="fa fa-times"></i> Erreur');
                
                setTimeout(function() {
                    button.removeClass('error').addClass('btn-save');
                    button.html('<i class="fa fa-save"></i> Sauver');
                    button.prop('disabled', false);
                }, 2000);
            }
        });
    });
    
    // Sauvegarde automatique avec Ctrl+S
    $(document).on('keydown', function(e) {
        if (e.ctrlKey && e.keyCode === 83) {
            e.preventDefault();
            $('.translation-item:visible .btn-save:enabled').first().click();
        }
    });
});
