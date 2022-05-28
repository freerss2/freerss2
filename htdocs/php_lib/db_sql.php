<?php

include "Mysqldump.php";

use Ifsnop\Mysqldump as IMysqldump;

# /*                                      *\
#   Generic SQL DB class
# \*                                      */

class DbSql {

  private $conn;
  private $db_conf;

  # Constructor
  # Optional argument: db_conf
  public function __construct($db_conf=null) {
    $this->conn = null;
    if ($db_conf) { $this->connectToDb($db_conf); }
  }

  /**
   * Connect to database using db_conf settings
   * @param $db_conf: dictionary with connection details
  **/
  public function connectToDb($db_conf) {
    if ( $this->conn ) { return $this->conn; }
    $this->db_conf = $db_conf;
    $limit_max = 5;
    $limit = $limit_max;
    do {
      try {
        $servername = $db_conf['servername'];
        $database = $db_conf['database'];
        $username = $db_conf['username'];
        $password = $db_conf['password'];
        $db_handle = new PDO("mysql:host=$servername;dbname=$database;charset=utf8", 
                             $username, $password);
        // set the PDO error mode to exception
        $db_handle->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->conn = $db_handle;
        $error = '';
      } 
      catch(PDOException $e) {
        $error = $e->getMessage();
        echo "Connection failed: $error";
        $this->last_error = $error;
        $sleep = (2+$limit_max-$limit)*3;
        my_sleep($sleep);
      }
      $limit--;
    } while ($error && $limit>0);
    return $db_handle;
  }

  /**
   * Dump all database to string buffer
  **/
  public function dumpDb($filename='-') {
    try {
      $servername = $this->db_conf['servername'];
      $database = $this->db_conf['database'];
      $username = $this->db_conf['username'];
      $password = $this->db_conf['password'];
      $dumpSettings = array('databases'=>true);
      $dump = new IMysqldump\Mysqldump("mysql:host=$servername;dbname=$database;charset=utf8", 
                                       $username, $password, $dumpSettings);
      $dump->start($filename);
      $result = $dump->result();
    } catch (\Exception $e) {
      $result = 'mysqldump-php error: ' . $e->getMessage();
    }
    return $result;
  }

  /**
   * Execute query without results
   * @param $query: query string
   * @param $bindings: (optional) query parameters mapping dictionary
  **/
  public function execQuery($query, $bindings=null) {
    try {
      $sth = $this->conn->prepare($query);
      $sth->execute($bindings);
    }
    catch (Exception $e) {
      echo "ERROR: ".$e."<BR>\n";
      echo "query=$query<BR>\n";
      if ($bindings) {
        echo "bindings=".print_r($bindings, true)."<BR>\n";
      }
    }
  }

  /**
   * Fetch single row result
   * @param $query: query string
   * @param $bindings: (optional) query parameters mapping dictionary
   * @return: array of results (or empty array)
  **/
  public function fetchSingleRow($query, $bindings=array()) {
    $ret = array();
    $sth = $this->conn->prepare($query);
    $result = $sth->execute($bindings);
    if ( ! $result ) {
      return $ret;
    }
    return $sth->fetch();
  }
 
  /**
   * Fetch single item result
   * @param $query: query string
   * @param $bindings: (optional) query parameters mapping dictionary
   * @return: result value (or empty string)
  **/
  public function fetchSingleResult($query, $bindings=array()) {
    $res = $this->fetchSingleRow($query, $bindings);
    if (! $res) { return ''; }
    return $res[0];
  }

  /**
   * Fetch all rows according to query
   * @param $query: query string
   * @param $bindings: (optional) query parameters mapping dictionary
   * @return: list of rows (or empty array)
  **/
  public function fetchQueryRows($query, $bindings=array()) {
    $ret = array();
    $sth = $this->conn->prepare($query);
    $result = $sth->execute($bindings);
    if ( ! $result ) {
      return $ret;
    }
    while ($row = $sth->fetch()) {
      array_push($ret, $row);
    }
    return $ret;
  }

  // if got scalar - return it back
  // if passed array - join it with ","
  function joinList($keyword, $arg) {
    if ( $arg ) {
      if (! is_array($arg)) {
        $arg = array($arg);
      }
      $quoted = array();
      foreach ($arg as $a) {
        if (strpos($a, '`') !== false || strpos($a, '*') !== false) {
          array_push($quoted, $a);
        } else {
          array_push($quoted, "`$a`");
        }
      }
      return "$keyword ".implode(", ", $quoted);
    }
    return "";
  }

  /**
   * Build simple query for table fields and return the result
   * @param $table_name: table to query
   * @param $what: list of attributes to fetch
   * @param $where: condition (optional)
   * @param $order: sort order (optional)
   * @return: list of arrays (or empty array)
  **/
  public function queryTable($table_name, $what, $where=null, $order=null) {
    $what = $this->joinList("", $what);
    if ($where) {
      $where = $this->buildWhere($where);
      $where = "WHERE $where";
    } else {
      $where = '';
    }
    $order = $this->joinList("ORDER BY", $order);
    $query = "SELECT $what FROM $table_name $where $order";
    // echo "$query<br>\n";
    return $this->fetchQueryRows($query);
  }

  function buildWhere($where) {
    if ($where && is_array($where)) {
        $where_cond = array();
        foreach ($where as $field => $value) {
            if ( ! is_numeric($value) ) { $value = "'$value'"; }
            array_push($where_cond, "`$field` = $value");
        }
    } else {
        if ( ! $where ) { $where = ''; }
        $where_cond = array($where);
    }
    return implode(" AND ", $where_cond);
  }

  // query table records
  // return all records with all fields inside
  public function queryTableRecords($table_name, $where=null, $order=null) {
    $what = '*';
    $where = $this->buildWhere($where);
    return $this->queryTable($table_name, $what, $where, $order);
  }

  // update field(s) for records matching 'where' condition
  // Where-condition could be a hash (field=>value) 
  //   or array of conditions,
  //   or just scalar (string) with condition
  // @param $table_name: schema table name
  // @param $set: dictionary of field name and target value
  // @param $where: dictionary or list or scalar, describing condition
  public function updateRecordsByFields($table_name, $set, $where) {
    $result=0;
    $where = $this->buildWhere($where);
    $bindings = $set;
    $fields = array();
    foreach ($set as $field => $value) {
      $fields[] = "`$field`=:$field";
    }
    $to_set = implode(', ', $fields);
    $sql = "UPDATE `$table_name` SET $to_set";
    if ($where) { $sql .= " WHERE $where"; }
    
    $this->execQuery($sql, $bindings);
  } // updateRecordsByFields

  # create table (if not exists)
  # table_schema is a dictionary of {table_field => field_definition}
  # return 1 if created
  public function createTable($table_name, $table_schema) {
    if ( $this->existTable($table_name) ){ return 0; }

    $schema = array();
    foreach ( $table_schema as $key => $value) {
      array_push($schema, "`$key` $value");
    }
    $schema = implode(' , ', $schema);
    $sql = "CREATE TABLE `$table_name` ( $schema );";
    # warn "DEBUG: createTable - sql=($sql)\n";
    $this->execQuery($sql);

    return 1;
  } # CreateTable

  # return 1/0 indicating given table existance
  public function existTable($table_name) {
    $sql = "SELECT 1 FROM `$table_name` LIMIT 1";
    $sh = $this->conn->prepare($sql);
    try {
      $result = $sh->execute() !== false;
    }
    catch (Exception $e) {
      $result = false;
    }
    # echo "DEBUG: existTable($table_name) ($result)\n";
    return $result;
  } # existTable


  public function createIndexes($table_name, $indexes) {
    $result = 0;

    if ( ! $indexes ){ return $result; }
    foreach ($indexes as $index_name => $index_descr) {
      list($index_name, $unique) = explode(' ', $index_name.' ');
      if ( $this->existIndex($table_name, $index_name) ) { continue; }

      $fields = array();
      foreach ($index_descr as $field) {
        list($field, $size) = explode(' ', $field.' ');
        if ($size) { $field = "`$field` ($size)"; }
        else       { $field = "`$field`"; }
        array_push($fields, $field);
      }
      $fields = implode(', ', $fields);
      $sql = "CREATE $unique INDEX `$index_name` ON `$table_name` ($fields);";
      $this->execQuery($sql);
    }
    return $result;
  }

  // Check if exist index with such name for this table
  public function existIndex($table_name, $index_name) {
    $sql = "SHOW INDEX FROM `$table_name` WHERE Key_name = '$index_name';";
    $row =  $this->fetchSingleRow($sql);
    return $row;
  }

}

?>
