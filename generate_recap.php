<?php
header('Content-Type: application/json');

$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        [$k, $v] = explode('=', $line, 2);
        putenv(trim($k) . '=' . trim($v));
    }
}

$apiKey = getenv('XAI_API_KEY');
if (!$apiKey) die(json_encode(['status' => 'error', 'message' => 'XAI_API_KEY non configurata']));

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

$winnerIdx = ($scoreH >= $scoreA) ? 'first' : 'second';
$loserIdx  = ($scoreH >= $scoreA) ? 'second' : 'first';

$scenes = [
    "The {$winnerIdx} person just scored the winning goal and is sliding on their knees across the wet grass, arms wide open, screaming with joy. The {$loserIdx} person is in the background, hands on head in disbelief.",
    "Both people are in a tense penalty shootout moment. The {$winnerIdx} person is celebrating after scoring, while the {$loserIdx} person is the goalkeeper diving the wrong way.",
    "The {$winnerIdx} person is being lifted on teammates' shoulders holding a golden trophy, confetti everywhere. The {$loserIdx} person sits alone on the bench, head down.",
    "An intense aerial duel for the ball between both people, caught mid-air in a dramatic freeze frame. Stadium packed, flashlights going off.",
    "The {$winnerIdx} person is doing an iconic celebration (backflip, shirt over head, finger to lips), while the {$loserIdx} person argues with the referee in the background.",
    "A dramatic tunnel walk scene: both people walking out side by side from a dark stadium tunnel into blinding floodlights, intense stare-down, about to compete.",
    "The {$winnerIdx} person scores a bicycle kick goal in slow motion, ball hitting the net. The {$loserIdx} person watches helplessly from behind.",
    "Post-match scene: the {$winnerIdx} person sprays champagne in a locker room celebration. The {$loserIdx} person is shown in a split-screen sitting alone in a dark locker room.",
    "A muddy, rainy pitch. The {$winnerIdx} person slides through puddles to score. The {$loserIdx} person slips trying to defend.",
    "Video game victory screen style: the {$winnerIdx} person in a winner pose with stats floating around them, the {$loserIdx} person shown as defeated character with a 'GAME OVER' sign.",
    "Comic book panel style: the {$winnerIdx} person punches the air with a 'GOOOL!' speech bubble. The {$loserIdx} person has a '...' thought bubble.",
    "The two people are arm wrestling on the center circle of a football pitch, with the scoreboard glowing behind them.",
    "A Rocky-style movie poster: the {$winnerIdx} person stands triumphant at the top of stadium steps, fist raised. The {$loserIdx} person is at the bottom looking up.",
    "Both people as fantasy warriors on a football pitch: the {$winnerIdx} person wielding a golden boot like a sword, the {$loserIdx} person with a broken shield.",
    "An overhead drone shot of the pitch with both people standing at opposite ends, dramatic shadows, the score burnt into the grass.",
    "The {$winnerIdx} person does a Cristiano Ronaldo 'SIUU' celebration. The {$loserIdx} person is on their knees behind them.",
    "Wrestling-style promo poster: both people face to face, nose to nose, with fire effects and the score in giant neon letters between them.",
    "Anime style: the {$winnerIdx} person has a power-up aura glowing around them scoring a goal. The {$loserIdx} person is blown back by the energy.",
];

$styles = [
    "cinematic sports photography, dramatic stadium lighting, 8k",
    "epic video game cutscene, unreal engine 5, volumetric fog",
    "renaissance oil painting, dramatic chiaroscuro lighting",
    "cyberpunk neon aesthetic, rain-soaked futuristic stadium",
    "comic book illustration, bold outlines, halftone dots",
    "anime style, dynamic action lines, vibrant colors",
    "movie poster composition, dramatic color grading",
    "hyperrealistic digital art, golden hour lighting",
    "gritty sports documentary photography, high contrast black and white with selective color",
    "retro pixel art style, 16-bit aesthetic with modern detail",
];

$scene = $scenes[array_rand($scenes)];
$style = $styles[array_rand($styles)];

$prompt = "{$scene} The match is '{$home}' vs '{$away}', final score '{$scoreH} - {$scoreA}' visible on a scoreboard. Keep faces strictly faithful to input photos. Style: {$style}.";

$images = [];
if (!empty($photoH) && filter_var($photoH, FILTER_VALIDATE_URL)) {
    $images[] = ['type' => 'image_url', 'url' => $photoH];
}
if (!empty($photoA) && filter_var($photoA, FILTER_VALIDATE_URL)) {
    $images[] = ['type' => 'image_url', 'url' => $photoA];
}

if (count($images) > 0) {
    $payload = [
        'model'  => 'grok-imagine-image',
        'prompt' => $prompt,
        'n'      => 1,
        'images' => $images
    ];
    $endpoint = 'https://api.x.ai/v1/images/edits';
} else {
    $payload = [
        'model'  => 'grok-imagine-image',
        'prompt' => $prompt,
        'n'      => 1,
    ];
    $endpoint = 'https://api.x.ai/v1/images/generations';
}

$ch = curl_init($endpoint);
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

if ($curlError) die(json_encode(['status' => 'error', 'message' => 'cURL: ' . $curlError]));
if ($httpCode !== 200) die(json_encode(['status' => 'error', 'message' => 'API HTTP ' . $httpCode, 'raw' => $response]));

$data = json_decode($response, true);
$generatedImageUrl = $data['data'][0]['url'] ?? null;

if ($generatedImageUrl) {
    echo json_encode([
        'status'     => 'success',
        'image_url'  => $generatedImageUrl,
        'prompt_used' => $prompt
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Nessuna immagine nella risposta', 'raw' => $data]);
}