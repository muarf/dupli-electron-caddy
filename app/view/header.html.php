
        

    <div class="navbar navbar-default navbar-static-top">
      <div class="container">
        <div class="navbar-header">
          <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#navbar-ex-collapse">
            <span class="sr-only"><?php _e('common.toggle_navigation'); ?></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </button>
          <a class="navbar-brand" href="?accueil" style="display: flex; align-items: center;">
            <button type="button" class="btn btn-default btn-sm" onclick="history.back()" style="margin-right: 10px; vertical-align: middle;">
              <span class="glyphicon glyphicon-chevron-left" aria-hidden="true"></span> <?php _e('header.previous'); ?>
            </button>
            <span><big><?php _e('header.brand'); ?></big></span>
          </a>
        </div>
        <div class="collapse navbar-collapse" id="navbar-ex-collapse">
          <ul class="nav navbar-nav navbar-right">
            <li class="dropdown">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
                <span class="glyphicon glyphicon-file" aria-hidden="true"></span>
                <?php _e('header.pdf_tools'); ?> <span class="caret"></span>
              </a>
              <ul class="dropdown-menu">
                <li>
                  <a href="?imposition">
                    <i class="fa fa-magic" style="color: #a8e6cf; margin-right: 8px;"></i>
                    <strong><?php _e('header.impose'); ?></strong>
                    <small class="text-muted d-block"><?php _e('header.impose_desc'); ?></small>
                  </a>
                </li>
                <li role="separator" class="divider"></li>
                <li>
                  <a href="?unimpose">
                    <i class="fa fa-undo" style="color: #ffb3ba; margin-right: 8px;"></i>
                    <strong><?php _e('header.unimpose'); ?></strong>
                    <small class="text-muted d-block"><?php _e('header.unimpose_desc'); ?></small>
                  </a>
                </li>
                <li role="separator" class="divider"></li>
                <li>
                  <a href="?imposition_tracts">
                    <i class="fa fa-copy" style="color: #ffd93d; margin-right: 8px;"></i>
                    <strong><?php _e('header.impose_tracts'); ?></strong>
                    <small class="text-muted d-block"><?php _e('header.impose_tracts_desc'); ?></small>
                  </a>
                </li>
                <li role="separator" class="divider"></li>
                <li>
                  <a href="?png_to_pdf">
                    <i class="fa fa-file-image-o" style="color: #a8e6cf; margin-right: 8px;"></i>
                    <strong><?php _e('header.images_to_pdf'); ?></strong>
                    <small class="text-muted d-block"><?php _e('header.images_to_pdf_desc'); ?></small>
                  </a>
                </li>
                <li>
                  <a href="?pdf_to_png">
                    <i class="fa fa-picture-o" style="color: #c3aed6; margin-right: 8px;"></i>
                    <strong><?php _e('header.pdf_to_images'); ?></strong>
                    <small class="text-muted d-block"><?php _e('header.pdf_to_images_desc'); ?></small>
                  </a>
                </li>
                <li role="separator" class="divider"></li>
                <li>
                  <a href="?riso_separator">
                    <i class="fa fa-adjust" style="color: #ff6b9d; margin-right: 8px;"></i>
                    <strong><?php _e('header.riso_separator'); ?></strong>
                    <small class="text-muted d-block"><?php _e('header.riso_separator_desc'); ?></small>
                  </a>
                </li>
                <li role="separator" class="divider"></li>
                <li>
                  <a href="?taux_remplissage">
                    <i class="fa fa-bar-chart" style="color: #84fab0; margin-right: 8px;"></i>
                    <strong><?php _e('header.fill_rate'); ?></strong>
                    <small class="text-muted d-block"><?php _e('header.fill_rate_desc'); ?></small>
                  </a>
                </li>
              </ul>
            </li>
            <li>
              <a href="?tirage_multimachines">
                <span class="glyphicon glyphicon-print" aria-hidden="true"></span>
                <?php _e('header.new_print'); ?>
              </a>
            </li>
            <li>
              <a href="?changement">
                <span class="glyphicon glyphicon-tint" aria-hidden="true"></span>
                <?php _e('header.change_report'); ?>
              </a>
            </li>
            <li>
              <a href="?aide_machines">
                <span class="glyphicon glyphicon-question-sign" aria-hidden="true"></span>
                <?php _e('header.help_tutorials'); ?>
              </a>
            </li>
            <li>
              <a href="?stats">
                <span class="glyphicon glyphicon-stats" aria-hidden="true"></span>
                <?php _e('header.statistics'); ?>
              </a>
            </li>
            <li>
              <a href="?admin"><?php _e('header.administration'); ?></a>
            </li>
            <li>
              <?php echo generateLanguageSelector(); ?>
            </li>
          </ul>
        </div>
      </div>
    </div>