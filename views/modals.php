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
        <div id="modal-price-picker" class="price-picker hidden">
            <div class="price-picker-label">Prezzo d'acquisto</div>
            <div class="price-picker-wheel-wrap">
                <div class="price-picker-highlight"></div>
                <div class="price-picker-fade top"></div>
                <div class="price-picker-fade bottom"></div>
                <div id="price-wheel" class="price-picker-wheel"></div>
            </div>
            <div class="price-picker-val">
                <span class="material-symbols-outlined">toll</span>
                <span id="price-picker-display">1</span>
            </div>
        </div>

        <button id="modal-btn-add" class="modal-btn-add">
            <span class="material-symbols-outlined">add_circle</span>
            <span id="modal-btn-label">Aggiungi</span>
        </button>
    </div>
</div>

<div id="formation-picker-overlay" class="hidden">
    <div id="formation-picker-sheet"></div>
</div>

<div id="toast-wrap" class="toast-wrap"></div>


<!-- REGOLAMENTO -->
<style id="fm-rules-styles">
    #rules-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
        background: var(--bg-1, #ffffff); 
        z-index: 999999; overflow-y: auto; transform: translateY(100%); 
        transition: transform 0.4s cubic-bezier(0.1, 0, 0.1, 1);
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    }
    
    /* Header leggermente trasparente per un effetto fluido allo scroll */
    .r-header {
        position: sticky; top: 0; background: rgba(255, 255, 255, 0.85); 
        backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
        padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; 
        border-bottom: 1px solid rgba(0,0,0,0.05); z-index: 100;
    }
    .r-title { font-weight: 800; font-size: 22px; color: var(--text-1, #000); letter-spacing: -0.5px; }
    .r-close { background: var(--bg-2, #f2f2f7); color: var(--text-1); border: none; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; }
    
    .r-content { padding: 10px 20px 80px; }
    
    /* Sezioni separate solo da respiro (margin), niente bordi duri */
    .r-section { margin-top: 32px; margin-bottom: 16px; }
    .r-h3 { font-weight: 800; font-size: 17px; color: var(--text-1); margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px; letter-spacing: -0.3px; }
    .r-h3 .material-symbols-outlined { color: var(--blue, #007aff); font-size: 22px !important; font-variation-settings: 'FILL' 1; }
    
    .r-p { font-size: 15px; color: var(--text-2, #3a3a3c); line-height: 1.4; margin: 0 0 12px 0; }
    .r-strong { color: var(--text-1); font-weight: 700; }
    
    /* Liste super pulite */
    .r-list { margin: 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 10px; }
    .r-li { font-size: 15px; color: var(--text-2); display: flex; align-items: center; gap: 10px; line-height: 1.3;}
    
    /* Pillole di ruolo dai colori pastello morbidissimi */
    .r-pill { font-size: 11px; font-weight: 800; padding: 3px 8px; border-radius: 12px; font-family: var(--mono, monospace); }
    .r-pill.por { background: #fff0e0; color: #ff9500; }
    .r-pill.dif { background: #e8f8ec; color: #34c759; }
    .r-pill.cen { background: #e5f1ff; color: #007aff; }
    .r-pill.att { background: #ffebeb; color: #ff3b30; }

    /* Tabella bonus malus minimalista (stile scontrino Apple Pay) */
    .r-table { width: 100%; border-collapse: collapse; margin-top: 4px; }
    .r-table td { padding: 12px 0; border-bottom: 1px solid var(--bg-2, #f2f2f7); font-size: 15px; color: var(--text-1); }
    .r-table td:last-child { text-align: right; font-weight: 800; font-family: var(--mono, monospace); font-size: 16px; }
    .r-table tr:last-child td { border-bottom: none; }
    .r-val-plus { color: var(--green, #34c759); }
    .r-val-minus { color: var(--red, #ff3b30); }
    .r-sub { font-size: 12px; color: var(--text-3); font-weight: 400; display: block; margin-top: 2px;}
</style>

<div id="rules-overlay" class="hidden">
    
    <div class="r-header">
        <div class="r-title">Regolamento</div>
        <button id="btn-close-rules" class="r-close">
            <span class="material-symbols-outlined" style="font-size:20px;">close</span>
        </button>
    </div>
    
    <div class="r-content">
        
        <div class="r-section" style="margin-top: 16px;">
            <h3 class="r-h3"><span class="material-symbols-outlined">hub</span> Il Torneo</h3>
            <p class="r-p">Sviluppo su <strong>8 Giornate</strong> parallele al Mondiale reale.</p>
            <ul class="r-list">
                <li class="r-li"><span class="r-strong">G1 - G7:</span> Campionato (Tutti contro Tutti).</li>
                <li class="r-li"><span class="r-strong">G8:</span> Finali dirette in base alla classifica.</li>
            </ul>
        </div>

        <div class="r-section">
            <h3 class="r-h3"><span class="material-symbols-outlined">groups</span> La Rosa (29)</h3>
            <ul class="r-list">
                <li class="r-li"><span class="r-pill por">POR</span> 4 Portieri</li>
                <li class="r-li"><span class="r-pill dif">DIF</span> 9 Difensori</li>
                <li class="r-li"><span class="r-pill cen">CEN</span> 9 Centrocampisti</li>
                <li class="r-li"><span class="r-pill att">ATT</span> 7 Attaccanti</li>
            </ul>
        </div>

        <div class="r-section">
            <h3 class="r-h3"><span class="material-symbols-outlined">strategy</span> Formazione e Cambi</h3>
            <p class="r-p">Schiera 11 titolari (Minimo 1 POR, 3 DIF, 3 CEN, 1 ATT).</p>
            <ul class="r-list">
                <li class="r-li"><span class="r-strong">Cambi:</span> Max 5 sostituzioni. Entra il primo con ruolo compatibile al modulo.</li>
                <li class="r-li"><span class="r-strong">Voto base:</span> Chi scende in campo parte sempre da 6.</li>
            </ul>
        </div>

        <div class="r-section">
            <h3 class="r-h3"><span class="material-symbols-outlined">exposure</span> Bonus e Malus</h3>
            <table class="r-table">
                <tr><td>Gol segnato <span class="r-sub">Portiere: +5</span></td><td class="r-val-plus">+3</td></tr>
                <tr><td>Assist</td><td class="r-val-plus">+1</td></tr>
                <tr><td>Rigore parato</td><td class="r-val-plus">+3</td></tr>
                <tr><td>Clean Sheet <span class="r-sub">Solo Portiere</span></td><td class="r-val-plus">+1</td></tr>
                <tr><td>Ammonizione</td><td class="r-val-minus">-0.5</td></tr>
                <tr><td>Espulsione / Autogol</td><td class="r-val-minus">-2</td></tr>
                <tr><td>Rigore sbagliato</td><td class="r-val-minus">-3</td></tr>
                <tr><td>Gol subito <span class="r-sub">Solo Portiere</span></td><td class="r-val-minus">-1</td></tr>
            </table>
        </div>

        <div class="r-section">
            <h3 class="r-h3"><span class="material-symbols-outlined">lock_clock</span> Classifica e Mercato</h3>
            <ul class="r-list">
                <li class="r-li"><span class="r-strong">Esito:</span> Vittoria 3pt &nbsp;•&nbsp; Pari 1pt &nbsp;•&nbsp; Persa 0pt</li>
                <li class="r-li"><span class="r-strong">Parità:</span> Ha la meglio chi ha più FantaPunti totali.</li>
                <li class="r-li"><span class="r-strong">Consegna:</span> Entro l'inizio della prima partita utile.</li>
                <li class="r-li"><span class="r-strong">Mercato:</span> Chiuso a torneo in corso.</li>
            </ul>
        </div>
        
    </div>
</div>