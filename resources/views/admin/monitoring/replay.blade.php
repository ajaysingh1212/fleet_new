@extends('layouts.app')

@section('page-eyebrow', 'Phase 4')
@section('page-title', 'Route Replay')

@section('content')
    <div class="card tracker-surface-card mb-4">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-2"><label class="tracker-form-label">User ID</label><input class="form-control" id="replay-user"></div>
                <div class="col-md-2"><label class="tracker-form-label">Preset</label><select class="form-select" id="replay-preset"><option value="today">Today</option><option value="last_7_days">Last 7 Days</option></select></div>
                <div class="col-md-2"><label class="tracker-form-label">Speed</label><select class="form-select" id="replay-speed"><option>1x</option><option>2x</option><option>4x</option><option>8x</option><option>16x</option><option>32x</option></select></div>
                <div class="col-md-2"><button class="btn tracker-primary-btn w-100" id="replay-load">Build Replay</button></div>
            </div>
        </div>
    </div>
    <div class="card tracker-surface-card"><div class="card-body"><pre class="mb-0" id="replay-output">Replay statistics and smoothed points will appear here.</pre></div></div>
    <script>
        document.getElementById('replay-load').addEventListener('click', async () => {
            const params = {preset: document.getElementById('replay-preset').value};
            if (document.getElementById('replay-user').value) params.user_id = document.getElementById('replay-user').value;
            const {data} = await axios.get('/api/v1/replay', {params});
            document.getElementById('replay-output').textContent = JSON.stringify({
                statistics: data.data.statistics,
                playback_speeds: data.data.playback_speeds,
                first_points: data.data.points.slice(0, 10),
            }, null, 2);
        });
    </script>
@endsection
