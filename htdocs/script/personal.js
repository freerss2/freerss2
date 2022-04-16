/* --------------- *\
   "Personal" page
   JavaScript code
\* --------------- */

var SEARCH_ENGINES = {
  'google': 'http://www.google.com/search?q=',
  'kinopoisk': 'http://www.kinopoisk.ru/index.php?first=no&what=&kp_query='
}

// semaphore for enabling keyboard shortucts in articles context only
var articles_context = false;

// ------------------( control nav appearance )------------------

var nav_visible = 0;

function toggleNav() {
  if (nav_visible) {
    closeNav();
  } else {
    openNav();
  }
}

function openNav() {
  document.getElementById("mySidebar").style.width = "250px";
  document.getElementById("main").style.marginLeft = "250px";
  nav_visible = 1;
}

function closeNav() {
  document.getElementById("mySidebar").style.width = "0";
  document.getElementById("main").style.marginLeft= "0";
  nav_visible = 0;
}

// ------------------( settings change callbacks )------------------

// Callback for settings change
function updateSettings(setting_name, setting_value) {
  // console.log("updateSettings("+setting_name+", "+setting_value+")");
  var url = '/api/settings/?set=' + setting_name + '&value=' + setting_value;
  httpGetAsync(url, function(buf){
    console.log(buf);
    window.location.reload();
  });
}

// Callback for settings change with element ID
function updateSettingsFrom(setting_name, setting_ID) {
  setting_value = document.getElementById(setting_ID).value;
  updateSettings(setting_name, setting_value);
}

// -------------------( Automatic refresh of main page )-------------

function refreshMainPage(initial) {
  var interactive_opened = nav_visible;
  var elm1 = document.getElementById('searchDialog');
  var elm2 = document.getElementById('updatingDialog');
  if (elm1) { interactive_opened ||= elm1.style['display'] == 'block'; }
  if (elm2) { interactive_opened ||= elm2.style['display'] == 'block'; }
  if (initial || interactive_opened) {
    setTimeout(refreshMainPage, 10*60*1000);
    return;
  }
  window.location.reload();
}

// -------------------( Scroll-up button )-------------------

// When user scrolls down 20px from the top of the document, show the button
var GO_TO_TOP_ID = 'goToTopBtn';

window.onscroll = function() {onWindowScrolled()};

// Callback for window scroll
// show/hide "go to top" button
function onWindowScrolled() {
    var go_to_top = document.getElementById(GO_TO_TOP_ID);
    if (! go_to_top) {
        // console.log("Failed to find in DOM id="+GO_TO_TOP_ID);
        return;
    }
    if (document.body.scrollTop > 20 || document.documentElement.scrollTop > 20) {
        go_to_top.style.display = "inline";
    } else {
        go_to_top.style.display = "none";
    }
}

// When user clicks on the button, scroll to the top of the document
function scrollToTop() {
    document.body.scrollTop = 0;
    document.documentElement.scrollTop = 0;
}

// -------------------( getting next/prev view )-------------------

// Go to next/previous feed
function goToPrevFeed() {
  if (! prev_feed_id) { return; }
  var curr_location = window.location.href;
  var new_url = curr_location.replace(/\?.*/, '') + '?type=subscr&id=' + prev_feed_id;
  window.location.href = new_url;
}

function goToNextFeed() {
  if (! next_feed_id) { return; }
  var curr_location = window.location.href;
  var new_url = curr_location.replace(/\?.*/, '') + '?type=subscr&id=' + next_feed_id;
  window.location.href = new_url;
}

// Go to next/previous watch
function goToPrevWatch() {
  if (! prev_watch_id) { return; }
  var curr_location = window.location.href;
  var new_url = curr_location.replace(/\?.*/, '') + '?type=watch&id=' + prev_watch_id;
  window.location.href = new_url;
}

function goToNextWatch() {
  if (! next_watch_id) { return; }
  var curr_location = window.location.href;
  var new_url = curr_location.replace(/\?.*/, '') + '?type=watch&id=' + next_watch_id;
  window.location.href = new_url;
}

// Go to next/previous group
function goToPrevGroup() {
  if (! prev_group_id) { return; }
  var curr_location = window.location.href;
  var new_url = curr_location.replace(/\?.*/, '') + '?type=group&id=' + prev_group_id;
  window.location.href = new_url;
}

function goToNextGroup() {
  if (! next_group_id) { return; }
  var curr_location = window.location.href;
  var new_url = curr_location.replace(/\?.*/, '') + '?type=group&id=' + next_group_id;
  window.location.href = new_url;
}

// get search URL by name
function getSearchUrl(search_name) {
  if (search_name in SEARCH_ENGINES) {
    return SEARCH_ENGINES[search_name];
  }
  return '';
}

// delete watch
function deleteWatch(watch_id) {
  var curr_location = window.location.href;
  var base_url = curr_location.replace(/edit_filter\.php.*/, '');
  var api_url = base_url + '../api/watch/delete/?watch_id=' + watch_id;
  var reply = httpGet(api_url);
  if ( reply.startsWith('Error') ) {
      // TODO: show it in a different way
      window.alert(reply);
      return;
  }
  window.location.href = '/personal/edit_filter.php';
}

// save watch name
function saveWatchName(watch_id) {
  var elm = document.getElementById('watch_name');
  if (! elm) { return; }
  new_watch_name = elm.value;
  if (! new_watch_name) { return; }
  console.log("saveWatchName("+watch_id+","+new_watch_name+")");
  var curr_location = window.location.href;
  var base_url = curr_location.replace(/edit_filter\.php.*/, '');
  var api_url = base_url + '../api/watch/'
  if (watch_id) {
    api_url += 'update/?watch_id=' + watch_id + '&name=' + new_watch_name;
  } else {
    api_url += 'create/?name=' + new_watch_name;
  }
  var reply = httpGet(api_url);
  if ( reply.startsWith('Error') ) {
      // TODO: show it in a different way
      window.alert(reply);
      return;
  }
  if (watch_id) {
    window.location.reload();
  } else {
    window.location.href = '/personal/edit_filter.php?watch_id='+reply;
  }
}

// open dialog box prompting for feeds init: [add link] or [upload OPML]
function promptForInit() {
  var promptForInit = new bootstrap.Modal(document.getElementById('promptForInit'), {focus: true});
  promptForInit.show();
}

// rerun watch filters
function rerunFilters() {
  // show modal "please wait"
  var refreshModal = new bootstrap.Modal(document.getElementById('updatingDialog'), {focus: true});
  refreshModal.show();
  // run API for reapplying filters
  httpGetAsync('/api/watch/rerun/', function(reply){
    refreshModal.hide();
    // go to homepage on completion
    if ( reply.startsWith('Error') ) {
      // TODO: show it in a different way
      window.alert(reply);
      return;
    }
    console.log(reply);
    // TODO: why reload?
    window.location.href = '/personal/';
  });
}

// delete rule in watch
// @param watch_id: watch where to delete rule
// @param rule_id: rule to delete
function deleteRule(watch_id, rule_id) {
  var curr_location = window.location.href;
  var base_url = curr_location.replace(/edit_filter\.php.*/, '');

  api_url = base_url +
    '../api/watch/rule/delete?watch_id=' + watch_id +
    '&rule_id=' + rule_id;
  var reply = httpGet(api_url);
  if ( reply.startsWith('Error') ) {
    // TODO: show it in a different way
    window.alert(reply);
    return;
  }
  console.log(reply);
  window.location.reload();
}

// add rule to current watch
// @param watch_id: watch where to add rule
// (rule name taken from input box)
function addRule(watch_id) {
  var elm = document.getElementById('new_rule');
  if (! elm) { return; }
  new_rule_name = elm.value;
  var curr_location = window.location.href;
  var base_url = curr_location.replace(/edit_filter\.php.*/, '');
  api_url = base_url +
    '../api/watch/rule/add?watch_id=' + watch_id +
    '&rule_name=' + new_rule_name;
  var reply = httpGet(api_url);
  if (reply.startsWith('Error')) {
    // TODO: show it in a different way
    window.alert(reply);
    return;
  }
  console.log(reply);
  window.location.reload();
}

// start 'rule edit' dialog
// @param watch_id: current watch ID
// @param rule_id: current rule ID
function openRuleEdit(watch_id, rule_id) {
  var curr_location = window.location.href;
  var base_url = curr_location.replace(/edit_filter\.php.*/, '');

  api_url = base_url +
    '../api/watch/rule/edit?watch_id=' + watch_id +
    '&rule_id=' + rule_id;
  var edit_code = httpGet(api_url);

  var ruleEditModal = new bootstrap.Modal(document.getElementById('ruleEditDialog'), {focus: true});
  edit_rule_id = rule_id;
  rule_edit = document.getElementById('rule-edit');
  rule_edit.innerHTML = edit_code;
  setArticlesContext(0);
  ruleEditModal.show();
}

// complete 'rule edit' and save result
// @param watch_id: current watch ID
// @param rule_id: current rule ID
// rule content is read from HTML elements by class names
function saveRule(watch_id, rule_id) {
  var rule_or_groups = document.getElementsByClassName('rule-or-group');
  var rule = Array();
  var rule_name = document.getElementById('rule_title').value;
  var group_limitation = document.getElementById('group-limitation').value;
  for (var i = 0; i < rule_or_groups.length; i++) {
    r_gr = rule_or_groups[i];
    var line = Array();
    for (var j = 0; j < r_gr.children.length; j++) {
      r_node = r_gr.children[j];
      if (! r_node.classList) { continue; }
      if (! r_node.classList.contains('rule-or-node') ) { continue; }
      node_attr = r_node.children[0].value;
      node_op = r_node.children[1].value.replace('MATCH', 'LIKE').replace('==', '=');
      node_val = r_node.children[2].value.replaceAll('*', '%');
      if (! node_attr) { continue; }
      line.push("`"+node_attr+"` "+node_op+" '"+node_val+"'");
    }
    if (line.length) {
      rule.push(line.join(' OR '));
    }
  }
  var result = {
    'watch_id': watch_id, 'rule_id': rule_id, 'rule_name': rule_name,
    'group_limitation': group_limitation,
    'where': rule};
  console.log(JSON.stringify(result));
  var post_url = '/api/watch/rule/update/';
  reply = httpPost(post_url, JSON.stringify(result));
  if (reply.startsWith('Error')) {
    // TODO: show it in a different way
    window.alert(reply);
    return;
  }
  console.log(reply);
  window.location.reload();
}

// Open import OPML dialog
function openImportModal() {
  var upoladOpmlDialog = document.getElementById('upoladOpmlDialog');
  var importOpmlModal = new bootstrap.Modal(upoladOpmlDialog, {focus: true});
  importOpmlModal.show();
}

// callback for article search selection
// @param selected_engine: search engine name (empty for search in articles)
// the pattern for search is taken from DOM element by ID
function triggerTitleSearch( selected_engine ) {
  var elm = document.getElementById('title-text-to-find');
  var tofind = elm.value; // read from dialog input
  // if nothing provided - warn and exit
  if (tofind.length == 0) {
      console.log("Empty search pattern is illegal");
      return 0;
  }
  tofind = encodeURIComponent(tofind);
  if (selected_engine == '') {
    // if selected local search - submit search URL in current window
    var curr_location = window.location.href;
    var new_url = curr_location.replace(/\?.*/, '').replace(/read.php/, '')
      + 'read.php?type=watch&id=search&pattern=' + tofind;
    window.location.href = new_url;
  }
  else {
    var selected_engine_url = getSearchUrl(selected_engine);
    // else - open link in new window
    window.open(selected_engine_url+tofind, '_blank', "height=500,width=800,alwaysRaised=yes");
  }
  return 0;
} // triggerTitleSearch

// open "search title in..." dialog
function startTitleSearch(article_id) {
  var searchTitleDialog = document.getElementById('searchTitleDialog');
  var searchModal = new bootstrap.Modal(searchTitleDialog, {focus: false});
  search_val = document.getElementById('heading_'+article_id).children[1].children[0].textContent;
  search_input = document.getElementById('title-text-to-find');
  search_input.value = search_val;
  setArticlesContext(0);
  searchTitleDialog.addEventListener(
      'hidden.bs.modal', function (event) { setArticlesContext(1); });
  setTimeout(function() {
    document.getElementById('title-text-to-find').focus();
  }, 200);
  searchModal.show();
}

// start search dialog
function startSearch() {
  var searchDialog = document.getElementById('searchDialog');
  var searchModal = new bootstrap.Modal(searchDialog, {focus: false});
  setArticlesContext(0);
  searchDialog.addEventListener(
      'hidden.bs.modal', function (event) { setArticlesContext(1); });
  setTimeout(function() {
    document.getElementById('text-to-find').focus();
  }, 200);
  searchModal.show();
}

// trigger search in back-end
function triggerSearch() {
  var elm = document.getElementById('text-to-find');
  if (! elm || !elm.value) { return; }
  var tofind = elm.value;
  // console.log('trigger search for: '+tofind);
  var curr_location = window.location.href;
  var new_url = curr_location.replace(/\?.*/, '').replace(/read.php/, '')
    + 'read.php?type=watch&id=search&pattern=' + tofind;
  window.location.href = new_url;
}

// start refresh process
function refreshRss() {
  document.title = "Free RSS (updating)";
  // show "busy" banner
  var refreshModal = new bootstrap.Modal(document.getElementById('updatingDialog'), {focus: true});
  refreshModal.show();

  // send API request
  // read results and show to user
  var url = '/api/articles/refresh/';
  httpGetAsync(url, function(buf){
    refreshModal.hide();
    console.log(buf);
    setTimeout(function() {
    completeRefreshRss(buf)}, 200);
  });
}

// complete refresh process
function completeRefreshRss(results) {
  document.title = "Free RSS (updated)";
  // show summary dialog with menu
  var refreshCompleteModal = new bootstrap.Modal(document.getElementById('updatedDialog'), {focus: true});
  document.getElementById('updatedDialogContent').innerHTML = results;
  // send results to dialog content
  refreshCompleteModal.show();
}

// callback for group selection change
function changeFeedGroup() {
  var new_group_elm = document.getElementById('new-rss-group');
  var group_select_elm = document.getElementById('group-select');
  if (! new_group_elm) { return; }
  if (! group_select_elm || ! group_select_elm.value) { return; }
  new_group_elm.value = group_select_elm.value;
}

// callback for create (add) new feed
function createFeed() {
  var xml_elm = document.getElementById('new-rss-xml-url');
  var title_elm = document.getElementById('new-rss-title');
  var group_elm = document.getElementById('new-rss-group');
  if (! xml_elm || ! xml_elm.value) { return; }
  if (! title_elm || ! title_elm.value) { return; }
  if (! group_elm || ! group_elm.value) { return; }
  var rss_xml = xml_elm.value;
  var rss_title = title_elm.value;
  var rss_group = group_elm.value;

  // show "busy" banner
  var createFeedModal = new bootstrap.Modal(document.getElementById('updatingDialog'), {focus: true});
  createFeedModal.show();
  var url = '/api/feeds/create/?title=' + rss_title +
    '&group=' + rss_group +
    '&xml_url=' + encodeURIComponent(rss_xml);
  httpGetAsync(url, function(buf){
    // console.log(buf);
    if (buf.startsWith("ERROR")) {
      var elm = document.getElementById('modal-message');
      if (elm) {
        elm.innerHTML = buf;
      } else {
        window.alert(buf);
        setTimeout(function(){ createFeedModal.hide(); }, 500);
      }
      return;
    }
    var feed_id = buf.split("\n")[0].split(/: /)[1];
    window.location.href = '/personal/read.php?type=subscr&id='+feed_id;
  });
}

// get an ordered list of displayed article heading IDs
function getDisplayedArticleHeadingIds() {
  var elements = document.getElementsByClassName('accordion-header');
  var ids = Array();
  for (const element of elements) {
    ids.push(element.id);
  }
  return ids;
}

// get an ordered list of displayed article heading IDs
function getDisplayedArticleIds() {
  var ids = getDisplayedArticleHeadingIds();
  return ids.map(function(v) {return v.replace('heading_', '');});
}

// get active article ID
// when active parent has wrong ID return first element
// (correct ID must start with 'heading_...')
function getActiveArticleId() {
  var article_id = '';
  try {
      article_id = document.activeElement.parentElement.id;
  }
  catch(err) {
    console.log(err);
  }
  if (! article_id.startsWith('heading_')) {
    article_id = '';
  }
  return article_id;
}

// For given DOM element get visibility (class 'hidden-element')
function domElementGetVisibility(element_id) {
  var dom_obj = document.getElementById(element_id);
  if ( dom_obj) {
    return ! dom_obj.classList.contains('hidden-element');
  }
  return false;
}


// For given DOM element change visibility
// by updating class list with 'hidden-element'
// @param visibility: 'on' / 'off' / 'toggle'
function domElementChangeVisibility(element_id, visibility) {
  var dom_obj = document.getElementById(element_id);
  if (!dom_obj) { console.log('wrong element_id='+element_id); return; }
  if (visibility == 'toggle') { dom_obj.classList.toggle('hidden-element'); }
  if (visibility == 'on') { dom_obj.classList.remove('hidden-element'); }
  if (visibility == 'off') { dom_obj.classList.add('hidden-element'); }
}
//
// For given DOM element change bold style
// @param bold_style: 'on' / 'off' / 'toggle'
function domElementChangeBoldStyle(dom_obj, bold_style) {
  if (bold_style == 'toggle') { dom_obj.classList.toggle('bold-element'); }
  if (bold_style == 'on') { dom_obj.classList.remove('bold-element'); }
  if (bold_style == 'off') { dom_obj.classList.add('bold-element'); }
}

// Cancel event (click event) propagation to parent DOM elements
function cancelEventPropagation(event) {
   event = event ? event:window.event;
   if (event.stopPropagation)    event.stopPropagation();
   if (event.cancelBubble!=null) event.cancelBubble = true;
}

/* - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - *\
 | events:
 |  > click on title => toggle article full view, force "read" = 1
 |  > click on envelope => do not change article view, toggle "read" state
 |  > press "arrow-right" key => make article full view, force "read" = 1
 |  > press "arrow-left" key => ensure article title-view
\* - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - */

function onArticleHeadingClick(event, article_id) {
  // set "read" state to 'yes' (1) only if article became "open" (visible)
  setTimeout(function() {
    article_id = article_id.replace('heading_', '');
    var elm = document.getElementById('collapse_'+article_id);
    if (! elm) { return; }
    if (elm.classList.contains('show')) {
      changeArticleReadState(article_id, 'on');
    }
  }, 400);
}

function onReadUnreadClick(event, article_id) {
  // toggle read/unread state
  changeArticleReadState(article_id, 'toggle');
}

// Change article "flagged" state
function changeArticleFlaggedState(article_id, change) {
  // get current state
  var is_read = domElementGetVisibility('flagged_'+article_id);
  if (change === 'on'    ) { set_flagged = 'on';     set_unflagged = 'off'; }
  if (change === 'off'   ) { set_flagged = 'off';    set_unflagged = 'on'; }
  domElementChangeVisibility('flagged_'+article_id, set_flagged);
  domElementChangeVisibility('unflagged_'+article_id, set_unflagged);

  var heading_id = 'heading_'+article_id;
  focusOnArticleById(heading_id, scroll_view=false);
  // send new state to server
  var url = '/api/articles/change_item_state/?item_id='+article_id+
    '&change_flagged='+set_flagged;
  httpGetAsync(url, function(buf){ console.log(buf); });
}

// Change article "read" state
function changeArticleReadState(article_id, change) {
  // get current state
  var is_read = domElementGetVisibility('read_'+article_id);

  // change read/unread display
  if (change === 'toggle') { set_read = is_read?'off':'on'; set_unread = is_read?'on':'off'; }
  if (change === 'on'    ) { set_read = 'on';     set_unread = 'off'; }
  if (change === 'off'   ) { set_read = 'off';    set_unread = 'on'; }
  domElementChangeVisibility('read_'+article_id, set_read);
  domElementChangeVisibility('unread_'+article_id, set_unread);

  // update heading title - bold/normal state
  var heading_id = 'heading_'+article_id;
  var elm = document.getElementById(heading_id);
  if (elm) {
    var sub_elm = elm.children[1].children[0];
    domElementChangeBoldStyle(sub_elm, change);
    focusOnArticleById(heading_id, scroll_view=false);
  } else {
    console.log('missing: heading_'+article_id);
  }

  // send new state to server
  var url = '/api/articles/change_item_state/?item_id='+article_id+
    '&change_read='+set_read;
  httpGetAsync(url, function(buf){ 
    if ( buf.startsWith('Error') ) {
      console.log(buf);
      window.location.href = '/';
    }
    console.log(buf);
  });
}

// Mark all articles on current page as read and open next page
function markReadAndNext() {
  // get IDs of all articles on page
  var ids = getDisplayedArticleIds();
  // send "mark read" for those IDs
  var url = '/api/articles/mark_items_read/?ids='+ids.join(",");
  httpGetAsync(url, function(buf){
    console.log(buf);
    // reload page on completion
    window.location.reload();
  });
}

function getFirstArticleId() {
  return getDisplayedArticleHeadingIds()[0];
}

// focus on next article
function focusOnNextArticle() {
  var id = getActiveArticleId();
  var next_id = '';
  if (! id) {
    next_id = getFirstArticleId();
  } else {
    // if article 'id' is not open - return false
    var clName ='collapse_'+id.replace('heading_', '');
    if (isVisibleItem(clName)) { return false; }
    var all_ids = getDisplayedArticleHeadingIds();
    var index = all_ids.indexOf(id)+1;
    if (index >= all_ids.length) {
      document.getElementById('reload_button').focus();
      return true;
      // index = all_ids.length-1;
    }
    next_id = all_ids[index];
  }
  focusOnArticleById(next_id);
  return true;
}

// focus on previous article
function focusOnPreviousArticle() {
  var id = getActiveArticleId();
  var previous_id = '';
  if (! id) {
    previous_id = getFirstArticleId();
  } else {
    // if article 'id' is not open - return false
    var clName ='collapse_'+id.replace('heading_', '');
    if (isVisibleItem(clName)) { return false; }
    var all_ids = getDisplayedArticleHeadingIds();
    var index = all_ids.indexOf(id);
    if (index > 0) {
      index -= 1;
    }
    previous_id = all_ids[index];
  }
  focusOnArticleById(previous_id);
  return true;
}

function closeCurrentArticle() {
  var article_id = getActiveArticleId();
  if (! article_id) {
    article_id = getFirstArticleId();
  }
  changeArticleVisibility(article_id, 'hide');
}

function openCurrentArticle() {
  var article_id = getActiveArticleId();
  // check if article is invisible and only then open
  if (! article_id) {
    article_id = getFirstArticleId();
  }
  changeArticleVisibility(article_id, 'show');
  changeArticleReadState(article_id.replace('heading_', ''), 'on');
}

function changeArticleVisibility(article_id, action) {
  var clName ='collapse_'+article_id.replace('heading_', '');
  if (action == 'show' && isVisibleItem(clName)) { return; }
  var elm = document.getElementById(clName);
  if (elm) {
    var bsCollapse = new bootstrap.Collapse(elm, { toggle: false });

    if (action == 'show'  ) { bsCollapse.show();   }
    if (action == 'hide'  ) { bsCollapse.hide();   }
    if (action == 'toggle') { bsCollapse.toggle(); }
  }
}

// delete feed
function delete_feed(feed_id) {
  // todo - get confirmation?
  // send request to server
  var url = '/api/feeds/change/?feed_id='+feed_id+
    '&action=delete';
  httpGetAsync(url, function(buf){
    console.log(buf);
    window.location.href = '/';
  });
}

// set feed parameter: xmlUrl, title or group
// @param dom_id: ID of DOM element, which "value" should be taken
// @param db_field: DB field to be updated
// @param feed_id: which RSS feed should be updated
function set_feed_param(dom_id, db_field, feed_id) {
  var elm = document.getElementById(dom_id);
  if (! elm) { return; }
  var new_value = elm.value;
  if (! new_value) {
    err="empty value";
    console.log(err);
    alert(err);
    return;
  }
  // send new state to server
  var url = '/api/feeds/change/?feed_id='+feed_id+
    '&'+db_field+'='+encodeURIComponent(new_value);
  httpGetAsync(url, function(buf){
    console.log(buf);
    window.location.reload();
  });
}

// enable feed: change enable/disable presentation
// and send "enable" request to server
function enable_feed(feed_id, enable_state) {
  if (enable_state) {
    domElementChangeVisibility('feed-enabled', "on");
    domElementChangeVisibility('feed-disabled', "off");
    enable = 1;
  } else {
    domElementChangeVisibility('feed-enabled', "off");
    domElementChangeVisibility('feed-disabled', "on");
    enable = 0;
  }
  // send new state to server
  var url = '/api/feeds/change/?feed_id='+feed_id+
    '&enable='+enable;
  httpGetAsync(url, function(buf){ console.log(buf); });
}

// Move focus to article
function focusOnArticleById(article_id, scroll_view=true) {
  var elm = document.getElementById(article_id).children[1];
  if (! elm) { return; }
  if (scroll_view) {
    elm.scrollIntoView();
  }
  elm.tabIndex = 0;
  elm.focus();
}

function isVisibleDom(element_id) {
  var elm = document.getElementById(element_id);
  if (! elm) return false;
  return (elm.offsetParent !== null);
}


function isVisibleItem(element_id) {
  var elm = document.getElementById(element_id);
  if (! elm) {
    console.log("no element with ID="+element_id);
    return false;
  }
  var bsCollapse = new bootstrap.Collapse(elm, { toggle: false });
  return (bsCollapse._isShown());
}

function goToPage(page_select, delta=0) {
  if (! page_select) {
    page_select = document.getElementById('page_select');
  }
  page_target = page_select.value;
  if (isNaN(page_target)) { console.log("ask a page number here"); return; }
  page_target = parseInt(page_target)+delta;
  if (! page_target) { return; }
  window.location.href = window.location.href.replace(/&page=.*/, '') + '&page=' + page_target;
}

/**
 * Set articles context on/off
**/
function setArticlesContext( value=true ) {
  switch (String(value).toLowerCase()) {
      case "on":    articles_context = true;  break;
      case "true":  articles_context = true;  break;
      case "1":     articles_context = true;  break;
      case "off":   articles_context = false; break;
      case "false": articles_context = false; break;
      case "null":  articles_context = false; break;
      case "0":     articles_context = false; break;
      default:      articles_context = true;  break;
  }
}


/**
 * Bind keyboard shortcuts for feeds reader screen
**/
function bindKeysForFeeds() {

  window.addEventListener("keydown", function (event) {
    if (event.defaultPrevented) {
      return; // Do nothing if the event was already processed
    }

    if (! articles_context) {
      return; // Skip irrelevant cases
    }

    switch (event.key) {
      case "h":
        if (event.altKey) {
          // console.log("Alt/H");
          window.location.href = '/';
        }
        break;
      case "z":
        if (event.ctrlKey) {
          // console.log("Ctrl/Z");
          markReadAndNext();
        }
        break;
      case "Down": // IE/Edge specific value
      case "ArrowDown":
        if (event.ctrlKey) {
          // mark current article as read
          // go to next article
          // console.log('"Ctrl down arrow" key press.');
          var article_id = getActiveArticleId();
          if (! article_id) { article_id = getFirstArticleId(); }
          changeArticleReadState(article_id.replace('heading_', ''), 'on');
          done = focusOnNextArticle();
          if (! done) { return; }
        } else {
          // console.log('"down arrow" key press.');
          done = focusOnNextArticle();
          if (! done) { return; }
        }
        break;
      case "Up": // IE/Edge specific value
      case "ArrowUp":
        if (event.ctrlKey) {
          console.log('"Ctrl up arrow" key press.');
        } else {
          // console.log('"up arrow" key press.');
          done = focusOnPreviousArticle();
          if (! done) { return; }
        }
        break;
        break;
      case "Left": // IE/Edge specific value
      case "ArrowLeft":
        if (event.ctrlKey) {
          if     (req_type == 'subscr') { goToPrevFeed();  }
          else if(req_type == 'watch' ) { goToPrevWatch(); }
          else if(req_type == 'group' ) { goToPrevGroup(); }
        } else {
          closeCurrentArticle();
        }
        break;
      case "Right": // IE/Edge specific value
      case "ArrowRight":
        if (event.ctrlKey) {
          if     (req_type == 'subscr') { goToNextFeed();  }
          else if(req_type == 'watch' ) { goToNextWatch(); }
          else if(req_type == 'group' ) { goToNextGroup(); }
        } else {
          openCurrentArticle();
        }
        break;
      case "Enter":
        console.log('"enter" or "return" key press.');
        break;
      case "Esc": // IE/Edge specific value
      case "Escape":
        console.log('"esc" key press.');
        break;
      default:
        return; // Quit when this doesn't handle the key event.
    }

    // Cancel the default action to avoid it being handled twice
    event.preventDefault();
  }, true);

}
