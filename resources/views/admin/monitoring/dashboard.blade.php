@extends('layouts.app')

@section('page-eyebrow', 'Phase 4')
@section('page-title', 'Analytics Dashboard')

@section('content')
    <div class="row g-3 mb-4">
        @foreach (['Today Distance', 'Working Hours', 'Idle Time', 'Trips', 'Geofence Visits', 'GPS Accuracy'] as $label)
            <div class="col-md-4 col-xl-2">
                <div class="card tracker-surface-card h-100">
                    <div class="card-body">
                        <p class="tracker-card-subtitle mb-1">{{ $label }}</p>
                        <h3 class="tracker-card-title mb-0" data-dashboard-card="{{ Str::slug($label, '_') }}">--</h3>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card tracker-surface-card">
                <div class="card-header border-0 bg-transparent"><h3 class="tracker-card-title mb-0">Movement Trends</h3></div>
                <div class="card-body"><canvas id="movement-chart" height="120"></canvas></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card tracker-surface-card">
                <div class="card-header border-0 bg-transparent"><h3 class="tracker-card-title mb-0">Leaderboards</h3></div>
                <div class="card-body" id="leaderboard-list"></div>
            </div>
        </div>
    </div>

    <script>
        window.addEventListener('DOMContentLoaded', async () => {
            const {data} = await axios.get('/api/v1/dashboard-analytics');
            const cards = data.data.cards;
            const map = {
                today_distance: cards.todays_distance_km + ' km',
                working_hours: cards.working_hours + ' h',
                idle_time: cards.idle_hours + ' h',
                trips: cards.trips,
                geofence_visits: cards.geofence_visits,
                gps_accuracy: (cards.gps_accuracy ?? '--') + ' m',
            };
            Object.entries(map).forEach(([key, value]) => {
                const el = document.querySelector(`[data-dashboard-card="${key}"]`);
                if (el) el.textContent = value;
            });
            document.getElementById('leaderboard-list').innerHTML = data.data.leaderboards.top_distance.map(row => `
                <div class="d-flex justify-content-between border-bottom py-2">
                    <strong>${row.name}</strong><span>${row.distance_km} km</span>
                </div>
            `).join('') || '<div class="tracker-empty-state">No movement data yet.</div>';
        });
    </script>
@endsection
