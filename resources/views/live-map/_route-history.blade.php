<div class="modal fade" id="route-history-modal" tabindex="-1" aria-labelledby="route-history-title" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="route-history-title">Route History</h5>
                <button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 align-items-end mb-3">
                    <div class="col-12 col-md-3">
                        <label for="route-history-preset" class="form-label">Range</label>
                        <select id="route-history-preset" class="form-select">
                            <option value="today">Today</option><option value="yesterday">Yesterday</option>
                            <option value="last_7_days">Last 7 days</option><option value="last_30_days">Last 30 days</option>
                            <option value="custom">Custom dates</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3"><label for="route-history-from" class="form-label">From</label><input id="route-history-from" class="form-control" type="date" required></div>
                    <div class="col-6 col-md-3"><label for="route-history-to" class="form-label">To</label><input id="route-history-to" class="form-control" type="date" required></div>
                    <div class="col-12 col-md-3 d-flex gap-2">
                        <button id="route-history-load" class="btn tracker-primary-btn" title="Preview route" aria-label="Preview route"><i class="fa-solid fa-route"></i></button>
                        <a id="route-history-download" class="btn tracker-outline-btn disabled" title="Download CSV" aria-label="Download CSV" aria-disabled="true"><i class="fa-solid fa-download"></i></a>
                    </div>
                </div>
                <p id="route-history-status" class="small mb-2" role="status" aria-live="polite"></p>
                <div id="route-history-map" style="height: min(42vh, 420px); min-height: 220px; width: 100%;"></div>
                <div class="d-flex flex-wrap align-items-center gap-2 py-2 border-bottom">
                    <button type="button" id="route-replay-play" class="btn tracker-primary-btn" title="Play" aria-label="Play" aria-pressed="false" disabled><i class="fa-solid fa-play"></i></button>
                    <button type="button" id="route-replay-restart" class="btn tracker-outline-btn" title="Restart" aria-label="Restart" disabled><i class="fa-solid fa-backward-step"></i></button>
                    <select id="route-replay-speed" class="form-select" style="width: 76px;" aria-label="Replay speed" disabled>
                        <option value="1">1x</option><option value="2">2x</option><option value="4">4x</option><option value="8">8x</option>
                    </select>
                    <input type="range" id="route-replay-seek" min="0" max="0" step="0.01" value="0" class="form-range flex-grow-1 mb-0" style="width: auto; min-width: 100px; flex-basis: 150px;" aria-label="Replay timeline" disabled>
                    <label class="form-check-label d-flex align-items-center gap-1"><input type="checkbox" id="route-replay-follow" class="form-check-input mt-0" checked>Follow</label>
                </div>
                <div class="d-flex flex-wrap justify-content-between gap-2 py-2" style="min-height: 58px;">
                    <div style="min-width: 0; overflow-wrap: anywhere;"><strong id="route-replay-location">No route selected</strong><div id="route-replay-coordinates" class="small text-muted"></div></div>
                    <time id="route-replay-time" class="small text-muted"></time>
                </div>
            </div>
        </div>
    </div>
</div>
