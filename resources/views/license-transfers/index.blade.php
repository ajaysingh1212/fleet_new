@extends('layouts.app')

@section('page-eyebrow', 'Licensing')
@section('page-title', 'License Transfer')

@section('content')
    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card tracker-surface-card">
                <div class="card-header border-0 bg-transparent">
                    <h2 class="tracker-card-title mb-1">Transfer an unused license</h2>
                    <p class="tracker-card-subtitle mb-0">Transferred licenses keep their original plan and demo eligibility. Transfer is only available before first use.</p>
                </div>
                <div class="card-body">
                    @if ($isAdmin)
                        <section class="mb-4">
                            <label class="tracker-form-label" for="source-search">License owner</label>
                            <input id="source-search" type="search" class="form-control" placeholder="Search owner by email or phone" autocomplete="off" minlength="3">
                            <div id="source-results" class="list-group mt-2" aria-live="polite"></div>
                            <div id="source-selected" class="mt-3" hidden></div>
                            <input id="source-user-id" type="hidden" value="">
                        </section>
                    @else
                        <div class="tracker-mini-item mb-4">
                            <span>Transferring from</span>
                            <strong>{{ $sourceUser->name }} · {{ $sourceUser->email }}</strong>
                        </div>
                        <input id="source-user-id" type="hidden" value="{{ $sourceUser->id }}">
                    @endif

                    <section class="mb-4" id="recipient-section" @if ($isAdmin) hidden @endif>
                        <label class="tracker-form-label" for="recipient-search">Recipient</label>
                        <input id="recipient-search" type="search" class="form-control" placeholder="Search by email or phone" autocomplete="off" minlength="3" @disabled($isAdmin)>
                        <div id="recipient-results" class="list-group mt-2" aria-live="polite"></div>
                        <div id="recipient-selected" class="mt-3" hidden></div>
                        <input id="recipient-user-id" type="hidden" name="recipient_user_id" form="license-transfer-form" value="">
                    </section>

                    <section class="mb-4" id="licenses-section" @if ($isAdmin) hidden @endif>
                        <label class="tracker-form-label">Unused licenses</label>
                        <div id="license-results" class="list-group"></div>
                    </section>

                    <form id="license-transfer-form" method="POST" action="{{ route('license-transfers.store') }}" data-is-admin="{{ $isAdmin ? '1' : '0' }}">
                        @csrf
                        @if ($isAdmin)
                            <input id="source-user-id-form" type="hidden" name="source_user_id" value="">
                        @endif
                        <input id="license-uuid" type="hidden" name="license_uuid" value="">
                        @error('recipient_user_id')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
                        @error('license_uuid')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
                        @error('license')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
                        @error('recipient')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

                        @unless ($isAdmin)
                            <div id="password-step" class="mb-3" hidden>
                                <label class="tracker-form-label" for="transfer-password">Confirm with your password</label>
                                <input id="transfer-password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="current-password" disabled>
                                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        @endunless

                        <button id="transfer-submit" type="submit" class="btn tracker-primary-btn" disabled>
                            <i class="fa-solid fa-right-left me-2"></i>{{ $isAdmin ? 'Confirm License Transfer' : 'Continue' }}
                        </button>
                        @if ($isAdmin)
                            <a href="{{ route('admin.user-licenses.create') }}" class="btn tracker-outline-btn ms-2">Grant a new license</a>
                        @endif
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card tracker-surface-card">
                <div class="card-header border-0 bg-transparent"><h3 class="tracker-card-title mb-0">Recent Transfers</h3></div>
                <div class="card-body">
                    @forelse ($transferHistory as $transfer)
                        <div class="tracker-mini-item mb-2">
                            <span>{{ $transfer->transferred_at?->format('d M Y') }}</span>
                            <strong>{{ $transfer->license?->plan?->name }} to {{ $transfer->toUser?->name }}</strong>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No transfers yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            (() => {
                const isAdmin = @json($isAdmin);
                const sourceInput = document.getElementById('source-user-id');
                const recipientInput = document.getElementById('recipient-user-id');
                const licenseInput = document.getElementById('license-uuid');
                const recipientSearch = document.getElementById('recipient-search');
                const recipientSection = document.getElementById('recipient-section');
                const licenseSection = document.getElementById('licenses-section');
                const licenseResults = document.getElementById('license-results');
                const transferForm = document.getElementById('license-transfer-form');
                const submitButton = document.getElementById('transfer-submit');
                let selectedRecipient = null;
                let sourceTimer;
                let recipientTimer;

                const addText = (parent, tag, text, className = '') => {
                    const element = document.createElement(tag);
                    element.textContent = text ?? '';
                    if (className) element.className = className;
                    parent.appendChild(element);
                    return element;
                };

                const userCard = (user, onSelect, disabled = false) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'list-group-item list-group-item-action';
                    button.disabled = disabled;
                    addText(button, 'strong', user.name);
                    addText(button, 'div', `${user.email}${user.phone ? ` · ${user.phone}` : ''}`, 'small text-muted');
                    addText(button, 'div', [user.department, user.designation, user.company].filter(Boolean).join(' · ') || 'No additional profile details', 'small text-muted mt-1');
                    const status = user.premium ? 'Premium license already assigned' : user.free_demo_used ? 'Free demo license already received' : user.license_count ? 'Has no current premium license' : 'No license assigned';
                    addText(button, 'span', status, `badge mt-2 ${user.premium ? 'text-bg-primary' : user.license_count ? 'text-bg-info' : 'text-bg-secondary'}`);
                    if (disabled) addText(button, 'span', ' · Cannot receive another license', 'small text-danger ms-2');
                    button.addEventListener('click', () => onSelect(user));
                    return button;
                };

                const loadLicenses = async () => {
                    licenseResults.replaceChildren();
                    licenseInput.value = '';
                    submitButton.disabled = true;
                    if (!sourceInput.value) return;
                    const url = new URL(@json(route('license-transfers.available')), window.location.origin);
                    if (isAdmin) url.searchParams.set('source_user_id', sourceInput.value);
                    const response = await fetch(url, { headers: { Accept: 'application/json' } });
                    const licenses = await response.json();
                    licenseSection.hidden = false;
                    if (!licenses.length) {
                        addText(licenseResults, 'div', 'This account has no unused licenses available.', 'text-muted py-2');
                        return;
                    }
                    licenses.forEach((license) => {
                        const label = document.createElement('label');
                        label.className = 'list-group-item d-flex align-items-start gap-2';
                        const radio = document.createElement('input');
                        radio.type = 'radio';
                        radio.name = 'transfer-license-choice';
                        radio.value = license.uuid;
                        radio.className = 'form-check-input mt-1';
                        radio.addEventListener('change', () => {
                            licenseInput.value = license.uuid;
                            submitButton.disabled = !selectedRecipient;
                        });
                        label.appendChild(radio);
                        const details = document.createElement('span');
                        addText(details, 'strong', `${license.plan} · ${license.type}`);
                        addText(details, 'span', `License ${license.number} · ${license.duration_days ?? 'Lifetime'}${license.duration_days ? ' days' : ''}`, 'd-block small text-muted');
                        if (license.is_demo) addText(details, 'span', 'Free demo license', 'badge text-bg-info mt-1');
                        label.appendChild(details);
                        licenseResults.appendChild(label);
                    });
                };

                const selectRecipient = (user) => {
                    selectedRecipient = user;
                    recipientInput.value = user.id;
                    const card = document.getElementById('recipient-selected');
                    card.replaceChildren();
                    card.hidden = false;
                    card.className = 'alert alert-info mb-0';
                    addText(card, 'strong', user.name);
                    addText(card, 'div', `${user.email}${user.phone ? ` · ${user.phone}` : ''}`);
                    addText(card, 'div', user.premium ? 'Premium license already assigned' : user.license_count ? 'Demo license already claimed' : 'No license assigned yet');
                    if (isAdmin) addText(card, 'div', [user.department, user.designation, user.company].filter(Boolean).join(' · '));
                    document.getElementById('recipient-results').replaceChildren();
                    submitButton.disabled = !licenseInput.value;
                };

                const searchUsers = async (input, results, mode) => {
                    const q = input.value.trim();
                    results.replaceChildren();
                    if (q.length < 3) return;
                    const url = new URL(@json(route('license-transfers.search-users')), window.location.origin);
                    url.searchParams.set('q', q);
                    url.searchParams.set('mode', mode);
                    if (mode === 'recipient' && isAdmin) url.searchParams.set('source_user_id', sourceInput.value);
                    const response = await fetch(url, { headers: { Accept: 'application/json' } });
                    const users = await response.json();
                    if (!users.length) addText(results, 'div', 'No matching users found.', 'list-group-item text-muted');
                    users.forEach((user) => {
                        if (mode === 'source') {
                            userCard(user, (selected) => {
                                sourceInput.value = selected.id;
                                document.getElementById('source-user-id-form').value = selected.id;
                                const card = document.getElementById('source-selected');
                                card.replaceChildren();
                                card.hidden = false;
                                card.className = 'alert alert-secondary';
                                addText(card, 'strong', `${selected.name} · ${selected.email}`);
                                addText(card, 'div', `${selected.available_license_count} unused licenses available`);
                                results.replaceChildren();
                                recipientSection.hidden = false;
                                recipientSearch.disabled = false;
                                loadLicenses();
                            }).addEventListener('click', (event) => event.preventDefault());
                        } else {
                            const cannotReceive = !isAdmin && user.premium;
                            results.appendChild(userCard(user, selectRecipient, cannotReceive));
                        }
                    });
                };

                if (isAdmin) {
                    const sourceSearch = document.getElementById('source-search');
                    sourceSearch.addEventListener('input', () => {
                        clearTimeout(sourceTimer);
                        sourceTimer = setTimeout(() => searchUsers(sourceSearch, document.getElementById('source-results'), 'source'), 250);
                    });
                } else {
                    loadLicenses();
                }

                recipientSearch.addEventListener('input', () => {
                    clearTimeout(recipientTimer);
                    recipientTimer = setTimeout(() => searchUsers(recipientSearch, document.getElementById('recipient-results'), 'recipient'), 250);
                });

                let confirmed = false;
                transferForm.addEventListener('submit', (event) => {
                    if (!licenseInput.value || !recipientInput.value) {
                        event.preventDefault();
                        return;
                    }
                    if (confirmed) return;
                    event.preventDefault();
                    if (!window.confirm('Transfer this unused license to the selected user? This cannot be undone.')) return;

                    if (isAdmin) {
                        confirmed = true;
                        transferForm.requestSubmit();
                        return;
                    }

                    confirmed = true;
                    const passwordStep = document.getElementById('password-step');
                    const password = document.getElementById('transfer-password');
                    passwordStep.hidden = false;
                    password.disabled = false;
                    password.required = true;
                    submitButton.innerHTML = '<i class="fa-solid fa-shield-keyhole me-2"></i>Confirm Transfer';
                    password.focus();
                });
            })();
        </script>
    @endpush
@endsection