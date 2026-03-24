import { db } from './firebase-init.js';
import { doc, getDoc, setDoc } from 'https://www.gstatic.com/firebasejs/10.8.1/firebase-firestore.js';
import { toast } from './utils.js';
import { getCurrentMatchday } from './calendar.js';

// moduli predefiniti: [DIF, CEN, ATT]
const DEFAULT_MODULES = [
    '4-3-3', '4-4-2', '4-2-3-1', '4-3-2-1',
    '3-5-2', '3-4-3', '5-3-2', '5-4-1',
    '4-5-1', '4-1-4-1', '3-6-1',
];

const ROLE_ORDER = ['POR', 'DIF', 'CEN', 'ATT'];

// stato locale
let lineup      = [];   // array di player objects, slot 0-10 = titolari, resto = panchina
let activeModule = '4-3-3';
let availableModules = [...DEFAULT_MODULES];
let isLocked    = false;
let dragSrc     = null;

export async function loadFormazione() {
    if (!window.__user?.uid) return;

    checkLock();

    const [userSnap, settingsSnap] = await Promise.all([
        getDoc(doc(db, 'users', window.__user.uid)),
        getDoc(doc(db, 'settings', 'modules')),
    ]);

    if (!userSnap.exists()) return;
    const data = userSnap.data();

    const roster = data.players ?? [];
    if (!roster.length) {
        renderEmptyFormazione();
        return;
    }

    // carica moduli custom da settings
    if (settingsSnap.exists()) {
        const custom = settingsSnap.data().list ?? [];
        availableModules = [...new Set([...DEFAULT_MODULES, ...custom])].sort();
    }

    const saved = data.lineup ?? [];
    if (saved.length) {
        lineup = saved.map(id => roster.find(p => String(p.id) === String(id)) ?? null).filter(Boolean);
    } else {
        lineup = [...roster];
    }

    activeModule = data.module ?? '4-3-3';

    renderFormazione(roster);
}

function checkLock() {
    const md  = getCurrentMatchday();
    const now = new Date();
    now.setHours(0, 0, 0, 0);
    const start = new Date(md.start ?? '2099-01-01');
    isLocked = now >= start && md.status !== 'upcoming';
}

function parseModule(mod) {
    const parts = mod.split('-').map(Number);
    return { dif: parts[0] ?? 4, cen: parts[1] ?? 3, att: parts[2] ?? 3 };
}

function validateLineup(starters) {
    const warnings = [];
    const pors = starters.filter(p => p?.role === 'POR').length;
    const difs = starters.filter(p => p?.role === 'DIF').length;
    const cens = starters.filter(p => p?.role === 'CEN').length;
    const atts = starters.filter(p => p?.role === 'ATT').length;
    const { dif, cen, att } = parseModule(activeModule);

    if (pors === 0) warnings.push({ icon: 'error', text: 'Nessun portiere schierato', level: 'error' });
    if (pors > 1)  warnings.push({ icon: 'warning', text: 'Più di un portiere tra i titolari', level: 'warn' });
    if (difs < dif) warnings.push({ icon: 'warning', text: `Il modulo ${activeModule} richiede ${dif} difensori (hai ${difs})`, level: 'warn' });
    if (cens < cen) warnings.push({ icon: 'warning', text: `Il modulo ${activeModule} richiede ${cen} centrocampisti (hai ${cens})`, level: 'warn' });
    if (atts < att) warnings.push({ icon: 'warning', text: `Il modulo ${activeModule} richiede ${att} attaccanti (hai ${atts})`, level: 'warn' });
    if (starters.filter(Boolean).length < 11) warnings.push({ icon: 'info', text: `Hai solo ${starters.filter(Boolean).length} titolari su 11`, level: 'info' });

    return warnings;
}

function renderEmptyFormazione() {
    document.getElementById('formation-content').innerHTML = `
        <div class="empty-state" style="padding-top:60px">
            <span class="material-icons-round">sports_soccer</span>
            <h3>Rosa vuota</h3>
            <p>Acquista almeno 11 giocatori dal Listone per schierare la formazione</p>
        </div>`;
}

function renderFormazione(roster) {
    const starters = lineup.slice(0, 11);
    const bench    = lineup.slice(11);
    const warnings = validateLineup(starters);
    const { dif, cen, att } = parseModule(activeModule);

    // warnings bar
    const warningsHtml = warnings.length ? `
        <div class="form-warnings">
            ${warnings.map(w => `
                <div class="form-warning form-warning-${w.level}">
                    <span class="material-icons-round">${w.icon}</span>
                    ${w.text}
                </div>`).join('')}
        </div>` : '';

    // moduli selector
    const modulesHtml = `
        <div class="form-module-row">
            <div class="form-module-label">Modulo</div>
            <div class="form-module-chips">
                ${availableModules.map(m => `
                    <button class="form-module-chip ${m === activeModule ? 'active' : ''}" data-module="${m}">${m}</button>
                `).join('')}
            </div>
        </div>`;

    // campo — righe per ruolo
    const rows = [
        { role: 'POR', players: starters.filter(p => p?.role === 'POR'), slots: 1 },
        { role: 'DIF', players: starters.filter(p => p?.role === 'DIF'), slots: dif },
        { role: 'CEN', players: starters.filter(p => p?.role === 'CEN'), slots: cen },
        { role: 'ATT', players: starters.filter(p => p?.role === 'ATT'), slots: att },
    ];

    const fieldHtml = `
        <div class="form-field">
            <div class="form-field-grass">
                ${rows.map(row => `
                    <div class="form-field-row">
                        ${Array.from({ length: row.slots }).map((_, i) => {
                            const p = row.players[i] ?? null;
                            return playerSlotHtml(p, row.role, i, true);
                        }).join('')}
                    </div>`).join('')}
            </div>
        </div>`;

    // panchina
    const benchHtml = `
        <div class="form-bench">
            <div class="form-bench-title">
                <span class="material-icons-round">airline_seat_recline_normal</span>
                Panchina <span class="form-bench-count">${bench.length}</span>
            </div>
            <div class="form-bench-list" id="form-bench-list">
                ${bench.map((p, i) => benchPlayerHtml(p, i)).join('')}
                ${bench.length === 0 ? `<div class="form-bench-empty">Nessun giocatore in panchina</div>` : ''}
            </div>
        </div>`;

    // save bar
    const lockBadge = isLocked
        ? `<div class="form-lock-badge"><span class="material-icons-round">lock</span>Giornata iniziata</div>`
        : '';

    const saveBtnHtml = `
        <div class="form-save-bar">
            ${lockBadge}
            <button class="btn-cta" id="btn-save-formazione" ${isLocked ? 'disabled' : ''} style="margin-top:0">
                <span class="material-icons-round">save</span>
                Salva formazione
            </button>
        </div>`;

    document.getElementById('formation-content').innerHTML =
        warningsHtml + modulesHtml + fieldHtml + benchHtml + saveBtnHtml;

    attachFormationEvents(roster);
}

function playerSlotHtml(p, role, idx, isStarter) {
    if (!p) {
        return `
            <div class="form-slot empty" data-role="${role}" data-idx="${idx}" data-starter="${isStarter}">
                <div class="form-slot-empty-icon">
                    <span class="material-icons-round">add</span>
                </div>
                <div class="form-slot-role">${role}</div>
            </div>`;
    }
    return `
        <div class="form-slot filled" data-id="${p.id}" draggable="${!isLocked}" data-starter="true">
            <div class="form-slot-photo-wrap">
                <img class="form-slot-photo" src="${p.photo ?? ''}" alt="${p.name}"
                    onerror="this.src='https://placehold.co/48x48/1a1a2e/fff?text=${encodeURIComponent(p.name?.[0] ?? '?')}'">
                <div class="form-slot-role-badge badge-${p.role}">${p.role}</div>
            </div>
            <div class="form-slot-name">${shortName(p.name)}</div>
            ${!isLocked ? `<button class="form-slot-remove" data-id="${p.id}" title="Sposta in panchina">
                <span class="material-icons-round">keyboard_arrow_down</span>
            </button>` : ''}
        </div>`;
}

function benchPlayerHtml(p, idx) {
    return `
        <div class="form-bench-row" data-id="${p.id}" draggable="${!isLocked}">
            <div class="form-bench-order">${idx + 1}</div>
            <img class="form-bench-photo" src="${p.photo ?? ''}" alt="${p.name}"
                onerror="this.src='https://placehold.co/36x36/f2f2f7/aeaeb2?text=${encodeURIComponent(p.name?.[0] ?? '?')}'">
            <div class="form-bench-info">
                <div class="form-bench-name">${p.name}</div>
                <div class="form-bench-meta"><span class="role-badge badge-${p.role}" style="margin:0">${p.role}</span></div>
            </div>
            ${!isLocked ? `
                <div class="form-bench-actions">
                    <button class="form-bench-promote" data-id="${p.id}" title="Promuovi a titolare">
                        <span class="material-icons-round">keyboard_arrow_up</span>
                    </button>
                    <button class="form-bench-up" data-idx="${idx}" title="Su" ${idx === 0 ? 'disabled' : ''}>
                        <span class="material-icons-round">expand_less</span>
                    </button>
                    <button class="form-bench-down" data-idx="${idx}" title="Giù">
                        <span class="material-icons-round">expand_more</span>
                    </button>
                </div>` : ''}
        </div>`;
}

function shortName(name) {
    const parts = name.trim().split(' ');
    if (parts.length === 1) return name;
    return parts[parts.length - 1]; // cognome
}

function attachFormationEvents(roster) {
    // modulo chips
    document.querySelectorAll('.form-module-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            activeModule = chip.dataset.module;
            renderFormazione(roster);
        });
    });

    // rimuovi titolare → panchina
    document.querySelectorAll('.form-slot-remove').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = String(btn.dataset.id);
            const idx = lineup.findIndex(p => String(p?.id) === id);
            if (idx === -1 || idx >= 11) return;
            // sposta in fondo alla panchina
            const [p] = lineup.splice(idx, 1);
            lineup.push(p);
            renderFormazione(roster);
        });
    });

    // promuovi panchina → titolari
    document.querySelectorAll('.form-bench-promote').forEach(btn => {
        btn.addEventListener('click', () => {
            const id  = String(btn.dataset.id);
            const idx = lineup.findIndex(p => String(p?.id) === id);
            if (idx < 11) return;
            // trova primo slot libero tra titolari per questo ruolo
            const p    = lineup[idx];
            const starters = lineup.slice(0, 11);
            const { dif, cen, att } = parseModule(activeModule);
            const roleSlots = { POR: 1, DIF: dif, CEN: cen, ATT: att };
            const currentCount = starters.filter(s => s?.role === p.role).length;

            if (currentCount < (roleSlots[p.role] ?? 0) && starters.filter(Boolean).length < 11) {
                lineup.splice(idx, 1);
                // inserisci dopo gli ultimi del suo ruolo tra i titolari
                const lastRoleIdx = [...starters].reverse().findIndex(s => s?.role === p.role);
                const insertAt = lastRoleIdx === -1 ? starters.filter(Boolean).length : (10 - lastRoleIdx + 1);
                lineup.splice(Math.min(insertAt, 10), 0, p);
            } else {
                // nessuno slot disponibile per ruolo, prende il posto dell'ultimo titolare
                if (starters.filter(Boolean).length >= 11) {
                    toast(`Slot ${p.role} esauriti nel modulo ${activeModule} — rimuovi un titolare prima`, 'error');
                    return;
                }
                lineup.splice(idx, 1);
                lineup.splice(10, 0, p);
            }
            renderFormazione(roster);
        });
    });

    // ordine panchina su/giù
    document.querySelectorAll('.form-bench-up').forEach(btn => {
        btn.addEventListener('click', () => {
            const i = parseInt(btn.dataset.idx);
            const bi = i + 11;
            if (bi <= 11) return;
            [lineup[bi - 1], lineup[bi]] = [lineup[bi], lineup[bi - 1]];
            renderFormazione(roster);
        });
    });

    document.querySelectorAll('.form-bench-down').forEach(btn => {
        btn.addEventListener('click', () => {
            const i  = parseInt(btn.dataset.idx);
            const bi = i + 11;
            if (bi >= lineup.length - 1) return;
            [lineup[bi], lineup[bi + 1]] = [lineup[bi + 1], lineup[bi]];
            renderFormazione(roster);
        });
    });

    // drag & drop tra slot campo e panchina
    if (!isLocked) attachDragDrop(roster);

    // salva
    document.getElementById('btn-save-formazione')?.addEventListener('click', () => saveFormazione());
}

function attachDragDrop(roster) {
    const draggables = document.querySelectorAll('[draggable="true"]');

    draggables.forEach(el => {
        el.addEventListener('dragstart', e => {
            dragSrc = el;
            e.dataTransfer.effectAllowed = 'move';
            el.classList.add('dragging');
        });
        el.addEventListener('dragend', () => {
            dragSrc = null;
            el.classList.remove('dragging');
            document.querySelectorAll('.drag-over').forEach(d => d.classList.remove('drag-over'));
        });
        el.addEventListener('dragover', e => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            el.classList.add('drag-over');
        });
        el.addEventListener('dragleave', () => el.classList.remove('drag-over'));
        el.addEventListener('drop', e => {
            e.preventDefault();
            el.classList.remove('drag-over');
            if (!dragSrc || dragSrc === el) return;

            const srcId  = dragSrc.dataset.id;
            const destId = el.dataset.id;
            if (!srcId || !destId) return;

            const srcIdx  = lineup.findIndex(p => String(p?.id) === String(srcId));
            const destIdx = lineup.findIndex(p => String(p?.id) === String(destId));
            if (srcIdx === -1 || destIdx === -1) return;

            // swap
            [lineup[srcIdx], lineup[destIdx]] = [lineup[destIdx], lineup[srcIdx]];
            renderFormazione(roster);
        });
    });
}

async function saveFormazione() {
    if (isLocked) { toast('Formazione bloccata — giornata già iniziata', 'error'); return; }
    if (!window.__user?.uid) return;

    const warnings = validateLineup(lineup.slice(0, 11));
    const errors   = warnings.filter(w => w.level === 'error');
    if (errors.length) {
        toast(errors[0].text, 'error');
        return;
    }

    const btn = document.getElementById('btn-save-formazione');
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="material-icons-round">hourglass_empty</span> Salvataggio...'; }

    try {
        await setDoc(doc(db, 'users', window.__user.uid), {
            lineup: lineup.map(p => String(p.id)),
            module: activeModule,
        }, { merge: true });
        toast('Formazione salvata');
    } catch { toast('Errore salvataggio', 'error'); }
    finally {
        if (btn) { btn.disabled = false; btn.innerHTML = '<span class="material-icons-round">save</span> Salva formazione'; }
    }
}

// admin — gestione moduli custom

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
        : `<div style="font-size:12px;color:var(--text-3)">Nessun modulo custom</div>`;

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

    const valid = /^\d+-\d+-\d+(-\d+)?$/.test(val);
    if (!valid) { toast('Formato non valido — es. 4-3-3 o 4-2-3-1', 'error'); return; }

    const snap = await getDoc(doc(db, 'settings', 'modules'));
    const list = snap.exists() ? (snap.data().list ?? []) : [];
    if (list.includes(val)) { toast('Modulo già presente', 'error'); return; }

    const updated = [...list, val];
    await setDoc(doc(db, 'settings', 'modules'), { list: updated }, { merge: true });
    if (input) input.value = '';
    toast(`Modulo ${val} aggiunto`);
    renderAdminModules(updated);
});