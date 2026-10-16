import './bootstrap';
import { Modal } from 'bootstrap';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const REACTION_EMOJIS = ['👍', '❤️', '😂', '😮', '😢', '🙏'];
const TYPING_IDLE_MS = 2500;
const TYPING_THROTTLE_MS = 2000;
const PRESENCE_ONLINE_GRACE_MS = 90_000;
const IDLE_SPEED_MPS = 0.6;

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';

    return div.innerHTML;
}

function initials(name) {
    return (name || '?').trim().charAt(0).toUpperCase();
}

function avatarHtml({ name, avatar_url: avatarUrl, icon = null }) {
    if (icon) {
        return `<i class="${icon}"></i>`;
    }

    if (avatarUrl) {
        return `<img src="${escapeHtml(avatarUrl)}" alt="${escapeHtml(name ?? 'Avatar')}">`;
    }

    return escapeHtml(initials(name));
}

async function requestCallMediaStream(type) {
    if (!navigator.mediaDevices?.getUserMedia) {
        throw new Error('media_devices_unavailable');
    }

    return navigator.mediaDevices.getUserMedia({
        audio: true,
        video: type === 'video',
    });
}

function alertCallMediaError() {
    alert('Please allow microphone/camera permission to start the call.');
}

function formatTime(iso) {
    if (!iso) return '';

    return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function formatFileSize(bytes) {
    if (!bytes && bytes !== 0) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function formatLastSeen(iso) {
    if (!iso) return 'Offline';

    const diffSeconds = Math.round((Date.now() - new Date(iso).getTime()) / 1000);

    if (diffSeconds < 60) return `Last seen just now`;
    if (diffSeconds < 3600) return `Last seen ${Math.round(diffSeconds / 60)}m ago`;

    return `Last seen ${Math.round(diffSeconds / 3600)}h ago`;
}

function isPresenceOnline(state) {
    if (!state?.isOnline) return false;
    if (!state.lastActivity) return true;

    return Date.now() - new Date(state.lastActivity).getTime() <= PRESENCE_ONLINE_GRACE_MS;
}

function formatRelativeTime(iso) {
    if (!iso) return '';

    const diffSeconds = Math.round((Date.now() - new Date(iso).getTime()) / 1000);

    if (diffSeconds < 5) return 'Updated just now';
    if (diffSeconds < 60) return `Updated ${diffSeconds}s ago`;
    if (diffSeconds < 3600) return `Updated ${Math.round(diffSeconds / 60)}m ago`;

    return `Updated ${Math.round(diffSeconds / 3600)}h ago`;
}

function haversineMeters(lat1, lon1, lat2, lon2) {
    const toRad = (deg) => (deg * Math.PI) / 180;
    const R = 6371000;
    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);
    const a = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) ** 2;

    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function bearingDegrees(lat1, lon1, lat2, lon2) {
    const toRad = (deg) => (deg * Math.PI) / 180;
    const toDeg = (rad) => (rad * 180) / Math.PI;
    const y = Math.sin(toRad(lon2 - lon1)) * Math.cos(toRad(lat2));
    const x = Math.cos(toRad(lat1)) * Math.sin(toRad(lat2)) - Math.sin(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.cos(toRad(lon2 - lon1));

    return (toDeg(Math.atan2(y, x)) + 360) % 360;
}

function headingToCompass(deg) {
    if (typeof deg !== 'number' || Number.isNaN(deg)) return null;

    const directions = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];

    return directions[Math.round((((deg % 360) + 360) % 360) / 45) % 8];
}

function computeMotionState(prevFix, position) {
    const { latitude, longitude, speed, heading } = position.coords;
    const now = Date.now();

    let derivedSpeed = typeof speed === 'number' ? speed : null;
    let derivedHeading = typeof heading === 'number' && !Number.isNaN(heading) ? heading : null;

    if ((derivedSpeed === null || derivedHeading === null) && prevFix) {
        const elapsedSeconds = (now - prevFix.at) / 1000;

        if (elapsedSeconds > 0.5) {
            const distance = haversineMeters(prevFix.lat, prevFix.lng, latitude, longitude);

            if (derivedSpeed === null) derivedSpeed = distance / elapsedSeconds;
            if (derivedHeading === null && distance > 2) derivedHeading = bearingDegrees(prevFix.lat, prevFix.lng, latitude, longitude);
        }
    }

    return {
        fix: { lat: latitude, lng: longitude, at: now },
        speed: derivedSpeed,
        heading: derivedHeading,
        isIdle: !derivedSpeed || derivedSpeed < IDLE_SPEED_MPS,
    };
}

function liveLocationMarkerIcon({ avatarUrl, name, variant, isIdle, heading }) {
    const avatarInner = avatarUrl
        ? `<img src="${escapeHtml(avatarUrl)}" alt="${escapeHtml(name ?? '')}">`
        : `<span class="tracker-live-marker-initials">${escapeHtml(initials(name))}</span>`;

    const arrow = (!isIdle && typeof heading === 'number')
        ? `<span class="tracker-live-marker-heading" style="transform: translateX(-50%) rotate(${heading}deg);"><i class="fa-solid fa-caret-up"></i></span>`
        : '';

    return L.divIcon({
        className: `tracker-live-marker tracker-live-marker-${variant} ${isIdle ? 'is-idle' : 'is-moving'}`,
        html: `
            <div class="tracker-live-marker-badge">
                ${arrow}
                <div class="tracker-live-marker-avatar">${avatarInner}</div>
                <span class="tracker-live-marker-status-dot"></span>
            </div>
            <span class="tracker-live-marker-label">${escapeHtml(name ?? '')}</span>
        `,
        iconSize: [46, 64],
        iconAnchor: [23, 46],
        popupAnchor: [0, -46],
    });
}

class PrivateChat {
    constructor({ conversations, contacts }) {
        this.currentUserId = window.__trackerUserId ?? null;
        this.currentUserName = window.__trackerUserName ?? '';
        this.conversations = new Map(conversations.map((c) => [c.uuid, c]));
        this.contacts = contacts;
        this.currentUuid = null;
        this.channels = new Map();
        this.presenceChannels = new Map();
        this.presence = new Map();
        this.replyTo = null;
        this.editingUuid = null;
        this.lastTypingWhisperAt = 0;
        this.typingUsers = new Map();
        this.typingTimers = {};
        this.currentGroupDetail = null;
        this.newGroupAddToUuid = null;
        this.isMobileConversationOpen = false;
        this.messageSearchTerm = '';
        this.showPinnedOnly = false;
        this.showStarredOnly = false;

        this.listEl = document.getElementById('chat-conversation-list');
        this.listEmptyEl = document.getElementById('chat-conversation-empty');
        this.searchEl = document.getElementById('chat-conversation-search');
        this.contactListEl = document.getElementById('chat-contact-list');
        this.contactEmptyEl = document.getElementById('chat-contact-empty');
        this.windowEmptyEl = document.getElementById('chat-window-empty');
        this.windowEl = document.getElementById('chat-window');
        this.headerAvatarEl = document.getElementById('chat-header-avatar');
        this.headerNameEl = document.getElementById('chat-header-name');
        this.headerStatusEl = document.getElementById('chat-header-status');
        this.messagesEl = document.getElementById('chat-messages');
        this.messageSearchEl = document.getElementById('chat-message-search');
        this.replyBarEl = document.getElementById('chat-reply-bar');
        this.replyLabelEl = document.getElementById('chat-reply-label');
        this.replyBodyEl = document.getElementById('chat-reply-body');
        this.editBarEl = document.getElementById('chat-edit-bar');
        this.composerEl = document.getElementById('chat-composer');
        this.composerInputEl = document.getElementById('chat-composer-input');
        this.groupInfoBtnEl = document.getElementById('chat-group-info-btn');
        this.profileInfoBtnEl = document.getElementById('chat-profile-info-btn');
        this.attachmentInputEl = document.getElementById('chat-attachment-input');
        this.photoInputEl = document.getElementById('chat-photo-input');
        this.documentInputEl = document.getElementById('chat-document-input');
        this.attachMenuEl = document.getElementById('chat-attach-menu');
        this.uploadProgressEl = document.getElementById('chat-upload-progress');
        this.uploadProgressBarEl = document.getElementById('chat-upload-progress-bar');
        this.uploadProgressLabelEl = document.getElementById('chat-upload-progress-label');
        this.previewModalEl = document.getElementById('attachment-preview-modal');
        this.previewStageEl = document.getElementById('attachment-preview-stage');
        this.previewCaptionEl = document.getElementById('attachment-preview-caption');
        this.previewViewOnceEl = document.getElementById('attachment-view-once');
        this.lightboxEl = document.getElementById('chat-lightbox');
        this.lightboxImageEl = document.getElementById('lightbox-image');
        this.sidebarEl = document.getElementById('chat-sidebar');
        this.loadedMessages = new Map();
        this.lightboxImages = [];
        this.lightboxIndex = 0;
        this.lightboxRotation = 0;
        this.lightboxZoomed = false;
        this.sharedMediaType = '';
        this.activeCall = null;
        this.heldCall = null;
        // Signals that arrive addressed to a call_uuid we don't have a
        // callState object for *yet* — e.g. the accept flow redirects to a
        // fresh page load (`?call=...`), and the caller's offer/ICE can
        // easily arrive before that reload finishes and _joinAcceptedCall
        // even runs. Keyed by call_uuid; claimed the instant a callState
        // for that uuid exists.
        this.pendingCallSignals = new Map();

        this.callOverlayEl = document.getElementById('call-overlay');
        this.callRingingScreenEl = document.getElementById('call-ringing-screen');
        this.callInProgressScreenEl = document.getElementById('call-in-progress-screen');
        this.callStatusLabelEl = document.getElementById('call-status-label');
        this.callAvatarEl = document.getElementById('call-avatar');
        this.callPeerNameEl = document.getElementById('call-peer-name');
        this.callTimerEl = document.getElementById('call-timer');
        this.callInProgressTimerEl = document.getElementById('call-in-progress-timer');
        this.callNetworkQualityEl = document.getElementById('call-network-quality');
        this.callPeerHoldBadgeEl = document.getElementById('call-peer-hold-badge');
        this.callRemoteVideoEl = document.getElementById('call-remote-video');
        this.callLocalVideoEl = document.getElementById('call-local-video');
        this.callHeldBarEl = document.getElementById('call-held-bar');
        this.callHeldBarNameEl = document.getElementById('call-held-bar-name');
        this.callMinimizedBarEl = document.getElementById('call-minimized-bar');
        this.callMinimizedAvatarEl = document.getElementById('call-minimized-avatar');
        this.callMinimizedNameEl = document.getElementById('call-minimized-name');
        this.callMinimizedTimerEl = document.getElementById('call-minimized-timer');
        this.callMinimizedVideoEl = document.getElementById('call-minimized-video');
        this.isCallMinimized = false;
        this.pendingAttachment = null;
        this.previewRotation = 0;
        this.previewImage = null;
        this.previewDrawMode = false;
        this.previewCanvasEl = null;
        this.mediaRecorder = null;
        this.recordingStream = null;
        this.recordingChunks = [];
        this.recordingKind = null;
        this.liveLocationWatchers = new Map();
        this.liveLocationModal = null;
        this.liveLocationMap = null;
        this.liveLocationMarker = null;
        this.liveLocationSelfMarker = null;
        this.liveLocationLine = null;
        this.activeLiveLocationMessage = null;
        this.liveLocationShareModal = null;
        this.pendingLiveLocationDuration = { label: '1 hour', minutes: 60 };
        this.pendingLiveLocationConversationUuid = null;
        this.liveLocationPeers = new Map();
        this.liveLocationConversationUuid = null;
        this.liveLocationSelfWatchId = null;
        this.selfLiveStatus = null;
        this.batteryInfo = null;
        this.batteryMonitorBound = false;
        this.routeProposals = new Map();
        this.routePickMode = false;
        this.routeProposalMarker = null;
        this.routeProposalLine = null;

        this._renderConversationList();
        this._renderContactList();
        this._primePresenceState();
        this._bindEvents();
        this._subscribeAll();
        this._subscribeOwnChannel();
        window.setInterval(() => this._refreshPresenceLabels(), 30000);

        this._syncShellHeight();
        window.addEventListener('resize', () => this._syncShellHeight());

        // Closing/refreshing the tab mid-call otherwise leaves the session
        // stuck "ongoing" forever server-side, permanently marking both
        // participants as busy for every future call. A normal axios POST
        // can be aborted mid-flight when the page unloads — keepalive fetch
        // is specifically designed to survive that.
        window.addEventListener('pagehide', () => {
            if (this.activeCall) this._sendLeaveBeacon(this.activeCall.uuid);
            if (this.heldCall) this._sendLeaveBeacon(this.heldCall.uuid);
        });

        const requestedUuid = window.__trackerOpenConversationUuid;

        if (requestedUuid && this.conversations.has(requestedUuid)) {
            this._openConversation(requestedUuid).then(() => {
                const requestedCallUuid = window.__trackerOpenCallUuid;

                if (requestedCallUuid) {
                    this._joinAcceptedCall({ call_uuid: requestedCallUuid, conversation_uuid: requestedUuid, type: window.__trackerOpenCallType ?? 'voice' });
                }
            });
        }
    }

    _subscribeOwnChannel() {
        if (!this.currentUserId) return;

        window.Echo.private(`App.Models.User.${this.currentUserId}`)
            .listen('.conversation.started', (payload) => this._onConversationStarted(payload));
    }

    _onConversationStarted(payload) {
        if (this.conversations.has(payload.uuid)) return;

        this.conversations.set(payload.uuid, payload);
        this._subscribeConversation(payload);
        this._renderConversationList();
    }

    _bindEvents() {
        this.searchEl.addEventListener('input', () => this._renderConversationList());
        this.messageSearchEl?.addEventListener('input', () => {
            this.messageSearchTerm = this.messageSearchEl.value.trim();
            this._reloadCurrentConversation();
        });
        document.getElementById('chat-filter-pinned')?.addEventListener('click', () => {
            this.showPinnedOnly = !this.showPinnedOnly;
            this.showStarredOnly = false;
            document.getElementById('chat-filter-pinned')?.classList.toggle('active', this.showPinnedOnly);
            document.getElementById('chat-filter-starred')?.classList.remove('active');
            this._reloadCurrentConversation();
        });
        document.getElementById('chat-filter-starred')?.addEventListener('click', () => {
            this.showStarredOnly = !this.showStarredOnly;
            this.showPinnedOnly = false;
            document.getElementById('chat-filter-starred')?.classList.toggle('active', this.showStarredOnly);
            document.getElementById('chat-filter-pinned')?.classList.remove('active');
            this._reloadCurrentConversation();
        });
        document.getElementById('chat-mobile-back')?.addEventListener('click', () => this._setMobileConversationOpen(false));
        document.addEventListener('click', (event) => {
            if (!event.target.closest('.tracker-chat-bubble')) {
                this._closeAllBubbleActions();
            }
        });

        document.getElementById('chat-reply-cancel').addEventListener('click', () => this._clearReply());
        document.getElementById('chat-edit-cancel').addEventListener('click', () => this._clearEdit());

        this.composerEl.addEventListener('submit', (event) => {
            event.preventDefault();
            this._submitComposer();
        });

        this.composerInputEl.addEventListener('input', () => this._handleTyping());

        document.getElementById('new-group-btn').addEventListener('click', () => this._openNewGroupModal());
        document.getElementById('new-group-submit').addEventListener('click', () => this._submitNewGroupModal());

        this.groupInfoBtnEl?.addEventListener('click', () => this._openGroupInfo());
        this.profileInfoBtnEl?.addEventListener('click', () => this._openProfileInfo());
        document.getElementById('group-info-add-member-btn').addEventListener('click', () => {
            this._openNewGroupModal({ addToUuid: this.currentUuid });
        });
        document.getElementById('group-info-rename-btn').addEventListener('click', () => this._renameGroupPrompt());
        document.getElementById('group-info-leave-btn').addEventListener('click', () => this._leaveGroup());
        document.getElementById('group-info-delete-btn').addEventListener('click', () => this._deleteGroup());

        document.getElementById('chat-attach-btn').addEventListener('click', (event) => {
            event.stopPropagation();
            if (!this._currentConversationCanCommunicate()) return;
            this.attachMenuEl?.classList.toggle('d-none');
        });
        document.addEventListener('click', (event) => {
            if (!event.target.closest('.tracker-chat-attach-wrap')) {
                this.attachMenuEl?.classList.add('d-none');
            }
        });
        this.attachMenuEl?.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            const button = event.target.closest('[data-share-kind]');
            if (!button) return;

            this.attachMenuEl.classList.add('d-none');
            this._handleShareOption(button.dataset.shareKind);
        });
        this.attachmentInputEl.addEventListener('change', () => this._previewSelectedFile(this.attachmentInputEl));
        this.photoInputEl?.addEventListener('change', () => this._previewSelectedFile(this.photoInputEl));
        this.documentInputEl?.addEventListener('change', () => this._previewSelectedFile(this.documentInputEl));
        document.getElementById('chat-audio-record-btn')?.addEventListener('click', () => this._toggleRecording('audio'));
        document.getElementById('chat-video-record-btn')?.addEventListener('click', () => this._toggleRecording('video'));
        document.getElementById('attachment-preview-rotate-left')?.addEventListener('click', () => this._rotatePreview(-90));
        document.getElementById('attachment-preview-rotate-right')?.addEventListener('click', () => this._rotatePreview(90));
        document.getElementById('attachment-preview-draw')?.addEventListener('click', () => this._togglePreviewDraw());
        document.getElementById('attachment-preview-emoji')?.addEventListener('click', () => this._stampPreviewEmoji());
        document.getElementById('attachment-preview-crop')?.addEventListener('click', () => this._cropPreviewSquare());
        document.getElementById('attachment-preview-reset')?.addEventListener('click', () => this._resetPreviewEdits());
        document.getElementById('attachment-preview-clear')?.addEventListener('click', () => this._clearAttachmentPreview());
        document.getElementById('attachment-preview-send')?.addEventListener('click', () => this._sendPendingAttachment());
        this._bindLiveLocationShareModal();

        document.getElementById('chat-files-btn').addEventListener('click', () => this._openSharedMedia());
        document.getElementById('shared-media-tabs').addEventListener('click', (event) => {
            const button = event.target.closest('[data-media-type]');
            if (!button) return;

            document.querySelectorAll('#shared-media-tabs .nav-link').forEach((el) => el.classList.remove('active'));
            button.classList.add('active');
            this.sharedMediaType = button.dataset.mediaType;
            this._loadSharedMedia();
        });

        document.getElementById('lightbox-close').addEventListener('click', () => this._closeLightbox());
        document.getElementById('lightbox-prev').addEventListener('click', () => this._navigateLightbox(-1));
        document.getElementById('lightbox-next').addEventListener('click', () => this._navigateLightbox(1));
        document.getElementById('lightbox-rotate').addEventListener('click', () => this._rotateLightbox());
        document.getElementById('lightbox-zoom').addEventListener('click', () => this._toggleLightboxZoom());
        document.getElementById('lightbox-fullscreen').addEventListener('click', () => this._fullscreenLightbox());
        document.getElementById('lightbox-delete').addEventListener('click', () => this._deleteLightboxImage());

        document.getElementById('chat-voice-call-btn')?.addEventListener('click', () => this._startCall('voice'));
        document.getElementById('chat-video-call-btn')?.addEventListener('click', () => this._startCall('video'));
        document.getElementById('call-cancel-btn')?.addEventListener('click', () => this._cancelOutgoingCall());
        document.getElementById('call-hangup-btn')?.addEventListener('click', () => this._hangupCall());
        document.getElementById('call-mute-btn')?.addEventListener('click', () => this._toggleMute());
        document.getElementById('call-video-toggle-btn')?.addEventListener('click', () => this._toggleVideo());
        document.getElementById('call-screen-share-btn')?.addEventListener('click', () => this._toggleScreenShare());
        document.getElementById('call-low-bandwidth-btn')?.addEventListener('click', () => this._toggleLowBandwidth());
        document.addEventListener('tracker:call-accepted', (event) => this._joinAcceptedCall(event.detail));
        document.addEventListener('tracker:call-waiting-accept', (event) => this._acceptWaitingCall(event.detail));

        document.getElementById('call-held-bar')?.addEventListener('click', (event) => {
            if (event.target.closest('#call-held-bar-end')) return;
            this._swapCalls();
        });
        document.getElementById('call-held-bar-end')?.addEventListener('click', async (event) => {
            event.stopPropagation();
            if (!this.heldCall) return;

            await window.axios.post(`/api/v1/calls/${this.heldCall.uuid}/leave`).catch(() => {});
            this._teardownCallState(this.heldCall);
            this.heldCall = null;
            this._renderHeldBar();
        });

        document.getElementById('chat-call-history-btn')?.addEventListener('click', () => this._openCallHistory());

        document.getElementById('call-minimize-btn')?.addEventListener('click', () => this._minimizeCall());
        document.getElementById('call-minimized-bar')?.addEventListener('click', (event) => {
            if (event.target.closest('#call-minimized-hangup, #call-minimized-mute')) return;
            this._restoreCall();
        });
        document.getElementById('call-minimized-mute')?.addEventListener('click', (event) => {
            event.stopPropagation();
            this._toggleMute();
            const track = this.activeCall?.localStream?.getAudioTracks()[0];
            event.currentTarget.innerHTML = `<i class="fa-solid ${track?.enabled ? 'fa-microphone' : 'fa-microphone-slash'}"></i>`;
        });
        document.getElementById('call-minimized-hangup')?.addEventListener('click', async (event) => {
            event.stopPropagation();
            await this._hangupCall();
        });
        this._bindCallMinimizedDrag();
    }

    _bindLiveLocationShareModal() {
        const modalEl = document.getElementById('live-location-share-modal');
        if (!modalEl) return;

        this.liveLocationShareModal = new Modal(modalEl);

        const optionsEl = document.getElementById('live-location-duration-options');
        const statusEl = document.getElementById('live-location-share-status');
        const confirmBtn = document.getElementById('live-location-share-confirm');

        optionsEl?.addEventListener('click', (event) => {
            const button = event.target.closest('button[data-minutes]');
            if (!button) return;

            optionsEl.querySelectorAll('button').forEach((el) => el.classList.remove('active'));
            button.classList.add('active');

            const minutes = button.dataset.minutes === '' ? null : Number(button.dataset.minutes);
            this.pendingLiveLocationDuration = { label: button.textContent.trim(), minutes };
        });

        confirmBtn?.addEventListener('click', () => this._confirmLiveLocationShare());

        modalEl.addEventListener('hidden.bs.modal', () => {
            if (statusEl) {
                statusEl.textContent = '';
                statusEl.classList.add('d-none');
            }
            confirmBtn?.removeAttribute('disabled');
        });
    }

    _subscribeAll() {
        this.conversations.forEach((conversation) => this._subscribeConversation(conversation));
    }

    _subscribeConversation(conversation) {
        if (this.channels.has(conversation.uuid)) return;

        const channel = window.Echo.private(`conversation.${conversation.uuid}`);

        channel.listen('.message.sent', (payload) => this._onMessageSent(conversation.uuid, payload));
        channel.listen('.message.updated', (payload) => this._onMessageUpdated(conversation.uuid, payload));
        channel.listen('.message.deleted', (payload) => this._onMessageDeleted(conversation.uuid, payload));
        channel.listen('.message.delivered', (payload) => this._onMessageDelivered(conversation.uuid, payload));
        channel.listen('.messages.read', (payload) => this._onMessagesRead(conversation.uuid, payload));
        channel.listen('.message.reaction.updated', (payload) => this._onReactionUpdated(conversation.uuid, payload));
        channel.listen('.conversation.updated', (payload) => this._onConversationUpdated(conversation.uuid, payload));
        channel.listen('.conversation.deleted', (payload) => this._onConversationDeleted(conversation.uuid, payload));
        channel.listen('.call.accepted', (payload) => this._onCallAccepted(payload));
        channel.listen('.call.rejected', (payload) => this._onCallRejected(payload));
        channel.listen('.call.ended', (payload) => this._onCallEnded(payload));
        channel.listen('.call.participant.left', (payload) => this._onCallParticipantLeft(payload));
        channel.listen('.call.participant.updated', (payload) => this._onCallParticipantUpdated(payload));
        channel.listen('.route-proposal.updated', (payload) => this._onRouteProposalUpdated(conversation.uuid, payload));
        channel.listenForWhisper('typing', (payload) => this._onTypingWhisper(conversation.uuid, payload));
        channel.listenForWhisper('webrtc-offer', (payload) => this._onWebrtcOffer(payload));
        channel.listenForWhisper('webrtc-answer', (payload) => this._onWebrtcAnswer(payload));
        channel.listenForWhisper('webrtc-ice-candidate', (payload) => this._onWebrtcIceCandidate(payload));
        channel.listenForWhisper('live-location-update', (payload) => this._onLiveLocationUpdate(conversation.uuid, payload));

        this.channels.set(conversation.uuid, channel);

        if (conversation.other_user) {
            this._subscribePresence(conversation.other_user.id);
        }
    }

    _subscribePresence(userId) {
        if (this.presenceChannels.has(userId)) return;

        const channel = window.Echo.private(`App.Models.User.${userId}`);
        channel.listen('.presence.changed', (payload) => this._onPresenceChange(userId, payload));
        this.presenceChannels.set(userId, channel);
    }

    _primePresenceState() {
        this.conversations.forEach((conversation) => {
            const otherUser = conversation.other_user;

            if (!otherUser?.id) return;

            this.presence.set(otherUser.id, {
                isOnline: Boolean(otherUser.is_online),
                lastActivity: otherUser.last_activity_at,
            });
        });
    }

    _refreshPresenceLabels() {
        if (this.currentUuid) {
            this._renderStatusForConversation(this.conversations.get(this.currentUuid));
        }

        this._renderConversationList();
    }

    // ---------- Conversation list ----------

    _renderConversationList() {
        const term = (this.searchEl.value || '').toLowerCase();
        const rows = Array.from(this.conversations.values())
            .filter((c) => !term || (c.name || '').toLowerCase().includes(term))
            .sort((a, b) => new Date(b.last_message?.created_at ?? b.updated_at) - new Date(a.last_message?.created_at ?? a.updated_at));

        this.listEmptyEl.classList.toggle('d-none', rows.length > 0);
        this.listEl.replaceChildren();

        rows.forEach((conversation) => {
            const isGroup = conversation.type === 'group';
            const row = document.createElement('div');
            row.className = 'tracker-chat-list-item';
            row.dataset.conversationUuid = conversation.uuid;
            row.classList.toggle('active', conversation.uuid === this.currentUuid);

            const presenceState = this.presence.get(conversation.other_user?.id);
            const isOnline = isPresenceOnline(presenceState);
            const lastMessage = conversation.last_message;
            const preview = lastMessage?.body ?? (isGroup ? 'Group ready for updates' : (isOnline ? 'Online now' : 'Tap to open chat'));
            const statusLabel = isGroup
                ? `${conversation.member_count ?? 0} members`
                : (isOnline ? 'Online' : formatLastSeen(conversation.other_user?.last_activity_at ?? presenceState?.lastActivity));

            row.innerHTML = `
                <div class="tracker-chat-list-avatar">
                    <div class="tracker-avatar-sm">${avatarHtml({
                        name: conversation.name,
                        avatar_url: conversation.avatar_url ?? conversation.other_user?.avatar_url,
                        icon: isGroup ? 'fa-solid fa-user-group' : null,
                    })}</div>
                    ${!isGroup ? `<span class="tracker-chat-presence-dot ${isOnline ? 'online' : ''}"></span>` : ''}
                </div>
                <div class="tracker-chat-list-copy">
                    <div class="tracker-chat-list-top">
                        <strong>${escapeHtml(conversation.name ?? 'Unknown')}</strong>
                        <span class="text-muted small">${lastMessage ? formatTime(lastMessage.created_at) : ''}</span>
                    </div>
                    <div class="tracker-chat-list-bottom">
                        <span class="text-muted small text-truncate">${escapeHtml(preview)}</span>
                        ${conversation.unread_count > 0 ? `<span class="tracker-chat-unread-badge">${conversation.unread_count}</span>` : ''}
                    </div>
                    <div class="tracker-chat-list-meta">${escapeHtml(statusLabel)}</div>
                </div>
            `;

            row.addEventListener('click', () => this._openConversation(conversation.uuid));

            this.listEl.appendChild(row);
        });
    }

    _updateConversationPreview(uuid, message) {
        const conversation = this.conversations.get(uuid);

        if (!conversation) return;

        conversation.last_message = {
            body: message.is_deleted ? 'This message was deleted' : message.body,
            sender_id: message.sender.id,
            created_at: message.created_at,
        };

        if (uuid !== this.currentUuid && message.sender.id !== this.currentUserId) {
            conversation.unread_count = (conversation.unread_count || 0) + 1;
        }

        this._renderConversationList();
    }

    // ---------- Contacts / new chat ----------

    _renderContactList() {
        this.contactEmptyEl.classList.toggle('d-none', this.contacts.length > 0);
        this.contactListEl.replaceChildren();

        this.contacts.forEach((contact) => {
            const row = document.createElement('div');
            row.className = 'tracker-chat-list-item';
            row.innerHTML = `
                <div class="tracker-avatar-sm">${avatarHtml(contact)}</div>
                <div class="tracker-chat-list-copy">
                    <strong>${escapeHtml(contact.name)}</strong>
                </div>
            `;
            row.addEventListener('click', () => this._startConversation(contact.id));

            this.contactListEl.appendChild(row);
        });
    }

    async _startConversation(userId) {
        const { data } = await window.axios.post('/api/v1/conversations', { user_id: userId });
        const conversation = data.data;

        this.conversations.set(conversation.uuid, conversation);
        this._subscribeConversation(conversation);
        this._renderConversationList();

        Modal.getInstance(document.getElementById('new-chat-modal'))?.hide();

        if (this.forwardingMessage) {
            const { uuid, body } = this.forwardingMessage;
            this.forwardingMessage = null;

            await window.axios.post(`/api/v1/conversations/${conversation.uuid}/messages`, {
                body,
                forwarded_from_message_id: uuid,
            });
        }

        this._openConversation(conversation.uuid);
    }

    // ---------- Groups ----------

    _openNewGroupModal({ addToUuid = null } = {}) {
        this.newGroupAddToUuid = addToUuid;

        document.getElementById('new-group-name-field').classList.toggle('d-none', !!addToUuid);
        document.getElementById('new-group-modal-title').textContent = addToUuid ? 'Add Members' : 'New Group';

        const existingIds = new Set(addToUuid ? (this.currentGroupDetail?.members ?? []).map((m) => m.id) : []);
        this._renderGroupContactCheckboxList(existingIds);

        new Modal(document.getElementById('new-group-modal')).show();
    }

    _renderGroupContactCheckboxList(excludeIds) {
        const listEl = document.getElementById('new-group-contact-list');
        const eligible = this.contacts.filter((contact) => !excludeIds.has(contact.id));

        document.getElementById('new-group-contact-empty').classList.toggle('d-none', eligible.length > 0);
        listEl.replaceChildren();

        eligible.forEach((contact) => {
            const row = document.createElement('label');
            row.className = 'tracker-chat-list-item';
            row.innerHTML = `
                <input type="checkbox" class="form-check-input me-2" value="${contact.id}">
                <div class="tracker-avatar-sm">${avatarHtml(contact)}</div>
                <div class="tracker-chat-list-copy"><strong>${escapeHtml(contact.name)}</strong></div>
            `;
            listEl.appendChild(row);
        });
    }

    async _submitNewGroupModal() {
        const ids = Array.from(document.getElementById('new-group-contact-list').querySelectorAll('input:checked'))
            .map((input) => Number(input.value));

        if (ids.length === 0) return;

        if (this.newGroupAddToUuid) {
            for (const id of ids) {
                await window.axios.post(`/api/v1/groups/${this.newGroupAddToUuid}/members`, { user_id: id });
            }

            Modal.getInstance(document.getElementById('new-group-modal'))?.hide();
            await this._refreshGroupInfo();

            return;
        }

        const name = document.getElementById('new-group-name-input').value.trim();

        if (!name) return;

        const { data } = await window.axios.post('/api/v1/groups', { name, member_ids: ids });
        const conversation = data.data;

        this.conversations.set(conversation.uuid, conversation);
        this._subscribeConversation(conversation);
        this._renderConversationList();

        Modal.getInstance(document.getElementById('new-group-modal'))?.hide();
        document.getElementById('new-group-name-input').value = '';

        this._openConversation(conversation.uuid);
    }

    async _openGroupInfo() {
        if (!this.currentUuid) return;

        await this._refreshGroupInfo();
        new Modal(document.getElementById('group-info-modal')).show();
    }

    async _refreshGroupInfo() {
        const { data } = await window.axios.get(`/api/v1/groups/${this.currentUuid}`);
        this.currentGroupDetail = data.data;
        this._renderGroupInfo();
    }

    _renderGroupInfo() {
        const detail = this.currentGroupDetail;

        if (!detail) return;

        const me = detail.members.find((member) => member.id === this.currentUserId);
        const myRole = me?.role ?? 'member';
        const canManage = myRole === 'owner' || myRole === 'admin';
        const isOwner = myRole === 'owner';

        document.getElementById('group-info-name').textContent = detail.name;
        document.getElementById('group-info-description').textContent = detail.description || 'No description';

        const membersEl = document.getElementById('group-info-members');
        membersEl.replaceChildren();

        detail.members.forEach((member) => {
            const canModerateThisMember = canManage && member.role !== 'owner' && member.id !== this.currentUserId;

            const row = document.createElement('div');
            row.className = 'tracker-chat-member-row';
            row.innerHTML = `
                <div class="tracker-avatar-sm">${avatarHtml(member)}</div>
                <div class="tracker-chat-list-copy"><strong>${escapeHtml(member.name)}</strong></div>
                <span class="tracker-chat-role-badge tracker-chat-role-${member.role}">${member.role}</span>
                ${canModerateThisMember ? `
                    <div class="tracker-chat-member-actions">
                        ${isOwner ? (member.role === 'admin'
        ? `<button type="button" class="tracker-chat-action-btn" data-member-action="demote" data-user-id="${member.id}" title="Demote"><i class="fa-solid fa-arrow-down"></i></button>`
        : `<button type="button" class="tracker-chat-action-btn" data-member-action="promote" data-user-id="${member.id}" title="Promote"><i class="fa-solid fa-arrow-up"></i></button>`) : ''}
                        <button type="button" class="tracker-chat-action-btn" data-member-action="remove" data-user-id="${member.id}" title="Remove"><i class="fa-solid fa-user-minus"></i></button>
                    </div>
                ` : ''}
            `;
            membersEl.appendChild(row);
        });

        membersEl.querySelectorAll('[data-member-action]').forEach((button) => {
            button.addEventListener('click', () => this._handleMemberAction(button.dataset.memberAction, Number(button.dataset.userId)));
        });

        document.getElementById('group-info-add-member-btn').classList.toggle('d-none', !canManage);
        document.getElementById('group-info-rename-btn').classList.toggle('d-none', !canManage);
        document.getElementById('group-info-delete-btn').classList.toggle('d-none', !isOwner);
        document.getElementById('group-info-leave-btn').classList.toggle('d-none', isOwner);
    }

    async _handleMemberAction(action, userId) {
        const uuid = this.currentUuid;

        if (action === 'promote') await window.axios.post(`/api/v1/groups/${uuid}/members/${userId}/promote`);
        if (action === 'demote') await window.axios.post(`/api/v1/groups/${uuid}/members/${userId}/demote`);
        if (action === 'remove') await window.axios.delete(`/api/v1/groups/${uuid}/members/${userId}`);

        await this._refreshGroupInfo();
    }

    async _renameGroupPrompt() {
        const name = prompt('New group name', this.currentGroupDetail?.name ?? '');

        if (!name) return;

        await window.axios.patch(`/api/v1/groups/${this.currentUuid}`, { name });
        await this._refreshGroupInfo();
    }

    async _leaveGroup() {
        if (!confirm('Leave this group?')) return;

        const uuid = this.currentUuid;

        await window.axios.post(`/api/v1/groups/${uuid}/leave`);

        Modal.getInstance(document.getElementById('group-info-modal'))?.hide();
        this.conversations.delete(uuid);
        this._renderConversationList();
        this._closeWindow();
    }

    async _deleteGroup() {
        if (!confirm('Delete this group for everyone? This cannot be undone.')) return;

        const uuid = this.currentUuid;

        await window.axios.delete(`/api/v1/groups/${uuid}`);

        Modal.getInstance(document.getElementById('group-info-modal'))?.hide();
        this.conversations.delete(uuid);
        this._renderConversationList();
        this._closeWindow();
    }

    _closeWindow() {
        this.currentUuid = null;
        this.isMobileConversationOpen = false;
        this.windowEl.classList.add('d-none');
        this.windowEl.classList.remove('d-flex');
        this.windowEmptyEl.classList.remove('d-none');
        this.sidebarEl?.classList.remove('chat-mobile-hidden');
    }

    // ---------- Opening a conversation / message history ----------

    async _openConversation(uuid) {
        this.currentUuid = uuid;
        window.__trackerActiveConversationUuid = uuid;
        this._clearReply();
        this._clearEdit();

        const conversation = this.conversations.get(uuid);

        this.windowEmptyEl.classList.add('d-none');
        this.windowEl.classList.remove('d-none');
        this.windowEl.classList.add('d-flex');
        this._setMobileConversationOpen(true);

        this._renderHeader(conversation);
        this._applyConversationActionState(conversation);
        this._renderConversationList();
        await this._loadConversationMessages(uuid);

        await window.axios.post(`/api/v1/conversations/${uuid}/read`).catch(() => {});

        if (conversation) {
            conversation.unread_count = 0;
            this._renderConversationList();
        }
    }

    async _reloadCurrentConversation() {
        if (!this.currentUuid) return;

        await this._loadConversationMessages(this.currentUuid, false);
    }

    async _loadConversationMessages(uuid, shouldScroll = true) {
        this.messagesEl.innerHTML = '<div class="tracker-chat-loading"><span></span><span></span><span></span></div>';

        const params = new URLSearchParams();
        if (this.messageSearchTerm) params.set('q', this.messageSearchTerm);
        if (this.showPinnedOnly) params.set('pinned_only', '1');
        if (this.showStarredOnly) params.set('starred_only', '1');

        const query = params.toString() ? `?${params.toString()}` : '';

        let data;

        try {
            ({ data } = await window.axios.get(`/api/v1/conversations/${uuid}/messages${query}`));
        } catch (error) {
            this.messagesEl.innerHTML = `
                <div class="tracker-empty-state">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <p class="mb-2">Couldn't load this conversation.</p>
                    <button type="button" class="btn tracker-outline-btn btn-sm" id="chat-messages-retry">Retry</button>
                </div>
            `;
            document.getElementById('chat-messages-retry')?.addEventListener('click', () => this._loadConversationMessages(uuid, shouldScroll));

            return;
        }

        this.messagesEl.replaceChildren();
        const conversation = this.conversations.get(uuid);

        if (conversation?.can_communicate === false) {
            const notice = document.createElement('div');
            notice.className = 'tracker-empty-state mb-3';
            notice.innerHTML = `
                <i class="fa-solid fa-lock"></i>
                <p class="mb-2">${escapeHtml(conversation.blocked_reason ?? 'This private chat is paused because the tracking license expired.')}</p>
                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    <a href="${conversation.license_actions?.renew_url ?? '/my-licenses'}" class="btn tracker-outline-btn btn-sm" title="Renew license"><i class="fa-solid fa-rotate me-1"></i> Renew</a>
                    <a href="${conversation.license_actions?.upgrade_url ?? '/my-licenses/plans'}" class="btn tracker-primary-btn btn-sm" title="Upgrade license"><i class="fa-solid fa-arrow-up-right-dots me-1"></i> Upgrade</a>
                </div>
            `;
            this.messagesEl.appendChild(notice);
        }

        data.data.forEach((message) => this._appendMessage(message));

        if (shouldScroll) {
            this._scrollToBottom();
        }
    }

    _setMobileConversationOpen(open) {
        this.isMobileConversationOpen = open;
        this.sidebarEl?.classList.toggle('chat-mobile-hidden', open);
        this.windowEl?.classList.toggle('chat-mobile-open', open);
    }

    _renderHeader(conversation) {
        this.typingUsers.clear();
        Object.values(this.typingTimers).forEach((timer) => clearTimeout(timer));
        this.typingTimers = {};

        if (!conversation) return;

        const isGroup = conversation.type === 'group';

        this.headerAvatarEl.innerHTML = avatarHtml({
            name: conversation.name,
            avatar_url: conversation.avatar_url ?? conversation.other_user?.avatar_url,
            icon: isGroup ? 'fa-solid fa-user-group' : null,
        });
        this.headerNameEl.textContent = conversation.name ?? 'Unknown';
        this.groupInfoBtnEl?.classList.toggle('d-none', !isGroup);
        this.profileInfoBtnEl?.classList.toggle('d-none', isGroup);
        document.getElementById('chat-voice-call-btn')?.classList.remove('d-none');
        document.getElementById('chat-video-call-btn')?.classList.remove('d-none');
        document.getElementById('chat-call-history-btn')?.classList.remove('d-none');
        this._renderStatusForConversation(conversation);
    }

    _openProfileInfo() {
        const conversation = this.conversations.get(this.currentUuid);

        if (!conversation || conversation.type === 'group') return;

        const user = conversation.other_user ?? {};
        const details = [
            user.designation,
            user.department,
            user.company,
        ].filter(Boolean).join(' · ');

        const profileUrl = '/profile';
        const settingsUrl = '/user-settings';

        window.Swal?.fire({
            title: escapeHtml(conversation.name ?? 'Contact'),
            html: `
                <div class="tracker-chat-profile-sheet">
                    <div class="tracker-profile-avatar-lg mx-auto mb-3">${avatarHtml({
                        name: conversation.name,
                        avatar_url: conversation.avatar_url ?? user.avatar_url,
                    })}</div>
                    <p class="mb-2 text-muted">${escapeHtml(details || 'Connected through tracking relationship')}</p>
                    <p class="mb-0 small text-muted">${escapeHtml(this.headerStatusEl.textContent || '')}</p>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Profile Settings',
            cancelButtonText: 'Close',
            showDenyButton: true,
            denyButtonText: 'My Settings',
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = profileUrl;
            }

            if (result.isDenied) {
                window.location.href = settingsUrl;
            }
        });
    }

    _renderStatusForConversation(conversation) {
        if (!conversation) return;

        if (this.activeCall && this.activeCall.conversationUuid === conversation.uuid) {
            const label = this.activeCall.startedAt ? this._formatDuration(Date.now() - this.activeCall.startedAt) : 'Connecting…';
            this.headerStatusEl.textContent = `On a call · ${label}`;

            return;
        }

        if (conversation.type === 'group') {
            this.headerStatusEl.textContent = `${conversation.member_count ?? 0} members`;
        } else if (conversation.can_communicate === false) {
            this.headerStatusEl.textContent = 'License expired';
        } else {
            this._renderPresenceStatus(conversation.other_user?.id);
        }
    }

    _currentConversationCanCommunicate() {
        const conversation = this.currentUuid ? this.conversations.get(this.currentUuid) : null;

        return !conversation || conversation.can_communicate !== false;
    }

    _applyConversationActionState(conversation) {
        const blocked = conversation?.can_communicate === false;
        const controls = [
            this.composerInputEl,
            document.getElementById('chat-attach-btn'),
            document.getElementById('chat-audio-record-btn'),
            document.getElementById('chat-video-record-btn'),
            document.getElementById('chat-voice-call-btn'),
            document.getElementById('chat-video-call-btn'),
            this.composerEl?.querySelector('button[type="submit"]'),
        ].filter(Boolean);

        controls.forEach((control) => {
            control.disabled = blocked;
            control.classList.toggle('disabled', blocked);
        });

        this.attachMenuEl?.classList.add('d-none');
        this.composerInputEl.placeholder = blocked
            ? 'License expired. Renew or upgrade to continue.'
            : 'Type a message...';
    }

    _renderPresenceStatus(userId) {
        if (!userId) {
            this.headerStatusEl.textContent = '';
            return;
        }

        const state = this.presence.get(userId);

        this.headerStatusEl.textContent = isPresenceOnline(state) ? 'Online' : formatLastSeen(state?.lastActivity);
    }

    // ---------- Rendering messages ----------

    _appendMessage(message) {
        if (this.messagesEl.querySelector(`[data-message-uuid="${message.uuid}"]`)) {
            return;
        }

        if (message.type === 'system') {
            const notice = document.createElement('div');
            notice.className = 'tracker-chat-system-message';
            notice.dataset.messageUuid = message.uuid;
            notice.textContent = message.body ?? '';
            this.messagesEl.appendChild(notice);

            return;
        }

        this.loadedMessages.set(message.uuid, message);

        const mine = message.sender.id === this.currentUserId;
        const bubble = document.createElement('div');
        bubble.className = `tracker-chat-bubble-row ${mine ? 'mine' : 'theirs'}`;
        bubble.dataset.messageUuid = message.uuid;

        bubble.innerHTML = this._bubbleHtml(message, mine);
        this.messagesEl.appendChild(bubble);
        this._bindBubbleActions(bubble, message, mine);
    }

    _bubbleHtml(message, mine) {
        const replyHtml = message.reply_to ? `
            <div class="tracker-chat-quote">
                <strong>${escapeHtml(message.reply_to.sender_name)}</strong>
                <span>${escapeHtml(message.reply_to.body)}</span>
            </div>` : '';

        const forwardHtml = message.forwarded_from ? `<div class="tracker-chat-forwarded"><i class="fa-solid fa-share"></i> Forwarded</div>` : '';

        const attachmentHtml = message.is_deleted ? '' : this._attachmentHtml(message);
        const locationHtml = message.is_deleted ? '' : this._locationHtml(message, mine);
        const visibleBody = message.metadata?.kind ? '' : message.body;

        const bodyHtml = message.is_deleted
            ? '<div class="tracker-chat-bubble-body"><em>This message was deleted</em></div>'
            : `${visibleBody ? `<div class="tracker-chat-bubble-body">${escapeHtml(visibleBody)}</div>` : ''}`;

        const reactionsHtml = (message.reactions || []).length
            ? `<div class="tracker-chat-reactions">${this._groupReactions(message.reactions)}</div>`
            : '';

        const ticksHtml = mine ? this._ticksHtml(message) : '';

        return `
            <div class="tracker-chat-bubble">
                ${forwardHtml}
                ${replyHtml}
                ${attachmentHtml}
                ${locationHtml}
                ${bodyHtml}
                ${reactionsHtml}
                <div class="tracker-chat-bubble-meta">
                    ${message.is_edited ? '<span class="me-1">edited</span>' : ''}
                    ${message.is_pinned ? '<i class="fa-solid fa-thumbtack me-1"></i>' : ''}
                    <span>${formatTime(message.created_at)}</span>
                    ${ticksHtml}
                </div>
                ${message.is_deleted ? '' : this._actionsHtml(message, mine)}
            </div>
        `;
    }

    _locationHtml(message, mine) {
        const metadata = message.metadata;

        if (!metadata || !['live_location', 'current_location'].includes(metadata.kind)) return '';

        const isLive = metadata.kind === 'live_location';
        const cancelled = Boolean(metadata.cancelled_at);
        const expired = metadata.expires_at && Date.now() > new Date(metadata.expires_at).getTime();
        const status = cancelled ? 'Cancelled' : (expired ? 'Expired' : (isLive ? 'Live now' : 'Shared location'));

        return `
            <div class="tracker-chat-location-card" data-location-message="${message.uuid}">
                <div>
                    <strong><i class="fa-solid ${isLive ? 'fa-route' : 'fa-location-dot'} me-1"></i>${isLive ? 'Live location' : 'Current location'}</strong>
                    <span>${escapeHtml(status)}</span>
                </div>
                <button type="button" class="tracker-chat-location-open" data-action="open-location">${isLive ? 'Open live map' : 'Open map'}</button>
                ${mine && isLive && !cancelled && !expired ? `<button type="button" class="tracker-chat-location-cancel" data-action="cancel-live-location">Cancel</button>` : ''}
            </div>
        `;
    }

    _attachmentHtml(message) {
        const attachment = (message.attachments || [])[0];

        if (!attachment) return '';

        if (attachment.type === 'image') {
            if (attachment.view_once) {
                const label = attachment.opened ? 'Opened' : 'Open';
                const icon = attachment.opened ? 'fa-eye-slash' : 'fa-eye';

                return `
                    <button type="button" class="tracker-chat-view-once ${attachment.opened ? 'opened' : ''}" data-lightbox-uuid="${attachment.opened ? '' : message.uuid}" ${attachment.opened ? 'disabled' : ''}>
                        <i class="fa-solid ${icon}"></i>
                        <span>${label}</span>
                    </button>
                `;
            }

            return `
                <div class="tracker-chat-attachment-image" data-lightbox-uuid="${message.uuid}">
                    <img src="${attachment.thumbnail_url || attachment.url}" alt="${escapeHtml(attachment.original_name)}" loading="lazy">
                </div>
            `;
        }

        if (attachment.type === 'video') {
            return `
                <div class="tracker-chat-attachment-video">
                    <video controls playsinline><source src="${attachment.url}" type="${attachment.mime_type}"></video>
                    <div class="tracker-chat-video-controls">
                        <select class="form-select form-select-sm tracker-chat-speed-select" data-action="video-speed">
                            <option value="0.5">0.5x</option>
                            <option value="1" selected>1x</option>
                            <option value="1.5">1.5x</option>
                            <option value="2">2x</option>
                        </select>
                        <button type="button" class="tracker-chat-action-btn" data-action="video-pip" title="Picture-in-picture"><i class="fa-solid fa-clone"></i></button>
                    </div>
                </div>
            `;
        }

        if (attachment.type === 'audio') {
            return `
                <div class="tracker-chat-attachment-audio">
                    <audio controls><source src="${attachment.url}" type="${attachment.mime_type}"></audio>
                </div>
            `;
        }

        return `
            <a class="tracker-chat-attachment-document" href="${attachment.url}" target="_blank" rel="noopener">
                <i class="fa-solid fa-file-lines"></i>
                <div>
                    <strong>${escapeHtml(attachment.original_name)}</strong>
                    <span class="text-muted small d-block">${formatFileSize(attachment.size)}</span>
                </div>
                <i class="fa-solid fa-download ms-auto"></i>
            </a>
        `;
    }

    _closeAllBubbleActions() {
        this.messagesEl.querySelectorAll('.tracker-chat-bubble-actions.show').forEach((el) => el.classList.remove('show'));
    }

    _groupReactions(reactions) {
        const counts = {};
        reactions.forEach((r) => { counts[r.emoji] = (counts[r.emoji] || 0) + 1; });

        return Object.entries(counts).map(([emoji, count]) => `<span class="tracker-chat-reaction-chip">${emoji} ${count}</span>`).join('');
    }

    _ticksHtml(message) {
        const reads = (message.reads || []).filter((r) => r.user_id !== this.currentUserId);

        if (reads.length === 0) {
            return '<i class="fa-solid fa-check tracker-chat-tick"></i>';
        }

        const allRead = reads.every((r) => r.read_at);
        const allDelivered = reads.every((r) => r.delivered_at);

        if (allRead) {
            return '<i class="fa-solid fa-check-double tracker-chat-tick tracker-chat-tick-read"></i>';
        }

        if (allDelivered) {
            return '<i class="fa-solid fa-check-double tracker-chat-tick"></i>';
        }

        return '<i class="fa-solid fa-check tracker-chat-tick"></i>';
    }

    _actionsHtml(message, mine) {
        const reactionButtons = REACTION_EMOJIS.map((emoji) => `<button type="button" class="tracker-chat-action-btn" data-action="react" data-emoji="${emoji}">${emoji}</button>`).join('');

        return `
            <div class="tracker-chat-bubble-actions">
                ${reactionButtons}
                <button type="button" class="tracker-chat-action-btn" data-action="reply" title="Reply"><i class="fa-solid fa-reply"></i></button>
                <button type="button" class="tracker-chat-action-btn" data-action="forward" title="Forward"><i class="fa-solid fa-share"></i></button>
                <button type="button" class="tracker-chat-action-btn" data-action="copy" title="Copy"><i class="fa-solid fa-copy"></i></button>
                <button type="button" class="tracker-chat-action-btn" data-action="pin" title="Pin/Unpin"><i class="fa-solid fa-thumbtack"></i></button>
                <button type="button" class="tracker-chat-action-btn" data-action="star" title="Star/Unstar"><i class="fa-${message.is_starred ? 'solid' : 'regular'} fa-star"></i></button>
                ${mine ? '<button type="button" class="tracker-chat-action-btn" data-action="edit" title="Edit"><i class="fa-solid fa-pen"></i></button>' : ''}
                ${mine ? '<button type="button" class="tracker-chat-action-btn" data-action="delete-everyone" title="Delete for everyone"><i class="fa-solid fa-trash"></i></button>' : ''}
                <button type="button" class="tracker-chat-action-btn" data-action="delete-me" title="Delete for me"><i class="fa-solid fa-eraser"></i></button>
            </div>
        `;
    }

    _bindBubbleActions(bubbleRow, message, mine) {
        const actionsEl = bubbleRow.querySelector('.tracker-chat-bubble-actions');
        const bubbleEl = bubbleRow.querySelector('.tracker-chat-bubble');

        if (actionsEl && !bubbleRow.previousElementSibling) {
            actionsEl.classList.add('tracker-chat-bubble-actions-below');
        }

        bubbleEl?.addEventListener('click', (event) => {
            if (event.target.closest('.tracker-chat-bubble-actions, [data-lightbox-uuid], .tracker-chat-attachment-video, .tracker-chat-attachment-audio, .tracker-chat-attachment-document')) {
                return;
            }

            const isOpen = actionsEl?.classList.contains('show');
            this._closeAllBubbleActions();

            if (actionsEl && !isOpen) {
                actionsEl.classList.add('show');
            }
        });

        bubbleRow.querySelectorAll('.tracker-chat-bubble-actions [data-action]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.stopPropagation();
                this._handleBubbleAction(button.dataset.action, message, mine, button.dataset.emoji);
                this._closeAllBubbleActions();
            });
        });

        bubbleRow.querySelector('[data-lightbox-uuid]')?.addEventListener('click', () => this._openLightbox(message.uuid));
        bubbleRow.querySelector('[data-action="open-location"]')?.addEventListener('click', () => this._openLocationMap(message));
        bubbleRow.querySelector('[data-action="cancel-live-location"]')?.addEventListener('click', () => this._cancelLiveLocation(message));

        const video = bubbleRow.querySelector('.tracker-chat-attachment-video video');

        bubbleRow.querySelector('[data-action="video-speed"]')?.addEventListener('change', (event) => {
            if (video) video.playbackRate = Number(event.target.value);
        });

        bubbleRow.querySelector('[data-action="video-pip"]')?.addEventListener('click', async () => {
            if (!video) return;

            try {
                if (document.pictureInPictureElement) {
                    await document.exitPictureInPicture();
                } else {
                    await video.requestPictureInPicture();
                }
            } catch (e) {
                // Picture-in-Picture isn't supported/allowed in this browser — ignore.
            }
        });
    }

    async _handleBubbleAction(action, message, mine, emoji) {
        switch (action) {
            case 'react':
                await window.axios.post(`/api/v1/messages/${message.uuid}/react`, { emoji });
                break;
            case 'reply':
                this._setReply(message);
                break;
            case 'forward':
                this.forwardingMessage = message;
                new Modal(document.getElementById('new-chat-modal')).show();
                break;
            case 'copy':
                navigator.clipboard?.writeText(message.body ?? '');
                break;
            case 'pin':
                if (message.is_pinned) {
                    await window.axios.delete(`/api/v1/messages/${message.uuid}/pin`);
                } else {
                    await window.axios.post(`/api/v1/messages/${message.uuid}/pin`);
                }
                break;
            case 'star':
                if (message.is_starred) {
                    await window.axios.delete(`/api/v1/messages/${message.uuid}/star`);
                    message.is_starred = false;
                } else {
                    await window.axios.post(`/api/v1/messages/${message.uuid}/star`);
                    message.is_starred = true;
                }
                this._onMessageUpdated(this.currentUuid, message);
                break;
            case 'edit':
                this._setEdit(message);
                break;
            case 'delete-everyone':
                if (confirm('Delete this message for everyone?')) {
                    await window.axios.delete(`/api/v1/messages/${message.uuid}`);
                }
                break;
            case 'delete-me':
                await window.axios.delete(`/api/v1/messages/${message.uuid}/for-me`);
                bubbleRowRemove(message.uuid, this.messagesEl);
                break;
            default:
                break;
        }
    }

    // ---------- Reply / edit state ----------

    _setReply(message) {
        this.editingUuid = null;
        this.editBarEl.classList.add('d-none');
        this.replyTo = message;
        this.replyLabelEl.textContent = `Replying to ${message.sender.name}`;
        this.replyBodyEl.textContent = message.body ?? '';
        this.replyBarEl.classList.remove('d-none');
        this.composerInputEl.focus();
    }

    _clearReply() {
        this.replyTo = null;
        this.replyBarEl.classList.add('d-none');
    }

    _setEdit(message) {
        this._clearReply();
        this.editingUuid = message.uuid;
        this.composerInputEl.value = message.body ?? '';
        this.editBarEl.classList.remove('d-none');
        this.composerInputEl.focus();
    }

    _clearEdit() {
        this.editingUuid = null;
        this.editBarEl.classList.add('d-none');
    }

    // ---------- Composer ----------

    async _submitComposer() {
        if (!this._currentConversationCanCommunicate()) return;

        const body = this.composerInputEl.value.trim();

        if (!body || !this.currentUuid) return;

        if (this.editingUuid) {
            await window.axios.patch(`/api/v1/messages/${this.editingUuid}`, { body });
            this._clearEdit();
        } else {
            await window.axios.post(`/api/v1/conversations/${this.currentUuid}/messages`, {
                body,
                reply_to_message_id: this.replyTo?.uuid ?? null,
            });
            this._clearReply();
        }

        this.composerInputEl.value = '';
    }

    _handleTyping() {
        if (!this.currentUuid || !this._currentConversationCanCommunicate()) return;

        const now = Date.now();

        if (now - this.lastTypingWhisperAt > TYPING_THROTTLE_MS) {
            this.channels.get(this.currentUuid)?.whisper('typing', { user_id: this.currentUserId, name: this.currentUserName });
            this.lastTypingWhisperAt = now;
        }
    }

    async _sendTextMessage(body, metadata = null, conversationUuid = this.currentUuid) {
        if (conversationUuid === this.currentUuid && !this._currentConversationCanCommunicate()) return;
        if (!body || !conversationUuid) return;

        await window.axios.post(`/api/v1/conversations/${conversationUuid}/messages`, {
            body,
            metadata,
            reply_to_message_id: this.replyTo?.uuid ?? null,
        });
        this._clearReply();
    }

    // ---------- Attachments ----------

    _handleShareOption(kind) {
        if (!this.currentUuid || !this._currentConversationCanCommunicate()) return;

        if (kind === 'photo') {
            this.photoInputEl?.click();
            return;
        }

        if (kind === 'document') {
            this.documentInputEl?.click();
            return;
        }

        if (kind === 'file') {
            this.attachmentInputEl?.click();
            return;
        }

        if (kind === 'current-location' || kind === 'live-location') {
            this._shareLocation(kind === 'live-location');
            return;
        }

        if (kind === 'contact') {
            this._shareContact();
        }
    }

    _previewSelectedFile(input) {
        const file = input.files[0];
        input.value = '';

        if (!file || !this.currentUuid) return;

        this._openAttachmentPreview(file);
    }

    _openAttachmentPreview(file) {
        this.pendingAttachment = file;
        this.previewRotation = 0;
        this.previewImage = null;
        this.previewDrawMode = false;
        this.previewCaptionEl.value = '';
        this.previewViewOnceEl.checked = false;
        this._renderAttachmentPreview();
        new Modal(this.previewModalEl).show();
    }

    _renderAttachmentPreview() {
        if (!this.pendingAttachment) return;

        const file = this.pendingAttachment;
        const url = URL.createObjectURL(file);

        if (file.type.startsWith('image/')) {
            this.previewStageEl.innerHTML = '<canvas class="tracker-attachment-editor-canvas"></canvas>';
            this.previewCanvasEl = this.previewStageEl.querySelector('canvas');
            const image = new Image();
            image.onload = () => {
                this.previewImage = image;
                this._paintPreviewCanvas();
                URL.revokeObjectURL(url);
            };
            image.src = url;
            return;
        }

        if (file.type.startsWith('video/')) {
            this.previewStageEl.innerHTML = `<video controls playsinline src="${url}"></video>`;
            return;
        }

        if (file.type.startsWith('audio/')) {
            this.previewStageEl.innerHTML = `<audio controls src="${url}"></audio>`;
            return;
        }

        this.previewStageEl.innerHTML = `
            <div class="tracker-attachment-preview-file">
                <i class="fa-solid fa-file-lines"></i>
                <strong>${escapeHtml(file.name)}</strong>
                <span>${formatFileSize(file.size)}</span>
            </div>
        `;
    }

    _rotatePreview(delta) {
        if (!this.pendingAttachment?.type.startsWith('image/')) return;

        this.previewRotation = (this.previewRotation + delta + 360) % 360;
        this._paintPreviewCanvas();
    }

    _paintPreviewCanvas(sourceCanvas = null) {
        if (!this.previewCanvasEl || (!this.previewImage && !sourceCanvas)) return;

        const source = sourceCanvas ?? this.previewImage;
        const rotated = this.previewRotation % 180 !== 0;
        const maxWidth = 1000;
        const scale = Math.min(1, maxWidth / Math.max(source.width, source.height));
        const width = Math.round((rotated ? source.height : source.width) * scale);
        const height = Math.round((rotated ? source.width : source.height) * scale);
        const canvas = this.previewCanvasEl;
        const ctx = canvas.getContext('2d');
        canvas.width = width;
        canvas.height = height;
        ctx.clearRect(0, 0, width, height);
        ctx.save();
        ctx.translate(width / 2, height / 2);
        ctx.rotate((this.previewRotation * Math.PI) / 180);
        ctx.drawImage(source, -source.width * scale / 2, -source.height * scale / 2, source.width * scale, source.height * scale);
        ctx.restore();
        this._bindPreviewCanvasDrawing();
    }

    _bindPreviewCanvasDrawing() {
        const canvas = this.previewCanvasEl;
        if (!canvas || canvas.dataset.bound === 'true') return;

        canvas.dataset.bound = 'true';
        let drawing = false;
        const draw = (event) => {
            if (!drawing || !this.previewDrawMode) return;
            const rect = canvas.getBoundingClientRect();
            const ctx = canvas.getContext('2d');
            ctx.lineWidth = 6;
            ctx.lineCap = 'round';
            ctx.strokeStyle = document.getElementById('attachment-preview-color')?.value ?? '#ff3b30';
            ctx.lineTo((event.clientX - rect.left) * (canvas.width / rect.width), (event.clientY - rect.top) * (canvas.height / rect.height));
            ctx.stroke();
        };

        canvas.addEventListener('pointerdown', (event) => {
            if (!this.previewDrawMode) return;
            drawing = true;
            const rect = canvas.getBoundingClientRect();
            const ctx = canvas.getContext('2d');
            ctx.beginPath();
            ctx.moveTo((event.clientX - rect.left) * (canvas.width / rect.width), (event.clientY - rect.top) * (canvas.height / rect.height));
        });
        canvas.addEventListener('pointermove', draw);
        window.addEventListener('pointerup', () => { drawing = false; });
    }

    _togglePreviewDraw() {
        if (!this.pendingAttachment?.type.startsWith('image/')) return;

        this.previewDrawMode = !this.previewDrawMode;
        document.getElementById('attachment-preview-draw')?.classList.toggle('active', this.previewDrawMode);
    }

    _stampPreviewEmoji() {
        if (!this.previewCanvasEl) return;

        const emoji = prompt('Emoji to place on image', '👍');
        if (!emoji) return;

        const ctx = this.previewCanvasEl.getContext('2d');
        ctx.font = `${Math.max(42, Math.round(this.previewCanvasEl.width * 0.08))}px sans-serif`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(emoji, this.previewCanvasEl.width / 2, this.previewCanvasEl.height / 2);
    }

    _cropPreviewSquare() {
        if (!this.previewCanvasEl) return;

        const source = this.previewCanvasEl;
        const side = Math.min(source.width, source.height);
        const temp = document.createElement('canvas');
        temp.width = side;
        temp.height = side;
        temp.getContext('2d').drawImage(source, (source.width - side) / 2, (source.height - side) / 2, side, side, 0, 0, side, side);
        this.previewRotation = 0;
        this._paintPreviewCanvas(temp);
    }

    _resetPreviewEdits() {
        if (!this.pendingAttachment?.type.startsWith('image/')) return;

        this.previewRotation = 0;
        this.previewDrawMode = false;
        document.getElementById('attachment-preview-draw')?.classList.remove('active');
        this._renderAttachmentPreview();
    }

    _clearAttachmentPreview() {
        this.pendingAttachment = null;
        this.previewImage = null;
        this.previewCanvasEl = null;
        this.previewDrawMode = false;
        this.previewStageEl.replaceChildren();
        Modal.getInstance(this.previewModalEl)?.hide();
    }

    async _sendPendingAttachment() {
        if (!this.pendingAttachment) return;

        const caption = this.previewCaptionEl.value.trim();
        const viewOnce = this.previewViewOnceEl.checked;
        const file = await this._preparedAttachmentFile();
        await this._uploadFile(file, {
            caption,
            viewOnce,
        });
        this._clearAttachmentPreview();
    }

    async _preparedAttachmentFile() {
        if (!this.pendingAttachment?.type.startsWith('image/') || !this.previewCanvasEl) {
            return this.pendingAttachment;
        }

        const blob = await new Promise((resolve) => this.previewCanvasEl.toBlob(resolve, this.pendingAttachment.type || 'image/png', 0.92));

        return new File([blob], this.pendingAttachment.name, { type: blob.type });
    }

    async _uploadFile(file, { caption = '', viewOnce = false } = {}) {
        if (!file || !this.currentUuid) return;

        const formData = new FormData();
        formData.append('file', file);

        if (caption) {
            formData.append('caption', caption);
        }

        if (viewOnce) {
            formData.append('view_once', '1');
        }

        if (this.replyTo) {
            formData.append('reply_to_message_id', this.replyTo.uuid);
        }

        this.uploadProgressEl.classList.remove('d-none');
        this.uploadProgressBarEl.style.width = '0%';
        this.uploadProgressLabelEl.textContent = `Uploading ${file.name}…`;

        try {
            await window.axios.post(`/api/v1/conversations/${this.currentUuid}/attachments`, formData, {
                onUploadProgress: (event) => {
                    if (!event.total) return;
                    this.uploadProgressBarEl.style.width = `${Math.round((event.loaded / event.total) * 100)}%`;
                },
            });
            this._clearReply();
        } catch (error) {
            const message = error.response?.data?.errors?.file?.[0] ?? error.response?.data?.message ?? 'Upload failed.';
            alert(message);
        } finally {
            this.uploadProgressEl.classList.add('d-none');
        }
    }

    async _toggleRecording(kind) {
        if (this.mediaRecorder) {
            this.mediaRecorder.stop();
            return;
        }

        if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {
            alert('Recording is not supported in this browser.');
            return;
        }

        const button = document.getElementById(kind === 'audio' ? 'chat-audio-record-btn' : 'chat-video-record-btn');

        try {
            this.recordingKind = kind;
            this.recordingChunks = [];
            this.recordingStream = await navigator.mediaDevices.getUserMedia({
                audio: true,
                video: kind === 'video',
            });
            this.mediaRecorder = new MediaRecorder(this.recordingStream);
            this.mediaRecorder.ondataavailable = (event) => {
                if (event.data.size > 0) this.recordingChunks.push(event.data);
            };
            this.mediaRecorder.onstop = () => this._finishRecording(button);
            this.mediaRecorder.start();
            button?.classList.add('recording');
            button?.setAttribute('title', 'Stop recording');
        } catch (error) {
            alert('Please allow microphone/camera permission to record.');
            this._cleanupRecording(button);
        }
    }

    _finishRecording(button) {
        const type = this.recordingKind === 'video' ? 'video/webm' : 'audio/webm';
        const extension = this.recordingKind === 'video' ? 'webm' : 'webm';
        const blob = new Blob(this.recordingChunks, { type });
        const file = new File([blob], `${this.recordingKind}-recording-${Date.now()}.${extension}`, { type });

        this._cleanupRecording(button);

        if (blob.size > 0) {
            this._openAttachmentPreview(file);
        }
    }

    _cleanupRecording(button = null) {
        this.recordingStream?.getTracks().forEach((track) => track.stop());
        this.recordingStream = null;
        this.mediaRecorder = null;
        this.recordingChunks = [];
        this.recordingKind = null;
        button?.classList.remove('recording');
        button?.setAttribute('title', button?.id === 'chat-video-record-btn' ? 'Record video' : 'Record audio');
    }

    async _shareLocation(isLive) {
        if (!this.currentUuid) return;

        if (!navigator.geolocation) {
            this._flashLocationStatus('Location sharing is not supported in this browser.');
            return;
        }

        if (isLive) {
            this.pendingLiveLocationConversationUuid = this.currentUuid;

            const statusEl = document.getElementById('live-location-share-status');
            statusEl?.classList.add('d-none');
            if (statusEl) statusEl.textContent = '';

            this.liveLocationShareModal?.show();
            return;
        }

        navigator.geolocation.getCurrentPosition(async (position) => {
            const { latitude, longitude } = position.coords;
            const metadata = {
                kind: 'current_location',
                latitude,
                longitude,
                duration_minutes: null,
                expires_at: null,
            };
            await this._sendTextMessage('Current location shared', metadata);
        }, () => {
            this._flashLocationStatus('Please allow location permission to share your location.');
        }, { enableHighAccuracy: true, timeout: 10000 });
    }

    _flashLocationStatus(message) {
        const statusEl = document.getElementById('live-location-share-status');

        if (statusEl) {
            statusEl.textContent = message;
            statusEl.classList.remove('d-none');
            return;
        }

        console.error(message);
    }

    async _confirmLiveLocationShare() {
        const conversationUuid = this.pendingLiveLocationConversationUuid ?? this.currentUuid;

        if (!conversationUuid) return;

        if (!navigator.geolocation) {
            this._flashLocationStatus('Location sharing is not supported in this browser.');
            return;
        }

        const duration = this.pendingLiveLocationDuration;
        const statusEl = document.getElementById('live-location-share-status');
        const confirmBtn = document.getElementById('live-location-share-confirm');

        confirmBtn?.setAttribute('disabled', 'disabled');
        if (statusEl) {
            statusEl.textContent = 'Fetching your location...';
            statusEl.classList.remove('d-none');
        }

        navigator.geolocation.getCurrentPosition(async (position) => {
            try {
                const { latitude, longitude } = position.coords;
                const expiresAt = duration?.minutes ? new Date(Date.now() + duration.minutes * 60_000).toISOString() : null;
                const metadata = {
                    kind: 'live_location',
                    latitude,
                    longitude,
                    duration_minutes: duration?.minutes ?? null,
                    expires_at: expiresAt,
                };
                const body = `Live location shared${duration?.label ? ` for ${duration.label}` : ' permanently'}`;

                await this._sendTextMessage(body, metadata, conversationUuid);
                this._startLiveLocationBroadcast(conversationUuid, metadata);

                this.liveLocationShareModal?.hide();
            } catch (error) {
                if (statusEl) statusEl.textContent = 'Could not share location. Please try again.';
                confirmBtn?.removeAttribute('disabled');
            }
        }, () => {
            if (statusEl) statusEl.textContent = 'Please allow location permission to share your location.';
            confirmBtn?.removeAttribute('disabled');
        }, { enableHighAccuracy: true, timeout: 10000 });
    }

    _bindBatteryMonitor() {
        if (this.batteryMonitorBound || !navigator.getBattery) return;

        this.batteryMonitorBound = true;

        navigator.getBattery().then((battery) => {
            const update = () => {
                this.batteryInfo = { level: Math.round(battery.level * 100), charging: battery.charging };
            };

            update();
            battery.addEventListener('levelchange', update);
            battery.addEventListener('chargingchange', update);
        }).catch(() => {});
    }

    _startLiveLocationBroadcast(conversationUuid, metadata) {
        if (!navigator.geolocation) return;

        this._stopLiveLocationBroadcast(conversationUuid);
        this._bindBatteryMonitor();

        const expiresAt = metadata.expires_at ? new Date(metadata.expires_at).getTime() : null;
        let prevFix = null;

        const watchId = navigator.geolocation.watchPosition((position) => {
            if (expiresAt && Date.now() > expiresAt) {
                this._stopLiveLocationBroadcast(conversationUuid);
                return;
            }

            const motion = computeMotionState(prevFix, position);
            prevFix = motion.fix;

            this.channels.get(conversationUuid)?.whisper('live-location-update', {
                user_id: this.currentUserId,
                name: this.currentUserName,
                avatar: window.__trackerUserAvatar ?? null,
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
                accuracy: position.coords.accuracy,
                speed: motion.speed,
                heading: motion.heading,
                is_idle: motion.isIdle,
                battery_level: this.batteryInfo?.level ?? null,
                is_charging: this.batteryInfo?.charging ?? null,
                recorded_at: new Date().toISOString(),
            });
        }, () => {}, { enableHighAccuracy: true, maximumAge: 5000, timeout: 15000 });

        this.liveLocationWatchers.set(conversationUuid, watchId);
    }

    _stopLiveLocationBroadcast(conversationUuid) {
        const watchId = this.liveLocationWatchers.get(conversationUuid);

        if (watchId !== undefined) {
            navigator.geolocation.clearWatch(watchId);
            this.liveLocationWatchers.delete(conversationUuid);
        }
    }

    async _cancelLiveLocation(message) {
        await window.axios.post(`/api/v1/messages/${message.uuid}/cancel-live-location`);
        this._stopLiveLocationBroadcast(message.conversation_uuid ?? this.currentUuid);
    }

    _openLocationMap(message) {
        const metadata = message.metadata ?? {};

        if (!metadata.latitude || !metadata.longitude) return;

        const conversationUuid = message.conversation_uuid ?? this.currentUuid;

        this.activeLiveLocationMessage = message;
        this.liveLocationConversationUuid = conversationUuid;

        if (message.sender?.id !== this.currentUserId && !this.liveLocationPeers.has(conversationUuid)) {
            const conversation = this.conversations.get(conversationUuid);
            const avatarUrl = conversation?.other_user?.id === message.sender?.id ? conversation.other_user.avatar_url : null;

            this.liveLocationPeers.set(conversationUuid, {
                userId: message.sender?.id,
                name: message.sender?.name ?? 'Shared location',
                avatarUrl,
                latLng: [Number(metadata.latitude), Number(metadata.longitude)],
                speed: null,
                heading: null,
                isIdle: true,
                battery: null,
                charging: null,
                updatedAt: message.created_at,
            });
        }

        this.liveLocationModal ??= new Modal(document.getElementById('live-location-modal'));
        document.getElementById('live-location-title').textContent = message.sender?.name
            ? `${message.sender.name}'s location`
            : 'Live Location';
        document.getElementById('live-location-meta').textContent = metadata.cancelled_at
            ? 'Location sharing cancelled'
            : (metadata.expires_at ? `Available until ${new Date(metadata.expires_at).toLocaleString()}` : 'Permanent sharing until cancelled');

        this.liveLocationModal.show();

        window.setTimeout(() => {
            const peer = this.liveLocationPeers.get(conversationUuid);
            const center = peer?.latLng ?? [Number(metadata.latitude), Number(metadata.longitude)];

            this._ensureLiveLocationMap(center);

            if (peer) {
                this._moveLiveLocationMarker(peer.latLng[0], peer.latLng[1], peer.name);
            }

            this._startSelfLiveWatch();
            this._loadRouteProposal(conversationUuid);
        }, 200);
    }

    _ensureLiveLocationMap(center) {
        if (!this.liveLocationMap) {
            this.liveLocationMap = L.map('live-location-map').setView(center, 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(this.liveLocationMap);

            document.getElementById('live-location-route')?.addEventListener('click', () => this._toggleRoutePickMode());
            this.liveLocationMap.on('click', (event) => this._handleLiveLocationMapClick(event));

            document.getElementById('live-location-modal')?.addEventListener('hidden.bs.modal', () => {
                this._stopSelfLiveWatch();
                this._setRoutePickMode(false);
                this.activeLiveLocationMessage = null;
                this.liveLocationConversationUuid = null;
            });
        }

        this.liveLocationMap.invalidateSize();
        this.liveLocationMap.setView(center, this.liveLocationMap.getZoom() || 15);
    }

    _moveLiveLocationMarker(latitude, longitude, name) {
        if (!this.liveLocationMap) return;

        const latLng = [latitude, longitude];
        const peer = this.liveLocationPeers.get(this.liveLocationConversationUuid) ?? {};
        const isIdle = peer.isIdle ?? true;
        const icon = liveLocationMarkerIcon({ avatarUrl: peer.avatarUrl, name, variant: 'peer', isIdle, heading: peer.heading });

        if (!this.liveLocationMarker) {
            this.liveLocationMarker = L.marker(latLng, { icon, zIndexOffset: 400 }).addTo(this.liveLocationMap);
        } else {
            this.liveLocationMarker.setLatLng(latLng);
            this.liveLocationMarker.setIcon(icon);
        }

        this.liveLocationMarker.bindPopup(this._liveLocationPopupHtml({
            name,
            isSelf: false,
            isIdle,
            speed: peer.speed,
            heading: peer.heading,
            battery: peer.battery,
            charging: peer.charging,
            updatedAt: peer.updatedAt,
        }));

        this._refreshLiveLocationLine();
    }

    _onLiveLocationUpdate(conversationUuid, payload) {
        if (payload.user_id === this.currentUserId) return;

        const conversation = this.conversations.get(conversationUuid);
        const avatarUrl = conversation?.other_user?.id === payload.user_id
            ? conversation.other_user.avatar_url
            : (payload.avatar ?? null);

        this.liveLocationPeers.set(conversationUuid, {
            userId: payload.user_id,
            name: payload.name ?? 'Live location',
            avatarUrl,
            latLng: [Number(payload.latitude), Number(payload.longitude)],
            speed: typeof payload.speed === 'number' ? payload.speed : null,
            heading: typeof payload.heading === 'number' ? payload.heading : null,
            isIdle: payload.is_idle ?? true,
            battery: typeof payload.battery_level === 'number' ? payload.battery_level : null,
            charging: payload.is_charging ?? null,
            updatedAt: payload.recorded_at ?? new Date().toISOString(),
        });

        if (this.activeLiveLocationMessage?.uuid && conversationUuid === this.liveLocationConversationUuid) {
            this.activeLiveLocationMessage.metadata.latitude = payload.latitude;
            this.activeLiveLocationMessage.metadata.longitude = payload.longitude;
            this._moveLiveLocationMarker(Number(payload.latitude), Number(payload.longitude), payload.name ?? 'Live location');
        }
    }

    _startSelfLiveWatch() {
        if (!navigator.geolocation || this.liveLocationSelfWatchId != null) return;

        this._bindBatteryMonitor();
        let prevFix = null;

        this.liveLocationSelfWatchId = navigator.geolocation.watchPosition((position) => {
            const motion = computeMotionState(prevFix, position);
            prevFix = motion.fix;

            this.selfLiveStatus = {
                speed: motion.speed,
                heading: motion.heading,
                isIdle: motion.isIdle,
                battery: this.batteryInfo?.level ?? null,
                charging: this.batteryInfo?.charging ?? null,
                updatedAt: new Date().toISOString(),
            };

            this._moveSelfMarker(position.coords.latitude, position.coords.longitude);
        }, () => {
            const distanceEl = document.getElementById('live-location-distance');
            if (distanceEl) distanceEl.textContent = 'Allow location to calculate distance';
        }, { enableHighAccuracy: true, maximumAge: 5000, timeout: 15000 });
    }

    _stopSelfLiveWatch() {
        if (this.liveLocationSelfWatchId != null) {
            navigator.geolocation.clearWatch(this.liveLocationSelfWatchId);
            this.liveLocationSelfWatchId = null;
        }
    }

    _moveSelfMarker(latitude, longitude) {
        if (!this.liveLocationMap) return;

        const selfLatLng = [latitude, longitude];
        const status = this.selfLiveStatus ?? {};
        const isIdle = status.isIdle ?? true;
        const icon = liveLocationMarkerIcon({ avatarUrl: window.__trackerUserAvatar, name: 'You', variant: 'self', isIdle, heading: status.heading });

        if (!this.liveLocationSelfMarker) {
            this.liveLocationSelfMarker = L.marker(selfLatLng, { icon, zIndexOffset: 500 }).addTo(this.liveLocationMap);
        } else {
            this.liveLocationSelfMarker.setLatLng(selfLatLng);
            this.liveLocationSelfMarker.setIcon(icon);
        }

        this.liveLocationSelfMarker.bindPopup(this._liveLocationPopupHtml({
            name: this.currentUserName || 'You',
            isSelf: true,
            isIdle,
            speed: status.speed,
            heading: status.heading,
            battery: status.battery,
            charging: status.charging,
            updatedAt: status.updatedAt,
        }));

        this._refreshLiveLocationLine();
    }

    _liveLocationPopupHtml({ name, isSelf, isIdle, speed, heading, battery, charging, updatedAt }) {
        const speedKmh = typeof speed === 'number' ? speed * 3.6 : null;
        const compass = headingToCompass(heading);
        const statusLine = isIdle
            ? 'Idle'
            : `Moving${speedKmh !== null ? ` • ${speedKmh.toFixed(1)} km/h` : ''}${compass ? ` • Heading ${compass}` : ''}`;

        const batteryLine = typeof battery === 'number'
            ? `<div class="tracker-live-popup-row"><i class="fa-solid ${charging ? 'fa-bolt' : 'fa-battery-three-quarters'}"></i> ${battery}%${charging ? ' (charging)' : ''}</div>`
            : '';

        const updatedLine = updatedAt ? `<div class="tracker-live-popup-row text-muted">${escapeHtml(formatRelativeTime(updatedAt))}</div>` : '';

        return `
            <div class="tracker-live-popup">
                <strong>${escapeHtml(name)}${isSelf ? ' (You)' : ''}</strong>
                <div class="tracker-live-popup-row"><span class="tracker-live-popup-status-dot ${isIdle ? 'idle' : 'moving'}"></span> ${escapeHtml(statusLine)}</div>
                ${batteryLine}
                ${updatedLine}
            </div>
        `;
    }

    _refreshLiveLocationLine() {
        if (!this.liveLocationMap || !this.liveLocationMarker || !this.liveLocationSelfMarker) return;

        const selfLatLng = this.liveLocationSelfMarker.getLatLng();
        const remoteLatLng = this.liveLocationMarker.getLatLng();
        const points = [[selfLatLng.lat, selfLatLng.lng], [remoteLatLng.lat, remoteLatLng.lng]];

        if (this.liveLocationLine) {
            this.liveLocationLine.setLatLngs(points);
        } else {
            this.liveLocationLine = L.polyline(points, { color: '#38bdf8', weight: 4, dashArray: '6 6' }).addTo(this.liveLocationMap);
        }

        const distanceEl = document.getElementById('live-location-distance');
        if (distanceEl) {
            distanceEl.textContent = `Distance: ${this._formatDistance(this.liveLocationMap.distance(selfLatLng, remoteLatLng))}`;
        }

        if (!this.routePickMode) {
            this.liveLocationMap.fitBounds(this.liveLocationLine.getBounds(), { padding: [40, 40] });
        }

        this._updateRouteProposalDistance();
    }

    _formatDistance(meters) {
        return meters >= 1000 ? `${(meters / 1000).toFixed(2)} km` : `${Math.round(meters)} m`;
    }

    // ---------- Route proposals ----------

    _toggleRoutePickMode() {
        this._setRoutePickMode(!this.routePickMode);
    }

    _setRoutePickMode(enabled) {
        this.routePickMode = enabled;

        document.getElementById('live-location-map')?.classList.toggle('tracker-route-pick-mode', enabled);

        const routeBtn = document.getElementById('live-location-route');
        if (routeBtn) {
            routeBtn.innerHTML = enabled
                ? '<i class="fa-solid fa-xmark me-1"></i> Cancel picking'
                : '<i class="fa-solid fa-route me-1"></i> Propose meeting point';
        }
    }

    async _handleLiveLocationMapClick(event) {
        if (!this.routePickMode) return;

        const conversationUuid = this.liveLocationConversationUuid;
        if (!conversationUuid) return;

        const { lat, lng } = event.latlng;
        this._setRoutePickMode(false);

        try {
            const existing = this.routeProposals.get(conversationUuid);
            const canEdit = existing && existing.proposed_by.id === this.currentUserId
                && !['cancelled', 'rejected'].includes(existing.status);

            const { data } = canEdit
                ? await window.axios.patch(`/api/v1/route-proposals/${existing.uuid}`, { target_lat: lat, target_lng: lng })
                : await window.axios.post(`/api/v1/conversations/${conversationUuid}/route-proposal`, { target_lat: lat, target_lng: lng });

            this._applyRouteProposal(conversationUuid, data.data);
        } catch (error) {
            this._flashRouteStatus('Could not propose the meeting point. Please try again.');
        }
    }

    async _loadRouteProposal(conversationUuid) {
        try {
            const { data } = await window.axios.get(`/api/v1/conversations/${conversationUuid}/route-proposal`);
            this._applyRouteProposal(conversationUuid, data.data);
        } catch (error) {
            this._applyRouteProposal(conversationUuid, null);
        }
    }

    _onRouteProposalUpdated(conversationUuid, payload) {
        this._applyRouteProposal(conversationUuid, payload);
    }

    _applyRouteProposal(conversationUuid, proposal) {
        if (proposal) {
            this.routeProposals.set(conversationUuid, proposal);
        } else {
            this.routeProposals.delete(conversationUuid);
        }

        if (conversationUuid === this.liveLocationConversationUuid) {
            this._renderRouteProposalBanner(proposal);
        }
    }

    _renderRouteProposalBanner(proposal) {
        const bannerEl = document.getElementById('live-location-route-banner');
        const textEl = document.getElementById('live-location-route-banner-text');
        const actionsEl = document.getElementById('live-location-route-banner-actions');

        if (!bannerEl || !textEl || !actionsEl) return;

        this._clearRouteOverlay();

        if (!proposal || ['cancelled', 'rejected'].includes(proposal.status)) {
            bannerEl.classList.add('d-none');
            textEl.textContent = '';
            actionsEl.replaceChildren();
            return;
        }

        const mine = proposal.proposed_by.id === this.currentUserId;
        bannerEl.classList.remove('d-none');
        actionsEl.replaceChildren();

        if (proposal.status === 'accepted') {
            textEl.textContent = mine
                ? `${proposal.accepted_by?.name ?? 'They'} accepted your meeting point.`
                : `You accepted ${proposal.proposed_by.name}'s meeting point.`;

            this._drawRouteProposalTarget(proposal);

            if (mine) {
                actionsEl.appendChild(this._buildRouteActionButton('Change point', 'tracker-secondary-btn', () => this._setRoutePickMode(true)));
            }

            return;
        }

        if (mine) {
            textEl.textContent = 'Waiting for the other person to accept your meeting point...';
            this._drawRouteProposalTarget(proposal);
            actionsEl.appendChild(this._buildRouteActionButton('Cancel', 'tracker-secondary-btn', () => this._cancelRouteProposal(proposal)));
            return;
        }

        textEl.textContent = `${proposal.proposed_by.name} proposed a meeting point.`;
        this._drawRouteProposalTarget(proposal);
        actionsEl.appendChild(this._buildRouteActionButton('Accept', 'tracker-primary-btn', () => this._respondRouteProposal(proposal, 'accept')));
        actionsEl.appendChild(this._buildRouteActionButton('Reject', 'tracker-outline-btn', () => this._respondRouteProposal(proposal, 'reject')));
    }

    _buildRouteActionButton(label, className, onClick) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `btn btn-sm ${className}`;
        button.textContent = label;
        button.addEventListener('click', onClick);
        return button;
    }

    _drawRouteProposalTarget(proposal) {
        if (!this.liveLocationMap) return;

        const targetLatLng = [Number(proposal.target_lat), Number(proposal.target_lng)];

        if (!this.routeProposalMarker) {
            this.routeProposalMarker = L.marker(targetLatLng, {
                icon: L.divIcon({ className: 'tracker-route-target-icon', html: '<i class="fa-solid fa-flag-checkered"></i>', iconSize: [24, 24] }),
            }).addTo(this.liveLocationMap);
        } else {
            this.routeProposalMarker.setLatLng(targetLatLng);
        }

        this.routeProposalMarker.bindPopup(escapeHtml(proposal.label || 'Meeting point'));
        this._updateRouteProposalDistance();
    }

    _updateRouteProposalDistance() {
        if (!this.liveLocationMap || !this.routeProposalMarker || !this.liveLocationSelfMarker) return;

        const targetLatLng = this.routeProposalMarker.getLatLng();
        const selfLatLng = this.liveLocationSelfMarker.getLatLng();
        const points = [[selfLatLng.lat, selfLatLng.lng], [targetLatLng.lat, targetLatLng.lng]];

        if (this.routeProposalLine) {
            this.routeProposalLine.setLatLngs(points);
        } else {
            this.routeProposalLine = L.polyline(points, { color: '#f59e0b', weight: 4 }).addTo(this.liveLocationMap);
        }
    }

    _clearRouteOverlay() {
        if (this.routeProposalMarker) {
            this.liveLocationMap?.removeLayer(this.routeProposalMarker);
            this.routeProposalMarker = null;
        }

        if (this.routeProposalLine) {
            this.liveLocationMap?.removeLayer(this.routeProposalLine);
            this.routeProposalLine = null;
        }
    }

    async _respondRouteProposal(proposal, action) {
        try {
            const { data } = await window.axios.post(`/api/v1/route-proposals/${proposal.uuid}/${action}`);
            this._applyRouteProposal(proposal.conversation_uuid, data.data);
        } catch (error) {
            this._flashRouteStatus('Could not update the route proposal. Please try again.');
        }
    }

    async _cancelRouteProposal(proposal) {
        try {
            await window.axios.delete(`/api/v1/route-proposals/${proposal.uuid}`);
            this._applyRouteProposal(proposal.conversation_uuid, null);
        } catch (error) {
            this._flashRouteStatus('Could not cancel the route proposal. Please try again.');
        }
    }

    _flashRouteStatus(message) {
        const bannerEl = document.getElementById('live-location-route-banner');
        const textEl = document.getElementById('live-location-route-banner-text');

        if (bannerEl && textEl) {
            bannerEl.classList.remove('d-none');
            textEl.textContent = message;
        }
    }

    async _shareContact() {
        if (!this.contacts.length) {
            alert('No contacts available to share.');
            return;
        }

        const name = prompt(`Type contact name to share:\n${this.contacts.map((contact) => contact.name).join(', ')}`);
        if (!name) return;

        const contact = this.contacts.find((item) => item.name.toLowerCase() === name.trim().toLowerCase());
        if (!contact) {
            alert('Contact not found.');
            return;
        }

        await this._sendTextMessage(`Contact\n${contact.name}`);
    }

    // ---------- Shared media ----------

    _openSharedMedia() {
        if (!this.currentUuid) return;

        this.sharedMediaType = '';
        document.querySelectorAll('#shared-media-tabs .nav-link').forEach((el) => el.classList.remove('active'));
        document.querySelector('#shared-media-tabs [data-media-type=""]').classList.add('active');

        new Modal(document.getElementById('shared-media-modal')).show();
        this._loadSharedMedia();
    }

    async _loadSharedMedia() {
        const gridEl = document.getElementById('shared-media-grid');
        const emptyEl = document.getElementById('shared-media-empty');
        const query = this.sharedMediaType ? `?type=${this.sharedMediaType}` : '';

        const { data } = await window.axios.get(`/api/v1/conversations/${this.currentUuid}/attachments${query}`);
        const items = data.data;

        emptyEl.classList.toggle('d-none', items.length > 0);
        gridEl.replaceChildren();

        items.forEach((attachment) => {
            const card = document.createElement('a');
            card.className = 'tracker-shared-media-item';
            card.href = attachment.url;
            card.target = '_blank';
            card.rel = 'noopener';

            const icon = {
                image: 'fa-image',
                video: 'fa-video',
                audio: 'fa-music',
                document: 'fa-file-lines',
            }[attachment.type] ?? 'fa-file';

            card.innerHTML = attachment.type === 'image' && attachment.thumbnail_url
                ? `<img src="${attachment.thumbnail_url}" alt="${escapeHtml(attachment.original_name)}">`
                : `<i class="fa-solid ${icon}"></i>`;

            card.title = attachment.original_name;
            gridEl.appendChild(card);
        });
    }

    // ---------- Call history ----------

    async _openCallHistory() {
        if (!this.currentUuid) return;

        new Modal(document.getElementById('call-history-modal')).show();

        const listEl = document.getElementById('call-history-list');
        const emptyEl = document.getElementById('call-history-empty');

        const { data } = await window.axios.get(`/api/v1/conversations/${this.currentUuid}/calls`);
        const calls = data.data;

        emptyEl.classList.toggle('d-none', calls.length > 0);
        listEl.replaceChildren();

        calls.forEach((call) => {
            const row = document.createElement('div');
            row.className = 'tracker-chat-list-item';

            const icon = call.type === 'video' ? 'fa-video' : 'fa-phone';
            const outcomeIcon = {
                missed: 'fa-arrow-down text-danger',
                busy: 'fa-arrow-down text-danger',
                ended: 'fa-arrow-up text-success',
            }[call.status] ?? 'fa-arrow-up text-success';

            const durationLabel = call.started_at && call.ended_at
                ? this._formatDuration(new Date(call.ended_at) - new Date(call.started_at))
                : (call.status === 'missed' ? 'Missed' : (call.status === 'busy' ? 'Busy' : 'No answer'));

            row.innerHTML = `
                <div class="tracker-avatar-sm"><i class="fa-solid ${icon}"></i></div>
                <div class="tracker-chat-list-copy">
                    <strong><i class="fa-solid ${outcomeIcon} me-1 small"></i>${call.type === 'video' ? 'Video call' : 'Voice call'}</strong>
                    <div class="text-muted small">${escapeHtml(durationLabel)} · ${formatTime(call.started_at ?? call.ended_at)}</div>
                </div>
            `;
            listEl.appendChild(row);
        });
    }

    _formatDuration(ms) {
        const seconds = Math.max(0, Math.round(ms / 1000));

        return `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
    }

    _logCallInThread(callState, outcome) {
        if (!callState || callState.conversationUuid !== this.currentUuid) return;
        if (this.messagesEl.querySelector(`[data-message-uuid="call-${callState.uuid}"]`)) return;

        const kind = callState.type === 'video' ? 'Video call' : 'Voice call';
        let label = `${kind} ended`;

        if (outcome === 'missed') label = `Missed ${kind.toLowerCase()}`;
        else if (outcome === 'declined') label = `${kind} declined`;
        else if (outcome === 'busy') label = `${kind} — busy`;
        else if (callState.startedAt) label = `${kind}, ${this._formatDuration(Date.now() - callState.startedAt)}`;

        const notice = document.createElement('div');
        notice.className = 'tracker-chat-system-message tracker-chat-call-log';
        notice.dataset.messageUuid = `call-${callState.uuid}`;
        notice.innerHTML = `<i class="fa-solid ${callState.type === 'video' ? 'fa-video' : 'fa-phone'} me-1"></i>${label}`;
        this.messagesEl.appendChild(notice);
        this._scrollToBottom();
    }

    // ---------- Image lightbox ----------

    _currentImageMessages() {
        return Array.from(this.messagesEl.querySelectorAll('[data-message-uuid]'))
            .filter((el) => el.querySelector('[data-lightbox-uuid]'))
            .map((el) => el.dataset.messageUuid);
    }

    _openLightbox(messageUuid) {
        this.lightboxImages = this._currentImageMessages();
        this.lightboxIndex = this.lightboxImages.indexOf(messageUuid);
        this.lightboxRotation = 0;
        this.lightboxZoomed = false;
        this._renderLightbox();
        this.lightboxEl.classList.remove('d-none');
    }

    _renderLightbox() {
        const uuid = this.lightboxImages[this.lightboxIndex];
        const attachment = this.loadedMessages.get(uuid)?.attachments?.[0];

        if (!attachment || !attachment.url) return;

        this.lightboxImageEl.src = attachment.url;
        this.lightboxImageEl.onload = () => {
            if (attachment.view_once) {
                attachment.opened = true;
                attachment.url = null;
                attachment.thumbnail_url = null;
                this._renderMessages(Array.from(this.loadedMessages.values()));
            }
        };
        this.lightboxImageEl.style.transform = `rotate(${this.lightboxRotation}deg) scale(${this.lightboxZoomed ? 1.8 : 1})`;

        const downloadLink = document.getElementById('lightbox-download');
        downloadLink.href = attachment.url;
        downloadLink.download = attachment.original_name;
    }

    _navigateLightbox(direction) {
        if (this.lightboxImages.length === 0) return;

        this.lightboxIndex = (this.lightboxIndex + direction + this.lightboxImages.length) % this.lightboxImages.length;
        this.lightboxRotation = 0;
        this.lightboxZoomed = false;
        this._renderLightbox();
    }

    _rotateLightbox() {
        this.lightboxRotation = (this.lightboxRotation + 90) % 360;
        this._renderLightbox();
    }

    _toggleLightboxZoom() {
        this.lightboxZoomed = !this.lightboxZoomed;
        this._renderLightbox();
    }

    _fullscreenLightbox() {
        this.lightboxEl.requestFullscreen?.().catch(() => {});
    }

    async _deleteLightboxImage() {
        const uuid = this.lightboxImages[this.lightboxIndex];

        if (!uuid || !confirm('Delete this photo for everyone?')) return;

        await window.axios.delete(`/api/v1/messages/${uuid}`);
        this._closeLightbox();
    }

    _closeLightbox() {
        this.lightboxEl.classList.add('d-none');

        if (document.fullscreenElement) {
            document.exitFullscreen?.().catch(() => {});
        }
    }

    // ---------- Realtime handlers ----------

    _onMessageSent(uuid, payload) {
        this._updateConversationPreview(uuid, payload);

        if (uuid === this.currentUuid) {
            this._appendMessage(payload);
            this._scrollToBottom();

            if (payload.sender.id !== this.currentUserId) {
                window.axios.post(`/api/v1/conversations/${uuid}/read`).catch(() => {});
            }
        } else if (payload.sender.id !== this.currentUserId) {
            window.axios.post(`/api/v1/conversations/${uuid}/delivered`).catch(() => {});
        }
    }

    _onMessageUpdated(uuid, payload) {
        this.loadedMessages.set(payload.uuid, payload);

        const existing = this.messagesEl.querySelector(`[data-message-uuid="${payload.uuid}"]`);

        if (existing) {
            const mine = payload.sender.id === this.currentUserId;
            existing.className = `tracker-chat-bubble-row ${mine ? 'mine' : 'theirs'}`;
            existing.innerHTML = this._bubbleHtml(payload, mine);
            this._bindBubbleActions(existing, payload, mine);
        }

        if (this.activeLiveLocationMessage?.uuid === payload.uuid) {
            this.activeLiveLocationMessage = payload;

            if (payload.metadata?.cancelled_at) {
                const metaEl = document.getElementById('live-location-meta');
                if (metaEl) metaEl.textContent = 'Location sharing cancelled';
            }
        }

        this._updateConversationPreview(uuid, payload);
    }

    _onMessageDeleted(uuid, payload) {
        const existing = this.messagesEl.querySelector(`[data-message-uuid="${payload.uuid}"]`);

        if (existing) {
            const bodyEl = existing.querySelector('.tracker-chat-bubble-body');
            if (bodyEl) bodyEl.innerHTML = '<em>This message was deleted</em>';
            existing.querySelector('.tracker-chat-bubble-actions')?.remove();
        }
    }

    _onMessageDelivered(uuid) {
        if (uuid !== this.currentUuid) return;
        // Ticks refresh on next open/read; low-priority for a live in-place update in this milestone.
    }

    _onMessagesRead(uuid) {
        if (uuid !== this.currentUuid) return;

        this.messagesEl.querySelectorAll('.tracker-chat-tick').forEach((tick) => {
            tick.classList.remove('fa-check');
            tick.classList.add('fa-check-double', 'tracker-chat-tick-read');
        });
    }

    _onReactionUpdated(uuid, payload) {
        const existing = this.messagesEl.querySelector(`[data-message-uuid="${payload.message_uuid}"]`);

        if (!existing) return;

        const container = existing.querySelector('.tracker-chat-reactions');
        const html = this._groupReactions(payload.reactions);

        if (container) {
            container.innerHTML = html;
        } else if (html) {
            const body = existing.querySelector('.tracker-chat-bubble-body');
            body?.insertAdjacentHTML('afterend', `<div class="tracker-chat-reactions">${html}</div>`);
        }
    }

    _onConversationUpdated(uuid, payload) {
        const conversation = this.conversations.get(uuid);

        if (conversation) {
            conversation.name = payload.name;
            conversation.member_count = payload.member_count;
            this._renderConversationList();
        }

        if (uuid === this.currentUuid) {
            this.headerNameEl.textContent = payload.name;
            this._renderStatusForConversation(conversation);

            if (document.getElementById('group-info-modal').classList.contains('show')) {
                this._refreshGroupInfo();
            }
        }
    }

    _onConversationDeleted(uuid) {
        this.conversations.delete(uuid);
        this._renderConversationList();

        if (uuid === this.currentUuid) {
            Modal.getInstance(document.getElementById('group-info-modal'))?.hide();
            this._closeWindow();
        }
    }

    _onTypingWhisper(uuid, payload) {
        if (uuid !== this.currentUuid || payload.user_id === this.currentUserId) return;

        this.typingUsers.set(payload.user_id, payload.name || 'Someone');
        this._renderTypingStatus();

        clearTimeout(this.typingTimers[payload.user_id]);
        this.typingTimers[payload.user_id] = setTimeout(() => {
            this.typingUsers.delete(payload.user_id);
            this._renderTypingStatus();
        }, TYPING_IDLE_MS);
    }

    _renderTypingStatus() {
        const names = Array.from(this.typingUsers.values());

        if (names.length === 0) {
            this._renderStatusForConversation(this.conversations.get(this.currentUuid));

            return;
        }

        this.headerStatusEl.textContent = names.length === 1
            ? `${names[0]} is typing…`
            : `${names.slice(0, -1).join(', ')} and ${names[names.length - 1]} are typing…`;
    }

    _onPresenceChange(userId, payload) {
        this.presence.set(userId, { isOnline: payload.is_online, lastActivity: payload.last_activity_at });

        this.conversations.forEach((conversation) => {
            if (conversation.other_user?.id === userId) {
                conversation.other_user.last_activity_at = payload.last_activity_at;
                conversation.other_user.is_online = payload.is_online;
            }
        });

        const conversation = this.conversations.get(this.currentUuid);
        if (conversation?.other_user?.id === userId) {
            this._renderPresenceStatus(userId);
        }

        this._renderConversationList();
    }

    _scrollToBottom() {
        this.messagesEl.scrollTop = this.messagesEl.scrollHeight;
    }

    /**
     * The chat shell needs a real, bounded height for its message list to
     * scroll internally instead of growing the whole page — but the page
     * chrome above it (topbar, page header) and below it (footer) isn't a
     * fixed size, so a guessed "100vh minus N pixels" CSS value drifts.
     * Measure the actual remaining viewport space instead.
     */
    _syncShellHeight() {
        const shell = document.querySelector('.tracker-chat-shell');

        if (!shell) return;

        const top = shell.getBoundingClientRect().top;
        const bottomReserve = 24;
        const available = Math.round(window.innerHeight - top - bottomReserve);

        shell.style.height = `${Math.max(available, 560)}px`;
    }

    /**
     * Fired from `pagehide` — the page can disappear at any moment after
     * this runs, so a normal axios POST (which can be cancelled mid-flight
     * on unload) isn't reliable here. `fetch` with `keepalive: true` is
     * built for exactly this: the request keeps going after the page is
     * gone, using a small body-size-limited background request.
     */
    _sendLeaveBeacon(callUuid) {
        if (!callUuid) return;

        const xsrfToken = document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1];

        fetch(`/api/v1/calls/${callUuid}/leave`, {
            method: 'POST',
            keepalive: true,
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(xsrfToken ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrfToken) } : {}),
            },
        }).catch(() => {});
    }

    // ---------- Calls (WebRTC) ----------

    _findCallState(uuid) {
        if (this.activeCall?.uuid === uuid) return this.activeCall;
        if (this.heldCall?.uuid === uuid) return this.heldCall;

        return null;
    }

    _channelFor(callState) {
        return callState ? this.channels.get(callState.conversationUuid) : null;
    }

    async _startCall(type) {
        if (!this.currentUuid || this.activeCall || !this._currentConversationCanCommunicate()) return;

        const conversation = this.conversations.get(this.currentUuid);

        if (!conversation) return;

        const isGroup = conversation.type === 'group';
        this._showRingingScreen({
            name: conversation.name,
            avatarUrl: conversation.avatar_url ?? conversation.other_user?.avatar_url,
            statusLabel: 'Calling...',
        });

        try {
            const { data } = await window.axios.post(`/api/v1/conversations/${this.currentUuid}/calls`, { type });
            const call = data.data;

            if (call.status === 'busy') {
                this._logCallInThread({ uuid: call.uuid, type, conversationUuid: this.currentUuid, startedAt: null }, 'busy');
                this._showRingingScreen({
                    name: conversation.name,
                    avatarUrl: conversation.avatar_url ?? conversation.other_user?.avatar_url,
                    statusLabel: 'Busy',
                });
                window.setTimeout(() => this._hideCallOverlay(), 2500);

                return;
            }

            this.activeCall = this._newCallState(call.uuid, type, this.currentUuid, true, isGroup);
            await this._claimPendingSignals(this.activeCall);

            if (isGroup) {
                // The initiator is already "Joined" server-side the moment the
                // room is created (or when joining one already in progress) —
                // there's no ring-then-accept step for them, straight to the grid.
                await this._setupGroupCall(this.activeCall);
                this._showInProgressScreen();
                this._startCallTimer(this.activeCall);
                this._renderHeldBar();

                const existingParticipants = (call.participants || [])
                    .filter((p) => p.status === 'joined' && p.user_id !== this.currentUserId)
                    .map((p) => ({ id: p.user_id, name: p.name }));

                for (const participant of existingParticipants) {
                    await this._connectToExistingPeer(this.activeCall, participant.id, participant.name);
                }

                if (call.status !== 'ongoing') {
                    // Nobody else has joined yet — if nobody does within the
                    // ring window, the room auto-ends server-side and everyone
                    // (including us) tears down via the `.call.ended` broadcast.
                    this.activeCall.timeoutId = window.setTimeout(() => {
                        window.axios.post(`/api/v1/calls/${call.uuid}/missed`).catch(() => {});
                    }, 30000);
                }

                return;
            }

            this._showRingingScreen({
                name: conversation.name,
                avatarUrl: conversation.avatar_url ?? conversation.other_user?.avatar_url,
                statusLabel: 'Ringing...',
            });
            this.activeCall.ringbackStop = this._startRingback();

            this.activeCall.timeoutId = window.setTimeout(() => {
                window.axios.post(`/api/v1/calls/${call.uuid}/missed`).catch(() => {});
                this._endActiveCallAndPromote();
            }, 30000);
        } catch (error) {
            alert(error.response?.data?.message ?? 'Could not start the call.');
            this._hideCallOverlay();
        }
    }

    async _joinAcceptedCall(payload) {
        if (this.activeCall) return;

        if (this.currentUuid !== payload.conversation_uuid) {
            await this._openConversation(payload.conversation_uuid);
        }

        const conversation = this.conversations.get(payload.conversation_uuid);
        const isGroup = conversation?.type === 'group';

        const callState = this._newCallState(payload.call_uuid, payload.type, payload.conversation_uuid, false, isGroup);
        this.activeCall = callState;
        await this._claimPendingSignals(callState);

        try {
            if (isGroup) {
                await this._setupGroupCall(callState);

                // _setupGroupCall's getUserMedia failure already tore this
                // down and cleared this.activeCall — nothing left to show.
                if (this.activeCall !== callState) return;

                this._showInProgressScreen();
                this._startCallTimer(callState);
                this._renderHeldBar();

                const existingParticipants = await this._fetchExistingGroupParticipants(payload.conversation_uuid, payload.call_uuid);

                for (const participant of existingParticipants) {
                    await this._connectToExistingPeer(callState, participant.id, participant.name);
                }

                return;
            }

            await this._setupPeerConnection(callState);

            if (this.activeCall !== callState || !callState.pc) return;

            this._showInProgressScreen();
            this._startCallTimer(callState);
            this._startNetworkQualityMonitor(callState);
            this._renderHeldBar();
        } catch (error) {
            console.error('Failed to join the accepted call.', error);
            alert('Could not connect this call. Please try again.');

            if (this.activeCall === callState) this.activeCall = null;
            this._teardownCallState(callState);
            this._promoteHeldOrTeardown();
        }
    }

    async _fetchExistingGroupParticipants(conversationUuid, callUuid) {
        const { data } = await window.axios.get(`/api/v1/conversations/${conversationUuid}/calls`).catch(() => ({ data: { data: [] } }));
        const call = (data.data || []).find((c) => c.uuid === callUuid);

        if (!call) return [];

        return call.participants
            .filter((p) => p.status === 'joined' && p.user_id !== this.currentUserId)
            .map((p) => ({ id: p.user_id, name: p.name }));
    }

    async _acceptWaitingCall(payload) {
        if (!this.activeCall || this.heldCall) return;

        await this._holdCall(this.activeCall);
        this.heldCall = this.activeCall;
        this.activeCall = null;
        this._renderHeldBar();

        await window.axios.post(`/api/v1/calls/${payload.call_uuid}/accept`).catch(() => {});
        await this._joinAcceptedCall({ call_uuid: payload.call_uuid, conversation_uuid: payload.conversation_uuid, type: payload.type });
    }

    _newCallState(uuid, type, conversationUuid, isInitiator, isGroup = false) {
        return {
            uuid,
            type,
            conversationUuid,
            isInitiator,
            isGroup,
            pc: null,
            localStream: null,
            remoteStream: null,
            screenStream: null,
            lowBandwidth: false,
            isOnHold: false,
            wasMutedBeforeHold: false,
            ringbackStop: null,
            timeoutId: null,
            timerInterval: null,
            statsInterval: null,
            startedAt: null,
            peers: new Map(),
            pendingSignals: [],
        };
    }

    // ---------- Group calls (WebRTC mesh) ----------

    async _setupGroupCall(callState) {
        try {
            callState.localStream = await requestCallMediaStream(callState.type);
        } catch (error) {
            alertCallMediaError();
            if (this.activeCall === callState) this.activeCall = null;
            if (this.heldCall === callState) this.heldCall = null;
            this._promoteHeldOrTeardown();

            return;
        }
    }

    _ensureSelfTile(callState) {
        const grid = document.getElementById('call-group-grid');

        if (!grid || grid.querySelector('[data-tile-user="self"]')) return;

        const isVideo = callState.type === 'video';
        const tile = document.createElement('div');
        tile.className = 'tracker-call-tile';
        tile.dataset.tileUser = 'self';
        tile.innerHTML = `
            <video autoplay playsinline muted class="${isVideo ? '' : 'd-none'}"></video>
            <div class="tracker-call-tile-avatar ${isVideo ? 'd-none' : ''}">${avatarHtml({ name: this.currentUserName })}</div>
            <span class="tracker-call-tile-name">You</span>
        `;
        grid.appendChild(tile);
        tile.querySelector('video').srcObject = callState.localStream;
    }

    _addPeerTile(peerId, peerName) {
        const grid = document.getElementById('call-group-grid');

        if (!grid) return null;

        let tile = grid.querySelector(`[data-tile-user="${peerId}"]`);

        if (tile) return tile;

        tile = document.createElement('div');
        tile.className = 'tracker-call-tile';
        tile.dataset.tileUser = String(peerId);
        tile.innerHTML = `
            <video autoplay playsinline class="d-none"></video>
            <div class="tracker-call-tile-avatar">${avatarHtml({ name: peerName })}</div>
            <span class="tracker-call-tile-name">${escapeHtml(peerName)}</span>
            <span class="tracker-call-tile-muted-badge d-none"><i class="fa-solid fa-microphone-slash"></i></span>
        `;
        grid.appendChild(tile);
        document.getElementById('call-group-waiting-label')?.classList.add('d-none');

        return tile;
    }

    _removePeerTile(peerId) {
        document.getElementById('call-group-grid')?.querySelector(`[data-tile-user="${peerId}"]`)?.remove();
    }

    async _createGroupPeerConnection(callState, peerId, peerName) {
        // Someone's actually joining the room now — the "nobody answered
        // within 30s" missed-call timeout no longer applies.
        window.clearTimeout(callState.timeoutId);

        const iceServers = await this._fetchIceServers();
        const pc = new RTCPeerConnection({ iceServers });
        const tile = this._addPeerTile(peerId, peerName);

        callState.localStream?.getTracks().forEach((track) => pc.addTrack(track, callState.localStream));

        pc.ontrack = (event) => {
            const video = tile?.querySelector('video');

            if (video) {
                video.srcObject = event.streams[0];
                video.classList.remove('d-none');
                tile.querySelector('.tracker-call-tile-avatar')?.classList.add('d-none');
            }
        };

        pc.onicecandidate = (event) => {
            if (event.candidate) {
                this._channelFor(callState)?.whisper('webrtc-ice-candidate', {
                    call_uuid: callState.uuid,
                    to_user_id: peerId,
                    from_user_id: this.currentUserId,
                    candidate: event.candidate,
                });
            }
        };

        pc.oniceconnectionstatechange = () => {
            if (pc.iceConnectionState === 'disconnected') {
                window.setTimeout(() => {
                    if (pc.iceConnectionState === 'disconnected' && typeof pc.restartIce === 'function') {
                        pc.restartIce();
                    }
                }, 3000);
            }
        };

        callState.peers.set(peerId, { pc, name: peerName });

        return pc;
    }

    async _connectToExistingPeer(callState, peerId, peerName) {
        if (callState.peers.has(peerId)) return;

        const pc = await this._createGroupPeerConnection(callState, peerId, peerName);
        const offer = await pc.createOffer();
        await pc.setLocalDescription(offer);

        this._channelFor(callState)?.whisper('webrtc-offer', {
            call_uuid: callState.uuid,
            to_user_id: peerId,
            from_user_id: this.currentUserId,
            from_name: this.currentUserName,
            sdp: pc.localDescription,
        });
    }

    async _onGroupWebrtcOffer(callState, payload) {
        let pc = callState.peers.get(payload.from_user_id)?.pc;

        if (!pc) {
            pc = await this._createGroupPeerConnection(callState, payload.from_user_id, payload.from_name ?? 'Member');
        }

        await pc.setRemoteDescription(new RTCSessionDescription(payload.sdp));
        const answer = await pc.createAnswer();
        await pc.setLocalDescription(answer);

        this._channelFor(callState)?.whisper('webrtc-answer', {
            call_uuid: callState.uuid,
            to_user_id: payload.from_user_id,
            from_user_id: this.currentUserId,
            sdp: pc.localDescription,
        });
    }

    async _onGroupWebrtcAnswer(callState, payload) {
        const pc = callState.peers.get(payload.from_user_id)?.pc;

        if (!pc) return;

        await pc.setRemoteDescription(new RTCSessionDescription(payload.sdp));
    }

    async _onGroupWebrtcIceCandidate(callState, payload) {
        const pc = callState.peers.get(payload.from_user_id)?.pc;

        if (!pc) return;

        try {
            await pc.addIceCandidate(new RTCIceCandidate(payload.candidate));
        } catch (e) {
            // A candidate can arrive before the remote description is set — safe to ignore.
        }
    }

    _updateMuteButtonFromTrack(callState) {
        const muteBtn = document.getElementById('call-mute-btn');
        const audioTrack = callState.localStream?.getAudioTracks()[0];

        if (muteBtn && audioTrack) {
            muteBtn.classList.toggle('active', !audioTrack.enabled);
            muteBtn.innerHTML = `<i class="fa-solid ${audioTrack.enabled ? 'fa-microphone' : 'fa-microphone-slash'}"></i>`;
        }
    }

    async _fetchIceServers() {
        try {
            const { data } = await window.axios.get('/api/v1/webrtc/ice-servers');

            return data.data;
        } catch (error) {
            // A failure here (network hiccup, auth blip) must not silently
            // kill the whole call setup — that's exactly what left callers
            // stuck on "Calling…" forever with no visible error. Fall back
            // to public STUN so the call can still attempt to connect.
            console.error('Failed to fetch ICE servers, falling back to public STUN.', error);

            return [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' },
            ];
        }
    }

    async _setupPeerConnection(callState) {
        const iceServers = await this._fetchIceServers();
        let localStream;

        try {
            localStream = await requestCallMediaStream(callState.type);
        } catch (error) {
            alertCallMediaError();
            this._teardownCallState(callState);
            if (this.activeCall === callState) this.activeCall = null;
            if (this.heldCall === callState) this.heldCall = null;
            this._promoteHeldOrTeardown();

            return;
        }

        callState.localStream = localStream;

        if (this.activeCall === callState) {
            this._attachActiveMedia(callState);
        }

        const pc = new RTCPeerConnection({ iceServers });
        callState.pc = pc;

        localStream.getTracks().forEach((track) => pc.addTrack(track, localStream));

        pc.ontrack = (event) => {
            callState.remoteStream = event.streams[0];

            if (this.activeCall === callState) {
                this.callRemoteVideoEl.srcObject = event.streams[0];
            }
        };

        pc.onicecandidate = (event) => {
            if (event.candidate) {
                this._channelFor(callState)?.whisper('webrtc-ice-candidate', { call_uuid: callState.uuid, candidate: event.candidate });
            }
        };

        pc.oniceconnectionstatechange = () => {
            if (pc.iceConnectionState === 'disconnected') {
                window.setTimeout(() => {
                    if (callState.pc?.iceConnectionState === 'disconnected' && typeof pc.restartIce === 'function') {
                        pc.restartIce();
                    }
                }, 3000);
            }
        };

        await this._drainPendingSignals(callState);
    }

    _attachActiveMedia(callState) {
        if (callState.isGroup) {
            this._ensureSelfTile(callState);
            this._updateMuteButtonFromTrack(callState);

            return;
        }

        const isVideo = callState.type === 'video';

        this.callLocalVideoEl.srcObject = callState.localStream ?? null;
        this.callLocalVideoEl.classList.toggle('d-none', !isVideo);
        this.callRemoteVideoEl.srcObject = callState.remoteStream ?? null;
        document.getElementById('call-video-toggle-btn')?.classList.toggle('d-none', !isVideo);
        document.getElementById('call-screen-share-btn')?.classList.toggle('d-none', !isVideo);

        this._updateMuteButtonFromTrack(callState);
    }

    async _createAndSendOffer(callState) {
        const pc = callState.pc;
        const offer = await pc.createOffer();
        await pc.setLocalDescription(offer);
        this._channelFor(callState)?.whisper('webrtc-offer', { call_uuid: callState.uuid, sdp: pc.localDescription });
    }

    async _onWebrtcOffer(payload) {
        const callState = this._findCallState(payload.call_uuid);

        if (!callState) {
            this._bufferPendingSignal(payload.call_uuid, 'offer', payload);

            return;
        }

        if (callState.isGroup) {
            if (payload.to_user_id !== this.currentUserId) return;

            return this._onGroupWebrtcOffer(callState, payload);
        }

        if (!callState.pc) {
            // The two sides set up their peer connection independently (each
            // waits on its own getUserMedia/permission prompt) — the other
            // side's offer/ICE can easily win that race and arrive before
            // ours exists yet. Buffer instead of dropping it on the floor.
            callState.pendingSignals.push({ kind: 'offer', payload });

            return;
        }

        await this._processWebrtcOffer(callState, payload);
    }

    async _processWebrtcOffer(callState, payload) {
        const pc = callState.pc;
        await pc.setRemoteDescription(new RTCSessionDescription(payload.sdp));
        const answer = await pc.createAnswer();
        await pc.setLocalDescription(answer);
        this._channelFor(callState)?.whisper('webrtc-answer', { call_uuid: callState.uuid, sdp: pc.localDescription });
    }

    async _onWebrtcAnswer(payload) {
        const callState = this._findCallState(payload.call_uuid);

        if (!callState) {
            this._bufferPendingSignal(payload.call_uuid, 'answer', payload);

            return;
        }

        if (callState.isGroup) {
            if (payload.to_user_id !== this.currentUserId) return;

            return this._onGroupWebrtcAnswer(callState, payload);
        }

        if (!callState.pc) {
            callState.pendingSignals.push({ kind: 'answer', payload });

            return;
        }

        await this._processWebrtcAnswer(callState, payload);
    }

    async _processWebrtcAnswer(callState, payload) {
        await callState.pc.setRemoteDescription(new RTCSessionDescription(payload.sdp));
    }

    async _onWebrtcIceCandidate(payload) {
        const callState = this._findCallState(payload.call_uuid);

        if (!callState) {
            this._bufferPendingSignal(payload.call_uuid, 'ice', payload);

            return;
        }

        if (callState.isGroup) {
            if (payload.to_user_id !== this.currentUserId) return;

            return this._onGroupWebrtcIceCandidate(callState, payload);
        }

        if (!callState.pc) {
            callState.pendingSignals.push({ kind: 'ice', payload });

            return;
        }

        await this._processWebrtcIceCandidate(callState, payload);
    }

    async _processWebrtcIceCandidate(callState, payload) {
        try {
            await callState.pc.addIceCandidate(new RTCIceCandidate(payload.candidate));
        } catch (e) {
            // A candidate can arrive before the remote description is set — safe to ignore.
        }
    }

    async _drainPendingSignals(callState) {
        const queued = callState.pendingSignals.splice(0, callState.pendingSignals.length);

        for (const { kind, payload } of queued) {
            if (kind === 'offer') await this._processWebrtcOffer(callState, payload);
            else if (kind === 'answer') await this._processWebrtcAnswer(callState, payload);
            else if (kind === 'ice') await this._processWebrtcIceCandidate(callState, payload);
        }
    }

    _bufferPendingSignal(callUuid, kind, payload) {
        if (!this.pendingCallSignals.has(callUuid)) {
            this.pendingCallSignals.set(callUuid, []);
        }

        this.pendingCallSignals.get(callUuid).push({ kind, payload });
    }

    /**
     * Called the instant a callState is created for a uuid, so any
     * offer/answer/ICE that arrived earlier — while nothing existed yet to
     * even buffer against — gets picked up instead of staying lost forever.
     */
    async _claimPendingSignals(callState) {
        const queued = this.pendingCallSignals.get(callState.uuid);

        if (!queued) return;

        this.pendingCallSignals.delete(callState.uuid);

        if (callState.isGroup) {
            for (const { kind, payload } of queued) {
                if (kind === 'offer') await this._onGroupWebrtcOffer(callState, payload);
                else if (kind === 'answer') await this._onGroupWebrtcAnswer(callState, payload);
                else if (kind === 'ice') await this._onGroupWebrtcIceCandidate(callState, payload);
            }

            return;
        }

        callState.pendingSignals.push(...queued);
    }

    async _onCallAccepted(payload) {
        if (!this.activeCall || this.activeCall.uuid !== payload.call_uuid || this.activeCall.isGroup) return;
        if (!this.activeCall.isInitiator || this.activeCall.pc) return;

        this.activeCall.ringbackStop?.();
        window.clearTimeout(this.activeCall.timeoutId);

        const callState = this.activeCall;

        try {
            await this._setupPeerConnection(callState);

            if (this.activeCall !== callState) return;

            // getUserMedia failure inside _setupPeerConnection already tore
            // the call down and cleared this.activeCall — nothing left to
            // continue with.
            if (!callState.pc) return;

            await this._createAndSendOffer(callState);
            this._showInProgressScreen();
            this._startCallTimer(callState);
            this._startNetworkQualityMonitor(callState);
            this._renderHeldBar();
        } catch (error) {
            console.error('Failed to connect the call after it was accepted.', error);
            alert('The call connected but the connection could not be completed. Please try again.');

            if (this.activeCall === callState) this.activeCall = null;
            this._teardownCallState(callState);
            this._promoteHeldOrTeardown();
        }
    }

    _onCallRejected(payload) {
        if (!this.activeCall || this.activeCall.uuid !== payload.call_uuid) return;

        // A group invite decline doesn't end the room for anyone else —
        // only `.call.ended` (nobody left at all) tears the call down.
        if (this.activeCall.isGroup) return;

        this._logCallInThread(this.activeCall, 'declined');
        this._showRingingScreen({ name: this.callPeerNameEl.textContent, statusLabel: 'Call declined' });
        window.setTimeout(() => this._endActiveCallAndPromote(), 2000);
    }

    _onCallEnded(payload) {
        if (this.heldCall?.uuid === payload.call_uuid) {
            this._logCallInThread(this.heldCall, payload.reason === 'missed' ? 'missed' : null);
            this._teardownCallState(this.heldCall);
            this.heldCall = null;
            this._renderHeldBar();

            return;
        }

        if (!this.activeCall || this.activeCall.uuid !== payload.call_uuid) return;

        this._logCallInThread(this.activeCall, payload.reason === 'missed' ? 'missed' : null);

        const conversation = this.conversations.get(this.activeCall.conversationUuid);
        const label = payload.reason === 'missed' ? 'No answer' : 'Call ended';
        this._showRingingScreen({ name: conversation?.name, statusLabel: label });
        window.setTimeout(() => this._endActiveCallAndPromote(), 1500);
    }

    _onCallParticipantLeft(payload) {
        const callState = this._findCallState(payload.call_uuid);

        if (!callState?.isGroup || !callState.peers.has(payload.user_id)) return;

        callState.peers.get(payload.user_id)?.pc?.close();
        callState.peers.delete(payload.user_id);

        if (callState === this.activeCall) {
            this._removePeerTile(payload.user_id);
            document.getElementById('call-group-waiting-label')?.classList.toggle('d-none', callState.peers.size > 0);
        }
    }

    _onCallParticipantUpdated(payload) {
        const callState = this._findCallState(payload.call_uuid);

        if (!callState || payload.user_id === this.currentUserId) return;

        if (callState !== this.activeCall) return;

        if (callState.isGroup) {
            const tile = document.getElementById('call-group-grid')?.querySelector(`[data-tile-user="${payload.user_id}"]`);
            tile?.querySelector('.tracker-call-tile-muted-badge')?.classList.toggle('d-none', !payload.is_muted);

            return;
        }

        this.callPeerHoldBadgeEl?.classList.toggle('d-none', !payload.is_on_hold);
    }

    async _cancelOutgoingCall() {
        if (!this.activeCall) return;

        const uuid = this.activeCall.uuid;
        const wasAccepted = !!this.activeCall.pc;

        await window.axios.post(`/api/v1/calls/${uuid}/${wasAccepted ? 'leave' : 'missed'}`).catch(() => {});
        this._logCallInThread(this.activeCall, wasAccepted ? null : 'missed');
        this._endActiveCallAndPromote();
    }

    async _hangupCall() {
        if (!this.activeCall) return;

        await window.axios.post(`/api/v1/calls/${this.activeCall.uuid}/leave`).catch(() => {});
        this._logCallInThread(this.activeCall, null);
        this._endActiveCallAndPromote();
    }

    // ---------- Call hold / call waiting ----------

    async _holdCall(callState) {
        if (!callState || callState.isOnHold) return;

        callState.isOnHold = true;
        const track = callState.localStream?.getAudioTracks()[0];
        callState.wasMutedBeforeHold = track ? !track.enabled : false;
        if (track) track.enabled = false;

        await window.axios.patch(`/api/v1/calls/${callState.uuid}`, { is_on_hold: true }).catch(() => {});
    }

    async _resumeCall(callState) {
        if (!callState || !callState.isOnHold) return;

        callState.isOnHold = false;
        const track = callState.localStream?.getAudioTracks()[0];
        if (track) track.enabled = !callState.wasMutedBeforeHold;

        await window.axios.patch(`/api/v1/calls/${callState.uuid}`, { is_on_hold: false }).catch(() => {});
    }

    async _swapCalls() {
        if (!this.heldCall || !this.activeCall) return;

        const newActive = this.heldCall;
        const newHeld = this.activeCall;

        await this._holdCall(newHeld);
        await this._resumeCall(newActive);

        this.activeCall = newActive;
        this.heldCall = newHeld;

        this._attachActiveMedia(this.activeCall);
        this._renderHeldBar();
        this.callPeerHoldBadgeEl?.classList.add('d-none');

        if (this.currentUuid !== this.activeCall.conversationUuid) {
            await this._openConversation(this.activeCall.conversationUuid);
        }
    }

    _renderHeldBar() {
        if (!this.callHeldBarEl) return;

        if (!this.heldCall) {
            this.callHeldBarEl.classList.add('d-none');

            return;
        }

        const conversation = this.conversations.get(this.heldCall.conversationUuid);
        this.callHeldBarNameEl.textContent = `${conversation?.name ?? 'Call'} — on hold`;
        this.callHeldBarEl.classList.remove('d-none');
    }

    _endActiveCallAndPromote() {
        this._teardownCallState(this.activeCall);
        this.activeCall = null;
        this._promoteHeldOrTeardown();
    }

    _promoteHeldOrTeardown() {
        if (this.heldCall) {
            this.activeCall = this.heldCall;
            this.heldCall = null;
            this._resumeCall(this.activeCall);
            this._attachActiveMedia(this.activeCall);
            this._showInProgressScreen();
            this._renderHeldBar();
            this.callPeerHoldBadgeEl?.classList.add('d-none');

            if (this.currentUuid !== this.activeCall.conversationUuid) {
                this._openConversation(this.activeCall.conversationUuid);
            }

            return;
        }

        this._hideCallOverlay();
    }

    _toggleMute() {
        const track = this.activeCall?.localStream?.getAudioTracks()[0];

        if (!track) return;

        track.enabled = !track.enabled;

        const btn = document.getElementById('call-mute-btn');
        btn?.classList.toggle('active', !track.enabled);

        if (btn) btn.innerHTML = `<i class="fa-solid ${track.enabled ? 'fa-microphone' : 'fa-microphone-slash'}"></i>`;

        window.axios.patch(`/api/v1/calls/${this.activeCall.uuid}`, { is_muted: !track.enabled }).catch(() => {});
    }

    _toggleVideo() {
        const track = this.activeCall?.localStream?.getVideoTracks()[0];

        if (!track) return;

        track.enabled = !track.enabled;

        const btn = document.getElementById('call-video-toggle-btn');
        btn?.classList.toggle('active', !track.enabled);

        if (btn) btn.innerHTML = `<i class="fa-solid ${track.enabled ? 'fa-video' : 'fa-video-slash'}"></i>`;

        window.axios.patch(`/api/v1/calls/${this.activeCall.uuid}`, { is_video_enabled: track.enabled }).catch(() => {});
    }

    async _toggleScreenShare() {
        if (!this.activeCall?.pc) return;

        const btn = document.getElementById('call-screen-share-btn');

        if (this.activeCall.screenStream) {
            this.activeCall.screenStream.getTracks().forEach((track) => track.stop());
            this.activeCall.screenStream = null;

            const cameraTrack = this.activeCall.localStream.getVideoTracks()[0];
            const sender = this.activeCall.pc.getSenders().find((s) => s.track?.kind === 'video');

            if (sender && cameraTrack) await sender.replaceTrack(cameraTrack);

            this.callLocalVideoEl.srcObject = this.activeCall.localStream;
            btn?.classList.remove('active');

            return;
        }

        try {
            const screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true });
            this.activeCall.screenStream = screenStream;

            const screenTrack = screenStream.getVideoTracks()[0];
            const sender = this.activeCall.pc.getSenders().find((s) => s.track?.kind === 'video');

            if (sender) await sender.replaceTrack(screenTrack);

            this.callLocalVideoEl.srcObject = screenStream;
            btn?.classList.add('active');

            screenTrack.onended = () => this._toggleScreenShare();
        } catch (e) {
            // User cancelled the screen-share picker — no-op.
        }
    }

    _toggleLowBandwidth() {
        if (!this.activeCall?.pc) return;

        this.activeCall.lowBandwidth = !this.activeCall.lowBandwidth;

        const btn = document.getElementById('call-low-bandwidth-btn');
        btn?.classList.toggle('active', this.activeCall.lowBandwidth);

        const sender = this.activeCall.pc.getSenders().find((s) => s.track?.kind === 'video');

        if (!sender) return;

        const params = sender.getParameters();
        params.encodings = params.encodings?.length ? params.encodings : [{}];
        params.encodings[0].maxBitrate = this.activeCall.lowBandwidth ? 150000 : undefined;
        sender.setParameters(params).catch(() => {});
    }

    _startRingback() {
        const tone = () => {
            try {
                const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                const ctx = new AudioContextClass();
                const oscillator = ctx.createOscillator();
                const gain = ctx.createGain();
                oscillator.type = 'sine';
                oscillator.frequency.value = 420;
                gain.gain.setValueAtTime(0.001, ctx.currentTime);
                gain.gain.linearRampToValueAtTime(0.12, ctx.currentTime + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.9);
                oscillator.connect(gain);
                gain.connect(ctx.destination);
                oscillator.start();
                oscillator.stop(ctx.currentTime + 0.9);
            } catch (e) {
                // Web Audio unsupported/blocked — silently skip the ringback tone.
            }
        };

        tone();
        const interval = window.setInterval(tone, 3000);

        return () => window.clearInterval(interval);
    }

    _startCallTimer(callState) {
        callState.startedAt = Date.now();
        callState.timerInterval = window.setInterval(() => {
            if (this.activeCall !== callState) return;

            const label = this._formatDuration(Date.now() - callState.startedAt);
            this.callInProgressTimerEl.textContent = label;

            if (this.callMinimizedTimerEl) this.callMinimizedTimerEl.textContent = label;

            if (callState.conversationUuid === this.currentUuid) {
                this._renderStatusForConversation(this.conversations.get(this.currentUuid));
            }
        }, 1000);
    }

    _startNetworkQualityMonitor(callState) {
        callState.statsInterval = window.setInterval(async () => {
            if (this.activeCall !== callState) return;

            const pc = callState.pc;

            if (!pc) return;

            try {
                const stats = await pc.getStats();
                let rtt = null;
                let packetsLost = 0;
                let packetsReceived = 0;

                stats.forEach((report) => {
                    if (report.type === 'candidate-pair' && report.state === 'succeeded' && report.currentRoundTripTime !== undefined) {
                        rtt = report.currentRoundTripTime;
                    }

                    if (report.type === 'inbound-rtp' && !report.isRemote) {
                        packetsLost += report.packetsLost ?? 0;
                        packetsReceived += report.packetsReceived ?? 0;
                    }
                });

                const lossRatio = packetsReceived > 0 ? packetsLost / (packetsLost + packetsReceived) : 0;
                let quality = 'Good';
                let statusClass = 'tracker-status-active';

                if ((rtt !== null && rtt > 0.35) || lossRatio > 0.08) {
                    quality = 'Poor';
                    statusClass = 'tracker-status-danger';
                } else if ((rtt !== null && rtt > 0.15) || lossRatio > 0.03) {
                    quality = 'Fair';
                    statusClass = 'tracker-status-pending';
                }

                this.callNetworkQualityEl.textContent = quality;
                this.callNetworkQualityEl.className = `tracker-status-pill ${statusClass}`;
            } catch (e) {
                // getStats() can fail transiently right after connect/teardown — ignore.
            }
        }, 4000);
    }

    _showRingingScreen({ name, avatarUrl, statusLabel }) {
        this.callOverlayEl.classList.remove('d-none');
        this.callRingingScreenEl.classList.remove('d-none');
        this.callInProgressScreenEl.classList.add('d-none');
        this.callStatusLabelEl.textContent = statusLabel;
        this.callPeerNameEl.textContent = name ?? '';
        this.callAvatarEl.innerHTML = avatarHtml({ name, avatar_url: avatarUrl });
        this.callTimerEl.textContent = '';
    }

    _showInProgressScreen() {
        this.callOverlayEl.classList.remove('d-none');
        this.callRingingScreenEl.classList.add('d-none');
        this.callInProgressScreenEl.classList.remove('d-none');

        const callState = this.activeCall;
        const isGroup = !!callState?.isGroup;

        document.getElementById('call-group-grid')?.classList.toggle('d-none', !isGroup);
        this.callRemoteVideoEl.classList.toggle('d-none', isGroup);
        this.callLocalVideoEl.classList.toggle('d-none', isGroup || callState?.type !== 'video');
        document.getElementById('call-screen-share-btn')?.classList.toggle('d-none', isGroup || callState?.type !== 'video');
        document.getElementById('call-video-toggle-btn')?.classList.toggle('d-none', callState?.type !== 'video');

        const waitingLabel = document.getElementById('call-group-waiting-label');

        if (isGroup) {
            this._ensureSelfTile(callState);
            waitingLabel?.classList.toggle('d-none', callState.peers.size > 0);
        } else {
            waitingLabel?.classList.add('d-none');
        }
    }

    _hideCallOverlay() {
        this.callOverlayEl.classList.add('d-none');
        this.callMinimizedBarEl?.classList.add('d-none');
        this.isCallMinimized = false;

        if (this.currentUuid) {
            this._renderStatusForConversation(this.conversations.get(this.currentUuid));
        }
    }

    _minimizeCall() {
        if (!this.activeCall) return;

        this.isCallMinimized = true;
        this.callOverlayEl.classList.add('d-none');

        const conversation = this.conversations.get(this.activeCall.conversationUuid);
        this.callMinimizedNameEl.textContent = conversation?.name ?? 'Call';
        this.callMinimizedAvatarEl.innerHTML = avatarHtml({
            name: conversation?.name,
            avatar_url: conversation?.avatar_url ?? conversation?.other_user?.avatar_url,
        });
        this.callMinimizedTimerEl.textContent = this.activeCall.startedAt
            ? this._formatDuration(Date.now() - this.activeCall.startedAt)
            : '00:00';
        if (this.callMinimizedVideoEl) {
            const showVideo = this.activeCall.type === 'video' && !!this.activeCall.remoteStream;
            this.callMinimizedVideoEl.classList.toggle('d-none', !showVideo);
            this.callMinimizedAvatarEl.classList.toggle('d-none', showVideo);
            this.callMinimizedVideoEl.srcObject = showVideo ? this.activeCall.remoteStream : null;
        }
        this.callMinimizedBarEl?.classList.remove('d-none');
    }

    _bindCallMinimizedDrag() {
        const bar = this.callMinimizedBarEl;
        if (!bar) return;

        let origin = null;
        bar.addEventListener('pointerdown', (event) => {
            if (event.target.closest('button, #call-minimized-hangup')) return;
            const rect = bar.getBoundingClientRect();
            origin = { x: event.clientX - rect.left, y: event.clientY - rect.top };
            bar.setPointerCapture(event.pointerId);
        });
        bar.addEventListener('pointermove', (event) => {
            if (!origin) return;
            const left = Math.max(8, Math.min(window.innerWidth - bar.offsetWidth - 8, event.clientX - origin.x));
            const top = Math.max(8, Math.min(window.innerHeight - bar.offsetHeight - 8, event.clientY - origin.y));
            Object.assign(bar.style, { left: `${left}px`, top: `${top}px`, right: 'auto', bottom: 'auto' });
        });
        bar.addEventListener('pointerup', () => { origin = null; });
        bar.addEventListener('pointercancel', () => { origin = null; });
    }

    _restoreCall() {
        if (!this.activeCall) return;

        this.isCallMinimized = false;
        this.callMinimizedBarEl?.classList.add('d-none');
        if (this.callMinimizedVideoEl) this.callMinimizedVideoEl.srcObject = null;
        this.callOverlayEl.classList.remove('d-none');
    }

    _teardownCallState(callState) {
        if (!callState) return;

        callState.ringbackStop?.();
        window.clearTimeout(callState.timeoutId);
        window.clearInterval(callState.timerInterval);
        window.clearInterval(callState.statsInterval);

        callState.pc?.close();
        callState.localStream?.getTracks().forEach((track) => track.stop());
        callState.screenStream?.getTracks().forEach((track) => track.stop());
        callState.peers.forEach((peer) => peer.pc?.close());
        callState.peers.clear();

        if (this.activeCall === callState) {
            this.callRemoteVideoEl.srcObject = null;
            this.callLocalVideoEl.srcObject = null;
            document.getElementById('call-group-grid')?.replaceChildren();
        }
    }
}

function bubbleRowRemove(uuid, container) {
    container.querySelector(`[data-message-uuid="${uuid}"]`)?.remove();
}

document.addEventListener('DOMContentLoaded', () => {
    const conversationsEl = document.getElementById('chat-conversations-data');

    if (!conversationsEl) return;

    const conversations = JSON.parse(conversationsEl.textContent);
    const contacts = JSON.parse(document.getElementById('chat-contacts-data').textContent);

    new PrivateChat({ conversations, contacts });
});
