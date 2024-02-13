<?php
  /* - - - - - - - - - - - - *\
     Build HTML code for watch
     rule edit
     watch_id=NN rule_id=MM
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

  $watch_id = $_GET['watch_id'] ?? Null; if (! $watch_id) { echo "missing watch_id arg"; exit(1); }
  $rule_id  = $_GET['rule_id']  ?? Null; if (! $rule_id)  { echo "missing rule_id arg"; exit(1); }

  echo $rss_app->editRule($watch_id, $rule_id);

?>