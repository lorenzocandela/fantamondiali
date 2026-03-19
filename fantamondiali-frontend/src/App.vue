<template>
  <div style="font-family: sans-serif; text-align: center; max-width: 1200px; margin: 0 auto; padding: 20px;">
    
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #eee; padding-bottom: 10px;">
      <h1 style="margin: 0;">🏆 FantaMondiali</h1>
      <div v-if="user">
        <span style="margin-right: 15px;">👤 {{ user.displayName || user.email }}</span>
        <button @click="logout" style="padding: 5px 15px; cursor: pointer; border-radius: 5px;">Esci</button>
      </div>
    </div>
    
    <div v-if="!user" style="margin-top: 100px;">
      <h2>Accedi per iniziare il mercato!</h2>
      <button @click="loginWithGoogle" style="padding: 12px 24px; font-size: 16px; cursor: pointer; background-color: #4285F4; color: white; border: none; border-radius: 5px; font-weight: bold;">
        Accedi con Google
      </button>
      <p style="color: red;">{{ errorMsg }}</p>
    </div>

    <div v-else style="margin-top: 30px;">
      <h2>🛒 Mercato Giocatori</h2>
      
      <div style="margin-bottom: 20px; display: flex; justify-content: center; gap: 10px; flex-wrap: wrap;">
        <input 
          v-model="searchQuery" 
          placeholder="Cerca un giocatore..." 
          style="padding: 10px; width: 250px; border-radius: 5px; border: 1px solid #ccc;"
        />
        <select v-model="selectedRole" style="padding: 10px; border-radius: 5px; border: 1px solid #ccc;">
          <option value="">Tutti i ruoli</option>
          <option value="POR">Portieri (POR)</option>
          <option value="DIF">Difensori (DIF)</option>
          <option value="CEN">Centrocampisti (CEN)</option>
          <option value="ATT">Attaccanti (ATT)</option>
        </select>
      </div>

      <p v-if="loading">Caricamento giocatori in corso... ⏳</p>

      <div v-else style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px;">
        <div 
          v-for="player in filteredPlayers" 
          :key="player.id" 
          style="border: 1px solid #ddd; border-radius: 10px; padding: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); background: #fff;"
        >
          <img :src="player.image_path" :alt="player.name" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 2px solid #eee;" />
          <h3 style="margin: 10px 0 5px 0; font-size: 16px;">{{ player.name }}</h3>
          <p style="margin: 0; color: #666; font-size: 14px;">{{ player.country }}</p>
          <span style="display: inline-block; margin-top: 10px; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 12px; background: #eee;">
            {{ player.position }}
          </span>
        </div>
      </div>
      
      <p v-if="!loading && filteredPlayers.length === 0">Nessun giocatore trovato con questi filtri.</p>

    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { auth } from './firebase';
import { GoogleAuthProvider, signInWithPopup, signOut, onAuthStateChanged } from 'firebase/auth';
import axios from 'axios';

const user = ref(null);
const errorMsg = ref('');
const players = ref([]);
const loading = ref(false);

// Filtri
const searchQuery = ref('');
const selectedRole = ref('');

// Controllo stato utente
onMounted(() => {
  onAuthStateChanged(auth, async (currentUser) => {
    user.value = currentUser;
    if (currentUser) {
      await fetchPlayers();
    }
  });
});

// Login
const loginWithGoogle = async () => {
  errorMsg.value = '';
  const provider = new GoogleAuthProvider();
  try {
    const result = await signInWithPopup(auth, provider);
    await axios.post('/api/users/sync', {
      firebase_uid: result.user.uid,
      email: result.user.email,
      team_name: `Team di ${result.user.displayName || 'Allenatore'}`
    });
    await fetchPlayers();
  } catch (error) {
    errorMsg.value = "Errore: " + error.message;
  }
};

// Logout
const logout = async () => {
  await signOut(auth);
  players.value = [];
};

// Scarica i giocatori dal nostro backend Node.js
const fetchPlayers = async () => {
  loading.value = true;
  try {
    const response = await axios.get('/api/players');
    players.value = response.data;
  } catch (error) {
    console.error("Errore nel caricamento giocatori:", error);
  } finally {
    loading.value = false;
  }
};

// Filtra i giocatori in tempo reale
const filteredPlayers = computed(() => {
  return players.value.filter(player => {
    const matchName = player.name.toLowerCase().includes(searchQuery.value.toLowerCase());
    const matchRole = selectedRole.value === '' || player.position === selectedRole.value;
    return matchName && matchRole;
  });
});
</script>

<style>
/* Reset base per rendere tutto pulito */
body {
  margin: 0;
  background-color: #f8f9fa;
  color: #333;
}
</style>
