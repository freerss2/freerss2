
<!--/ Project:   freerss        \--
  --| Author:    Felix Liberman |--
  --| Subsystem: UI             |--
  --| Function:  personal area  |--
  --\ Function:  statistics rep /-->

<?php
  session_start();
  if ( !$_SESSION || !$_SESSION['user_id'] ) {
    header("Location: /login/"); /* Redirect browser */
    exit();
  }

  define('SOURCE_LEVEL', 1);
  $INCLUDE_PATH = str_repeat('../', SOURCE_LEVEL) . 'php_lib';

  include "$INCLUDE_PATH/rss_app.php";

  $rss_app = new RssApp();
  $user_id = $_SESSION['user_id'];  # take from login info
  $rss_app->setUserId($user_id);

  $subscr_tree = $rss_app->getAllSubscrTree();

?>

<!doctype html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">

    <!-- Fontawesome -->
    <link rel="stylesheet" href="../style/fontawesome/css/all.css">
    <link rel="icon" href="/img/favicon.ico">

    <!-- App styles -->
    <link rel="stylesheet" href="../style/main_screen.css<?php echo $VER_SUFFIX;?>">

    <title>Free RSS - Statistics Report</title>
  </head>
  <body onload="setArticlesContext(0);">

    <!-- Optional JavaScript; choose one of the two! -->

    <!-- Option 1: Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>

    <!-- Option 2: Separate Popper and Bootstrap JS -->
    <!--
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js" integrity="sha384-7+zCNj/IqJ95wo16oMtfsKbZ9ccEh31eOz1HGyDuCQ6wgnyJNSYdrPa03rtR1zdB" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js" integrity="sha384-QJHtvGhmr9XOIpI6YVutG+2QOK9T+ZnN4kzFN1RtK3zEFEIsxhlmWl5/YESvpZ13" crossorigin="anonymous"></script>
    -->

    <script src="../script/service.js<?php echo $VER_SUFFIX;?>" ></script>
    <script src="../script/personal.js<?php echo $VER_SUFFIX;?>" ></script>


    <div id="main">
     <nav class="navbar sticky-top navbar-dark bg-dark">
       <button class="openbtn" onclick="history.back()"><i class="fas fa-chevron-left"></i></button>
       <span class="navbar-brand">&nbsp;<a href="/" class="navbar-brand">Free RSS</a>- Statistics</span>
       <a class="btn btn-secondary btn-md" href="../help">
           <i class="fas fa-question"></i>
       </a>

     </nav>
     <div class="card container">
       <br>
       <?php
          echo '<div class="row">';
          echo '  <div class="col-4">Name</div>';
          echo '  <div class="col-2" style="text-align:center;">Unread</div>';
          echo '  <div class="col-2" style="text-align:center;">Flagged</div>';
          echo '  <div class="col-4" style="text-align:center;">Last Update</div>';
          echo '</div>';

          foreach ($subscr_tree as $rec) {
            if ($rec[2] == 'watch' && substr($rec[3], 0, 4) != 'tag_') { continue; }
            list($enabled, $unread, $flagged, $last_upd) = $rss_app->getStatistics($rec[2], $rec[3]);
            $enable_style = $enabled ? 'style="text-decoration: none;"' : 'style="text-decoration: line-through;"';
            $unread = $unread ? "<B>$unread</B>" : '-';
            $flagged = $flagged ? "<B>$flagged</B>" : '-';
            $ref = 'read.php?type='.$rec[2].'&id='.$rec[3];
            $last_upd = _date_to_passed_time($last_upd);
            if (strstr($last_upd, '-') !== false) {
              $last_upd = "<B>$last_upd</B>";
            }
            echo '<div class="row">';
            echo '  <div class="col-4"><a href="'.$ref.'" class="no-text-overflow" '.$enable_style.' target="_blank">'.$rec[1].'</a></div>';
            echo '  <div class="col-2" style="text-align:center;">'.$unread.'</div>';
            echo '  <div class="col-2" style="text-align:center;">'.$flagged.'</div>';
            echo '  <div class="col-4" style="text-align:center;">'.$last_upd.'</div>';
            echo "</div>\n";
          }
       ?>
     </div>


  </body>
</html>
