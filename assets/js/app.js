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

function handleAvatarUpload(e) {
    const file = e.target.files[0];
    if (!file || !window.__user?.uid) return;
    if (file.size > 500 * 1024) { toast('Immagine troppo grande (max 500KB)', 'error'); return; }

    const reader = new FileReader();
    reader.onload = async ev => {
        try {
            await setDoc(doc(db, 'users', window.__user.uid), { avatar: ev.target.result }, { merge: true });
            document.getElementById('profilo-avatar-img').src = ev.target.result;
            document.getElementById('profilo-avatar-img').classList.remove('hidden');
            document.getElementById('profilo-avatar-initials').classList.add('hidden');
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

document.getElementById('nav-listone').addEventListener('click',      () => showPage('listone'));
document.getElementById('nav-squadra').addEventListener('click',      () => showPage('squadra'));
document.getElementById('nav-competizioni').addEventListener('click', () => showPage('competizioni'));
document.getElementById('nav-profilo').addEventListener('click',      () => showPage('profilo'));
document.getElementById('nav-admin').addEventListener('click',        () => showPage('admin'));

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

function syncAdminUI() {
    const s = window.__settings ?? {};

    const mktToggle  = document.getElementById('toggle-market');
    const compToggle = document.getElementById('toggle-competition');
    const regToggle  = document.getElementById('toggle-registrations');
    const mktLabel   = document.getElementById('admin-market-status-text');
    const compLabel  = document.getElementById('admin-comp-status-text');
    const mdVal      = document.getElementById('admin-matchday-val');

    if (mktToggle) {
        mktToggle.checked    = s.market_open === true;
        mktLabel.textContent = s.market_open ? 'Mercato aperto' : 'Mercato chiuso';
        mktLabel.style.color = s.market_open ? 'var(--green)' : 'var(--red)';
    }
    if (compToggle) {
        compToggle.checked    = s.competition_active === true;
        compLabel.textContent = s.competition_active ? 'Competizione attiva' : 'Competizione non attiva';
        compLabel.style.color = s.competition_active ? 'var(--green)' : 'var(--text-2)';
    }
    if (regToggle) {
        regToggle.checked = s.registrations_open !== false;
    }
    if (mdVal) {
        mdVal.textContent = s.current_matchday ?? 1;
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
                <div class="admin-user-meta">${u.email ?? ''} · ${(u.players ?? []).length} giocatori · ${u.credits ?? 500} cr.</div>
            </div>
            <div class="admin-user-badges">
                ${u.role === 'admin' ? '<span class="admin-badge">admin</span>' : ''}
                ${u.competition_joined ? '<span class="joined-mini">iscritto</span>' : ''}
            </div>
        </div>
    `).join('');
}

document.getElementById('toggle-market')?.addEventListener('change', async e => {
    COMPETITION_ACTIVE = e.target.checked;
    await saveSetting('market_open', e.target.checked);
    toast(e.target.checked ? 'Mercato aperto' : 'Mercato chiuso');
});

document.getElementById('toggle-competition')?.addEventListener('change', async e => {
    await saveSetting('competition_active', e.target.checked);
    toast(e.target.checked ? 'Competizione attivata' : 'Competizione disattivata');
});

document.getElementById('toggle-registrations')?.addEventListener('change', async e => {
    await saveSetting('registrations_open', e.target.checked);
    toast(e.target.checked ? 'Registrazioni aperte' : 'Registrazioni chiuse');
});

document.getElementById('matchday-minus')?.addEventListener('click', () => {
    const el = document.getElementById('admin-matchday-val');
    const cur = parseInt(el.textContent) || 1;
    if (cur > 1) el.textContent = cur - 1;
});

document.getElementById('matchday-plus')?.addEventListener('click', () => {
    const el = document.getElementById('admin-matchday-val');
    el.textContent = (parseInt(el.textContent) || 1) + 1;
});

document.getElementById('btn-save-matchday')?.addEventListener('click', async () => {
    const val = parseInt(document.getElementById('admin-matchday-val').textContent) || 1;
    await saveSetting('current_matchday', val);
    toast(`Giornata ${val} salvata`);
});

document.getElementById('btn-clear-cache')?.addEventListener('click', async () => {
    try {
        const res  = await fetch('clear_cache.php');
        const data = await res.json();
        toast(data.status === 'ok' ? 'Cache invalidata' : 'Errore cache',
              data.status === 'ok' ? 'success' : 'error');
    } catch {
        toast('clear_cache.php non trovato', 'error');
    }
});

document.addEventListener('app:ready', async e => {
    const user = e.detail;
    document.getElementById('squad-team-name').textContent = user.team_name ?? '';

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
document.getElementById('nav-profilo').addEventListener('click',      () => showPage('profilo'));
document.getElementById('nav-admin').addEventListener('click', () => {
    showPage('admin');
    loadAdminStats();
    syncAdminUI();
});