<div id="auth-screen" class="auth-screen">
    <div class="auth-inner">
        <div class="auth-brand">
            <img src="logo_fm26_clean.png" alt="Logo FantaMondiali 2026" style="width:50%;margin:0 auto;margin-top:-50px;">
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
                            <span class="material-symbols-outlined">visibility</span>
                        </button>
                    </div>
                </div>
                <div id="error-msg" class="err-box">
                    <span class="material-symbols-outlined">error_outline</span>
                    <span id="error-text"></span>
                </div>
                <button id="btn-submit" class="btn-cta">
                    <span id="btn-label">Accedi</span>
                </button>
            </div>
        </div>

        <div id="pwa-install-section-auth" style="display:none;margin-top:16px;text-align:center">
            <button id="btn-install-pwa-auth" class="btn-cta" style="background:transparent;border:1.5px solid rgba(0,0,0,0.12);color:var(--text-2);font-size:14px;gap:6px">
                <span class="material-symbols-outlined" style="font-size:18px">install_mobile</span>
                Installa app
            </button>
        </div>
    </div>
</div>