# Phase 3 Gap Audit

Date: July 28, 2026

## Present in repo

- Private chat foundation exists with realtime messages, replies, forwards, edit/delete, reactions, pin/unpin, attachments, shared media, delivered/read flows, and notification integration.
- Group chat foundation exists with membership roles, add/remove/promote/demote, rename, description updates, and system messages.
- Presence foundation exists with `UserPresenceChanged`, active-session based online detection, and last-activity timestamps.
- Notification Center foundation exists with realtime bell updates, category UI, and browser notifications.
- Live map foundation exists with Blade + Leaflet assets already present.
- Calling foundation exists at backend level with call enums, call models/events/controllers/services, and signaling endpoints.

## Partially implemented

- Typing indicator exists via whisper, but UX still needs richer visual treatment and explicit stop events.
- Last seen exists in chat JS/service layer, but needs broader UI polish and consistency.
- Voice/video calling backend scaffolding exists, but full production WebRTC screens and participant UX are not yet complete.
- Group calling architecture appears started, but the full SFU-ready participant experience is not complete in UI/JS.
- File sharing exists for common files and previews, but dedicated enterprise file manager screens are not complete.
- Dashboard theming exists, but some areas still carry legacy layout assumptions and need consistency passes.

## Missing or not yet complete

- Dedicated voice note recorder with record, pause, resume, cancel, waveform, and playback-speed UX.
- Starred messages.
- Message search UI and scoped long-history search workflow.
- Private-chat contact profile pane with full enterprise contact details/history.
- Full calls dashboard pages: incoming/outgoing/call screen/group screen/participant management.
- Full group dashboard pages: group list, details, settings, permissions, media gallery, call history.
- Full file manager dashboard pages: shared files, images, videos, documents, audio, filters, downloads.
- Strong application-layer encryption/E2EE architecture documentation and implementation.
- Chunked uploads, virtualization for long chat histories, and large-scale performance tuning for 10,000+ concurrent users.
- TURN/STUN operational UI, packet-loss/network indicators, reconnect UX, and adaptive media controls in browser.
- Screen sharing, active speaker layouts, raise hand, and multi-party call tiles in frontend.
- Missing Phase 2/3 enterprise dashboard surfaces listed in prompt such as reports/files/calls-specific workspace pages.
- Complete removal of AdminLTE dependency from layout stack.

## Recommended build order

1. Stabilize current chat UX: typing, last seen, avatars, profile panel, search, pinned/stars.
2. Finish one-to-one WebRTC voice/video UI end-to-end.
3. Add dedicated group pages and call pages.
4. Add voice notes and enterprise file manager.
5. Add group call frontend architecture and SFU-ready abstraction.
6. Remove remaining AdminLTE dependency and unify dashboard UI.
