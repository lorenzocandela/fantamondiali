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

<style id="fm-rules-styles">
    #rules-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
        background: var(--bg-2, #f2f2f7);
        z-index: 999999; overflow-y: auto; transform: translateY(100%); 
        transition: transform 0.3s cubic-bezier(0.1, 0, 0.1, 1);
    }
    .rules-header-sticky {
        position: sticky; top: 0; background: var(--bg-1, #ffffff); padding: 16px 20px; 
        display: flex; align-items: center; justify-content: space-between; 
        border-bottom: 1px solid var(--border-color, #e5e5ea); z-index: 100;
        box-shadow: 0 2px 10px rgba(0,0,0,0.03);
    }
    .rules-content { padding: 20px 16px 80px; }
    
    .rule-card {
        background: var(--bg-1, #ffffff); border: 1px solid var(--border-color, #e5e5ea); 
        border-radius: 16px; padding: 16px; margin-bottom: 16px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.02);
    }
    .rule-header {
        display: flex; align-items: center; gap: 10px; margin-bottom: 12px;
        padding-bottom: 10px; border-bottom: 1px solid var(--bg-2, #f2f2f7);
    }
    .rule-icon { color: var(--blue, #007aff); font-size: 22px !important; }
    .rule-title { font-weight: 800; font-size: 16px; color: var(--text-1, #000); margin: 0; }
    .rule-text { font-size: 14px; color: var(--text-2, #3a3a3c); line-height: 1.5; margin: 0; }
    
    .rule-list { margin: 10px 0 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 8px; }
    .rule-list li { display: flex; align-items: flex-start; gap: 8px; font-size: 14px; color: var(--text-2); line-height: 1.4; }
    .rule-bullet { color: var(--blue); font-size: 16px !important; margin-top: 2px; }
    
    .bm-container { display: flex; flex-direction: column; gap: 10px; margin-top: 12px; }
    .bm-box { padding: 12px; border-radius: 12px; }
    .bm-bonus { background: var(--green-soft, #e8f5e9); border: 1px solid rgba(52, 199, 89, 0.2); }
    .bm-malus { background: var(--red-soft, #ffebee); border: 1px solid rgba(255, 59, 48, 0.2); }
    .bm-title { font-weight: 800; font-size: 14px; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
    .bm-bonus .bm-title { color: var(--green, #34c759); }
    .bm-malus .bm-title { color: var(--red, #ff3b30); }
    .bm-row { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 4px; color: var(--text-1); }
    .bm-val { font-family: var(--mono); font-weight: 700; }
    
    /* Piccoli badge di ruolo inline */
    .rp-inline { font-size: 10px; font-weight: 800; padding: 2px 6px; border-radius: 4px; color: white; margin-right: 4px; font-family: var(--mono); }
    .rp-por { background: #ff9500; }
    .rp-dif { background: #34c759; }
    .rp-cen { background: #007aff; }
    .rp-att { background: #ff3b30; }
</style>

<div id="rules-overlay" class="hidden">
    
    <div class="rules-header-sticky">
        <div style="display:flex; align-items:center; gap:8px;">
            <span class="material-symbols-outlined" style="color:var(--text-1);">menu_book</span>
            <div style="font-weight:800; font-size:18px; color:var(--text-1);">Regolamento</div>
        </div>
        <button id="btn-close-rules" style="background:var(--bg-2); color:var(--text-1); border:none; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer;">
            <span class="material-symbols-outlined" style="font-size:20px;">close</span>
        </button>
    </div>
    
    <div class="rules-content">
        
        <div class="rule-card">
            <div class="rule-header">
                <span class="material-symbols-outlined rule-icon">hub</span>
                <h3 class="rule-title">1. Struttura del Torneo</h3>
            </div>
            <p class="rule-text">Il Fantamondiali 2026 si svolge parallelamente al Mondiale reale ed è diviso in 8 giornate.<br>I partecipanti sono 8. Il torneo segue una formula campionato tutti-contro-tutti per 7 giornate, seguite da una <strong>Giornata 8 di Finali</strong> dirette in base alla classifica.</p>
        </div>

        <div class="rule-card">
            <div class="rule-header">
                <span class="material-symbols-outlined rule-icon">groups</span>
                <h3 class="rule-title">2. La Rosa</h3>
            </div>
            <p class="rule-text">Ogni partecipante dovrà creare una rosa composta da <strong>29 giocatori</strong>, selezionabili tra tutte le nazionali partecipanti:</p>
            <ul class="rule-list" style="margin-top: 12px;">
                <li><span class="rp-inline rp-por">POR</span> 4 Portieri</li>
                <li><span class="rp-inline rp-dif">DIF</span> 9 Difensori</li>
                <li><span class="rp-inline rp-cen">CEN</span> 9 Centrocampisti</li>
                <li><span class="rp-inline rp-att">ATT</span> 7 Attaccanti</li>
            </ul>
        </div>

        <div class="rule-card">
            <div class="rule-header">
                <span class="material-symbols-outlined rule-icon">strategy</span>
                <h3 class="rule-title">3. Formazione e Moduli</h3>
            </div>
            <p class="rule-text">Per ogni giornata schiererai 11 titolari con modulo libero, rispettando però questi minimi:</p>
            <ul class="rule-list">
                <li><span class="material-symbols-outlined rule-bullet">check_circle</span> 1 Portiere</li>
                <li><span class="material-symbols-outlined rule-bullet">check_circle</span> Almeno 3 Difensori</li>
                <li><span class="material-symbols-outlined rule-bullet">check_circle</span> Almeno 3 Centrocampisti</li>
                <li><span class="material-symbols-outlined rule-bullet">check_circle</span> Almeno 1 Attaccante</li>
            </ul>
        </div>

        <div class="rule-card">
            <div class="rule-header">
                <span class="material-symbols-outlined rule-icon">airline_seat_recline_normal</span>
                <h3 class="rule-title">4. Panchina e Sostituzioni</h3>
            </div>
            <p class="rule-text">La panchina è libera (max 18 giocatori). Sono consentite un <strong>massimo di 5 sostituzioni</strong> per giornata.<br><br>Se un titolare non va a voto, entrerà il primo panchinaro disponibile con ruolo compatibile al modulo. Il voto base di partenza per chi scende in campo è sempre <strong>6</strong>.</p>
        </div>

        <div class="rule-card">
            <div class="rule-header">
                <span class="material-symbols-outlined rule-icon">calculate</span>
                <h3 class="rule-title">5. Bonus e Malus</h3>
            </div>
            <p class="rule-text">I punteggi si basano sui dati ufficiali di API-Football e vengono sommati al voto base (6):</p>
            
            <div class="bm-container">
                <div class="bm-box bm-bonus">
                    <div class="bm-title"><span class="material-symbols-outlined" style="font-size:16px;">add_circle</span> Bonus</div>
                    <div class="bm-row"><span>Gol segnato</span><span class="bm-val">+3</span></div>
                    <div class="bm-row"><span style="color:var(--text-3); font-size:11px;">(Gol del Portiere)</span><span class="bm-val" style="color:var(--text-3);">+5</span></div>
                    <div class="bm-row"><span>Assist</span><span class="bm-val">+1</span></div>
                    <div class="bm-row"><span>Rigore parato</span><span class="bm-val">+3</span></div>
                    <div class="bm-row"><span>Porta inviolata <span style="font-size:10px">(Clean Sheet)</span></span><span class="bm-val">+1</span></div>
                </div>
                
                <div class="bm-box bm-malus">
                    <div class="bm-title"><span class="material-symbols-outlined" style="font-size:16px;">do_not_disturb_on</span> Malus</div>
                    <div class="bm-row"><span>Ammonizione</span><span class="bm-val">-0.5</span></div>
                    <div class="bm-row"><span>Espulsione</span><span class="bm-val">-2</span></div>
                    <div class="bm-row"><span>Autogol</span><span class="bm-val">-2</span></div>
                    <div class="bm-row"><span>Rigore sbagliato</span><span class="bm-val">-3</span></div>
                    <div class="bm-row"><span>Gol subito <span style="font-size:10px">(Portiere)</span></span><span class="bm-val">-1</span></div>
                </div>
            </div>
        </div>

        <div class="rule-card">
            <div class="rule-header">
                <span class="material-symbols-outlined rule-icon">sports_score</span>
                <h3 class="rule-title">6. Esito e Classifica</h3>
            </div>
            <p class="rule-text">Vince la sfida chi ottiene il punteggio fanta-totale più alto (senza soglie gol).<br><br><strong>Punti Campionato:</strong> Vittoria 3pt, Pareggio 1pt, Sconfitta 0pt.<br><br><strong>Criteri Classifica:</strong><br>1. Punti<br>2. FantaPunti totali<br>3. Numero Vittorie<br>4. Sorteggio</p>
        </div>

        <div class="rule-card">
            <div class="rule-header">
                <span class="material-symbols-outlined rule-icon">lock_clock</span>
                <h3 class="rule-title">7. Consegna e Mercato</h3>
            </div>
            <ul class="rule-list">
                <li><span class="material-symbols-outlined rule-bullet">edit_calendar</span> <strong>Formazione:</strong> Inserita prima della prima partita utile. Se dimenticata, vale l'ultima salvata.</li>
                <li><span class="material-symbols-outlined rule-bullet">shopping_cart</span> <strong>Mercato:</strong> Attivo solo prima dell'inizio del torneo (salvo finestre decise dall'Admin).</li>
            </ul>
        </div>

        <div class="rule-card">
            <div class="rule-header">
                <span class="material-symbols-outlined rule-icon">event</span>
                <h3 class="rule-title">8. Il Calendario</h3>
            </div>
            <div style="background:var(--bg-2); border-radius:8px; padding:12px; margin-top:8px;">
                <div style="font-size:12px; font-weight:700; color:var(--text-3); margin-bottom:6px; text-transform:uppercase;">Campionato (Tutti vs Tutti)</div>
                <div class="bm-row"><span>G1 - G3</span><span style="font-family:var(--mono);">Fase a Gironi</span></div>
                <div class="bm-row"><span>G4</span><span style="font-family:var(--mono);">Sedicesimi</span></div>
                <div class="bm-row"><span>G5</span><span style="font-family:var(--mono);">Ottavi</span></div>
                <div class="bm-row"><span>G6</span><span style="font-family:var(--mono);">Quarti</span></div>
                <div class="bm-row"><span>G7</span><span style="font-family:var(--mono);">Semifinali</span></div>
                <hr style="border:none; border-top:1px solid var(--border-color); margin:8px 0;">
                <div style="font-size:12px; font-weight:700; color:var(--text-3); margin-bottom:6px; text-transform:uppercase;">Fase Finale</div>
                <div class="bm-row" style="color:var(--blue); font-weight:700;"><span>G8</span><span style="font-family:var(--mono);">Finali Dirette</span></div>
            </div>
        </div>

        <div style="text-align:center; padding: 20px 0; color:var(--text-3); font-size:12px;">
            In caso di casistiche non previste, decide l'Admin <strong>Samuele Formigaro</strong>.
        </div>
    </div>
</div>