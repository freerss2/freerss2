<!--/ Project:   freerss        \--
  --| Author:    Felix Liberman |--
  --| Subsystem: UI             |--
  --| Function:  login          |--
  --\ Function:  login          /-->

<?php
  session_start();

  //  $_SESSION['user_id'] = 1;  ### HARDCODED FOR TESTING
  define('SOURCE_LEVEL', 1);
  $INCLUDE_PATH = str_repeat('../', SOURCE_LEVEL) . 'php_lib';

  include "$INCLUDE_PATH/rss_app.php";


if ($_GET['logout']) {
    $_SESSION = array();
}

# Generate login prompt page

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
  <body>

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
      <div class="row">
        <h1 class="h3 col-7 fw-normal" >Free RSS 2.0 </h1>
        <div class="col" style="text-align: right; margin-right:4px;">
          <a href="../help/#about" class="btn btn-dark btn-sm low-action-button">
             <i class="fab fa-readme"></i>&nbsp;About
          </a>
        </div>
      </div>
      <h1 class="h3 mb-3 fw-normal">Please sign in</h1>

    </div>


    <div class="form-floating">
      <input type="email" class="form-control" id="login_email" placeholder="name@example.com">
      <label for="login_email">Email address</label>
    </div>
    <div class="form-floating">
      <input type="password" class="form-control" id="login_password" placeholder="Password">
      <label for="login_password">Password</label>
    </div>
    <button class="w-100 btn btn-lg btn-primary" onclick="signIn()">Sign in</button>
  <div >
    <p class="mt-5 mb-3 text-muted alert alert-secondary">No account? Create it!</p>
    <div class="form-floating">
      <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com">
      <label for="email">Email address</label>
    </div>
    <div class="form-floating">
      <input type="text" class="form-control" id="name" name="name" placeholder="Name">
      <label for="name">Name</label>
    </div>
    <div class="form-check alert alert-secondary">
      <input class="form-check-input" type="checkbox" style="margin-left:1px;margin-right:6px;" value="" id="no_mail_send">
      <label class="form-check-label" style="display:inline;" for="no_mail_send">
      Do not send password by email<BR>(I'm aware of potential security issues)
      </label>
    </div>
    <button class="w-100 btn btn-lg btn-primary" onclick="createAccount()">Submit</button>

  </div>

</main>

    <div class="modal fade" id="infoDialog" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Login Information</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p id="infoDialogContent"></p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="window.location.href='/'">Close</button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="errorDialog" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Sorry :-(</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p id="errorDialogContent"></p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="window.location.href='/'">Close</button>
          </div>
        </div>
      </div>
    </div>


  </body>
</html>

