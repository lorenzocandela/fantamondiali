<template>
  <div class="listone-container">
    <h2 class="page-title">🛒 Listone e Mercato</h2>
    
    <div class="filters-card">
      <input 
        v-model="searchQuery" 
        placeholder="Cerca per nome..." 
        class="filter-input"
      />
      
      <div class="select-group">
        <select v-model="selectedRole" class="filter-select">
          <option value="">Tutti i ruoli</option>
          <option value="POR">Portieri (POR)</option>
          <option value="DIF">Difensori (DIF)</option>
          <option value="CEN">Centrocampisti (CEN)</option>
          <option value="ATT">Attaccanti (ATT)</option>
        </select>

        <select v-model="selectedCountry" class="filter-select">
          <option value="">Tutte le nazioni</option>
          <option v-for="country in availableCountries" :key="country" :value="country">
            {{ country }}
          </option>
        </select>
      </div>
    </div>

    <div v-if="loading" class="loading">Caricamento giocatori in corso... ⏳</div>

    <div v-else class="players-grid">
      <div v-for="player in filteredPlayers" :key="player.id" class="player-card">
        <div class="player-header">
          <span :class="['role-badge', player.position]">{{ player.position }}</span>
          <span class="country-name">{{ player.country }}</span>
        </div>
        
        <img :src="player.image_path" :alt="player.name" class="player-img" />
        <h3 class="player-name">{{ player.name }}</h3>
        
        <button class="buy-btn" @click="startAuction(player)">
          Acquista
        </button>
      </div>
    </div>
    
    <div v-if="!loading && filteredPlayers.length === 0" class="no-results">
      Nessun giocatore trovato. 😢
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const players = ref([]);
const loading = ref(false);

// Stati per i filtri
const searchQuery = ref('');
const selectedRole = ref('');
const selectedCountry = ref('');

// Carica i giocatori all'avvio del componente
onMounted(async () => {
  loading.value = true;
  try {
    const response = await axios.get('/api/players');
    players.value = response.data;
  } catch (error) {
    console.error("Errore nel caricamento giocatori:", error);
  } finally {
    loading.value = false;
  }
});

// Estrae la lista delle nazioni uniche per il menu a tendina
const availableCountries = computed(() => {
  const countries = players.value.map(p => p.country);
  return [...new Set(countries)].sort(); // Rimuove i duplicati e in ordine alfabetico
});

// Filtro combinato (Nome + Ruolo + Nazione)
const filteredPlayers = computed(() => {
  return players.value.filter(player => {
    const matchName = player.name.toLowerCase().includes(searchQuery.value.toLowerCase());
    const matchRole = selectedRole.value === '' || player.position === selectedRole.value;
    const matchCountry = selectedCountry.value === '' || player.country === selectedCountry.value;
    return matchName && matchRole && matchCountry;
  });
});

// Placeholder per la logica dell'Asta
const startAuction = (player) => {
  alert(`Hai cliccato su ${player.name}! Nel prossimo step apriremo il popup per fare l'offerta.`);
};
</script>

<style scoped>
.listone-container { padding-bottom: 20px; }
.page-title { text-align: center; color: #1a1a1a; margin-bottom: 20px; font-size: 22px; }

/* Stile Filtri PWA */
.filters-card { background: white; padding: 15px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; flex-direction: column; gap: 10px; }
.filter-input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; box-sizing: border-box; }
.select-group { display: flex; gap: 10px; }
.filter-select { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; background: white; }

/* Stile Griglia e Card PWA */
.players-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; }
.player-card { background: white; border-radius: 12px; padding: 12px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.08); display: flex; flex-direction: column; align-items: center; position: relative; }
.player-header { width: 100%; display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-size: 11px; font-weight: bold; }
.country-name { color: #666; text-transform: uppercase; font-size: 10px; }

/* Colori Ruoli Fantacalcio */
.role-badge { padding: 3px 6px; border-radius: 4px; color: white; }
.role-badge.POR { background-color: #f1c40f; color: #000; }
.role-badge.DIF { background-color: #2ecc71; }
.role-badge.CEN { background-color: #3498db; }
.role-badge.ATT { background-color: #e74c3c; }

.player-img { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid #f4f7f6; margin-bottom: 8px; }
.player-name { margin: 0; font-size: 14px; color: #333; flex-grow: 1; }

.buy-btn { margin-top: 10px; width: 100%; background: #4285F4; color: white; border: none; padding: 8px; border-radius: 6px; font-weight: bold; cursor: pointer; transition: background 0.2s; }
.buy-btn:active { background: #3367d6; }

.loading, .no-results { text-align: center; color: #666; padding: 20px; }
</style>