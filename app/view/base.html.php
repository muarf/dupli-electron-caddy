<!DOCTYPE html>
<html lang="fr">
    <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script type="text/javascript" src="js/jquery.min.js"></script>
    <script type="text/javascript" src="js/bootstrap.min.js"></script>
    <script type="text/javascript" src="js/calcul.js"></script>
    <link href="css/font-awesome.min.css"
    rel="stylesheet" type="text/css">
    <link href="css/bootstrap.css"
    rel="stylesheet" type="text/css">
    <script>
      $(document).ready(function(){
        $('[data-toggle="tooltip"]').tooltip(); 
      });
    </script>
    <!-- <script type="text/javascript">
    function test() {
        var machineValue = document.getElementById('machine1').value;

        if (machineValue == 'A4' || machineValue == 'A3') {
            document.getElementById('reveal').setAttribute('style', 'visibility: visible;');
            document.getElementById('nb_m').required = true;
        } else {
            document.getElementById('reveal').setAttribute('style', 'visibility: hidden;');
        }

        if (machineValue == 'pA4' || machineValue == 'pA3') {
            document.getElementById('reveal1').setAttribute('style', 'visibility: visible;');
            document.getElementById('couleur').required = true;
        } else {
            document.getElementById('reveal1').setAttribute('style', 'visibility: hidden;');
        }
    }
</script>

 -->
  </head>
  <body>

<?= $header  ?>
 <div class="section">
      <div <?php if(!isset($_GET['admin'])){ ?> class="container" <?php } ?> >
<?= $content ?>
</div></div></div>
<?= $footer ?>
</body>
</html>