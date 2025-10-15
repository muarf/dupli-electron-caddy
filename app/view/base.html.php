<!DOCTYPE html>
<html lang="fr">
    <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script type="text/javascript" src="js/jquery.min.js"></script>
    <script type="text/javascript" src="js/bootstrap.min.js"></script>
    <script type="text/javascript" src="js/calcul.js"></script>
    <!-- Lazy Loading pour optimiser les images -->
    <script type="text/javascript" src="js/lazy-loading.js"></script>
    <!-- Preload critique - seulement la police principale utilisée immédiatement -->
    <link rel="preload" href="fonts/fontawesome-webfont.woff2" as="font" type="font/woff2" crossorigin="anonymous" media="all">
    <!-- Preload du CSS critique -->
    <link rel="preload" href="css/bootstrap.css" as="style">
    <link rel="preload" href="css/font-awesome.min.css" as="style">
    
    <!-- CSS non-bloquant avec media="print" puis activation JS -->
    <link href="css/font-awesome.min.css" rel="stylesheet" type="text/css" media="print" onload="this.media='all'">
    <link href="css/bootstrap.css" rel="stylesheet" type="text/css" media="print" onload="this.media='all'">
    <noscript>
        <link href="css/font-awesome.min.css" rel="stylesheet" type="text/css">
        <link href="css/bootstrap.css" rel="stylesheet" type="text/css">
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