<?php
header('Content-Type: application/json');

define('API_KEY',      '1a4942a032906326bcdaa564e10dbe65');
define('API_BASE_URL', 'https://v3.football.api-sports.io/');
define('LEAGUE_ID',    1);
define('SEASON',       2026);

$roundLabels = [
    1 => 'Group Stage - 1',
    2 => 'Group Stage - 2',
    3 => 'Group Stage - 3',
    4 => 'Round of 16',
    5 => 'Quarter-finals',
    6 => 'Semi-finals',
    7 => 'Final',
];

$round = isset($_GET['round']) ? (int) $_GET['round'] : 1;

if ($round < 1 || $round > 7) {
    echo json_encode(['status' => 'error', 'message' => 'round non valido']);
    exit;
}

$cacheFile = sys_get_temp_dir() . "/fm_scores_r{$round}.json";
$cacheTtl  = 3600;

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
    header('X-Cache: HIT');
    echo file_get_contents($cacheFile);
    exit;
}

header('X-Cache: MISS');

function apiGet(string $endpoint): ?array {
    $url = API_BASE_URL . $endpoint;
    $ch  = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 12,
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

$label    = $roundLabels[$round] ?? 'Group Stage - 1';
$fixtures = apiGet("fixtures?league=" . LEAGUE_ID . "&season=" . SEASON . "&round=" . urlencode($label));

$playerRatings = [];
$source        = 'real';

if (!empty($fixtures)) {
    foreach ($fixtures as $fixture) {
        $fixtureId = $fixture['fixture']['id'] ?? null;
        if (!$fixtureId) continue;

        $players = apiGet("fixtures/players?fixture={$fixtureId}");
        if (empty($players)) continue;

        foreach ($players as $teamData) {
            foreach ($teamData['players'] ?? [] as $entry) {
                $pid   = $entry['player']['id']    ?? null;
                $stats = $entry['statistics'][0]   ?? [];
                if (!$pid) continue;

                $rating = (float) ($stats['games']['rating']         ?? 0);
                $goals  = (int)   ($stats['goals']['total']          ?? 0);
                $assists= (int)   ($stats['goals']['assists']        ?? 0);
                $yellow = (int)   ($stats['cards']['yellow']         ?? 0);
                $red    = (int)   ($stats['cards']['red']            ?? 0);
                $cs     = ($stats['goals']['conceded'] ?? 1) === 0;
                $played = (bool)  ($stats['games']['minutes']        ?? 0);

                $playerRatings[(string)$pid] = [
                    'rating'  => $rating,
                    'goals'   => $goals,
                    'assists' => $assists,
                    'yellow'  => $yellow,
                    'red'     => $red,
                    'cs'      => $cs,
                    'played'  => $played,
                ];
            }
        }

        usleep(150000);
    }
}

if (empty($playerRatings)) {
    $source = 'simulated';
    $listonePath = sys_get_temp_dir() . '/fm_listone_v3.json';
    if (file_exists($listonePath)) {
        $listone = json_decode(file_get_contents($listonePath), true);
        foreach ($listone['data'] ?? [] as $p) {
            $baseRating = (float) ($p['rating'] ?? 6.5);
            $simRating  = round(min(10, max(4, $baseRating + (mt_rand(-150, 150) / 100))), 2);
            $goals      = mt_rand(0, 100) < 8 ? mt_rand(1, 2) : 0;    // 8% chance gol
            $assists    = mt_rand(0, 100) < 6 ? 1 : 0;                  // 6% assist
            $yellow     = mt_rand(0, 100) < 10 ? 1 : 0;                 // 10% giallo
            $red        = mt_rand(0, 100) < 2  ? 1 : 0;                 // 2% rosso
            $cs         = mt_rand(0, 100) < 30;                          // 30% clean sheet
            $played     = mt_rand(0, 100) < 85;                          // 85% giocato

            $playerRatings[(string)$p['id']] = [
                'rating'  => $simRating,
                'goals'   => $goals,
                'assists' => $assists,
                'yellow'  => $yellow,
                'red'     => $red,
                'cs'      => $cs,
                'played'  => $played,
            ];
        }
    }
}

$output = json_encode([
    'status'  => 'success',
    'round'   => $round,
    'source'  => $source,
    'players' => $playerRatings,
]);

file_put_contents($cacheFile, $output);
echo $output;