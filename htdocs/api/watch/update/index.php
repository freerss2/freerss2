<?php
  /* - - - - - - - - - - - - *\
     Rename watch:
     watch_id, name
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

  $user_id = $_SESSION['user_id'] ?? Null;
  $rss_app->setUserId($user_id);

  // 2. Get arguments (watch_id=STR, name=STR)
  // TODO: develop API args parser

  $watch_id = $_GET['watch_id'] ?? Null; if (! $watch_id) { echo "Error: missing watch_id arg"; exit(1); }
  $name     = $_GET['name']     ?? Null; if (! $name)     { echo "Error: missing name arg"; exit(1); }

  $err = $rss_app->saveWatchName($watch_id, $name);
  echo $err;
?>
