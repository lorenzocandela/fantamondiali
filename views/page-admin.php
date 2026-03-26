<section id="page-admin" class="page">
    <div class="admin-stats-row">
        <div class="admin-stat-card">
            <div class="admin-stat-icon" style="background:var(--blue-mid);color:var(--blue)">
                <span class="material-symbols-outlined">group</span>
            </div>
            <div class="admin-stat-val" id="admin-stat-users">-</div>
            <div class="admin-stat-lbl">Utenti</div>
        </div>
        <div class="admin-stat-card">
            <div class="admin-stat-icon" style="background:var(--green-soft);color:var(--green)">
                <span class="material-symbols-outlined">emoji_events</span>
            </div>
            <div class="admin-stat-val" id="admin-stat-joined">-</div>
            <div class="admin-stat-lbl">Iscritti</div>
        </div>
        <div class="admin-stat-card">
            <div class="admin-stat-icon" style="background:var(--amber-soft);color:var(--amber)">
                <span class="material-symbols-outlined">sports_soccer</span>
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
                <div class="admin-control-desc">Gli utenti non possono acquistare giocatori.</div>
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
                <div class="admin-control-desc">Abilita la competizione ufficiale.</div>
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
                <div class="admin-control-desc">Permetti iscrizioni nuovi utenti.</div>
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
            <div class="admin-control-desc">Round-robin su 7 giornate basato sugli iscritti alla competizione.</div>
        </div>
        <div id="admin-cal-preview" class="admin-cal-preview hidden"></div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <button class="admin-action-btn" id="btn-preview-calendar" style="flex:1">
                <span class="material-symbols-outlined">preview</span>
                Anteprima
            </button>
            <button class="admin-action-btn" id="btn-generate-calendar" style="flex:1;background:var(--green-soft);color:var(--green)" disabled>
                Genera
            </button>
            <button class="admin-action-btn danger" id="btn-reset-calendar" style="flex:1">
                <span class="material-symbols-outlined">delete_sweep</span>
                Reset
            </button>
        </div>
    </div>

    <div class="admin-section">
        <div class="admin-section-title">Calcola punteggi</div>
        <div class="admin-control-info" style="margin-bottom:12px">
            <div class="admin-control-label">Giornata da calcolare</div>
            <div class="admin-control-desc">Legge le formazioni schierate, chiama l'API e salva i punteggi su Firestore.</div>
        </div>
        <div class="admin-score-row">
            <select id="admin-score-round" class="nation-select" style="flex:1">
                <option value="">Seleziona giornata...</option>
                <option value="1">GJ1</option>
                <option value="2">GJ2</option>
                <option value="3">GJ3</option>
                <option value="4">Ottavi</option>
                <option value="5">Quarti</option>
                <option value="6">Semifinali</option>
                <option value="7">Finali</option>
            </select>
        </div>
        <button class="admin-action-btn" id="btn-calc-scores" style="margin-top:8px">
            <span class="material-symbols-outlined">calculate</span>
            Calcola
        </button>
        <div id="admin-score-result" class="admin-score-result hidden"></div>
    </div>

    <div class="admin-section">
        <div class="admin-section-title">Moduli di gioco</div>
        <div id="admin-modules-list" class="admin-modules-list"></div>
        <div class="admin-module-input-row" style="margin-top:10px">
            <input id="admin-module-input" class="field-in" type="text" placeholder="es. 4-3-3" style="font-family:var(--mono)">
            <button class="admin-action-btn" id="btn-add-module" style="flex-shrink:0;white-space:nowrap">
                <span class="material-symbols-outlined">add</span>
                Aggiungi
            </button>
        </div>
    </div>

    <div class="admin-section">
        <div class="admin-section-title">Cache API</div>
        <div class="admin-control-info" style="margin-bottom:12px">
            <div class="admin-control-label">Listone giocatori</div>
            <div class="admin-control-desc">Elimina la cache per forzare il ricaricamento dall'API al prossimo accesso.</div>
        </div>
        <button class="admin-action-btn danger" id="btn-clear-cache">
            <span class="material-symbols-outlined">refresh</span>
            Reset cache
        </button>
    </div>

    <div class="admin-section">
        <div class="admin-section-title">Utenti</div>
        <div id="admin-users-list" class="admin-users-list"></div>
    </div>
</section>