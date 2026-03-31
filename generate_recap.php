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

$isDraw  = ($scoreH == $scoreA);
$winSide = ($scoreH >= $scoreA) ? 'home' : 'away';

$winName  = ($winSide === 'home') ? $home : $away;
$loseName = ($winSide === 'home') ? $away : $home;
$winRef   = ($winSide === 'home') ? 'first' : 'second';
$loseRef  = ($winSide === 'home') ? 'second' : 'first';

$hasTwo = (!empty($photoH) && filter_var($photoH, FILTER_VALIDATE_URL))
       && (!empty($photoA) && filter_var($photoA, FILTER_VALIDATE_URL));

if ($isDraw) {
    $scenes = [
        "Both people standing shoulder to shoulder at the center circle, arms crossed, staring intensely into the camera. Equal rivals. Stadium lights behind them casting long shadows on the pitch.",
        "Split composition: left half shows the {$winRef} person mid-celebration fist pump, right half shows the {$loseRef} person with the exact same pose mirrored. Perfect symmetry. Neither won.",
        "Both people sitting on the pitch grass back to back, exhausted after a grueling match. Sweat dripping, jerseys dirty, mutual respect. Golden hour light flooding the empty stadium.",
        "Close-up portrait of both people face to face, foreheads almost touching, intense eye contact, breath visible in cold stadium air. A draw but the rivalry burns.",
        "Both people walking off the pitch together in the rain, side by side, each holding one half of a broken trophy. Bittersweet draw.",
        "Dramatic low-angle shot of both people shaking hands at center field, grip tight, competitive stare, stadium towering behind them. Respect between equals.",
    ];
} else {
    $scenes = [
        "The {$winRef} person in a powerful knee-slide celebration towards the camera, face screaming with raw emotion, rain drops frozen mid-air. The {$loseRef} person is blurred in the background, head down, walking away.",
        "Close-up portrait: the {$winRef} person lifting a golden trophy above their head, confetti falling, face lit with pure joy and stadium floodlights. The {$loseRef} person visible behind, applauding with a forced smile.",
        "The {$winRef} person standing on top of the stadium dugout, arms raised like a champion, fans reaching up. The {$loseRef} person sits on the bench below, towel over head.",
        "Cinematic shot of the {$winRef} person doing the Cristiano Ronaldo SIUUU celebration, jumping and turning mid-air. The {$loseRef} person is on their knees in the penalty area, devastated.",
        "The {$winRef} person kissing the badge on their jersey, eyes closed, emotional moment. In the background the {$loseRef} person is arguing with the referee, hands spread in frustration.",
        "Dramatic portrait of the {$winRef} person holding a flare, red smoke swirling around them, victorious smirk. The {$loseRef} person walks through the tunnel in the background, alone.",
        "The {$winRef} person scoring a bicycle kick, captured in freeze-frame mid-air, face determined and focused. The {$loseRef} person is the goalkeeper, diving hopelessly in the wrong direction.",
        "Movie poster composition: the {$winRef} person in the foreground, heroic pose, golden light. The {$loseRef} person faded in the background like a fallen antagonist. Score displayed like a movie title.",
        "The {$winRef} person pouring water over their own head in slow-motion celebration, droplets catching the light, euphoric expression. The {$loseRef} person sits on the grass pulling their socks down, dejected.",
        "The {$winRef} person sprinting towards the corner flag to celebrate, shirt off, athletic physique, face full of adrenaline. The {$loseRef} person stands at the halfway line, hands on hips, staring at the ground.",
        "Boxing-style face-off poster: the {$winRef} person on the left looking confident with a slight grin, the {$loseRef} person on the right looking bruised and defeated. Score in giant metallic numbers between them.",
        "The {$winRef} person standing in the center of the pitch under a single spotlight beam, holding the match ball, looking directly at the camera. The {$loseRef} person is a silhouette walking away into darkness.",
        "Renaissance painting composition: the {$winRef} person crowned with a laurel wreath, draped in light, triumphant. The {$loseRef} person kneeling, head bowed, dramatic fabric and shadows.",
        "The {$winRef} person mid-roar, veins visible, face close-up filling half the frame. The other half shows the {$loseRef} person with eyes closed, a single tear, cinematic depth of field.",
        "Overhead drone shot: the {$winRef} person lying spread-eagle on the pitch in joy, making a star shape. The {$loseRef} person curled up in fetal position nearby. Score carved into the pitch like crop circles.",
    ];
}

$styles = [
    "shot on Sony A7IV, 85mm f/1.4, shallow depth of field, cinematic color grading, stadium floodlights",
    "shot on Canon R5, 70-200mm f/2.8, dramatic rim lighting, volumetric fog, photojournalism style",
    "Hasselblad medium format look, rich tonal range, natural skin tones, editorial sports photography",
    "IMAX film still aesthetic, anamorphic lens flare, teal and orange color grade, epic scale",
    "dark moody sports portrait, single key light, deep shadows, magazine cover quality",
    "golden hour natural light, warm tones, sweat and rain detail visible on skin, intimate close-up feel",
    "high-speed flash photography, frozen motion, crisp detail, black background isolation",
    "drone perspective mixed with portrait, dramatic wide-angle distortion, HDR stadium lights",
];

$scene = $scenes[array_rand($scenes)];
$style = $styles[array_rand($styles)];

$corePrompt = "VERTICAL portrait orientation (9:16 aspect ratio). Photorealistic, NOT illustration, NOT cartoon. Show clear recognizable faces with natural skin texture and detail.";

$prompt = "{$corePrompt} {$scene} The match is '{$home}' vs '{$away}', final score '{$scoreH} - {$scoreA}' displayed on a stadium LED scoreboard in the background. Both people wear professional football kits. {$style}.";

$images = [];
if (!empty($photoH) && filter_var($photoH, FILTER_VALIDATE_URL)) {
    $images[] = ['type' => 'image_url', 'url' => $photoH];
}
if (!empty($photoA) && filter_var($photoA, FILTER_VALIDATE_URL)) {
    $images[] = ['type' => 'image_url', 'url' => $photoA];
}

if (count($images) > 0) {
    $payload = [
        'model'        => 'grok-imagine-image',
        'prompt'       => $prompt,
        'n'            => 1,
        'aspect_ratio' => '9:16',
        'images'       => $images
    ];
    $endpoint = 'https://api.x.ai/v1/images/edits';
} else {
    $payload = [
        'model'        => 'grok-imagine-image',
        'prompt'       => $prompt,
        'n'            => 1,
        'aspect_ratio' => '9:16',
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