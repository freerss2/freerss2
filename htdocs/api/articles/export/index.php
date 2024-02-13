<?php
  /* - - - - - - - - - - - - *\
     Dump unread articles of current user
     /api/articles/export
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

  $dump = $rss_app->exportArticles('json');

  # Set header for JSON/txt
  header("Content-type: text/plain");
  header("Content-Disposition: attachment; filename=articles.json");

  echo $dump;

?>
