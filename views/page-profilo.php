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

    <div id="pwa-install-section" style="display:none" class="profilo-section">
        <div class="profilo-section-title">Installa app</div>
        <p class="profilo-section-desc">
            Aggiungi FantaMondiali alla home del tuo dispositivo per un'esperienza a schermo intero.
        </p>
        <button id="btn-install-pwa" class="btn-join" style="background:var(--blue-mid);color:var(--blue)">
            <span class="material-icons-round">install_mobile</span>
            Installa app
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