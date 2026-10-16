@extends('layouts.app')
@section('page-title', $gpsDevice->name)
@section('content')
@if(session('gps_gateway_warning'))
<div class="alert alert-warning" role="alert">{{ session('gps_gateway_warning') }}</div>
@endif
<div class="card card-body"><div><a class="btn btn-primary" href="{{ route('admin.gps-devices.edit',$gpsDevice) }}">Edit / activate / deactivate</a> <a class="btn btn-secondary" href="{{ route('admin.gps-devices.index') }}">Dashboard</a></div>
<dl class="row mt-3">@foreach(['identifier','imei','manufacturer','model','protocol','protocol_version','status','configuration_status','configuration_updated_at'] as $key)<dt class="col-sm-4">{{ ucfirst(str_replace('_',' ',$key)) }}</dt><dd class="col-sm-8">{{ $gpsDevice->$key ?? 'Unknown / unsupported' }}</dd>@endforeach</dl>
<h2 class="h5">Recorded configuration</h2><pre>{{ json_encode($gpsDevice->configuration, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
<p>Supported remote commands: none. Configuration is recorded only. Missing telemetry is unknown or unsupported.</p>
@foreach($gpsDevice->details['documents'] ?? [] as $index=>$document)<a href="{{ route('admin.gps-devices.document',[$gpsDevice,$index]) }}">{{ $document['name'] }}</a>@endforeach
<h2 class="h5 mt-3">Installation history</h2><ul>@foreach($assignments as $assignment)<li>{{ $assignment->registration_number }}: {{ $assignment->installed_at }} UTC — {{ $assignment->removed_at ?? 'current' }}</li>@endforeach</ul>
<h2 class="h5">Recent authenticated sessions</h2><ul>@foreach($sessions as $session)<li>{{ $session->remote_ip }} / {{ $session->protocol }} / {{ $session->connected_at }} UTC; last communication {{ $session->last_packet_at }} UTC</li>@endforeach</ul>
<h2 class="h5">Location history</h2><form class="d-flex gap-2" method="GET"><label>From (UTC)<input class="form-control" name="from" type="datetime-local" value="{{ request('from') }}"></label><label>To (UTC)<input class="form-control" name="to" type="datetime-local" value="{{ request('to') }}"></label><button class="btn btn-secondary">Filter</button></form>
<div class="table-responsive"><table class="table"><thead><tr><th>GPS time</th><th>Received</th><th>Latitude</th><th>Longitude</th><th>Speed km/h</th><th>Satellites</th><th>Telemetry</th></tr></thead><tbody>@foreach($locations as $location)<tr><td>{{ $location->recorded_at->timezone(auth()->user()->timezone ?? 'UTC')->format('Y-m-d H:i:s T') }}</td><td>{{ $location->received_at->timezone(auth()->user()->timezone ?? 'UTC')->format('Y-m-d H:i:s T') }}</td><td>{{ $location->latitude }}</td><td>{{ $location->longitude }}</td><td>{{ $location->speed ?? 'Unknown' }}</td><td>{{ $location->satellites ?? 'Unknown' }}</td><td><details><summary>Supported fields</summary><pre>{{ json_encode($location->telemetry, JSON_PRETTY_PRINT) }}</pre></details></td></tr>@endforeach</tbody></table></div>{{ $locations->links() }}</div>
@endsection
