<!-- CSS pour l'édition inline des traductions -->
<?php $base_path = $base_path ?? ''; ?>
<link rel="stylesheet" href="<?= $base_path ?>css/inline-translation.css">

<style>
  .navbar-brand {
    white-space: nowrap !important;
    overflow: visible !important;
    font-size: 14px !important;
  }

  .navbar-nav {
    white-space: nowrap !important;
    display: flex !important;
    flex-wrap: nowrap !important;
  }

  .navbar-nav>li {
    white-space: nowrap !important;
    flex-shrink: 0 !important;
  }

  .navbar-nav>li>a {
    white-space: nowrap !important;
    padding: 8px 4px !important;
    font-size: 12px !important;
  }

  .navbar {
    min-height: 32px !important;
  }

  .navbar-header {
    float: left !important;
  }

  .navbar-collapse {
    overflow: visible !important;
    max-height: none !important;
  }

  .navbar-nav {
    margin-top: 0 !important;
    margin-bottom: 0 !important;
  }

  .btn-sm {
    padding: 4px 8px !important;
    font-size: 11px !important;
  }

  .navbar-brand big {
    font-size: 16px !important;
  }

  .dropdown-menu {
    font-size: 12px !important;
  }

  .language-selector .btn {
    padding: 2px 4px !important;
    font-size: 9px !important;
    vertical-align: middle !important;
    margin-top: 0px !important;
    line-height: 1.2 !important;
  }

  .language-selector .btn i {
    font-size: 8px !important;
  }

  .language-selector {
    vertical-align: middle !important;
    margin-top: -2px !important;
  }
</style>

<div class="navbar navbar-default navbar-static-top">
  <div class="container">
    <div class="navbar-header">
      <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#navbar-ex-collapse">
        <span class="sr-only"><?php _e('common.toggle_navigation'); ?></span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
      </button>
      <a class="navbar-brand" href="?accueil" style="white-space: nowrap;">
        <span><big><?php _e('header.brand'); ?></big></span>
      </a>
    </div>
    <div class="collapse navbar-collapse" id="navbar-ex-collapse">
      <ul class="nav navbar-nav navbar-right">
        <!-- Dupli Studio -->
        <li>
          <a href="?studio" style="color: #4f6ef7;">
            <i class="fa fa-magic" style="margin-right: 5px;"></i>
            <strong>Studio</strong>
          </a>
        </li>
        <!-- Bibliothèque en haut -->
        <li>
          <a href="?bibliotheque">
            <i class="fa fa-book" style="margin-right: 5px;"></i>
            <strong><?php _e('header.library'); ?></strong>
          </a>
        </li>
        <li>
          <a href="?tirage_multimachines">
            <span class="glyphicon glyphicon-print" aria-hidden="true"></span>
            <?php _e('header.new_print'); ?>
          </a>
        </li>
        <li>
          <a href="?auto_tirage">
            <i class="fa fa-magic" style="margin-right: 5px;"></i>
            <?php _e('header.auto_tirage'); ?>
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

<!-- Auto-updater UI (Electron uniquement - ne s'active pas en mode PHP standalone) -->
<script src="<?= $base_path ?>js/updater-ui.js"></script>

<!-- JavaScript pour l'édition inline des traductions -->
<script src="<?= $base_path ?>js/inline-translation.js"></script>

<script>
// Lancer la maintenance en arrière-plan (indexation texte, etc.)
// Le script API gère lui-même un verrou de 5 minutes pour ne pas se lancer à chaque clic
fetch('?run_background_maintenance').catch(e => console.warn("Background maintenance error", e));
</script>
