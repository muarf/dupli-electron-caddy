<div class="section">
  <div class="container">
    <div class="row">
      <div class="col-md-12">
        <h1 class="text-center"><i class="fa fa-print"></i> <?php _e('admin_imprimantes.title', [], false); ?></h1>
        <hr>
        
        <!-- Statut du moniteur -->
        <div class="panel panel-info" id="monitor-status-panel">
          <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-info-circle"></i> <?php _e('admin_imprimantes.status_title', [], false); ?></h3>
          </div>
          <div class="panel-body">
            <div id="monitor-status">
              <p><i class="fa fa-spinner fa-spin"></i> <?php _e('admin_imprimantes.checking_status', [], false); ?></p>
            </div>
            <div id="monitor-actions" style="margin-top: 15px;">
              <button class="btn btn-success" id="btn-start-monitor" onclick="toggleMonitor(true)" style="display: none;">
                <i class="fa fa-play"></i> <?php _e('admin_imprimantes.start_monitor', [], false); ?>
              </button>
              <button class="btn btn-warning" id="btn-stop-monitor" onclick="toggleMonitor(false)" style="display: none;">
                <i class="fa fa-stop"></i> <?php _e('admin_imprimantes.stop_monitor', [], false); ?>
              </button>
              <button class="btn btn-info" onclick="refreshStatus()">
                <i class="fa fa-refresh"></i> <?php _e('admin_imprimantes.refresh', [], false); ?>
              </button>
            </div>
          </div>
        </div>
        
        <!-- Liste des imprimantes -->
        <div class="panel panel-default">
          <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-list"></i> <?php _e('admin_imprimantes.available_printers', [], false); ?></h3>
          </div>
          <div class="panel-body">
            <div id="printers-list">
              <p><i class="fa fa-spinner fa-spin"></i> <?php _e('admin_imprimantes.loading_printers', [], false); ?></p>
            </div>
          </div>
        </div>
        
        <!-- Statistiques -->
        <div class="panel panel-success">
          <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-bar-chart"></i> <?php _e('admin_imprimantes.stats_title', [], false); ?></h3>
          </div>
          <div class="panel-body">
            <div id="stats-container">
              <p><i class="fa fa-spinner fa-spin"></i> <?php _e('admin_imprimantes.loading_stats', [], false); ?></p>
            </div>
          </div>
        </div>
        
        <!-- Liste des impressions récentes -->
        <div class="panel panel-primary">
          <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-history"></i> <?php _e('admin_imprimantes.recent_jobs', [], false); ?></h3>
          </div>
          <div class="panel-body">
            <div id="print-jobs-list">
              <p><i class="fa fa-spinner fa-spin"></i> <?php _e('admin_imprimantes.loading_jobs', [], false); ?></p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="js/admin-imprimantes-list.js" defer></script>
