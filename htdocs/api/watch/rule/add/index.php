<?php
  /* - - - - - - - - - - - - *\
     Add rule to watch
     "watch_id", "rule_name"
  \* - - - - - - - - - - - - */
  session_start();

  define('SOURCE_LEVEL', 4);
  $INCLUDE_PATH = str_repeat('../', SOURCE_LEVEL) . 'php_lib';

  include "$INCLUDE_PATH/rss_app.php";

  $rss_app = new RssApp();

  // 1. Check login token
  // 1.1. Return message on error
  // TODO

  $user_id = $_SESSION['user_id'];
  $rss_app->setUserId($user_id);

  // 2. Get arguments (watch_id=NN rule_id=MM)
  // TODO: develop API args parser

  $watch_id = $_GET['watch_id']; if (! $watch_id) { echo "missing watch_id arg"; exit(1); }
  $new_rule_name = $_GET['rule_name']; if (! $new_rule_name) { echo "missing rule_name arg"; exit(1); }
  $reply = $rss_app->addRule($watch_id, $new_rule_name);

  echo $reply;
  exit(0);
?>
