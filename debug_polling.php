<?php
define('API_KEY', '1a4942a032906326bcdaa564e10dbe65');
$fixtureId = 1537243; //da sost con italia iralnda

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

// FETCH DATI
$fixtureReq = apiGet("https://v3.football.api-sports.io/fixtures?id={$fixtureId}");
$lineupsReq = apiGet("https://v3.football.api-sports.io/fixtures/lineups?fixture={$fixtureId}");
$eventsReq  = apiGet("https://v3.football.api-sports.io/fixtures/events?fixture={$fixtureId}");

$fixtureData = $fixtureReq['response'][0] ?? null;
$lineups     = $lineupsReq['response'] ?? [];
$events      = $eventsReq['response'] ?? [];

// MOTORE FANTACALCIO (Calcolo Voti)
$playerScores = [];
if (!empty($lineups)) {
    foreach ($lineups as $team) {
        foreach ($team['startXI'] as $p) { $playerScores[$p['player']['id']] = 6.0; } // Titolari
        foreach ($team['substitutes'] as $p) { $playerScores[$p['player']['id']] = null; } // Panchina
    }
}

if (!empty($events)) {
    foreach ($events as $ev) {
        $pid = $ev['player']['id'] ?? null;
        $assistId = $ev['assist']['id'] ?? null;
        
        if ($ev['type'] === 'Goal') {
            if (stripos($ev['detail'], 'Own') !== false) {
                if ($pid && isset($playerScores[$pid])) $playerScores[$pid] -= 2; // Autogol
            } else {
                if ($pid && isset($playerScores[$pid])) $playerScores[$pid] += 3; // Gol
                if ($assistId && isset($playerScores[$assistId])) $playerScores[$assistId] += 1; // Assist
            }
        } elseif ($ev['type'] === 'Card') {
            if ($ev['detail'] === 'Yellow Card' && $pid && isset($playerScores[$pid])) $playerScores[$pid] -= 0.5;
            if (stripos($ev['detail'], 'Red') !== false && $pid && isset($playerScores[$pid])) $playerScores[$pid] -= 1;
        } elseif ($ev['type'] === 'subst' && $assistId && array_key_exists($assistId, $playerScores)) {
            if ($playerScores[$assistId] === null) $playerScores[$assistId] = 6.0; // Voto a chi entra
        }
    }
}

// Helpers
function getEventIcon($type, $detail) {
    if ($type === 'Goal') return '⚽';
    if ($type === 'Card' && str_contains($detail, 'Yellow')) return '🟨';
    if ($type === 'Card' && str_contains($detail, 'Red')) return '🟥';
    if ($type === 'subst') return '🔄';
    if ($type === 'Var') return '📺';
    return '⚡';
}

function getScoreColor($score) {
    if ($score === null) return 'bg-slate-600 text-slate-300';
    if ($score >= 6.5) return 'bg-emerald-500 text-white';
    if ($score >= 6.0) return 'bg-blue-500 text-white';
    return 'bg-red-500 text-white';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Italia - Irlanda del Nord | Fanta Debug Live</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #0f172a; color: #f8fafc; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #1e293b; border-radius: 8px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #475569; border-radius: 8px; }
    </style>
</head>
<body class="p-4 md:p-8">

<div class="max-w-7xl mx-auto space-y-6">
    
    <?php if ($fixtureData): ?>
    <div class="bg-slate-800 rounded-2xl shadow-2xl p-6 border border-slate-700 flex flex-col md:flex-row items-center justify-between">
        <div class="flex items-center gap-6 w-1/3 justify-end">
            <h2 class="text-2xl font-bold text-slate-200"><?= $fixtureData['teams']['home']['name'] ?></h2>
            <img src="<?= $fixtureData['teams']['home']['logo'] ?>" class="w-20 h-20 object-contain">
        </div>
        
        <div class="flex flex-col items-center w-1/3">
            <span class="text-sm font-semibold text-emerald-400 mb-1 tracking-widest uppercase">
                <?= $fixtureData['fixture']['status']['short'] ?> <?= $fixtureData['fixture']['status']['elapsed'] ? $fixtureData['fixture']['status']['elapsed'] . "'" : '' ?>
            </span>
            <div class="text-5xl font-extrabold bg-gradient-to-r from-white to-slate-400 bg-clip-text text-transparent">
                <?= $fixtureData['goals']['home'] ?? 0 ?> - <?= $fixtureData['goals']['away'] ?? 0 ?>
            </div>
            <span class="text-xs text-slate-400 mt-2"><?= $fixtureData['league']['name'] ?> • <?= $fixtureData['fixture']['venue']['name'] ?? 'Stadio' ?></span>
        </div>

        <div class="flex items-center gap-6 w-1/3 justify-start">
            <img src="<?= $fixtureData['teams']['away']['logo'] ?>" class="w-20 h-20 object-contain">
            <h2 class="text-2xl font-bold text-slate-200"><?= $fixtureData['teams']['away']['name'] ?></h2>
        </div>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        
        <div class="col-span-1 bg-slate-800 rounded-2xl p-5 border border-slate-700 shadow-inner">
            <?php if (!empty($lineups[0])): $home = $lineups[0]; ?>
                <div class="flex justify-between items-center mb-4 border-b border-slate-700 pb-2">
                    <h3 class="font-bold text-lg"><?= $home['team']['name'] ?></h3>
                    <span class="px-2 py-1 bg-slate-700 text-xs font-bold rounded-lg"><?= $home['formation'] ?></span>
                </div>
                <div class="space-y-1">
                    <p class="text-xs text-slate-400 uppercase font-semibold mb-2">Titolari</p>
                    <?php foreach ($home['startXI'] as $p): $score = $playerScores[$p['player']['id']] ?? null; ?>
                    <div class="flex items-center justify-between p-2 hover:bg-slate-700/50 rounded-lg transition">
                        <span class="font-semibold text-sm"><?= $p['player']['name'] ?></span>
                        <span class="px-2 py-0.5 rounded text-xs font-bold <?= getScoreColor($score) ?>"><?= $score !== null ? number_format($score, 1) : 'SV' ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="h-full flex flex-col items-center justify-center text-slate-500 p-6 text-center">
                    <svg class="w-12 h-12 mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <p>In attesa delle formazioni ufficiali (Home)</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-span-2 bg-slate-900 rounded-2xl p-5 border border-slate-700 shadow-inner">
            <h3 class="font-bold text-lg mb-4 text-center text-slate-300">Timeline Eventi</h3>
            <?php if (empty($events)): ?>
                <div class="text-center text-slate-500 py-10">Nessun evento registrato o partita non iniziata.</div>
            <?php else: ?>
                <div class="space-y-4 max-h-[600px] overflow-y-auto custom-scrollbar pr-2">
                    <?php foreach (array_reverse($events) as $ev): 
                        $isHome = isset($fixtureData) && $ev['team']['id'] === $fixtureData['teams']['home']['id'];
                    ?>
                    <div class="flex items-center gap-4 bg-slate-800 p-3 rounded-xl border border-slate-700/50 relative overflow-hidden">
                        <div class="absolute inset-y-0 <?= $isHome ? 'left-0 border-l-4 border-blue-500' : 'right-0 border-r-4 border-green-500' ?> w-full bg-gradient-to-r <?= $isHome ? 'from-blue-500/10 to-transparent' : 'from-transparent to-green-500/10' ?> pointer-events-none"></div>
                        <div class="font-black text-slate-400 w-10 text-right shrink-0"><?= $ev['time']['elapsed'] ?>'</div>
                        <div class="text-2xl shrink-0"><?= getEventIcon($ev['type'], $ev['detail']) ?></div>
                        <div class="flex-1 min-w-0 z-10">
                            <p class="font-bold text-slate-200 truncate">
                                <?= $ev['player']['name'] ?>
                                <?php if($ev['type'] === 'subst' && $ev['assist']['name']): ?><span class="text-slate-400 text-sm font-normal"> / <?= $ev['assist']['name'] ?></span><?php endif; ?>
                            </p>
                            <p class="text-xs text-slate-400"><?= $ev['detail'] ?> • <?= $ev['team']['name'] ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-span-1 bg-slate-800 rounded-2xl p-5 border border-slate-700 shadow-inner">
            <?php if (!empty($lineups[1])): $away = $lineups[1]; ?>
                <div class="flex justify-between items-center mb-4 border-b border-slate-700 pb-2">
                    <span class="px-2 py-1 bg-slate-700 text-xs font-bold rounded-lg"><?= $away['formation'] ?></span>
                    <h3 class="font-bold text-lg"><?= $away['team']['name'] ?></h3>
                </div>
                <div class="space-y-1">
                    <p class="text-xs text-slate-400 uppercase font-semibold mb-2 text-right">Titolari</p>
                    <?php foreach ($away['startXI'] as $p): $score = $playerScores[$p['player']['id']] ?? null; ?>
                    <div class="flex items-center justify-between p-2 hover:bg-slate-700/50 rounded-lg transition">
                        <span class="px-2 py-0.5 rounded text-xs font-bold <?= getScoreColor($score) ?>"><?= $score !== null ? number_format($score, 1) : 'SV' ?></span>
                        <span class="font-semibold text-sm text-right"><?= $p['player']['name'] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="h-full flex flex-col items-center justify-center text-slate-500 p-6 text-center">
                    <svg class="w-12 h-12 mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <p>In attesa delle formazioni ufficiali (Away)</p>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <div class="mt-10 border border-slate-700 rounded-2xl bg-slate-900 overflow-hidden">
        <button onclick="document.getElementById('debug-content').classList.toggle('hidden')" class="w-full p-4 flex justify-between items-center bg-slate-800 hover:bg-slate-700 transition">
            <span class="font-bold text-yellow-500">🛠 Inspect Raw API Data</span>
            <span class="text-slate-400 text-sm">Clicca per espandere</span>
        </button>
        <div id="debug-content" class="hidden p-4 space-y-4">
            <div>
                <h4 class="text-emerald-400 font-bold mb-2">Lineups API (<?= count($lineups) ?> teams found)</h4>
                <pre class="bg-black text-green-400 p-4 rounded text-xs overflow-x-auto max-h-60 custom-scrollbar"><?= json_encode($lineupsReq, JSON_PRETTY_PRINT) ?></pre>
            </div>
            <div>
                <h4 class="text-blue-400 font-bold mb-2">Events API (<?= count($events) ?> events found)</h4>
                <pre class="bg-black text-blue-400 p-4 rounded text-xs overflow-x-auto max-h-60 custom-scrollbar"><?= json_encode($eventsReq, JSON_PRETTY_PRINT) ?></pre>
            </div>
        </div>
    </div>

</div>

</body>
</html>