import { auth, db } from "./firebase-init.js";
import {
    doc,
    getDoc,
    updateDoc,
    setDoc,
    arrayUnion,
    arrayRemove,
    collection,
    getDocs
} from "https://www.gstatic.com/firebasejs/10.8.1/firebase-firestore.js";

let COMPETITION_ACTIVE = false;
const PAGE_SIZE          = 25;

const playersGrid  = document.getElementById('players-grid');
const squadList    = document.getElementById('squad-list');
const toastWrap    = document.getElementById('toast-wrap');
const modalOverlay = document.getElementById('modal-overlay');
const searchIn     = document.getElementById('search-in');

let allPlayers   = [];
let myTeam       = [];
let activeRole   = 'ALL';
let activeNations = [];
let activeView   = 'grid';
let displayCount = PAGE_SIZE;
let apiSource    = '';
let scrollSentinel;

function toast(msg, type = 'success') {
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.innerHTML = `<span class="material-icons-round">${type === 'success' ? 'check_circle' : 'error_outline'}</span>${msg}`;
    toastWrap.appendChild(el);
    setTimeout(() => {
        el.style.animation = 'toastOut 0.25s ease forwards';
        el.addEventListener('animationend', () => el.remove());
    }, 2800);
}

function updateCreditsDisplay(credits) {
    document.getElementById('credits-val').textContent = credits;
    document.getElementById('stat-credits').textContent = credits;
    if (window.__user) window.__user.credits = credits;
}

function isOwned(playerId) {
    return myTeam.some(p => p.id === playerId || p.id === String(playerId));
}

function getFiltered() {
    const q = searchIn.value.trim().toLowerCase();
    return allPlayers.filter(p => {
        const matchRole   = activeRole   === 'ALL' || p.role        === activeRole;
        const matchNation = activeNations.length === 0 || activeNations.includes(p.nationality);
        const matchQ      = !q || p.name.toLowerCase().includes(q) || p.nationality.toLowerCase().includes(q);
        return matchRole && matchNation && matchQ;
    });
}

function buildSubtitle() {
    const parts = [];
    if (allPlayers.length) parts.push(`${allPlayers.length} giocatori`);
    if (!COMPETITION_ACTIVE) parts.push('mercato chiuso');
    if (apiSource) parts.push(apiSource);
    document.getElementById('listone-subtitle').textContent = parts.join(' · ');
}

function populateNationFilter(players) {
    const container = document.getElementById('nation-filter-row');
    const nations = [...new Set(players.map(p => p.nationality))].filter(Boolean).sort();

    container.innerHTML = `
        <button class="chip active" data-nation="ALL">Tutte</button>
        ${nations.map(nat => `<button class="chip" data-nation="${nat}">${nat}</button>`).join('')}
    `;

    const chips = container.querySelectorAll('.chip');
    
    chips.forEach(chip => {
        chip.addEventListener('click', () => {
            const nat = chip.dataset.nation;

            if (nat === 'ALL') {
                activeNations = [];
                chips.forEach(c => c.classList.remove('active'));
                chip.classList.add('active');
            } else {
                container.querySelector('[data-nation="ALL"]').classList.remove('active');

                if (activeNations.includes(nat)) {
                    activeNations = activeNations.filter(n => n !== nat);
                    chip.classList.remove('active');
                } else {
                    activeNations.push(nat);
                    chip.classList.add('active');
                }

                if (activeNations.length === 0) {
                    container.querySelector('[data-nation="ALL"]').classList.add('active');
                }
            }

            displayCount = PAGE_SIZE;
            renderPlayers(getFiltered(), displayCount);
        });
    });
}

function showSkeletons(n = PAGE_SIZE) {
    if (activeView === 'grid') {
        playersGrid.className = 'players-grid';
        playersGrid.innerHTML = Array.from({ length: n }, () => `
            <div class="skel-card skeleton">
                <div class="skel-circle"></div>
                <div class="skel-line" style="width:70%"></div>
                <div class="skel-line" style="width:48%"></div>
                <div class="skel-btn"></div>
            </div>
        `).join('');
    } else {
        playersGrid.className = 'players-list';
        playersGrid.innerHTML = Array.from({ length: n }, () => `
            <div class="skel-list-row skeleton">
                <div class="skel-circle" style="width:42px;height:42px"></div>
                <div style="flex:1;display:flex;flex-direction:column;gap:6px">
                    <div class="skel-line" style="width:55%"></div>
                    <div class="skel-line" style="width:35%"></div>
                </div>
                <div class="skel-btn" style="width:70px;height:30px"></div>
            </div>
        `).join('');
    }
}

function renderPlayers(players, slice) {
    const visible = players.slice(0, slice);

    if (!visible.length) {
        playersGrid.className = activeView === 'grid' ? 'players-grid' : 'players-list';
        playersGrid.innerHTML = `
            <div class="empty-state" style="grid-column:1/-1">
                <span class="material-icons-round">search_off</span>
                <h3>Nessun risultato</h3>
                <p>Prova con un altro filtro o ricerca</p>
            </div>`;
        return;
    }

    if (activeView === 'grid') {
        playersGrid.className = 'players-grid';
        playersGrid.innerHTML = visible.map(p => {
            const owned = isOwned(p.id);
            const locked = !COMPETITION_ACTIVE;
            return `
            <div class="player-card" data-role="${p.role}" data-id="${p.id}" tabindex="0">
                <div class="player-photo-wrap">
                    <img class="player-photo" src="${p.photo}" alt="${p.name}" onerror="this.src='https://placehold.co/80x80/f2f2f7/aeaeb2?text=${encodeURIComponent(p.lastname?.[0] ?? '?')}'">
                    ${p.team_logo ? `<img class="team-logo" src="${p.team_logo}" alt="" onerror="this.style.display='none'">` : ''}
                </div>
                <div class="role-badge badge-${p.role}">${p.role}</div>
                <div class="player-name">${p.name}</div>
                <div class="player-nat">${p.nationality}</div>
                <div class="price-row">
                    <span class="material-icons-round">toll</span>${p.price}
                </div>
                <button class="btn-add ${locked ? 'locked' : owned ? 'owned' : ''}" data-id="${p.id}">
                    <span class="material-icons-round">${locked ? 'lock' : owned ? 'check' : 'add'}</span>
                    ${locked ? 'Non attivo' : owned ? 'In rosa' : 'Aggiungi'}
                </button>
            </div>`;
        }).join('');
    } else {
        playersGrid.className = 'players-list';
        playersGrid.innerHTML = visible.map(p => {
            const owned = isOwned(p.id);
            const locked = !COMPETITION_ACTIVE;
            return `
            <div class="player-row" data-role="${p.role}" data-id="${p.id}" tabindex="0">
                <div class="player-row-photo-wrap">
                    <img class="player-row-photo" src="${p.photo}" alt="${p.name}" onerror="this.src='https://placehold.co/42x42/f2f2f7/aeaeb2?text=${encodeURIComponent(p.lastname?.[0] ?? '?')}'">
                </div>
                <div class="player-row-info">
                    <div class="player-row-name">${p.name}</div>
                    <div class="player-row-meta">
                        <span class="role-badge badge-${p.role}" style="margin-bottom:0">${p.role}</span>
                        <span class="player-row-nat">${p.nationality}</span>
                        ${p.team ? `<span class="player-row-team">${p.team}</span>` : ''}
                    </div>
                </div>
                <div class="player-row-right">
                    <div class="player-row-price">
                        <span class="material-icons-round">toll</span>${p.price}
                    </div>
                    <button class="btn-add-row ${locked ? 'locked' : owned ? 'owned' : ''}" data-id="${p.id}">
                        <span class="material-icons-round">${locked ? 'lock' : owned ? 'check' : 'add'}</span>
                    </button>
                </div>
            </div>`;
        }).join('');
    }

    playersGrid.querySelectorAll('.player-card, .player-row').forEach(card => {
        card.addEventListener('click', e => {
            if (e.target.closest('.btn-add, .btn-add-row')) return;
            openModal(allPlayers.find(p => p.id === parseInt(card.dataset.id)));
        });
        card.addEventListener('keydown', e => { if (e.key === 'Enter') card.click(); });
    });

    playersGrid.querySelectorAll('.btn-add, .btn-add-row').forEach(btn => {
        btn.addEventListener('click', e => {
            e.stopPropagation();
            if (btn.classList.contains('locked')) {
                toast('La competizione non e ancora attiva', 'error');
                return;
            }
            if (btn.classList.contains('owned')) return;
            addPlayer(allPlayers.find(p => p.id === parseInt(btn.dataset.id)));
        });
    });

    attachScrollSentinel(players, slice);
}

function attachScrollSentinel(players, currentSlice) {
    scrollSentinel?.disconnect();
    document.getElementById('scroll-sentinel')?.remove();

    if (currentSlice >= players.length) return;

    const sentinel = document.createElement('div');
    sentinel.id = 'scroll-sentinel';
    sentinel.style.height = '1px';
    playersGrid.after(sentinel);

    scrollSentinel = new IntersectionObserver(entries => {
        if (entries[0].isIntersecting) {
            displayCount = currentSlice + PAGE_SIZE;
            renderPlayers(players, displayCount);
        }
    }, { rootMargin: '200px' });

    scrollSentinel.observe(sentinel);
}

async function loadListone() {
    showSkeletons();
    document.getElementById('listone-subtitle').textContent = 'caricamento...';

    try {
        const res  = await fetch('get_api.php');
        const data = await res.json();

        if (data.status !== 'success') throw new Error(data.message);

        allPlayers = data.data;
        apiSource  = data.source ? `dati ${data.source}` : '';

        displayCount = PAGE_SIZE;
        buildSubtitle();
        populateNationFilter(allPlayers);
        renderPlayers(getFiltered(), displayCount);

    } catch (err) {
        playersGrid.innerHTML = `
            <div class="empty-state" style="grid-column:1/-1">
                <span class="material-icons-round">wifi_off</span>
                <h3>Errore caricamento</h3>
                <p>${err.message}</p>
            </div>`;
    }
}

async function loadSquadra() {
    if (!window.__user?.uid) return;

    const snap = await getDoc(doc(db, 'users', window.__user.uid));
    if (!snap.exists()) return;

    const data    = snap.data();
    myTeam        = data.players ?? [];
    const credits = data.credits ?? 500;
    const spent   = 500 - credits;

    updateCreditsDisplay(credits);
    document.getElementById('stat-count').textContent   = myTeam.length;
    document.getElementById('stat-spent').textContent   = spent;
    document.getElementById('squad-team-name').textContent = data.team_name ?? '';
    document.getElementById('squad-team-meta').textContent =
        `${myTeam.length} giocatori · ${credits} crediti`;

    renderSquad();
}

function renderSquad() {
    if (!myTeam.length) {
        squadList.innerHTML = `
            <div class="empty-state">
                <span class="material-icons-round">sports_soccer</span>
                <h3>Rosa vuota</h3>
                <p>Vai nel Listone e aggiungi i tuoi giocatori</p>
            </div>`;
        return;
    }

    const grouped = { POR: [], DIF: [], CEN: [], ATT: [] };
    myTeam.forEach(p => (grouped[p.role] ?? grouped.CEN).push(p));

    squadList.innerHTML = Object.entries(grouped).map(([role, players]) => {
        if (!players.length) return '';
        return `
            <div class="squad-section">
                <div class="squad-section-label">${role} · ${players.length}</div>
                ${players.map(p => `
                    <div class="squad-player-row">
                        <img class="squad-player-photo" src="${p.photo ?? ''}" alt="${p.name}" onerror="this.src='https://placehold.co/44x44/f2f2f7/aeaeb2?text=${encodeURIComponent(p.name?.[0] ?? '?')}'">
                        <div class="squad-player-info">
                            <div class="squad-player-name">${p.name}</div>
                            <div class="squad-player-meta">${p.team ?? ''} · ${p.nationality ?? ''}</div>
                        </div>
                        <span class="squad-player-price">${p.price}</span>
                        <button class="btn-remove" data-id="${p.id}" aria-label="Rimuovi">
                            <span class="material-icons-round">close</span>
                        </button>
                    </div>
                `).join('')}
            </div>`;
    }).join('');

    squadList.querySelectorAll('.btn-remove').forEach(btn => {
        btn.addEventListener('click', () => removePlayer(parseInt(btn.dataset.id)));
    });
}

async function addPlayer(player) {
    if (!player || !window.__user?.uid) return;
    if (!COMPETITION_ACTIVE) { toast('La competizione non e ancora attiva', 'error'); return; }
    if (isOwned(player.id))  { toast('Gia in rosa', 'error'); return; }

    const credits = window.__user.credits ?? 0;
    if (credits < player.price) { toast(`Crediti insufficienti (servono ${player.price})`, 'error'); return; }
    if (myTeam.length >= 25)    { toast('Rosa completa (max 20 giocatori)', 'error'); return; }

    const playerData = {
        id: player.id, name: player.name, photo: player.photo,
        role: player.role, team: player.team,
        nationality: player.nationality, price: player.price
    };

    try {
        await updateDoc(doc(db, 'users', window.__user.uid), {
            players: arrayUnion(playerData),
            credits: credits - player.price
        });
        await loadSquadra();
        renderPlayers(getFiltered(), displayCount);
        closeModal();
        toast(`${player.name} aggiunto alla rosa`);
    } catch { toast('Errore di rete', 'error'); }
}

async function removePlayer(playerId) {
    if (!window.__user?.uid) return;

    const playerData = myTeam.find(p => p.id === playerId || p.id === String(playerId));
    if (!playerData) return;

    const refund  = Math.round(playerData.price * 0.7);
    const credits = (window.__user.credits ?? 0) + refund;

    try {
        await updateDoc(doc(db, 'users', window.__user.uid), {
            players: arrayRemove(playerData),
            credits: credits
        });
        await loadSquadra();
        renderPlayers(getFiltered(), displayCount);
        toast('Giocatore rimosso (rimborso 70%)');
    } catch { toast('Errore di rete', 'error'); }
}

function openModal(player) {
    if (!player) return;

    const owned  = isOwned(player.id);
    const locked = !COMPETITION_ACTIVE;

    document.getElementById('modal-photo').src         = player.photo;
    document.getElementById('modal-photo').alt         = player.name;
    document.getElementById('modal-name').textContent  = player.name;
    document.getElementById('modal-meta').textContent  =
        `${player.nationality} · ${player.team} · ${player.role}`;
    document.getElementById('modal-goals').textContent   = player.goals   ?? 0;
    document.getElementById('modal-assists').textContent = player.assists  ?? 0;
    document.getElementById('modal-rating').textContent  =
        player.rating ? Number(player.rating).toFixed(1) : '-';

    const btnAdd = document.getElementById('modal-btn-add');
    const btnLbl = document.getElementById('modal-btn-label');
    const btnIco = btnAdd.querySelector('.material-icons-round');

    if (locked) {
        btnAdd.className   = 'modal-btn-add locked';
        btnLbl.textContent = 'Mercato non ancora aperto';
        btnIco.textContent = 'lock';
        btnAdd.onclick     = () => toast('La competizione non e ancora attiva', 'error');
    } else if (owned) {
        btnAdd.className   = 'modal-btn-add owned';
        btnLbl.textContent = 'Gia in rosa';
        btnIco.textContent = 'check';
        btnAdd.onclick     = null;
    } else {
        btnAdd.className   = 'modal-btn-add';
        btnLbl.textContent = `Aggiungi · ${player.price} cr.`;
        btnIco.textContent = 'add_circle';
        btnAdd.onclick     = () => addPlayer(player);
    }

    modalOverlay.classList.remove('hidden');
    requestAnimationFrame(() => modalOverlay.classList.add('open'));
}

function closeModal() {
    modalOverlay.classList.remove('open');
    modalOverlay.addEventListener('transitionend',
        () => modalOverlay.classList.add('hidden'), { once: true });
}

modalOverlay.addEventListener('click', e => { if (e.target === modalOverlay) closeModal(); });
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && !modalOverlay.classList.contains('hidden')) closeModal();
});

async function loadCompetizioni() {
    const container = document.getElementById('comp-teams-list');
    container.innerHTML = `<div class="skel-card skeleton" style="margin:0 20px"><div class="skel-line" style="width:55%"></div></div>`;

    try {
        const snap  = await getDocs(collection(db, 'users'));
        const teams = [];

        snap.forEach(d => {
            const data = d.data();
            if (data.competition_joined) {
                teams.push({
                    uid:       d.id,
                    team_name: data.team_name ?? 'Squadra senza nome',
                    team_logo: data.team_logo ?? null,
                    credits:   data.credits   ?? 500,
                    players:   (data.players ?? []).length,
                });
            }
        });

        if (!teams.length) {
            container.innerHTML = `
                <div class="empty-state">
                    <span class="material-icons-round">emoji_events</span>
                    <h3>Nessuna squadra iscritta</h3>
                    <p>Vai su Profilo per iscrivere la tua squadra</p>
                </div>`;
            return;
        }

        container.innerHTML = teams.map((t, i) => `
            <div class="comp-team-row">
                <div class="comp-rank">${i + 1}</div>
                <div class="comp-team-logo-wrap">
                    ${t.team_logo
                        ? `<img src="${t.team_logo}" alt="${t.team_name}" class="comp-team-logo" onerror="this.style.display='none'">`
                        : `<div class="comp-team-logo-placeholder">${t.team_name[0].toUpperCase()}</div>`
                    }
                </div>
                <div class="comp-team-info">
                    <div class="comp-team-name">${t.team_name}</div>
                    <div class="comp-team-meta">${t.players} giocatori · ${t.credits} cr.</div>
                </div>
            </div>
        `).join('');

    } catch (err) {
        container.innerHTML = `<div class="empty-state"><p>${err.message}</p></div>`;
    }
}

async function loadProfilo() {
    if (!window.__user?.uid) return;

    const snap = await getDoc(doc(db, 'users', window.__user.uid));
    if (!snap.exists()) return;

    const data = snap.data();

    document.getElementById('profilo-email').textContent      = data.email ?? '';
    document.getElementById('profilo-team-name-in').value     = data.team_name ?? '';
    document.getElementById('profilo-joined-status').className =
        data.competition_joined ? 'joined-badge active' : 'joined-badge';
    document.getElementById('profilo-joined-status').textContent =
        data.competition_joined ? 'Iscritto alla competizione' : 'Non iscritto';
    document.getElementById('btn-join-comp').textContent =
        data.competition_joined ? 'Ritira iscrizione' : 'Iscriviti alla competizione';

    if (data.avatar) {
        document.getElementById('profilo-avatar-img').src = data.avatar;
        document.getElementById('profilo-avatar-img').classList.remove('hidden');
        document.getElementById('profilo-avatar-initials').classList.add('hidden');
    } else {
        const initials = (data.team_name ?? 'U').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();
        document.getElementById('profilo-avatar-initials').textContent = initials;
    }

    if (data.team_logo) {
        document.getElementById('profilo-logo-preview').src = data.team_logo;
        document.getElementById('profilo-logo-preview').classList.remove('hidden');
    }
}

async function saveProfilo() {
    const teamName = document.getElementById('profilo-team-name-in').value.trim();
    if (!teamName || !window.__user?.uid) return;

    const btn = document.getElementById('btn-save-profilo');
    btn.disabled = true;
    btn.textContent = 'Salvataggio...';

    try {
        await setDoc(doc(db, 'users', window.__user.uid), { team_name: teamName }, { merge: true });
        window.__user.team_name = teamName;
        document.getElementById('squad-team-name').textContent = teamName;
        updateTopbarAvatar();
        toast('Profilo aggiornato');
    } catch (err) { console.error("Errore profilo:", err); toast('Errore salvataggio', 'error'); }
    finally { btn.disabled = false; btn.textContent = 'Salva modifiche'; }
}

async function toggleJoinCompetition() {
    if (!window.__user?.uid) return;

    try {
        const snap = await getDoc(doc(db, 'users', window.__user.uid));
        const data = snap.exists() ? snap.data() : {};

        const joined = !data?.competition_joined;
        const teamName = document.getElementById('profilo-team-name-in').value.trim() || data?.team_name || 'Squadra';

        await setDoc(doc(db, 'users', window.__user.uid), {
            competition_joined: joined,
            team_name: teamName
        }, { merge: true });
        document.getElementById('profilo-joined-status').className = joined ? 'joined-badge active' : 'joined-badge';
        document.getElementById('profilo-joined-status').textContent = joined ? 'Iscritto alla competizione' : 'Non iscritto';
        document.getElementById('btn-join-comp').textContent = joined ? 'Ritira iscrizione' : 'Iscriviti alla competizione';
        toast(joined ? 'Iscrizione completata' : 'Iscrizione ritirata');
    } catch (err) {
        console.error("Errore join competizione:", err);
        toast('Errore di connessione a Firebase', 'error'); 
    }
}

function updateTopbarAvatar() {
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

function handleAvatarUpload(e) {
    const file = e.target.files[0];
    if (!file || !window.__user?.uid) return;
    if (file.size > 500 * 1024) { toast('Immagine troppo grande (max 500KB)', 'error'); return; }

    const reader = new FileReader();
    reader.onload = async ev => {
        try {
            await setDoc(doc(db, 'users', window.__user.uid), { avatar: ev.target.result }, { merge: true });
            window.__user.avatar = ev.target.result;
            document.getElementById('profilo-avatar-img').src = ev.target.result;
            document.getElementById('profilo-avatar-img').classList.remove('hidden');
            document.getElementById('profilo-avatar-initials').classList.add('hidden');
            updateTopbarAvatar();
            toast('Foto profilo aggiornata');
        } catch (err) { console.error("Errore avatar:", err); toast('Errore upload', 'error'); }
    };
    reader.readAsDataURL(file);
}

function handleLogoUpload(e) {
    const file = e.target.files[0];
    if (!file || !window.__user?.uid) return;
    if (file.size > 300 * 1024) { toast('Logo troppo grande (max 300KB)', 'error'); return; }

    const reader = new FileReader();
    reader.onload = async ev => {
        try {
            await setDoc(doc(db, 'users', window.__user.uid), { team_logo: ev.target.result }, { merge: true });
            const preview = document.getElementById('profilo-logo-preview');
            preview.src = ev.target.result;
            preview.classList.remove('hidden');
            toast('Logo squadra aggiornato');
        } catch (err) { console.error("Errore logo:", err); toast('Errore upload logo', 'error'); }
    };
    reader.readAsDataURL(file);
}

function showPage(name) {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-item').forEach(t => t.classList.remove('active'));
    document.getElementById(`page-${name}`).classList.add('active');
    document.getElementById(`nav-${name}`).classList.add('active');

    if (name === 'squadra')      loadSquadra();
    if (name === 'competizioni') loadCompetizioni();
    if (name === 'profilo')      loadProfilo();
}


document.querySelectorAll('#role-filter-row .chip').forEach(chip => {
    chip.addEventListener('click', () => {
        document.querySelectorAll('#role-filter-row .chip').forEach(c => c.classList.remove('active'));
        chip.classList.add('active');
        activeRole   = chip.dataset.role;
        displayCount = PAGE_SIZE;
        renderPlayers(getFiltered(), displayCount);
    });
});

document.getElementById('view-toggle').addEventListener('click', () => {
    activeView = activeView === 'grid' ? 'list' : 'grid';
    document.getElementById('view-toggle-icon').textContent =
        activeView === 'grid' ? 'view_list' : 'grid_view';
    displayCount = PAGE_SIZE;
    renderPlayers(getFiltered(), displayCount);
});

let searchTimer;
searchIn.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        displayCount = PAGE_SIZE;
        renderPlayers(getFiltered(), displayCount);
    }, 250);
});

document.getElementById('btn-save-profilo').addEventListener('click', saveProfilo);
document.getElementById('btn-join-comp').addEventListener('click', toggleJoinCompetition);
document.getElementById('avatar-upload').addEventListener('change', handleAvatarUpload);
document.getElementById('logo-upload').addEventListener('change', handleLogoUpload);
document.getElementById('trigger-avatar-upload').addEventListener('click',
    () => document.getElementById('avatar-upload').click());
document.getElementById('trigger-logo-upload').addEventListener('click',
    () => document.getElementById('logo-upload').click());


// ─── CALENDARIO MONDIALI 2026 ────────────────────────────────────────────────
const MATCHDAY_SCHEDULE = [
    { round: 1,  label: 'Fase a gironi – Giornata 1', start: '2026-06-11', end: '2026-06-14' },
    { round: 2,  label: 'Fase a gironi – Giornata 2', start: '2026-06-15', end: '2026-06-19' },
    { round: 3,  label: 'Fase a gironi – Giornata 3', start: '2026-06-20', end: '2026-06-25' },
    { round: 4,  label: 'Ottavi di finale',            start: '2026-06-27', end: '2026-07-03' },
    { round: 5,  label: 'Quarti di finale',            start: '2026-07-04', end: '2026-07-05' },
    { round: 6,  label: 'Semifinali',                  start: '2026-07-07', end: '2026-07-08' },
    { round: 7,  label: 'Finale 3° posto + Finale',    start: '2026-07-11', end: '2026-07-19' },
];

function getCurrentMatchday() {
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
        if (today > endCur && today < startNxt) {
            return { ...MATCHDAY_SCHEDULE[i + 1], status: 'next' };
        }
    }

    const last = MATCHDAY_SCHEDULE[MATCHDAY_SCHEDULE.length - 1];
    return { ...last, status: 'ended' };
}

function renderMatchdayAdmin() {
    const md    = getCurrentMatchday();
    const numEl = document.getElementById('admin-matchday-num');
    const lbl   = document.getElementById('admin-matchday-label');
    const dates = document.getElementById('admin-matchday-dates');
    const list  = document.getElementById('admin-matchday-list');

    if (!numEl) return;

    const statusMap = {
        live:     { text: 'In corso',    cls: 'md-live' },
        upcoming: { text: 'Prossima',    cls: 'md-upcoming' },
        next:     { text: 'Prossima',    cls: 'md-upcoming' },
        ended:    { text: 'Concluso',    cls: 'md-ended' },
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
        const isPast    = m.round < md.round ||
            (md.status === 'ended' && m.round === md.round);
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

function formatDate(iso) {
    const d = new Date(iso);
    return d.toLocaleDateString('it-IT', { day: 'numeric', month: 'short' });
}

// ─── SYSTEM SETTINGS ────────────────────────────────────────────────────────

async function loadSystemSettings() {
    try {
        const snap = await getDoc(doc(db, 'settings', 'system'));
        if (snap.exists()) {
            const d = snap.data();
            COMPETITION_ACTIVE = d.market_open === true;
            window.__settings  = d;
        }
    } catch (err) {
        console.error("Errore impostazioni:", err);
    }
}

function setToggleState(id, on) {
    const btn = document.getElementById(id);
    if (!btn) return;
    btn.dataset.state = on ? 'on' : 'off';
    btn.querySelector('.admin-toggle-label').textContent = on ? 'On' : 'Off';
}

function syncAdminUI() {
    const s = window.__settings ?? {};

    setToggleState('toggle-market',        s.market_open === true);
    setToggleState('toggle-competition',   s.competition_active === true);
    setToggleState('toggle-registrations', s.registrations_open !== false);

    const mktLabel  = document.getElementById('admin-market-status-text');
    const compLabel = document.getElementById('admin-comp-status-text');

    if (mktLabel) {
        mktLabel.textContent = s.market_open ? 'Mercato aperto' : 'Mercato chiuso';
        mktLabel.style.color = s.market_open ? 'var(--green)' : 'var(--red)';
    }
    if (compLabel) {
        compLabel.textContent = s.competition_active ? 'Attiva' : 'Non attiva';
        compLabel.style.color = s.competition_active ? 'var(--green)' : 'var(--text-2)';
    }
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
        console.error("Errore setting:", err);
        toast('Errore di permessi', 'error');
        syncAdminUI();
    }
}

function wireToggle(id, settingKey, onMsg, offMsg) {
    const btn = document.getElementById(id);
    if (!btn) return;
    btn.addEventListener('click', async () => {
        const newState = btn.dataset.state !== 'on';
        if (settingKey === 'market_open') COMPETITION_ACTIVE = newState;
        await saveSetting(settingKey, newState);
        toast(newState ? onMsg : offMsg);
    });
}

wireToggle('toggle-market',        'market_open',         'Mercato aperto',          'Mercato chiuso');
wireToggle('toggle-competition',   'competition_active',  'Competizione attivata',   'Competizione disattivata');
wireToggle('toggle-registrations', 'registrations_open',  'Registrazioni aperte',    'Registrazioni chiuse');

async function loadAdminStats() {
    try {
        const snap  = await getDocs(collection(db, 'users'));
        let joined  = 0;
        let players = 0;
        snap.forEach(d => {
            const data = d.data();
            if (data.competition_joined) joined++;
            players += (data.players ?? []).length;
        });
        document.getElementById('admin-stat-users').textContent   = snap.size;
        document.getElementById('admin-stat-joined').textContent  = joined;
        document.getElementById('admin-stat-players').textContent = players;
        renderAdminUsers(snap);
    } catch (err) {
        console.error("Errore stats admin:", err);
    }
}

function renderAdminUsers(snap) {
    const list = document.getElementById('admin-users-list');
    if (!list) return;

    const rows = [];
    snap.forEach(d => rows.push({ uid: d.id, ...d.data() }));

    if (!rows.length) {
        list.innerHTML = `<div class="empty-state"><p>Nessun utente</p></div>`;
        return;
    }

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
                ${u.role === 'admin'       ? '<span class="admin-badge">admin</span>'   : ''}
                ${u.competition_joined     ? '<span class="joined-mini">iscritto</span>' : ''}
            </div>
        </div>
    `).join('');
}

document.getElementById('btn-clear-cache')?.addEventListener('click', async () => {
    try {
        const res  = await fetch('clear_cache.php');
        const data = await res.json();
        toast(data.status === 'ok' ? 'Cache resettata' : 'Errore cache',
              data.status === 'ok' ? 'success' : 'error');
    } catch {
        toast('clear_cache.php non trovato', 'error');
    }
});

// ─── INIT ────────────────────────────────────────────────────────────────────

document.addEventListener('app:ready', async e => {
    const user = e.detail;
    document.getElementById('squad-team-name').textContent = user.team_name ?? '';
    updateTopbarAvatar();

    const userSnap = await getDoc(doc(db, 'users', user.uid));
    if (userSnap.exists() && userSnap.data().role === 'admin') {
        document.getElementById('nav-admin').classList.remove('hidden');
    }

    await loadSystemSettings();
    syncAdminUI();
    await Promise.all([loadListone(), loadSquadra()]);
});

document.getElementById('nav-listone').addEventListener('click',      () => showPage('listone'));
document.getElementById('nav-squadra').addEventListener('click',      () => showPage('squadra'));
document.getElementById('nav-competizioni').addEventListener('click', () => showPage('competizioni'));
document.getElementById('nav-admin').addEventListener('click', () => {
    showPage('admin');
    loadAdminStats();
    syncAdminUI();
    renderMatchdayAdmin();
});

document.addEventListener('goto:profilo', () => showPage('profilo'));

// ════════════════════════════════════════════════════════════════════════════
// SISTEMA PUNTEGGIO
// ════════════════════════════════════════════════════════════════════════════

const SCORE_TABLE = {
    goal:          { POR: 10, DIF: 6, CEN: 6, ATT: 8 },
    assist:        3,
    yellow_card:  -0.5,
    red_card:     -1,
    clean_sheet:   { POR: 1, DIF: 1 },
};

function calcPlayerScore(player, stats) {
    if (!stats || !stats.played) return 0;

    let score = stats.rating ?? 6;

    const goalBonus = SCORE_TABLE.goal[player.role] ?? 6;
    score += (stats.goals   ?? 0) * goalBonus;
    score += (stats.assists ?? 0) * SCORE_TABLE.assist;
    score += (stats.yellow_cards ?? 0) * SCORE_TABLE.yellow_card;
    score += (stats.red_cards    ?? 0) * SCORE_TABLE.red_card;

    const cs = SCORE_TABLE.clean_sheet[player.role];
    if (cs && stats.clean_sheet) score += cs;

    return Math.round(score * 100) / 100;
}

function calcTeamScore(players, roundStats) {
    return players.reduce((sum, p) => {
        const ps = roundStats?.[String(p.id)] ?? null;
        return sum + calcPlayerScore(p, ps);
    }, 0);
}

// ════════════════════════════════════════════════════════════════════════════
// GENERATORE CALENDARIO ROUND-ROBIN
// ════════════════════════════════════════════════════════════════════════════

function generateRoundRobin(teams) {
    const n    = teams.length;
    const list = [...teams];

    if (n % 2 !== 0) list.push({ uid: 'bye', team_name: 'Turno libero' });

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
            if (home.uid !== 'bye' && away.uid !== 'bye') {
                round.push({ home: home.uid, away: away.uid,
                             home_name: home.team_name, away_name: away.team_name });
            }
        }

        schedule.push({ round: r + 1, matches: round });
        rotate.push(rotate.shift()); // rotazione
    }

    return schedule;
}

// ════════════════════════════════════════════════════════════════════════════
// CLASSIFICA
// ════════════════════════════════════════════════════════════════════════════

function buildStandings(teams, schedule, results) {
    const table = {};
    teams.forEach(t => {
        table[t.uid] = { uid: t.uid, name: t.team_name, logo: t.team_logo ?? null,
                         pts: 0, w: 0, d: 0, l: 0, pf: 0, pa: 0 };
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

            if (r.home_score > r.away_score) {
                h.pts += 3; h.w++; a.l++;
            } else if (r.home_score < r.away_score) {
                a.pts += 3; a.w++; h.l++;
            } else {
                h.pts++; a.pts++; h.d++; a.d++;
            }
        });
    });

    return Object.values(table).sort((a, b) =>
        b.pts - a.pts || (b.pf - b.pa) - (a.pf - a.pa) || b.pf - a.pf
    );
}

// ════════════════════════════════════════════════════════════════════════════
// TAB CALENDARIO — UI
// ════════════════════════════════════════════════════════════════════════════

let calSchedule = [];
let calResults  = {};
let calTeams    = [];
let calRound    = 1;

async function loadCalendario() {
    document.getElementById('cal-subtitle').textContent = 'caricamento...';

    try {
        const [calSnap, usersSnap] = await Promise.all([
            getDoc(doc(db, 'settings', 'calendar')),
            getDocs(collection(db, 'users')),
        ]);

        calTeams = [];
        usersSnap.forEach(d => {
            const data = d.data();
            if (data.competition_joined) {
                calTeams.push({ uid: d.id, team_name: data.team_name ?? 'Squadra',
                                team_logo: data.team_logo ?? null,
                                players: data.players ?? [] });
            }
        });

        if (!calSnap.exists() || !calSnap.data().schedule) {
            document.getElementById('cal-matches-list').innerHTML = `
                <div class="empty-state">
                    <span class="material-icons-round">calendar_month</span>
                    <h3>Calendario non ancora generato</h3>
                    <p>L'admin deve generarlo dalla dashboard</p>
                </div>`;
            document.getElementById('cal-subtitle').textContent =
                `${calTeams.length} squadre iscritte`;
            return;
        }

        calSchedule = calSnap.data().schedule ?? [];
        calResults  = calSnap.data().results  ?? {};
        calRound    = getCurrentMatchday().round;
        if (calRound > calSchedule.length) calRound = calSchedule.length;

        document.getElementById('cal-subtitle').textContent =
            `${calTeams.length} squadre · ${calSchedule.length} giornate`;

        renderCalRound();

    } catch (err) {
        console.error('Errore calendario:', err);
        document.getElementById('cal-matches-list').innerHTML =
            `<div class="empty-state"><span class="material-icons-round">wifi_off</span><h3>Errore</h3><p>${err.message}</p></div>`;
    }
}

function renderCalRound() {
    const rd = calSchedule[calRound - 1];
    if (!rd) return;

    const roundNames = ['GJ1','GJ2','GJ3','Ottavi','Quarti','Semifinali','Finale'];
    document.getElementById('cal-round-label').textContent =
        `G${calRound} — ${roundNames[calRound - 1] ?? ''}`;

    const res = calResults[String(calRound)] ?? {};

    document.getElementById('cal-matches-list').innerHTML = rd.matches.map(m => {
        const key = `${m.home}_${m.away}`;
        const r   = res[key];
        const played = r?.home_score !== undefined;

        return `
        <div class="cal-match-card ${played ? 'played' : ''}">
            <div class="cal-team home">
                <div class="cal-team-logo-wrap">
                    ${logoHtml(m.home, calTeams)}
                </div>
                <div class="cal-team-name">${m.home_name}</div>
            </div>
            <div class="cal-score-box">
                ${played
                    ? `<span class="cal-score">${r.home_score}</span>
                       <span class="cal-score-sep">–</span>
                       <span class="cal-score">${r.away_score}</span>`
                    : `<span class="cal-score-tbd">vs</span>`
                }
            </div>
            <div class="cal-team away">
                <div class="cal-team-logo-wrap">
                    ${logoHtml(m.away, calTeams)}
                </div>
                <div class="cal-team-name">${m.away_name}</div>
            </div>
        </div>`;
    }).join('');

    const prev = document.getElementById('cal-round-prev');
    const next = document.getElementById('cal-round-next');
    prev.disabled = calRound <= 1;
    next.disabled = calRound >= calSchedule.length;
}

function logoHtml(uid, teams) {
    const t = teams.find(t => t.uid === uid);
    if (t?.team_logo) {
        return `<img src="${t.team_logo}" class="cal-team-logo" alt="${t.team_name}" onerror="this.style.display='none'">`;
    }
    const initial = (t?.team_name ?? '?')[0].toUpperCase();
    return `<div class="cal-team-logo-placeholder">${initial}</div>`;
}

function renderCalStandings() {
    const standings = buildStandings(calTeams, calSchedule, calResults);
    const list = document.getElementById('cal-standings-list');

    if (!standings.length) {
        list.innerHTML = `<div class="empty-state"><span class="material-icons-round">leaderboard</span><h3>Nessuna squadra</h3></div>`;
        return;
    }

    list.innerHTML = standings.map((s, i) => `
        <div class="cal-standing-row ${i === 0 ? 'first' : ''}">
            <div class="cal-standing-pos">${i + 1}</div>
            <div class="cal-team-logo-wrap small">
                ${logoHtml(s.uid, calTeams)}
            </div>
            <div class="cal-standing-info">
                <div class="cal-standing-name">${s.name}</div>
                <div class="cal-standing-meta">${s.w}V ${s.d}P ${s.l}S · ${s.pf} pf</div>
            </div>
            <div class="cal-standing-pts">${s.pts}</div>
        </div>
    `).join('');
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

// ════════════════════════════════════════════════════════════════════════════
// ADMIN — GENERA CALENDARIO
// ════════════════════════════════════════════════════════════════════════════

let previewSchedule = [];

document.getElementById('btn-preview-calendar')?.addEventListener('click', async () => {
    const snap = await getDocs(collection(db, 'users'));
    const teams = [];
    snap.forEach(d => {
        const data = d.data();
        if (data.competition_joined) {
            teams.push({ uid: d.id, team_name: data.team_name ?? 'Squadra' });
        }
    });

    if (teams.length < 2) {
        toast('Servono almeno 2 squadre iscritte', 'error');
        return;
    }

    previewSchedule = generateRoundRobin(teams);

    const preview = document.getElementById('admin-cal-preview');
    const roundNames = ['GJ1','GJ2','GJ3','Ottavi','Quarti','Semifinali','Finale'];

    preview.innerHTML = previewSchedule.map(rd => `
        <div class="admin-cal-round">
            <div class="admin-cal-round-label">G${rd.round} — ${roundNames[rd.round - 1] ?? ''}</div>
            ${rd.matches.map(m => `
                <div class="admin-cal-match">
                    <span>${m.home_name}</span>
                    <span class="admin-cal-vs">vs</span>
                    <span>${m.away_name}</span>
                </div>
            `).join('')}
        </div>
    `).join('');

    preview.classList.remove('hidden');

    const saveBtn = document.getElementById('btn-generate-calendar');
    saveBtn.disabled = false;
    saveBtn.style.background = 'var(--green-soft)';
    saveBtn.style.color = 'var(--green)';
    toast(`${teams.length} squadre · ${previewSchedule.length} giornate`);
});

document.getElementById('btn-generate-calendar')?.addEventListener('click', async () => {
    if (!previewSchedule.length) return;

    try {
        await setDoc(doc(db, 'settings', 'calendar'), {
            schedule:    previewSchedule,
            results:     {},
            generated_at: new Date().toISOString(),
        });
        toast('Calendario salvato');
        document.getElementById('btn-generate-calendar').disabled = true;
    } catch (err) {
        toast('Errore salvataggio: ' + err.message, 'error');
    }
});

// ════════════════════════════════════════════════════════════════════════════
// ADMIN — CALCOLA PUNTEGGI
// ════════════════════════════════════════════════════════════════════════════

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

            const homeScore = simulateTeamScore(homeUser.players ?? []);
            const awayScore = simulateTeamScore(awayUser.players ?? []);

            roundResults[`${m.home}_${m.away}`] = {
                home_score: Math.round(homeScore * 10) / 10,
                away_score: Math.round(awayScore * 10) / 10,
            };
        });

        results[String(roundNum)] = roundResults;

        await setDoc(doc(db, 'settings', 'calendar'), { results }, { merge: true });

        const resultEl = document.getElementById('admin-score-result');
        resultEl.classList.remove('hidden');
        resultEl.innerHTML = rd.matches.map(m => {
            const r = roundResults[`${m.home}_${m.away}`];
            if (!r) return '';
            const winner = r.home_score > r.away_score ? m.home_name
                         : r.away_score > r.home_score ? m.away_name : 'Pareggio';
            return `<div class="admin-score-row-item">
                <span>${m.home_name} <strong>${r.home_score}</strong></span>
                <span>–</span>
                <span><strong>${r.away_score}</strong> ${m.away_name}</span>
            </div>`;
        }).join('');

        toast(`Giornata ${roundNum} calcolata`);

    } catch (err) {
        toast('Errore: ' + err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<span class="material-icons-round">calculate</span> Calcola punteggi giornata';
    }
});

function simulateTeamScore(players) {
    if (!players.length) return 0;
    const base = players.reduce((s, p) => s + (p.price ?? 10), 0) / players.length;
    return base + (Math.random() * 6 - 3);
}

document.getElementById('nav-calendario')?.addEventListener('click', () => {
    showPage('calendario');
    loadCalendario();
});

// ════════════════════════════════════════════════════════════════════════════
// PWA — INSTALL
// ════════════════════════════════════════════════════════════════════════════

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
    const visible = !isStandalone && !!_pwaPrompt;

    const s1 = document.getElementById('pwa-install-section');
    const s2 = document.getElementById('pwa-install-section-auth');
    if (s1) s1.style.display = visible ? '' : 'none';
    if (s2) s2.style.display = visible ? '' : 'none';
}

document.getElementById('btn-install-pwa-auth')?.addEventListener('click', async () => {
    if (!_pwaPrompt) return;
    _pwaPrompt.prompt();
    const { outcome } = await _pwaPrompt.userChoice;
    if (outcome === 'accepted') _pwaPrompt = null;
    updatePwaBtnVisibility();
});

document.getElementById('btn-install-pwa')?.addEventListener('click', async () => {
    if (!_pwaPrompt) return;
    _pwaPrompt.prompt();
    const { outcome } = await _pwaPrompt.userChoice;
    if (outcome === 'accepted') _pwaPrompt = null;
    updatePwaBtnVisibility();
});


function initTabPill() {
    const bar      = document.querySelector('.tab-bar');
    if (!bar) return;

    const pill = document.createElement('div');
    pill.className = 'tab-pill';
    bar.appendChild(pill);

    function movePill(btn) {
        const barRect = bar.getBoundingClientRect();
        const btnRect = btn.getBoundingClientRect();
        pill.style.left   = (btnRect.left - barRect.left) + 'px';
        pill.style.width  = btnRect.width + 'px';
        pill.style.height = btnRect.height + 'px';
    }

    const active = bar.querySelector('.tab-item.active');
    if (active) {
        pill.style.transition = 'none';
        movePill(active);
        requestAnimationFrame(() => { pill.style.transition = ''; });
    }

    bar.addEventListener('click', e => {
        const btn = e.target.closest('.tab-item');
        if (!btn || btn.classList.contains('hidden')) return;
        movePill(btn);
    });

    window.addEventListener('resize', () => {
        const a = bar.querySelector('.tab-item.active');
        if (a) { pill.style.transition = 'none'; movePill(a); requestAnimationFrame(() => { pill.style.transition = ''; }); }
    }, { passive: true });
}

initTabPill();

const _origShowPage = typeof showPage === 'function' ? showPage : null;

function showPageAnimated(name) {
    const incoming = document.getElementById(`page-${name}`);
    if (!incoming) return;

    document.querySelectorAll('.page.active').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-item').forEach(t => t.classList.remove('active'));

    incoming.classList.add('active', 'page-enter');
    incoming.addEventListener('animationend', () => incoming.classList.remove('page-enter'), { once: true });

    const tab = document.getElementById(`nav-${name}`);
    if (tab) tab.classList.add('active');

    const bar  = document.querySelector('.tab-bar');
    const pill = bar?.querySelector('.tab-pill');
    if (pill && tab && !tab.classList.contains('hidden')) {
        const barRect = bar.getBoundingClientRect();
        const btnRect = tab.getBoundingClientRect();
        pill.style.left   = (btnRect.left - barRect.left) + 'px';
        pill.style.width  = btnRect.width + 'px';
        pill.style.height = btnRect.height + 'px';
    }

    if (name === 'squadra')      loadSquadra();
    if (name === 'competizioni') loadCompetizioni();
    if (name === 'profilo')      { loadProfilo(); renderProfiloHero(); }
}

['listone','squadra','calendario','competizioni','profilo','admin'].forEach(name => {
    const btn = document.getElementById(`nav-${name}`);
    if (!btn) return;
    btn.addEventListener('click', () => showPageAnimated(name));
});

document.getElementById('btn-profile-avatar')?.addEventListener('click', () => showPageAnimated('profilo'));

function spawnConfetti(x, y, count = 18) {
    const colors = ['#0066cc','#28b463','#e8392a','#d97706','#a855f7','#fff'];
    for (let i = 0; i < count; i++) {
        const el = document.createElement('div');
        el.className = 'confetti-particle';
        const angle  = (Math.random() * 360) * (Math.PI / 180);
        const dist   = 50 + Math.random() * 70;
        el.style.cssText = `
            left: ${x}px;
            top:  ${y}px;
            background: ${colors[Math.floor(Math.random() * colors.length)]};
            --tx: ${Math.cos(angle) * dist}px;
            --ty: ${Math.sin(angle) * dist + 40}px;
            --rot: ${Math.random() > 0.5 ? '' : '-'}${180 + Math.random() * 180}deg;
            --dur: ${0.5 + Math.random() * 0.45}s;
            border-radius: ${Math.random() > 0.5 ? '50%' : '2px'};
        `;
        document.body.appendChild(el);
        el.addEventListener('animationend', () => el.remove());
    }
}

const _origAddPlayer = typeof addPlayer === 'function' ? addPlayer : null;

const _toastObs = new MutationObserver(mutations => {
    mutations.forEach(m => {
        m.addedNodes.forEach(node => {
            if (node.classList?.contains('toast') && node.classList.contains('success')) {
                const modal = document.querySelector('.modal-overlay.open');
                if (modal) {
                    const rect = modal.getBoundingClientRect();
                    spawnConfetti(rect.width / 2, rect.height * 0.4, 22);
                } else {
                    spawnConfetti(window.innerWidth / 2, window.innerHeight * 0.45, 16);
                }
            }
        });
    });
});
_toastObs.observe(document.getElementById('toast-wrap'), { childList: true });

const _origUpdateCredits = updateCreditsDisplay;
window.__patchedCredits = function(credits) {
    const pill = document.querySelector('.credits-pill');
    if (pill) {
        pill.classList.remove('bump');
        void pill.offsetWidth;
        pill.classList.add('bump');
        pill.addEventListener('animationend', () => pill.classList.remove('bump'), { once: true });
    }
    document.getElementById('credits-val').textContent = credits;
    document.getElementById('stat-credits').textContent = credits;
    if (window.__user) window.__user.credits = credits;
};

function renderProfiloHero() {
    const user = window.__user ?? {};

    const oldSection = document.querySelector('.profilo-avatar-section');
    if (oldSection) oldSection.style.display = 'none';

    let hero = document.getElementById('profilo-hero-card');
    if (!hero) {
        hero = document.createElement('div');
        hero.id = 'profilo-hero-card';
        hero.className = 'profilo-hero';
        const firstSection = document.querySelector('#page-profilo .profilo-section');
        if (firstSection) firstSection.before(hero);
    }

    const teamName = user.team_name || 'La mia squadra';
    const initials = teamName.split(/\s+/).map(w => w[0]).join('').slice(0, 2).toUpperCase();
    const avatarHtml = user.avatar
        ? `<img class="profilo-hero-avatar" src="${user.avatar}" alt="Avatar">`
        : `<div class="profilo-hero-initials">${initials}</div>`;

    const logoHtml = user.team_logo
        ? `<img class="profilo-hero-logo-img" src="${user.team_logo}" alt="Logo squadra">`
        : `<div class="profilo-hero-logo-placeholder">${initials}</div>`;

    const isJoined = user.competition_joined ?? false;

    hero.innerHTML = `
        <div class="profilo-hero-left">
            <div class="profilo-hero-avatar-wrap" id="trigger-avatar-upload-hero">
                ${avatarHtml}
                <div class="profilo-avatar-overlay">
                    <span class="material-icons-round">photo_camera</span>
                </div>
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
        <div class="profilo-hero-logo">
            ${logoHtml}
        </div>
    `;

    document.getElementById('trigger-avatar-upload-hero')?.addEventListener('click', () => {
        document.getElementById('avatar-upload').click();
    });
}

const _origHandleAvatar = handleAvatarUpload;
const _origHandleLogo   = handleLogoUpload;

document.getElementById('avatar-upload')?.addEventListener('change', () => {
    setTimeout(renderProfiloHero, 200);
});

document.getElementById('logo-upload')?.addEventListener('change', () => {
    setTimeout(renderProfiloHero, 200);
});

if (document.getElementById('page-profilo')?.classList.contains('active')) {
    renderProfiloHero();
}