<div id="incoming-call-overlay" class="tracker-incoming-call-banner d-none">
    <div class="tracker-incoming-call-card">
        <span class="tracker-call-kicker">Incoming Call</span>
        <div class="tracker-call-avatar tracker-incoming-call-avatar" id="incoming-call-avatar"></div>
        <div class="tracker-incoming-call-copy">
            <h3 class="tracker-call-name" id="incoming-call-name"></h3>
            <p class="tracker-call-status" id="incoming-call-type">Voice call</p>
        </div>
        <div class="tracker-call-actions">
            <button type="button" class="tracker-call-btn tracker-call-btn-decline" id="incoming-call-decline" title="Decline">
                <i class="fa-solid fa-phone-slash"></i>
            </button>
            <button type="button" class="tracker-call-btn tracker-call-btn-accept" id="incoming-call-accept" title="Accept">
                <i class="fa-solid fa-phone"></i>
            </button>
        </div>
    </div>
</div>

<div id="call-waiting-banner" class="tracker-call-waiting-banner d-none">
    <div class="tracker-call-waiting-avatar" id="call-waiting-avatar"></div>
    <div class="tracker-call-waiting-copy">
        <strong id="call-waiting-name"></strong>
        <span id="call-waiting-type">Voice call waiting…</span>
    </div>
    <div class="tracker-call-waiting-actions">
        <button type="button" class="tracker-call-waiting-btn tracker-call-waiting-btn-decline" id="call-waiting-decline" title="Decline">
            <i class="fa-solid fa-phone-slash"></i>
        </button>
        <button type="button" class="tracker-call-waiting-btn tracker-call-waiting-btn-accept" id="call-waiting-accept" title="Hold current call & accept">
            <i class="fa-solid fa-phone"></i>
        </button>
    </div>
</div>
