<?php
 
# /*                                      *\
#   Utility functions for application
# \*                                      */


/**
 * Generate a random string, using a cryptographically secure 
 * pseudorandom number generator (random_int)
 *
 * This function uses type hints now (PHP 7+ only), but it was originally
 * written for PHP 5 as well.
 * 
 * For PHP 7, random_int is a PHP core function
 * For PHP 5.x, depends on https://github.com/paragonie/random_compat
 * 
 * @param int $length      How many characters do we want?
 * @param string $keyspace A string of all possible characters
 *                         to select from
 * @return string
 */
function random_str(
  int $length = 64,
  string $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'
): string {
  if ($length < 1) {
      throw new \RangeException("Length must be a positive integer");
  }
  $pieces = [];
  $max = mb_strlen($keyspace, '8bit') - 1;
  for ($i = 0; $i < $length; ++$i) {
      $pieces []= $keyspace[random_int(0, $max)];
  }
  return implode('', $pieces);
} // random_str

/**
 * Get unique hex key from any string
 * @param $str: input string
 * @return:     hexadecimal MD5 checksum
 *              in format like 'f441998e-a1d8-cdca-dce2-3df788f16bdc'
 */
function _digest_hex($str) {
    return preg_replace('/(.{8})(.{4})(.{4})(.{4})(.{12})/',
                        '$1-$2-$3-$4-$5',
                        md5($str));
}

/**
 * Generate digest from GUID (remove irrelevant data)
 * @param $str: input GUID URL
 * @return:     hexadecimal MD5 checksum for pure address part
 */
function _guid_digest_hex($str) {
    $guid = str_replace(' at ', '', $str);
    $guid = str_replace('http', '', $guid);
    $guid = str_replace('://', '', $guid);
    return _digest_hex($guid);
}

/**
 * Get delta (in seconds) since timestamp
 */
function _date_to_passed_seconds($timestamp) {

  $seconds = strtotime($timestamp);
  $seconds_now = strtotime("now");

  return ($seconds_now - $seconds);
} # _date_to_passed_seconds

/**
 * Seconds in time periods
 */
$_S = array('minute'=>60);
$_S['hour'] = $_S['minute']*60;
$_S['day']  = $_S['hour']*24;
$_S['week'] = $_S['day']*7;

/**
 * Convert string representation of timestamp into symbolic
 * equivalent like "1 minute before" "2 hours before" "7 days before"
 * @param $timestamp: time representation as human-readable string
 * @return: back-in-time reference or original string (for more than 3 weeks)
 */
function _date_to_passed_time($timestamp) {
  global $_S;   # seconds in time periods

  $delta = _date_to_passed_seconds($timestamp);
  # check seconds / minutes / hours / days / weeks
  # return the same date if difference is more than 3 weeks
  if ($delta > $_S['week']*3) { return $timestamp; }
  if ($delta > $_S['week']  ) { return (floor($delta/$_S['week']  ).' <INTL>weeks ago</INTL>');   }
  if ($delta > $_S['day']   ) { return (floor($delta/$_S['day']   ).' <INTL>days ago</INTL>' );   }
  if ($delta > $_S['hour']  ) { return (floor($delta/$_S['hour']  ).' <INTL>hours ago</INTL>');   }
  if ($delta > $_S['minute']) { return (floor($delta/$_S['minute']).' <INTL>minutes ago</INTL>'); }
  if ($delta > 1      ) { return (     $delta      .' <INTL>seconds ago</INTL>'); }
  return "$timestamp (now)";
} # _date_to_passed_time 

/**
 * Ugly replacement for "sleep()"
 * My hoster blocked the original function "for security reasons"
 * Well, I did some homework and voila
 * @param $seconds: delay in seconds
**/
function my_sleep($seconds) {

  $ch = curl_init("http://8.8.8.8/down/time");
  curl_setopt($ch, CURLOPT_FILE, $fp);
  curl_setopt($ch, CURLOPT_HEADER, 0);
  curl_setopt($ch, CURLOPT_TIMEOUT, $seconds);
  curl_exec($ch);
  curl_close($ch);
}


?>
