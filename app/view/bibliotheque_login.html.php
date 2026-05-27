<div class="container" style="margin-top: 100px;">
    <div class="row">
        <div class="col-md-4 col-md-offset-4">
            <div class="panel panel-default shadow-lg" style="border-radius: 15px; overflow: hidden; border: none; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
                <div class="panel-heading text-center" style="background: #6366f1; color: white; padding: 25px; border: none;">
                    <i class="fa fa-book fa-3x mb-3"></i>
                    <h3 class="panel-title" style="font-weight: 600; font-size: 1.5em; margin-top: 10px;">Bibliothèque Sécurisée</h3>
                </div>
                <div class="panel-body" style="padding: 30px;">
                    <p class="text-muted text-center mb-4">L'accès à cette bibliothèque est restreint. Veuillez saisir le mot de passe.</p>
                    
                    <?php if (isset($bib_error)): ?>
                        <div class="alert alert-danger">
                            <i class="fa fa-times-circle"></i> <?= $bib_error ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-group">
                            <label for="bib_pass">Mot de passe</label>
                            <input type="password" name="bib_pass" id="bib_pass" class="form-control input-lg" 
                                   placeholder="Saisissez le mot de passe..." required autofocus
                                   style="border-radius: 10px; border: 1px solid #e2e8f0;">
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg btn-block" 
                                style="background: #6366f1; border: none; border-radius: 10px; padding: 12px; font-weight: 600; margin-top: 20px;">
                            Accéder à la bibliothèque <i class="fa fa-arrow-right ml-2"></i>
                        </button>
                    </form>
                </div>
                <div class="panel-footer text-center" style="background: #f8fafc; border: none; padding: 15px;">
                    <a href="?accueil" class="text-muted small"><i class="fa fa-home"></i> Retour à l'accueil</a>
                </div>
            </div>
        </div>
    </div>
</div>
