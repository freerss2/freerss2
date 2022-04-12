<!--/ Project:   freerss        \--
  --| Author:    Felix Liberman |--
  --| Subsystem: UI             |--
  --\ Function:  show help doc  /-->


<?php
  define('SOURCE_LEVEL', 1);
  $INCLUDE_PATH = str_repeat('../', SOURCE_LEVEL) . 'php_lib';

  include "$INCLUDE_PATH/rss_app.php";
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

    <title>Free RSS - Documentation</title>
  </head>
  <body>

    <!-- Optional JavaScript; choose one of the two! -->

    <!-- Option 1: Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>

    <!-- Option 2: Separate Popper and Bootstrap JS -->
    <!--
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js" integrity="sha384-7+zCNj/IqJ95wo16oMtfsKbZ9ccEh31eOz1HGyDuCQ6wgnyJNSYdrPa03rtR1zdB" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js" integrity="sha384-QJHtvGhmr9XOIpI6YVutG+2QOK9T+ZnN4kzFN1RtK3zEFEIsxhlmWl5/YESvpZ13" crossorigin="anonymous"></script>
    -->

    <div id="main">
      <nav class="navbar sticky-top navbar-dark bg-dark">
        <div>
          <button class="openbtn" onclick="history.back()"><i class="fas fa-chevron-left"></i></button>
          <span class="navbar-brand">&nbsp;<a href="/" class="navbar-brand">Free RSS</a>Documentation</span>
        </div>
      </nav>
      <a name="motivation"></a>
      <H1>Motivation</H1>
      <p>During last years every one realized the importance of correct information sources. When we say "correct", we are not talking about the quality of one media or blog, but about a variety of opinions. Only by studying the maximum range of interpretations of the same event, one can make a balanced assessment of reality. FreeRSS, like other news aggregators, provides you such technical capability in modern-style and friendly way. It is up to you to decide whether to remain among the people subject to conspiracy theories and brainwashing, or to take your first step towards informational freedom.</p>
      <a name="about"></a>
      <H1>About</H1>
      <p>Free RSS2 is a successor to <a href="http://felixl.coolpage.biz/free_rss/" target="_blank">Free RSS1 project</a>. It helps to keep in touch with news, published on your favorite sites.</p>
      <p>You don't need to travel over list of sites, trying to find something new and really interesting. RSS opens for everyone a new world, free of annoying ads, banners and irrelevant topics.</p>
      <p>But even RSS is not ideal - sometime on the same news channel you can receive all topics as a mix: sport, politics, finance, entertainment... And several channels can cover the same topics in parallel. Naturally, you want to get all news of the same subject together, regardless of origin, while news about irrelevant topics should be simply muted.<p>
      <p>This is the goal of Free RSS project: collect articles from different sites, filter-out unwanted content (by tags or keywords), and group the rest in handy "newspapers" - each on specific subject</p>
      <a name="terminology"></a>
      <H1>Terminology</H1>
      <p>Term "Feed" is a short of "RSS Feed". This way we'll refer a link where news could be downloaded, and also the articles, received from it</p>
      <p>Well-organized websites are publishing such URL on their homepage. This link commonly looks like <i class="fa fa-rss"></i></p>
      <p>Articles are very similar to emails: it has title, origin and body. In addition, some articles could be marked with tags. Article titles allow to get a general idea of the content. By clicking on title, one can dive into the article content, or even follow the link and open original site in separate window.</p>
      <p>For better management, the feeds could be arranged in "groups" by their origin, like: news, blogs, humor, general.</p>
      <p>In addition, it is possible to create own "rules" for marking article with unobvious topic. For example, news-related site can publish some updates about finance, leisure, politics, entertainment, crime... The article tag (if exist) may serve as a pattern for creating respective rule, like: "mark as music if title match *concert* or tag = performers". Articles that have received such a classification will be displayed in so-called "watch" named "music". Also it's possible to define rules for removing articles, related to irrelevant topic ("trash" watch).</p>
      <H1>Requirements</H1>
      <p>Free RSS uses a browser-based GUI with design adaptive for screen-size. You can connect to this service from any place in the world, using any web browser, on any platform: desktop, mobile, tablet, smart-TV. Naturally, it should be a modern browser with HTML5 support and enabled dynamic content. Due to lack of compatibility, full functionality on IE and other MS browsers is not guaranteed.</p>
      <a name="start"></a>
      <H1>Getting started</H1>
      <p>There are two possible ways for filling-up personal list of subscriptions: import from OPML file or enter their links one-by-one.</p>
      <p>If you know what's OPML - just go to "settings" and upload your OPML file. Please be careful - this operation removes all existing feeds definitions, so it could be used for recovery, but should not repeated in regular circumstances.</p>
      <p>Now let's learn how to add single RSS subscription to FreeRSS reader. First, you've to copy the feed URL to clipboard. Then select from application menu "Add new RSS" and paste the URL in first textbox. You can add some informal title, but it's not critical: the original title will be read from RSS, and you can always rename it. The "group" selection is also optional, but it could be nice to place new RSS under right origin.</p>
      <H1>Keyboard shortcuts</H1>
      <p>For making better user experience on bigger screen with large distances between control buttons, we added some keyboard shortcuts:</p>
      <table>
        <tr><th>Keystroke</th><th>Function</th></tr>
        <tr><td>ArrowRight</td><td>Open current article</td></tr>
        <tr><td>ArrowLeft</td><td>Collapse current article</td></tr>
        <tr><td>Ctrl/ArrowRight</td><td>Go to next RSS</td></tr>
        <tr><td>Ctrl/ArrowLeft</td><td>Go to previous RSS</td></tr>
        <tr><td>Ctrl/ArrowDown</td><td>Mark current article as "read" and move to next one</td></tr>
        <tr><td>Alt/H</td><td>Jump to service homepage</td></tr>
        <tr><td>Ctrl/Z</td><td>Mark all articles on current page as "read"</td></tr>
      </table>
      <H1>Technical details and source code</H1>
      <p>This is a FREE Open Source project. All code (excluding hosting-specific credentials) is available online on <a href="https://github.com/freerss2/freerss2" target="_blank">GitHub</a></p>
      <p>The code is rewritten from scratch using only DB schema from Free RSS 1. Since this is online multy-user system, all tables got extra "user_id" column. Naturally, the programming language was changed from Perl to PHP, and SQL engine from SQLite3 to MySQL. In addition, for getting modern look-n-feel style, all GUI part styled with latest <a href="https://getbootstrap.com/" target="_blank">Bootstrap CSS-classes</a> and <a href="https://fontawesome.com/" target="_blank">FontAwesome</a> icons set.</p>
      <a name="author"></a>
      <H1>Contacting author</H1>
      <p>Feel free to open bug report or improvement suggestion via <a href="https://github.com/freerss2/freerss2/issues/new" target="_blank">GitHub</a></p>
      <p>Please see my contact email on <a href="http://felixl.coolpage.biz/" target="_blank">personal homepage</a></p>
    </div>

  </body>
</html>
