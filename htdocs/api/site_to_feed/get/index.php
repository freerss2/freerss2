<?php
  /* - - - - - - - - - - - - *\
     Get info about site-to-feed:
     feed_id
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

  $feed_id     = $_GET['feed_id'];
  if (! $feed_id) { echo "missing feed_id arg"; exit(1); }

  $rec = $rss_app->getSiteToFeed($feed_id);
  echo json_encode($rec);
?>
