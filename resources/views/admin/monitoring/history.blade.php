@extends('layouts.app')

@section('page-eyebrow', 'Phase 4')
@section('page-title', 'GPS History & Timeline')

@section('content')
    <div class="card tracker-surface-card mb-4">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-3"><input class="form-control" id="history-user" placeholder="User ID"></div>
                <div class="col-md-3">
                    <select class="form-select" id="history-preset">
                        <option value="today">Today</option>
                        <option value="yesterday">Yesterday</option>
                        <option value="last_7_days">Last 7 Days</option>
                        <option value="last_30_days">Last 30 Days</option>
                    </select>
                </div>
                <div class="col-md-2"><button class="btn tracker-primary-btn w-100" id="history-load">Load</button></div>
            </div>
        </div>
    </div>
    <div class="row g-4">
        <div class="col-lg-7"><div class="card tracker-surface-card"><div class="card-body"><pre class="mb-0" id="history-output">Select filters and load history.</pre></div></div></div>
        <div class="col-lg-5"><div class="card tracker-surface-card"><div class="card-body"><div id="timeline-output">Timeline events will appear here.</div></div></div></div>
    </div>
    <script>
        document.getElementById('history-load').addEventListener('click', async () => {
            const params = {preset: document.getElementById('history-preset').value};
            if (document.getElementById('history-user').value) params.user_id = document.getElementById('history-user').value;
            const [history, timeline] = await Promise.all([
                axios.get('/api/v1/history', {params}),
                axios.get('/api/v1/timeline', {params}),
            ]);
            document.getElementById('history-output').textContent = JSON.stringify(history.data.data.slice(0, 20), null, 2);
            document.getElementById('timeline-output').innerHTML = timeline.data.data.slice(0, 50).map(event => `
                <div class="border-bottom py-2"><strong>${event.title}</strong><div class="text-muted small">${event.occurred_at}</div></div>
            `).join('') || 'No events.';
        });
    </script>
@endsection
