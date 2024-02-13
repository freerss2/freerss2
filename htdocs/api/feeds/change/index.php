<?php
  /* - - - - - - - - - - - - *\
     Change feed state:
     enable/disable, XMLURL, title, delete, rtl
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

  // 2. Get arguments (feed_id=STR, enable=1/0, xml_url=STR, title=STR, action=delete)
  // TODO: develop API args parser

  $feed_id = $_GET['feed_id'] ?? Null; if (! $feed_id) { echo "missing feed_id arg"; exit(1); }

  $enable  = $_GET['enable']  ?? '';
  $rtl     = $_GET['rtl']     ?? '';
  $xml_url = $_GET['xmlUrl']  ?? '';
  $title   = $_GET['title']   ?? '';
  $group   = $_GET['group']   ?? '';
  $action  = $_GET['action']  ?? '';

  // 3. Update `tbl_subscr` for user_id=$user_id and fd_feed=$feed_id
  // 3.1. if enable is 0/1 - update field `download_enabled`
  if ($enable === '1' || $enable === '0') {
    $rss_app->updateFeed($feed_id, $enable);
    echo "updated feed 'enable' state to $enable\n";
    $done = 1;
  }
  if ($rtl === '1' || $rtl === '0') {
    $rss_app->updateFeed($feed_id, null, null, null, null, null, $rtl);
    echo "updated feed 'rtl' to $rtl\n";
    $done = 1;
  }
  if ($xml_url) {
    $rss_app->updateFeed($feed_id, null, $xml_url);
    echo "updated feed xml_url to $xml_url\n";
    $done = 1;
  }
  if ($title) {
    $err = $rss_app->updateFeed($feed_id, null, null, $title);
    if ( $err ) {
      echo $err;
    } else {
      echo "updated feed title to $title\n";
    }
    $done = 1;
  }
  if ($group) {
    $err = $rss_app->updateFeed($feed_id, null, null, null, $group);
    if ( $err ) {
      echo $err;
    } else {
      echo "updated feed group to $group\n";
    }
    $done = 1;
  }
  if ($action && $action == 'delete') {
    $rss_app->updateFeed($feed_id, null, null, null, null, $action);
    echo "deleted feed and its articles\n";
    $done = 1;
  }
  if (! $done) {
    echo "missing/wrong arguments for API";
  }
  // 3.2. if "change_mark" - update field `flagged`
  // TODO

?>
