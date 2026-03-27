import { db } from './firebase-init.js';
import {
    doc, getDoc, updateDoc, getDocs, collection,
    arrayUnion, arrayRemove
} from 'https://www.gstatic.com/firebasejs/10.8.1/firebase-firestore.js';
import { toast, bumpCredits } from './utils.js';
import { renderPlayers, getFiltered, displayCount, isOwned, closeModal, loadGlobalOwnership } from './players.js';

const squadList = document.getElementById('squad-list');

export async function loadSquadra() {
    if (!window.__user?.uid) return;
    const snap = await getDoc(doc(db, 'users', window.__user.uid));
    if (!snap.exists()) return;
    const data    = snap.data();
    window.__myTeam = data.players ?? [];
    const credits = data.credits ?? 500;
    const spent   = 500 - credits;

    bumpCredits(credits);
    const meta = document.getElementById('profilo-squad-meta');
    if (meta) meta.textContent = `${(window.__myTeam??[]).length} giocatori · ${credits} crediti`;
    document.getElementById('stat-count').textContent      = window.__myTeam.length;
    document.getElementById('stat-spent').textContent      = spent;
    document.getElementById('squad-team-name') && (document.getElementById('squad-team-name').textContent = data.team_name ?? '');
    document.getElementById('squad-team-meta').textContent = `${window.__myTeam.length} giocatori · ${credits} crediti`;

    renderSquad();
}

function renderSquad() {
    if (!window.__myTeam?.length) {
        squadList.innerHTML = `
            <div class="empty-state">
                <span class="material-symbols-outlined">sports_soccer</span>
                <h3>Rosa vuota</h3>
                <p>Vai nel Listone e aggiungi i tuoi giocatori</p>
            </div>`;
        return;
    }

    const grouped = { POR: [], DIF: [], CEN: [], ATT: [] };
    window.__myTeam.forEach(p => (grouped[p.role] ?? grouped.CEN).push(p));

    squadList.innerHTML = Object.entries(grouped).map(([role, players]) => {
        if (!players.length) return '';
        return `
            <div class="squad-section">
                <div class="squad-section-label">${role} · ${players.length}</div>
                ${players.map(p => `
                    <div class="squad-player-row">
                        <img class="squad-player-photo" src="${p.photo ?? ''}" alt="${p.name}"
                            onerror="this.src='https://placehold.co/44x44/f2f2f7/aeaeb2?text=${encodeURIComponent(p.name?.[0] ?? '?')}'">
                        <div class="squad-player-info">
                            <div class="squad-player-name">${p.name}</div>
                            <div class="squad-player-meta">${p.team ?? ''} · ${p.nationality ?? ''}</div>
                        </div>
                        <span class="squad-player-price">${p.price}</span>
                        <button class="btn-remove" data-id="${p.id}" aria-label="Rimuovi">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>`).join('')}
            </div>`;
    }).join('');

    squadList.querySelectorAll('.btn-remove').forEach(btn => {
        btn.addEventListener('click', () => removePlayer(parseInt(btn.dataset.id)));
    });
}

export async function addPlayer(player, customPrice) {
    if (!player || !window.__user?.uid) return;
    if (!window.__competitionActive) { toast('La competizione non e ancora attiva', 'error'); return; }
    if (isOwned(player.id))          { toast('Gia in rosa', 'error'); return; }

    const price   = customPrice ?? player.price;
    const credits = window.__user.credits ?? 0;
    if (credits < price)                     { toast(`Crediti insufficienti (servono ${price})`, 'error'); return; }
    if ((window.__myTeam ?? []).length >= 29) { toast('Rosa completa (max 29 giocatori)', 'error'); return; }

    const playerData = {
        id: player.id, name: player.name, photo: player.photo,
        role: player.role, team: player.team,
        nationality: player.nationality, price: price
    };

    try {
        await updateDoc(doc(db, 'users', window.__user.uid), {
            players: arrayUnion(playerData),
            credits: credits - price
        });
        await loadSquadra();
        await loadGlobalOwnership();
        renderPlayers(getFiltered(), displayCount);
        closeModal();
        toast(`${player.name} aggiunto per ${price} cr.`);
    } catch { toast('Errore di rete', 'error'); }
}

export async function removePlayer(playerId) {
    if (!window.__user?.uid) return;
    const playerData = window.__myTeam.find(p => p.id === playerId || p.id === String(playerId));
    if (!playerData) return;
    const refund  = Math.round(playerData.price);
    const credits = (window.__user.credits ?? 0) + refund;
    try {
        await updateDoc(doc(db, 'users', window.__user.uid), {
            players: arrayRemove(playerData),
            credits
        });
        await loadSquadra();
        await loadGlobalOwnership();
        renderPlayers(getFiltered(), displayCount);
        toast('Giocatore rimosso');
    } catch { toast('Errore di rete', 'error'); }
}

export async function loadCompetizioni() {
    // --- 1. Aggiornamento Dinamico Banner ---
    const badge = document.querySelector('#page-competizione .comp-status-badge');
    const sub   = document.querySelector('#page-competizione .comp-banner-sub');
    if (badge) {
        if (window.__settings?.competition_active) {
            badge.textContent = 'IN CORSO';
            badge.style.background = 'var(--green-soft)';
            badge.style.color = 'var(--green)';
            if (sub) sub.textContent = 'La competizione ufficiale è attiva';
        } else {
            badge.textContent = 'ATTESA';
            badge.style.background = '';
            badge.style.color = '';
            if (sub) sub.textContent = 'La competizione inizierà con i Mondiali';
        }
    }

    const container = document.getElementById('comp-teams-list');
    if (!container) return;
    container.innerHTML = `<div class="skel-card skeleton" style="margin:0 20px"><div class="skel-line" style="width:55%"></div></div>`;
    
    try {
        const snap  = await getDocs(collection(db, 'users'));
        const teams = [];
        snap.forEach(d => {
            const data = d.data();
            if (data.competition_joined) teams.push({
                uid: d.id, 
                team_name: data.team_name ?? 'Squadra senza nome',
                team_logo: data.team_logo ?? null,
                credits: data.credits ?? 500,
                players: data.players ?? [] // Salviamo l'intero array per mostrarlo nella modale
            });
        });
        
        if (!teams.length) {
            container.innerHTML = `
                <div class="empty-state">
                    <span class="material-symbols-outlined">emoji_events</span>
                    <h3>Nessuna squadra iscritta</h3>
                    <p>Vai su Profilo per iscrivere la tua squadra</p>
                </div>`;
            return;
        }

        // --- 2. Costruzione della Lista Squadre ---
        container.innerHTML = teams.map((t, i) => `
            <div class="comp-team-row clickable-team" data-index="${i}" style="cursor:pointer">
                <div class="comp-rank">${i + 1}</div>
                <div class="comp-team-logo-wrap">
                    ${t.team_logo
                        ? `<img src="${t.team_logo}" class="comp-team-logo" onerror="this.style.display='none'">`
                        : `<div class="comp-team-logo-placeholder">${t.team_name[0].toUpperCase()}</div>`}
                </div>
                <div class="comp-team-info">
                    <div class="comp-team-name">${t.team_name}</div>
                    <div class="comp-team-meta">${t.players.length} giocatori · ${t.credits} cr.</div>
                </div>
                <span class="material-symbols-outlined" style="color:var(--text-3); font-size: 20px;">chevron_right</span>
            </div>`).join('');

        // --- 3. Aggancio Eventi Modale ---
        container.querySelectorAll('.clickable-team').forEach(row => {
            row.addEventListener('click', () => {
                const teamData = teams[row.dataset.index];
                showOpponentSquad(teamData);
            });
        });

    } catch (err) {
        container.innerHTML = `<div class="empty-state"><p>${err.message}</p></div>`;
    }
}

// --- Funzione per mostrare la rosa dell'avversario usando il bottom sheet esistente ---
function showOpponentSquad(team) {
    const overlay = document.getElementById('squad-sheet-overlay');
    if (!overlay) return;

    // Cambia temporaneamente il titolo del bottom sheet con il nome della squadra avversaria
    const titleEl = document.querySelector('.squad-sheet-title');
    if (titleEl) {
        titleEl.dataset.original = titleEl.dataset.original || titleEl.textContent;
        titleEl.textContent = team.team_name;
    }

    const credits = team.credits;
    const spent = 500 - credits;
    const players = team.players;

    document.getElementById('sheet-stat-count').textContent = players.length;
    document.getElementById('sheet-stat-spent').textContent = spent;
    document.getElementById('sheet-stat-credits').textContent = credits;

    const list = document.getElementById('squad-sheet-list');
    
    if (!players.length) {
        list.innerHTML = `<div class="empty-state" style="padding:40px 20px">
            <span class="material-symbols-outlined">sports_soccer</span>
            <h3>Rosa vuota</h3>
        </div>`;
    } else {
        const grouped = { POR: [], DIF: [], CEN: [], ATT: [] };
        players.forEach(p => (grouped[p.role] ?? grouped.CEN).push(p));

        list.innerHTML = Object.entries(grouped).map(([role, plist]) => {
            if (!plist.length) return '';
            return `
            <div class="squad-sheet-group">
                <div class="squad-sheet-role">${role} · ${plist.length}</div>
                ${plist.map(p => `
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

    // Mostra il bottom sheet
    overlay.classList.remove('hidden');
    requestAnimationFrame(() => overlay.classList.add('open'));

    // Ripristina il titolo originale ("La mia rosa") alla chiusura
    const closeBtn = document.getElementById('btn-close-squad-sheet');
    const resetTitle = () => {
        if (titleEl && titleEl.dataset.original) {
            titleEl.textContent = titleEl.dataset.original;
        }
        closeBtn?.removeEventListener('click', resetTitle);
        overlay.removeEventListener('click', resetTitle);
    };
    
    closeBtn?.addEventListener('click', resetTitle);
    overlay.addEventListener('click', e => {
        if (e.target === overlay) resetTitle();
    });
}