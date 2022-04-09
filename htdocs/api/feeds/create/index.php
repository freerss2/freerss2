<?php
  /* - - - - - - - - - - - - *\
     Create feed
     by XMLURL
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

  // 2. Get argument (xml_url=STR)
  // TODO: develop API args parser

  $xml_url = $_GET['xml_url'];
  if (! $xml_url) { echo "missing xml_url arg"; exit(1); }
  $title = $_GET['title'];
  if (! $title) { echo "missing title arg"; exit(1); }
  $group = $_GET['group'];
  if (! $group) { echo "missing group arg"; exit(1); }

  list ($error, $feed_id, $title) = $rss_app->createFeed($xml_url, $title, $group);
  if ($error) {
    echo "ERROR: $error";
    exit(1);
  }
  echo "Created: $feed_id\nTitle: $title\n";


?>
