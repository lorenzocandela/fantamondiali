<?php
header('Content-Type: application/json');

define('API_KEY',      '1a4942a032906326bcdaa564e10dbe65');
define('API_BASE_URL', 'https://v3.football.api-sports.io/');
define('MIN_PRICE', 5);
define('MAX_PRICE', 60);

// ─── CONFIGURAZIONE TORNEO ──────────────────────────────────────────────────
$LEAGUE_ID = isset($_GET['league']) ? (int)$_GET['league'] : 32; // 32 = Playoff UEFA, 1 = World Cup
$SEASON    = isset($_GET['season']) ? (int)$_GET['season'] : 2024;

// ─── CACHE PERMANENTE ───────────────────────────────────────────────────────
$cacheFile = sys_get_temp_dir() . "/fm_listone_L{$LEAGUE_ID}_S{$SEASON}.json";
$forceReset = isset($_GET['reset']) && $_GET['reset'] === '1';

if (!$forceReset && file_exists($cacheFile) && filesize($cacheFile) > 0) {
    header('X-Cache: HIT-PERMANENT');
    echo file_get_contents($cacheFile);
    exit;
}
header('X-Cache: MISS');

// ─── FUNZIONE API HELPER ────────────────────────────────────────────────────
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

// ─── 1. TROVA LE SQUADRE QUALIFICATE ────────────────────────────────────────
$teamsResponse = apiGet("teams?league={$LEAGUE_ID}&season={$SEASON}");

if (empty($teamsResponse)) {
    echo json_encode(['status' => 'error', 'message' => "Nessuna squadra trovata per League {$LEAGUE_ID} Season {$SEASON}"]);
    exit;
}

// ─── 2. SCARICA LE ROSE (SQUADS) ────────────────────────────────────────────
$allPlayers = [];
$roleMap = [
    'Goalkeeper' => 'POR',
    'Defender'   => 'DIF',
    'Midfielder' => 'CEN',
    'Attacker'   => 'ATT',
];

foreach ($teamsResponse as $t) {
    $teamId   = $t['team']['id'];
    $teamName = $t['team']['name'];
    
    $squad = apiGet("players/squads?team={$teamId}");
    
    if (!empty($squad) && !empty($squad[0]['players'])) {
        foreach ($squad[0]['players'] as $p) {
            $role  = $roleMap[$p['position'] ?? 'Midfielder'] ?? 'CEN';
            // L'endpoint squads non restituisce il rating, quindi generiamo un prezzo
            // randomico o di base. Potrai affinarlo in seguito se serve.
            $price = mt_rand(MIN_PRICE, MAX_PRICE); 

            $allPlayers[] = [
                'id'          => $p['id'],
                'name'        => $p['name'] ?? '',
                'firstname'   => '',
                'lastname'    => '',
                'photo'       => $p['photo'] ?? '',
                'nationality' => $teamName, // Usiamo il nome della nazionale come nazionalità
                'age'         => $p['age'] ?? null,
                'role'        => $role,
                'team'        => $teamName,
                'team_logo'   => $t['team']['logo'] ?? '',
                'rating'      => 6.5,
                'price'       => $price,
                'goals'       => 0,
                'assists'     => 0,
                'appearances' => 0,
            ];
        }
    }
    // Pausa di 200ms per non superare il rate limit dell'API (circa 5 req/sec)
    usleep(200000); 
}

// ─── 3. RIMUOVI DUPLICATI E ORDINA ──────────────────────────────────────────
$seen = [];
$unique = [];
foreach ($allPlayers as $p) {
    if (!isset($seen[$p['id']])) {
        $seen[$p['id']] = true;
        $unique[] = $p;
    }
}

usort($unique, fn($a, $b) => $b['price'] <=> $a['price']);

// ─── 4. SALVA E RESTITUISCI ─────────────────────────────────────────────────
$output = json_encode([
    'status' => 'success', 
    'total'  => count($unique), 
    'source' => "League {$LEAGUE_ID} - Squads Only",
    'data'   => $unique
]);

file_put_contents($cacheFile, $output);
echo $output;