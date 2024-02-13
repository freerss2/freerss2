<?php
  /* - - - - - - - - - - - - *\
     query for site-to-feed dialog:
       * site_address 
       * site_address & item_search & global_search
       * site_address & item_search & global_search & item_title & item_link & item_content
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

  $site_address     = $_GET['site_address']   ?? Null;
  $global_pattern   = $_GET['global_pattern'] ?? '';
  $item_pattern     = $_GET['item_pattern']   ?? '';
  $item_title       = $_GET['item_title']     ?? '';
  $item_link        = $_GET['item_link']      ?? '';
  $item_content     = $_GET['item_content']   ?? '';

  if ($site_address && $item_pattern && $item_title && $item_link && $item_content) {
    $content = $rss_app->extractSiteToFeedPreview($site_address, $global_pattern, $item_pattern,
      $item_title, $item_link, $item_content);
    echo $content;
    exit(0);
  } elseif ($site_address && $item_pattern) {
    $content = $rss_app->extractSiteToFeedContent($site_address, $global_pattern, $item_pattern);
    echo $content;
    exit(0);
  } elseif ($site_address) {
    $content = $rss_app->querySiteToFeedContent($site_address);
    echo $content;
    exit(0);
  } # else

  echo "Error: missing/empty mandatory parameter(s)";
  exit(1);


?>
