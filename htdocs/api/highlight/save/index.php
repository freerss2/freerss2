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

  $user_id = $_SESSION['user_id'] ?? Null;
  $rss_app->setUserId($user_id);

  // 2. Get arguments (keyword)
  // TODO: develop API args parser

  $original_keyword = $_GET['original_keyword'] ?? Null;
  $keyword          = $_GET['keyword']          ?? Null;
  $fg_color         = $_GET['fg_color']         ?? Null;
  $bg_color         = $_GET['bg_color']         ?? Null;
  $bold             = $_GET['bold']             ?? Null;
  $italic           = $_GET['italic']           ?? Null;
  $underscore       = $_GET['underscore']       ?? Null;

  if (! $keyword) { echo "missing keyword arg"; exit(1); }

  $err = $rss_app->saveHighlight($original_keyword, $keyword, $fg_color, $bg_color, $bold, $italic, $underscore);
  echo "$err";
?>
