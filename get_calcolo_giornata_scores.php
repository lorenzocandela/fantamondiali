<?php
header('Content-Type: application/json');

// ─── CONFIGURAZIONE DATABASE ────────────────────────────────────────────────
$db_host = '127.0.0.1';
$db_name = 'fm';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Errore DB: ' . $e->getMessage()]);
    exit;
}

// ─── CONFIGURAZIONE API ─────────────────────────────────────────────────────
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
    if (!empty($data['errors']) || empty($data['response'])) return null;
    return $data['response'];
}

// ─── INPUT DAL FRONTEND ─────────────────────────────────────────────────────
$from   = $_GET['from'] ?? date('Y-m-d');
$to     = $_GET['to'] ?? date('Y-m-d');
$league = $_GET['league'] ?? 32;   // 32 = Test (Qualifiers), 1 = Mondiali
$season = $_GET['season'] ?? 2024; // 2024 = Test, 2026 = Mondiali
$force  = isset($_GET['force']) ? (int)$_GET['force'] : 1; // Messo a 1 di default per i tuoi test

// ─── 1. VERIFICA FIXTURES NEL RANGE ─────────────────────────────────────────
$endpoint = "fixtures?league={$league}&season={$season}&from={$from}&to={$to}";
$fixtures = apiGet($endpoint) ?? [];

if (empty($fixtures)) {
    echo json_encode(['status' => 'error', 'message' => "Nessuna partita reale trovata nel periodo $from al $to."]);
    exit;
}

$fixtureIds = [];
$finishedStatuses = ['FT', 'AET', 'PEN'];
$allFinished = true;

foreach ($fixtures as $f) {
    $fixtureIds[] = $f['fixture']['id'];
    if (!in_array($f['fixture']['status']['short'], $finishedStatuses)) {
        $allFinished = false;
    }
}

if (!$allFinished && !$force) {
    echo json_encode(['status' => 'error', 'message' => "Ci sono ancora partite non terminate in questa giornata. Attendi o usa force=1."]);
    exit;
}

// ─── 2. RECUPERA DATI DA MARIADB ────────────────────────────────────────────
$inQuery = implode(',', array_fill(0, count($fixtureIds), '?'));
$stmt = $pdo->prepare("SELECT * FROM player_match_stats WHERE fixture_id IN ($inQuery)");
$stmt->execute($fixtureIds);
$dbStats = $stmt->fetchAll();

$playerStats = [];

foreach ($dbStats as $row) {
    $pid = $row['player_id'];
    // Salviamo le stats raw, i punti li calcolerà il JS di Admin
    $playerStats[(string)$pid] = [
        'name'           => $row['player_name'],
        'role'           => $row['role'],
        'rating'         => (float)$row['rating'],
        'goals'          => (int)$row['goals'],
        'assists'        => (int)$row['assists'],
        'yellow_cards'   => (int)$row['yellow_cards'],
        'red_cards'      => (int)$row['red_cards'],
        'clean_sheet'    => (bool)$row['clean_sheet'],
        'minutes_played' => (int)$row['minutes_played'],
        'played'         => ((int)$row['minutes_played'] > 0),
        'fixture_id'     => $row['fixture_id']
    ];
}

// ─── 3. OUTPUT JSON ─────────────────────────────────────────────────────────
echo json_encode([
    'status'  => 'success',
    'source'  => 'real',
    'players' => $playerStats
]);