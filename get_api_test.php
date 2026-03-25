<?php
header('Content-Type: application/json');

// 1. Dove va la cache?
$tmpDir = sys_get_temp_dir();

// 2. Lista file cache FM
$cacheFiles = glob($tmpDir . '/fm_*');

// 3. Test rapido API — cerca playoff di domani (26 marzo)
define('API_KEY', '1a4942a032906326bcdaa564e10dbe65');
$tomorrow = '2026-03-26';
$today    = date('Y-m-d');

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => "https://v3.football.api-sports.io/fixtures?date={$tomorrow}&league=960&season=2026",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 12,
    CURLOPT_HTTPHEADER     => ['x-apisports-key: ' . API_KEY, 'Accept: application/json'],
]);
$resp960 = json_decode(curl_exec($ch), true);
curl_close($ch);

// Test intercontinental
$ch2 = curl_init();
curl_setopt_array($ch2, [
    CURLOPT_URL            => "https://v3.football.api-sports.io/fixtures?date={$tomorrow}&league=37&season=2026",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 12,
    CURLOPT_HTTPHEADER     => ['x-apisports-key: ' . API_KEY, 'Accept: application/json'],
]);
$resp37 = json_decode(curl_exec($ch2), true);
curl_close($ch2);

// Test amichevoli oggi
$ch3 = curl_init();
curl_setopt_array($ch3, [
    CURLOPT_URL            => "https://v3.football.api-sports.io/fixtures?date={$today}&league=5&season=2026",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 12,
    CURLOPT_HTTPHEADER     => ['x-apisports-key: ' . API_KEY, 'Accept: application/json'],
]);
$resp5 = json_decode(curl_exec($ch3), true);
curl_close($ch3);

echo json_encode([
    'temp_dir'     => $tmpDir,
    'today'        => $today,
    'cache_files'  => $cacheFiles,
    'league_960_tomorrow' => [
        'count'   => count($resp960['response'] ?? []),
        'errors'  => $resp960['errors'] ?? null,
        'sample'  => array_map(fn($f) => ($f['teams']['home']['name'] ?? '?') . ' vs ' . ($f['teams']['away']['name'] ?? '?'), array_slice($resp960['response'] ?? [], 0, 5)),
    ],
    'league_37_tomorrow' => [
        'count'  => count($resp37['response'] ?? []),
        'errors' => $resp37['errors'] ?? null,
        'sample' => array_map(fn($f) => ($f['teams']['home']['name'] ?? '?') . ' vs ' . ($f['teams']['away']['name'] ?? '?'), array_slice($resp37['response'] ?? [], 0, 5)),
    ],
    'league_5_today' => [
        'count'  => count($resp5['response'] ?? []),
        'errors' => $resp5['errors'] ?? null,
        'sample' => array_map(fn($f) => ($f['teams']['home']['name'] ?? '?') . ' vs ' . ($f['teams']['away']['name'] ?? '?'), array_slice($resp5['response'] ?? [], 0, 5)),
    ],
], JSON_PRETTY_PRINT);