import { auth, db } from './firebase-init.js';
import { doc, getDoc } from 'https://www.gstatic.com/firebasejs/10.8.1/firebase-firestore.js';
import { toast } from './utils.js';
import { showPage, initTabPill, updateTopbarAvatar, loadProfilo, saveProfilo, toggleJoinCompetition, handleAvatarUpload, handleLogoUpload } from './ui.js';
import { loadListone, renderPlayers, getFiltered, displayCount } from './players.js';
import { addPlayer, loadSquadra, loadCompetizioni } from './squad.js';
import { loadCalendario, renderMatchdayAdmin } from './calendar.js';
import { loadAdminStats, loadSystemSettings, syncAdminUI } from './admin.js';
import { loadFormazione, loadAdminModules } from './formation.js';

window.__addPlayer = addPlayer;
window.__myTeam    = [];

// pwa

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(() => {});
}

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
    const visible      = !isStandalone && !!_pwaPrompt;
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

// navigazione

initTabPill();

document.getElementById('nav-listone')?.addEventListener('click',      () => showPage('listone'));
document.getElementById('nav-formazione')?.addEventListener('click',   () => { showPage('formazione'); loadFormazione(); });
document.getElementById('nav-calendario')?.addEventListener('click',   () => { showPage('calendario'); loadCalendario(); });
document.getElementById('nav-competizioni')?.addEventListener('click', () => { showPage('competizioni'); loadCompetizioni(); });
document.getElementById('nav-profilo')?.addEventListener('click',      () => { showPage('profilo'); loadProfilo(); });
document.getElementById('nav-admin')?.addEventListener('click', () => {
    showPage('admin');
    loadAdminStats();
    syncAdminUI();
    renderMatchdayAdmin();
    loadAdminModules();
});

document.getElementById('btn-profile-avatar')?.addEventListener('click', () => {
    showPage('profilo');
    loadProfilo();
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

// init app

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
});