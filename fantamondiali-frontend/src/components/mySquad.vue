<template>
  <div class="squad-wrap">

    <!-- HERO -->
    <div class="hero">
      <div class="hero-logo-wrap">
        <img v-if="teamLogo" :src="teamLogo" class="hero-logo" alt="Logo" />
        <div v-else class="hero-logo-ph">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
            <path d="M12 2L2 7l10 5 10-5-10-5z"/>
            <path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/>
          </svg>
        </div>
      </div>
      <div class="hero-text">
        <h1 class="hero-name">{{ teamName || 'La tua squadra' }}</h1>
        <span class="hero-sub">{{ totalPlayers }} / {{ maxPlayers }} giocatori</span>
      </div>
    </div>

    <!-- BUDGET -->
    <div class="grp" style="margin-top:12px;">
      <div class="grp-card budget-card">
        <div class="budget-row">
          <div>
            <div class="budget-lbl">Budget disponibile</div>
            <div class="budget-val">{{ budgetLeft }} <span class="budget-unit">crediti</span></div>
          </div>
          <div class="budget-pct-wrap">
            <svg viewBox="0 0 36 36" class="pct-ring">
              <circle cx="18" cy="18" r="15.9" fill="none" stroke="var(--sep2)" stroke-width="2.5"/>
              <circle
                cx="18" cy="18" r="15.9" fill="none"
                stroke="var(--green)" stroke-width="2.5"
                stroke-dasharray="100 100"
                :stroke-dashoffset="100 - budgetPct"
                stroke-linecap="round"
                transform="rotate(-90 18 18)"
              />
            </svg>
            <span class="pct-num">{{ Math.round(budgetPct) }}%</span>
          </div>
        </div>
        <div class="budget-track"><div class="budget-fill" :style="{ width: budgetPct + '%' }"></div></div>
        <div class="budget-detail">
          <span>Spesi: {{ budgetSpent }} cr.</span>
          <span>Totale: {{ budgetTotal }} cr.</span>
        </div>
      </div>
    </div>

    <!-- CAMPIONATO NON INIZIATO -->
    <div v-if="!competitionStarted" class="grp">
      <div class="grp-card pending-card">
        <div class="pending-pill">In attesa</div>
        <p class="pending-title">Campionato non ancora iniziato</p>
        <p class="pending-sub">Il mercato e aperto. Costruisci la tua rosa prima del fischio d'inizio.</p>
        <div class="role-counters">
          <div v-for="r in roles" :key="r.v" class="rc-item">
            <span class="rc-n">{{ roleCount(r.v) }}</span>
            <span class="rbadge" :class="r.v">{{ r.v }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ROSA -->
    <div v-for="r in roles" :key="r.v" class="grp">
      <div class="grp-label-row">
        <span class="rbadge" :class="r.v">{{ r.v }}</span>
        <span class="grp-label">{{ r.label }}</span>
        <span class="grp-count">{{ roleCount(r.v) }}</span>
      </div>
      <div class="grp-card">
        <template v-if="playersByRole(r.v).length">
          <div v-for="p in playersByRole(r.v)" :key="p.id" class="grp-row">
            <img :src="p.image_path" :alt="p.name" class="sq-av" @error="e => e.target.src = fallback" />
            <div class="row-text">
              <span class="row-title">{{ p.name }}</span>
              <span class="row-sub">{{ p.country }}</span>
            </div>
            <div class="row-right">
              <span v-if="p.price" class="sq-price">{{ p.price }}</span>
              <button class="rm-btn" @click="removePlayer(p)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                  <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
              </button>
            </div>
          </div>
        </template>
        <div v-else class="grp-row" style="color: var(--text-3); font-size:14px;">
          Nessun {{ r.label.toLowerCase() }} in rosa
        </div>
      </div>
    </div>

    <div style="height:16px;"></div>

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

const fallback = `data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 40 40'%3E%3Ccircle cx='20' cy='20' r='20' fill='%231c1c1e'/%3E%3Ccircle cx='20' cy='16' r='7' fill='%232c2c2e'/%3E%3Cellipse cx='20' cy='34' rx='12' ry='8' fill='%232c2c2e'/%3E%3C/svg%3E`;

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
    myPlayers.value          = res.data.players             || [];
    budgetSpent.value        = res.data.spent               || 0;
    budgetTotal.value        = res.data.budget              || 500;
    teamLogo.value           = res.data.team_logo           || '';
    competitionStarted.value = res.data.competition_started || false;
  } catch { /* mock */ }
});

const budgetLeft    = computed(() => budgetTotal.value - budgetSpent.value);
const budgetPct     = computed(() => Math.max(0, Math.min(100, (budgetLeft.value / budgetTotal.value) * 100)));
const totalPlayers  = computed(() => myPlayers.value.length);
const roleCount     = r => myPlayers.value.filter(p => p.position === r).length;
const playersByRole = r => myPlayers.value.filter(p => p.position === r);
const removePlayer  = p => { myPlayers.value = myPlayers.value.filter(x => x.id !== p.id); };
</script>

<style scoped>
.squad-wrap { padding-bottom: 24px; }

/* HERO */
.hero {
  display: flex; align-items: center; gap: 14px;
  padding: 16px 16px 12px;
  border-bottom: 1px solid var(--sep);
}

.hero-logo-wrap { flex-shrink: 0; }

.hero-logo {
  width: 56px; height: 56px; border-radius: var(--r-lg);
  object-fit: contain; background: var(--bg-1); border: 1px solid var(--sep2);
}

.hero-logo-ph {
  width: 56px; height: 56px; border-radius: var(--r-lg);
  background: var(--bg-1); border: 1px solid var(--sep2);
  display: flex; align-items: center; justify-content: center; color: var(--text-3);
}
.hero-logo-ph svg { width: 24px; height: 24px; }

.hero-text { display: flex; flex-direction: column; gap: 2px; }
.hero-name { font-size: 20px; font-weight: 700; letter-spacing: -0.3px; }
.hero-sub  { font-size: 13px; color: var(--text-2); }

/* BUDGET */
.budget-card { padding: 16px; }

.budget-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }

.budget-lbl { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.4px; color: var(--text-2); margin-bottom: 4px; }
.budget-val { font-size: 24px; font-weight: 700; letter-spacing: -0.5px; color: var(--green); }
.budget-unit { font-size: 13px; font-weight: 400; color: var(--text-2); }

.budget-pct-wrap { position: relative; width: 44px; height: 44px; flex-shrink: 0; }
.pct-ring { width: 44px; height: 44px; }
.pct-num {
  position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
  font-size: 10px; font-weight: 700; color: var(--text-2);
}

.budget-track { height: 3px; background: var(--sep2); border-radius: 2px; overflow: hidden; margin-bottom: 8px; }
.budget-fill { height: 100%; background: var(--green); border-radius: 2px; transition: width .4s ease; }

.budget-detail { display: flex; justify-content: space-between; font-size: 12px; color: var(--text-2); }

/* PENDING */
.pending-card { padding: 20px; display: flex; flex-direction: column; align-items: center; gap: 8px; text-align: center; }

.pending-pill {
  font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px;
  padding: 3px 10px; border-radius: 10px;
  background: var(--yellow-d); color: var(--yellow); border: 1px solid rgba(255,214,10,.2);
}

.pending-title { font-size: 15px; font-weight: 700; }
.pending-sub   { font-size: 13px; color: var(--text-2); line-height: 1.5; max-width: 260px; }

.role-counters {
  display: flex; gap: 0; margin-top: 4px; width: 100%;
  background: var(--bg-2); border-radius: var(--r); overflow: hidden;
}

.rc-item {
  flex: 1; display: flex; flex-direction: column; align-items: center; gap: 5px;
  padding: 12px 8px; border-right: 1px solid var(--sep);
}
.rc-item:last-child { border-right: none; }

.rc-n { font-size: 22px; font-weight: 800; line-height: 1; }

/* ROSTER GROUP LABEL */
.grp-label-row {
  display: flex; align-items: center; gap: 8px;
  padding: 0 4px; margin-bottom: 6px;
}
.grp-label { font-size: 13px; font-weight: 600; flex: 1; }
.grp-count {
  font-size: 12px; color: var(--text-2); background: var(--bg-2);
  padding: 2px 8px; border-radius: 10px; font-weight: 600;
}

/* SQUAD ROW (overrides shared grp-row) */
.sq-av {
  width: 36px; height: 36px; border-radius: 50%;
  object-fit: cover; background: var(--bg-2); flex-shrink: 0;
}

.sq-price { font-size: 13px; font-weight: 600; color: var(--accent); }

.rm-btn {
  width: 28px; height: 28px; border-radius: 8px;
  background: var(--red-d); border: none; color: var(--red);
  cursor: pointer; display: flex; align-items: center; justify-content: center;
  transition: background .1s;
}
.rm-btn:active { background: rgba(255,69,58,.3); }
.rm-btn svg { width: 12px; height: 12px; }
</style>