<?php
  /* - - - - - - - - - - - - *\
     Create watch:
     name
     if name is empty, used or
     incorrect - return error
  \* - - - - - - - - - - - - */
  session_start();

  define('SOURCE_LEVEL', 3);
  $INCLUDE_PATH = str_repeat('../', SOURCE_LEVEL) . 'php_lib';

  include "$INCLUDE_PATH/rss_app.php";

  $rss_app = new RssApp();

  // 1. Check login token
  // 1.1. Return message on error
  // TODO

  $user_id = $_SESSION['user_id'];
  $rss_app->setUserId($user_id);

  // 2. Get arguments (feed_id=STR, enable=1/0, xml_url=STR, title=STR, action=delete)
  // TODO: develop API args parser

  $name     = $_GET['name'];
  if (! $name) { echo "missing name arg"; exit(1); }

  $err = $rss_app->createWatch($name);
  echo $err;
?>
