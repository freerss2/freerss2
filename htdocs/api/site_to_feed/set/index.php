<?php
  /* - - - - - - - - - - - - *\
     save result from site-to-feed dialog:
       * feed_id & site_address & item_search & global_search & item_title & item_link & item_content
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

  $feed_id          = $_GET['feed_id']        ?? Null;
  // when feed_id is empty - create new (with ID according to site_address)
  $site_address     = $_GET['site_address']   ?? Null;
  $global_pattern   = $_GET['global_pattern'] ?? Null;
  $item_pattern     = $_GET['item_pattern']   ?? Null;
  $item_title       = $_GET['item_title']     ?? Null;
  $item_link        = $_GET['item_link']      ?? Null;
  $item_content     = $_GET['item_content']   ?? Null;
  $rss_title        = $_GET['rss_title']      ?? Null;
  $rss_group        = $_GET['rss_group']      ?? Null;

  if ($site_address && $item_pattern && $item_title && $item_link && $item_content) {
    $content = $rss_app->saveSiteToFeed($feed_id, $site_address, $global_pattern, $item_pattern,
      $item_title, $item_link, $item_content, $rss_title, $rss_group);
    echo $content;
    exit(0);
  } # else

  echo "Error: missing/empty mandatory parameter(s)";
  exit(1);


?>
