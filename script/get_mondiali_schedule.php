<?php
header('Content-Type: application/json');

$cacheFile = sys_get_temp_dir() . "/fm_worldcup_schedule_2026.json";
$forceSync = isset($_GET['sync']) && $_GET['sync'] === '1';

if (!$forceSync && file_exists($cacheFile) && filesize($cacheFile) > 0) {
    header('X-Cache: HIT');
    echo file_get_contents($cacheFile);
    exit;
}

define('API_KEY', '1a4942a032906326bcdaa564e10dbe65');
define('API_BASE_URL', 'https://v3.football.api-sports.io/');

function apiGet(string $endpoint): ?array {
    $url = API_BASE_URL . $endpoint;
    $ch  = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'x-apisports-key: ' . API_KEY,
            'Accept: application/json',
        ],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$resp) return null;
    $data = json_decode($resp, true);
    if (!empty($data['errors'])) return null;
    return $data ?? null;
}

$LEAGUE_ID = 1; 
$SEASON = 2026; 

$fixturesData = apiGet("fixtures?league={$LEAGUE_ID}&season={$SEASON}");

if (empty($fixturesData['response'])) {
    die(json_encode(['status' => 'error', 'message' => "Nessuna partita trovata per L{$LEAGUE_ID} S{$SEASON}"]));
}

$output = [
    'status' => 'success',
    'total'  => count($fixturesData['response']),
    'source' => 'API-Sports',
    'data'   => $fixturesData['response']
];

$jsonOutput = json_encode($output);

file_put_contents($cacheFile, $jsonOutput);
echo $jsonOutput;