<?php
  /* - - - - - - - - - - - - *\
     Create feed
     by xml_url, title and group
     if passed input_type_rss=false, consider xml_url as site URL
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

  // 2. Get argument (xml_url=STR)
  // TODO: develop API args parser

  $xml_url = $_GET['xml_url'] ?? Null; if (! $xml_url) { echo "missing xml_url arg"; exit(1); }
  $title   = $_GET['title']   ?? Null; if (! $title)   { echo "missing title arg"; exit(1); }
  $group   = $_GET['group']   ?? Null; if (! $group)   { echo "missing group arg"; exit(1); }

  $input_type_rss = $_GET['input_type_rss'] ?? Null;
  $input_type_rss = $input_type_rss = 'true' || $input_type_rss = 1;
  $source_type    = $_GET['source_type'] ?? Null;

  if (! $input_type_rss) {
    $result = $rss_app->findRssForSite($xml_url);
    if (! array_key_exists('xmlUrl', $result) ) {
      echo "ERROR: failed to find RSS in $xml_url";
      exit(0);
    }
    $xml_url = $result['xmlUrl'];
    if ( $result['title'] ) {
      $title = $result['title'];
    }
  }
  list ($error, $feed_id, $title) = $rss_app->createFeed($xml_url, $title, $group, $source_type);
  if ($error) {
    echo mb_strimwidth("ERROR: $error", 0, 240, "...");;
    exit(1);
  }
  echo "Created: $feed_id\nTitle: $title\n";


?>
