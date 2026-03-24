import { db } from './firebase-init.js';
import { doc, getDoc, getDocs, setDoc, collection } from 'https://www.gstatic.com/firebasejs/10.8.1/firebase-firestore.js';
import { toast, formatDate } from './utils.js';

const MATCHDAY_SCHEDULE = [
    { round: 1, label: 'Fase a gironi – Giornata 1', start: '2026-06-11', end: '2026-06-14' },
    { round: 2, label: 'Fase a gironi – Giornata 2', start: '2026-06-15', end: '2026-06-19' },
    { round: 3, label: 'Fase a gironi – Giornata 3', start: '2026-06-20', end: '2026-06-25' },
    { round: 4, label: 'Ottavi di finale',            start: '2026-06-27', end: '2026-07-03' },
    { round: 5, label: 'Quarti di finale',            start: '2026-07-04', end: '2026-07-05' },
    { round: 6, label: 'Semifinali',                  start: '2026-07-07', end: '2026-07-08' },
    { round: 7, label: 'Finale 3° posto + Finale',    start: '2026-07-11', end: '2026-07-19' },
];

export function getCurrentMatchday() {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    for (const md of MATCHDAY_SCHEDULE) {
        const start = new Date(md.start);
        const end   = new Date(md.end);
        end.setHours(23, 59, 59);
        if (today >= start && today <= end) return { ...md, status: 'live' };
    }
    const first = new Date(MATCHDAY_SCHEDULE[0].start);
    if (today < first) return { ...MATCHDAY_SCHEDULE[0], status: 'upcoming' };
    for (let i = 0; i < MATCHDAY_SCHEDULE.length - 1; i++) {
        const endCur   = new Date(MATCHDAY_SCHEDULE[i].end);
        const startNxt = new Date(MATCHDAY_SCHEDULE[i + 1].start);
        if (today > endCur && today < startNxt) return { ...MATCHDAY_SCHEDULE[i + 1], status: 'next' };
    }
    return { ...MATCHDAY_SCHEDULE[MATCHDAY_SCHEDULE.length - 1], status: 'ended' };
}

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

// stato locale

let calSchedule = [];
let calResults  = {};
let calTeams    = [];
let calRound    = 1;

export async function loadCalendario() {
    document.getElementById('cal-subtitle').textContent = 'caricamento...';
    try {
        const [calSnap, usersSnap] = await Promise.all([
            getDoc(doc(db, 'settings', 'calendar')),
            getDocs(collection(db, 'users')),
        ]);
        calTeams = [];
        usersSnap.forEach(d => {
            const data = d.data();
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
        <div class="cal-match-card ${played ? 'played' : ''}">
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
        </div>`;
    }).join('');
    document.getElementById('cal-round-prev').disabled = calRound <= 1;
    document.getElementById('cal-round-next').disabled = calRound >= calSchedule.length;
}

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


// admin — genera calendario
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