<?php
define('API_KEY', '1a4942a032906326bcdaa564e10dbe65');
define('API_BASE', 'https://v3.football.api-sports.io/');

// score table (same as app)
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
    curl_close($ch);
    return ['code' => $code, 'data' => json_decode($resp, true) ?: []];
}

// params
$fixtureId   = $_GET['fixture'] ?? null;
$searchDate  = $_GET['date'] ?? date('Y-m-d');
$searchQuery = $_GET['search'] ?? '';
$showCoverage = isset($_GET['coverage']);
$apiCallCount = 0;

// league config
$KNOWN_LEAGUES = [
    ['id' => 32,   'season' => 2024, 'name' => 'WC Qual Europe',          'priority' => 1],
    ['id' => 1,    'season' => 2026, 'name' => 'World Cup 2026',          'priority' => 2],
    ['id' => 1222, 'season' => 2026, 'name' => 'FIFA Series',             'priority' => 3],
    ['id' => 960,  'season' => 2026, 'name' => 'UEFA Playoff WC',         'priority' => 4],
    ['id' => 37,   'season' => 2026, 'name' => 'Intercontinental Playoff','priority' => 5],
    ['id' => 5,    'season' => 2026, 'name' => 'Friendlies',              'priority' => 6],
];

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
    $r = apiGet("fixtures?id={$fixtureId}"); $apiCallCount++;
    $fixtureData = $r['data']['response'][0] ?? null;

    if ($fixtureData) {
        $leagueId = $fixtureData['league']['id'] ?? null;
        $season   = $fixtureData['league']['season'] ?? null;

        if ($leagueId && $season) {
            $r = apiGet("leagues?id={$leagueId}&season={$season}"); $apiCallCount++;
            $covData = $r['data']['response'][0]['seasons'] ?? [];
            foreach ($covData as $s) {
                if ($s['year'] == $season) { $coverage = $s['coverage'] ?? null; break; }
            }
        }

        $r = apiGet("fixtures/lineups?fixture={$fixtureId}"); $apiCallCount++;
        $rawLineups = $r['data'];
        $lineups = $r['data']['response'] ?? [];

        $r = apiGet("fixtures/events?fixture={$fixtureId}"); $apiCallCount++;
        $rawEvents = $r['data'];
        $events = $r['data']['response'] ?? [];

        $r = apiGet("fixtures/players?fixture={$fixtureId}"); $apiCallCount++;
        $rawPlayers = $r['data'];
        $playerStats = $r['data']['response'] ?? [];

        $r = apiGet("fixtures/statistics?fixture={$fixtureId}"); $apiCallCount++;
        $rawStats = $r['data'];

        // fantacalcio engine
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
        } elseif (!empty($lineups)) {
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
                    if ($assistId && isset($playerScores[$assistId]) && $playerScores[$assistId]['score'] === null) {
                        $playerScores[$assistId]['score'] = 6.0;
                        $playerScores[$assistId]['rating'] = 6.0;
                        $playerScores[$assistId]['source'] = 'sub_entered';
                    }
                }
            }
        } elseif (!empty($events)) {
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
                        $playerScores[$pid]['score'] += 6;
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

function getEventIcon($type, $detail) {
    if ($type === 'Goal' && stripos($detail, 'Own') !== false) return '<span class="ev-icon ev-owngoal">OG</span>';
    if ($type === 'Goal' && $detail === 'Missed Penalty') return '<span class="ev-icon ev-miss">×P</span>';
    if ($type === 'Goal') return '<span class="ev-icon ev-goal">G</span>';
    if ($type === 'Card' && str_contains($detail, 'Yellow')) return '<span class="ev-icon ev-yellow">Y</span>';
    if ($type === 'Card' && str_contains($detail, 'Red')) return '<span class="ev-icon ev-red">R</span>';
    if ($type === 'subst') return '<span class="ev-icon ev-sub">↔</span>';
    if ($type === 'Var') return '<span class="ev-icon ev-var">V</span>';
    return '<span class="ev-icon ev-other">!</span>';
}

function getScoreColor($score) {
    if ($score === null) return 'pill-sv';
    if ($score >= 7.0) return 'pill-high';
    if ($score >= 6.0) return 'pill-mid';
    if ($score >= 5.0) return 'pill-warn';
    return 'pill-low';
}

function getSourceBadge($source) {
    $map = [
        'players_stats' => ['Stats', 'src-stats'],
        'lineups'       => ['XI',    'src-xi'],
        'lineups_sub'   => ['Bench', 'src-bench'],
        'sub_entered'   => ['Sub',   'src-sub'],
        'events_only'   => ['Evt',   'src-evt'],
    ];
    $s = $map[$source] ?? ['?', 'src-bench'];
    return "<span class=\"src-badge {$s[1]}\">{$s[0]}</span>";
}

function covBadge($val) {
    if ($val === true) return '<span class="cov-dot cov-ok"></span>';
    if ($val === false) return '<span class="cov-dot cov-no"></span>';
    return '<span class="cov-dot cov-na"></span>';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>FM26 Debug</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <?php if ($fixtureId && isset($status) && in_array($status, ['1H','HT','2H','ET','P','BT','LIVE'])): ?>
    <meta http-equiv="refresh" content="60">
    <?php endif; ?>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }

        :root {
            --bg:        #f4f6f9;
            --surface:   #ffffff;
            --surface2:  #f8f9fb;
            --border:    #e8eaef;
            --border2:   #d8dce6;
            --text-1:    #111827;
            --text-2:    #4b5563;
            --text-3:    #9ca3af;
            --text-4:    #c5cad5;
            --accent:    #3b6cf4;
            --accent-bg: rgba(59,108,244,0.08);
            --green:     #16a34a;
            --green-bg:  rgba(22,163,74,0.08);
            --red:       #dc2626;
            --red-bg:    rgba(220,38,38,0.08);
            --amber:     #d97706;
            --amber-bg:  rgba(217,119,6,0.08);
            --cyan:      #0891b2;
            --cyan-bg:   rgba(8,145,178,0.08);
            --pill-r:    8px;
            --card-r:    16px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
            --shadow:    0 4px 16px rgba(0,0,0,0.07), 0 1px 4px rgba(0,0,0,0.04);
        }

        html { font-size: 15px; }
        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text-1);
            -webkit-font-smoothing: antialiased;
            min-height: 100dvh;
            padding: env(safe-area-inset-top) env(safe-area-inset-right) env(safe-area-inset-bottom) env(safe-area-inset-left);
        }

        .page { max-width: 1100px; margin: 0 auto; padding: 12px; display: flex; flex-direction: column; gap: 10px; }
        @media (min-width: 768px) { .page { padding: 20px 24px; gap: 14px; } }

        /* cards */
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--card-r);
            box-shadow: var(--shadow-sm);
        }
        .card-inner {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 10px;
        }
        .card-pad { padding: 14px 16px; }
        @media (min-width: 768px) { .card-pad { padding: 20px 24px; } }

        /* header */
        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }
        .logo {
            font-size: 1rem;
            font-weight: 800;
            color: var(--text-1);
            text-decoration: none;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .logo-dot { width: 8px; height: 8px; background: var(--accent); border-radius: 50%; }
        .top-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            color: var(--text-3);
        }
        .meta-chip {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 3px 8px;
        }

        /* search form */
        .search-form { display: flex; gap: 8px; flex-wrap: wrap; }
        .search-form input {
            flex: 1;
            min-width: 110px;
            background: var(--surface2);
            border: 1px solid var(--border2);
            border-radius: 10px;
            padding: 9px 12px;
            font-size: 14px;
            font-family: 'DM Sans', sans-serif;
            color: var(--text-1);
            outline: none;
            transition: border-color 0.15s;
        }
        .search-form input:focus { border-color: var(--accent); }
        .search-form input::placeholder { color: var(--text-4); }
        .btn {
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 9px 18px;
            font-size: 14px;
            font-weight: 700;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            transition: opacity 0.15s;
            white-space: nowrap;
        }
        .btn:hover { opacity: 0.88; }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
            color: var(--text-2);
            text-decoration: none;
            font-weight: 500;
        }
        .back-link:hover { color: var(--text-1); }

        /* live pill */
        .live-badge {
            background: var(--red-bg);
            color: var(--red);
            border: 1px solid rgba(220,38,38,0.2);
            border-radius: 20px;
            padding: 3px 10px;
            font-size: 11px;
            font-weight: 700;
            animation: pulse 2s ease-in-out infinite;
        }
        @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.5; } }

        /* section label */
        .section-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-3);
            margin-bottom: 10px;
        }

        /* score header */
        .score-header {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 12px;
            text-align: center;
        }
        .team-side { display: flex; flex-direction: column; align-items: center; gap: 6px; }
        .team-side img { width: 44px; height: 44px; object-fit: contain; }
        @media (min-width: 768px) { .team-side img { width: 60px; height: 60px; } }
        .team-name { font-size: 14px; font-weight: 700; color: var(--text-1); line-height: 1.2; }
        @media (min-width: 768px) { .team-name { font-size: 18px; } }
        .score-center { display: flex; flex-direction: column; align-items: center; gap: 4px; min-width: 80px; }
        .score-val {
            font-size: 36px;
            font-weight: 800;
            letter-spacing: -2px;
            color: var(--text-1);
            font-family: 'JetBrains Mono', monospace;
            line-height: 1;
        }
        @media (min-width: 768px) { .score-val { font-size: 52px; } }
        .score-status { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; }
        .score-meta { font-size: 10px; color: var(--text-3); font-family: 'JetBrains Mono', monospace; }

        /* stats grid */
        .data-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 6px;
        }
        .data-cell { text-align: center; padding: 10px 4px; }
        .data-num { font-size: 22px; font-weight: 800; line-height: 1; }
        .data-lbl { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-3); margin-top: 3px; }

        /* coverage */
        .cov-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
        .cov-row { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--text-2); }
        .cov-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
        .cov-ok { background: var(--green); }
        .cov-no { background: var(--red); }
        .cov-na { background: var(--border2); }

        /* score pills */
        .pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 38px;
            padding: 3px 7px;
            border-radius: 7px;
            font-size: 11px;
            font-weight: 700;
            font-family: 'JetBrains Mono', monospace;
            white-space: nowrap;
        }
        .pill-high { background: var(--green-bg); color: var(--green); }
        .pill-mid  { background: var(--accent-bg); color: var(--accent); }
        .pill-warn { background: var(--amber-bg); color: var(--amber); }
        .pill-low  { background: var(--red-bg); color: var(--red); }
        .pill-sv   { background: var(--surface2); color: var(--text-3); border: 1px solid var(--border); }

        /* source badges */
        .src-badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 5px;
            border-radius: 5px;
            font-size: 9px;
            font-weight: 700;
            white-space: nowrap;
        }
        .src-stats { background: var(--green-bg); color: var(--green); }
        .src-xi    { background: var(--accent-bg); color: var(--accent); }
        .src-bench { background: var(--surface2); color: var(--text-3); border: 1px solid var(--border); }
        .src-sub   { background: var(--cyan-bg); color: var(--cyan); }
        .src-evt   { background: var(--amber-bg); color: var(--amber); }

        /* lineup 3-col */
        .lineup-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
        }
        @media (min-width: 600px) {
            .lineup-grid { grid-template-columns: 1fr 1fr; }
            .timeline-col { grid-column: span 2; order: 3; }
        }
        @media (min-width: 900px) {
            .lineup-grid { grid-template-columns: 1fr 1.6fr 1fr; }
            .timeline-col { grid-column: auto; order: 0; }
        }

        /* player rows */
        .player-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding: 6px 8px;
            border-radius: 8px;
            transition: background 0.12s;
        }
        .player-row:hover { background: var(--surface2); }
        .player-left { display: flex; align-items: center; gap: 6px; min-width: 0; flex: 1; }
        .player-right { display: flex; align-items: center; gap: 4px; flex-shrink: 0; }
        .player-pos {
            font-family: 'JetBrains Mono', monospace;
            font-size: 10px;
            font-weight: 600;
            color: var(--text-3);
            width: 16px;
            flex-shrink: 0;
        }
        .player-name { font-size: 13px; font-weight: 600; color: var(--text-1); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .player-min { font-size: 10px; color: var(--text-3); font-family: 'JetBrains Mono', monospace; }
        .player-rating { font-size: 10px; color: var(--text-3); font-family: 'JetBrains Mono', monospace; }
        .player-sub { opacity: 0.55; }

        .lineup-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 10px;
            margin-bottom: 10px;
            border-bottom: 1px solid var(--border);
        }
        .lineup-team { font-size: 15px; font-weight: 800; }
        .formation-badge {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: 700;
            font-family: 'JetBrains Mono', monospace;
            color: var(--text-2);
        }
        .sublist-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-3);
            margin: 10px 0 4px;
        }

        /* events */
        .event-list { display: flex; flex-direction: column; gap: 6px; max-height: 560px; overflow-y: auto; }
        .event-list::-webkit-scrollbar { width: 3px; }
        .event-list::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 3px; }
        .event-card {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: var(--surface);
            transition: border-color 0.12s;
            position: relative;
            overflow: hidden;
        }
        .event-card:hover { border-color: var(--border2); }
        .event-card.home-ev { border-left: 3px solid var(--accent); }
        .event-card.away-ev { border-left: 3px solid var(--green); }
        .event-time {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            font-weight: 600;
            color: var(--text-3);
            width: 32px;
            flex-shrink: 0;
            text-align: right;
        }
        .ev-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 26px;
            height: 26px;
            border-radius: 7px;
            font-size: 10px;
            font-weight: 800;
            flex-shrink: 0;
        }
        .ev-goal    { background: var(--green-bg); color: var(--green); }
        .ev-owngoal { background: var(--red-bg); color: var(--red); }
        .ev-yellow  { background: var(--amber-bg); color: var(--amber); }
        .ev-red     { background: var(--red-bg); color: var(--red); }
        .ev-sub     { background: var(--cyan-bg); color: var(--cyan); }
        .ev-var     { background: var(--surface2); color: var(--text-2); border: 1px solid var(--border); }
        .ev-miss    { background: var(--surface2); color: var(--text-3); border: 1px solid var(--border); }
        .ev-other   { background: var(--surface2); color: var(--text-3); border: 1px solid var(--border); }
        .event-info { flex: 1; min-width: 0; }
        .event-main { font-size: 13px; font-weight: 600; color: var(--text-1); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .event-sub-text { font-size: 11px; color: var(--text-3); margin-top: 1px; }

        /* team stats */
        .stat-row { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid var(--border); }
        .stat-row:last-child { border-bottom: none; }
        .stat-key { font-size: 12px; color: var(--text-2); }
        .stat-val { font-size: 12px; font-weight: 700; font-family: 'JetBrains Mono', monospace; }

        /* raw data accordion */
        .accordion summary {
            cursor: pointer;
            list-style: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            font-size: 13px;
            font-weight: 700;
            color: var(--text-2);
            user-select: none;
        }
        .accordion summary::-webkit-details-marker { display: none; }
        .accordion summary:hover { color: var(--text-1); }
        .accordion[open] summary { border-bottom: 1px solid var(--border); }
        .accordion-body { padding: 14px 16px; display: flex; flex-direction: column; gap: 12px; }
        .raw-label { font-size: 11px; font-weight: 700; color: var(--text-2); margin-bottom: 6px; }
        .raw-pre {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 10px;
            font-family: 'JetBrains Mono', monospace;
            color: var(--text-2);
            overflow-x: auto;
            max-height: 200px;
            overflow-y: auto;
            white-space: pre;
        }
        .raw-pre::-webkit-scrollbar { width: 3px; height: 3px; }
        .raw-pre::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 3px; }

        /* search match cards */
        .match-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 8px;
        }
        @media (min-width: 480px) { .match-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (min-width: 900px) { .match-grid { grid-template-columns: repeat(3, 1fr); } }

        .match-card {
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--surface);
            padding: 12px;
            transition: border-color 0.12s, box-shadow 0.12s;
        }
        .match-card:hover { border-color: var(--border2); box-shadow: var(--shadow); }
        .match-league {
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            color: var(--text-3);
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border);
        }
        .match-league span:first-child { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; min-width: 0; }
        .match-teams { display: flex; align-items: center; justify-content: space-between; gap: 4px; margin-bottom: 10px; }
        .match-team { display: flex; align-items: center; gap: 5px; width: 38%; min-width: 0; }
        .match-team.away { flex-direction: row-reverse; }
        .match-team img { width: 18px; height: 18px; object-fit: contain; flex-shrink: 0; }
        .match-team-name { font-size: 11px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .match-score { font-size: 16px; font-weight: 800; font-family: 'JetBrains Mono', monospace; text-align: center; flex-shrink: 0; }
        .match-link {
            display: block;
            text-align: center;
            padding: 7px;
            background: var(--accent-bg);
            color: var(--accent);
            border-radius: 8px;
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
            transition: background 0.12s;
            font-family: 'JetBrains Mono', monospace;
        }
        .match-link:hover { background: rgba(59,108,244,0.14); }

        /* status colors */
        .st-live { color: var(--red); }
        .st-ft   { color: var(--green); }
        .st-ns   { color: var(--text-3); }

        /* league config */
        .league-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px; }
        @media (min-width: 600px) { .league-grid { grid-template-columns: repeat(3, 1fr); } }
        .league-item {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 8px 10px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 10px;
        }
        .league-id { color: var(--accent); font-weight: 600; }
        .league-season { color: var(--green); font-weight: 600; }
        .league-name { color: var(--text-3); font-size: 9px; margin-top: 3px; }

        /* empty / wait state */
        .empty-state { padding: 40px 20px; text-align: center; color: var(--text-3); font-size: 14px; }

        /* two-col for coverage + data */
        .info-grid { display: grid; grid-template-columns: 1fr; gap: 10px; }
        @media (min-width: 600px) { .info-grid { grid-template-columns: 1fr 1fr; } }
    </style>
</head>
<body>
<div class="page">

    <!-- top bar -->
    <div class="card card-pad">
        <div class="top-bar">
            <a href="?" class="logo">
                <div class="logo-dot"></div>
                FM26 Debug
            </a>
            <div class="top-meta">
                <span class="meta-chip"><?= $apiCallCount ?> calls</span>
                <span class="meta-chip"><?= date('H:i:s') ?></span>
                <?php if ($fixtureId && isset($status) && in_array($status, ['1H','HT','2H','ET','P','BT','LIVE'])): ?>
                <span class="live-badge">LIVE 60s</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$fixtureId): ?>
        <form method="GET" class="search-form" style="margin-top:12px">
            <input type="date" name="date" value="<?= htmlspecialchars($searchDate) ?>">
            <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Es. Italy">
            <button type="submit" class="btn">Cerca</button>
        </form>
        <?php else: ?>
        <a href="?" class="back-link" style="margin-top:10px;display:inline-flex">← Torna alla ricerca</a>
        <?php endif; ?>
    </div>


    <?php if (!$fixtureId): ?>
    <!-- search results -->
    <div class="card card-pad">
        <div class="section-label">
            <?= date('d/m/Y', strtotime($searchDate)) ?>
            <?= $searchQuery ? " — \"{$searchQuery}\"" : '' ?>
            <span style="color:var(--text-4);margin-left:4px">(<?= count($matchesList) ?>)</span>
        </div>

        <?php if (empty($matchesList)): ?>
            <div class="empty-state">Nessuna partita trovata.</div>
        <?php else: ?>
            <div class="match-grid">
                <?php foreach ($matchesList as $m):
                    $st = $m['fixture']['status']['short'] ?? 'NS';
                    $stCls = match(true) {
                        in_array($st, ['1H','2H','HT','ET','LIVE']) => 'st-live',
                        in_array($st, ['FT','AET','PEN'])           => 'st-ft',
                        default => 'st-ns',
                    };
                ?>
                <div class="match-card">
                    <div class="match-league">
                        <span><?= htmlspecialchars($m['league']['name']) ?> <span style="color:var(--text-4)">#<?= $m['league']['id'] ?></span></span>
                        <span class="<?= $stCls ?>" style="font-weight:700;flex-shrink:0;margin-left:8px"><?= $st ?></span>
                    </div>
                    <div class="match-teams">
                        <div class="match-team">
                            <img src="<?= $m['teams']['home']['logo'] ?>" alt="">
                            <span class="match-team-name"><?= htmlspecialchars($m['teams']['home']['name']) ?></span>
                        </div>
                        <div class="match-score"><?= $m['goals']['home'] ?? '–' ?>:<?= $m['goals']['away'] ?? '–' ?></div>
                        <div class="match-team away">
                            <img src="<?= $m['teams']['away']['logo'] ?>" alt="">
                            <span class="match-team-name"><?= htmlspecialchars($m['teams']['away']['name']) ?></span>
                        </div>
                    </div>
                    <a href="?fixture=<?= $m['fixture']['id'] ?>" class="match-link">
                        ID:<?= $m['fixture']['id'] ?> · L<?= $m['league']['id'] ?>/S<?= $m['league']['season'] ?>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>


    <?php if ($fixtureId && $fixtureData):
        $st = $fixtureData['fixture']['status']['short'];
        $elapsed = $fixtureData['fixture']['status']['elapsed'];
        $isLive = in_array($st, ['1H','2H','HT','ET','LIVE']);
        $stColor = $isLive ? 'var(--red)' : ($st === 'FT' ? 'var(--green)' : 'var(--text-3)');
    ?>

    <!-- score header -->
    <div class="card card-pad">
        <div class="score-header">
            <div class="team-side">
                <img src="<?= $fixtureData['teams']['home']['logo'] ?>" alt="">
                <div class="team-name"><?= htmlspecialchars($fixtureData['teams']['home']['name']) ?></div>
            </div>
            <div class="score-center">
                <span class="score-status <?= $isLive ? 'live-badge' : '' ?>" style="color:<?= $stColor ?>">
                    <?= $st ?><?= $elapsed ? " {$elapsed}'" : '' ?>
                </span>
                <div class="score-val"><?= $fixtureData['goals']['home'] ?? 0 ?>–<?= $fixtureData['goals']['away'] ?? 0 ?></div>
                <div class="score-meta"><?= htmlspecialchars($fixtureData['league']['name']) ?> · L<?= $fixtureData['league']['id'] ?>/S<?= $fixtureData['league']['season'] ?> · #<?= $fixtureId ?></div>
            </div>
            <div class="team-side">
                <img src="<?= $fixtureData['teams']['away']['logo'] ?>" alt="">
                <div class="team-name"><?= htmlspecialchars($fixtureData['teams']['away']['name']) ?></div>
            </div>
        </div>
    </div>

    <!-- coverage + data availability -->
    <div class="info-grid">
        <?php if ($coverage): ?>
        <div class="card card-pad">
            <div class="section-label">Coverage</div>
            <div class="cov-grid">
                <?php foreach ([
                    'Events'         => $coverage['fixtures']['events'] ?? null,
                    'Lineups'        => $coverage['fixtures']['lineups'] ?? null,
                    'Stats Fixture'  => $coverage['fixtures']['statistics_fixtures'] ?? null,
                    'Stats Players'  => $coverage['fixtures']['statistics_players'] ?? null,
                    'Standings'      => $coverage['standings'] ?? null,
                    'Players'        => $coverage['players'] ?? null,
                ] as $label => $val): ?>
                <div class="cov-row"><?= covBadge($val) ?> <?= $label ?></div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="card card-pad">
            <div class="section-label">Dati disponibili</div>
            <div class="data-grid">
                <?php
                $checks = [
                    ['Lin', count($lineups), $lineups ? 'var(--accent)' : 'var(--text-4)'],
                    ['Evt', count($events), $events ? 'var(--green)' : 'var(--text-4)'],
                    ['Pl.S', count($playerStats) ? array_sum(array_map(fn($t) => count($t['players'] ?? []), $playerStats)) : 0, $playerStats ? 'var(--green)' : 'var(--text-4)'],
                    ['T.S', count($rawStats['response'] ?? []), ($rawStats['response'] ?? []) ? 'var(--cyan)' : 'var(--text-4)'],
                    ['Voti', count(array_filter($playerScores, fn($p) => $p['score'] !== null)), $playerScores ? 'var(--amber)' : 'var(--text-4)'],
                ];
                foreach ($checks as [$label, $count, $color]):
                ?>
                <div class="data-cell">
                    <div class="data-num" style="color:<?= $color ?>"><?= $count ?></div>
                    <div class="data-lbl"><?= $label ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- lineup 3-col -->
    <div class="lineup-grid">

        <!-- home lineup -->
        <div class="card card-pad">
            <?php if (!empty($lineups[0])): $home = $lineups[0]; ?>
                <div class="lineup-header">
                    <div class="lineup-team"><?= htmlspecialchars($home['team']['name']) ?></div>
                    <div class="formation-badge"><?= $home['formation'] ?></div>
                </div>
                <div class="sublist-label">Titolari</div>
                <div>
                    <?php foreach ($home['startXI'] as $p):
                        $pid = $p['player']['id'];
                        $ps = $playerScores[$pid] ?? null;
                        $score = $ps['score'] ?? null;
                    ?>
                    <div class="player-row">
                        <div class="player-left">
                            <span class="player-pos"><?= $p['player']['pos'] ?? '?' ?></span>
                            <span class="player-name"><?= htmlspecialchars($p['player']['name']) ?></span>
                        </div>
                        <div class="player-right">
                            <?php if ($ps): echo getSourceBadge($ps['source']); endif; ?>
                            <span class="pill <?= getScoreColor($score) ?>"><?= $score !== null ? number_format($score, 1) : 'SV' ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="sublist-label">Panchina</div>
                <div>
                    <?php foreach ($home['substitutes'] as $p):
                        $pid = $p['player']['id'];
                        $ps = $playerScores[$pid] ?? null;
                        $score = $ps['score'] ?? null;
                    ?>
                    <div class="player-row player-sub">
                        <div class="player-left">
                            <span class="player-pos"><?= $p['player']['pos'] ?? '?' ?></span>
                            <span class="player-name"><?= htmlspecialchars($p['player']['name']) ?></span>
                        </div>
                        <div class="player-right">
                            <?php if ($ps && $ps['source'] === 'sub_entered'): echo getSourceBadge($ps['source']); endif; ?>
                            <span class="pill <?= getScoreColor($score) ?>"><?= $score !== null ? number_format($score, 1) : '–' ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

            <?php elseif (!empty($playerStats)):
                $homeTeamId = $fixtureData['teams']['home']['id'];
                $homeStats = null;
                foreach ($playerStats as $t) { if (($t['team']['id'] ?? 0) == $homeTeamId) { $homeStats = $t; break; } }
            ?>
                <?php if ($homeStats): ?>
                <div class="lineup-header">
                    <div class="lineup-team"><?= htmlspecialchars($homeStats['team']['name']) ?></div>
                    <span class="src-badge src-stats">da Player Stats</span>
                </div>
                <div>
                    <?php foreach ($homeStats['players'] as $entry):
                        $pid = $entry['player']['id'];
                        $ps = $playerScores[$pid] ?? null;
                        $score = $ps['score'] ?? null;
                        $stats = $entry['statistics'][0] ?? [];
                    ?>
                    <div class="player-row">
                        <div class="player-left">
                            <span class="player-pos"><?= $stats['games']['position'] ?? '?' ?></span>
                            <span class="player-name"><?= htmlspecialchars($entry['player']['name']) ?></span>
                            <span class="player-min"><?= $stats['games']['minutes'] ?? 0 ?>'</span>
                        </div>
                        <div class="player-right">
                            <?php if ($stats['games']['rating'] ?? null): ?>
                            <span class="player-rating"><?= number_format((float)$stats['games']['rating'], 1) ?></span>
                            <?php endif; ?>
                            <span class="pill <?= getScoreColor($score) ?>"><?= $score !== null ? number_format($score, 1) : 'SV' ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="empty-state">In attesa formazioni (Home)<br><span style="font-size:11px">~20–40 min prima del match</span></div>
            <?php endif; ?>
        </div>

        <!-- timeline eventi -->
        <div class="card card-pad timeline-col">
            <div class="section-label" style="text-align:center;margin-bottom:12px">Timeline eventi</div>
            <?php if (empty($events)): ?>
                <div class="empty-state">Nessun evento.</div>
            <?php else: ?>
                <div class="event-list">
                    <?php foreach (array_reverse($events) as $ev):
                        $isHome = ($ev['team']['id'] ?? 0) === ($fixtureData['teams']['home']['id'] ?? -1);
                        $pid = $ev['player']['id'] ?? null;
                    ?>
                    <div class="event-card <?= $isHome ? 'home-ev' : 'away-ev' ?>">
                        <div class="event-time"><?= $ev['time']['elapsed'] ?>'<?= $ev['time']['extra'] ? '+' . $ev['time']['extra'] : '' ?></div>
                        <?= getEventIcon($ev['type'], $ev['detail'] ?? '') ?>
                        <div class="event-info">
                            <div class="event-main">
                                <?= htmlspecialchars($ev['player']['name'] ?? '?') ?>
                                <?php if($ev['type'] === 'subst' && ($ev['assist']['name'] ?? null)): ?>
                                <span style="color:var(--text-3);font-weight:400"> ↔ <?= htmlspecialchars($ev['assist']['name']) ?></span>
                                <?php endif; ?>
                                <?php if($ev['type'] === 'Goal' && ($ev['assist']['name'] ?? null)): ?>
                                <span style="color:var(--text-3);font-weight:400"> A: <?= htmlspecialchars($ev['assist']['name']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="event-sub-text">
                                <?= htmlspecialchars($ev['detail']) ?> · <?= htmlspecialchars($ev['team']['name']) ?>
                                <?php if ($pid): ?><span style="color:var(--text-4);font-family:'JetBrains Mono',monospace;font-size:9px"> pid:<?= $pid ?></span><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- away lineup -->
        <div class="card card-pad">
            <?php if (!empty($lineups[1])): $away = $lineups[1]; ?>
                <div class="lineup-header">
                    <div class="formation-badge"><?= $away['formation'] ?></div>
                    <div class="lineup-team"><?= htmlspecialchars($away['team']['name']) ?></div>
                </div>
                <div class="sublist-label" style="text-align:right">Titolari</div>
                <div>
                    <?php foreach ($away['startXI'] as $p):
                        $pid = $p['player']['id'];
                        $ps = $playerScores[$pid] ?? null;
                        $score = $ps['score'] ?? null;
                    ?>
                    <div class="player-row">
                        <div class="player-right">
                            <span class="pill <?= getScoreColor($score) ?>"><?= $score !== null ? number_format($score, 1) : 'SV' ?></span>
                            <?php if ($ps): echo getSourceBadge($ps['source']); endif; ?>
                        </div>
                        <div class="player-left" style="justify-content:flex-end">
                            <span class="player-name" style="text-align:right"><?= htmlspecialchars($p['player']['name']) ?></span>
                            <span class="player-pos"><?= $p['player']['pos'] ?? '?' ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="sublist-label" style="text-align:right">Panchina</div>
                <div>
                    <?php foreach ($away['substitutes'] as $p):
                        $pid = $p['player']['id'];
                        $ps = $playerScores[$pid] ?? null;
                        $score = $ps['score'] ?? null;
                    ?>
                    <div class="player-row player-sub">
                        <div class="player-right">
                            <?php if ($ps && $ps['source'] === 'sub_entered'): echo getSourceBadge($ps['source']); endif; ?>
                            <span class="pill <?= getScoreColor($score) ?>"><?= $score !== null ? number_format($score, 1) : '–' ?></span>
                        </div>
                        <div class="player-left" style="justify-content:flex-end">
                            <span class="player-name" style="text-align:right"><?= htmlspecialchars($p['player']['name']) ?></span>
                            <span class="player-pos"><?= $p['player']['pos'] ?? '?' ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

            <?php elseif (!empty($playerStats)):
                $awayTeamId = $fixtureData['teams']['away']['id'];
                $awayStatsData = null;
                foreach ($playerStats as $t) { if (($t['team']['id'] ?? 0) == $awayTeamId) { $awayStatsData = $t; break; } }
            ?>
                <?php if ($awayStatsData): ?>
                <div class="lineup-header">
                    <span class="src-badge src-stats">da Player Stats</span>
                    <div class="lineup-team"><?= htmlspecialchars($awayStatsData['team']['name']) ?></div>
                </div>
                <div>
                    <?php foreach ($awayStatsData['players'] as $entry):
                        $pid = $entry['player']['id'];
                        $ps = $playerScores[$pid] ?? null;
                        $score = $ps['score'] ?? null;
                        $stats = $entry['statistics'][0] ?? [];
                    ?>
                    <div class="player-row">
                        <div class="player-right">
                            <?php if ($stats['games']['rating'] ?? null): ?>
                            <span class="player-rating"><?= number_format((float)$stats['games']['rating'], 1) ?></span>
                            <?php endif; ?>
                            <span class="pill <?= getScoreColor($score) ?>"><?= $score !== null ? number_format($score, 1) : 'SV' ?></span>
                        </div>
                        <div class="player-left" style="justify-content:flex-end">
                            <span class="player-name" style="text-align:right"><?= htmlspecialchars($entry['player']['name']) ?></span>
                            <span class="player-pos"><?= $stats['games']['position'] ?? '?' ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="empty-state">In attesa formazioni (Away)<br><span style="font-size:11px">~20–40 min prima del match</span></div>
            <?php endif; ?>
        </div>

    </div><!-- /lineup-grid -->

    <!-- team statistics -->
    <?php if (!empty($rawStats['response'])): ?>
    <div class="card card-pad">
        <div class="section-label" style="margin-bottom:12px">Statistiche partita</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <?php foreach ($rawStats['response'] as $teamStat): ?>
            <div>
                <div style="font-size:13px;font-weight:700;margin-bottom:8px"><?= htmlspecialchars($teamStat['team']['name'] ?? '?') ?></div>
                <?php foreach ($teamStat['statistics'] ?? [] as $stat): ?>
                <div class="stat-row">
                    <span class="stat-key"><?= htmlspecialchars($stat['type']) ?></span>
                    <span class="stat-val"><?= $stat['value'] ?? '–' ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- raw data accordion -->
    <div class="card">
        <details class="accordion">
            <summary>
                <span style="color:var(--amber);font-size:13px">Raw API Data</span>
                <span style="font-size:11px;color:var(--text-3);font-weight:400">espandi</span>
            </summary>
            <div class="accordion-body">
                <div>
                    <div class="raw-label">Lineups (<?= count($lineups) ?> teams)</div>
                    <pre class="raw-pre"><?= htmlspecialchars(json_encode($rawLineups, JSON_PRETTY_PRINT)) ?></pre>
                </div>
                <div>
                    <div class="raw-label">Events (<?= count($events) ?>)</div>
                    <pre class="raw-pre"><?= htmlspecialchars(json_encode($rawEvents, JSON_PRETTY_PRINT)) ?></pre>
                </div>
                <div>
                    <div class="raw-label">Player Stats (<?= count($playerStats) ?> teams)</div>
                    <pre class="raw-pre"><?= htmlspecialchars(json_encode($rawPlayers, JSON_PRETTY_PRINT)) ?></pre>
                </div>
                <div>
                    <div class="raw-label">Team Statistics</div>
                    <pre class="raw-pre"><?= htmlspecialchars(json_encode($rawStats, JSON_PRETTY_PRINT)) ?></pre>
                </div>
                <div>
                    <div class="raw-label">Fantacalcio Engine (<?= count($playerScores) ?> players)</div>
                    <pre class="raw-pre"><?= htmlspecialchars(json_encode($playerScores, JSON_PRETTY_PRINT)) ?></pre>
                </div>
            </div>
        </details>
    </div>

    <?php endif; ?>

    <!-- league config reference -->
    <div class="card">
        <details class="accordion">
            <summary>
                <span style="font-size:12px">League Config Reference</span>
                <span style="font-size:11px;color:var(--text-3);font-weight:400">espandi</span>
            </summary>
            <div style="padding:12px 16px">
                <div class="league-grid">
                    <?php foreach ($KNOWN_LEAGUES as $lg): ?>
                    <div class="league-item">
                        <span style="color:var(--text-3)">L=</span><span class="league-id"><?= $lg['id'] ?></span>
                        <span style="color:var(--text-3)"> S=</span><span class="league-season"><?= $lg['season'] ?></span>
                        <div class="league-name"><?= htmlspecialchars($lg['name']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </details>
    </div>

</div><!-- /page -->
</body>
</html>