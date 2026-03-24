import { db } from './firebase-init.js';
import { doc, getDoc, setDoc } from 'https://www.gstatic.com/firebasejs/10.8.1/firebase-firestore.js';
import { toast } from './utils.js';

// tab pill

export function initTabPill() {
    const bar = document.querySelector('.tab-bar');
    if (!bar) return;
    const pill = document.createElement('div');
    pill.className = 'tab-pill';
    bar.appendChild(pill);

    function move(btn) {
        const br = bar.getBoundingClientRect();
        const tr = btn.getBoundingClientRect();
        pill.style.left   = (tr.left - br.left) + 'px';
        pill.style.width  = tr.width + 'px';
        pill.style.height = tr.height + 'px';
    }

    const active = bar.querySelector('.tab-item.active');
    if (active) {
        pill.style.transition = 'none';
        move(active);
        requestAnimationFrame(() => { pill.style.transition = ''; });
    }

    bar.addEventListener('click', e => {
        const btn = e.target.closest('.tab-item');
        if (btn && !btn.classList.contains('hidden')) move(btn);
    });

    window.addEventListener('resize', () => {
        const a = bar.querySelector('.tab-item.active');
        if (a) { pill.style.transition = 'none'; move(a); requestAnimationFrame(() => { pill.style.transition = ''; }); }
    }, { passive: true });
}

// page navigation

export function showPage(name) {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active', 'page-enter'));
    document.querySelectorAll('.tab-item').forEach(t => t.classList.remove('active'));

    const page = document.getElementById(`page-${name}`);
    const tab  = document.getElementById(`nav-${name}`);

    if (!page) return;
    page.classList.add('active', 'page-enter');
    page.addEventListener('animationend', () => page.classList.remove('page-enter'), { once: true });

    if (tab) tab.classList.add('active');

    const bar  = document.querySelector('.tab-bar');
    const pill = bar?.querySelector('.tab-pill');
    if (pill && tab && !tab.classList.contains('hidden')) {
        const br = bar.getBoundingClientRect();
        const tr = tab.getBoundingClientRect();
        pill.style.left   = (tr.left - br.left) + 'px';
        pill.style.width  = tr.width + 'px';
        pill.style.height = tr.height + 'px';
    }
}

// topbar avatar

export function updateTopbarAvatar() {
    const user     = window.__user ?? {};
    const img      = document.getElementById('topbar-avatar-img');
    const initials = document.getElementById('topbar-avatar-initials');
    if (!img || !initials) return;

    if (user.avatar) {
        img.src = user.avatar;
        img.classList.remove('hidden');
        initials.classList.add('hidden');
    } else {
        img.classList.add('hidden');
        initials.classList.remove('hidden');
        const raw = user.team_name ?? user.email ?? '?';
        initials.textContent = raw.split(/[\s@]+/).map(w => w[0]).join('').slice(0, 2).toUpperCase();
    }
}

// profilo hero card

export function renderProfiloHero() {
    const user     = window.__user ?? {};
    const teamName = user.team_name || 'La mia squadra';
    const initials = teamName.split(/\s+/).map(w => w[0]).join('').slice(0, 2).toUpperCase();
    const isJoined = user.competition_joined ?? false;

    const oldSection = document.querySelector('.profilo-avatar-section');
    if (oldSection) oldSection.style.display = 'none';

    let hero = document.getElementById('profilo-hero-card');
    if (!hero) {
        hero = document.createElement('div');
        hero.id        = 'profilo-hero-card';
        hero.className = 'profilo-hero';
        const first = document.querySelector('#page-profilo .profilo-section');
        if (first) first.before(hero);
    }

    const avatarHtml = user.avatar
        ? `<img class="profilo-hero-avatar" src="${user.avatar}" alt="">`
        : `<div class="profilo-hero-initials">${initials}</div>`;

    const logoHtml = user.team_logo
        ? `<img class="profilo-hero-logo-img" src="${user.team_logo}" alt="logo">`
        : `<div class="profilo-hero-logo-placeholder">${initials}</div>`;

    hero.innerHTML = `
        <div class="profilo-hero-avatar-wrap" id="trigger-avatar-upload-hero">
            ${avatarHtml}
            <div class="profilo-avatar-overlay">
                <span class="material-icons-round">photo_camera</span>
            </div>
        </div>
        <div class="profilo-hero-info">
            <div class="profilo-hero-team">${teamName}</div>
            <div class="profilo-hero-email">${user.email ?? ''}</div>
            <div class="profilo-hero-badge ${isJoined ? 'active' : ''}">
                <span class="material-icons-round">${isJoined ? 'emoji_events' : 'sports_soccer'}</span>
                ${isJoined ? 'Iscritto' : 'Non iscritto'}
            </div>
        </div>
        ${logoHtml}
    `;

    document.getElementById('trigger-avatar-upload-hero')?.addEventListener('click', () => {
        document.getElementById('avatar-upload').click();
    });
}

// load / save profilo

export async function loadProfilo() {
    if (!window.__user?.uid) return;
    const snap = await getDoc(doc(db, 'users', window.__user.uid));
    if (!snap.exists()) return;
    const data = snap.data();

    document.getElementById('profilo-email')?.remove(); // legacy, shown in hero
    document.getElementById('profilo-team-name-in').value = data.team_name ?? '';
    document.getElementById('profilo-joined-status').className =
        data.competition_joined ? 'joined-badge active' : 'joined-badge';
    document.getElementById('profilo-joined-status').textContent =
        data.competition_joined ? 'Iscritto alla competizione' : 'Non iscritto';
    document.getElementById('btn-join-comp').textContent =
        data.competition_joined ? 'Ritira iscrizione' : 'Iscriviti alla competizione';

    if (data.team_logo) {
        const prev = document.getElementById('profilo-logo-preview');
        prev.src = data.team_logo;
        prev.classList.remove('hidden');
    }

    window.__user = { ...window.__user, ...data };
    renderProfiloHero();
}

export async function saveProfilo() {
    const teamName = document.getElementById('profilo-team-name-in').value.trim();
    if (!teamName || !window.__user?.uid) return;
    const btn = document.getElementById('btn-save-profilo');
    btn.disabled    = true;
    btn.textContent = 'Salvataggio...';
    try {
        await setDoc(doc(db, 'users', window.__user.uid), { team_name: teamName }, { merge: true });
        window.__user.team_name = teamName;
        document.getElementById('squad-team-name').textContent = teamName;
        updateTopbarAvatar();
        renderProfiloHero();
        toast('Profilo aggiornato');
    } catch { toast('Errore salvataggio', 'error'); }
    finally { btn.disabled = false; btn.textContent = 'Salva modifiche'; }
}

export async function toggleJoinCompetition() {
    if (!window.__user?.uid) return;
    try {
        const snap   = await getDoc(doc(db, 'users', window.__user.uid));
        const data   = snap.exists() ? snap.data() : {};
        const joined = !data?.competition_joined;
        const name   = document.getElementById('profilo-team-name-in').value.trim() || data?.team_name || 'Squadra';
        await setDoc(doc(db, 'users', window.__user.uid), { competition_joined: joined, team_name: name }, { merge: true });
        window.__user.competition_joined = joined;
        document.getElementById('profilo-joined-status').className = joined ? 'joined-badge active' : 'joined-badge';
        document.getElementById('profilo-joined-status').textContent = joined ? 'Iscritto alla competizione' : 'Non iscritto';
        document.getElementById('btn-join-comp').textContent = joined ? 'Ritira iscrizione' : 'Iscriviti alla competizione';
        renderProfiloHero();
        toast(joined ? 'Iscrizione completata' : 'Iscrizione ritirata');
    } catch { toast('Errore di connessione a Firebase', 'error'); }
}

export function handleAvatarUpload(e) {
    const file = e.target.files[0];
    if (!file || !window.__user?.uid) return;
    if (file.size > 500 * 1024) { toast('Immagine troppo grande (max 500KB)', 'error'); return; }
    const reader = new FileReader();
    reader.onload = async ev => {
        try {
            await setDoc(doc(db, 'users', window.__user.uid), { avatar: ev.target.result }, { merge: true });
            window.__user.avatar = ev.target.result;
            updateTopbarAvatar();
            renderProfiloHero();
            toast('Foto profilo aggiornata');
        } catch { toast('Errore upload', 'error'); }
    };
    reader.readAsDataURL(file);
}

export function handleLogoUpload(e) {
    const file = e.target.files[0];
    if (!file || !window.__user?.uid) return;
    if (file.size > 300 * 1024) { toast('Logo troppo grande (max 300KB)', 'error'); return; }
    const reader = new FileReader();
    reader.onload = async ev => {
        try {
            await setDoc(doc(db, 'users', window.__user.uid), { team_logo: ev.target.result }, { merge: true });
            window.__user.team_logo = ev.target.result;
            const prev = document.getElementById('profilo-logo-preview');
            prev.src = ev.target.result;
            prev.classList.remove('hidden');
            renderProfiloHero();
            toast('Logo squadra aggiornato');
        } catch { toast('Errore upload logo', 'error'); }
    };
    reader.readAsDataURL(file);
}