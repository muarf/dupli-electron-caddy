
            <h1 class="text-center">Bienvenue duplicateur-euse</h1>
            <hr>

          </div>
        </div>
      </div>
    </div>
    <div class="section">
      <div class="container">
        <div class="row">
          <div class="col-md-8 col-md-offset-2 text-center">
            <a href="?tirage_multimachines" style="text-decoration:none">
              <div class="well" style="padding: 40px; margin: 20px 0; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border: 2px solid #007bff; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,123,255,0.1); transition: all 0.3s ease;">
                <div style="font-size: 48px; color: #007bff; margin-bottom: 20px;">
                  <i class="fa fa-print"></i>
                </div>
                <h2 style="color: #007bff; margin-bottom: 15px; font-weight: bold;">Tirage Multi-Machines</h2>
                <p style="font-size: 16px; color: #6c757d; margin-bottom: 0;">Enregistre ton tirage sur toutes les machines disponibles et optimise tes impressions !</p>
              </div>
            </a>
          </div>
        </div>
      </div>
    </div>
    <div class="section">
      <div class="container">
        <div class="row">
          <div class="col-md-12" id="info">
            <h1 class="text-center">Informations utiles</h1>
            <hr>
            <?php 
            if (isset($news) && is_array($news)) {
                for ($i = 0;$i < count($news);$i++) 
                {?>
              <div class="well">
                <h3><?= $news[$i]['titre'] ?></h3>
                <div class="text-muted text-right"><small><?= $news[$i]['time'] ?></small></div>
                <div class="news-content"><?= html_entity_decode($news[$i]['news']) ?></div>
              </div>
            <?php 
                }
            }  ?>
          </div>
        </div>
      </div>
    </div>

    <?php if(isset($show_mailing_list) && $show_mailing_list == '1'): ?>
    <div class="section">
      <div class="container">
        <div class="row">
          <div class="col-md-12" id="diffusion">
            <h1 class="text-center">S'inscrire à la liste de Diffusion</h1>
          </div>
        </div>
        <div class="row">
          <div class="col-md-offset-3 col-md-6">
              <?php if(isset($_POST['email'])){ echo $email;}else {?>
            <form role="form" action="#diffusion"method="post">
                
              <div class="form-group">
                <div class="input-group">
                    
                  <input type="email" name = "email" class="form-control" placeholder="email">
                  <span class="input-group-btn">
                    <input class="btn btn-success" type="submit">
                  </span>
                </div>
              </div>
            </form><?php } ?>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

