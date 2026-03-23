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

onAuthStateChanged(auth, async user => {
    if (user) {
        const snap = await getDoc(doc(db, 'users', user.uid));
        const data = snap.exists() ? snap.data() : { team_name: user.email, credits: 500 };

        window.__user = { uid: user.uid, ...data };

        document.getElementById('credits-val').textContent = data.credits ?? 500;

        authScreen.classList.add('hidden');
        mainApp.classList.remove('hidden');

        document.dispatchEvent(new CustomEvent('app:ready', { detail: window.__user }));
    } else {
        window.__user = null;
        mainApp.classList.add('hidden');
        authScreen.classList.remove('hidden');
    }
});

document.getElementById('btn-logout')?.addEventListener('click', () => signOut(auth));

document.getElementById('btn-profile-avatar')?.addEventListener('click', () => {
    if (typeof showPage === 'function') showPage('profilo');
    else document.dispatchEvent(new CustomEvent('goto:profilo'));
});

document.addEventListener('goto:profilo', () => {
    if (typeof showPage === 'function') showPage('profilo');
});

function mapAuthError(code) {
    const map = {
        'auth/invalid-credential':   'Email o password non corretti.',
        'auth/email-already-in-use': 'Email gia registrata.',
        'auth/weak-password':        'Password minimo 6 caratteri.',
        'auth/invalid-email':        'Formato email non valido.',
    };
    return map[code] ?? 'Errore imprevisto. Riprova.';
}