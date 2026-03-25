<?php
define('API_KEY', '1a4942a032906326bcdaa564e10dbe65');
$fixtureId = 1537243; // Kazakhstan vs Namibia

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

// 1. Info Partita (Risultato, Status, Tempo)
$fixtureReq = apiGet("https://v3.football.api-sports.io/fixtures?id={$fixtureId}");
$fixtureData = $fixtureReq['response'][0] ?? null;

// 2. Formazioni
$lineupsReq = apiGet("https://v3.football.api-sports.io/fixtures/lineups?fixture={$fixtureId}");
$lineups = $lineupsReq['response'] ?? [];

// 3. Eventi
$eventsReq = apiGet("https://v3.football.api-sports.io/fixtures/events?fixture={$fixtureId}");
$events = $eventsReq['response'] ?? [];

// Helper per le icone degli eventi
function getEventIcon($type, $detail) {
    if ($type === 'Goal') return '⚽';
    if ($type === 'Card' && str_contains($detail, 'Yellow')) return '🟨';
    if ($type === 'Card' && str_contains($detail, 'Red')) return '🟥';
    if ($type === 'subst') return '🔄';
    if ($type === 'Var') return '📺';
    return '⚡';
}

// Helper per i colori dei ruoli
function getRoleColor($pos) {
    switch ($pos) {
        case 'G': return 'bg-yellow-500 text-yellow-900';
        case 'D': return 'bg-blue-500 text-blue-100';
        case 'M': return 'bg-green-500 text-green-100';
        case 'F': return 'bg-red-500 text-red-100';
        default: return 'bg-gray-500 text-gray-100';
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Match Live | Road to 2026</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #0f172a; color: #f8fafc; }
        /* Scrollbar stilizzata per gli eventi */
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #1e293b; border-radius: 8px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #475569; border-radius: 8px; }
    </style>
</head>
<body class="p-4 md:p-8">

<div class="max-w-6xl mx-auto space-y-6">
    
    <?php if ($fixtureData): ?>
    <div class="bg-slate-800 rounded-2xl shadow-2xl p-6 border border-slate-700 flex flex-col md:flex-row items-center justify-between">
        <div class="flex items-center gap-6 w-1/3 justify-end">
            <h2 class="text-2xl font-bold text-slate-200"><?= $fixtureData['teams']['home']['name'] ?></h2>
            <img src="<?= $fixtureData['teams']['home']['logo'] ?>" alt="Home" class="w-20 h-20 object-contain">
        </div>
        
        <div class="flex flex-col items-center w-1/3">
            <span class="text-sm font-semibold text-emerald-400 mb-1 tracking-widest uppercase">
                <?= $fixtureData['fixture']['status']['short'] ?> 
                <?= $fixtureData['fixture']['status']['elapsed'] ? $fixtureData['fixture']['status']['elapsed'] . "'" : '' ?>
            </span>
            <div class="text-5xl font-extrabold tracking-tighter bg-gradient-to-r from-white to-slate-400 bg-clip-text text-transparent">
                <?= $fixtureData['goals']['home'] ?? 0 ?> - <?= $fixtureData['goals']['away'] ?? 0 ?>
            </div>
            <span class="text-xs text-slate-400 mt-2"><?= $fixtureData['fixture']['venue']['name'] ?? 'Stadio' ?></span>
        </div>

        <div class="flex items-center gap-6 w-1/3 justify-start">
            <img src="<?= $fixtureData['teams']['away']['logo'] ?>" alt="Away" class="w-20 h-20 object-contain">
            <h2 class="text-2xl font-bold text-slate-200"><?= $fixtureData['teams']['away']['name'] ?></h2>
        </div>
    </div>
    <?php else: ?>
    <div class="bg-red-500/20 text-red-400 p-4 rounded-xl border border-red-500/50 text-center">
        Dati partita non disponibili. Controlla l'ID.
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        
        <?php if (!empty($lineups[0])): $home = $lineups[0]; ?>
        <div class="bg-slate-800 rounded-2xl p-5 border border-slate-700 col-span-1">
            <div class="flex justify-between items-center mb-4 border-b border-slate-700 pb-2">
                <h3 class="font-bold text-lg"><?= $home['team']['name'] ?></h3>
                <span class="px-2 py-1 bg-slate-700 text-xs font-bold rounded-lg"><?= $home['formation'] ?></span>
            </div>
            
            <div class="space-y-1">
                <p class="text-xs text-slate-400 uppercase font-semibold mb-2">Titolari</p>
                <?php foreach ($home['startXI'] as $p): ?>
                <div class="flex items-center gap-3 p-2 hover:bg-slate-700/50 rounded-lg transition">
                    <span class="w-6 h-6 flex items-center justify-center text-[10px] font-bold rounded-full <?= getRoleColor($p['player']['pos']) ?>"><?= $p['player']['pos'] ?></span>
                    <span class="font-semibold text-sm"><?= $p['player']['name'] ?> <span class="text-slate-500 text-xs ml-1">#<?= $p['player']['number'] ?></span></span>
                </div>
                <?php endforeach; ?>
                
                <p class="text-xs text-slate-400 uppercase font-semibold mb-2 mt-4">Panchina</p>
                <?php foreach ($home['substitutes'] as $p): ?>
                <div class="flex items-center gap-3 p-2 opacity-70 hover:opacity-100 hover:bg-slate-700/50 rounded-lg transition">
                    <span class="w-6 h-6 flex items-center justify-center text-[10px] font-bold rounded-full <?= getRoleColor($p['player']['pos']) ?>"><?= $p['player']['pos'] ?></span>
                    <span class="text-sm"><?= $p['player']['name'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="bg-slate-900 rounded-2xl p-5 border border-slate-700 col-span-2 shadow-inner">
            <h3 class="font-bold text-lg mb-4 text-center">Live Events</h3>
            
            <?php if (empty($events)): ?>
                <div class="text-center text-slate-500 py-10">Nessun evento registrato al momento.</div>
            <?php else: ?>
                <div class="space-y-4 max-h-[600px] overflow-y-auto custom-scrollbar pr-2">
                    <?php 
                    // Invertiamo l'array per avere l'evento più recente in alto
                    foreach (array_reverse($events) as $ev): 
                        $isHome = isset($fixtureData) && $ev['team']['id'] === $fixtureData['teams']['home']['id'];
                    ?>
                    <div class="flex items-center gap-4 bg-slate-800 p-3 rounded-xl border border-slate-700/50 relative overflow-hidden">
                        <div class="absolute inset-y-0 <?= $isHome ? 'left-0 border-l-4 border-blue-500' : 'right-0 border-r-4 border-red-500' ?> w-full bg-gradient-to-r <?= $isHome ? 'from-blue-500/10 to-transparent' : 'from-transparent to-red-500/10' ?> pointer-events-none"></div>
                        
                        <div class="font-black text-slate-400 w-10 text-right shrink-0"><?= $ev['time']['elapsed'] ?>'</div>
                        <div class="text-2xl shrink-0"><?= getEventIcon($ev['type'], $ev['detail']) ?></div>
                        
                        <div class="flex-1 min-w-0 z-10">
                            <p class="font-bold text-slate-200 truncate">
                                <?= $ev['player']['name'] ?>
                                <?php if($ev['type'] === 'subst' && $ev['assist']['name']): ?>
                                    <span class="text-slate-400 text-sm font-normal"> (in) / <?= $ev['assist']['name'] ?> (out)</span>
                                <?php endif; ?>
                            </p>
                            <p class="text-xs text-slate-400"><?= $ev['detail'] ?> • <?= $ev['team']['name'] ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($lineups[1])): $away = $lineups[1]; ?>
        <div class="bg-slate-800 rounded-2xl p-5 border border-slate-700 col-span-1">
            <div class="flex justify-between items-center mb-4 border-b border-slate-700 pb-2">
                <span class="px-2 py-1 bg-slate-700 text-xs font-bold rounded-lg"><?= $away['formation'] ?></span>
                <h3 class="font-bold text-lg text-right"><?= $away['team']['name'] ?></h3>
            </div>
            
            <div class="space-y-1">
                <p class="text-xs text-slate-400 uppercase font-semibold mb-2 text-right">Titolari</p>
                <?php foreach ($away['startXI'] as $p): ?>
                <div class="flex items-center justify-end gap-3 p-2 hover:bg-slate-700/50 rounded-lg transition">
                    <span class="font-semibold text-sm text-right"><span class="text-slate-500 text-xs mr-1">#<?= $p['player']['number'] ?></span> <?= $p['player']['name'] ?></span>
                    <span class="w-6 h-6 flex items-center justify-center text-[10px] font-bold rounded-full <?= getRoleColor($p['player']['pos']) ?>"><?= $p['player']['pos'] ?></span>
                </div>
                <?php endforeach; ?>
                
                <p class="text-xs text-slate-400 uppercase font-semibold mb-2 mt-4 text-right">Panchina</p>
                <?php foreach ($away['substitutes'] as $p): ?>
                <div class="flex items-center justify-end gap-3 p-2 opacity-70 hover:opacity-100 hover:bg-slate-700/50 rounded-lg transition">
                    <span class="text-sm text-right"><?= $p['player']['name'] ?></span>
                    <span class="w-6 h-6 flex items-center justify-center text-[10px] font-bold rounded-full <?= getRoleColor($p['player']['pos']) ?>"><?= $p['player']['pos'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
    </div>
</div>

</body>
</html>