import { db } from './firebase-init.js';
import { doc, getDoc, setDoc } from 'https://www.gstatic.com/firebasejs/10.8.1/firebase-firestore.js';
import { toast, formatDate } from './utils.js';
import { getCurrentMatchday } from './calendar.js';

const MATCHDAY_SCHEDULE = [
    { round: 1, label: 'Fase a gironi – GJ 1', short: 'GJ1', start: '2026-06-11', end: '2026-06-14' },
    { round: 2, label: 'Fase a gironi – GJ 2', short: 'GJ2', start: '2026-06-15', end: '2026-06-19' },
    { round: 3, label: 'Fase a gironi – GJ 3', short: 'GJ3', start: '2026-06-20', end: '2026-06-25' },
    { round: 4, label: 'Ottavi di finale',      short: 'R16', start: '2026-06-27', end: '2026-07-03' },
    { round: 5, label: 'Quarti di finale',      short: 'QF',  start: '2026-07-04', end: '2026-07-05' },
    { round: 6, label: 'Semifinali',            short: 'SF',  start: '2026-07-07', end: '2026-07-08' },
    { round: 7, label: 'Finale',                short: 'F',   start: '2026-07-11', end: '2026-07-19' },
];

const DEFAULT_MODULES = [
    '4-3-3','4-4-2',
    '3-5-2','3-4-3','5-3-2','5-4-1',
    '4-5-1','3-6-1',
];

let roster           = [];
let lineup           = [];
let activeModule     = '4-3-3';
let availableModules = [...DEFAULT_MODULES];
let activeRound      = null;
let pickerState      = null;

export async function loadFormazione() {
    if (!window.__user?.uid) return;
    renderRoundList();
}

function renderRoundList() {
    const current = getCurrentMatchday();
    const content = document.getElementById('formation-content');

    content.innerHTML = `
        <div class="fround-list">
            ${MATCHDAY_SCHEDULE.map(md => {
                const now      = new Date(); now.setHours(0,0,0,0);
                const start    = new Date(md.start);
                const end      = new Date(md.end); end.setHours(23,59,59);
                const isLive   = now >= start && now <= end;
                const isPast   = now > end;
                const isFuture = now < start;
                const isCurrent = md.round === current.round;

                let statusLabel = '';
                let statusClass = '';
                if (isLive)        { statusLabel = 'In corso'; statusClass = 'live'; }
                else if (isPast)   { statusLabel = 'Conclusa'; statusClass = 'past'; }
                else               { statusLabel = formatDate(md.start); statusClass = 'future'; }

                return `
                <div class="fround-card ${isCurrent ? 'current' : ''} ${isPast ? 'past' : ''} ${isFuture ? 'future' : ''}"
                     data-round="${md.round}">
                    <div class="fround-card-left">
                        <div class="fround-badge fround-badge-${statusClass}">${md.short}</div>
                        <div class="fround-info">
                            <div class="fround-label">${md.label}</div>
                            <div class="fround-dates">${formatDate(md.start)} – ${formatDate(md.end)}</div>
                        </div>
                    </div>
                    <div class="fround-card-right">
                        <div class="fround-status fround-status-${statusClass}">${statusLabel}</div>
                        <span class="material-symbols-outlined fround-arrow">chevron_right</span>
                    </div>
                </div>`;
            }).join('')}
        </div>`;

    content.querySelectorAll('.fround-card').forEach(card => {
        card.addEventListener('click', () => openRound(parseInt(card.dataset.round)));
    });
}

async function openRound(round) {
    activeRound = round;
    const md = MATCHDAY_SCHEDULE.find(m => m.round === round);
    if (!md) return;

    const now      = new Date(); now.setHours(0,0,0,0);
    const start    = new Date(md.start);
    const end      = new Date(md.end); end.setHours(23,59,59);
    const isLocked = now > end || md.round !== getCurrentMatchday().round;

    document.getElementById('form-subtitle').textContent = md.label;

    const [userSnap, settingsSnap] = await Promise.all([
        getDoc(doc(db, 'users', window.__user.uid)),
        getDoc(doc(db, 'settings', 'modules')),
    ]);

    if (!userSnap.exists()) return;
    const data = userSnap.data();
    roster = data.players ?? [];
    if (!roster.length) { renderEmptyRoster(); return; }

    if (settingsSnap.exists()) {
        const custom = settingsSnap.data().list ?? [];
        availableModules = [...new Set([...DEFAULT_MODULES, ...custom])].sort();
    }

    const savedKey  = `lineup_r${round}`;
    const savedIds  = data[savedKey] ?? data.lineup ?? [];
    activeModule    = data[`module_r${round}`] ?? data.module ?? '4-3-3';

    lineup = savedIds.length
        ? savedIds.map(id => roster.find(p => String(p.id) === String(id))).filter(Boolean)
        : autoFill(roster, activeModule);

    roster.forEach(p => {
        if (!lineup.find(l => String(l.id) === String(p.id))) lineup.push(p);
    });

    renderFormazione(md, isLocked);
}

function renderFormazione(md, isLocked) {
    const s     = lineup.slice(0, 11);
    const b     = lineup.slice(11);
    const warns = validateLineup();
    const { dif, cen, att } = parseModule(activeModule);

    const lockBanner = isLocked ? `
        <div class="form-lock-badge" style="margin:0 20px 14px">
            <span class="material-symbols-outlined">lock</span>
            Formazione bloccata
        </div>` : '';

    const warningsHtml = warns.length && !isLocked ? `
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
                ${isLocked ? `<div style="font-weight:700;font-family:var(--mono)">${activeModule}</div>` : 
                    availableModules.map(m =>
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
                            slotHtml(rp[i] ?? null, row.role, i, isLocked)
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
                ${b.length ? b.map((p, i) => benchRowHtml(p, i, isLocked)).join('') : `<div class="form-bench-empty">Rosa completa schierata</div>`}
            </div>
        </div>`;

    const saveBar = !isLocked ? `
        <div class="form-save-bar">
            <button class="btn-cta" id="btn-save-formazione">
                <span class="material-symbols-outlined">check_circle</span>Salva formazione G${activeRound}
            </button>
        </div>` : '';

    document.getElementById('formation-content').innerHTML = lockBanner + warningsHtml + modulesHtml + fieldHtml + benchHtml + saveBar;
    
    document.getElementById('fround-back')?.addEventListener('click', () => renderRoundList());
    attachFormationEvents(isLocked);
}

function renderEmptyRoster() {
    document.getElementById('formation-content').innerHTML = `
        <button class="fround-back-btn" id="fround-back">
            <span class="material-symbols-outlined">arrow_back</span> Indietro
        </button>
        <div class="empty-state" style="padding-top:40px">
            <span class="material-symbols-outlined">sports_soccer</span>
            <h3>Rosa vuota</h3>
            <p>Acquista almeno 11 giocatori dal Listone</p>
        </div>`;
    document.getElementById('fround-back')?.addEventListener('click', () => renderRoundList());
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

function shortName(name) {
    const parts = name.trim().split(' ');
    return parts.length === 1 ? name : parts[parts.length - 1];
}

// ─── events ───────────────────────────────────────────────────────────────────

function attachFormationEvents(isLocked) {
    document.querySelectorAll('.form-module-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            activeModule = chip.dataset.module;
            lineup = autoFill(roster, activeModule);
            const md = MATCHDAY_SCHEDULE.find(m => m.round === activeRound);
            renderFormazione(md, isLocked, false, false, !isLocked);
        });
    });

    if (!isLocked) {
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
    }

    document.getElementById('btn-save-formazione')?.addEventListener('click', saveFormazione);
}

// ─── picker ───────────────────────────────────────────────────────────────────

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

    const title = context === 'starter' ? `Scegli per lo slot ${role}` : `Scambia con un titolare`;
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
    const md = MATCHDAY_SCHEDULE.find(m => m.round === activeRound);
    renderFormazione(md, false, new Date() < new Date(md?.start ?? '2099-01-01'), false, true);
}

function moveToBench(playerId) {
    const idx = lineup.findIndex(p => String(p?.id) === String(playerId));
    if (idx === -1 || idx >= 11) return;
    const [p] = lineup.splice(idx, 1);
    lineup.push(p);
    const md = MATCHDAY_SCHEDULE.find(m => m.round === activeRound);
    renderFormazione(md, false, new Date() < new Date(md?.start ?? '2099-01-01'), false, true);
}

// ─── save ─────────────────────────────────────────────────────────────────────

async function saveFormazione() {
    if (!window.__user?.uid || !activeRound) return;
    const warns  = validateLineup();
    const errors = warns.filter(w => w.level === 'error');
    if (errors.length) { toast(errors[0].text, 'error'); return; }

    const btn = document.getElementById('btn-save-formazione');
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="material-symbols-outlined">hourglass_empty</span> Salvataggio...'; }

    try {
        await setDoc(doc(db, 'users', window.__user.uid), {
            [`lineup_r${activeRound}`]: lineup.map(p => String(p.id)),
            [`module_r${activeRound}`]: activeModule,
            lineup: lineup.map(p => String(p.id)), // fallback generico aggiornato
            module: activeModule,
        }, { merge: true });
        toast(`Formazione GJ${activeRound} salvata`);
    } catch { toast('Errore salvataggio', 'error'); }
    finally {
        if (btn) { btn.disabled = false; btn.innerHTML = '<span class="material-symbols-outlined">check_circle</span> Salva formazione GJ' + activeRound; }
    }
}

// ─── helpers ──────────────────────────────────────────────────────────────────

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

// ─── admin moduli ─────────────────────────────────────────────────────────────

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