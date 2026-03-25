<?php
define('API_KEY', '1a4942a032906326bcdaa564e10dbe65');
define('API_BASE', 'https://v3.football.api-sports.io/');

function apiGet($endpoint) {
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL            => API_BASE . $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => array(
            'x-apisports-key: ' . API_KEY,
            'Accept: application/json',
        ),
    ));
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($raw, true);
    return array('code' => $code, 'data' => $data);
}

$today    = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$target   = isset($_GET['date']) ? $_GET['date'] : $tomorrow;

// ─── 1. CERCA FIXTURE ───────────────────────────────────────────────────────
$leagues = array(
    array('id' => 1222, 'name' => 'FIFA Series', 'season' => 2026),
    array('id' => 960,  'name' => 'UEFA WC Playoff', 'season' => 2026),
    array('id' => 37,   'name' => 'Intercontinental Playoff', 'season' => 2026),
    array('id' => 5,    'name' => 'Amichevoli', 'season' => 2026),
    array('id' => 5,    'name' => 'Amichevoli 2025', 'season' => 2025),
);

$allFixtures = array();
$apiCalls    = 0;

foreach ($leagues as $lg) {
    $res = apiGet("fixtures?date={$target}&league={$lg['id']}&season={$lg['season']}");
    $apiCalls++;
    if (!empty($res['data']['response'])) {
        foreach ($res['data']['response'] as $f) {
            $f['_league_label'] = $lg['name'];
            $allFixtures[] = $f;
        }
    }
    usleep(200000);
}

// Se nessun risultato dalle league specifiche, cerca tutte
if (empty($allFixtures)) {
    $res = apiGet("fixtures?date={$target}");
    $apiCalls++;
    if (!empty($res['data']['response'])) {
        // Filtra solo nazionali (team type = national)
        foreach ($res['data']['response'] as $f) {
            $ln = strtolower($f['league']['name'] ?? '');
            if (strpos($ln, 'friend') !== false || strpos($ln, 'qualif') !== false || 
                strpos($ln, 'playoff') !== false || strpos($ln, 'fifa') !== false ||
                strpos($ln, 'uefa') !== false || strpos($ln, 'world') !== false) {
                $f['_league_label'] = $f['league']['name'];
                $allFixtures[] = $f;
            }
        }
    }
}

// ─── 2. PER OGNI FIXTURE: lineups, events, players ─────────────────────────
$fixtureDetails = array();

// Limita a max 5 fixture per non bruciare API calls
$checkFixtures = array_slice($allFixtures, 0, 5);

foreach ($checkFixtures as $f) {
    $fid    = $f['fixture']['id'];
    $status = $f['fixture']['status']['short'] ?? 'NS';
    
    $detail = array(
        'id'      => $fid,
        'status'  => $status,
        'minute'  => $f['fixture']['status']['elapsed'],
        'home'    => $f['teams']['home']['name'],
        'away'    => $f['teams']['away']['name'],
        'home_id' => $f['teams']['home']['id'],
        'away_id' => $f['teams']['away']['id'],
        'home_logo' => $f['teams']['home']['logo'],
        'away_logo' => $f['teams']['away']['logo'],
        'score'   => ($f['goals']['home'] ?? '-') . ' - ' . ($f['goals']['away'] ?? '-'),
        'league'  => $f['_league_label'],
        'date'    => $f['fixture']['date'],
        'lineups' => null,
        'events'  => null,
        'players' => null,
        'squad_home' => null,
        'squad_away' => null,
    );
    
    // Solo se in corso o finita, chiedi lineups/events/players
    $liveStatuses = array('1H','HT','2H','ET','FT','AET','PEN','LIVE');
    
    if (in_array($status, $liveStatuses)) {
        // Lineups
        $lr = apiGet("fixtures/lineups?fixture={$fid}");
        $apiCalls++;
        $detail['lineups'] = $lr['data']['response'] ?? array();
        usleep(200000);
        
        // Events
        $er = apiGet("fixtures/events?fixture={$fid}");
        $apiCalls++;
        $detail['events'] = $er['data']['response'] ?? array();
        usleep(200000);
        
        // Players stats
        $pr = apiGet("fixtures/players?fixture={$fid}");
        $apiCalls++;
        $detail['players'] = $pr['data']['response'] ?? array();
        usleep(200000);
    }
    
    // Squad (roster) — sempre disponibile
    $sr1 = apiGet("players/squads?team={$detail['home_id']}");
    $apiCalls++;
    $detail['squad_home'] = (!empty($sr1['data']['response'][0]['players'])) ? $sr1['data']['response'][0]['players'] : array();
    usleep(200000);
    
    $sr2 = apiGet("players/squads?team={$detail['away_id']}");
    $apiCalls++;
    $detail['squad_away'] = (!empty($sr2['data']['response'][0]['players'])) ? $sr2['data']['response'][0]['players'] : array();
    usleep(200000);
    
    $fixtureDetails[] = $detail;
}

// ─── HTML OUTPUT ────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>FM26 Debug Dashboard</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f5f5f7; color: #1d1d1f; padding: 16px; max-width: 900px; margin: 0 auto; }
h1 { font-size: 22px; font-weight: 800; margin-bottom: 4px; }
.meta { font-size: 12px; color: #86868b; font-family: monospace; margin-bottom: 20px; }
.card { background: #fff; border-radius: 14px; border: 1px solid #e5e5ea; padding: 16px; margin-bottom: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
.card-title { font-size: 13px; font-weight: 700; color: #86868b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; font-family: monospace; }
.match-header { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
.match-header img { width: 36px; height: 36px; }
.match-header .vs { font-weight: 300; color: #aaa; }
.match-header .team { font-weight: 700; font-size: 15px; }
.match-header .score { font-size: 22px; font-weight: 800; font-family: monospace; }
.status { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 700; font-family: monospace; }
.status-ns { background: #f0f0f0; color: #888; }
.status-live { background: #fee; color: #e00; animation: pulse 2s infinite; }
.status-ft { background: #e8f5e9; color: #2e7d32; }
@keyframes pulse { 50% { opacity: .6; } }
.check { display: flex; align-items: center; gap: 8px; padding: 6px 0; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
.check:last-child { border-bottom: none; }
.check-icon { width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; flex-shrink: 0; }
.check-ok { background: #e8f5e9; color: #2e7d32; }
.check-no { background: #fce4ec; color: #c62828; }
.check-wait { background: #fff3e0; color: #e65100; }
.check-label { flex: 1; }
.check-val { font-family: monospace; font-size: 12px; color: #555; }
table { width: 100%; border-collapse: collapse; font-size: 12px; }
th { text-align: left; font-size: 10px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; padding: 4px 6px; border-bottom: 2px solid #eee; font-family: monospace; }
td { padding: 5px 6px; border-bottom: 1px solid #f5f5f5; }
tr:hover { background: #fafafa; }
.role { display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: 10px; font-weight: 700; font-family: monospace; }
.role-g { background: #fff9c4; color: #f9a825; }
.role-d { background: #e3f2fd; color: #1565c0; }
.role-m { background: #e8f5e9; color: #2e7d32; }
.role-f { background: #fce4ec; color: #c62828; }
.event-row { display: flex; align-items: center; gap: 8px; padding: 5px 0; border-bottom: 1px solid #f5f5f5; font-size: 13px; }
.event-min { font-family: monospace; font-weight: 700; font-size: 12px; color: #888; min-width: 30px; }
.event-type { font-size: 11px; font-weight: 700; padding: 2px 6px; border-radius: 4px; }
.event-goal { background: #e8f5e9; color: #2e7d32; }
.event-card-y { background: #fff9c4; color: #f9a825; }
.event-card-r { background: #fce4ec; color: #c62828; }
.event-subst { background: #e3f2fd; color: #1565c0; }
.empty-msg { color: #aaa; font-style: italic; font-size: 13px; padding: 10px 0; }
.nav { display: flex; gap: 8px; margin-bottom: 16px; }
.nav a { padding: 6px 14px; background: #e5e5ea; border-radius: 20px; text-decoration: none; color: #333; font-size: 12px; font-weight: 600; }
.nav a.active { background: #007aff; color: #fff; }
</style>
</head>
<body>

<h1>FM26 Debug Dashboard</h1>
<div class="meta">
    Data target: <strong><?php echo $target; ?></strong> · 
    Oggi: <?php echo $today; ?> · 
    API calls: <?php echo $apiCalls; ?> · 
    Fixture trovate: <?php echo count($allFixtures); ?> · 
    Dettagli caricati: <?php echo count($fixtureDetails); ?>
</div>

<div class="nav">
    <a href="?date=<?php echo $today; ?>" <?php if($target===$today) echo 'class="active"'; ?>>Oggi</a>
    <a href="?date=<?php echo $tomorrow; ?>" <?php if($target===$tomorrow) echo 'class="active"'; ?>>Domani</a>
    <a href="?date=2026-03-27" <?php if($target==='2026-03-27') echo 'class="active"'; ?>>27 mar</a>
    <a href="?date=2026-03-31" <?php if($target==='2026-03-31') echo 'class="active"'; ?>>31 mar</a>
</div>

<?php if (empty($fixtureDetails)): ?>
<div class="card">
    <div class="empty-msg">Nessuna fixture internazionale trovata per <?php echo $target; ?></div>
</div>
<?php endif; ?>

<?php foreach ($fixtureDetails as $fd): ?>
<div class="card">
    <div class="match-header">
        <img src="<?php echo $fd['home_logo']; ?>" alt="">
        <span class="team"><?php echo $fd['home']; ?></span>
        <span class="score"><?php echo $fd['score']; ?></span>
        <span class="team"><?php echo $fd['away']; ?></span>
        <img src="<?php echo $fd['away_logo']; ?>" alt="">
        <?php
        $sc = 'status-ns';
        if (in_array($fd['status'], array('1H','2H','HT','ET','LIVE'))) $sc = 'status-live';
        elseif (in_array($fd['status'], array('FT','AET','PEN'))) $sc = 'status-ft';
        ?>
        <span class="status <?php echo $sc; ?>"><?php echo $fd['status']; ?><?php if($fd['minute']) echo " {$fd['minute']}'"; ?></span>
    </div>
    <div style="font-size:11px;color:#888;margin-bottom:12px;font-family:monospace">
        <?php echo $fd['league']; ?> · ID: <?php echo $fd['id']; ?> · <?php echo $fd['date']; ?>
    </div>

    <!-- CHECKS -->
    <div class="card-title">Disponibilità dati</div>
    
    <?php
    $hasSquadH = count($fd['squad_home'] ?? array()) > 0;
    $hasSquadA = count($fd['squad_away'] ?? array()) > 0;
    $hasLineups = !empty($fd['lineups']) && count($fd['lineups'] ?? array()) > 0;
    $hasEvents = !empty($fd['events']) && count($fd['events'] ?? array()) > 0;
    $hasPlayers = !empty($fd['players']) && count($fd['players'] ?? array()) > 0;
    $isNS = ($fd['status'] === 'NS' || $fd['status'] === 'TBD');
    ?>
    
    <div class="check">
        <div class="check-icon <?php echo $hasSquadH ? 'check-ok' : 'check-no'; ?>"><?php echo $hasSquadH ? '✓' : '✗'; ?></div>
        <span class="check-label">Squad <?php echo $fd['home']; ?></span>
        <span class="check-val"><?php echo count($fd['squad_home'] ?? array()); ?> giocatori</span>
    </div>
    <div class="check">
        <div class="check-icon <?php echo $hasSquadA ? 'check-ok' : 'check-no'; ?>"><?php echo $hasSquadA ? '✓' : '✗'; ?></div>
        <span class="check-label">Squad <?php echo $fd['away']; ?></span>
        <span class="check-val"><?php echo count($fd['squad_away'] ?? array()); ?> giocatori</span>
    </div>
    <div class="check">
        <div class="check-icon <?php echo $hasLineups ? 'check-ok' : ($isNS ? 'check-wait' : 'check-no'); ?>">
            <?php echo $hasLineups ? '✓' : ($isNS ? '⏳' : '✗'); ?>
        </div>
        <span class="check-label">Lineups (titolari)</span>
        <span class="check-val"><?php echo $hasLineups ? count((array)$fd['lineups']) . ' squadre' : ($isNS ? 'pre-partita' : 'non disponibile'); ?></span>
    </div>
    <div class="check">
        <div class="check-icon <?php echo $hasEvents ? 'check-ok' : ($isNS ? 'check-wait' : 'check-no'); ?>">
            <?php echo $hasEvents ? '✓' : ($isNS ? '⏳' : '✗'); ?>
        </div>
        <span class="check-label">Events (gol/cartellini)</span>
        <span class="check-val"><?php echo $hasEvents ? count((array)$fd['events']) . ' eventi' : ($isNS ? 'pre-partita' : 'nessuno'); ?></span>
    </div>
    <div class="check">
        <div class="check-icon <?php echo $hasPlayers ? 'check-ok' : ($isNS ? 'check-wait' : 'check-no'); ?>">
            <?php echo $hasPlayers ? '✓' : ($isNS ? '⏳' : '✗'); ?>
        </div>
        <span class="check-label">Players stats (rating+dettaglio)</span>
        <span class="check-val"><?php echo $hasPlayers ? 'disponibile' : ($isNS ? 'pre-partita' : 'non disponibile'); ?></span>
    </div>

    <!-- LINEUPS -->
    <?php if ($hasLineups): ?>
    <div class="card-title" style="margin-top:14px">Formazioni titolari</div>
    <?php foreach ((array)$fd['lineups'] as $team): ?>
    <div style="margin-bottom:10px">
        <strong><?php echo $team['team']['name']; ?></strong> 
        <span style="font-family:monospace;color:#888">(<?php echo $team['formation']; ?>)</span>
    </div>
    <table>
        <tr><th>#</th><th>Nome</th><th>Pos</th><th>ID</th></tr>
        <?php foreach ($team['startXI'] as $p): 
            $pos = $p['player']['pos'] ?? '?';
            $rc = 'role-m';
            if ($pos === 'G') $rc = 'role-g';
            elseif ($pos === 'D') $rc = 'role-d';
            elseif ($pos === 'F') $rc = 'role-f';
        ?>
        <tr>
            <td><?php echo $p['player']['number'] ?? '-'; ?></td>
            <td><strong><?php echo $p['player']['name']; ?></strong></td>
            <td><span class="role <?php echo $rc; ?>"><?php echo $pos; ?></span></td>
            <td style="font-family:monospace;color:#888"><?php echo $p['player']['id']; ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endforeach; ?>
    <?php endif; ?>

    <!-- EVENTS -->
    <?php if ($hasEvents): ?>
    <div class="card-title" style="margin-top:14px">Eventi partita</div>
    <?php foreach ((array)$fd['events'] as $ev): 
        $type = $ev['type'];
        $detail = $ev['detail'] ?? '';
        $tc = 'event-subst';
        if ($type === 'Goal') $tc = 'event-goal';
        elseif ($type === 'Card' && strpos($detail, 'Yellow') !== false) $tc = 'event-card-y';
        elseif ($type === 'Card' && strpos($detail, 'Red') !== false) $tc = 'event-card-r';
    ?>
    <div class="event-row">
        <span class="event-min"><?php echo $ev['time']['elapsed']; ?>'</span>
        <span class="event-type <?php echo $tc; ?>"><?php echo $type; ?></span>
        <span><strong><?php echo $ev['player']['name'] ?? '?'; ?></strong></span>
        <span style="color:#888;font-size:11px"><?php echo $ev['team']['name']; ?></span>
        <?php if (!empty($ev['assist']['name'])): ?>
            <span style="color:#888;font-size:11px">(assist: <?php echo $ev['assist']['name']; ?>)</span>
        <?php endif; ?>
        <span style="color:#aaa;font-size:10px;font-family:monospace">id:<?php echo $ev['player']['id'] ?? '?'; ?></span>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <!-- PLAYERS STATS -->
    <?php if ($hasPlayers): ?>
    <div class="card-title" style="margin-top:14px">Stats giocatori (da API)</div>
    <?php foreach ((array)$fd['players'] as $teamData): ?>
    <div style="margin-bottom:6px"><strong><?php echo $teamData['team']['name']; ?></strong></div>
    <table>
        <tr><th>Nome</th><th>Pos</th><th>Rating</th><th>Gol</th><th>Ass</th><th>Gialli</th><th>Rossi</th><th>Min</th><th>ID</th></tr>
        <?php foreach ($teamData['players'] as $p):
            $s = $p['statistics'][0] ?? array();
        ?>
        <tr>
            <td><strong><?php echo $p['player']['name']; ?></strong></td>
            <td><?php echo $s['games']['position'] ?? '?'; ?></td>
            <td style="font-weight:700;font-family:monospace"><?php echo $s['games']['rating'] ?? '-'; ?></td>
            <td><?php echo $s['goals']['total'] ?? 0; ?></td>
            <td><?php echo $s['goals']['assists'] ?? 0; ?></td>
            <td><?php echo $s['cards']['yellow'] ?? 0; ?></td>
            <td><?php echo $s['cards']['red'] ?? 0; ?></td>
            <td style="font-family:monospace"><?php echo $s['games']['minutes'] ?? '-'; ?></td>
            <td style="font-family:monospace;color:#888"><?php echo $p['player']['id']; ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endforeach; ?>
    <?php endif; ?>

    <!-- SQUAD (roster) -->
    <?php if ($hasSquadH || $hasSquadA): ?>
    <details style="margin-top:14px">
        <summary style="cursor:pointer;font-size:12px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:0.5px;font-family:monospace">
            Roster completi (<?php echo count((array)$fd['squad_home']) + count((array)$fd['squad_away']); ?> giocatori)
        </summary>
        <?php foreach (array(array($fd['home'], $fd['squad_home']), array($fd['away'], $fd['squad_away'])) as $sq): ?>
        <div style="margin:10px 0 4px;font-weight:700"><?php echo $sq[0]; ?> (<?php echo count($sq[1]); ?>)</div>
        <table>
            <tr><th>#</th><th>Nome</th><th>Pos</th><th>Età</th><th>ID</th></tr>
            <?php foreach ($sq[1] as $p):
                $pos = $p['position'] ?? '?';
                $rc = 'role-m';
                if ($pos === 'Goalkeeper') $rc = 'role-g';
                elseif ($pos === 'Defender') $rc = 'role-d';
                elseif ($pos === 'Attacker') $rc = 'role-f';
            ?>
            <tr>
                <td><?php echo $p['number'] ?? '-'; ?></td>
                <td><?php echo $p['name']; ?></td>
                <td><span class="role <?php echo $rc; ?>"><?php echo substr($pos,0,3); ?></span></td>
                <td><?php echo $p['age'] ?? '-'; ?></td>
                <td style="font-family:monospace;color:#888"><?php echo $p['id']; ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endforeach; ?>
    </details>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<div class="card" style="background:#f9f9fb">
    <div class="card-title">Recap</div>
    <div style="font-size:13px;line-height:1.7">
        <strong>Oggi (<?php echo $today; ?>):</strong><br>
        • Implementato: calendario → match detail → formazione + confronto live<br>
        • Testato su Kazakhstan vs Namibia (FIFA Series, league 1222)<br>
        • Events ✓ funzionano (gol, cartellini) — Lineups ✗ — Players stats ✗<br>
        • Voti live basati su events (rating base 6 + bonus/malus)<br>
        <br>
        <strong>TODO domani:</strong><br>
        • Controllare se Italia vs N.Ireland ha lineups + players stats (league da trovare)<br>
        • Se players stats disponibili → voti con rating reale API (non base 6)<br>
        • Se lineups disponibili → voto base 6 SOLO a chi è in campo (non tutti)<br>
        • Vista dettaglio confronto da rifare meglio<br>
        • Aggiornare get_api_test.php con la league corretta<br>
        • Aggiornare get_live_scores.php con la league corretta<br>
        • Ricaricare listone, comprare giocatori Italia/Irlanda del Nord, schierare, testare live<br>
    </div>
</div>

</body>
</html>