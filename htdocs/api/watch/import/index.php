<?php
  /* - - - - - - - - - - - - *\
     import watches from file:
---
- fd_watchid: tag_serials
  rules:
    tag_serials_1:
      conditions:
        - chk_text: "`fd_feedid` IN (SELECT `fd_feedid` FROM `tbl_subscr` WHERE `group` = 'Warez')"
        - chk_text: "`link` NOT like '%xxx%'"
      rl_type: text
      title: By_content
    tag_serials_2:
     ...
  title: Serials
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

  // 2. Get arguments (watchesFile=str-buffer)
  // TODO: develop API args parser

  if (!isset($_FILES['watchesFile'])) {
    echo "Error: missing 'watchesFile'";
    exit(1);
  }
  if($_FILES['watchesFile']['error'] !== UPLOAD_ERR_OK) {
    echo "Error: ".$_FILES['watchesFile']['error'];
    exit(1);
  }
  $watches_source = file_get_contents($_FILES['watchesFile']['tmp_name']);
  # call app-function for parsing and import
  $err = $rss_app->loadWatches($watches_source);
  if ($err) {
    echo $err;
  }
?>
