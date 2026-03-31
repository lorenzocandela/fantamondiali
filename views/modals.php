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
<div id="rules-overlay" class="hidden" style="position:fixed; top:0; left:0; width:100%; height:100%; background:var(--bg); z-index:999999; overflow-y:auto; transform:translateY(100%); transition:transform 0.3s var(--ease);">
    
    <div style="display: flex; align-items: center; justify-content: space-between; padding: 26px 20px 14px; position: sticky; top: 0; background: rgba(247, 247, 248, 0.88); backdrop-filter: blur(24px) saturate(1.6); -webkit-backdrop-filter: blur(24px) saturate(1.6); border-bottom: 1px solid var(--border); z-index: 100;">
        <div style="display:flex; align-items:center; gap:8px;">
            <div class="page-title" style="font-size: 22px; margin:0;">Regolamento</div>
        </div>
        <button id="btn-close-rules" class="icon-btn" style="padding: 6px; border-radius: 50%; border: none; box-shadow: none;">
            <span class="material-symbols-outlined" style="font-size: 22px;">close</span>
        </button>
    </div>
    
    <div style="padding: 16px 0 80px;">
        
        <div class="admin-section">
            <div class="admin-section-title" style="display: flex; align-items: center; gap: 6px;"><span class="material-symbols-outlined" style="font-size: 14px;">hub</span>STRUTTURA</div>
            <div style="font-size: 13.5px; color: var(--text-2); line-height: 1.5;">Il Fantamondiali 2026 si svolge parallelamente al Mondiale reale ed è diviso in 8 giornate.<br>I partecipanti sono 8. Il torneo segue una formula campionato tutti-contro-tutti per 7 giornate, seguite da una <strong>Giornata 8 di Finali</strong> dirette in base alla classifica.</div>
        </div>

        <div class="admin-section">
            <div class="admin-section-title" style="display: flex; align-items: center; gap: 6px;"><span class="material-symbols-outlined" style="font-size: 14px;">groups</span>ROSA</div>
            <div style="font-size: 13.5px; color: var(--text-2); line-height: 1.5;">Ogni partecipante dovrà creare una rosa composta da <strong>29 giocatori</strong>, selezionabili tra tutte le nazionali partecipanti:</div>
            <div style="display: flex; flex-direction: column; gap: 6px; margin-top: 4px;">
                <div style="display: flex; align-items: center; gap: 8px;"><span class="role-badge badge-POR" style="margin: 0;">POR</span> <span style="font-size: 13.5px; color: var(--text-2);">4 Portieri</span></div>
                <div style="display: flex; align-items: center; gap: 8px;"><span class="role-badge badge-DIF" style="margin: 0;">DIF</span> <span style="font-size: 13.5px; color: var(--text-2);">9 Difensori</span></div>
                <div style="display: flex; align-items: center; gap: 8px;"><span class="role-badge badge-CEN" style="margin: 0;">CEN</span> <span style="font-size: 13.5px; color: var(--text-2);">9 Centrocampisti</span></div>
                <div style="display: flex; align-items: center; gap: 8px;"><span class="role-badge badge-ATT" style="margin: 0;">ATT</span> <span style="font-size: 13.5px; color: var(--text-2);">7 Attaccanti</span></div>
            </div>
        </div>

        <div class="admin-section">
            <div class="admin-section-title" style="display: flex; align-items: center; gap: 6px;"><span class="material-symbols-outlined" style="font-size: 14px;">strategy</span>FORMAZIONI E MODULI</div>
            <div style="font-size: 13.5px; color: var(--text-2); line-height: 1.5;">Per ogni giornata schiererai 11 titolari con modulo libero, rispettando però questi minimi:</div>
            <div style="display: flex; flex-direction: column; gap: 4px; margin-top: 4px;">
                <div style="display: flex; align-items: center; gap: 8px; font-size: 13.5px; color: var(--text-2);"><span class="material-symbols-outlined" style="font-size: 14px; color: var(--blue);">check_circle</span> 1 Portiere</div>
                <div style="display: flex; align-items: center; gap: 8px; font-size: 13.5px; color: var(--text-2);"><span class="material-symbols-outlined" style="font-size: 14px; color: var(--blue);">check_circle</span> Almeno 3 Difensori</div>
                <div style="display: flex; align-items: center; gap: 8px; font-size: 13.5px; color: var(--text-2);"><span class="material-symbols-outlined" style="font-size: 14px; color: var(--blue);">check_circle</span> Almeno 3 Centrocampisti</div>
                <div style="display: flex; align-items: center; gap: 8px; font-size: 13.5px; color: var(--text-2);"><span class="material-symbols-outlined" style="font-size: 14px; color: var(--blue);">check_circle</span> Almeno 1 Attaccante</div>
            </div>
        </div>

        <div class="admin-section">
            <div class="admin-section-title" style="display: flex; align-items: center; gap: 6px;"><span class="material-symbols-outlined" style="font-size: 14px;">airline_seat_recline_normal</span>PANCHINA</div>
            <div style="font-size: 13.5px; color: var(--text-2); line-height: 1.5;">La panchina è libera (max 18 giocatori). Sono consentite un <strong>massimo di 5 sostituzioni</strong> per giornata.<br><br>Se un titolare non va a voto, entrerà il primo panchinaro disponibile con ruolo compatibile al modulo. Il voto base di partenza per chi scende in campo è sempre <strong>6</strong>.</div>
        </div>

        <div class="admin-section">
            <div class="admin-section-title" style="display: flex; align-items: center; gap: 6px;"><span class="material-symbols-outlined" style="font-size: 14px;">calculate</span>BONUS E MALUS</div>
            
            <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 8px;">
                <div style="background: var(--green-soft); padding: 12px; border-radius: var(--r-sm); border: 1px solid rgba(40,180,99,0.15);">
                    <div style="font-weight: 700; font-size: 12px; color: var(--green); margin-bottom: 8px; display: flex; align-items: center; gap: 4px; font-family: var(--mono); text-transform: uppercase;"><span class="material-symbols-outlined" style="font-size:14px;">add_circle</span> Bonus</div>
                    <div style="display: flex; justify-content: space-between; font-size: 13px; color: var(--text); margin-bottom: 4px;"><span>Gol segnato <span style="font-size:10px; color:var(--text-2);">(+5 per Portiere)</span></span><span style="font-family: var(--mono); font-weight: 700;">+3</span></div>
                    <div style="display: flex; justify-content: space-between; font-size: 13px; color: var(--text); margin-bottom: 4px;"><span>Assist</span><span style="font-family: var(--mono); font-weight: 700;">+1</span></div>
                    <div style="display: flex; justify-content: space-between; font-size: 13px; color: var(--text); margin-bottom: 4px;"><span>Rigore parato</span><span style="font-family: var(--mono); font-weight: 700;">+3</span></div>
                    <div style="display: flex; justify-content: space-between; font-size: 13px; color: var(--text);"><span>Clean Sheet <span style="font-size:10px; color:var(--text-2);">(Portiere)</span></span><span style="font-family: var(--mono); font-weight: 700;">+1</span></div>
                </div>
                
                <div style="background: var(--red-soft); padding: 12px; border-radius: var(--r-sm); border: 1px solid rgba(232,57,42,0.15);">
                    <div style="font-weight: 700; font-size: 12px; color: var(--red); margin-bottom: 8px; display: flex; align-items: center; gap: 4px; font-family: var(--mono); text-transform: uppercase;"><span class="material-symbols-outlined" style="font-size:14px;">do_not_disturb_on</span> Malus</div>
                    <div style="display: flex; justify-content: space-between; font-size: 13px; color: var(--text); margin-bottom: 4px;"><span>Ammonizione</span><span style="font-family: var(--mono); font-weight: 700;">-0.5</span></div>
                    <div style="display: flex; justify-content: space-between; font-size: 13px; color: var(--text); margin-bottom: 4px;"><span>Espulsione / Autogol</span><span style="font-family: var(--mono); font-weight: 700;">-2</span></div>
                    <div style="display: flex; justify-content: space-between; font-size: 13px; color: var(--text); margin-bottom: 4px;"><span>Rigore sbagliato</span><span style="font-family: var(--mono); font-weight: 700;">-3</span></div>
                    <div style="display: flex; justify-content: space-between; font-size: 13px; color: var(--text);"><span>Gol subito <span style="font-size:10px; color:var(--text-2);">(Portiere)</span></span><span style="font-family: var(--mono); font-weight: 700;">-1</span></div>
                </div>
            </div>
        </div>

        <div class="admin-section">
            <div class="admin-section-title" style="display: flex; align-items: center; gap: 6px;"><span class="material-symbols-outlined" style="font-size: 14px;">sports_score</span>CLASSIFICA E ESITI</div>
            <div style="font-size: 13.5px; color: var(--text-2); line-height: 1.5;">Vince la sfida chi ottiene il punteggio totale più alto (senza soglie gol).<br><br><strong style="color: var(--text);">Punti:</strong> Vittoria 3pt, Pareggio 1pt, Sconfitta 0pt.<br><br><strong style="color: var(--text);">Criteri Parità:</strong><br>1. Punti<br>2. FantaPunti totali<br>3. Numero Vittorie<br>4. Sorteggio</div>
        </div>

        <div class="admin-section">
            <div class="admin-section-title" style="display: flex; align-items: center; gap: 6px;"><span class="material-symbols-outlined" style="font-size: 14px;">event</span>CALENDARIO</div>
            <div style="font-size: 13.5px; color: var(--text-2); line-height: 1.5;">
                <strong style="color: var(--text);">Formazione:</strong> Inserita prima dell'inizio della prima partita utile. In caso di dimenticanza, vale l'ultima schierata.<br><br>
                <strong style="color: var(--text);">Mercato:</strong> Chiuso a torneo in corso.
            </div>
            
            <div style="background: var(--surface-2); border: 1px solid var(--border); border-radius: var(--r-sm); padding: 12px; margin-top: 12px;">
                <div style="font-size: 10px; font-weight: 700; color: var(--text-3); margin-bottom: 6px; text-transform: uppercase; font-family: var(--mono);">Campionato</div>
                <div style="display: flex; justify-content: space-between; font-size: 12.5px; color: var(--text); margin-bottom: 4px;"><span>G1 - G3</span><span style="font-family: var(--mono);">Fase a Gironi</span></div>
                <div style="display: flex; justify-content: space-between; font-size: 12.5px; color: var(--text); margin-bottom: 4px;"><span>G4</span><span style="font-family: var(--mono);">Sedicesimi</span></div>
                <div style="display: flex; justify-content: space-between; font-size: 12.5px; color: var(--text); margin-bottom: 4px;"><span>G5</span><span style="font-family: var(--mono);">Ottavi</span></div>
                <div style="display: flex; justify-content: space-between; font-size: 12.5px; color: var(--text); margin-bottom: 4px;"><span>G6</span><span style="font-family: var(--mono);">Quarti</span></div>
                <div style="display: flex; justify-content: space-between; font-size: 12.5px; color: var(--text); margin-bottom: 8px;"><span>G7</span><span style="font-family: var(--mono);">Semifinali</span></div>
                
                <div style="font-size: 10px; font-weight: 700; color: var(--text-3); margin-bottom: 6px; padding-top: 8px; border-top: 1px solid var(--border); text-transform: uppercase; font-family: var(--mono);">Fase Finale</div>
                <div style="display: flex; justify-content: space-between; font-size: 12.5px; color: var(--blue); font-weight: 700;"><span>G8</span><span style="font-family: var(--mono);">Finali Dirette</span></div>
            </div>
        </div>

    </div>
</div>