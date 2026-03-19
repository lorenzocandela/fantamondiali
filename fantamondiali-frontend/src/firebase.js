import { initializeApp } from "firebase/app";
import { getAuth } from "firebase/auth";
import { getFirestore, enableIndexedDbPersistence } from "firebase/firestore";

const firebaseConfig = {
  apiKey: import.meta.env.VITE_FIREBASE_API_KEY,
  authDomain: "fantamondiali-e1f5c.firebaseapp.com",
  projectId: "fantamondiali-e1f5c",
  storageBucket: "fantamondiali-e1f5c.firebasestorage.app",
  messagingSenderId: "607003325581",
  appId: "1:607003325581:web:ac19cf13f7e5e401f75ec4"
};

const app = initializeApp(firebaseConfig);

export const auth = getAuth(app);
export const db   = getFirestore(app);

enableIndexedDbPersistence(db).catch(() => {});