@extends('layouts.app')

@section('page-eyebrow', 'Realtime')
@section('page-title', 'Private Chats')

@push('scripts')
    @vite(['resources/js/private-chat.js'])
@endpush

@section('content')
    <script id="chat-conversations-data" type="application/json">{!! json_encode($conversations) !!}</script>
    <script id="chat-contacts-data" type="application/json">{!! json_encode($contacts) !!}</script>
    <script>
        window.__trackerOpenConversationUuid = @json(request('conversation'));
        window.__trackerOpenCallUuid = @json(request('call'));
        window.__trackerOpenCallType = @json(request('call_type'));
    </script>

    <div class="tracker-chat-shell">
        <aside class="tracker-chat-sidebar" id="chat-sidebar">
            <div class="tracker-chat-sidebar-hero">
                <div>
                    <span class="tracker-chat-sidebar-kicker">Messaging Hub</span>
                    <h3 class="tracker-card-title mb-1">Private Chats</h3>
                    <p class="tracker-card-subtitle mb-0">Faster, clearer, live team conversations.</p>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn tracker-primary-btn btn-sm" data-bs-toggle="modal" data-bs-target="#new-chat-modal">
                        <i class="fa-solid fa-plus me-1"></i> New
                    </button>
                    <button type="button" class="btn tracker-outline-btn btn-sm" id="new-group-btn">
                        <i class="fa-solid fa-user-group me-1"></i> Group
                    </button>
                </div>
            </div>

            <div class="tracker-chat-toolbar">
                <label class="tracker-chat-search-wrap mb-0">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="search" class="form-control tracker-chat-search" id="chat-conversation-search" placeholder="Search conversations...">
                </label>
            </div>

            <div id="chat-conversation-list" class="tracker-chat-list"></div>
            <div id="chat-conversation-empty" class="tracker-empty-state d-none">
                <i class="fa-solid fa-comments"></i>
                <p class="mb-0">No conversations yet. Start one with someone you track.</p>
            </div>
        </aside>

        <section class="tracker-chat-stage">
            <div class="card tracker-surface-card tracker-chat-window">
                <div id="chat-window-empty" class="tracker-empty-state">
                    <i class="fa-solid fa-comment-dots"></i>
                    <p class="mb-0">Select a conversation to start chatting.</p>
                </div>

                <div id="chat-window" class="d-none d-flex flex-column h-100">
                    <div class="tracker-chat-header">
                        <button type="button" class="btn tracker-chat-mobile-back" id="chat-mobile-back" title="Back to conversations">
                            <i class="fa-solid fa-arrow-left"></i>
                        </button>
                        <div class="tracker-avatar-sm" id="chat-header-avatar"></div>
                        <div class="tracker-chat-header-copy flex-grow-1">
                            <strong id="chat-header-name"></strong>
                            <span id="chat-header-status" class="text-muted small"></span>
                        </div>
                        <button type="button" class="btn tracker-chat-action-btn d-none" id="chat-voice-call-btn" title="Voice call">
                            <i class="fa-solid fa-phone"></i>
                        </button>
                        <button type="button" class="btn tracker-chat-action-btn d-none" id="chat-video-call-btn" title="Video call">
                            <i class="fa-solid fa-video"></i>
                        </button>
                        <button type="button" class="btn tracker-chat-action-btn d-none" id="chat-call-history-btn" title="Call history">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                        </button>
                        <button type="button" class="btn tracker-chat-action-btn" id="chat-files-btn" title="Shared media">
                            <i class="fa-solid fa-folder-open"></i>
                        </button>
                        <button type="button" class="btn tracker-chat-action-btn d-none" id="chat-profile-info-btn" title="Profile info">
                            <i class="fa-solid fa-id-card"></i>
                        </button>
                        <button type="button" class="btn tracker-chat-action-btn d-none" id="chat-group-info-btn" title="Group info">
                            <i class="fa-solid fa-circle-info"></i>
                        </button>
                    </div>

                    <div class="tracker-chat-message-stage">
                        <div class="tracker-chat-tools">
                            <label class="tracker-chat-inline-search mb-0">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="search" class="form-control" id="chat-message-search" placeholder="Search messages...">
                            </label>
                            <button type="button" class="btn tracker-chat-filter-btn" id="chat-filter-pinned">
                                <i class="fa-solid fa-thumbtack me-1"></i>Pinned
                            </button>
                            <button type="button" class="btn tracker-chat-filter-btn" id="chat-filter-starred">
                                <i class="fa-solid fa-star me-1"></i>Starred
                            </button>
                        </div>
                        <div id="chat-messages" class="tracker-chat-messages"></div>
                    </div>

                    <div id="chat-reply-bar" class="tracker-chat-context-bar d-none">
                        <div>
                            <span class="tracker-chat-context-label" id="chat-reply-label"></span>
                            <p class="mb-0 small text-truncate" id="chat-reply-body"></p>
                        </div>
                        <button type="button" class="btn-close" id="chat-reply-cancel" aria-label="Cancel"></button>
                    </div>

                    <div id="chat-edit-bar" class="tracker-chat-context-bar d-none">
                        <div>
                            <span class="tracker-chat-context-label">Editing message</span>
                        </div>
                        <button type="button" class="btn-close" id="chat-edit-cancel" aria-label="Cancel"></button>
                    </div>

                    <div id="chat-upload-progress" class="tracker-chat-upload-progress d-none">
                        <div class="progress flex-grow-1">
                            <div class="progress-bar" id="chat-upload-progress-bar" style="width: 0%"></div>
                        </div>
                        <span class="text-muted small" id="chat-upload-progress-label">Uploading...</span>
                    </div>

                    <form id="chat-composer" class="tracker-chat-composer" autocomplete="off">
                        <input type="file" id="chat-attachment-input" class="d-none" accept=".jpg,.jpeg,.png,.gif,.webp,.mp4,.mov,.webm,.avi,.mp3,.wav,.ogg,.m4a,.aac,.pdf,.doc,.docx,.xls,.xlsx,.zip,.txt,.csv">
                        <input type="file" id="chat-photo-input" class="d-none" accept="image/*,video/*">
                        <input type="file" id="chat-document-input" class="d-none" accept=".pdf,.doc,.docx,.xls,.xlsx,.zip,.txt,.csv">
                        <div class="tracker-chat-attach-wrap">
                            <button type="button" class="btn tracker-chat-action-btn" id="chat-attach-btn" title="Attach">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                            <div class="tracker-chat-attach-menu d-none" id="chat-attach-menu">
                                <button type="button" data-share-kind="photo"><i class="fa-solid fa-image"></i><span>Photo / Video</span></button>
                                <button type="button" data-share-kind="document"><i class="fa-solid fa-file-lines"></i><span>Document</span></button>
                                <button type="button" data-share-kind="file"><i class="fa-solid fa-paperclip"></i><span>File</span></button>
                                <button type="button" data-share-kind="current-location"><i class="fa-solid fa-location-crosshairs"></i><span>Current location</span></button>
                                <button type="button" data-share-kind="live-location"><i class="fa-solid fa-route"></i><span>Live location</span></button>
                                <button type="button" data-share-kind="contact"><i class="fa-solid fa-address-book"></i><span>Contact</span></button>
                            </div>
                        </div>
                        <div class="tracker-chat-input-shell">
                            <textarea id="chat-composer-input" class="form-control" rows="1" placeholder="Type a message..." maxlength="10000"></textarea>
                            <button type="button" class="tracker-chat-inline-btn" id="chat-audio-record-btn" title="Record audio"><i class="fa-solid fa-microphone"></i></button>
                            <button type="button" class="tracker-chat-inline-btn" id="chat-video-record-btn" title="Record video"><i class="fa-solid fa-video"></i></button>
                        </div>
                        <button type="submit" class="btn tracker-primary-btn tracker-chat-send-btn">
                            <i class="fa-solid fa-paper-plane"></i>
                        </button>
                    </form>
                </div>
            </div>
        </section>
    </div>

    <div class="modal fade" id="new-chat-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content tracker-modal">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Start a Chat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="chat-contact-list" class="tracker-chat-list"></div>
                    <div id="chat-contact-empty" class="tracker-empty-state d-none">
                        <i class="fa-solid fa-user-group"></i>
                        <p class="mb-0">No connected users yet. Set up a tracking relation first.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="new-group-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content tracker-modal">
                <div class="modal-header border-0">
                    <h5 class="modal-title" id="new-group-modal-title">New Group</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3" id="new-group-name-field">
                        <label class="tracker-form-label" for="new-group-name-input">Group name</label>
                        <input type="text" class="form-control" id="new-group-name-input" maxlength="255" placeholder="e.g. Field Team">
                    </div>
                    <div id="new-group-contact-list" class="tracker-chat-list"></div>
                    <div id="new-group-contact-empty" class="tracker-empty-state d-none">
                        <i class="fa-solid fa-user-group"></i>
                        <p class="mb-0">No eligible connected users to add.</p>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn tracker-primary-btn" id="new-group-submit">Create</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="group-info-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content tracker-modal">
                <div class="modal-header border-0">
                    <h5 class="modal-title" id="group-info-name">Group</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small" id="group-info-description"></p>
                    <div id="group-info-members" class="tracker-chat-list mb-3"></div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn tracker-primary-btn btn-sm d-none" id="group-info-add-member-btn">
                            <i class="fa-solid fa-user-plus me-1"></i> Add Member
                        </button>
                        <button type="button" class="btn tracker-outline-btn btn-sm d-none" id="group-info-rename-btn">
                            <i class="fa-solid fa-pen me-1"></i> Rename
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm d-none" id="group-info-delete-btn">
                            <i class="fa-solid fa-trash me-1"></i> Delete Group
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="group-info-leave-btn">
                            <i class="fa-solid fa-right-from-bracket me-1"></i> Leave Group
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="shared-media-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content tracker-modal">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Shared Media</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav tracker-nav-tabs mb-3" id="shared-media-tabs">
                        <li class="nav-item"><button type="button" class="nav-link active" data-media-type="">All</button></li>
                        <li class="nav-item"><button type="button" class="nav-link" data-media-type="image">Images</button></li>
                        <li class="nav-item"><button type="button" class="nav-link" data-media-type="video">Videos</button></li>
                        <li class="nav-item"><button type="button" class="nav-link" data-media-type="audio">Audio</button></li>
                        <li class="nav-item"><button type="button" class="nav-link" data-media-type="document">Documents</button></li>
                    </ul>
                    <div id="shared-media-grid" class="tracker-shared-media-grid"></div>
                    <div id="shared-media-empty" class="tracker-empty-state d-none">
                        <i class="fa-solid fa-folder-open"></i>
                        <p class="mb-0">No shared files yet.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="call-history-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content tracker-modal">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Call History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="call-history-list" class="tracker-chat-list"></div>
                    <div id="call-history-empty" class="tracker-empty-state d-none">
                        <i class="fa-solid fa-phone"></i>
                        <p class="mb-0">No calls yet in this conversation.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="attachment-preview-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content tracker-modal">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="attachment-preview-stage" class="tracker-attachment-preview-stage"></div>
                    <div class="tracker-attachment-preview-tools">
                        <button type="button" class="tracker-chat-action-btn" id="attachment-preview-rotate-left" title="Rotate left"><i class="fa-solid fa-rotate-left"></i></button>
                        <button type="button" class="tracker-chat-action-btn" id="attachment-preview-rotate-right" title="Rotate right"><i class="fa-solid fa-rotate-right"></i></button>
                        <button type="button" class="tracker-chat-action-btn" id="attachment-preview-draw" title="Draw"><i class="fa-solid fa-pen"></i></button>
                        <input type="color" id="attachment-preview-color" class="tracker-preview-color" value="#ff3b30" title="Color">
                        <button type="button" class="tracker-chat-action-btn" id="attachment-preview-emoji" title="Add emoji"><i class="fa-regular fa-face-smile"></i></button>
                        <button type="button" class="tracker-chat-action-btn" id="attachment-preview-crop" title="Crop square"><i class="fa-solid fa-crop-simple"></i></button>
                        <button type="button" class="tracker-chat-action-btn" id="attachment-preview-reset" title="Reset edits"><i class="fa-solid fa-rotate"></i></button>
                        <button type="button" class="tracker-chat-action-btn" id="attachment-preview-clear" title="Remove"><i class="fa-solid fa-xmark"></i></button>
                        <label class="tracker-view-once-toggle">
                            <input type="checkbox" id="attachment-view-once">
                            <span><i class="fa-solid fa-eye-slash"></i> View once</span>
                        </label>
                    </div>
                    <textarea id="attachment-preview-caption" class="form-control mt-3" rows="2" placeholder="Add a caption..." maxlength="2000"></textarea>
                    <p class="text-muted small mt-2 mb-0">View-once media can be opened only one time by the receiver.</p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn tracker-secondary-btn" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn tracker-primary-btn" id="attachment-preview-send"><i class="fa-solid fa-paper-plane me-1"></i> Send</button>
                </div>
            </div>
        </div>
    </div>

    <div id="chat-lightbox" class="tracker-chat-lightbox d-none">
        <div class="tracker-chat-lightbox-toolbar">
            <button type="button" class="tracker-chat-action-btn" id="lightbox-rotate" title="Rotate"><i class="fa-solid fa-rotate-right"></i></button>
            <button type="button" class="tracker-chat-action-btn" id="lightbox-zoom" title="Zoom"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
            <button type="button" class="tracker-chat-action-btn" id="lightbox-fullscreen" title="Fullscreen"><i class="fa-solid fa-expand"></i></button>
            <a class="tracker-chat-action-btn" id="lightbox-download" title="Download" download><i class="fa-solid fa-download"></i></a>
            <button type="button" class="tracker-chat-action-btn" id="lightbox-delete" title="Delete"><i class="fa-solid fa-trash"></i></button>
            <button type="button" class="tracker-chat-action-btn" id="lightbox-close" title="Close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <button type="button" class="tracker-chat-lightbox-nav tracker-chat-lightbox-prev" id="lightbox-prev"><i class="fa-solid fa-chevron-left"></i></button>
        <img id="lightbox-image" src="" alt="">
        <button type="button" class="tracker-chat-lightbox-nav tracker-chat-lightbox-next" id="lightbox-next"><i class="fa-solid fa-chevron-right"></i></button>
    </div>

    <div class="modal fade" id="live-location-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content tracker-modal">
                <div class="modal-header border-0">
                    <div>
                        <h5 class="modal-title" id="live-location-title">Live Location</h5>
                        <p class="text-muted small mb-0" id="live-location-meta"></p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="live-location-map" class="tracker-live-location-map"></div>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <button type="button" class="btn tracker-secondary-btn" id="live-location-route"><i class="fa-solid fa-route me-1"></i> Propose meeting point</button>
                        <span class="tracker-status-pill" id="live-location-distance">Distance waiting...</span>
                    </div>
                    <div class="tracker-route-proposal-banner d-none" id="live-location-route-banner">
                        <span id="live-location-route-banner-text"></span>
                        <div class="tracker-route-proposal-actions" id="live-location-route-banner-actions"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="live-location-share-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content tracker-modal">
                <div class="modal-header border-0">
                    <div>
                        <h5 class="modal-title">Share Live Location</h5>
                        <p class="text-muted small mb-0">Choose how long this chat can see your live movement.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="tracker-live-duration-grid" id="live-location-duration-options">
                        <button type="button" data-minutes="30">30 minute</button>
                        <button type="button" data-minutes="60" class="active">1 hour</button>
                        <button type="button" data-minutes="300">5 hour</button>
                        <button type="button" data-minutes="480">8 hour</button>
                        <button type="button" data-minutes="1500">25 hour</button>
                        <button type="button" data-minutes="10080">1 week</button>
                        <button type="button" data-minutes="43200">1 month</button>
                        <button type="button" data-minutes="">Permanent</button>
                    </div>
                    <div class="tracker-live-location-share-status d-none" id="live-location-share-status"></div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn tracker-secondary-btn" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn tracker-primary-btn" id="live-location-share-confirm">
                        <i class="fa-solid fa-location-crosshairs me-1"></i> Share
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="call-overlay" class="tracker-call-overlay d-none">
        <div class="tracker-call-screen" id="call-ringing-screen">
            <span class="tracker-call-kicker" id="call-status-label">Calling…</span>
            <div class="tracker-call-avatar" id="call-avatar"></div>
            <h3 class="tracker-call-name" id="call-peer-name"></h3>
            <p class="tracker-call-status" id="call-timer">00:00</p>
            <div class="tracker-call-actions">
                <button type="button" class="tracker-call-btn tracker-call-btn-decline" id="call-cancel-btn" title="End call">
                    <i class="fa-solid fa-phone-slash"></i>
                </button>
            </div>
        </div>

        <div class="tracker-call-in-progress d-none" id="call-in-progress-screen">
            <video id="call-remote-video" autoplay playsinline></video>
            <video id="call-local-video" autoplay playsinline muted></video>

            <div id="call-group-grid" class="tracker-call-group-grid d-none"></div>

            <button type="button" class="tracker-call-minimize-btn" id="call-minimize-btn" title="Minimize"><i class="fa-solid fa-chevron-down"></i></button>

            <div class="tracker-call-hud">
                <span id="call-network-quality" class="tracker-status-pill tracker-status-active">Good</span>
                <span id="call-in-progress-timer">00:00</span>
                <span id="call-peer-hold-badge" class="tracker-status-pill tracker-status-pending d-none">On hold</span>
                <span id="call-group-waiting-label" class="tracker-status-pill d-none">Waiting for others to join…</span>
            </div>

            <button type="button" class="tracker-call-held-bar d-none" id="call-held-bar" title="Switch back to this call">
                <i class="fa-solid fa-pause"></i>
                <span id="call-held-bar-name">On hold</span>
                <span class="tracker-call-held-bar-action">Tap to switch</span>
                <span class="tracker-call-held-bar-end" id="call-held-bar-end" title="End this call"><i class="fa-solid fa-phone-slash"></i></span>
            </button>

            <div class="tracker-call-controls">
                <button type="button" class="tracker-call-control-btn" id="call-mute-btn" title="Mute"><i class="fa-solid fa-microphone"></i></button>
                <button type="button" class="tracker-call-control-btn d-none" id="call-video-toggle-btn" title="Camera"><i class="fa-solid fa-video"></i></button>
                <button type="button" class="tracker-call-control-btn d-none" id="call-screen-share-btn" title="Share screen"><i class="fa-solid fa-desktop"></i></button>
                <button type="button" class="tracker-call-control-btn" id="call-low-bandwidth-btn" title="Low bandwidth mode"><i class="fa-solid fa-gauge-simple"></i></button>
                <button type="button" class="tracker-call-control-btn tracker-call-control-btn-end" id="call-hangup-btn" title="End call"><i class="fa-solid fa-phone-slash"></i></button>
            </div>
        </div>
    </div>

    <div id="call-minimized-bar" class="tracker-call-minimized-bar d-none" role="dialog" aria-label="Active call">
        <video id="call-minimized-video" class="tracker-call-minimized-video d-none" autoplay playsinline></video>
        <span class="tracker-call-minimized-avatar" id="call-minimized-avatar"></span>
        <span class="tracker-call-minimized-copy">
            <strong id="call-minimized-name"></strong>
            <span id="call-minimized-timer">00:00</span>
        </span>
        <button type="button" class="tracker-call-minimized-action" id="call-minimized-mute" title="Mute"><i class="fa-solid fa-microphone"></i></button>
        <span class="tracker-call-minimized-hangup" id="call-minimized-hangup" title="End call"><i class="fa-solid fa-phone-slash"></i></span>
    </div>
@endsection
