<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Debug Notifiche FM26</title>
    <style>
        body { font-family: monospace; background: #1a1a1a; color: #00ff00; padding: 20px; }
        .log-card { border: 1px solid #333; padding: 15px; margin-bottom: 10px; background: #222; border-radius: 8px; }
        .success { color: #00ff00; }
        .error { color: #ff4444; }
        .meta { color: #888; font-size: 12px; }
    </style>
    <meta http-equiv="refresh" content="5"> </head>
<body>
    <h1>Logs Notifiche OneSignal</h1>
    <p>Ultimo aggiornamento: <?php echo date('H:i:s'); ?></p>
    <hr>
    <?php
    $logs = file_exists('push_debug.json') ? json_decode(file_get_contents('push_debug.json'), true) : [];
    if (empty($logs)) echo "<p>Nessun log trovato. Prova a inviare una notifica dall'Admin.</p>";
    foreach ($logs as $log) {
        $res = $log['onesignal_response'];
        $status = isset($res['id']) ? 'SUCCESSO' : 'ERRORE';
        $class = isset($res['id']) ? 'success' : 'error';
        echo "<div class='log-card'>";
        echo "<div class='meta'>{$log['timestamp']}</div>";
        echo "<h3 class='{$class}'>Stato: {$status}</h3>";
        echo "<b>Titolo:</b> " . ($log['payload_sent']['headings']['it'] ?? 'N/D') . "<br>";
        echo "<b>Risposta OneSignal:</b> <pre>" . json_encode($res, JSON_PRETTY_PRINT) . "</pre>";
        echo "</div>";
    }
    ?>
</body>
</html>