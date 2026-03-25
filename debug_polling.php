<?php
header('Content-Type: application/json');

define('API_KEY',      '1a4942a032906326bcdaa564e10dbe65');
define('API_BASE_URL', 'https://v3.football.api-sports.io/');

$today = date('Y-m-d');

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
    return ['code' => $code, 'raw' => json_decode($resp, true)];
}

// 1. Cerca fixture FIFA Series oggi
$step1 = apiGet("fixtures?date={$today}&league=1222&season=2026");

// 2. Cerca fixture live adesso (FIFA Series)
$step2 = apiGet("fixtures?live=all&league=1222");

// 3. Cerca TUTTE le fixture live adesso
$step3 = apiGet("fixtures?live=all");
$liveFixtures = [];
if (!empty($step3['raw']['response'])) {
    foreach ($step3['raw']['response'] as $f) {
        $liveFixtures[] = [
            'id'        => $f['fixture']['id'] ?? null,
            'status'    => $f['fixture']['status']['short'] ?? '?',
            'minute'    => $f['fixture']['status']['elapsed'] ?? null,
            'home'      => $f['teams']['home']['name'] ?? '?',
            'away'      => $f['teams']['away']['name'] ?? '?',
            'league'    => $f['league']['name'] ?? '?',
            'league_id' => $f['league']['id'] ?? null,
            'score'     => ($f['goals']['home'] ?? 0) . '-' . ($f['goals']['away'] ?? 0),
        ];
    }
}

// Filtra per Kazakhstan/Namibia
$kazNam = array_filter($liveFixtures, fn($f) => 
    stripos($f['home'], 'kazakh') !== false || stripos($f['away'], 'kazakh') !== false ||
    stripos($f['home'], 'namibia') !== false || stripos($f['away'], 'namibia') !== false
);

// 4. Se troviamo il fixture ID, prendi le stats giocatori
$playerStats = [];
$fixtureId = null;

// Cerca anche dalle fixture di oggi
$todayFixtures = $step1['raw']['response'] ?? [];
foreach ($todayFixtures as $f) {
    $home = $f['teams']['home']['name'] ?? '';
    $away = $f['teams']['away']['name'] ?? '';
    if (stripos($home, 'kazakh') !== false || stripos($away, 'kazakh') !== false) {
        $fixtureId = $f['fixture']['id'];
        break;
    }
}

// Se non trovato da oggi, cerca dai live
if (!$fixtureId && !empty($kazNam)) {
    $first = array_values($kazNam)[0];
    $fixtureId = $first['id'];
}

$step4 = null;
if ($fixtureId) {
    $step4 = apiGet("fixtures/players?fixture={$fixtureId}");
    if (!empty($step4['raw']['response'])) {
        foreach ($step4['raw']['response'] as $teamData) {
            $teamName = $teamData['team']['name'] ?? '?';
            foreach ($teamData['players'] ?? [] as $entry) {
                $pid   = $entry['player']['id'] ?? null;
                $pname = $entry['player']['name'] ?? '';
                $stats = $entry['statistics'][0] ?? [];
                $playerStats[] = [
                    'team'     => $teamName,
                    'id'       => $pid,
                    'name'     => $pname,
                    'rating'   => $stats['games']['rating'] ?? null,
                    'minutes'  => $stats['games']['minutes'] ?? null,
                    'position' => $stats['games']['position'] ?? null,
                    'goals'    => $stats['goals']['total'] ?? 0,
                    'assists'  => $stats['goals']['assists'] ?? 0,
                    'yellow'   => $stats['cards']['yellow'] ?? 0,
                    'red'      => $stats['cards']['red'] ?? 0,
                    'conceded' => $stats['goals']['conceded'] ?? null,
                ];
            }
        }
    }
}

echo json_encode([
    'timestamp'         => date('Y-m-d H:i:s'),
    'today'             => $today,
    'fixture_id_found'  => $fixtureId,
    
    'step1_fixtures_today_1222' => [
        'http_code' => $step1['code'],
        'count'     => count($step1['raw']['response'] ?? []),
        'errors'    => $step1['raw']['errors'] ?? [],
        'fixtures'  => array_map(fn($f) => [
            'id'     => $f['fixture']['id'] ?? null,
            'status' => $f['fixture']['status']['short'] ?? '?',
            'minute' => $f['fixture']['status']['elapsed'] ?? null,
            'home'   => $f['teams']['home']['name'] ?? '?',
            'away'   => $f['teams']['away']['name'] ?? '?',
            'score'  => ($f['goals']['home'] ?? 0) . '-' . ($f['goals']['away'] ?? 0),
        ], $step1['raw']['response'] ?? []),
    ],
    
    'step2_live_1222' => [
        'http_code' => $step2['code'],
        'count'     => count($step2['raw']['response'] ?? []),
        'errors'    => $step2['raw']['errors'] ?? [],
    ],
    
    'step3_all_live' => [
        'total'   => count($liveFixtures),
        'kaz_nam' => array_values($kazNam),
    ],
    
    'step4_player_stats' => [
        'fixture_id'    => $fixtureId,
        'players_count' => count($playerStats),
        'players'       => array_slice($playerStats, 0, 30),
        'api_errors'    => $step4['raw']['errors'] ?? null,
    ],
], JSON_PRETTY_PRINT);