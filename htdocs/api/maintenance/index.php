<?php

  session_start();

  define('SOURCE_LEVEL', 2);
  $INCLUDE_PATH = str_repeat('../', SOURCE_LEVEL) . 'php_lib';

  include "$INCLUDE_PATH/rss_app.php";

  $rss_app = new RssApp();

  $user_id = $_SESSION['user_id'];
  $rss_app->setUserId($user_id);

  $now = new DateTime();
  $timestamp = date_format($now, "Y-m-d_H-i-s");
  $filename = "dump_$timestamp.sql";
  $result = $rss_app->dumpDb("../../data/$filename");
  echo $filename;
  exit(0);

?>