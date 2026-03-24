import { auth, db } from './firebase-init.js';
import { doc, getDoc } from 'https://www.gstatic.com/firebasejs/10.8.1/firebase-firestore.js';
import { toast } from './utils.js';
import { showPage, initTabPill, updateTopbarAvatar, loadProfilo, saveProfilo, toggleJoinCompetition, handleAvatarUpload, handleLogoUpload } from './ui.js';
import { loadListone } from './players.js';
import { addPlayer, loadSquadra, loadCompetizioni } from './squad.js';
import { loadCalendario, renderMatchdayAdmin } from './calendar.js';
import { loadAdminStats, loadSystemSettings, syncAdminUI } from './admin.js';
import { loadFormazione, loadAdminModules } from './formation.js';
import { initNotifications, requestNotificationPermission } from './notifications.js';

window.__addPlayer = (player, customPrice) => addPlayer(player, customPrice);
window.__myTeam    = [];

// pwa

let _pwaPrompt = null;

window.addEventListener('beforeinstallprompt', e => {
    e.preventDefault();
    _pwaPrompt = e;
    updatePwaBtnVisibility();
});

window.addEventListener('appinstalled', () => {
    _pwaPrompt = null;
    updatePwaBtnVisibility();
    toast('App installata!');
});

function updatePwaBtnVisibility() {
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    const visible = !isStandalone && !!_pwaPrompt;
    ['pwa-install-section', 'pwa-install-section-auth'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.style.display = visible ? '' : 'none';
    });
}

async function handlePwaClick() {
    if (!_pwaPrompt) return;
    _pwaPrompt.prompt();
    const { outcome } = await _pwaPrompt.userChoice;
    if (outcome === 'accepted') _pwaPrompt = null;
    updatePwaBtnVisibility();
}

document.getElementById('btn-install-pwa')?.addEventListener('click', handlePwaClick);
document.getElementById('btn-install-pwa-auth')?.addEventListener('click', handlePwaClick);

// navigazione tab

initTabPill();

document.getElementById('nav-listone')?.addEventListener('click', () => showPage('listone'));
document.getElementById('nav-formazione')?.addEventListener('click', () => { 
    showPage('formazione'); 
    loadFormazione(); 
});
document.getElementById('nav-calendario')?.addEventListener('click', () => { 
    showPage('calendario'); 
    loadCalendario();
});
document.getElementById('nav-competizione')?.addEventListener('click', () => { 
    showPage('competizione');
    loadCompetizioni(); 
});
document.getElementById('nav-admin')?.addEventListener('click', () => {
    showPage('admin');
    loadAdminStats();
    syncAdminUI();
    renderMatchdayAdmin();
    loadAdminModules();
});

// avatar → profilo
document.getElementById('btn-profile-avatar')?.addEventListener('click', () => {
    showPage('profilo');
    loadProfilo();
});

// topbar team logo → squadra
document.getElementById('btn-topbar-logo')?.addEventListener('click', () => {
    showPage('squadra');
    loadSquadra();
});

document.addEventListener('goto:profilo', () => {
    showPage('profilo');
    loadProfilo();
});

// profilo form
document.getElementById('btn-save-profilo')?.addEventListener('click', saveProfilo);
document.getElementById('btn-join-comp')?.addEventListener('click', toggleJoinCompetition);
document.getElementById('avatar-upload')?.addEventListener('change', handleAvatarUpload);
document.getElementById('logo-upload')?.addEventListener('change', handleLogoUpload);
document.getElementById('trigger-avatar-upload')?.addEventListener('click', () => document.getElementById('avatar-upload').click());
document.getElementById('trigger-logo-upload')?.addEventListener('click',   () => document.getElementById('logo-upload').click());

// squad bottom sheet
document.getElementById('btn-open-squad')?.addEventListener('click', openSquadSheet);
document.getElementById('btn-close-squad-sheet')?.addEventListener('click', closeSquadSheet);
document.getElementById('squad-sheet-overlay')?.addEventListener('click', e => {
    if (e.target === document.getElementById('squad-sheet-overlay')) closeSquadSheet();
});

function openSquadSheet() {
    const overlay = document.getElementById('squad-sheet-overlay');
    if (!overlay) return;
    renderSquadSheet();
    overlay.classList.remove('hidden');
    requestAnimationFrame(() => overlay.classList.add('open'));
}

function closeSquadSheet() {
    const overlay = document.getElementById('squad-sheet-overlay');
    overlay.classList.remove('open');
    overlay.addEventListener('transitionend', () => overlay.classList.add('hidden'), { once: true });
}

function renderSquadSheet() {
    const team    = window.__myTeam ?? [];
    const credits = window.__user?.credits ?? 500;
    const spent   = 500 - credits;

    const countEl = document.getElementById('sheet-stat-count');
    const spentEl = document.getElementById('sheet-stat-spent');
    const credEl  = document.getElementById('sheet-stat-credits');
    if (countEl) countEl.textContent = team.length;
    if (spentEl) spentEl.textContent = spent;
    if (credEl)  credEl.textContent  = credits;

    const list = document.getElementById('squad-sheet-list');
    if (!list) return;

    if (!team.length) {
        list.innerHTML = `<div class="empty-state" style="padding:40px 20px">
            <span class="material-symbols-outlined">sports_soccer</span>
            <h3>Rosa vuota</h3>
            <p>Acquista giocatori dal Listone</p>
        </div>`;
        return;
    }

    const grouped = { POR: [], DIF: [], CEN: [], ATT: [] };
    team.forEach(p => (grouped[p.role] ?? grouped.CEN).push(p));

    list.innerHTML = Object.entries(grouped).map(([role, players]) => {
        if (!players.length) return '';
        return `
        <div class="squad-sheet-group">
            <div class="squad-sheet-role">${role} · ${players.length}</div>
            ${players.map(p => `
            <div class="squad-sheet-row">
                <img class="squad-sheet-photo" src="${p.photo ?? ''}" alt="${p.name}"
                    onerror="this.src='https://placehold.co/38x38/f2f2f7/aeaeb2?text=${encodeURIComponent(p.name?.[0] ?? '?')}'">
                <div class="squad-sheet-info">
                    <div class="squad-sheet-name">${p.name}</div>
                    <div class="squad-sheet-meta">${p.team ?? ''} · ${p.nationality ?? ''}</div>
                </div>
                <span class="squad-sheet-price">
                    <span class="material-symbols-outlined">toll</span>${p.price}
                </span>
            </div>`).join('')}
        </div>`;
    }).join('');
}

// init

document.addEventListener('app:ready', async e => {
    const user = e.detail;
    updateTopbarAvatar();

    const userSnap = await getDoc(doc(db, 'users', user.uid));
    if (userSnap.exists() && userSnap.data().role === 'admin') {
        document.getElementById('nav-admin').classList.remove('hidden');
    }

    await loadSystemSettings();
    syncAdminUI();
    await Promise.all([loadListone(), loadSquadra()]);

    // notifiche push
    await initNotifications();
    // chiedi permesso dopo 3 secondi se non ancora concesso
    if ('Notification' in window && Notification.permission === 'default') {
        setTimeout(() => requestNotificationPermission(), 3000);
    }

    // aggiorna meta rosa nel profilo
    updateProfiloSquadMeta();
});

export function updateProfiloSquadMeta() {
    const meta = document.getElementById('profilo-squad-meta');
    if (!meta) return;
    const count   = (window.__myTeam ?? []).length;
    const credits = window.__user?.credits ?? 500;
    meta.textContent = `${count} giocatori · ${credits} crediti`;
}