<?php
header('Content-Type: application/json');
define('API_KEY', '1a4942a032906326bcdaa564e10dbe65');

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

$fixtureId = 1537243; // Kazakhstan vs Namibia

// 1. Events (gol, cartellini, sost)
$events = apiGet("https://v3.football.api-sports.io/fixtures/events?fixture={$fixtureId}");

// 2. Lineups
$lineups = apiGet("https://v3.football.api-sports.io/fixtures/lineups?fixture={$fixtureId}");

// 3. Players stats (sappiamo che è vuoto ma confermiamo)
$players = apiGet("https://v3.football.api-sports.io/fixtures/players?fixture={$fixtureId}");

// 4. Fixture statistics (team level)
$stats = apiGet("https://v3.football.api-sports.io/fixtures/statistics?fixture={$fixtureId}");

echo json_encode([
    'fixture_id' => $fixtureId,
    'events' => [
        'count' => count($events['response'] ?? []),
        'data'  => $events['response'] ?? [],
        'errors' => $events['errors'] ?? [],
    ],
    'lineups' => [
        'count' => count($lineups['response'] ?? []),
        'has_data' => !empty($lineups['response']),
        'sample' => array_map(fn($t) => [
            'team' => $t['team']['name'] ?? '?',
            'formation' => $t['formation'] ?? '?',
            'players_count' => count($t['startXI'] ?? []),
        ], $lineups['response'] ?? []),
        'errors' => $lineups['errors'] ?? [],
    ],
    'players_stats' => [
        'count' => count($players['response'] ?? []),
        'errors' => $players['errors'] ?? [],
    ],
    'team_stats' => [
        'count' => count($stats['response'] ?? []),
        'errors' => $stats['errors'] ?? [],
    ],
], JSON_PRETTY_PRINT);