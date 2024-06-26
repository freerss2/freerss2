
<!--/ Project:   freerss        \--
  --| Author:    Felix Liberman |--
  --| Subsystem: UI             |--
  --| Function:  personal area  |--
  --\ Function:  add new rss    /-->

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

  $groups = $rss_app->getSubscrGroups();
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

    <title>Free RSS</title>
  </head>
  <body onload="setArticlesContext(0);">

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
      var inputTypeRss = 1;
    </script>

    <div id="main">
      <nav class="navbar sticky-top navbar-dark bg-dark">
        <button class="openbtn" onclick="history.back()"><i class="fas fa-chevron-left"></i></button>
        <span class="navbar-brand">&nbsp;<a href="/" class="navbar-brand">Free RSS</a></span>
        <a class="btn btn-secondary btn-md" href="../help/#WatchFilters">
          <i class="fas fa-question"></i>
        </a>

      </nav>
      <div class="card">
        <h1>Add new RSS channel subscription</h1>
        <div class="card-body">

          <h5 class="form-check-label card-title" for="inputTypeSite">
            Address <span class="indicate-required">(*)<span>
          </h5>
          <div class="input-group short-input mb-3">
            <select class="form-select" onchange="changedFeedSourceType(this.value)" style="max-width: 30%;" id="sourceType">
              <option value="site">Site</option>
              <option selected value="rss">RSS source</option>
              <option value="site_to_feed">Site-to-feed</option>
            </select>
            <input type="text" class="form-control" id="xmlUrl" 
              placeholder="Link to website or RSS XML (feed)">
            <button class="btn btn-outline-secondary" type="button" id="edit-settings" style="display:none;min-width: 70%;"
              onclick="openSiteToFeedEdit('', document.getElementById('xmlUrl').value);" >
                Define...
            </button>
          </div>
          <h5 class="card-title">RSS title (description) <span class="indicate-required">(*)<span></h5>
          <input type="text" class="form-control short-input mb-3" id="new-rss-title" placeholder="Informal feed description">
          <h5 class="card-title">Group (main topic) <span class="indicate-required">(*)<span></h5>
          <div class="d-grid gap-2 d-md-block short-input mb-3" >
            <div class="input-group">
              <input type="text" class="form-control" id="new-rss-group"
            value="<?php echo $groups[0]; ?>" placeholder="To which feeds group it should belong">
 
            <?php
              if ($groups) {
                echo '<button class="btn btn-outline-secondary dropdown-toggle" type="button"
                 data-bs-toggle="dropdown" aria-expanded="false"></button>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li><span class="dropdown-item-text">Select from existing</span></li>';
 
                $sel = 'active';
                foreach ($groups as $group) {
                  echo "<li><a class=\"dropdown-item $sel\" href=\"javascript:changeFeedGroup('$group');\">$group</a></li>";
                  $sel = '';
                }
 
                echo '</ul>';
              }
            ?>
 
            </div>
          </div>
          <BR>
          <button type="button" class="btn btn-primary" onclick="createFeed();" style="min-width:8em;">
            <i class="fas fa-rss-square"></i>
             Add this RSS
          </button>
        </div>
      </div>

    </div>

    <div class="modal fade" id="updatingDialog" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Add new RSS channel</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" id="modal-message">
            <img class="loading-big" src="../img/loading.gif" >
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>

    <?php html_include('edit_site_to_feed_dialog.html'); ?>

    <?php html_include('error_dialog.html'); ?>

  </body>
</html>
