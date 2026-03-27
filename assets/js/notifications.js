export async function initNotifications() {
    console.log("OneSignal pronto");
}

export function requestNotificationPermission() {
    window.OneSignalDeferred.push(function(OneSignal) {
        OneSignal.Notifications.requestPermission();
    });
}

export function areNotificationsEnabled() {
    if (!window.OneSignal) return false;
    return window.OneSignal.Notifications.permission === true || window.OneSignal.Notifications.permission === "granted";
}

export async function askForNotificationPermission() {
    if (!window.OneSignal) {
        console.warn("OneSignal non inizializzato");
        return false;
    }
    try {
        await window.OneSignal.Notifications.requestPermission();
        return window.OneSignal.Notifications.permission === true || window.OneSignal.Notifications.permission === "granted";
    } catch (err) {
        console.error("Errore richiesta notifiche:", err);
        return false;
    }
}