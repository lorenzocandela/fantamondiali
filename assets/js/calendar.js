import { db } from './firebase-init.js';
import { doc, getDoc, getDocs, setDoc, collection } from 'https://www.gstatic.com/firebasejs/10.8.1/firebase-firestore.js';
import { toast, formatDate } from './utils.js';

// ─── SCHEDULE ────────────────────────────────────────────────────────────────

const MATCHDAY_SCHEDULE = [
    { round: 1, label: 'Fase a gironi – Giornata 1', short: 'GJ1', start: '2026-03-25', end: '2026-03-25' }, // TEST
//    { round: 1, label: 'Fase a gironi – Giornata 1', short: 'GJ1', start: '2026-06-11', end: '2026-06-14' },
    { round: 2, label: 'Fase a gironi – Giornata 2', short: 'GJ2', start: '2026-06-15', end: '2026-06-19' },
    { round: 3, label: 'Fase a gironi – Giornata 3', short: 'GJ3', start: '2026-06-20', end: '2026-06-25' },
    { round: 4, label: 'Ottavi di finale',            short: 'R16', start: '2026-06-27', end: '2026-07-03' },
    { round: 5, label: 'Quarti di finale',            short: 'QF',  start: '2026-07-04', end: '2026-07-05' },
    { round: 6, label: 'Semifinali',                  short: 'SF',  start: '2026-07-07', end: '2026-07-08' },
    { round: 7, label: 'Finale 3° posto + Finale',   short: 'F',   start: '2026-07-11', end: '2026-07-19' },
];

export { MATCHDAY_SCHEDULE };

export function getCurrentMatchday() {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    for (const md of MATCHDAY_SCHEDULE) {
        const start = new Date(md.start + 'T00:00:00');
        const end   = new Date(md.end   + 'T23:59:59');
        if (today >= start && today <= end) return { ...md, status: 'live' };
    }
    const first = new Date(MATCHDAY_SCHEDULE[0].start + 'T00:00:00');
    if (today < first) return { ...MATCHDAY_SCHEDULE[0], status: 'upcoming' };
    for (let i = 0; i < MATCHDAY_SCHEDULE.length - 1; i++) {
        const endCur   = new Date(MATCHDAY_SCHEDULE[i].end     + 'T23:59:59');
        const startNxt = new Date(MATCHDAY_SCHEDULE[i + 1].start + 'T00:00:00');
        if (today > endCur && today < startNxt) return { ...MATCHDAY_SCHEDULE[i + 1], status: 'next' };
    }
    return { ...MATCHDAY_SCHEDULE[MATCHDAY_SCHEDULE.length - 1], status: 'ended' };
}

// ─── ADMIN MATCHDAY ──────────────────────────────────────────────────────────

export function renderMatchdayAdmin() {
    const md    = getCurrentMatchday();
    const numEl = document.getElementById('admin-matchday-num');
    const lbl   = document.getElementById('admin-matchday-label');
    const dates = document.getElementById('admin-matchday-dates');
    const list  = document.getElementById('admin-matchday-list');
    if (!numEl) return;

    const statusMap = {
        live:     { text: 'In corso', cls: 'md-live' },
        upcoming: { text: 'Prossima', cls: 'md-upcoming' },
        next:     { text: 'Prossima', cls: 'md-upcoming' },
        ended:    { text: 'Concluso', cls: 'md-ended' },
    };

    const s = statusMap[md.status] ?? statusMap.upcoming;
    numEl.textContent = md.round;
    numEl.className   = `admin-matchday-num ${s.cls}`;
    lbl.textContent   = md.label;
    dates.innerHTML   = `
        <span class="md-date-range">${formatDate(md.start)} – ${formatDate(md.end)}</span>
        <span class="md-status-pill ${s.cls}">${s.text}</span>
    `;

    list.innerHTML = MATCHDAY_SCHEDULE.map(m => {
        const isCurrent = m.round === md.round;
        const isPast    = m.round < md.round || (md.status === 'ended' && m.round === md.round);
        return `
        <div class="md-row ${isCurrent ? 'current' : ''} ${isPast ? 'past' : ''}">
            <div class="md-row-num">${m.round}</div>
            <div class="md-row-info">
                <div class="md-row-label">${m.label}</div>
                <div class="md-row-dates">${formatDate(m.start)} – ${formatDate(m.end)}</div>
            </div>
            ${isCurrent ? '<span class="md-row-badge">corrente</span>' : ''}
        </div>`;
    }).join('');
}

// ─── HELPERS ─────────────────────────────────────────────────────────────────

function logoHtml(uid, teams) {
    const t = teams.find(t => t.uid === uid);
    if (t?.team_logo) return `<img src="${t.team_logo}" class="cal-team-logo" alt="${t.team_name}" onerror="this.style.display='none'">`;
    const initial = (t?.team_name ?? '?')[0].toUpperCase();
    return `<div class="cal-team-logo-placeholder">${initial}</div>`;
}

function buildStandings(teams, schedule, results) {
    const table = {};
    teams.forEach(t => {
        table[t.uid] = { uid: t.uid, name: t.team_name, pts: 0, w: 0, d: 0, l: 0, pf: 0, pa: 0 };
    });
    schedule.forEach(rd => {
        const res = results?.[String(rd.round)];
        if (!res) return;
        rd.matches.forEach(m => {
            const r = res[`${m.home}_${m.away}`];
            if (!r || r.home_score === undefined) return;
            const h = table[m.home];
            const a = table[m.away];
            if (!h || !a) return;
            h.pf += r.home_score; h.pa += r.away_score;
            a.pf += r.away_score; a.pa += r.home_score;
            if (r.home_score > r.away_score)      { h.pts += 3; h.w++; a.l++; }
            else if (r.home_score < r.away_score) { a.pts += 3; a.w++; h.l++; }
            else                                  { h.pts++; a.pts++; h.d++; a.d++; }
        });
    });
    return Object.values(table).sort((a, b) =>
        b.pts - a.pts || (b.pf - b.pa) - (a.pf - a.pa) || b.pf - a.pf
    );
}

export function generateRoundRobin(teams) {
    const list = [...teams];
    if (list.length % 2 !== 0) list.push({ uid: 'bye', team_name: 'Turno libero' });
    const total  = list.length;
    const rounds = total - 1;
    const fixed  = list[0];
    const rotate = list.slice(1);
    const schedule = [];
    for (let r = 0; r < rounds; r++) {
        const round   = [];
        const current = [fixed, ...rotate];
        for (let i = 0; i < total / 2; i++) {
            const home = current[i];
            const away = current[total - 1 - i];
            if (home.uid !== 'bye' && away.uid !== 'bye')
                round.push({ home: home.uid, away: away.uid, home_name: home.team_name, away_name: away.team_name });
        }
        schedule.push({ round: r + 1, matches: round });
        rotate.push(rotate.shift());
    }
    return schedule;
}

// ─── STATO LOCALE ────────────────────────────────────────────────────────────

let calSchedule = [];
let calResults  = {};
let calTeams    = [];
let calRound    = 1;
let calUsersMap = {};  // uid → user data (per formazioni)

// ─── LOAD CALENDARIO ─────────────────────────────────────────────────────────

export async function loadCalendario() {
    // torna alla vista principale se eri nel dettaglio
    document.getElementById('cal-main-view')?.classList.remove('hidden');
    document.getElementById('cal-match-detail')?.classList.add('hidden');
    document.getElementById('cal-subtitle').textContent = 'caricamento...';

    try {
        const [calSnap, usersSnap] = await Promise.all([
            getDoc(doc(db, 'settings', 'calendar')),
            getDocs(collection(db, 'users')),
        ]);
        calTeams = [];
        calUsersMap = {};
        usersSnap.forEach(d => {
            const data = d.data();
            calUsersMap[d.id] = data;
            if (data.competition_joined) calTeams.push({
                uid: d.id, team_name: data.team_name ?? 'Squadra',
                team_logo: data.team_logo ?? null, players: data.players ?? []
            });
        });
        if (!calSnap.exists() || !calSnap.data().schedule) {
            document.getElementById('cal-matches-list').innerHTML = `
                <div class="empty-state">
                    <span class="material-symbols-outlined">calendar_month</span>
                    <h3>Calendario non ancora generato</h3>
                    <p>L'admin deve generarlo dalla dashboard</p>
                </div>`;
            document.getElementById('cal-subtitle').textContent = `${calTeams.length} squadre iscritte`;
            return;
        }
        calSchedule = calSnap.data().schedule ?? [];
        calResults  = calSnap.data().results  ?? {};
        calRound    = getCurrentMatchday().round;
        if (calRound > calSchedule.length) calRound = calSchedule.length;
        document.getElementById('cal-subtitle').textContent = `${calTeams.length} squadre · ${calSchedule.length} giornate`;
        renderCalRound();
    } catch (err) {
        document.getElementById('cal-matches-list').innerHTML =
            `<div class="empty-state"><span class="material-symbols-outlined">wifi_off</span><h3>Errore</h3><p>${err.message}</p></div>`;
    }
}

// ─── RENDER GIORNATA ─────────────────────────────────────────────────────────

function renderCalRound() {
    const rd = calSchedule[calRound - 1];
    if (!rd) return;
    const roundNames = ['GJ1','GJ2','GJ3','Ottavi','Quarti','Semifinali','Finale'];
    document.getElementById('cal-round-label').textContent = `G${calRound} — ${roundNames[calRound - 1] ?? ''}`;
    const res = calResults[String(calRound)] ?? {};

    document.getElementById('cal-matches-list').innerHTML = rd.matches.map(m => {
        const key    = `${m.home}_${m.away}`;
        const r      = res[key];
        const played = r?.home_score !== undefined;
        return `
        <div class="cal-match-card clickable ${played ? 'played' : ''}" data-home="${m.home}" data-away="${m.away}" data-round="${calRound}">
            <div class="cal-team home">
                <div class="cal-team-logo-wrap">${logoHtml(m.home, calTeams)}</div>
                <div class="cal-team-name">${m.home_name}</div>
            </div>
            <div class="cal-score-box">
                ${played
                    ? `<span class="cal-score">${r.home_score}</span><span class="cal-score-sep">–</span><span class="cal-score">${r.away_score}</span>`
                    : `<span class="cal-score-tbd">vs</span>`}
            </div>
            <div class="cal-team away">
                <div class="cal-team-logo-wrap">${logoHtml(m.away, calTeams)}</div>
                <div class="cal-team-name">${m.away_name}</div>
            </div>
            <div class="cal-match-tap-hint">
                <span class="material-symbols-outlined">chevron_right</span>
            </div>
        </div>`;
    }).join('');

    // attach click
    document.querySelectorAll('.cal-match-card.clickable').forEach(card => {
        card.addEventListener('click', () => {
            openMatchDetail(
                card.dataset.home,
                card.dataset.away,
                parseInt(card.dataset.round)
            );
        });
    });

    document.getElementById('cal-round-prev').disabled = calRound <= 1;
    document.getElementById('cal-round-next').disabled = calRound >= calSchedule.length;
}

// ─── CLASSIFICA ──────────────────────────────────────────────────────────────

function renderCalStandings() {
    const standings = buildStandings(calTeams, calSchedule, calResults);
    const list      = document.getElementById('cal-standings-list');
    if (!standings.length) {
        list.innerHTML = `<div class="empty-state"><span class="material-symbols-outlined">leaderboard</span><h3>Nessuna squadra</h3></div>`;
        return;
    }
    list.innerHTML = standings.map((s, i) => `
        <div class="cal-standing-row ${i === 0 ? 'first' : ''}">
            <div class="cal-standing-pos">${i + 1}</div>
            <div class="cal-team-logo-wrap small">${logoHtml(s.uid, calTeams)}</div>
            <div class="cal-standing-info">
                <div class="cal-standing-name">${s.name}</div>
                <div class="cal-standing-meta">${s.w}V ${s.d}P ${s.l}S · ${s.pf} pf</div>
            </div>
            <div class="cal-standing-pts">${s.pts}</div>
        </div>`).join('');
}

// ─── NAV LISTENERS ───────────────────────────────────────────────────────────

document.getElementById('cal-round-prev')?.addEventListener('click', () => {
    if (calRound > 1) { calRound--; renderCalRound(); }
});

document.getElementById('cal-round-next')?.addEventListener('click', () => {
    if (calRound < calSchedule.length) { calRound++; renderCalRound(); }
});

document.querySelectorAll('.cal-seg-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.cal-seg-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const v = btn.dataset.view;
        document.getElementById('cal-view-scontri').classList.toggle('hidden',    v !== 'scontri');
        document.getElementById('cal-view-classifica').classList.toggle('hidden', v !== 'classifica');
        if (v === 'classifica') renderCalStandings();
    });
});

// ═══════════════════════════════════════════════════════════════════════════════
// MATCH DETAIL — formazione + confronto
// ═══════════════════════════════════════════════════════════════════════════════

const DEFAULT_MODULES = [
    '4-3-3','4-4-2','4-2-3-1','4-3-2-1',
    '3-5-2','3-4-3','5-3-2','5-4-1',
    '4-5-1','4-1-4-1','3-6-1',
];

let mdRound          = null;  // round corrente del detail
let mdHomeUid        = null;
let mdAwayUid        = null;
let mdView           = 'confronto'; // 'formazione' | 'confronto'
let roster           = [];
let lineup           = [];
let activeModule     = '4-3-3';
let availableModules = [...DEFAULT_MODULES];
let pickerState      = null;
let liveScoresCache  = null;
let liveRefreshTimer = null;
let liveTotals       = { home: null, away: null }; // aggiornati dal confronto

function getMatchdayMeta(round) {
    return MATCHDAY_SCHEDULE.find(m => m.round === round);
}

function getRoundStatus(round) {
    const md = getMatchdayMeta(round);
    if (!md) return 'future';
    const now   = new Date(); now.setHours(0,0,0,0);
    const start = new Date(md.start + 'T00:00:00');
    const end   = new Date(md.end   + 'T23:59:59');
    if (now >= start && now <= end) return 'live';
    if (now > end) return 'past';
    return 'future';
}

function isMyMatch(homeUid, awayUid) {
    const uid = window.__user?.uid;
    return uid === homeUid || uid === awayUid;
}

// ─── OPEN MATCH DETAIL ──────────────────────────────────────────────────────

async function openMatchDetail(homeUid, awayUid, round) {
    mdRound   = round;
    mdHomeUid = homeUid;
    mdAwayUid = awayUid;

    const status = getRoundStatus(round);
    const isMine = isMyMatch(homeUid, awayUid);
    
    // Se la giornata è futura e il match è mio → mostra formazione, altrimenti confronto
    if (status === 'future' && isMine) {
        mdView = 'formazione';
    } else {
        mdView = 'confronto';
    }

    // nascondo la vista principale, mostro il dettaglio
    document.getElementById('cal-main-view').classList.add('hidden');
    document.getElementById('cal-match-detail').classList.remove('hidden');

    // carico dati formazione utente corrente
    const [userSnap, settingsSnap] = await Promise.all([
        getDoc(doc(db, 'users', window.__user.uid)),
        getDoc(doc(db, 'settings', 'modules')),
    ]);

    if (userSnap.exists()) {
        const data = userSnap.data();
        roster = data.players ?? [];
        if (settingsSnap.exists()) {
            const custom = settingsSnap.data().list ?? [];
            availableModules = [...new Set([...DEFAULT_MODULES, ...custom])].sort();
        }
        const savedIds = data[`lineup_r${round}`] ?? data.lineup ?? [];
        activeModule   = data[`module_r${round}`] ?? data.module ?? '4-3-3';
        lineup = savedIds.length
            ? savedIds.map(id => roster.find(p => String(p.id) === String(id))).filter(Boolean)
            : autoFill(roster, activeModule);
        roster.forEach(p => {
            if (!lineup.find(l => String(l.id) === String(p.id))) lineup.push(p);
        });
    }

    renderMatchDetail();

    // Se live, inizia polling
    if (status === 'live') startLivePolling();
}

function closeMatchDetail() {
    stopLivePolling();
    document.getElementById('cal-match-detail').classList.add('hidden');
    document.getElementById('cal-main-view').classList.remove('hidden');
    document.getElementById('cal-subtitle').textContent = `${calTeams.length} squadre · ${calSchedule.length} giornate`;
}

// ─── RENDER MATCH DETAIL ────────────────────────────────────────────────────

function renderMatchDetail() {
    const content = document.getElementById('match-detail-content');
    const md      = getMatchdayMeta(mdRound);
    const status  = getRoundStatus(mdRound);
    const isMine  = isMyMatch(mdHomeUid, mdAwayUid);
    const home    = calTeams.find(t => t.uid === mdHomeUid);
    const away    = calTeams.find(t => t.uid === mdAwayUid);
    const res     = calResults[String(mdRound)]?.[`${mdHomeUid}_${mdAwayUid}`];
    const played  = res?.home_score !== undefined;

    const canEdit = status === 'future' && isMine;

    // Header
    const headerHtml = `
        <div class="md-header">
            <button class="md-back-btn" id="md-back">
                <span class="material-symbols-outlined">arrow_back</span>
            </button>
            <div class="md-header-info">
                <div class="md-header-round">${md?.short ?? 'G' + mdRound} — ${md?.label ?? ''}</div>
                <div class="md-header-dates">${formatDate(md?.start)} – ${formatDate(md?.end)}</div>
            </div>
            ${status === 'live' ? '<span class="md-live-pill">LIVE</span>' : ''}
        </div>`;

    // Score bar — usa liveTotals se disponibili durante il live
    const liveHome = liveTotals.home != null ? liveTotals.home.toFixed(1) : null;
    const liveAway = liveTotals.away != null ? liveTotals.away.toFixed(1) : null;
    const hasLive  = status === 'live' && liveHome != null;
    
    const scoreHtml = `
        <div class="md-score-bar">
            <div class="md-score-team">
                <div class="md-score-logo">${logoHtml(mdHomeUid, calTeams)}</div>
                <div class="md-score-name">${home?.team_name ?? '?'}</div>
            </div>
            <div class="md-score-center">
                ${played
                    ? `<span class="md-score-val">${res?.home_score?.toFixed?.(1) ?? res?.home_score ?? '–'}</span>
                       <span class="md-score-sep">:</span>
                       <span class="md-score-val">${res?.away_score?.toFixed?.(1) ?? res?.away_score ?? '–'}</span>`
                    : hasLive
                    ? `<span class="md-score-val ${scoreClass(liveTotals.home)}">${liveHome}</span>
                       <span class="md-score-sep">:</span>
                       <span class="md-score-val ${scoreClass(liveTotals.away)}">${liveAway}</span>`
                    : `<span class="md-score-vs">VS</span>`}
            </div>
            <div class="md-score-team">
                <div class="md-score-logo">${logoHtml(mdAwayUid, calTeams)}</div>
                <div class="md-score-name">${away?.team_name ?? '?'}</div>
            </div>
        </div>`;

    // Switch (solo se futura e match mio)
    const switchHtml = canEdit ? `
        <div class="md-switch-bar">
            <button class="md-switch-btn ${mdView === 'formazione' ? 'active' : ''}" data-view="formazione">
                <span class="material-symbols-outlined">sports_soccer</span> Formazione
            </button>
            <button class="md-switch-btn ${mdView === 'confronto' ? 'active' : ''}" data-view="confronto">
                <span class="material-symbols-outlined">compare_arrows</span> Confronto
            </button>
        </div>` : '';

    // Content area
    let bodyHtml = '';
    if (mdView === 'formazione' && canEdit) {
        bodyHtml = renderFormationEditor();
    } else {
        bodyHtml = renderConfrontoView(res, status);
    }

    content.innerHTML = headerHtml + scoreHtml + switchHtml + `<div id="md-body">${bodyHtml}</div>`;

    // Listeners
    document.getElementById('md-back')?.addEventListener('click', closeMatchDetail);

    content.querySelectorAll('.md-switch-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            mdView = btn.dataset.view;
            renderMatchDetail();
        });
    });

    if (mdView === 'formazione' && canEdit) {
        attachFormationEvents();
    }

    // Toggle compatto/dettaglio
    document.getElementById('confronto-toggle')?.addEventListener('click', () => {
        confrontoDetail = !confrontoDetail;
        renderMatchDetail();
    });
}

// ═══════════════════════════════════════════════════════════════════════════════
// FORMAZIONE EDITOR (integrato nel match detail)
// ═══════════════════════════════════════════════════════════════════════════════

function renderFormationEditor() {
    if (!roster.length) {
        return `
            <div class="empty-state" style="padding-top:40px">
                <span class="material-symbols-outlined">sports_soccer</span>
                <h3>Rosa vuota</h3>
                <p>Acquista almeno 11 giocatori dal Listone</p>
            </div>`;
    }

    const s     = lineup.slice(0, 11);
    const b     = lineup.slice(11);
    const warns = validateLineup();
    const { dif, cen, att } = parseModule(activeModule);

    const warningsHtml = warns.length ? `
        <div class="form-warnings">
            ${warns.map(w => `
                <div class="form-warning form-warning-${w.level}">
                    <span class="material-symbols-outlined">${w.icon}</span>${w.text}
                </div>`).join('')}
        </div>` : '';

    const modulesHtml = `
        <div class="form-module-row">
            <div class="form-module-label">Modulo</div>
            <div class="form-module-chips">
                ${availableModules.map(m =>
                    `<button class="form-module-chip ${m === activeModule ? 'active' : ''}" data-module="${m}">${m}</button>`
                ).join('')}
            </div>
        </div>`;

    const rows = [
        { role: 'ATT', count: att },
        { role: 'CEN', count: cen },
        { role: 'DIF', count: dif },
        { role: 'POR', count: 1   },
    ];

    const fieldHtml = `
        <div class="form-field">
            <div class="form-field-grass">
                ${rows.map(row => {
                    const rp = s.filter(p => p?.role === row.role);
                    return `<div class="form-field-row">
                        ${Array.from({ length: row.count }).map((_, i) =>
                            slotHtml(rp[i] ?? null, row.role, i, false)
                        ).join('')}
                    </div>`;
                }).join('')}
            </div>
        </div>`;

    const benchHtml = `
        <div class="form-bench">
            <div class="form-bench-title">
                <span class="material-symbols-outlined">airline_seat_recline_normal</span>
                Panchina <span class="form-bench-count">${b.length}</span>
            </div>
            <div class="form-bench-list">
                ${b.length ? b.map((p, i) => benchRowHtml(p, i, false)).join('') : `<div class="form-bench-empty">Rosa completa schierata</div>`}
            </div>
        </div>`;

    const saveBar = `
        <div class="form-save-bar">
            <button class="btn-cta" id="btn-save-formazione">
                <span class="material-symbols-outlined">check_circle</span>Salva formazione G${mdRound}
            </button>
        </div>`;

    return warningsHtml + modulesHtml + fieldHtml + benchHtml + saveBar;
}

function slotHtml(p, role, idx, locked) {
    if (!p) return `
        <div class="form-slot empty${!locked ? ' tappable' : ''}" data-role="${role}" data-slot-idx="${idx}">
            <div class="form-slot-inner">
                <span class="material-symbols-outlined form-slot-add-icon">add_circle_outline</span>
                <div class="form-slot-role-label">${role}</div>
            </div>
        </div>`;
    return `
        <div class="form-slot filled${!locked ? ' tappable' : ''}" data-id="${p.id}" data-role="${role}" data-slot-idx="${idx}">
            <div class="form-slot-inner">
                <div class="form-slot-photo-wrap">
                    <img class="form-slot-photo" src="${p.photo ?? ''}" alt="${p.name}" onerror="this.src='https://placehold.co/52x52/1a3a1a/fff?text=?'">
                    <span class="form-slot-role-badge badge-${p.role}">${p.role}</span>
                </div>
                <div class="form-slot-name">${p.name.split(' ').pop()}</div>
                ${!locked ? `<div class="form-slot-tap-hint"><span class="material-symbols-outlined">swap_vert</span></div>` : ''}
            </div>
        </div>`;
}

function benchRowHtml(p, idx, locked) {
    return `
        <div class="form-bench-row${!locked ? ' tappable' : ''}" data-id="${p.id}" data-bench-idx="${idx}">
            <div class="form-bench-order">${idx + 1}</div>
            <img class="form-bench-photo" src="${p.photo ?? ''}" alt="${p.name}" onerror="this.src='https://placehold.co/36x36/f2f2f7/aeaeb2?text=?'">
            <div class="form-bench-info">
                <div class="form-bench-name">${p.name}</div>
                <div class="form-bench-meta"><span class="role-badge badge-${p.role}">${p.role}</span></div>
            </div>
            ${!locked ? `<span class="material-symbols-outlined form-bench-swap-icon">swap_vert</span>` : ''}
        </div>`;
}

function attachFormationEvents() {
    document.querySelectorAll('.form-module-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            activeModule = chip.dataset.module;
            lineup = autoFill(roster, activeModule);
            renderMatchDetail();
        });
    });

    document.querySelectorAll('.form-slot').forEach(slot => {
        slot.addEventListener('click', () => openPicker({
            role:      slot.dataset.role,
            slotIdx:   parseInt(slot.dataset.slotIdx),
            currentId: slot.dataset.id ?? null,
            context:   'starter',
        }));
    });

    document.querySelectorAll('.form-bench-row').forEach(row => {
        row.addEventListener('click', () => {
            const p = lineup.slice(11)[parseInt(row.dataset.benchIdx)];
            if (!p) return;
            openPicker({ role: p.role, currentId: String(p.id), context: 'bench' });
        });
    });

    document.getElementById('btn-save-formazione')?.addEventListener('click', saveFormazione);
}

// ─── PICKER ──────────────────────────────────────────────────────────────────

function openPicker({ role, slotIdx, currentId, context }) {
    pickerState = { role, slotIdx, currentId, context };

    const candidates = context === 'starter'
        ? [...roster].sort((a, b) => {
              if (a.role === role && b.role !== role) return -1;
              if (b.role === role && a.role !== role) return  1;
              return b.price - a.price;
          })
        : lineup.slice(0, 11).filter(Boolean).sort((a, b) => {
              if (a.role === role && b.role !== role) return -1;
              if (b.role === role && a.role !== role) return  1;
              return b.price - a.price;
          });

    const title   = context === 'starter' ? `Scegli per lo slot ${role}` : `Scambia con un titolare`;
    const overlay = document.getElementById('formation-picker-overlay');
    const sheet   = document.getElementById('formation-picker-sheet');

    sheet.innerHTML = `
        <div class="fpicker-handle"></div>
        <div class="fpicker-header">
            <div class="fpicker-title">${title}</div>
            <button class="fpicker-close" id="fpicker-close">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        ${currentId ? `
        <div class="fpicker-actions">
            <button class="fpicker-remove-btn" id="fpicker-remove">
                <span class="material-symbols-outlined">arrow_downward</span>Sposta in panchina
            </button>
        </div>` : ''}
        <div class="fpicker-list">
            ${candidates.map(p => {
                const li        = lineup.findIndex(l => String(l?.id) === String(p.id));
                const isStarter = li !== -1 && li < 11;
                const isCurrent = String(p.id) === String(currentId ?? '');
                const roleMatch = p.role === role;
                return `
                <div class="fpicker-row ${isCurrent ? 'current' : ''} ${!roleMatch ? 'role-mismatch' : ''}" data-id="${p.id}">
                    <img class="fpicker-photo" src="${p.photo ?? ''}" alt="${p.name}"
                        onerror="this.src='https://placehold.co/44x44/f2f2f7/aeaeb2?text=${encodeURIComponent(p.name?.[0]??'?')}'">
                    <div class="fpicker-info">
                        <div class="fpicker-name">${p.name}</div>
                        <div class="fpicker-meta">
                            <span class="role-badge badge-${p.role}" style="margin:0">${p.role}</span>
                            <span class="fpicker-price"><span class="material-symbols-outlined" style="font-size:10px">toll</span>${p.price}</span>
                            ${!roleMatch ? `<span class="fpicker-warn">ruolo diverso</span>` : ''}
                        </div>
                    </div>
                    <span class="fpicker-badge ${isCurrent ? 'current-badge' : isStarter ? 'starter-badge' : 'bench-badge'}">
                        ${isCurrent ? 'attuale' : isStarter ? 'titolare' : 'panchina'}
                    </span>
                </div>`;
            }).join('')}
        </div>`;

    overlay.classList.remove('hidden');
    requestAnimationFrame(() => overlay.classList.add('open'));

    document.getElementById('fpicker-close')?.addEventListener('click', closePicker);
    overlay.addEventListener('click', e => { if (e.target === overlay) closePicker(); }, { once: true });
    document.getElementById('fpicker-remove')?.addEventListener('click', () => { if (currentId) moveToBench(currentId); closePicker(); });
    sheet.querySelectorAll('.fpicker-row').forEach(row => {
        row.addEventListener('click', () => { applyPick(String(row.dataset.id)); closePicker(); });
    });
}

function closePicker() {
    const overlay = document.getElementById('formation-picker-overlay');
    overlay.classList.remove('open');
    overlay.addEventListener('transitionend', () => overlay.classList.add('hidden'), { once: true });
    pickerState = null;
}

function applyPick(targetId) {
    if (!pickerState) return;
    const { currentId } = pickerState;
    const ti = lineup.findIndex(p => String(p?.id) === String(targetId));
    const ci = currentId ? lineup.findIndex(p => String(p?.id) === String(currentId)) : -1;
    if (ti === -1) return;
    if (ci !== -1) {
        [lineup[ci], lineup[ti]] = [lineup[ti], lineup[ci]];
    } else {
        const p = lineup.splice(ti, 1)[0];
        lineup.splice(10, 0, p);
    }
    renderMatchDetail();
}

function moveToBench(playerId) {
    const idx = lineup.findIndex(p => String(p?.id) === String(playerId));
    if (idx === -1 || idx >= 11) return;
    const [p] = lineup.splice(idx, 1);
    lineup.push(p);
    renderMatchDetail();
}

// ─── SAVE FORMAZIONE ────────────────────────────────────────────────────────

async function saveFormazione() {
    if (!window.__user?.uid || !mdRound) return;
    const warns  = validateLineup();
    const errors = warns.filter(w => w.level === 'error');
    if (errors.length) { toast(errors[0].text, 'error'); return; }

    const btn = document.getElementById('btn-save-formazione');
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="material-symbols-outlined">hourglass_empty</span> Salvataggio...'; }

    try {
        const lineupIds = lineup.map(p => String(p.id));
        await setDoc(doc(db, 'users', window.__user.uid), {
            [`lineup_r${mdRound}`]: lineupIds,
            [`module_r${mdRound}`]: activeModule,
            lineup: lineupIds,
            module: activeModule,
        }, { merge: true });

        // aggiorna subito la cache locale così il Confronto è aggiornato
        const uid = window.__user.uid;
        if (!calUsersMap[uid]) calUsersMap[uid] = {};
        calUsersMap[uid][`lineup_r${mdRound}`] = lineupIds;
        calUsersMap[uid][`module_r${mdRound}`] = activeModule;
        calUsersMap[uid].lineup = lineupIds;
        calUsersMap[uid].module = activeModule;
        // assicurati che i players siano nella cache
        if (roster.length && (!calUsersMap[uid].players || !calUsersMap[uid].players.length)) {
            calUsersMap[uid].players = roster;
        }

        toast(`Formazione G${mdRound} salvata ✓`);
    } catch { toast('Errore salvataggio', 'error'); }
    finally {
        if (btn) { btn.disabled = false; btn.innerHTML = `<span class="material-symbols-outlined">check_circle</span>Salva formazione G${mdRound}`; }
    }
}

// ─── HELPERS FORMAZIONE ─────────────────────────────────────────────────────

function parseModule(mod) {
    const parts = mod.split('-').map(Number);
    return { dif: parts[0] ?? 4, cen: parts[1] ?? 3, att: parts[2] ?? 3 };
}

function autoFill(players, mod) {
    const { dif, cen, att } = parseModule(mod);
    const slots  = { POR: 1, DIF: dif, CEN: cen, ATT: att };
    const filled = [];
    const bench  = [];
    Object.entries(slots).forEach(([role, count]) => {
        const sorted = players.filter(p => p.role === role).sort((a, b) => b.price - a.price);
        filled.push(...sorted.slice(0, count));
        bench.push(...sorted.slice(count));
    });
    players.forEach(p => {
        if (!filled.find(f => String(f.id) === String(p.id)) &&
            !bench.find(b => String(b.id) === String(p.id))) bench.push(p);
    });
    return [...filled, ...bench];
}

function validateLineup() {
    const s = lineup.slice(0, 11);
    const { dif, cen, att } = parseModule(activeModule);
    const w    = [];
    const pors = s.filter(p => p?.role === 'POR').length;
    const difs = s.filter(p => p?.role === 'DIF').length;
    const cens = s.filter(p => p?.role === 'CEN').length;
    const atts = s.filter(p => p?.role === 'ATT').length;
    const tot  = s.filter(Boolean).length;
    if (pors === 0) w.push({ icon: 'error',   text: 'Nessun portiere schierato',                                 level: 'error' });
    if (pors > 1)   w.push({ icon: 'warning', text: 'Più di un portiere tra i titolari',                        level: 'warn'  });
    if (difs < dif) w.push({ icon: 'warning', text: `Modulo ${activeModule}: servono ${dif} DIF (hai ${difs})`, level: 'warn'  });
    if (cens < cen) w.push({ icon: 'warning', text: `Modulo ${activeModule}: servono ${cen} CEN (hai ${cens})`, level: 'warn'  });
    if (atts < att) w.push({ icon: 'warning', text: `Modulo ${activeModule}: servono ${att} ATT (hai ${atts})`, level: 'warn'  });
    if (tot < 11)   w.push({ icon: 'info',    text: `${tot}/11 titolari schierati`,                              level: 'info'  });
    return w;
}

// ═══════════════════════════════════════════════════════════════════════════════
// CONFRONTO VIEW — formazioni a confronto con voti
// ═══════════════════════════════════════════════════════════════════════════════

function renderConfrontoView(res, status) {
    const homeData = calUsersMap[mdHomeUid] ?? {};
    const awayData = calUsersMap[mdAwayUid] ?? {};

    // Se i risultati sono già calcolati (da admin), mostra quelli
    if (res?.home_detail && res?.away_detail) {
        return renderConfrontoFromResults(res);
    }

    // Altrimenti, ricostruisci dalle lineup salvate
    const homeLineupIds = homeData[`lineup_r${mdRound}`] ?? homeData.lineup ?? [];
    const awayLineupIds = awayData[`lineup_r${mdRound}`] ?? awayData.lineup ?? [];
    const homeRoster    = homeData.players ?? [];
    const awayRoster    = awayData.players ?? [];

    const homeAll = homeLineupIds
        .map(id => homeRoster.find(p => String(p.id) === String(id)))
        .filter(Boolean);
    const awayAll = awayLineupIds
        .map(id => awayRoster.find(p => String(p.id) === String(id)))
        .filter(Boolean);

    const homeLineup = homeAll.slice(0, 11);
    const awayLineup = awayAll.slice(0, 11);
    const homeBench  = homeAll.slice(11);
    const awayBench  = awayAll.slice(11);

    const homeModule = homeData[`module_r${mdRound}`] ?? homeData.module ?? '4-3-3';
    const awayModule = awayData[`module_r${mdRound}`] ?? awayData.module ?? '4-3-3';

    // Se live, prova a mostrare voti live
    const liveStats = (status === 'live') ? liveScoresCache : null;

    const homeName = calTeams.find(t => t.uid === mdHomeUid)?.team_name ?? '?';
    const awayName = calTeams.find(t => t.uid === mdAwayUid)?.team_name ?? '?';

    // Panchina
    const benchMaxLen = Math.max(homeBench.length, awayBench.length);
    // Toggle compatto/dettaglio
    const toggleHtml = status !== 'future' ? `
        <div class="confronto-toggle-wrap">
            <button class="confronto-toggle-btn" id="confronto-toggle">
                <span class="material-symbols-outlined">${confrontoDetail ? 'visibility_off' : 'visibility'}</span>
                ${confrontoDetail ? 'Compatto' : 'Dettaglio'}
            </button>
        </div>` : '';

    // Panchina con renderConfrontoRows
    let benchSection = '';
    if (benchMaxLen > 0) {
        benchSection = `
            <div class="confronto-bench-title">
                <span class="material-symbols-outlined">airline_seat_recline_normal</span>
                Panchina
            </div>
            <div class="confronto-list bench-list">
                ${renderConfrontoRows(homeBench, awayBench, liveStats, status, true)}
            </div>`;
    }

    return `
        <div class="confronto-wrap">
            <div class="confronto-modules">
                <span class="confronto-mod">${homeModule}</span>
                <span class="confronto-mod-label">Moduli</span>
                <span class="confronto-mod">${awayModule}</span>
            </div>
            ${toggleHtml}
            <div class="confronto-header">
                <div class="confronto-team-name">${homeName}</div>
                <div class="confronto-team-name">${awayName}</div>
            </div>
            <div class="confronto-list">
                ${renderConfrontoRows(homeLineup, awayLineup, liveStats, status, false)}
            </div>
            ${benchSection}
            ${!homeLineup.length && !awayLineup.length ? `
                <div class="empty-state" style="padding:30px 20px">
                    <span class="material-symbols-outlined">sports_soccer</span>
                    <h3>Formazioni non ancora schierate</h3>
                </div>` : ''}
        </div>`;
}

function renderConfrontoFromResults(res) {
    const homeDetail = res.home_detail ?? [];
    const awayDetail = res.away_detail ?? [];
    const maxLen     = Math.max(homeDetail.length, awayDetail.length);

    let rows = '';
    for (let i = 0; i < maxLen; i++) {
        const h = homeDetail[i];
        const a = awayDetail[i];
        rows += `
        <div class="confronto-row">
            <div class="confronto-player home ${h ? '' : 'empty'}">
                ${h ? `
                    <span class="confronto-score ${scoreClass(h.score)}">${h.score?.toFixed(1) ?? '–'}</span>
                    <span class="confronto-pname">${h.name?.split(' ').pop() ?? ''}</span>
                    <span class="role-badge badge-${h.role}" style="margin:0;font-size:9px">${h.role}</span>
                ` : ''}
            </div>
            <div class="confronto-divider"></div>
            <div class="confronto-player away ${a ? '' : 'empty'}">
                ${a ? `
                    <span class="role-badge badge-${a.role}" style="margin:0;font-size:9px">${a.role}</span>
                    <span class="confronto-pname">${a.name?.split(' ').pop() ?? ''}</span>
                    <span class="confronto-score ${scoreClass(a.score)}">${a.score?.toFixed(1) ?? '–'}</span>
                ` : ''}
            </div>
        </div>`;
    }

    return `
        <div class="confronto-wrap">
            <div class="confronto-list">${rows}</div>
            <div class="confronto-total">
                <span class="confronto-total-val">${res.home_score?.toFixed(1) ?? '–'}</span>
                <span class="confronto-total-label">Totale</span>
                <span class="confronto-total-val">${res.away_score?.toFixed(1) ?? '–'}</span>
            </div>
        </div>`;
}

// ─── TABELLA PUNTEGGI FANTACALCIO ────────────────────────────────────────────

const SCORE_TABLE = {
    goal:        { POR: 10, DIF: 6, CEN: 6, ATT: 8 },
    assist:      3,
    yellow:     -0.5,
    red:        -2,
    clean_sheet: { POR: 2, DIF: 1 },
};

let confrontoDetail = false; // false = compatto, true = dettaglio

function calcLiveScore(player, stats) {
    if (!stats) return null;
    const base = stats.rating ?? 6;
    let score = base;
    if (stats) {
        score += (stats.goals   ?? 0) * (SCORE_TABLE.goal[player.role] ?? 6);
        score += (stats.assists ?? 0) * SCORE_TABLE.assist;
        score += (stats.yellow  ?? 0) * SCORE_TABLE.yellow;
        score += (stats.red     ?? 0) * SCORE_TABLE.red;
        if (stats.cs && SCORE_TABLE.clean_sheet[player.role]) {
            score += SCORE_TABLE.clean_sheet[player.role];
        }
    }
    return Math.round(score * 100) / 100;
}

function bonusBreakdown(player, stats) {
    if (!stats) return '';
    const parts = [];
    const g = stats.goals ?? 0;
    const a = stats.assists ?? 0;
    const y = stats.yellow ?? 0;
    const r = stats.red ?? 0;
    if (g > 0) {
        const bonus = g * (SCORE_TABLE.goal[player.role] ?? 6);
        parts.push(`<span class="bd-item bd-goal"><span class="material-symbols-outlined">sports_soccer</span>+${bonus.toFixed(1)}</span>`);
    }
    if (a > 0) {
        const bonus = a * SCORE_TABLE.assist;
        parts.push(`<span class="bd-item bd-assist"><span class="material-symbols-outlined">handshake</span>+${bonus.toFixed(1)}</span>`);
    }
    if (r > 0) {
        parts.push(`<span class="bd-item bd-red"><span class="material-symbols-outlined">square</span>${SCORE_TABLE.red.toFixed(1)}</span>`);
    } else if (y > 0) {
        parts.push(`<span class="bd-item bd-yellow"><span class="material-symbols-outlined">square</span>${SCORE_TABLE.yellow.toFixed(1)}</span>`);
    }
    if (stats.cs && SCORE_TABLE.clean_sheet[player.role]) {
        parts.push(`<span class="bd-item bd-cs"><span class="material-symbols-outlined">security</span>+${SCORE_TABLE.clean_sheet[player.role].toFixed(1)}</span>`);
    }
    return parts.length ? `<div class="bd-row">${parts.join('')}</div>` : '';
}

function statBadgesCompact(stats) {
    if (!stats) return '';
    let b = '';
    if ((stats.goals ?? 0) > 0)  b += `<span class="confronto-badge badge-goal"><span class="material-symbols-outlined">sports_soccer</span>${stats.goals > 1 ? '×' + stats.goals : ''}</span>`;
    if ((stats.assists ?? 0) > 0) b += `<span class="confronto-badge badge-assist"><span class="material-symbols-outlined">handshake</span>${stats.assists > 1 ? '×' + stats.assists : ''}</span>`;
    if ((stats.red ?? 0) > 0) b += `<span class="confronto-badge badge-red"><span class="material-symbols-outlined">square</span></span>`;
    else if ((stats.yellow ?? 0) > 0) b += `<span class="confronto-badge badge-yellow"><span class="material-symbols-outlined">square</span></span>`;
    return b;
}

function renderPlayerCell(p, stats, score, side, pending, isDetail) {
    if (!p) return `<span class="confronto-empty-slot">—</span>`;
    
    const flag = flagImg(p.nationality || p.team);
    const name = p.name?.split(' ').pop() ?? '';
    const role = `<span class="role-badge badge-${p.role}" style="margin:0;font-size:9px">${p.role}</span>`;
    
    // Se pending (pre-partita) mostra –, se no stats mostra SV, altrimenti voto
    let scoreLabel;
    let scoreClass2;
    if (pending) {
        scoreLabel = '–';
        scoreClass2 = 'pending';
    } else if (score === null) {
        scoreLabel = 'SV';
        scoreClass2 = 'pending';
    } else {
        scoreLabel = score.toFixed(1);
        scoreClass2 = scoreClass(score);
    }
    
    const scoreHtml = `<span class="confronto-score ${scoreClass2}">${scoreLabel}</span>`;
    const badges = !pending && score !== null ? statBadgesCompact(stats) : '';
    const bd = (!pending && isDetail && score !== null) ? bonusBreakdown(p, stats) : '';

    // Home: voto a SX, poi flag nome badge ruolo → (allineato a dx)
    // Away: ← ruolo badge nome flag, poi voto a DX
    if (side === 'home') {
        return `
            <div class="cp-row">
                ${scoreHtml}
                <span class="cp-info home">${role}${flag}<span class="confronto-pname">${name}</span>${badges}</span>
            </div>
            ${bd}`;
    } else {
        return `
            <div class="cp-row">
                <span class="cp-info away">${badges}<span class="confronto-pname">${name}</span>${flag}${role}</span>
                ${scoreHtml}
            </div>
            ${bd}`;
    }
}

function renderConfrontoRows(homeLineup, awayLineup, liveStats, status, isBench) {
    const maxLen = isBench 
        ? Math.max(homeLineup.length, awayLineup.length) 
        : Math.max(homeLineup.length, awayLineup.length, 11);
    let rows = '';
    let homeTotal = 0, awayTotal = 0;
    let homeCount = 0, awayCount = 0;
    const isLive = status === 'live';
    const pending = status === 'future' || (!liveStats && status !== 'past');
    const isDetail = confrontoDetail;

    for (let i = 0; i < maxLen; i++) {
        const h = homeLineup[i];
        const a = awayLineup[i];
        
        // Stats dall'API o null
        const hStats = h && liveStats ? (liveStats[String(h.id)] ?? null) : null;
        const aStats = a && liveStats ? (liveStats[String(a.id)] ?? null) : null;
        
        // Voto solo se abbiamo stats reali dall'API (lineups o events)
        // Se non abbiamo stats → null → mostra "SV"
        const hScore = (h && hStats) ? calcLiveScore(h, hStats) : null;
        const aScore = (a && aStats) ? calcLiveScore(a, aStats) : null;

        if (hScore != null) { homeTotal += hScore; homeCount++; }
        if (aScore != null) { awayTotal += aScore; awayCount++; }

        rows += `
        <div class="confronto-row ${isDetail ? 'detail' : ''} ${isBench ? 'bench' : ''}">
            <div class="confronto-player home ${h ? '' : 'empty'}">
                ${renderPlayerCell(h, hStats, hScore ?? 0, 'home', pending, isDetail)}
            </div>
            <div class="confronto-divider ${isBench ? 'bench-num' : ''}">${isBench ? i + 1 : i + 1}</div>
            <div class="confronto-player away ${a ? '' : 'empty'}">
                ${renderPlayerCell(a, aStats, aScore ?? 0, 'away', pending, isDetail)}
            </div>
        </div>`;
    }

    // Salva i totali per la score bar (solo titolari, non panchina)
    if (!isBench) {
        liveTotals.home = homeCount > 0 ? homeTotal : null;
        liveTotals.away = awayCount > 0 ? awayTotal : null;
    }

    return rows;
}

// ─── FLAG HELPER ────────────────────────────────────────────────────────────

const COUNTRY_CODES = {
    'Afghanistan':'af','Albania':'al','Algeria':'dz','Andorra':'ad','Angola':'ao',
    'Argentina':'ar','Armenia':'am','Australia':'au','Austria':'at','Azerbaijan':'az',
    'Bahrain':'bh','Bangladesh':'bd','Belarus':'by','Belgium':'be','Benin':'bj',
    'Bolivia':'bo','Bosnia and Herzegovina':'ba','Botswana':'bw','Brazil':'br',
    'Bulgaria':'bg','Burkina Faso':'bf','Burundi':'bi','Cameroon':'cm','Canada':'ca',
    'Cape Verde':'cv','Central African Republic':'cf','Chad':'td','Chile':'cl',
    'China':'cn','Colombia':'co','Comoros':'km','Congo':'cg','Costa Rica':'cr',
    'Croatia':'hr','Cuba':'cu','Curaçao':'cw','Cyprus':'cy','Czech Republic':'cz',
    'Czechia':'cz','DR Congo':'cd','Denmark':'dk','Ecuador':'ec','Egypt':'eg',
    'El Salvador':'sv','England':'gb-eng','Equatorial Guinea':'gq','Eritrea':'er',
    'Estonia':'ee','Ethiopia':'et','Fiji':'fj','Finland':'fi','France':'fr',
    'Gabon':'ga','Gambia':'gm','Georgia':'ge','Germany':'de','Ghana':'gh',
    'Greece':'gr','Grenada':'gd','Guatemala':'gt','Guinea':'gn','Haiti':'ht',
    'Honduras':'hn','Hungary':'hu','Iceland':'is','India':'in','Indonesia':'id',
    'Iran':'ir','Iraq':'iq','Ireland':'ie','Israel':'il','Italy':'it',
    'Ivory Coast':'ci','Cote D\'Ivoire':'ci','Jamaica':'jm','Japan':'jp','Jordan':'jo',
    'Kazakhstan':'kz','Kenya':'ke','Korea Republic':'kr','South Korea':'kr',
    'Kosovo':'xk','Kuwait':'kw','Kyrgyzstan':'kg','Latvia':'lv','Lebanon':'lb',
    'Libya':'ly','Liechtenstein':'li','Lithuania':'lt','Luxembourg':'lu',
    'Madagascar':'mg','Malawi':'mw','Malaysia':'my','Mali':'ml','Malta':'mt',
    'Mauritania':'mr','Mauritius':'mu','Mexico':'mx','Moldova':'md','Monaco':'mc',
    'Montenegro':'me','Morocco':'ma','Mozambique':'mz','Myanmar':'mm',
    'Namibia':'na','Nepal':'np','Netherlands':'nl','New Zealand':'nz',
    'Nicaragua':'ni','Niger':'ne','Nigeria':'ng','North Macedonia':'mk',
    'Northern Ireland':'gb-nir','Norway':'no','Oman':'om','Pakistan':'pk',
    'Palestine':'ps','Panama':'pa','Paraguay':'py','Peru':'pe','Philippines':'ph',
    'Poland':'pl','Portugal':'pt','Puerto Rico':'pr','Qatar':'qa','Romania':'ro',
    'Russia':'ru','Rwanda':'rw','Saudi Arabia':'sa','Scotland':'gb-sct',
    'Senegal':'sn','Serbia':'rs','Sierra Leone':'sl','Singapore':'sg',
    'Slovakia':'sk','Slovenia':'si','Somalia':'so','South Africa':'za',
    'Spain':'es','Sri Lanka':'lk','Sudan':'sd','Suriname':'sr','Sweden':'se',
    'Switzerland':'ch','Syria':'sy','Tanzania':'tz','Thailand':'th','Togo':'tg',
    'Trinidad and Tobago':'tt','Tunisia':'tn','Turkey':'tr','Turkmenistan':'tm',
    'Uganda':'ug','Ukraine':'ua','United Arab Emirates':'ae','United States':'us',
    'USA':'us','Uruguay':'uy','Uzbekistan':'uz','Venezuela':'ve','Vietnam':'vn',
    'Wales':'gb-wls','Yemen':'ye','Zambia':'zm','Zimbabwe':'zw',
    'American Samoa':'as','Bermuda':'bm','Guam':'gu','US Virgin Islands':'vi',
};

function flagImg(nationality) {
    if (!nationality) return '';
    const code = COUNTRY_CODES[nationality];
    if (!code) return `<span class="confronto-nat-text">${nationality.slice(0,3).toUpperCase()}</span>`;
    return `<img class="confronto-flag" src="assets/flags/${code}.svg" alt="${nationality}" title="${nationality}">`;
}

function scoreClass(score) {
    if (score == null) return '';
    if (score >= 7) return 'score-high';
    if (score >= 6) return 'score-mid';
    return 'score-low';
}

// ─── LIVE POLLING ───────────────────────────────────────────────────────────

async function fetchLiveScores() {
    try {
        const res  = await fetch('get_live_scores.php?mode=test');
        const data = await res.json();
        if (data.status === 'success') {
            liveScoresCache = data.players ?? {};
            // re-render solo il body del confronto se siamo in quella view
            if (mdView === 'confronto') {
                const bodyEl = document.getElementById('md-body');
                const status = getRoundStatus(mdRound);
                const result = calResults[String(mdRound)]?.[`${mdHomeUid}_${mdAwayUid}`];
                if (bodyEl) bodyEl.innerHTML = renderConfrontoView(result, status);
            }
        }
    } catch (err) { console.error('Live scores error:', err); }
}

function startLivePolling() {
    stopLivePolling();
    fetchLiveScores(); // fetch immediato
    liveRefreshTimer = setInterval(fetchLiveScores, 60000); // ogni 60s
}

function stopLivePolling() {
    if (liveRefreshTimer) { clearInterval(liveRefreshTimer); liveRefreshTimer = null; }
}

// ═══════════════════════════════════════════════════════════════════════════════
// ADMIN — genera calendario
// ═══════════════════════════════════════════════════════════════════════════════

const ROUND_LABELS = ['GJ1','GJ2','GJ3','Ottavi','Quarti','Semifinali','Finale'];
let previewSchedule = [];

function buildFullSchedule(teams) {
    const base = generateRoundRobin(teams);
    if (base.length >= 7) return base.slice(0, 7).map((rd, i) => ({ ...rd, round: i + 1 }));
    const full = [];
    let r = 0;
    while (full.length < 7) {
        const src = base[r % base.length];
        full.push({ round: full.length + 1, matches: src.matches });
        r++;
    }
    return full;
}

document.getElementById('btn-preview-calendar')?.addEventListener('click', async () => {
    const snap  = await getDocs(collection(db, 'users'));
    const teams = [];
    snap.forEach(d => {
        const data = d.data();
        if (data.competition_joined) teams.push({ uid: d.id, team_name: data.team_name ?? 'Squadra' });
    });
    if (teams.length < 2) { toast('Servono almeno 2 squadre iscritte', 'error'); return; }

    previewSchedule = buildFullSchedule(teams);
    const preview   = document.getElementById('admin-cal-preview');

    preview.innerHTML = previewSchedule.map(rd => `
        <div class="admin-cal-round">
            <div class="admin-cal-round-label">G${rd.round} — ${ROUND_LABELS[rd.round - 1] ?? ''}</div>
            ${rd.matches.map(m => `
                <div class="admin-cal-match">
                    <span>${m.home_name}</span>
                    <span class="admin-cal-vs">vs</span>
                    <span>${m.away_name}</span>
                </div>`).join('')}
        </div>`).join('');

    preview.classList.remove('hidden');
    document.getElementById('btn-generate-calendar').disabled = false;
    toast(`${teams.length} squadre · ${previewSchedule.length} giornate`);
});

document.getElementById('btn-generate-calendar')?.addEventListener('click', async () => {
    if (!previewSchedule.length) return;
    const btn = document.getElementById('btn-generate-calendar');
    btn.disabled = true;
    try {
        await setDoc(doc(db, 'settings', 'calendar'), {
            schedule:     previewSchedule,
            results:      {},
            generated_at: new Date().toISOString(),
        });
        toast('Calendario salvato ✓');
        document.getElementById('admin-cal-preview').classList.add('hidden');
        previewSchedule = [];
    } catch (err) {
        toast('Errore salvataggio: ' + err.message, 'error');
        btn.disabled = false;
    }
});

document.getElementById('btn-reset-calendar')?.addEventListener('click', async () => {
    const btn = document.getElementById('btn-reset-calendar');
    if (btn.dataset.confirm !== 'yes') {
        btn.dataset.confirm = 'yes';
        btn.innerHTML = '<span class="material-symbols-outlined">warning</span> Conferma reset';
        btn.style.background = 'var(--red-soft)';
        btn.style.color      = 'var(--red)';
        setTimeout(() => {
            btn.dataset.confirm  = '';
            btn.innerHTML = '<span class="material-symbols-outlined">delete_sweep</span> Reset calendario';
            btn.style.background = '';
            btn.style.color      = '';
        }, 3000);
        return;
    }
    btn.disabled = true;
    try {
        await setDoc(doc(db, 'settings', 'calendar'), {
            schedule: [], results: {}, generated_at: null,
        });
        previewSchedule = [];
        document.getElementById('admin-cal-preview')?.classList.add('hidden');
        document.getElementById('btn-generate-calendar').disabled = true;
        toast('Calendario resettato');
    } catch (err) { toast('Errore reset: ' + err.message, 'error'); }
    finally { btn.disabled = false; }
});

// ─── ADMIN MODULI (spostato qui da formation.js) ─────────────────────────────

export async function loadAdminModules() {
    const snap = await getDoc(doc(db, 'settings', 'modules'));
    const list = snap.exists() ? (snap.data().list ?? []) : [];
    renderAdminModules(list);
}

function renderAdminModules(list) {
    const container = document.getElementById('admin-modules-list');
    if (!container) return;
    container.innerHTML = list.length
        ? list.map(m => `
            <div class="admin-module-row">
                <span class="admin-module-name">${m}</span>
                <button class="admin-module-del" data-module="${m}">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>`).join('')
        : `<div style="font-size:12px;color:var(--text-3)">Nessun modulo custom aggiunto</div>`;
    container.querySelectorAll('.admin-module-del').forEach(btn => {
        btn.addEventListener('click', async () => {
            const updated = list.filter(m => m !== btn.dataset.module);
            await setDoc(doc(db, 'settings', 'modules'), { list: updated }, { merge: true });
            toast('Modulo rimosso');
            renderAdminModules(updated);
        });
    });
}

document.getElementById('btn-add-module')?.addEventListener('click', async () => {
    const input = document.getElementById('admin-module-input');
    const val   = input?.value.trim();
    if (!val) return;
    if (!/^\d+-\d+-\d+(-\d+)?$/.test(val)) { toast('Formato non valido — es. 4-3-3', 'error'); return; }
    const snap    = await getDoc(doc(db, 'settings', 'modules'));
    const list    = snap.exists() ? (snap.data().list ?? []) : [];
    if (list.includes(val)) { toast('Modulo già presente', 'error'); return; }
    const updated = [...list, val];
    await setDoc(doc(db, 'settings', 'modules'), { list: updated }, { merge: true });
    if (input) input.value = '';
    toast(`Modulo ${val} aggiunto`);
    renderAdminModules(updated);
});