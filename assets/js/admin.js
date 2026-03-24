import { db } from './firebase-init.js';
import { doc, getDoc, setDoc, getDocs, collection } from 'https://www.gstatic.com/firebasejs/10.8.1/firebase-firestore.js';
import { toast } from './utils.js';
import { buildSubtitle, renderPlayers, getFiltered, displayCount } from './players.js';
import { renderMatchdayAdmin, generateRoundRobin } from './calendar.js';

const SCORE_TABLE = {
    goal:        { POR: 10, DIF: 6, CEN: 6, ATT: 8 },
    assist:      3,
    yellow_card: -0.5,
    red_card:    -1,
    clean_sheet: { POR: 1, DIF: 1 },
};

function calcPlayerScore(player, stats) {
    if (!stats || !stats.played) return 0;
    let score = stats.rating ?? 6;
    score += (stats.goals        ?? 0) * (SCORE_TABLE.goal[player.role] ?? 6);
    score += (stats.assists      ?? 0) * SCORE_TABLE.assist;
    score += (stats.yellow_cards ?? 0) * SCORE_TABLE.yellow_card;
    score += (stats.red_cards    ?? 0) * SCORE_TABLE.red_card;
    const cs = SCORE_TABLE.clean_sheet[player.role];
    if (cs && stats.clean_sheet) score += cs;
    return Math.round(score * 100) / 100;
}

function simulateTeamScore(players) {
    if (!players.length) return 0;
    const base = players.reduce((s, p) => s + (p.price ?? 10), 0) / players.length;
    return base + (Math.random() * 6 - 3);
}

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
    });
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

function renderAdminUsers(snap) {
    const list = document.getElementById('admin-users-list');
    if (!list) return;
    const rows = [];
    snap.forEach(d => rows.push({ uid: d.id, ...d.data() }));
    if (!rows.length) { list.innerHTML = `<div class="empty-state"><p>Nessun utente</p></div>`; return; }
    list.innerHTML = rows.map(u => `
        <div class="admin-user-row">
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
        </div>`).join('');
}

document.getElementById('btn-clear-cache')?.addEventListener('click', async () => {
    try {
        const res  = await fetch('clear_cache.php');
        const data = await res.json();
        toast(data.status === 'ok' ? 'Cache resettata' : 'Errore cache', data.status === 'ok' ? 'success' : 'error');
    } catch { toast('clear_cache.php non trovato', 'error'); }
});

document.getElementById('btn-calc-scores')?.addEventListener('click', async () => {
    const roundNum = parseInt(document.getElementById('admin-score-round').value);
    if (!roundNum) { toast('Seleziona una giornata', 'error'); return; }
    const btn = document.getElementById('btn-calc-scores');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-icons-round">hourglass_empty</span> Calcolo...';
    try {
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
        const roundResults = {};
        rd.matches.forEach(m => {
            const homeUser = usersMap[m.home];
            const awayUser = usersMap[m.away];
            if (!homeUser || !awayUser) return;
            roundResults[`${m.home}_${m.away}`] = {
                home_score: Math.round(simulateTeamScore(homeUser.players ?? []) * 10) / 10,
                away_score: Math.round(simulateTeamScore(awayUser.players ?? []) * 10) / 10,
            };
        });
        results[String(roundNum)] = roundResults;
        await setDoc(doc(db, 'settings', 'calendar'), { results }, { merge: true });
        const resultEl = document.getElementById('admin-score-result');
        resultEl.classList.remove('hidden');
        resultEl.innerHTML = rd.matches.map(m => {
            const r = roundResults[`${m.home}_${m.away}`];
            if (!r) return '';
            const winner = r.home_score > r.away_score ? m.home_name : r.away_score > r.home_score ? m.away_name : 'Pareggio';
            return `<div class="admin-score-row-item">
                <span>${m.home_name} <strong>${r.home_score}</strong></span>
                <span>–</span>
                <span><strong>${r.away_score}</strong> ${m.away_name}</span>
            </div>`;
        }).join('');
        toast(`Giornata ${roundNum} calcolata`);
    } catch (err) { toast('Errore: ' + err.message, 'error'); }
    finally {
        btn.disabled = false;
        btn.innerHTML = '<span class="material-icons-round">calculate</span> Calcola punteggi giornata';
    }
});