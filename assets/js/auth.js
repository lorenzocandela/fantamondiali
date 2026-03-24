import { auth, db } from "./firebase-init.js";
import {
    signInWithEmailAndPassword,
    createUserWithEmailAndPassword,
    signOut,
    onAuthStateChanged
} from "https://www.gstatic.com/firebasejs/10.8.1/firebase-auth.js";
import {
    doc,
    setDoc,
    getDoc,
    serverTimestamp
} from "https://www.gstatic.com/firebasejs/10.8.1/firebase-firestore.js";

const authScreen     = document.getElementById('auth-screen');
const mainApp        = document.getElementById('main-app');
const tabLogin       = document.getElementById('tab-login');
const tabRegister    = document.getElementById('tab-register');
const registerFields = document.getElementById('register-fields');
const btnSubmit      = document.getElementById('btn-submit');
const btnLabel       = document.getElementById('btn-label');
const errBox         = document.getElementById('error-msg');
const errText        = document.getElementById('error-text');
const togglePwd      = document.getElementById('toggle-pwd');
const pwdInput       = document.getElementById('password');

let isRegister = false;

function showError(msg) {
    errText.textContent = msg;
    errBox.classList.add('show');
}

function hideError() {
    errBox.classList.remove('show');
}

function setLoading(state) {
    btnSubmit.disabled = state;
    btnLabel.textContent = state ? '' : (isRegister ? 'Crea account' : 'Accedi');
    if (state) {
        const s = document.createElement('div');
        s.className = 'spinner';
        btnSubmit.appendChild(s);
    } else {
        btnSubmit.querySelector('.spinner')?.remove();
    }
}

function switchMode(register) {
    isRegister = register;
    tabLogin.classList.toggle('active', !register);
    tabRegister.classList.toggle('active', register);
    registerFields.classList.toggle('hidden', !register);
    btnLabel.textContent = register ? 'Crea account' : 'Accedi';
    hideError();
}

tabLogin.addEventListener('click',    () => switchMode(false));
tabRegister.addEventListener('click', () => switchMode(true));

togglePwd.addEventListener('click', () => {
    const icon = togglePwd.querySelector('.material-icons-round');
    const isPassword = pwdInput.type === 'password';
    pwdInput.type = isPassword ? 'text' : 'password';
    icon.textContent = isPassword ? 'visibility_off' : 'visibility';
});

btnSubmit.addEventListener('click', handleSubmit);

document.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !authScreen.classList.contains('hidden')) handleSubmit();
});

async function handleSubmit() {
    hideError();

    const email    = document.getElementById('email').value.trim();
    const password = pwdInput.value.trim();
    const teamName = document.getElementById('team-name').value.trim();

    if (!email || !password) { showError('Compila tutti i campi'); return; }
    if (isRegister && !teamName) { showError('Inserisci il nome squadra'); return; }

    setLoading(true);

    try {
        if (isRegister) {
            const cred = await createUserWithEmailAndPassword(auth, email, password);
            await setDoc(doc(db, 'users', cred.user.uid), {
                email:      cred.user.email,
                team_name:  teamName,
                credits:    500,
                players:    [],
                created_at: serverTimestamp()
            });
        } else {
            await signInWithEmailAndPassword(auth, email, password);
        }
    } catch (err) {
        showError(mapAuthError(err.code));
    } finally {
        setLoading(false);
    }
}

function resetAppState() {
    window.__user              = null;
    window.__myTeam            = [];
    window.__settings          = {};
    window.__competitionActive = false;

    document.getElementById('nav-admin')?.classList.add('hidden');

    const img      = document.getElementById('topbar-avatar-img');
    const initials = document.getElementById('topbar-avatar-initials');
    if (img)      { img.src = ''; img.classList.add('hidden'); }
    if (initials) { initials.textContent = '?'; initials.classList.remove('hidden'); }

    const creditsEl = document.getElementById('credits-val');
    if (creditsEl) creditsEl.textContent = '500';

    document.getElementById('profilo-hero-card')?.remove();
    const oldSection = document.querySelector('.profilo-avatar-section');
    if (oldSection) oldSection.style.display = '';
}

function applyAvatarToTopbar(data) {
    const img      = document.getElementById('topbar-avatar-img');
    const initials = document.getElementById('topbar-avatar-initials');
    if (!img || !initials) return;

    if (data.avatar) {
        img.src = data.avatar;
        img.classList.remove('hidden');
        initials.classList.add('hidden');
    } else {
        img.classList.add('hidden');
        initials.classList.remove('hidden');
        const raw = data.team_name ?? data.email ?? '?';
        initials.textContent = raw.split(/[\s@]+/).map(w => w[0]).join('').slice(0, 2).toUpperCase();
    }
}

onAuthStateChanged(auth, async user => {
    if (user) {
        resetAppState();

        const snap = await getDoc(doc(db, 'users', user.uid));
        const data = snap.exists() ? snap.data() : { team_name: user.email, credits: 500, players: [] };

        window.__user   = { uid: user.uid, email: user.email, ...data };
        window.__myTeam = data.players ?? [];

        document.getElementById('credits-val').textContent = data.credits ?? 500;

        applyAvatarToTopbar(data);

        authScreen.classList.add('hidden');
        mainApp.classList.remove('hidden');

        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                const bar    = document.querySelector('.tab-bar');
                const pill   = bar?.querySelector('.tab-pill');
                const active = bar?.querySelector('.tab-item.active');
                if (pill && active) {
                    const br = bar.getBoundingClientRect();
                    const tr = active.getBoundingClientRect();
                    pill.style.transition = 'none';
                    pill.style.left   = (tr.left - br.left) + 'px';
                    pill.style.width  = tr.width + 'px';
                    pill.style.height = tr.height + 'px';
                    requestAnimationFrame(() => { pill.style.transition = ''; });
                }
            });
        });

        document.dispatchEvent(new CustomEvent('app:ready', { detail: window.__user }));
    } else {
        resetAppState();
        mainApp.classList.add('hidden');
        authScreen.classList.remove('hidden');
    }
});

document.getElementById('btn-logout')?.addEventListener('click', () => signOut(auth));

document.getElementById('btn-profile-avatar')?.addEventListener('click', () => {
    document.dispatchEvent(new CustomEvent('goto:profilo'));
});

function mapAuthError(code) {
    const map = {
        'auth/invalid-credential':   'Email o password non corretti.',
        'auth/email-already-in-use': 'Email gia registrata.',
        'auth/weak-password':        'Password minimo 6 caratteri.',
        'auth/invalid-email':        'Formato email non valido.',
        'auth/user-not-found':       'Nessun account con questa email.',
        'auth/wrong-password':       'Password non corretta.',
        'auth/too-many-requests':    'Troppi tentativi, riprova tra qualche minuto.',
    };
    return map[code] ?? 'Errore imprevisto. Riprova.';
}