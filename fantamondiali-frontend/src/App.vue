<template>
  <div class="app-root">

    <!-- AUTH LOADING -->
    <div v-if="authLoading" class="auth-loading">
      <div class="spinner"></div>
    </div>

    <!-- LOGIN / REGISTER -->
    <transition name="fade" v-else-if="!user">
      <div class="auth-screen">
        <div class="auth-glow"></div>

        <div class="auth-panel">
          <div class="auth-brand">
            <div class="brand-icon">FM</div>
            <h1 class="brand-title">Fanta<span class="accent">Mondiali</span></h1>
          </div>

          <div class="auth-tabs">
            <button :class="['auth-tab', { active: mode === 'login' }]" @click="mode = 'login'; errorMsg = ''">Accedi</button>
            <button :class="['auth-tab', { active: mode === 'register' }]" @click="mode = 'register'; errorMsg = ''">Registrati</button>
          </div>

          <div class="auth-form">
            <div v-if="mode === 'register'" class="field-group">
              <label class="field-label">Nome squadra</label>
              <input v-model="teamName" class="field-input" type="text" placeholder="es. Gli Invincibili" autocomplete="off" />
            </div>

            <div class="field-group">
              <label class="field-label">Email</label>
              <input v-model="email" class="field-input" type="email" placeholder="nome@email.com" autocomplete="email" @keyup.enter="submit" />
            </div>

            <div class="field-group">
              <label class="field-label">Password</label>
              <div class="input-wrap">
                <input
                  v-model="password"
                  class="field-input"
                  :type="showPwd ? 'text' : 'password'"
                  placeholder="••••••••"
                  autocomplete="current-password"
                  @keyup.enter="submit"
                />
                <button class="pwd-toggle" @click="showPwd = !showPwd" tabindex="-1">
                  <svg v-if="!showPwd" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                  </svg>
                  <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                    <line x1="1" y1="1" x2="23" y2="23"/>
                  </svg>
                </button>
              </div>
            </div>

            <p v-if="errorMsg" class="error-msg">{{ errorMsg }}</p>

            <button class="btn-submit" @click="submit" :disabled="loading">
              <span v-if="loading" class="btn-spinner"></span>
              <span>{{ mode === 'login' ? 'Accedi' : 'Crea account' }}</span>
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- MAIN APP -->
    <div v-else class="main-app">

      <header class="app-header">
        <div class="header-left">
          <span class="header-brand">Fanta<span class="accent">Mondiali</span></span>
        </div>
        <div class="header-right">
          <span class="header-user">{{ userTeamName || user.email }}</span>
          <button class="btn-icon" @click="logout" title="Esci">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
              <polyline points="16 17 21 12 16 7"/>
              <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
          </button>
        </div>
      </header>

      <main class="page-content">
        <router-view :user="user" :userTeamName="userTeamName" @team-updated="fetchUserData" />
      </main>

      <nav class="bottom-nav">
        <button
          v-for="tab in tabs"
          :key="tab.path"
          class="nav-tab"
          :class="{ active: $route.path === tab.path }"
          @click="$router.push(tab.path)"
        >
          <span class="nav-icon" v-html="tab.icon"></span>
          <span class="nav-label">{{ tab.label }}</span>
        </button>
      </nav>

    </div>

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { auth, db } from './firebase';
import {
  createUserWithEmailAndPassword,
  signInWithEmailAndPassword,
  signOut,
  onAuthStateChanged
} from 'firebase/auth';
import { doc, setDoc, getDoc, serverTimestamp } from 'firebase/firestore';

const user         = ref(null);
const authLoading  = ref(true);
const loading      = ref(false);
const errorMsg     = ref('');
const mode         = ref('login');
const email        = ref('');
const password     = ref('');
const teamName     = ref('');
const showPwd      = ref(false);
const userTeamName = ref('');

const $route = useRoute();

const tabs = [
  {
    path: '/listone',
    label: 'Listone',
    icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><circle cx="3" cy="6" r="1" fill="currentColor" stroke="none"/><circle cx="3" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="3" cy="18" r="1" fill="currentColor" stroke="none"/></svg>`
  },
  {
    path: '/squadra',
    label: 'Squadra',
    icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>`
  },
  {
    path: '/classifica',
    label: 'Classifica',
    icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>`
  },
  {
    path: '/calendario',
    label: 'Calendario',
    icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>`
  },
  {
    path: '/impostazioni',
    label: 'Impost.',
    icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>`
  },
];

onMounted(() => {
  onAuthStateChanged(auth, async (u) => {
    user.value = u;
    authLoading.value = false;
    if (u) await fetchUserData();
  });
});

const fetchUserData = async () => {
  if (!user.value) return;
  const snap = await getDoc(doc(db, 'users', user.value.uid));
  if (snap.exists()) userTeamName.value = snap.data().team_name || '';
};

const submit = async () => {
  errorMsg.value = '';
  if (!email.value || !password.value) { errorMsg.value = 'Compila tutti i campi.'; return; }
  loading.value = true;
  try {
    if (mode.value === 'register') {
      if (!teamName.value.trim()) { errorMsg.value = 'Inserisci il nome della tua squadra.'; loading.value = false; return; }
      const cred = await createUserWithEmailAndPassword(auth, email.value, password.value);
      await setDoc(doc(db, 'users', cred.user.uid), {
        email:      cred.user.email,
        team_name:  teamName.value.trim(),
        team_logo:  '',
        budget:     500,
        created_at: serverTimestamp()
      });
    } else {
      await signInWithEmailAndPassword(auth, email.value, password.value);
    }
  } catch (err) {
    const msgs = {
      'auth/invalid-credential':       'Email o password non corretti.',
      'auth/email-already-in-use':     'Email gia registrata.',
      'auth/weak-password':            'Password troppo corta (min. 6 caratteri).',
      'auth/invalid-email':            'Formato email non valido.',
      'auth/user-not-found':           'Nessun account con questa email.',
      'auth/wrong-password':           'Password non corretta.',
    };
    errorMsg.value = msgs[err.code] || 'Errore. Riprova.';
  } finally {
    loading.value = false;
  }
};

const logout = () => signOut(auth);
</script>

<style>
/* ── RESET ─────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --bg:          #1e1e1e;
  --bg-panel:    #252526;
  --bg-card:     #2d2d2d;
  --bg-input:    #3c3c3c;
  --border:      #3e3e42;
  --border-focus:#007acc;
  --accent:      #4fc3f7;
  --accent-btn:  #007acc;
  --text:        #d4d4d4;
  --text-muted:  #858585;
  --text-dim:    #6a6a6a;
  --success:     #4ec9b0;
  --danger:      #f44747;
  --warning:     #dcdcaa;
  --tag-por:     #b5a642;
  --tag-dif:     #4ec9b0;
  --tag-cen:     #569cd6;
  --tag-att:     #ce6464;
  --radius:      6px;
  --radius-lg:   10px;
  --header-h:    48px;
  --nav-h:       60px;
}

html, body {
  height: 100%;
  background: var(--bg);
  color: var(--text);
  font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
  font-size: 14px;
  -webkit-font-smoothing: antialiased;
  overscroll-behavior: none;
}

.app-root {
  min-height: 100vh;
  min-height: 100dvh;
}

.accent { color: var(--accent); }

/* ── SPINNER ───────────────────────────────────────────── */
.spinner {
  width: 28px; height: 28px;
  border: 2px solid var(--border);
  border-top-color: var(--accent-btn);
  border-radius: 50%;
  animation: spin .6s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── AUTH LOADING ──────────────────────────────────────── */
.auth-loading {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--bg);
}

/* ── AUTH SCREEN ───────────────────────────────────────── */
.auth-screen {
  min-height: 100vh;
  min-height: 100dvh;
  background: var(--bg);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  position: relative;
  overflow: hidden;
}

.auth-glow {
  position: absolute;
  width: 400px; height: 400px;
  background: radial-gradient(circle, rgba(0,122,204,0.12) 0%, transparent 70%);
  top: -100px; right: -100px;
  pointer-events: none;
}

.auth-panel {
  width: 100%;
  max-width: 360px;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.auth-brand {
  display: flex;
  align-items: center;
  gap: 12px;
}

.brand-icon {
  width: 40px; height: 40px;
  background: var(--accent-btn);
  border-radius: var(--radius);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 800;
  color: #fff;
  letter-spacing: 0.5px;
  flex-shrink: 0;
}

.brand-title {
  font-size: 22px;
  font-weight: 700;
  letter-spacing: -0.3px;
}

/* tabs */
.auth-tabs {
  display: flex;
  border-bottom: 1px solid var(--border);
}

.auth-tab {
  flex: 1;
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  padding: 10px 0;
  color: var(--text-muted);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  margin-bottom: -1px;
  transition: color .15s, border-color .15s;
}

.auth-tab.active {
  color: var(--text);
  border-bottom-color: var(--accent-btn);
}

/* form */
.auth-form {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.field-group {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.field-label {
  font-size: 12px;
  color: var(--text-muted);
  font-weight: 500;
}

.field-input {
  width: 100%;
  background: var(--bg-input);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 9px 12px;
  color: var(--text);
  font-size: 13px;
  outline: none;
  transition: border-color .15s;
}

.field-input:focus { border-color: var(--border-focus); }
.field-input::placeholder { color: var(--text-dim); }

.input-wrap { position: relative; }
.input-wrap .field-input { padding-right: 40px; }

.pwd-toggle {
  position: absolute;
  right: 10px; top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  color: var(--text-muted);
  cursor: pointer;
  display: flex;
  padding: 2px;
}
.pwd-toggle svg { width: 16px; height: 16px; }

.error-msg {
  font-size: 12px;
  color: var(--danger);
  padding: 8px 10px;
  background: rgba(244,71,71,.08);
  border: 1px solid rgba(244,71,71,.2);
  border-radius: var(--radius);
}

.btn-submit {
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
  margin-top: 4px;
}

.btn-submit:disabled { opacity: .5; cursor: not-allowed; }
.btn-submit:hover:not(:disabled) { opacity: .9; }

.btn-spinner {
  width: 14px; height: 14px;
  border: 2px solid rgba(255,255,255,.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin .6s linear infinite;
  flex-shrink: 0;
}

/* ── MAIN APP ──────────────────────────────────────────── */
.main-app {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  min-height: 100dvh;
  background: var(--bg);
}

/* ── HEADER ────────────────────────────────────────────── */
.app-header {
  height: var(--header-h);
  background: var(--bg-panel);
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 16px;
  position: sticky;
  top: 0;
  z-index: 100;
  flex-shrink: 0;
}

.header-brand {
  font-size: 15px;
  font-weight: 700;
  letter-spacing: -0.2px;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 10px;
}

.header-user {
  font-size: 12px;
  color: var(--text-muted);
  max-width: 140px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.btn-icon {
  width: 30px; height: 30px;
  background: none;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  color: var(--text-muted);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: color .15s, border-color .15s;
}

.btn-icon:hover { color: var(--danger); border-color: var(--danger); }
.btn-icon svg { width: 14px; height: 14px; }

/* ── PAGE CONTENT ──────────────────────────────────────── */
.page-content {
  flex: 1;
  overflow-y: auto;
  padding-bottom: calc(var(--nav-h) + env(safe-area-inset-bottom, 0px));
}

/* ── BOTTOM NAV ────────────────────────────────────────── */
.bottom-nav {
  height: var(--nav-h);
  background: var(--bg-panel);
  border-top: 1px solid var(--border);
  display: flex;
  position: fixed;
  bottom: 0; left: 0; right: 0;
  z-index: 100;
  padding-bottom: env(safe-area-inset-bottom, 0);
}

.nav-tab {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 3px;
  background: none;
  border: none;
  color: var(--text-muted);
  cursor: pointer;
  padding: 8px 4px;
  position: relative;
  transition: color .15s;
}

.nav-tab.active { color: var(--accent-btn); }

.nav-tab.active::after {
  content: '';
  position: absolute;
  bottom: 0; left: 50%;
  transform: translateX(-50%);
  width: 24px; height: 2px;
  background: var(--accent-btn);
  border-radius: 2px 2px 0 0;
}

.nav-icon { display: flex; line-height: 0; }
.nav-icon svg { width: 20px; height: 20px; }

.nav-label {
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 0.1px;
}

/* ── TRANSITIONS ───────────────────────────────────────── */
.fade-enter-active, .fade-leave-active { transition: opacity .25s; }
.fade-enter-from, .fade-leave-to       { opacity: 0; }

/* ── SHARED UTILITIES (usate dai componenti figli) ─────── */
.page-pad { padding: 16px; }

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}

.section-title {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  color: var(--text-muted);
}

.card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: hidden;
}

.role-badge {
  font-size: 10px;
  font-weight: 800;
  padding: 2px 6px;
  border-radius: 3px;
  letter-spacing: 0.3px;
  display: inline-block;
}

.role-badge.POR, .role-badge.role-POR { background: rgba(181,166,66,.18);  color: var(--tag-por); }
.role-badge.DIF, .role-badge.role-DIF { background: rgba(78,201,176,.18);  color: var(--tag-dif); }
.role-badge.CEN, .role-badge.role-CEN { background: rgba(86,156,214,.18);  color: var(--tag-cen); }
.role-badge.ATT, .role-badge.role-ATT { background: rgba(206,100,100,.18); color: var(--tag-att); }

.placeholder-screen {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 55vh;
  padding: 40px 24px;
  text-align: center;
  gap: 10px;
}

.placeholder-icon {
  width: 48px; height: 48px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-muted);
  margin-bottom: 6px;
}
.placeholder-icon svg { width: 22px; height: 22px; }

.placeholder-title {
  font-size: 15px;
  font-weight: 700;
  color: var(--text);
}

.placeholder-sub {
  font-size: 13px;
  color: var(--text-muted);
  line-height: 1.5;
  max-width: 260px;
}
</style>