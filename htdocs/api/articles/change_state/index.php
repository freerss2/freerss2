<?php
  /* - - - - - - - - - - - - *\
     Change articles list state
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

  // 2. Get arguments (action=NNN&ids=id1,id2,...)
  // TODO: develop API args parser

  $action = $_GET['action'] ?? Null; if (! $action) { echo "missing action arg"; exit(1); }

  $type = $_GET['type'] ?? Null;
  if ($type) {
    // alternative "mark all read" for all pages
    // - type=group/subscr/watch
    // - id=STRING
    $id = $_GET['id'] ?? Null; if (! $id) { echo "missing id arg"; exit(1); }
    $item_ids = $rss_app->getUnreadNonmarked($type, $id);
    // update field `read`
    if ( $item_ids ) {
      $rss_app->updateItemsState($item_ids, 'read', 1);
    }
  } else {
    $ids = $_GET['ids'] ?? Null; if (! $ids) { echo "missing ids arg"; exit(1); }
    $item_ids = explode(',', $ids);
    $result = $rss_app->changeItemsState($item_ids, $action);
  }

  echo "updated 'read' state for ".count($item_ids)." items<BR>\n";

?>
