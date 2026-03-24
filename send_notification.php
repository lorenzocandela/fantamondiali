<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Metodo non permesso']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$title = $input['title'] ?? '';
$body  = $input['body']  ?? '';

if (!$title) {
    echo json_encode(['status' => 'error', 'message' => 'Titolo richiesto']);
    exit;
}

// ─── CONFIG ───────────────────────────────────────────────────────────────────

$serviceAccountPath = __DIR__ . '/firebase-service-account.json';
$projectId          = 'fantamondiali-e1f5c';

// ─── ACCESS TOKEN ─────────────────────────────────────────────────────────────

require_once __DIR__ . '/vendor/autoload.php';

use Google\Auth\Credentials\ServiceAccountCredentials;

function getAccessToken($serviceAccountPath) {
    $credentials = new ServiceAccountCredentials(
        'https://www.googleapis.com/auth/firebase.messaging',
        json_decode(file_get_contents($serviceAccountPath), true)
    );
    $token = $credentials->fetchAuthToken();
    return $token['access_token'] ?? null;
}

// ─── FIRESTORE: LEGGI TOKENS ─────────────────────────────────────────────────

function getTokensFromFirestore($projectId, $accessToken) {
    $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/fcm_tokens?pageSize=500";
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$accessToken}"],
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) return [];

    $data   = json_decode($res, true);
    $tokens = [];
    foreach ($data['documents'] ?? [] as $doc) {
        $fields = $doc['fields'] ?? [];
        $token  = $fields['token']['stringValue'] ?? null;
        if ($token) $tokens[] = $token;
    }
    return $tokens;
}

// ─── INVIO PUSH ───────────────────────────────────────────────────────────────

function sendPush($projectId, $accessToken, $token, $title, $body) {
    $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

    $message = [
        'message' => [
            'token' => $token,
            'data'  => [
                'title' => $title,
                'body'  => $body,
                'url'   => '/',
            ],
            'webpush' => [
                'headers' => [
                    'Urgency' => 'high',
                ],
                'notification' => [
                    'title' => $title,
                    'body'  => $body,
                    'icon'  => '/logo_fm26.png',
                ],
            ],
        ],
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer {$accessToken}",
            "Content-Type: application/json",
        ],
        CURLOPT_POSTFIELDS => json_encode($message),
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $code === 200;
}

// ─── ESECUZIONE ───────────────────────────────────────────────────────────────

try {
    if (!file_exists($serviceAccountPath)) {
        throw new Exception('Service account JSON non trovato. Scaricalo da Firebase Console.');
    }

    $accessToken = getAccessToken($serviceAccountPath);
    if (!$accessToken) throw new Exception('Impossibile ottenere access token');

    $tokens = getTokensFromFirestore($projectId, $accessToken);
    if (empty($tokens)) {
        echo json_encode(['status' => 'ok', 'sent' => 0, 'message' => 'Nessun token registrato']);
        exit;
    }

    $sent   = 0;
    $failed = 0;
    foreach ($tokens as $token) {
        if (sendPush($projectId, $accessToken, $token, $title, $body)) {
            $sent++;
        } else {
            $failed++;
        }
    }

    echo json_encode([
        'status'  => 'ok',
        'sent'    => $sent,
        'failed'  => $failed,
        'total'   => count($tokens),
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}