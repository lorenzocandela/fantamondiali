<template>
  <div class="list-wrap">

    <!-- SEARCH & FILTERS -->
    <div class="filters">
      <div class="search-row">
        <div class="search-box" :class="{ focused: searchFocused }">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="s-ico">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
          <input
            v-model="searchQuery"
            class="s-input"
            placeholder="Cerca giocatore, nazione..."
            @focus="searchFocused = true"
            @blur="searchFocused = false"
          />
          <button v-if="searchQuery" class="s-clear" @click="searchQuery = ''">
            <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10" opacity=".3"/><line x1="15" y1="9" x2="9" y2="15" stroke="white" stroke-width="2" stroke-linecap="round"/><line x1="9" y1="9" x2="15" y2="15" stroke="white" stroke-width="2" stroke-linecap="round"/></svg>
          </button>
        </div>
      </div>

      <div class="pills-row">
        <button
          v-for="r in roles" :key="r.v"
          :class="['pill', { active: selectedRole === r.v }]"
          :style="selectedRole === r.v ? { background: r.bg, color: r.color, borderColor: 'transparent' } : {}"
          @click="selectedRole = selectedRole === r.v ? '' : r.v"
        >{{ r.v }}</button>

        <div class="pill-sep"></div>

        <div class="select-pill-wrap">
          <select v-model="selectedCountry" class="select-pill">
            <option value="">Nazione</option>
            <option v-for="c in availableCountries" :key="c" :value="c">{{ c }}</option>
          </select>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="sel-ico"><polyline points="6 9 12 15 18 9"/></svg>
        </div>
      </div>

      <div class="filter-info">
        <span class="f-count">{{ filteredPlayers.length }} giocatori</span>
        <button v-if="hasFilters" class="f-reset" @click="resetFilters">Azzera</button>
      </div>
    </div>

    <!-- LOADING -->
    <div v-if="loading" class="center-state">
      <div class="spinner"></div>
    </div>

    <!-- LIST -->
    <div v-else-if="filteredPlayers.length" class="p-list">
      <div
        v-for="(p, i) in filteredPlayers"
        :key="p.id"
        class="p-row"
        :style="{ animationDelay: `${Math.min(i,25)*18}ms` }"
      >
        <div class="p-avatar-wrap">
          <img :src="p.image_path" :alt="p.name" class="p-avatar" @error="e => e.target.src = fallback" />
          <span :class="['p-dot', p.position]"></span>
        </div>

        <div class="p-info">
          <span class="p-name">{{ p.name }}</span>
          <span class="p-meta">
            <span :class="['rbadge', p.position]">{{ p.position }}</span>
            <span class="p-country">{{ p.country }}</span>
          </span>
        </div>

        <div class="p-right">
          <span v-if="p.owned_by" class="owned-badge">Preso</span>
          <span v-else-if="p.price" class="p-price">{{ p.price }}</span>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="p-chev"><polyline points="9 18 15 12 9 6"/></svg>
        </div>
      </div>
    </div>

    <!-- EMPTY -->
    <div v-else class="ph-screen">
      <div class="ph-ico">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
          <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
      </div>
      <p class="ph-title">Nessun risultato</p>
      <p class="ph-sub">Prova con altri filtri di ricerca</p>
      <button class="btn-ghost" style="margin-top:8px; padding: 10px 24px; width:auto;" @click="resetFilters">Azzera filtri</button>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

defineProps({ user: Object });

const players         = ref([]);
const loading         = ref(false);
const searchQuery     = ref('');
const selectedRole    = ref('');
const selectedCountry = ref('');
const searchFocused   = ref(false);

const roles = [
  { v: 'POR', bg: 'var(--yellow-d)', color: 'var(--por-c)' },
  { v: 'DIF', bg: 'var(--green-d)',  color: 'var(--dif-c)' },
  { v: 'CEN', bg: 'var(--accent-d)', color: 'var(--cen-c)' },
  { v: 'ATT', bg: 'var(--red-d)',    color: 'var(--att-c)' },
];

const fallback = `data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 40 40'%3E%3Ccircle cx='20' cy='20' r='20' fill='%231c1c1e'/%3E%3Ccircle cx='20' cy='16' r='7' fill='%232c2c2e'/%3E%3Cellipse cx='20' cy='34' rx='12' ry='8' fill='%232c2c2e'/%3E%3C/svg%3E`;

onMounted(async () => {
  loading.value = true;
  try {
    const res = await axios.get('/api/players');
    players.value = res.data;
  } catch (e) { console.error(e); }
  finally { loading.value = false; }
});

const availableCountries = computed(() =>
  [...new Set(players.value.map(p => p.country))].sort()
);
const hasFilters = computed(() => searchQuery.value || selectedRole.value || selectedCountry.value);
const filteredPlayers = computed(() => {
  const q = searchQuery.value.toLowerCase();
  return players.value.filter(p =>
    (p.name.toLowerCase().includes(q) || p.country.toLowerCase().includes(q)) &&
    (!selectedRole.value    || p.position === selectedRole.value) &&
    (!selectedCountry.value || p.country  === selectedCountry.value)
  );
});
const resetFilters = () => { searchQuery.value = ''; selectedRole.value = ''; selectedCountry.value = ''; };
</script>

<style scoped>
.list-wrap { display: flex; flex-direction: column; }

/* FILTERS */
.filters {
  position: sticky; top: 0; z-index: 10;
  background: var(--bg); border-bottom: 1px solid var(--sep);
  padding: 10px 16px 8px;
  display: flex; flex-direction: column; gap: 8px;
}

.search-box {
  display: flex; align-items: center; gap: 8px;
  background: var(--bg-2); border-radius: var(--r);
  padding: 0 12px; transition: background .15s;
}
.search-box.focused { background: var(--bg-1); outline: 1px solid var(--accent); }

.s-ico { width: 15px; height: 15px; color: var(--text-3); flex-shrink: 0; }

.s-input {
  flex: 1; background: none; border: none; outline: none;
  color: var(--text); font-size: 15px; padding: 11px 0;
}
.s-input::placeholder { color: var(--text-3); }

.s-clear { background: none; border: none; cursor: pointer; display: flex; padding: 0; color: var(--text-3); }
.s-clear svg { width: 18px; height: 18px; }

/* pills */
.pills-row { display: flex; align-items: center; gap: 6px; overflow-x: auto; scrollbar-width: none; }
.pills-row::-webkit-scrollbar { display: none; }

.pill {
  flex-shrink: 0; padding: 5px 12px; border-radius: 20px;
  border: 1px solid var(--sep2); background: var(--bg-2);
  color: var(--text-2); font-size: 12px; font-weight: 700;
  cursor: pointer; transition: all .15s; letter-spacing: 0.3px;
}

.pill-sep { width: 1px; height: 16px; background: var(--sep2); flex-shrink: 0; }

.select-pill-wrap { position: relative; display: flex; align-items: center; flex-shrink: 0; }
.select-pill {
  appearance: none; background: var(--bg-2); border: 1px solid var(--sep2);
  border-radius: 20px; color: var(--text-2); font-size: 12px; font-weight: 600;
  padding: 5px 24px 5px 10px; cursor: pointer; outline: none;
}
.sel-ico { position: absolute; right: 7px; width: 11px; height: 11px; color: var(--text-3); pointer-events: none; }

.filter-info { display: flex; align-items: center; gap: 8px; }
.f-count { font-size: 12px; color: var(--text-2); }
.f-reset { font-size: 12px; color: var(--accent); background: none; border: none; cursor: pointer; padding: 0; }

/* LIST */
.p-row {
  display: flex; align-items: center; gap: 12px; padding: 10px 16px;
  border-bottom: 1px solid var(--sep); cursor: pointer;
  transition: background .1s;
  animation: rowIn .22s both;
}
.p-row:active { background: var(--bg-1); }

@keyframes rowIn {
  from { opacity: 0; transform: translateY(5px); }
  to   { opacity: 1; transform: none; }
}

.p-avatar-wrap { position: relative; flex-shrink: 0; }
.p-avatar { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; background: var(--bg-2); display: block; }

.p-dot {
  position: absolute; bottom: 0; right: 0;
  width: 10px; height: 10px; border-radius: 50%; border: 2px solid var(--bg);
}
.p-dot.POR { background: var(--por-c); }
.p-dot.DIF { background: var(--dif-c); }
.p-dot.CEN { background: var(--cen-c); }
.p-dot.ATT { background: var(--att-c); }

.p-info { flex: 1; display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.p-name { font-size: 15px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.p-meta { display: flex; align-items: center; gap: 6px; }
.p-country { font-size: 12px; color: var(--text-2); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.p-right { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
.p-price { font-size: 13px; font-weight: 600; color: var(--accent); }

.owned-badge {
  font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 10px;
  background: var(--green-d); color: var(--green);
}

.p-chev { width: 15px; height: 15px; color: var(--text-3); }

.center-state { display: flex; justify-content: center; padding: 60px; }
</style>