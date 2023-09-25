<?php

# /*                                      *\
#   Get movie rating using ext. site services
# \*                                      */

include "movie_rating_conf.php";


# Get movie rating using some online service
#[AllowDynamicProperties]
class MovieRatingProvider {

    public function __construct($name) {
        $this->name = $name;
    }

    public function get_rating_info($pattern) {
        # Pure virtual function
        return '';
    }

}

# Get movie rating using rutor.org online service
# /!\ This trick is NOT WORKING due to anti-crawling defence on site
class MovieRatingRutor extends MovieRatingProvider {

    public function __construct($name) {
        parent::__construct($name);
        $this->url = 'https://rutor.org';
    }

    public function get_rating_info($pattern) {
        # send request like this:
        # https://rutor.org/search/1/0/000/0/<search pattern>
        # in result find first row matching tr class="gai" or tr class="tum"
        # under this element see <a href="/torrent/886732">
        # download this link as https://rutor.org/torrent/886732
        # and finally get in this buffer line <a href="http://www.imdb.com/...
        #
        // Disable any errors reporting
        error_reporting(0);
        $search_url = $this->url . '/search/1/0/000/0/' . rawurlencode($pattern);
        $search_url = $this->url;
        $search_page_code = file_get_contents($site_url);
        if (! $search_page_code) {
            return '';
        }
        $matches = preg_grep('#.*<a href="/torrent/.*#',
                             explode("\n", $search_page_code));
        if (! $matches) {
            return '';
        }
        # find the link inside the buffer
        $movie_link = $this->url . explode('"', array_values($matches)[0])[1];
        # download the link and get right fragment
        $movie_page_code = file_get_contents($movie_link);
        if (! $movie_page_code) {
            return '';
        }
        $matches = preg_grep('#.*<a href="http://www.imdb.com/.*#',
                             explode("\n", $movie_page_code));
        if (! $matches) {
            return '';
        }
        error_reporting(E_ERROR | E_WARNING | E_PARSE);
        // Enable errors and warnings
        return array_values($matches)[0];
    }

}


# Get movie rating using unofficial kinopoisk online search
class MovieRatingKinopoiskUnoff extends MovieRatingProvider {

    public function __construct($name) {
        parent::__construct($name);
        $this->url = 'https://kinopoiskapiunofficial.tech/api/';
        $this->ver = 'v2.1';
        $this->timeout = 5;
        $this->api_key = KP_UNOFF_API_KEY;
    }

    private function decode_html($s) {
        return html_entity_decode(str_replace('#', '&#', $s));
    }

    # Possible pattern formats:
    # single title
    # title / alternative title (year)
    # title / more / titles ... (year)
    public function get_rating_info($pattern) {
        # check if $pattern contains ' / '
        # if not - use it "as is" without double-checks
        $info = explode(' / ', str_replace(' (', ' / ', preg_replace('/\).*/', '', $pattern)));
        $year = '';
        if ( is_numeric(end($info)) ) {
            $year = array_pop($info);
        }
        $pattern = $info[0];
        $search_res = $this->get_api_data('films', '/search-by-keyword?keyword=' .
                                          rawurlencode($pattern) . '&page=1');
        if (! $search_res) { return ''; }

        $search_data = json_decode($search_res);
        $films = $search_data->films;
        if (! $films) { return ''; }

        # for all $films try to find better match
        $found = false;
        # If $info contains more than 1 element - compare nameRu / nameEn (year)
        for ($i=0; $i < count($films); $i++) {
            $film = $films[$i];
            if ( ! $year) { $found = true; break; } # no comparison criteria - use first match
            # compare film year and exit on match
            $film_nameRu = $film->nameRu ?? null;
            $film_nameEn = $film->nameEn ?? null;
            if ( $year == $film->year && $info[0] == $film_nameRu) {
              $found = true; break;
            }
        }
        if ( ! $found ) { return ''; }
        if ( count($info) > 1 ) {
            # when $info contains alternative names - try to find full match
            $found = false;
            for ($i=1; $i < count($info); $i++ ) {
                if ( $info[0] == $film_nameRu && $this->decode_html($info[$i]) == $film_nameEn ) {
                    $found = true;
                    break;
                }
            }
            if ( ! $found ) { return ''; }
        }
        $kp_id = $film->filmId;
        $title = $film_nameRu . ' / ' . $film_nameEn .
          ' ('.$film->year.')';
        $result = "<a target=\"_blank\" title=\"$title\" href=\"https://www.kinopoisk.ru/film/$kp_id/\"> ".
          "<img loading=\"lazy\" src=\"http://www.kinopoisk.ru/rating/$kp_id.gif\"></a>";
        # try to get IMDb ratings
        $film_res = $this->get_api_data('films', '/'.$kp_id, 'v2.2');
        if (! $film_res) { return $result; }
        $film_data = json_decode($film_res);
        $imdb_id = $film_data->imdbId;
        # imdb code: <a href="http://www.imdb.com/title/ID/"><img loading="lazy" src="https://imdb.desol.one/ID.png"></a>
        if ($imdb_id) {
          $imdb_code = "<a href=\"http://www.imdb.com/title/$imdb_id/\"><img loading=\"lazy\" src=\"https://imdb.desol.one/$imdb_id.png\"></a>";
        } else {
          $imdb_rating = $film_data->ratingImdb;
          $imdb_count = $film_data->ratingImdbVoteCount;
          if ($imdb_rating) {
            $imdb_code = "<span><b style=\"vertical-align: top;\">IMDb</b>".
              "<span><b>$imdb_rating</b>&nbsp;($imdb_count)</span></span>";
          } else {
            $imdb_code = "";
          }
        }
        return $result . $imdb_code;
    }

# Use unofficial API
# https://kinopoiskapiunofficial.tech/documentation/api/#/films/get_api_v2_1_films_search_by_keyword
# curl -X 'GET' \
#  'https://kinopoiskapiunofficial.tech/api/v2.1/films/search-by-keyword?keyword=%D0%90%D0%B1%D0%B1%D0%B0%D1%82%D1%81%D1%82%D0%B2%D0%BE%20%D0%94%D0%B0%D1%83%D0%BD%D1%82%D0%BE%D0%BD%202&page=1' \
#  -H 'accept: application/json' \
#  -H 'X-API-KEY: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'

    private function get_api_data($func, $api_suffix, $ver=null) {
        $seconds = $this->timeout;
        error_reporting(0);
        if (! $ver) {
          $ver = $this->ver;
        }
        $search_url = $this->url . $ver . '/' . $func . $api_suffix;
        $ch = curl_init($search_url);
        $header = array(
          "Accept: application/json",
          "X-API-KEY: " . $this->api_key,
          "Keep-Alive: 300");
        # curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_TIMEOUT, $seconds);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        $result = curl_exec($ch);
        curl_close($ch);
        error_reporting(E_ERROR | E_WARNING | E_PARSE);
        return $result;
    }
}

?>
