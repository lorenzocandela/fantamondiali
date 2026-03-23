import { initializeApp } from "https://www.gstatic.com/firebasejs/10.8.1/firebase-app.js";
import { getAuth } from "https://www.gstatic.com/firebasejs/10.8.1/firebase-auth.js";
import { getFirestore } from "https://www.gstatic.com/firebasejs/10.8.1/firebase-firestore.js";

const firebaseConfig = {
    apiKey: "AIzaSyAUlVRrqZg8qL6_eYwSrp0czllt2IHL0eg",
    authDomain: "fantamondiali-e1f5c.firebaseapp.com",
    projectId: "fantamondiali-e1f5c",
    storageBucket: "fantamondiali-e1f5c.firebasestorage.app",
    messagingSenderId: "607003325581",
    appId: "1:607003325581:web:ac19cf13f7e5e401f75ec4"
};

const firebaseApp = initializeApp(firebaseConfig);

export const auth = getAuth(firebaseApp);
export const db   = getFirestore(firebaseApp);