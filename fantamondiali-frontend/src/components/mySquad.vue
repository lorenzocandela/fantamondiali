<template>
  <div class="squad-wrap">

    <!-- HERO -->
    <div class="squad-hero">
      <div class="logo-wrap">
        <img v-if="teamLogo" :src="teamLogo" class="team-logo" alt="Logo" />
        <div v-else class="logo-placeholder">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M12 2L2 7l10 5 10-5-10-5z"/>
            <path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/>
          </svg>
        </div>
      </div>
      <div class="hero-info">
        <h1 class="team-name">{{ teamName || 'La tua squadra' }}</h1>
        <span class="hero-sub">{{ totalPlayers }} / {{ maxPlayers }} giocatori</span>
      </div>
    </div>

    <!-- BUDGET -->
    <div class="budget-block">
      <div class="budget-top">
        <span class="budget-label">Budget disponibile</span>
        <span class="budget-val">{{ budgetLeft }} <em>cr.</em></span>
      </div>
      <div class="budget-track"><div class="budget-fill" :style="{ width: budgetPct + '%' }"></div></div>
      <div class="budget-bot">
        <span>Spesi: {{ budgetSpent }} cr.</span>
        <span>Totale: {{ budgetTotal }} cr.</span>
      </div>
    </div>

    <!-- CAMPIONATO NON INIZIATO -->
    <div v-if="!competitionStarted" class="pending-block">
      <div class="pending-badge">In attesa</div>
      <p class="pending-title">Campionato non ancora iniziato</p>
      <p class="pending-sub">Il mercato e aperto. Costruisci la tua rosa.</p>
      <div class="role-stats">
        <div v-for="r in roles" :key="r.v" class="rstat">
          <span class="rstat-n">{{ roleCount(r.v) }}</span>
          <span :class="['rstat-label', r.v]">{{ r.v }}</span>
        </div>
      </div>
    </div>

    <!-- ROSA -->
    <div class="roster">
      <div v-for="r in roles" :key="r.v" class="role-group">
        <div class="rg-header">
          <span :class="['role-badge', r.v]">{{ r.v }}</span>
          <span class="rg-title">{{ r.label }}</span>
          <span class="rg-count">{{ roleCount(r.v) }}</span>
        </div>

        <template v-if="playersByRole(r.v).length">
          <div v-for="p in playersByRole(r.v)" :key="p.id" class="sq-row">
            <img :src="p.image_path" :alt="p.name" class="sq-avatar" @error="e => e.target.src = fallback" />
            <div class="sq-info">
              <span class="sq-name">{{ p.name }}</span>
              <span class="sq-country">{{ p.country }}</span>
            </div>
            <div class="sq-right">
              <span class="sq-price">{{ p.price || '—' }}</span>
              <button class="btn-remove" @click="removePlayer(p)" title="Rimuovi">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </button>
            </div>
          </div>
        </template>

        <div v-else class="rg-empty">Nessun {{ r.label.toLowerCase() }} in rosa</div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({ user: Object, userTeamName: String });

const myPlayers          = ref([]);
const competitionStarted = ref(false);
const budgetTotal        = ref(500);
const budgetSpent        = ref(0);
const teamName           = ref('');
const teamLogo           = ref('');
const maxPlayers         = 25;

const fallback = `data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 40 40'%3E%3Ccircle cx='20' cy='20' r='20' fill='%232d2d2d'/%3E%3Ccircle cx='20' cy='16' r='7' fill='%233e3e42'/%3E%3Cellipse cx='20' cy='34' rx='12' ry='8' fill='%233e3e42'/%3E%3C/svg%3E`;

const roles = [
  { v: 'POR', label: 'Portieri' },
  { v: 'DIF', label: 'Difensori' },
  { v: 'CEN', label: 'Centrocampisti' },
  { v: 'ATT', label: 'Attaccanti' },
];

onMounted(async () => {
  teamName.value = props.userTeamName || '';
  if (!props.user) return;
  try {
    const res = await axios.get(`/api/users/${props.user.uid}/squad`);
    myPlayers.value          = res.data.players            || [];
    budgetSpent.value        = res.data.spent              || 0;
    budgetTotal.value        = res.data.budget             || 500;
    teamLogo.value           = res.data.team_logo          || '';
    competitionStarted.value = res.data.competition_started || false;
  } catch { /* mock */ }
});

const budgetLeft  = computed(() => budgetTotal.value - budgetSpent.value);
const budgetPct   = computed(() => Math.max(0, (budgetLeft.value / budgetTotal.value) * 100));
const totalPlayers = computed(() => myPlayers.value.length);
const roleCount   = r => myPlayers.value.filter(p => p.position === r).length;
const playersByRole = r => myPlayers.value.filter(p => p.position === r);

const removePlayer = p => {
  myPlayers.value = myPlayers.value.filter(x => x.id !== p.id);
};
</script>

<style scoped>
.squad-wrap { padding-bottom: 24px; }

/* HERO */
.squad-hero {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px 14px;
  border-bottom: 1px solid var(--border);
}

.logo-wrap { flex-shrink: 0; }

.team-logo {
  width: 56px; height: 56px;
  border-radius: var(--radius-lg);
  object-fit: contain;
  background: var(--bg-card);
  border: 1px solid var(--border);
}

.logo-placeholder {
  width: 56px; height: 56px;
  border-radius: var(--radius-lg);
  background: var(--bg-card);
  border: 1px solid var(--border);
  display: flex; align-items: center; justify-content: center;
  color: var(--text-muted);
}
.logo-placeholder svg { width: 24px; height: 24px; }

.hero-info { display: flex; flex-direction: column; gap: 3px; }
.team-name { font-size: 17px; font-weight: 700; letter-spacing: -0.2px; }
.hero-sub  { font-size: 12px; color: var(--text-muted); }

/* BUDGET */
.budget-block {
  margin: 12px 14px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 12px 14px;
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.budget-top { display: flex; justify-content: space-between; align-items: baseline; }
.budget-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.6px; color: var(--text-muted); font-weight: 600; }
.budget-val { font-size: 20px; font-weight: 800; color: var(--success); }
.budget-val em { font-size: 12px; font-weight: 500; color: var(--text-muted); font-style: normal; }

.budget-track { height: 3px; background: var(--border); border-radius: 2px; overflow: hidden; }
.budget-fill  { height: 100%; background: var(--success); border-radius: 2px; transition: width .4s; }

.budget-bot { display: flex; justify-content: space-between; font-size: 11px; color: var(--text-muted); }

/* PENDING */
.pending-block {
  margin: 0 14px 14px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 20px 14px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  text-align: center;
}

.pending-badge {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  padding: 3px 10px;
  border-radius: 3px;
  background: rgba(220,220,170,.1);
  color: var(--warning);
  border: 1px solid rgba(220,220,170,.2);
}

.pending-title { font-size: 14px; font-weight: 700; }
.pending-sub   { font-size: 12px; color: var(--text-muted); }

.role-stats {
  display: flex;
  width: 100%;
  background: var(--bg-panel);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  overflow: hidden;
  margin-top: 4px;
}

.rstat {
  flex: 1;
  display: flex; flex-direction: column; align-items: center;
  gap: 3px; padding: 10px 6px;
  border-right: 1px solid var(--border);
}
.rstat:last-child { border-right: none; }

.rstat-n { font-size: 20px; font-weight: 800; }

.rstat-label {
  font-size: 10px; font-weight: 800; letter-spacing: 0.3px;
}
.rstat-label.POR { color: var(--tag-por); }
.rstat-label.DIF { color: var(--tag-dif); }
.rstat-label.CEN { color: var(--tag-cen); }
.rstat-label.ATT { color: var(--tag-att); }

/* ROSTER */
.roster { padding: 0 14px; display: flex; flex-direction: column; gap: 10px; }

.role-group {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: hidden;
}

.rg-header {
  display: flex; align-items: center; gap: 8px;
  padding: 9px 12px;
  background: var(--bg-panel);
  border-bottom: 1px solid var(--border);
}

.rg-title { font-size: 12px; font-weight: 600; flex: 1; }

.rg-count {
  font-size: 11px; font-weight: 700;
  padding: 2px 7px; border-radius: 3px;
  background: var(--border);
  color: var(--text-muted);
}

.sq-row {
  display: flex; align-items: center; gap: 10px;
  padding: 8px 12px;
  border-bottom: 1px solid var(--border);
  transition: background .1s;
}
.sq-row:last-child { border-bottom: none; }
.sq-row:active { background: var(--bg-panel); }

.sq-avatar {
  width: 34px; height: 34px;
  border-radius: 50%; object-fit: cover;
  background: var(--bg-panel); flex-shrink: 0;
}

.sq-info { flex: 1; display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.sq-name { font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sq-country { font-size: 11px; color: var(--text-muted); }

.sq-right { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.sq-price { font-size: 12px; font-weight: 700; color: var(--accent-btn); }

.btn-remove {
  width: 26px; height: 26px;
  background: rgba(244,71,71,.08);
  border: none; border-radius: var(--radius);
  color: var(--danger);
  cursor: pointer; display: flex; align-items: center; justify-content: center;
  transition: background .1s;
}
.btn-remove:active { background: rgba(244,71,71,.2); }
.btn-remove svg { width: 12px; height: 12px; }

.rg-empty { padding: 12px; font-size: 12px; color: var(--text-muted); font-style: italic; }
</style>