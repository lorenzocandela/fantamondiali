<?php
define('API_KEY', '1a4942a032906326bcdaa564e10dbe65');
define('API_BASE', 'https://v3.football.api-sports.io/');

// ─── SCORE TABLE (same as app) ──────────────────────────────────────────────
$SCORE_TABLE = [
    'goal'   => ['POR' => 10, 'DIF' => 6, 'CEN' => 6, 'ATT' => 8],
    'owngoal'=> -2,
    'assist' => 3,
    'yellow' => -0.5,
    'red'    => -2,
    'cs'     => ['POR' => 2, 'DIF' => 1],
];

$ROLE_MAP = ['G' => 'POR', 'D' => 'DIF', 'M' => 'CEN', 'F' => 'ATT',
             'Goalkeeper' => 'POR', 'Defender' => 'DIF', 'Midfielder' => 'CEN', 'Attacker' => 'ATT'];

function apiGet($endpoint) {
    $url = (strpos($endpoint, 'http') === 0) ? $endpoint : API_BASE . $endpoint;
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['x-apisports-key: ' . API_KEY, 'Accept: application/json'],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $remaining = curl_getinfo($ch, CURLINFO_HEADER_SIZE); // not actual remaining, see below
    curl_close($ch);
    return ['code' => $code, 'data' => json_decode($resp, true) ?: []];
}

// ─── PARAMS ─────────────────────────────────────────────────────────────────
$fixtureId   = $_GET['fixture'] ?? null;
$searchDate  = $_GET['date'] ?? date('Y-m-d');
$searchQuery = $_GET['search'] ?? '';
$showCoverage = isset($_GET['coverage']);
$apiCallCount = 0;

// ─── LEAGUE CONFIG (what we know works) ─────────────────────────────────────
$KNOWN_LEAGUES = [
    ['id' => 32,   'season' => 2024, 'name' => 'WC Qual Europe',          'priority' => 1],
    ['id' => 1,    'season' => 2026, 'name' => 'World Cup 2026',          'priority' => 2],
    ['id' => 1222, 'season' => 2026, 'name' => 'FIFA Series',             'priority' => 3],
    ['id' => 960,  'season' => 2026, 'name' => 'UEFA Playoff WC',         'priority' => 4],
    ['id' => 37,   'season' => 2026, 'name' => 'Intercontinental Playoff','priority' => 5],
    ['id' => 5,    'season' => 2026, 'name' => 'Friendlies',              'priority' => 6],
];

// ═══════════════════════════════════════════════════════════════════════════════
// FIXTURE DETAIL MODE
// ═══════════════════════════════════════════════════════════════════════════════
$fixtureData = null;
$lineups = [];
$events = [];
$playerStats = [];
$rawPlayers = [];
$rawLineups = [];
$rawEvents = [];
$rawStats = [];
$playerScores = [];
$coverage = null;
$matchesList = [];

if ($fixtureId) {
    // 1. Fixture base data
    $r = apiGet("fixtures?id={$fixtureId}"); $apiCallCount++;
    $fixtureData = $r['data']['response'][0] ?? null;
    
    if ($fixtureData) {
        $leagueId = $fixtureData['league']['id'] ?? null;
        $season   = $fixtureData['league']['season'] ?? null;
        
        // 2. Coverage check
        if ($leagueId && $season) {
            $r = apiGet("leagues?id={$leagueId}&season={$season}"); $apiCallCount++;
            $covData = $r['data']['response'][0]['seasons'] ?? [];
            foreach ($covData as $s) {
                if ($s['year'] == $season) { $coverage = $s['coverage'] ?? null; break; }
            }
        }
        
        // 3. Lineups
        $r = apiGet("fixtures/lineups?fixture={$fixtureId}"); $apiCallCount++;
        $rawLineups = $r['data'];
        $lineups = $r['data']['response'] ?? [];
        
        // 4. Events
        $r = apiGet("fixtures/events?fixture={$fixtureId}"); $apiCallCount++;
        $rawEvents = $r['data'];
        $events = $r['data']['response'] ?? [];
        
        // 5. Player statistics (the gold mine)
        $r = apiGet("fixtures/players?fixture={$fixtureId}"); $apiCallCount++;
        $rawPlayers = $r['data'];
        $playerStats = $r['data']['response'] ?? [];
        
        // 6. Team statistics
        $r = apiGet("fixtures/statistics?fixture={$fixtureId}"); $apiCallCount++;
        $rawStats = $r['data'];
        
        // ─── FANTACALCIO ENGINE ─────────────────────────────────────────────
        
        // Source 1: Player stats from API (best — has rating)
        if (!empty($playerStats)) {
            foreach ($playerStats as $teamData) {
                $teamName = $teamData['team']['name'] ?? '?';
                foreach ($teamData['players'] ?? [] as $entry) {
                    $pid   = $entry['player']['id'] ?? null;
                    if (!$pid) continue;
                    $stats = $entry['statistics'][0] ?? [];
                    $pos   = $stats['games']['position'] ?? 'M';
                    $role  = $ROLE_MAP[$pos] ?? 'CEN';
                    $rating = (float)($stats['games']['rating'] ?? 0);
                    $minutes = (int)($stats['games']['minutes'] ?? 0);
                    
                    $score = $rating;
                    $goals   = (int)($stats['goals']['total'] ?? 0);
                    $assists = (int)($stats['goals']['assists'] ?? 0);
                    $yellow  = (int)($stats['cards']['yellow'] ?? 0);
                    $red     = (int)($stats['cards']['red'] ?? 0);
                    $conceded = $stats['goals']['conceded'] ?? null;
                    $cs = ($conceded !== null && $conceded === 0);
                    
                    $score += $goals * ($SCORE_TABLE['goal'][$role] ?? 6);
                    $score += $assists * $SCORE_TABLE['assist'];
                    $score += $yellow * $SCORE_TABLE['yellow'];
                    $score += $red * $SCORE_TABLE['red'];
                    if ($cs && isset($SCORE_TABLE['cs'][$role])) $score += $SCORE_TABLE['cs'][$role];
                    
                    $playerScores[$pid] = [
                        'score'   => round($score, 2),
                        'rating'  => $rating,
                        'role'    => $role,
                        'minutes' => $minutes,
                        'goals'   => $goals,
                        'assists' => $assists,
                        'yellow'  => $yellow,
                        'red'     => $red,
                        'cs'      => $cs,
                        'source'  => 'players_stats',
                        'team'    => $teamName,
                    ];
                }
            }
        }
        // Source 2: Lineups + Events fallback
        elseif (!empty($lineups)) {
            // Start XI = 6.0 base
            foreach ($lineups as $team) {
                foreach ($team['startXI'] ?? [] as $p) {
                    $pid = $p['player']['id'] ?? null;
                    $pos = $p['player']['pos'] ?? 'M';
                    if ($pid) $playerScores[$pid] = [
                        'score' => 6.0, 'rating' => 6.0, 'role' => $ROLE_MAP[$pos] ?? 'CEN',
                        'minutes' => 90, 'goals' => 0, 'assists' => 0, 'yellow' => 0, 'red' => 0,
                        'cs' => false, 'source' => 'lineups', 'team' => $team['team']['name'] ?? '?',
                    ];
                }
                foreach ($team['substitutes'] ?? [] as $p) {
                    $pid = $p['player']['id'] ?? null;
                    $pos = $p['player']['pos'] ?? 'M';
                    if ($pid) $playerScores[$pid] = [
                        'score' => null, 'rating' => null, 'role' => $ROLE_MAP[$pos] ?? 'CEN',
                        'minutes' => 0, 'goals' => 0, 'assists' => 0, 'yellow' => 0, 'red' => 0,
                        'cs' => false, 'source' => 'lineups_sub', 'team' => $team['team']['name'] ?? '?',
                    ];
                }
            }
            // Apply events on top
            foreach ($events as $ev) {
                $pid = $ev['player']['id'] ?? null;
                $assistId = $ev['assist']['id'] ?? null;
                $type = $ev['type'] ?? '';
                $detail = $ev['detail'] ?? '';
                
                if ($type === 'Goal' && $pid && isset($playerScores[$pid])) {
                    if (stripos($detail, 'Own') !== false) {
                        $playerScores[$pid]['score'] += $SCORE_TABLE['owngoal'];
                    } elseif ($detail !== 'Missed Penalty') {
                        $role = $playerScores[$pid]['role'];
                        $playerScores[$pid]['goals']++;
                        $playerScores[$pid]['score'] += ($SCORE_TABLE['goal'][$role] ?? 6);
                        if ($assistId && isset($playerScores[$assistId])) {
                            $playerScores[$assistId]['assists']++;
                            $playerScores[$assistId]['score'] += $SCORE_TABLE['assist'];
                        }
                    }
                } elseif ($type === 'Card' && $pid && isset($playerScores[$pid])) {
                    if ($detail === 'Yellow Card') { $playerScores[$pid]['yellow']++; $playerScores[$pid]['score'] += $SCORE_TABLE['yellow']; }
                    if (stripos($detail, 'Red') !== false) { $playerScores[$pid]['red']++; $playerScores[$pid]['score'] += $SCORE_TABLE['red']; }
                } elseif ($type === 'subst') {
                    // assist = player entering
                    if ($assistId && isset($playerScores[$assistId]) && $playerScores[$assistId]['score'] === null) {
                        $playerScores[$assistId]['score'] = 6.0;
                        $playerScores[$assistId]['rating'] = 6.0;
                        $playerScores[$assistId]['source'] = 'sub_entered';
                    }
                }
            }
        }
        // Source 3: Events only (no lineups)
        elseif (!empty($events)) {
            foreach ($events as $ev) {
                $pid = $ev['player']['id'] ?? null;
                $type = $ev['type'] ?? '';
                $detail = $ev['detail'] ?? '';
                if (!$pid) continue;
                
                if (!isset($playerScores[$pid])) {
                    $playerScores[$pid] = [
                        'score' => 6.0, 'rating' => 6.0, 'role' => 'CEN',
                        'minutes' => 0, 'goals' => 0, 'assists' => 0, 'yellow' => 0, 'red' => 0,
                        'cs' => false, 'source' => 'events_only', 'team' => $ev['team']['name'] ?? '?',
                    ];
                }
                
                if ($type === 'Goal' && $detail !== 'Missed Penalty') {
                    if (stripos($detail, 'Own') !== false) {
                        $playerScores[$pid]['score'] += $SCORE_TABLE['owngoal'];
                    } else {
                        $playerScores[$pid]['goals']++;
                        $playerScores[$pid]['score'] += 6; // default CEN
                        $assistId = $ev['assist']['id'] ?? null;
                        if ($assistId) {
                            if (!isset($playerScores[$assistId])) {
                                $playerScores[$assistId] = [
                                    'score' => 6.0, 'rating' => 6.0, 'role' => 'CEN',
                                    'minutes' => 0, 'goals' => 0, 'assists' => 0, 'yellow' => 0, 'red' => 0,
                                    'cs' => false, 'source' => 'events_only', 'team' => $ev['team']['name'] ?? '?',
                                ];
                            }
                            $playerScores[$assistId]['assists']++;
                            $playerScores[$assistId]['score'] += $SCORE_TABLE['assist'];
                        }
                    }
                } elseif ($type === 'Card') {
                    if ($detail === 'Yellow Card') { $playerScores[$pid]['yellow']++; $playerScores[$pid]['score'] += $SCORE_TABLE['yellow']; }
                    if (stripos($detail, 'Red') !== false) { $playerScores[$pid]['red']++; $playerScores[$pid]['score'] += $SCORE_TABLE['red']; }
                }
            }
        }
    }
} else {
    // ═════════════════════════════════════════════════════════════════════════
    // SEARCH MODE
    // ═════════════════════════════════════════════════════════════════════════
    $r = apiGet("fixtures?date={$searchDate}"); $apiCallCount++;
    $allMatches = $r['data']['response'] ?? [];
    
    if (!empty($searchQuery)) {
        foreach ($allMatches as $m) {
            $home = $m['teams']['home']['name'] ?? '';
            $away = $m['teams']['away']['name'] ?? '';
            if (stripos($home, $searchQuery) !== false || stripos($away, $searchQuery) !== false) {
                $matchesList[] = $m;
            }
        }
    } else {
        $matchesList = $allMatches;
    }
}

// Helpers
function getEventIcon($type, $detail) {
    if ($type === 'Goal' && stripos($detail, 'Own') !== false) return '🔴⚽';
    if ($type === 'Goal' && $detail === 'Missed Penalty') return '❌';
    if ($type === 'Goal') return '⚽';
    if ($type === 'Card' && str_contains($detail, 'Yellow')) return '🟨';
    if ($type === 'Card' && str_contains($detail, 'Red')) return '🟥';
    if ($type === 'subst') return '🔄';
    if ($type === 'Var') return '📺';
    return '⚡';
}

function getScoreColor($score) {
    if ($score === null) return 'bg-slate-600 text-slate-300';
    if ($score >= 7.0) return 'bg-emerald-500 text-white';
    if ($score >= 6.0) return 'bg-blue-500 text-white';
    if ($score >= 5.0) return 'bg-amber-500 text-white';
    return 'bg-red-500 text-white';
}

function getSourceBadge($source) {
    $map = [
        'players_stats' => ['⭐ API Stats', 'bg-emerald-500/20 text-emerald-400'],
        'lineups'       => ['📋 Lineup XI', 'bg-blue-500/20 text-blue-400'],
        'lineups_sub'   => ['🪑 Panchina', 'bg-slate-500/20 text-slate-400'],
        'sub_entered'   => ['🔄 Subentrato', 'bg-cyan-500/20 text-cyan-400'],
        'events_only'   => ['⚡ Solo eventi', 'bg-amber-500/20 text-amber-400'],
    ];
    $s = $map[$source] ?? ['?', 'bg-slate-500/20 text-slate-400'];
    return "<span class=\"px-2 py-0.5 rounded text-[10px] font-bold {$s[1]}\">{$s[0]}</span>";
}

function covBadge($val) {
    if ($val === true) return '<span class="text-emerald-400 font-bold">✓</span>';
    if ($val === false) return '<span class="text-red-400 font-bold">✗</span>';
    return '<span class="text-slate-500">?</span>';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FM26 Debug Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #0f172a; color: #f8fafc; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #1e293b; border-radius: 8px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #475569; border-radius: 8px; }
        <?php if ($fixtureId): ?>
        /* Auto-refresh ogni 60s quando fixture è aperta */
        <?php
            $status = $fixtureData['fixture']['status']['short'] ?? 'NS';
            $liveStatuses = ['1H','HT','2H','ET','P','BT','LIVE'];
            if (in_array($status, $liveStatuses)):
        ?>
        /* LIVE — auto refresh */
        <?php endif; ?>
        <?php endif; ?>
    </style>
    <?php
    if ($fixtureId && isset($status) && in_array($status, ['1H','HT','2H','ET','P','BT','LIVE'])):
    ?>
    <meta http-equiv="refresh" content="60">
    <?php endif; ?>
</head>
<body class="p-4 md:p-8">

<div class="max-w-7xl mx-auto space-y-6">

    <!-- HEADER -->
    <div class="bg-slate-800 rounded-2xl p-4 border border-slate-700 shadow flex flex-col md:flex-row justify-between items-center gap-4">
        <a href="?" class="text-xl font-black bg-gradient-to-r from-emerald-400 to-blue-500 bg-clip-text text-transparent hover:opacity-80 transition">
            ⚽ FM26 Debug Dashboard
        </a>
        <div class="flex items-center gap-3 text-xs text-slate-500 font-mono">
            <span>API calls: <?= $apiCallCount ?></span>
            <span>·</span>
            <span><?= date('H:i:s') ?></span>
            <?php if ($fixtureId && isset($status) && in_array($status, ['1H','HT','2H','ET','P','BT','LIVE'])): ?>
            <span class="px-2 py-1 bg-red-500/20 text-red-400 rounded font-bold animate-pulse">LIVE AUTO-REFRESH 60s</span>
            <?php endif; ?>
        </div>
        
        <?php if (!$fixtureId): ?>
        <form method="GET" class="flex flex-wrap gap-3 w-full md:w-auto">
            <input type="date" name="date" value="<?= htmlspecialchars($searchDate) ?>" class="bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500">
            <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Es. Italy" class="bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500 placeholder-slate-500">
            <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded-lg text-sm font-bold transition">Cerca</button>
        </form>
        <?php else: ?>
        <a href="?" class="bg-slate-700 hover:bg-slate-600 text-white px-4 py-2 rounded-lg text-sm font-bold transition">⬅ Torna</a>
        <?php endif; ?>
    </div>

    <?php if (!$fixtureId): ?>
    <!-- ═══════════════════ SEARCH RESULTS ═══════════════════ -->
    <div class="bg-slate-800 rounded-2xl border border-slate-700 p-6 shadow-inner min-h-[50vh]">
        <h2 class="text-xl font-bold mb-4">Risultati per il <?= date('d/m/Y', strtotime($searchDate)) ?> <?= $searchQuery ? " — \"$searchQuery\"" : '' ?>
            <span class="text-sm text-slate-500 font-normal ml-2">(<?= count($matchesList) ?> partite)</span>
        </h2>
        
        <?php if (empty($matchesList)): ?>
            <div class="text-center text-slate-500 py-10">Nessuna partita trovata.</div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($matchesList as $m): 
                    $st = $m['fixture']['status']['short'] ?? 'NS';
                    $stColor = match(true) {
                        in_array($st, ['1H','2H','HT','ET','LIVE']) => 'text-red-400',
                        in_array($st, ['FT','AET','PEN']) => 'text-emerald-400',
                        default => 'text-yellow-400',
                    };
                ?>
                <div class="bg-slate-900 border border-slate-700 p-4 rounded-xl hover:border-blue-500 transition">
                    <div class="flex justify-between text-xs text-slate-400 mb-2 pb-2 border-b border-slate-800">
                        <span class="truncate"><?= $m['league']['name'] ?> <span class="text-slate-600">(<?= $m['league']['id'] ?>)</span></span>
                        <span class="font-bold <?= $stColor ?>"><?= $st ?></span>
                    </div>
                    <div class="flex justify-between items-center my-2">
                        <div class="flex items-center gap-2 w-2/5">
                            <img src="<?= $m['teams']['home']['logo'] ?>" class="w-6 h-6 object-contain">
                            <span class="font-bold text-sm truncate"><?= $m['teams']['home']['name'] ?></span>
                        </div>
                        <div class="text-lg font-black bg-slate-800 px-3 rounded text-slate-300">
                            <?= $m['goals']['home'] ?? '-' ?> : <?= $m['goals']['away'] ?? '-' ?>
                        </div>
                        <div class="flex items-center justify-end gap-2 w-2/5">
                            <span class="font-bold text-sm truncate text-right"><?= $m['teams']['away']['name'] ?></span>
                            <img src="<?= $m['teams']['away']['logo'] ?>" class="w-6 h-6 object-contain">
                        </div>
                    </div>
                    <a href="?fixture=<?= $m['fixture']['id'] ?>" class="mt-3 block text-center bg-blue-600/20 text-blue-400 border border-blue-600/50 hover:bg-blue-600 hover:text-white py-2 rounded-lg text-xs font-bold transition">
                        Debug (ID: <?= $m['fixture']['id'] ?>) · League <?= $m['league']['id'] ?> · Season <?= $m['league']['season'] ?>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($fixtureId && $fixtureData): ?>
    <!-- ═══════════════════ FIXTURE DETAIL ═══════════════════ -->
    
    <!-- Score header -->
    <div class="bg-slate-800 rounded-2xl shadow-2xl p-6 border border-slate-700">
        <div class="flex flex-col md:flex-row items-center justify-between">
            <div class="flex items-center gap-4 w-full md:w-1/3 justify-center md:justify-end">
                <h2 class="text-xl md:text-2xl font-bold text-slate-200 text-right"><?= $fixtureData['teams']['home']['name'] ?></h2>
                <img src="<?= $fixtureData['teams']['home']['logo'] ?>" class="w-16 h-16 object-contain">
            </div>
            <div class="flex flex-col items-center w-full md:w-1/3 my-4 md:my-0">
                <?php 
                $st = $fixtureData['fixture']['status']['short'];
                $elapsed = $fixtureData['fixture']['status']['elapsed'];
                $stClass = in_array($st, ['1H','2H','HT','ET','LIVE']) ? 'text-red-400 animate-pulse' : 'text-emerald-400';
                ?>
                <span class="text-sm font-semibold <?= $stClass ?> mb-1 tracking-widest uppercase">
                    <?= $st ?> <?= $elapsed ? $elapsed . "'" : '' ?>
                </span>
                <div class="text-5xl font-extrabold text-white">
                    <?= $fixtureData['goals']['home'] ?? 0 ?> — <?= $fixtureData['goals']['away'] ?? 0 ?>
                </div>
                <span class="text-xs text-slate-400 mt-2"><?= $fixtureData['league']['name'] ?> · League <?= $fixtureData['league']['id'] ?> · Season <?= $fixtureData['league']['season'] ?></span>
                <span class="text-xs text-slate-500 mt-1 font-mono">Fixture ID: <?= $fixtureId ?></span>
            </div>
            <div class="flex items-center gap-4 w-full md:w-1/3 justify-center md:justify-start">
                <img src="<?= $fixtureData['teams']['away']['logo'] ?>" class="w-16 h-16 object-contain">
                <h2 class="text-xl md:text-2xl font-bold text-slate-200"><?= $fixtureData['teams']['away']['name'] ?></h2>
            </div>
        </div>
    </div>

    <!-- Coverage check -->
    <?php if ($coverage): ?>
    <div class="bg-slate-800 rounded-2xl p-4 border border-slate-700">
        <h3 class="font-bold text-sm text-slate-400 uppercase tracking-wider mb-3">Coverage League <?= $fixtureData['league']['id'] ?> · Season <?= $fixtureData['league']['season'] ?></h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
            <div class="flex items-center gap-2"><?= covBadge($coverage['fixtures']['events'] ?? null) ?> Events</div>
            <div class="flex items-center gap-2"><?= covBadge($coverage['fixtures']['lineups'] ?? null) ?> Lineups</div>
            <div class="flex items-center gap-2"><?= covBadge($coverage['fixtures']['statistics_fixtures'] ?? null) ?> Stats Fixture</div>
            <div class="flex items-center gap-2"><?= covBadge($coverage['fixtures']['statistics_players'] ?? null) ?> Stats Players</div>
            <div class="flex items-center gap-2"><?= covBadge($coverage['standings'] ?? null) ?> Standings</div>
            <div class="flex items-center gap-2"><?= covBadge($coverage['players'] ?? null) ?> Players</div>
            <div class="flex items-center gap-2"><?= covBadge($coverage['top_scorers'] ?? null) ?> Top Scorers</div>
            <div class="flex items-center gap-2"><?= covBadge($coverage['predictions'] ?? null) ?> Predictions</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Data availability summary -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <?php
        $checks = [
            ['Lineups', count($lineups), $lineups ? 'emerald' : 'slate', count($lineups) . ' teams'],
            ['Events', count($events), $events ? 'blue' : 'slate', count($events) . ' eventi'],
            ['Player Stats', count($playerStats), $playerStats ? 'emerald' : 'slate', $playerStats ? array_sum(array_map(fn($t) => count($t['players'] ?? []), $playerStats)) . ' players' : 'N/A'],
            ['Team Stats', count($rawStats['response'] ?? []), ($rawStats['response'] ?? []) ? 'cyan' : 'slate', count($rawStats['response'] ?? []) . ' teams'],
            ['Voti Calc.', count($playerScores), $playerScores ? 'amber' : 'slate', count(array_filter($playerScores, fn($p) => $p['score'] !== null)) . ' con voto'],
        ];
        foreach ($checks as [$label, $count, $color, $detail]):
        ?>
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-3 text-center">
            <div class="text-2xl font-black text-<?= $color ?>-400"><?= $count ?></div>
            <div class="text-xs text-slate-400 font-semibold"><?= $label ?></div>
            <div class="text-[10px] text-slate-500 mt-1"><?= $detail ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 3-column layout -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        
        <!-- HOME LINEUP -->
        <div class="col-span-1 bg-slate-800 rounded-2xl p-5 border border-slate-700">
            <?php if (!empty($lineups[0])): $home = $lineups[0]; ?>
                <div class="flex justify-between items-center mb-4 border-b border-slate-700 pb-2">
                    <h3 class="font-bold text-lg"><?= $home['team']['name'] ?></h3>
                    <span class="px-2 py-1 bg-slate-700 text-xs font-bold rounded-lg"><?= $home['formation'] ?></span>
                </div>
                <p class="text-xs text-slate-400 uppercase font-semibold mb-2">Titolari</p>
                <div class="space-y-1">
                    <?php foreach ($home['startXI'] as $p): 
                        $pid = $p['player']['id'];
                        $ps = $playerScores[$pid] ?? null;
                        $score = $ps['score'] ?? null;
                    ?>
                    <div class="flex items-center justify-between p-2 hover:bg-slate-700/50 rounded-lg transition gap-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="text-xs text-slate-500 font-mono w-4"><?= $p['player']['pos'] ?? '?' ?></span>
                            <span class="font-semibold text-sm truncate"><?= $p['player']['name'] ?></span>
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                            <?php if ($ps): echo getSourceBadge($ps['source']); endif; ?>
                            <span class="px-2 py-0.5 rounded text-xs font-bold <?= getScoreColor($score) ?>"><?= $score !== null ? number_format($score, 1) : 'SV' ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <p class="text-xs text-slate-400 uppercase font-semibold mb-2 mt-4">Panchina</p>
                <div class="space-y-1">
                    <?php foreach ($home['substitutes'] as $p): 
                        $pid = $p['player']['id'];
                        $ps = $playerScores[$pid] ?? null;
                        $score = $ps['score'] ?? null;
                    ?>
                    <div class="flex items-center justify-between p-1.5 hover:bg-slate-700/50 rounded-lg transition opacity-60 gap-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="text-xs text-slate-500 font-mono w-4"><?= $p['player']['pos'] ?? '?' ?></span>
                            <span class="text-sm truncate"><?= $p['player']['name'] ?></span>
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                            <?php if ($ps && $ps['source'] === 'sub_entered'): echo getSourceBadge($ps['source']); endif; ?>
                            <span class="px-2 py-0.5 rounded text-xs font-bold <?= getScoreColor($score) ?>"><?= $score !== null ? number_format($score, 1) : '—' ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif (!empty($playerStats)): ?>
                <!-- No lineups but have player stats — show from stats -->
                <?php 
                $homeTeamId = $fixtureData['teams']['home']['id'];
                $homeStats = null;
                foreach ($playerStats as $t) { if (($t['team']['id'] ?? 0) == $homeTeamId) { $homeStats = $t; break; } }
                ?>
                <?php if ($homeStats): ?>
                <div class="flex justify-between items-center mb-4 border-b border-slate-700 pb-2">
                    <h3 class="font-bold text-lg"><?= $homeStats['team']['name'] ?></h3>
                    <span class="px-2 py-1 bg-emerald-500/20 text-emerald-400 text-xs font-bold rounded-lg">da Player Stats</span>
                </div>
                <div class="space-y-1">
                    <?php foreach ($homeStats['players'] as $entry):
                        $pid = $entry['player']['id'];
                        $ps = $playerScores[$pid] ?? null;
                        $score = $ps['score'] ?? null;
                        $stats = $entry['statistics'][0] ?? [];
                    ?>
                    <div class="flex items-center justify-between p-2 hover:bg-slate-700/50 rounded-lg transition gap-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="text-xs text-slate-500 font-mono w-4"><?= $stats['games']['position'] ?? '?' ?></span>
                            <span class="font-semibold text-sm truncate"><?= $entry['player']['name'] ?></span>
                            <span class="text-[10px] text-slate-500"><?= $stats['games']['minutes'] ?? 0 ?>'</span>
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                            <?php if ($stats['games']['rating'] ?? null): ?>
                            <span class="text-[10px] text-slate-500 font-mono"><?= number_format((float)$stats['games']['rating'], 1) ?></span>
                            <?php endif; ?>
                            <span class="px-2 py-0.5 rounded text-xs font-bold <?= getScoreColor($score) ?>"><?= $score !== null ? number_format($score, 1) : 'SV' ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="h-full flex flex-col items-center justify-center text-slate-500 p-6 text-center">
                    <p class="text-4xl mb-3">⏳</p>
                    <p>In attesa formazioni (Home)</p>
                    <p class="text-xs mt-2">Disponibili ~20-40 min prima del match</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- TIMELINE EVENTI -->
        <div class="col-span-2 bg-slate-900 rounded-2xl p-5 border border-slate-700">
            <h3 class="font-bold text-lg mb-4 text-center text-slate-300">Timeline Eventi</h3>
            <?php if (empty($events)): ?>
                <div class="text-center text-slate-500 py-10">Nessun evento.</div>
            <?php else: ?>
                <div class="space-y-3 max-h-[600px] overflow-y-auto custom-scrollbar pr-2">
                    <?php foreach (array_reverse($events) as $ev): 
                        $isHome = ($ev['team']['id'] ?? 0) === ($fixtureData['teams']['home']['id'] ?? -1);
                        $pid = $ev['player']['id'] ?? null;
                        $ps = $pid ? ($playerScores[$pid] ?? null) : null;
                    ?>
                    <div class="flex items-center gap-3 bg-slate-800 p-3 rounded-xl border border-slate-700/50 relative overflow-hidden">
                        <div class="absolute inset-y-0 <?= $isHome ? 'left-0 border-l-4 border-blue-500' : 'right-0 border-r-4 border-green-500' ?> w-full bg-gradient-to-r <?= $isHome ? 'from-blue-500/10 to-transparent' : 'from-transparent to-green-500/10' ?> pointer-events-none"></div>
                        <div class="font-black text-slate-400 w-10 text-right shrink-0 font-mono"><?= $ev['time']['elapsed'] ?>'<?= $ev['time']['extra'] ? '+' . $ev['time']['extra'] : '' ?></div>
                        <div class="text-2xl shrink-0"><?= getEventIcon($ev['type'], $ev['detail'] ?? '') ?></div>
                        <div class="flex-1 min-w-0 z-10">
                            <p class="font-bold text-slate-200 truncate">
                                <?= $ev['player']['name'] ?? '?' ?>
                                <?php if($ev['type'] === 'subst' && ($ev['assist']['name'] ?? null)): ?>
                                    <span class="text-slate-400 text-sm font-normal">↔ <?= $ev['assist']['name'] ?></span>
                                <?php endif; ?>
                                <?php if($ev['type'] === 'Goal' && ($ev['assist']['name'] ?? null)): ?>
                                    <span class="text-slate-400 text-sm font-normal">(🅰 <?= $ev['assist']['name'] ?>)</span>
                                <?php endif; ?>
                            </p>
                            <p class="text-xs text-slate-400">
                                <?= $ev['detail'] ?> · <?= $ev['team']['name'] ?>
                                <?php if ($pid): ?><span class="text-slate-600 font-mono"> · pid:<?= $pid ?></span><?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- AWAY LINEUP -->
        <div class="col-span-1 bg-slate-800 rounded-2xl p-5 border border-slate-700">
            <?php if (!empty($lineups[1])): $away = $lineups[1]; ?>
                <div class="flex justify-between items-center mb-4 border-b border-slate-700 pb-2">
                    <span class="px-2 py-1 bg-slate-700 text-xs font-bold rounded-lg"><?= $away['formation'] ?></span>
                    <h3 class="font-bold text-lg"><?= $away['team']['name'] ?></h3>
                </div>
                <p class="text-xs text-slate-400 uppercase font-semibold mb-2 text-right">Titolari</p>
                <div class="space-y-1">
                    <?php foreach ($away['startXI'] as $p): 
                        $pid = $p['player']['id'];
                        $ps = $playerScores[$pid] ?? null;
                        $score = $ps['score'] ?? null;
                    ?>
                    <div class="flex items-center justify-between p-2 hover:bg-slate-700/50 rounded-lg transition gap-2">
                        <div class="flex items-center gap-1 shrink-0">
                            <span class="px-2 py-0.5 rounded text-xs font-bold <?= getScoreColor($score) ?>"><?= $score !== null ? number_format($score, 1) : 'SV' ?></span>
                            <?php if ($ps): echo getSourceBadge($ps['source']); endif; ?>
                        </div>
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="font-semibold text-sm truncate text-right"><?= $p['player']['name'] ?></span>
                            <span class="text-xs text-slate-500 font-mono w-4"><?= $p['player']['pos'] ?? '?' ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <p class="text-xs text-slate-400 uppercase font-semibold mb-2 mt-4 text-right">Panchina</p>
                <div class="space-y-1">
                    <?php foreach ($away['substitutes'] as $p): 
                        $pid = $p['player']['id'];
                        $ps = $playerScores[$pid] ?? null;
                        $score = $ps['score'] ?? null;
                    ?>
                    <div class="flex items-center justify-between p-1.5 hover:bg-slate-700/50 rounded-lg transition opacity-60 gap-2">
                        <div class="flex items-center gap-1 shrink-0">
                            <span class="px-2 py-0.5 rounded text-xs font-bold <?= getScoreColor($score) ?>"><?= $score !== null ? number_format($score, 1) : '—' ?></span>
                            <?php if ($ps && $ps['source'] === 'sub_entered'): echo getSourceBadge($ps['source']); endif; ?>
                        </div>
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="text-sm truncate text-right"><?= $p['player']['name'] ?></span>
                            <span class="text-xs text-slate-500 font-mono w-4"><?= $p['player']['pos'] ?? '?' ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif (!empty($playerStats)): ?>
                <?php 
                $awayTeamId = $fixtureData['teams']['away']['id'];
                $awayStatsData = null;
                foreach ($playerStats as $t) { if (($t['team']['id'] ?? 0) == $awayTeamId) { $awayStatsData = $t; break; } }
                ?>
                <?php if ($awayStatsData): ?>
                <div class="flex justify-between items-center mb-4 border-b border-slate-700 pb-2">
                    <span class="px-2 py-1 bg-emerald-500/20 text-emerald-400 text-xs font-bold rounded-lg">da Player Stats</span>
                    <h3 class="font-bold text-lg"><?= $awayStatsData['team']['name'] ?></h3>
                </div>
                <div class="space-y-1">
                    <?php foreach ($awayStatsData['players'] as $entry):
                        $pid = $entry['player']['id'];
                        $ps = $playerScores[$pid] ?? null;
                        $score = $ps['score'] ?? null;
                        $stats = $entry['statistics'][0] ?? [];
                    ?>
                    <div class="flex items-center justify-between p-2 hover:bg-slate-700/50 rounded-lg transition gap-2">
                        <div class="flex items-center gap-1 shrink-0">
                            <span class="px-2 py-0.5 rounded text-xs font-bold <?= getScoreColor($score) ?>"><?= $score !== null ? number_format($score, 1) : 'SV' ?></span>
                            <?php if ($stats['games']['rating'] ?? null): ?>
                            <span class="text-[10px] text-slate-500 font-mono"><?= number_format((float)$stats['games']['rating'], 1) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="font-semibold text-sm truncate text-right"><?= $entry['player']['name'] ?></span>
                            <span class="text-xs text-slate-500 font-mono w-4"><?= $stats['games']['position'] ?? '?' ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="h-full flex flex-col items-center justify-center text-slate-500 p-6 text-center">
                    <p class="text-4xl mb-3">⏳</p>
                    <p>In attesa formazioni (Away)</p>
                    <p class="text-xs mt-2">Disponibili ~20-40 min prima del match</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Team Stats -->
    <?php if (!empty($rawStats['response'])): ?>
    <div class="bg-slate-800 rounded-2xl p-5 border border-slate-700">
        <h3 class="font-bold text-lg mb-4 text-slate-300">Statistiche Partita</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php foreach ($rawStats['response'] as $teamStat): ?>
            <div>
                <h4 class="font-bold text-sm mb-2"><?= $teamStat['team']['name'] ?? '?' ?></h4>
                <div class="space-y-1">
                    <?php foreach ($teamStat['statistics'] ?? [] as $stat): ?>
                    <div class="flex justify-between text-xs">
                        <span class="text-slate-400"><?= $stat['type'] ?></span>
                        <span class="font-mono font-bold"><?= $stat['value'] ?? '—' ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Raw data -->
    <div class="border border-slate-700 rounded-2xl bg-slate-900 overflow-hidden">
        <button onclick="document.getElementById('debug-content').classList.toggle('hidden')" class="w-full p-4 flex justify-between items-center bg-slate-800 hover:bg-slate-700 transition">
            <span class="font-bold text-yellow-500">🛠 Raw API Data</span>
            <span class="text-slate-400 text-sm">Clicca per espandere</span>
        </button>
        <div id="debug-content" class="hidden p-4 space-y-4">
            <div>
                <h4 class="text-emerald-400 font-bold mb-2">Lineups (<?= count($lineups) ?> teams)</h4>
                <pre class="bg-black text-green-400 p-4 rounded text-xs overflow-x-auto max-h-60 custom-scrollbar"><?= json_encode($rawLineups, JSON_PRETTY_PRINT) ?></pre>
            </div>
            <div>
                <h4 class="text-blue-400 font-bold mb-2">Events (<?= count($events) ?>)</h4>
                <pre class="bg-black text-blue-400 p-4 rounded text-xs overflow-x-auto max-h-60 custom-scrollbar"><?= json_encode($rawEvents, JSON_PRETTY_PRINT) ?></pre>
            </div>
            <div>
                <h4 class="text-purple-400 font-bold mb-2">Player Stats (<?= count($playerStats) ?> teams)</h4>
                <pre class="bg-black text-purple-400 p-4 rounded text-xs overflow-x-auto max-h-60 custom-scrollbar"><?= json_encode($rawPlayers, JSON_PRETTY_PRINT) ?></pre>
            </div>
            <div>
                <h4 class="text-cyan-400 font-bold mb-2">Team Statistics</h4>
                <pre class="bg-black text-cyan-400 p-4 rounded text-xs overflow-x-auto max-h-60 custom-scrollbar"><?= json_encode($rawStats, JSON_PRETTY_PRINT) ?></pre>
            </div>
            <div>
                <h4 class="text-amber-400 font-bold mb-2">Fantacalcio Engine (<?= count($playerScores) ?> players)</h4>
                <pre class="bg-black text-amber-400 p-4 rounded text-xs overflow-x-auto max-h-60 custom-scrollbar"><?= json_encode($playerScores, JSON_PRETTY_PRINT) ?></pre>
            </div>
        </div>
    </div>

    <?php endif; ?>

    <!-- Known leagues reference -->
    <div class="bg-slate-800/50 rounded-xl p-4 border border-slate-700/50">
        <details>
            <summary class="cursor-pointer text-xs text-slate-500 font-bold uppercase tracking-wider">League Config Reference</summary>
            <div class="mt-3 grid grid-cols-2 md:grid-cols-3 gap-2 text-xs">
                <?php foreach ($KNOWN_LEAGUES as $lg): ?>
                <div class="bg-slate-900 rounded-lg p-2 font-mono">
                    <span class="text-slate-400">league=</span><span class="text-blue-400"><?= $lg['id'] ?></span>
                    <span class="text-slate-400">&season=</span><span class="text-emerald-400"><?= $lg['season'] ?></span>
                    <div class="text-slate-500 text-[10px] mt-1"><?= $lg['name'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </details>
    </div>

</div>

</body>
</html>