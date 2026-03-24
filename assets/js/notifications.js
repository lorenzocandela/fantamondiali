export async function initNotifications() {
    console.log("OneSignal pronto");
}

export function requestNotificationPermission() {
    window.OneSignalDeferred.push(function(OneSignal) {
        OneSignal.Notifications.requestPermission();
    });
}