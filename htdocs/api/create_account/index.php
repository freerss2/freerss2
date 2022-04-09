<?php
  /* - - - - - - - - - - - - *\
     Create account
     using registerNewUser
  \* - - - - - - - - - - - - */
  session_start();

  define('SOURCE_LEVEL', 2);
  $INCLUDE_PATH = str_repeat('../', SOURCE_LEVEL) . 'php_lib';

  include "$INCLUDE_PATH/rss_app.php";
  include "$INCLUDE_PATH/smtp.php";

  $rss_app = new RssApp();

  $email = $_GET['email'];
  if (! $email) {
    echo "Error: missing email argument";
    exit(1);
  }
  $name = $_GET['name'];

  $result = $rss_app->registerNewUser($email, $name);

  if (substr($result, 0, 5) === "Error") {
    echo $result;
    exit(1);
  } 

  if ($_GET['no_mail_send']) {
    echo $result;
  } else {
    sendMail( $smtp_conf, "noreply@free.rss2", $email, "Service Free RSS 2 registration", $result );  
    echo "Password sent to $email (it can reach \"spam\" folder)";
  }

?>
