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

<div id="rules-overlay" class="hidden" style="position:fixed; top:0; left:0; width:100%; height:100%; background:var(--bg-1, #ffffff); z-index:999999; overflow-y:auto; transform:translateY(100%); transition:transform 0.3s cubic-bezier(0.1, 0, 0.1, 1);">
    
    <div style="position:sticky; top:0; background:var(--bg-1, #ffffff); padding: 16px 20px; display:flex; align-items:center; justify-content:space-between; border-bottom: 1px solid var(--border-color, #e5e5ea); z-index:100;">
        <div style="font-weight:800; font-size:18px; color:var(--text-1, #000);">Regolamento</div>
        <button id="btn-close-rules" style="background:var(--bg-2, #f2f2f7); color:var(--text-1, #000); border:none; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer;">
            <span class="material-symbols-outlined" style="font-size:20px;">close</span>
        </button>
    </div>
    
    <div style="padding: 24px 20px 80px; color: var(--text-2, #3a3a3c); font-size: 14px; line-height: 1.6;">
        <h3 style="color:var(--text-1, #000); margin: 0 0 8px;">1. Struttura</h3>
        <p style="margin-bottom: 20px;">Il Fantamondiali 2026 si svolge parallelamente al Mondiale reale ed è diviso in giornate, sulla base del calendario ufficiale delle partite.<br>I partecipanti minimi sono 8 e il torneo segue una formula semplice stile campionato tutti contro tutti. Per ogni giornata, ogni fantallenatore sfida un altro fantallenatore secondo il calendario stabilito prima dell’inizio del torneo.</p>

        <h3 style="color:var(--text-1, #000); margin: 0 0 8px;">2. Rosa</h3>
        <p style="margin-bottom: 20px;">Ogni partecipante dovrà creare una rosa composta da 29 giocatori così suddivisi:<br>
        • 4 portieri<br>• 9 difensori<br>• 9 centrocampisti<br>• 7 attaccanti<br>
        I giocatori possono essere scelti tra tutte le nazionali partecipanti al Mondiale 2026.</p>

        <h3 style="color:var(--text-1, #000); margin: 0 0 8px;">3. Formazione</h3>
        <p style="margin-bottom: 20px;">Per ogni giornata ogni partecipante dovrà schierare una formazione titolare composta da 11 giocatori, con modulo libero tra quelli consentiti dal fantacalcio classico, purché venga rispettato il seguente assetto minimo:<br>
        • 1 portiere<br>• almeno 3 difensori<br>• almeno 3 centrocampisti<br>• almeno 1 attaccante<br>
        Saranno inoltre indicati i giocatori in panchina, utilizzabili per eventuali sostituzioni.</p>

        <h3 style="color:var(--text-1, #000); margin: 0 0 8px;">4. Panchina e sostituzioni</h3>
        <p style="margin-bottom: 20px;">La panchina sarà libera (max 18). In caso di calciatore titolare senza voto, entrerà il primo panchinaro disponibile dello stesso ruolo o comunque compatibile con il modulo, secondo l’ordine inserito in panchina.<br>Le sostituzioni massime consentite per giornata sono 5, in linea generale, il voto base di partenza per chi gioca è 6, mentre il calciatore senza minuti effettivi resta senza voto.</p>

        <h3 style="color:var(--text-1, #000); margin: 0 0 8px;">5. Fonte dati e voti</h3>
        <p style="margin-bottom: 20px;">Per statistiche, eventi e dati ufficiali si utilizzerà API-Football come fonte unica di riferimento.<br>Il sistema di calcolo parte da un voto base pari a 6 per ogni calciatore sceso in campo, al quale verranno sommati bonus e malus in base alle statistiche registrate.</p>

        <h3 style="color:var(--text-1, #000); margin: 0 0 8px;">6. Bonus e malus</h3>
        <p style="margin-bottom: 8px;">I punteggi sono calcolati automaticamente in base alle prestazioni reali:</p>
        <div style="background:var(--bg-2, #f2f2f7); padding:12px; border-radius:8px; margin-bottom:20px;">
            <strong>Bonus</strong><br>
            • Gol segnato: +3 <i>(+5 per i Portieri)</i><br>
            • Assist: +1<br>
            • Rigore parato: +3<br>
            • Porta inviolata (Clean Sheet): +1<br><br>
            <strong>Malus</strong><br>
            • Ammonizione: -0,5<br>
            • Espulsione: -2<br>
            • Autogol: -2<br>
            • Rigore sbagliato: -3<br>
            • Gol subito dal portiere: -1 per ogni gol
        </div>

        <h3 style="color:var(--text-1, #000); margin: 0 0 8px;">7. Esito della sfida</h3>
        <p style="margin-bottom: 20px;">Ogni giornata mette di fronte due squadre, vince chi ottiene il punteggio totale più alto (no soglie gol).<br>Assegnazione punti in classifica:<br>• Vittoria: 3 punti<br>• Pareggio: 1 punto<br>• Sconfitta: 0 punti</p>

        <h3 style="color:var(--text-1, #000); margin: 0 0 8px;">8. Classifica finale</h3>
        <p style="margin-bottom: 20px;">La classifica sarà determinata nell’ordine da:<br>1. Punti in classifica<br>2. Punteggio totale complessivo<br>3. Numero di vittorie<br>4. Sorteggio, come ultima soluzione</p>

        <h3 style="color:var(--text-1, #000); margin: 0 0 8px;">9. Consegna formazione</h3>
        <p style="margin-bottom: 20px;">La formazione deve essere inserita prima dell’inizio della prima partita utile della giornata. Una volta iniziata la giornata, la formazione non potrà più essere modificata.<br>In caso di mancata consegna, verrà considerata valida l’ultima formazione schierata disponibile.</p>

        <h3 style="color:var(--text-1, #000); margin: 0 0 8px;">10. Mercato</h3>
        <p style="margin-bottom: 20px;">Il mercato si svolge solo prima dell’inizio del torneo, salvo eventuali finestre straordinarie decise dall’organizzatore.</p>

        <h3 style="color:var(--text-1, #000); margin: 0 0 8px;">11. Calendario</h3>
        <p style="margin-bottom: 8px;">Il calendario segue le 8 fasi del Mondiale 2026, suddivise in 8 giornate fantacalcistiche:</p>
        <ul style="margin: 0 0 12px; padding-left:20px;">
            <li>G1: Fase a gironi (1° turno)</li>
            <li>G2: Fase a gironi (2° turno)</li>
            <li>G3: Fase a gironi (3° turno)</li>
            <li>G4: Sedicesimi di finale</li>
            <li>G5: Ottavi di finale</li>
            <li>G6: Quarti di finale</li>
            <li>G7: Semifinali</li>
            <li>G8: Finali (1°/2° posto e 3°/4° posto)</li>
        </ul>
        <p style="margin-bottom: 20px;"><strong>Sviluppo del Torneo (Campionato + Finali):</strong><br>Dato il numero di 8 partecipanti, il torneo si divide in due fasi:<br>• <strong>Fase a Campionato (Giornate 1-7):</strong> Girone all'italiana, ogni fantallenatore sfiderà tutti gli altri partecipanti.<br>• <strong>Fase Finale (Giornata 8):</strong> La classifica decreterà gli accoppiamenti per l'ultima giornata. Il 1° sfiderà il 2° in una finale secca, il 3° sfiderà il 4°, e così via.</p>

        <h3 style="color:var(--text-1, #000); margin: 0 0 8px;">12. Regola finale</h3>
        <p style="margin-bottom: 20px;">Per qualsiasi caso non previsto dal presente regolamento decide l’organizzatore insieme a <strong>Samuele Formigaro</strong>, cercando di mantenere il gioco semplice, equilibrato e coerente con lo spirito del fantacalcio classico.</p>
    </div>
</div>