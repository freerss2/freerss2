<?php
  /* - - - - - - - - - - - - *\
     Save highlight:
     original_keyword, keyword, fg_color, bg_color, bold, italic, underscore
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

  $original_keyword     = $_GET['original_keyword'];
  if (! $original_keyword) { echo "missing original_keyword arg"; exit(1); }

  $keyword     = $_GET['keyword'];
  $fg_color    = $_GET['fg_color'];
  $bg_color    = $_GET['bg_color'];
  $bold        = $_GET['bold'];
  $italic      = $_GET['italic'];
  $underscore  = $_GET['underscore'];

  $err = $rss_app->saveHighlight($original_keyword, $keyword, $fg_color, $bg_color, $bold, $italic, $underscore);
  echo "$err";
?>
