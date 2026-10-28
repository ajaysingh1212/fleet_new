@if($pendingDevices->total())
<section class="mb-5" aria-label="Vehicles awaiting registration number">
<div class="d-flex align-items-center gap-2 mb-3"><h4 class="fw-bold mb-0">Awaiting vehicle number</h4><span class="badge bg-warning-subtle text-warning-emphasis">{{ $pendingDevices->total() }}</span></div>
<p class="text-muted small">These GPS devices already exist. Add the vehicle number to complete their registration.</p>
<div class="row g-4">@foreach($pendingDevices as $pendingDevice)
<div class="col-md-6 col-xl-4"><article class="card vehicle-card pending-device-card h-100 border-0 shadow-sm rounded-4 overflow-hidden">
<div class="vehicle-card-placeholder"><i class="fa-solid fa-satellite-dish"></i></div>
<div class="card-body p-4"><div class="d-flex justify-content-between gap-2"><span class="badge bg-warning-subtle text-warning-emphasis">Vehicle number pending</span><span class="badge {{ $pendingDevice->is_online ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $pendingDevice->is_online ? 'Online' : 'Offline' }}</span></div>
<h4 class="fw-bold mt-3">{{ $pendingDevice->identifier }}</h4><p class="text-muted">{{ $pendingDevice->name }}</p>
<dl><dt>IMEI</dt><dd>{{ $pendingDevice->identifier }}</dd><dt>Device model</dt><dd>{{ $pendingDevice->model ?: 'Unconfirmed' }}</dd><dt>Protocol</dt><dd>{{ strtoupper($pendingDevice->protocol) }} {{ $pendingDevice->protocol_version }}</dd><dt>Device status</dt><dd>{{ ucfirst($pendingDevice->status) }}</dd><dt>Last packet</dt><dd>{{ $pendingDevice->last_packet_at?->timezone(config('app.timezone'))->format('d M Y, H:i:s') ?: 'Not received' }}</dd></dl>
<div class="d-flex flex-wrap gap-2 border-top pt-3"><a class="btn btn-sm btn-primary" href="{{ route('admin.vehicles.from-device',$pendingDevice) }}">Add vehicle number</a><a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.gps-devices.show',$pendingDevice) }}">Device details</a><a class="btn btn-sm btn-light" href="{{ route('admin.gps-devices.index',['search'=>$pendingDevice->identifier]) }}">Map</a></div>
</div></article></div>
@endforeach</div><div class="mt-3">{{ $pendingDevices->links() }}</div>
</section>
@endif
