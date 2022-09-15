<?php

if (php_sapi_name() != 'cli') {
  exit;
}

date_default_timezone_set('UTC');
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'twitch.log');

require __DIR__ . '/configuration.php';

$token = NULL;

$ch = curl_init('https://id.twitch.tv/oauth2/token');
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/x-www-form-urlencoded'
));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, 'client_id=' . TWITCH_ID . '&client_secret=' . TWITCH_SECRET . '&grant_type=client_credentials');
curl_setopt($ch, CURLOPT_USERAGENT, 'PHP');
$response = curl_exec($ch);
if ($response) {
    $response = json_decode($response);
    $token = $response->access_token;
}

$ch2 = curl_init('https://api.twitch.tv/helix/streams?first=1&user_id=8597260');
curl_setopt($ch2, CURLOPT_HTTPHEADER, array(
  'Authorization: Bearer ' . $token,
  'Client-Id: ' . TWITCH_ID
));
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_CUSTOMREQUEST, 'GET');
curl_setopt($ch2, CURLOPT_USERAGENT, 'PHP');
$response = curl_exec($ch2);
if ($response) {
  $response = json_decode($response);
  if (count($response->data)) {
    file_put_contents('twitch.json', json_encode($response->data[0]), LOCK_EX);
  } else {
    file_put_contents('twitch.json', '', LOCK_EX);
  }
} else {
  file_put_contents('twitch.json', '', LOCK_EX);
}