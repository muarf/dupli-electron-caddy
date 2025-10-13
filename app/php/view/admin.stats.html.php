<!-- Quill.js CSS -->
<link href="js/quill/quill.snow.css" rel="stylesheet">
<!-- Quill.js JS -->
<script src="js/quill/quill.min.js"></script>

<div class="section">
  <div class="container">
    <div class="row">
      <div class="col-md-10 col-md-offset-1">
        <h1 class="text-center">Gestion des Statistiques</h1>
        <hr>
        
        <!-- Section Texte d'introduction des statistiques -->
        <div class="row">
          <div class="col-md-12">
            <div class="panel panel-info">
              <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-bar-chart"></i> Texte d'introduction des statistiques</h3>
              </div>
              <div class="panel-body">
                <?php if(isset($array['stats_text_updated'])): ?>
                  <div class="alert alert-success">
                    <strong>Succès!</strong> Le texte d'introduction des statistiques a été mis à jour.
                  </div>
                <?php endif; ?>
                
                <p>Modifiez le texte d'introduction qui apparaît sur la page des statistiques. Vous pouvez utiliser les variables suivantes :</p>
                
                <div class="alert alert-info">
                  <strong>Variables disponibles :</strong><br>
                  <code>{nb_f}</code> - Nombre total de feuilles<br>
                  <code>{nb_t}</code> - Nombre total de tirages<br>
                  <code>{nb_t_par_mois}</code> - Nombre de tirages par mois<br>
                  <code>{nbf_par_mois}</code> - Nombre de feuilles par mois<br>
                  <code>{nb_moy_par_mois}</code> - Nombre moyen de copies par tirage<br>
                  <code>{ca}</code> - Chiffre d'affaire total<br>
                  <code>{ca2}</code> - Chiffre d'affaire déclaré payé<br>
                  <code>{ca1}</code> - Chiffre d'affaire CB payé<br>
                  <code>{doit}</code> - Montant dû<br>
                  <code>{benf}</code> - Bénéfice
                </div>
                
                <?php 
                $default_text = 'Depuis le debut de l\'aventure dupli en 2011, nous avons tire un total de {nb_f} pages en plus de {nb_t} fois. Si l\'on regarde de plus pret ca nous fait une moyenne de {nb_t_par_mois} tirages par mois, avec environ {nbf_par_mois} feuilles en moyenne. Vous tirez {nb_moy_par_mois} copies par tirage. Je ne vous epargne pas le chiffre d\'affaire : {ca} euros depuis le debut, si l\'on enleve les {doit} euros que l\'on nous doit : {ca2} euros. Vous nous avez donne {ca1} euros. Nous sommes donc {benf} euros, mais c\'est sans compter le prix du loyer des condos qui a raison de 50 euros par mois nous ferai... ! Le big data est la, je vous laisse regarder :) ps : et c\'est aussi 1800 lignes de code !';
                $current_text = isset($stats_intro_text) ? $stats_intro_text : $default_text;
                ?>
                
                <form method="post" id="stats-form">
                  <input type="hidden" name="stats_intro_text" id="stats_intro_text_hidden" value="">
                  <div class="form-group">
                    <label for="stats_editor">Texte d'introduction des statistiques :</label>
                    <div id="stats_editor" style="height: 300px; margin-bottom: 10px;"><?= $current_text ?></div>
                  </div>
                  
                  <button type="submit" name="update_stats_text" class="btn btn-info btn-block">
                    <i class="fa fa-save"></i> Sauvegarder le texte des statistiques
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
                <h3 class="panel-title"><i class="fa fa-arrow-left"></i> Navigation</h3>
              </div>
              <div class="panel-body">
                <a href="?admin" class="btn btn-primary">
                  <i class="fa fa-arrow-left"></i> Retour à l'administration
                </a>
                <a href="?stats" class="btn btn-info" target="_blank">
                  <i class="fa fa-external-link"></i> Voir la page des statistiques
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
      placeholder: 'Entrez le texte d\'introduction des statistiques...'
  });
  
  // Mettre à jour le champ caché avant soumission
  document.getElementById('stats-form').addEventListener('submit', function() {
      var content = quillStats.root.innerHTML;
      document.getElementById('stats_intro_text_hidden').value = content;
  });
  </script>
</div>
