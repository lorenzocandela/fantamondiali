<template>
  <div class="placeholder-screen">
    <div class="placeholder-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
      </svg>
    </div>
    <p class="placeholder-title">Classifica non disponibile</p>
    <p class="placeholder-sub">La classifica sara visibile una volta che il campionato sara iniziato.</p>
  </div>
</template>

<script setup>
defineProps({ user: Object });
</script>