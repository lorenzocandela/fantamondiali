<section id="page-profilo" class="page">

    <!-- hero — generata da renderProfiloHero() -->
    <div class="profilo-avatar-section" style="display:none">
        <div class="profilo-avatar-wrap" id="trigger-avatar-upload">
            <img id="profilo-avatar-img" class="profilo-avatar hidden" src="" alt="Avatar">
            <div id="profilo-avatar-initials" class="profilo-avatar-initials">-</div>
            <div class="profilo-avatar-overlay">
                <span class="material-symbols-outlined">photo_camera</span>
            </div>
        </div>
        <input id="avatar-upload" type="file" accept="image/*" class="hidden">
        <div id="profilo-email" class="profilo-email"></div>
        <div id="profilo-joined-status" class="joined-badge">Non iscritto</div>
    </div>

    <!-- pulsante rosa -->
    <div style="padding: 20px 14px;">
        <button class="profilo-squad-btn" id="btn-open-squad">
            <div class="profilo-squad-btn-left">
                <span class="material-symbols-outlined">shield</span>
                <div style="text-align:left;">
                    <div class="profilo-squad-btn-label">La mia rosa</div>
                    <div class="profilo-squad-btn-meta" id="profilo-squad-meta">0 giocatori · 500 crediti</div>
                </div>
            </div>
            <span class="material-symbols-outlined">chevron_right</span>
        </button>
    </div>

    <!-- impostazioni squadra -->
    <div class="profilo-section">
        <div class="profilo-section-title">Impostazioni squadra</div>
        <div class="profilo-field">
            <label class="profilo-field-lbl">Nome squadra</label>
            <input id="profilo-team-name-in" class="field-in" type="text" placeholder="Nome squadra..." autocomplete="off">
        </div>
        <div class="profilo-field">
            <label class="profilo-field-lbl">Logo squadra</label>
            <div class="logo-upload-row">
                <img id="profilo-logo-preview" class="logo-preview hidden" src="" alt="Logo">
                <button class="btn-upload" id="trigger-logo-upload" type="button">
                    <span class="material-symbols-outlined">upload</span>
                    Carica logo
                </button>
                <input id="logo-upload" type="file" accept="image/*" class="hidden">
            </div>
        </div>
        <button id="btn-save-profilo" class="btn-cta" style="margin-top:4px">
            <span class="material-symbols-outlined">save</span>
            Salva modifiche
        </button>
    </div>

    <!-- competizione -->
    <div class="profilo-section">
        <div class="profilo-section-title">Competizione</div>
        <p class="profilo-section-desc">Iscriviti per apparire nella classifica e competere contro gli altri allenatori.</p>
        <button id="btn-join-comp" class="btn-join">
            Iscriviti alla competizione
        </button>
    </div>

    <!-- pwa -->
    <div id="pwa-install-section" style="display:none" class="profilo-section">
        <div class="profilo-section-title">Download</div>
        <p class="profilo-section-desc">Aggiungi FantaMondiali alla home per un'esperienza a schermo intero.</p>
        <button id="btn-install-pwa" class="btn-join" style="background:var(--blue-mid);color:var(--blue)">
            Download
        </button>
    </div>

    <!-- logout -->
    <div class="profilo-section">
        <button id="btn-logout" class="btn-logout-full">
            <span class="material-symbols-outlined">logout</span>
            Esci
        </button>
    </div>
</section>

<!-- bottom sheet rosa -->
<div id="squad-sheet-overlay" class="hidden">
    <div id="squad-sheet">
        <div class="modal-handle" style="margin:10px auto 0"></div>
        <div class="squad-sheet-header">
            <div class="squad-sheet-title">La mia Rosa</div>
            <button id="btn-close-squad-sheet" class="fpicker-close">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="squad-sheet-stats">
            <div class="stat-box"><div class="stat-val" id="sheet-stat-count">0</div><div class="stat-lbl">Giocatori</div></div>
            <div class="stat-box"><div class="stat-val" id="sheet-stat-spent">0</div><div class="stat-lbl">Spesi</div></div>
            <div class="stat-box" style="background:var(--blue-soft);border-color:rgba(0,102,204,.12)">
                <div class="stat-val" style="color:var(--blue)" id="sheet-stat-credits">500</div>
                <div class="stat-lbl">Crediti</div>
            </div>
        </div>
        <div id="squad-sheet-list" class="squad-sheet-list"></div>
    </div>
</div>