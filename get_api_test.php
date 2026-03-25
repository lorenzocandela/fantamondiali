<?php
header('Content-Type: application/json');
define('API_KEY', '1a4942a032906326bcdaa564e10dbe65');

$tomorrow = '2026-03-26';

function apiGet($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_HTTPHEADER     => ['x-apisports-key: ' . API_KEY, 'Accept: application/json'],
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    return json_decode($resp, true);
}

// 1. Cerca tutte le fixture di domani per l'Italia (team ID = 768)
$italy = apiGet("https://v3.football.api-sports.io/fixtures?date={$tomorrow}&team=768");

// 2. Cerca tutte le fixture di domani per la Polonia (team ID = 24)
$poland = apiGet("https://v3.football.api-sports.io/fixtures?date={$tomorrow}&team=24");

// 3. Cerca TUTTE le fixture di domani (senza filtro league) — prime 20
$all = apiGet("https://v3.football.api-sports.io/fixtures?date={$tomorrow}");
$allFixtures = array_slice($all['response'] ?? [], 0, 30);
$allSummary = array_map(fn($f) => [
    'league_id'   => $f['league']['id'] ?? null,
    'league_name' => $f['league']['name'] ?? '',
    'home'        => $f['teams']['home']['name'] ?? '?',
    'away'        => $f['teams']['away']['name'] ?? '?',
    'date'        => $f['fixture']['date'] ?? '',
], $allFixtures);

// Filtra solo le nazionali/qualificazioni dalla lista completa
$nationals = array_filter($allSummary, fn($f) => 
    stripos($f['league_name'], 'world') !== false ||
    stripos($f['league_name'], 'qualif') !== false ||
    stripos($f['league_name'], 'playoff') !== false ||
    stripos($f['league_name'], 'play-off') !== false ||
    stripos($f['league_name'], 'friendly') !== false ||
    stripos($f['league_name'], 'UEFA') !== false ||
    stripos($f['league_name'], 'FIFA') !== false
);

echo json_encode([
    'italy_tomorrow' => [
        'count'   => count($italy['response'] ?? []),
        'matches' => array_map(fn($f) => [
            'league_id'   => $f['league']['id'] ?? null,
            'league_name' => $f['league']['name'] ?? '',
            'home'        => $f['teams']['home']['name'] ?? '?',
            'away'        => $f['teams']['away']['name'] ?? '?',
        ], $italy['response'] ?? []),
    ],
    'poland_tomorrow' => [
        'count'   => count($poland['response'] ?? []),
        'matches' => array_map(fn($f) => [
            'league_id'   => $f['league']['id'] ?? null,
            'league_name' => $f['league']['name'] ?? '',
            'home'        => $f['teams']['home']['name'] ?? '?',
            'away'        => $f['teams']['away']['name'] ?? '?',
        ], $poland['response'] ?? []),
    ],
    'all_nationals' => array_values($nationals),
    'total_fixtures_tomorrow' => count($all['response'] ?? []),
], JSON_PRETTY_PRINT);