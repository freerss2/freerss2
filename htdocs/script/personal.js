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

// codes: '' - no touch/scroll right now
//        'touch'
//        'scroll handled'
var scroll_handle = '';

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

// ------------( focus on initial element on page )-----------------

function initFocus() {
    // get current page path
    var page_path = window.location.href;
    var elm = '';
    // if it's a '/personal' - focus on "start reading" button
    if (page_path.endsWith('/personal') || page_path.endsWith('/personal/')) {
      elm = document.getElementsByClassName('openbtn')[0];
    }
    if (elm) {
      elm.focus();
    }
}

// ------( initialize callbacks for inline-help elements )----------

function initInlineHelp() {
    var elements = document.getElementsByClassName('inline-help');
    for (var i=0; i<elements.length; i++) {
        var elm = elements[i];
        elm.onclick = openInlineHelp;
    }
}

function openInlineHelp(event) {
     var context_elm = event.target;
     // Open help window
     var elm_d = document.getElementById('inlineHelpDialog');
     if (! elm_d) { return; }
     var inlineHelpDialog = new bootstrap.Modal(elm_d, {focus: true});
     var help_msg = context_elm.title;
     var help_for_elm = document.getElementById('inlineHelpContent');
     if (help_for_elm) { help_for_elm.textContent = help_msg; }
     inlineHelpDialog.show();
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

// -------------------( build URL )--------------------------------

// get current app URL without args after '?...'
function app_url_no_args() {
  var curr_location = window.location.href;
  return curr_location.replace(/\?.*/, '');
}

// go to current app URL with new arguments after '?...'
function set_app_args(args) {
  showUpdatingDialog();
  window.location.href = app_url_no_args() + '?' + args;
}

// -------------------( getting next/prev view )-------------------

// Go to next/previous feed
function goToPrevFeed() {
  if (! prev_feed_id) { return; }
  set_app_args('type=subscr&id=' + prev_feed_id);
}

function goToNextFeed() {
  if (! next_feed_id) { return; }
  set_app_args('type=subscr&id=' + next_feed_id);
}

// Go to next/previous watch
function goToPrevWatch() {
  if (! prev_watch_id) { return; }
  set_app_args('type=watch&id=' + prev_watch_id);
}

function goToNextWatch() {
  if (! next_watch_id) { return; }
  set_app_args('type=watch&id=' + next_watch_id);
}

// Go to next/previous group
function goToPrevGroup() {
  if (! prev_group_id) { return; }
  set_app_args('type=group&id=' + prev_group_id);
}

function goToNextGroup() {
  if (! next_group_id) { return; }
  set_app_args('type=group&id=' + next_group_id);
}

// get search URL by name
function getSearchUrl(search_name) {
  if (search_name in SEARCH_ENGINES) {
    return SEARCH_ENGINES[search_name];
  }
  return '';
}

// build API URL for edit_filter
// @return: full URL for API call
function build_api_url(url) {
  var curr_location = window.location.href;
  var base_url = curr_location.replace(/edit_filter\.php.*/, '');
  return base_url + '..' + url;
}

// Open single event dialog
function showEventDialog() {
  var elm_d = document.getElementById('eventDetailsDialog');
  if (! elm_d) { return; }
  var eventDetailsDialog = new bootstrap.Modal(elm_d, {focus: true});
  eventDetailsDialog.show();
}

// Open confirmation dialog
// @param message: message to be shown (HTML)
// @param action_func: action to be run on "Ok" press
function askConfirmation(message, action_func) {
  var elm_d = document.getElementById('confirmationDialog');
  if (! elm_d) { return; }
  var confirmationDialog = new bootstrap.Modal(elm_d, {focus: true});
  var elm_m = document.getElementById('confirmation-body');
  elm_m.innerHTML = message;
  var elm_b = document.getElementById('confirmation-button');
  elm_b.onclick = function() {
    confirmationDialog.dispose();
    action_func();
  };
  confirmationDialog.show();
}

// Display error in modal window (no action required)
// @param message: message to be shown (HTML)
function showError(message) {
  var elm_d = document.getElementById('errorDialog');
  if (! elm_d) { return; }
  var errorDialog = new bootstrap.Modal(elm_d, {focus: true});
  var elm_m = document.getElementById('error-body');
  elm_m.innerHTML = message;
  errorDialog.show();
}

// delete watch
function deleteWatch(watch_id) {
  // show confirmation modal and delete on "Ok"
  askConfirmation("This watch will be <b>deleted</b>, are you sure?",
      function() {
        var api_url = build_api_url('/api/watch/delete/?watch_id=' + watch_id);
        var reply = httpGet(api_url);
        reply = filterResponse(reply);
        if ( reply.startsWith('Error') ) {
            showError(reply);
            return;
        }
        showUpdatingDialog();
        window.location.href = '/personal/edit_filter.php';
      }
  );
}

// save watch name
function saveWatchName(watch_id) {
  var new_watch_name = extract_and_prepare('watch_name');
  if (! new_watch_name) {
    showError("Error: missing watch name");
    return;
  }
  var api_url = build_api_url('/api/watch/')
  if (watch_id) {
    api_url += 'update/?watch_id=' + watch_id + '&name=' + new_watch_name;
  } else {
    api_url += 'create/?name=' + new_watch_name;
  }
  var reply = httpGet(api_url);
  reply = filterResponse(reply);
  if ( reply.startsWith('Error') ) {
      showError(reply);
      return;
  }
  showUpdatingDialog();
  if (watch_id) {
    window.location.reload();
  } else {
    window.location.href = '/personal/edit_filter.php?watch_id='+reply;
  }
}

// move watch in direction, specified by delta
// @param watch_id: watch ID
// @param delta: signed integer - move watch forward/backward according to sign
// @return: 'Ok' on success, error message on failure
function moveWatch(watch_id, delta) {
  api_url = '/api/watch/move/?watch_id='+watch_id+'&delta='+delta;
  showUpdatingDialog();
  console.log(api_url);
  httpGetAsync(api_url, function(reply){
    reply = filterResponse(reply);
    // reload on completion
    if ( reply.startsWith('Error') ) {
      showError(reply);
      return;
    }
    console.log(reply);
    window.location.reload();
  });
}

// move rule to different watch
// @param rule_id: rule ID
// @param watch_id: watch ID
function moveRuleToWatch(rule_id, watch_id) {
  api_url = '/api/watch/rule/move/?watch_id='+watch_id+'&rule_id='+rule_id;
  // call API, get result, reload page with destination watch
  showUpdatingDialog();
  console.log('Move rule '+rule_id+' to watch '+watch_id);
  httpGetAsync(api_url, function(reply){
    reply = filterResponse(reply);
    // reload on completion
    if ( reply.startsWith('Error') ) {
      showError(reply);
      return;
    }
    console.log(reply);
    window.location.href = '/personal/edit_filter.php?watch_id='+watch_id;
  });
}

// open dialog box prompting for feeds init: [add link] or [upload OPML]
function promptForInit() {
  var promptForInit = new bootstrap.Modal(document.getElementById('promptForInit'), {focus: true});
  promptForInit.show();
}

// edit SiteToFeed configuration
function openSiteToFeedEdit(feed_id, site_addr) {
  var siteToFeedEditModal = new bootstrap.Modal(document.getElementById('editSiteToFeedDialog'), {focus: true});
  var addr_elm = document.getElementById('site-address');
  addr_elm.value = site_addr;
  siteToFeedEditModal.show();
  setTimeout(function() { addr_elm.focus(); }, 500);
  if (feed_id) {
    // send API query for respective site-to-feed data
    // where `tbl_site_to_feed`.`fd_feedid` equals to feed_id
    // encoding, global_pattern, item_pattern, mapping(title,link,content)
    httpGetAsync('/api/site_to_feed/get?feed_id='+feed_id, function(reply){
      reply = filterResponse(reply);
      if (reply && reply[0] == '{') {
        reply = JSON.parse(reply);
        var db2web = [
          ['htmlUrl', 'site-address'],
          ['encoding ', 'site-encoding'],
          ['global_pattern ', 'global_pattern'],
          ['item_pattern', 'item_pattern'],
          ['title', 'item-title-template'],
          ['link', 'item-link-template'],
          ['content', 'item-content-template']
        ];
        for (var i=0; i<db2web.length; i++) {
          document.getElementById(db2web[i][1]).value = reply[db2web[i][0]];
        }
      }
    });
  }
}

// Extract DOM element and prepare it for sending to REST API
// @param element_id: DOM element ID
// @return: element value encoded as URI component (safe for transfer)
function extract_and_prepare(element_id) {
  var elm = document.getElementById(element_id);
  if ( ! elm ) { return ''; }
  return encodeURIComponent(elm.value);
}

// site-to-feed dialog: reload site code
// Read content from given URL and place it in text-box
function siteToFeedReload() {
  var elm = document.getElementById('site-code');
  if (elm) { elm.value = 'Reloading...'; }
  var site_address = extract_and_prepare('site-address');
  // send request
  httpGetAsync('/api/site_to_feed/query/?site_address='+site_address,
      function(reply){
        console.log(reply);
        // place result in 'site-code'
        var elm = document.getElementById('site-code');
        if (elm) { elm.value = reply; }
      }
  );
}

function siteToFeedExtract() {
  var elm = document.getElementById('extracted-data');
  if (elm) { elm.value = 'Reloading...'; }
  var site_address = extract_and_prepare('site-address');
  var global_pattern = extract_and_prepare('global_pattern');
  var item_pattern = extract_and_prepare('item_pattern');
  httpGetAsync('/api/site_to_feed/query/?site_address='+site_address+
      '&global_pattern='+global_pattern+'&item_pattern='+item_pattern,
      function(reply){
        console.log(reply);
        // place result in 'site-code'
        var elm = document.getElementById('extracted-data');
        if (elm) { elm.value = reply; }
      }
  );
}

function siteToFeedPreview() {
  var elm = document.getElementById('items-preview');
  if (elm) { elm.innerHTML = 'Reloading...'; }
  var site_address = extract_and_prepare('site-address');
  var global_pattern = extract_and_prepare('global_pattern');
  var item_pattern = extract_and_prepare('item_pattern');
  var item_title = extract_and_prepare('item-title-template');
  var item_link = extract_and_prepare('item-link-template');
  var item_content = extract_and_prepare('item-content-template');
  httpGetAsync('/api/site_to_feed/query/?site_address='+site_address+
      '&global_pattern='+global_pattern+'&item_pattern='+item_pattern+
      '&item_title='+item_title+'&item_link='+item_link+
      '&item_content='+item_content,
      function(reply){
        // place result in 'site-code'
        var elm = document.getElementById('items-preview');
        if (elm) { elm.innerHTML = reply; }
      }
  );
}


function siteToFeedSave() {
  // Get inputs and send request
  var site_address = extract_and_prepare('site-address');
  var global_pattern = extract_and_prepare('global_pattern');
  var item_pattern = extract_and_prepare('item_pattern');
  var item_title = extract_and_prepare('item-title-template');
  var item_link = extract_and_prepare('item-link-template');
  var item_content = extract_and_prepare('item-content-template');
  var rss_title = extract_and_prepare('new-rss-title');
  if (! rss_title ) {
    rss_title = extract_and_prepare('rss_title');
  }
  var rss_group = extract_and_prepare('new-rss-group');
  if (! rss_title ) { return; }
  if (! rss_group ) { return; }
  // Get original feed ID (if any) and pass it to API as input
  // When feed_id is empty, it will be generated from site_address
  var curr_url = window.location;
  var url = new URL(curr_url);
  var feed_id = url.searchParams.get('id');
  httpGetAsync('/api/site_to_feed/set/?feed_id='+feed_id+
      '&site_address='+site_address+
      '&global_pattern='+global_pattern+'&item_pattern='+item_pattern+
      '&item_title='+item_title+'&item_link='+item_link+
      '&item_content='+item_content+
      '&rss_title='+rss_title+
      '&rss_group='+rss_group,
      function(reply){
        console.log(reply);
        // TODO: go to feed page (?)
        showUpdatingDialog();
        window.location.href = '/personal/';
      }
  );
}

// rerun watch filters
function rerunFilters() {
  // show modal "please wait"
  var refreshModal = new bootstrap.Modal(document.getElementById('updatingDialog'), {focus: true});
  refreshModal.show();
  // run API for reapplying filters
  httpGetAsync('/api/watch/rerun/', function(reply){
    reply = filterResponse(reply);
    refreshModal.hide();
    // go to homepage on completion
    if ( reply.startsWith('Error') ) {
      showError(reply);
      return;
    }
    // TODO: why reload?
    showUpdatingDialog();
    window.location.href = '/personal/';
  });
}

// delete rule in watch
// @param watch_id: watch where to delete rule
// @param rule_id: rule to delete
function deleteRule(watch_id, rule_id) {
  askConfirmation("This rule will be <b>deleted</b>, are you sure?",
      function() {
        api_url = build_api_url(
          '/api/watch/rule/delete?watch_id=' + watch_id + '&rule_id=' + rule_id);
        var reply = httpGet(api_url);
        reply = filterResponse(reply);
        if ( reply.startsWith('Error') ) {
          showError(reply);
          return;
        }
        window.location.reload();
      }
  );
}

// add rule to current watch
// @param watch_id: watch where to add rule
// (rule name taken from input box)
function addRule(watch_id) {
  var new_rule_name = extract_and_prepare('new_rule');
  api_url = build_api_url(
    '/api/watch/rule/add?watch_id=' + watch_id + '&rule_name=' + new_rule_name);
  var reply = httpGet(api_url);
  reply = filterResponse(reply);
  if (reply.startsWith('Error')) {
    showError(reply);
    return;
  }
  window.location.reload();
}

// start 'rule edit' dialog
// @param watch_id: current watch ID
// @param rule_id: current rule ID
function openRuleEdit(watch_id, rule_id) {
  api_url = build_api_url(
    '/api/watch/rule/edit?watch_id=' + watch_id + '&rule_id=' + rule_id);
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
  var post_url = '/api/watch/rule/update/';
  reply = httpPost(post_url, JSON.stringify(result));
  reply = filterResponse(reply);
  if (reply.startsWith('Error')) {
    showError(reply);
    return;
  }
  window.location.reload();
}

// Open import OPML dialog
function openImportModal() {
  var upoladOpmlDialog = document.getElementById('upoladOpmlDialog');
  var importOpmlModal = new bootstrap.Modal(upoladOpmlDialog, {focus: true});
  importOpmlModal.show();
}

// Open import filters dialog
function uploadFiltersModal() {
  var upoladWatchesDialog = document.getElementById('upoladWatchesDialog');
  var importWatchesModal = new bootstrap.Modal(upoladWatchesDialog, {focus: true});
  importWatchesModal.show();
}

// Open import articles dialog
function openImportArticles() {
  var upoladArticlesDialog = document.getElementById('upoladArticlesDialog');
  var importArticlesModal = new bootstrap.Modal(upoladArticlesDialog, {focus: true});
  importArticlesModal.show();
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
    var new_url = app_url_no_args().replace(/read.php/, '')
      + 'read.php?type=watch&id=search&pattern=' + tofind;
    showUpdatingDialog();
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
  search_val = document.getElementById('heading_'+article_id).children[1].children[1].textContent;
  search_input = document.getElementById('title-text-to-find');
  if (!search_input) { return; }
  search_input.value = search_val;
  setArticlesContext(0);
  searchTitleDialog.addEventListener(
      'hidden.bs.modal', function (event) { setArticlesContext(1); });
  setTimeout(function() {
    var search_input = document.getElementById('title-text-to-find');
    if (!search_input) { return; }
    // try to select irrelevant part (if any)
    var i0 = search_input.value.indexOf(' / ');
    var i1 = search_input.value.length;
    if (i0<0) {
      i0 = search_input.value.indexOf(' (');
    }
    if (i0>=0) {
      select_sub_string(search_input, i0, i1);
    }
    // bind "CR" event to "find" button push
    bindKeyForElement('title-text-to-find', "Enter", function(){ triggerTitleSearch(''); } );
    search_input.focus();
  }, 200);
  searchModal.show();
}

// open page selection dialog
function openPageSelectDialog() {
  var pageSelectDialog = document.getElementById('pageSelectDialog');
  var pageSelectModal = new bootstrap.Modal(pageSelectDialog, {focus: false});
  setArticlesContext(0);
  pageSelectDialog.addEventListener(
      'hidden.bs.modal', function (event) { setArticlesContext(1); });
  setTimeout(function() {
    document.getElementById('page-number').focus();
    // bind "CR" event to "goToInputPage()"
    bindKeyForElement('page-number', "Enter", goToInputPage );
  }, 200);
  pageSelectModal.show();
}

// go to page, typed by user
function goToInputPage() {
  var elm = document.getElementById('page-number');
  if (! elm) return;
  var page_num = elm.value;
  if (! page_num) page_num = 1;
  // replace in args &page=... with new page_num
  var args = getQueryParams(document.location.search);
  args['page'] = page_num;
  var new_url = new URL(app_url_no_args());
  new_url.search = new URLSearchParams( args );
  window.location.href = new_url.href;
}

// start search dialog
function startSearch() {
  var searchDialog = document.getElementById('searchDialog');
  var searchModal = new bootstrap.Modal(searchDialog, {focus: false});
  // disable keyboard shortcuts relevant in "articles view" mode
  setArticlesContext(0);
  // enable "articles view" mode on exit event
  searchDialog.addEventListener(
      'hidden.bs.modal', function (event) { setArticlesContext(1); });
  // focus on text input
  setTimeout(function() {
    var search_input = document.getElementById('text-to-find');
    if (!search_input) { return; }
    search_input.focus();

    // bind "CR" event to "find" button push
    bindKeyForElement('text-to-find', "Enter", triggerSearch);
  }, 200);
  searchModal.show();
}

// trigger search in back-end
function triggerSearch() {
  var elm = document.getElementById('text-to-find');
  if (! elm || !elm.value) { return; }
  var tofind = encodeURIComponent(elm.value);
  var new_url = app_url_no_args().replace(/read.php/, '')
    + 'read.php?type=watch&id=search&pattern=' + tofind;
  showUpdatingDialog();
  window.location.href = new_url;
}


var highlight_settings = {};

// Initialize highlight settings
function initHighlightSettings( rec ) {
  highlight_settings = rec;
}

// Change highlight setting
function changeHighlightSetting( setting_name, setting_value ) {
  // for string - save, for boolean - just toggle
  var STRING_SETTINGS = ['fg_color', 'bg_color', 'keyword'];
  if (STRING_SETTINGS.includes(setting_name)) {
    highlight_settings[setting_name] = setting_value;
  } else {
    highlight_settings[setting_name] = ! highlight_settings[setting_name];
  }
  // Always update the preview
  updateHighlightPreview();
}

// Calculate style for highlighted keyword editor
// using stored colors and other selected parameters
// @return: string defining highlight style
function getHighlightStyle() {
  var result = [];
  if ( highlight_settings['fg_color'] )  { result.push("color: " + highlight_settings['fg_color']); }
  if ( highlight_settings['bg_color'] )  { result.push("background-color: " + highlight_settings['bg_color']); }
  if ( highlight_settings['bold']     )  { result.push("font-weight: bold"); }
  if ( highlight_settings['italic']   )  { result.push("font-style: italic"); }
  if ( highlight_settings['underscore']) { result.push("text-decoration: underline"); }
  return result.join('; ');

}

// calclulate new style_preview and apply it to "preview" text
function updateHighlightPreview() {
  var style = getHighlightStyle();
  var keyword = highlight_settings['keyword'] ? highlight_settings['keyword'] : '=???=';
  var elm = document.getElementById('style_preview');
  elm.outerHTML = '<span id="style_preview" style="'+style+'">'+keyword+'</span>';
}

// callback for color enable/disable checkbox
// @param checkbox_id: 'use_'+color type (bg_color/fg_color)
function disableColorSelect(checkbox_id, color_elm_id) {
  var e_elm = document.getElementById(checkbox_id);
  var c_elm = document.getElementById(color_elm_id);
  c_elm.disabled = e_elm.checked ? '' : 'true';
  // when color is enabled - take its value from respective "input" or clean it
  var var_name = checkbox_id.replace('use_', '');
  highlight_settings[var_name] = e_elm.checked ? c_elm.value : '';
}

// callback for cloning highlight definition
function cloneHighlight(original_keyword) {
  // show "busy" banner
  var refreshModal = new bootstrap.Modal(document.getElementById('updatingDialog'), {focus: true});
  refreshModal.show();

  // create a copy of original keyword settings and open editor for new instance
  // send all parameters to API
  var url = '/api/highlight/clone/?original_keyword='+encodeURIComponent(original_keyword);
  httpGetAsync(url, function(reply){
    reply = filterResponse(reply);
    refreshModal.hide();
    // if API fails - display error and exit
    if ( reply.startsWith('Error') ) {
        showError(reply);
        return;
    }
    // on success - reload editor page with new name as argument
    showUpdatingDialog();
    window.location.href = '/personal/edit_highlight.php?keyword_id=' +
       reply + '#edit';
  });
}

// callback for deleting highlight definition
function deleteHighligt(original_keyword) {
  // delete after confirmation and open page without any arguments
  askConfirmation("This keyword highlight will be <b>deleted</b>, are you sure?",
      function() {
        var api_url = '/api/highlight/delete/?keyword=' + encodeURIComponent(original_keyword);
        var reply = httpGet(api_url);
        reply = filterResponse(reply);
        if ( reply.startsWith('Error') ) {
            showError(reply);
            return;
        }
        showUpdatingDialog();
        window.location.href = '/personal/edit_highlight.php';
      }
  );
}

// callback for save new/updated keyword highlight definition
function saveHighlight(original_keyword) {
  // show "busy" banner
  var refreshModal = new bootstrap.Modal(document.getElementById('updatingDialog'), {focus: true});
  refreshModal.show();

  // send all parameters to API
  var url = '/api/highlight/save/?original_keyword='+encodeURIComponent(original_keyword)+
    '&keyword='+encodeURIComponent(highlight_settings['keyword'])+
    '&fg_color='+encodeURIComponent(highlight_settings['fg_color'])+
    '&bg_color='+encodeURIComponent(highlight_settings['bg_color'])+
    '&bold='+(highlight_settings['bold']?1:0)+
    '&italic='+(highlight_settings['italic']?1:0)+
    '&underscore='+(highlight_settings['underscore']?1:0);
  httpGetAsync(url, function(reply){
    refreshModal.hide();
    // if API fails - display error and exit
    reply = filterResponse(reply);
    if ( reply.startsWith('Error') ) {
        showError(reply);
        return;
    }
    // on success - reload editor page with new keyword
    showUpdatingDialog();
    window.location.href = '/personal/edit_highlight.php?keyword_id='+
        encodeURIComponent(highlight_settings['keyword']) + '#edit';
  });
}

// -_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_-_

// Show "Updating" banner for background processing
function showUpdatingDialog() {
  document.title = "Free RSS (updating)";
  // show "busy" banner
  var elm = document.getElementById('processingDialog');
  if (! elm) { return; }
  var refreshModal = new bootstrap.Modal(elm, {focus: true});
  refreshModal.show();
}

// change article details (move to specific watch, edit labels)
function changeArticle(article_id) {
  // showUpdatingDialog();

  var editArticleDialog = document.getElementById('editArticleDialog');
  var editArticleModal = new bootstrap.Modal(editArticleDialog, {focus: true});
  setArticlesContext(0);
  editArticleDialog.addEventListener(
      'hidden.bs.modal', function (event) { setArticlesContext(1); });
  editArticleModal.show();

  setTimeout(function() {
    var new_label = document.getElementById('new_label');
    if (!new_label) { return; }
    // bind in 'new_label' key "Enter" to "saveArticleChanges"
    bindKeyForElement('new_label', "Enter", saveArticleChanges );
    // focus on 'new_label'
    new_label.focus();
  }, 200);
  var url = '/api/articles/edit/?item_id='+article_id;
  // send query to get article editing code
  httpGetAsync(url, function(buf){
    document.getElementById('editArticleContent').innerHTML = buf;
  });
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
// propagate select-list value to input-box
function changeFeedGroup(group_select_val='') {
  var new_group_elm = document.getElementById('new-rss-group');
  if (! new_group_elm) { return; }
  if (! group_select_val) {
    group_select_elm = document.getElementById('group-select');
    if (! group_select_elm) { return; }
    group_select_val = group_select_elm.value;
  }
  if (! group_select_val) { return; }
  new_group_elm.value = group_select_val;
}

// callback for change RSS source type
function changedFeedSourceType(new_type) {
  var new_rss_xml_url = document.getElementById('xmlUrl');
  var edit_settings = document.getElementById('edit-settings');
  var save_url_button = document.getElementById('save-url-button');

  var enable_input = 1;
  switch(new_type) {
      case "site":         inputTypeRss = 0;
        break;
      case "rss":          inputTypeRss = 1;
        break;
      case "site_to_feed": inputTypeRss = 0;
        enable_input = 0;
        break;
      default:      break;
  }
  set_bootstrap_visibility(new_rss_xml_url, enable_input);
  set_bootstrap_visibility(edit_settings, ! enable_input);
  set_bootstrap_visibility(save_url_button, enable_input);
}

// For DOM object remove old class and add new class
function replace_elment_class(elm, old_cls, new_cls) {
  if (! elm) { return; }
  elm.classList.add(new_cls);
  elm.classList.remove(old_cls);
}

// Using Boostrap5 classes change DOM element visibility on/off
function set_bootstrap_visibility(elm, is_visible) {
  if (! elm) { return; }
  replace_elment_class(elm,
      is_visible ? 'd-none' : 'd-block',
      is_visible ? 'd-block' : 'd-none');
}

// callback for create (add) new feed
function createFeed() {
  var rss_xml = extract_and_prepare('xmlUrl');
  var rss_title = extract_and_prepare('new-rss-title');
  var rss_group = extract_and_prepare('new-rss-group');
  if (! rss_xml ) { return; }
  if (! rss_title ) { return; }
  if (! rss_group ) { return; }

  // check 'sourceType' element
  // and if it's '2' (site-to-feed) - pass respective option to API
  var source_type_elm = document.getElementById('sourceType');
  source_type = (source_type_elm.selectedIndex == 2) ? 'site-to-feed' : 'rss';

  // show "busy" banner
  var createFeedModal = new bootstrap.Modal(document.getElementById('updatingDialog'), {focus: true});
  createFeedModal.show();
  var url = '/api/feeds/create/?title=' + rss_title +
    '&group=' + rss_group +
    '&xml_url=' + rss_xml +
    '&input_type_rss=' + inputTypeRss +
    '&source_type=' + source_type;
  httpGetAsync(url, function(buf){
    buf = filterResponse(buf);
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
    showUpdatingDialog();
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
  if (! article_id ) {
    var elements = document.getElementsByClassName('accordion-header');
    for (var i = 0; i < elements.length; i++) {
      var element = elements[i];
      if (element.parentElement.children[1].classList.contains('show')) {
        article_id = element.id;
        break;
      }
    }
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
 |  > click on star => do not change article view, toggle "flagged" state
 |                     if flagged "on" - force "read" = 0
 |  > press "arrow-right" key => make article full view, force "read" = 1
 |  > press "arrow-left" key => ensure article title-view
\* - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - */

// Callback for click on article heading
// @param article_id: context article ID
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

// @param article_id: context article ID
function onReadUnreadClick(event, article_id) {
  // toggle read/unread state
  changeArticleReadState(article_id, 'toggle');
}

// Toggle current item "bookmarked" state (when curr. item could be identified)
function toggleBookmarkCurrItem() {
  // identify "current item"
  var article_id = getActiveArticleId();
  if (! article_id) { article_id = getFirstArticleId(); }
  if (! article_id) { return; }
  article_id = article_id.replace('heading_', '');
  // if found - get its "bookmarked" state
  var is_flagged = domElementGetVisibility('flagged_'+article_id);
  var change = is_flagged ? 'off' : 'on';
  changeArticleFlaggedState(article_id, change);
}

// Change article "flagged" state
// @param article_id: context article ID
// @param change: how to change article state (on/off/toggle)
function changeArticleFlaggedState(article_id, change) {
  if (change === 'on'    ) { set_flagged = 'on';     set_unflagged = 'off'; }
  if (change === 'off'   ) { set_flagged = 'off';    set_unflagged = 'on'; }
  domElementChangeVisibility('flagged_'+article_id, set_flagged);
  domElementChangeVisibility('unflagged_'+article_id, set_unflagged);

  var heading_id = 'heading_'+article_id;
  var item_header = document.getElementById(heading_id).children[1].children[1];
  if (set_flagged == 'off') {
    focusOnArticleById(heading_id, scroll_view=false);
    // remove respective class (item-header-flagged) from title
    item_header.classList.remove('item-header-flagged');
  } else {
    // assign respective class (item-header-flagged) to title
    item_header.classList.add('item-header-flagged');
    changeArticleReadStateVisual(article_id, 'off');
  }
  // send new state to server
  var url = '/api/articles/change_item_state/?item_id='+article_id+
    '&change_flagged='+set_flagged;
  httpGetAsync(url, function(buf){ console.log(buf); });
}

// Change article "read" state
// @param article_id: context article ID
// @param change: how to change article state (on/off/toggle)
function changeArticleReadState(article_id, change) {
  // get current "flagged" state
  var is_flagged = domElementGetVisibility('flagged_'+article_id);
  // skip update for flagged article
  if (is_flagged) { return; }
  var set_read = changeArticleReadStateVisual(article_id, change);

  // send new state to server
  var url = '/api/articles/change_item_state/?item_id='+article_id+
    '&change_read='+set_read;
  httpGetAsync(url, function(buf){
    buf = filterResponse(buf);
    if ( buf.startsWith('Error') ) {
      console.log(buf);
      showUpdatingDialog();
      window.location.href = '/';
    }
    console.log(buf);
  });
}

// Change visual representation of read/unread state for given article
// @param article_id: context article ID
// @param change: how to change article state (on/off/toggle)
// @return: set_read value (0/1)
function changeArticleReadStateVisual(article_id, change) {
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
    var sub_elm = elm.children[1].children[1];
    domElementChangeBoldStyle(sub_elm, change);
    focusOnArticleById(heading_id, scroll_view=false);
  } else {
    console.log('missing: heading_'+article_id);
  }
  changeUnreadArticlesCount(!is_read, set_read=='off');
  return set_read;
}

// Change articles count state
// @param curr_unread: true/false
// @param new_unread: true/false
function changeUnreadArticlesCount(curr_unread, new_unread) {
  if (curr_unread == new_unread) { return; }
  var elm = document.getElementById('articles_count');
  if (! elm) { return; }
  var count = elm.innerText;
  if (count == '99+') { return; }
  count = parseInt(count);
  if (isNaN(count)) { return; }
  count = new_unread ? count+1 : count-1;
  if (count >= 100) count = '99+';
  elm.innerText = count;
}

// Change explicitly article labels and watch
function saveArticleChanges() {
  var new_labels = document.getElementById('new_label').value;
  var elm = document.getElementById('new_watch_id');
  var new_watch_id = elm.value;
  var article_id = elm.attributes['dest_id'].value;
  // call API and reload page on completion
  var url = '/api/articles/change_item_state/?item_id='+article_id+'&labels='+new_labels+'&watch_id='+new_watch_id;
  httpGetAsync(url, function(buf){
    // reload page on completion
    window.location.reload();
  });
}

// Mark all articles on current page as read and open next page
function markReadAndNext() {
  showUpdatingDialog();
  // get IDs of all articles on page
  var ids = getDisplayedArticleIds();
  // send "mark read" for those IDs
  var url = '/api/articles/mark_items_read/?ids='+ids.join(",");
  httpGetAsync(url, function(buf){
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

function closeCurrentArticle() {
  var article_id = getActiveArticleId();
  if (! article_id) {
    article_id = getFirstArticleId();
  }
  changeArticleVisibility(article_id, 'hide');
  focusOnArticleById(article_id, scroll_view=false);
}

function openCurrentArticle() {
  var article_id = getActiveArticleId();
  // check if article is invisible and only then open
  if (! article_id) {
    article_id = getFirstArticleId();
  }
  changeArticleVisibility(article_id, 'show');
  changeArticleReadState(article_id.replace('heading_', ''), 'on');
  focusOnArticleById(article_id, scroll_view=false);
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

// ----------------------( feed settings )-----------------------

// delete feed by ID
function deleteFeed(feed_id) {
  // get confirmation
  askConfirmation("This feed will be <b>deleted</b>, are you sure?",
      function() {
        // send request to server
        var url = '/api/feeds/change/?feed_id='+feed_id+
          '&action=delete';
        httpGetAsync(url, function(buf){
          console.log(buf);
          showUpdatingDialog();
          window.location.href = '/';
        });
      }
  );
}

// set feed parameter: xmlUrl, title or group
// @param dom_id: ID of DOM element, which "value" should be taken
// @param db_field: DB field to be updated
// @param feed_id: which RSS feed should be updated
function setFeedParam(dom_id, db_field, feed_id) {
  var elm = document.getElementById(dom_id);
  if (! elm) { return; }
  var new_value = elm.value;
  if (elm.type == 'checkbox') {
    new_value = elm.checked ? 1:0;
  } else {
    if (! new_value) {
      err="empty value";
      console.log(err);
      alert(err); // <<< What is it?
      return;
    }
  }
  // send new state to server
  var url = '/api/feeds/change/?feed_id='+feed_id+
    '&'+db_field+'='+encodeURIComponent(new_value);
    console.log(url);
  httpGetAsync(url, function(buf){
    window.location.reload();
  });
}

// start movie rating search
// @param article_id: context article ID
function startMovieRatingSearch(article_id) {
  // replace button content with "Loading..." banner
  var element_id = 'search_' + article_id;
  var dom_obj = document.getElementById(element_id);
  dom_obj.outerHTML = '<span id="'+element_id+'" >'+
    '<img style="max-width:7rem;" src="../img/loading-progress-s.gif"></span>';
  // send API request
  // On API reply: non-empty - place instead of banner
  // on empty - place banner "Failed"
  var url = '/api/articles/search/?plugin=kinopoisk&item_id=' + article_id;
  httpGetAsync(url, function(buf){
    console.log(buf);
    var dom_obj = document.getElementById(element_id);
    if (buf) {
      dom_obj.outerHTML = '<span>'+buf+'</span>';
    } else {
      dom_obj.outerHTML = '<span class="badge badge-warning">Failed :-(</span>';
    }
  });
}

// enable feed: change enable/disable presentation
// and send "enable" request to server
function enableFeed(feed_id, enable_state) {
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
  if (isNaN(page_target)) {
    openPageSelectDialog();
    return;
  }
  page_target = parseInt(page_target)+delta;
  if (! page_target) { return; }
  showUpdatingDialog();
  window.location.href = window.location.href.replace(/&page=.*/, '') + '&page=' + page_target;
}

// start group editing - open dialog
function editGroup(group_id) {
  var editGroupModal = new bootstrap.Modal(document.getElementById('editGroupModal'), {focus: true});
  editGroupModal.show();
  var elm_n = document.getElementById('group_id');
  elm_n.value = group_id;
  // fill modal with content using api call
  var url = '/api/feeds/group/?action=edit&group_id=' + group_id;
  httpGetAsync(url, function(buf){
    // console.log(buf);
    var elm_e = document.getElementById('editGroupContent');
    elm_e.innerHTML = buf;
  });
}

// move feed in group up/down (when possible)
function moveFeed(id, delta) {
  // get all feeds by class name
  var feeds = document.getElementsByClassName('feed-in-group');
  // find feed index by id
  var feed_idx = -1;
  for (var i=0; i<feeds.length; i++) {
    if (feeds[i].id == id) {
      feed_idx = i;
      break;
    }
  }
  if (feed_idx == -1) { return; }
  // try to move
  var new_idx = feed_idx+delta;
  if (new_idx < 0 || new_idx >= feeds.length) { return; }
  var feeds_html = [];
  for (var i=0; i<feeds.length; i++) {
    feeds_html[i] = feeds[i].outerHTML;
  }
  var tmp = feeds_html[new_idx];
  feeds_html[new_idx] = feeds_html[feed_idx];
  feeds_html[feed_idx] = tmp;
  var buffer = '<ul class="nav nav-pills flex-column">';
  for (var i=0; i<feeds_html.length; i++) {
    buffer += feeds_html[i];
  }
  buffer += '</ul>';
  var elm_e = document.getElementById('editGroupContent');
  elm_e.innerHTML = buffer;
}

// Save changes in feeds group
function saveGroupChanges() {
  var elm_n = document.getElementById('group_id');
  var new_group_id = elm_n.value;
  var args = new URLSearchParams(window.location.search);
  var group_id = args.get('id');
  var feeds = document.getElementsByClassName('feed-in-group');
  var result = {'group_id': group_id, 'new_group_id': new_group_id, 'feeds': []}
  for (var i=0; i<feeds.length; i++) {
    console.log(feeds[i].id);
    result.feeds.push(feeds[i].id.replace('feed_', ''));
  }
  // console.log('saveGroupChanges: '+JSON.stringify(result));
  // send update via API
  var url = '/api/feeds/group/?action=save&group_id=' + group_id;
  var result = httpPost(url, JSON.stringify(result));
  // check if new group is not exist (excluding case of same name)
  // save to 'tbl_subscr' with 'user_id', 'fd_feed_id', 'group', 'index_in_gr'
  // and reload page on api completion
  // TODO: show error if returned
  window.location.reload();
}

// Set articles context on/off
// change global semaphore article_context (boolean) value
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

// Show/hide document elements belonging to given class
// @param class_name: DOM class name
// @param is_visible: <true> if element should be shown
function changeClassVisibility( class_name, is_visible ) {
  var to_change = Array.from( document.getElementsByClassName( class_name ) );
  var set_display = is_visible ? "" : "none";
  for (var i=0; i<to_change.length; i++) {
    to_change[i].style.display = set_display;
  }
}

// Bind keyboard/touch events for feeds reading screen
function bindKeysForFeeds() {

  // Touch-screen start click
  window.addEventListener("touchstart", function(event) {
    scroll_handle = 'touch'; // touch - still no move
  }, true);

  // Touch-screen move
  window.addEventListener("touchmove", function (event) {
    if (event.defaultPrevented) {
      return; // Do nothing if the event was already processed outside
    }

    if (! articles_context) {
      return; // Skip irrelevant cases
    }

    if (scroll_handle != 'touch') {
      return; // Do nothing if it's not first time after touch
    }

    // temporary hide elements of class 'post-time-info', 'item-menu-button'
    changeClassVisibility( 'post-time-info', 0 );
    changeClassVisibility( 'item-menu-button', 0 );

    scroll_handle = 'scroll handled';
  }, true);

  // Touch-screen end click/move
  window.addEventListener("touchend", function (event) {
    if (scroll_handle == 'scroll handled') {
      // unhide elements of class 'post-time-info', 'item-menu-button'
      changeClassVisibility( 'post-time-info', 1 );
      changeClassVisibility( 'item-menu-button', 1 );
    }
    scroll_handle = '';
  }, true);

  // Keyboard events (including keys combinations)
  window.addEventListener("keydown", function (event) {
    if (event.defaultPrevented) {
      return; // Do nothing if the event was already processed
    }

    if (! articles_context) {
      return; // Skip irrelevant cases
    }

    var handled = false;
    var event_key = event.key;
    switch (event.keyCode) {
      case 82: event_key = "r"; break;
      case 72: event_key = "h"; break;
      case 90: event_key = "z"; break;
      case 66: event_key = "b"; break;
    }
    switch (event_key) {
      case "r":
        if (event.ctrlKey) { return; }
        if (event.altKey) {
          refreshRss();
          handled = true;
        }
        break;
      case "h":
        if (event.ctrlKey) { return; }
        if (event.altKey) {
          showUpdatingDialog();
          handled = true;
          window.location.href = '/';
        }
        break;
      case "z":
        if (event.altKey) { return; }
        if (event.ctrlKey) {
          handled = true;
          markReadAndNext();
        }
        break;
      case "b":
        if (event.altKey) { return; }
        if (event.ctrlKey) {
          handled = true;
          toggleBookmarkCurrItem();
        }
       break;
      case "Down": // IE/Edge specific value
      case "ArrowDown":
        if (event.shiftKey) { return; }
        if (event.ctrlKey) {
          // mark current article as read
          // go to next article
          var article_id = getActiveArticleId();
          if (! article_id) { article_id = getFirstArticleId(); }
          changeArticleReadState(article_id.replace('heading_', ''), 'on');
          done = focusOnNextArticle();
          handled = true;
          if (! done) { return; }
        } else {
          done = focusOnNextArticle();
          handled = true;
          if (! done) { return; }
        }
        break;
      case "Up": // IE/Edge specific value
      case "ArrowUp":
        if (event.shiftKey) { return; }
        if (event.ctrlKey) {
          console.log('"Ctrl up arrow" key press.');
        } else {
          done = focusOnPreviousArticle();
          handled = true;
          if (! done) { return; }
        }
        break;
      case "Left": // IE/Edge specific value
      case "ArrowLeft":
        if (event.shiftKey) { return; }
        if (event.altKey) { return; }
        if (event.ctrlKey) {
          if     (req_type == 'subscr') { goToPrevFeed();  handled = true; }
          else if(req_type == 'watch' ) { goToPrevWatch(); handled = true; }
          else if(req_type == 'group' ) { goToPrevGroup(); handled = true; }
        } else {
          closeCurrentArticle(); handled = true;
        }
        break;
      case "Right": // IE/Edge specific value
      case "ArrowRight":
        if (event.shiftKey) { return; }
        if (event.altKey) { return; }
        if (event.ctrlKey) {
          if     (req_type == 'subscr') { goToNextFeed();  handled = true; }
          else if(req_type == 'watch' ) { goToNextWatch(); handled = true; }
          else if(req_type == 'group' ) { goToNextGroup(); handled = true; }
        } else {
          openCurrentArticle(); handled = true;
        }
        break;
      default:
        return; // Quit when this doesn't handle the key event.
    }

    // Cancel the default action to avoid it being handled twice
    if (handled) {
      event.preventDefault();
    }
  }, true);
}
