@extends('layouts.app')

@section('page-eyebrow', 'Monitoring')
@section('page-title', 'Geofences')

@push('scripts')
    @vite(['resources/js/geofence-manager.js'])
@endpush

@section('content')
    <script id="geofence-categories-data" type="application/json">{!! json_encode(array_map(fn ($case) => ['value' => $case->value, 'label' => $case->label()], App\Enums\GeofenceCategory::cases())) !!}</script>

    <div class="tracker-geofence-shell">
        <aside class="tracker-geofence-sidebar">
            <div class="tracker-geofence-toolbar">
                <div class="d-flex gap-2 flex-wrap align-items-start">
                    <div class="dropdown">
                        <button class="btn tracker-primary-btn btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fa-solid fa-plus me-1"></i> New Geofence
                        </button>
                        <ul class="dropdown-menu">
                            <li><button class="dropdown-item" type="button" data-draw-type="circle"><i class="fa-solid fa-circle me-2"></i>Circle</button></li>
                            <li><button class="dropdown-item" type="button" data-draw-type="polygon"><i class="fa-solid fa-draw-polygon me-2"></i>Polygon</button></li>
                            <li><button class="dropdown-item" type="button" data-draw-type="rectangle"><i class="fa-regular fa-square me-2"></i>Rectangle</button></li>
                        </ul>
                    </div>
                    <button type="button" class="btn tracker-outline-btn btn-sm" id="geofence-import-btn"><i class="fa-solid fa-file-import me-1"></i> Import</button>
                    <input type="file" id="geofence-import-input" class="d-none" accept="application/json">
                    <button type="button" class="btn tracker-outline-btn btn-sm" id="geofence-export-btn"><i class="fa-solid fa-file-export me-1"></i> Export</button>
                </div>

                <label class="tracker-chat-search-wrap mb-0 mt-2">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="search" class="form-control tracker-chat-search" id="geofence-search" placeholder="Search geofences...">
                </label>

                <div class="d-flex gap-2 mt-2">
                    <select class="form-select form-select-sm" id="geofence-filter-category">
                        <option value="">All categories</option>
                    </select>
                    <select class="form-select form-select-sm" id="geofence-filter-status">
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>
            </div>

            <div class="tracker-geofence-list" id="geofence-list"></div>
            <div class="tracker-empty-state d-none" id="geofence-list-empty">
                <i class="fa-solid fa-draw-polygon"></i>
                <p class="mb-0">No geofences yet. Draw one on the map to get started.</p>
            </div>
        </aside>

        <div class="tracker-geofence-map-wrap">
            <div id="geofence-map" class="tracker-geofence-map"></div>
            <div class="tracker-geofence-draw-hint d-none" id="geofence-draw-hint">
                Draw the shape on the map — the details form will open once you finish.
            </div>
            <div class="tracker-geofence-edit-bar d-none" id="geofence-edit-bar">
                <span>Reshape the geofence, then save.</span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn tracker-secondary-btn btn-sm" id="geofence-edit-cancel">Cancel</button>
                    <button type="button" class="btn tracker-primary-btn btn-sm" id="geofence-edit-save">Save shape</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="geofence-details-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content tracker-modal">
                <div class="modal-header border-0">
                    <h5 class="modal-title" id="geofence-details-title">New Geofence</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="tracker-form-label">Name</label>
                        <input type="text" class="form-control" id="geofence-name-input" maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label class="tracker-form-label">Description</label>
                        <textarea class="form-control" id="geofence-description-input" rows="2" maxlength="2000"></textarea>
                    </div>
                    <div class="row g-2">
                        <div class="col-8">
                            <label class="tracker-form-label">Category</label>
                            <select class="form-select" id="geofence-category-input"></select>
                        </div>
                        <div class="col-4">
                            <label class="tracker-form-label">Color</label>
                            <input type="color" class="form-control form-control-color w-100" id="geofence-color-input" value="#38bdf8">
                        </div>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-6">
                            <label class="tracker-form-label">Min speed (km/h)</label>
                            <input type="number" min="0" max="300" class="form-control" id="geofence-min-speed-input" placeholder="No minimum">
                        </div>
                        <div class="col-6">
                            <label class="tracker-form-label">Max speed (km/h)</label>
                            <input type="number" min="0" max="300" class="form-control" id="geofence-max-speed-input" placeholder="No limit">
                        </div>
                        <div class="col-12">
                            <p class="tracker-card-subtitle mb-0">Speeding inside this zone alerts both the tracked person and whoever tracks them, in realtime.</p>
                        </div>
                    </div>
                    <hr>
                    <h6 class="mb-2">Assign routine while creating</h6>
                    <div class="row g-2" id="geofence-create-assignment-fields">
                        <div class="col-6">
                            <label class="tracker-form-label">Tracked user</label>
                            <select class="form-select form-select-sm" id="geofence-create-user"></select>
                        </div>
                        <div class="col-4">
                            <label class="tracker-form-label">Route label</label>
                            <input type="text" class="form-control form-control-sm" id="geofence-create-route-label" placeholder="Way Home">
                        </div>
                        <div class="col-2">
                            <label class="tracker-form-label">Order</label>
                            <input type="number" min="0" class="form-control form-control-sm" id="geofence-create-sequence" placeholder="1">
                        </div>
                        <div class="col-4">
                            <label class="tracker-form-label">Routine</label>
                            <select class="form-select form-select-sm" id="geofence-create-schedule-type">
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="yearly">Yearly</option>
                                <option value="custom_date">Custom date</option>
                            </select>
                        </div>
                        <div class="col-8 d-none" id="geofence-create-days-wrap">
                            <label class="tracker-form-label" id="geofence-create-days-label">Days</label>
                            <select class="form-select form-select-sm" id="geofence-create-days" multiple size="3"></select>
                        </div>
                        <div class="col-8 d-none" id="geofence-create-date-wrap">
                            <label class="tracker-form-label">Date</label>
                            <input type="date" class="form-control form-control-sm" id="geofence-create-date">
                        </div>
                        <div class="col-6">
                            <label class="tracker-form-label">Expected from</label>
                            <input type="time" class="form-control form-control-sm" id="geofence-create-window-start">
                        </div>
                        <div class="col-6">
                            <label class="tracker-form-label">Expected until</label>
                            <input type="time" class="form-control form-control-sm" id="geofence-create-window-end">
                        </div>
                        <div class="col-12 d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="geofence-create-alert-exit" checked>
                                <label class="form-check-label small" for="geofence-create-alert-exit">Alert both sides on exit</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="geofence-create-alert-missed" checked>
                                <label class="form-check-label small" for="geofence-create-alert-missed">Alert if checkpoint is missed/bypassed</label>
                            </div>
                        </div>
                    </div>
                    <div class="tracker-geofence-modal-status d-none" id="geofence-details-status"></div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn tracker-secondary-btn" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn tracker-primary-btn" id="geofence-details-save">Save</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="geofence-assignment-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content tracker-modal">
                <div class="modal-header border-0">
                    <h5 class="modal-title" id="geofence-assignment-title">Assignments</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="geofence-assignment-list" class="tracker-geofence-assignment-list mb-3"></div>
                    <div class="tracker-empty-state d-none" id="geofence-assignment-empty">
                        <i class="fa-solid fa-user-clock"></i>
                        <p class="mb-0">No one is assigned to this geofence yet.</p>
                    </div>

                    <hr>

                    <h6 class="mb-2">New Assignment</h6>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="tracker-form-label">User</label>
                            <select class="form-select form-select-sm" id="geofence-assignment-user"></select>
                        </div>
                        <div class="col-6">
                            <label class="tracker-form-label">Route label (optional)</label>
                            <input type="text" class="form-control form-control-sm" id="geofence-assignment-route-label" placeholder="e.g. Way Home">
                        </div>
                        <div class="col-4">
                            <label class="tracker-form-label">Schedule</label>
                            <select class="form-select form-select-sm" id="geofence-assignment-schedule-type">
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="yearly">Yearly</option>
                                <option value="custom_date">Custom date</option>
                            </select>
                        </div>
                        <div class="col-8 d-none" id="geofence-assignment-days-wrap">
                            <label class="tracker-form-label" id="geofence-assignment-days-label">Days</label>
                            <select class="form-select form-select-sm" id="geofence-assignment-days" multiple size="3"></select>
                        </div>
                        <div class="col-8 d-none" id="geofence-assignment-date-wrap">
                            <label class="tracker-form-label">Date</label>
                            <input type="date" class="form-control form-control-sm" id="geofence-assignment-date">
                        </div>
                        <div class="col-6">
                            <label class="tracker-form-label">Window start (optional)</label>
                            <input type="time" class="form-control form-control-sm" id="geofence-assignment-window-start">
                        </div>
                        <div class="col-6">
                            <label class="tracker-form-label">Window end (optional)</label>
                            <input type="time" class="form-control form-control-sm" id="geofence-assignment-window-end">
                        </div>
                        <div class="col-12 d-flex gap-3 mt-1">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="geofence-assignment-alert-exit" checked>
                                <label class="form-check-label small" for="geofence-assignment-alert-exit">Alert on exit</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="geofence-assignment-alert-missed" checked>
                                <label class="form-check-label small" for="geofence-assignment-alert-missed">Alert if missed</label>
                            </div>
                        </div>
                    </div>
                    <div class="tracker-geofence-modal-status d-none mt-2" id="geofence-assignment-status"></div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn tracker-primary-btn" id="geofence-assignment-save">Add Assignment</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="geofence-runs-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content tracker-modal">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Compliance History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="geofence-runs-list" class="tracker-geofence-assignment-list"></div>
                    <div class="tracker-empty-state d-none" id="geofence-runs-empty">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        <p class="mb-0">No runs recorded yet.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
