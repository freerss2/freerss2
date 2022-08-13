<?php
  /* - - - - - - - - - - - - *\
     Import feeds as OPML (XML)
     $_FILES['opmlFile']
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

  // 2. Get argument (format=opml)
  // TODO: develop API args parser

  if (!isset($_FILES['opmlFile']) || !$_FILES['opmlFile']['name']) {
    echo "Error: missing 'opmlFile'";
    exit(1);
  }
  if($_FILES['opmlFile']['error'] !== UPLOAD_ERR_OK) {
    echo "Error: ".$_FILES['opmlFile']['error'];
    exit(1);
  }
  $opml_source = file_get_contents($_FILES['opmlFile']['tmp_name']);
  # call app-function for parsing and import
  list( $error, $groups_count, $feeds_count ) = $rss_app->loadOpml($opml_source);
  if ($error) {
    $rss_app->registerAppEvent('system', 'import_opml', 'Error', $error);
  } else {
    $rss_app->registerAppEvent('system', 'import_opml', 'Success', "$groups_count groups, $feeds_count feeds");
  }
  header("Location: /personal/report_events.php?type=system&id=import_opml"); /* Redirect browser */
?>
