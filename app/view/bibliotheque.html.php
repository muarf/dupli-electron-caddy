<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="mb-0"><i class="fa fa-book"></i> Bibliothèque</h1>
        </div>
    </div>

    <!-- Zone d'upload séparée et bien visible -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="upload-drop-zone" id="dropZone">
                <i class="fa fa-cloud-upload"></i>
                <span class="drop-zone-text">Glisser-déposer des fichiers PDF ou PNG ici</span>
                <span class="drop-zone-subtext">ou cliquez pour sélectionner des fichiers</span>
                <input type="file" id="fileInput" class="d-none" style="display: none !important;" accept=".pdf,.png"
                    multiple>
            </div>
        </div>
    </div>

    <!-- Zone d'indexation de dossier -->
    <div class="card mb-4">
        <div class="card-body text-center">
            <button class="btn btn-primary btn-lg" onclick="openIndexModal()">
                <i class="fa fa-folder-open"></i> Indexer un dossier
            </button>
            <p class="text-muted mt-2 mb-0"><small>Ajoutez des fichiers à la bibliothèque en indexant un dossier
                    externe</small></p>
        </div>
    </div>

    <!-- Barre de recherche -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="input-group input-group-lg">
                <span class="input-group-text"><i class="fa fa-search"></i></span>
                <input type="text" class="form-control" id="searchInput"
                    placeholder="Rechercher dans la bibliothèque...">
            </div>
        </div>
    </div>

    <!-- Grille de fichiers -->
    <div class="row" id="fileGrid">
        <!-- Les fichiers seront chargés ici -->
    </div>
</div>

<!-- Modal Visualisation PDF -->
<div class="modal fade" id="pdfViewerModal" tabindex="-1">
    <div class="modal-dialog modal-xl" style="max-width: 95vw;">
        <div class="modal-content" style="border-radius: 15px; overflow: hidden;">
            <div class="modal-header">
                <h5 class="modal-title" id="pdfViewerTitle">Visualisation PDF</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Fermer">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 0;">
                <div id="pdfViewerContainer"
                    style="position: relative; width: 100%; height: 80vh; overflow: auto; background: #525252;">
                    <div id="pdfLoadingIndicator"
                        style="display: none; text-align: center; padding: 50px; color: white;">
                        <i class="fa fa-spinner fa-spin fa-3x"></i>
                        <p style="margin-top: 20px;">Chargement du PDF...</p>
                    </div>
                    <canvas id="pdfCanvas" style="display: none; margin: 0 auto;"></canvas>
                    <div id="pdfImageView" style="display: none; text-align: center; padding: 20px;">
                        <img id="pdfImageElement" style="max-width: 100%; height: auto; display: block; margin: 0 auto;"
                            alt="Image">
                    </div>
                </div>
                <div
                    style="padding: 15px; background: #f8f9fa; border-top: 1px solid #dee2e6; position: sticky; bottom: 0; z-index: 10;">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2"
                        style="flex-wrap: nowrap;">
                        <div class="d-flex align-items-center gap-2" style="flex-shrink: 0;">
                            <button class="btn btn-sm btn-primary" id="prevPage" onclick="changePage(-1)">
                                <i class="fa fa-chevron-left"></i> Précédent
                            </button>
                            <span class="mx-2" style="white-space: nowrap; color: #212529; font-weight: 500;">Page
                                <input type="number" id="pageInput" min="1" value="1"
                                    style="width: 60px; text-align: center; display: inline-block; padding: 4px 8px; border: 1px solid #ced4da; border-radius: 4px; background: white; color: #212529; font-weight: 600;"
                                    onchange="goToPage(parseInt(this.value))"> / <span id="totalPages"
                                    style="color: #495057; font-weight: 600;">1</span></span>
                            <button class="btn btn-sm btn-primary" id="nextPage" onclick="changePage(1)">
                                Suivant <i class="fa fa-chevron-right"></i>
                            </button>
                        </div>
                        <div class="d-flex align-items-center gap-2" id="modalActions"
                            style="flex-shrink: 0; position: relative; border-left: 2px solid #dee2e6; padding-left: 15px; margin-left: 15px;">
                            <!-- Les boutons d'action seront ajoutés ici dynamiquement -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Indexation Dossier -->
<div class="modal fade" id="indexModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Indexer un dossier externe</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Fermer">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i> L'indexation permet d'ajouter des fichiers à la bibliothèque sans
                    les copier. Les fichiers doivent être accessibles par le serveur.
                </div>

                <div class="mb-3">
                    <label class="form-label">Chemin du dossier</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="folderPath" placeholder="/chemin/vers/le/dossier">

                        <!-- Bouton pour Tauri -->
                        <button class="btn btn-outline-secondary" type="button" onclick="browseFolder()" id="browseBtn"
                            style="display:none;">
                            <i class="fa fa-folder-open"></i> Parcourir
                        </button>

                        <!-- Bouton pour Web Standard (Simulé pour l'UX, mais limité par sécurité) -->
                        <button class="btn btn-outline-secondary" type="button" id="webBrowseInfo"
                            onclick="showAppModal({ title: 'Aide chemin', message: 'Sur un navigateur web standard, pour des raisons de sécurité, vous ne pouvez pas sélectionner un dossier local pour l\'indexation serveur. Veuillez copier-coller le chemin absolu du dossier sur le serveur (ex: /var/www/html/...)', type: 'info' })">
                            <i class="fa fa-info-circle"></i> Aide chemin
                        </button>
                    </div>
                    <small class="text-muted">Saisissez le chemin absolu du dossier sur le serveur.</small>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="recursiveCheck">
                    <label class="form-check-label" for="recursiveCheck">Indexer récursivement (sous-dossiers)</label>
                </div>

                <div class="d-grid gap-2 mb-3">
                    <button class="btn btn-secondary" onclick="previewDirectory()">
                        <i class="fa fa-search"></i> Analyser le dossier
                    </button>
                </div>

                <div id="previewArea" style="display:none;">
                    <h6><i class="fa fa-files-o"></i> Fichiers trouvés : <span id="foundCount">0</span></h6>
                    <div class="progress mb-3" style="display:none;" id="indexProgress">
                        <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                    </div>
                    <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>Fichier</th>
                                    <th>Type</th>
                                    <th>Taille</th>
                                </tr>
                            </thead>
                            <tbody id="previewList"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button>
                <button type="button" class="btn btn-primary" onclick="startIndexing()" id="indexBtn" disabled>Lancer
                    l'indexation</button>
            </div>
        </div>
    </div>
</div>



<style>
    .upload-drop-zone {
        border: 3px dashed #dee2e6;
        border-radius: 12px;
        padding: 50px 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        color: #6c757d;
    }

    .upload-drop-zone:hover,
    .upload-drop-zone.dragover {
        background: linear-gradient(135deg, #e9ecef 0%, #f8f9fa 100%);
        border-color: #0d6efd;
        color: #0d6efd;
        transform: scale(1.01);
    }

    .upload-drop-zone i {
        font-size: 3.5em;
        margin-bottom: 15px;
        color: #6c757d;
        transition: color 0.3s;
    }

    .upload-drop-zone:hover i,
    .upload-drop-zone.dragover i {
        color: #0d6efd;
    }

    .drop-zone-text {
        display: block;
        font-size: 1.2em;
        font-weight: 500;
        margin-bottom: 8px;
        color: #495057;
    }

    .drop-zone-subtext {
        display: block;
        font-size: 0.9em;
        color: #6c757d;
    }

    .file-card {
        transition: transform 0.2s, box-shadow 0.2s;
        border: 1px solid #e0e0e0;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
        border-radius: 8px;
        overflow: visible;
        background: white;
        height: 100%;
        display: flex;
        flex-direction: column;
        position: relative;
    }

    .file-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        border-color: #0d6efd;
    }

    .file-thumb-wrapper {
        overflow: hidden;
        border-radius: 8px 8px 0 0;
    }

    .file-thumb {
        height: 200px;
        width: 100%;
        object-fit: contain;
        background: #f8f9fa;
        border-bottom: 1px solid #eee;
        padding: 15px;
    }

    .card-body {
        padding: 15px;
        flex: 1;
        display: flex;
        flex-direction: column;
        min-height: 120px;
    }

    .card-title {
        font-size: 0.95rem;
        font-weight: 600;
        margin-bottom: 8px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        color: #212529;
    }

    .file-meta {
        font-size: 0.85rem;
        color: #6c757d;
        margin-bottom: 12px;
        flex-shrink: 0;
    }

    .file-actions {
        margin-top: auto;
        display: flex;
        flex-direction: column;
        gap: 6px;
        padding-top: 8px;
        position: relative;
        z-index: 1;
    }

    .file-actions-row {
        display: flex;
        gap: 6px;
        width: 100%;
    }

    .file-actions-row .btn {
        flex: 1;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 8px 12px;
        white-space: nowrap;
        min-width: 0;
    }

    .file-actions .btn-group {
        flex: 1;
        position: relative;
    }

    .file-actions .btn-group {
        position: static;
    }

    .file-actions .btn-group .dropdown-menu {
        position: fixed;
        z-index: 9999;
        min-width: 200px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    }

    .file-actions-menu-trigger {
        position: relative;
    }

    .floating-menu .dropdown-item:hover {
        background-color: #f5f5f5;
    }

    .file-thumb {
        cursor: pointer;
    }

    .file-actions .btn i {
        margin-right: 5px;
    }

    .badge-ext {
        background-color: rgba(13, 110, 253, 0.9);
        backdrop-filter: blur(2px);
        font-size: 0.75rem;
    }
</style>

<!-- PDF.js -->
<script src="js/build/pdf.js"></script>
<script>
    let currentFiles = [];
    let filesToIndex = [];
    let pdfDoc = null;
    let currentPage = 1;
    let pdfViewerFileId = null;

    document.addEventListener('DOMContentLoaded', function () {
        loadFiles();

        // Drag & Drop
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');

        // Empêcher la propagation du clic depuis l'input file
        fileInput.addEventListener('click', (e) => {
            e.stopPropagation();
        });

        dropZone.addEventListener('click', (e) => {
            if (e.target !== fileInput && e.target !== dropZone.querySelector('i') && e.target !== dropZone.querySelector('.drop-zone-text') && e.target !== dropZone.querySelector('.drop-zone-subtext')) {
                fileInput.click();
            }
        });

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('dragover');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            handleFiles(e.dataTransfer.files);
        });

        fileInput.addEventListener('change', () => {
            handleFiles(fileInput.files);
        });

        // Recherche
        let searchTimeout;
        document.getElementById('searchInput').addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                loadFiles(e.target.value);
            }, 300);
        });

        // Check for Tauri or Electron
        const isTauri = typeof window !== 'undefined' && window.__TAURI__;
        const isElectron = typeof window !== 'undefined' && window.electronAPI;

        if (isTauri || isElectron) {
            document.getElementById('browseBtn').style.display = 'block';
            document.getElementById('webBrowseInfo').style.display = 'none';
        } else {
            document.getElementById('browseBtn').style.display = 'none';
            document.getElementById('webBrowseInfo').style.display = 'block';
        }
    });

    function handleFiles(files) {
        Array.from(files).forEach(file => {
            uploadFile(file);
        });
    }

    function uploadFile(file) {
        const formData = new FormData();
        formData.append('file', file);

        fetch('?upload_bibliotheque', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadFiles();
                } else {
                    showAppModal({ message: 'Erreur upload: ' + data.error, type: 'danger' });
                }
            })
            .catch(error => console.error('Error:', error));
    }

    // Variable pour annuler les requêtes en cours
    let currentSearchAbortController = null;

    function loadFiles(search = null) {
        // Si search n'est pas fourni, utiliser la valeur actuelle de la barre de recherche
        if (search === null || search === undefined) {
            const searchInput = document.getElementById('searchInput');
            search = searchInput ? searchInput.value : '';
        }

        // Annuler la requête précédente si elle est encore en cours
        if (currentSearchAbortController) {
            currentSearchAbortController.abort();
        }

        // Créer un nouveau AbortController pour cette requête
        currentSearchAbortController = new AbortController();

        // Afficher l'indicateur de progression
        const grid = document.getElementById('fileGrid');
        grid.innerHTML = `
        <div class="col-12">
            <div class="text-center" style="padding: 50px;">
                <i class="fa fa-spinner fa-spin fa-3x" style="color: #0d6efd; margin-bottom: 15px;"></i>
                <p style="color: #6c757d; font-size: 1.1em;">Recherche en cours...</p>
            </div>
        </div>
    `;

        fetch('?search_bibliotheque&q=' + encodeURIComponent(search), {
            signal: currentSearchAbortController.signal
        })
            .then(response => {
                // Vérifier si la réponse est vide
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('Réponse non-JSON reçue du serveur (Content-Type: ' + contentType + ')');
                }

                // Lire le texte de la réponse
                return response.text().then(text => {
                    if (!text || text.trim() === '') {
                        throw new Error('Réponse vide du serveur');
                    }
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Texte reçu du serveur:', text);
                        throw new Error('Réponse JSON invalide: ' + e.message);
                    }
                });
            })
            .then(data => {
                // Vérifier que cette réponse correspond bien à la recherche actuelle
                const currentSearchInput = document.getElementById('searchInput');
                const currentSearchValue = currentSearchInput ? currentSearchInput.value : '';

                // Ignorer les réponses qui ne correspondent pas à la recherche actuelle
                if (search !== currentSearchValue) {
                    return; // Ignorer cette réponse obsolète
                }

                if (data.success) {
                    renderGrid(data.files);
                } else {
                    console.error('Erreur serveur:', data.error);
                    const grid = document.getElementById('fileGrid');
                    grid.innerHTML = '<div class="col-12"><div class="alert alert-danger text-center"><i class="fa fa-exclamation-triangle"></i> Erreur lors du chargement: ' + (data.error || 'Erreur inconnue') + '</div></div>';
                }
            })
            .catch(error => {
                // Ignorer les erreurs d'annulation (AbortError)
                if (error.name === 'AbortError') {
                    return; // Ignorer silencieusement les annulations
                }

                console.error('Erreur lors du chargement des fichiers:', error);
                const grid = document.getElementById('fileGrid');
                grid.innerHTML = '<div class="col-12"><div class="alert alert-danger text-center"><i class="fa fa-exclamation-triangle"></i> Erreur de connexion: ' + error.message + '</div></div>';
            });
    }

    function renderGrid(files) {
        const grid = document.getElementById('fileGrid');
        grid.innerHTML = '';

        if (files.length === 0) {
            grid.innerHTML = '<div class="col-12"><div class="alert alert-info text-center"><i class="fa fa-info-circle"></i> Aucun fichier dans la bibliothèque. Ajoutez des fichiers pour commencer.</div></div>';
            return;
        }

        files.forEach(file => {
            const col = document.createElement('div');
            col.className = 'col-lg-3 col-md-4 col-sm-6 mb-4';

            const thumbUrl = '?get_bibliotheque_thumbnail&file=' + encodeURIComponent(file.thumbnail_path);
            const isExternal = file.is_external == 1;
            const badge = isExternal ? '<span class="badge badge-ext position-absolute top-0 end-0 m-2" title="Fichier externe"><i class="fa fa-link"></i></span>' : '';

            col.innerHTML = `
            <div class="card file-card">
                <div class="position-relative file-thumb-wrapper">
                    <img src="${thumbUrl}" class="file-thumb" alt="${file.filename}" onclick="openPdfViewer(${file.id}, '${file.file_type}')" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'200\' height=\'200\'%3E%3Crect fill=\'%23f0f0f0\' width=\'200\' height=\'200\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\' fill=\'%23999\' font-family=\'Arial\' font-size=\'14\'%3E${file.file_type.toUpperCase()}%3C/text%3E%3C/svg%3E'">
                    ${badge}
                </div>
                <div class="card-body">
                    <h6 class="card-title" title="${file.filename}">${file.filename}</h6>
                    <div class="file-meta">
                        ${formatBytes(file.file_size)} • ${file.file_type.toUpperCase()}
                    </div>
                    ${file.match_contexts && file.match_contexts.length > 0 ? `
                    <div class="file-match-contexts" style="font-size: 0.8rem; color: #6c757d; margin-top: 8px; margin-bottom: 8px; padding-top: 8px; border-top: 1px solid #e9ecef;">
                        <div style="font-weight: 600; margin-bottom: 4px; color: #495057;">Résultats trouvés dans :</div>
                        ${file.match_contexts.map(ctx => `<div style="margin-bottom: 4px; line-height: 1.4;">${ctx}</div>`).join('')}
                    </div>
                    ` : ''}
                    <div class="file-actions">
                        <div class="file-actions-row">
                            <button class="btn btn-primary btn-sm" onclick="openFile(${file.id})" title="Ouvrir le fichier">
                                <i class="fa fa-external-link"></i> Ouvrir
                            </button>
                            <button class="btn btn-info btn-sm" onclick="printFile(${file.id})" title="Imprimer le fichier">
                                <i class="fa fa-print"></i> Imprimer
                            </button>
                            <div class="btn-group btn-group-sm file-actions-menu-trigger" role="group" data-file-id="${file.id}" data-file-type="${file.file_type}">
                                <button type="button" class="btn btn-success" onclick="showActionsMenu(event, ${file.id}, '${file.file_type}')">
                                    <i class="fa fa-print"></i> Imposer <i class="fa fa-caret-down"></i>
                                </button>
                            </div>
                        </div>
                        <div class="file-actions-row">
                            ${file.file_type === 'pdf' || file.file_type === 'png' ? `
                            <div class="btn-group btn-group-sm file-actions-menu-trigger" role="group" data-file-id="${file.id}" data-file-type="${file.file_type}">
                                <button type="button" class="btn btn-warning" onclick="showModifyMenu(event, ${file.id}, '${file.file_type}')">
                                    <i class="fa fa-edit"></i> Modifier <i class="fa fa-caret-down"></i>
                                </button>
                            </div>
                            ` : '<div style="flex: 1;"></div>'}
                            <button class="btn btn-danger btn-sm" onclick="deleteFile(${file.id})" title="Supprimer">
                                <i class="fa fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
            grid.appendChild(col);
        });
    }

    function downloadFile(id) {
        const fileUrl = '?get_bibliotheque_file&id=' + encodeURIComponent(id);
        const link = document.createElement('a');
        link.href = fileUrl;
        link.download = ''; // Le nom du fichier sera déterminé par le serveur
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function printFile(id) {
        // Obtenir les infos du fichier
        // On doit trouver le filename et le type à partir de l'ID si possible, ou faire un fetch
        // Pour l'instant, simplifions en passant l'URL. 
        // L'idéal serait d'avoir le filename passé en paramètre de printFile,
        // mais cela demanderait de changer tous les appels onclick.

        // On va essayer de récupérer le nom du fichier depuis le DOM si possible
        // (Hack: on cherche l'image qui a cet ID dans son onclick)
        let filename = 'Document';
        let fileType = 'pdf';

        // Essayer de trouver l'élément dans la grille (si affiché)
        const fileCardContainer = document.querySelector(`button[onclick="printFile(${id})"]`);
        if (fileCardContainer) {
            // Remonter au container
            const cardBody = fileCardContainer.closest('.card-body');
            if (cardBody) {
                const titleEl = cardBody.querySelector('.card-title');
                if (titleEl) filename = titleEl.innerText;

                const metaEl = cardBody.querySelector('.file-meta');
                if (metaEl && metaEl.innerText.toLowerCase().includes('png')) fileType = 'png';
            }
        }

        // Construire l'URL complète
        const fileUrl = window.location.origin + '/?get_bibliotheque_file&id=' + encodeURIComponent(id);

        // Utiliser la modale globale
        if (window.openPrintModal) {
            window.openPrintModal(fileUrl, id, fileType, filename);
        } else {
            // Fallback (ne devrait pas arriver si footer inclus)
            downloadFile(id);
        }
    }

    function openFile(id) {
        // Construire l'URL complète du fichier
        const fileUrl = window.location.origin + '/?get_bibliotheque_file&id=' + encodeURIComponent(id);

        // Vérifier si l'API Electron est disponible
        if (window.electronAPI && window.electronAPI.openExternalFile) {
            // Dans Electron : ouvrir avec l'application système
            console.log('Ouverture externe pour:', fileUrl);
            window.electronAPI.openExternalFile(fileUrl)
                .then(result => {
                    if (result.success) {
                        console.log('Fichier ouvert avec succès');
                    } else {
                        console.error('Erreur lors de l\'ouverture:', result.error);
                        showAppModal({ message: 'Erreur lors de l\'ouverture du fichier: ' + (result.error || 'Erreur inconnue'), type: 'danger' });
                    }
                })
                .catch(error => {
                    console.error('Erreur ouverture:', error);
                    showAppModal({ message: 'Erreur lors de l\'ouverture du fichier: ' + error.message, type: 'danger' });
                });
        } else {
            // Dans un navigateur web : ouvrir dans un nouvel onglet
            console.log('Ouverture dans un nouvel onglet:', fileUrl);
            window.open(fileUrl, '_blank');
        }
    }

    function deleteFile(id) {
        showAppModal({
            message: 'Êtes-vous sûr de vouloir supprimer ce fichier ?',
            confirm: true,
            type: 'danger'
        }, (confirmed) => {
            if (!confirmed) return;

            fetch('?delete_bibliotheque_file', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadFiles();
                    } else {
                        showAppModal({ message: 'Erreur suppression: ' + data.error, type: 'danger' });
                    }
                });
        });
    }

    // Indexation Folder logic
    function openIndexModal() {
        const modalElement = document.getElementById('indexModal');
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        } else {
            // Fallback pour jQuery Bootstrap
            $(modalElement).modal('show');
        }
    }

    async function browseFolder() {
        // Tauri
        if (window.__TAURI__) {
            try {
                const selected = await window.__TAURI__.dialog.open({
                    directory: true,
                    multiple: false,
                });
                if (selected) {
                    document.getElementById('folderPath').value = selected;
                }
            } catch (e) {
                console.error(e);
            }
            return;
        }

        // Electron
        if (window.electronAPI) {
            try {
                // Utiliser l'API Electron pour ouvrir un dialogue de sélection de dossier
                const result = await window.electronAPI.showOpenDialog({
                    properties: ['openDirectory']
                });
                if (result && !result.canceled && result.filePaths && result.filePaths.length > 0) {
                    document.getElementById('folderPath').value = result.filePaths[0];
                }
            } catch (e) {
                console.error('Erreur lors de la sélection du dossier Electron:', e);
            }
            return;
        }
    }

    function previewDirectory() {
        const path = document.getElementById('folderPath').value;
        const recursive = document.getElementById('recursiveCheck').checked;

        if (!path) return showAppModal({ message: 'Veuillez saisir un chemin', type: 'warning' });

        document.getElementById('previewArea').style.display = 'block';
        document.getElementById('previewList').innerHTML = '<tr><td colspan="3" class="text-center"><i class="fa fa-spinner fa-spin"></i> Analyse en cours...</td></tr>';

        fetch('?preview_directory', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ path, recursive })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    filesToIndex = data.files;
                    document.getElementById('foundCount').textContent = filesToIndex.length;
                    document.getElementById('indexBtn').disabled = filesToIndex.length === 0;

                    const tbody = document.getElementById('previewList');
                    tbody.innerHTML = '';

                    // Limiter l'affichage à 100 fichiers pour la perf
                    const displayFiles = filesToIndex.slice(0, 100);
                    displayFiles.forEach(f => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                    <td><small class="text-truncate d-block" style="max-width: 300px;" title="${f.path}">${f.filename}</small></td>
                    <td>${f.type}</td>
                    <td>${formatBytes(f.size)}</td>
                `;
                        tbody.appendChild(tr);
                    });

                    if (filesToIndex.length > 100) {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `<td colspan="3" class="text-center text-muted">... et ${filesToIndex.length - 100} autres fichiers</td>`;
                        tbody.appendChild(tr);
                    }
                } else {
                    document.getElementById('previewList').innerHTML = `<tr><td colspan="3" class="text-danger">Erreur: ${data.error}</td></tr>`;
                }
            });
    }

    async function startIndexing() {
        const btn = document.getElementById('indexBtn');
        const progress = document.getElementById('indexProgress');
        const progressBar = progress.querySelector('.progress-bar');

        btn.disabled = true;
        progress.style.display = 'flex';

        let processed = 0;
        let errors = 0;

        for (const file of filesToIndex) {
            try {
                const response = await fetch('?index_file', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ path: file.path })
                });
                const result = await response.json();
                if (!result.success) errors++;
            } catch (e) {
                errors++;
                console.error(e);
            }

            processed++;
            const percent = (processed / filesToIndex.length) * 100;
            progressBar.style.width = percent + '%';
            progressBar.textContent = Math.round(percent) + '%';
        }

        showAppModal({ message: `Indexation terminée ! ${processed - errors} fichiers ajoutés, ${errors} erreurs.`, type: 'info' });
        const modalElement = document.getElementById('indexModal');
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) modal.hide();
        } else {
            $(modalElement).modal('hide');
        }
        loadFiles();

        // Reset
        btn.disabled = false;
        progress.style.display = 'none';
        progressBar.style.width = '0%';
        filesToIndex = [];
    }

    function formatBytes(bytes, decimals = 2) {
        if (!+bytes) return '0 Bytes';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return `${parseFloat((bytes / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`;
    }

    // Menu flottant pour les actions
    let currentFloatingMenu = null;

    function showActionsMenu(event, fileId, fileType) {
        event.stopPropagation();
        closeFloatingMenu();

        const button = event.target.closest('button');
        const rect = button.getBoundingClientRect();

        const menu = document.createElement('div');
        menu.className = 'floating-menu';
        menu.style.position = 'fixed';
        menu.style.left = rect.left + 'px';

        // Calculer la position pour que le menu reste dans la fenêtre
        const menuHeight = 200; // Estimation de la hauteur du menu
        const windowHeight = window.innerHeight;
        const spaceBelow = windowHeight - rect.bottom;
        const spaceAbove = rect.top;

        // Si pas assez d'espace en bas, afficher au-dessus
        if (spaceBelow < menuHeight && spaceAbove > menuHeight) {
            menu.style.top = (rect.top - menuHeight - 5) + 'px';
        } else {
            menu.style.top = (rect.bottom + 5) + 'px';
        }

        menu.style.zIndex = '10000';
        menu.style.minWidth = '200px';
        menu.style.backgroundColor = 'white';
        menu.style.border = '1px solid #ddd';
        menu.style.borderRadius = '4px';
        menu.style.boxShadow = '0 4px 12px rgba(0,0,0,0.3)';
        menu.style.padding = '5px 0';
        menu.style.maxHeight = (windowHeight - 20) + 'px';
        menu.style.overflowY = 'auto';

        menu.innerHTML = `
        <a class="dropdown-item" href="?imposition_brochure&from_lib=${fileId}" style="display: block; padding: 8px 15px; color: #333; text-decoration: none;">
            <i class="fa fa-book" style="color: #28a745; margin-right: 8px;"></i> Imposition Brochure
        </a>
        <a class="dropdown-item" href="?imposition_livre&from_lib=${fileId}" style="display: block; padding: 8px 15px; color: #333; text-decoration: none;">
            <i class="fa fa-book" style="color: #007bff; margin-right: 8px;"></i> Imposition Livre
        </a>
        <a class="dropdown-item" href="?imposition_tracts&from_lib=${fileId}" style="display: block; padding: 8px 15px; color: #333; text-decoration: none;">
            <i class="fa fa-copy" style="color: #ffd93d; margin-right: 8px;"></i> Imposition Tracts
        </a>
    `;

        document.body.appendChild(menu);
        currentFloatingMenu = menu;

        // Fermer au clic ailleurs
        setTimeout(() => {
            document.addEventListener('click', closeFloatingMenu, { once: true });
        }, 10);
    }

    function showModifyMenu(event, fileId, fileType) {
        event.stopPropagation();
        closeFloatingMenu();

        const button = event.target.closest('button');
        const rect = button.getBoundingClientRect();

        const menu = document.createElement('div');
        menu.className = 'floating-menu';
        menu.style.position = 'fixed';
        menu.style.left = rect.left + 'px';

        // Calculer la position pour que le menu reste dans la fenêtre
        const menuHeight = 200; // Estimation de la hauteur du menu
        const windowHeight = window.innerHeight;
        const spaceBelow = windowHeight - rect.bottom;
        const spaceAbove = rect.top;

        // Si pas assez d'espace en bas, afficher au-dessus
        if (spaceBelow < menuHeight && spaceAbove > menuHeight) {
            menu.style.top = (rect.top - menuHeight - 5) + 'px';
        } else {
            menu.style.top = (rect.bottom + 5) + 'px';
        }

        menu.style.zIndex = '10000';
        menu.style.minWidth = '200px';
        menu.style.backgroundColor = 'white';
        menu.style.border = '1px solid #ddd';
        menu.style.borderRadius = '4px';
        menu.style.boxShadow = '0 4px 12px rgba(0,0,0,0.3)';
        menu.style.padding = '5px 0';
        menu.style.maxHeight = (windowHeight - 20) + 'px';
        menu.style.overflowY = 'auto';

        let items = '';
        if (fileType === 'pdf') {
            items += `<a class="dropdown-item" href="?pdf_to_png&from_lib=${fileId}" style="display: block; padding: 8px 15px; color: #333; text-decoration: none;">
            <i class="fa fa-file-image-o" style="margin-right: 8px;"></i> PDF vers PNG
        </a>`;
        }
        if (fileType === 'png') {
            items += `<a class="dropdown-item" href="?png_to_pdf&from_lib=${fileId}" style="display: block; padding: 8px 15px; color: #333; text-decoration: none;">
            <i class="fa fa-file-pdf-o" style="margin-right: 8px;"></i> PNG vers PDF
        </a>`;
        }
        if (fileType === 'pdf' || fileType === 'png') {
            items += `<a class="dropdown-item" href="?image_processor&from_lib=${fileId}" style="display: block; padding: 8px 15px; color: #333; text-decoration: none;">
            <i class="fa fa-sliders" style="margin-right: 8px;"></i> Bitmap/Luminosité
        </a>`;
        }
        if (fileType === 'png') {
            items += `<a class="dropdown-item" href="?riso_separator&from_lib=${fileId}" style="display: block; padding: 8px 15px; color: #333; text-decoration: none;">
            <i class="fa fa-palette" style="margin-right: 8px;"></i> Séparer les couleurs
        </a>`;
        }

        menu.innerHTML = items;

        document.body.appendChild(menu);
        currentFloatingMenu = menu;

        // Fermer au clic ailleurs
        setTimeout(() => {
            document.addEventListener('click', closeFloatingMenu, { once: true });
        }, 10);
    }

    function closeFloatingMenu() {
        if (currentFloatingMenu) {
            currentFloatingMenu.remove();
            currentFloatingMenu = null;
        }
    }

    // Fermer le menu au scroll
    window.addEventListener('scroll', closeFloatingMenu, true);

    // PDF Viewer functions
    function openPdfViewer(fileId, fileType) {
        // Réinitialiser l'affichage
        document.getElementById('pdfCanvas').style.display = 'none';
        document.getElementById('pdfImageView').style.display = 'none';
        document.getElementById('pdfLoadingIndicator').style.display = 'none';
        document.getElementById('modalActions').style.display = 'none';

        // Stocker les infos du fichier pour les actions
        window.currentViewerFileId = fileId;
        window.currentViewerFileType = fileType;

        if (fileType !== 'pdf') {
            // Pour les PNG, on peut afficher directement dans une modal simple
            const fileUrl = '?get_bibliotheque_file&id=' + encodeURIComponent(fileId);
            document.getElementById('pdfViewerTitle').textContent = 'Visualisation Image';
            document.getElementById('pdfImageElement').src = fileUrl;
            document.getElementById('pdfImageView').style.display = 'block';

            // Ajouter les boutons d'action pour PNG
            const actionsHtml = `
            <button type="button" class="btn btn-sm btn-primary" onclick="openFile(${fileId})" title="Ouvrir le fichier" style="border-right: 1px solid #dee2e6; margin-right: 8px; padding-right: 12px;">
                <i class="fa fa-external-link"></i> Ouvrir
            </button>
            <button type="button" class="btn btn-sm btn-info" onclick="printFile(${fileId})" title="Imprimer le fichier" style="border-right: 1px solid #dee2e6; margin-right: 8px; padding-right: 12px;">
                <i class="fa fa-print"></i> Imprimer
            </button>
            <div class="btn-group btn-group-sm" style="border-right: 1px solid #dee2e6; margin-right: 8px; padding-right: 8px;">
                <button type="button" class="btn btn-sm btn-success" onclick="showActionsMenu(event, ${fileId}, '${fileType}')">
                    <i class="fa fa-print"></i> Imposer
                </button>
            </div>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-sm btn-warning" onclick="showModifyMenu(event, ${fileId}, '${fileType}')">
                    <i class="fa fa-edit"></i> Modifier
                </button>
            </div>
        `;
            document.getElementById('modalActions').innerHTML = actionsHtml;
            document.getElementById('modalActions').style.display = 'flex';

            const modalElement = document.getElementById('pdfViewerModal');
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                const bsModal = new bootstrap.Modal(modalElement);
                bsModal.show();
            } else {
                $(modalElement).modal('show');
            }
            return;
        }

        pdfViewerFileId = fileId;
        currentPage = 1;
        pdfDoc = null;

        const fileUrl = '?get_bibliotheque_file&id=' + encodeURIComponent(fileId);
        document.getElementById('pdfViewerTitle').textContent = 'Visualisation PDF';

        // Ajouter les boutons d'action pour PDF
        const actionsHtml = `
        <button type="button" class="btn btn-sm btn-primary" onclick="openFile(${fileId})" title="Ouvrir le fichier" style="border-right: 1px solid #dee2e6; margin-right: 8px; padding-right: 12px;">
            <i class="fa fa-external-link"></i> Ouvrir
        </button>
        <button type="button" class="btn btn-sm btn-info" onclick="printFile(${fileId})" title="Imprimer le fichier" style="border-right: 1px solid #dee2e6; margin-right: 8px; padding-right: 12px;">
            <i class="fa fa-print"></i> Imprimer
        </button>
        <div class="btn-group btn-group-sm" style="border-right: 1px solid #dee2e6; margin-right: 8px; padding-right: 8px;">
            <button type="button" class="btn btn-sm btn-success" onclick="showActionsMenu(event, ${fileId}, '${fileType}')">
                <i class="fa fa-print"></i> Imposer
            </button>
        </div>
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-sm btn-warning" onclick="showModifyMenu(event, ${fileId}, '${fileType}')">
                <i class="fa fa-edit"></i> Modifier
            </button>
        </div>
    `;
        document.getElementById('modalActions').innerHTML = actionsHtml;
        document.getElementById('modalActions').style.display = 'flex';

        // Ouvrir la modal d'abord
        const modalElement = document.getElementById('pdfViewerModal');
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const bsModal = new bootstrap.Modal(modalElement);
            bsModal.show();
        } else {
            $(modalElement).modal('show');
        }

        // Afficher l'indicateur de chargement
        document.getElementById('pdfLoadingIndicator').style.display = 'block';

        // Attendre un peu pour que la modal soit complètement rendue
        setTimeout(() => {
            // Charger le PDF via fetch pour créer un blob
            fetch(fileUrl)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erreur HTTP: ' + response.status);
                    }
                    return response.blob();
                })
                .then(blob => {
                    if (blob.size === 0) {
                        throw new Error('Le fichier PDF est vide');
                    }

                    // Créer un blob URL pour PDF.js
                    const blobUrl = URL.createObjectURL(blob);

                    // Configurer PDF.js worker si nécessaire
                    if (typeof pdfjsLib !== 'undefined' && !pdfjsLib.GlobalWorkerOptions.workerSrc) {
                        pdfjsLib.GlobalWorkerOptions.workerSrc = 'js/build/pdf.worker.js';
                    }

                    // Charger le PDF avec PDF.js depuis le blob
                    return pdfjsLib.getDocument({ url: blobUrl }).promise;
                })
                .then(function (pdf) {
                    pdfDoc = pdf;
                    document.getElementById('totalPages').textContent = pdf.numPages;
                    document.getElementById('pdfLoadingIndicator').style.display = 'none';
                    document.getElementById('pdfCanvas').style.display = 'block';
                    renderPage(1);
                })
                .catch(function (error) {
                    console.error('Erreur chargement PDF:', error);
                    document.getElementById('pdfLoadingIndicator').innerHTML = '<div style="text-align: center; padding: 50px; color: white;"><i class="fa fa-exclamation-triangle fa-3x"></i><p style="margin-top: 20px;">Erreur lors du chargement du PDF: ' + error.message + '</p></div>';
                });
        }, 100);
    }

    function renderPage(pageNum) {
        if (!pdfDoc) return;

        currentPage = Math.max(1, Math.min(pageNum, pdfDoc.numPages));

        pdfDoc.getPage(currentPage).then(function (page) {
            const canvas = document.getElementById('pdfCanvas');
            if (!canvas) {
                console.error('Canvas non trouvé');
                return;
            }
            const context = canvas.getContext('2d');
            const viewport = page.getViewport({ scale: 1.0 });

            canvas.height = viewport.height;
            canvas.width = viewport.width;

            const renderContext = {
                canvasContext: context,
                viewport: viewport
            };

            page.render(renderContext).promise.then(function () {
                document.getElementById('pageInput').value = currentPage;
                document.getElementById('totalPages').textContent = pdfDoc.numPages;

                // Activer/désactiver les boutons
                document.getElementById('prevPage').disabled = currentPage <= 1;
                document.getElementById('nextPage').disabled = currentPage >= pdfDoc.numPages;
            });
        });
    }

    function changePage(delta) {
        if (!pdfDoc) return;
        renderPage(currentPage + delta);
    }

    function goToPage(pageNum) {
        if (!pdfDoc) return;
        renderPage(pageNum);
    }

</script>