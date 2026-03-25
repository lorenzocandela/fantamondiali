<?php
// Rimuoviamo header JSON per stampare HTML
// header('Content-Type: application/json');

define('API_KEY', '1a4942a032906326bcdaa564e10dbe65');

function apiGet($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_HTTPHEADER     => ['x-apisports-key: ' . API_KEY, 'Accept: application/json'],
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    return json_decode($resp, true);
}

$fixtureId = 1537243; // Kazakhstan vs Namibia

// --- 1. FETCH DATI ---
$eventsReq  = apiGet("https://v3.football.api-sports.io/fixtures/events?fixture={$fixtureId}");
$lineupsReq = apiGet("https://v3.football.api-sports.io/fixtures/lineups?fixture={$fixtureId}");
$statsReq   = apiGet("https://v3.football.api-sports.io/fixtures/statistics?fixture={$fixtureId}");

$events  = $eventsReq['response'] ?? [];
$lineups = $lineupsReq['response'] ?? [];
$stats   = $statsReq['response'] ?? [];

// --- 2. MOTORE FANTACALCIO (Calcolo Voti e Log Eventi) ---
$playerScores = [];
$playerLogs   = [];

// Inizializza Titolari (Voto 6) e Panchinari (N.V.)
if (!empty($lineups)) {
    foreach ($lineups as $team) {
        // Titolari
        foreach ($team['startXI'] as $p) {
            $id = $p['player']['id'];
            $playerScores[$id] = 6.0;
            $playerLogs[$id] = [];
        }
        // Panchinari
        foreach ($team['substitutes'] as $p) {
            $id = $p['player']['id'];
            $playerScores[$id] = null; // Senza Voto
            $playerLogs[$id] = [];
        }
    }
}

// Elabora gli eventi per Bonus/Malus e Subentri
if (!empty($events)) {
    foreach ($events as $ev) {
        $pid = $ev['player']['id'] ?? null;
        $assistId = $ev['assist']['id'] ?? null;
        $time = $ev['time']['elapsed'] . "'";

        if ($ev['type'] === 'Goal') {
            if (stripos($ev['detail'], 'Own') !== false) {
                if ($pid && isset($playerScores[$pid])) { $playerScores[$pid] -= 2; $playerLogs[$pid][] = "[$time] ❌ Autogol (-2)"; }
            } else {
                if ($pid && isset($playerScores[$pid])) { $playerScores[$pid] += 3; $playerLogs[$pid][] = "[$time] ⚽ Gol (+3)"; }
                if ($assistId && isset($playerScores[$assistId])) { $playerScores[$assistId] += 1; $playerLogs[$assistId][] = "[$time] 👟 Assist (+1)"; }
            }
        } elseif ($ev['type'] === 'Card') {
            if ($ev['detail'] === 'Yellow Card') {
                if ($pid && isset($playerScores[$pid])) { $playerScores[$pid] -= 0.5; $playerLogs[$pid][] = "[$time] 🟨 Giallo (-0.5)"; }
            } elseif (stripos($ev['detail'], 'Red') !== false) {
                if ($pid && isset($playerScores[$pid])) { $playerScores[$pid] -= 1; $playerLogs[$pid][] = "[$time] 🟥 Rosso (-1)"; }
            }
        } elseif ($ev['type'] === 'subst') {
            // Player esce ($pid), Assist entra ($assistId)
            if ($pid) { $playerLogs[$pid][] = "[$time] 🔻 Sostituito"; }
            if ($assistId && array_key_exists($assistId, $playerScores)) {
                if ($playerScores[$assistId] === null) {
                    $playerScores[$assistId] = 6.0; // Prende voto entrando
                }
                $playerLogs[$assistId][] = "[$time] 🟢 Subentrato (Voto Base: 6)";
            }
        }
    }
}

// Funzione helper per badge colore
function getScoreBadgeClass($score) {
    if ($score === null) return 'bg-secondary';
    if ($score >= 6.5) return 'bg-success';
    if ($score >= 6) return 'bg-primary';
    return 'bg-danger';
}
?>

<!DOCTYPE html>
<html lang="it" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Fantacalcio Live</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #121212; }
        .player-card { background-color: #1e1e1e; border: 1px solid #333; margin-bottom: 8px; border-radius: 6px; padding: 10px; }
        .event-log { font-size: 0.85em; color: #aaa; margin-top: 5px; }
        pre { background-color: #000; padding: 15px; border-radius: 5px; color: #0f0; border: 1px solid #333; }
    </style>
</head>
<body>

<div class="container py-4">
    <header class="pb-3 mb-4 border-bottom">
        <h1 class="text-warning">⚙️ Fanta-Debug Live</h1>
        <p class="text-muted">Fixture ID: <?= $fixtureId ?> | Calcolo in tempo reale su base voto 6.0</p>
    </header>

    <?php if (empty($lineups)): ?>
        <div class="alert alert-warning">Le formazioni non sono ancora disponibili per questa partita.</div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($lineups as $team): ?>
                <div class="col-md-6 mb-4">
                    <div class="card bg-dark border-secondary h-100">
                        <div class="card-header bg-secondary text-white">
                            <h3 class="mb-0">
                                <img src="<?= $team['team']['logo'] ?>" width="30" class="me-2">
                                <?= $team['team']['name'] ?> 
                                <span class="badge bg-dark float-end"><?= $team['formation'] ?></span>
                            </h3>
                        </div>
                        <div class="card-body">
                            
                            <h5 class="text-info border-bottom border-info pb-1">Titolari</h5>
                            <?php foreach ($team['startXI'] as $p): 
                                $id = $p['player']['id'];
                                $score = $playerScores[$id] ?? null;
                                $logs = $playerLogs[$id] ?? [];
                            ?>
                                <div class="player-card">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <strong><?= $p['player']['number'] ?>. <?= $p['player']['name'] ?></strong>
                                        <span class="badge <?= getScoreBadgeClass($score) ?> fs-6">
                                            <?= $score !== null ? number_format($score, 1) : 'S.V.' ?>
                                        </span>
                                    </div>
                                    <?php if(!empty($logs)): ?>
                                        <div class="event-log">
                                            <?= implode("<br>", $logs) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>

                            <h5 class="text-info border-bottom border-info pb-1 mt-4">Panchina</h5>
                            <?php foreach ($team['substitutes'] as $p): 
                                $id = $p['player']['id'];
                                $score = $playerScores[$id] ?? null;
                                $logs = $playerLogs[$id] ?? [];
                            ?>
                                <div class="player-card opacity-75">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span><?= $p['player']['number'] ?>. <?= $p['player']['name'] ?></span>
                                        <span class="badge <?= getScoreBadgeClass($score) ?>">
                                            <?= $score !== null ? number_format($score, 1) : 'S.V.' ?>
                                        </span>
                                    </div>
                                    <?php if(!empty($logs)): ?>
                                        <div class="event-log">
                                            <?= implode("<br>", $logs) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>

                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <hr class="my-5 border-secondary">
    <h2 class="text-danger mb-3">🛠 Mega Detailed Raw Debug</h2>
    
    <div class="accordion accordion-flush bg-dark" id="debugAccordion">
        <div class="accordion-item bg-dark">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed bg-dark text-white border-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#debugEvents">
                    Raw API: Events Data (<?= count($events) ?>)
                </button>
            </h2>
            <div id="debugEvents" class="accordion-collapse collapse" data-bs-parent="#debugAccordion">
                <div class="accordion-body">
                    <pre><?= json_encode($eventsReq, JSON_PRETTY_PRINT) ?></pre>
                </div>
            </div>
        </div>

        <div class="accordion-item bg-dark">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed bg-dark text-white border-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#debugLineups">
                    Raw API: Lineups Data
                </button>
            </h2>
            <div id="debugLineups" class="accordion-collapse collapse" data-bs-parent="#debugAccordion">
                <div class="accordion-body">
                    <pre><?= json_encode($lineupsReq, JSON_PRETTY_PRINT) ?></pre>
                </div>
            </div>
        </div>

        <div class="accordion-item bg-dark">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed bg-dark text-white border-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#debugStats">
                    Raw API: Team Stats
                </button>
            </h2>
            <div id="debugStats" class="accordion-collapse collapse" data-bs-parent="#debugAccordion">
                <div class="accordion-body">
                    <pre><?= json_encode($statsReq, JSON_PRETTY_PRINT) ?></pre>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>