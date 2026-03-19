<template>
  <div class="app-root">

    <!-- AUTH LOADING -->
    <div v-if="authLoading" class="auth-loading">
      <div class="spinner"></div>
    </div>

    <!-- LOGIN / REGISTER -->
    <div v-else-if="!user" class="auth-screen">
      <div class="auth-inner">

        <div class="auth-brand">
          <div class="brand-mark">FM</div>
          <div>
            <h1 class="brand-name">FantaMondiali</h1>
            <p class="brand-sub">Il tuo campionato dei Mondiali</p>
          </div>
        </div>

        <div class="auth-card">
          <div class="seg-control">
            <button :class="['seg-btn', { active: mode === 'login' }]" @click="switchMode('login')">Accedi</button>
            <button :class="['seg-btn', { active: mode === 'register' }]" @click="switchMode('register')">Registrati</button>
          </div>

          <div class="form-body">
            <transition name="field-slide">
              <div v-if="mode === 'register'" class="field">
                <label class="field-lbl">Nome squadra</label>
                <input v-model="teamName" class="field-in" type="text" placeholder="es. Gli Invincibili" autocomplete="off" />
              </div>
            </transition>

            <div class="field">
              <label class="field-lbl">Email</label>
              <input v-model="email" class="field-in" type="email" placeholder="nome@email.com" autocomplete="email" @keyup.enter="submit" />
            </div>

            <div class="field">
              <label class="field-lbl">Password</label>
              <div class="pwd-row">
                <input v-model="password" class="field-in" :type="showPwd ? 'text' : 'password'" placeholder="Minimo 6 caratteri" @keyup.enter="submit" />
                <button class="pwd-eye" @click="showPwd = !showPwd" tabindex="-1" type="button">
                  <svg v-if="!showPwd" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                  </svg>
                  <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                    <line x1="1" y1="1" x2="23" y2="23"/>
                  </svg>
                </button>
              </div>
            </div>

            <transition name="field-slide">
              <div v-if="errorMsg" class="err-box">{{ errorMsg }}</div>
            </transition>

            <button class="btn-cta" @click="submit" :disabled="loading">
              <span v-if="loading" class="btn-spin"></span>
              {{ mode === 'login' ? 'Accedi' : 'Crea account' }}
            </button>
          </div>
        </div>

      </div>
    </div>

    <!-- MAIN APP -->
    <div v-else class="main-app">

      <header class="top-bar">
        <span class="top-logo">Fanta<strong>Mondiali</strong></span>
        <div class="top-right">
          <span class="top-user">{{ userTeamName || user.email }}</span>
          <button class="icon-btn" @click="logout">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
              <polyline points="16 17 21 12 16 7"/>
              <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
          </button>
        </div>
      </header>

      <main class="page-area">
        <router-view :user="user" :userTeamName="userTeamName" @team-updated="fetchUserData" />
      </main>

      <nav class="tab-bar">
        <button
          v-for="t in tabs" :key="t.path"
          :class="['tab-item', { active: $route.path === t.path }]"
          @click="$router.push(t.path)"
        >
          <span class="tab-ico" v-html="t.icon"></span>
          <span class="tab-lbl">{{ t.label }}</span>
        </button>
      </nav>

    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { auth, db } from './firebase';
import { createUserWithEmailAndPassword, signInWithEmailAndPassword, signOut, onAuthStateChanged } from 'firebase/auth';
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
const $route       = useRoute();

const tabs = [
  { path: '/listone',      label: 'Listone',
    icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><circle cx="3" cy="6" r="1.2" fill="currentColor" stroke="none"/><circle cx="3" cy="12" r="1.2" fill="currentColor" stroke="none"/><circle cx="3" cy="18" r="1.2" fill="currentColor" stroke="none"/></svg>` },
  { path: '/squadra',      label: 'Squadra',
    icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>` },
  { path: '/classifica',   label: 'Classifica',
    icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><rect x="18" y="3" width="4" height="18" rx="1"/><rect x="10" y="8" width="4" height="13" rx="1"/><rect x="2" y="13" width="4" height="8" rx="1"/></svg>` },
  { path: '/calendario',   label: 'Calendario',
    icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>` },
  { path: '/impostazioni', label: 'Profilo',
    icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>` },
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
  try {
    const snap = await getDoc(doc(db, 'users', user.value.uid));
    if (snap.exists()) userTeamName.value = snap.data().team_name || '';
  } catch { /* offline graceful */ }
};

const switchMode = (m) => { mode.value = m; errorMsg.value = ''; };

const submit = async () => {
  errorMsg.value = '';
  if (!email.value || !password.value) { errorMsg.value = 'Compila tutti i campi.'; return; }
  loading.value = true;
  try {
    if (mode.value === 'register') {
      if (!teamName.value.trim()) { errorMsg.value = 'Inserisci il nome della tua squadra.'; loading.value = false; return; }
      const cred = await createUserWithEmailAndPassword(auth, email.value, password.value);
      await setDoc(doc(db, 'users', cred.user.uid), {
        email: cred.user.email, team_name: teamName.value.trim(),
        team_logo: '', budget: 500, created_at: serverTimestamp()
      });
    } else {
      await signInWithEmailAndPassword(auth, email.value, password.value);
    }
  } catch (err) {
    const msgs = {
      'auth/invalid-credential':   'Email o password non corretti.',
      'auth/email-already-in-use': 'Email gia registrata.',
      'auth/weak-password':        'Password troppo corta (min. 6 caratteri).',
      'auth/invalid-email':        'Formato email non valido.',
      'auth/user-not-found':       'Nessun account con questa email.',
      'auth/wrong-password':       'Password non corretta.',
    };
    errorMsg.value = msgs[err.code] || 'Errore imprevisto. Riprova.';
  } finally { loading.value = false; }
};

const logout = () => signOut(auth);
</script>

<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --bg:        #000000;
  --bg-1:      #1c1c1e;
  --bg-2:      #2c2c2e;
  --bg-3:      #3a3a3c;
  --sep:       rgba(255,255,255,0.08);
  --sep2:      rgba(255,255,255,0.15);
  --text:      #ffffff;
  --text-2:    rgba(255,255,255,0.55);
  --text-3:    rgba(255,255,255,0.25);
  --accent:    #0a84ff;
  --accent-d:  rgba(10,132,255,0.18);
  --green:     #30d158;
  --green-d:   rgba(48,209,88,0.18);
  --red:       #ff453a;
  --red-d:     rgba(255,69,58,0.18);
  --yellow:    #ffd60a;
  --yellow-d:  rgba(255,214,10,0.18);
  --por-c:     #ffd60a;
  --dif-c:     #30d158;
  --cen-c:     #0a84ff;
  --att-c:     #ff453a;
  --r:         10px;
  --r-lg:      14px;
  --header-h:  50px;
  --nav-h:     70px;
}

html, body {
  height: 100%; background: var(--bg); color: var(--text);
  font-family: -apple-system, 'SF Pro Text', 'Helvetica Neue', sans-serif;
  font-size: 15px; -webkit-font-smoothing: antialiased;
  overscroll-behavior: none;
}

.app-root { min-height: 100vh; min-height: 100dvh; }

/* spinner */
.spinner {
  width: 24px; height: 24px;
  border: 2px solid var(--sep2); border-top-color: var(--accent);
  border-radius: 50%; animation: spin .55s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.auth-loading {
  min-height: 100vh; display: flex; align-items: center; justify-content: center;
}

/* ── AUTH ──────────────────────────────────────────────── */
.auth-screen {
  min-height: 100vh; min-height: 100dvh; background: var(--bg);
  display: flex; align-items: center; justify-content: center; padding: 28px 20px;
}

.auth-inner { width: 100%; max-width: 360px; display: flex; flex-direction: column; gap: 28px; }

.auth-brand { display: flex; align-items: center; gap: 14px; }

.brand-mark {
  width: 46px; height: 46px; background: var(--accent); border-radius: var(--r-lg);
  display: flex; align-items: center; justify-content: center;
  font-size: 14px; font-weight: 800; color: #fff; letter-spacing: 0.5px; flex-shrink: 0;
}

.brand-name { font-size: 22px; font-weight: 700; letter-spacing: -0.5px; line-height: 1.1; }
.brand-sub  { font-size: 13px; color: var(--text-2); margin-top: 2px; }

.auth-card {
  background: var(--bg-1); border-radius: var(--r-lg);
  border: 1px solid var(--sep2); overflow: hidden;
}

.seg-control {
  display: flex; padding: 5px 5px 0;
  border-bottom: 1px solid var(--sep);
}

.seg-btn {
  flex: 1; padding: 9px; background: none; border: none;
  border-bottom: 2px solid transparent; margin-bottom: -1px;
  color: var(--text-2); font-size: 14px; font-weight: 600; cursor: pointer;
  transition: color .15s, border-color .15s;
}
.seg-btn.active { color: var(--text); border-bottom-color: var(--accent); }

.form-body { padding: 18px; display: flex; flex-direction: column; gap: 13px; }

.field { display: flex; flex-direction: column; gap: 5px; }
.field-lbl { font-size: 12px; font-weight: 600; color: var(--text-2); }

.field-in {
  width: 100%; background: var(--bg-2); border: 1px solid var(--sep2);
  border-radius: var(--r); padding: 12px 14px; color: var(--text);
  font-size: 15px; outline: none; transition: border-color .15s; -webkit-appearance: none;
}
.field-in:focus { border-color: var(--accent); }
.field-in::placeholder { color: var(--text-3); }

.pwd-row { position: relative; }
.pwd-row .field-in { padding-right: 44px; }
.pwd-eye {
  position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
  background: none; border: none; color: var(--text-2); cursor: pointer; display: flex; padding: 2px;
}
.pwd-eye svg { width: 18px; height: 18px; }

.err-box {
  background: var(--red-d); border: 1px solid rgba(255,69,58,.22);
  border-radius: var(--r); padding: 10px 12px; font-size: 13px; color: var(--red);
}

.btn-cta {
  width: 100%; background: var(--accent); border: none; border-radius: var(--r);
  padding: 14px; color: #fff; font-size: 15px; font-weight: 600;
  cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;
  transition: opacity .15s; -webkit-appearance: none;
}
.btn-cta:disabled { opacity: .4; cursor: not-allowed; }
.btn-cta:active:not(:disabled) { opacity: .8; }

.btn-spin {
  width: 15px; height: 15px;
  border: 2px solid rgba(255,255,255,.3); border-top-color: #fff;
  border-radius: 50%; animation: spin .55s linear infinite;
}

/* ── MAIN ──────────────────────────────────────────────── */
.main-app { display: flex; flex-direction: column; min-height: 100vh; min-height: 100dvh; }

.top-bar {
  height: var(--header-h);
  background: rgba(0,0,0,.85);
  backdrop-filter: saturate(180%) blur(20px);
  -webkit-backdrop-filter: saturate(180%) blur(20px);
  border-bottom: 1px solid var(--sep);
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 16px; position: sticky; top: 0; z-index: 100; flex-shrink: 0;
}

.top-logo { font-size: 16px; color: var(--text-2); letter-spacing: -0.2px; }
.top-logo strong { color: var(--text); font-weight: 700; }

.top-right { display: flex; align-items: center; gap: 10px; }
.top-user { font-size: 12px; color: var(--text-2); max-width: 130px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.icon-btn {
  width: 30px; height: 30px; background: var(--bg-1); border: 1px solid var(--sep2);
  border-radius: 50%; color: var(--text-2); cursor: pointer;
  display: flex; align-items: center; justify-content: center; transition: color .15s;
}
.icon-btn:active { color: var(--red); }
.icon-btn svg { width: 14px; height: 14px; }

.page-area {
  flex: 1; overflow-y: auto;
  padding-bottom: calc(var(--nav-h) + env(safe-area-inset-bottom, 0px));
}

/* ── TAB BAR ───────────────────────────────────────────── */
.tab-bar {
  height: var(--nav-h);
  background: rgba(28,28,30,.92);
  backdrop-filter: saturate(180%) blur(20px);
  -webkit-backdrop-filter: saturate(180%) blur(20px);
  border-top: 1px solid var(--sep);
  display: flex; position: fixed; bottom: 0; left: 0; right: 0; z-index: 100;
  padding-bottom: env(safe-area-inset-bottom, 0);
}

.tab-item {
  flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 3px; background: none; border: none; color: var(--text-3);
  cursor: pointer; padding: 6px 4px; transition: color .15s;
}
.tab-item.active { color: var(--accent); }

.tab-ico { display: flex; }
.tab-ico svg { width: 22px; height: 22px; }
.tab-lbl { font-size: 10px; font-weight: 600; }

/* ── TRANSITIONS ───────────────────────────────────────── */
.field-slide-enter-active, .field-slide-leave-active { transition: opacity .18s, max-height .25s; overflow: hidden; max-height: 80px; }
.field-slide-enter-from, .field-slide-leave-to { opacity: 0; max-height: 0; }

/* ── SHARED (usate da tutti i figli) ───────────────────── */

/* Grouped card (iOS settings style) */
.grp { margin: 16px 16px 0; }
.grp-label { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-2); padding: 0 4px; margin-bottom: 6px; }
.grp-card { background: var(--bg-1); border-radius: var(--r-lg); overflow: hidden; }
.grp-row {
  display: flex; align-items: center; gap: 12px; padding: 13px 16px;
  border-bottom: 1px solid var(--sep); min-height: 50px;
}
.grp-row:last-child { border-bottom: none; }
.row-icon {
  width: 32px; height: 32px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.row-icon svg { width: 16px; height: 16px; }
.row-text { flex: 1; display: flex; flex-direction: column; gap: 1px; }
.row-title { font-size: 15px; font-weight: 400; }
.row-sub { font-size: 12px; color: var(--text-2); }
.row-right { display: flex; align-items: center; gap: 6px; }

/* Role badges */
.rbadge { font-size: 10px; font-weight: 800; letter-spacing: 0.4px; padding: 2px 6px; border-radius: 4px; display: inline-block; flex-shrink: 0; }
.rbadge.POR { background: var(--yellow-d); color: var(--por-c); }
.rbadge.DIF { background: var(--green-d);  color: var(--dif-c); }
.rbadge.CEN { background: var(--accent-d); color: var(--cen-c); }
.rbadge.ATT { background: var(--red-d);    color: var(--att-c); }

/* Placeholder screen */
.ph-screen { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 55vh; padding: 40px 24px; text-align: center; gap: 8px; }
.ph-ico { width: 60px; height: 60px; background: var(--bg-1); border-radius: 20px; display: flex; align-items: center; justify-content: center; color: var(--text-3); margin-bottom: 4px; }
.ph-ico svg { width: 28px; height: 28px; }
.ph-title { font-size: 17px; font-weight: 700; }
.ph-sub { font-size: 14px; color: var(--text-2); line-height: 1.55; max-width: 250px; }

/* Shared buttons */
.btn-fill {
  background: var(--accent); border: none; border-radius: var(--r);
  padding: 14px; color: #fff; font-size: 15px; font-weight: 600;
  cursor: pointer; width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px;
  transition: opacity .15s;
}
.btn-fill:disabled { opacity: .4; cursor: not-allowed; }
.btn-fill:active:not(:disabled) { opacity: .8; }

.btn-ghost {
  background: var(--bg-1); border: 1px solid var(--sep2); border-radius: var(--r);
  padding: 12px; color: var(--text); font-size: 14px; font-weight: 600; cursor: pointer;
}
.btn-ghost:active { background: var(--bg-2); }

.btn-danger-ghost {
  background: var(--red-d); border: 1px solid rgba(255,69,58,.2); border-radius: var(--r);
  padding: 14px; color: var(--red); font-size: 15px; font-weight: 600;
  cursor: pointer; width: 100%; transition: background .15s;
}
.btn-danger-ghost:active { background: rgba(255,69,58,.3); }
</style>