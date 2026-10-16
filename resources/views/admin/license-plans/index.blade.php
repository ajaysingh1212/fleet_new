@extends('layouts.app')

@section('page-eyebrow', 'Licensing')
@section('page-title', 'License Plans')

@section('page-actions')
    @can('create', App\Models\LicensePlan::class)
        <a href="{{ route('admin.license-plans.create') }}" class="btn tracker-primary-btn"><i class="fa-solid fa-plus me-2"></i>Add Plan</a>
    @endcan
@endsection

@section('content')
    <div class="card tracker-surface-card">
        <div class="card-body table-responsive">
            <table class="table tracker-table align-middle">
                <thead>
                    <tr>
                        <th>Plan</th>
                        <th>Type</th>
                        <th>Duration</th>
                        <th>Price</th>
                        <th>Renewal Price</th>
                        <th>Free</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($plans as $plan)
                        <tr>
                            <td>
                                <div class="fw-bold">{{ $plan->name }}</div>
                                <div class="small text-muted">{{ \Illuminate\Support\Str::limit($plan->description, 60) }}</div>
                            </td>
                            <td>{{ $plan->type->label() }}</td>
                            <td>{{ $plan->duration_in_days ? $plan->duration_in_days.' days' : 'Lifetime' }}</td>
                            <td><i class="fa-solid fa-indian-rupee-sign me-1" aria-hidden="true"></i>{{ number_format($plan->price, 2) }}</td>
                            <td><i class="fa-solid fa-indian-rupee-sign me-1" aria-hidden="true"></i>{{ number_format($plan->renewal_price, 2) }}</td>
                            <td>{{ $plan->is_free ? 'Yes' : 'No' }}</td>
                            <td>@include('admin.partials.status-pill', ['status' => $plan->status])</td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    @can('update', $plan)
                                        <a href="{{ route('admin.license-plans.edit', $plan) }}" class="btn btn-sm tracker-outline-btn"><i class="fa-solid fa-pen"></i></a>
                                    @endcan
                                    @can('delete', $plan)
                                        <form method="POST" action="{{ route('admin.license-plans.destroy', $plan) }}" data-confirm-delete data-confirm-title="Delete this plan?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm tracker-outline-btn tracker-outline-btn-danger"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="tracker-empty-state"><i class="fa-solid fa-id-card"></i><p class="mb-0">No license plans yet.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($plans->hasPages())
            <div class="card-footer border-0 bg-transparent">{{ $plans->links() }}</div>
        @endif
    </div>
@endsection