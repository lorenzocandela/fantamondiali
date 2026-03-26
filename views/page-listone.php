<section id="page-listone" class="page active">
    <div class="content-header">
        <button class="view-toggle-btn" id="view-toggle" aria-label="Cambia vista">
            <span class="material-symbols-outlined" id="view-toggle-icon">view_list</span>
        </button>
    </div>
    <div class="search-wrap">
        <input id="search-in" class="search-in" type="text" placeholder="Cerca giocatore o nazione...">
    </div>
    <div class="filter-row" id="role-filter-row">
        <button class="chip active" data-role="ALL">Tutti</button>
        <button class="chip" data-role="POR">Portieri</button>
        <button class="chip" data-role="DIF">Difensori</button>
        <button class="chip" data-role="CEN">Centrocampisti</button>
        <button class="chip" data-role="ATT">Attaccanti</button>
    </div>
    <div class="filter-row" id="nation-filter-row" style="padding-top:0;"></div>
    <div id="players-grid" class="players-grid"></div>
</section>