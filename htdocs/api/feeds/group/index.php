<?php
  /* - - - - - - - - - - - - *\
     Change feeds group:
     action=edit/save group_id=group_id
     (changed content in "save" request body)
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

  // 2. Get arguments (feed_id=STR, enable=1/0, xml_url=STR, title=STR, action=delete)
  // TODO: develop API args parser

  $group_id     = $_GET['group_id'];
  if (! $group_id) {
    echo "missing group_id arg";
    exit(1);
  }

  $action = $_GET['action'];

  // For 'action' == 'edit'
  // return HTML code for feeds group editing
  if ($action == 'edit') {
    $buffer = $rss_app->feedsGroupEdit($group_id);
    echo $buffer;
  } else {
    $data = json_decode(file_get_contents('php://input'));
    $result = $rss_app->feedsGroupSave($group_id, $data);
    echo $result;
  }

?>
