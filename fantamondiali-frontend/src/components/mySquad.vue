<template>
  <div class="squad-wrap">

    <!-- HEADER SQUADRA -->
    <div class="squad-hero">
      <div class="squad-logo-wrap">
        <img
          v-if="teamLogo"
          :src="teamLogo"
          class="squad-logo"
          alt="Logo squadra"
        />
        <div v-else class="squad-logo-placeholder">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M12 2L2 7l10 5 10-5-10-5z"/>
            <path d="M2 17l10 5 10-5"/>
            <path d="M2 12l10 5 10-5"/>
          </svg>
        </div>
      </div>
      <div class="squad-info">
        <h1 class="squad-name">{{ teamName || 'La tua squadra' }}</h1>
        <span class="squad-sub">{{ totalPlayers }} / {{ maxPlayers }} giocatori</span>
      </div>
    </div>

    <!-- BUDGET BAR -->
    <div class="budget-card">
      <div class="budget-row">
        <span class="budget-label">Budget disponibile</span>
        <span class="budget-value">{{ budgetLeft }} <span class="budget-unit">cr.</span></span>
      </div>
      <div class="budget-bar-track">
        <div class="budget-bar-fill" :style="{ width: budgetPercent + '%' }"></div>
      </div>
      <div class="budget-sub-row">
        <span class="budget-spent">Spesi: {{ budgetSpent }} cr.</span>
        <span class="budget-total">Totale: {{ budgetTotal }} cr.</span>
      </div>
    </div>

    <!-- COMPETIZIONE NON INIZIATA -->
    <div v-if="!competitionStarted" class="competition-pending">
      <div class="pending-icon-wrap">
        <span class="pending-icon">⏳</span>
        <div class="pending-ring"></div>
      </div>
      <h2 class="pending-title">Campionato non ancora iniziato</h2>
      <p class="pending-sub">Il mercato è aperto. Costruisci la tua rosa in attesa del fischio d'inizio.</p>

      <div class="pending-stats">
        <div class="pstat">
          <span class="pstat-val">{{ roleCount('POR') }}</span>
          <span class="pstat-label pstat-POR">POR</span>
        </div>
        <div class="pstat-divider"></div>
        <div class="pstat">
          <span class="pstat-val">{{ roleCount('DIF') }}</span>
          <span class="pstat-label pstat-DIF">DIF</span>
        </div>
        <div class="pstat-divider"></div>
        <div class="pstat">
          <span class="pstat-val">{{ roleCount('CEN') }}</span>
          <span class="pstat-label pstat-CEN">CEN</span>
        </div>
        <div class="pstat-divider"></div>
        <div class="pstat">
          <span class="pstat-val">{{ roleCount('ATT') }}</span>
          <span class="pstat-label pstat-ATT">ATT</span>
        </div>
      </div>
    </div>

    <!-- ROSA GIOCATORI -->
    <div class="roster-section">

      <div v-for="role in roles" :key="role.value" class="role-group">
        <div class="role-group-header">
          <span :class="['role-tag', `role-${role.value}`]">{{ role.value }}</span>
          <span class="role-group-title">{{ role.label }}</span>
          <span class="role-count">{{ roleCount(role.value) }}</span>
        </div>

        <div v-if="playersByRole(role.value).length" class="role-players">
          <div
            v-for="player in playersByRole(role.value)"
            :key="player.id"
            class="squad-player-row"
          >
            <img
              :src="player.image_path"
              :alt="player.name"
              class="sq-avatar"
              @error="e => e.target.src = fallbackAvatar"
            />
            <div class="sq-info">
              <span class="sq-name">{{ player.name }}</span>
              <span class="sq-country">{{ player.country }}</span>
            </div>
            <div class="sq-right">
              <span class="sq-price">{{ player.price || '–' }}</span>
              <button class="sq-remove" @click.stop="removePlayer(player)" title="Rimuovi">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <line x1="18" y1="6" x2="6" y2="18"/>
                  <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
              </button>
            </div>
          </div>
        </div>

        <div v-else class="role-empty">
          <span>Nessun {{ role.label.toLowerCase() }} in rosa</span>
        </div>
      </div>

    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({ user: Object });

const myPlayers          = ref([]);
const competitionStarted = ref(false);
const budgetTotal        = ref(500);
const budgetSpent        = ref(0);
const teamName           = ref('');
const teamLogo           = ref('');
const maxPlayers         = 25;

const fallbackAvatar = `data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 40 40'%3E%3Ccircle cx='20' cy='20' r='20' fill='%231e1e2e'/%3E%3Ccircle cx='20' cy='16' r='7' fill='%232a2a3e'/%3E%3Cellipse cx='20' cy='34' rx='12' ry='8' fill='%232a2a3e'/%3E%3C/svg%3E`;

const roles = [
  { value: 'POR', label: 'Portieri' },
  { value: 'DIF', label: 'Difensori' },
  { value: 'CEN', label: 'Centrocampisti' },
  { value: 'ATT', label: 'Attaccanti' },
];

onMounted(async () => {
  if (!props.user) return;
  try {
    const res = await axios.get(`/api/users/${props.user.uid}/squad`);
    myPlayers.value   = res.data.players   || [];
    budgetSpent.value = res.data.spent      || 0;
    budgetTotal.value = res.data.budget     || 500;
    teamName.value    = res.data.team_name  || '';
    teamLogo.value    = res.data.team_logo  || '';
    competitionStarted.value = res.data.competition_started || false;
  } catch (e) {
    // dati mock finché non c'è API
    myPlayers.value = [];
  }
});

const budgetLeft    = computed(() => budgetTotal.value - budgetSpent.value);
const budgetPercent = computed(() => Math.max(0, (budgetLeft.value / budgetTotal.value) * 100));
const totalPlayers  = computed(() => myPlayers.value.length);

const roleCount     = (r) => myPlayers.value.filter(p => p.position === r).length;
const playersByRole = (r) => myPlayers.value.filter(p => p.position === r);

const removePlayer = async (player) => {
  myPlayers.value = myPlayers.value.filter(p => p.id !== player.id);
  // TODO: axios.delete(`/api/users/${props.user.uid}/squad/${player.id}`)
};
</script>

<style scoped>
.squad-wrap {
  padding-bottom: 24px;
}

/* ── HERO ─────────────────────────────────────────────── */
.squad-hero {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 20px 16px 16px;
}

.squad-logo-wrap {
  flex-shrink: 0;
}

.squad-logo {
  width: 64px;
  height: 64px;
  border-radius: 16px;
  object-fit: contain;
  background: var(--bg-card2);
  border: 1px solid var(--border);
}

.squad-logo-placeholder {
  width: 64px;
  height: 64px;
  border-radius: 16px;
  background: var(--bg-card2);
  border: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-muted);
}

.squad-logo-placeholder svg { width: 28px; height: 28px; }

.squad-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.squad-name {
  font-size: 20px;
  font-weight: 800;
  letter-spacing: -0.3px;
}

.squad-sub {
  font-size: 13px;
  color: var(--text-muted);
}

/* ── BUDGET ───────────────────────────────────────────── */
.budget-card {
  margin: 0 16px 16px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.budget-row {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
}

.budget-label {
  font-size: 12px;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-weight: 600;
}

.budget-value {
  font-size: 22px;
  font-weight: 800;
  color: var(--success);
}

.budget-unit {
  font-size: 13px;
  font-weight: 600;
  color: var(--text-muted);
}

.budget-bar-track {
  height: 4px;
  background: var(--border);
  border-radius: 2px;
  overflow: hidden;
}

.budget-bar-fill {
  height: 100%;
  background: var(--success);
  border-radius: 2px;
  transition: width 0.5s ease;
}

.budget-sub-row {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: var(--text-muted);
}

/* ── COMPETITION PENDING ──────────────────────────────── */
.competition-pending {
  margin: 0 16px 20px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 28px 20px;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
}

.pending-icon-wrap {
  position: relative;
  width: 64px;
  height: 64px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 4px;
}

.pending-icon {
  font-size: 32px;
  position: relative;
  z-index: 1;
}

.pending-ring {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  border: 2px solid var(--accent);
  opacity: 0.3;
  animation: pulse-ring 2s ease-in-out infinite;
}

@keyframes pulse-ring {
  0%, 100% { transform: scale(1); opacity: 0.3; }
  50%       { transform: scale(1.15); opacity: 0.1; }
}

.pending-title {
  font-size: 17px;
  font-weight: 700;
}

.pending-sub {
  font-size: 13px;
  color: var(--text-muted);
  line-height: 1.5;
  max-width: 280px;
}

.pending-stats {
  display: flex;
  align-items: center;
  gap: 0;
  margin-top: 8px;
  background: var(--bg-card2);
  border: 1px solid var(--border);
  border-radius: 12px;
  overflow: hidden;
  width: 100%;
}

.pstat {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 12px 8px;
}

.pstat-val {
  font-size: 22px;
  font-weight: 800;
  line-height: 1;
}

.pstat-label {
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.3px;
}

.pstat-divider {
  width: 1px;
  height: 32px;
  background: var(--border);
  flex-shrink: 0;
}

.pstat-POR { color: #e0a800; }
.pstat-DIF { color: #22c47e; }
.pstat-CEN { color: #4f8ef7; }
.pstat-ATT { color: #f25757; }

/* ── ROSTER ───────────────────────────────────────────── */
.roster-section {
  padding: 0 16px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.role-group {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  overflow: hidden;
}

.role-group-header {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  border-bottom: 1px solid var(--border);
  background: var(--bg-card2);
}

.role-tag {
  font-size: 10px;
  font-weight: 800;
  padding: 3px 7px;
  border-radius: 5px;
  letter-spacing: 0.3px;
}

.role-tag.role-POR { background: rgba(224,168,0,0.2);  color: #e0a800; }
.role-tag.role-DIF { background: rgba(34,196,126,0.2); color: #22c47e; }
.role-tag.role-CEN { background: rgba(79,142,247,0.2); color: #4f8ef7; }
.role-tag.role-ATT { background: rgba(242,87,87,0.2);  color: #f25757; }

.role-group-title {
  font-size: 13px;
  font-weight: 700;
  flex: 1;
}

.role-count {
  font-size: 12px;
  color: var(--text-muted);
  background: var(--border);
  padding: 2px 8px;
  border-radius: 20px;
  font-weight: 700;
}

.squad-player-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  border-bottom: 1px solid var(--border);
  transition: background 0.15s;
}

.squad-player-row:last-child { border-bottom: none; }
.squad-player-row:active { background: var(--bg-card2); }

.sq-avatar {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  object-fit: cover;
  background: var(--bg-card2);
  flex-shrink: 0;
}

.sq-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.sq-name {
  font-size: 14px;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sq-country {
  font-size: 12px;
  color: var(--text-muted);
}

.sq-right {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-shrink: 0;
}

.sq-price {
  font-size: 13px;
  font-weight: 700;
  color: var(--accent);
}

.sq-remove {
  width: 28px;
  height: 28px;
  background: rgba(242,87,87,0.1);
  border: none;
  border-radius: 7px;
  color: var(--danger);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.15s;
}

.sq-remove:active { background: rgba(242,87,87,0.25); }
.sq-remove svg { width: 13px; height: 13px; }

.role-empty {
  padding: 16px 14px;
  font-size: 13px;
  color: var(--text-muted);
  font-style: italic;
}
</style>