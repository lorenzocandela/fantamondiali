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
$force  = isset($_GET['force']) ? (int)$_GET['force'] : 0; // Per bypassare il blocco partite in corso

// ─── 1. VERIFICA STATO PARTITE NEL RANGE ────────────────────────────────────
$endpoint = "fixtures?league={$league}&season={$season}&from={$from}&to={$to}";
$fixtures = apiGet($endpoint) ?? [];

if (empty($fixtures)) {
    echo json_encode(['status' => 'error', 'message' => "Nessuna partita reale trovata nel periodo $from - $to (Lega $league)."]);
    exit;
}

$fixtureIds = [];
$finishedStatuses = ['FT', 'AET', 'PEN'];
$allFinished = true;
$unfinishedCount = 0;

foreach ($fixtures as $f) {
    $fixtureIds[] = $f['fixture']['id'];
    if (!in_array($f['fixture']['status']['short'], $finishedStatuses)) {
        $allFinished = false;
        $unfinishedCount++;
    }
}

// Se ci sono partite in corso e non abbiamo forzato, blocchiamo il calcolo
if (!$allFinished && !$force) {
    echo json_encode([
        'status' => 'error',
        'message' => "Ci sono ancora $unfinishedCount partite non terminate in questa giornata. Attendi la fine o usa il force."
    ]);
    exit;
}

// ─── 2. RECUPERA DATI DA MARIADB ────────────────────────────────────────────
$inQuery = implode(',', array_fill(0, count($fixtureIds), '?'));
$stmt = $pdo->prepare("SELECT * FROM player_match_stats WHERE fixture_id IN ($inQuery)");
$stmt->execute($fixtureIds);
$dbStats = $stmt->fetchAll();

// ─── 3. CALCOLA FANTAVOTI (TABELLA CALENDAR.JS) ─────────────────────────────
$bonusTable = [
    'goal'        => ['POR' => 5, 'DIF' => 3, 'CEN' => 3, 'ATT' => 3],
    'assist'      => 1,
    'yellow'      => -0.5,
    'red'         => -2,
    'clean_sheet' => ['POR' => 1]
];

$playersScores = [];

foreach ($dbStats as $row) {
    $pid    = $row['player_id'];
    $role   = $row['role'];
    $rating = (float)$row['rating'];
    
    // Controlla se ha preso bonus/malus anche senza prendere voto
    $hasBonus = ($row['goals'] > 0 || $row['assists'] > 0 || $row['yellow_cards'] > 0 || $row['red_cards'] > 0 || ($row['clean_sheet'] == 1 && $role == 'POR'));

    // Se non ha giocato/preso voto e non ha fatto nulla di rilevante -> SV
    if ($rating == 0 && !$hasBonus) {
        continue; 
    }

    // Se ha preso bonus ma non ha voto base, gli diamo un 6 d'ufficio per poter calcolare
    $baseRating = ($rating == 0 && $hasBonus) ? 6.0 : $rating;
    
    $score = $baseRating;
    $score += $row['goals'] * ($bonusTable['goal'][$role] ?? 3);
    $score += $row['assists'] * $bonusTable['assist'];
    $score += $row['yellow_cards'] * $bonusTable['yellow'];
    $score += $row['red_cards'] * $bonusTable['red'];
    
    if ($row['clean_sheet'] == 1 && isset($bonusTable['clean_sheet'][$role])) {
        $score += $bonusTable['clean_sheet'][$role];
    }

    $playersScores[(string)$pid] = [
        'name'   => $row['player_name'],
        'role'   => $role,
        'rating' => $baseRating,
        'score'  => round($score, 2),
        'stats'  => [
            'goals'   => $row['goals'],
            'assists' => $row['assists'],
            'yellow'  => $row['yellow_cards'],
            'red'     => $row['red_cards'],
            'cs'      => $row['clean_sheet'] == 1
        ]
    ];
}

// ─── 4. OUTPUT JSON ─────────────────────────────────────────────────────────
echo json_encode([
    'status' => 'success',
    'round_dates' => "$from al $to",
    'fixtures_processed' => count($fixtureIds),
    'players' => $playersScores
]);