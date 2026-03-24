import { db, messaging, getToken, onMessage } from './firebase-init.js';
import { doc, setDoc } from 'https://www.gstatic.com/firebasejs/10.8.1/firebase-firestore.js';
import { toast } from './utils.js';

const VAPID_KEY = 'BJE78g4H1YY7qQXW6qh8p7xAmxf5kuwBrl3it_QgQmhn8tBcOoBeDiNJSRGQPZAgOp7ULkJV06jMaCVlBDenyws';

let _tokenSaved = false;

export async function initNotifications() {
    if (!messaging) return;
    if (!('Notification' in window)) return;

    // ascolta messaggi in foreground
    onMessage(messaging, payload => {
        const data = payload.data ?? {};
        showInAppNotification(data.title ?? 'FantaMondiali', data.body ?? '');
    });

    // se già concesse, registra token silenziosamente
    if (Notification.permission === 'granted') {
        await registerToken();
    }
}

export async function requestNotificationPermission() {
    if (!messaging) { toast('Notifiche non supportate', 'error'); return false; }
    if (Notification.permission === 'granted') {
        await registerToken();
        return true;
    }
    if (Notification.permission === 'denied') {
        toast('Notifiche bloccate. Abilitale dalle impostazioni del browser.', 'error');
        return false;
    }

    const permission = await Notification.requestPermission();
    if (permission === 'granted') {
        await registerToken();
        toast('Notifiche attivate');
        return true;
    }
    return false;
}

async function registerToken() {
    if (_tokenSaved || !window.__user?.uid) return;
    try {
        const sw = await navigator.serviceWorker.ready;
        const token = await getToken(messaging, {
            vapidKey: VAPID_KEY,
            serviceWorkerRegistration: sw,
        });
        if (!token) return;

        await setDoc(doc(db, 'fcm_tokens', window.__user.uid), {
            token,
            updated_at: new Date().toISOString(),
            uid: window.__user.uid,
        });
        _tokenSaved = true;
    } catch (err) {
        console.warn('Errore registrazione token FCM:', err);
    }
}

function showInAppNotification(title, body) {
    const wrap = document.getElementById('toast-wrap');
    if (!wrap) return;
    const el = document.createElement('div');
    el.className = 'toast success';
    el.innerHTML = `<span class="material-symbols-outlined">notifications_active</span><div><strong>${title}</strong><br>${body}</div>`;
    el.style.whiteSpace = 'normal';
    el.style.maxWidth = '320px';
    el.style.textAlign = 'left';
    wrap.appendChild(el);
    setTimeout(() => {
        el.style.animation = 'toastOut 0.25s ease forwards';
        el.addEventListener('animationend', () => el.remove());
    }, 5000);
}

export function resetNotificationState() {
    _tokenSaved = false;
}