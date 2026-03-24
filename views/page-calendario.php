<section id="page-calendario" class="page">
    <div class="content-header">
        <div>
            <h2 class="page-title">Calendario</h2>
            <p class="page-subtitle" id="cal-subtitle">scontri diretti</p>
        </div>
    </div>

    <div class="cal-seg-wrap">
        <div class="cal-seg">
            <button class="cal-seg-btn active" data-view="scontri">Scontri</button>
            <button class="cal-seg-btn" data-view="classifica">Classifica</button>
        </div>
    </div>

    <div id="cal-view-scontri">
        <div class="cal-round-nav">
            <button class="cal-nav-btn" id="cal-round-prev">
                <span class="material-icons-round">chevron_left</span>
            </button>
            <div class="cal-round-label" id="cal-round-label">Giornata 1</div>
            <button class="cal-nav-btn" id="cal-round-next">
                <span class="material-icons-round">chevron_right</span>
            </button>
        </div>
        <div id="cal-matches-list" class="cal-matches-list"></div>
    </div>

    <div id="cal-view-classifica" class="hidden">
        <div id="cal-standings-list" class="cal-standings-list"></div>
    </div>
</section>