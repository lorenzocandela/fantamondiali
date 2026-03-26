<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#f2f2f7">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>FM26</title>
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="logo_fm26.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="assets/css/layout.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/pages.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="assets/css/formation.css">
    <link rel="stylesheet" href="assets/css/match-detail.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
</head>
<body>

<?php include 'views/auth.php'; ?>

<div id="main-app" class="main-app hidden">

    <header class="top-bar">
        <div class="top-left">
            <button id="btn-topbar-logo" class="topbar-team-logo-btn" aria-label="La mia squadra">
                <img id="topbar-team-logo-img" class="topbar-team-logo-img hidden" src="" alt="">
                <span id="topbar-team-logo-fallback" class="topbar-team-logo-fallback">
                    <span class="material-symbols-outlined">shield</span>
                </span>
            </button>
        </div>
        <div class="top-right">
            <div class="credits-pill">
                <span class="material-symbols-outlined">toll</span>
                <span id="credits-val">500</span>
            </div>
            <button id="btn-profile-avatar" class="avatar-btn" aria-label="Profilo">
                <img id="topbar-avatar-img" class="avatar-btn-img hidden" src="" alt="Avatar">
                <span id="topbar-avatar-initials" class="avatar-btn-initials">?</span>
            </button>
        </div>
    </header>

    <?php include 'views/page-listone.php'; ?>
    <?php include 'views/page-squadra.php'; ?>
    <?php include 'views/page-formazione.php'; ?>
    <?php include 'views/page-profilo.php'; ?>
    <?php include 'views/page-calendario.php'; ?>
    <?php include 'views/page-admin.php'; ?>

    <?php include 'views/nav.php'; ?>

</div>

<?php include 'views/modals.php'; ?>

<script type="module" src="assets/js/auth.js"></script>
<script type="module" src="assets/js/app.js"></script>
<script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
<script>
    window.OneSignalDeferred = window.OneSignalDeferred || [];
        OneSignalDeferred.push(function(OneSignal) {
        OneSignal.init({
            appId: "74d236f6-3c61-476d-a2ce-f71beed3c045",
        });
    });
</script>
</body>
</html>