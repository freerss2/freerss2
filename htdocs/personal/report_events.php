
<!--/ Project:   freerss        \--
  --| Author:    Felix Liberman |--
  --| Subsystem: UI             |--
  --| Function:  personal area  |--
  --\ Function:  events rep     /-->

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

  $event_type = $_GET['type'];
  $id = $_GET['id'];
  if ( $event_type ) {
    $query_type = ( $event_type == 'all' ) ? null : $event_type;
  } else {
    $query_type = 'subscr';
  }
  if ( $event_type && $id ) {
    $event_rec = $rss_app->getEventRecord($event_type, $id);
  } else {
    $event_rec = array();
  }
  $report_data = $rss_app->eventsReportData($query_type);

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

    <title>Free RSS - Events Report</title>
  </head>
  <body onload="setArticlesContext(0);
    <?php
      if ( $event_rec ) {
         echo "showEventDialog();";
      }
    ?>
    ">

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
       <span class="navbar-brand">&nbsp;<a href="/" class="navbar-brand">Free RSS</a>- Events</span>
       <a class="btn btn-secondary btn-md" href="../help">
           <i class="fas fa-question"></i>
       </a>

     </nav>
     <div class="card container">
       <br>
       <?php
          if ($report_data) {
            echo '<div class="row">';
            echo '  <div class="col-3">Name</div>';
            echo '  <div class="col-2">Timestamp</div>';
            echo '  <div class="col-2">Status</div>';
            echo '  <div class="col-5">Log</div>';
            echo '</div>';

            foreach ($report_data as $rec) {
              $ref = 'read.php?type='.$rec[0].'&id='.$rec[1];
              $timestamp = _date_to_passed_time($rec[3]);
              echo '<div class="row stat_row">';
              echo '  <div class="col-3 no-text-overflow"><a href="'.$ref.'" target="_blank">'.$rec[2].'</a></div>';
              echo '  <div class="col-2" >'.$timestamp.'</div>';
              echo '  <div class="col-2 no-text-overflow" >'.$rec[4].'</div>';
              echo '  <div class="col-5" >'.substr(strip_tags($rec[5]), 0, 80).'</div>';
              echo "</div>\n";
            }
          } else {
            echo '<center><h2><i class="far fa-smile-beam"></i>&nbsp;Nothing catched</h2></center>';
          }
       ?>
     </div>


    <div class="modal fade" id="eventDetailsDialog" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Event details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <?php
              if ( $event_rec ) {
                // received record with 'name', 'timestamp', 'status', 'log'
                if ( ! $event_rec ) {
                  echo "Wrong event type & id: $event_type, $id";
                } else {
                  echo $event_rec['timestamp']."<br>";
                  echo $event_rec['name']."<br>";
                  echo "<label>".$event_rec['status'].":</label>&nbsp;";
                  echo $event_rec['log']."<br>";
                }
              }
            ?>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Dismiss</button>
          </div>
        </div>
      </div>
    </div>


  </body>
</html>
