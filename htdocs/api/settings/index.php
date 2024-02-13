<?php
  /* - - - - - - - - - - - - *\
     Change user settings:
     show_articles=read/unread/both,
     order_articles=time/name
  \* - - - - - - - - - - - - */
  session_start();

  define('SOURCE_LEVEL', 2);
  $INCLUDE_PATH = str_repeat('../', SOURCE_LEVEL) . 'php_lib';

  include "$INCLUDE_PATH/rss_app.php";

  $rss_app = new RssApp();

  // 1. Check login token
  // 1.1. Return message on error
  // TODO

  $user_id = $_SESSION['user_id'] ?? Null;
  $rss_app->setUserId($user_id);

  // 2. Get arguments (item_id=STR, change_read=on/off/toggle, change_mark=on/off)
  // TODO: develop API args parser

  $set   = $_GET['set']   ?? Null; if (! $set)   { echo "missing set arg";   exit(1); }
  $value = $_GET['value'] ?? Null; if (! $value) { echo "missing value arg"; exit(1); }

  $rss_app->setPersonalSetting($set, $value);
  echo "updated $set to $value<BR>\n";

?>