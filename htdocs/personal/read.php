
<!--/ Project:   freerss        \--
  --| Author:    Felix Liberman |--
  --| Subsystem: UI             |--
  --| Function:  personal area  |--
  --\ Function:  main           /-->

<?php
  session_start();

  define('SOURCE_LEVEL', 1);
  $INCLUDE_PATH = str_repeat('../', SOURCE_LEVEL) . 'php_lib';

  include "$INCLUDE_PATH/rss_app.php";
  if ( !$_SESSION || !$_SESSION['user_id'] ) {
    $_SESSION = array(
      'return_link' =>
      (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"
    );
    header("Location: /login/connect.php"); /* Redirect browser */
    exit();
  }

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

  $rss_app->saveLastLink();

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

// "feed edit" / "group edit" popup trigger
$edit_feed = '';
$edit_group = '';
if ($_GET['open'] == 'edit') {
  $edit_feed = $req_type == 'subscr' ? 'show' : '';
  $edit_group = $req_type == 'group' ? 'editGroup(\''.$req_id.'\');' : '';
}
if ( $promptForInit ) {
  $edit_group = '';
}

if ($req_type == 'group' && $req_id == 'all') {
  $req_type = 'watch';
}

$no_subscr_msg = "";
$feed_rtl = false;
if($req_type == 'watch') {
  $req_feed_id = null;
  $curr_watch_id = '';
  $curr_group_id = '';
  if ($req_id == 'search') {
    $watch_title = ucfirst($req_id);
    $watch_description = "results of pattern search in articles titles and content";
    $pattern = $_GET['pattern'];
    $items = $rss_app->findItems($pattern);
  } else {
    $curr_watch_id = $req_id;
    list($watch_title, $watch_description, $items) = $rss_app->retrieveWatchItems($req_id);
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
    $feed_rtl = $rss_info['rtl'];
    $download_enabled = $rss_info['download_enabled'];
    $fts = $rss_info['mapping'] ? 1 : 0;
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
    <link href="../style/bootstrap_5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">

    <!-- Fontawesome -->
    <link rel="stylesheet" href="../style/fontawesome/css/all.css">
    <link rel="icon" type="image/x-icon" href="../img/favicon.ico">

    <!-- App styles -->
    <link rel="stylesheet" href="../style/main_screen.css<?php echo $VER_SUFFIX;?>">
    <style>
    <?php
      $keywords = $rss_app->getKeywords();
      foreach($keywords as $rec) {
        $class_name = $rec['class_name'];
        $class_style = $rec['class_style'];
        echo ".$class_name {\n";
        echo "  $class_style\n";
        echo "}\n";
      }
    ?>
    </style>

    <title>Free RSS</title>
  </head>
  <body onload="setLoginAuthToken(); <?php echo $promptForInit; echo $edit_group; ?>">

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
      var enable_push_reminders =
          <?php echo $statistics['enable_push_reminders'] ?> ;
      if ( update_required && enable_push_reminders ) {
        systemPopupNotification(
          'FreeRSS2 notification',
          'Feeds update required', function(n,c) { refreshRss(); },
          30000);
      }
    </script>
    <script> setArticlesContext(1); bindKeysForFeeds(); </script>

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
  /*  ----------( start title )----------- */
if ($req_type == 'subscr' && $no_subscr_msg) {
  $overflow_class = '';
} else {
  $overflow_class = 'no-text-overflow';
}
echo '<H3 class="vertical-middle '.$overflow_class.'">';

$group_edit_button = '';
$watch_edit_button = '';
$mark_read_and_next =
  '<span class="btn-group">
   <button type="button"
      class="btn btn-light btn-sm big-icon-button" style="margin-right: 0;"
      title="Mark all articles on this page as \'read\', excluding bookmarked ones"
      onclick="markReadAndNext();"> <i class="far fa-check-square"></i> </button>
  <button type="button"
      class="btn btn-light btn-sm"
      onclick="startMarkAllDialog();"
  >
    <i class="fas fa-ellipsis-v"></i>
  </button>
  </span>';
$reload_button =
  '<button id="reload_button" type="button"
      class="btn btn-light btn-sm big-icon-button"
      onclick="showUpdatingDialog(); window.location.reload();"> <i class="fa fa-redo-alt"></i> </button>';
  /* -------------( choose title content )------------ */
if ($req_type == 'subscr') {
  if ($no_subscr_msg) {
    echo
    '<div class="alert alert-warning d-flex align-items-center" role="alert">
      <div>
        <i class="fas fa-exclamation-triangle"></i>&nbsp;
        '.$no_subscr_msg .'
     </div>
    </div>';

  } else {
    $prev_feed_button =
      '<button type="button" class="btn btn-light btn-sm big-icon-button" onclick="goToPrevFeed()" title="Go to previos feed"><i class="fas fa-chevron-left"></i></button>';
    $next_feed_button =
      '<button type="button" class="btn btn-light btn-sm big-icon-button" onclick="goToNextFeed()" title="Go to next feed"><i class="fas fa-chevron-right"></i></button>';
    $title_icon =
      "<span style='vertical-align: bottom;'>
         <i class='fas fa-rss'></i>
         <span id='articles_count' class='position-relative translate-middle badge rounded-pill bg-primary' style='font-size: x-small; top: -8px;'>
           $articles_count
         </span>
         </span>";
    $title_button =
      "<a role=\"button\" class='rss-title' data-bs-toggle=\"collapse\" href=\"#feedSettings\" aria-expanded=\"false\" aria-controls=\"feedSettings\">" .
        $title_icon . $rss_title .
      "</a>";
    echo
      $mark_read_and_next . $reload_button . $prev_feed_button . $next_feed_button . "&nbsp;" . $title_button;
  }
} elseif ($req_type == 'watch') {
  /* create link to panel with watch description and button opening edit screen (or "built-in" comment) */
  $prev_watch_button =
    '<button type="button" class="btn btn-light btn-sm big-icon-button" onclick="goToPrevWatch()" title="Go to previos watch"><i class="fas fa-chevron-left"></i></button>';
  $next_watch_button =
    '<button type="button" class="btn btn-light btn-sm big-icon-button" onclick="goToNextWatch()" title="Go to next watch"><i class="fas fa-chevron-right"></i></button>';
  $title_button =
    "<a role=\"button\" class='rss-title' data-bs-toggle=\"collapse\" href=\"#watchSettings\" aria-expanded=\"false\" aria-controls=\"watchSettings\">" .
    '<i class="fas fa-filter"></i>&nbsp;'.$watch_title .
    "</a>";
  echo
    $mark_read_and_next . $reload_button . $prev_watch_button . $next_watch_button . $title_button;
  if (! $rss_app->isReservedWatch($req_id)) {
    $watch_edit_button =
      '<div class="mb-3">' .
      '<b>'.$watch_title.'</b> is a user-defined watch. It contains '.$watch_description.'. You can fine-tune this condition here: ' .
      '<a role="button" class="btn btn-light btn-sm big-icon-button" href="edit_filter.php?watch_id='.$req_id.'"> <i class="far fa-edit"></i> </a>' .
      '</div>';
  } else {
    $watch_edit_button =
      '<div class="mb-3">' .
      '<b>'.$watch_title.'</b> is a pre-defined watch for ' . $watch_description . 
      '</div>';
  }
} elseif ($req_type == 'group') {
  /* create group-editing panel and open it on group title click */
  $prev_group_button = 
    '<button type="button" class="btn btn-light btn-sm big-icon-button" onclick="goToPrevGroup()" title="Go to previos feeds group"><i class="fas fa-chevron-left"></i></button>';
  $next_group_button =
    '<button type="button" class="btn btn-light btn-sm big-icon-button" onclick="goToNextGroup()" title="Go to next feeds group"><i class="fas fa-chevron-right"></i></button>';
  $title_button =
    "<a role=\"button\" class='rss-title' data-bs-toggle=\"collapse\" href=\"#groupSettings\" aria-expanded=\"false\" aria-controls=\"groupSettings\">" .
      '<i class="far fa-newspaper"></i>&nbsp;'.
      $req_id .
    "</a>";
  echo
    $mark_read_and_next . $reload_button . $prev_group_button . $next_group_button . $title_button;
  if($req_id != 'all') {
    $group_edit_button =
      '<div class="mb-3">' .
      'Use this button to customise the group: <button role="button" class="btn btn-light btn-sm big-icon-button" onclick="editGroup(\''.$req_id.'\')"> <i class="far fa-edit"></i> </button>' .
      '</div>';
  }
} else {
  echo $mark_read_and_next. $reload_button;
  echo $watch_title;
}
  echo "</H3>";
  /*  ----------( Dynamic panels under title )----------- */
  echo
 '<div class="collapse '.$edit_feed.'" id="feedSettings">
    <div class="card-body mb-3">
      <div class="mb-3">
        <p>This is a site "<a href="'.$html_url.'" target="_blank">'.$rss_title.'</a>" feed.
        It\'s collected from site <a href="'.$xmlUrl.'" target="_blank">RSS</a>.</p>
       <div>
         <a role="button" data-bs-toggle="collapse" href="#feedSettings" aria-expanded="false" aria-controls="feedSettings">
           <i class="fas fa-chevron-up"></i>
         </a>
       </div>
      </div>
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
      <div class="d-grid gap-2 d-md-block mb-3 short-input">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" '.($feed_rtl?'checked':'').' id="rtl_feed" onclick="setFeedParam(\'rtl_feed\', \'rtl\', \''.$curr_feed_id.'\');">
          <label class="form-check-label" for="rtl_feed">RTL (Right-To-Left) Language</label>
        </div>
      </div>
      <div class="d-grid gap-2 d-md-block short-input" >
        <div class="input-group mb-3">
          <select class="form-select" onchange="changedFeedSourceType(this.value)" style="max-width: 30%;" id="sourceType">
              <option '.($fts?'':'selected').' value="rss">RSS source</option>
              <option '.($fts?'selected':'').' value="site_to_feed">Site-to-feed</option>
          </select>

          <input type="text" class="form-control '.($fts?'d-none':'d-block').'"
              placeholder="Feed Source URL"
              aria-label="RSS Source URL" aria-describedby="basic-addon2"
              id="xmlUrl" value="'.$xmlUrl.'"
          >
          <button class="btn btn-outline-secondary '.($fts?'d-block':'d-none').'"
              type="button"
              id="edit-settings" style="display:none;min-width: 70%;"
              onclick="openSiteToFeedEdit(\''.$curr_feed_id.'\', \'\');"
          >
                Define...
          </button>
          <button class="btn btn-secondary '.($fts?'d-none':'d-block').'"
            type="button" id="save-url-button"
            onclick="setFeedParam(\'xmlUrl\', \'xmlUrl\', \''.$curr_feed_id.'\');">
            Save
          </button>

        </div>
      </div>

      <div class="d-grid gap-2 d-md-block short-input" >
        <div class="input-group mb-3">
          <span class="input-group-text" id="basic-addon1">Title</span>
          <input type="text" class="form-control" placeholder="Feed Name"
              aria-label="RSS Feed Name" aria-describedby="basic-addon2"
              id="rss_title" value="'.$rss_title.'">
          <button class="btn btn-secondary" type="button"
            onclick="setFeedParam(\'rss_title\', \'title\', \''.$curr_feed_id.'\');">
            Save
          </button>
        </div>
      </div>
      <div class="d-grid gap-2 d-md-block short-input" >
        <div class="input-group">
          <span class="input-group-text" id="basic-addon1">Group</span>
          <input type="text" class="form-control" placeholder="Associate feed with group"
              aria-label="Where to place RSS Feed" aria-describedby="basic-addon2"
              id="new-rss-group" value="'.$rss_group.'">
            <button class="btn btn-outline-secondary dropdown-toggle" type="button"
              data-bs-toggle="dropdown" aria-expanded="false"></button>
            <ul class="dropdown-menu dropdown-menu-end">
            <li><span class="dropdown-item-text">Select from existing</span></li>
              ';
              foreach ($groups as $group) {
                $sel = $group == $rss_group ? 'active' : '';
                echo "<li><a class=\"dropdown-item $sel\" href=\"javascript:changeFeedGroup('$group');\">$group</a></li>";
              }
            echo '
            </ul>
          <button class="btn btn-secondary" type="button"
            onclick="setFeedParam(\'new-rss-group\', \'group\', \''.$curr_feed_id.'\');">
            Save
          </button>
          </div>
        </div>

      </div>
      <div class="d-grid gap-2 d-md-block mb-3">
        <button type="button" class="btn btn-danger" onclick="deleteFeed(\''.$curr_feed_id.'\');" style="min-width:8em;">
          <i class="far fa-trash-alt"></i>
        </button>
      </div>
    </div>
  </div>';
echo
  '<div class="collapse" id="groupSettings">
     <div class="card-body mb-3">
       <p class="mb-3"><b>'.$req_id.'</b> is a group of feeds. Here you can browse articles, collected from similar sites.</p>
       '.$group_edit_button.'
       <div>
         <a role="button" data-bs-toggle="collapse" href="#groupSettings" aria-expanded="false" aria-controls="groupSettings">
           <i class="fas fa-chevron-up"></i>
         </a>
       </div>
     </div>
   </div>';
echo
  '<div class="collapse" id="watchSettings">
     <div class="card-body mb-3">
       '.$watch_edit_button.'
       <div>
         <a role="button" data-bs-toggle="collapse" href="#watchSettings" aria-expanded="false" aria-controls="watchSettings">
           <i class="fas fa-chevron-up"></i>
         </a>
       </div>
     </div>
   </div>';
  
if ($error) {
    $error = mb_substr($error, 0, 15);
    echo "<B>ERROR: $error</B><BR>\n";
    exit(1);
}
echo $rss_inactivity_warning;
# ------------[ End dynamic content ]---------------

# ------------------( items )-------------------------
$items = $rss_app->prepareForDisplay($items);
$rss_app->showItems($items, $mark_read_and_next . $reload_button);

# ------------[ End of items ]---------------

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

    <?php html_include('confirmation_dialog.html'); ?>

    <?php html_include('error_dialog.html'); ?>

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

    <div class="modal" id="editGroupModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Change feeds group</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div>
              <label>Name:</label>&nbsp;<input id="group_id" value=""></input>
            </div>
            <div id="editGroupContent">
              <label>Preparing...</label>
              <img style="display: block; margin-left: auto; margin-right: auto;" src="../img/processing_bar.gif" >
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Dismiss</button>
            <button type="button" class="btn btn-primary" onclick="saveGroupChanges();">Apply changes</button>
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

    <div class="modal fade" id="markAllDialog" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Massive mark operations</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="container">
              <div class="row">
                <div class="col">
                  <i class="far fa-envelope-open me-2 mb-3"></i>
                  <button class="btn btn-outline-secondary mb-3" style="min-width: 11rem;" onclick="markAll('read');">
                    Mark as read
                  </button></div>
                <div class="col">
                  <i class="fa fa-star me-2 mb-3" style="color:blue;"></i>
                  <button class="btn btn-outline-secondary mb-3" style="min-width: 11rem;" onclick="markAll('bookmark');">
                    Bookmark articles
                  </button></div>
              </div>
              <div class="row">
                <div class="col">
                  <i class="fas fa-envelope me-2 mb-3"></i>
                  <button class="btn btn-outline-secondary mb-3" style="min-width: 11rem;" onclick="markAll('unread');">
                    Mark as unread
                  </button></div>
                <div class="col">
                  <i class="far fa-star me-2 mb-3" style="color:gray;"></i>
                  <button class="btn btn-outline-secondary mb-3" style="min-width: 11rem;" onclick="markAll('unbookmark');">
                    Remove bookmarks
                  </button></div>
              </div>
              <div class="row">
                <div class="col">
                  <i class="fas fa-envelope me-2 mb-3"></i>
                  <button class="btn btn-outline-secondary mb-3" style="min-width: 11rem;" onclick="markAll('toggleread');">
                    Toggle read
                  </button></div>
                <div class="col">
                  <i class="far fa-star me-2 mb-3" style="color:gray;"></i>
                  <button class="btn btn-outline-secondary mb-3" style="min-width: 11rem;" onclick="markAll('togglebookmark');">
                    Toggle bookmarks
                  </button></div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Dismiss</button>
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

    <div class="modal fade" id="pageSelectDialog" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Go to page</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <label class="mb-3">Page number:</label>
            <input type="number" min="1" class="form-control" id="page-number">
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="goToInputPage();">Go there</button>
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

    <?php html_include('edit_site_to_feed_dialog.html'); ?>

    <textarea id="clipboardInput" style="display:none;"></textarea>

  </body>
</html>
