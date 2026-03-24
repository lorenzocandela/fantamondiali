<?php
require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$APP_ID = $_ENV['ONESIGNAL_APP_ID'];
$REST_API_KEY = $_ENV['ONESIGNAL_REST_API_KEY'];

$data = json_decode(file_get_contents('php://input'), true);
$title = $data['title'] ?? 'Test';
$message = $data['message'] ?? 'Messaggio di prova';

$fields = array(
    'app_id' => $APP_ID,
    'included_segments' => array('Total Subscriptions'), 
    'contents' => $content,
    'headings' => $headings
);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json; charset=utf-8',
    'Authorization: Basic ' . $REST_API_KEY
));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_POST, TRUE);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

$response = curl_exec($ch);
curl_close($ch);

// --- LOGGING DEBUG ---
$log_entry = [
    'timestamp' => date('Y-m-d H:i:s'),
    'payload_sent' => $fields,
    'onesignal_response' => json_decode($response, true)
];
$current_logs = file_exists('push_debug.json') ? json_decode(file_get_contents('push_debug.json'), true) : [];
array_unshift($current_logs, $log_entry); // Metti l'ultimo log in cima
file_put_contents('push_debug.json', json_encode(array_slice($current_logs, 0, 20))); // Tieni gli ultimi 20

echo $response;
?>