<?php
  phpinfo();
  exit(0);

  session_start();

  define('SOURCE_LEVEL', 2);
  $INCLUDE_PATH = str_repeat('../', SOURCE_LEVEL) . 'php_lib';

  include "$INCLUDE_PATH/rss_app.php";

  $sleep = 10;
  $timeout = $sleep;

  $ch = curl_init("http://8.8.8.8/down/time");
  curl_setopt($ch, CURLOPT_FILE, $fp);
  curl_setopt($ch, CURLOPT_HEADER, 0);
  curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

  curl_exec($ch);
  curl_close($ch);


  echo "<h1>I'm done after $sleep seconds</h1>";
  exit(0);

  $rss_app = new RssApp();

  $user_id = $_SESSION['user_id'];
  $rss_app->setUserId($user_id);

$curl = curl_init();

curl_setopt_array($curl, [
	CURLOPT_URL => "https://rapidprod-sendgrid-v1.p.rapidapi.com/mail/send",
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_FOLLOWLOCATION => true,
	CURLOPT_ENCODING => "",
	CURLOPT_MAXREDIRS => 10,
	CURLOPT_TIMEOUT => 30,
	CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	CURLOPT_CUSTOMREQUEST => "POST",
	CURLOPT_POSTFIELDS => "{\r
    \"personalizations\": [\r
        {\r
            \"to\": [\r
                {\r
                    \"email\": \"felix.liberman@gmail.com\"\r
                }\r
            ],\r
            \"subject\": \"Registration on FreeRSS2 site\"\r
        }\r
    ],\r
    \"from\": {\r
        \"email\": \"noreply@freerss2.org\"\r
    },\r
    \"content\": [\r
        {\r
            \"type\": \"text/plain\",\r
            \"value\": \"This is an example of registration email\"\r
        }\r
    ]\r
}",
	CURLOPT_HTTPHEADER => [
		"content-type: application/json",
		"x-rapidapi-host: rapidprod-sendgrid-v1.p.rapidapi.com",
		"x-rapidapi-key: 9c045d2101msh8b084513e0cc848p161870jsn7d05fce4837d"
	],
]);

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
	echo "cURL Error #:" . $err;
} else {
	echo $response;
}

?>