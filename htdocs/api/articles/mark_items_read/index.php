<?php
  /* - - - - - - - - - - - - *\
     Change articles list state
     to "read"
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

  // 2. Get arguments (ids=id1,id2,...)
  // TODO: develop API args parser

  $ids = $_GET['ids'];
  if (! $ids) {
    echo "missing ids arg";
    exit(1);
  }

  // 3. Update `tbl_posts` for user_id=$user_id and fd_postid=item_id
  // update field `read`
  $item_ids = explode(',', $ids);
  $rss_app->updateItemsState($item_ids, 'read', 1);
  echo "updated 'read' state for ".count($item_ids)." items<BR>\n";

?>