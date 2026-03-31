<section id="page-competizione" class="page">
    <div class="content-header" style="background: var(--bg-1); padding: 10px 16px; position: sticky; top: 0; z-index: 10; border-bottom: 1px solid var(--border-color);">
        <div class="cal-seg-control">
            <button class="comp-seg-btn active" data-view="squadre">Fanta Squadre</button>
            <button class="comp-seg-btn" data-view="mondiali">Mondiali Reali</button>
        </div>
    </div>

    <div id="comp-view-squadre">
        <div class="comp-banner">
            <div class="comp-banner-icon">
                <span class="material-symbols-outlined">emoji_events</span>
            </div>
            <div>
                <div class="comp-banner-title">FM26</div>
                <div class="comp-banner-sub">La competizione ufficiale è attiva</div>
            </div>
            <div class="comp-status-badge">ATTESA</div>
        </div>

        <div id="comp-teams-list" class="comp-teams-list"></div>
    </div>

    <div id="comp-view-mondiali" class="hidden">
        <div id="mondiali-matches-list" style="padding-top: 10px;"></div>
    </div>
</section>