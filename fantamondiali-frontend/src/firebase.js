import { initializeApp } from "firebase/app";
import { getAuth } from "firebase/auth";

// I dati copiati dalla tua console Firebase
const firebaseConfig = {
  apiKey: "AIzaSyAUlVRrqZg8qL6_eYwSrp0czllt2IHL0eg",
  authDomain: "fantamondiali-e1f5c.firebaseapp.com",
  projectId: "fantamondiali-e1f5c",
  storageBucket: "fantamondiali-e1f5c.firebasestorage.app",
  messagingSenderId: "607003325581",
  appId: "1:607003325581:web:ac19cf13f7e5e401f75ec4"
};

// Inizializza Firebase
const app = initializeApp(firebaseConfig);

// Inizializza l'autenticazione ed esportala
export const auth = getAuth(app);
