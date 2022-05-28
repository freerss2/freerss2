<?php
  /* - - - - - - - - - - - - *\
     Move watch:
     watch_id, delta
     if delta is empty, or
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

  // 2. Get arguments (watch_id=STR, delta=INT)
  // TODO: develop API args parser

  $watch_id = $_GET['watch_id'];
  if (! $watch_id) { echo "missing watch_id arg"; exit(1); }
  $delta     = $_GET['delta'];
  if (! $delta) { echo "missing delta arg"; exit(1); }

  $err = $rss_app->moveWatch($watch_id, $delta);
  echo $err;
?>
