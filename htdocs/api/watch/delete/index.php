<?php
  /* - - - - - - - - - - - - *\
     Delete watch:
     watch_id
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

  // 2. Get arguments (feed_id=STR, enable=1/0, xml_url=STR, title=STR, action=delete)
  // TODO: develop API args parser

  $watch_id = $_GET['watch_id'] ?? Null; if (! $watch_id) { echo "missing watch_id arg"; exit(1); }

  $err = $rss_app->deleteWatch($watch_id);
  echo $err;
?>
