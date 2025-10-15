<div class="section">
  <div class="container">
    <div class="row">
      <div class="col-md-10 col-md-offset-1">
        <h1 class="text-center"><?php _e('admin.title'); ?></h1>
        <hr>
        
        
        <!-- Gestion des machines -->
        <div class="panel panel-success">
          <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-cogs"></i> <?php _e('admin.machine_management'); ?></h3>
          </div>
          <div class="panel-body">
            <div class="row">
              <div class="col-md-3">
                <a href="?admin&machines" class="btn btn-success btn-block">
                  <i class="fa fa-print"></i> <?php _e('admin.machine_management_btn'); ?>
                </a>
                <small class="text-muted"><?php _e('admin.machine_management_desc'); ?></small>
              </div>
              <div class="col-md-3">
                <a href="?admin&changes" class="btn btn-primary btn-block">
                  <i class="fa fa-exchange"></i> <?php _e('admin.change_management_btn'); ?>
                </a>
                <small class="text-muted"><?php _e('admin.change_management_desc'); ?></small>
              </div>
              <div class="col-md-3">
                <a href="?admin&prix" class="btn btn-info btn-block">
                  <i class="fa fa-euro"></i> <?php _e('admin.price_management_btn'); ?>
                </a>
                <small class="text-muted"><?php _e('admin.price_management_desc'); ?></small>
              </div>
              <div class="col-md-3">
                <a href="?admin&tirages" class="btn btn-warning btn-block">
                  <i class="fa fa-list"></i> <?php _e('admin.print_management_btn'); ?>
                </a>
                <small class="text-muted"><?php _e('admin.print_management_desc'); ?></small>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Gestion du contenu -->
        <div class="panel panel-info">
          <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-file-text"></i> <?php _e('admin.content_management'); ?></h3>
          </div>
          <div class="panel-body">
            <div class="row">
              <div class="col-md-3">
                <a href="?admin&aide_machines" class="btn btn-primary btn-block">
                  <i class="fa fa-question-circle"></i> <?php _e('admin.help_management_btn'); ?>
                </a>
                <small class="text-muted"><?php _e('admin.help_management_desc'); ?></small>
              </div>
              <div class="col-md-3">
                <a href="?admin&news" class="btn btn-info btn-block">
                  <i class="fa fa-newspaper-o"></i> <?php _e('admin.news_management_btn'); ?>
                </a>
                <small class="text-muted"><?php _e('admin.news_management_desc'); ?></small>
              </div>
              <div class="col-md-3">
                <a href="?admin&stats" class="btn btn-default btn-block">
                  <i class="fa fa-bar-chart"></i> Gestion des statistiques
                </a>
                <small class="text-muted">Textes et messages des stats</small>
              </div>
              <div class="col-md-3">
                <a href="?admin&emails" class="btn btn-default btn-block">
                  <i class="fa fa-envelope"></i> Gestion des emails
                </a>
                <small class="text-muted">Liste de diffusion</small>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Sécurité -->
        <div class="panel panel-warning">
          <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-shield"></i> Sécurité</h3>
          </div>
          <div class="panel-body">
            <div class="row">
              <div class="col-md-6">
                <a href="?admin&mots" class="btn btn-warning btn-block">
                  <i class="fa fa-key"></i> Gestion des mots de passe
                </a>
                <small class="text-muted">Sécurité et accès</small>
              </div>
              <div class="col-md-6">
                <a href="?admin&bdd" class="btn btn-danger btn-block">
                  <i class="fa fa-database"></i> Gestion des BDD
                </a>
                <small class="text-muted">Création, sauvegarde, restauration</small>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Bouton retour -->
        <div class="row">
          <div class="col-md-12">
            <a href="?accueil" class="btn btn-default btn-block">
              <i class="fa fa-home"></i> Retour à l'accueil
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
