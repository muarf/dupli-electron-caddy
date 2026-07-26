<div class="section">
  <div class="container">
    <div class="row">
      <div class="col-md-12">
        <h1 class="text-center"><i class="fa fa-print"></i> Gestion du Moniteur d'Imprimantes</h1>
        <hr>
        
        <!-- Statut du moniteur -->
        <div class="panel panel-info" id="monitor-status-panel">
          <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-info-circle"></i> Statut du Moniteur</h3>
          </div>
          <div class="panel-body">
            <div id="monitor-status">
              <p><i class="fa fa-spinner fa-spin"></i> Vérification du statut...</p>
            </div>
            <div id="monitor-actions" style="margin-top: 15px;">
              <button class="btn btn-success" id="btn-start-monitor" onclick="toggleMonitor(true)" style="display: none;">
                <i class="fa fa-play"></i> Démarrer le moniteur
              </button>
              <button class="btn btn-warning" id="btn-stop-monitor" onclick="toggleMonitor(false)" style="display: none;">
                <i class="fa fa-stop"></i> Arrêter le moniteur
              </button>
              <button class="btn btn-info" onclick="refreshStatus()">
                <i class="fa fa-refresh"></i> Actualiser
              </button>
            </div>
          </div>
        </div>
        
        <!-- Liste des imprimantes -->
        <div class="panel panel-default">
          <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-list"></i> Imprimantes Disponibles</h3>
          </div>
          <div class="panel-body">
            <div id="printers-list">
              <p><i class="fa fa-spinner fa-spin"></i> Chargement des imprimantes...</p>
            </div>
          </div>
        </div>
        
        <!-- Statistiques -->
        <div class="panel panel-success">
          <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-bar-chart"></i> Statistiques d'Impression</h3>
          </div>
          <div class="panel-body">
            <div id="stats-container">
              <p><i class="fa fa-spinner fa-spin"></i> Chargement des statistiques...</p>
            </div>
          </div>
        </div>
        
        <!-- Liste des impressions récentes -->
        <div class="panel panel-primary">
          <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-history"></i> Impressions Récentes</h3>
          </div>
          <div class="panel-body">
            <div id="print-jobs-list">
              <p><i class="fa fa-spinner fa-spin"></i> Chargement des impressions...</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="js/admin-imprimantes-list.js" defer></script>
