<?php
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
    die("Errore di connessione al DB: " . $e->getMessage() . "\n");
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

// ─── MAPPA RUOLI ────────────────────────────────────────────────────────────
$roleMap = [
    'Goalkeeper' => 'POR', 'G' => 'POR',
    'Defender'   => 'DIF', 'D' => 'DIF',
    'Midfielder' => 'CEN', 'M' => 'CEN',
    'Attacker'   => 'ATT', 'F' => 'ATT',
];

// ─── 1. OTTIENI LE PARTITE DI OGGI ──────────────────────────────────────────
$today = '2026-03-26'; // TEST SU PARTITE DI IERI (italia irlanda del nord es.)
#$today = date('Y-m-d');

echo "[".date('Y-m-d H:i:s')."] Avvio sincronizzazione partite...\n";

$fixtures = apiGet("fixtures?date={$today}&league=32&season=2024") ?? []; 
// PROD: $fixtures = apiGet("fixtures?date={$today}&league=1&season=2026") ?? [];

if (empty($fixtures)) {
    die("Nessuna partita trovata per oggi.\n");
}

$finishedStatuses = ['FT', 'AET', 'PEN'];
$syncedCount = 0;

foreach ($fixtures as $f) {
    $fixtureId = $f['fixture']['id'];
    $status    = $f['fixture']['status']['short'];

    if (!in_array($status, $finishedStatuses)) {
        continue;
    }

    echo "Partita ID {$fixtureId} terminata ($status). Scaricamento statistiche giocatori...\n";

    // ─── 2. SCARICA STATISTICHE GIOCATORI DELLA PARTITA ─────────────────────
    $playersData = apiGet("fixtures/players?fixture={$fixtureId}");
    
    if (empty($playersData)) {
        echo "  Nessuna statistica giocatori trovata per fixture {$fixtureId}.\n";
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

            // ─── 3. INSERISCI O AGGIORNA A DB ───────────────────────────────
            $stmt = $pdo->prepare("
                INSERT INTO player_match_stats 
                (fixture_id, player_id, player_name, role, rating, minutes_played, goals, assists, yellow_cards, red_cards, clean_sheet, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                rating = VALUES(rating),
                minutes_played = VALUES(minutes_played),
                goals = VALUES(goals),
                assists = VALUES(assists),
                yellow_cards = VALUES(yellow_cards),
                red_cards = VALUES(red_cards),
                clean_sheet = VALUES(clean_sheet),
                status = VALUES(status)
            ");

            $stmt->execute([
                $fixtureId, $pid, $pname, $role, $rating, $minutes, 
                $goals, $assists, $yellow, $red, $cs, $status
            ]);
        }
    }
    
    $syncedCount++;
    usleep(200000); 
}

echo "[".date('Y-m-d H:i:s')."] Sincronizzazione completata. {$syncedCount} partite processate.\n";