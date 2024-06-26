<?php

include "db_conf.php";
include "db_app.php";
include "php_util.php";
include "opml.php";
include "site_to_feed.php";
require_once "Spyc.php";


$APP_VERSION = '2.0.1.7.3b';

$VER_SUFFIX = "?v=$APP_VERSION";

# /*                                     *\
#   RSS App functionality implementation
# \*                                     */

define('TOKEN_LENGTH', 16);


class RssApp {
  private $db;
  private $NOW;
  private $user_id;
  private $builtin_watches = array('all', 'today', 'older', 'bookmarked', 'unfiltered');
  private $reserved_watches;
  private $keywords = null;
  # TODO: read this data from conf-file
  private $SHARE_LINKS = array(
      array('Telegram', 'https://t.me/share/url?url={link}&text={title}'),
      array('Facebook', 'https://www.facebook.com/sharer.php?u={link}'),
      array('LiveJournal', 'https://www.livejournal.com/update.bml?event={link}'),
      array('GMail', 'https://mail.google.com/mail/u/0/?fs=1&tf=cm&to=somebody@email.com&su={title}&body={link}'),
      array('WhatsApp', 'https://wa.me/?text={link}'),
      array('Twitter', 'https://twitter.com/intent/tweet?original_referer={link}&text={title}')
    );

  const PASSWORD_CHARSET =
      '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_.,-+!:@';

  const API_KEY_CHARSET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

  const POSTS_SELECT = "SELECT ".
      "p.`link`, p.`title`, p.`author`, p.`categories`, ".
      "DATE_FORMAT(p.`timestamp`, '%e %b %Y') AS 'dateStr', ".
      "CONVERT(p.`description` USING utf8) as description, ".
      "p.`fd_postid`, p.`fd_feedid`, p.`guid`, p.`read`, p.`flagged`, ".
      "p.`gr_original_id`, s.`rtl` ".
      "FROM `tbl_posts` p, `tbl_subscr` s ".
      "WHERE p.`user_id` = :user_id AND p.`user_id` = s.`user_id` AND p.`fd_feedid` = s.`fd_feedid` ";

  const PAGE_RANGE = 10;

  /**
   * Constructor
  **/
  public function __construct() {
    global $db_conf; # defined in db_conf.php

    $this->db = new DbApp($db_conf);
    $this->user_id = null;
    $this->reserved_watches = $this->builtin_watches;
    $this->reserved_watches[] = 'search';
    $this->reserved_watches[] = 'trash';
    $this->reserved_watches[] = '= new =';
    // generate MySQL timestamp from PHP current time
    // $timestamp = date('Y-m-d H:i:s');
    //   STR_TO_DATE( '$timestamp', '%Y-%m-%d %H:%i:%s')
    $this->initCurrTime();
  }

  public function dumpDb($filename) {
    return $this->db->dumpDb($filename);
  }

  // init $this->NOW with current timestamp in MySQL format
  public function initCurrTime() {
    $timestamp = date('Y-m-d H:i:s');
    $this->NOW = "STR_TO_DATE( '$timestamp', '%Y-%m-%d %H:%i:%s')";
  }

  /**
   * Is this watch in reserved list?
  **/
  public function isReservedWatch($watch_id) {
    return in_array(strtolower($watch_id), $this->reserved_watches);
  }

  /**
   * Set user ID for further queries
   * @param $user_id: user ID
  **/
  public function setUserId($user_id) {
    $this->user_id = $user_id;
  }

  /**
   * Get user ID
   * @return: user ID (if any set)
  **/
  public function getUserId() {
    return $this->user_id;
  }

  /**
   * Set user ID for further queries, using login name
   * @param $login: user login name
  **/
  public function setUserByLogin($login) {
    $query = "SELECT `user_id` FROM `tbl_users` WHERE `login_name`=:login";
    $bindings = array('login' => $login);
    $this->user_id = $this->db->fetchSingleResult($query, $bindings);
  }

  /**
   * Register new user
   * @param $email: new user email
   * @param $name: new user name
   * @return: message - summary or error (to be shown as reply)
  **/
  public function registerNewUser($email, $name) {
    // validate inputs
    if (!$email || !$name) {
      return "Error: empty input(s)";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      return "Error: invalid email address";
    }
    // first, clean records that already not relevant
    $query1 = "DELETE FROM `tbl_users` WHERE `expiration_timestamp` < $this->NOW";
    $this->db->execQuery($query1);
    // make sure this email is not in use
    $query2 = "SELECT COUNT(1) FROM `tbl_users` ".
      "WHERE LOWER(`email`) = LOWER(:email)";
    $bindings = array('email' => $email);
    $count = $this->db->fetchSingleResult($query2, $bindings);
    if ($count) {
      return "Error: email already in use";
    }
    // generate password
    $password = random_str(10, RssApp::PASSWORD_CHARSET);
    // calculate MD5 checksum for password
    $checksum = md5($password);
    $query3 = "INSERT INTO `tbl_users` ".
      "(`user_id`, `full_name`, `login_name`, `email`, `expiration_timestamp`, `password`) ".
      "VALUES ( (SELECT MAX(u.`user_id`)+1 FROM `tbl_users` as u), ".
      "            :name,       :email,       :email, $this->NOW + INTERVAL 24 HOUR, :checksum )";
    $bindings['name'] = $name;
    $bindings['checksum'] = $checksum;
    $this->db->execQuery($query3, $bindings);
    # TODO: query back the new user ID
    # TODO: add subscription "FreeRSS2 updates" under "Announcements"
    # with address http://felixl.coolpage.biz/free_rss2/rss.xml (take from conf-file)
    $message = "Hello $name,<BR>\n".
      "This is automatic message from FreeRSS application.<BR>\n".
      "If you never submit account request - just ignore it.<BR>\n".
      "Your personal password is $password<BR>\n".
      "If you never login with this password in next 24 hours, it will be invalidated<BR>\n".
      "Please note that passwords aren't stored in application database<BR>\n".
      "We respect your privacy and only compare checksums.";
    return $message;
  }

  /**
   * Create/fetch token associated with this user
   * This function should be called only from successful login routine!
   * @param user_id: logged user ID
   * @param $client_addr: login client info
   * @return: generated auth token associated with this user
  **/
  public function get_auth_token($user_id, $client_addr) {
    $query0 = "SELECT t.token FROM tbl_auth_tokens t WHERE t.user_id = :user_id";
    $bindings = array(':user_id' => $user_id);
    $row = $this->db->fetchSingleRow($query0, $bindings);
    if ( $row ) {
      return $row['token'];
    }
    $auth_token = random_str(TOKEN_LENGTH);
    $query1 = "INSERT INTO tbl_auth_tokens (user_id, token, source, expiration)
      VALUES (:user_id, :auth_token, :source, DATE_ADD(NOW(), INTERVAL 90 DAY))";
    $this->db->execQuery($query1, array(
      ':user_id'        => $user_id,
      ':auth_token'     => $auth_token,
      ':source'         => $client_addr));
    return $auth_token;
  }

  /**
   * check auth_token from local storage
   * @param $auth_token: token value
   * @param $client_addr: client address
   * @return: string result - Error or empty string
  **/
  public function check_auth_token($auth_token, $client_addr) {
    $query = "SELECT t.user_id, t.expiration
      FROM tbl_auth_tokens t
      WHERE t.token = :token";
    $bindings = array(':token' => $auth_token);
    $row = $this->db->fetchSingleRow($query, $bindings);
    if ( ! $row ) {
      return "Error: invalid token";
    }
    // TODO: check expiration
    $_SESSION['user_id'] = $row['user_id'];
    return '';
  }

  /**
   * Check login - stage1 (encription)
   * @param $login: login ID for temporary key generation
   * @return: generated temporary key for password checksum encription
  **/
  public function loginStage1($login) {
    $this->setUserByLogin($login);
    if (! $this->user_id) { return ''; }

    $temp_key = random_str(5);
    $this->setPersonalSetting('temp_key', $temp_key);
    $temp_key = $this->getPersonalSetting('temp_key');

    return $temp_key;
  }

  /**
   * Check login - stage2 (decription)
   * @param $login: login ID for temporary key retrieve
   * @param $encr_password: password checksum after encription
   * @return: login success 0/1
  **/
  public function loginStage2($login, $encr_password) {
    $this->setUserByLogin($login);
    if (! $this->user_id) { return 0; }

    $query0 = "SELECT `password` FROM `tbl_users` WHERE `user_id`=:user_id";
    $bindings = array('user_id' => $this->user_id);
    $checksum = $this->db->fetchSingleResult($query0, $bindings);
    $temp_key = $this->getPersonalSetting('temp_key');
    $this->deletePersonalSetting('temp_key');

    $login_result = $encr_password == md5($temp_key . $checksum)? 1 : 0;
    if ($login_result) {
      $query1 = "UPDATE `tbl_users` SET ".
        "`expiration_timestamp` = '2030-02-19 00:00:00', ".
        "`login_timestamp` = $this->NOW ".
        "WHERE `user_id` = :user_id;";
      $this->db->execQuery($query1, $bindings);
    }

    return $login_result;
  }

  /**
   * Check if it's time for cleanup and perform if needed
   * @return: summary log
  **/
  public function checkCleanup() {
    global $_S;
    // read last maintenance for this user
    $last_maintenance = $this->getPersonalSetting('last_maintenance');
    // and exit if still not passed 2 days
    if ($last_maintenance &&
        (time()-$last_maintenance) < $_S['day']*2) { return ""; }
    $where = array("user_id" => $this->user_id);
    $rss_records = $this->db->queryTableRecords('tbl_subscr', $where);
    $bindings = array('user_id' => $this->user_id);
    $mincount = $this->getPersonalSetting('retention_leave_articles');
    $mincount = $mincount ? $mincount : 100;
    $count = 0;
    foreach ($rss_records as $rec) {
        # get amount of "read" articles for this feed
        $fd_feedid = $rec['fd_feedid'];
        $query1 = "SELECT COUNT(1) FROM `tbl_posts` WHERE " .
            "`user_id` = :user_id AND `fd_feedid` = :fd_feedid AND `read`=1";
        $bindings['fd_feedid'] = $fd_feedid;
        $count = $this->db->fetchSingleResult($query1, $bindings);
        if ($count <= $mincount) { continue; }
        $count += 1;
        # clean "read=1" articles that exceed mincount
        $query2 = "DELETE FROM `tbl_posts` WHERE ".
          "`fd_postid` IN (SELECT `fd_postid` FROM ".
          "(SELECT `fd_postid` FROM `tbl_posts` WHERE ".
          " `user_id`=:user_id AND `read`=1 AND `fd_feedid`=:fd_feedid ".
          " AND `timestamp` < $this->NOW - INTERVAL 2 DAY ".
          " ORDER BY `timestamp` ASC LIMIT ".($count-$mincount).") tt)";
        $this->db->execQuery($query2, $bindings);
    }
    $this->setPersonalSetting('last_maintenance', time());
    return "Performed maintenance - $count article(s) cleaned";
  }

  /**
   * Read site code using URL and try to find rss feed address and title
   * @param: site_url: site full address
   * @return: dictionary with fields 'xmlUrl' and 'title' (if any found)
  **/
  public function findRssForSite($site_url) {
    // Disable any errors reporting
    error_reporting(0);
    $page_code = file_get_contents($site_url);
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
    // Enable errors and warnings
    $rec = array();
    if (! $page_code) { return $rec; }
    foreach (explode('>', $page_code) as $html_tag) {
      if (stripos($html_tag, 'rss') === false) { continue; }
      if (stripos($html_tag, 'href=') === false) { continue; }
      $html_tag = str_replace("/>", "", $html_tag.'>');
      preg_match('/href=([^>< ]+)/', $html_tag, $matches);
      $xml_url = trim($matches[1], '"'."'");
      $xml_url_info = parse_url($xml_url);
      if (! array_key_exists('host', $xml_url_info)) {
        $url_info = parse_url($site_url);
        $xml_url = $url_info['scheme'] . '://' . $url_info['host'] . '/' . $xml_url;
      }
      $rec['xmlUrl'] = $xml_url;
    }
    preg_match ('/<title>([^<>]+)<\/title>/i', $page_code, $title);
    if ($title) {
      $rec['title'] = $title[1];
    }
    return $rec;
  }
  /**
   * Read articles from speficic RSS feed source
   * @param rss_url: RSS URL address
   * @param rss_title: RSS page title (for diagnostics)
   * @return: error message (if any),
   *          array of article records (link, title, date, description)
   *          feed formal title
   *          feed site URL
  **/
  public function readRssUpdate($rss_url, $rss_title, $site_to_feed=null) {

    $rss_link = '';
    try {

      // Disable any errors reporting
      error_reporting(0);
      $rss_buffer = file_get_contents($rss_url);
      error_reporting(E_ERROR | E_WARNING | E_PARSE);
      // Enable errors and warnings

      if (! $rss_buffer) {
          return array("Nothing read from $rss_url", null, $rss_title, '');
      }
      # if $site_to_feed - convert $rss_buffer to $items
      if ( $site_to_feed &&  $site_to_feed['item_pattern'] ) {
        $global_pattern = $site_to_feed['global_pattern'];
        $item_pattern = $site_to_feed['item_pattern'];
        $item_pattern = html_entity_decode($item_pattern);
        $mapping = $site_to_feed['mapping'];
        $encoding = $site_to_feed['encoding'];
        if ($encoding) {
          $rss_buffer = mb_convert_encoding($rss_buffer, "utf-8", $encoding);
        }
        $s = new SiteToFeed($rss_url, $item_pattern, $mapping, $global_pattern);
        $r = $s->convert_to_rss($rss_buffer);
        # make sure it's non-empty structure
        $items = $r ? $r['items'] : array();
      } else {
        if ( strpos($rss_buffer, '<?xml') === false &&
             strpos($rss_buffer, '<rss') === false) {
            return array($rss_buffer, null, $rss_title, '');
        }
        # echo "read-in ".strlen($rss_buffer)." bytes<BR>\n";

        // Disable any errors reporting
        error_reporting(0);
        $rss=simplexml_load_string($rss_buffer);
        // Enable errors and warnings
        error_reporting(E_ERROR | E_WARNING | E_PARSE);

        if (! $rss) {
          $rss_buffer = substr($rss_buffer, 0, 16);
          return array("Failed parsing of content from $rss_url<BR>///$rss_buffer///\n", null, $rss_title, '');
        }
        $items = array();
        $rss_title = $rss->channel->title;
        $rss_link = $rss->channel->link;
        $channel_items = $rss->channel ? $rss->channel->item : $rss->entry;
        foreach ($channel_items as $item) {
          $link = is_array($item->link)? $item->link[0] : $item->link;
          if ($link && $link->attributes()) {
            $link = $link->attributes()['href'];
          }
          $link = is_array($link) ? $link['href'] : $link;
          $fd_postid = $link ? $link : $item->id;
          $pubDate = $item->pubDate ? $item->pubDate : $item->updated;
          $pubDate = str_replace(' (Coordinated Universal Time)', '', $pubDate);
          $content = $item->description ? $item->description : $item->summary;
          if ( ! $content ) { $content = $item->content; }
          if (strpos($content, '<![CDATA[') !== false) {
            $content = str_replace('<![CDATA[', '', $content);
            $content = str_replace(']]>', '', $content);
          }
          $author = $item->author ? $item->author : '';
          $new_item = array(
            'link'       => $link,
            'title'      => $item->title,
            'author'     => $author,
            'categories' => $item->category,
            'dateStr'    => $pubDate,
            'timestamp'  => strtotime($pubDate),
            'description'=> $content,
            'fd_postid'  => _guid_digest_hex($fd_postid),
            'guid'       => $fd_postid );
          // print_r($new_item);
          array_push($items, $new_item);
        }
      }
      $error = "";
    }
    catch(Exception $e) {
      if (! $rss_title) { $rss_title = 'UNKNOWN'; }
      if (! $rss_link) { $rss_link = ''; }
      $error = "Exception while getting RSS '$rss_title' - $e";
      error_reporting(E_ERROR | E_WARNING | E_PARSE);
    }
    return array($error, $items, $rss_title, $rss_link);
  } // readRssUpdate

  /**
   * Register application event
   * @param $obj_type: 'subscr', 'group' or other object type
   * @param $obj_id: object ID
   * @param $status: error/Ok
   * @param $log: free text describing the event
  **/
  public function registerAppEvent($obj_type, $obj_id, $status, $log) {
    $this->initCurrTime();
    $bindings = array(
        'user_id'    => $this->user_id,
        'type'       => $obj_type,
        'id'         => $obj_id
      );
    $query0 = "DELETE FROM `tbl_subscr_state` ".
      "WHERE `user_id`=:user_id AND `type`=:type AND `id`=:id";
    $this->db->execQuery($query0, $bindings);

    $bindings['upd_status'] = $status;
    $bindings['upd_log']    = $log;
    $query1 = "INSERT INTO `tbl_subscr_state` ".
      "(`user_id`, `type`, `id`, `timestamp`, `upd_status`, `upd_log`) ".
      "VALUES ".
      "(:user_id , :type , :id , $this->NOW, :upd_status , :upd_log )";
    $this->db->execQuery($query1, $bindings);
  }

  /**
   * Get event info
   * @param $obj_type: object type
   * @param $id: object ID
   * @return: record with fields 'name', 'timestamp', 'status', 'log'
  **/
  public function getEventRecord($obj_type, $id) {
    $rec = array();
    # for type == 'subscr' resolve ID to name
    if ( $obj_type == 'subscr' ) {
      $bindings0 = array('user_id'=>$this->user_id);
      if (strtolower($id) == 'last') {
        $query = "SELECT s.`id` FROM `tbl_subscr_state` AS s ".
          "WHERE s.`user_id`=:user_id ORDER BY s.`timestamp` DESC";
        $id = $this->db->fetchSingleResult($query, $bindings0);
      }
      $bindings0['id'] = $id;
      $query0 = "SELECT f.`title` FROM `tbl_subscr` AS f ".
        "WHERE f.`fd_feedid`=:id AND f.`user_id`=:user_id";
      $name = $this->db->fetchSingleResult($query0, $bindings0);
    } else {
      $name = $id;
    }
    $bindings1 = array(
      'user_id'=>$this->user_id, 'type' => $obj_type, 'id' => $id);
    $query1 = "SELECT s.`timestamp`, s.`upd_status` AS status, s.`upd_log` AS log ".
      "FROM `tbl_subscr_state` AS s ".
      "WHERE s.`user_id`=:user_id AND s.`id`=:id AND s.`type`=:type";
    $rec = $this->db->fetchSingleRow($query1, $bindings1);
    if (! $rec) { return $rec; }
    $rec['name'] = $name;
    return $rec;
  }

  /**
   * Events report
   * @param $obj_type: object type (when missing - take all)
   * @return: array of records with
   *          'type', 'id', 'title', 'time', 'status' and 'log'
  **/
  public function eventsReportData($obj_type=null) {
    $bindings = array('user_id'=>$this->user_id);
    if (is_null($obj_type)) {
      $obj_cond = '';
    } else {
      $bindings['type'] = $obj_type;
      $obj_cond = ' WHERE a.`type`=:type ';
    }
    $query = "SELECT a.* FROM (
SELECT s1.`type`, s1.`id`, f.`title`, s1.`timestamp`, s1.`upd_status`, s1.`upd_log`
FROM `tbl_subscr_state`AS s1, `tbl_subscr` AS f
WHERE s1.`user_id`=:user_id AND s1.`id` = f.`fd_feedid`
  AND s1.`type`='subscr' AND f.`user_id` = :user_id
UNION
SELECT s2.`type`, s2.`id`, s2.`id`, s2.`timestamp`, s2.`upd_status`, s2.`upd_log`
FROM `tbl_subscr_state`AS s2
WHERE s2.`user_id`=:user_id AND s2.`type`!='subscr'
) a $obj_cond
ORDER BY a.`timestamp` DESC";
    return $this->db->fetchQueryRows($query, $bindings);
  }

  /**
   * Get next RSS after previously updated
   * @param $last_rss_id: last read RSS ID (null for initial call)
   * @return: next RSS record (rss_id, rss_title, rss_url, site_to_feed)
   *          or null (on end of sequence)
  **/
  public function getNextRss($last_rss_id) {
      # get a list of all RSS records
      # if $last_rss_id is null - return first record
      # try to find $last_rss_id in records and return next one
      # (or null if reached last record)
    $query = 'SELECT
      `tbl_subscr`.`fd_feedid`, `tbl_subscr`.`title`, `tbl_subscr`.`xmlUrl`, `tbl_subscr`.`htmlUrl`,
      `tbl_site_to_feed`.`encoding`, `tbl_site_to_feed`.`global_pattern`, `tbl_site_to_feed`.`item_pattern`, `tbl_site_to_feed`.`mapping`
      FROM `tbl_subscr`
      LEFT JOIN `tbl_site_to_feed`
      ON `tbl_site_to_feed`.`user_id` = `tbl_subscr`.`user_id`
      AND `tbl_site_to_feed`.`fd_feedid` = `tbl_subscr`.`fd_feedid`
      WHERE `tbl_subscr`.`user_id` = :user_id AND `tbl_subscr`.`download_enabled` = 1';
      $bindings = array("user_id" => $this->user_id);
      $rss_records = $this->db->fetchQueryRows($query, $bindings);
      if ( ! $rss_records ) { return null; }
      if ( ! $last_rss_id ) { return $rss_records[0]; }
      $found = null;
      foreach ($rss_records as $rec) {
          if ($found) { return $rec; }
          $found = ($rec['fd_feedid'] == $last_rss_id);
          //echo("found=$found for ".$rec['xmlUrl']."<BR>\n");
      }
      return null;
  } // getNextRss


  /**
   * Get site-to-feed definition from DB
   * @param feed_id: feed ID
   * @return: record (empty if not found)
  **/
  public function getSiteToFeed($feed_id) {
    $query = "SELECT * ".
      "FROM `tbl_site_to_feed` " .
      "WHERE `user_id`=:user_id AND `fd_feedid`=:feed_id";
    $bindings = array( 'user_id' => $this->user_id, 'feed_id' => $feed_id );
    $rec = $this->db->fetchSingleRow($query, $bindings);
    $mapping = $rec['mapping'];
    unset($rec['mapping']);
    $mapping = json_decode($mapping, true);

    return array_merge($rec, $mapping);
  }

  /**
   * Query site content for site-to-feed dialog
   * @param $site_address: site URL
   * @return: site content or error message
  **/
  public function querySiteToFeedContent($site_address) {
    // Disable any errors reporting
    error_reporting(0);
    $content = file_get_contents($site_address);
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
    // Enable errors and warnings

    return $content;
  }

  /**
   * Extract site content for site-to-feed dialog
   * @param $site_address: site URL
   * @param $global_pattern: global search pattern (must be with {%} inside)
   * @param $item_pattern: repeated items search pattern (also with {%} inside)
   * @return: parsed site content or error message
  **/
  public function extractSiteToFeedContent($site_address, $global_pattern, $item_pattern) {
    $item_pattern = html_entity_decode($item_pattern);
    $content = $this->querySiteToFeedContent($site_address);
    $mapping = array();
    $s = new SiteToFeed($site_address, $item_pattern, $mapping, $global_pattern);
    $items = $s->content_to_items($content);
    $result = array();
    # Get parsed parameters per item and
    # return representation as {%1} = '', {%2} = '', ...
    for ($i=0; $i<count($items); $i++) {
      $parameters = $items[$i];
      $repr = array();
      for ($j=1; $j<count($parameters); $j++) {
        $repr []= '{%'.$j.'} = '. $parameters[$j];
      }
      $result []= implode("\n", $repr);
    }
    return implode("\n\n----------------\n", $result);
  }

  /**
   * Extract site-to-feed preview (full HTML)
   * @param $site_address: site URL
   * @param $global_pattern: global search pattern (must be with {%} inside)
   * @param $item_pattern: repeated items search pattern (also with {%} inside)
   * @param $item_title: item title template
   * @param $item_link: item link template
   * @param $item_content: item content template
   * @return: parsed site content or error message
  **/
  public function extractSiteToFeedPreview(
    $site_address, $global_pattern, $item_pattern, $item_title, $item_link, $item_content) {
    $item_pattern = html_entity_decode($item_pattern);
    // 1. get site content
    $content = $this->querySiteToFeedContent($site_address);
    // 2. extract records
    $mapping = (object) array(
      'link'=>$item_link, 'title'=>$item_title, 'content'=>$item_content);
    $s = new SiteToFeed($site_address, $item_pattern, $mapping, $global_pattern);
    $repr = $s->convert_to_rss($content);
    $items = $repr ? $repr['items'] : array();
    // 3. return joined records as single HTML buffer
    $result = array();
    foreach ($items as $article) {
      $link = $article['link'] ?? '';
      $title = $article['title'] ?? '';
      $content = $article['content'] ?? '';
      $result []= "<h3><a href='$link'>$title</a></h3>$content";
    }
    return implode("<BR>--------<BR>\n", $result);
  }

  /**
   * save site-to-feed definition
   * @param $feed_id: feed ID (for edited existing feed)
   * @param $site_address: site URL
   * @param $global_pattern: global search pattern (must be with {%} inside)
   * @param $item_pattern: repeated items search pattern (also with {%} inside)
   * @param $item_title: item title template
   * @param $item_link: item link template
   * @param $item_content: item content template
   * @param $rss_title: RSS title
   * @param $rss_group: to which RSS group it should belong
   * @return: error message (if any)
  **/
  public function saveSiteToFeed(
      $feed_id, $site_address, $global_pattern,
      $item_pattern, $item_title, $item_link, $item_content,
      $rss_title, $rss_group) {
    // if no feed_id - create new feed
    if (! $feed_id || strval($feed_id) == 'null') {
      // check rss_title for validity
      $error = $this->checkNameValidity($rss_title, 'subscr');
      if ( $error ) { return "Error: " . $error; }
      $error = $this->checkNameValidity($rss_group, 'group');
      if ( $error ) { return "Error: " . $error; }
      $this->insertNewFeed($rss_group, $rss_title, $rss_title, $site_address, $site_address, 0);
      $feed_id = _digest_hex($site_address);
    }
    // clean old record if exist
    $bindings0 = array('user_id'=>$this->user_id, 'fd_feedid'=>$feed_id);
    $query0 = "DELETE FROM `tbl_site_to_feed` WHERE `user_id`=:user_id AND `fd_feedid`=:fd_feedid";
    $this->db->execQuery($query0, $bindings0);

    $query1 = "INSERT INTO `tbl_site_to_feed`(
      `user_id`,
      `fd_feedid`,
      `htmlUrl`,
      `global_pattern`,
      `item_pattern`,
      `mapping`
    ) VALUES (
      :user_id, :fd_feedid, :htmlUrl, :global_pattern, :item_pattern, :mapping)";
    $mapping = json_encode(array(
        "title" => $item_title,
        "link" => $item_link,
        "content" => $item_content
    ));
    $bindings1 = array('user_id'=>$this->user_id, 'fd_feedid'=>$feed_id,
      'htmlUrl'=>$site_address, 'global_pattern'=>$global_pattern, 'item_pattern'=>$item_pattern,
      'mapping'=>$mapping);
    $this->db->execQuery($query1, $bindings1);

    return "";
  }

  /**
   * Get list of subscription groups
   * @return: list of group names for this user
  **/
  public function getSubscrGroups(){
    $query = "SELECT DISTINCT `group` FROM `tbl_subscr` ".
      "WHERE `user_id`=:user_id ORDER BY `group`";
    $groups = $this->db->fetchQueryRows($query, array("user_id" => $this->user_id));
    $result = array();
    foreach ($groups as $rec) {
      $result[] = $rec['group'];
    }
    return $result;
  }

  /**
   * Get all feeds as records
   * @return: list of records 'title', 'fd_feedid'
  **/
  public function getAllFeeds() {
    $where = array("user_id" => $this->user_id);
    $feeds = $this->db->queryTable('tbl_subscr',
      array('group', 'title', 'fd_feedid'),
      $where,
      array('group', 'index_in_gr'));
    return $feeds;
  }

  /**
   * Get all subscriptions as tree
   * @return: tree, where each record contains path, title, type and id
  **/
  public function getAllSubscrTree() {
    $result = array();
    $where = array("user_id" => $this->user_id);
    $subscr = $this->db->queryTable('tbl_subscr',
      array('group', 'title', 'fd_feedid'),
      $where,
      array('group', 'index_in_gr'));
    if ($subscr) {
      array_push($result, array('All', 'All', 'group', 'all'));
    }
    $prev_subpath = '';
    foreach ($subscr as $rec) {
        $subpath = $rec['group'];
        if ($prev_subpath != $subpath) {
            array_push($result, array("All^$subpath", $subpath, 'group', $subpath));
        }
        $prev_subpath = $subpath;
        $title = $rec['title'];
        array_push($result, array("All^$subpath^$title", $title, 'subscr', $rec['fd_feedid']));
    }
    // add views (watches) on level 1
    $watches = $this->getWatchesList();
    foreach ($watches as $watch) {
      $title = $watch['title'];
      if ($title != 'trash') {
        $fd_watchid = $watch['fd_watchid'];
        array_push($result, array($title, $title, 'watch', $fd_watchid));
      }
    }
    return $result;
  } // getAllSubscrTree

  /**
   * Collect (in a dictionary) all user-specific data
   * @return: dictionary with keys 'system', 'subscr', 'watches', 'articles'
   *  under 'system': login_name, full_name, db_version, app_version
   *  under 'subscr': list of records {'group', 'text', 'title', 'htmlUrl', 'xmlUrl', 'index_in_gr'}
   *  under 'watches': list of records {'fd_watchid', 'title', 'rules'}
  **/
  public function collectUserData() {
    global $APP_VERSION;
    $query = "SELECT `full_name`, `login_name`, `email` FROM `tbl_users` WHERE `user_id`=:user_id";
    $bindings = array( 'user_id'   => $this->user_id );
    $user_data = $this->db->fetchSingleRow($query0, $bindings);
    $watches_data = $this->getWatchesList();
    $watches_data = $this->convertWatchesForDump($watches_data);
    $result = array(
      'system' => array('app_version'=>$APP_VERSION,
        'full_name'=>$user_data[0],
        'login_name'=>$user_data[1],
        'email'=>$user_data[2]),
      'subscr' => $this->getSubscrForExport(),
      'watches' => $watches_data,
      'articles' => $this->getActualArticles()
    );
    return $result;
  }

  /**
   * Convert watches for dump
   * @param $watches_data: array of all watch descriptors
   * @return: list of correct formatted objects
  **/
  public function convertWatchesForDump($watches_data) {
    $result = array();
    foreach ($watches_data as $watch) {
      if ( $this->isReservedWatch($watch['fd_watchid']) ) {
        continue;
      }
      $rules = array();
      foreach ($watch['rules'] as $rule) {
        $rule_id = $rule['rl_id'];
        $conditions = array();
        foreach ($rule['where'] as $chk_text) {
          $conditions [ ]= array('chk_text' => $chk_text);
        }
        $rules[$rule_id] = array(
          'title' => $rule['title'],
          'rl_type'  => 'text',
          'conditions' => $conditions
        );
      }
      $rec = array(
        'fd_watchid' => $watch['fd_watchid'],
        'title'      => $watch['title'],
        'rules'      => $rules
      );
      $result []= $rec;
    }
    return $result;
  }

  /**
   * Generate YAML for user's watches (filters)
   * @return: text buffer with YAML representation
  **/
  public function exportWatches($format) {
    switch($format) {
      case "yaml":
        $watches_data = $this->getWatchesList();
        $watches_data = $this->convertWatchesForDump($watches_data);
        $result = spyc_dump($watches_data);
        break;
      # TODO: support other formats: XML/JSON/...
      default:
        $result = "Error: unsupported format - $format";
        break;
    }
    return $result;
  }

  /**
   * Load YAML with user's watches (filters)
   * @param $watches_source: input YAML buffer text
   * @return: error message (if any)
  **/
  public function loadWatches($watches_source) {
    $err = '';
    # Remove existing watches and their conditions
    $bindings = array('user_id'=>$this->user_id);
    $query1 = "DELETE FROM `tbl_rules_text` WHERE `user_id`=:user_id";
    $this->db->execQuery($query1, $bindings);
    $query2 = "DELETE FROM `tbl_rules` WHERE `user_id`=:user_id";
    $this->db->execQuery($query2, $bindings);
    $query3 = "DELETE FROM `tbl_watches` WHERE `user_id`=:user_id";
    $this->db->execQuery($query3, $bindings);
    #
    # parse input buffer
    # [{"fd_watchid":"tag_serials","title":"Serials","rules":
    #   {"tag_serials_3":
    #     {"title":"By_content","rl_type":"text","conditions":
    #        [{"chk_text":"`fd_feedid`
    #
    # For each watch in top array:
    # create watch record in `tbl_watches`
    #   For each rule under watch.rules:
    #   create rule record in `tbl_rules`
    #     For each condition in rule.conditions:
    #     create condition record in `tbl_rules_text`
    #
    $watches = spyc_load($watches_source);
    $count = 0;
    foreach ($watches as $watch) {
       if ( ! array_key_exists('fd_watchid' , $watch) ) { continue; }
       $rl_action = ($watch['fd_watchid'] == 'trash') ? 'mark_read' : 'set_tag';
       $rl_act_arg = $watch['fd_watchid'];
       if ($watch['fd_watchid'] !== 'trash') {
         $count++;
         $bindings1 = array(
           'user_id'=>$this->user_id, 'watch_id'=>$watch['fd_watchid'], 'name'=>$watch['title'], 'sort_index'=>$count);
         $query1 = "INSERT INTO `tbl_watches` ".
           "(`user_id`, `fd_watchid`, `title`, `sort_index`) VALUES ".
           "(:user_id,  :watch_id,    :name,   :sort_index)";
         $this->db->execQuery($query1, $bindings1);
       }
       foreach ($watch['rules'] as $rule_id => $rule) {
         $bindings2 = array(
           'user_id'=>$this->user_id, 'rl_id'=>$rule_id, 'title'=>$rule['title'],
           'rl_action'=>$rl_action, 'rl_act_arg'=>$rl_act_arg);
         $query2 = "INSERT INTO `tbl_rules` ".
           "(`user_id`, `rl_id`, `title`, `rl_type`, `rl_action`, `rl_act_arg`) ".
           "VALUES ".
           "(:user_id,  :rl_id,  :title,  'text',    :rl_action,  :rl_act_arg)";
         $this->db->execQuery($query2, $bindings2);
         foreach ($rule['conditions'] as $condition) {
           $query3 = "INSERT INTO `tbl_rules_text` ".
             "(`user_id`, `rl_id`, `chk_text`) ".
             "VALUES ".
             "(:user_id,  :rl_id,  :chk_text)";
           $bindings3 = array(
             'user_id'=>$this->user_id, 'rl_id'=>$rule_id, 'chk_text'=>$condition['chk_text']);
           $this->db->execQuery($query3, $bindings3);
         }
       }
    }
    if ( ! $count ) { $err = "nothing imported (wrong format?)"; }
    return $err;
  }


  /**
   * Get subscriptions info as a list of records
   * `group`, `text`, `title`, `htmlUrl`, `xmlUrl`, `index_in_gr`
   * @return: array of dictionaries
  **/
  public function getSubscrForExport() {
    $bindings = array("user_id" => $this->user_id);
    $query = "SELECT `group`, `text`, `title`, `htmlUrl`, `xmlUrl`, `index_in_gr`, `rtl` ".
      "FROM `tbl_subscr` WHERE `user_id`=:user_id ".
      "ORDER BY `group`, `index_in_gr`";
    return $this->db->fetchQueryRows($query, $bindings);
  }

  /**
   * Generate OPML (XML) representation of groups and their feeds
   * @return: text buffer with XML
  **/
  public function exportOpml() {
    # get list of subscriptions
    $subscr = $this->getSubscrForExport();
    $opml = new GenOpml(array('version'=>'1.1'));
    $opml->setHead('RSS Subscriptions',
                   date_format(date_create(),"Y-m-d H:i:s"));

    $last_group = '';
    $opml_group = null;
    foreach ($subscr as $rec) {
      $group = $rec['group'];
      if ($last_group != $group) {
        if ($opml_group) {
          $opml->appendToBody($opml_group->toStr());
        }
        $opml_group = new XmlPairTag('outline text="'.$group.'"', 2);
      }
      $rss = array(
        'text'    => urlencode($rec['text']),
        'title'   => urlencode($rec['title']),
        'htmlUrl' => urlencode($rec['htmlUrl']),
        'xmlUrl'  => urlencode($rec['xmlUrl'])
      );
      if ($rec['rtl']) {
        $rss['rtl'] = urlencode($rec['rtl']);
      }
      $outline = new XmlSingleTag('outline', 3, $rss);
      $opml_group->array_push($outline->toStr());
      $last_group = $group;
    }
    if ($opml_group) {
      $opml->appendToBody($opml_group->toStr());
    }
    return $opml->toStr();
  }

  /**
   * Export articles
   * @param $format: output format
   * @return: all unread/flagged articles for this user
  **/
  public function exportArticles($format) {
    switch($format) {
      case "json":
        $articles_data = $this->getActualArticles();
        $result = json_encode($articles_data);
        break;
      # TODO: support other formats: XML/YAML/...
      default:
        $result = "Error: unsupported format - $format";
        break;
    }
    return $result;
  }

  /**
   * Get actual (unread/flagged) articles
   * @return: list of records with article data
  **/

  public function getActualArticles() {
    $query = "SELECT `fd_postid`, `fd_feedid`, `title`,
      CONVERT(`description` USING utf8) as description, `link`, `guid`,
      `author`, `categories`, `read`, `flagged`,
      `gr_original_id`, `timestamp`
 FROM `tbl_posts`
WHERE `user_id` = :user_id
  AND (`read` <> 1 OR `flagged` = 1)";
    $bindings = array('user_id'=>$this->user_id);
    $db_data = $this->db->fetchQueryRows($query, $bindings);
    $result = array();
    $keys = array('fd_postid', 'fd_feedid', 'title', 'description', 'link',
      'guid', 'author', 'categories', 'read', 'flagged', 'gr_original_id', 'timestamp');
    foreach ($db_data as $r) {
      $result []= array_intersect_key($r, array_flip($keys));
    }
    return $result;
  }

  /**
   * Import articles from serialized source (dump file)
   * @param $articles: parsed list of records
     'fd_postid', 'fd_feedid', 'title', 'description', 'link',
     'guid', 'author', 'categories', 'read', 'flagged', 'gr_original_id',
     'timestamp'
   * @return: insert & update counts
  **/
  public function importArticles($articles) {
    $inserted = 0;
    $updated = 0;
    # get all article IDs for this user
    $query1 = "SELECT `fd_postid` FROM `tbl_posts` WHERE `user_id` = :user_id";
    $bindings1 = array('user_id'=>$this->user_id);
    $db_ids_rec = $this->db->fetchQueryRows($query1, $bindings1);
    $db_ids = array();
    foreach ($db_ids_rec as $rec) {
      $db_ids []= $rec['fd_postid'];
    }
    foreach ($articles as $rec) {
      $post_id = $rec['fd_postid'];
      $bindings2 = $rec;
      $bindings2['user_id'] = $this->user_id;
      # insert or update record
      if (in_array($post_id, $db_ids)) {
        $query2 = "UPDATE `tbl_posts`
          SET
          `link`=:link, `title`=:title, `author`=:author, `categories`=:categories, `timestamp`=:timestamp,
          `description`=:description, `guid`=:guid, `fd_feedid`=:fd_feedid, `gr_original_id`=:gr_original_id,
          `read`=:read, `flagged`=:flagged
          WHERE `user_id` = :user_id AND `fd_postid` = :fd_postid";
        $updated += 1;
      } else {
        $query2 = "INSERT INTO `tbl_posts`".
        "(`user_id`, `link`, `title`, `author`, `categories`, `timestamp`, `description`, `fd_postid`, `guid`, `fd_feedid`, `gr_original_id`, `read`, `flagged`) " .
        "VALUES ".
        "(:user_id,  :link,  :title,  :author,  :categories,  :timestamp,  :description,  :fd_postid,  :guid,  :fd_feedid,  :gr_original_id,  :read,  :flagged)";
        $inserted += 1;
      }
      $this->db->execQuery($query2, $bindings2);
    }
    return array('' => $inserted, '' => $updated);
  }

  /**
   * Get watches list
   * @return: list of watches, where each element contains:
   *   title=><name>,
   *   fd_watchid=><name>/tag_<name>,
   *   queries=>[list of queries],
   *   rules=>[rules tree]
  **/
  public function getWatchesList() {
    $result = array();
    foreach ($this->builtin_watches as $watch_name) {
      $result[] = array('title'=>ucfirst($watch_name), 'fd_watchid'=>$watch_name);
    }
    $plist = $this->db->queryTableRecords('tbl_watches',
       array('user_id'=>$this->user_id), 'sort_index');
    $plist[] = array('title'=>'trash', 'fd_watchid'=>'trash');
    foreach ($plist as $watch) {
      $rl_action = ($watch['fd_watchid'] == 'trash') ? 'mark_read' : 'set_tag';
      $prules = $this->db->queryTableRecords('tbl_rules',
        array('rl_act_arg'=>$watch['fd_watchid'],
              'rl_action'=>$rl_action,
              'user_id'=>$this->user_id)
      );
      $queries = array();
      $rules = array();
      foreach ($prules as $rule){
        $where = array();
        $gr_limit_rule='';
        # query in tbl_rules_text / tbl_rules_simple
        $ptext = '';
        if($rule['rl_type'] == 'text') {
          $ptext = $this->db->queryTableRecords('tbl_rules_text',
            array('rl_id'=>$rule['rl_id'], 'user_id'=>$this->user_id));
          foreach ($ptext as $p) {
            $chk_text = $p['chk_text'];
            if (strpos($chk_text, '`fd_feedid` IN ') !== false) {
              $gr_limit_rule = $chk_text;
            } else {
              $where[] = $chk_text;
            }
          }
        }
        else # 'simple'
        {
          $ptext = $this->db->queryTableRecords('tbl_rules_simple',
            array('rl_id'=>$rule['rl_id'], 'user_id'=>$this->user_id));
          foreach ($ptext as $p) {
            $where[] = sprintf("`%s` %s '%s'",
              $p['chk_field'], $p['chk_op'], $p['chk_arg']);
          }
        }
        $where = array_map(
          function ($v) { return preg_replace('/(\s+OR)+ /', ' OR ', $v); },
          $where);
        if ($gr_limit_rule) {
          array_unshift($where, $gr_limit_rule);
        }
        $rule['where'] = $where;
        if ($where) {
          $queries[] = '( '.implode(" ) AND ( ", $where).' )';
        }
        $rules[] = $rule;
      }
      $watch['queries'] = $queries;
      $watch['rules'] = $rules;
      $result[] = $watch;
    }

    return $result;
  }

  /**
   * Generate HTML code for rule editing
   * @param $watch_id: watch ID
   * @param $rule_id: rule ID
  **/
  public function editRule($watch_id, $rule_id) {
    $result = array();

    $watches = $this->getWatchesList();
    # find watch by ID
    $watch = '';
    foreach ($watches as $w) {
      if ($w['fd_watchid'] == $watch_id) {
        $watch = $w;
        break;
      }
    }
    if (! $watch) { return "Not found watch $watch_id<BR>\n"; }
    # find rule inside watch
    $rule = '';
    foreach ($watch['rules'] as $r) {
      if ($r['rl_id'] == $rule_id) {
        $rule = $r;
        break;
      }
    }
    if (! $rule) { return "Not found rule $rule_id<BR>\n"; }

    $rule_title = $rule['title'];

    $result[] = "<div class=\"input-group mb-3\">";
    $result[] = "  <span class=\"input-group-text\">Name</span>";
    $result[] = "  <input type=\"text\" class=\"form-control\" id=\"rule_title\" style=\"min-width: 8rem;\" value=\"$rule_title\" placeholder=\"Unique rule name\">";
    $result[] = "  </div>";
    $result[] = "</div>";
    $where = $rule['where'];
    $rule_group = 'any';
    if ($where && substr($where[0], 0, 11) == '`fd_feedid`') {
      $rule_group = explode("'", $where[0])[1];
      $where = array_slice($where, 1);
    }
    $selected = ('any' === $rule_group)? 'selected' : '';
    $result[] = "<select class=\"form-select mb-3\" id=\"group-limitation\">";
    $result[] = "<option $selected value=\"any\">For feeds from any group</option>";
    $groups = $this->getSubscrGroups();
    foreach ($groups as $g) {
      $selected = ($g === $rule_group)? 'selected' : '';
      $result[] = "<option $selected value=\"$g\">For feeds from group $g</option>";
    }
    $result[] = "</select>";
    # show sequence of "OR" conditions
    foreach ($where as $cond_line) {
      $result[] = "<span class=\"rule-or-group\">";
      foreach (explode(' OR ', $cond_line) as $cond) {
        preg_match('/`(.+)` (\S.*) \'(.*)\'/', $cond, $matches);
        $field = $matches[1];
        $op = str_replace('like', 'MATCH', $matches[2]);
        $op = str_replace('LIKE', 'MATCH', $op);
        $val = str_replace('%', '*', $matches[3]);
        $result = array_merge($result, $this->editRuleOrNode($field, $op, $val));
        $result[] = "<span class=\"rule_logical_delimiter edit-rule-group\"> OR </span>";
      }
      $result = array_merge($result, $this->editRuleOrNode('', '==', ''));
      $result[] = "</span>";
      $result[] = "<p class=\"rule_logical_delimiter edit-rule-delimiter\">AND</p>";
    }
    $result[] = "<span class=\"rule-or-group\">";
    $result = array_merge($result, $this->editRuleOrNode('', '==', ''));
    $result[] = "</span>";
    return implode("\n", $result);
  }

  /**
   * Create HTML code for editing rule 'OR' node
   * @param $field: article field name (empty means 'skip')
   * @param $op: comparison operation
   * @param $val: comparison value or pattern
   * @return: array of HTML code
  **/
  private function editRuleOrNode($field, $op, $val) {
    $result = array();
    $field_options = array('title', 'link', 'categories', 'description', '');
    $op_options = array('==', '!=', 'MATCH', 'NOT MATCH');
    $result[] = "<div class=\"input-group rule-or-node edit-rule-group\">";
    $result[] = "  <select class=\"btn btn-outline-secondary form-select\" aria-label=\"select field\">";
    foreach ($field_options as $opt) {
      $selected = $field === $opt ? 'selected' : '';
      $result[] = "  <option value=\"$opt\" $selected>$opt</option>";
    }
    $result[] = "  </select>";
    $result[] = "  <select class=\"btn btn-outline-secondary form-select\" aria-label=\"select op\">";
    foreach ($op_options as $opt) {
      $selected = $op === $opt ? 'selected' : '';
      $result[] = "  <option value=\"$opt\" $selected>$opt</option>";
    }
    $result[] = "  </select>";
    $result[] = "  <input value=\"$val\" type=\"text\" class=\"form-control\">";
    $result[] = "</div>";
    return $result;
  }

  /**
   * move rule to other watch
   * @param $rule_id: rule ID
   * @param $watch_id: destination watch ID
   * @return: error message (if any)
  **/
  public function moveRuleToWatch($rule_id, $watch_id) {
    $result = 'Ok';
    $bindings = array('user_id'=>$this->user_id, 'watch_id' => $watch_id, 'rule_id' => $rule_id);
    $query = "UPDATE `tbl_rules` SET `rl_act_arg` = :watch_id
        WHERE `user_id` = :user_id AND `rl_id` = :rule_id";
    $this->db->execQuery($query, $bindings);
    return $result;
  }

  public function moveWatch($watch_id, $delta) {
    if (! is_numeric($delta) ) {
      return "Error: delta '$delta' should be integer";
    }
    # Get $curr_index for this $watch_id
    $bindings0 = array('user_id'=>$this->user_id, 'watch_id' => $watch_id);
    $query0 = "SELECT `sort_index` FROM `tbl_watches`
        WHERE `user_id` = :user_id AND `fd_watchid` = :watch_id";
    $curr_index = $this->db->fetchSingleResult($query0, $bindings0);
    # Get $new_index relative to $curr_index according to $delta
    if ( $delta < 0 ) {
      $query1 = "SELECT MAX(`sort_index`) FROM `tbl_watches`
          WHERE `sort_index` < :curr_index AND `user_id` = :user_id";
    } else {
      $query1 = "SELECT MIN(`sort_index`) FROM `tbl_watches`
          WHERE `sort_index` > :curr_index AND `user_id` = :user_id";
    }
    $bindings1 = array('user_id'=>$this->user_id, 'curr_index' => $curr_index);
    $new_index = $this->db->fetchSingleResult($query1, $bindings1);
    # Exit if $new_index is NULL
    if (! $new_index ) {
      return "Error: can't move in this direction";
    }
    # Exchange indexes
    $bindings2 = array('user_id'=>$this->user_id, 'curr_index' => $curr_index, 'new_index' => $new_index);
    $query2 = "UPDATE `tbl_watches` SET `sort_index` = :curr_index
      WHERE `user_id` = :user_id AND `sort_index` = :new_index";
    $bindings3 = array('user_id'=>$this->user_id, 'new_index' => $new_index, 'watch_id' => $watch_id);
    $query3 = "UPDATE `tbl_watches` SET `sort_index` = :new_index
      WHERE `user_id` = :user_id AND `fd_watchid` = :watch_id";
    $this->db->execQuery($query2, $bindings2);
    $this->db->execQuery($query3, $bindings3);
    return "Ok";
  }

  /**
   * create watch
   * @param $name: new display name for watch
   * @return: error message (if any) or new watch ID
  **/
  public function createWatch($name) {
    if ( $this->isReservedWatch(strtolower($name)) ) { return "Error: reserved name"; }
    $error = $this->checkNameValidity($name, 'watch');
    if ( $error ) { return "Error: " . $error; }
    // Check that such name is not in use
    $query0 = "SELECT COUNT(1) FROM `tbl_watches` WHERE LOWER(`title`)=LOWER(:name) AND `user_id`=:user_id";
    $bindings0 = array('name'=>$name, 'user_id'=>$this->user_id);
    $count0 = $this->db->fetchSingleResult($query0, $bindings0);
    if ($count0 != 0) { return "Error: this name already in use"; }
    $watch_id = 'tag_'.strtolower($name);
    $bindings1 = array('user_id'=>$this->user_id, 'watch_id' => $watch_id);
    $query1 = "SELECT COUNT(1) FROM `tbl_watches` WHERE `fd_watchid`=:watch_id AND `user_id`=:user_id";
    $count1 = $this->db->fetchSingleResult($query1, $bindings1);
    if ($count1 != 0) { return "Error: this id already in use"; }
    // Create record in table
    $bindings2 = array('user_id'=>$this->user_id);
    $query2 = "SELECT COALESCE(MAX(`sort_index`),0)+1 FROM `tbl_watches` WHERE `user_id` = :user_id";
    $count2 = $this->db->fetchSingleResult($query2, $bindings2);
    $bindings3 = array('name'=>$name, 'user_id'=>$this->user_id, 'watch_id' => $watch_id, 'sort_index'=>$count2);
    $query3 = "INSERT INTO `tbl_watches` ".
      "(`user_id`, `fd_watchid`, `title`, `sort_index`) VALUES ".
      "(:user_id,  :watch_id,    :name,   :sort_index)";
    $this->db->execQuery($query3, $bindings3);
    return $watch_id;
  }

  /**
   * delete watch
   * @param $watch_id: watch ID
  **/
  public function deleteWatch($watch_id) {
    $bindings = array('user_id'=>$this->user_id, 'watch_id' => $watch_id);
    // first, remove related logical expressions
    $query1 = "DELETE FROM `tbl_rules_text` WHERE `user_id`=:user_id AND ".
      "`rl_id` IN (SELECT r.`rl_id` FROM `tbl_rules` AS r WHERE r.`user_id`=:user_id AND r.`rl_act_arg`=:watch_id)";
    $this->db->execQuery($query1, $bindings);
    // then, remove rules
    $query2 = "DELETE FROM `tbl_rules` WHERE `user_id`=:user_id AND `rl_act_arg`=:watch_id";
    $this->db->execQuery($query2, $bindings);
    // finally remove watches
    $query3 = "DELETE FROM `tbl_watches` WHERE `user_id`=:user_id AND `fd_watchid`=:watch_id";
    $this->db->execQuery($query3, $bindings);
    // ensure consistency: clean this tag from all posts for this user
    $query4 = "UPDATE `tbl_posts` SET `gr_original_id`='' WHERE `user_id`=:user_id AND `gr_original_id`=:watch_id";
    $this->db->execQuery($query4, $bindings);
    return "";
  }

  /**
   * save watch name
   * @param $watch_id: watch ID
   * @param $name: new display name for watch
   * @return: error message (if any)
  **/
  public function saveWatchName($watch_id, $name) {
    if ($this->isReservedWatch(strtolower($name))) {
      return "Error: reserved watch name";
    }
    // Check that such name is not in use by OTHER watches
    $query0 = "SELECT COUNT(1) FROM `tbl_watches` WHERE LOWER(`title`)=LOWER(:name) AND `user_id`=:user_id AND `fd_watchid`!=:watch_id";
    $bindings = array('name'=>$name, 'user_id'=>$this->user_id, 'watch_id'=>$watch_id);
    $count = $this->db->fetchSingleResult($query0, $bindings);
    if ($count != 0) { return "Error: this name already in use"; }
    // Update name in table
    $query1 = "UPDATE `tbl_watches` SET `title`=:name WHERE `user_id`=:user_id AND `fd_watchid`=:watch_id";
    $this->db->execQuery($query1, $bindings);
    return "";
  }

  /**
   * add rule to watch
   * @param $watch_id: watch ID
   * @param $new_rule_name: new rule name
   * @return: error when such rule exists
  **/
  public function addRule($watch_id, $new_rule_name) {
    $query0 = "SELECT COUNT(1) FROM `tbl_rules` WHERE ".
      "`user_id`=:user_id AND `rl_act_arg`=:watch_id AND `title`=:title";
    $bindings0 = array('user_id' => $this->user_id,
      'title' => $new_rule_name, 'watch_id' => $watch_id);
    $count = $this->db->fetchSingleResult($query0, $bindings0);
    if ($count != 0) {
      return "Error: rule with such name already exist";
    }
    $query1 = "SELECT MAX(replace(`rl_id`, :watch_prefix, '')) ".
    "FROM `tbl_rules` WHERE `user_id`=:user_id AND `rl_act_arg`=:watch_id";
    $bindings1 = array('user_id' => $this->user_id, 'watch_id' => $watch_id,
        'watch_prefix' => $watch_id.'_');
    $max_rule_id = $this->db->fetchSingleResult($query1, $bindings1);
    if ($max_rule_id) { $max_rule_id += 1; }
    else { $max_rule_id = 1; }
    $query2 = "INSERT INTO `tbl_rules` ".
      "(`user_id`, `title`, `rl_id`, `rl_type`, `rl_action`, `rl_act_arg`) ".
      "VALUES ".
      "(:user_id, :title, :rl_id, :rl_type, :rl_action, :watch_id)";
    $bindings2 = array('user_id' => $this->user_id,
      'title' => $new_rule_name, 'rl_id' => $watch_id.'_'.$max_rule_id,
      'rl_type' => 'text',
      'rl_action' => ($watch_id=='trash')?'mark_read':'set_tag',
      'watch_id' => $watch_id);
    $this->db->execQuery($query2, $bindings2);
    return "";
  }
  /**
   * Update watch rule
   * @param $watch_id: watch ID
   * @param $rule_id: rule ID
   * @param $rule_name: display name for rule
   * @param $group_limitation: either group name or 'any'
   * @param $where: array of "or" conditions to be joined with "and"
   * @return: error message (if any)
  **/
  public function updateWatchRule($watch_id, $rule_id, $rule_name, $group_limitation, $where) {
    $error = '';
    # check if there any rule with different ID and the same name
    $query0 = "SELECT COUNT(1) FROM `tbl_rules` WHERE ".
      "`user_id`=:user_id AND `title`=:rule_name AND ".
      "`rl_id`!=:rule_id AND `rl_act_arg`=:watch_id";
    $bindings = array(
      'user_id'=>$this->user_id, 'rule_name'=>$rule_name, 'rule_id'=>$rule_id,
      'watch_id'=>$watch_id);
    $count = $this->db->fetchSingleResult($query0, $bindings);
    if ( $count != 0 ) {
      return "Error: rule with such name exists";
    }
    if ($watch_id == 'trash') {
      $rl_action = 'mark_read';
    } else {
      $rl_action = 'set_tag';
    }
    $rl_type = 'text';
    # delete rule data
    $query1 = "DELETE FROM `tbl_rules` WHERE `user_id`=:user_id ".
      "AND `rl_id`=:rule_id AND `rl_act_arg`=:watch_id";
    $bindings = array('user_id'=>$this->user_id, 'rule_id'=>$rule_id,
                      'watch_id'=>$watch_id);
    $this->db->execQuery($query1, $bindings);
    $query2 = "DELETE FROM `tbl_rules_text` WHERE `user_id`=:user_id ".
      "AND `rl_id`=:rule_id";
    $bindings = array('user_id'=>$this->user_id, 'rule_id'=>$rule_id);
    $this->db->execQuery($query2, $bindings);
    # recreate record for rule name
    $query3 = "INSERT INTO `tbl_rules` ".
      "(`user_id`, `title`, `rl_id`, `rl_type`, `rl_action`, `rl_act_arg`) VALUES ".
      "(:user_id, :rule_name, :rule_id, :rl_type, :rl_action, :watch_id)";
    $bindings = array('user_id'=>$this->user_id, 'rule_name'=>$rule_name, 'rule_id'=>$rule_id,
                      'rl_type'=>$rl_type, 'rl_action'=>$rl_action, 'watch_id'=>$watch_id);
    $this->db->execQuery($query3, $bindings);
    # recreate records for conditions (including group limitation)
    if ( $group_limitation != 'any' ) {
      $condition = "`fd_feedid` IN (SELECT `fd_feedid` FROM `tbl_subscr` ".
        "WHERE `user_id`=:user_id AND `group`='$group_limitation')";
      $query4 = "INSERT INTO `tbl_rules_text` ".
        "(`user_id`, `rl_id`, `chk_text`) VALUES ".
        "(:user_id, :rule_id, :condition)";
      $bindings = array('user_id'=>$this->user_id, 'rule_id'=>$rule_id, 'condition'=>$condition);
      $this->db->execQuery($query4, $bindings);
    }
    for ($i = 0; $i < count($where); $i++) {
      $condition = $where[$i];
      $query4 = "INSERT INTO `tbl_rules_text` ".
        "(`user_id`, `rl_id`, `chk_text`) VALUES ".
        "(:user_id, :rule_id, :condition)";
      $bindings = array('user_id'=>$this->user_id, 'rule_id'=>$rule_id, 'condition'=>$condition);
      $this->db->execQuery($query4, $bindings);
    }
    return $error;
  }

  /**
   * delete watch rule
   * @param $watch_id: watch ID
   * @param $rule_id: rule ID
  **/
  public function deleteRule($watch_id, $rule_id){
    # delete rule data
    $query1 = "DELETE FROM `tbl_rules` WHERE `user_id`=:user_id ".
      "AND `rl_id`=:rule_id AND `rl_act_arg`=:watch_id";
    $bindings = array('user_id'=>$this->user_id, 'rule_id'=>$rule_id,
                      'watch_id'=>$watch_id);
    $this->db->execQuery($query1, $bindings);
    $query2 = "DELETE FROM `tbl_rules_text` WHERE `user_id`=:user_id ".
      "AND `rl_id`=:rule_id";
    $bindings = array('user_id'=>$this->user_id, 'rule_id'=>$rule_id);
    $this->db->execQuery($query2, $bindings);
  }

  /**
   * Show rule (for review only)                     Rule: [ name|   ] [_!_]     [move to watch... v]
   * @param $watches: watches list                   For articles from [ Any group ]\\group Warez\\...
   * @param $watch_id: watch ID                      [title MATCH '*seazon*'] OR [description MATCH '*seazon*']    [.%.]
   * @param $rule_id: rule ID inside watch           [AND]  [...]
   * @return: HTML code for displaying rule
  **/
  public function showRule($watches, $watch_id, $rule_id) {
    $result = array();
    # find watch by ID
    $watch = '';
    foreach ($watches as $w) {
      if ($w['fd_watchid'] == $watch_id) {
        $watch = $w;
        break;
      }
    }
    if (! $watch) { return "Not found watch $watch_id<BR>\n"; }
    # find rule inside watch
    $rule = '';
    foreach ($watch['rules'] as $r) {
      if ($r['rl_id'] == $rule_id) {
        $rule = $r;
        break;
      }
    }
    if (! $rule) { return "Not found rule $rule_id<BR>\n"; }
    # show rule name with "rename" button and "move to watch..." selector
    $rule_title = $rule['title'];
    $result[] = "<div class=\"card mb-3\">";
    $result[] = "<div class=\"card-title\">";
    $result[] = "<div class=\"input-group\">";
    $result[] = "  <span class=\"input-group-text\">Rule</span>";
    $result[] = "  <input type=\"text\" readonly class=\"form-control rule-title-ro\" id=\"$rule_id\" style=\"min-width:8rem;\" value=\"$rule_title\" placeholder=\"Unique rule name\">";
    $result[] = "    <button class=\"btn btn-outline-secondary\" type=\"button\" onclick=\"openRuleEdit('$watch_id', '$rule_id');\"><i class=\"far fa-edit\"></i></button>";
    $result[] = "    <button class=\"btn btn-outline-secondary\" type=\"button\" onclick=\"deleteRule('$watch_id', '$rule_id');\"><i class=\"far fa-trash-alt\"></i></button>";
    # "move" selector
    $result[] = "    <button class=\"btn btn-outline-secondary dropdown-toggle\" type=\"button\" data-bs-toggle=\"dropdown\" aria-haspopup=\"true\" aria-expanded=\"false\">Move to watch...</button>";
    $result[] = "    <ul class=\"dropdown-menu\">";
    foreach ($watches as $w) {
      if ($w['fd_watchid'] === $watch_id) { continue; }
      $result[] = "      <li><a class=\"dropdown-item\" href=\"javascript:moveRuleToWatch('$rule_id','".$w['fd_watchid']."');\">".$w['title']."</a></li>";
    }
    $result[] = "    </ul>";
    $result[] = "</div>";
    $result[] = "</div>";
    $result[] = "<div class=\"card-body\">";
    # show group limitation
    # take group name from rule where[0] if it looks like
    # `fd_feedid` IN (SELECT `fd_feedid` FROM `tbl_subscr` WHERE `group` = 'Warez')
    $rule_group = 'any';
    $where = $rule['where'];
    if ($where && substr($where[0], 0, 11) == '`fd_feedid`') {
      $rule_group = explode("'", $where[0])[1];
      $where = array_slice($where, 1);
    }
    $group_limitation = ('any' === $rule_group)? 'For feeds from any group' : "For feeds from group $rule_group";
    $result[] = "<span class=\"rule_where_cond badge rounded-pill bg-secondary mb-3\">$group_limitation</span>";
    # show sequence of "OR" conditions
    $first_AND = True;
    foreach ($where as $cond_line) {
      if (!$first_AND) { $result[] = "<p>AND</p>"; }
      $first_AND = False;
      $result[] = "<p>";
      $first_OR = True;
      foreach (explode(' OR ', $cond_line) as $cond) {
        if (!$first_OR) {
          $result[] = "<span class=\"rule_logical_delimiter\"> OR </span>";
        }
        $first_OR = False;
        $cond = str_replace('`', '', $cond);
        $cond = str_replace(' like ', ' MATCH ', $cond);
        $cond = str_replace(' LIKE ', ' MATCH ', $cond);
        $cond = str_replace('%', '*', $cond);
        $cond = str_replace('==', '=', $cond);
        $result[] = "<span class=\"rule_where_cond badge rounded-pill bg-secondary\">$cond</span>";
      }
      $result[] = "</p>";
    }
    $result[] = "</div>";
    $result[] = "</div>";
    return implode("\n", $result);
  }

  /**
   * Rerun filters from watches
   * @return: summary message
  **/
  public function rerunFilters($clean=False) {
    $watches = $this->getWatchesList();
    # first try to apply 'trash' rule
    # than all the rest
    $trash = '';
    $tag_watches = array();
    if ($clean) {
      $this->db->execQuery(
        "UPDATE `tbl_posts` SET `gr_original_id` = '' WHERE `user_id`=:user_id",
        array('user_id'=>$this->user_id));
    }
    foreach ($watches as $w) {
      if ($w['title'] == 'trash') {
        $trash = $w;
      } else {
        $tag_watches[] = $w;
      }
    }
    array_unshift($tag_watches, $trash);
    $count = 0;
    foreach ($tag_watches as $watch) {
      // do not count built-in watches
      if ( ! $this->isReservedWatch($watch['fd_watchid']) ) {
        $count += 1;
      }
      if ($watch['queries'] ?? null) {
        $set = ($watch['title'] == 'trash') ?
          array('read' => 1) :
          array('gr_original_id' => $watch['fd_watchid']);
        $extra_cond = ($watch['title'] == 'trash') ?
          "(`flagged` != 1) AND " :
          "(`gr_original_id` = '') AND ";
        foreach ($watch['queries'] as $where) {
          if ($where) {
            $where = "$extra_cond $where AND (`read` = 0) AND `user_id`=:user_id";
            $set['user_id'] = $this->user_id;
            $this->db->updateRecordsByFields('tbl_posts', $set, $where);
          }
        }
      }
    }
    return "Applied $count watch-filters";
  }

  /**
   * Check if RSS record exists
   * @param $rec: record dictionary with 'fd_postid' inside
   * @return: zero if such record not found
  **/
  public function checkRssRecordExist($rec) {
    $query = "SELECT COUNT(1) FROM `tbl_posts` WHERE " .
        "`user_id` = :user_id AND `fd_postid` = :fd_postid";
    $bindings = array('user_id' => $this->user_id, 'fd_postid' => $rec['fd_postid']);
    return $this->db->fetchSingleResult($query, $bindings);
  } // checkRssRecordExist

  /**
   * Store recieved RSS items in DB
   * 1. get latest timestamp for this feed_id
   * 2. if found - skip items older than this timestamp
   * @param $items: list of records
   * @param $feed_id: feed ID
   * @return: actually inserted records count
  **/
  public function storeRssItems($items, $feed_id) {
    $query = "SELECT MAX(`timestamp`) FROM `tbl_posts` " .
        "WHERE `user_id` = :user_id  AND `fd_feedid` = :feed_id";
    $bindings = array('user_id' => $this->user_id, 'feed_id' => $feed_id);
    $last_timestamp = $this->db->fetchSingleRow($query, $bindings);
    if ($last_timestamp) {
      $last_timestamp = strtotime($last_timestamp[0]);
    }

    $inserted_count = 0;
    $time_now = time();
    if (! $items) { return $inserted_count; }
    foreach ($items as $rec) {
      if ($rec['timestamp'] > $time_now) {
        $rec['timestamp'] = $time_now;
      }
      if ($last_timestamp && $rec['timestamp'] < $last_timestamp) {
        // echo "skip ".$rec['timestamp']." is older than $last_timestamp<BR>\n"; ### DEBUG @@@
        continue;
      }
      /*
          $this->user_id => user_id
          'link'      => link,
          'title'     => title,
          'author'    => author,
          'categories'=> categories,
          'dateStr'   => $pubDate,
          'timestamp' => timestamp,
          'content'   => description,
          'guid'      => guid,
          'fd_postid' => fd_postid,
          $feed_id    => fd_feedid
             0        => read
             0        => flagged
      */
      if ($this->checkRssRecordExist($rec)) { continue; }
      // the record should be inserted
      $bindings = array(
          'user_id'    => $this->user_id,
          'link'       => (string) $rec['link'],
          'title'      => (string) $rec['title'],
          'author'     => (string) $rec['author'],
          'categories' => (string) $rec['categories'],
          'timestamp'  => date("Y-m-d H:i:s", $rec['timestamp']),
          'description'=> (string) $rec['description'],
          'fd_postid'  => $rec['fd_postid'],
          'guid'       => (string) $rec['guid'],
          'fd_feedid'  => $feed_id,
          'gr_original_id' => '',
          'read'       => 0,
          'flagged'    => 0);
      $query = "INSERT INTO `tbl_posts` " .
        "(`user_id`, `link`, `title`, `author`, `categories`, `timestamp`, `description`, `fd_postid`, `guid`, `fd_feedid`, `gr_original_id`, `read`, `flagged`) " .
        "VALUES ".
        "(:user_id,  :link,  :title,  :author,  :categories,  :timestamp,  :description,  :fd_postid,  :guid,  :fd_feedid,  :gr_original_id,  :read,  :flagged)";
      $this->db->execQuery($query, $bindings);
      // reverse action for `description` SELECT CONVERT(`description` USING utf8) ...
      $inserted_count++;
    }
    return $inserted_count;
  } // storeRssItems


  // ------------------( Retrieve items from DB )----------------------

  /**
   * Get personal settings for retrieve items routines (Feed/Watch/Group)
   * @return: $show_articles(read/unread), $order_articles=(time/name)
  **/
  public function settingsForRetrieve() {
    $personal_settings = $this->getAllPersonalSettings();
    $show_articles = ($personal_settings['show_articles'] ?? '') ? $personal_settings['show_articles'] : 'unread';
    $order_articles = ($personal_settings['order_articles'] ?? '') ? $personal_settings['order_articles'] : 'time';
    return array($show_articles, $order_articles);
  }

  /**
   * Retrieve from DB all items, matching given pattern
   * @param $pattern: string to find in titles and content
   * @return: list article records (maybe empty),
   *          including original feed references - fd_feedid,feed_name
  **/
  public function findItems($pattern) {
    list($show_articles, $order_articles) = $this->settingsForRetrieve();
    $query = RssApp::POSTS_SELECT .
      "AND (p.`title` LIKE :pattern OR p.`description` LIKE :pattern)";
    $query .= " ORDER BY ".(('time' === $order_articles) ? "`timestamp` DESC" : "`title`");
    $bindings = array('user_id' => $this->user_id, 'pattern' => "%$pattern%");
    $items = $this->db->fetchQueryRows($query, $bindings);
    $result = $this->addWatchesInfo($items);
    $result = $this->addFeedInfo($result);
    $result = $this->markKeywordsInItems($result);
    return $result;
  }

  /**
   * Get a list of IDs for articles in state "unread" and not "bookmarked"
   * @param $type: group/subscr/watch
   * @param $id: slice ID (string)
  **/
  public function getUnreadNonmarked($type, $id) {
    $result = array();
    $bindings = array(
      'user_id'    => $this->user_id);
    $query = "SELECT `fd_postid` FROM `tbl_posts` p ".
          "WHERE p.`user_id` = :user_id ".
          "AND   p.`flagged` = 0 ".
          "AND   p.`read` = 0";
    if ($type == 'subscr') {
      # similar to retrieveRssItems($id)
      $query .=
        " AND p.`fd_feedid` = :fd_feedid";
      $bindings['fd_feedid'] = $id;
    } elseif ($type == 'group') {
      # similar to retrieveGroupItems($id)
      if (strtolower($id) == 'all') {
        $specific_group = '';
      } else {
        $specific_group = "AND `group` = :group";
        $bindings['group'] = $id;
      }
      $query .=
        " AND p.`fd_feedid` IN (SELECT `fd_feedid` FROM `tbl_subscr` WHERE ".
        "`user_id` = :user_id $specific_group)";
    } elseif ($type == 'watch') {
      # similar to retrieveWatchItems($id)
      list($watch_title, $watch_description, $where) = $this->getWatchDescrAndFilter($id);
      if ($where) {
        $query .=
         ' AND ' . implode(' AND ', $where);
      }
    } else { # search
      # similar to findItems($id) - TODO
    }
    $items = $this->db->fetchQueryRows($query, $bindings);
    foreach ($items as $rec) {
      $result []= $rec['fd_postid'];
    }
    return $result;
  }

  /**
   * Retrieve from DB RSS items related to given group
   * @param $group_id: group ID
   * @return: RSS articles according to "show" filter
  **/
  public function retrieveGroupItems($group_id) {
    list($show_articles, $order_articles) = $this->settingsForRetrieve();
    $bindings = array(
      'user_id'    => $this->user_id,
      'group'  => $group_id
    );
    if ('read'   === $show_articles) { $bindings['read'] = 1; }
    if ('unread' === $show_articles) { $bindings['read'] = 0; }
    $query = RssApp::POSTS_SELECT .
      "AND p.`fd_feedid` IN (SELECT `fd_feedid` FROM `tbl_subscr` WHERE ".
      "`user_id` = :user_id AND `group` = :group)";
    if (array_key_exists('read', $bindings)) {
      $query .= " AND p.`read` = :read";
    }
    $query .= " ORDER BY ".(('time' === $order_articles) ? "`timestamp` DESC" : "`title`");
    $items = $this->db->fetchQueryRows($query, $bindings);
    $result = $this->addWatchesInfo($items);
    $result = $this->addFeedInfo($result);
    $result = $this->markKeywordsInItems($result);
    return $result;
  }

  /**
   * Retrieve from DB RSS items related to given feed
   * @param $feed_id: feed ID
   * @return: RSS info dictionary and feed items according to "show" filter
  **/
  public function retrieveRssItems($feed_id) {
    list($show_articles, $order_articles) = $this->settingsForRetrieve();
    # Read info about feed
    $query1 = "SELECT s.*, stf.`mapping` FROM `tbl_subscr` s ".
      "LEFT JOIN `tbl_site_to_feed` stf ".
      "ON stf.user_id = s.`user_id` AND stf.fd_feedid = s.fd_feedid ".
      "WHERE s.`fd_feedid` = :fd_feedid AND s.`user_id` = :user_id";
    $rss_info = $this->db->fetchSingleRow($query1,
      array('fd_feedid' => $feed_id, 'user_id' => $this->user_id));

    $bindings = array(
      'user_id'    => $this->user_id,
      'fd_feedid'  => $feed_id
    );
    if ('read'   === $show_articles) { $bindings['read'] = 1; }
    if ('unread' === $show_articles) { $bindings['read'] = 0; }
    $query2 = RssApp::POSTS_SELECT .
      "AND p.`fd_feedid` = :fd_feedid";
    if (array_key_exists('read', $bindings)) {
      $query2 .= " AND p.`read` = :read";
    }
    $query2 .= " ORDER BY ".(('time' === $order_articles) ? "`timestamp` DESC" : "`title`");
    $items = $this->db->fetchQueryRows($query2, $bindings);
    # read info about user-defined watches and add 'watch_title'
    $result = $this->addWatchesInfo($items);
    $result = $this->addFeedInfo($items);
    $result = $this->markKeywordsInItems($result);
    return array($rss_info, $result);
  } // retrieveRssItems

  /**
   * Add watches info to found articles
   * @param $items: list of article records with 'gr_original_id' inside
   * @return: same list with 'watch_title' fields
  **/
  function addWatchesInfo($items) {
    $watches = array();
    $w_query = 'SELECT `title`, `fd_watchid` FROM `tbl_watches` WHERE '.
        '`user_id`=:user_id';
    $w_records = $this->db->fetchQueryRows(
      $w_query, array('user_id'=>$this->user_id));
    foreach ($w_records as $r) {
      $watches[$r['fd_watchid']] = $r['title'];
    }
    $result = array();
    foreach ($items as $item) {
      if (array_key_exists($item['gr_original_id'], $watches)) {
        $watch_title = $watches[$item['gr_original_id']];
      } else {
        $watch_title = ucfirst($item['gr_original_id']);
      }
      $item['watch_title'] = $watch_title;
      $result[] = $item;
    }
    return $result;
  }

  /**
   * Get watch human-readable description and SQL filter
   * @param $watch_id: watch ID (string)
   * @return: array with title, textual description and SQL WHERE condition
  **/
  function getWatchDescrAndFilter($watch_id) {
    $where = array();
    $watch_title = ucfirst($watch_id);
    # check built-in watches
    if ($watch_id == 'all' || $watch_id == 'trash') {
      # do nothing - take all
      $watch_description = 'all articles from all feeds';
    } elseif ($watch_id == 'today') {
      $where[] = "`timestamp` >= $this->NOW - INTERVAL 1 DAY";
      $watch_description = 'articles received today';
    } elseif ($watch_id == 'older') {
      $where[] = "`timestamp` < $this->NOW - INTERVAL 1 DAY";
      $watch_description = 'articles received yesterday and older';
    } elseif ($watch_id == 'bookmarked') {
      $where[] = "`flagged` != 0";
      $watch_description = 'bookmarked articles';
    } elseif ($watch_id == 'unfiltered') {
      $watch_description = 'articles not belonging to any watch';
      $where[] = "`gr_original_id` = ''";
    } elseif (strpos($watch_id, 'tag_' ) === 0) {
      $where[] = "`gr_original_id` = '$watch_id'";
      # get user-defined watch name
      $w_query = 'SELECT `title` FROM `tbl_watches` WHERE '.
        '`user_id`=:user_id AND `fd_watchid`=:fd_watchid';
      $w_bindings = array('user_id'=>$this->user_id, 'fd_watchid'=>$watch_id);
      $watch_title = $this->db->fetchSingleResult($w_query, $w_bindings);
      $watch_description = 'articles matching watch (filter) condition';
    }
    return array($watch_title, $watch_description, $where);
  }

  /**
   * Retrieve watch items from DB
   * @param $watch_id: watch ID (builtin or user-defined filter tag)
   * @return: watch title, description and list of watch items according to "show" filter
  **/
  public function retrieveWatchItems($watch_id) {
    list($show_articles, $order_articles) = $this->settingsForRetrieve();
    $bindings = array('user_id' => $this->user_id);

    list($watch_title, $watch_description, $where) = $this->getWatchDescrAndFilter($watch_id);
    if ('read'   === $show_articles) { $bindings['read'] = 1; }
    if ('unread' === $show_articles) { $bindings['read'] = 0; }
    if (array_key_exists('read', $bindings)) {
      $where[] = "p.`read` = :read";
    }
    $query = RssApp::POSTS_SELECT;
    if ($where) {
      $query .= " AND ". implode(' AND ', $where);
    }
    $query .= " ORDER BY ".(('time' === $order_articles) ? "`timestamp` DESC" : "`title`");
    $items = $this->db->fetchQueryRows($query, $bindings);
    # add 'feed_info' to items - `xmlUrl` `title` `fd_feedid`
    $result = $this->addFeedInfo($items);
    $result = $this->markKeywordsInItems($result);

    return array($watch_title, $watch_description, $result);
  }

  /**
   * Add feed info to articles
   * @param $items: list of article records with 'fd_feedid' inside
   * @return: same list with 'feed_info' fields
  **/
  function addFeedInfo($items) {
    $tbl_subscr = $this->db->queryTableRecords('tbl_subscr', array("user_id" => $this->user_id));
    $feeds = array();
    foreach ($tbl_subscr as $rec) {
      $feeds[$rec['fd_feedid']] = $rec;
    }
    $result = array();
    foreach ($items as $item) {
      $item['feed_info'] = $feeds[$item['fd_feedid']];
      $result[] = $item;
    }
    return $result;
  }


  /* *\
  )   ( -------- Keyword Highlighting ----------
  \* */

  /**
   * This feature allows user to define personal preferences for keywords highlighting in newsfeeds
   * The highlighting is applyed dynamically when article title and content sent to browser
   * Article title/content remains in database unchanged, as it was downloaded from RSS feed
   * Highlighting defined by setting keyword foreground and/or background color,
   * plus text decoration (bold, italic, underscore)
   * User is fully responsibile for selection of readable and meaningful color combinations
   * Highlight definitions could be created, cloned, updated and deleted
   * Unselected values for boolean parameters stored as NULL, for colors - as empty string
  **/

  /**
   * Highlight in article title/description keywords (if any)
   * @param $items: list of article records
   * @return: same list with highlighted keywords inside
  **/
  public function markKeywordsInItems($items) {
    $result = array();
    foreach ($items as $item) {
      $item['title'] = $this->markKeywordsInStr($item['title']);
      $item['description'] = $this->markKeywordsInStr($item['description']);
      $result[] = $item;
    }
    return $result;
  }

  /**
   * Mark keywords in given string
   * @param: $str: string to check
   * @return: string with <span> tags around keywords
   *     <span> will have class="freerss2_kw_NNN"
   **/
  public function markKeywordsInStr($str) {
    # get a list of keywords
    $keywords = $this->getKeywords();
    foreach($keywords as $rec) {
      $keyword = $rec['keyword'];
      $p = stripos($str, $keyword);
      if (stripos($str, $keyword) === false) { continue; }
      # for each keyword - if match:
      # replace all keyword instances with <span class="">keyword</span>
      $class_name = $rec['class_name'];

      $tag_matches = array();
      $index = 0;
      do {
        preg_match('#(<[^<>]+>)#is', $str, $matches);
        if ( ! $matches ) { break; }
        $tag = $matches[0];
        $tag_matches[$index] = $tag;
        $str = str_replace($tag, "([$index])", $str);
        $index ++;
      } while(1);
      $str = str_ireplace($keyword, "<span class=\"$class_name\">$keyword</span>", $str);
      foreach($tag_matches as $index=>$tag) {
        $str = str_replace("([$index])", $tag, $str);
      }
    }
    return $str;
  }

  /**
   * Read list of keywords and their highlight definitions
   * @return: list of records with 'keyword', 'class_name' and 'class_style'
  **/
  public function getKeywords() {
    # if already initialized - return the content
    if ( ! is_null($this->keywords) ) { return $this->keywords; }
    $cond = array('user_id' => $this->user_id);
    $highlights = $this->db->queryTableRecords('tbl_highlight', $cond, 'keyword');
    $this->keywords = array();
    $i = 0;
    foreach ($highlights as $rec) {
      $i++;
      $rec['class_name'] = "freerss2_kw_$i";
      $rec['class_style'] = buildStyleDefinition($rec);
      $this->keywords []= $rec;
    }
    return $this->keywords;
  }

  /**
   * Delete in database all settings for given keyword
   * @param $keyword: keyword to be deleted
  **/
  public function deleteHighlight($keyword) {
    $query = "DELETE FROM `tbl_highlight` WHERE `user_id`=:user_id AND `keyword`=:keyword";
    $bindings = array('user_id' => $this->user_id, 'keyword' => $keyword);
    $this->db->execQuery($query, $bindings);
    return "";
  }

  /**
   * Clone highlight definition by adding '_copy'
   * @param $original_keyword: from which keyword make a copy
   * @return: error message with 'Error' at the beginning or new keyword
  **/
  public function cloneHighlight($original_keyword) {
    // generate new keyword
    $keyword = $original_keyword . "_copy";
    $query1 = "SELECT COUNT(*) FROM `tbl_highlight`
        WHERE `user_id`=:user_id AND BINARY `keyword`=:keyword";
    $bindings = array('user_id' => $this->user_id, 'keyword' => $keyword);
    $exist = $this->db->fetchSingleResult($query1, $bindings);
    if ($exist) { return "Error: keyword '$keyword' already exists"; }
    // clone record
    $query2 = "SELECT `fg_color`, `bg_color`, `bold`, `italic`, `underscore` ".
        "FROM `tbl_highlight` ".
        "WHERE `user_id`=:user_id AND BINARY `keyword`=:keyword";
    $bindings2 = array('user_id' => $this->user_id, 'keyword' => $original_keyword);
    $rec = $this->db->fetchSingleRow($query2, $bindings2);
    $rec['keyword'] = $keyword;
    $query3 ="INSERT INTO `tbl_highlight` ".
      "(`user_id`, `keyword`, `fg_color`, `bg_color`, `bold`, `italic`, `underscore`) ".
      "VALUES (:user_id, :keyword, :fg_color, :bg_color, :bold, :italic, :underscore)";
    $bindings3 = $this->alignHighlightForStorage(array(
        'user_id' => $this->user_id,
        'keyword' => $keyword,
        'fg_color' => $rec['fg_color'],
        'bg_color' => $rec['bg_color'],
        'bold' => $rec['bold'],
        'italic' => $rec['italic'],
        'underscore' => $rec['underscore']
      ));
    $this->db->execQuery($query3, $bindings3);
    // return new keyword
    return urlencode($keyword);
  }

  /**
   * Save new/updated keyword highlight definition
   * @param $original_keyword: (if not empty) modify this keyword definition
   * @param $keyword: new/updated keyword text
   * @param $fg_color: foreground color (or nothing)
   * @param $bg_color: background color (or nothing)
   * @param $bold: bold flag
   * @param $italic: italic flag
   * @param $underscore: underscore flag
   * @return: error message (if any)
  **/
  public function saveHighlight($original_keyword, $keyword, $fg_color, $bg_color, $bold, $italic, $underscore) {
    if (!$keyword) { return "Error: missing keyword"; }
    if (!$fg_color && !$bg_color && !$bold && !$italic && !$underscore) { return "Error: nothing selected"; }
    // when creating or renaming - make sure the destination is not exist
    if($keyword != $original_keyword) {
      $query1 = "SELECT COUNT(*) FROM `tbl_highlight`
        WHERE `user_id`=:user_id AND BINARY `keyword`=:keyword";
      $bindings = array('user_id' => $this->user_id, 'keyword' => $keyword);
      $exist = $this->db->fetchSingleResult($query1, $bindings);
      if ($exist) { return "Error: keyword '$keyword' already exists"; }
    }
    // delete existing record
    if ($original_keyword) {
      $query2 = "DELETE FROM `tbl_highlight`
        WHERE `user_id`=:user_id AND BINARY `keyword`=:keyword";
      $bindings = array('user_id' => $this->user_id, 'keyword' => $original_keyword);
      $this->db->execQuery($query2, $bindings);
    }
    // insert new one
    $query3 ="INSERT INTO `tbl_highlight` ".
      "(`user_id`, `keyword`, `fg_color`, `bg_color`, `bold`, `italic`, `underscore`) ".
      "VALUES (:user_id, :keyword, :fg_color, :bg_color, :bold, :italic, :underscore)";
    $bindings3 = $this->alignHighlightForStorage(array(
        'user_id' => $this->user_id,
        'keyword' => $keyword,
        'fg_color' => $fg_color,
        'bg_color' => $bg_color,
        'bold' => $bold,
        'italic' => $italic,
        'underscore' => $underscore
      ));
    $this->db->execQuery($query3, $bindings3);
    return "";  # Ok
  }

  /**
   * Align "keyword highlight" record before storing in database:
   * when color is "null" convert it to empty string
   * values of bold/italic/underscore convert to either 1 or null
   * @param $rec: dictionary with highlight definition
   * @return: updated dictionary
  **/
  public function alignHighlightForStorage($rec) {
    if ( $rec['fg_color'] == 'null' ) { $rec['fg_color'] = ''; }
    if ( $rec['bg_color'] == 'null' ) { $rec['bg_color'] = ''; }
    $rec['bold']       = $rec['bold']       ? 1 : null;
    $rec['italic']     = $rec['italic']     ? 1 : null;
    $rec['underscore'] = $rec['underscore'] ? 1 : null;
    return $rec;
  }

  /* *\
  )   ( -------- Paging ----------
  \* */

  /**
   * Build paging structure:
   * 1. list of articles on page
   * 2. range of pages
   * 3. page number
   * @param $articles: array of items to be shown
   * @param $page_size: items per page
   * @param $page_num: current page number (when omitted - take 1st)
   * @return: (page_articles, maxpage, displayed_page)
  **/
  public function buildPaging($articles, $page_size, $page_num=null) {
    if (! $page_num ) { $page_num = 1; }
    $count_articles = count($articles);
    if ($count_articles <= $page_size) {
      return array($articles, 1, 1);
    }
    $maxpage = ceil($count_articles/$page_size);
    if ($count_articles < $page_size*$page_num) {
      $page_num = $maxpage;
    }
    // take range from array
    $page_articles = array_slice( $articles, ($page_num-1)*$page_size , $page_size);
    return array($page_articles, $maxpage, $page_num);
  }

  /**
   * Build range of pages around current page
   * if the range does not cover whole set - add "more" element
   * @param $maxpage: last page number
   * @param $page_num: current page
   * @return: pages list +/- 10
              and "select..." when this range does not cover 1 .. maxpage
  **/
  public function getPagesRange($maxpage, $page_num) {
    $low = max(($page_num - RssApp::PAGE_RANGE + 1), 1);
    $high = min(($page_num + RssApp::PAGE_RANGE), $maxpage);
    $result = range($low, $high);
    if ($low > 1 || $high < $maxpage) { array_push($result, "select:..."); }
    return $result;
  }

  /**
   * Check RSS inactivity according to last item (if any)
   * If items are empty or last one is too old - generate warning
   * @param $items: items read-in from DB
   * @param $curr_feed_id: feed_id
   * @return: HTML text to be shown on the top (or empty string)
  **/
  public function warnRssInactivity($items, $curr_feed_id) {
    global $_S;

    # return empty string when running out specific feed context
    if (! $curr_feed_id) { return ''; }
    if ( ! count($items) || _date_to_passed_seconds($items[0]['dateStr']) > $_S['week']*3) {
      $query = "SELECT `xmlUrl` FROM `tbl_subscr` WHERE `user_id` = :user_id AND `fd_feedid` = :feed_id";
      $bindings = array('user_id'=>$this->user_id, 'feed_id'=>$curr_feed_id);
      $feed_url = $this->db->fetchSingleResult($query, $bindings);
      $msg = "There's no <b>fresh articles</b> on this channel.";
      return '
<div class="alert alert-warning d-flex align-items-center" role="alert">
  <div>
    <p><i class="fas fa-exclamation-triangle"></i>&nbsp;
      '.$msg .'</p>
      <a class="btn btn-outline-dark" data-bs-toggle="collapse" href="#collapseExample" role="button" aria-expanded="false" aria-controls="collapseExample">
        Recommended actions...
      </a>
      <div class="collapse" id="collapseExample">
        <div class="card card-body">
          <ul class="list-group list-group-flush">
            <li class="list-group-item"><a href="javascript:updateSettings(\'show_articles\', \'both\');"> <i class="fas fa-mail-bulk"></i> Show read/unread articles</a></li>
            <li class="list-group-item"><a href="javascript:refreshRss();"> <i class="fa fa-sync-alt"></i> Refresh the content</a></li>
            <li class="list-group-item"><a href="'.$feed_url.'" target="_blank"> <i class="fas fa-external-link-alt"></i> Check RSS channel status </a></li>
            <li class="list-group-item"><a href="javascript:deleteFeed(\''.$curr_feed_id.'\')"> <i class="far fa-trash-alt"></i> Delete feed as inactive</a></li>
          </ul>
        </div>
      </div>
  </div>
</div>';
    }
    return '';
  } // warnRssInactivity


  /**
   * Get information about article by ID
   * @return: dictionary with keys
   *   'title', 'link', 'categories', 'author', fd_feedid, decription...
  **/
  public function getItem($item_id) {
    $keys = array('link', 'title', 'author', 'categories', 'timestamp', 'description', 'fd_feedid', 'gr_original_id');
    $query = 'SELECT `'.implode('`, `', $keys).'` ' .
      'FROM `tbl_posts` ' .
      'WHERE `user_id` = :user_id AND `fd_postid` = :fd_postid';
    $bindings = array('user_id'=>$this->user_id, 'fd_postid'=>$item_id);
    return $this->db->fetchSingleRow($query, $bindings);
  }

  /**
   * Generate code for item (article) edit
   * @param $item_id: article ID
   * @return: html code for article tags & watch modification
  **/
  public function itemEditCode($item_id) {
    $result = array();
    // read from DB gr_original_id & categories
    $query = "SELECT `gr_original_id`, `categories` ".
      "FROM `tbl_posts` WHERE ".
      "`user_id`=:user_id AND `fd_postid`=:fd_postid";
    $bindings = array('user_id'=>$this->user_id, 'fd_postid'=>$item_id);
    $item_info = $this->db->fetchSingleRow($query, $bindings);
    $labels = $item_info[1];
    $result []= '<div class="input-group input-group-sm mb-3">';
    $result []= '  <span class="input-group-text">Label(s)</span>';
    $result []= '  <input type="text" class="form-control" value="'.$labels.'" id="new_label"></input>';
    $result []= '</div>';
    $item_watch_id = $item_info[0];
    $watches = $this->getWatchesList();

    $result []= '<div class="input-group mb-3">';
    $result []= '  <span class="input-group-text">Associate with watch</span>';
    $result []= '  <select class="form-select" dest_id="'.$item_id.'" id="new_watch_id">';
    $selected = ($item_watch_id) ? '' : 'selected';
    $result []= '    <option '.$selected.' value="unfiltered"> - unfiltered - </option>';
    foreach ($watches as $watch) {
      $title = $watch['title'];
      if ($title == 'trash') { continue; }
      $watch_id = $watch['fd_watchid'];
      if ( in_array($watch_id, $this->builtin_watches) ) { continue; }
      $selected = ( $watch_id == $item_watch_id ) ? 'selected' : '';
      $result []= '<option '.$selected.' value="'.$watch_id.'">'.$title.'</option>';
    }
    $result []= '  </select>';
    $result []= '</div>';
    return implode("\n", $result);
  }

  /**
   * Show items as grid of accordion elements
   * @param $items: list of item records to be shown on this page
   * @param $action_buttons: action buttons at the bottom of list
  **/
  public function showItems($items, $action_buttons) {
    echo '<div class="accordion accordion-flush" id="rss_items">';
    foreach ($items as $item) {
        $item['description'] = htmlspecialchars_decode($item['description']);
        $fd_postid = $item['fd_postid'];
        $read = $item['read'];
        $read_state = array(
          'read'   => $read ? '' : 'hidden-element',
          'unread' => $read ? 'hidden-element' : ''
        );
        $flagged = $item['flagged'];
        $flagged_state = array(
          'flagged'   => $flagged ? '' : 'hidden-element',
          'unflagged' => $flagged ? 'hidden-element' : ''
        );

        if ( ! $item['title'] ) {
          $item['title'] = '(no title)';
        }
        $item_title = html_entity_decode(preg_replace('/(#\d+;)/', '&${1}', $item['title']));
        $rtl = $item['rtl'] ? 'dir="rtl"' : '';

        $share_links = $this->generateShareLinks($item['link'], $item_title);
        $share_links_code = [];
        foreach ($share_links as $link) {
          $share_links_code []= '<li><a class="dropdown-item" href="'.$link['href'].'" target="_blank">&nbsp;&nbsp;<i class="fas fa-caret-right"></i>&nbsp;'.$link['title'].'</a></li>';
        }
        $share_links_code = implode("\n", $share_links_code);

        # build tooltip text:
        $tooltip = array( '[published: '.$item['dateStr'].']');
        if($item['feed_info']  ) { $tooltip []= '[feed: '.$item['feed_info']['title'].']'; }
        if($item['watch_title'] ?? null) { $tooltip []= '[watch: '.$item['watch_title'].']';}
        if($item['author']     ) { $tooltip []= '[author: '.$item['author'].']';}
        $tooltip = implode('; ', $tooltip);

        $origin = array();
        if($item['watch_title'] ?? null) { $origin []= $item['watch_title']; }
        if($item['feed_info']  ) { $origin []= $item['feed_info']['title']; }
        if($item['author']     ) { $origin []= $item['author']; }
        $origin = implode('&nbsp;*&nbsp;', $origin);

        echo '  <div class="accordion-item">
    <h2 class="accordion-header item-header" id="heading_'.$fd_postid.'">
        <span class="btn-group me-2 item-header-buttons" role="group">
         <span class="btn btn-light btn-sm" style="padding-top: 9px;" onclick="onReadUnreadClick(event, \''.$fd_postid.'\');" >
           <i id="unread_'.$fd_postid.'" class="fas fa-envelope  '.$read_state['unread'].'"></i>
           <i id="read_'.$fd_postid.'" class="far fa-envelope-open '.$read_state['read'].'"></i>
         </span>
         <span class="btn btn-light btn-sm" >
           <i class="fa fa-star '.$flagged_state['flagged'].'" style="color:blue;" id="flagged_'.$fd_postid.'" onclick="changeArticleFlaggedState(\''.$fd_postid.'\', \'off\')"></i>
           <i class="far fa-star '.$flagged_state['unflagged'].'" style="color:gray;" id="unflagged_'.$fd_postid.'" onclick="changeArticleFlaggedState(\''.$fd_postid.'\', \'on\')"></i>&nbsp;
         </span>
        </span>
      <button '.$rtl.' class="accordion-button collapsed item-header-bar" type="button" data-bs-toggle="collapse"
          data-bs-target="#collapse_'.$fd_postid.'" aria-expanded="false" aria-controls="collapse_'.$fd_postid.'"
          title="'.$tooltip.'"
          onclick="onArticleHeadingClick(event, \'heading_'.$fd_postid.'\')">
        &nbsp;
        <span class="hide-on-cellular no-text-overflow item-source">'.$origin.'</span>
        <span class="'.($read? '':'bold-element').' '.($flagged? 'item-header-flagged' : '').' no-text-overflow item-title">'.$item_title.'</span>
        <span class="post-time-info" dir="ltr">'.$item['passedTime'].'</span>
      </button>
    </h2>
    <div id="collapse_'.$fd_postid.'" class="accordion-collapse collapse" aria-labelledby="heading_'.$fd_postid.'" data-bs-parent="#rss_items">
    <div class="accordion-body">
         <div class="btn-group dropdown item-menu-button">
           <button class="btn btn-light btn-sm" onclick="startTitleSearch(\''.$fd_postid.'\')">
              <i class="fas fa-search"></i>
           </button>
           <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="dropdownMenuButton_'.$fd_postid.'" data-bs-toggle="dropdown" aria-expanded="false">
             <i class="fas fa-share-square"></i>
           </button>
           <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton_'.$fd_postid.'">
             <li><a class="dropdown-item" href="javascript:copyArticleToClipboard(\''.$fd_postid.'\')"><i class="far fa-clipboard"></i>&nbsp;Copy to Clipboard</a></li>
             <li><span class="dropdown-item-text"><i class="fas fa-share-alt"></i>&nbsp;Share using:</span></li>'.$share_links_code.'
             <li><a class="dropdown-item" href="javascript:changeArticle(\''.$fd_postid.'\')"><i class="fas fa-tags"></i>&nbsp;Associate with ...</a></li>
           </ul>
         </div>
         <h5 '.$rtl.'>
           <a href="'.$item['link'].'" target="_blank" >
            '.$item_title.'
           </a>
         </h5>

         <span class="badge bg-light text-dark">'.$item['dateStr'].'</span>&nbsp;';
      if($item['feed_info']) {
        $feed_id = $item['fd_feedid'];
        $feed_title = $item['feed_info']['title'];
        echo '<a href="read.php?type=subscr&id='.$feed_id.'" class="badge rounded-pill bg-info text-dark">'.$feed_title.'</a>&nbsp;';
      }
      if($item['watch_title'] ?? null) {
        echo '<a href="read.php?type=watch&id='.$item['gr_original_id'].'" class="badge rounded-pill bg-info text-dark">'.$item['watch_title'].'</a>&nbsp;';
      }
      if($item['author']) {
        echo '<span class="badge bg-info text-dark">'.$item['author'].'</span>&nbsp;';
      }
      echo '<span class="badge bg-secondary">'.$item['categories'].'</span>';
      $slash_pos = strpos($item_title, ' / ');
      if ( $slash_pos ) {
        # if $item_title contains ' / ' - create KinoPoisk/IMDb search button
        $search_link = '/api/articles/search/?plugin=kinopoisk&item_id='.$fd_postid;
        # The result should replace HTML content of button with ID = search_$fd_postid
        echo '&nbsp; &nbsp;<span id="search_'.$fd_postid.'">'.
          '<button type="button" onclick="startMovieRatingSearch(\''.$fd_postid.'\');" title="Search for movie ratings" class="btn btn-dark btn-sm"><i class="fas fa-star-half-alt"></i></button></span>';
      }
      echo '<br>
      '.$item['description'].'
    </div>
    </div>
  </div>';
    }
    if ($items) {
      echo '<H3 style="padding-left: 16px;">' . $action_buttons . '</H3>';
    }
    echo '</div>';
  }

  // Generate sharing menu items (name and link URL)
  // @return: list of records with 'title' and 'href' inside
  public function generateShareLinks($item_link, $item_title) {
    $link_quoted = urlencode($item_link);
    $title_quoted = urlencode($item_title);
    $result = array();
    foreach ($this->SHARE_LINKS as $share) {
      $href = $share[1];
      $href = str_replace('{link}', $link_quoted, $href);
      $href = str_replace('{title}', $title_quoted, $href);
      $result []= array('title'=>$share[0], 'href'=>$href);
    }
    return $result;
  }

  /**
   * Prepare articles for displaying right now
   * by caclulating "passedTime" from original "dateStr"
   * @param $items: array of records, read from DB
   * @return: updated array with caclulated "passedTime"
  **/
  public function prepareForDisplay($items) {
    $result = array();
    if (! $items) { return $result; }
    foreach ($items as $item) {
        $item['passedTime'] = _date_to_passed_time($item['dateStr']);
        array_push($result, $item);
    }

    return $result;
  } // prepareForDisplay

  // change items (articles) state/bookmark by IDs
  // @param $item_ids: list of IDs
  // @param $action: 'read', 'bookmark', 'unread', 'unbookmark',
  //                 'toggleread', 'togglebookmark'
  public function changeItemsState($item_ids, $action) {
    switch ($action) {
      case 'read':
        $this->updateItemsState($item_ids, 'read',     1); break;
      case 'unread':
        $this->updateItemsState($item_ids, 'read',     0); break;
      case 'bookmark':
        $this->updateItemsState($item_ids, 'bookmark', 1); break;
      case 'unbookmark':
        $this->updateItemsState($item_ids, 'bookmark', 0); break;
      case 'toggleread':
        $this->updateItemsState($item_ids, 'read',     2); break;
      case 'togglebookmark':
        $this->updateItemsState($item_ids, 'bookmark', 2); break;
      default:
        return "Error: unsupported action '$action'";
    }
    return "";
  }

  // udpdate items (articles) state by IDs
  // @param $item_ids: list of article IDs
  // @param $change_type: which parameter to change ('read' or 'bookmark')
  // @param $new_value: the value 0/1 to assign for parameter ('2' means 'toggle')
  public function updateItemsState($item_ids, $change_type, $new_value) {
    $bindings = array(
        'user_id'   => $this->user_id
    );

    // Toggle value
    if ($new_value != 0 && $new_value != 1) {

      // for togle 'read' state - avoid flagged modification
      if ($change_type == 'read') {
        $field = 'read';
        $extra_set = '';
        $extra_cond = "AND `flagged`=0";
      } else {
      // for toggle flagged state - always force 'unread'
        $field = 'flagged';
        $extra_set = ', `read`=0 ';
        $extra_cond = '';
      }

      $query = "UPDATE `tbl_posts` ".
          "SET `$field` = IF (`$field`, 0, 1) $extra_set".
          "WHERE `user_id`=:user_id ".
          "AND `fd_postid` IN ('".implode("','", $item_ids)."') $extra_cond";

    } else { // Set value

      if ($change_type == 'read') {
        $query = "UPDATE `tbl_posts` ".
          "SET `read`=:new_read ".
          "WHERE `user_id`=:user_id ".
          "AND `fd_postid` IN ('".implode("','", $item_ids)."') ".
          "AND `flagged`=0";
        $bindings['new_read'] = $new_value;
      } else {
        // do similar for "flagged"
        // mark (when relevant) "flagged" also as "unread"
        $extra_set = '';
        if ( $new_value ) { $extra_set = ', `read`=0 '; }
        $query = "UPDATE `tbl_posts` ".
          "SET `flagged`=:new_flagged $extra_set ".
          "WHERE `user_id`=:user_id ".
          "AND `fd_postid` IN ('".implode("','", $item_ids)."') ";
        $bindings['new_flagged'] = $new_value;
      }

    }

    $this->db->execQuery($query, $bindings);
  }

  // udpdate single item (article) state
  // @param $item_id: item ID
  // @param $change_type: name of article attribute (read/flagged)
  // @param $new_value: attribute new value
  public function updateItemState($item_id, $change_type, $new_value) {
    $query = "UPDATE `tbl_posts` SET `$change_type`=:new_value WHERE `user_id`=:user_id AND `fd_postid`=:fd_postid";
    $bindings = array(
        'new_value'  => $new_value,
        'user_id'   => $this->user_id,
        'fd_postid' => $item_id
    );

    $this->db->execQuery($query, $bindings);
    if ($change_type == 'flagged' && $new_value) {
      $this->updateItemState($item_id, 'read', 0);
    }
  }

  /**
   * Set personal setting value
   * @param $setting_name: field name
   * @param $setting_value: field value to be stored
  **/
  public function setPersonalSetting($setting_name, $setting_value) {
    $this->deletePersonalSetting($setting_name);

    $query = "INSERT INTO `tbl_settings` (`user_id`, `param`, `value`) ".
      "VALUES (:user_id, :param_name, :param_value)";
    $bindings = array(
        'user_id' => $this->user_id,
        'param_name' => $setting_name,
        'param_value' => $setting_value
      );
    $this->db->execQuery($query, $bindings);
  }

  /**
   * Delete personal setting
   * @param $setting_name: field name
  **/
  public function deletePersonalSetting($setting_name) {
    $query = "DELETE FROM `tbl_settings` WHERE `user_id`=:user_id AND `param`=:param_name";

    $bindings = array('user_id' => $this->user_id, 'param_name' => $setting_name);
    $this->db->execQuery($query, $bindings);
  }

  /**
   * Get personal setting
   * @param $setting_name: field name
   * @return: field value
  **/
  public function getPersonalSetting($setting_name) {
    $query = "SELECT `value` FROM `tbl_settings` WHERE `user_id`=:user_id AND `param`=:param_name";
    $bindings = array('user_id' => $this->user_id, 'param_name' => $setting_name);
    return $this->db->fetchSingleResult($query, $bindings);
  }

  /**
   * Get all personal settings for current user
   * @return: dictionary of settings
  **/
  public function getAllPersonalSettings() {
    $result = array();
    $query = "SELECT `param`, `value` FROM `tbl_settings` WHERE `user_id`=:user_id";
    $bindings = array('user_id' => $this->user_id);
    $records = $this->db->fetchQueryRows($query, $bindings);
    foreach ($records as $rec) {
      $result[$rec['param']] = $rec['value'];
    }
    return $result;
  }

  /**
   * Save given link to recover on next application start
   * @param $actual_link: current page full URL
  **/
  public function saveLastLink() {
    if ( ! $this->user_id ) { return; }
    $req_url = (empty($_SERVER['HTTPS']) ? 'http' : 'https') .
      "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    if ( strpos($req_url, '/api/') !== false ) { return; }
    # store this URL in DB
    # TODO: use safer REQUEST_URI without potential injections
    $this->setPersonalSetting('last_page', $req_url);
  }

  /**
   * Generate feeds group editing code
   * @param $group_id: edited group ID
   * @return: HTML code for group change modal dialog
  **/
  public function feedsGroupEdit($group_id) {
    $buffer = array();
    // group feeds list
    $query1 = "SELECT `fd_feedid`, `title`, `htmlUrl` FROM `tbl_subscr`
      WHERE `user_id`=:user_id AND `group`=:group_id ORDER BY index_in_gr";
    $bindings = array('user_id'=>$this->user_id, 'group_id'=>$group_id);
    $feeds = $this->db->fetchQueryRows($query1, $bindings);

    $buffer []= '<ul class="nav nav-pills flex-column">';
    foreach ($feeds as $feed) {
      $buffer []= '
<li class="nav-item btn-group feed-in-group" id="feed_'.$feed['fd_feedid'].'">
<label class="col-6 bold-element no-text-overflow" title="'.$feed['htmlUrl'].'">'.$feed['title'].'</label>
<a class="btn btn-light col-3" onclick="moveFeed(\'feed_'.$feed['fd_feedid'].'\', -1)" style="padding-left: 0.2rem;padding-right: 0.2rem;"><i class="fas fa-chevron-up"></i></a>
<a class="btn btn-light col-3" onclick="moveFeed(\'feed_'.$feed['fd_feedid'].'\', 1)" style="padding-left: 0.2rem;padding-right: 0.2rem;"><i class="fas fa-chevron-down"></i></a>
</li>';
    }
    $buffer []= '</ul>';
    return implode("\n", $buffer);
  }

  /**
   * Save updated info about feeds group
   * @param $group_id: original group_id
   * @param $data: dictionary with 'new_group_id' and
   * array 'feeds' with feed IDs in updated order
   * @return: error message (if any)
  **/
  public function feedsGroupSave($group_id, $data) {
    // if new group id differs from original - make sure it's not already in use
    $new_group_id = trim($data->new_group_id);
    # Make sure the new name complies general rules:
    # * length (minimal/maximal)
    # * valid characters inside
    # * not used as filter (watch) name
    $error = $this->checkNameValidity($new_group_id, 'group');
    if ( $error ) {
      return "Error: " . $error;
    }
    if ($group_id != $new_group_id) {
      $query1 = "SELECT COUNT(*) FROM `tbl_subscr`
        WHERE `user_id`=:user_id AND `group`=:new_group_id";
      $bindings1 = array('user_id'=>$this->user_id, 'new_group_id'=>$new_group_id);
      $count = $this->db->fetchSingleResult($query1, $bindings1);
      if ($count != 0) { return "Error: '$new_group_id' already in use"; }
    }
    # TODO: make sure there is no filter (watch) with such name
    $query2 = "UPDATE `tbl_subscr` SET `group`=:new_group_id, `index_in_gr`=:i
      WHERE `user_id`=:user_id AND `group`=:group_id AND `fd_feedid`=:feed_id";
    $bindings2 = array('user_id'=>$this->user_id, 'new_group_id'=>$new_group_id, 'group_id'=>$group_id);
    $index = 0;
    foreach ($data->feeds as $feed_id) {
      $index += 1;
      $bindings2['feed_id'] = $feed_id;
      $bindings2['i'] = $index;
      $this->db->execQuery($query2, $bindings2);
    }
    return "";
  }

  /**
   * Check name of group/view for length/content rules
   * @param $name: name to check
   * @param $type: object type (group, subscr, watch)
   * @return: Error text (if any)
  **/
  public function checkNameValidity($name, $type) {
    $name = strtolower($name);
    if ( strlen($name) > 40 ) { return "name is too long"; }
    if ( strlen($name) < 3 ) { return "name is too short"; }
    $pattern = '/^[\p{L}\p{N}\-_\s]+$/u';
    if (! preg_match($pattern, $name)) {
      return "name contains incorrect characters";
    }
    # check against reserved names
    if ( $this->isReservedWatch($name) ) {
      return "name reserved for built-in watches";
    }
  }

  /**
   * Count status, unread, flagged and last update for given watch/group/feed
   * Status (enabled/disabled) is relevant for feeds only
   * @param $type: slice type (watch/group/subscr)
   * @param $id: slice ID
   * @return: array ($enabled, $unread, $flagged)
  **/
  public function getStatistics($type, $id) {
    $query1 = "SELECT `download_enabled` FROM `tbl_subscr` WHERE `user_id`=:user_id";
    $query2 = "SELECT COUNT(1) FROM `tbl_posts` WHERE `user_id`=:user_id AND `read` = 0";
    $query3 = "SELECT COUNT(1) FROM `tbl_posts` WHERE `user_id`=:user_id AND `flagged` != 0";
    $query4 = "SELECT MAX(`timestamp`) FROM `tbl_posts` WHERE `user_id`=:user_id";
    $bindings = array('user_id'=>$this->user_id);
    if ($type == 'group') {
      if ($type == 'group' && $id == 'all') {
        $cond = '';
      } else {
        $cond = '`fd_feedid` IN (SELECT `fd_feedid` FROM `tbl_subscr` WHERE `user_id`=:user_id AND `group`=:id)';
      }
    } elseif ($type == 'watch') {
      $cond = '`gr_original_id`=:id';
    } elseif ($type == 'subscr') {
      $cond = '`fd_feedid`=:id';
    }
    if ($cond) {
      $query1 .= ' AND '.$cond;
      $query2 .= ' AND '.$cond;
      $query3 .= ' AND '.$cond;
      $query4 .= ' AND '.$cond;
      $bindings['id'] = $id;
    }
    if ( $type == 'subscr' ) {
      $enabled   = $this->db->fetchSingleResult($query1, $bindings);
    } else {
      $enabled  = 1;
    }
    $unread   = $this->db->fetchSingleResult($query2, $bindings);
    $flagged  = $this->db->fetchSingleResult($query3, $bindings);
    $last_upd = $this->db->fetchSingleResult($query4, $bindings);
    return array($enabled, $unread, $flagged, $last_upd);
  }

  /**
   * Get subscriptions summary statistics
   * @return: dictionary with fields
   *         - user_name
   *         - total_subscriptions
   *         - active_subscriptions
   *         - unread_articles
   *         - bookmarked_articles
   *         - updated_at
   *         - update_required
   *         - enable_push_reminders
   *         - last_page (or null)
  **/
  public function getSubscrSummary() {
    global $_S;
    $personal_settings = $this->getAllPersonalSettings();
    $reminder_hours = $personal_settings['reminder_hours'] ? max($personal_settings['reminder_hours'], 1) : 1;

    $statistics = array();

    $query0 = "SELECT `full_name` FROM `tbl_users` WHERE `user_id`=:user_id";
    $query1 = "SELECT COUNT(1) FROM `tbl_subscr` WHERE `user_id`=:user_id";
    $query2 = "SELECT COUNT(1) FROM `tbl_subscr` WHERE `user_id`=:user_id AND `download_enabled` != 0";
    $query3 = "SELECT COUNT(1) FROM `tbl_posts` WHERE `user_id`=:user_id AND `read` = 0";
    $query4 = "SELECT COUNT(1) FROM `tbl_posts` WHERE `user_id`=:user_id AND `flagged` != 0";
    $query5 = "SELECT MAX(`timestamp`) AS `latest` FROM `tbl_posts` WHERE `user_id`=:user_id";
    $query6 = "SELECT `value` FROM `tbl_settings` WHERE `user_id`=:user_id AND `param` = 'last_page'";
    $bindings = array( 'user_id'   => $this->user_id );

    $statistics['user_name']            =
      $this->db->fetchSingleResult($query0, $bindings);
    $statistics['total_subscriptions' ] =
      $this->db->fetchSingleResult($query1, $bindings);
    $statistics['active_subscriptions'] =
      $this->db->fetchSingleResult($query2, $bindings);
    $statistics['unread_articles'     ] =
      $this->db->fetchSingleResult($query3, $bindings);
    $statistics['bookmarked_articles' ] =
      $this->db->fetchSingleResult($query4, $bindings);
    $last_update = $this->db->fetchSingleResult($query5, $bindings);
    $statistics['updated_at' ] = _date_to_passed_time($last_update);
    $delta = _date_to_passed_seconds($last_update);
    $statistics['update_required' ] = $delta > ($_S['hour'] * $reminder_hours);
    $statistics['enable_push_reminders'] = $personal_settings['enable_push_reminders'] == 'true' ? 1 : 0;
    $statistics['enable_popup_reminders'] = $personal_settings['enable_popup_reminders'] == 'true' ? 1 : 0;
    $statistics['last_page'           ] =
      $this->db->fetchSingleResult($query6, $bindings);
    return $statistics;
  }

  /**
   * Parse and load OPML
   * @param $opml_source: text (XML) with subscriptions info
   * @return: error (if any) and statistics (how many groups and feeds loaded)
  **/
  public function loadOpml($opml_source) {
    $error = '';
    $groups_count = 0;
    $feeds_count = 0;
    // remove bom
    $bom = pack('H*','EFBBBF');
    $opml_source = preg_replace("/^$bom/", '', $opml_source);
    libxml_use_internal_errors(true);
    $opml = simplexml_load_string($opml_source);
    $errors = array();
    if (false === $opml) {
      foreach (libxml_get_errors() as $e) {
        $errors []= $e->message;
      }
      $error = implode("<BR>\n", $errors);
      return array($error, $groups_count, $feeds_count);
    }
    # remove all articles and subscriptions for this user
    $this->cleanSubscr();
    # get $opml['body']
    # for each 'outline' - get group name
    #    for each sub-element 'outline' create feed under group
    $body = $opml->body;
    foreach ($body->outline as $group_outline) {
      $group_name = $group_outline["text"];
      $feeds = $group_outline->outline;
      foreach ($feeds as $feed) {
        $text = urldecode($feed['text']);
        $title = urldecode($feed['title']);
        $htmlUrl = urldecode($feed['htmlUrl']);
        $xmlUrl = urldecode($feed['xmlUrl']);
        $rtl = urldecode($feed['rtl'] ? 1 : 0);
        $result = $this->insertNewFeed($group_name, $text, $title, $htmlUrl, $xmlUrl, $rtl);
        if ($result) {
          $errors []= $result;
        }
        $feeds_count++;
      }
      $error = implode('; ', $errors);
      $groups_count++;
    }
    return array($error, $groups_count, $feeds_count);
  }

  /**
   * Clean all subscriptions and articles for this user
   * Do not touch filters (watches)
  **/
  public function cleanSubscr() {
    $bindings = array(
      'user_id'   => $this->user_id,
    );
    $query1 = "DELETE FROM `tbl_subscr` WHERE `user_id`=:user_id";
    $query2 = "DELETE FROM `tbl_posts` WHERE `user_id`=:user_id";
    // delete last page as irrelevant
    $query3 = "DELETE FROM `tbl_settings` WHERE `user_id`=:user_id AND `param` = 'last_page'";
    $this->db->execQuery($query1, $bindings);
    $this->db->execQuery($query2, $bindings);
    $this->db->execQuery($query3, $bindings);
  }

  /**
   * Insert new feed under specified group
   * (for import only)
   * @param $group: feed group
   * @param $text: feed text
   * @param $title: feed title
   * @param $html_url: feed HTML URL
   * @param $xml_url: feed XML URL
   * @return: error (if any)
  **/
  public function insertNewFeed($group, $text, $title, $html_url, $xml_url, $rtl) {
    $feed_id = _digest_hex($xml_url);
    $bindings = array(
      'user_id'   => $this->user_id,
      'group'     => $group,
      'xml_url'   => $xml_url,
      'html_url'  => $html_url,
      'title'     => $title,
      'text'      => $text,
      'rtl'       => $rtl,
      'feed_id'   => $feed_id
    );
    $query = "INSERT INTO `tbl_subscr` ".
      "(`user_id`, `group`, `fd_feedid`, `text`, `title`, `xmlUrl`, `htmlUrl`, `index_in_gr`, `download_enabled`, `rtl`) ".
      "VALUES ".
      "(:user_id,  :group,  :feed_id,    :text,  :title,  :xml_url, :html_url, 0, 1, :rtl)";
    $this->db->execQuery($query, $bindings);
    return "";
  }

  /**
   * Create new RSS feed
   * @param $xml_url: feed XML URL
   * @param $title: feed title (when empty - take from feed)
   * @param $group: feed group
   * @param $source_type: either 'site-to-feed' or 'rss'
   * @return: error (if any), feed ID, title
  **/
  public function createFeed($xml_url, $title, $group, $source_type) {
    // if inputs are wrong or duplicated - return error
    if (!$xml_url) {
      return array("Error: empty input", null, '');
    }
    $feed_id = _digest_hex($xml_url);

    $where = array('user_id'=>$this->user_id, 'fd_feedid'=>$feed_id);
    if ($source_type == 'rss') {
      # Clean any site-to-feed data for this feed_id
      $this->db->deleteTableRecords('tbl_site_to_feed', $where);
    } else {
      # Make sure associated site-to-feed setting already saved
      $exist = $this->db->queryTableRecords('tbl_site_to_feed', $where);
      if (!$exist || !$exist[0]) {
        return array("Error: missing site-to-feed settings for this site", null, '');
      }
    }

    // Check title for validity
    $error = $this->checkNameValidity($title, 'subscr');
    if ( $error ) {
      return array("Error: " . $error, null, '');
    }
    // Check group for validity
    $error = $this->checkNameValidity($group, 'group');
    if ( $error ) {
      return array("Error: " . $error, null, '');
    }
    $query1 = "SELECT COUNT(1) FROM `tbl_subscr` WHERE ".
      "`user_id`=:user_id AND ".
      " (`xmlUrl`=:xml_url OR `title`=:title OR `fd_feedid`=:feed_id)";
    $bindings = array(
      'user_id'   => $this->user_id,
      'xml_url'   => $xml_url,
      'title'     => $title,
      'feed_id'   => $feed_id
    );
    $exist = $this->db->fetchSingleResult($query1, $bindings);
    if ($exist) {
      return array("Such RSS already subscribed", null, '');
    }

    $text = $title;
    list($error, $items, $new_title, $new_link) = $this->readRssUpdate($xml_url, $title);
    if ($new_title) { $title = $new_title; }
    if ($error) {
      return array($error, null, $title);
    }

    $bindings['title']   = $title;
    $bindings['text']    = $text;
    $bindings['htmlUrl'] = $new_link;
    $bindings['group']   = $group;
    $query2 = "INSERT INTO `tbl_subscr` ".
      "(`user_id`, `fd_feedid`, `text`, `title`, `xmlUrl`, `htmlUrl`, `group`, `index_in_gr`, `download_enabled`) ".
      "VALUES ".
      "(:user_id,  :feed_id,    :text,  :title,  :xml_url, :htmlUrl,  :group,   0,             1)";
    $this->db->execQuery($query2, $bindings);

    $error = '';
    return array($error, $feed_id, $title);
  }

  /**
   * Update feed parameters in DB
   * @param $feeed_id: feed ID
   * @param $set_enable: 1/0 for enable/disable feed downloading
   * @param $set_xml_url: set feed XML URL
   * @param $set_title: set feed title
   * @param $delete: when non-empty - delete articles and feed itself
   * @return: error message (if any)
  **/
  public function updateFeed($feed_id, $set_enable=null, $set_xml_url=null, $set_title=null, $set_group=null, $delete=null, $rtl=null) {
    $bindings = array(
        'user_id'   => $this->user_id,
        'feed_id'   => $feed_id
    );
    if ($set_enable === '1' || $set_enable === '0') {
      $query = "UPDATE `tbl_subscr` SET `download_enabled`=:set_enable ".
          "WHERE `user_id`=:user_id AND `fd_feedid`=:feed_id";
      $bindings['set_enable'] = intval($set_enable);
      $this->db->execQuery($query, $bindings);
    }
    if ($rtl === '1' || $rtl === '0') {
      $query = "UPDATE `tbl_subscr` SET `rtl`=:rtl ".
          "WHERE `user_id`=:user_id AND `fd_feedid`=:feed_id";
      $bindings['rtl'] = intval($rtl);
      $this->db->execQuery($query, $bindings);
    }
    if ($set_xml_url) {
      $query = "UPDATE `tbl_subscr` SET `xmlUrl`=:set_xml_url ".
          "WHERE `user_id`=:user_id AND `fd_feedid`=:feed_id";
      $bindings['set_xml_url'] = $set_xml_url;
      $this->db->execQuery($query, $bindings);
    }
    if ($set_title) {
      # 1. TODO: make sure this name is unique (not used in other contexts)
      # 2. check for validity (length, content)
      $error = $this->checkNameValidity($set_title, 'subscr');
      if ( $error ) { return "Error: " . $error; }
      $query = "UPDATE `tbl_subscr` SET `title`=:set_title ".
          "WHERE `user_id`=:user_id AND `fd_feedid`=:feed_id";
      $bindings['set_title'] = $set_title;
      $this->db->execQuery($query, $bindings);
    }
    if ($set_group) {
      $error = $this->checkNameValidity($set_group, 'group');
      if ( $error ) { return "Error: " . $error; }
      $query = "UPDATE `tbl_subscr` SET `group`=:set_group ".
          "WHERE `user_id`=:user_id AND `fd_feedid`=:feed_id";
      $bindings['set_group'] = $set_group;
      $this->db->execQuery($query, $bindings);
    }
    if ($delete) {
      // delete articles
      $query1 = "DELETE FROM `tbl_posts` WHERE `user_id`=:user_id AND `fd_feedid`=:feed_id";
      // delete statistics?
      // delete feed
      $query2 = "DELETE FROM `tbl_subscr` WHERE `user_id`=:user_id AND `fd_feedid`=:feed_id";
      // delete last page as irrelevant
      $query3 = "DELETE FROM `tbl_settings` WHERE `user_id`=:user_id AND `param` = 'last_page'";

      $this->db->execQuery($query1, $bindings);
      $this->db->execQuery($query2, $bindings);
      $bindings = array('user_id' => $this->user_id);
      $this->db->execQuery($query3, $bindings);
    }
  }

} // RssApp

/**
 * Build style definition from record with fields:
 *   fg_color, bg_color, bold, underscore, italic
 * @return: string that could be used as CSS style definition
**/
function buildStyleDefinition($rec) {
  $result = array();
  if ( $rec['fg_color'] )  { $result []= "color: ".$rec['fg_color']; }
  if ( $rec['bg_color'] )  { $result []= "background-color: ".$rec['bg_color']; }
  if ( $rec['bold']     )  { $result []= "font-weight: bold"; }
  if ( $rec['italic']   )  { $result []= "font-style: italic"; }
  if ( $rec['underscore']) { $result []= "text-decoration: underline"; }
  return implode('; ', $result);
}
?>
