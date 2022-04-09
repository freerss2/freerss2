<?php

include "db_sql.php";

# /*                                      *\
#   Application-specific DB functionality
# \*                                      */

class DbApp extends DbSql {

  private $DB_SCHEMA = array( # database structure
    'tbl_users'     => array(
      'user_id'     => 'INTEGER',
      'full_name'   => 'TEXT',
      'login_name'  => 'TEXT',
      'email'       => 'TEXT',
      'phone'       => 'TEXT',
      'login_timestamp'      => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP', # last login timestamp
      'expiration_timestamp' => "TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00'",
      'api_token'   => 'TEXT', # temporary API token for limited time period
      'password'    => 'TEXT'   # store salted checksum only!
    ),
    'tbl_subscr'    => array(   # subscriptions list
      'user_id'     => 'INTEGER',
      'fd_feedid'   => 'TEXT',
      'text'        => 'TEXT',
      'title'       => 'TEXT',
      'xmlUrl'      => 'TEXT',
      'htmlUrl'     => 'TEXT',
      'group'       => 'TEXT',
      'index_in_gr' => 'INTEGER', # index for subscr forced re-order by user
      'download_enabled' => 'INTEGER', # download enable flag
    ),
    'tbl_watches'   => array( # tags AKA watches AKA newspapers - set of records with specific keyword
      'user_id'     => 'INTEGER',
      'title'       => 'TEXT', # displayed name
      'fd_watchid'  => 'TEXT', # 'tag_' + ID to be stored in associated posts
    ),
    'tbl_rules'     => array( # rules for automatic fintering/tagging
      'user_id'     => 'INTEGER',
      'title'       => 'TEXT', # rule name
      'rl_id'       => 'TEXT', # hexId of this rule
      'rl_type'     => 'TEXT', # type=simple/text
      'rl_action'   => 'TEXT', # mark_read/mark_unread/set_tag/reset_tag/erase
      'rl_act_arg'  => 'TEXT', # tag ID (when actual)
    ),
    # rule_id     chk_field  chk_op  chk_arg
    # FDFF123EF1  fd_feedid  ==      321FE231DC643
    # FDFF123EF1  htmlUrl    like    %/trance/%
    #
    # EF1289ECD9  categories in     'wallpapers', 'RPG', 'PSP'
    'tbl_rules_simple' => array( # rules conditions
      'user_id'     => 'INTEGER',
      'rl_id'       => 'TEXT', # hexId (same IDs are AND-ed)
      'chk_field'   => 'TEXT', # field to be tested
      'chk_op'      => 'TEXT', # ==, !=, LIKE, NOT LIKE, IN, NOT IN
      'chk_arg'     => 'TEXT', # free text to be checked agains field
    ),
    'tbl_rules_text' => array( # free-text conditions per rule
      'user_id'     => 'INTEGER',
      'rl_id'       => 'TEXT', # hexId (same IDs are AND-ed)
      'chk_text'    => 'TEXT', # free text of 'where' condition
    ),
    'tbl_subscr_state' => array( # state of subscriptions and watches
      'user_id'     => 'INTEGER',
      'type'        => 'TEXT', # rss / group / watch
      'id'          => 'TEXT', # hex-id of group/rss/watch
      'total'       => 'INTEGER DEFAULT 0', # total records = read + unread
      'unread'      => 'INTEGER DEFAULT 0', # unread records count
      'timestamp'   => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP', # oldest non-displayed record timestamp
      'upd_status'  => 'TEXT', # none/updated/failed
      'upd_log'     => 'TEXT', # free-text - update log
    ),
    'tbl_post_links' => array(
      'user_id'     => 'INTEGER',
      'fd_postid'   => 'TEXT',
      'fd_feedid'   => 'TEXT',
      'dt_generic'  => 'REAL DEFAULT 0',
      'link_url'    => 'TEXT',
      'link_hash'   => 'INTEGER DEFAULT 0',
    ),
    'tbl_deleted_posts' => array(
      'user_id'     => 'INTEGER',
      'fd_feedid' => 'TEXT',
      'post_hash' => 'INTEGER',
      'timestamp' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
    ),
    'tbl_posts' => array(
      'user_id'     => 'INTEGER',
      'fd_postid'        => 'TEXT',              # digest from PostID
      'fd_feedid'        => 'TEXT',              # digest from FeedURL
      'ng_postid'        => 'INTEGER DEFAULT 0', # unused
      'ng_feedid'        => 'INTEGER DEFAULT 0', # unused
      'gr_itemid'        => 'INTEGER DEFAULT 0',
      'title'            => 'TEXT',
      'description'      => 'BLOB',
      'link'             => 'TEXT',
      'guid'             => 'TEXT',              # original PostID
      'author'           => 'TEXT',
      'categories'       => 'TEXT',              # comma-separated original tags
      'read'             => 'INTEGER DEFAULT 0', # 1/0
      'flagged'          => 'INTEGER DEFAULT 0',
      'dt_pub'           => 'REAL DEFAULT 0',
      'dt_rcvd'          => 'REAL DEFAULT 0',
      'dt_generic'       => 'REAL DEFAULT 0',
      'dt_modified'      => 'REAL DEFAULT 0',
      'xml_base'         => 'TEXT',
      'source_title'     => 'TEXT', # info about original feed
      'source_xmlurl'    => 'TEXT',
      'source_htmlurl'   => 'TEXT',
      'source_fd_postid' => 'TEXT',
      'source_fd_feedid' => 'TEXT',
      'enclosure_url'    => 'TEXT',
      'enclosure_length' => 'INTEGER DEFAULT 0',
      'enclosure_type'   => 'TEXT',
      'media_thumbnail_url'=>'TEXT',
      'post_hash'        => 'INTEGER DEFAULT 0',
      'user_like'        => 'INTEGER DEFAULT 0',
      'num_likes'        => 'INTEGER DEFAULT 0',
      'gr_original_id'   => 'TEXT',
      'comments_html_url'=> 'TEXT',
      'comments_xml_url' => 'TEXT',
      'orig_link'        => 'TEXT',
      'shared'           => 'INTEGER DEFAULT 0',
      'is_translated'    => 'INTEGER DEFAULT 0',
      'is_deleted'       => 'INTEGER DEFAULT 0',
      'timestamp'        => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
    ),
    'tbl_settings' => array( # UI settings
      'user_id'     => 'INTEGER',
      'param'   => 'TEXT', # parameter name
      'value'   => 'TEXT', # value
    ),
    'tbl_intl_en' => array( # English language tokens
      'token'   => 'TEXT',    # token content
      'id'      => 'INTEGER PRIMARY KEY', # token ID
    ),
    'tbl_avail_intl' => array( # List of available intl languages
      'code'      => 'TEXT',    # 2-letter code
      'name_en'   => 'TEXT',    # language name in English
      'name_self' => 'TEXT',    # language self-name
      'rtl'       => 'INTEGER', # 1 - for Right-To-Left language
    )
  );

  # Database indexes (used for searches speed-up)
  private $DB_INDEX = array(
    'tbl_posts' => array(
      'idx_posts_dt_generic' => array('dt_generic 8'),
      'idx_posts_feedid' => array('fd_feedid 60'),
      'idx_posts_title' => array('title 120'),
      'idx_posts_timestamp' => array('timestamp'),
      'idx_posts_gritemid' => array('gr_itemid'),
      'idx_posts_postid' => array('fd_postid 60'),
      'idx_user_posts_postid UNIQUE' => array('user_id', 'fd_postid 36'),
      'idx_posts_read' => array('read'),
    )
  );


  # Automatically created tables (when missing)
  private $basic_tables = array('tbl_users', 
    'tbl_deleted_posts', 'tbl_posts', 'tbl_post_links',
    'tbl_subscr_state', 'tbl_subscr', 'tbl_settings', 'tbl_intl_en',
    'tbl_avail_intl',
    'tbl_watches', 'tbl_rules', 'tbl_rules_simple', 'tbl_rules_text');

  /**
   * Constructor
   * @param db_conf: DB configuration for connection
   */
  public function __construct($db_conf) {
      parent::__construct($db_conf);
      $this->last_error = null;
      $limit = 3;
      do {
        try {
          $this->initAppDb();
          $error = '';
        }
        //catch exception
        catch(Exception $e) {
          $error = $e->getMessage();
          $this->last_error = $error;
          $sleep = (5-$limit)*3;
          my_sleep($sleep);
        }
        $limit--;
      } while($error && $limit>0);
      # throw new Exception($error); ???
  }

  public function reportError($msg) {
      $this->last_error = $msg;
  }

  /**
   * Initialize DB - create schema when missing
  **/
  public function initAppDb() {
    foreach ($this->basic_tables as $table) {
      $created = $this->createTable($table, $this->DB_SCHEMA[$table]);
      if (! $created) { continue; }
      if ( array_key_exists($table, $this->DB_INDEX) ) {
        $this->createIndexes($table, $this->DB_INDEX[$table]);
      }
    }
  }

  /**
   * Get table records matching optional condition
   * @param $table_name: in which table to search
   * @param $where: condition for search (take all when omitted)
  **/
  public function queryTableRecords($table_name, $where=null) {
      if (array_key_exists($table_name, $this->DB_SCHEMA)) {
          return parent::queryTableRecords($table_name, $where);
      }
      # TODO: report exception
      $this->reportError("unsupported table '$table_name'");
      return array();
  }
}

?>
