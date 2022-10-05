<?php
  /*                                           *\
  // Read RSS feeds from all subscribed URLs
  // and insert new articles per feed in DB
  \*                                           */

  session_start();

  define('SOURCE_LEVEL', 3);
  $INCLUDE_PATH = str_repeat('../', SOURCE_LEVEL) . 'php_lib';

  include "$INCLUDE_PATH/rss_app.php";

  ##//##//##//##//##//##//##// MAIN

  // 0. Check validity of api key

  if ( ! $_SESSION ) {
    echo "Login expired!";
    // header("Location: /login/"); /* Redirect browser */
    exit();
  } // else

  # 1. Read arguments
  # $api_tocken = $_GET['api_tocken'];
  # TODO: implement this method
  //$user_id = $rss_app->checkApiTocken();
  //if ( ! $user_id ) {
  //  header("Location: /login/"); /* Redirect browser */
  //  exit();
  //}
  $rss_app = new RssApp();
  $user_id = $_SESSION['user_id'];
  $rss_app->setUserId($user_id);

  $feed_id = null;
  $errors = array();
  list ($enabled, $unread, $flagged) = $rss_app->getStatistics('group', 'all');
  $updated = 0;
  do {
    $next_rss = $rss_app->getNextRss($feed_id);
    if ( $next_rss) {
      $read_rss_url = $next_rss['xmlUrl'];
      $rss_title = $next_rss['title'];
      $feed_id = $next_rss['fd_feedid'];
      $site_to_feed = '';
      if ( $next_rss['mapping'] ) {
        # put in $read_rss_url site URL from next_rss
        $read_rss_url = $next_rss['htmlUrl'];
        $site_to_feed = array(
          'global_pattern' => $next_rss['global_pattern'],
          'item_pattern' => $next_rss['item_pattern'],
          'mapping' => json_decode($next_rss['mapping'])
        );
      }

      list($error, $items, $title, $link) = $rss_app->readRssUpdate($read_rss_url, $rss_title, $site_to_feed);
      if ($error) {
        // echo "ERROR: $error<BR>\n";
        $errors[] = $error;
        $rss_app->registerAppEvent('subscr', $feed_id, 'Error', $error);
      }
      // echo "storing ".count($items)." items...<BR>\n";
      $updated += $rss_app->storeRssItems($items, $feed_id);
    }
  } while($next_rss);

  $maintenance_log = array();
  if ($updated) {
    // run filters
    $maintenance_log[] = $rss_app->rerunFilters();
    // if last cleanup done 2 days before - perform it now
    $maintenance_log[] = $rss_app->checkCleanup();
  }

  list ($enabled, $new_unread, $flagged) = $rss_app->getStatistics('group', 'all');
  $new_count = $new_unread - $unread;
  # echo "Updated: $updated<BR>\n";
  echo "New articles: <b><a href='/personal/read.php'>$new_count</a></b><BR>\n";
  if (count($errors)) {
    echo "Download errors: <a href='/personal/report_events.php?type=subscr&id=last'>".
      count($errors)."</a><BR>\n";
  }
  echo substr(implode("<BR>\n", $maintenance_log), 0, 80);

?>
