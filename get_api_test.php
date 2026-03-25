<?php
header('Content-Type: application/json');

define('API_KEY',      '1a4942a032906326bcdaa564e10dbe65');
define('API_BASE_URL', 'https://v3.football.api-sports.io/');
define('MIN_PRICE', 5);
define('MAX_PRICE', 60);

$today = date('Y-m-d');

// ─── CACHE (breve per test: 10 minuti) ──────────────────────────────────────
$cacheFile = sys_get_temp_dir() . "/fm_listone_test_{$today}.json";
$cacheTtl  = 600;

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
    return $data['response'] ?? null;
}

// ─── 1. TROVA LE FIXTURE DI OGGI ────────────────────────────────────────────

$fixtures = [];
$leagueUsed = '';

// Prova in ordine: FIFA Series, playoff UEFA, intercontinental playoffs, amichevoli
$attempts = [
    ['endpoint' => "fixtures?date={$today}&league=1222&season=2026", 'label' => 'FIFA Series 2026'],
    ['endpoint' => "fixtures?date={$today}&league=960&season=2026",  'label' => 'UEFA Playoff WC'],
    ['endpoint' => "fixtures?date={$today}&league=37&season=2026",   'label' => 'Intercontinental Playoff'],
    ['endpoint' => "fixtures?date={$today}&league=5&season=2026",    'label' => 'Amichevoli 2026'],
    ['endpoint' => "fixtures?date={$today}&league=5&season=2025",    'label' => 'Amichevoli 2025'],
];

// Accumula fixture da più leghe (playoff UEFA + intercontinental)
$fixtures = [];
$leagueLabels = [];
foreach ($attempts as $a) {
    $result = apiGet($a['endpoint']);
    if (!empty($result)) {
        $fixtures = array_merge($fixtures, $result);
        $leagueLabels[] = $a['label'];
    }
    usleep(200000);
    // Se abbiamo già fixture dai playoff, non cercare le amichevoli
    if (count($fixtures) >= 4 && count($leagueLabels) >= 2) break;
}
$leagueUsed = implode(' + ', array_unique($leagueLabels));

if (empty($fixtures)) {
    echo json_encode(['status' => 'error', 'message' => "Nessuna partita trovata per oggi ({$today})"]);
    exit;
}

// Filtra: se c'è un parametro ?teams=, prendi solo quelle squadre
$filterTeams = isset($_GET['teams']) ? array_map('strtolower', explode(',', $_GET['teams'])) : [];
if (!empty($filterTeams)) {
    $fixtures = array_filter($fixtures, function($f) use ($filterTeams) {
        $home = strtolower($f['teams']['home']['name'] ?? '');
        $away = strtolower($f['teams']['away']['name'] ?? '');
        foreach ($filterTeams as $t) {
            $t = trim($t);
            if (stripos($home, $t) !== false || stripos($away, $t) !== false) return true;
        }
        return false;
    });
}
// Massimo 10 fixture
$fixtures = array_values(array_slice($fixtures, 0, 10));

// ─── 2. INFO FIXTURE PER DEBUG ──────────────────────────────────────────────

$fixtureInfo = [];
foreach ($fixtures as $f) {
    $fixtureInfo[] = [
        'id'     => $f['fixture']['id'] ?? null,
        'home'   => $f['teams']['home']['name'] ?? '?',
        'away'   => $f['teams']['away']['name'] ?? '?',
        'status' => $f['fixture']['status']['short'] ?? 'NS',
        'date'   => $f['fixture']['date'] ?? '',
    ];
}

// ─── 3. PRENDI I ROSTER DELLE SQUADRE ───────────────────────────────────────

$teamIds = [];
$teamCountry = []; // team_id → country name (per nazionali = nome squadra)
foreach ($fixtures as $f) {
    $homeId = $f['teams']['home']['id'] ?? null;
    $awayId = $f['teams']['away']['id'] ?? null;
    if ($homeId) { $teamIds[$homeId] = $f['teams']['home']['name'] ?? ''; $teamCountry[$homeId] = $f['teams']['home']['name'] ?? ''; }
    if ($awayId) { $teamIds[$awayId] = $f['teams']['away']['name'] ?? ''; $teamCountry[$awayId] = $f['teams']['away']['name'] ?? ''; }
}

// Per ogni squadra, prendi i giocatori dal roster
$allPlayers = [];
$season = (int) date('Y'); // stagione corrente

foreach ($teamIds as $teamId => $teamName) {
    $countryName = $teamCountry[$teamId] ?? $teamName;
    
    // Prova prima con la stagione corrente, poi con l'anno precedente
    $squad = apiGet("players/squads?team={$teamId}");
    
    if (!empty($squad) && !empty($squad[0]['players'])) {
        foreach ($squad[0]['players'] as $p) {
            $roleMap = [
                'Goalkeeper' => 'POR',
                'Defender'   => 'DIF',
                'Midfielder' => 'CEN',
                'Attacker'   => 'ATT',
            ];

            $role  = $roleMap[$p['position'] ?? 'Midfielder'] ?? 'CEN';
            $price = mt_rand(MIN_PRICE, MAX_PRICE);

            $allPlayers[] = [
                'id'          => $p['id'],
                'name'        => $p['name'] ?? '',
                'firstname'   => '',
                'lastname'    => '',
                'photo'       => $p['photo'] ?? '',
                'nationality' => $countryName, // per nazionali = nome paese
                'age'         => $p['age'] ?? null,
                'role'        => $role,
                'team'        => $countryName,
                'team_logo'   => '',
                'rating'      => 6.5,
                'price'       => $price,
                'goals'       => 0,
                'assists'     => 0,
                'appearances' => 0,
            ];
        }
    }
    usleep(200000);
}

// Rimuovi duplicati per id
$seen = [];
$unique = [];
foreach ($allPlayers as $p) {
    if (!isset($seen[$p['id']])) {
        $seen[$p['id']] = true;
        $unique[] = $p;
    }
}

usort($unique, fn($a, $b) => $b['price'] <=> $a['price']);

$output = json_encode([
    'status'   => 'success',
    'total'    => count($unique),
    'source'   => "test · {$leagueUsed}",
    'date'     => $today,
    'fixtures' => $fixtureInfo,
    'data'     => $unique,
]);

file_put_contents($cacheFile, $output);
echo $output;