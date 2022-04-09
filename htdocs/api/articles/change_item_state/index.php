<?php
  /* - - - - - - - - - - - - *\
     Change article state:
     read/unread, mark/unmark
  \* - - - - - - - - - - - - */

  session_start();

  if ( ! $_SESSION['user_id'] ) {
    echo "Error: not logged-in";
    exit(1);
  }

  define('SOURCE_LEVEL', 3);
  $INCLUDE_PATH = str_repeat('../', SOURCE_LEVEL) . 'php_lib';

  include "$INCLUDE_PATH/rss_app.php";

  $rss_app = new RssApp();

  // 1. Check login token
  // 1.1. Return message on error
  // TODO

  $user_id = $_SESSION['user_id'];
  $rss_app->setUserId($user_id);

  // 2. Get arguments (item_id=STR, change_read=on/off/toggle, change_flagged=on/off)
  // TODO: develop API args parser

  $item_id     = $_GET['item_id'];
  if (! $item_id) {
    echo "missing item_id arg";
    exit(1);
  }

  $change_read = $_GET['change_read'];
  $change_flagged = $_GET['change_flagged'];

  if (! $change_read && ! $change_flagged) {
    echo "missing change args";
    exit(1);
  }

  // 3. Update `tbl_posts` for user_id=$user_id and fd_postid=$item_id
  // 3.1. if "change_read" - update field `read`
  if ($change_read) {
    $rss_app->updateItemState($item_id, 'read', $change_read == 'on'? 1 : 0);
    echo "updated item 'read' state<BR>\n";
  }
  // 3.2. if "change_flagged" - update field `flagged`
  if ($change_flagged) {
    $rss_app->updateItemState($item_id, 'flagged', $change_flagged == 'on'? 1 : 0);
    echo "updated item 'flagged' state<BR>\n";
  }

?>
