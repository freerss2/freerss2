<!--/ Project:   freerss        \--
  --| Author:    Felix Liberman |--
  --| Subsystem: UI             |--
  --\ Function:  login.connect  /-->

<?php
  define('SOURCE_LEVEL', 1);
  $INCLUDE_PATH = str_repeat('../', SOURCE_LEVEL) . 'php_lib';

  include "$INCLUDE_PATH/rss_app.php";
?>

<!doctype html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap CSS -->
    <link href="../style/bootstrap_5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">

    <!-- Fontawesome -->
    <link rel="stylesheet" href="../style/fontawesome/css/all.css">
    <link rel="icon" href="/img/favicon.ico">

    <!-- App styles -->
    <link rel="stylesheet" href="../style/login.css<?php echo $VER_SUFFIX;?>">

    <title>Free RSS</title>

  <style>
    body::after {
  content: "";
  background: url(../img/rss_bg4_sm.png);
  opacity: 0.3;
  top: 0;
  left: 0;
  bottom: 0;
  right: 0;
  position: absolute;
  z-index: -1;   
  background-size: 200px;
}
  </style>

  </head>
  <body onload="checkAuthToken();">

    <!-- Optional JavaScript; choose one of the two! -->

    <!-- Option 1: Bootstrap Bundle with Popper -->
    <script src="../style/bootstrap_5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>

    <!-- Option 2: Separate Popper and Bootstrap JS -->
    <!--
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js" integrity="sha384-7+zCNj/IqJ95wo16oMtfsKbZ9ccEh31eOz1HGyDuCQ6wgnyJNSYdrPa03rtR1zdB" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js" integrity="sha384-QJHtvGhmr9XOIpI6YVutG+2QOK9T+ZnN4kzFN1RtK3zEFEIsxhlmWl5/YESvpZ13" crossorigin="anonymous"></script>
    -->


    <script src="../script/service.js<?php echo $VER_SUFFIX;?>" ></script>
    <script src="../script/md5.js<?php echo $VER_SUFFIX;?>" ></script>
    <script src="../script/login.js<?php echo $VER_SUFFIX;?>" ></script>


<main class="form-signin">

    <div class="container alert alert-secondary" style="padding-right: 0;">
      <center>
        <h1 class="h3 mb3 fw-normal" >Free RSS 2.0 </h1>
        <h1 class="h3 mb-3 fw-normal">Auto Sign-in</h1>
        <img src="../img/load_rotating.gif" />
      </center>
    </div>
    <button class="w-100 btn btn-lg btn-primary" onclick="window.location.href = '/login';">Abort</button>

</main>

  </body>
</html>

