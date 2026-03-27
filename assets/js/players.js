import { db } from './firebase-init.js';
import { getDocs, collection } from 'https://www.gstatic.com/firebasejs/10.8.1/firebase-firestore.js';
import { toast, spawnConfetti } from './utils.js';
import { showPage } from './ui.js';

export const PAGE_SIZE = 25;

// ─── FLAG HELPER ────────────────────────────────────────────────────────────

const COUNTRY_CODES = {
    'Afghanistan':'af','Albania':'al','Algeria':'dz','Andorra':'ad','Angola':'ao',
    'Argentina':'ar','Armenia':'am','Australia':'au','Austria':'at','Azerbaijan':'az',
    'Bahrain':'bh','Bangladesh':'bd','Belarus':'by','Belgium':'be','Benin':'bj',
    'Bolivia':'bo','Bosnia and Herzegovina':'ba','Botswana':'bw','Brazil':'br',
    'Bulgaria':'bg','Burkina Faso':'bf','Burundi':'bi','Cameroon':'cm','Canada':'ca',
    'Cape Verde':'cv','Central African Republic':'cf','Chad':'td','Chile':'cl',
    'China':'cn','Colombia':'co','Comoros':'km','Congo':'cg','Costa Rica':'cr',
    'Croatia':'hr','Cuba':'cu','Curaçao':'cw','Cyprus':'cy','Czech Republic':'cz',
    'Czechia':'cz','DR Congo':'cd','Denmark':'dk','Ecuador':'ec','Egypt':'eg',
    'El Salvador':'sv','England':'gb-eng','Equatorial Guinea':'gq','Eritrea':'er',
    'Estonia':'ee','Ethiopia':'et','Fiji':'fj','Finland':'fi','France':'fr',
    'Gabon':'ga','Gambia':'gm','Georgia':'ge','Germany':'de','Ghana':'gh',
    'Greece':'gr','Grenada':'gd','Guatemala':'gt','Guinea':'gn','Haiti':'ht',
    'Honduras':'hn','Hungary':'hu','Iceland':'is','India':'in','Indonesia':'id',
    'Iran':'ir','Iraq':'iq','Ireland':'ie','Israel':'il','Italy':'it',
    'Ivory Coast':'ci','Cote D\'Ivoire':'ci','Jamaica':'jm','Japan':'jp','Jordan':'jo',
    'Kazakhstan':'kz','Kenya':'ke','Korea Republic':'kr','South Korea':'kr',
    'Kosovo':'xk','Kuwait':'kw','Kyrgyzstan':'kg','Latvia':'lv','Lebanon':'lb',
    'Libya':'ly','Liechtenstein':'li','Lithuania':'lt','Luxembourg':'lu',
    'Madagascar':'mg','Malawi':'mw','Malaysia':'my','Mali':'ml','Malta':'mt',
    'Mauritania':'mr','Mauritius':'mu','Mexico':'mx','Moldova':'md','Monaco':'mc',
    'Montenegro':'me','Morocco':'ma','Mozambique':'mz','Myanmar':'mm',
    'Namibia':'na','Nepal':'np','Netherlands':'nl','New Zealand':'nz',
    'Nicaragua':'ni','Niger':'ne','Nigeria':'ng','North Macedonia':'mk',
    'Northern Ireland':'gb-nir','Norway':'no','Oman':'om','Pakistan':'pk',
    'Palestine':'ps','Panama':'pa','Paraguay':'py','Peru':'pe','Philippines':'ph',
    'Poland':'pl','Portugal':'pt','Puerto Rico':'pr','Qatar':'qa','Romania':'ro',
    'Russia':'ru','Rwanda':'rw','Saudi Arabia':'sa','Scotland':'gb-sct',
    'Senegal':'sn','Serbia':'rs','Sierra Leone':'sl','Singapore':'sg',
    'Slovakia':'sk','Slovenia':'si','Somalia':'so','South Africa':'za',
    'Spain':'es','Sri Lanka':'lk','Sudan':'sd','Suriname':'sr','Sweden':'se',
    'Switzerland':'ch','Syria':'sy','Tanzania':'tz','Thailand':'th','Togo':'tg',
    'Trinidad and Tobago':'tt','Tunisia':'tn','Turkey':'tr','Turkmenistan':'tm',
    'Uganda':'ug','Ukraine':'ua','United Arab Emirates':'ae','United States':'us',
    'USA':'us','Uruguay':'uy','Uzbekistan':'uz','Venezuela':'ve','Vietnam':'vn',
    'Wales':'gb-wls','Yemen':'ye','Zambia':'zm','Zimbabwe':'zw',
};

function natFlag(nationality) {
    if (!nationality) return '';
    const code = COUNTRY_CODES[nationality];
    if (!code) return `<span class="player-nat-text">${nationality}</span>`;
    return `<img class="player-nat-flag" src="assets/flags/${code}.svg" alt="${nationality}" title="${nationality}">`;
}

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

// --- ownership globale ---

let globalOwnership = {}; // { playerId → team_name }

export async function loadGlobalOwnership() {
    globalOwnership = {};
    try {
        const snap = await getDocs(collection(db, 'users'));
        snap.forEach(d => {
            const data = d.data();
            const uid  = d.id;
            (data.players ?? []).forEach(p => {
                if (uid !== window.__user?.uid) {
                    globalOwnership[p.id] = data.team_name ?? 'Altro giocatore';
                    globalOwnership[String(p.id)] = data.team_name ?? 'Altro giocatore';
                }
            });
        });
    } catch (err) { console.error('Errore caricamento ownership:', err); }
}

export function getOwner(playerId) {
    if (isOwned(playerId)) return null;
    return globalOwnership[playerId] ?? globalOwnership[String(playerId)] ?? null;
}

// --- fine ownership globale ---

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
                <span class="material-symbols-outlined">search_off</span>
                <h3>Nessun risultato</h3>
                <p>Prova con un altro filtro o ricerca</p>
            </div>`;
        return;
    }

    if (activeView === 'grid') {
        playersGrid.className = 'players-grid';
        playersGrid.innerHTML = visible.map(p => {
            const owned  = isOwned(p.id);
            const taken  = !owned ? getOwner(p.id) : null;
            const locked = !window.__competitionActive;
            return `
            <div class="player-card" data-role="${p.role}" data-id="${p.id}" tabindex="0">
                <div class="player-photo-wrap">
                    <img class="player-photo" src="${p.photo}" alt="${p.name}" onerror="this.src='https://placehold.co/80x80/f2f2f7/aeaeb2?text=${encodeURIComponent(p.lastname?.[0] ?? '?')}'">
                    ${COUNTRY_CODES[p.team] 
                        ? `<img class="team-logo" src="assets/flags/${COUNTRY_CODES[p.team]}.svg" alt="${p.team}" title="${p.team}" onerror="this.style.display='none'">` 
                        : (p.team_logo ? `<img class="team-logo" src="${p.team_logo}" alt="" onerror="this.style.display='none'">` : '')}
                </div>
                <div class="role-badge badge-${p.role}">${p.role}</div>
                <div class="player-name">${p.name}</div>
                <div class="player-nat">${natFlag(p.nationality)}</div>
                <div class="price-row"><span class="material-symbols-outlined">toll</span>${p.price}</div>
                <button class="btn-add ${locked ? 'locked' : owned ? 'owned' : taken ? 'taken' : ''}" data-id="${p.id}">
                    <span class="material-symbols-outlined">${locked ? 'lock' : owned ? 'check' : taken ? 'block' : 'add'}</span>
                    ${locked ? 'Non attivo' : owned ? 'In rosa' : taken ? taken : 'Aggiungi'}
                </button>
            </div>`;
        }).join('');
    } else {
        playersGrid.className = 'players-list';
        playersGrid.innerHTML = visible.map(p => {
            const owned  = isOwned(p.id);
            const taken  = !owned ? getOwner(p.id) : null;
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
                        ${natFlag(p.nationality)}
                    </div>
                </div>
                <div class="player-row-right">
                    <div class="player-row-price"><span class="material-symbols-outlined">toll</span>${p.price}</div>
                    <button class="btn-add-row ${locked ? 'locked' : owned ? 'owned' : taken ? 'taken' : ''}" data-id="${p.id}">
                        <span class="material-symbols-outlined">${locked ? 'lock' : owned ? 'check' : taken ? 'block' : 'add'}</span>
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
            if (btn.classList.contains('owned'))  return;
            if (btn.classList.contains('taken'))  { toast('Giocatore gia acquistato da un altro utente', 'error'); return; }
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
    try {
        const res  = await fetch('get_api.php');
        const data = await res.json();
        if (data.status !== 'success') throw new Error(data.message);
        allPlayers   = data.data;
        apiSource    = data.source ? `dati ${data.source}` : '';
        displayCount = PAGE_SIZE;
        await loadGlobalOwnership();
        buildSubtitle();
        populateNationFilter(allPlayers);
        renderPlayers(getFiltered(), displayCount);
    } catch (err) {
        playersGrid.innerHTML = `
            <div class="empty-state" style="grid-column:1/-1">
                <span class="material-symbols-outlined">wifi_off</span>
                <h3>Errore caricamento</h3>
                <p>${err.message}</p>
            </div>`;
    }
}

// modal

const modalOverlay = document.getElementById('modal-overlay');

// price wheel picker

const ITEM_H = 40;
const VISIBLE_ITEMS = 5;
let _wheelItems = [];
let _wheelY = 0;
let _wheelTarget = 0;
let _wheelDragging = false;
let _wheelStartY = 0;
let _wheelStartScroll = 0;
let _wheelVelocity = 0;
let _wheelLastY = 0;
let _wheelLastTime = 0;
let _wheelAnim = null;
let _wheelSelectedPrice = 1;
let _wheelMax = 500;

function initWheel(defaultPrice, maxCredits) {
    const wheel = document.getElementById('price-wheel');
    const display = document.getElementById('price-picker-display');
    _wheelMax = Math.max(1, maxCredits);
    _wheelItems = [];
    let html = '';
    for (let i = 1; i <= _wheelMax; i++) {
        _wheelItems.push(i);
        html += `<div class="price-wheel-item" style="height:${ITEM_H}px">${i}</div>`;
    }
    wheel.innerHTML = html;

    const startIdx = Math.max(0, Math.min(defaultPrice - 1, _wheelMax - 1));
    _wheelY = -startIdx * ITEM_H;
    _wheelTarget = _wheelY;
    _wheelSelectedPrice = _wheelItems[startIdx];
    display.textContent = _wheelSelectedPrice;
    applyWheelTransform();
    updateWheelItemStyles();
}

function applyWheelTransform() {
    const wheel = document.getElementById('price-wheel');
    const offset = (VISIBLE_ITEMS * ITEM_H) / 2 - ITEM_H / 2;
    wheel.style.transform = `translateY(${_wheelY + offset}px)`;
}

function updateWheelItemStyles() {
    const wheel = document.getElementById('price-wheel');
    const items = wheel.children;
    const offset = (VISIBLE_ITEMS * ITEM_H) / 2 - ITEM_H / 2;
    const centerY = -_wheelY;
    for (let i = 0; i < items.length; i++) {
        const itemCenter = i * ITEM_H;
        const dist = Math.abs(itemCenter - centerY);
        const norm = Math.min(dist / (ITEM_H * 2.5), 1);
        const scale = 1 - norm * 0.3;
        const opacity = 1 - norm * 0.65;
        items[i].style.transform = `scale(${scale})`;
        items[i].style.opacity = opacity;
    }
}

function snapWheel() {
    const idx = Math.round(-_wheelTarget / ITEM_H);
    const clamped = Math.max(0, Math.min(idx, _wheelItems.length - 1));
    _wheelTarget = -clamped * ITEM_H;
    _wheelSelectedPrice = _wheelItems[clamped];
    document.getElementById('price-picker-display').textContent = _wheelSelectedPrice;
    updateBtnLabel();
    animateWheel();
}

function animateWheel() {
    cancelAnimationFrame(_wheelAnim);
    (function tick() {
        const diff = _wheelTarget - _wheelY;
        if (Math.abs(diff) < 0.3) {
            _wheelY = _wheelTarget;
            applyWheelTransform();
            updateWheelItemStyles();
            return;
        }
        _wheelY += diff * 0.18;
        applyWheelTransform();
        updateWheelItemStyles();
        _wheelAnim = requestAnimationFrame(tick);
    })();
}

function updateBtnLabel() {
    const btnLbl = document.getElementById('modal-btn-label');
    if (btnLbl && !document.getElementById('modal-btn-add').classList.contains('owned') &&
        !document.getElementById('modal-btn-add').classList.contains('locked') &&
        !document.getElementById('modal-btn-add').classList.contains('taken')) {
        btnLbl.textContent = `Acquista · ${_wheelSelectedPrice} cr.`;
    }
}

const priceWheelEl = document.getElementById('price-wheel');
const pickerWrap = document.querySelector('.price-picker-wheel-wrap');

if (pickerWrap) {
    const getY = e => e.touches ? e.touches[0].clientY : e.clientY;

    pickerWrap.addEventListener('touchstart', e => {
        _wheelDragging = true;
        _wheelStartY = getY(e);
        _wheelStartScroll = _wheelY;
        _wheelVelocity = 0;
        _wheelLastY = _wheelStartY;
        _wheelLastTime = Date.now();
        cancelAnimationFrame(_wheelAnim);
    }, { passive: true });

    pickerWrap.addEventListener('mousedown', e => {
        _wheelDragging = true;
        _wheelStartY = getY(e);
        _wheelStartScroll = _wheelY;
        _wheelVelocity = 0;
        _wheelLastY = _wheelStartY;
        _wheelLastTime = Date.now();
        cancelAnimationFrame(_wheelAnim);
    });

    const onMove = e => {
        if (!_wheelDragging) return;
        const y = getY(e);
        const delta = y - _wheelStartY;
        const now = Date.now();
        const dt = now - _wheelLastTime;
        if (dt > 0) _wheelVelocity = (y - _wheelLastY) / dt;
        _wheelLastY = y;
        _wheelLastTime = now;
        _wheelY = _wheelStartScroll + delta;
        const maxY = 0;
        const minY = -(_wheelItems.length - 1) * ITEM_H;
        _wheelY = Math.max(minY - ITEM_H, Math.min(maxY + ITEM_H, _wheelY));
        applyWheelTransform();
        updateWheelItemStyles();
    };

    pickerWrap.addEventListener('touchmove', onMove, { passive: true });
    window.addEventListener('mousemove', onMove);

    const onEnd = () => {
        if (!_wheelDragging) return;
        _wheelDragging = false;
        const momentum = _wheelVelocity * 120;
        _wheelTarget = _wheelY + momentum;
        const maxY = 0;
        const minY = -(_wheelItems.length - 1) * ITEM_H;
        _wheelTarget = Math.max(minY, Math.min(maxY, _wheelTarget));
        snapWheel();
    };

    pickerWrap.addEventListener('touchend', onEnd);
    window.addEventListener('mouseup', onEnd);
}

export function openModal(player) {
    if (!player) return;
    const owned  = isOwned(player.id);
    const taken  = !owned ? getOwner(player.id) : null;
    const locked = !window.__competitionActive;

    document.getElementById('modal-photo').src          = player.photo;
    document.getElementById('modal-photo').alt          = player.name;
    document.getElementById('modal-name').textContent   = player.name;
    document.getElementById('modal-meta').textContent   = `${player.nationality} · ${player.team} · ${player.role}`;
    document.getElementById('modal-goals').textContent  = player.goals   ?? 0;
    document.getElementById('modal-assists').textContent = player.assists ?? 0;
    document.getElementById('modal-rating').textContent = player.rating ? Number(player.rating).toFixed(1) : '-';

    const pricePicker = document.getElementById('modal-price-picker');
    const btnAdd = document.getElementById('modal-btn-add');
    const btnLbl = document.getElementById('modal-btn-label');
    const btnIco = btnAdd.querySelector('.material-symbols-outlined');

    if (locked) {
        pricePicker.classList.add('hidden');
        btnAdd.className   = 'modal-btn-add locked';
        btnLbl.textContent = 'Mercato non ancora aperto';
        btnIco.textContent = 'lock';
        btnAdd.onclick     = () => toast('La competizione non e ancora attiva', 'error');
    } else if (owned) {
        pricePicker.classList.add('hidden');
        btnAdd.className   = 'modal-btn-add owned';
        btnLbl.textContent = 'Gia in rosa';
        btnIco.textContent = 'check';
        btnAdd.onclick     = null;
    } else if (taken) {
        pricePicker.classList.add('hidden');
        btnAdd.className   = 'modal-btn-add taken';
        btnLbl.textContent = `Preso da ${taken}`;
        btnIco.textContent = 'block';
        btnAdd.onclick     = null;
    } else {
        const credits = window.__user?.credits ?? 500;
        pricePicker.classList.remove('hidden');
        initWheel(player.price, credits);
        btnAdd.className   = 'modal-btn-add';
        btnLbl.textContent = `Acquista · ${_wheelSelectedPrice} cr.`;
        btnIco.textContent = 'add_circle';
        btnAdd.onclick     = () => {
            const rect = btnAdd.getBoundingClientRect();
            spawnConfetti(rect.left + rect.width / 2, rect.top, 22);
            window.__addPlayer(player, _wheelSelectedPrice);
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