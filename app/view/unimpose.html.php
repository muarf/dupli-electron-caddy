<?php
$title = "Désimposer un PDF";
ob_start();
?>

<div class="container">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <!-- En-tête -->
            <div class="page-header text-center" style="background: linear-gradient(135deg, #ffb3ba 0%, #ffdfba 100%); padding: 30px; border-radius: 10px; margin-bottom: 30px;">
                <h1 style="color: #333; margin: 0;">
                    <i class="fa fa-undo" style="margin-right: 15px;"></i>
                    Désimposer un PDF
                </h1>
                <p class="lead" style="color: #666; margin: 10px 0 0 0;">
                    Transforme un livret imposé en pages normales
                </p>
            </div>

            <!-- Messages d'erreur -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <h4><i class="fa fa-exclamation-triangle"></i> Erreurs détectées :</h4>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Formulaire d'upload -->
            <div class="panel panel-default">
                <div class="panel-body">
                    <form method="POST" enctype="multipart/form-data" class="form-horizontal">
                        <div class="file-upload-area" id="fileUploadArea" style="border: 3px dashed #ffb3ba; border-radius: 15px; padding: 40px; text-align: center; background: linear-gradient(135deg, #fff5f5 0%, #ffe8e8 100%); transition: all 0.3s ease; cursor: pointer;">
                            <div class="file-upload-icon" style="font-size: 48px; color: #ffb3ba; margin-bottom: 20px;">
                                <i class="fa fa-file-pdf-o"></i>
                            </div>
                            <div id="uploadText">
                                <h3 style="color: #333; margin-bottom: 10px;">Glissez votre PDF ici</h3>
                                <p style="color: #666; margin-bottom: 20px;">ou cliquez pour sélectionner un fichier</p>
                                <input type="file" name="pdf" id="pdf" accept=".pdf" style="display: none;" required>
                                <button type="button" class="btn btn-lg" style="background: #ffb3ba; border: none; color: white; padding: 12px 30px; border-radius: 25px;">
                                    <i class="fa fa-upload"></i> Sélectionner un PDF
                                </button>
                            </div>
                            <div id="fileInfo" style="display: none;">
                                <h4 style="color: #333; margin-bottom: 10px;">
                                    <i class="fa fa-check-circle" style="color: #28a745; margin-right: 10px;"></i>
                                    Fichier sélectionné
                                </h4>
                                <p id="fileName" style="color: #666; margin-bottom: 15px;"></p>
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fa fa-magic"></i> Désimposer le PDF
                                </button>
                                <button type="button" class="btn btn-default btn-lg" onclick="resetForm()" style="margin-left: 10px;">
                                    <i class="fa fa-times"></i> Annuler
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Informations -->
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="fa fa-info-circle"></i> Comment ça marche ?
                    </h3>
                </div>
                <div class="panel-body">
                    <p>Cette fonction permet de transformer un PDF imposé (livret) en pages normales :</p>
                    <ul>
                        <li><strong>Pages A3 imposées</strong> → <strong>Pages A4 normales</strong></li>
                        <li><strong>Ordre de livret</strong> → <strong>Ordre séquentiel</strong></li>
                        <li><strong>2 pages par feuille</strong> → <strong>1 page par feuille</strong></li>
                    </ul>
                    <p class="text-muted">
                        <i class="fa fa-lightbulb-o"></i> 
                        Parfait pour récupérer un document original à partir d'un livret déjà imposé.
                    </p>
                </div>
            </div>

            <!-- Résultat -->
            <?php if ($success && !empty($result)): ?>
                <div class="panel panel-success">
                    <div class="panel-heading">
                        <h3 class="panel-title">
                            <i class="fa fa-check-circle"></i> Désimposition réussie !
                        </h3>
                    </div>
                    <div class="panel-body text-center">
                        <div style="font-size: 48px; color: #28a745; margin-bottom: 20px;">
                            <i class="fa fa-file-text-o"></i>
                        </div>
                        <h4 style="color: #333; margin-bottom: 20px;">
                            Votre PDF a été désimposé avec succès
                        </h4>
                        <p style="color: #666; margin-bottom: 25px;">
                            Le fichier <strong><?= htmlspecialchars($result) ?></strong> est prêt au téléchargement.
                        </p>
                        <a href="<?= htmlspecialchars($download_url) ?>" class="btn btn-success btn-lg" onclick="openPdfInApp('<?= htmlspecialchars($download_url) ?>')">
                            <i class="fa fa-download"></i> Télécharger le PDF désimposé
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- JavaScript pour le drag & drop -->
<script>
        // Fonction pour ouvrir un PDF dans l'application
        async function openPdfInApp(pdfUrl) {
            try {
                // Vérifier si on est dans Tauri
                if (window.__TAURI__) {
                    const { invoke } = window.__TAURI__.tauri;
                    
                    // Construire le chemin local du fichier
                    const localPath = './app/public/' + pdfUrl;
                    
                    // Essayer d'ouvrir le fichier avec l'application par défaut
                    await invoke('open_file', { filePath: localPath });
                } else {
                    // Fallback pour navigateur web
                    window.open(pdfUrl, '_blank');
                }
            } catch (error) {
                console.error('Erreur lors de l\'ouverture du PDF:', error);
                // Fallback: ouvrir dans un nouvel onglet
                window.open(pdfUrl, '_blank');
            }
        }

document.addEventListener('DOMContentLoaded', function() {
    const fileUploadArea = document.getElementById('fileUploadArea');
    const fileInput = document.getElementById('pdf');
    const uploadText = document.getElementById('uploadText');
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');

    // Gestion du clic sur la zone d'upload
    fileUploadArea.addEventListener('click', function() {
        fileInput.click();
    });

    // Gestion de la sélection de fichier
    fileInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            handleFileSelect(this.files[0]);
        }
    });

    // Gestion du drag & drop
    fileUploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.style.borderColor = '#28a745';
        this.style.backgroundColor = '#f8fff8';
    });

    fileUploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.style.borderColor = '#ffb3ba';
        this.style.backgroundColor = '#fff5f5';
    });

    fileUploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        this.style.borderColor = '#ffb3ba';
        this.style.backgroundColor = '#fff5f5';
        
        const files = e.dataTransfer.files;
        if (files.length > 0 && files[0].type === 'application/pdf') {
            fileInput.files = files;
            handleFileSelect(files[0]);
        } else {
            alert('Veuillez sélectionner un fichier PDF valide.');
        }
    });

    function handleFileSelect(file) {
        if (file.type !== 'application/pdf') {
            alert('Veuillez sélectionner un fichier PDF.');
            return;
        }

        fileName.textContent = file.name;
        uploadText.style.display = 'none';
        fileInfo.style.display = 'block';
    }

    // Fonction pour réinitialiser le formulaire
    window.resetForm = function() {
        fileInput.value = '';
        uploadText.style.display = 'block';
        fileInfo.style.display = 'none';
    };
});
</script>

<?php
$content = ob_get_clean();
?>