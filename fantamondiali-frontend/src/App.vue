<template>
  <div class="app-root">

    <!-- LOGIN SCREEN -->
    <transition name="fade">
      <div v-if="!user && !authLoading" class="login-screen">
        <div class="login-bg-orbs">
          <span class="orb orb-1"></span>
          <span class="orb orb-2"></span>
        </div>
        <div class="login-content">
          <div class="login-logo">
            <span class="logo-icon">🏆</span>
          </div>
          <h1 class="login-title">Fanta<span class="accent">Mondiali</span></h1>
          <p class="login-sub">Il tuo campionato privato dei Mondiali</p>

          <div class="login-card">
            <p class="login-card-label">Accedi al tuo account</p>
            <button class="btn-google" @click="loginWithGoogle" :disabled="loginLoading">
              <svg class="google-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
              </svg>
              <span>{{ loginLoading ? 'Accesso in corso...' : 'Continua con Google' }}</span>
            </button>
            <p v-if="errorMsg" class="error-msg">{{ errorMsg }}</p>
          </div>

          <p class="login-footer">Partecipa al campionato, scegli i tuoi campioni.</p>
        </div>
      </div>
    </transition>

    <!-- LOADING STATE -->
    <div v-if="authLoading" class="auth-loading">
      <div class="spinner"></div>
    </div>

    <!-- MAIN APP (autenticato) -->
    <transition name="slide-up">
      <div v-if="user && !authLoading" class="main-app">

        <!-- HEADER -->
        <header class="app-header">
          <div class="header-left">
            <span class="header-logo">🏆</span>
            <span class="header-title">Fanta<span class="accent">Mondiali</span></span>
          </div>
          <button class="btn-logout" @click="logout" title="Esci">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
              <polyline points="16 17 21 12 16 7"/>
              <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
          </button>
        </header>

        <!-- PAGE CONTENT -->
        <main class="page-content">
          <component :is="currentTabComponent" :user="user" />
        </main>

        <!-- BOTTOM TAB BAR -->
        <nav class="bottom-nav">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            class="nav-tab"
            :class="{ active: activeTab === tab.id }"
            @click="activeTab = tab.id"
          >
            <span class="nav-icon" v-html="tab.icon"></span>
            <span class="nav-label">{{ tab.label }}</span>
          </button>
        </nav>

      </div>
    </transition>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, defineAsyncComponent } from 'vue';
import { auth } from './firebase';
import { GoogleAuthProvider, signInWithPopup, signOut, onAuthStateChanged } from 'firebase/auth';
import axios from 'axios';

// Lazy load tab components
const ListGlobal    = defineAsyncComponent(() => import('./components/listGlobal.vue'));
const MySquad       = defineAsyncComponent(() => import('./components/mySquad.vue'));
const SettingGlobal = defineAsyncComponent(() => import('./components/settingGlobal.vue'));
const HighlightsGlobal = defineAsyncComponent(() => import('./components/highlightsGlobal.vue'));

// State
const user        = ref(null);
const authLoading = ref(true);
const loginLoading = ref(false);
const errorMsg    = ref('');
const activeTab   = ref('listone');

// Tabs config
const tabs = [
  {
    id: 'listone',
    label: 'Listone',
    icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><circle cx="3" cy="6" r="1" fill="currentColor"/><circle cx="3" cy="12" r="1" fill="currentColor"/><circle cx="3" cy="18" r="1" fill="currentColor"/></svg>`
  },
  {
    id: 'squadra',
    label: 'Squadra',
    icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>`
  },
  {
    id: 'classifica',
    label: 'Classifica',
    icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>`
  },
  {
    id: 'calendario',
    label: 'Calendario',
    icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>`
  },
  {
    id: 'impostazioni',
    label: 'Impost.',
    icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>`
  },
];

// Mappa tab → componente
const tabComponentMap = {
  listone:      ListGlobal,
  squadra:      MySquad,
  classifica:   HighlightsGlobal,
  calendario:   HighlightsGlobal, // placeholder, diventerà CalendarioGlobal
  impostazioni: SettingGlobal,
};

const currentTabComponent = computed(() => tabComponentMap[activeTab.value]);

// Auth
onMounted(() => {
  onAuthStateChanged(auth, async (currentUser) => {
    user.value = currentUser;
    authLoading.value = false;
    if (currentUser) {
      try {
        await axios.post('/api/users/sync', {
          firebase_uid: currentUser.uid,
          email: currentUser.email,
          team_name: `Team di ${currentUser.displayName || 'Allenatore'}`
        });
      } catch (e) {
        // sync non bloccante
      }
    }
  });
});

const loginWithGoogle = async () => {
  errorMsg.value = '';
  loginLoading.value = true;
  const provider = new GoogleAuthProvider();
  try {
    await signInWithPopup(auth, provider);
  } catch (error) {
    errorMsg.value = 'Errore durante l\'accesso. Riprova.';
  } finally {
    loginLoading.value = false;
  }
};

const logout = async () => {
  await signOut(auth);
};
</script>

<style>
/* ── RESET & GLOBAL ─────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --bg:        #0d0d14;
  --bg-card:   #161622;
  --bg-card2:  #1e1e2e;
  --border:    #2a2a3e;
  --accent:    #4f8ef7;
  --accent2:   #7c5cf6;
  --text:      #f0f0f8;
  --text-muted:#7a7a99;
  --success:   #22d3a5;
  --danger:    #f25757;
  --radius:    14px;
  --nav-h:     68px;
  --header-h:  56px;
}

html, body { height: 100%; background: var(--bg); }

body {
  font-family: 'SF Pro Display', -apple-system, 'Segoe UI', sans-serif;
  color: var(--text);
  -webkit-font-smoothing: antialiased;
  overscroll-behavior: none;
}

.app-root {
  min-height: 100vh;
  min-height: 100dvh;
  position: relative;
  overflow: hidden;
}

.accent { color: var(--accent); }

/* ── LOGIN SCREEN ───────────────────────────────────────── */
.login-screen {
  min-height: 100vh;
  min-height: 100dvh;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  overflow: hidden;
  background: var(--bg);
}

.login-bg-orbs {
  position: absolute;
  inset: 0;
  pointer-events: none;
}

.orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
  opacity: 0.18;
}

.orb-1 {
  width: 360px; height: 360px;
  background: var(--accent);
  top: -80px; right: -80px;
}

.orb-2 {
  width: 300px; height: 300px;
  background: var(--accent2);
  bottom: -60px; left: -60px;
}

.login-content {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 40px 24px;
  width: 100%;
  max-width: 380px;
}

.login-logo {
  width: 80px; height: 80px;
  background: var(--bg-card2);
  border: 1px solid var(--border);
  border-radius: 24px;
  display: flex; align-items: center; justify-content: center;
  font-size: 36px;
  margin-bottom: 20px;
  box-shadow: 0 0 40px rgba(79,142,247,0.15);
}

.login-title {
  font-size: 32px;
  font-weight: 800;
  letter-spacing: -0.5px;
  margin-bottom: 8px;
}

.login-sub {
  color: var(--text-muted);
  font-size: 15px;
  margin-bottom: 40px;
  text-align: center;
}

.login-card {
  width: 100%;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 24px;
  margin-bottom: 24px;
}

.login-card-label {
  color: var(--text-muted);
  font-size: 13px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 16px;
}

.btn-google {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 14px 20px;
  background: var(--bg-card2);
  border: 1px solid var(--border);
  border-radius: 10px;
  color: var(--text);
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s, transform 0.1s;
}

.btn-google:hover { background: #26263a; }
.btn-google:active { transform: scale(0.98); }
.btn-google:disabled { opacity: 0.5; cursor: not-allowed; }

.google-icon { width: 20px; height: 20px; flex-shrink: 0; }

.error-msg {
  color: var(--danger);
  font-size: 13px;
  margin-top: 12px;
  text-align: center;
}

.login-footer {
  color: var(--text-muted);
  font-size: 13px;
  text-align: center;
}

/* ── AUTH LOADING ───────────────────────────────────────── */
.auth-loading {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
}

.spinner {
  width: 36px; height: 36px;
  border: 3px solid var(--border);
  border-top-color: var(--accent);
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}

@keyframes spin { to { transform: rotate(360deg); } }

/* ── MAIN APP ───────────────────────────────────────────── */
.main-app {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  min-height: 100dvh;
}

/* ── HEADER ─────────────────────────────────────────────── */
.app-header {
  height: var(--header-h);
  background: var(--bg-card);
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 20px;
  position: sticky;
  top: 0;
  z-index: 100;
  flex-shrink: 0;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 8px;
}

.header-logo { font-size: 22px; }

.header-title {
  font-size: 18px;
  font-weight: 800;
  letter-spacing: -0.3px;
}

.btn-logout {
  width: 36px; height: 36px;
  background: transparent;
  border: 1px solid var(--border);
  border-radius: 8px;
  color: var(--text-muted);
  cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: color 0.2s, border-color 0.2s;
}

.btn-logout:hover { color: var(--danger); border-color: var(--danger); }
.btn-logout svg { width: 16px; height: 16px; }

/* ── PAGE CONTENT ───────────────────────────────────────── */
.page-content {
  flex: 1;
  overflow-y: auto;
  padding-bottom: calc(var(--nav-h) + 8px);
  background: var(--bg);
}

/* ── BOTTOM NAV ─────────────────────────────────────────── */
.bottom-nav {
  height: var(--nav-h);
  background: var(--bg-card);
  border-top: 1px solid var(--border);
  display: flex;
  align-items: center;
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
  gap: 4px;
  background: none;
  border: none;
  cursor: pointer;
  color: var(--text-muted);
  padding: 8px 4px;
  transition: color 0.2s;
  position: relative;
}

.nav-tab.active { color: var(--accent); }

.nav-tab.active::before {
  content: '';
  position: absolute;
  top: 0; left: 50%;
  transform: translateX(-50%);
  width: 32px; height: 2px;
  background: var(--accent);
  border-radius: 0 0 4px 4px;
}

.nav-icon { display: flex; }
.nav-icon svg { width: 22px; height: 22px; }

.nav-label {
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 0.2px;
}

/* ── TRANSITIONS ────────────────────────────────────────── */
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.slide-up-enter-active { transition: opacity 0.35s, transform 0.35s; }
.slide-up-enter-from { opacity: 0; transform: translateY(20px); }

/* ── SHARED CARD STYLES (usate dai figli) ───────────────── */
.card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 16px;
}

.section-title {
  font-size: 13px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  color: var(--text-muted);
  margin-bottom: 12px;
}

.placeholder-screen {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 60vh;
  padding: 40px 24px;
  text-align: center;
  gap: 16px;
}

.placeholder-icon { font-size: 56px; }
.placeholder-title { font-size: 20px; font-weight: 700; }
.placeholder-sub { color: var(--text-muted); font-size: 15px; line-height: 1.5; max-width: 280px; }
</style>