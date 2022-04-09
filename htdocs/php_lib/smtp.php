<?php
#! /usr/bin/env php

# /*                                      *\
#   Send SMTP via API
# \*                                      */

$smtp_conf = [
	CURLOPT_URL => "https://rapidprod-sendgrid-v1.p.rapidapi.com/mail/send",
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_FOLLOWLOCATION => true,
	CURLOPT_ENCODING => "",
	CURLOPT_MAXREDIRS => 10,
	CURLOPT_TIMEOUT => 30,
	CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	CURLOPT_CUSTOMREQUEST => "POST",
	CURLOPT_POSTFIELDS => "{\r
    \"from\": {\r
        \"email\": \"%s\"\r
    },\r
    \"personalizations\": [\r
        {\r
            \"to\": [\r
                {\r
                    \"email\": \"%s\"\r
                }\r
            ],\r
            \"subject\": \"%s\"\r
        }\r
    ],\r
    \"content\": [\r
        {\r
            \"type\": \"text/plain\",\r
            \"value\": \"%s\"\r
        }\r
    ]\r
}",
	CURLOPT_HTTPHEADER => [
		"content-type: application/json",
		"x-rapidapi-host: rapidprod-sendgrid-v1.p.rapidapi.com",
		"x-rapidapi-key: {{api_key}}"
	],
];

/**
 * Send mail according to conf
 * @param $conf: JSON template for sending message
 * @param $from: sender mail                 noreply@freerss2.org
 * @param $to: recipient mail                  new.user@gmail.com
 * @param $subject: message subject string
 *                                  Registration on FreeRSS2 site
 * @param $body: message body
 *                       This is an example of registration email
 * @return: list of 2 elements - reply and error
**/
function sendMail( $conf, $from, $to, $subject, $body ) {
  $curl = curl_init();
  $conf[CURLOPT_POSTFIELDS] =
    sprintf($conf[CURLOPT_POSTFIELDS], $from, $to, $subject, $body);
  curl_setopt_array($curl, $conf);

  $response = curl_exec($curl);
  $err = curl_error($curl);
  curl_close($curl);

  return array($response, $err);
}

?>