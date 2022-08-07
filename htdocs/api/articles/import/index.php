<?php
  /* - - - - - - - - - - - - *\
     Import articles from JSON dump
     $_FILES['articles']
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
  if (! $user_id) { $user_id = $_GET['user_id']; }
  $rss_app->setUserId($user_id);

  // 2. Get argument (format=opml)
  // TODO: develop API args parser
  if (!isset($_FILES['articles']) || !$_FILES['articles']['name']) {
    echo "Error: missing 'articles'";
    exit(1);
  }
  if($_FILES['articles']['error'] !== UPLOAD_ERR_OK) {
    echo "Error: ".$_FILES['articles']['error'];
    exit(1);
  }
  $articles = file_get_contents($_FILES['articles']['tmp_name']);
  # check type
  if ($_FILES['articles']['type'] != "application/json") {
    echo "Error: unsupported data format - ".$_FILES['articles']['type'];
    exit(1);
  }
  echo "type of articles=".gettype($articles)."\n";
  try {
    $articles = json_decode(remove_utf8_bom($articles), true);
  }
  catch (Exception $e) {
    echo $e->getMessage();
    exit(1);
  }
  echo "type of articles=".gettype($articles)."\n";
  if (is_null($articles)) {
    echo "Error: decoding failed";
    exit(1);
  }
  # call app-function for parsing and import
  list( $error, $count ) = $rss_app->importArticles($articles);

  if ($error) {
    $rss_app->registerAppEvent('system', 'import_articles', 'Error', $error);
  } else {
    $rss_app->registerAppEvent('system', 'import_articles', 'Success', "$count articles");
  }

  echo $error;
  exit(0);
?>
