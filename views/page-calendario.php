<section id="page-calendario" class="page">
    <!-- VISTA PRINCIPALE: lista giornate + classifica -->
    <div id="cal-main-view">
        <div class="cal-seg-wrap">
            <div class="cal-seg">
                <button class="cal-seg-btn active" data-view="scontri">Scontri</button>
                <button class="cal-seg-btn" data-view="classifica">Classifica</button>
            </div>
        </div>

        <div id="cal-view-scontri">
            <div class="cal-round-nav">
                <button class="cal-nav-btn" id="cal-round-prev">
                    <span class="material-symbols-outlined">chevron_left</span>
                </button>
                <div class="cal-round-label" id="cal-round-label">Giornata 1</div>
                <button class="cal-nav-btn" id="cal-round-next">
                    <span class="material-symbols-outlined">chevron_right</span>
                </button>
            </div>
            <div id="cal-matches-list" class="cal-matches-list"></div>
        </div>

        <div id="cal-view-classifica" class="hidden">
            <div id="cal-standings-list" class="cal-standings-list"></div>
        </div>
    </div>

    <!-- VISTA DETTAGLIO MATCH: formazione + confronto -->
    <div id="cal-match-detail" class="hidden">
        <div id="match-detail-content"></div>
    </div>
</section>

<!-- picker formazione (riusato da formation.js) -->
<div id="formation-picker-overlay" class="fpicker-overlay hidden">
    <div id="formation-picker-sheet" class="fpicker-sheet"></div>
</div>