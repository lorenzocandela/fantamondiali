<template>
  <div class="settings-wrap">

    <div class="settings-section">
      <p class="section-title">Squadra</p>

      <!-- LOGO -->
      <div class="card setting-item logo-item">
        <div class="logo-preview-wrap">
          <img v-if="previewLogo" :src="previewLogo" class="logo-preview" alt="Logo" />
          <div v-else class="logo-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/>
              <polyline points="21 15 16 10 5 21"/>
            </svg>
          </div>
        </div>
        <div class="logo-info">
          <span class="setting-label">Logo squadra</span>
          <span class="setting-sub">{{ previewLogo ? 'Logo caricato' : 'Nessun logo' }}</span>
        </div>
        <label class="btn-secondary btn-sm logo-upload-btn">
          Cambia
          <input type="file" accept="image/*" class="file-input" @change="onLogoChange" />
        </label>
      </div>

      <!-- NOME SQUADRA -->
      <div class="card setting-item">
        <div class="setting-text">
          <span class="setting-label">Nome squadra</span>
          <span class="setting-sub">Visibile in classifica</span>
        </div>
        <input
          v-model="editTeamName"
          class="field-input setting-input"
          placeholder="Nome squadra"
          maxlength="30"
        />
      </div>

      <button class="btn-primary" @click="saveTeam" :disabled="saving">
        <span v-if="saving" class="btn-spinner"></span>
        {{ saving ? 'Salvataggio...' : 'Salva modifiche' }}
      </button>

      <p v-if="saveMsg" :class="['save-msg', saveMsg.type]">{{ saveMsg.text }}</p>
    </div>

    <div class="settings-section">
      <p class="section-title">Account</p>

      <div class="card setting-item">
        <div class="setting-text">
          <span class="setting-label">Email</span>
          <span class="setting-sub">{{ user?.email }}</span>
        </div>
      </div>

      <div class="card setting-item">
        <div class="setting-text">
          <span class="setting-label">Password</span>
          <span class="setting-sub">Cambia la tua password</span>
        </div>
        <button class="btn-secondary btn-sm" @click="sendReset">Reset</button>
      </div>

      <p v-if="resetMsg" class="save-msg success">{{ resetMsg }}</p>
    </div>

    <div class="settings-section">
      <p class="section-title">App</p>

      <div class="card setting-item">
        <div class="setting-text">
          <span class="setting-label">Notifiche</span>
          <span class="setting-sub">Avvisi su aste e giornate</span>
        </div>
        <label class="toggle">
          <input type="checkbox" v-model="notificationsOn" />
          <span class="toggle-track"></span>
        </label>
      </div>

      <div class="card setting-item">
        <div class="setting-text">
          <span class="setting-label">Versione</span>
          <span class="setting-sub">1.0.0</span>
        </div>
      </div>
    </div>

    <div class="settings-section">
      <button class="btn-danger" @click="logout">Esci dall'account</button>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import { auth, db } from '../firebase';
import { signOut, sendPasswordResetEmail } from 'firebase/auth';
import { doc, getDoc, updateDoc } from 'firebase/firestore';
import { getStorage, ref as storageRef, uploadBytes, getDownloadURL } from 'firebase/storage';

const props = defineProps({ user: Object, userTeamName: String });
const emit  = defineEmits(['team-updated']);

const editTeamName    = ref('');
const previewLogo     = ref('');
const logoFile        = ref(null);
const saving          = ref(false);
const saveMsg         = ref(null);
const resetMsg        = ref('');
const notificationsOn = ref(false);

onMounted(async () => {
  editTeamName.value = props.userTeamName || '';
  if (!props.user) return;
  try {
    const snap = await getDoc(doc(db, 'users', props.user.uid));
    if (snap.exists()) {
      const d = snap.data();
      editTeamName.value = d.team_name  || '';
      previewLogo.value  = d.team_logo  || '';
    }
  } catch (e) { console.error(e); }
});

watch(() => props.userTeamName, v => { if (v) editTeamName.value = v; });

const onLogoChange = (e) => {
  const file = e.target.files[0];
  if (!file) return;
  logoFile.value = file;
  const reader = new FileReader();
  reader.onload = ev => { previewLogo.value = ev.target.result; };
  reader.readAsDataURL(file);
};

const saveTeam = async () => {
  if (!props.user) return;
  saving.value = true;
  saveMsg.value = null;
  try {
    let logoUrl = previewLogo.value;

    if (logoFile.value) {
      const storage = getStorage();
      const sRef = storageRef(storage, `logos/${props.user.uid}`);
      await uploadBytes(sRef, logoFile.value);
      logoUrl = await getDownloadURL(sRef);
    }

    await updateDoc(doc(db, 'users', props.user.uid), {
      team_name: editTeamName.value.trim(),
      team_logo: logoUrl || '',
    });

    previewLogo.value = logoUrl;
    logoFile.value = null;
    saveMsg.value = { type: 'success', text: 'Modifiche salvate.' };
    emit('team-updated');
  } catch (e) {
    saveMsg.value = { type: 'error', text: 'Errore durante il salvataggio.' };
  } finally {
    saving.value = false;
    setTimeout(() => { saveMsg.value = null; }, 3000);
  }
};

const sendReset = async () => {
  if (!props.user?.email) return;
  try {
    await sendPasswordResetEmail(auth, props.user.email);
    resetMsg.value = 'Email di reset inviata.';
    setTimeout(() => { resetMsg.value = ''; }, 4000);
  } catch { resetMsg.value = 'Errore. Riprova.'; }
};

const logout = () => signOut(auth);
</script>

<style scoped>
.settings-wrap {
  padding: 16px 14px 32px;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.settings-section {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

/* CARD ITEM */
.setting-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
}

.setting-text, .logo-info {
  display: flex; flex-direction: column; gap: 2px; flex: 1;
}

.setting-label { font-size: 13px; font-weight: 600; }
.setting-sub   { font-size: 11px; color: var(--text-muted); }

/* LOGO */
.logo-preview-wrap { flex-shrink: 0; }

.logo-preview {
  width: 48px; height: 48px;
  border-radius: var(--radius);
  object-fit: contain;
  background: var(--bg-panel);
  border: 1px solid var(--border);
}

.logo-empty {
  width: 48px; height: 48px;
  border-radius: var(--radius);
  background: var(--bg-panel);
  border: 1px dashed var(--border);
  display: flex; align-items: center; justify-content: center;
  color: var(--text-muted);
}
.logo-empty svg { width: 20px; height: 20px; }

.logo-upload-btn { position: relative; cursor: pointer; }
.file-input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }

/* INPUT INLINE */
.setting-input {
  width: 160px;
  flex-shrink: 0;
  font-size: 13px;
  padding: 7px 10px;
}

/* BUTTONS */
.btn-primary {
  width: 100%;
  background: var(--accent-btn);
  border: none;
  border-radius: var(--radius);
  padding: 10px;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: opacity .15s;
}
.btn-primary:disabled { opacity: .5; cursor: not-allowed; }

.btn-secondary {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  color: var(--text);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: border-color .15s;
}
.btn-secondary:hover { border-color: var(--text-muted); }

.btn-sm { padding: 6px 12px; flex-shrink: 0; }

.btn-danger {
  width: 100%;
  background: rgba(244,71,71,.08);
  border: 1px solid rgba(244,71,71,.2);
  border-radius: var(--radius);
  padding: 10px;
  color: var(--danger);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: background .15s;
}
.btn-danger:hover { background: rgba(244,71,71,.15); }

/* TOGGLE */
.toggle { position: relative; flex-shrink: 0; }
.toggle input { opacity: 0; width: 0; height: 0; position: absolute; }

.toggle-track {
  display: block;
  width: 36px; height: 20px;
  background: var(--border);
  border-radius: 20px;
  cursor: pointer;
  transition: background .2s;
  position: relative;
}

.toggle-track::after {
  content: '';
  position: absolute;
  top: 3px; left: 3px;
  width: 14px; height: 14px;
  background: var(--text-muted);
  border-radius: 50%;
  transition: transform .2s, background .2s;
}

.toggle input:checked + .toggle-track { background: var(--accent-btn); }
.toggle input:checked + .toggle-track::after { transform: translateX(16px); background: #fff; }

/* MESSAGES */
.save-msg {
  font-size: 12px;
  padding: 8px 12px;
  border-radius: var(--radius);
  margin-top: 2px;
}
.save-msg.success { background: rgba(78,201,176,.1); color: var(--success); border: 1px solid rgba(78,201,176,.2); }
.save-msg.error   { background: rgba(244,71,71,.08); color: var(--danger);  border: 1px solid rgba(244,71,71,.2);  }

/* SPINNER */
.btn-spinner {
  width: 13px; height: 13px;
  border: 2px solid rgba(255,255,255,.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin .6s linear infinite;
  flex-shrink: 0;
}
</style>