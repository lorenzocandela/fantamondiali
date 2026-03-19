<template>
  <div class="listone-wrap">

    <!-- FILTERS -->
    <div class="filters-bar">
      <div class="search-box">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="ico">
          <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input v-model="searchQuery" class="search-input" placeholder="Cerca giocatore..." />
        <button v-if="searchQuery" class="clear-btn" @click="searchQuery = ''">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>

      <div class="chips-row">
        <button
          v-for="r in roles" :key="r"
          :class="['chip', { active: selectedRole === r }]"
          @click="selectedRole = selectedRole === r ? '' : r"
        >{{ r }}</button>

        <div class="chip-sep"></div>

        <div class="select-wrap">
          <select v-model="selectedCountry" class="chip-select">
            <option value="">Nazione</option>
            <option v-for="c in availableCountries" :key="c" :value="c">{{ c }}</option>
          </select>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="sel-arrow"><polyline points="6 9 12 15 18 9"/></svg>
        </div>
      </div>

      <div class="filter-meta">
        <span class="results-n">{{ filteredPlayers.length }} giocatori</span>
        <button v-if="hasFilters" class="reset-link" @click="resetFilters">Azzera filtri</button>
      </div>
    </div>

    <!-- LOADING -->
    <div v-if="loading" class="center-pad">
      <div class="spinner"></div>
    </div>

    <!-- LIST -->
    <div v-else-if="filteredPlayers.length" class="player-list">
      <div
        v-for="(p, i) in filteredPlayers"
        :key="p.id"
        class="player-row"
        :style="{ animationDelay: `${Math.min(i,30)*20}ms` }"
      >
        <div class="avatar-wrap">
          <img :src="p.image_path" :alt="p.name" class="avatar" @error="e => e.target.src = fallback" />
          <span :class="['role-dot', p.position]"></span>
        </div>

        <div class="pinfo">
          <span class="pname">{{ p.name }}</span>
          <span class="pmeta">
            <span :class="['role-badge', p.position]">{{ p.position }}</span>
            <span class="pcountry">{{ p.country }}</span>
          </span>
        </div>

        <div class="pright">
          <span v-if="p.owned_by" class="owned-tag">Preso</span>
          <span v-else-if="p.price" class="price-tag">{{ p.price }}</span>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="chevron"><polyline points="9 18 15 12 9 6"/></svg>
        </div>
      </div>
    </div>

    <!-- EMPTY -->
    <div v-else class="placeholder-screen">
      <div class="placeholder-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      </div>
      <p class="placeholder-title">Nessun giocatore trovato</p>
      <p class="placeholder-sub">Modifica i filtri per vedere altri risultati</p>
      <button class="btn-secondary" @click="resetFilters">Azzera filtri</button>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

defineProps({ user: Object });

const players      = ref([]);
const loading      = ref(false);
const searchQuery  = ref('');
const selectedRole = ref('');
const selectedCountry = ref('');
const roles = ['POR', 'DIF', 'CEN', 'ATT'];

const fallback = `data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 40 40'%3E%3Ccircle cx='20' cy='20' r='20' fill='%232d2d2d'/%3E%3Ccircle cx='20' cy='16' r='7' fill='%233e3e42'/%3E%3Cellipse cx='20' cy='34' rx='12' ry='8' fill='%233e3e42'/%3E%3C/svg%3E`;

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

const availableCountries = computed(() =>
  [...new Set(players.value.map(p => p.country))].sort()
);

const hasFilters = computed(() => searchQuery.value || selectedRole.value || selectedCountry.value);

const filteredPlayers = computed(() => {
  const q = searchQuery.value.toLowerCase();
  return players.value.filter(p =>
    p.name.toLowerCase().includes(q) &&
    (!selectedRole.value    || p.position === selectedRole.value) &&
    (!selectedCountry.value || p.country  === selectedCountry.value)
  );
});

const resetFilters = () => {
  searchQuery.value = '';
  selectedRole.value = '';
  selectedCountry.value = '';
};
</script>

<style scoped>
.listone-wrap {
  display: flex;
  flex-direction: column;
}

/* FILTERS */
.filters-bar {
  position: sticky;
  top: 0;
  z-index: 10;
  background: var(--bg);
  border-bottom: 1px solid var(--border);
  padding: 10px 14px 8px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.search-box {
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--bg-input);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 0 10px;
  transition: border-color .15s;
}
.search-box:focus-within { border-color: var(--border-focus); }

.ico { width: 14px; height: 14px; color: var(--text-muted); flex-shrink: 0; }

.search-input {
  flex: 1;
  background: none;
  border: none;
  outline: none;
  color: var(--text);
  font-size: 13px;
  padding: 9px 0;
}
.search-input::placeholder { color: var(--text-dim); }

.clear-btn {
  background: none; border: none;
  color: var(--text-muted); cursor: pointer;
  display: flex; padding: 0;
}
.clear-btn svg { width: 13px; height: 13px; }

.chips-row {
  display: flex;
  align-items: center;
  gap: 5px;
  overflow-x: auto;
  scrollbar-width: none;
}
.chips-row::-webkit-scrollbar { display: none; }

.chip {
  flex-shrink: 0;
  padding: 4px 10px;
  border-radius: 3px;
  border: 1px solid var(--border);
  background: var(--bg-card);
  color: var(--text-muted);
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
  transition: all .15s;
  letter-spacing: 0.3px;
}

.chip.active { background: var(--accent-btn); border-color: var(--accent-btn); color: #fff; }

.chip-sep { width: 1px; height: 16px; background: var(--border); flex-shrink: 0; }

.select-wrap { position: relative; display: flex; align-items: center; flex-shrink: 0; }

.chip-select {
  appearance: none;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 3px;
  color: var(--text-muted);
  font-size: 11px;
  font-weight: 600;
  padding: 4px 22px 4px 8px;
  cursor: pointer;
  outline: none;
}

.sel-arrow { position: absolute; right: 6px; width: 10px; height: 10px; color: var(--text-muted); pointer-events: none; }

.filter-meta {
  display: flex;
  align-items: center;
  gap: 8px;
}

.results-n { font-size: 11px; color: var(--text-muted); }

.reset-link {
  font-size: 11px;
  color: var(--accent-btn);
  background: none;
  border: none;
  cursor: pointer;
  padding: 0;
}

/* LIST */
.player-row {
  display: flex;
  align-items: center;
  gap: 11px;
  padding: 9px 14px;
  border-bottom: 1px solid var(--border);
  cursor: pointer;
  transition: background .1s;
  animation: rowIn .2s both;
}
.player-row:active { background: var(--bg-panel); }

@keyframes rowIn {
  from { opacity: 0; transform: translateY(6px); }
  to   { opacity: 1; transform: none; }
}

.avatar-wrap { position: relative; flex-shrink: 0; }

.avatar {
  width: 40px; height: 40px;
  border-radius: 50%;
  object-fit: cover;
  background: var(--bg-card);
  display: block;
}

.role-dot {
  position: absolute;
  bottom: 0; right: 0;
  width: 9px; height: 9px;
  border-radius: 50%;
  border: 2px solid var(--bg);
}
.role-dot.POR { background: var(--tag-por); }
.role-dot.DIF { background: var(--tag-dif); }
.role-dot.CEN { background: var(--tag-cen); }
.role-dot.ATT { background: var(--tag-att); }

.pinfo { flex: 1; display: flex; flex-direction: column; gap: 3px; min-width: 0; }

.pname { font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.pmeta { display: flex; align-items: center; gap: 6px; }

.pcountry { font-size: 11px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.pright { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }

.price-tag { font-size: 12px; font-weight: 700; color: var(--accent-btn); }

.owned-tag {
  font-size: 10px; font-weight: 700;
  padding: 2px 7px; border-radius: 3px;
  background: rgba(78,201,176,.12);
  color: var(--success);
}

.chevron { width: 14px; height: 14px; color: var(--border); }

/* MISC */
.center-pad { display: flex; justify-content: center; padding: 60px 0; }

.btn-secondary {
  margin-top: 8px;
  padding: 8px 20px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  color: var(--text);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}
</style>