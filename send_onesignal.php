<?php
$APP_ID = "74d236f6-3c61-476d-a2ce-f71beed3c045";
$REST_API_KEY = "os_v2_app_otjdn5r4mfdw3iwo64n65u6aixf6rxqoxziuf55q3kmgpbuzw6mktni4bpsj5tbzmrraevmd5z7vp6u7jhmulcc36bocrmhrdtpvfxa";

$data = json_decode(file_get_contents('php://input'), true);
$title = $data['title'] ?? 'FantaMondiali 2026';
$message = $data['message'] ?? 'Nuovo aggiornamento disponibile!';

$content = array("en" => $message, "it" => $message);
$headings = array("en" => $title, "it" => $title);

$fields = array(
    'app_id' => $APP_ID,
    'included_segments' => array('All'),
    'contents' => $content,
    'headings' => $headings
);

$fields = json_encode($fields);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json; charset=utf-8',
    'Authorization: Basic ' . $REST_API_KEY
));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_HEADER, FALSE);
curl_setopt($ch, CURLOPT_POST, TRUE);
curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

$response = curl_exec($ch);
curl_close($ch);

echo $response;
?>