<!-- CSS pour les accordéons Q&A -->
<style>
.qa-container {
    margin-bottom: 30px;
}

.machine-section {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    margin-bottom: 20px;
    overflow: hidden;
}

.machine-header {
    background: #007bff;
    color: white;
    padding: 20px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.machine-header:hover {
    background: #0056b3;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.machine-header h3 {
    margin: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.machine-header .fa {
    font-size: 1.5em;
    transition: transform 0.3s ease;
}

.machine-header.active .fa {
    transform: rotate(180deg);
}

.machine-content {
    display: none;
    padding: 0;
}

.machine-content.active {
    display: block;
}

.qa-item {
    border-bottom: 1px solid #e9ecef;
    background: white;
}

.qa-item:last-child {
    border-bottom: none;
}

.qa-question {
    padding: 20px;
    cursor: pointer;
    background: white;
    transition: background-color 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.qa-question:hover {
    background: #f8f9fa;
}

.qa-question h4 {
    margin: 0;
    color: #495057;
    font-weight: 600;
    flex: 1;
    margin-right: 15px;
}

.qa-question .fa {
    color: #6c757d;
    transition: transform 0.3s ease;
}

.qa-question.active .fa {
    transform: rotate(180deg);
    color: #007bff;
}

.qa-answer {
    display: none;
    padding: 0 20px 20px 20px;
    background: #f8f9fa;
    border-top: 1px solid #e9ecef;
}

.qa-answer.active {
    display: block;
}

.qa-answer-content {
    background: white;
    padding: 20px;
    border-radius: 6px;
    border-left: 4px solid #007bff;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.search-section {
    background: white;
    border-radius: 8px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.search-section h2 {
    color: #495057;
    margin-bottom: 20px;
    text-align: center;
}

.machine-selector {
    max-width: 400px;
    margin: 0 auto;
}

.no-qa {
    text-align: center;
    padding: 40px;
    color: #6c757d;
    font-style: italic;
}

.qa-count {
    background: #007bff;
    color: white;
    border-radius: 50%;
    width: 30px;
    height: 30px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9em;
    font-weight: bold;
    margin-left: 10px;
}

@media (max-width: 768px) {
    .machine-header h3 {
        font-size: 1.2em;
    }
    
    .qa-question h4 {
        font-size: 1em;
    }
    
    .qa-answer-content {
        padding: 15px;
    }
}
</style>

<div class="section">
  <div class="container">
    <div class="row">
      <div class="col-md-12">
        <h1 class="text-center">
          <i class="fa fa-question-circle" style="color: #007bff;"></i>
          Aide & Questions-Réponses des Machines
        </h1>
        <hr>
        
        <!-- Section de recherche -->
        <div class="search-section">
          <h2>
            <i class="fa fa-search"></i>
            Rechercher une machine
          </h2>
          
          <div class="machine-selector">
            <form method="GET" id="machine-search-form">
              <input type="hidden" name="aide_machines" value="1">
              <div class="form-group">
                <label for="machine-select">Sélectionner une machine :</label>
                <select class="form-control" id="machine-select" name="machine">
                  <option value="">-- Choisir une machine --</option>
                  <?php if(isset($all_machines) && !empty($all_machines)): ?>
                    <?php foreach($all_machines as $machine): ?>
                      <option value="<?= htmlspecialchars($machine) ?>" 
                              <?= (isset($machine_selectionnee) && $machine_selectionnee === $machine) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($machine) ?>
                      </option>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </select>
              </div>
              <div class="text-center">
                <button type="submit" class="btn btn-primary btn-lg">
                  <i class="fa fa-search"></i> Consulter les Q&A
                </button>
              </div>
            </form>
          </div>
        </div>
        
        <!-- Affichage des Q&A sélectionnées -->
        <?php if(isset($qa_selectionnees) && !empty($qa_selectionnees)): ?>
          <div class="qa-container">
            <div class="machine-section">
              <div class="machine-header active" onclick="toggleMachineContent('selected')">
                <h3>
                  <i class="fa fa-print"></i>
                  Questions-Réponses pour : <?= htmlspecialchars($machine_selectionnee) ?>
                  <span class="qa-count"><?= count($qa_selectionnees) ?></span>
                  <i class="fa fa-chevron-down"></i>
                </h3>
              </div>
              <div class="machine-content active" id="content-selected">
                <?php foreach($qa_selectionnees as $qa): ?>
                  <div class="qa-item">
                    <div class="qa-question" onclick="toggleQA('qa-<?= $qa['id'] ?>')">
                      <h4>
                        <i class="fa fa-question-circle"></i>
                        <?= htmlspecialchars($qa['question']) ?>
                      </h4>
                      <i class="fa fa-chevron-down"></i>
                    </div>
                    <div class="qa-answer" id="qa-<?= $qa['id'] ?>">
                      <div class="qa-answer-content">
                        <?= $qa['reponse'] ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        <?php elseif(isset($machine_selectionnee) && empty($qa_selectionnees)): ?>
          <div class="alert alert-warning text-center">
            <i class="fa fa-exclamation-triangle"></i>
            <strong>Aucune Q&A disponible</strong> pour la machine "<?= htmlspecialchars($machine_selectionnee) ?>".
            <br>
            <small>Contactez l'administrateur pour ajouter des questions-réponses pour cette machine.</small>
          </div>
        <?php endif; ?>
        
        <!-- Liste de toutes les Q&A disponibles -->
        <?php if(isset($qa_by_machine) && !empty($qa_by_machine)): ?>
          <div class="qa-container">
            <h2 class="text-center">
              <i class="fa fa-list"></i>
              Toutes les Questions-Réponses disponibles
            </h2>
            
            <?php foreach($qa_by_machine as $machine_name => $qa_list): ?>
              <div class="machine-section">
                <div class="machine-header" onclick="toggleMachineContent('<?= $machine_name ?>')">
                  <h3>
                    <i class="fa fa-print"></i>
                    <?= htmlspecialchars($machine_name) ?>
                    <span class="qa-count"><?= count($qa_list) ?></span>
                    <i class="fa fa-chevron-down"></i>
                  </h3>
                </div>
                <div class="machine-content" id="content-<?= $machine_name ?>">
                  <?php foreach($qa_list as $qa): ?>
                    <div class="qa-item">
                      <div class="qa-question" onclick="toggleQA('qa-<?= $qa['id'] ?>')">
                        <h4>
                          <i class="fa fa-question-circle"></i>
                          <?= htmlspecialchars($qa['question']) ?>
                        </h4>
                        <i class="fa fa-chevron-down"></i>
                      </div>
                      <div class="qa-answer" id="qa-<?= $qa['id'] ?>">
                        <div class="qa-answer-content">
                          <?= $qa['reponse'] ?>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="alert alert-info text-center">
            <i class="fa fa-info-circle"></i>
            Aucune question-réponse n'est encore disponible pour les machines.
            <br>
            <small>Contactez l'administrateur pour ajouter des informations d'aide.</small>
          </div>
        <?php endif; ?>
        
        <!-- Navigation -->
        <div class="row">
          <div class="col-md-12">
            <div class="panel panel-default">
              <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-arrow-left"></i> Navigation</h3>
              </div>
              <div class="panel-body">
                <a href="?accueil" class="btn btn-primary">
                  <i class="fa fa-arrow-left"></i> Retour à l'accueil
                </a>
                <a href="?devis" class="btn btn-success">
                  <i class="fa fa-calculator"></i> Faire un devis
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Fonction pour basculer l'affichage du contenu d'une machine
function toggleMachineContent(machineName) {
    var content = document.getElementById('content-' + machineName);
    var header = content.previousElementSibling;
    var icon = header.querySelector('.fa-chevron-down');
    
    if (content.classList.contains('active')) {
        content.classList.remove('active');
        header.classList.remove('active');
    } else {
        content.classList.add('active');
        header.classList.add('active');
    }
}

// Fonction pour basculer l'affichage d'une Q&A
function toggleQA(qaId) {
    var answer = document.getElementById(qaId);
    var question = answer.previousElementSibling;
    var icon = question.querySelector('.fa-chevron-down');
    
    if (answer.classList.contains('active')) {
        answer.classList.remove('active');
        question.classList.remove('active');
    } else {
        answer.classList.add('active');
        question.classList.add('active');
    }
}

// Auto-submit du formulaire de sélection
document.getElementById('machine-select').addEventListener('change', function() {
    if (this.value) {
        document.getElementById('machine-search-form').submit();
    }
});

// Animation pour les accordéons
$(document).ready(function() {
    // Animation smooth pour les accordéons
    $('.qa-question').click(function() {
        $(this).find('.fa-chevron-down').toggleClass('active');
    });
    
    $('.machine-header').click(function() {
        $(this).find('.fa-chevron-down').toggleClass('active');
    });
});
</script>