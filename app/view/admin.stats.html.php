<!-- Quill.js CSS -->
<link href="js/quill/quill.snow.css" rel="stylesheet">
<!-- Quill.js JS -->
<script src="js/quill/quill.min.js"></script>

<div class="section">
  <div class="container">
    <div class="row">
      <div class="col-md-10 col-md-offset-1">
        <h1 class="text-center"><?php _e('admin.stats_management'); ?></h1>
        <hr>
        
        <!-- Section Texte d'introduction des statistiques -->
        <div class="row">
          <div class="col-md-12">
            <div class="panel panel-info">
              <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-bar-chart"></i> <?php _e('admin.stats_management_desc'); ?></h3>
              </div>
              <div class="panel-body">
                <?php if(isset($array['stats_text_updated'])): ?>
                  <div class="alert alert-success">
                    <strong><?php _e('common.success'); ?>!</strong> <?php _e('admin_stats.text_updated'); ?>
                  </div>
                <?php endif; ?>
                
                <p><?php _e('admin_stats.modify_text_desc'); ?></p>
                
                <div class="alert alert-info">
                  <strong><?php _e('admin_stats.available_variables'); ?></strong><br>
                  <code>{nb_f}</code> - <?php _e('admin_stats.var_total_sheets'); ?><br>
                  <code>{nb_t}</code> - <?php _e('admin_stats.var_total_prints'); ?><br>
                  <code>{nb_t_par_mois}</code> - <?php _e('admin_stats.var_prints_per_month'); ?><br>
                  <code>{nbf_par_mois}</code> - <?php _e('admin_stats.var_sheets_per_month'); ?><br>
                  <code>{nb_moy_par_mois}</code> - <?php _e('admin_stats.var_avg_copies'); ?><br>
                  <code>{ca}</code> - <?php _e('admin_stats.var_total_ca'); ?><br>
                  <code>{ca2}</code> - <?php _e('admin_stats.var_paid_ca'); ?><br>
                  <code>{ca1}</code> - <?php _e('admin_stats.var_cb_ca'); ?><br>
                  <code>{doit}</code> - <?php _e('admin_stats.var_due_amount'); ?><br>
                  <code>{benf}</code> - <?php _e('admin_stats.var_profit'); ?>
                </div>
                
                <?php 
                $default_text = __('admin_stats.placeholder');
                $current_text = isset($stats_intro_text) ? $stats_intro_text : $default_text;
                ?>
                
                <form method="post" id="stats-form">
                  <input type="hidden" name="stats_intro_text" id="stats_intro_text_hidden" value="">
                  <div class="form-group">
                    <label for="stats_editor"><?php _e('admin_stats.intro_text_label'); ?></label>
                    <div id="stats_editor" style="height: 300px; margin-bottom: 10px;"><?= $current_text ?></div>
                  </div>
                  
                  <button type="submit" name="update_stats_text" class="btn btn-info btn-block">
                    <i class="fa fa-save"></i> <?php _e('admin_stats.save_btn'); ?>
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Section Navigation -->
        <div class="row">
          <div class="col-md-12">
            <div class="panel panel-default">
              <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-arrow-left"></i> <?php _e('admin_machines.back_to_admin'); ?></h3>
              </div>
              <div class="panel-body">
                <a href="?admin" class="btn btn-primary">
                  <i class="fa fa-arrow-left"></i> <?php _e('admin_machines.back_to_admin'); ?>
                </a>
                <a href="?stats" class="btn btn-info" target="_blank">
                  <i class="fa fa-external-link"></i> <?php _e('admin_stats.view_page'); ?>
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  
  <script>
  // Initialiser Quill.js pour les statistiques
  var quillStats = new Quill('#stats_editor', {
      theme: 'snow',
      modules: {
          toolbar: [
              [{ 'header': [1, 2, 3, false] }],
              ['bold', 'italic', 'underline', 'strike'],
              [{ 'color': [] }, { 'background': [] }],
              [{ 'list': 'ordered'}, { 'list': 'bullet' }],
              [{ 'align': [] }],
              ['link', 'image'],
              ['clean']
          ]
      },
      placeholder:  "<?php echo __('admin_stats.placeholder'); ?>" 
  });
  
  // Mettre à jour le champ caché avant soumission
  document.getElementById('stats-form').addEventListener('submit', function() {
      var content = quillStats.root.innerHTML;
      document.getElementById('stats_intro_text_hidden').value = content;
  });
  </script>
</div>
