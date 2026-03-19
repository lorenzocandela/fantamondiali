<template>
  <div class="listone-wrap">

    <!-- STICKY FILTERS -->
    <div class="filters-sticky">
      <div class="search-row">
        <div class="search-box">
          <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
          <input
            v-model="searchQuery"
            placeholder="Cerca giocatore..."
            class="search-input"
          />
          <button v-if="searchQuery" class="clear-btn" @click="searchQuery = ''">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
          </button>
        </div>
      </div>

      <div class="chips-row">
        <!-- Ruolo chips -->
        <button
          v-for="r in roles"
          :key="r.value"
          class="chip"
          :class="{ active: selectedRole === r.value, [`role-${r.value}`]: selectedRole === r.value }"
          @click="selectedRole = selectedRole === r.value ? '' : r.value"
        >
          {{ r.label }}
        </button>

        <div class="chip-divider"></div>

        <!-- Nazione select -->
        <div class="select-wrap">
          <svg class="select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <polyline points="6 9 12 15 18 9"/>
          </svg>
          <select v-model="selectedCountry" class="chip-select">
            <option value="">🌍 Nazione</option>
            <option v-for="c in availableCountries" :key="c" :value="c">{{ c }}</option>
          </select>
        </div>
      </div>

      <div class="results-count">
        {{ filteredPlayers.length }} giocatori
        <span v-if="hasFilters" class="reset-link" @click="resetFilters">· Azzera</span>
      </div>
    </div>

    <!-- LOADING -->
    <div v-if="loading" class="list-loading">
      <div class="spinner"></div>
      <span>Caricamento giocatori...</span>
    </div>

    <!-- PLAYER LIST -->
    <div v-else-if="filteredPlayers.length" class="players-list">
      <div
        v-for="(player, i) in filteredPlayers"
        :key="player.id"
        class="player-row"
        :style="{ animationDelay: `${Math.min(i, 20) * 30}ms` }"
        @click="openPlayer(player)"
      >
        <div class="player-avatar-wrap">
          <img
            :src="player.image_path"
            :alt="player.name"
            class="player-avatar"
            @error="e => e.target.src = fallbackAvatar"
          />
          <span :class="['role-dot', `role-${player.position}`]"></span>
        </div>

        <div class="player-info">
          <span class="player-name">{{ player.name }}</span>
          <span class="player-meta">
            <span :class="['role-badge', `role-${player.position}`]">{{ player.position }}</span>
            <span class="player-country">{{ player.country }}</span>
          </span>
        </div>

        <div class="player-right">
          <span v-if="player.owned_by" class="owned-tag">Preso</span>
          <span v-else class="price-tag">{{ player.price ? `${player.price}` : '–' }}</span>
          <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="9 18 15 12 9 6"/>
          </svg>
        </div>
      </div>
    </div>

    <!-- EMPTY STATE -->
    <div v-else class="placeholder-screen">
      <span class="placeholder-icon">🔍</span>
      <p class="placeholder-title">Nessun giocatore trovato</p>
      <p class="placeholder-sub">Prova a cambiare i filtri di ricerca</p>
      <button class="btn-reset" @click="resetFilters">Azzera filtri</button>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({ user: Object });

const players     = ref([]);
const loading     = ref(false);
const searchQuery = ref('');
const selectedRole    = ref('');
const selectedCountry = ref('');

const fallbackAvatar = `data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 40 40'%3E%3Ccircle cx='20' cy='20' r='20' fill='%231e1e2e'/%3E%3Ccircle cx='20' cy='16' r='7' fill='%232a2a3e'/%3E%3Cellipse cx='20' cy='34' rx='12' ry='8' fill='%232a2a3e'/%3E%3C/svg%3E`;

const roles = [
  { value: 'POR', label: 'POR' },
  { value: 'DIF', label: 'DIF' },
  { value: 'CEN', label: 'CEN' },
  { value: 'ATT', label: 'ATT' },
];

onMounted(async () => {
  loading.value = true;
  try {
    const res = await axios.get('/api/players');
    players.value = res.data;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
});

const availableCountries = computed(() => {
  return [...new Set(players.value.map(p => p.country))].sort();
});

const hasFilters = computed(() => searchQuery.value || selectedRole.value || selectedCountry.value);

const filteredPlayers = computed(() => {
  const q = searchQuery.value.toLowerCase();
  return players.value.filter(p => {
    const matchName    = p.name.toLowerCase().includes(q);
    const matchRole    = !selectedRole.value    || p.position === selectedRole.value;
    const matchCountry = !selectedCountry.value || p.country  === selectedCountry.value;
    return matchName && matchRole && matchCountry;
  });
});

const resetFilters = () => {
  searchQuery.value    = '';
  selectedRole.value   = '';
  selectedCountry.value = '';
};

const openPlayer = (player) => {
  // TODO: aprire modale/dettaglio giocatore
  console.log('apri', player.name);
};
</script>

<style scoped>
.listone-wrap {
  display: flex;
  flex-direction: column;
  min-height: calc(100vh - var(--header-h) - var(--nav-h));
}

/* ── FILTERS ──────────────────────────────────────────── */
.filters-sticky {
  position: sticky;
  top: 0;
  z-index: 10;
  background: var(--bg);
  padding: 12px 16px 8px;
  border-bottom: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.search-row { display: flex; gap: 8px; }

.search-box {
  flex: 1;
  display: flex;
  align-items: center;
  background: var(--bg-card2);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 0 12px;
  gap: 8px;
  transition: border-color 0.2s;
}

.search-box:focus-within { border-color: var(--accent); }

.search-icon { width: 16px; height: 16px; color: var(--text-muted); flex-shrink: 0; }

.search-input {
  flex: 1;
  background: none;
  border: none;
  outline: none;
  color: var(--text);
  font-size: 14px;
  padding: 11px 0;
}

.search-input::placeholder { color: var(--text-muted); }

.clear-btn {
  background: none;
  border: none;
  color: var(--text-muted);
  cursor: pointer;
  display: flex;
  padding: 0;
}
.clear-btn svg { width: 14px; height: 14px; }

/* chips row */
.chips-row {
  display: flex;
  align-items: center;
  gap: 6px;
  overflow-x: auto;
  scrollbar-width: none;
}
.chips-row::-webkit-scrollbar { display: none; }

.chip {
  flex-shrink: 0;
  padding: 5px 12px;
  border-radius: 20px;
  border: 1px solid var(--border);
  background: var(--bg-card2);
  color: var(--text-muted);
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s;
}

.chip.active { color: #fff; border-color: transparent; }
.chip.role-POR { background: #c89b14; }
.chip.role-DIF { background: #1a9e5c; }
.chip.role-CEN { background: #2b6fd4; }
.chip.role-ATT { background: #c0392b; }

.chip-divider {
  width: 1px;
  height: 20px;
  background: var(--border);
  flex-shrink: 0;
}

.select-wrap {
  position: relative;
  flex-shrink: 0;
  display: flex;
  align-items: center;
}

.chip-select {
  appearance: none;
  background: var(--bg-card2);
  border: 1px solid var(--border);
  border-radius: 20px;
  color: var(--text-muted);
  font-size: 12px;
  font-weight: 600;
  padding: 5px 28px 5px 10px;
  cursor: pointer;
  outline: none;
}

.select-arrow {
  position: absolute;
  right: 8px;
  width: 12px;
  height: 12px;
  color: var(--text-muted);
  pointer-events: none;
}

.results-count {
  font-size: 12px;
  color: var(--text-muted);
}

.reset-link {
  color: var(--accent);
  cursor: pointer;
  margin-left: 2px;
}

/* ── LIST ─────────────────────────────────────────────── */
.players-list {
  flex: 1;
  padding: 8px 0;
}

.player-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 16px;
  border-bottom: 1px solid var(--border);
  cursor: pointer;
  transition: background 0.15s;
  animation: rowIn 0.25s both;
}

.player-row:active { background: var(--bg-card2); }

@keyframes rowIn {
  from { opacity: 0; transform: translateY(8px); }
  to   { opacity: 1; transform: none; }
}

.player-avatar-wrap {
  position: relative;
  flex-shrink: 0;
}

.player-avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  object-fit: cover;
  background: var(--bg-card2);
}

.role-dot {
  position: absolute;
  bottom: 1px;
  right: 1px;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  border: 2px solid var(--bg);
}

.role-dot.role-POR { background: #e0a800; }
.role-dot.role-DIF { background: #22c47e; }
.role-dot.role-CEN { background: #4f8ef7; }
.role-dot.role-ATT { background: #f25757; }

.player-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.player-name {
  font-size: 15px;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.player-meta {
  display: flex;
  align-items: center;
  gap: 6px;
}

.role-badge {
  font-size: 10px;
  font-weight: 800;
  padding: 2px 6px;
  border-radius: 4px;
  letter-spacing: 0.3px;
}

.role-badge.role-POR { background: rgba(224,168,0,0.2);  color: #e0a800; }
.role-badge.role-DIF { background: rgba(34,196,126,0.2); color: #22c47e; }
.role-badge.role-CEN { background: rgba(79,142,247,0.2); color: #4f8ef7; }
.role-badge.role-ATT { background: rgba(242,87,87,0.2);  color: #f25757; }

.player-country {
  font-size: 12px;
  color: var(--text-muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.player-right {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
}

.price-tag {
  font-size: 13px;
  font-weight: 700;
  color: var(--accent);
}

.owned-tag {
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 20px;
  background: rgba(34,211,165,0.15);
  color: var(--success);
}

.chevron { width: 16px; height: 16px; color: var(--border); }

/* ── LOADING ──────────────────────────────────────────── */
.list-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 60px 20px;
  color: var(--text-muted);
  font-size: 14px;
}

/* ── EMPTY ────────────────────────────────────────────── */
.btn-reset {
  margin-top: 8px;
  padding: 10px 24px;
  background: var(--bg-card2);
  border: 1px solid var(--border);
  border-radius: 10px;
  color: var(--text);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}
</style>