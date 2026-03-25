<?php
header('Content-Type: application/json');
define('API_KEY', '1a4942a032906326bcdaa564e10dbe65');

// Cerca la league giusta per i playoff UEFA WC 2026
// Prova vari ID candidati
$candidates = [960, 882, 32, 34, 30, 531, 848, 904, 906, 16];
$tomorrow = '2026-03-26';
$results = [];

foreach ($candidates as $lid) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => "https://v3.football.api-sports.io/fixtures?date={$tomorrow}&league={$lid}&season=2026",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['x-apisports-key: ' . API_KEY, 'Accept: application/json'],
    ]);
    $resp = json_decode(curl_exec($ch), true);
    curl_close($ch);
    $count = count($resp['response'] ?? []);
    $matches = array_map(fn($f) => ($f['teams']['home']['name'] ?? '?') . ' vs ' . ($f['teams']['away']['name'] ?? '?'), array_slice($resp['response'] ?? [], 0, 4));
    $results["league_{$lid}"] = ['count' => $count, 'matches' => $matches, 'errors' => $resp['errors'] ?? null];
    usleep(200000);
}

// Prova anche a cercare per nome
$ch2 = curl_init();
curl_setopt_array($ch2, [
    CURLOPT_URL            => "https://v3.football.api-sports.io/leagues?search=world%20cup&season=2026",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HTTPHEADER     => ['x-apisports-key: ' . API_KEY, 'Accept: application/json'],
]);
$leagueSearch = json_decode(curl_exec($ch2), true);
curl_close($ch2);

$leagueList = array_map(fn($l) => [
    'id'   => $l['league']['id'] ?? null,
    'name' => $l['league']['name'] ?? '',
    'type' => $l['league']['type'] ?? '',
], $leagueSearch['response'] ?? []);

// Cerca anche "qualification" e "playoff"
$ch3 = curl_init();
curl_setopt_array($ch3, [
    CURLOPT_URL            => "https://v3.football.api-sports.io/leagues?search=qualification&season=2026",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HTTPHEADER     => ['x-apisports-key: ' . API_KEY, 'Accept: application/json'],
]);
$qualSearch = json_decode(curl_exec($ch3), true);
curl_close($ch3);

$qualList = array_map(fn($l) => [
    'id'   => $l['league']['id'] ?? null,
    'name' => $l['league']['name'] ?? '',
    'type' => $l['league']['type'] ?? '',
], $qualSearch['response'] ?? []);

echo json_encode([
    'fixture_checks'    => $results,
    'leagues_worldcup'  => $leagueList,
    'leagues_qualification' => $qualList,
], JSON_PRETTY_PRINT);