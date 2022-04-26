<?php
  /* - - - - - - - - - - - - *\
     Generate article edit code
     by article ID
     /api/articles/edit/?item_id=article_id
  \* - - - - - - - - - - - - */

  session_start();

  if ( ! $_SESSION['user_id'] ) {
    echo "Error: not logged-in";
    exit(1);
  }

  define('SOURCE_LEVEL', 3);
  $INCLUDE_PATH = str_repeat('../', SOURCE_LEVEL) . 'php_lib';

  include "$INCLUDE_PATH/rss_app.php";

  $rss_app = new RssApp();

  // 1. Check login token
  // 1.1. Return message on error
  // TODO

  $user_id = $_SESSION['user_id'];
  $rss_app->setUserId($user_id);

  // 2. Get argument (item_id=STR)
  // TODO: develop API args parser

  $item_id     = $_GET['item_id'];
  if (! $item_id) {
    echo "missing item_id arg";
    exit(1);
  }

  $code = $rss_app->itemEditCode($item_id);
  echo $code;

?>
