<?php
header('Content-Type: application/json');

define('API_KEY',      '1a4942a032906326bcdaa564e10dbe65');
define('API_BASE_URL', 'https://v3.football.api-sports.io/');

// ─── MODALITÀ ────────────────────────────────────────────────────────────────
// ?mode=test  → prende le partite di OGGI (amichevoli, qualificazioni, ecc.)
// ?mode=prod  → prende le partite dei Mondiali 2026 (league=1, season=2026)
// In entrambi i casi restituisce le stats per giocatore delle fixture trovate.

$mode = $_GET['mode'] ?? 'test';

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
    if (!empty($data['errors']) || empty($data['response'])) return null;
    return $data['response'];
}

// ─── CACHE ───────────────────────────────────────────────────────────────────
$today     = date('Y-m-d');
$cacheFile = sys_get_temp_dir() . "/fm_live_{$mode}_{$today}.json";
$cacheTtl  = ($mode === 'test') ? 120 : 300; // test: 2min, prod: 5min

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
    header('X-Cache: HIT');
    echo file_get_contents($cacheFile);
    exit;
}

header('X-Cache: MISS');

// ─── 1. TROVA LE FIXTURE ────────────────────────────────────────────────────

$fixtures = [];
$fixturesMeta = [];

if ($mode === 'test') {
    // Partite di oggi — tutte le leghe (amichevoli internazionali = league 5)
    // Cerchiamo prima amichevoli, poi qualificazioni mondiali, poi qualsiasi
    $candidates = [
        "fixtures?date={$today}&league=5&season=2025",   // amichevoli internazionali 2025
        "fixtures?date={$today}&league=5&season=2026",   // amichevoli internazionali 2026
        "fixtures?date={$today}&league=32&season=2025",  // qualificazioni mondiali
        "fixtures?date={$today}",                         // fallback: tutte le partite di oggi
    ];

    foreach ($candidates as $endpoint) {
        $result = apiGet($endpoint);
        if (!empty($result)) {
            $fixtures = $result;
            break;
        }
        usleep(200000);
    }
} else {
    // Produzione: Mondiali 2026
    $round = $_GET['round'] ?? null;
    $roundLabels = [
        1 => 'Group Stage - 1',
        2 => 'Group Stage - 2',
        3 => 'Group Stage - 3',
        4 => 'Round of 16',
        5 => 'Quarter-finals',
        6 => 'Semi-finals',
        7 => 'Final',
    ];

    if ($round && isset($roundLabels[(int)$round])) {
        $label = $roundLabels[(int)$round];
        $fixtures = apiGet("fixtures?league=1&season=2026&round=" . urlencode($label)) ?? [];
    } else {
        // tutte le fixture live dei mondiali
        $fixtures = apiGet("fixtures?league=1&season=2026&live=all") ?? [];
        if (empty($fixtures)) {
            // fallback: fixture di oggi
            $fixtures = apiGet("fixtures?league=1&season=2026&date={$today}") ?? [];
        }
    }
}

// ─── 2. ESTRAI METADATA FIXTURE ─────────────────────────────────────────────

foreach ($fixtures as $f) {
    $fid    = $f['fixture']['id'] ?? null;
    $status = $f['fixture']['status']['short'] ?? 'NS'; // NS, 1H, HT, 2H, FT, etc.
    $minute = $f['fixture']['status']['elapsed'] ?? null;
    if (!$fid) continue;

    $fixturesMeta[] = [
        'id'        => $fid,
        'status'    => $status,
        'minute'    => $minute,
        'home_team' => $f['teams']['home']['name'] ?? '',
        'away_team' => $f['teams']['away']['name'] ?? '',
        'home_logo' => $f['teams']['home']['logo'] ?? '',
        'away_logo' => $f['teams']['away']['logo'] ?? '',
        'home_goals'=> $f['goals']['home'] ?? 0,
        'away_goals'=> $f['goals']['away'] ?? 0,
        'date'      => $f['fixture']['date'] ?? '',
        'venue'     => $f['fixture']['venue']['name'] ?? '',
    ];
}

// ─── 3. ESTRAI STATS GIOCATORI ──────────────────────────────────────────────

$playerStats = [];
$liveStatuses = ['1H','HT','2H','ET','P','BT','LIVE','FT','AET','PEN'];

foreach ($fixturesMeta as $fm) {
    // Prendi stats solo per partite in corso o concluse
    if (!in_array($fm['status'], $liveStatuses)) continue;

    $players = apiGet("fixtures/players?fixture={$fm['id']}");
    if (empty($players)) continue;

    foreach ($players as $teamData) {
        foreach ($teamData['players'] ?? [] as $entry) {
            $pid   = $entry['player']['id']   ?? null;
            $pname = $entry['player']['name']  ?? '';
            $photo = $entry['player']['photo'] ?? '';
            $stats = $entry['statistics'][0]   ?? [];
            if (!$pid) continue;

            $rating  = (float) ($stats['games']['rating']      ?? 0);
            $goals   = (int)   ($stats['goals']['total']       ?? 0);
            $assists = (int)   ($stats['goals']['assists']     ?? 0);
            $yellow  = (int)   ($stats['cards']['yellow']      ?? 0);
            $red     = (int)   ($stats['cards']['red']         ?? 0);
            $cs      = ($stats['goals']['conceded'] ?? 1) === 0;
            $played  = (bool)  ($stats['games']['minutes']     ?? 0);
            $minutes = (int)   ($stats['games']['minutes']     ?? 0);
            $position= $stats['games']['position']             ?? '';

            $playerStats[(string)$pid] = [
                'name'       => $pname,
                'photo'      => $photo,
                'rating'     => $rating,
                'goals'      => $goals,
                'assists'    => $assists,
                'yellow'     => $yellow,
                'red'        => $red,
                'cs'         => $cs,
                'played'     => $played,
                'minutes'    => $minutes,
                'position'   => $position,
                'fixture_id' => $fm['id'],
            ];
        }
    }
    usleep(150000);
}

// ─── 4. OUTPUT ──────────────────────────────────────────────────────────────

$output = json_encode([
    'status'   => 'success',
    'mode'     => $mode,
    'date'     => $today,
    'fixtures' => $fixturesMeta,
    'players'  => $playerStats,
    'count'    => [
        'fixtures' => count($fixturesMeta),
        'players'  => count($playerStats),
    ],
]);

file_put_contents($cacheFile, $output);
echo $output;