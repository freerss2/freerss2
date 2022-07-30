
<!--/ Project:   freerss        \--
  --| Author:    Felix Liberman |--
  --| Subsystem: UI             |--
  --| Function:  personal area  |--
  --\ Function:  settings       /-->

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

  $admin_elements = ($user_id == 1);

  $subscr_tree = $rss_app->getAllSubscrTree();
  
  $personal_settings = $rss_app->getAllPersonalSettings();
  $page_size = $personal_settings['page_size'] ? max($personal_settings['page_size'], 5) : 20;
  $reminder_hours = $personal_settings['reminder_hours'] ? max($personal_settings['reminder_hours'], 1) : 2;
  $retention_leave_articles = $personal_settings['retention_leave_articles'] ? max($personal_settings['retention_leave_articles'], 10) : 100;
  $start_page = $personal_settings['start_page'] ? $personal_settings['start_page'] : 'group:All';

  $open_feature = $_GET['open'];  # supported: ?open=ImportModal
  if ( $open_feature == 'ImportModal' ) {
    $onload = "openImportModal();";
  } else {
    $onload = "";
  }
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

    <title>Free RSS - Settings</title>
  </head>
  <body onload="setArticlesContext(0); <?php echo $onload; ?>" data-bs-spy="scroll" data-bs-target="#navbar-settings" data-bs-offset="0" tabindex="0" >

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
        <span class="navbar-brand">&nbsp;<a href="/" class="navbar-brand">Free RSS</a> - Settings</span>
        <a class="btn btn-secondary btn-md" href="../help">
          <i class="fas fa-question"></i>
        </a>

      </nav>
     <div style="position:relative;">
      <nav id="navbar-settings" class="navbar navbar-light bg-light px-3 " style="position: sticky; top: 0; z-index: 1022;">        
        <ul class="nav nav-pills">
          <li class="nav-item" title="Scroll up">
            <a class="nav-link" href="#"><i class="fas fa-arrow-up"></i></a>
          </li>
          <li class="nav-item" title="Preferences">
            <a class="nav-link" href="#preferences"><i class="fas fa-user-cog"></i></a>
          </li>
          <li class="nav-item" title="Content">
            <a class="nav-link" href="#content"><i class="far fa-edit"></i></a>
          </li>
          <li class="nav-item" title="Reports">
            <a class="nav-link" href="#reports"><i class="far fa-chart-bar"></i></a>
          </li>
          <li class="nav-item" title="Support">
            <a class="nav-link" href="#support"><i class="far fa-question-circle"></i></a>
          </li>
        </ul>       
      </nav>

     <div class="card mb-3" id="preferences">
       <h1>&nbsp;<i class="fas fa-user-cog"></i>&nbsp;Preferences</h1>
       <div class="card-body">
         <h5 class="card-title">Reminder for articles refresh after...</h5>
         <div class="input-group mb-3" style="max-width:30em;">
           <span class="input-group-text">hours</span>
           <input type="number" min="1" max="24" class="form-control" id="reminder-hours" value="<?php echo $reminder_hours; ?>">
           <button class="btn btn-outline-secondary" type="button" id="submit1" onclick="updateSettingsFrom('reminder_hours', 'reminder-hours')">
             <i class="fa fa-check"></i>
           </button>
         </div>
         <h5 class="card-title">How many articles to leave on cleanup</h5>
         <div class="input-group mb-3" style="max-width:30em;">
           <span class="input-group-text">articles</span>
           <input type="number" min="10" max="200" step="10" class="form-control" id="retention-leave-articles" value="<?php echo $retention_leave_articles; ?>">
           <button class="btn btn-outline-secondary" type="button" id="submit2" onclick="updateSettingsFrom('retention_leave_articles', 'retention-leave-articles')">
             <i class="fa fa-check"></i>
           </button>
         </div>
         <h5 class="card-title">Page size</h5>
         <div class="input-group mb-3" style="max-width:30em;">
           <span class="input-group-text">articles</span>
           <input type="number" min="5" max="100" class="form-control" id="page-size" value="<?php echo $page_size; ?>">
           <button class="btn btn-outline-secondary" type="button" id="submit3" onclick="updateSettingsFrom('page_size', 'page-size')">
             <i class="fa fa-check"></i>
           </button>
         </div>
         <h5 class="card-title">Start reading from</h5>
         <select class="form-select" id="start-page" style="max-width:30em;" onchange="updateSettings('start_page', this.value)">
            <?php
              foreach ($subscr_tree as $row) {
                $name = $row[1];
                $type = $row[2];
                $id = $row[3];
                if ($type == 'watch' && $id == 'all') { continue; }
                $value = "$type:$id";
                if ($type == 'group') {
                  if (strtolower($id) == 'all') {
                    $title = 'All articles';
                  } else {
                    $title = "Feeds group \"$name\"";
                  }
                } elseif ($type == 'subscr') {
                  $title = "Feed \"$name\"";
                } elseif ($type == 'watch') {
                  $title = "Watch \"$name\"";
                }
                $selected = $start_page == $value ? 'selected' : '';
                echo "<option $selected value=\"$value\">$title</option>";
              }
            ?>
         </select>
       </div>
     </div>

     <div class="card mb-3" id="content">
       <h1>&nbsp;<i class="far fa-edit"></i>&nbsp;Manage Content</h1>
       <div class="card-body">
         <a type="button" class="btn btn-primary mb-3" href="/personal/edit_filter.php" style="min-width:18em;">
           <div class="row">
             <i class="fa fa-filter col-2 col-xs-1 settings-icon"></i>
             <span class="col-10 col-sm-9">Edit Filters (Watches)</span>
           </div>
         </a> <br>
         <a type="button" class="btn btn-primary mb-3" href="/personal/add_new_rss.php" style="min-width:18em;">
           <div class="row">
             <i class="fa fa-plus col-2 col-xs-1 settings-icon"></i>
             <span class="col-10 col-sm-9">Add new RSS</span>
           </div>
         </a> <br>
         <button type="button" class="btn btn-primary mb-3" style="min-width:18em;" onclick="openImportModal()">
           <div class="row">
             <i class="fa fa-cloud-upload-alt col-2 col-xs-1 settings-icon"></i>
             <span class="col-10 col-sm-9">Import OPML</span>
           </div>
         </button> <br>
         <a type="button" class="btn btn-primary mb-3" href="../api/feeds/export/" style="min-width:18em;">
           <div class="row">
             <i class="fa fa-download col-2 col-xs-1 settings-icon"></i>
             <span class="col-10 col-sm-9">Export OPML</span>
           </div>
         </a> <br>
         <?php
           if ( $admin_elements ) {
             echo '
         <a type="button" class="btn btn-primary mb-3" href="../api/maintenance/" style="min-width:18em;">
           <div class="row">
             <i class="fas fa-database col-2 col-xs-1 settings-icon"></i>
             <span class="col-10 col-sm-9">Create DB snapshot</span>
           </div>
         </a> <br>
         <a type="button" class="btn btn-primary mb-3" href="../data/" style="min-width:18em;">
           <div class="row">
             <i class="fas fa-tasks col-2 col-xs-1 settings-icon"></i>
             <span class="col-10 col-sm-9">DB snapshots list</span>
           </div>
         </a> <br>';
           }
         ?>
         <a type="button" class="btn btn-primary mb-3" href="../api/articles/export/" style="min-width:18em;">
           <div class="row">
             <i class="fa fa-download col-2 col-xs-1 settings-icon"></i>
             <span class="col-10 col-sm-9">Export articles (JSON)</span>
           </div>
         </a> <br>
         <a type="button" class="btn btn-primary mb-3" href="../api/articles/import/" style="min-width:18em;">
           <div class="row">
             <i class="fas fa-project-diagram col-2 col-xs-1 settings-icon"></i>
             <span class="col-10 col-sm-9">Import articles (JSON)</span>
           </div>
         </a> <br>
       </div>
     </div>

     <div class="card mb-3" id="reports">
       <h1>&nbsp;<i class="far fa-chart-bar"></i>&nbsp;Reports</h1>
       <div class="card-body">
         <a type="button" class="btn btn-primary mb-3" href="/personal/report_stat.php" style="min-width:18em;">
           <div class="row">
             <i class="fa fa-file-alt col-2 col-xs-1 settings-icon"></i>
             <span class="col-10 col-sm-9">Statistics</span>
           </div>
         </a> <br>
         <a type="button" class="btn btn-primary mb-3" href="/personal/report_events.php" style="min-width:18em;">
           <div class="row">
             <i class="fas fa-exclamation-circle col-2 col-xs-1 settings-icon"></i>
             <span class="col-10 col-sm-9">Events (update failures)</span>
           </div>
         </a> <br>
       </div>
     </div>

     <div class="card mb-3" id="support">
       <h1>&nbsp;<i class="far fa-question-circle"></i>&nbsp;Support</h1>
       <div class="card-body">
         <h5 class="card-title">Application version: <?php echo $APP_VERSION; ?> </h5>
       </div>
       <div class="card-body">
         <a type="button" class="btn btn-primary mb-3" href="https://github.com/freerss2/freerss2/issues/new" target="_blank" style="min-width:18em;">
           <div class="row">
             <i class="fas fa-bug col-2 col-xs-1 settings-icon"></i>
             <span class="col-10 col-sm-9">Report an issue (GitHub)</span>
           </div>
         </a> <br>
         <a type="button" class="btn btn-primary mb-3" href="https://telegram.me/coolwolf0" target="_blank" style="min-width:18em;">
           <div class="row">
             <i class="far fa-paper-plane col-2 col-xs-1 settings-icon"></i>
             <span class="col-10 col-sm-9">Contact author</span>
           </div>
         </a> <br>
         <a type="button" class="btn btn-primary mb-3" href="../help/" style="min-width:18em;">
           <div class="row">
             <i class="fas fa-book-reader col-2 col-xs-1 settings-icon"></i>
             <span class="col-10 col-sm-9">Documentation</span>
           </div>
         </a> <br>
       </div>
     </div>

    </div>

    <div class="modal fade" id="upoladOpmlDialog" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <form action="../api/feeds/import/" method="post" enctype="multipart/form-data">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Import Subscriptions from OPML file</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
             <input type="file" class="mb-3" id="opmlFile" name="opmlFile" />
             <div class="alert alert-danger" role="alert">
                 <i class="fas fa-exclamation-triangle"></i>&nbsp;
                 This action could not be undone. All existing subscriptions and downloaded articles will be erased.
             </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Dismiss</button>
            <button type="submit" class="btn btn-warning">Start import</button>
          </div>
        </div>
        </form>
      </div>
    </div>


  </body>
</html>
