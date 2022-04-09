<?php

# /*                                      *\
#   OPML input/output
# \*                                      */



# Convert associated array of params to string representation key="val"
# @param $params: input dictionary of parameters
# @return: string representation (prepended with space when non-empty)
function paramsToStr($params) {
   $pairs = array();
    foreach($params as $name => $value) {
        $pairs []= $name.'="'.$value.'"';
    }
    $result = implode(' ', $pairs);
    if (!$result) { return ''; }
    return ' '.$result;
}

# Single XML tag class: <name param="value" />
class XmlSingleTag {
    private $name;
    private $params;

    # Constructor
    # @param $name: tag name
    # @param $level: printing level (integer)
    # @param $params: dictionary of optional tag parameters
    public function __construct($name, $level=0, $params=null) {
        $this->name = $name;
        $this->level = $level;
        if (is_null($params)) {
            $params = array();
        }
        $this->params = $params;
    }

    # Single parameter setter
    public function set_param($name, $value) {
        $this->params[$name] = $value;
    }

    # Convert object to string
    public function toStr() {
        $params = paramsToStr($this->params);
        $indent = str_repeat('  ', $this->level);
        return $indent.'<'.$this->name.$params.' />';
    }
}

# Paired XML tag class: <name param="value"> content... </name>
class XmlPairTag {
    private $name;
    private $params;
    private $level;
    private $content;

    # Constructor
    # @param $name: tag name; can contain extra attributes after space
    # @param $level: printing level (integer)
    # @param $content: optional content (array)
    public function __construct($name, $level=0, $content=null) {
        $tag_inputs = explode(' ', $name);
        $this->name = array_shift($tag_inputs);
        $this->params = implode(' ', $tag_inputs);
        $this->level = $level;
        if ( is_null($content)) {
            $content = array();
        }
        $this->content = $content;
    }

    # Append string to content    
    public function array_push($str) {
        $this->content []= $str;
        return $this;
    }
    
    # Convert object to string
    # @param $delimiter: delimiter for joining tag and content
    public function toStr($delimiter="\n") {
        $result = array();
        $indent = str_repeat('  ', $this->level);
        if ($this->params) {
            $this->params = ' '.$this->params;
        }
        $result []= ($indent.'<'.$this->name.$this->params.'>');
        $result = array_merge($result, $this->content);
        if (! $delimiter) { $indent=''; }
        $result []= ($indent.'</'.$this->name.">");
        return implode($delimiter, $result);
    }
}

# Generate (XML) string representation of OPML structure
# opml
#   head
#     title: V
#     dateModified: V
#   body
#     outline text=V:
#        outline text=V htmlUrl=V ... /
class GenOpml {
    private $params;
    private $head;
    private $body;

    public function __construct($main_params=array()) {
        $this->params = $main_params;
        $this->head = array();
        $this->body = array();
    }
    
    public function toStr() {
        $params = paramsToStr($this->params);
        $opml = new XmlPairTag('opml'.$params);
        $head = new XmlPairTag('head', 1, $this->head);
        $opml->array_push($head->toStr());
        $body = new XmlPairTag('body', 1, $this->body);
        $opml->array_push($body->toStr());
        return $opml->toStr();
    }
    
    public function setHead($title, $date_modified) {
      $this->head []= (new XmlPairTag('title', 2))->array_push($title)->toStr('');
      $this->head []= (new XmlPairTag('dateModified', 2))->array_push($date_modified)->toStr('');
    }
    
    public function appendToBody($data) {
        array_push($this->body, $data);
    }
}

# Reference design

/*
$opml = new GenOpml(array('version'=>'1.1'));
$opml->setHead('RSS Subscriptions', 'today');
$gr1 = new XmlPairTag('outline text="News"', 2);
### type="rss" htmlUrl="http://9tv.co.il/news/" title="9tv новости" xmlUrl="http://9tv.co.il/rss/news/news_32.xml" text="9tv новости"
$o1 = new XmlSingleTag('outline', 3, array('text'=>'iXBT.com', 'htmlUrl'=>"http://9tv.co.il/news/"));
$gr1->array_push($o1->toStr());
$opml->appendToBody($gr1->toStr());
print $opml->toStr();
*/

?>