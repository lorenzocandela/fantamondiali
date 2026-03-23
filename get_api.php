<?php
header('Content-Type: application/json');

define('API_KEY',      '1a4942a032906326bcdaa564e10dbe65');
define('API_BASE_URL', 'https://v3.football.api-sports.io/');
define('MIN_PRICE', 5);
define('MAX_PRICE', 60);
define('MAX_PAGES', 15);

function fetchPage(string $season, int $page): array {
    $url = API_BASE_URL . "players?league=1&season={$season}&page={$page}";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_HTTPHEADER     => [
            'x-apisports-key: ' . API_KEY,
            'Accept: application/json',
        ],
    ]);

    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$response) {
        throw new RuntimeException("HTTP {$code} on page {$page}");
    }

    $data = json_decode($response, true);

    if (!empty($data['errors'])) {
        $errMsg = is_array($data['errors']) ? implode(', ', $data['errors']) : json_encode($data['errors']);
        throw new RuntimeException("API error: {$errMsg}");
    }

    return $data;
}

function fetchAllPages(string $season): array {
    $first = fetchPage($season, 1);

    if (empty($first['response'])) {
        throw new RuntimeException("Nessun dato per stagione {$season}");
    }

    $totalPages = min((int) ($first['paging']['total'] ?? 1), MAX_PAGES);
    $all        = $first['response'];

    for ($p = 2; $p <= $totalPages; $p++) {
        try {
            $page = fetchPage($season, $p);
            if (!empty($page['response'])) {
                $all = array_merge($all, $page['response']);
            }
        } catch (RuntimeException) {
            break;
        }
        usleep(200000);
    }

    return $all;
}

function normalize(array $item): array {
    $p = $item['player'];
    $s = $item['statistics'][0] ?? [];

    $roleMap = [
        'Goalkeeper' => 'POR',
        'Defender'   => 'DIF',
        'Midfielder' => 'CEN',
        'Attacker'   => 'ATT',
    ];

    $rawRole = $s['games']['position'] ?? 'Midfielder';
    $role    = $roleMap[$rawRole] ?? 'CEN';
    $rating  = (float) ($s['games']['rating'] ?? 6.5);
    $price   = (int) round(($rating - 5.5) / 4 * (MAX_PRICE - MIN_PRICE) + MIN_PRICE);
    $price   = max(MIN_PRICE, min(MAX_PRICE, $price));

    return [
        'id'          => $p['id'],
        'name'        => $p['name'],
        'firstname'   => $p['firstname'] ?? '',
        'lastname'    => $p['lastname']  ?? '',
        'photo'       => $p['photo']     ?? '',
        'nationality' => $p['nationality'] ?? '',
        'age'         => $p['age']       ?? null,
        'role'        => $role,
        'team'        => $s['team']['name'] ?? '',
        'team_logo'   => $s['team']['logo'] ?? '',
        'rating'      => $rating,
        'price'       => $price,
        'goals'       => $s['goals']['total']      ?? 0,
        'assists'     => $s['goals']['assists']     ?? 0,
        'appearances' => $s['games']['appearences'] ?? 0,
    ];
}

$cacheFile = sys_get_temp_dir() . '/fm_listone_v3.json';
$cacheTtl  = 86400;

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
    header('X-Cache: HIT');
    echo file_get_contents($cacheFile);
    exit;
}

$seasonUsed = '';

try {
    $raw = fetchAllPages('2026');
    $seasonUsed = '2026';
} catch (RuntimeException) {
    try {
        $raw = fetchAllPages('2022');
        $seasonUsed = '2022';
    } catch (RuntimeException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

$players = array_map('normalize', $raw);
usort($players, fn($a, $b) => $b['price'] <=> $a['price']);

$output = json_encode([
    'status' => 'success', 
    'total'  => count($players), 
    'source' => $seasonUsed,
    'data'   => $players
]);
file_put_contents($cacheFile, $output);

header('X-Cache: MISS');
echo $output;