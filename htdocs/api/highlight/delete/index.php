<?php
  /* - - - - - - - - - - - - *\
     Delete highlight:
     keyword
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

  // 2. Get arguments (keyword)
  // TODO: develop API args parser

  $keyword     = $_GET['keyword'];
  if (! $keyword) { echo "missing keyword arg"; exit(1); }

  $err = $rss_app->deleteHighlight($keyword);
  echo "$err";
?>
