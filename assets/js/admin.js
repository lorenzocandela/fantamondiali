import { db } from './firebase-init.js';
import { doc, getDoc, setDoc, getDocs, deleteDoc, collection } from 'https://www.gstatic.com/firebasejs/10.8.1/firebase-firestore.js';
import { toast } from './utils.js';
import { buildSubtitle, renderPlayers, getFiltered, displayCount } from './players.js';
import { renderMatchdayAdmin, generateRoundRobin } from './calendar.js';


export async function loadSystemSettings() {
    try {
        const snap = await getDoc(doc(db, 'settings', 'system'));
        if (snap.exists()) {
            const d = snap.data();
            window.__competitionActive = d.market_open === true;
            window.__settings = d;
        }
    } catch (err) { console.error('errore impostazioni:', err); }
}

function setToggleState(id, on) {
    const btn = document.getElementById(id);
    if (!btn) return;
    btn.dataset.state = on ? 'on' : 'off';
    btn.querySelector('.admin-toggle-label').textContent = on ? 'On' : 'Off';
}

export function syncAdminUI() {
    const s = window.__settings ?? {};
    setToggleState('toggle-market',        s.market_open === true);
    setToggleState('toggle-competition',   s.competition_active === true);
    setToggleState('toggle-registrations', s.registrations_open !== false);

    const mktLabel  = document.getElementById('admin-market-status-text');
    const compLabel = document.getElementById('admin-comp-status-text');
    if (mktLabel)  { mktLabel.textContent  = s.market_open        ? 'Mercato aperto' : 'Mercato chiuso'; mktLabel.style.color  = s.market_open        ? 'var(--green)' : 'var(--red)'; }
    if (compLabel) { compLabel.textContent = s.competition_active ? 'Attiva'         : 'Non attiva';     compLabel.style.color = s.competition_active ? 'var(--green)' : 'var(--text-2)'; }
}

async function saveSetting(key, value) {
    try {
        await setDoc(doc(db, 'settings', 'system'), { [key]: value }, { merge: true });
        if (!window.__settings) window.__settings = {};
        window.__settings[key] = value;
        syncAdminUI();
        buildSubtitle();
        renderPlayers(getFiltered(), displayCount);
    } catch (err) {
        console.error('errore setting:', err);
        toast('Errore di permessi', 'error');
        syncAdminUI();
    }
}

function wireToggle(id, settingKey, onMsg, offMsg) {
    const btn = document.getElementById(id);
    if (!btn) return;
    btn.addEventListener('click', async () => {
        const newState = btn.dataset.state !== 'on';
        if (settingKey === 'market_open') window.__competitionActive = newState;
        await saveSetting(settingKey, newState);
        toast(newState ? onMsg : offMsg);
        
        if (newState) {
            const messages = {
                'market_open': { t: 'Mercato aperto!', m: 'Corri ad acquistare i tuoi giocatori' },
                'competition_active': { t: 'Competizione attivata!', m: 'La sfida ufficiale è iniziata' },
                'registrations_open': { t: 'Registrazioni aperte!', m: 'Invita i tuoi amici a iscriversi' }
            };
            if (messages[settingKey]) {
                sendPushNotification(messages[settingKey].t, messages[settingKey].m);
            }
        }
    });
}

async function sendPushNotification(title, message) {
    try {
        await fetch('/send_onesignal.php', { 
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ title, message }),
        });
        console.log("Richiesta inviata alla root");
    } catch (err) { console.error('Errore fetch:', err); }
}
wireToggle('toggle-market',        'market_open',        'Mercato aperto',        'Mercato chiuso');
wireToggle('toggle-competition',   'competition_active', 'Competizione attivata', 'Competizione disattivata');
wireToggle('toggle-registrations', 'registrations_open', 'Registrazioni aperte',  'Registrazioni chiuse');

export async function loadAdminStats() {
    try {
        const snap   = await getDocs(collection(db, 'users'));
        let joined   = 0;
        let players  = 0;
        snap.forEach(d => {
            const data = d.data();
            if (data.competition_joined) joined++;
            players += (data.players ?? []).length;
        });
        document.getElementById('admin-stat-users').textContent   = snap.size;
        document.getElementById('admin-stat-joined').textContent  = joined;
        document.getElementById('admin-stat-players').textContent = players;
        renderAdminUsers(snap);
    } catch (err) { console.error('errore stats admin:', err); }
}

let _usersCache = [];

function renderAdminUsers(snap) {
    const list = document.getElementById('admin-users-list');
    if (!list) return;
    _usersCache = [];
    snap.forEach(d => _usersCache.push({ uid: d.id, ...d.data() }));
    if (!_usersCache.length) { list.innerHTML = `<div class="empty-state"><p>Nessun utente</p></div>`; return; }
    list.innerHTML = _usersCache.map(u => buildUserRow(u)).join('');
    attachUserRowListeners(list);
}

function buildUserRow(u) {
    return `
    <div class="admin-user-row" data-uid="${u.uid}">
        <div class="comp-team-logo-placeholder" style="width:34px;height:34px;font-size:13px;flex-shrink:0">
            ${(u.team_name ?? u.email ?? '?')[0].toUpperCase()}
        </div>
        <div class="admin-user-info">
            <div class="admin-user-name">${u.team_name ?? 'Senza nome'}</div>
            <div class="admin-user-meta">${u.email ?? ''} · ${(u.players ?? []).length} gioc. · ${u.credits ?? 500} cr.</div>
        </div>
        <div class="admin-user-badges">
            ${u.role === 'admin'   ? '<span class="admin-badge">admin</span>'    : ''}
            ${u.competition_joined ? '<span class="joined-mini">iscritto</span>' : ''}
        </div>
        <button class="admin-user-menu-btn" data-uid="${u.uid}" aria-label="Azioni">
            <span class="material-icons-round">more_vert</span>
        </button>
        <div class="admin-user-actions hidden" data-uid="${u.uid}">
            <button class="admin-user-action-btn reset" data-uid="${u.uid}">
                <span class="material-icons-round">restart_alt</span>
                Reset rosa
            </button>
            <button class="admin-user-action-btn delete" data-uid="${u.uid}">
                <span class="material-icons-round">delete_outline</span>
                Elimina account
            </button>
        </div>
        <div class="admin-user-confirm hidden" data-uid="${u.uid}">
            <span class="admin-user-confirm-text"></span>
            <button class="admin-user-confirm-ok" data-uid="${u.uid}">Conferma</button>
            <button class="admin-user-confirm-cancel" data-uid="${u.uid}">Annulla</button>
        </div>
    </div>`;
}

function attachUserRowListeners(list) {
    list.querySelectorAll('.admin-user-menu-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const uid     = btn.dataset.uid;
            const actions = list.querySelector(`.admin-user-actions[data-uid="${uid}"]`);
            const confirm = list.querySelector(`.admin-user-confirm[data-uid="${uid}"]`);
            // chiudi tutti gli altri
            list.querySelectorAll('.admin-user-actions').forEach(el => { if (el.dataset.uid !== uid) el.classList.add('hidden'); });
            list.querySelectorAll('.admin-user-confirm').forEach(el => { if (el.dataset.uid !== uid) el.classList.add('hidden'); });
            confirm.classList.add('hidden');
            actions.classList.toggle('hidden');
        });
    });

    list.querySelectorAll('.admin-user-action-btn.reset').forEach(btn => {
        btn.addEventListener('click', () => showConfirm(list, btn.dataset.uid, 'reset'));
    });

    list.querySelectorAll('.admin-user-action-btn.delete').forEach(btn => {
        btn.addEventListener('click', () => showConfirm(list, btn.dataset.uid, 'delete'));
    });

    list.querySelectorAll('.admin-user-confirm-cancel').forEach(btn => {
        btn.addEventListener('click', () => {
            const uid = btn.dataset.uid;
            list.querySelector(`.admin-user-actions[data-uid="${uid}"]`).classList.add('hidden');
            list.querySelector(`.admin-user-confirm[data-uid="${uid}"]`).classList.add('hidden');
        });
    });

    list.querySelectorAll('.admin-user-confirm-ok').forEach(btn => {
        btn.addEventListener('click', async () => {
            const uid    = btn.dataset.uid;
            const action = btn.closest('.admin-user-confirm').dataset.action;
            btn.disabled = true;
            if (action === 'reset')  await resetUser(uid);
            if (action === 'delete') await deleteUser(uid);
        });
    });
}

function showConfirm(list, uid, action) {
    const actions = list.querySelector(`.admin-user-actions[data-uid="${uid}"]`);
    const confirm = list.querySelector(`.admin-user-confirm[data-uid="${uid}"]`);
    const text    = confirm.querySelector('.admin-user-confirm-text');
    actions.classList.add('hidden');
    confirm.classList.remove('hidden');
    confirm.dataset.action = action;
    text.textContent = action === 'reset'
        ? 'Azzera rosa e ripristina 500 crediti?'
        : 'Eliminare il documento utente? (auth record rimane)';
}

async function resetUser(uid) {
    try {
        await setDoc(doc(db, 'users', uid), { players: [], credits: 500 }, { merge: true });
        toast('Rosa e crediti azzerati');
        loadAdminStats();
    } catch { toast('Errore reset', 'error'); }
}

async function deleteUser(uid) {
    try {
        await deleteDoc(doc(db, 'users', uid));
        toast('Account eliminato da Firestore');
        loadAdminStats();
    } catch { toast('Errore eliminazione', 'error'); }
}

document.getElementById('btn-clear-cache')?.addEventListener('click', async () => {
    try {
        const res  = await fetch('clear_cache.php');
        const data = await res.json();
        toast(data.status === 'ok' ? 'Cache resettata' : 'Errore cache', data.status === 'ok' ? 'success' : 'error');
    } catch { toast('clear_cache.php non trovato', 'error'); }
});

// ─── calcolo punteggi reale ───────────────────────────────────────────────────

const SCORE_TABLE_REAL = {
    goal:        { POR: 10, DIF: 6, CEN: 6, ATT: 8 },
    assist:      3,
    yellow:     -0.5,
    red:        -2,
    clean_sheet: { POR: 2, DIF: 1 },
};

function calcPlayerScoreReal(player, stats) {
    if (!stats) return 0;
    if (!stats.played) return 0;

    let score = stats.rating ?? 6;
    score += (stats.goals   ?? 0) * (SCORE_TABLE_REAL.goal[player.role] ?? 6);
    score += (stats.assists ?? 0) * SCORE_TABLE_REAL.assist;
    score += (stats.yellow  ?? 0) * SCORE_TABLE_REAL.yellow;
    score += (stats.red     ?? 0) * SCORE_TABLE_REAL.red;
    if (stats.cs && SCORE_TABLE_REAL.clean_sheet[player.role]) {
        score += SCORE_TABLE_REAL.clean_sheet[player.role];
    }
    return Math.round(score * 100) / 100;
}

function calcTeamScoreFromLineup(lineup, playerStats) {
    // lineup = array di player objects (solo i titolari, max 11)
    // playerStats = { player_id: { rating, goals, ... } }
    const starters = lineup.slice(0, 11).filter(Boolean);
    if (!starters.length) return 0;

    let total = 0;
    let count = 0;

    starters.forEach(p => {
        const stats = playerStats[String(p.id)];
        if (!stats) return;
        total += calcPlayerScoreReal(p, stats);
        count++;
    });

    // se meno di 11 giocatori con stats, penalità per slot vuoti
    const missing = 11 - count;
    total -= missing * 2;

    return Math.max(0, Math.round(total * 10) / 10);
}

// sovrascrive il vecchio listener aggiungendo il nuovo flusso
document.getElementById('btn-calc-scores')?.removeEventListener('click', () => {});

document.getElementById('btn-calc-scores')?.addEventListener('click', async () => {
    const roundNum = parseInt(document.getElementById('admin-score-round').value);
    if (!roundNum) { toast('Seleziona una giornata', 'error'); return; }

    const btn = document.getElementById('btn-calc-scores');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-icons-round">hourglass_empty</span> Chiamata API...';

    try {
        // 1. recupera rating giocatori dalla API (o fallback simulato)
        const scoresRes = await fetch(`get_scores.php?round=${roundNum}`);
        const scoresData = await scoresRes.json();
        if (scoresData.status !== 'success') throw new Error(scoresData.message ?? 'Errore API scores');

        const playerStats = scoresData.players ?? {};
        const source      = scoresData.source;

        btn.innerHTML = '<span class="material-icons-round">hourglass_empty</span> Calcolo formazioni...';

        // 2. carica calendario e utenti
        const [calSnap, usersSnap] = await Promise.all([
            getDoc(doc(db, 'settings', 'calendar')),
            getDocs(collection(db, 'users')),
        ]);

        if (!calSnap.exists()) { toast('Nessun calendario trovato', 'error'); return; }

        const schedule = calSnap.data().schedule ?? [];
        const results  = calSnap.data().results  ?? {};
        const rd       = schedule.find(r => r.round === roundNum);
        if (!rd) { toast('Giornata non trovata nel calendario', 'error'); return; }

        const usersMap = {};
        usersSnap.forEach(d => { usersMap[d.id] = d.data(); });

        // 3. calcola punteggio per ogni match dalla formazione schierata
        const roundResults = {};

        rd.matches.forEach(m => {
            const homeUser = usersMap[m.home];
            const awayUser = usersMap[m.away];
            if (!homeUser || !awayUser) return;

            // recupera lineup salvata → array di player objects
            const homeLineupIds = homeUser.lineup ?? [];
            const awayLineupIds = awayUser.lineup ?? [];

            const homeRoster = homeUser.players ?? [];
            const awayRoster = awayUser.players ?? [];

            // ricostruisci oggetti player dai loro id
            const homeLineup = homeLineupIds
                .map(id => homeRoster.find(p => String(p.id) === String(id)))
                .filter(Boolean)
                .slice(0, 11);

            const awayLineup = awayLineupIds
                .map(id => awayRoster.find(p => String(p.id) === String(id)))
                .filter(Boolean)
                .slice(0, 11);

            // se nessuna formazione → usa i migliori 11 per prezzo
            const homeFinal = homeLineup.length >= 1 ? homeLineup
                : [...homeRoster].sort((a, b) => b.price - a.price).slice(0, 11);
            const awayFinal = awayLineup.length >= 1 ? awayLineup
                : [...awayRoster].sort((a, b) => b.price - a.price).slice(0, 11);

            const homeScore = calcTeamScoreFromLineup(homeFinal, playerStats);
            const awayScore = calcTeamScoreFromLineup(awayFinal, playerStats);

            // salva anche il dettaglio per giocatore (utile per visualizzazioni future)
            const homeDetail = homeFinal.map(p => ({
                id:    p.id,
                name:  p.name,
                role:  p.role,
                score: calcPlayerScoreReal(p, playerStats[String(p.id)] ?? null),
                stats: playerStats[String(p.id)] ?? null,
            }));

            const awayDetail = awayFinal.map(p => ({
                id:    p.id,
                name:  p.name,
                role:  p.role,
                score: calcPlayerScoreReal(p, playerStats[String(p.id)] ?? null),
                stats: playerStats[String(p.id)] ?? null,
            }));

            roundResults[`${m.home}_${m.away}`] = {
                home_score:  homeScore,
                away_score:  awayScore,
                home_detail: homeDetail,
                away_detail: awayDetail,
                source,
            };
        });

        results[String(roundNum)] = roundResults;
        await setDoc(doc(db, 'settings', 'calendar'), { results }, { merge: true });

        // 4. render risultati
        const resultEl = document.getElementById('admin-score-result');
        resultEl.classList.remove('hidden');
        resultEl.innerHTML = `
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px;font-size:11px;color:var(--text-2);font-family:var(--mono)">
                <span class="material-icons-round" style="font-size:13px">${source === 'real' ? 'sports_soccer' : 'casino'}</span>
                dati ${source === 'real' ? 'reali API' : 'simulati (torneo non iniziato)'}
            </div>
            ${rd.matches.map(m => {
                const r = roundResults[`${m.home}_${m.away}`];
                if (!r) return '';
                return `<div class="admin-score-row-item">
                    <span>${m.home_name} <strong>${r.home_score}</strong></span>
                    <span>–</span>
                    <span><strong>${r.away_score}</strong> ${m.away_name}</span>
                </div>`;
            }).join('')}`;

        toast(`Giornata ${roundNum} calcolata · ${source === 'real' ? 'dati reali' : 'simulazione'}`);

    } catch (err) {
        toast('Errore: ' + err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<span class="material-icons-round">calculate</span> Calcola punteggi giornata';
    }
});