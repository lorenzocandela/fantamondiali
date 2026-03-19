<template>
  <div class="settings-wrap">

    <!-- PROFILO HEADER -->
    <div class="profile-hero">
      <div class="profile-logo-wrap" @click="triggerLogoUpload">
        <img v-if="previewLogo" :src="previewLogo" class="profile-logo" alt="Logo" />
        <div v-else class="profile-logo-ph">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
            <path d="M12 2L2 7l10 5 10-5-10-5z"/>
            <path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/>
          </svg>
        </div>
        <div class="logo-edit-badge">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
          </svg>
        </div>
      </div>
      <div class="profile-info">
        <span class="profile-name">{{ editTeamName || 'La tua squadra' }}</span>
        <span class="profile-email">{{ user?.email }}</span>
      </div>
      <input ref="fileInput" type="file" accept="image/*" class="hidden-input" @change="onLogoChange" />
    </div>

    <!-- SEZIONE SQUADRA -->
    <div class="grp">
      <p class="grp-label">Squadra</p>
      <div class="grp-card">
        <div class="grp-row">
          <div class="row-text">
            <span class="row-title">Nome squadra</span>
            <span class="row-sub">Visibile in classifica</span>
          </div>
          <input v-model="editTeamName" class="inline-input" placeholder="Nome..." maxlength="30" />
        </div>
        <div class="grp-row">
          <div class="row-text">
            <span class="row-title">Logo squadra</span>
            <span class="row-sub">{{ previewLogo ? 'Logo caricato' : 'Nessun logo impostato' }}</span>
          </div>
          <button class="btn-small" @click="triggerLogoUpload">Cambia</button>
        </div>
      </div>
    </div>

    <div class="grp" style="margin-top:8px;">
      <button class="btn-fill" @click="saveTeam" :disabled="saving">
        <span v-if="saving" class="btn-spin"></span>
        {{ saving ? 'Salvataggio...' : 'Salva modifiche' }}
      </button>
      <transition name="field-slide">
        <p v-if="saveMsg" :class="['save-notice', saveMsg.type]">{{ saveMsg.text }}</p>
      </transition>
    </div>

    <!-- SEZIONE ACCOUNT -->
    <div class="grp">
      <p class="grp-label">Account</p>
      <div class="grp-card">
        <div class="grp-row">
          <div class="row-text">
            <span class="row-title">Email</span>
            <span class="row-sub">{{ user?.email }}</span>
          </div>
        </div>
        <div class="grp-row">
          <div class="row-text">
            <span class="row-title">Password</span>
            <span class="row-sub">Invia email di reset</span>
          </div>
          <button class="btn-small" @click="sendReset">Reset</button>
        </div>
      </div>
      <transition name="field-slide">
        <p v-if="resetMsg" class="save-notice success">{{ resetMsg }}</p>
      </transition>
    </div>

    <!-- SEZIONE APP -->
    <div class="grp">
      <p class="grp-label">App</p>
      <div class="grp-card">
        <div class="grp-row">
          <div class="row-text">
            <span class="row-title">Notifiche</span>
            <span class="row-sub">Avvisi su aste e giornate</span>
          </div>
          <label class="toggle">
            <input type="checkbox" v-model="notificationsOn" />
            <span class="toggle-track"></span>
          </label>
        </div>
        <div class="grp-row">
          <div class="row-text">
            <span class="row-title">Versione</span>
            <span class="row-sub">1.0.0</span>
          </div>
        </div>
      </div>
    </div>

    <!-- LOGOUT -->
    <div class="grp" style="margin-top:8px;">
      <button class="btn-danger-ghost" @click="logout">Esci dall'account</button>
    </div>

    <div style="height:16px;"></div>

  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import { auth, db } from '../firebase';
import { signOut, sendPasswordResetEmail } from 'firebase/auth';
import { doc, getDoc, updateDoc } from 'firebase/firestore';

const props = defineProps({ user: Object, userTeamName: String });
const emit  = defineEmits(['team-updated']);

const editTeamName    = ref('');
const previewLogo     = ref('');
const logoBase64      = ref('');
const saving          = ref(false);
const saveMsg         = ref(null);
const resetMsg        = ref('');
const notificationsOn = ref(false);
const fileInput       = ref(null);

onMounted(async () => {
  editTeamName.value = props.userTeamName || '';
  if (!props.user) return;
  try {
    const snap = await getDoc(doc(db, 'users', props.user.uid));
    if (snap.exists()) {
      const d = snap.data();
      editTeamName.value = d.team_name || '';
      previewLogo.value  = d.team_logo || '';
    }
  } catch (e) {
    // Firestore offline o non abilitato — ignora
    console.warn('Firestore non raggiungibile:', e.code);
  }
});

watch(() => props.userTeamName, v => { if (v) editTeamName.value = v; });

const triggerLogoUpload = () => fileInput.value?.click();

const onLogoChange = (e) => {
  const file = e.target.files?.[0];
  if (!file) return;
  // Limite 200KB per stare nei limiti Firestore (max 1MB per document)
  if (file.size > 200 * 1024) {
    saveMsg.value = { type: 'error', text: 'Immagine troppo grande. Max 200KB.' };
    setTimeout(() => { saveMsg.value = null; }, 3000);
    return;
  }
  const reader = new FileReader();
  reader.onload = ev => {
    previewLogo.value = ev.target.result;
    logoBase64.value  = ev.target.result;
  };
  reader.readAsDataURL(file);
};

const saveTeam = async () => {
  if (!props.user) return;
  saving.value = true;
  saveMsg.value = null;
  try {
    const updateData = {
      team_name: editTeamName.value.trim(),
    };
    // Solo aggiorna il logo se ne e' stato caricato uno nuovo
    if (logoBase64.value) {
      updateData.team_logo = logoBase64.value;
    }
    await updateDoc(doc(db, 'users', props.user.uid), updateData);
    logoBase64.value = '';
    saveMsg.value = { type: 'success', text: 'Modifiche salvate.' };
    emit('team-updated');
  } catch (e) {
    if (e.code === 'unavailable') {
      saveMsg.value = { type: 'error', text: 'Connessione assente. Riprova.' };
    } else {
      saveMsg.value = { type: 'error', text: 'Errore durante il salvataggio.' };
    }
    console.error(e);
  } finally {
    saving.value = false;
    setTimeout(() => { saveMsg.value = null; }, 4000);
  }
};

const sendReset = async () => {
  if (!props.user?.email) return;
  try {
    await sendPasswordResetEmail(auth, props.user.email);
    resetMsg.value = 'Email di reset inviata a ' + props.user.email;
    setTimeout(() => { resetMsg.value = ''; }, 5000);
  } catch { resetMsg.value = 'Errore. Riprova.'; }
};

const logout = () => signOut(auth);
</script>

<style scoped>
.settings-wrap { padding-bottom: 24px; }

/* PROFILE HERO */
.profile-hero {
  display: flex; align-items: center; gap: 14px;
  padding: 20px 16px 16px;
  border-bottom: 1px solid var(--sep);
}

.profile-logo-wrap {
  position: relative; flex-shrink: 0; cursor: pointer;
}

.profile-logo {
  width: 62px; height: 62px; border-radius: var(--r-lg);
  object-fit: contain; background: var(--bg-1); border: 1px solid var(--sep2);
  display: block;
}

.profile-logo-ph {
  width: 62px; height: 62px; border-radius: var(--r-lg);
  background: var(--bg-1); border: 1px solid var(--sep2);
  display: flex; align-items: center; justify-content: center; color: var(--text-3);
}
.profile-logo-ph svg { width: 26px; height: 26px; }

.logo-edit-badge {
  position: absolute; bottom: -4px; right: -4px;
  width: 22px; height: 22px; border-radius: 50%;
  background: var(--accent); border: 2px solid var(--bg);
  display: flex; align-items: center; justify-content: center; color: #fff;
}
.logo-edit-badge svg { width: 10px; height: 10px; }

.hidden-input { display: none; }

.profile-info { display: flex; flex-direction: column; gap: 3px; }
.profile-name  { font-size: 18px; font-weight: 700; letter-spacing: -0.2px; }
.profile-email { font-size: 13px; color: var(--text-2); }

/* INLINE INPUT */
.inline-input {
  background: var(--bg-2); border: 1px solid var(--sep2); border-radius: var(--r);
  padding: 8px 10px; color: var(--text); font-size: 14px; outline: none;
  width: 140px; flex-shrink: 0; text-align: right;
  transition: border-color .15s;
}
.inline-input:focus { border-color: var(--accent); }
.inline-input::placeholder { color: var(--text-3); }

/* SMALL BUTTON */
.btn-small {
  background: var(--bg-2); border: 1px solid var(--sep2); border-radius: var(--r);
  padding: 7px 14px; color: var(--accent); font-size: 14px; font-weight: 600;
  cursor: pointer; flex-shrink: 0; white-space: nowrap;
}
.btn-small:active { background: var(--bg-3); }

/* SAVE NOTICE */
.save-notice {
  font-size: 13px; padding: 9px 12px; border-radius: var(--r); margin-top: 8px;
}
.save-notice.success { background: var(--green-d); color: var(--green); border: 1px solid rgba(48,209,88,.2); }
.save-notice.error   { background: var(--red-d);   color: var(--red);   border: 1px solid rgba(255,69,58,.2); }

/* TOGGLE */
.toggle { position: relative; cursor: pointer; }
.toggle input { position: absolute; opacity: 0; width: 0; height: 0; }

.toggle-track {
  display: block; width: 46px; height: 26px;
  background: var(--bg-3); border-radius: 20px;
  transition: background .2s; position: relative;
}
.toggle-track::after {
  content: ''; position: absolute; top: 3px; left: 3px;
  width: 20px; height: 20px; background: #fff; border-radius: 50%;
  transition: transform .2s; box-shadow: 0 1px 3px rgba(0,0,0,.4);
}
.toggle input:checked + .toggle-track { background: var(--accent); }
.toggle input:checked + .toggle-track::after { transform: translateX(20px); }

/* spinner */
.btn-spin {
  width: 15px; height: 15px;
  border: 2px solid rgba(255,255,255,.3); border-top-color: #fff;
  border-radius: 50%; animation: spin .55s linear infinite; flex-shrink: 0;
}

.field-slide-enter-active, .field-slide-leave-active { transition: opacity .2s; }
.field-slide-enter-from, .field-slide-leave-to { opacity: 0; }
</style>