<?php
  /*                                           *\
  // Search info about article in external sources
  // ?plugin=kinopoisk&item_id=article_id
  \*                                           */

  session_start();

  define('SOURCE_LEVEL', 3);
  $INCLUDE_PATH = str_repeat('../', SOURCE_LEVEL) . 'php_lib';

  include "$INCLUDE_PATH/rss_app.php";
  include "$INCLUDE_PATH/movie_rating.php";

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

  $item_id     = $_GET['item_id'];
  if (! $item_id) {
    echo "missing item_id arg";
    exit(1);
  }
  $plugin     = $_GET['plugin'];
  if (! $plugin) {
    echo "missing plugin arg";
    exit(1);
  }
  if ($plugin != 'kinopoisk') {
    echo "unsupported plugin name";
    exit(1);
  }

  $article = $rss_app->getItem($item_id);
  $title = $article['title'];
  $movie_info = explode(' / ',
    str_replace('(', ' / ', str_replace(')', ' / ', $title)));
  # first two items are titles and third is a year
  if (! $movie_info) {
    echo '';
    exit(0);
  }
  $searcher = new MovieRatingKinopoiskUnoff('kp_unofficial');
  $result = $searcher->get_rating_info($title);
  if ( ! $result ) {
    $result = '<i title="Not found :-(" class="far fa-eye-slash"></i>';
  }
  echo $result;
?>
