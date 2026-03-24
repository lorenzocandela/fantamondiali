<div id="modal-overlay" class="modal-overlay hidden">
    <div class="modal-sheet" role="dialog" aria-modal="true">
        <div class="modal-handle"></div>
        <div class="modal-player-header">
            <img id="modal-photo" class="modal-photo" src="" alt="">
            <div>
                <div id="modal-name" class="modal-player-name"></div>
                <div id="modal-meta" class="modal-player-meta"></div>
            </div>
        </div>
        <div class="modal-stats-grid">
            <div class="modal-stat">
                <div class="modal-stat-val" id="modal-goals">-</div>
                <div class="modal-stat-lbl">Gol</div>
            </div>
            <div class="modal-stat">
                <div class="modal-stat-val" id="modal-assists">-</div>
                <div class="modal-stat-lbl">Assist</div>
            </div>
            <div class="modal-stat">
                <div class="modal-stat-val" id="modal-rating">-</div>
                <div class="modal-stat-lbl">Rating</div>
            </div>
        </div>
        <button id="modal-btn-add" class="modal-btn-add">
            <span class="material-icons-round">add_circle</span>
            <span id="modal-btn-label">Aggiungi</span>
        </button>
    </div>
</div>

<div id="formation-picker-overlay" class="hidden">
    <div id="formation-picker-sheet"></div>
</div>

<div id="toast-wrap" class="toast-wrap"></div>