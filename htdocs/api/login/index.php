<?php
  /* - - - - - - - - - - - - *\
     Login:
     first and second stage
     On 2nd stage return either
     Error: <ERROR>
       or
     Location: <new location>
  \* - - - - - - - - - - - - */
  session_start();

  define('SOURCE_LEVEL', 2);
  $INCLUDE_PATH = str_repeat('../', SOURCE_LEVEL) . 'php_lib';
  include "$INCLUDE_PATH/rss_app.php";

  $rss_app = new RssApp();

  $function = $_GET['function'];
  if (! $function) {
    echo "Error: missing function argument";
    exit(1);
  }

  if ($function == 'check_auth_token') {
    $err = $rss_app->check_auth_token($_GET['auth_token'], $_SERVER['REMOTE_ADDR']);
    if  ( $err ) {
      echo "Error: $err";
      exit(1);
    }
    // imitate login completion
    $rss_app->setUserId($_SESSION['user_id']);
    // read from session last redirect url
    $last_page = $rss_app->getPersonalSetting('last_page');
    echo "Location:  $last_page";
    exit(0);
  }
  // get login, fetch record (if exist)
  $login = $_GET['login'];
  if (! $login) {
    echo "Error: missing login argument";
    exit(1);
  }

  if ($function == 'first_stage') {

    // generate temporary key and create login session record:
    $temp_key = $rss_app->loginStage1($login);
    // return back temp_key
    echo $temp_key;
    exit(0);
  }

  if ($function == 'second_stage') {
    // get salted password
    $gui_password = $_GET['password'];
    if (! $gui_password) {
      echo "Error: missing password argument";
      exit(1);
    }
    $login_success = $rss_app->loginStage2($login, $gui_password);
    if ($login_success) {
      //echo "old user_id=".$_SESSION['user_id']."<BR>\n";
      $_SESSION['user_id'] = $rss_app->getUserId();
      //echo "new user_id=".$_SESSION['user_id']."<BR>\n";
    } else {
      echo "Error: login failed";
      exit(1);
    }
    // get auth token
    $auth_token = $rss_app->get_auth_token($_SESSION['user_id'], '');
    // return redirect URL with this token
    echo "Location: /personal/?auth_token=$auth_token";
    exit(0);
  }

  echo "Error: unsupported function";
  exit(1);
?>
