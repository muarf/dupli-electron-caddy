            <div class="form-section">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <h4><i class="fa fa-exclamation-triangle"></i> Erreurs détectées :</h4>
                        <ul class="mb-3">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="mt-3">
                            <button onclick="history.back()" class="btn btn-secondary me-2">
                                <i class="fa fa-arrow-left"></i> Retour
                            </button>
                            <button onclick="location.reload()" class="btn btn-primary me-2">
                                <i class="fa fa-refresh"></i> Recharger
                            </button>
                            <button onclick="window.location.href='?accueil'" class="btn btn-success">
                                <i class="fa fa-home"></i> Accueil
                            </button>
                        </div>
                    </div>
                <?php endif; ?>