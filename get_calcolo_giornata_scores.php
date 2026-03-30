<?php
header('Content-Type: application/json');

// CONFIG
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

// INPUT
$from  = $_GET['from']  ?? date('Y-m-d');
$to    = $_GET['to']    ?? date('Y-m-d');
$force = isset($_GET['force']) ? (int)$_GET['force'] : 1;

// leggi fixture nel range date dal DB
$stmt = $pdo->prepare("
    SELECT DISTINCT fixture_id 
    FROM player_match_stats 
    WHERE match_date BETWEEN ? AND ?
    AND status IN ('FT', 'AET', 'PEN')
");
$stmt->execute([$from, $to]);
$fixtureIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($fixtureIds)) {
    echo json_encode(['status' => 'error', 'message' => "Nessuna partita trovata nel DB per il periodo $from al $to."]);
    exit;
}

// leggi stats dal DB
$inQuery = implode(',', array_fill(0, count($fixtureIds), '?'));
$stmt = $pdo->prepare("SELECT * FROM player_match_stats WHERE fixture_id IN ($inQuery)");
$stmt->execute($fixtureIds);
$dbStats = $stmt->fetchAll();

$playerStats = [];

foreach ($dbStats as $row) {
    $pid = $row['player_id'];
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

// RETURN
echo json_encode([
    'status'  => 'success',
    'source'  => 'real',
    'players' => $playerStats
]);