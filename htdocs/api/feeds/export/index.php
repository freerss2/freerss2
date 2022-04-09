<?php
  /* - - - - - - - - - - - - *\
     Export feeds as OPML (XML)
     format=opml
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

  $formal = $_GET['formal'];
  if (! $format) { $format='opml'; }
  if ($format != 'opml') { echo "unsupported format: $format"; exit(1); }

  $opml = $rss_app->exportOpml();
  # Save as a file
  header("Content-type: text/plain");
  header("Content-Disposition: attachment; filename=user.opml");
  echo $opml;

?>
