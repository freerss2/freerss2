
<!--/ Project:   freerss        \--
  --| Author:    Felix Liberman |--
  --| Subsystem: UI             |--
  --| Function:  personal area  |--
  --\ Function:  edit filters   /-->

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

  $watch_req = $_GET['watch_id'];

  $watches_info = $rss_app->getWatchesList();
  $watches = array();
  $active_watch = Null;
  foreach ($watches_info as $watch) {
    $title = $watch['title'];
    if ($watch['user_id'] || $title == 'trash') {
      $watches[] = $watch;
      if ($watch_req && $watch['fd_watchid'] == $watch_req) {
        $active_watch = $watch;
      }
    }
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

    <title>Free RSS edit filters</title>
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
    <script>
      var edit_rule_id = '';
    </script>

    <div id="main">
    <nav class="navbar sticky-top navbar-dark bg-dark">
       <button class="openbtn" onclick="history.back()" title="Go back">
         <i class="fas fa-chevron-left"></i>
       </button>
       <span class="navbar-brand">Free RSS filter</span>
       <div class="btn-group" role="group" aria-label="toolbar group">
         <a class="btn btn-secondary btn-md" href="javascript:rerunFilters();" title="Rerun filters">
           <i class="far fa-play-circle"></i>
         </a>
         <a class="btn btn-secondary btn-md" href="../help#WatchFilters" title="Read about watches (filters)">
           <i class="fa fa-question"></i>
         </a>
       </div>
    </nav>

      <div class="container-fluid">
        <span style="display:inline-block; max-width:70%; margin-right:1em;"> 
          <h3>Watches (filters)</h3>
          <div class="btn-group mb-3" style="width: 100%;">
                <a class="btn btn-outline-secondary btn-md"
                  href="/api/watch/export/" title="Download watches as text">
                    <i class="fa fa-download"></i>
                </a>
                <a class="btn btn-outline-secondary btn-md"
                  href="javascript:uploadFiltersModal();" title="Upload watches from text file">
                    <i class="fas fa-cloud-upload-alt"></i>
                </a>
          </div>
          
          <ul class="nav nav-pills flex-column">
            <?php
              $active = is_null($active_watch) ? 'active' : '';
              $show_all_edit = is_null($active_watch) ? 'visually-hidden' : '';
              $show_save_delete = ($watch_req == 'trash') ? 'visually-hidden' : '';
              echo "<li class=\"nav-item\">\n";
              echo "<a class=\"nav-link $active\" href=\"edit_filter.php\">= New =</a>\n";
              echo "</li>\n";
              foreach ($watches as $watch) {
                  $active = $watch['fd_watchid'] === $active_watch['fd_watchid'] ? 'active' : '';
                  $watch_id = $watch['fd_watchid'];
                  $title = $watch['title'];
                  echo "<li class=\"nav-item\">\n";
                  echo "<a class=\"nav-link $active\" href=\"edit_filter.php?watch_id=$watch_id#\">$title</a>\n";
                  echo "</li>\n";
              }
            ?>            
          </ul>
        </span>
        <span style="display:inline-block; vertical-align: top; max-width: 800px;">

          <div class="input-group mb-3">
            <span class="input-group-text">Watch</span>
            <input type="text" class="form-control" value="<?php echo $active_watch['title'] ?>" id="watch_name" style="min-width: 8rem;" placeholder="Watch name">
            <button type="button" class="btn btn-outline-secondary <?php echo $show_save_delete; ?>" onclick="saveWatchName('<?php echo $active_watch['fd_watchid']; ?>')">
                Save
            </button>
            <button type="button" class="btn btn-outline-secondary <?php echo $show_save_delete; ?> <?php echo $show_all_edit; ?>" onclick="deleteWatch('<?php echo $active_watch['fd_watchid']; ?>')">
                Delete
            </button>
          </div>
          <?php
              if ( ! is_null($active_watch) ) {
                foreach ($active_watch['rules'] as $rule) {
                   $rule_repr = $rss_app->showRule($watches, $active_watch['fd_watchid'], $rule['rl_id']);
                   echo $rule_repr;
                }
              }
          ?>
          <div class="input-group mb-3 <?php echo $show_all_edit; ?>">
            <span class="input-group-text">Add rule</span>
            <input type="text" class="form-control" id="new_rule" style="min-width: 8rem;" placeholder="New rule name">
            <button class="btn btn-outline-secondary" type="button" onclick="addRule('<?php echo $watch_req; ?>');">
                <i class="far fa-edit"></i>
            </button>
          </div>
        </span>
      </div>

    </div>

    <div class="modal fade" id="ruleEditDialog" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Define watch rule</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" id="rule-edit">
            
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Dismiss</button>
            <button type="button" class="btn btn-primary" onclick="saveRule('<?php echo $watch_req; ?>', edit_rule_id)">Save</button>
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

    <div class="modal fade" id="upoladWatchesDialog" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <form action="../api/watch/import/" method="post" enctype="multipart/form-data">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Import Watches from text file</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
             <input type="file" class="mb-3" id="watchesFile" name="watchesFile" />
             <div class="alert alert-danger" role="alert">
                 <i class="fas fa-exclamation-triangle"></i>&nbsp;
                 This action could not be undone. All existing watches will be erased.
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
