
<!--/ Project:   freerss        \--
  --| Author:    Felix Liberman |--
  --| Subsystem: UI             |--
  --| Function:  personal area  |--
  --\ Function:  main           /-->

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

  $groups = $rss_app->getSubscrGroups();

  $statistics = $rss_app->getSubscrSummary();  

  $empty_subscr = ! $statistics['total_subscriptions'];
  if ($empty_subscr) {
    $statistics['update_required'] = false;
  }

  # Add new RSS or import OPML
  $promptForInit = $empty_subscr ? 'promptForInit();' : '';

  $actual_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") .
     "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
  $rss_app->saveLastLink($actual_link);

# ------------[ Get articles for display ]---------------

$maxpage = 1;

$personal_settings = $rss_app->getAllPersonalSettings();
$show_articles = $personal_settings['show_articles'] ? $personal_settings['show_articles'] : 'unread';
$order_articles = $personal_settings['order_articles'] ? $personal_settings['order_articles'] : 'time';
$page_size = $personal_settings['page_size'] ? max($personal_settings['page_size'], 5) : 20;
$show_active = array(
  'unread' => $show_articles == 'unread' ? 'active' : '',
  'read'   => $show_articles == 'read'   ? 'active' : '',
  'both'   => $show_articles == 'both'   ? 'active' : ''
);
$order_active = array(
  'time' => $order_articles == 'time' ? 'active' : '',
  'name' => $order_articles == 'name' ? 'active' : '',
);

$req_type = $_GET['type'] ? $_GET['type'] : 'group';
$req_id = $_GET['id'] ? $_GET['id'] : 'all';

if ($req_type == 'group' && $req_id == 'all') {
  $req_type = 'watch';
}

$no_subscr_msg = "";
if($req_type == 'watch') {
  $req_feed_id = null;
  $curr_watch_id = '';
  $curr_group_id = '';
  if ($req_id == 'search') {
    $watch_title = $req_id;
    $pattern = $_GET['pattern'];
    $items = $rss_app->findItems($pattern);
  } else {
    $curr_watch_id = $req_id;
    list($watch_title, $items) = $rss_app->retrieveWatchItems($req_id);
  }
} elseif ($req_type == 'group') {
  $req_feed_id = null;
  $curr_watch_id = '';
  $curr_feed_id = '';
  $curr_group_id = $req_id;
  $items = $rss_app->retrieveGroupItems($req_id);
} elseif ($req_type == 'subscr') {
  $curr_feed_id = '';
  $curr_watch_id = null;
  $curr_group_id = '';
  $req_feed_id = $req_id;
  if ($req_feed_id == 'null') { $req_feed_id = null; }

  if (! $req_feed_id) {
    # try to take default view
    foreach ($subscr_tree as $row) {
        $type = $row[2];
        if ($type === 'subscr') {
          $req_feed_id = $row[3];
          break;
        }
    }
  }

  $articles_count = 0;

  if ( $req_feed_id ) {
    list($rss_info, $items) = $rss_app->retrieveRssItems($req_feed_id);

    $rss_title = $rss_info['title'];
    $html_url = $rss_info['htmlUrl'];
    $xmlUrl = $rss_info['xmlUrl'];
    $rss_group = $rss_info['group'];
    $download_enabled = $rss_info['download_enabled'];
    $curr_feed_id = $req_feed_id;
  } else {
    $items = array();
    $no_subscr_msg = "No subscriptions found. Please add some RSS subscription in application settings";
  }

}

$articles_count = $items ? count($items) : 0;
if ($articles_count >= 100) { $articles_count = '99+'; }

// if specified page N - get specific page
// else - take page 1 
$page_num = $_GET['page'];
if (! $page_num) { $page_num = 1; }
else             { $page_num = intval($page_num); }

$rss_inactivity_warning = $rss_app->warnRssInactivity($items, $req_feed_id);

// build paging structure (range, curr. active)
if (!$items) {
  $items = array();
}
list ($items, $maxpage, $displayed_page) = $rss_app->buildPaging($items, $page_size, $page_num);
$pages_range = $rss_app->getPagesRange($maxpage, $displayed_page);
$next_page = $displayed_page < $maxpage;
$prev_page = $displayed_page > 1;

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
    <link rel="icon" type="image/x-icon" href="../img/favicon.ico">

    <!-- App styles -->
    <link rel="stylesheet" href="../style/main_screen.css<?php echo $VER_SUFFIX;?>">

    <title>Free RSS</title>
  </head>
  <body onload="setArticlesContext(1); <?php echo $promptForInit; ?>">

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

    <script> bindKeysForFeeds(); </script>

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
        $link = "$link_base?type=$type&id=$id";
        echo "<a class=\"sidebar_l$level no-text-overflow\" href=\"$link\">$title</a>\n";
      }
    ?>
    </div>

    <div id="main">
    <nav class="navbar sticky-top navbar-dark bg-dark">
      <div class="container-fluid">
        <div>
          <button class="openbtn" onclick="toggleNav()"><i class="fas fa-bars"></i></button>
          <div class="btn-group" role="group" aria-label="toolbar group">
            <button title="Search..." class="btn btn-secondary btn-md" onclick="startSearch();">
              <i class="fas fa-search"></i>
            </button>
            <button type="button" title="Refresh now" class="btn btn-secondary btn-md position-relative" onclick="refreshRss();">
              <i class="fa fa-sync-alt"></i>
             <span class="<?php echo $statistics['update_required'] ? '': 'visually-hidden' ?> position-absolute top-0 start-100 translate-middle p-2 bg-danger border border-light rounded-circle">
                <span class="visually-hidden">Too old</span>
             </span>
            </button>
          </div>
        </div>
        <span class="navbar-brand no-text-overflow">&nbsp;<a href="/" title="Go to homepage" class="navbar-brand">Free RSS</a></span>
        <div class="btn-group" role="group" aria-label="toolbar group">
          <div class="dropdown">
            <button class="btn btn-secondary btn-md dropdown-toggle" type="button" id="feedReadMenu" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fas fa-sliders-h"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="feedReadMenu">
              <li><a class="dropdown-item <?php echo $show_active['unread']; ?>" href="javascript:updateSettings('show_articles', 'unread');"> <i class="fas fa-envelope"></i>      Show unread</a></li>
              <li><a class="dropdown-item <?php echo $show_active['read'];   ?>" href="javascript:updateSettings('show_articles', 'read');"> <i class="far fa-envelope-open"></i> Show read  </a></li>
              <li><a class="dropdown-item <?php echo $show_active['both'];   ?>" href="javascript:updateSettings('show_articles', 'both');"> <i class="fas fa-mail-bulk"></i>     Show both  </a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item <?php echo $order_active['time'];  ?>" href="javascript:updateSettings('order_articles', 'time');"><i class="fas fa-sort-numeric-down-alt"></i> Order by time</a></li>
              <li><a class="dropdown-item <?php echo $order_active['name'];  ?>" href="javascript:updateSettings('order_articles', 'name');"><i class="fas fa-sort-alpha-down"></i> Order by name</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="/personal/add_new_rss.php"><i class="fa fa-plus"></i> Add new RSS... </a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="/personal/settings.php"><i class="fas fa-tools"></i> Settings... </a></li>
            </ul>
          </div>
        </div>

      </div>
    </nav>
    

<?php

  $error = '';
echo "<script> var curr_feed_id = '$curr_feed_id' </script>";
// find prev and next feed ID
$prev_feed_id = ''; $next_feed_id = ''; $first_feed_id = ''; $last_feed_id = ''; $feed_found = false;
$prev_watch_id = ''; $next_watch_id = ''; $first_watch_id = ''; $last_watch_id = ''; $watch_found = false;
$prev_group_id = ''; $next_group_id = ''; $first_group_id = ''; $last_group_id = ''; $group_found = false;
foreach ($subscr_tree as $row) {
  $type = $row[2];
  $id = $row[3];
  if ($type === 'watch') {
    if (! $first_watch_id) { $first_watch_id = $id; }
    if ($id === $curr_watch_id) {
      $prev_watch_id = $last_watch_id;
      $watch_found = true;
    } elseif ($watch_found && ! $next_watch_id) {
      $next_watch_id = $id;
    }
    $last_watch_id = $id;
  } elseif ($type === 'subscr') {
    if (! $first_feed_id) { $first_feed_id = $id; }
    if ($id === $curr_feed_id) {
      $prev_feed_id = $last_feed_id;
      $feed_found = true;
    } elseif ($feed_found && ! $next_feed_id) {
      $next_feed_id = $id;
    }
    $last_feed_id = $id;
  } elseif ($type === 'group') {
    if (! $first_group_id) { $first_group_id = $id; }
    if ($id === $curr_group_id) {
      $prev_group_id = $last_group_id;
      $group_found = true;
    } elseif ($group_found && ! $next_group_id) {
      $next_group_id = $id;
    }
    $last_group_id = $id;
  }
}
if (! $prev_feed_id) { $prev_feed_id = $last_feed_id; }
if (! $next_feed_id) { $next_feed_id = $first_feed_id; }
if (! $prev_watch_id) { $prev_watch_id = $last_watch_id; }
if (! $next_watch_id) { $next_watch_id = $first_watch_id; }
if ($download_enabled ) {
  $enabled_class = ''; $disabled_class = ' class="hidden-element"';
} else {
  $disabled_class = ''; $enabled_class = ' class="hidden-element"';
}
echo
  "<script> var prev_feed_id = '$prev_feed_id'; ".
          " var next_feed_id = '$next_feed_id'; ".
          " var prev_watch_id = '$prev_watch_id'; ".
          " var next_watch_id = '$next_watch_id'; ".
          " var prev_group_id = '$prev_group_id'; ".
          " var next_group_id = '$next_group_id'; ".
          " var req_type = '$req_type'; ".
  "</script>";
echo '<H3 class="vertical-middle">';

if ($req_type == 'subscr') {
  if ($no_subscr_msg)
  {
  echo
  '<div class="alert alert-warning d-flex align-items-center" role="alert">
    <div>
      <i class="fas fa-exclamation-triangle"></i>&nbsp;  
      '.$no_subscr_msg .'
   </div>
  </div>';
    
  } else {
  echo 
  '<button type="button" class="btn btn-light btn-sm big-icon-button" onclick="markReadAndNext();"> <i class="far fa-check-square"></i> </button>'.
  '<button type="button" class="btn btn-light btn-sm big-icon-button" onclick="goToPrevFeed()"><i class="fas fa-chevron-left"></i></button>'.
  '<a role="button" class="btn btn-light btn-sm big-icon-button" data-bs-toggle="collapse" href="#feedSettings" aria-expanded="false" aria-controls="feedSettings">
    <i class="far fa-edit"></i>
  </a>'.
  '<button type="button" class="btn btn-light btn-sm big-icon-button" onclick="goToNextFeed()"><i class="fas fa-chevron-right"></i></button>'.
  "&nbsp;
  <span style='vertical-align: bottom;'>
    <i class='fas fa-rss'></i>
    <span class='position-relative translate-middle badge rounded-pill bg-primary' style='font-size: x-small; top: -8px;'>
      $articles_count
    </span>
  </span>
   <a href='$html_url' target='_blank' class='rss-title'>$rss_title</a>";
  }
} elseif ($req_type == 'watch') {
  echo 
  '<button type="button" class="btn btn-light btn-sm big-icon-button" onclick="markReadAndNext();"> <i class="far fa-check-square"></i> </button>'.
  '<button type="button" class="btn btn-light btn-sm big-icon-button" onclick="goToPrevWatch()"><i class="fas fa-chevron-left"></i></button>';
  if (! $rss_app->isReservedWatch($curr_watch_id)) {
    echo '<a role="button" class="btn btn-light btn-sm big-icon-button" href="edit_filter.php?watch_id='.$curr_watch_id.'"> <i class="far fa-edit"></i> </a>';
  }
  echo '<button type="button" class="btn btn-light btn-sm big-icon-button" onclick="goToNextWatch()"><i class="fas fa-chevron-right"></i></button>';
 echo '<i class="fas fa-filter"></i>&nbsp;'.$watch_title;
} elseif ($req_type == 'group') {
  echo 
  '<button type="button" class="btn btn-light btn-sm big-icon-button" onclick="markReadAndNext();"> <i class="far fa-check-square"></i> </button>'.
  '<button type="button" class="btn btn-light btn-sm big-icon-button" onclick="goToPrevGroup()"><i class="fas fa-chevron-left"></i></button>'.
  '<button type="button" class="btn btn-light btn-sm big-icon-button" onclick="goToNextGroup()"><i class="fas fa-chevron-right"></i></button>';
 echo '<i class="far fa-newspaper"></i>&nbsp;'.$req_id;
} else {
  echo 
  '<button type="button" class="btn btn-light btn-sm big-icon-button" onclick="markReadAndNext();"> <i class="far fa-check-square"></i> </button>';
 echo $watch_title;
}
  echo "</H3>";
  echo
 '<div class="collapse" id="feedSettings">
    <div class="card card-body">
      <div class="btn-toolbar mb-3" role="toolbar" aria-label="Enable feed">
        <div class="btn-group me-2" role="group" aria-label="Enable/disable">
          <button type="button" class="btn btn-outline-primary"
              onclick="enableFeed(\''.$curr_feed_id.'\', 0)">
            <i class="fas fa-ban"></i>
          </button>
          <button type="button" class="btn btn-outline-primary"
              onclick="enableFeed(\''.$curr_feed_id.'\', 1)">
            <i class="fas fa-check"></i>
          </button>
        </div>
        <label id="feed-enabled" '.$enabled_class.'>Enabled</label>
        <label id="feed-disabled" '.$disabled_class.'>Disabled</label>
      </div>
      <div class="d-grid gap-2 d-md-block" style="max-width:30em;">
        <div class="input-group mb-3">
          <span class="input-group-text" id="basic-addon1">RSS</span>
          <input type="text" class="form-control" placeholder="Feed URL"
              aria-label="RSS Feed URL" aria-describedby="basic-addon2"
              id="xmlUrl" value="'.$xmlUrl.'">
          <button class="btn btn-secondary" type="button"
            onclick="setFeedParam(\'xmlUrl\', \'xmlUrl\', \''.$curr_feed_id.'\');">
            <i class="far fa-thumbs-up"></i>
          </button>
        </div>
      </div>
      <div class="d-grid gap-2 d-md-block" style="max-width:30em;">
        <div class="input-group mb-3">
          <span class="input-group-text" id="basic-addon1">Title</span>
          <input type="text" class="form-control" placeholder="Feed Name"
              aria-label="RSS Feed Name" aria-describedby="basic-addon2"
              id="rss_title" value="'.$rss_title.'">
          <button class="btn btn-secondary" type="button"
            onclick="setFeedParam(\'rss_title\', \'title\', \''.$curr_feed_id.'\');">
            <i class="far fa-thumbs-up"></i>
          </button>
        </div>
      </div>
      <div class="d-grid gap-2 d-md-block" style="max-width:30em;">
        <div class="input-group">
          <span class="input-group-text" id="basic-addon1">Group</span>
          <input type="text" class="form-control" placeholder="Group name"
              aria-label="Where to place RSS Feed" aria-describedby="basic-addon2"
              id="new-rss-group" value="'.$rss_group.'">
          <button class="btn btn-secondary" type="button"
            onclick="setFeedParam(\'new-rss-group\', \'group\', \''.$curr_feed_id.'\');">
            <i class="far fa-thumbs-up"></i>
          </button>
          </div>
        </div>
        <select class="form-select mb-3" id="group-select" onchange="changeFeedGroup();" aria-label="Group select">';
        foreach ($groups as $group) {
           $sel = $group == $rss_group ? 'selected' : '';
           echo "<option $sel value='$group'>$group</option>";
        }
        echo '
        </select>

      </div>
      <div class="d-grid gap-2 d-md-block">
        <button type="button" class="btn btn-danger" onclick="deleteFeed(\''.$curr_feed_id.'\');" style="min-width:8em;">
          <i class="far fa-trash-alt"></i>
        </button>
      </div>
    </div>
  </div>';
if ($error) {
    $error = mb_substr($error, 0, 15);
    echo "<B>ERROR: $error</B><BR>\n";
    exit(1);
}
echo $rss_inactivity_warning;
$items = $rss_app->prepareForDisplay($items);
$rss_app->showItems($items);

# ------------[ End dynamic content ]---------------

?>
  </div>

    <!-- Floating button for paging and scroll up  -->
    <div id="floatingBottom">
      <button onclick="scrollToTop()" id="goToTopBtn" title="Go to top">
        <i class="fas fa-angle-double-up"></i>
      </button>
      <div class="btn-group btn-group-sm" role="group" aria-label="Paging">
      <button <?php if (! $prev_page) { echo 'style="display:none;"'; } ?> type="button" class="btn btn-light" onclick="goToPage('', -1);">
          <i class="fas fa-chevron-left"></i>
        </button>
        
          <?php
            if ($pages_range && count($pages_range) == 1) {
              echo '<button type="button" class="btn btn-light" disabled>1</button>';
            } else {
            echo '<select class="form-select form-select-sm" onchange="goToPage(this);" id="page_select" aria-label=".form-select-sm">';
            foreach ($pages_range as $page) {
              if (strpos($page, ':') !== false){
                echo "<option value=\"select\">...</option>\n";
              } else {
                $selected = ($page == $displayed_page)? ' selected' : '';
                echo "<option value=\"$page\"$selected>$page</option>\n";
              }
            }
            echo '</select>';
            }
          ?>
        <button <?php if (! $next_page) { echo 'style="display:none;"'; } ?> type="button" class="btn btn-light" onclick="goToPage('', 1);">
          <i class="fas fa-chevron-right"></i>
        </button>
      </div>
    </div>

    <!-- -- -- -- -- -- -- ( Modal windows ) -- -- -- -- -- -- -->

    <div class="modal fade" id="confirmationDialog" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title alert alert-danger"><i class="fas fa-exclamation-triangle"></i>&nbsp;Please confirm</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" id="confirmation-body">
            
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Dismiss</button>
            <button type="button" class="btn btn-primary" id="confirmation-button">Ok</button>
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

    <div class="modal" id="editArticleDialog" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Article properties</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" id="editArticleContent">
            <label>Preparing...</label>
            <img style="display: block; margin-left: auto; margin-right: auto;" src="../img/processing_bar.gif" >
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Dismiss</button>
            <button type="button" class="btn btn-primary" onclick="saveArticleChanges();">Apply changes</button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal" id="processingDialog" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Processing, please wait...</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <img style="display: block; margin-left: auto; margin-right: auto;" src="../img/processing_bar.gif" >
          </div>
          <div class="modal-footer">
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
            <label class="mb-3">Find in article title or body</label>
            <input type="text" class="form-control" id="text-to-find">
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="button" class="btn btn-primary" onclick="triggerSearch();">Start search</button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="searchTitleDialog" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Search article</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="text" class="form-control mb-3" id="title-text-to-find" />
            <button type="button" class="btn btn-primary" onclick="triggerTitleSearch('');">In FreeRSS</button>
            <button type="button" class="btn btn-primary" onclick="triggerTitleSearch('google');">In Google</button>
            <button type="button" class="btn btn-primary" onclick="triggerTitleSearch('kinopoisk');">In KinoPoisk</button>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Dismiss</button>
          </div>
        </div>
      </div>
    </div>

  </body>
</html>
