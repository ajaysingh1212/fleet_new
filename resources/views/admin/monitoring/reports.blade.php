@extends('layouts.app')

@section('page-eyebrow', 'Phase 4')
@section('page-title', 'Reports & Export Center')

@section('content')
    <div class="card tracker-surface-card">
        <div class="card-header border-0 bg-transparent">
            <h3 class="tracker-card-title mb-1">Generate a report</h3>
            <p class="tracker-card-subtitle mb-0">{{ $reportUsers->count() }} trackable people available to you</p>
        </div>
        <div class="card-body pt-0">
            @if ($reportUsers->isEmpty())
                <div class="tracker-empty-state">
                    <i class="fa-solid fa-file-export"></i>
                    <p class="mb-0">No tracked, licensed people are available for reports yet.</p>
                </div>
            @else
                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="tracker-form-label">Person</label>
                        <select class="form-select" id="report-user">
                            @foreach ($reportUsers as $reportUser)
                                <option value="{{ $reportUser->id }}">{{ $reportUser->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="tracker-form-label">Report</label>
                        <select class="form-select" id="report-type">
                            @foreach (['employee_daily', 'employee_weekly', 'employee_monthly', 'attendance', 'travel', 'mileage', 'idle', 'geofence', 'visit', 'diagnostic', 'communication', 'license_usage', 'organization_summary'] as $type)
                                <option value="{{ $type }}">{{ Str::headline($type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="tracker-form-label">Range</label>
                        <select class="form-select" id="report-preset">
                            <option value="today">Today</option>
                            <option value="yesterday">Yesterday</option>
                            <option value="last_7_days">Last 7 Days</option>
                            <option value="last_30_days">Last 30 Days</option>
                            <option value="custom">Custom</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-none" id="report-custom-range">
                        <label class="tracker-form-label">From / To</label>
                        <div class="d-flex gap-1">
                            <input type="date" class="form-control" id="report-from">
                            <input type="date" class="form-control" id="report-to">
                        </div>
                    </div>
                    <div class="col-md-2 d-grid">
                        <label class="tracker-form-label">&nbsp;</label>
                        <button type="button" class="btn tracker-primary-btn" id="report-generate">
                            <i class="fa-solid fa-bolt"></i> Generate
                        </button>
                    </div>
                    <div class="col-md-2">
                        <label class="tracker-form-label">Min speed</label>
                        <input type="number" min="0" step="0.1" class="form-control" id="report-min-speed">
                    </div>
                    <div class="col-md-2">
                        <label class="tracker-form-label">Max speed</label>
                        <input type="number" min="0" step="0.1" class="form-control" id="report-max-speed">
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="modal fade" id="report-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content tracker-modal">
                <div class="modal-header border-0">
                    <div>
                        <h5 class="modal-title" id="report-modal-title">Report</h5>
                        <p class="tracker-card-subtitle mb-0">
                            <span class="tracker-status-pill tracker-status-active" id="report-live-pill">Live</span>
                            Auto-refreshes every 30s while open
                        </p>
                    </div>
                    <div class="ms-auto d-flex align-items-center gap-2">
                        <a class="btn btn-sm tracker-outline-btn" id="report-export-csv" href="#" target="_blank">CSV</a>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body">
                    <div id="report-chart-root"></div>
                    <div id="report-content-block" class="d-none">
                        <div class="row g-3 mb-3" id="report-cards"></div>
                        <div class="row g-3 mb-3" id="report-menu"></div>

                        <div class="row g-3">
                            <div class="col-lg-6">
                                <h6 class="tracker-card-title">Device Diagnostics — GPS / Internet / Battery History</h6>
                                <div class="tracker-report-table-wrap">
                                    <table class="table table-sm tracker-report-table">
                                        <thead><tr><th>Event</th><th>Time</th><th>Battery</th><th>Network</th></tr></thead>
                                        <tbody id="report-diagnostics"></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <h6 class="tracker-card-title">Geofence Activity</h6>
                                <div class="tracker-report-table-wrap">
                                    <table class="table table-sm tracker-report-table">
                                        <thead><tr><th>Geofence</th><th>Type</th><th>Time</th></tr></thead>
                                        <tbody id="report-geofence"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <pre class="mt-3 mb-0 tracker-report-json" id="report-output">Loading...</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @vite(['resources/js/report-page.js'])
@endpush
