@extends('layouts.app')

@section('page-eyebrow', 'System')
@section('page-title', 'Settings')

@push('styles')
<style>
    /* Scoped settings theme — AdminLTE se clash na ho isliye sab kuch .settings-scope ke andar */
    .settings-scope {
        --st-primary: #7C3AED;
        --st-primary-dark: #6366F1;
        --st-gradient: linear-gradient(135deg, #7C3AED 0%, #6366F1 100%);
        --st-surface: rgba(255, 255, 255, 0.72);
        --st-border: rgba(124, 58, 237, 0.14);
        --st-text: #1E1B2E;
        --st-text-muted: #6B7280;
        --st-radius: 16px;
        font-family: 'Inter', 'Outfit', sans-serif;
    }

    .settings-scope .st-card {
        background: var(--st-surface);
        backdrop-filter: blur(18px);
        -webkit-backdrop-filter: blur(18px);
        border: 1px solid var(--st-border);
        border-radius: var(--st-radius);
        box-shadow: 0 8px 32px rgba(124, 58, 237, 0.08), 0 1px 2px rgba(0,0,0,0.03);
        overflow: hidden;
    }

    .settings-scope .st-card-header {
        background: var(--st-gradient);
        padding: 1.75rem 2rem 4.5rem;
        position: relative;
    }

    .settings-scope .st-card-header h1 {
        font-family: 'Outfit', sans-serif;
        font-weight: 700;
        font-size: 1.5rem;
        color: #fff;
        margin: 0;
    }

    .settings-scope .st-card-header p {
        color: rgba(255,255,255,0.85);
        margin: 0.25rem 0 0;
        font-size: 0.9rem;
    }

    /* Tab pills — header ke neeche overlap karti hui floating strip */
    .settings-scope .st-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        list-style: none;
        margin: -3rem 2rem 0;
        padding: 0.5rem;
        position: relative;
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 10px 30px rgba(30, 27, 46, 0.12);
        z-index: 2;
    }

    .settings-scope .st-tab-btn {
        border: none;
        background: transparent;
        padding: 0.65rem 1.1rem;
        border-radius: 10px;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--st-text-muted);
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .settings-scope .st-tab-btn:hover {
        background: rgba(124, 58, 237, 0.06);
        color: var(--st-primary);
    }

    .settings-scope .st-tab-btn.active {
        background: var(--st-gradient);
        color: #fff;
        box-shadow: 0 4px 12px rgba(124, 58, 237, 0.35);
    }

    .settings-scope .st-tab-btn i { font-size: 0.9rem; }

    .settings-scope .st-body {
        padding: 2rem;
    }

    @media (max-width: 576px) {
        .settings-scope .st-tabs { margin: -2.25rem 1rem 0; }
        .settings-scope .st-body { padding: 1.25rem; }
    }
</style>
@endpush

@section('content')
<div class="settings-scope">
    <div class="st-card">
        <div class="st-card-header">
            <h1><i class="fa-solid fa-sliders me-2"></i>Settings</h1>
            <p>Apna system, branding aur payment configuration yahan se control karein</p>
        </div>

        @php
            $tabs = [
                'site' => ['label' => 'Site & Branding', 'icon' => 'fa-solid fa-building'],
                'system' => ['label' => 'System', 'icon' => 'fa-solid fa-gears'],
                'license' => ['label' => 'License', 'icon' => 'fa-solid fa-id-card'],
                'payments' => ['label' => 'Payment Gateway', 'icon' => 'fa-solid fa-credit-card'],
                'security' => ['label' => 'Security', 'icon' => 'fa-solid fa-shield-halved'],
                'appearance' => ['label' => 'Appearance', 'icon' => 'fa-solid fa-palette'],
            ];
        @endphp

        <ul class="nav st-tabs" role="tablist">
            @foreach ($tabs as $key => $tab)
                <li class="nav-item">
                    <button class="st-tab-btn {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-{{ $key }}" type="button">
                        <i class="{{ $tab['icon'] }}"></i>{{ $tab['label'] }}
                    </button>
                </li>
            @endforeach
        </ul>

        <div class="st-body">
            <div class="tab-content">
                @foreach ($tabs as $key => $tab)
                    <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="tab-{{ $key }}">
                        @include('admin.settings._group-form', ['groupKey' => $key])
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection