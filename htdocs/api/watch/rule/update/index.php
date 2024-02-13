<?php
  /* - - - - - - - - - - - - *\
     Build HTML code for watch
     rule update (POST method)
     {"watch_id":"tag_humor","rule_id":"rl_tag_humor_by_link",
      "rule_name":"by_link","group_limitation":"any",
      "where":[
        "`link` MATCH '*fakejews.co.il*' OR `link` MATCH '*panorama.pub*' OR `link` MATCH '*xkcd.com*'",""]
     }
  \* - - - - - - - - - - - - */
  session_start();

  define('SOURCE_LEVEL', 4);
  $INCLUDE_PATH = str_repeat('../', SOURCE_LEVEL) . 'php_lib';

  include "$INCLUDE_PATH/rss_app.php";

  $rss_app = new RssApp();

  // 1. Check login token
  // 1.1. Return message on error
  // TODO

  $user_id = $_SESSION['user_id'] ?? Null;
  $rss_app->setUserId($user_id);

  // 2. Get arguments (watch_id=NN rule_id=MM)
  // TODO: develop API args parser

  if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo "Error: unsupported request method ".$_SERVER['REQUEST_METHOD'];
    exit(1);
  }
  $data = json_decode(file_get_contents('php://input'));
  $reply = $rss_app->updateWatchRule(
    $data->watch_id, $data->rule_id, $data->rule_name, $data->group_limitation,
    $data->where);

  echo $reply;
  exit(0);
?>
