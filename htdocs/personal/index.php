
<!--/ Project:   freerss        \--
  --| Author:    Felix Liberman |--
  --| Subsystem: UI             |--
  --| Function:  personal area  |--
  --\ Function:  main screen    /-->

<?php
  session_start();

  define('SOURCE_LEVEL', 1);
  $INCLUDE_PATH = str_repeat('../', SOURCE_LEVEL) . 'php_lib';

  if ( !$_SESSION || !$_SESSION['user_id'] ) {
    $_SESSION = array(
      'return_link' =>
      (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"
    );
    header("Location: /login/connect.php"); /* Redirect browser */
    exit();
  }

  include "$INCLUDE_PATH/rss_app.php";

  $rss_app = new RssApp();
  $user_id = $_SESSION['user_id'];  # take from login info
  $rss_app->setUserId($user_id);

  $subscr_tree = $rss_app->getAllSubscrTree();

  $statistics = $rss_app->getSubscrSummary();

  $empty_subscr = ! $statistics['total_subscriptions'];
  if ($empty_subscr) {
    $statistics['update_required'] = false;
  }

  # Add new RSS or import OPML
  $promptForInit = $empty_subscr ? 'promptForInit();' : '';

  $start_page_id = $rss_app->getPersonalSetting('start_page');
  $start_page_id = $start_page_id ? $start_page_id : 'watch:all';
  list($start_page_type, $start_page_id) = explode(':', $start_page_id);
  $start_page = "read.php?type=$start_page_type&id=$start_page_id";
?>

<!doctype html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS -->
    <link href="../style/bootstrap_5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">

    <!-- Fontawesome -->
    <link rel="stylesheet" href="../style/fontawesome/css/all.css">
    <link rel="icon" href="/img/favicon.ico">

    <!-- App styles -->
    <link rel="stylesheet" href="../style/main_screen.css<?php echo $VER_SUFFIX;?>">

    <title>Free RSS <?php if ( $statistics['update_required'] ) { echo " (*)"; } ?> </title>
  </head>
  <body onload="setArticlesContext(0); initInlineHelp(); initFocus(); setLoginAuthToken(); <?php echo $promptForInit; ?>">

    <!-- Optional JavaScript; choose one of the two! -->

    <!-- Option 1: Bootstrap Bundle with Popper -->
    <script src="../style/bootstrap_5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>

    <!-- Option 2: Separate Popper and Bootstrap JS -->
    <!--
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js" integrity="sha384-7+zCNj/IqJ95wo16oMtfsKbZ9ccEh31eOz1HGyDuCQ6wgnyJNSYdrPa03rtR1zdB" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js" integrity="sha384-QJHtvGhmr9XOIpI6YVutG+2QOK9T+ZnN4kzFN1RtK3zEFEIsxhlmWl5/YESvpZ13" crossorigin="anonymous"></script>
    -->

    <script src="../script/service.js<?php echo $VER_SUFFIX;?>" ></script>
    <script src="../script/personal.js<?php echo $VER_SUFFIX;?>" ></script>

    <script>
      var update_required = 
          "<?php echo $statistics['update_required'] ?$statistics['update_required'] : '' ?>" ;
      if ( update_required ) {
        systemPopupNotification(
          'FreeRSS2 notification', 
          'Feeds update required', function(n,c) { refreshRss(); }, 30000);
      }
    </script>

    <script> refreshMainPage(1); </script>

    <div id="mySidebar" class="sidebar">
      <a href="javascript:void(0)" class="closebtn" onclick="closeNav()"><i class="fas fa-times"></i></a>
    <?php
      $link_base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
      $pattern = '/\?.*/';
      $link_base = preg_replace($pattern, '', $link_base);
      
      # each tree row contains: path, title, type and id
      foreach ($subscr_tree as $row) {
        $path = $row[0];
        $title = $row[1];
        $type = $row[2];
        $id = $row[3];
        $level = count(explode('^', $path));
        $link = $link_base."read.php?type=$type&id=$id";
        echo "<a class=\"sidebar_l$level no-text-overflow\" href=\"$link\">$title</a>\n";
      }
    ?>
    </div>

    <div id="main">
      <nav class="navbar sticky-top navbar-dark bg-dark">
        <div>
          <button class="openbtn" onclick="toggleNav()" title="Select feed/group/watch from subscriptions tree"><i class="fas fa-bars"></i></button>
          <div class="btn-group" role="group" aria-label="toolbar group">
            <button title="Search in articles..." class="btn btn-secondary btn-md" onclick="startSearch();">
              <i class="fas fa-search"></i>
            </button>
            <button title="Refresh articles from feeds" type="button" class="btn btn-secondary btn-md position-relative" onclick="refreshRss();">
              <i class="fa fa-sync-alt"></i>
             <span class="<?php echo $statistics['update_required'] ? '': 'visually-hidden' ?> position-absolute top-0 start-100 translate-middle p-2 bg-danger border border-light rounded-circle">
                <span class="visually-hidden">Too old</span>
             </span>
            </button>
          </div>
        </div>
        <span class="navbar-brand">Free RSS</span>
        <div class="btn-group" role="group" aria-label="toolbar group">


          <div class="dropdown">
            <button class="btn btn-secondary btn-md dropdown-toggle" type="button" id="feedReadMenu" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fas fa-ellipsis-v"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="feedReadMenu">
              <li><a class="dropdown-item" href="/personal/add_new_rss.php"><i class="fa fa-plus"></i> Add new RSS... </a></li>
              <li><a class="dropdown-item" href="/personal/edit_filter.php"><i class="fa fa-filter"></i> Add new Watch... </a></li>
              <li><a class="dropdown-item" href="/personal/settings.php"><i class="fas fa-tools"></i> Settings... </a></li>
            </ul>
          </div>


        </div>
      </nav>

     <div class="card">
       <h1>Hello <?php echo $statistics['user_name']; ?>, and welcome to you personal area</h1>
       <div class="card-body">
         <h5 class="card-title">Subscriptions statistics</h5>
         <div class="alert alert-secondary main-screen-stat" role="alert">
           <span class="stat-title"> <i class="fa fa-rss"></i> Total subscriptions  </span>
           <b><?php echo $statistics['total_subscriptions']; ?></b>
           <i class="far fa-question-circle stat-help inline-help" 
             title="Here counted all subcsriptions, including temporary disabled. The motivation for disabling some subscription is to avoid errors about feed unavailability. So, if the feed is under maintenance or reached end-of-life, but you still want to see some old articles received from it, - just mark it as 'disabled'."></i>
         </div>
         <div class="alert alert-secondary main-screen-stat" role="alert">
           <span class="stat-title"> <i class="fas fa-rss"></i> Active subscriptions </span>
           <b><?php echo $statistics['active_subscriptions']; ?></b>
           <i class="far fa-question-circle stat-help inline-help" title="Active subcsriptions are subscriptions that participate in content updates"></i>
         </div>
         <div class="alert alert-secondary main-screen-stat" role="alert">
           <span class="stat-title"> <i class="fas fa-envelope"></i> Unread articles      </span>
           <b><?php echo $statistics['unread_articles']; ?></b>
           <i class="far fa-question-circle stat-help inline-help" title="Counted articles that not marked as 'read' (including bookmarked)"></i>
         </div>
         <div class="alert alert-secondary main-screen-stat" role="alert">
           <span class="stat-title"> <i class="far fa-star"></i> Bookmarked articles  </span>
           <b><?php echo $statistics['bookmarked_articles']; ?></b>
           <i class="far fa-question-circle stat-help inline-help" title="Bookmarked articles are protected from accidential marking as 'read'"></i>
         </div>
         <div class="alert alert-secondary main-screen-stat" role="alert">
           <span style="width: 6em; display: inline-block;"> <i class="far fa-clock"></i> Updated </span>
           <b><?php echo $statistics['updated_at']; ?></b>
           <i class="far fa-question-circle stat-help inline-help" title="Here displayed the timestamp from last updated article"></i>
         </div>

         <div>
           <a href="<?php echo $start_page; ?>" title="Open feed/group/watch specified in settings as default" class="btn btn-dark btn-sm low-action-button">
             <i class="fas fa-rss"></i> Start reading
           </a>
           <a href="<?php echo $statistics['last_page']; ?>" title="Return to last watched feeds page" class="btn btn-dark btn-sm low-action-button">
             <i class="fas fa-history"></i> Last page
           </a>
           <a href="../login?logout=1" class="btn btn-dark btn-sm low-action-button">
             <i class="fas fa-sign-out-alt"></i> Logout
           </a>
           <a href="../help/#start" title="Read documentation" class="btn btn-dark btn-sm low-action-button">
             <i class="fab fa-readme"></i> Help
           </a>
         </div>
       </div>
     </div>

    </div>

    <div class="modal" id="promptForInit" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">No subscriptions</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p>You <b>have no any subscriptions</b> so far</p>
            <p>There are two possible ways for building your personal feeds set:</p>
            <a type="button" class="btn btn-primary" href="/personal/add_new_rss.php">
              <i class="fa fa-plus"></i>&nbsp;Add RSS link
            </a>
              OR
            <a type="button" class="btn btn-primary" href="/personal/settings.php?open=ImportModal">
              <i class="fas fa-cloud-upload-alt"></i>&nbsp;Import OPML
            </a>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Dismiss</button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal" id="updatingDialog" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Updating, please wait...</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <img class="loading-big" src="../img/loading.gif" >
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="updatedDialog" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Done</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p id="updatedDialogContent"></p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="window.location.href='/'">Close</button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="searchDialog" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Search</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <label>Find in article title or body</label>
            <input type="text" class="form-control" id="text-to-find">
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="button" class="btn btn-primary" onclick="triggerSearch();">Start search</button>
          </div>
        </div>
      </div>
    </div>

    <?php html_include('inline_help_dialog.html'); ?>

  </body>
</html>
