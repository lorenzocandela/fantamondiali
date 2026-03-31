<?php
header('Content-Type: application/json');

$requestData = json_decode(file_get_contents('php://input'), true);

if (!$requestData || !isset($requestData['home_name'])) {
    die(json_encode(['status' => 'error', 'message' => 'Dati mancanti']));
}

$home   = $requestData['home_name'];
$away   = $requestData['away_name'];
$scoreH = $requestData['home_score'];
$scoreA = $requestData['away_score'];
$photoH = $requestData['home_photo'];
$photoA = $requestData['away_photo'];

$styles = [
    "cinematic sports reconstruction, brutalist action photography",
    "epic video game cutscene style, unreal engine 5 render, dramatic lighting",
    "renaissance oil painting style, depicting a chaotic battle on pitch",
    "futuristic cyberpunk football reconstruction, neon effects"
];
$randomStyle = $styles[array_rand($styles)];

$winnerIdx = ($scoreH >= $scoreA) ? 'first' : 'second';
$loserIdx  = ($scoreH >= $scoreA) ? 'second' : 'first';

$prompt = "A high-end, dynamic {$randomStyle} showing a dramatic reconstruction of a football match between '{$home}' and '{$away}'. The final score '{$scoreH} - {$scoreA}' is displayed giant on a broken stadium scoreboard. The {$winnerIdx} person is celebrating wildly wearing a gold medal, while the {$loserIdx} person looks devastated on the muddy pitch. Keep the facial features of both people strictly faithful to the input photos. Background has heavy atmosphere, screaming fans, pouring rain, 8k resolution, photorealistic but stylized.";

$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        [$key, $value] = explode('=', $line, 2);
        putenv(trim($key) . '=' . trim($value));
    }
}

$apiKey = getenv('XAI_API_KEY');

if (!$apiKey) {
    die(json_encode([
        'status' => 'error',
        'message' => 'XAI_API_KEY non configurata'
    ]));
}

$payload = [
    'model'  => 'grok-imagine-image',
    'prompt' => $prompt,
    'n'      => 1,
    'images' => [
        ['type' => 'image_url', 'url' => $photoH],
        ['type' => 'image_url', 'url' => $photoA]
    ]
];

$ch = curl_init('https://api.x.ai/v1/images/edits');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_TIMEOUT        => 90,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ],
    CURLOPT_POSTFIELDS => json_encode($payload)
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    die(json_encode(['status' => 'error', 'message' => 'Errore cURL: ' . $curlError]));
}

if ($httpCode !== 200) {
    die(json_encode(['status' => 'error', 'message' => 'API HTTP ' . $httpCode, 'raw' => $response]));
}

$data = json_decode($response, true);
$generatedImageUrl = $data['data'][0]['url'] ?? null;

if ($generatedImageUrl) {
    echo json_encode([
        'status'     => 'success',
        'image_url'  => $generatedImageUrl,
        'prompt_used' => $prompt
    ]);
} else {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Nessuna immagine nella risposta',
        'raw'     => $data
    ]);
}