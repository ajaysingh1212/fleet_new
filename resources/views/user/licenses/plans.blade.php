@extends('layouts.app')

@section('page-eyebrow', 'Licensing')
@section('page-title', 'Choose a License')

@section('page-actions')
    <a href="{{ route('my-licenses.index') }}" class="btn tracker-outline-btn"><i class="fa-solid fa-arrow-left me-2"></i>My Licenses</a>
@endsection

@section('content')

<style>
    /* ---------------------------------------------------------
       License plans — certificate / seal inspired design
       Scoped under .lic-plans so nothing else on the page shifts
    --------------------------------------------------------- */
    .lic-plans {
        --lic-ink:      #0B1424;
        --lic-ink-2:    #101B30;
        --lic-gold:     #D4AF6A;
        --lic-gold-dim: #8C7345;
        --lic-cream:    #F4EFE4;
        --lic-slate:    #97A2B8;
        --lic-teal:     #4FD1C5;
        --lic-radius:   14px;
        font-family: 'Inter', system-ui, sans-serif;
    }

    .lic-plans .lic-intro {
        max-width: 62ch;
        margin: 0 0 1.75rem;
        color: var(--lic-slate);
        font-size: 1.02rem;
        line-height: 1.6;
    }

    .lic-plans .lic-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
    }

    .lic-plans .lic-card {
        position: relative;
        background: linear-gradient(165deg, var(--lic-ink) 0%, var(--lic-ink-2) 100%);
        border: 1px solid rgba(212, 175, 106, 0.18);
        border-radius: var(--lic-radius);
        padding: 1.9rem 1.75rem 1.75rem;
        color: var(--lic-cream);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        opacity: 0;
        transform: translateY(18px) scale(0.985);
        animation: lic-rise 0.6s cubic-bezier(.2,.7,.25,1) forwards;
        transition: transform 0.35s ease, border-color 0.35s ease, box-shadow 0.35s ease;
    }

    .lic-plans .lic-card::before {
        /* fine engraved corner rule, like a printed certificate */
        content: "";
        position: absolute;
        inset: 8px;
        border: 1px solid rgba(212, 175, 106, 0.14);
        border-radius: calc(var(--lic-radius) - 4px);
        pointer-events: none;
    }

    .lic-plans .lic-card:hover {
        transform: translateY(-6px);
        border-color: rgba(212, 175, 106, 0.5);
        box-shadow: 0 18px 40px -18px rgba(212, 175, 106, 0.35);
    }

    .lic-plans .lic-card:nth-child(1) { animation-delay: 0.05s; }
    .lic-plans .lic-card:nth-child(2) { animation-delay: 0.15s; }
    .lic-plans .lic-card:nth-child(3) { animation-delay: 0.25s; }
    .lic-plans .lic-card:nth-child(4) { animation-delay: 0.35s; }
    .lic-plans .lic-card:nth-child(5) { animation-delay: 0.45s; }
    .lic-plans .lic-card:nth-child(6) { animation-delay: 0.55s; }

    @keyframes lic-rise {
        to { opacity: 1; transform: translateY(0) scale(1); }
    }

    .lic-plans .lic-card-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
    }

    .lic-plans .lic-name {
        font-family: 'Fraunces', Georgia, serif;
        font-size: 1.5rem;
        font-weight: 600;
        letter-spacing: 0.2px;
        margin: 0 0 0.3rem;
        color: var(--lic-cream);
    }

    .lic-plans .lic-duration {
        color: var(--lic-slate);
        font-size: 0.88rem;
    }

    .lic-plans .lic-seal {
        flex: 0 0 auto;
        width: 46px;
        height: 46px;
        border-radius: 50%;
        display: grid;
        place-items: center;
        background: radial-gradient(circle at 35% 30%, #6EE7DE, var(--lic-teal) 70%);
        color: #0B1424;
        font-size: 0.62rem;
        font-weight: 700;
        text-align: center;
        line-height: 1.05;
        box-shadow: 0 0 0 3px rgba(79, 209, 197, 0.18);
        transform: rotate(-8deg);
        transition: transform 0.4s ease;
    }

    .lic-plans .lic-card:hover .lic-seal { transform: rotate(4deg) scale(1.05); }

    .lic-plans .lic-desc {
        margin: 1.1rem 0 1.6rem;
        color: #C9D1E0;
        font-size: 0.95rem;
        line-height: 1.55;
    }

    .lic-plans .lic-foot { margin-top: auto; }

    .lic-plans .lic-price-row {
        display: flex;
        align-items: baseline;
        gap: 0.35rem;
        border-top: 1px dashed rgba(212, 175, 106, 0.3);
        padding-top: 1.1rem;
    }

    .lic-plans .lic-price-icon { font-size: 1rem; color: var(--lic-gold); }

    .lic-plans .lic-price {
        font-family: 'Fraunces', Georgia, serif;
        font-size: 2.05rem;
        font-weight: 600;
        color: var(--lic-gold);
        font-variant-numeric: tabular-nums;
    }

    .lic-plans .lic-renewal {
        font-size: 0.82rem;
        color: var(--lic-slate);
        margin: 0.35rem 0 1.1rem;
    }

    .lic-plans .lic-btn {
        position: relative;
        display: block;
        width: 100%;
        text-align: center;
        border: 1px solid var(--lic-gold-dim);
        background: transparent;
        color: var(--lic-gold);
        font-weight: 600;
        letter-spacing: 0.2px;
        border-radius: 9px;
        padding: 0.65rem 1rem;
        overflow: hidden;
        cursor: pointer;
        transition: color 0.3s ease, border-color 0.3s ease;
    }

    .lic-plans .lic-btn::after {
        content: "";
        position: absolute;
        inset: 0;
        background: linear-gradient(100deg, transparent 30%, rgba(212,175,106,0.9) 50%, transparent 70%);
        transform: translateX(-120%);
        transition: transform 0.6s ease;
    }

    .lic-plans .lic-btn:hover {
        color: #0B1424;
        border-color: var(--lic-gold);
    }

    .lic-plans .lic-btn:hover::after { transform: translateX(120%); }

    .lic-plans .lic-btn span { position: relative; z-index: 1; }

    .lic-plans .lic-btn.is-solid {
        background: linear-gradient(135deg, var(--lic-gold) 0%, #C79A4F 100%);
        color: #0B1424;
        border-color: transparent;
    }

    .lic-plans .lic-btn.is-solid:hover { color: #0B1424; }

    .lic-plans .lic-btn:disabled {
        opacity: 0.55;
        cursor: not-allowed;
    }
    .lic-plans .lic-btn:disabled::after { display: none; }

    .lic-plans .lic-badge-free {
        font-size: 0.68rem;
        font-weight: 700;
        color: #0B1424;
        background: var(--lic-teal);
        padding: 0.22rem 0.55rem;
        border-radius: 999px;
    }

    .lic-plans .lic-empty {
        text-align: center;
        padding: 3.5rem 1.5rem;
        color: var(--lic-slate);
        border: 1px dashed rgba(212, 175, 106, 0.3);
        border-radius: var(--lic-radius);
    }

    .lic-plans .lic-empty i {
        font-size: 2rem;
        color: var(--lic-gold);
        display: block;
        margin-bottom: 0.75rem;
        animation: lic-pulse 2.4s ease-in-out infinite;
    }

    @keyframes lic-pulse {
        0%, 100% { opacity: 0.65; transform: scale(1); }
        50% { opacity: 1; transform: scale(1.08); }
    }

    @media (prefers-reduced-motion: reduce) {
        .lic-plans .lic-card { animation: none; opacity: 1; transform: none; }
        .lic-plans .lic-seal, .lic-plans .lic-btn::after, .lic-plans .lic-empty i { animation: none; transition: none; }
    }
</style>

<div class="lic-plans">
    <p class="lic-intro">Every license unlocks the full toolset for the length of its term. Pick the one that matches how long you'll need it — you can always renew or upgrade later.</p>

    <div class="lic-grid">
        @forelse ($plans as $plan)
            <div class="lic-card">
                <div class="lic-card-head">
                    <div>
                        <h2 class="lic-name">{{ $plan->name }}</h2>
                        <div class="lic-duration">{{ $plan->duration_in_days ? $plan->duration_in_days.' days from first use' : 'Lifetime from first use' }}</div>
                    </div>
                    @if ($plan->is_free)
                        <div class="lic-seal">FREE<br>USE</div>
                    @endif
                </div>

                <p class="lic-desc">{{ $plan->description }}</p>

                <div class="lic-foot">
                    <div class="lic-price-row">
                        <i class="fa-solid fa-indian-rupee-sign lic-price-icon" aria-hidden="true"></i>
                        <span class="lic-price" data-lic-price="{{ (float) $plan->price }}">0</span>
                    </div>

                    @if (! $plan->is_free)
                        <div class="lic-renewal">Renews at <i class="fa-solid fa-indian-rupee-sign" aria-hidden="true"></i> {{ number_format($plan->renewal_price, 2) }}</div>
                    @else
                        <div class="lic-renewal">&nbsp;</div>
                    @endif

                    @if ($plan->is_free && $freeLicenseClaimed)
                        <button type="button" class="lic-btn" disabled><span>Free license already claimed</span></button>
                    @else
                        <form method="POST" action="{{ route('my-licenses.purchase') }}">
                            @csrf
                            <input type="hidden" name="license_plan_id" value="{{ $plan->id }}">
                            <button type="submit" class="lic-btn {{ $plan->is_free ? '' : 'is-solid' }}">
                                <span>{{ $plan->is_free ? 'Claim Free License' : 'Buy License' }}</span>
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div class="lic-empty" style="grid-column: 1 / -1;">
                <i class="fa-solid fa-id-card"></i>
                <p class="mb-0">No licenses are available right now.</p>
            </div>
        @endforelse
    </div>
</div>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

<script>
    // Gentle price count-up — plays once on load, respects reduced-motion users
    document.addEventListener('DOMContentLoaded', function () {
        var prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        document.querySelectorAll('[data-lic-price]').forEach(function (el, i) {
            var target = parseFloat(el.getAttribute('data-lic-price')) || 0;

            if (prefersReduced) {
                el.textContent = target.toFixed(2);
                return;
            }

            var duration = 700;
            var delay = 150 + (i * 90);

            setTimeout(function () {
                var start = null;
                function step(ts) {
                    if (!start) start = ts;
                    var progress = Math.min((ts - start) / duration, 1);
                    var eased = 1 - Math.pow(1 - progress, 3);
                    el.textContent = (target * eased).toFixed(2);
                    if (progress < 1) requestAnimationFrame(step);
                }
                requestAnimationFrame(step);
            }, delay);
        });
    });
</script>

@endsection