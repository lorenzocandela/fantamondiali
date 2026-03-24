import { db } from './firebase-init.js';
import { doc, getDoc, setDoc } from 'https://www.gstatic.com/firebasejs/10.8.1/firebase-firestore.js';
import { toast } from './utils.js';
import { getCurrentMatchday } from './calendar.js';

const DEFAULT_MODULES = [
    '4-3-3', '4-4-2', '4-2-3-1', '4-3-2-1',
    '3-5-2', '3-4-3', '5-3-2', '5-4-1',
    '4-5-1', '4-1-4-1', '3-6-1',
];

let roster           = [];
let lineup           = [];
let activeModule     = '4-3-3';
let availableModules = [...DEFAULT_MODULES];
let isLocked         = false;
let pickerState      = null;

export async function loadFormazione() {
    if (!window.__user?.uid) return;
    checkLock();

    const [userSnap, settingsSnap] = await Promise.all([
        getDoc(doc(db, 'users', window.__user.uid)),
        getDoc(doc(db, 'settings', 'modules')),
    ]);

    if (!userSnap.exists()) return;
    const data = userSnap.data();

    roster = data.players ?? [];
    if (!roster.length) { renderEmpty(); return; }

    if (settingsSnap.exists()) {
        const custom = settingsSnap.data().list ?? [];
        availableModules = [...new Set([...DEFAULT_MODULES, ...custom])].sort();
    }

    activeModule = data.module ?? '4-3-3';

    const saved = data.lineup ?? [];
    lineup = saved.length
        ? saved.map(id => roster.find(p => String(p.id) === String(id))).filter(Boolean)
        : autoFill(roster, activeModule);

    roster.forEach(p => {
        if (!lineup.find(l => String(l.id) === String(p.id))) lineup.push(p);
    });

    render();
}

function checkLock() {
    const md  = getCurrentMatchday();
    const now = new Date(); now.setHours(0, 0, 0, 0);
    isLocked  = now >= new Date(md.start ?? '2099-01-01') && md.status !== 'upcoming';
}

function parseModule(mod) {
    const parts = mod.split('-').map(Number);
    return { dif: parts[0] ?? 4, cen: parts[1] ?? 3, att: parts[2] ?? 3 };
}

function autoFill(players, mod) {
    const { dif, cen, att } = parseModule(mod);
    const slots = { POR: 1, DIF: dif, CEN: cen, ATT: att };
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

function getStarters() { return lineup.slice(0, 11); }
function getBench()    { return lineup.slice(11); }

function validateLineup() {
    const s = getStarters();
    const { dif, cen, att } = parseModule(activeModule);
    const w = [];
    const pors = s.filter(p => p?.role === 'POR').length;
    const difs = s.filter(p => p?.role === 'DIF').length;
    const cens = s.filter(p => p?.role === 'CEN').length;
    const atts = s.filter(p => p?.role === 'ATT').length;
    const tot  = s.filter(Boolean).length;
    if (pors === 0) w.push({ icon: 'error',   text: 'Nessun portiere schierato',                                  level: 'error' });
    if (pors > 1)   w.push({ icon: 'warning', text: 'Più di un portiere tra i titolari',                         level: 'warn'  });
    if (difs < dif) w.push({ icon: 'warning', text: `Modulo ${activeModule}: servono ${dif} DIF (hai ${difs})`,  level: 'warn'  });
    if (cens < cen) w.push({ icon: 'warning', text: `Modulo ${activeModule}: servono ${cen} CEN (hai ${cens})`,  level: 'warn'  });
    if (atts < att) w.push({ icon: 'warning', text: `Modulo ${activeModule}: servono ${att} ATT (hai ${atts})`,  level: 'warn'  });
    if (tot < 11)   w.push({ icon: 'info',    text: `${tot}/11 titolari schierati`,                               level: 'info'  });
    return w;
}

function render() {
    const s     = getStarters();
    const b     = getBench();
    const warns = validateLineup();
    const { dif, cen, att } = parseModule(activeModule);

    const warningsHtml = warns.length ? `
        <div class="form-warnings">
            ${warns.map(w => `
                <div class="form-warning form-warning-${w.level}">
                    <span class="material-icons-round">${w.icon}</span>${w.text}
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
                        ${Array.from({ length: row.count }).map((_, i) => slotHtml(rp[i] ?? null, row.role, i)).join('')}
                    </div>`;
                }).join('')}
            </div>
        </div>`;

    const benchHtml = `
        <div class="form-bench">
            <div class="form-bench-title">
                <span class="material-icons-round">airline_seat_recline_normal</span>
                Panchina <span class="form-bench-count">${b.length}</span>
            </div>
            <div class="form-bench-list">
                ${b.length
                    ? b.map((p, i) => benchRowHtml(p, i)).join('')
                    : `<div class="form-bench-empty">Tutti i giocatori sono titolari</div>`}
            </div>
        </div>`;

    const saveBar = `
        <div class="form-save-bar">
            ${isLocked ? `<div class="form-lock-badge"><span class="material-icons-round">lock</span>Giornata in corso — formazione bloccata</div>` : ''}
            <button class="btn-cta" id="btn-save-formazione" ${isLocked ? 'disabled' : ''} style="margin-top:0">
                <span class="material-icons-round">check_circle</span>Salva formazione
            </button>
        </div>`;

    document.getElementById('formation-content').innerHTML =
        warningsHtml + modulesHtml + fieldHtml + benchHtml + saveBar;

    attachEvents();
}

function slotHtml(p, role, idx) {
    if (!p) return `
        <div class="form-slot empty" data-role="${role}" data-slot-idx="${idx}">
            <div class="form-slot-inner">
                <span class="material-icons-round form-slot-add-icon">add_circle_outline</span>
                <div class="form-slot-role-label">${role}</div>
            </div>
        </div>`;
    return `
        <div class="form-slot filled ${!isLocked ? 'tappable' : ''}" data-id="${p.id}" data-role="${role}" data-slot-idx="${idx}">
            <div class="form-slot-inner">
                <div class="form-slot-photo-wrap">
                    <img class="form-slot-photo" src="${p.photo ?? ''}" alt="${p.name}"
                        onerror="this.src='https://placehold.co/52x52/1a3a1a/fff?text=${encodeURIComponent(p.name?.[0] ?? '?')}'">
                    <span class="form-slot-role-badge badge-${p.role}">${p.role}</span>
                </div>
                <div class="form-slot-name">${shortName(p.name)}</div>
                ${!isLocked ? `<div class="form-slot-tap-hint"><span class="material-icons-round">swap_vert</span></div>` : ''}
            </div>
        </div>`;
}

function benchRowHtml(p, idx) {
    return `
        <div class="form-bench-row ${!isLocked ? 'tappable' : ''}" data-id="${p.id}" data-bench-idx="${idx}">
            <div class="form-bench-order">${idx + 1}</div>
            <img class="form-bench-photo" src="${p.photo ?? ''}" alt="${p.name}"
                onerror="this.src='https://placehold.co/36x36/f2f2f7/aeaeb2?text=${encodeURIComponent(p.name?.[0] ?? '?')}'">
            <div class="form-bench-info">
                <div class="form-bench-name">${p.name}</div>
                <div class="form-bench-meta">
                    <span class="role-badge badge-${p.role}" style="margin:0">${p.role}</span>
                </div>
            </div>
            ${!isLocked ? `<span class="material-icons-round form-bench-swap-icon">swap_vert</span>` : ''}
        </div>`;
}

function shortName(name) {
    const parts = name.trim().split(' ');
    return parts.length === 1 ? name : parts[parts.length - 1];
}

function attachEvents() {
    document.querySelectorAll('.form-module-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            activeModule = chip.dataset.module;
            lineup = autoFill(roster, activeModule);
            render();
        });
    });

    if (!isLocked) {
        document.querySelectorAll('.form-slot').forEach(slot => {
            slot.addEventListener('click', () => {
                const role    = slot.dataset.role;
                const slotIdx = parseInt(slot.dataset.slotIdx);
                const curId   = slot.dataset.id ?? null;
                openPicker({ role, slotIdx, currentId: curId, context: 'starter' });
            });
        });

        document.querySelectorAll('.form-bench-row').forEach(row => {
            row.addEventListener('click', () => {
                const id       = String(row.dataset.id);
                const benchIdx = parseInt(row.dataset.benchIdx);
                const p        = getBench()[benchIdx];
                if (!p) return;
                openPicker({ role: p.role, currentId: id, benchIdx, context: 'bench' });
            });
        });
    }

    document.getElementById('btn-save-formazione')?.addEventListener('click', saveFormazione);
}

// ─── picker ───────────────────────────────────────────────────────────────────

function openPicker({ role, slotIdx, currentId, benchIdx, context }) {
    pickerState = { role, slotIdx, currentId, benchIdx, context };

    const candidates = context === 'starter'
        ? [...roster].sort((a, b) => {
            if (a.role === role && b.role !== role) return -1;
            if (b.role === role && a.role !== role) return  1;
            return b.price - a.price;
          })
        : getStarters().filter(Boolean).sort((a, b) => {
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
                <span class="material-icons-round">close</span>
            </button>
        </div>
        ${currentId ? `
        <div class="fpicker-actions">
            <button class="fpicker-remove-btn" id="fpicker-remove">
                <span class="material-icons-round">arrow_downward</span>
                Sposta in panchina
            </button>
        </div>` : ''}
        <div class="fpicker-list">
            ${candidates.map(p => {
                const lineupIdx = lineup.findIndex(l => String(l?.id) === String(p.id));
                const isStarter = lineupIdx !== -1 && lineupIdx < 11;
                const isCurrent = String(p.id) === String(currentId ?? '');
                const roleMatch = p.role === role;
                return `
                <div class="fpicker-row ${isCurrent ? 'current' : ''} ${!roleMatch ? 'role-mismatch' : ''}" data-id="${p.id}">
                    <img class="fpicker-photo" src="${p.photo ?? ''}" alt="${p.name}"
                        onerror="this.src='https://placehold.co/44x44/f2f2f7/aeaeb2?text=${encodeURIComponent(p.name?.[0] ?? '?')}'">
                    <div class="fpicker-info">
                        <div class="fpicker-name">${p.name}</div>
                        <div class="fpicker-meta">
                            <span class="role-badge badge-${p.role}" style="margin:0">${p.role}</span>
                            <span class="fpicker-price"><span class="material-icons-round" style="font-size:10px">toll</span>${p.price}</span>
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

    document.getElementById('fpicker-remove')?.addEventListener('click', () => {
        if (currentId) moveToBench(currentId);
        closePicker();
    });

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
    const targetIdx  = lineup.findIndex(p => String(p?.id) === String(targetId));
    const currentIdx = currentId ? lineup.findIndex(p => String(p?.id) === String(currentId)) : -1;
    if (targetIdx === -1) return;
    if (currentIdx !== -1) {
        [lineup[currentIdx], lineup[targetIdx]] = [lineup[targetIdx], lineup[currentIdx]];
    } else {
        // slot vuoto — porta il target tra i titolari in prima posizione libera
        const p = lineup.splice(targetIdx, 1)[0];
        lineup.splice(10, 0, p);
    }
    render();
}

function moveToBench(playerId) {
    const idx = lineup.findIndex(p => String(p?.id) === String(playerId));
    if (idx === -1 || idx >= 11) return;
    const [p] = lineup.splice(idx, 1);
    lineup.push(p);
    render();
}

async function saveFormazione() {
    if (isLocked) { toast('Giornata in corso — formazione bloccata', 'error'); return; }
    if (!window.__user?.uid) return;
    const warns  = validateLineup();
    const errors = warns.filter(w => w.level === 'error');
    if (errors.length) { toast(errors[0].text, 'error'); return; }
    const btn = document.getElementById('btn-save-formazione');
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="material-icons-round">hourglass_empty</span> Salvataggio...'; }
    try {
        await setDoc(doc(db, 'users', window.__user.uid), {
            lineup: lineup.map(p => String(p.id)),
            module: activeModule,
        }, { merge: true });
        toast('Formazione salvata ✓');
    } catch { toast('Errore salvataggio', 'error'); }
    finally {
        if (btn) { btn.disabled = false; btn.innerHTML = '<span class="material-icons-round">check_circle</span> Salva formazione'; }
    }
}

function renderEmpty() {
    document.getElementById('formation-content').innerHTML = `
        <div class="empty-state" style="padding-top:60px">
            <span class="material-icons-round">sports_soccer</span>
            <h3>Rosa vuota</h3>
            <p>Acquista almeno 11 giocatori dal Listone</p>
        </div>`;
}

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
                    <span class="material-icons-round">close</span>
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
    const snap  = await getDoc(doc(db, 'settings', 'modules'));
    const list  = snap.exists() ? (snap.data().list ?? []) : [];
    if (list.includes(val)) { toast('Modulo già presente', 'error'); return; }
    const updated = [...list, val];
    await setDoc(doc(db, 'settings', 'modules'), { list: updated }, { merge: true });
    if (input) input.value = '';
    toast(`Modulo ${val} aggiunto`);
    renderAdminModules(updated);
});