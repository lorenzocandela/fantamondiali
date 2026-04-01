<?php
header('Content-Type: application/json');

// ─── CONFIGURAZIONE DB ──────────────────────────────────────────────────────
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
    die(json_encode(['status' => 'error', 'message' => 'DB error: ' . $e->getMessage()]));
}

$isSync = isset($_GET['sync']) && $_GET['sync'] === '1';

// ════════════════════════════════════════════════════════════════════════════
// MODALITÀ LETTURA VELOCE (Default per l'app)
// ════════════════════════════════════════════════════════════════════════════
if (!$isSync) {
    $stmt = $pdo->query("SELECT * FROM player_listone ORDER BY price DESC, rating DESC");
    $players = $stmt->fetchAll();
    
    // Cast dei tipi per far felice il JS
    foreach ($players as &$p) {
        $p['id'] = (int)$p['id'];
        $p['price'] = (int)$p['price'];
        $p['goals'] = (int)$p['goals'];
        $p['assists'] = (int)$p['assists'];
        $p['appearances'] = (int)$p['appearances'];
        $p['rating'] = (float)$p['rating'];
        // I nuovi campi
        $p['own_goals'] = isset($p['own_goals']) ? (int)$p['own_goals'] : 0;
        $p['pen_saved'] = isset($p['pen_saved']) ? (int)$p['pen_saved'] : 0;
    }

    echo json_encode([
        'status' => 'success',
        'total'  => count($players),
        'source' => 'MariaDB Locale',
        'data'   => $players
    ]);
    exit;
}

// ════════════════════════════════════════════════════════════════════════════
// MODALITÀ SYNC (Da lanciare a mano: get_api.php?sync=1)
// ════════════════════════════════════════════════════════════════════════════
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
    if (!empty($data['errors'])) return null;
    return $data ?? null;
}

$LEAGUE_ID = isset($_GET['league']) ? (int)$_GET['league'] : 1; // Es. 32 Qualificazioni
$SEASON    = isset($_GET['season']) ? (int)$_GET['season'] : 2026;

$teamsResponse = apiGet("teams?league={$LEAGUE_ID}&season={$SEASON}");
if (empty($teamsResponse['response'])) {
    die(json_encode(['status' => 'error', 'message' => "Nessuna squadra trovata per L{$LEAGUE_ID} S{$SEASON}"]));
}

$roleMap = [
    'Goalkeeper' => 'POR',
    'Defender'   => 'DIF',
    'Midfielder' => 'CEN',
    'Attacker'   => 'ATT',
];

$syncedCount = 0;

// Prepariamo la query di inserimento (aggiornata con own_goals e pen_saved)
$stmt = $pdo->prepare("
    INSERT INTO player_listone (id, name, photo, nationality, role, team, team_logo, rating, price, goals, assists, appearances, own_goals, pen_saved)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
    photo=VALUES(photo), role=VALUES(role), team=VALUES(team), team_logo=VALUES(team_logo),
    rating=VALUES(rating), price=VALUES(price), goals=VALUES(goals), assists=VALUES(assists), 
    appearances=VALUES(appearances), own_goals=VALUES(own_goals), pen_saved=VALUES(pen_saved)
");

foreach ($teamsResponse['response'] as $t) {
    $teamId   = $t['team']['id'];
    $teamName = $t['team']['name'];
    $teamLogo = $t['team']['logo'];
    
    // Per le statistiche usiamo l'endpoint players (non squads) che supporta l'impaginazione
    $page = 1;
    $totalPages = 1;

    while ($page <= $totalPages) {
        $playersData = apiGet("players?team={$teamId}&season={$SEASON}&page={$page}");
        
        if (empty($playersData['response'])) break;
        
        $totalPages = $playersData['paging']['total'] ?? 1;

        foreach ($playersData['response'] as $item) {
            $p = $item['player'];
            $stats = $item['statistics'][0] ?? []; // Prendiamo le stats della lega principale

            $role   = $roleMap[$stats['games']['position'] ?? 'Midfielder'] ?? 'CEN';
            $apps   = (int)($stats['games']['appearences'] ?? 0);
            $goals  = (int)($stats['goals']['total'] ?? 0);
            $assists= (int)($stats['goals']['assists'] ?? 0);
            $rating = (float)($stats['games']['rating'] ?? 6.0);
            
            // Nuove statistiche recuperate QUI (dentro il ciclo del giocatore)
            $og = (int)($stats['goals']['owngoals'] ?? 0);
            $ps = (int)($stats['penalty']['saved'] ?? 0);

            // ─── CALCOLO DEL PREZZO DINAMICO ───
            $basePrice = 5;
            $ratingBonus = max(0, ($rating - 6.0) * 8); // +8 cr per ogni punto rating sopra il 6
            $appBonus = $apps * 0.5; // +0.5 cr per presenza
            
            // Peso gol/assist diverso per ruolo
            if ($role === 'ATT') {
                $goalBonus = $goals * 3; $assistBonus = $assists * 1;
            } elseif ($role === 'CEN') {
                $goalBonus = $goals * 4; $assistBonus = $assists * 2;
            } elseif ($role === 'DIF') {
                $goalBonus = $goals * 5; $assistBonus = $assists * 2;
            } else {
                $goalBonus = $goals * 10; $assistBonus = $assists * 5; // Portieri col vizio
            }

            // Aggiungiamo il peso di autogol e rigori parati al prezzo
            $ogMalus = $og * 2;
            $psBonus = $ps * 3;
            $calculatedPrice = round($basePrice + $ratingBonus + $appBonus + $goalBonus + $assistBonus - $ogMalus + $psBonus);
            
            // Limitiamo il prezzo tra 5 e 60 (o quanto vuoi tu)
            $finalPrice = min(60, max(5, $calculatedPrice));

            // Salviamo a DB (utf8 safe)
            $cleanName = mb_convert_encoding($p['name'], 'UTF-8', 'UTF-8');
            $nat = mb_convert_encoding($p['nationality'] ?? $teamName, 'UTF-8', 'UTF-8');

            $stmt->execute([
                $p['id'], $cleanName, $p['photo'], $nat, $role, $teamName, $teamLogo,
                $rating, $finalPrice, $goals, $assists, $apps, $og, $ps
            ]);
            $syncedCount++;
        }
        $page++;
        usleep(250000); // Rispetta il Rate Limit API (4-5 req/sec)
    }
}

echo json_encode([
    'status' => 'success',
    'message' => "Sincronizzazione completata. $syncedCount giocatori aggiornati a DB."
]);