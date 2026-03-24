import { toast, spawnConfetti } from './utils.js';
import { showPage } from './ui.js';

export const PAGE_SIZE = 25;

export let allPlayers   = [];
export let activeRole   = 'ALL';
export let activeNations = [];
export let activeView   = 'grid';
export let displayCount = PAGE_SIZE;
export let apiSource    = '';

let scrollSentinel;

const playersGrid = document.getElementById('players-grid');
const searchIn    = document.getElementById('search-in');

export function setAllPlayers(p)    { allPlayers    = p; }
export function setApiSource(s)     { apiSource     = s; }
export function setDisplayCount(n)  { displayCount  = n; }

export function isOwned(playerId) {
    return (window.__myTeam ?? []).some(p => p.id === playerId || p.id === String(playerId));
}

export function getFiltered() {
    const q = searchIn.value.trim().toLowerCase();
    return allPlayers.filter(p => {
        const matchRole   = activeRole === 'ALL' || p.role === activeRole;
        const matchNation = activeNations.length === 0 || activeNations.includes(p.nationality);
        const matchQ      = !q || p.name.toLowerCase().includes(q) || p.nationality.toLowerCase().includes(q);
        return matchRole && matchNation && matchQ;
    });
}

export function buildSubtitle() {
    const parts = [];
    if (allPlayers.length) parts.push(`${allPlayers.length} giocatori`);
    if (!window.__competitionActive) parts.push('mercato chiuso');
    if (apiSource) parts.push(apiSource);
    document.getElementById('listone-subtitle').textContent = parts.join(' · ');
}

export function populateNationFilter(players) {
    const container = document.getElementById('nation-filter-row');
    const nations   = [...new Set(players.map(p => p.nationality))].filter(Boolean).sort();

    container.innerHTML = `
        <button class="chip active" data-nation="ALL">Tutte</button>
        ${nations.map(n => `<button class="chip" data-nation="${n}">${n}</button>`).join('')}
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
                if (activeNations.length === 0) container.querySelector('[data-nation="ALL"]').classList.add('active');
            }
            displayCount = PAGE_SIZE;
            renderPlayers(getFiltered(), displayCount);
        });
    });
}

export function showSkeletons(n = PAGE_SIZE) {
    if (activeView === 'grid') {
        playersGrid.className = 'players-grid';
        playersGrid.innerHTML = Array.from({ length: n }, () => `
            <div class="skel-card skeleton">
                <div class="skel-circle"></div>
                <div class="skel-line" style="width:70%"></div>
                <div class="skel-line" style="width:48%"></div>
                <div class="skel-btn"></div>
            </div>`).join('');
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
            </div>`).join('');
    }
}

export function renderPlayers(players, slice) {
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
            const owned  = isOwned(p.id);
            const locked = !window.__competitionActive;
            return `
            <div class="player-card" data-role="${p.role}" data-id="${p.id}" tabindex="0">
                <div class="player-photo-wrap">
                    <img class="player-photo" src="${p.photo}" alt="${p.name}" onerror="this.src='https://placehold.co/80x80/f2f2f7/aeaeb2?text=${encodeURIComponent(p.lastname?.[0] ?? '?')}'">
                    ${p.team_logo ? `<img class="team-logo" src="${p.team_logo}" alt="" onerror="this.style.display='none'">` : ''}
                </div>
                <div class="role-badge badge-${p.role}">${p.role}</div>
                <div class="player-name">${p.name}</div>
                <div class="player-nat">${p.nationality}</div>
                <div class="price-row"><span class="material-icons-round">toll</span>${p.price}</div>
                <button class="btn-add ${locked ? 'locked' : owned ? 'owned' : ''}" data-id="${p.id}">
                    <span class="material-icons-round">${locked ? 'lock' : owned ? 'check' : 'add'}</span>
                    ${locked ? 'Non attivo' : owned ? 'In rosa' : 'Aggiungi'}
                </button>
            </div>`;
        }).join('');
    } else {
        playersGrid.className = 'players-list';
        playersGrid.innerHTML = visible.map(p => {
            const owned  = isOwned(p.id);
            const locked = !window.__competitionActive;
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
                    <div class="player-row-price"><span class="material-icons-round">toll</span>${p.price}</div>
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
            if (btn.classList.contains('locked')) { toast('La competizione non e ancora attiva', 'error'); return; }
            if (btn.classList.contains('owned')) return;
            const p = allPlayers.find(p => p.id === parseInt(btn.dataset.id));
            if (p) window.__addPlayer(p);
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

export async function loadListone() {
    showSkeletons();
    document.getElementById('listone-subtitle').textContent = 'caricamento...';
    try {
        const res  = await fetch('get_api.php');
        const data = await res.json();
        if (data.status !== 'success') throw new Error(data.message);
        allPlayers   = data.data;
        apiSource    = data.source ? `dati ${data.source}` : '';
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

// modal

const modalOverlay = document.getElementById('modal-overlay');

export function openModal(player) {
    if (!player) return;
    const owned  = isOwned(player.id);
    const locked = !window.__competitionActive;

    document.getElementById('modal-photo').src          = player.photo;
    document.getElementById('modal-photo').alt          = player.name;
    document.getElementById('modal-name').textContent   = player.name;
    document.getElementById('modal-meta').textContent   = `${player.nationality} · ${player.team} · ${player.role}`;
    document.getElementById('modal-goals').textContent  = player.goals   ?? 0;
    document.getElementById('modal-assists').textContent = player.assists ?? 0;
    document.getElementById('modal-rating').textContent = player.rating ? Number(player.rating).toFixed(1) : '-';

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
        btnAdd.onclick     = () => {
            const rect = btnAdd.getBoundingClientRect();
            spawnConfetti(rect.left + rect.width / 2, rect.top, 22);
            window.__addPlayer(player);
        };
    }

    modalOverlay.classList.remove('hidden');
    requestAnimationFrame(() => modalOverlay.classList.add('open'));
}

export function closeModal() {
    modalOverlay.classList.remove('open');
    modalOverlay.addEventListener('transitionend', () => modalOverlay.classList.add('hidden'), { once: true });
}

modalOverlay.addEventListener('click', e => { if (e.target === modalOverlay) closeModal(); });
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && !modalOverlay.classList.contains('hidden')) closeModal();
});

// role filter

document.querySelectorAll('#role-filter-row .chip').forEach(chip => {
    chip.addEventListener('click', () => {
        document.querySelectorAll('#role-filter-row .chip').forEach(c => c.classList.remove('active'));
        chip.classList.add('active');
        activeRole   = chip.dataset.role;
        displayCount = PAGE_SIZE;
        renderPlayers(getFiltered(), displayCount);
    });
});

document.getElementById('view-toggle')?.addEventListener('click', () => {
    activeView = activeView === 'grid' ? 'list' : 'grid';
    document.getElementById('view-toggle-icon').textContent = activeView === 'grid' ? 'view_list' : 'grid_view';
    displayCount = PAGE_SIZE;
    renderPlayers(getFiltered(), displayCount);
});

let searchTimer;
searchIn?.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { displayCount = PAGE_SIZE; renderPlayers(getFiltered(), displayCount); }, 250);
});