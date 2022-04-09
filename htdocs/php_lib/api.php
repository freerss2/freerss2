<?php
 
# /*                                      *\
#   API arguments parsing
# \*                                      */


/**
 * URL args parser
 * @param $arguments:
 *  {
 *    'email' => {'default'=>'user@mail.com', 'descr'=>'email for registration', 'required'=>True},
 *    'gender' => {'choices'=>['M','F']},
 *    'cash' => {},
 *    'credit_card_number' => {}
 *  }
 * @param $options:
 *  {
 *    'ignore_unsupported' => True/False,
 *    'mutually_exclusive' => [ ['cash', 'credit_card_number'], ... ]
 *  }
 * @return:
 *  => error(s)
 *  => arguments dictionary
**/

function parseApiArgs($arguments, $options, $to_parse=Null) {
  $errors = array();
  $result = array();
  if (is_null($to_parse)) { $to_parse=$_GET; }
  # check that all 'required' args are present
  # collect arguments values and validate 'choices'
  foreach ($arguments as $arg_name => $arg_opt) {
    if (array_key_exists($arg_name, $to_parse)) {
      $result[$arg_name] = $to_parse[$arg_name];
    } else {
      if (!array_key_exists('required', $arg_opt)) { continue; }
      if (!$arg_opt['required']) { continue; }
      $arg_descr = array_key_exists('descr', $arg_opt) ? $arg_opt['descr'] : '';
      $errors []= "missing required argument '$arg_name' ($arg_descr)";
    }
  }
  # check mutual exclusive rules
  # for each mutual exclusive rule - check respective args existance
  $mutually_exclusive = array_key_exists('mutually_exclusive', $options) ? $options['mutually_exclusive'] : array();
  foreach ($mutually_exclusive as $mutex) {
    $found = array();
    foreach ($mutex as $m_key) {
      if (array_key_exists($m_key, $to_parse)) {
        $found []= $m_key;
      }
    }
    if (count($found)>1) {
      $errors []= "mutually exclusive arguments '".implode("', '", $found)."'";
    }
  }
  # identify remaining (unsupported) arguments
  $ignore_unsupported = array_key_exists('ignore_unsupported', $options) ? $options['ignore_unsupported'] : False;
  if (! $ignore_unsupported) {
    # get non-parsed args and report
    foreach($to_parse as $p_name => $p_val){
      if (! array_key_exists($p_name, $arguments)) {
        $errors []= "unsupported argument '$p_name'";
      }
    }
  }
  return array($errors, $result);
}

?>
