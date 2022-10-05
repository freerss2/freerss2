<?php

/* Find in site code articles using predefined patterns and generate XML
 * Inputs: site URL, article pattern, result mapping, global pattern
 *
 * Example:
  {
    "global_pattern": "{*}<div id=\"mainside\" class=\"structure\">{%}<div class=\"banner\">{*}",
    "item_pattern": "{*}<div class=\"story_line\">{*}<a href=\"{%}\" title{*}\">{*}<i class=\"image cover\" style=\"background-image: url({%})\"></i>{*}<b class=\"date\">{%}</b>{*}<span class=\"title\">{%}</span>{*}</div>{*}</a>",
    "mapping": {
      "title": "{%4}", "link": "{%1}", "content": "<B>{%3}</B><BR>\n<img src=\"{%2}\" />"
    }
  }
 */

/* Substitute in given string 
 * placeholders {i} with parameters from array 1..n
 */
function apply_parameters($str, $parameters) {
    # start with index=1 since parameters numbered from 1
    for ($i=1; $i<count($parameters); $i++) {
        $str = str_replace('{%'.$i.'}', $parameters[$i], $str);
    }
    return $str;
}


class SiteToFeed {

    public function __construct($site, $item_pattern, $result_mapping, $global_pattern=Null) {
        $this->site           = $site;
        $this->item_pattern   = $item_pattern;
        $this->result_mapping = $result_mapping;
        $this->global_pattern = $global_pattern;
    }

    /* Convert site content to RSS
    ** When nothing found - return empty result
    */
    public function convert_to_rss($content) {
        $content = str_replace("\n", ' ', $content);
        # Get match if global_pattern defined
        if ($this->global_pattern) {
          $global_pattern = $this->convert_pattern_to_regex($this->global_pattern, 1);
          preg_match($global_pattern, $content, $matches);
          if ($matches) {
            $content = $matches[1];
          }
        }
        $result = array('feed' => $this->site, 'items' => array());
        # Search for articles
        $item_pattern = $this->convert_pattern_to_regex($this->item_pattern, 0);
        $items = array();
        do {
            preg_match($item_pattern, $content, $item_match);
            if ($item_match) {
              $content = str_replace($item_match[0], '', $content);
              $items []= $item_match;
            }
        } while( $item_match );
        if (! $items) {
            return '';
        }
        for ($i=0; $i<count($items); $i++) {
           $item = $items[$i];
           # Build the title, link and content according to $this->result_mapping
           $link = apply_parameters($this->result_mapping->link, $item);
           $fd_postid = $link;
           $pubDate = date("Y-m-d G:i");
           array_push($result['items'], array(
             'title' => apply_parameters($this->result_mapping->title, $item),
             'link' => $link,
             'description' => apply_parameters($this->result_mapping->content, $item),
             'author' => '',
             'categories' => '',
             'dateStr'    => $pubDate,
             'timestamp'  => strtotime($pubDate),
             'fd_postid'  => _guid_digest_hex($fd_postid),
             'guid' => $fd_postid
             ));
        }
        return $result;
    }

    /* Convert pattern with wildcards {*} and content {%} to regex
     * Apply non-greedy modifier when $greedy is "False"
     */
    private function convert_pattern_to_regex($pattern, $greedy=0) {
        $pattern = str_replace('(', '\(', $pattern);
        $pattern = str_replace(')', '\)', $pattern);
        $pattern = str_replace('/', '\/', $pattern);
        if ($greedy) {
            $wildcard_target = '.*';
            $content_target = '(.*)';
        } else {
            $wildcard_target = '.*?';
            $content_target = '(.*?)';
        }
        $pattern = str_replace('{*}', $wildcard_target, $pattern);
        $pattern = str_replace('{%}', $content_target, $pattern);
        return "/$pattern/";
    }

}

/*
       ### Reference design
$content = '<div class="body_right">

<div id="mainside" class="structure">
<div class="str_left">



<section id="content">
<div id=\'dle-content\'><article class="block story shortstory">

<div class="tab-content">

<div class="tab-pane active" id="news_top"><div class="story_line">
<a href="https://audioknig.su/literatura/fantastika/760420-vremja-ubivaet-golovachev-vasilij.html" title="Время убивает - Головачев Василий">
<i class="image cover" style="background-image: url(/uploads/posts/2022-09/1663522591_1591_golovachyov_vasilij___absolyutnoe_oruzhie_vasiliya_golovachyova_1__vremya_ubivaet.jpg)"></i>
<div>
<b class="date"><time datetime="2022-09-18">18/09/2022</time></b>
<span class="title">Время убивает - Головачев Василий</span>
</div>
</a>
</div><div class="story_line">
<a href="https://audioknig.su/literatura/detektiv/760336-v-otsutstvie-nachalstva-svechin-nikolaj.html" title="В отсутствие начальства - Свечин Николай">
<i class="image cover" style="background-image: url(/uploads/posts/2022-09/1662274532_2081_svechin_nikolaj___sischik_ego_velichestva_30_v_otsutstvie_nachaljstva.jpg)"></i>
<div>
<b class="date"><time datetime="2022-09-04">04/09/2022</time></b>
<span class="title">В отсутствие начальства - Свечин Николай</span>
</div>
</a>
</div><div class="story_line">
<a href="https://audioknig.su/literatura/fantastika/760344-aleksandrovske-kadety-tom-1-perumov-nik.html" title="Александровскіе кадеты. Том 1 - Перумов Ник">
<i class="image cover" style="background-image: url(/uploads/posts/2022-09/1662381711_9011_perumov_nik___aleksandrovsk_e_kadeti__aleksandrovsk_e_kadeti__tom_1.jpg)"></i>
<div>
<b class="date"><time datetime="2022-09-05">05/09/2022</time></b>
<span class="title">Александровскіе кадеты. Том 1 - Перумов Ник</span>
</div>
</a>
</div>

<div class="block">
<div class="banner"> 123';
$global_pattern_str = '{*}<div id="mainside" class="structure">{%}<div class="banner">{*}';
$item_pattern_str = '{*}<div class="story_line">{*}<a href="{%}" title{*}">{*}<i class="image cover" style="background-image: url({%})"></i>{*}<b class="date">{%}</b>{*}<span class="title">{%}</span>{*}</div>{*}</a>';
$mapping = array('title' => '{%4}', 'link'=>'{%1}', 'content' => "<B>{%3}</B><BR>\n<img src=\"{%2}\" />");
$s = new SiteToFeed('https://audioknig.su/', $item_pattern_str, $mapping, $global_pattern_str);
$r = $s->convert_to_rss($content);
echo json_encode($r);
echo "\n------------------\n";
*/

?>
