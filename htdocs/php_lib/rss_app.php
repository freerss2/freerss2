<?php

include "db_conf.php";
include "db_app.php";
include "php_util.php";
include "opml.php";
require_once "Spyc.php";


$APP_VERSION = '2.0.1.6.5j';

$VER_SUFFIX = "?v=$APP_VERSION";

# /*                                      *\
#   Application main functionality
# \*                                      */

define('PASSWORD_CHARSET',
  '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_.,-+!:@');

class RssApp {
  private $db;
  private $NOW;
  private $user_id;
  private $builtin_watches = array('all', 'today', 'older', 'bookmarked', 'unfiltered');
  private $reserved_watches;


  /**
   * Constructor
  **/
  public function __construct() {
    global $db_conf; # defined in db_conf.php

    $this->NOW = $db_conf['time_skew'] ?
      "ADDTIME(NOW(),'".$db_conf['time_skew']."')" : 'NOW()';
    $this->db = new DbApp($db_conf);
    $this->user_id = null;
    $this->reserved_watches = $this->builtin_watches;
    $this->reserved_watches[] = 'search';
  }

  public function dumpDb($filename) {
    return $this->db->dumpDb($filename);
  }

  /**
   * Is this watch in reserved list?
  **/
  public function isReservedWatch($watch_id) {
    return in_array($watch_id, $this->reserved_watches);
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
    $password = random_str(10, PASSWORD_CHARSET);
    // calculate MD5 checksum for password
    $checksum = md5($password);
    $query3 = "INSERT INTO `tbl_users` ".
      "(`user_id`, `full_name`, `login_name`, `email`, `expiration_timestamp`, `password`) ".
      "VALUES ( (SELECT MAX(u.`user_id`)+1 FROM `tbl_users` as u), ".
      "            :name,       :email,       :email, $this->NOW + INTERVAL 24 HOUR, :checksum )";
    $bindings['name'] = $name;
    $bindings['checksum'] = $checksum;
    $this->db->execQuery($query3, $bindings);
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
   * Read collected statistics from DB - TODO
   * @param $id: view ID
   * @param $type: View type
  **/
  public function get_statistics_from_db($id, $type) {
    if($id == 'search')  {
      # special case - no statistics in static table - query from actual articles
      #my ($total,$unread,$new_t) = _get_rss_statistics(%args);
      #return ($total,$unread,$new_t,'none','');
    }
    $rec = $this->db->queryTableRecords('tbl_subscr_state', array('id'=>$id, 'type'=>$type, 'user_id'=>$this->user_id));

    return array(
     $rec[0]->total  || 0,
     $rec[0]->unread || 0,
     $rec[0]->timestamp || '1970-01-01 00:00:01',
     $rec[0]->upd_status || 'none',
     $rec[0]->upd_log || ''
     );
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
        # echo $query2."<BR>\n";
        $this->db->execQuery($query2, $bindings);
    }
    $this->setPersonalSetting('last_maintenance', time());
    return "Performed maintenance for $count feeds";
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
  public function readRssUpdate($rss_url, $rss_title) {

    try {
      // echo "reading '$rss_title': $rss_url<BR>\n";

      // Disable any errors reporting
      error_reporting(0);
      $rss_buffer = file_get_contents($rss_url);
      error_reporting(E_ERROR | E_WARNING | E_PARSE);
      // Enable errors and warnings

      if (! $rss_buffer) {
          return array("Nothing read from $rss_url", null, $rss_title);
      }
      if ( strpos($rss_buffer, '<?xml') === false &&
           strpos($rss_buffer, '<rss') === false) {
          return array($rss_buffer, null, $rss_title);
      }
      # echo "read-in ".strlen($rss_buffer)." bytes<BR>\n";

      // Disable any errors reporting
      error_reporting(0);
      $rss=simplexml_load_string($rss_buffer);
      // Enable errors and warnings
      error_reporting(E_ERROR | E_WARNING | E_PARSE);

      if (! $rss) {
        $rss_buffer = substr($rss_buffer, 0, 16);
        return array("Failed parsing of content from $rss_url<BR>///$rss_buffer///\n", null, $rss_title);
      }
      $items = array();
      $rss_title = $rss->channel->title;
      $rss_link = $rss->channel->link;
      $channel_items = $rss->channel ? $rss->channel->item : $rss->entry;
      foreach ($channel_items as $item) {
        $link = is_array($item->link)? $item->link[0] : $item->link;
        if ($link->attributes()) {
          $link = $link->attributes()['href'];
        }
        $link = is_array($link) ? $link['href'] : $link;
        $fd_postid = $link ? $link : $item->id;
        $pubDate = $item->pubDate ? $item->pubDate : $item->updated;
        $pubDate = str_replace(' (Coordinated Universal Time)', '', $pubDate);
        $content = $item->description ? $item->description : $item->summary;
        if ( ! $content ) { $content = $item->content; }
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
      $obj_cond = ' AND s.`type`=:type ';
    }
    $query = "SELECT s.`type`, s.`id`, f.`title`, s.`timestamp`, ".
      "s.`upd_status`, s.`upd_log` ".
      "FROM `tbl_subscr_state`AS s, `tbl_subscr` AS f ".
      "WHERE s.`user_id`=:user_id AND s.`id` = f.`fd_feedid` $obj_cond ".
      "ORDER BY s.`timestamp` DESC";
    return $this->db->fetchQueryRows($query, $bindings);
  }

  /**
   * Get next RSS after previously updated
   * @param $last_rss_id: last read RSS ID (null for initial call)
   * @return: next RSS record (rss_id, rss_title, rss_url)
   *          or null (on end of sequence)
  **/
  public function getNextRss($last_rss_id) {
      # get a list of all RSS records
      # if $last_rss_id is null - return first record
      # try to find $last_rss_id in records and return next one
      # (or null if reached last record)
      $where = array("user_id" => $this->user_id, "download_enabled" => "1");
      $rss_records = $this->db->queryTableRecords('tbl_subscr', $where);
      if ( ! $rss_records ) { return null; }
      if ( ! $last_rss_id ) { return $rss_records[0]; }
      // echo ("search for $last_rss_id ...<BR>\n");
      $found = null;
      foreach ($rss_records as $rec) {
          if ($found) { return $rec; }
          $found = ($rec['fd_feedid'] == $last_rss_id);
          //echo("found=$found for ".$rec['xmlUrl']."<BR>\n");
      }
      return null;
  } // getNextRss

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
    foreach ($watches as $watch) {
       $rl_action = ($watch['fd_watchid'] == 'trash') ? 'mark_read' : 'set_tag';
       $rl_act_arg = $watch['fd_watchid'];
       if ($watch['fd_watchid'] !== 'trash') {
         $bindings1 = array(
           'user_id'=>$this->user_id, 'watch_id'=>$watch['fd_watchid'], 'name'=>$watch['title']);
         $query1 = "INSERT INTO `tbl_watches` ".
           "(`user_id`, `fd_watchid`, `title`) VALUES ".
           "(:user_id, :watch_id, :name)";
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
    return $err;
  }

  /**
   * Generate OPML (XML) representation of groups and their feeds
   * @return: text buffer with XML
  **/
  public function exportOpml() {
    # get list of subscriptions
    $bindings = array("user_id" => $this->user_id);
    $query = "SELECT `group`, `text`, `title`, `htmlUrl`, `xmlUrl` ".
      "FROM `tbl_subscr` WHERE `user_id`=:user_id ".
      "ORDER BY `group`, `index_in_gr`";
    $subscr = $this->db->fetchQueryRows($query, $bindings);
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
       array('user_id'=>$this->user_id));
    $plist[] = array('title'=>'trash', 'fd_watchid'=>'trash');
    foreach ($plist as $watch) {
      # echo json_encode($watch)."<BR>\n";
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
        $result[] = "<span class=\"rule_logical_delimiter\"> OR </span>";
      }
      $result = array_merge($result, $this->editRuleOrNode('', '==', ''));
      $result[] = "</span>";
      $result[] = "<p class=\"rule_logical_delimiter\">AND</p>";
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
    $result[] = "<div class=\"input-group rule-or-node\">";
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
   * create watch
   * @param $name: new display name for watch
   * @return: error message (if any) or new watch ID
  **/
  public function createWatch($name) {
    if (strtolower($name) == 'all') { return "Error: reserved name"; }
    if (strtolower($name) == 'trash') { return "Error: reserved name"; }
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
    $bindings2 = array('name'=>$name, 'user_id'=>$this->user_id, 'watch_id' => $watch_id);
    $query2 = "INSERT INTO `tbl_watches` ".
      "(`user_id`, `fd_watchid`, `title`) VALUES ".
      "(:user_id,  :watch_id,    :name)";
    $this->db->execQuery($query2, $bindings2);
    return $watch_id;
  }

  /**
   * delete watch
   * @param $watch_id: watch ID
  **/
  public function deleteWatch($watch_id) {
    $bindings = array('user_id'=>$this->user_id, 'watch_id' => $watch_id);
    $query1 = "DELETE FROM `tbl_rules_text` WHERE `user_id`=:user_id AND ".
      "`rl_id` IN (SELECT r.`rl_id` FROM `tbl_rules` AS r WHERE r.`user_id`=:user_id AND r.`rl_act_arg`=:watch_id)";
    $this->db->execQuery($query1, $bindings);
    $query2 = "DELETE FROM `tbl_rules` WHERE `user_id`=:user_id AND `rl_act_arg`=:watch_id";
    $this->db->execQuery($query2, $bindings);
    $query3 = "DELETE FROM `tbl_watches` WHERE `user_id`=:user_id AND `fd_watchid`=:watch_id";
    $this->db->execQuery($query3, $bindings);
    return "";
  }

  /**
   * save watch name
   * @param $watch_id: watch ID
   * @param $name: new display name for watch
   * @return: error message (if any)
  **/
  public function saveWatchName($watch_id, $name) {
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
    $result[] = "<div class=\"card-body\">";
    $result[] = "<div class=\"input-group mb-3\">";
    $result[] = "  <span class=\"input-group-text\">Rule</span>";
    $result[] = "  <input type=\"text\" readonly class=\"form-control rule-title-ro\" id=\"$rule_id\" style=\"min-width:8rem;\" value=\"$rule_title\" placeholder=\"Unique rule name\">";
    $result[] = "    <button class=\"btn btn-outline-secondary\" type=\"button\" onclick=\"openRuleEdit('$watch_id', '$rule_id');\"><i class=\"far fa-edit\"></i></button>";
    $result[] = "    <button class=\"btn btn-outline-secondary\" type=\"button\" onclick=\"deleteRule('$watch_id', '$rule_id');\"><i class=\"far fa-trash-alt\"></i></button>";
    # "move" selector
    $result[] = "    <button class=\"btn btn-outline-secondary dropdown-toggle\" type=\"button\" data-bs-toggle=\"dropdown\" aria-haspopup=\"true\" aria-expanded=\"false\">Move to watch...</button>";
    $result[] = "    <ul class=\"dropdown-menu\">";
    foreach ($watches as $w) {
      if ($w['fd_watchid'] === $watch_id) { continue; }
      $result[] = "      <li><a class=\"dropdown-item\" href=\"#".$w['fd_watchid']."\">".$w['title']."</a></li>";
    }
    $result[] = "    </ul>";
    $result[] = "</div>";
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
    $result[] = "<span class=\"rule_where_cond badge rounded-pill bg-secondary\">$group_limitation</span>";
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
      $count += 1;
      if ($watch['queries']) {
        $set = ($watch['title'] == 'trash') ?
          array('read' => 1) :
          array('gr_original_id' => $watch['fd_watchid']);
        $extra_cond = ($watch['title'] == 'trash') ?
          '' :
          "(`gr_original_id` = '') AND ";
        foreach ($watch['queries'] as $where) {
          if ($where) {
            $where = "$extra_cond $where AND `user_id`=:user_id";
            $set['user_id'] = $this->user_id;
            # echo "$set -- $where<BR>\n";
            $this->db->updateRecordsByFields('tbl_posts', $set, $where);
            // echo "updateRecordsByFields('tbl_posts', 'set'=>".json_encode($set).", 'where'=>$extra_cond $where)<BR>\n";
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
      $last_timestamp = $last_timestamp[0];
    }

    $inserted_count = 0;
    $time_now = time();
    if (! $items) { return $inserted_count; }
    foreach ($items as $rec) {
      if ($rec['timestamp'] > $time_now) {
        $rec['timestamp'] = $time_now;
      }
      if ($last_timestamp && $rec['timestamp'] < $last_timestamp) {
        // echo "skip ".$rec['timestamp']." is older than $last_timestamp<BR>\n";
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
    $show_articles = $personal_settings['show_articles'] ? $personal_settings['show_articles'] : 'unread';
    $order_articles = $personal_settings['order_articles'] ? $personal_settings['order_articles'] : 'time';
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
    $query = "SELECT ".
      "`link`, `title`, `author`, `categories`, ".
      "DATE_FORMAT(`timestamp`, '%e %b %Y') AS 'dateStr', ".
      "CONVERT(`description` USING utf8) as description, ".
      "`fd_postid`, `fd_feedid`, `guid`, `read`, `flagged`, ".
      "`gr_original_id`, `fd_feedid` ".
      "FROM `tbl_posts` ".
      "WHERE `user_id` = :user_id AND ".
      "(`title` LIKE '%$pattern%' OR `description` LIKE '%$pattern%')";
    $query .= " ORDER BY ".(('time' === $order_articles) ? "`timestamp` DESC" : "`title`");
    $bindings = array('user_id' => $this->user_id);
    $items = $this->db->fetchQueryRows($query, $bindings);
    $result = $this->addWatchesInfo($items);
    $result = $this->addFeedInfo($result);
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
    $query = "SELECT ".
      "`link`, `title`, `author`, `categories`, ".
      "DATE_FORMAT(`timestamp`, '%e %b %Y') AS 'dateStr', ".
      "CONVERT(`description` USING utf8) as description, ".
      "`fd_postid`, `guid`, `read`, `flagged`, `gr_original_id`, `fd_feedid` ".
      "FROM `tbl_posts` ".
      "WHERE `user_id` = :user_id AND `fd_feedid` IN ".
      "(SELECT `fd_feedid` FROM `tbl_subscr` WHERE ".
      "`user_id` = :user_id AND `group` = :group)";
    if (array_key_exists('read', $bindings)) {
      $query .= " AND `read` = :read";
    }
    $query .= " ORDER BY ".(('time' === $order_articles) ? "`timestamp` DESC" : "`title`");
    $items = $this->db->fetchQueryRows($query, $bindings);
    $result = $this->addWatchesInfo($items);
    $result = $this->addFeedInfo($result);
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
    $query1 = "SELECT * FROM `tbl_subscr` ".
      "WHERE `fd_feedid` = :fd_feedid AND `user_id` = :user_id";
    $rss_info = $this->db->fetchSingleRow($query1,
      array('fd_feedid' => $feed_id, 'user_id' => $this->user_id));

    $bindings = array(
      'user_id'    => $this->user_id,
      'fd_feedid'  => $feed_id
    );
    if ('read'   === $show_articles) { $bindings['read'] = 1; }
    if ('unread' === $show_articles) { $bindings['read'] = 0; }
    $query2 = "SELECT ".
      "`link`, `title`, `author`, `categories`, ".
      "DATE_FORMAT(`timestamp`, '%e %b %Y') AS 'dateStr', ".
      "CONVERT(`description` USING utf8) as description, ".
      "`fd_postid`, `guid`, `read`, `flagged`, `gr_original_id` ".
      "FROM `tbl_posts` ".
      "WHERE `user_id` = :user_id AND `fd_feedid` = :fd_feedid";
    if (array_key_exists('read', $bindings)) {
      $query2 .= " AND `read` = :read";
    }
    $query2 .= " ORDER BY ".(('time' === $order_articles) ? "`timestamp` DESC" : "`title`");
    $items = $this->db->fetchQueryRows($query2, $bindings);
    # read info about user-defined watches and add 'watch_title'
    $result = $this->addWatchesInfo($items);
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
   * Retrieve watch items from DB
   * @param $watch_id: watch ID (builtin or user-defined filter tag)
   * @return: watch title and list of watch items according to "show" filter
  **/
  public function retrieveWatchItems($watch_id) {
    list($show_articles, $order_articles) = $this->settingsForRetrieve();
    $watch_title = ucfirst($watch_id);
    $where = array('`user_id` = :user_id');
    $bindings = array('user_id' => $this->user_id);
    # check built-in watches
    if ($watch_id == 'all' || $watch_id == 'trash') {
      # do nothing - take all
    } elseif ($watch_id == 'today') {
      $where[] = "`timestamp` >= $this->NOW - INTERVAL 1 DAY";
    } elseif ($watch_id == 'older') {
      $where[] = "`timestamp` < $this->NOW - INTERVAL 1 DAY";
    } elseif ($watch_id == 'bookmarked') {
      $where[] = "`flagged` != 0";
    } elseif ($watch_id == 'unfiltered') {
      $where[] = "`gr_original_id` = ''";
    } elseif (strpos($watch_id, 'tag_' ) === 0) {
      $where[] = "`gr_original_id` = '$watch_id'";
      # get user-defined watch name
      $w_query = 'SELECT `title` FROM `tbl_watches` WHERE '.
        '`user_id`=:user_id AND `fd_watchid`=:fd_watchid';
      $w_bindings = array('user_id'=>$this->user_id, 'fd_watchid'=>$watch_id);
      $watch_title = $this->db->fetchSingleResult($w_query, $w_bindings);
    }
    if ('read'   === $show_articles) { $bindings['read'] = 1; }
    if ('unread' === $show_articles) { $bindings['read'] = 0; }
    if (array_key_exists('read', $bindings)) {
      $where[] = "`read` = :read";
    }
    $query = "SELECT ".
      "`link`, `title`, `author`, `categories`, ".
      "DATE_FORMAT(`timestamp`, '%e %b %Y') AS 'dateStr', ".
      "CONVERT(`description` USING utf8) as description, ".
      "`fd_postid`, `guid`, `read`, `flagged`, `fd_feedid` ".
      "FROM `tbl_posts` ".
      "WHERE ". implode(' AND ', $where);
    $query .= " ORDER BY ".(('time' === $order_articles) ? "`timestamp` DESC" : "`title`");
    $items = $this->db->fetchQueryRows($query, $bindings);
    # add 'feed_info' to items - `xmlUrl` `title` `fd_feedid`
    $result = $this->addFeedInfo($items);

    return array($watch_title, $result);
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
    // TODO: use page_size from personal settings
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
  function getPagesRange($maxpage, $page_num) {
    // TODO: define +/- 10 as constant
    $low = max(($page_num - 9), 1);
    $high = min(($page_num + 10), $maxpage);
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
    # TODO: separate messages for empty list and too old last item date
    if ( ! count($items) || _date_to_passed_seconds($items[0]['dateStr']) > $_S['week']*3) {

      # TODO: add links in text?
      $msg = "There's no fresh articles on this channel.";
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
            <li class="list-group-item"><a href="javascript:refreshRss();"> <i class="fa fa-sync-alt"></i> Refresh the content</a></li>
            <li class="list-group-item"> <i class="fas fa-external-link-alt"></i> Check RSS channel status</li>
            <li class="list-group-item"><a href="javascript:delete_feed(\''.$curr_feed_id.'\')"> <i class="far fa-trash-alt"></i> Delete feed as inactive</li>
          </ul>
        </div>
      </div>
  </div>
</div>';
    }
    return '';
  } // warnRssInactivity

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
  **/
  public function showItems($items) {
    echo '<div class="accordion accordion-flush" id="rss_items">';
    foreach ($items as $item) {
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
        $link_quoted = urlencode($item['link']);
        $item_title = html_entity_decode(preg_replace('/(#\d+;)/', '&${1}', $item['title']));
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
      <button class="accordion-button collapsed item-header-bar" type="button" data-bs-toggle="collapse"
          data-bs-target="#collapse_'.$fd_postid.'" aria-expanded="false" aria-controls="collapse_'.$fd_postid.'"
          onclick="onArticleHeadingClick(event, \'heading_'.$fd_postid.'\')">
        &nbsp;
        <span class="'.($read? '':'bold-element').' no-text-overflow">'.$item_title.'</span>
        <span class="post-time-info">'.$item['passedTime'].'</span>
      </button>
    </h2>
    <div id="collapse_'.$fd_postid.'" class="accordion-collapse collapse" aria-labelledby="heading_'.$fd_postid.'" data-bs-parent="#rss_items">
    <div class="accordion-body">
         <div class="btn-group dropdown item-menu-button">
           <button class="btn btn-light btn-sm" onclick="startTitleSearch(\''.$fd_postid.'\')">
              <i class="fas fa-search"></i>
           </button>
           <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="dropdownMenuButton_'.$fd_postid.'" data-bs-toggle="dropdown" aria-expanded="false">
             <i class="fas fa-ellipsis-v"></i>
           </button>
           <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton_'.$fd_postid.'">
             <li><span class="dropdown-item-text">Share in:</span></li>
             <li><a class="dropdown-item" href="https://www.facebook.com/sharer.php?u='.$link_quoted.'" target="_blank">- Facebook</a></li>
             <li><a class="dropdown-item" href="https://www.livejournal.com/update.bml?event='.$link_quoted.'" target="_blank">- LiveJournal</a></li>
             <li><a class="dropdown-item" href="https://twitter.com/intent/tweet?original_referer='.$link_quoted.'&text=From%20FreeRSS" target="_blank">- Twitter</a></li>
             <li><a class="dropdown-item" href="javascript:changeArticle(\''.$fd_postid.'\')">Move to ...</a></li>
           </ul>
         </div>
         <h5>
           <a href="'.$item['link'].'" target="_blank" >
            '.$item_title.'
           </a>
         </h5>

         <span class="badge bg-dark">'.$item['dateStr'].'</span>&nbsp;';
      if($item['feed_info']) {
        $feed_id = $item['fd_feedid'];
        $feed_title = $item['feed_info']['title'];
        echo '<a href="read.php?type=subscr&id='.$feed_id.'" class="badge rounded-pill bg-info text-dark">'.$feed_title.'</a>&nbsp;';
      }
      if($item['watch_title']) {
        echo '<a href="read.php?type=watch&id='.$item['gr_original_id'].'" class="badge rounded-pill bg-info text-dark">'.$item['watch_title'].'</a>&nbsp;';
      }
      if($item['author']) {
        echo '<span class="badge bg-info text-dark">'.$item['author'].'</span>&nbsp;';
      }
      echo '<span class="badge bg-secondary">'.$item['categories'].'</span><br>
      '.$item['description'].'
    </div>
    </div>
  </div>';
    }
    echo '
      <button id="reload_button" type="button" class="btn btn-outline-primary" onclick="showUpdatingDialog(); window.location.reload();">
      <i class="fa fa-redo-alt"></i> Reload page
    </button>';
    echo '</div>';
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

  // udpdate items (articles) state by IDs
  public function updateItemsState($item_ids, $change_type, $new_value) {
    $query = "UPDATE `tbl_posts` SET `read`=:new_read WHERE ".
      "`user_id`=:user_id AND ".
      "`fd_postid` IN ('".implode("','", $item_ids)."') AND ".
      "`flagged`=0";
    $bindings = array(
        'new_read'  => $new_value,
        'user_id'   => $this->user_id
    );

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
  public function saveLastLink($actual_link) {
    $this->setPersonalSetting('last_page', $actual_link);
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
   *         - total_subscriptions
   *         - active_subscriptions
   *         - unread_articles
   *         - bookmarked_articles
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
        $result = $this->insertNewFeed($group_name, $text, $title, $htmlUrl, $xmlUrl);
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
  public function insertNewFeed($group, $text, $title, $htmlUrl, $xmlUrl) {
    $feed_id = _digest_hex($xml_url);
    $bindings = array(
      'user_id'   => $this->user_id,
      'group'     => $group,
      'xml_url'   => $xml_url,
      'html_url'  => $html_url,
      'title'     => $title,
      'text'      => $text,
      'feed_id'   => $feed_id
    );
    $query = "INSERT INTO `tbl_subscr` ".
      "(`user_id`, `group`, `fd_feedid`, `text`, `title`, `xmlUrl`, `htmlUrl`, `index_in_gr`, `download_enabled`) ".
      "VALUES ".
      "(:user_id,  :group,  :feed_id,    :text,  :title,  :xml_url, :html_url, 0, 1)";
    $this->db->execQuery($query, $bindings);
    return "";
  }

  /**
   * Create new RSS feed
   * @param $xml_url: feed XML URL
   * @param $title: feed title (when empty - take from feed)
   * @param $group: feed group
   * @return: error (if any), feed ID, title
  **/
  public function createFeed($xml_url, $title, $group) {
    // if inputs are wrong or duplicated - return error
    if (!$xml_url) {
      return array("Empty input", null, '');
    }
    $feed_id = _digest_hex($xml_url);

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
  **/
  public function updateFeed($feed_id, $set_enable=null, $set_xml_url=null, $set_title=null, $set_group=null, $delete=null) {
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
    if ($set_xml_url) {
      $query = "UPDATE `tbl_subscr` SET `xmlUrl`=:set_xml_url ".
          "WHERE `user_id`=:user_id AND `fd_feedid`=:feed_id";
      $bindings['set_xml_url'] = $set_xml_url;
      $this->db->execQuery($query, $bindings);
    }
    if ($set_title) {
      $query = "UPDATE `tbl_subscr` SET `title`=:set_title ".
          "WHERE `user_id`=:user_id AND `fd_feedid`=:feed_id";
      $bindings['set_title'] = $set_title;
      $this->db->execQuery($query, $bindings);
    }
    if ($set_group) {
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

?>
