<!DOCTYPE html>
<html lang="fr">
    <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php _e("auto_clean.base_html_php_1", [], false); ?></title>
    <?php $base_path = $base_path ?? ''; ?>
    <script type="text/javascript" src="<?= $base_path ?>js/jquery.min.js"></script>
    <script type="text/javascript" src="<?= $base_path ?>js/bootstrap.min.js"></script>
    <script type="text/javascript" src="<?= $base_path ?>js/calcul.js"></script>
    <!-- Lazy Loading pour optimiser les images -->
    <script type="text/javascript" src="<?= $base_path ?>js/lazy-loading.js"></script>
    <!-- Preload critique - seulement la police principale utilisée immédiatement -->
    <link rel="preload" href="<?= $base_path ?>fonts/fontawesome-webfont.woff2" as="font" type="font/woff2" crossorigin="anonymous" media="all">
    <!-- Preload du CSS critique -->
    <link rel="preload" href="<?= $base_path ?>css/bootstrap.css" as="style">
    <link rel="preload" href="<?= $base_path ?>css/font-awesome.min.css" as="style">
    
    <!-- CSS critique bloquant pour éviter le FOUC -->
    <link href="<?= $base_path ?>css/bootstrap.css" rel="stylesheet" type="text/css">
    
    <!-- CSS non-bloquant pour les ressources non-critiques -->
    <link href="<?= $base_path ?>css/font-awesome.min.css" rel="stylesheet" type="text/css" media="print" onload="this.media='all'">
    <noscript>
        <link href="<?= $base_path ?>css/font-awesome.min.css" rel="stylesheet" type="text/css">
    </noscript>
    <script>
      $(document).ready(function(){
        $('[data-toggle="tooltip"]').tooltip(); 
        // S'assurer que les dropdowns fonctionnent sur toutes les pages
        $('.dropdown-toggle').dropdown();
      });
    </script>

  </head>
  <body style="padding-bottom: 60px;">

<?= $header  ?>
 <div class="section">
      <div <?php if(!isset($_GET['admin'])){ ?> class="container-fluid" <?php } ?> >
<?= $content ?>
</div></div></div>
<?= $footer ?>
</body>
</html>
