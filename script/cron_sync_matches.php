<?php
// CONFIG
$log_dir = __DIR__ . '/logs'; 
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}
$log_file = $log_dir . '/sync_stats.log';

function writeLog($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
    echo "[$timestamp] $message\n";
}

writeLog("start sync...");

// DB
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
    writeLog("ERR di connessione al DB: " . $e->getMessage());
    die();
}

// API FUNZ
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

// MAPPING
$roleMap = [
    'Goalkeeper' => 'POR', 'G' => 'POR',
    'Defender'   => 'DIF', 'D' => 'DIF',
    'Midfielder' => 'CEN', 'M' => 'CEN',
    'Attacker'   => 'ATT', 'F' => 'ATT',
];

// SOLO PARTITE FINITE

// TEST passo direttamente gli ID specifici (test su england e argentina)
$test_fixtures_ids = [1502470, 1536911]; 
$fixtures = [];
foreach ($test_fixtures_ids as $id) {
    $res = apiGet("fixtures?id={$id}");
    if (!empty($res[0])) {
        $fixtures[] = $res[0];
    }
    usleep(200000); // 0.2 sleep
}

// PROD
/*
$today = date('Y-m-d'); 
$fixtures = apiGet("fixtures?date={$today}&league=1&season=2026") ?? []; 
*/
if (empty($fixtures)) {
    writeLog("0 match trovati");
    exit;
}

$finishedStatuses = ['FT', 'AET', 'PEN'];
$syncedCount = 0;

foreach ($fixtures as $f) {
    $fixtureId = $f['fixture']['id'];
    $status    = $f['fixture']['status']['short'];

    // skip live match
    if (!in_array($status, $finishedStatuses)) {
        writeLog("match ID {$fixtureId} saltata (status: $status)...");
        continue;
    }

    // skip partite finite stesso range tempo
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM player_match_stats WHERE fixture_id = ?");
    $stmtCheck->execute([$fixtureId]);
    $alreadyInDb = $stmtCheck->fetchColumn();

    if ($alreadyInDb > 0) {
        writeLog("match ID {$fixtureId} già salvata a DB...skip...");
        continue;
    }

    writeLog("match ID {$fixtureId} terminata ($status)...download statistiche...");

    // stats player call
    $playersData = apiGet("fixtures/players?fixture={$fixtureId}");
    
    if (empty($playersData)) {
        writeLog("  -> 0 stats giocatori trovata per fixture {$fixtureId}.");
        continue;
    }

    $homeGoals = (int)($f['goals']['home'] ?? 0);
    $awayGoals = (int)($f['goals']['away'] ?? 0);
    $homeCs    = ($awayGoals === 0);
    $awayCs    = ($homeGoals === 0);

    foreach ($playersData as $teamIdx => $teamData) {
        $teamCs = ($teamIdx === 0) ? $homeCs : $awayCs;

        foreach ($teamData['players'] ?? [] as $entry) {
            $pid   = $entry['player']['id'] ?? null;
            $pname = $entry['player']['name'] ?? 'Sconosciuto';
            $stats = $entry['statistics'][0] ?? [];
            if (!$pid) continue;

            $pos     = $stats['games']['position'] ?? 'M';
            $role    = $roleMap[$pos] ?? 'CEN';
            $rating  = (float) ($stats['games']['rating'] ?? 0);
            $minutes = (int)   ($stats['games']['minutes'] ?? 0);
            $goals   = (int)   ($stats['goals']['total'] ?? 0);
            $assists = (int)   ($stats['goals']['assists'] ?? 0);
            $yellow  = (int)   ($stats['cards']['yellow'] ?? 0);
            $red     = (int)   ($stats['cards']['red'] ?? 0);
            
            $cs = ($teamCs && $minutes > 0) ? 1 : 0;

            // salva db
            try {
                $matchDate = substr($f['fixture']['date'] ?? date('Y-m-d'), 0, 10);

                $stmt = $pdo->prepare("INSERT INTO player_match_stats 
                    (fixture_id, player_id, player_name, role, rating, minutes_played, goals, assists, yellow_cards, red_cards, clean_sheet, status, match_date) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    rating = VALUES(rating),
                    minutes_played = VALUES(minutes_played),
                    goals = VALUES(goals),
                    assists = VALUES(assists),
                    yellow_cards = VALUES(yellow_cards),
                    red_cards = VALUES(red_cards),
                    clean_sheet = VALUES(clean_sheet),
                    status = VALUES(status),
                    match_date = VALUES(match_date)
                ");

                $stmt->execute([
                    $fixtureId, $pid, $pname, $role, $rating, $minutes, 
                    $goals, $assists, $yellow, $red, $cs, $status, $matchDate
                ]);
            } catch (PDOException $e) {
                writeLog("  -> ERR DB su inserimento pid {$pid}: " . $e->getMessage());
            }
        }
    }
    
    $syncedCount++;
    usleep(200000); 
}

writeLog("sync OK per: {$syncedCount} match");
?>