
<!--/ Project:   freerss        \--
  --| Author:    Felix Liberman |--
  --| Subsystem: UI             |--
  --| Function:  personal area  |--
  --\ Function:  edit highlight /-->

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

  $keyword_req = $_GET['keyword_id'];

  $keywords_info = $rss_app->getKeywords();
  $active_keyword = Null;
  $class_style = '';
  $enabled_bold = '';
  $enabled_italic = '';
  $enabled_underscore = '';
  $use_fg_color = '';
  $use_bg_color = '';
  $fg_color = '#000000';
  $bg_color = '#ffffff';
  $init_settings = array();
  foreach ($keywords_info as $rec) {
    $keyword = $rec['keyword'];
    if ($keyword_req && $keyword == $keyword_req) {
      $active_keyword = $keyword;
      $class_style = $rec['class_style'];
      $enabled_bold = $rec['bold'] ? 'checked' : '';
      $enabled_italic = $rec['italic'] ? 'checked' : '';
      $enabled_underscore = $rec['underscore'] ? 'checked' : '';
      $use_fg_color = $rec['fg_color'] ? 'checked' : '';
      $use_bg_color = $rec['bg_color'] ? 'checked' : '';
      if ($rec['fg_color']) { $fg_color = $rec['fg_color']; }
      if ($rec['bg_color']) { $bg_color = $rec['bg_color']; }
      $init_settings = $rec;
      break;
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
    <link href="../style/bootstrap_5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">

    <!-- Fontawesome -->
    <link rel="stylesheet" href="../style/fontawesome/css/all.css">
    <link rel="icon" href="/img/favicon.ico">

    <!-- App styles -->
    <link rel="stylesheet" href="../style/main_screen.css<?php echo $VER_SUFFIX;?>">

    <title>Free RSS edit keyword highlights</title>
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
    <!-- JS data init -->
    <script>
        var init_settings = <?php echo json_encode($init_settings); ?> ;
        initHighlightSettings( init_settings );
    </script>

    <div id="main">
    <nav class="navbar sticky-top navbar-dark bg-dark">
       <button class="openbtn" onclick="history.back()" title="Go back">
         <i class="fas fa-chevron-left"></i>
       </button>
       <span class="navbar-brand">Free RSS keyword highlights</span>
       <div class="btn-group" role="group" aria-label="toolbar group">
         <a class="btn btn-secondary btn-md" href="../help#WatchFilters" title="Read about watches (filters)">
           <i class="fa fa-question"></i>
         </a>
       </div>
    </nav>

      <div class="container-fluid">
        <span style="display:inline-block; max-width:70%; margin-right:1em;">
          <h3>Keywords to highlight</h3>

          <ul class="nav nav-pills flex-column">
            <?php
              $active = is_null($active_keyword) ? 'active' : '';
              $show_all_edit = is_null($active_keyword) ? 'visually-hidden' : '';
              echo "<li class=\"nav-item\">\n";
              echo "<a class=\"nav-link $active\" href=\"edit_highlight.php#edit\">= New =</a>\n";
              echo "</li>\n";
              foreach ($keywords_info as $rec) {
                  $keyword = $rec['keyword'];
                  $title =  $rec['keyword'];
                  $active = $keyword === $active_keyword ? 'active' : '';
                  echo "<li class=\"nav-item btn-group\">\n";
                  echo "<a class=\"nav-link $active no-text-overflow col-8\" href=\"edit_highlight.php?keyword_id=$keyword#edit\">$title</a>\n";
                  echo "<a class=\"btn btn-light col-2\" onclick=\"cloneHighlight('$keyword')\" style=\"padding-left: 0.2rem;padding-right: 0.2rem;\"><i class=\"far fa-copy\"></i></a>\n";
                  echo "<a class=\"btn btn-light col-2\" onclick=\"deleteHighligt('$keyword')\" style=\"padding-left: 0.2rem;padding-right: 0.2rem;\"><i class=\"far fa-trash-alt\"></i></a>\n";

                  echo "</li>\n";
              }
            ?>
          </ul>
        </span>
        <span style="display:inline-block; vertical-align: top; max-width: 800px;">
            <A name="edit">
            <H1>&nbsp;</H1>
            </A>

          <div class="input-group mb-3 short-input">
            <span class="input-group-text">Keyword</span>
            <input type="text" class="form-control" value="<?php echo $active_keyword ?>" id="keyword_name" style="min-width: 8rem;" placeholder="Keyword to highlight" oninput="changeHighlightSetting('keyword', this.value);">
            <button type="button" class="btn btn-outline-secondary" onclick="saveHighlight('<?php echo $active_keyword; ?>')">
                Save
            </button>
          </div>
          <?php
              $show_keyword = is_null($active_keyword) ? '=(???)=' : $active_keyword;
                echo "<p>Some text with <span id='style_preview' style='".$class_style."'>$show_keyword</span> inside it...</p>";
          ?>

          <div class="input-group mb-3 short-input">
            <span class="input-group-text">Foreground</span>
            <input type="color" class="form-control" style="min-height: 2.5rem;" value="<?php echo $fg_color; ?>"
             id="foreground_select" onchange="changeHighlightSetting('fg_color', this.value);"
             <?php echo ($use_fg_color)? '' : 'disabled'  ?> >
            <div class="input-group-text">
              <input class="form-check-input mt-0" type="checkbox" value="" aria-label="Enable/disable coloring"
               onclick="disableColorSelect('use_fg_color', 'foreground_select'); updateHighlightPreview();"
               id="use_fg_color" <?php echo $use_fg_color; ?> >
            </div>
          </div>

          <div class="input-group mb-3 short-input">
            <span class="input-group-text">Background</span>
            <input type="color" class="form-control" style="min-height: 2.5rem;" value="<?php echo $bg_color; ?>"
             id="background_select" onchange="changeHighlightSetting('bg_color', this.value);"
             <?php echo ($use_bg_color)? '' : 'disabled'  ?> >
            <div class="input-group-text">
              <input class="form-check-input mt-0" type="checkbox" value="" aria-label="Enable/disable coloring"
               onclick="disableColorSelect('use_bg_color', 'background_select'); updateHighlightPreview();"
               id="use_bg_color" <?php echo $use_bg_color; ?> >
            </div>
          </div>

          <div class="input-group mb-3 short-input">
            <span class="input-group-text" style="width: 87%">Style with&nbsp;<b>BOLD</b></span>
            <div class="input-group-text">
              <input class="form-check-input mt-0" type="checkbox" id="styleBold" <?php echo "$enabled_bold"; ?>
               onclick="changeHighlightSetting('bold', '');" >
            </div>
          </div>

          <div class="input-group mb-3 short-input">
            <span class="input-group-text" style="width: 87%">Style with&nbsp;<i>ITALIC</i></span>
            <div class="input-group-text">
              <input class="form-check-input mt-0" type="checkbox" id="styleItalic" <?php echo "$enabled_italic"; ?>
               onclick="changeHighlightSetting('italic', '');" >
            </div>
          </div>

          <div class="input-group mb-3 short-input">
            <span class="input-group-text" style="width: 87%">Style with&nbsp;<u>UNDERSCORE</u></span>
            <div class="input-group-text">
              <input class="form-check-input mt-0" type="checkbox" id="styleUnderscore" <?php echo "$enabled_underscore"; ?>
               onclick="changeHighlightSetting('underscore', '');" >
            </div>
          </div>

          <!--
          <div  class="mb-3 short-input">
            <input type="color" id="foreground_select"
             onchange="changeHighlightSetting('fg_color', this.value)"
             value="<?php echo $fg_color; ?>"
             <?php echo ($default_fg_color)? 'disabled' : ''  ?> />
            <label>Foreground color</label>
            <div class="form-check mb-3 form-switch" onclick="changeHighlightSetting('fg_color', ''); disableColorSelect('default_fg_color', 'foreground_select');">
              <input class="form-check-input" type="checkbox" id="default_fg_color" <?php echo $default_fg_color; ?> />
              <label class="form-check-label" for="default_fg_color">Default</label>
            </div>
          </div>

          <div  class="mb-3 short-input">
            <input type="color" id="background_select"
             onchange="changeHighlightSetting('bg_color', this.value)"
             value="<?php echo $bg_color; ?>"
             <?php echo ($default_bg_color)? 'true' : ''  ?> />
            <label>Background color</label>
            <div class="form-check mb-3 form-switch" onclick="changeHighlightSetting('bg_color', ''); disableColorSelect('default_bg_color', 'background_select');">
              <input class="form-check-input" type="checkbox" id="default_bg_color" <?php echo $default_bg_color; ?> />
              <label class="form-check-label" for="default_bg_color">Default</label>
            </div>
          </div>

          <div class="form-check mb-3 form-switch" onclick="changeHighlightSetting('bold', '');">
            <input class="form-check-input" type="checkbox" id="styleBold" <?php echo "$enabled_bold"; ?> />
            <label class="form-check-label" for="styleBold"></label>
          </div>
          <div class="form-check mb-3 form-switch" onclick="changeHighlightSetting('italic', '');">
            <input class="form-check-input" type="checkbox" id="styleItalic" <?php echo "$enabled_italic"; ?> />
            <label class="form-check-label" for="styleItalic">Style with <i>ITALIC</i></label>
          </div>
          <div class="form-check mb-3 form-switch" onclick="changeHighlightSetting('underscore', '');">
            <input class="form-check-input" type="checkbox" id="styleUnderscore" <?php echo "$enabled_underscore"; ?> />
            <label class="form-check-label" for="styleUnderscore">Style with <u>UNDERSCORE</u></label>
          </div>
          -->
        </span>
      </div>

    </div>

    <!-- -- -- -- -- -- -- ( Modal windows ) -- -- -- -- -- -- -->

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

    <?php html_include('confirmation_dialog.html'); ?>

    <?php html_include('error_dialog.html'); ?>

  </body>
</html>
