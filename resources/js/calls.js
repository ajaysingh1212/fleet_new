const RING_INTERVAL_MS = 1800;

function ringTone() {
    try {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        const ctx = new AudioContextClass();
        [0, 0.15].forEach((delay) => {
            const oscillator = ctx.createOscillator();
            const gain = ctx.createGain();
            oscillator.type = 'sine';
            oscillator.frequency.value = 480;
            gain.gain.setValueAtTime(0.001, ctx.currentTime + delay);
            gain.gain.linearRampToValueAtTime(0.18, ctx.currentTime + delay + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + delay + 0.28);
            oscillator.connect(gain);
            gain.connect(ctx.destination);
            oscillator.start(ctx.currentTime + delay);
            oscillator.stop(ctx.currentTime + delay + 0.3);
        });
    } catch (e) {
        // Web Audio unsupported/blocked — silently skip the ringtone.
    }
}

function startRingLoop() {
    ringTone();
    const interval = setInterval(ringTone, RING_INTERVAL_MS);

    return () => clearInterval(interval);
}

async function ensureCallMediaAccess(type) {
    if (!navigator.mediaDevices?.getUserMedia) {
        throw new Error('media_devices_unavailable');
    }

    const stream = await navigator.mediaDevices.getUserMedia({
        audio: true,
        video: type === 'video',
    });

    stream.getTracks().forEach((track) => track.stop());
}

export function initIncomingCallRinger() {
    const userId = window.__trackerUserId;

    if (!userId || !window.Echo) return;

    const overlay = document.getElementById('incoming-call-overlay');

    if (!overlay) return;

    const avatarEl = document.getElementById('incoming-call-avatar');
    const nameEl = document.getElementById('incoming-call-name');
    const typeEl = document.getElementById('incoming-call-type');
    const acceptBtn = document.getElementById('incoming-call-accept');
    const declineBtn = document.getElementById('incoming-call-decline');

    const waitingBanner = document.getElementById('call-waiting-banner');
    const waitingAvatarEl = document.getElementById('call-waiting-avatar');
    const waitingNameEl = document.getElementById('call-waiting-name');
    const waitingTypeEl = document.getElementById('call-waiting-type');
    const waitingAcceptBtn = document.getElementById('call-waiting-accept');
    const waitingDeclineBtn = document.getElementById('call-waiting-decline');

    let currentCall = null;
    let stopRinging = null;
    let conversationChannel = null;

    let currentWaitingCall = null;
    let stopWaitingChime = null;
    let waitingConversationChannel = null;

    const handOffAcceptedCall = (call) => {
        if (!document.getElementById('chat-conversations-data')) {
            const params = new URLSearchParams({
                conversation: call.conversation_uuid,
                call: call.call_uuid,
                call_type: call.type,
            });

            window.location.assign(`/chats?${params.toString()}`);

            return;
        }

        document.dispatchEvent(new CustomEvent('tracker:call-accepted', { detail: call }));
        window.dispatchEvent(new CustomEvent('tracker:call-accepted', { detail: call }));
    };

    const dismiss = () => {
        stopRinging?.();
        stopRinging = null;
        overlay.classList.add('d-none');
        currentCall = null;
    };

    const dismissWaiting = () => {
        stopWaitingChime?.();
        stopWaitingChime = null;
        waitingBanner?.classList.add('d-none');
        currentWaitingCall = null;
    };

    window.Echo.private(`App.Models.User.${userId}`).listen('.call.incoming', (payload) => {
        // A second call arriving while the callee is already mid-conversation:
        // don't hijack the screen with the full ringer — a small banner with
        // "Hold & Accept" (WhatsApp-style call waiting) is enough.
        if (payload.is_call_waiting) {
            currentWaitingCall = payload;
            waitingAvatarEl.textContent = (payload.caller.name || '?').trim().charAt(0).toUpperCase();
            waitingNameEl.textContent = payload.caller.name;
            waitingTypeEl.textContent = `${payload.type === 'video' ? 'Video' : 'Voice'} call waiting…`;
            waitingBanner?.classList.remove('d-none');
            stopWaitingChime = startRingLoop();

            waitingConversationChannel = window.Echo.private(`conversation.${payload.conversation_uuid}`);
            waitingConversationChannel.listen('.call.ended', () => dismissWaiting());
            waitingConversationChannel.listen('.call.rejected', () => dismissWaiting());

            return;
        }

        currentCall = payload;
        avatarEl.textContent = (payload.caller.name || '?').trim().charAt(0).toUpperCase();
        nameEl.textContent = payload.caller.name;
        typeEl.textContent = payload.type === 'video' ? 'Video call' : 'Voice call';
        overlay.classList.remove('d-none');
        stopRinging = startRingLoop();

        // The callee might not be on /chats (and so not subscribed to this
        // conversation's channel) — subscribe here too so a cancelled/timed
        // out call dismisses the ringer instead of ringing forever.
        conversationChannel = window.Echo.private(`conversation.${payload.conversation_uuid}`);
        conversationChannel.listen('.call.ended', () => dismiss());
        conversationChannel.listen('.call.rejected', () => dismiss());
    });

    acceptBtn.addEventListener('click', async () => {
        if (!currentCall) return;

        const call = currentCall;

        try {
            await ensureCallMediaAccess(call.type);
        } catch (error) {
            alert('Please allow microphone/camera permission before accepting the call.');

            return;
        }

        dismiss();

        try {
            await window.axios.post(`/api/v1/calls/${call.call_uuid}/accept`);
        } catch (error) {
            alert(error.response?.data?.message ?? 'Could not accept the call.');

            return;
        }

        handOffAcceptedCall(call);
    });

    declineBtn.addEventListener('click', async () => {
        if (!currentCall) return;

        const call = currentCall;
        dismiss();

        await window.axios.post(`/api/v1/calls/${call.call_uuid}/reject`).catch(() => {});
    });

    waitingAcceptBtn?.addEventListener('click', async () => {
        if (!currentWaitingCall) return;

        const call = currentWaitingCall;

        try {
            await ensureCallMediaAccess(call.type);
        } catch (error) {
            alert('Please allow microphone/camera permission before accepting the call.');

            return;
        }

        dismissWaiting();

        // The current call can only be "held" from the page that's actually
        // running it — private-chat.js owns that state, so hand off instead
        // of duplicating hold logic here.
        document.dispatchEvent(new CustomEvent('tracker:call-waiting-accept', { detail: call }));
        window.dispatchEvent(new CustomEvent('tracker:call-waiting-accept', { detail: call }));
    });

    waitingDeclineBtn?.addEventListener('click', async () => {
        if (!currentWaitingCall) return;

        const call = currentWaitingCall;
        dismissWaiting();

        await window.axios.post(`/api/v1/calls/${call.call_uuid}/reject`).catch(() => {});
    });
}
