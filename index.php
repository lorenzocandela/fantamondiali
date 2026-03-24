<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <meta name="theme-color" content="#f2f2f7">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <title>FM26</title>
        <link rel="apple-touch-icon" href="logo_fm26.png">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="manifest" href="/manifest.json">
        <link rel="apple-touch-icon" href="logo_fm26.png">
        <link rel="stylesheet" href="assets/css/base.css">
        <link rel="stylesheet" href="assets/css/layout.css">
        <link rel="stylesheet" href="assets/css/components.css">
        <link rel="stylesheet" href="assets/css/pages.css">
        <link rel="stylesheet" href="assets/css/animations.css">
        <link rel="stylesheet" href="assets/css/formation.css">
        <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    </head>
    <body>
        <div id="auth-screen" class="auth-screen">
            <div class="auth-inner">
                <div class="auth-brand">
                    <img src="logo_fm26_clean.png" alt="Logo FantaMondiali 2026" style="width:50%; margin: 0 auto; margin-top: -50px;">
                </div>

                <div class="auth-card">
                    <div class="seg-wrap">
                        <div class="seg-control">
                            <button id="tab-login"    class="seg-btn active">Accedi</button>
                            <button id="tab-register" class="seg-btn">Registrati</button>
                        </div>
                    </div>
                    <div class="form-body">
                        <div id="register-fields" class="hidden">
                            <div class="field">
                                <label class="field-lbl" for="team-name">Nome squadra</label>
                                <input id="team-name" class="field-in" type="text" placeholder="Es. Gli Invincibili" autocomplete="off">
                            </div>
                        </div>
                        <div class="field">
                            <label class="field-lbl" for="email">Email</label>
                            <input id="email" class="field-in" type="email" placeholder="nome@email.com" autocomplete="email">
                        </div>
                        <div class="field">
                            <label class="field-lbl" for="password">Password</label>
                            <div class="pwd-row">
                                <input id="password" class="field-in" type="password" placeholder="Minimo 6 caratteri" autocomplete="current-password">
                                <button id="toggle-pwd" class="pwd-eye" type="button" aria-label="Mostra password">
                                    <span class="material-icons-round">visibility</span>
                                </button>
                            </div>
                        </div>
                        <div id="error-msg" class="err-box">
                            <span class="material-icons-round">error_outline</span>
                            <span id="error-text"></span>
                        </div>
                        <button id="btn-submit" class="btn-cta">
                            <span id="btn-label">Accedi</span>
                        </button>
                    </div>
                </div>

                <div id="pwa-install-section-auth" style="display:none;margin-top:16px;text-align:center">
                    <button id="btn-install-pwa-auth" class="btn-cta" style="background:transparent;border:1.5px solid rgba(255,255,255,0.2);color:inherit;font-size:14px;gap:6px">
                        <span class="material-icons-round" style="font-size:18px">install_mobile</span>
                        Download
                    </button>
                </div>
            </div>
        </div>

        <div id="main-app" class="main-app hidden">

            <header class="top-bar">
                <span class="top-logo">Fanta<span>Mondiali</span></span>
                <div class="top-right">
                    <div class="credits-pill">
                        <span class="material-icons-round">toll</span>
                        <span id="credits-val">500</span>
                    </div>
                    <button id="btn-profile-avatar" class="avatar-btn" aria-label="Profilo">
                        <img id="topbar-avatar-img" class="avatar-btn-img hidden" src="" alt="Avatar">
                        <span id="topbar-avatar-initials" class="avatar-btn-initials">?</span>
                    </button>
                </div>
            </header>

            <!-- PAGE: LISTONE -->
            <section id="page-listone" class="page active">
                <div class="content-header">
                    <div>
                        <h2 class="page-title">Listone</h2>
                        <p class="page-subtitle" id="listone-subtitle">caricamento...</p>
                    </div>
                    <button class="view-toggle-btn" id="view-toggle" aria-label="Cambia vista">
                        <span class="material-icons-round" id="view-toggle-icon">view_list</span>
                    </button>
                </div>
                <div class="search-wrap">
                    <div class="search-icon">
                        <span class="material-icons-round">search</span>
                    </div>
                    <input id="search-in" class="search-in" type="text" placeholder="Cerca giocatore o nazione...">
                </div>
                <div class="filter-row" id="role-filter-row">
                    <button class="chip active" data-role="ALL">Tutti</button>
                    <button class="chip" data-role="POR">Portieri</button>
                    <button class="chip" data-role="DIF">Difensori</button>
                    <button class="chip" data-role="CEN">Centrocampisti</button>
                    <button class="chip" data-role="ATT">Attaccanti</button>
                </div>
                <div class="filter-row" id="nation-filter-row" style="padding-top: 0;"></div>
                <div id="players-grid" class="players-grid"></div>
            </section>

            <!-- PAGE: SQUADRA -->
            <section id="page-squadra" class="page">
                <div class="content-header">
                    <div>
                        <h2 class="page-title">La mia Squadra</h2>
                        <p class="page-subtitle">gestisci la tua rosa</p>
                    </div>
                </div>
                <div class="squad-header">
                    <div class="squad-icon">
                        <span class="material-icons-round">shield</span>
                    </div>
                    <div>
                        <div class="squad-info-name" id="squad-team-name">-</div>
                        <div class="squad-info-meta" id="squad-team-meta">0 giocatori · 500 crediti</div>
                    </div>
                </div>
                <div class="squad-stats">
                    <div class="stat-box">
                        <div class="stat-val" id="stat-count">0</div>
                        <div class="stat-lbl">Giocatori</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-val" id="stat-spent">0</div>
                        <div class="stat-lbl">Spesi</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-val" id="stat-credits">500</div>
                        <div class="stat-lbl">Rimasti</div>
                    </div>
                </div>
                <div id="squad-list" class="squad-list"></div>
            </section>

            <section id="page-formazione" class="page">
                <div class="content-header">
                    <div>
                        <h2 class="page-title">Formazione</h2>
                        <p class="page-subtitle" id="form-subtitle">schiera i tuoi 11</p>
                    </div>
                </div>
                <div id="formation-content"></div>
            </section>

            <!-- PAGE: COMPETIZIONI -->
            <section id="page-competizioni" class="page">
                <div class="content-header">
                    <div>
                        <h2 class="page-title">Competizioni</h2>
                        <p class="page-subtitle">squadre iscritte</p>
                    </div>
                </div>

                <div class="comp-banner">
                    <div class="comp-banner-icon">
                        <span class="material-icons-round">emoji_events</span>
                    </div>
                    <div>
                        <div class="comp-banner-title">FantaMondiali 2026</div>
                        <div class="comp-banner-sub">La competizione iniziera con i Mondiali</div>
                    </div>
                    <div class="comp-status-badge">In attesa</div>
                </div>

                <div id="comp-teams-list" class="comp-teams-list"></div>
            </section>

            <!-- PAGE: PROFILO -->
            <section id="page-profilo" class="page">
                <div class="content-header">
                    <div>
                        <h2 class="page-title">Profilo</h2>
                        <p class="page-subtitle">la tua squadra</p>
                    </div>
                </div>

                <div class="profilo-avatar-section">
                    <div class="profilo-avatar-wrap" id="trigger-avatar-upload">
                        <img id="profilo-avatar-img" class="profilo-avatar hidden" src="" alt="Avatar">
                        <div id="profilo-avatar-initials" class="profilo-avatar-initials">-</div>
                        <div class="profilo-avatar-overlay">
                            <span class="material-icons-round">photo_camera</span>
                        </div>
                    </div>
                    <input id="avatar-upload" type="file" accept="image/*" class="hidden">
                    <div id="profilo-email" class="profilo-email"></div>
                    <div id="profilo-joined-status" class="joined-badge">Non iscritto</div>
                </div>

                <div class="profilo-section">
                    <div class="profilo-section-title">Squadra</div>

                    <div class="profilo-field">
                        <label class="profilo-field-lbl">Nome squadra</label>
                        <input id="profilo-team-name-in" class="field-in" type="text" placeholder="Nome squadra..." autocomplete="off">
                    </div>

                    <div class="profilo-field">
                        <label class="profilo-field-lbl">Logo squadra</label>
                        <div class="logo-upload-row">
                            <img id="profilo-logo-preview" class="logo-preview hidden" src="" alt="Logo">
                            <button class="btn-upload" id="trigger-logo-upload" type="button">
                                <span class="material-icons-round">upload</span>
                                Carica logo
                            </button>
                        </div>
                        <input id="logo-upload" type="file" accept="image/*" class="hidden">
                    </div>

                    <button id="btn-save-profilo" class="btn-cta" style="margin-top:8px">
                        Salva modifiche
                    </button>
                </div>

                <div class="profilo-section" id="pwa-install-section" style="display:none">
                    <div class="profilo-section-title">Installa app</div>
                    <p class="profilo-section-desc">
                        Aggiungi FantaMondiali alla home del tuo dispositivo per un'esperienza a schermo intero.
                    </p>
                    <button id="btn-install-pwa" class="btn-join" style="background:var(--blue-mid);color:var(--blue)">
                        Download
                    </button>
                </div>

                <div class="profilo-section">
                    <div class="profilo-section-title">Competizione</div>
                    <p class="profilo-section-desc">
                        Iscriviti alla competizione per apparire nella classifica e competere contro gli altri allenatori.
                    </p>
                    <button id="btn-join-comp" class="btn-join">
                        Iscriviti alla competizione
                    </button>
                </div>

                <div class="profilo-section">
                    <button id="btn-logout" class="btn-logout-full">
                        <span class="material-icons-round">logout</span>
                        Esci dall'account
                    </button>
                </div>

            </section>

            <!-- PAGE: CALENDARIO -->
            <section id="page-calendario" class="page">
                <div class="content-header">
                    <div>
                        <h2 class="page-title">Calendario</h2>
                        <p class="page-subtitle" id="cal-subtitle">scontri diretti</p>
                    </div>
                </div>

                <div class="cal-seg-wrap">
                    <div class="cal-seg">
                        <button class="cal-seg-btn active" data-view="scontri">Scontri</button>
                        <button class="cal-seg-btn" data-view="classifica">Classifica</button>
                    </div>
                </div>

                <div id="cal-view-scontri">
                    <div class="cal-round-nav">
                        <button class="cal-nav-btn" id="cal-round-prev">
                            <span class="material-icons-round">chevron_left</span>
                        </button>
                        <div class="cal-round-label" id="cal-round-label">Giornata 1</div>
                        <button class="cal-nav-btn" id="cal-round-next">
                            <span class="material-icons-round">chevron_right</span>
                        </button>
                    </div>
                    <div id="cal-matches-list" class="cal-matches-list"></div>
                </div>

                <div id="cal-view-classifica" class="hidden">
                    <div id="cal-standings-list" class="cal-standings-list"></div>
                </div>
            </section>

            <section id="page-admin" class="page">
                <div class="content-header">
                    <div>
                        <h2 class="page-title">Admin</h2>
                        <p class="page-subtitle" id="admin-subtitle">pannello di controllo</p>
                    </div>
                </div>

                <div class="admin-stats-row">
                    <div class="admin-stat-card">
                        <div class="admin-stat-icon" style="background:var(--blue-mid);color:var(--blue)">
                            <span class="material-icons-round">group</span>
                        </div>
                        <div class="admin-stat-val" id="admin-stat-users">-</div>
                        <div class="admin-stat-lbl">Utenti</div>
                    </div>
                    <div class="admin-stat-card">
                        <div class="admin-stat-icon" style="background:var(--green-soft);color:var(--green)">
                            <span class="material-icons-round">emoji_events</span>
                        </div>
                        <div class="admin-stat-val" id="admin-stat-joined">-</div>
                        <div class="admin-stat-lbl">Iscritti</div>
                    </div>
                    <div class="admin-stat-card">
                        <div class="admin-stat-icon" style="background:var(--amber-soft);color:var(--amber)">
                            <span class="material-icons-round">sports_soccer</span>
                        </div>
                        <div class="admin-stat-val" id="admin-stat-players">-</div>
                        <div class="admin-stat-lbl">Giocatori</div>
                    </div>
                </div>

                <div class="admin-section">
                    <div class="admin-section-title">Mercato</div>
                    <div class="admin-control-row">
                        <div class="admin-control-info">
                            <div class="admin-control-label" id="admin-market-status-text">Mercato chiuso</div>
                            <div class="admin-control-desc">Gli utenti non possono acquistare giocatori</div>
                        </div>
                        <div class="admin-toggle-btn-wrap">
                            <button class="admin-toggle-btn" id="toggle-market" data-state="off">
                                <span class="admin-toggle-dot"></span>
                                <span class="admin-toggle-label">Off</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="admin-section">
                    <div class="admin-section-title">Competizione</div>
                    <div class="admin-control-row">
                        <div class="admin-control-info">
                            <div class="admin-control-label" id="admin-comp-status-text">Non attiva</div>
                            <div class="admin-control-desc">Abilita la competizione ufficiale</div>
                        </div>
                        <div class="admin-toggle-btn-wrap">
                            <button class="admin-toggle-btn" id="toggle-competition" data-state="off">
                                <span class="admin-toggle-dot"></span>
                                <span class="admin-toggle-label">Off</span>
                            </button>
                        </div>
                    </div>
                    <div class="admin-control-row">
                        <div class="admin-control-info">
                            <div class="admin-control-label">Registrazioni</div>
                            <div class="admin-control-desc">Permetti iscrizioni nuovi utenti</div>
                        </div>
                        <div class="admin-toggle-btn-wrap">
                            <button class="admin-toggle-btn" id="toggle-registrations" data-state="on">
                                <span class="admin-toggle-dot"></span>
                                <span class="admin-toggle-label">On</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="admin-section">
                    <div class="admin-section-title">Giornata corrente</div>
                    <div class="admin-matchday-display">
                        <div class="admin-matchday-num" id="admin-matchday-num">-</div>
                        <div class="admin-matchday-info">
                            <div class="admin-matchday-label" id="admin-matchday-label">Calcolo in corso...</div>
                            <div class="admin-matchday-dates" id="admin-matchday-dates"></div>
                        </div>
                    </div>
                    <div id="admin-matchday-list" class="admin-matchday-list"></div>
                </div>

                <div class="admin-section">
                    <div class="admin-section-title">Calendario scontri</div>
                    <div class="admin-control-info" style="margin-bottom:12px">
                        <div class="admin-control-label">Generazione automatica</div>
                        <div class="admin-control-desc">Round-robin basato sugli utenti iscritti alla competizione. Sovrascrive il calendario esistente.</div>
                    </div>
                    <div id="admin-cal-preview" class="admin-cal-preview hidden"></div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <button class="admin-action-btn" id="btn-preview-calendar" style="flex:1">
                            <span class="material-icons-round">preview</span>
                            Anteprima
                        </button>
                        <button class="admin-action-btn" id="btn-generate-calendar" style="flex:1;background:var(--green-soft);color:var(--green)" disabled>
                            <span class="material-icons-round">save</span>
                            Genera e salva
                        </button>
                        <button class="admin-action-btn danger" id="btn-reset-calendar" style="flex:1">
                            <span class="material-icons-round">delete_sweep</span>
                            Reset calendario
                        </button>
                    </div>
                </div>

                <div class="admin-section">
                    <div class="admin-section-title">Calcola punteggi</div>
                    <div class="admin-control-info" style="margin-bottom:12px">
                        <div class="admin-control-label">Giornata da calcolare</div>
                        <div class="admin-control-desc">Legge le rose, simula le statistiche e salva i punteggi su Firestore.</div>
                    </div>
                    <div class="admin-score-row">
                        <select id="admin-score-round" class="nation-select" style="flex:1">
                            <option value="">Seleziona giornata...</option>
                            <option value="1">GJ1 – Fase gironi 1</option>
                            <option value="2">GJ2 – Fase gironi 2</option>
                            <option value="3">GJ3 – Fase gironi 3</option>
                            <option value="4">Ottavi di finale</option>
                            <option value="5">Quarti di finale</option>
                            <option value="6">Semifinali</option>
                            <option value="7">Finale</option>
                        </select>
                    </div>
                    <button class="admin-action-btn" id="btn-calc-scores" style="margin-top:8px">
                        <span class="material-icons-round">calculate</span>
                        Calcola punteggi giornata
                    </button>
                    <div id="admin-score-result" class="admin-score-result hidden"></div>
                </div>

                <div class="admin-section">
                    <div class="admin-section-title">Moduli di gioco</div>
                    <div class="admin-control-info" style="margin-bottom:12px">
                        <div class="admin-control-label">Moduli disponibili</div>
                        <div class="admin-control-desc">I moduli custom si aggiungono a quelli predefiniti. Formato: DIF-CEN-ATT (es. 4-3-3) o DIF-MED-TRQ-ATT (es. 4-2-3-1).</div>
                    </div>
                    <div id="admin-modules-list" class="admin-modules-list"></div>
                    <div class="admin-module-input-row" style="margin-top:10px">
                        <input id="admin-module-input" class="field-in" type="text" placeholder="es. 4-3-3" style="font-family:var(--mono)">
                        <button class="admin-action-btn" id="btn-add-module" style="flex-shrink:0;white-space:nowrap">
                            <span class="material-icons-round">add</span>
                            Aggiungi
                        </button>
                    </div>
                </div>

                <div class="admin-section">
                    <div class="admin-section-title">Cache API</div>
                    <div class="admin-control-info" style="margin-bottom:12px">
                        <div class="admin-control-label">Listone giocatori</div>
                        <div class="admin-control-desc">Elimina la cache per forzare il ricaricamento dall'API al prossimo accesso</div>
                    </div>
                    <button class="admin-action-btn danger" id="btn-clear-cache">
                        <span class="material-icons-round">refresh</span>
                        Reset cache
                    </button>
                </div>

                <div class="admin-section">
                    <div class="admin-section-title">Utenti</div>
                    <div id="admin-users-list" class="admin-users-list"></div>
                </div>

            </section>

            <nav class="tab-bar">
                <button class="tab-item active" id="nav-listone">
                    <span class="material-icons-round">format_list_bulleted</span>
                </button>
                <button class="tab-item" id="nav-formazione">
                    <span class="material-icons-round">view_list</span>
                </button>
                <button class="tab-item" id="nav-squadra">
                    <span class="material-icons-round">shield</span>
                </button>
                <button class="tab-item" id="nav-calendario">
                    <span class="material-icons-round">calendar_month</span>
                </button>
                <button class="tab-item" id="nav-competizioni">
                    <span class="material-icons-round">emoji_events</span>
                </button>
                <button class="tab-item hidden" id="nav-admin">
                    <span class="material-icons-round">admin_panel_settings</span>
                </button>
            </nav>

        </div>

        <div id="modal-overlay" class="modal-overlay hidden">
            <div class="modal-sheet" role="dialog" aria-modal="true">
                <div class="modal-handle"></div>
                <div class="modal-player-header">
                    <img id="modal-photo" class="modal-photo" src="" alt="">
                    <div>
                        <div id="modal-name" class="modal-player-name"></div>
                        <div id="modal-meta" class="modal-player-meta"></div>
                    </div>
                </div>
                <div class="modal-stats-grid">
                    <div class="modal-stat">
                        <div class="modal-stat-val" id="modal-goals">-</div>
                        <div class="modal-stat-lbl">Gol</div>
                    </div>
                    <div class="modal-stat">
                        <div class="modal-stat-val" id="modal-assists">-</div>
                        <div class="modal-stat-lbl">Assist</div>
                    </div>
                    <div class="modal-stat">
                        <div class="modal-stat-val" id="modal-rating">-</div>
                        <div class="modal-stat-lbl">Rating</div>
                    </div>
                </div>
                <button id="modal-btn-add" class="modal-btn-add">
                    <span class="material-icons-round">add_circle</span>
                    <span id="modal-btn-label">Aggiungi</span>
                </button>
            </div>
        </div>

        <div id="toast-wrap" class="toast-wrap"></div>

        <script type="module" src="assets/js/auth.js"></script>
        <script type="module" src="assets/js/app.js"></script>

        <div id="formation-picker-overlay" class="hidden">
            <div id="formation-picker-sheet"></div>
        </div>
    </body>
</html>