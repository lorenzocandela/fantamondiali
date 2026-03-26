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
    if ($score === null) return 'score-sv';
    if ($score >= 7.0) return 'score-high';
    if ($score >= 6.0) return 'score-mid';
    if ($score >= 5.0) return 'score-warn';
    return 'score-low';
}

function getSourceBadge($source) {
    $map = [
        'players_stats' => ['⭐ Stats', 'background:rgba(16,185,129,0.12);color:#34d399'],
        'lineups'       => ['📋 XI', 'background:rgba(59,130,246,0.12);color:#60a5fa'],
        'lineups_sub'   => ['🪑 Bench', 'background:rgba(100,116,139,0.1);color:#64748b'],
        'sub_entered'   => ['🔄 Sub', 'background:rgba(6,182,212,0.12);color:#22d3ee'],
        'events_only'   => ['⚡ Evt', 'background:rgba(245,158,11,0.12);color:#fbbf24'],
    ];
    $s = $map[$source] ?? ['?', 'background:rgba(100,116,139,0.1);color:#64748b'];
    return "<span class=\"src-badge\" style=\"{$s[1]}\">{$s[0]}</span>";
}

function covBadge($val) {
    if ($val === true) return '<span class="cov-dot cov-ok"></span>';
    if ($val === false) return '<span class="cov-dot cov-no"></span>';
    return '<span class="cov-dot" style="background:#334155"></span>';
}
?>

<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>FM26 Debug Dashboard</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        colors: {
                            dark: { 900: '#0a0f1a', 800: '#111827', 700: '#1a2234', 600: '#243044', 500: '#2d3b52' },
                        }
                    }
                }
            }
        </script>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
            * { -webkit-tap-highlight-color: transparent; }
            body { font-family: 'Inter', sans-serif; background-color: #0a0f1a; color: #e2e8f0; -webkit-font-smoothing: antialiased; }
            .custom-scrollbar::-webkit-scrollbar { width: 4px; }
            .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
            .custom-scrollbar::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
            
            /* Soft card style */
            .card { background: #111827; border: 1px solid #1e293b; border-radius: 16px; }
            .card-inner { background: #0d1321; border: 1px solid #1a2234; border-radius: 12px; }
            
            /* Mobile grid fixes */
            @media (max-width: 768px) {
                body { padding: 8px !important; }
                .score-header { flex-direction: column; gap: 12px; }
                .score-header .team-side { width: 100%; justify-content: center; }
                .score-header .score-center { order: -1; }
                .lineup-grid { grid-template-columns: 1fr !important; }
                .lineup-grid .timeline-col { order: 3; }
                .stats-grid { grid-template-columns: repeat(3, 1fr) !important; }
                .search-grid { grid-template-columns: 1fr !important; }
            }
            @media (min-width: 769px) and (max-width: 1024px) {
                .lineup-grid { grid-template-columns: 1fr 1fr !important; }
                .lineup-grid .timeline-col { grid-column: span 2; order: 3; }
            }
            
            /* Animate pulse for live */
            .live-pulse { animation: softPulse 2s ease-in-out infinite; }
            @keyframes softPulse { 0%,100% { opacity: 1; } 50% { opacity: 0.5; } }
            
            /* Player rows */
            .player-row { transition: background 0.15s; border-radius: 8px; padding: 6px 8px; }
            .player-row:hover { background: rgba(255,255,255,0.03); }
            
            /* Score pills */
            .score-pill { padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; font-family: 'Inter', monospace; min-width: 36px; text-align: center; display: inline-block; }
            .score-high { background: rgba(16,185,129,0.15); color: #34d399; }
            .score-mid { background: rgba(59,130,246,0.15); color: #60a5fa; }
            .score-warn { background: rgba(245,158,11,0.15); color: #fbbf24; }
            .score-low { background: rgba(239,68,68,0.15); color: #f87171; }
            .score-sv { background: rgba(100,116,139,0.1); color: #64748b; }
            
            /* Source badges */
            .src-badge { padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: 700; white-space: nowrap; }
            
            /* Coverage dots */
            .cov-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
            .cov-ok { background: #34d399; }
            .cov-no { background: #f87171; }
            
            /* Event card */
            .event-card { background: #111827; border: 1px solid #1e293b; border-radius: 10px; padding: 10px 12px; transition: all 0.15s; }
            .event-card:hover { border-color: #334155; }
        </style>
        <?php
        if ($fixtureId && isset($status) && in_array($status, ['1H','HT','2H','ET','P','BT','LIVE'])):
        ?>
        <meta http-equiv="refresh" content="60">
        <?php endif; ?>
    </head>
    <body class="p-2 md:p-6 lg:p-8">
        <div class="max-w-7xl mx-auto space-y-4 md:space-y-6">

            <!-- HEADER -->
            <div class="card p-3 md:p-4 flex flex-col gap-3">
                <div class="flex justify-between items-center">
                    <a href="?" class="text-lg md:text-xl font-black bg-gradient-to-r from-emerald-400 to-blue-400 bg-clip-text text-transparent">
                        ⚽ FM26 Debug
                    </a>
                    <div class="flex items-center gap-2 text-[10px] md:text-xs text-slate-600 font-mono">
                        <span>calls: <?= $apiCallCount ?></span>
                        <span><?= date('H:i:s') ?></span>
                        <?php if ($fixtureId && isset($status) && in_array($status, ['1H','HT','2H','ET','P','BT','LIVE'])): ?>
                        <span class="px-2 py-1 rounded-full text-[10px] font-bold live-pulse" style="background:rgba(239,68,68,0.15);color:#f87171">● LIVE 60s</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!$fixtureId): ?>
                <form method="GET" class="flex flex-wrap gap-2">
                    <input type="date" name="date" value="<?= htmlspecialchars($searchDate) ?>" class="flex-1 min-w-[130px] bg-dark-900 border border-dark-600 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500/50">
                    <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Es. Italy" class="flex-1 min-w-[100px] bg-dark-900 border border-dark-600 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500/50 placeholder-slate-600">
                    <button type="submit" class="bg-blue-600/80 hover:bg-blue-500 text-white px-4 py-2 rounded-lg text-sm font-bold transition">Cerca</button>
                </form>
                <?php else: ?>
                <a href="?" class="inline-flex items-center gap-1 text-sm text-slate-400 hover:text-white transition">← Torna alla ricerca</a>
                <?php endif; ?>
            </div>

            <?php if (!$fixtureId): ?>
            <!-- ═══════════════════ SEARCH RESULTS ═══════════════════ -->
            <div class="card p-4 md:p-6 min-h-[40vh]">
                <h2 class="text-base md:text-xl font-bold mb-4"><?= date('d/m/Y', strtotime($searchDate)) ?> <?= $searchQuery ? "— \"$searchQuery\"" : '' ?>
                    <span class="text-xs text-slate-600 font-normal ml-2">(<?= count($matchesList) ?>)</span>
                </h2>
                
                <?php if (empty($matchesList)): ?>
                    <div class="text-center text-slate-600 py-10">Nessuna partita trovata.</div>
                <?php else: ?>
                    <div class="search-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <?php foreach ($matchesList as $m): 
                            $st = $m['fixture']['status']['short'] ?? 'NS';
                            $stColor = match(true) {
                                in_array($st, ['1H','2H','HT','ET','LIVE']) => 'color:#f87171',
                                in_array($st, ['FT','AET','PEN']) => 'color:#34d399',
                                default => 'color:#64748b',
                            };
                        ?>
                        <div class="card-inner p-3 hover:border-blue-500/30 transition">
                            <div class="flex justify-between text-[10px] text-slate-500 mb-2 pb-2 border-b border-dark-600">
                                <span class="truncate"><?= $m['league']['name'] ?> <span class="text-slate-700"><?= $m['league']['id'] ?></span></span>
                                <span class="font-bold" style="<?= $stColor ?>"><?= $st ?></span>
                            </div>
                            <div class="flex justify-between items-center my-2">
                                <div class="flex items-center gap-1.5 w-2/5 min-w-0">
                                    <img src="<?= $m['teams']['home']['logo'] ?>" class="w-5 h-5 object-contain shrink-0">
                                    <span class="font-semibold text-xs truncate"><?= $m['teams']['home']['name'] ?></span>
                                </div>
                                <div class="text-sm font-black text-slate-300 px-2 shrink-0">
                                    <?= $m['goals']['home'] ?? '-' ?>:<?= $m['goals']['away'] ?? '-' ?>
                                </div>
                                <div class="flex items-center justify-end gap-1.5 w-2/5 min-w-0">
                                    <span class="font-semibold text-xs truncate text-right"><?= $m['teams']['away']['name'] ?></span>
                                    <img src="<?= $m['teams']['away']['logo'] ?>" class="w-5 h-5 object-contain shrink-0">
                                </div>
                            </div>
                            <a href="?fixture=<?= $m['fixture']['id'] ?>" class="mt-2 block text-center py-1.5 rounded-lg text-[10px] font-bold transition" style="background:rgba(59,130,246,0.1);color:#60a5fa;border:1px solid rgba(59,130,246,0.2)">
                                ID:<?= $m['fixture']['id'] ?> · L<?= $m['league']['id'] ?>/S<?= $m['league']['season'] ?>
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
            <div class="card p-4 md:p-6">
                <div class="score-header flex items-center justify-between gap-4">
                    <div class="team-side flex items-center gap-3 flex-1 min-w-0 justify-end">
                        <h2 class="text-sm md:text-xl font-bold text-slate-200 text-right truncate"><?= $fixtureData['teams']['home']['name'] ?></h2>
                        <img src="<?= $fixtureData['teams']['home']['logo'] ?>" class="w-10 h-10 md:w-14 md:h-14 object-contain shrink-0">
                    </div>
                    <div class="score-center flex flex-col items-center shrink-0 px-2">
                        <?php 
                        $st = $fixtureData['fixture']['status']['short'];
                        $elapsed = $fixtureData['fixture']['status']['elapsed'];
                        $isLive = in_array($st, ['1H','2H','HT','ET','LIVE']);
                        ?>
                        <span class="text-[10px] md:text-xs font-bold uppercase tracking-wider mb-1 <?= $isLive ? 'live-pulse' : '' ?>" style="color:<?= $isLive ? '#f87171' : '#34d399' ?>">
                            <?= $st ?> <?= $elapsed ? $elapsed . "'" : '' ?>
                        </span>
                        <div class="text-3xl md:text-5xl font-extrabold text-white tracking-tight">
                            <?= $fixtureData['goals']['home'] ?? 0 ?> — <?= $fixtureData['goals']['away'] ?? 0 ?>
                        </div>
                        <div class="text-[9px] md:text-xs text-slate-500 mt-1 text-center font-mono">
                            <?= $fixtureData['league']['name'] ?> · L<?= $fixtureData['league']['id'] ?>/S<?= $fixtureData['league']['season'] ?> · #<?= $fixtureId ?>
                        </div>
                    </div>
                    <div class="team-side flex items-center gap-3 flex-1 min-w-0">
                        <img src="<?= $fixtureData['teams']['away']['logo'] ?>" class="w-10 h-10 md:w-14 md:h-14 object-contain shrink-0">
                        <h2 class="text-sm md:text-xl font-bold text-slate-200 truncate"><?= $fixtureData['teams']['away']['name'] ?></h2>
                    </div>
                </div>
            </div>

            <!-- Coverage + Data availability -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <?php if ($coverage): ?>
                <div class="card p-3">
                    <h3 class="text-[10px] text-slate-500 uppercase tracking-wider font-bold mb-2">Coverage</h3>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <?php foreach ([
                            'Events' => $coverage['fixtures']['events'] ?? null,
                            'Lineups' => $coverage['fixtures']['lineups'] ?? null,
                            'Stats Fix' => $coverage['fixtures']['statistics_fixtures'] ?? null,
                            'Stats Players' => $coverage['fixtures']['statistics_players'] ?? null,
                            'Standings' => $coverage['standings'] ?? null,
                            'Players' => $coverage['players'] ?? null,
                        ] as $label => $val): ?>
                        <div class="flex items-center gap-2 text-slate-400"><?= covBadge($val) ?> <?= $label ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="card p-3">
                    <h3 class="text-[10px] text-slate-500 uppercase tracking-wider font-bold mb-2">Dati disponibili</h3>
                    <div class="stats-grid grid grid-cols-5 gap-2">
                        <?php
                        $checks = [
                            ['Lin', count($lineups), $lineups ? '#34d399' : '#334155'],
                            ['Evt', count($events), $events ? '#60a5fa' : '#334155'],
                            ['Pl.S', count($playerStats) ? array_sum(array_map(fn($t) => count($t['players'] ?? []), $playerStats)) : 0, $playerStats ? '#34d399' : '#334155'],
                            ['T.S', count($rawStats['response'] ?? []), ($rawStats['response'] ?? []) ? '#22d3ee' : '#334155'],
                            ['Voti', count(array_filter($playerScores, fn($p) => $p['score'] !== null)), $playerScores ? '#fbbf24' : '#334155'],
                        ];
                        foreach ($checks as [$label, $count, $color]):
                        ?>
                        <div class="text-center">
                            <div class="text-lg md:text-xl font-black" style="color:<?= $color ?>"><?= $count ?></div>
                            <div class="text-[9px] text-slate-500 font-semibold"><?= $label ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- 3-column layout (stacks on mobile) -->
            <div class="lineup-grid grid grid-cols-1 md:grid-cols-4 gap-3 md:gap-6">
                
                <!-- HOME LINEUP -->
                <div class="col-span-1 card p-4">
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
                            <div class="player-row flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="text-xs text-slate-500 font-mono w-4"><?= $p['player']['pos'] ?? '?' ?></span>
                                    <span class="font-semibold text-sm truncate"><?= $p['player']['name'] ?></span>
                                </div>
                                <div class="flex items-center gap-1 shrink-0">
                                    <?php if ($ps): echo getSourceBadge($ps['source']); endif; ?>
                                    <span class="score-pill <?= getScoreColor($score) ?>"><?= $score !== null ? number_format($score, 1) : 'SV' ?></span>
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
                            <div class="player-row flex items-center justify-between opacity-60 gap-2">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="text-xs text-slate-500 font-mono w-4"><?= $p['player']['pos'] ?? '?' ?></span>
                                    <span class="text-sm truncate"><?= $p['player']['name'] ?></span>
                                </div>
                                <div class="flex items-center gap-1 shrink-0">
                                    <?php if ($ps && $ps['source'] === 'sub_entered'): echo getSourceBadge($ps['source']); endif; ?>
                                    <span class="score-pill <?= getScoreColor($score) ?>"><?= $score !== null ? number_format($score, 1) : '—' ?></span>
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
                            <div class="player-row flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="text-xs text-slate-500 font-mono w-4"><?= $stats['games']['position'] ?? '?' ?></span>
                                    <span class="font-semibold text-sm truncate"><?= $entry['player']['name'] ?></span>
                                    <span class="text-[10px] text-slate-500"><?= $stats['games']['minutes'] ?? 0 ?>'</span>
                                </div>
                                <div class="flex items-center gap-1 shrink-0">
                                    <?php if ($stats['games']['rating'] ?? null): ?>
                                    <span class="text-[10px] text-slate-500 font-mono"><?= number_format((float)$stats['games']['rating'], 1) ?></span>
                                    <?php endif; ?>
                                    <span class="score-pill <?= getScoreColor($score) ?>"><?= $score !== null ? number_format($score, 1) : 'SV' ?></span>
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
                <div class="col-span-1 md:col-span-2 timeline-col card p-4">
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
                            <div class="event-card flex items-center gap-3 relative overflow-hidden">
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
                <div class="col-span-1 card p-4">
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
                            <div class="player-row flex items-center justify-between gap-2">
                                <div class="flex items-center gap-1 shrink-0">
                                    <span class="score-pill <?= getScoreColor($score) ?>"><?= $score !== null ? number_format($score, 1) : 'SV' ?></span>
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
                            <div class="player-row flex items-center justify-between opacity-60 gap-2">
                                <div class="flex items-center gap-1 shrink-0">
                                    <span class="score-pill <?= getScoreColor($score) ?>"><?= $score !== null ? number_format($score, 1) : '—' ?></span>
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
                            <div class="player-row flex items-center justify-between gap-2">
                                <div class="flex items-center gap-1 shrink-0">
                                    <span class="score-pill <?= getScoreColor($score) ?>"><?= $score !== null ? number_format($score, 1) : 'SV' ?></span>
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
            <div class="card p-4">
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
            <div class="card overflow-hidden">
                <button onclick="document.getElementById('debug-content').classList.toggle('hidden')" class="w-full p-4 flex justify-between items-center card-inner hover:border-dark-500 transition">
                    <span class="font-bold text-yellow-500">🛠 Raw API Data</span>
                    <span class="text-slate-400 text-sm">Clicca per espandere</span>
                </button>
                <div id="debug-content" class="hidden p-4 space-y-4">
                    <div>
                        <h4 class="text-emerald-400 font-bold mb-2">Lineups (<?= count($lineups) ?> teams)</h4>
                        <pre class="card-inner text-emerald-400/70 p-4 rounded text-xs overflow-x-auto max-h-60 custom-scrollbar"><?= json_encode($rawLineups, JSON_PRETTY_PRINT) ?></pre>
                    </div>
                    <div>
                        <h4 class="text-blue-400 font-bold mb-2">Events (<?= count($events) ?>)</h4>
                        <pre class="card-inner text-blue-400/70 p-4 rounded text-xs overflow-x-auto max-h-60 custom-scrollbar"><?= json_encode($rawEvents, JSON_PRETTY_PRINT) ?></pre>
                    </div>
                    <div>
                        <h4 class="text-purple-400 font-bold mb-2">Player Stats (<?= count($playerStats) ?> teams)</h4>
                        <pre class="card-inner text-purple-400/70 p-4 rounded text-xs overflow-x-auto max-h-60 custom-scrollbar"><?= json_encode($rawPlayers, JSON_PRETTY_PRINT) ?></pre>
                    </div>
                    <div>
                        <h4 class="text-cyan-400 font-bold mb-2">Team Statistics</h4>
                        <pre class="card-inner text-cyan-400/70 p-4 rounded text-xs overflow-x-auto max-h-60 custom-scrollbar"><?= json_encode($rawStats, JSON_PRETTY_PRINT) ?></pre>
                    </div>
                    <div>
                        <h4 class="text-amber-400 font-bold mb-2">Fantacalcio Engine (<?= count($playerScores) ?> players)</h4>
                        <pre class="card-inner text-amber-400/70 p-4 rounded text-xs overflow-x-auto max-h-60 custom-scrollbar"><?= json_encode($playerScores, JSON_PRETTY_PRINT) ?></pre>
                    </div>
                </div>
            </div>

            <?php endif; ?>

            <!-- Known leagues reference -->
            <div class="card p-3 opacity-60">
                <details>
                    <summary class="cursor-pointer text-xs text-slate-500 font-bold uppercase tracking-wider">League Config Reference</summary>
                    <div class="mt-3 grid grid-cols-2 md:grid-cols-3 gap-2 text-xs">
                        <?php foreach ($KNOWN_LEAGUES as $lg): ?>
                        <div class="card-inner rounded-lg p-2 font-mono">
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