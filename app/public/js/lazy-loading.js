/**
 * Lazy Loading pour les images Quill.js
 * 
 * Ce script optimise le chargement des images en les chargeant seulement
 * quand elles deviennent visibles à l'utilisateur.
 */

(function() {
    'use strict';
    
    // Configuration
    const CONFIG = {
        rootMargin: '50px 0px', // Commencer à charger 50px avant que l'image soit visible
        threshold: 0.1, // Charger quand 10% de l'image est visible
        placeholder: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjMwMCIgdmlld0JveD0iMCAwIDQwMCAzMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjQwMCIgaGVpZ2h0PSIzMDAiIGZpbGw9IiNmMGYwZjAiLz48dGV4dCB4PSIyMDAiIHk9IjE1MCIgZm9udC1mYW1pbHk9IkFyaWFsLCBzYW5zLXNlcmlmIiBmb250LXNpemU9IjE0cHgiIGZpbGw9IiM5OTk5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiPkxvYWRpbmcuLi48L3RleHQ+PC9zdmc+',
        errorPlaceholder: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjMwMCIgdmlld0JveD0iMCAwIDQwMCAzMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjQwMCIgaGVpZ2h0PSIzMDAiIGZpbGw9IiNmOGY5ZmEiLz48dGV4dCB4PSIyMDAiIHk9IjE1MCIgZm9udC1mYW1pbHk9IkFyaWFsLCBzYW5zLXNlcmlmIiBmb250LXNpemU9IjE0cHgiIGZpbGw9IiNkYzM0NDUiIHRleHQtYW5jaG9yPSJtaWRkbGUiPkVycmV1ciBjaGFyZ2VtZW50PC90ZXh0Pjwvc3ZnPg=='
    };
    
    // Variables globales
    let imageObserver;
    let processedImages = new Set();
    
    /**
     * Initialise le lazy loading
     */
    function initLazyLoading() {
        // Vérifier si IntersectionObserver est supporté
        if (!('IntersectionObserver' in window)) {
            console.warn('IntersectionObserver non supporté, chargement normal des images');
            loadAllImages();
            return;
        }
        
        // Créer l'observer
        imageObserver = new IntersectionObserver(handleIntersection, {
            root: null,
            rootMargin: CONFIG.rootMargin,
            threshold: CONFIG.threshold
        });
        
        // Traiter les images existantes
        processImages();
        
        // Surveiller les changements dans le DOM (pour les nouvelles images ajoutées par Quill)
        observeDOMChanges();
        
        console.log('🚀 Lazy loading initialisé');
    }
    
    /**
     * Traite les images existantes
     */
    function processImages() {
        const images = document.querySelectorAll('.ql-editor img, .ql-container img, [class*="quill"] img, img[src*="data:image"]');
        
        images.forEach(img => {
            if (!processedImages.has(img)) {
                setupLazyImage(img);
                processedImages.add(img);
            }
        });
        
        console.log(`📊 ${images.length} images trouvées pour le lazy loading`);
        
        // Traiter aussi les images dans les contenus Quill dynamiques
        setTimeout(() => {
            const dynamicImages = document.querySelectorAll('.aide-item img, .qa-answer img, [class*="aide"] img');
            dynamicImages.forEach(img => {
                if (!processedImages.has(img)) {
                    setupLazyImage(img);
                    processedImages.add(img);
                }
            });
            
            if (dynamicImages.length > 0) {
                console.log(`📊 ${dynamicImages.length} images dynamiques supplémentaires trouvées`);
            }
        }, 500);
    }
    
    /**
     * Configure une image pour le lazy loading
     */
    function setupLazyImage(img) {
        // Vérifier si l'image a déjà un src valide
        if (img.src && !img.src.startsWith('data:image/svg+xml')) {
            return; // Image déjà chargée
        }
        
        // Stocker l'URL originale
        const originalSrc = img.src || img.getAttribute('data-src');
        if (!originalSrc) {
            return;
        }
        
        // Marquer l'image comme lazy
        img.classList.add('lazy-image');
        img.setAttribute('data-original-src', originalSrc);
        img.src = CONFIG.placeholder;
        
        // Ajouter un style pour la transition
        img.style.transition = 'opacity 0.3s ease-in-out';
        img.style.opacity = '0.7';
        
        // Observer l'image
        imageObserver.observe(img);
    }
    
    /**
     * Gère l'intersection des images avec le viewport
     */
    function handleIntersection(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                loadImage(img);
                imageObserver.unobserve(img);
            }
        });
    }
    
    /**
     * Charge une image
     */
    function loadImage(img) {
        const originalSrc = img.getAttribute('data-original-src');
        
        if (!originalSrc) {
            return;
        }
        
        // Créer une nouvelle image pour tester le chargement
        const testImg = new Image();
        
        testImg.onload = function() {
            // Image chargée avec succès
            img.src = originalSrc;
            img.style.opacity = '1';
            img.classList.remove('lazy-image');
            img.classList.add('lazy-loaded');
            
            // Nettoyer
            img.removeAttribute('data-original-src');
        };
        
        testImg.onerror = function() {
            // Erreur de chargement
            img.src = CONFIG.errorPlaceholder;
            img.style.opacity = '1';
            img.classList.remove('lazy-image');
            img.classList.add('lazy-error');
            
            console.warn('❌ Erreur de chargement de l\'image:', originalSrc);
        };
        
        // Commencer le chargement
        testImg.src = originalSrc;
    }
    
    /**
     * Charge toutes les images (fallback pour les navigateurs sans IntersectionObserver)
     */
    function loadAllImages() {
        const images = document.querySelectorAll('.ql-editor img, .ql-container img, [class*="quill"] img');
        
        images.forEach(img => {
            if (img.getAttribute('data-original-src')) {
                loadImage(img);
            }
        });
    }
    
    /**
     * Surveille les changements dans le DOM pour les nouvelles images
     */
    function observeDOMChanges() {
        // Observer les mutations du DOM
        const observer = new MutationObserver(function(mutations) {
            let shouldProcess = false;
            
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === Node.ELEMENT_NODE) {
                            // Vérifier si c'est une image ou contient des images
                            if (node.tagName === 'IMG' || node.querySelector && node.querySelector('img')) {
                                shouldProcess = true;
                            }
                        }
                    });
                }
            });
            
            if (shouldProcess) {
                // Attendre un peu que le DOM soit stable
                setTimeout(processImages, 100);
            }
        });
        
        // Observer tous les conteneurs Quill
        const containers = document.querySelectorAll('.ql-container, .ql-editor, [class*="quill"]');
        containers.forEach(container => {
            observer.observe(container, {
                childList: true,
                subtree: true
            });
        });
    }
    
    /**
     * Optimise les performances en préchargeant les images visibles
     */
    function preloadVisibleImages() {
        const images = document.querySelectorAll('.lazy-image');
        
        images.forEach(img => {
            const rect = img.getBoundingClientRect();
            const isVisible = rect.top < window.innerHeight && rect.bottom > 0;
            
            if (isVisible) {
                loadImage(img);
            }
        });
    }
    
    /**
     * Gère le redimensionnement de la fenêtre
     */
    function handleResize() {
        // Débounce pour éviter trop d'appels
        clearTimeout(window.lazyLoadResizeTimeout);
        window.lazyLoadResizeTimeout = setTimeout(preloadVisibleImages, 250);
    }
    
    /**
     * API publique
     */
    window.LazyLoading = {
        init: initLazyLoading,
        processImages: processImages,
        loadAllImages: loadAllImages,
        destroy: function() {
            if (imageObserver) {
                imageObserver.disconnect();
            }
        }
    };
    
    // Initialisation automatique
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLazyLoading);
    } else {
        initLazyLoading();
    }
    
    // Gérer le redimensionnement
    window.addEventListener('resize', handleResize);
    
    // Gérer la visibilité de la page (pour optimiser quand l'onglet devient visible)
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            preloadVisibleImages();
        }
    });
    
})();
