<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>The Last Key</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  :root{
    /* ===== PRIMARY COLORS — customize here ===== */
    --void:#07080a;
    --wall:#0f1114;
    --wall-warm:#181510;
    --metal-1:#26282e;
    --metal-2:#3d4148;
    --metal-hi:#6a6f78;
    --bronze:#8a6a3e;
    --bronze-bright:#d1a35c;
    --cyan:#33d9e8;
    --cyan-soft:rgba(51,217,232,0.28);
    --danger:#c23b2e;
    --danger-soft:rgba(194,59,46,0.3);
    --gold:#f0d99a;
    --gold-soft:rgba(240,217,154,0.4);
    --text-primary:#e7e6e2;
    --text-muted:#888c94;
    --charge:0;
    /* ===== ANIMATION SPEED — customize here ===== */
    --ease:cubic-bezier(.4,0,.2,1);
    --ring-outer-speed:90s;
    --ring-mid-speed:70s;
    --ring-inner-speed:50s;
  }

  *{box-sizing:border-box;}
  html,body{height:100%;}
  body{
    margin:0;
    min-height:100vh;
    overflow:hidden;
    background:#000;
    font-family:'Inter',system-ui,sans-serif;
    color:var(--text-primary);
    cursor:default;
  }

  .cursor-trail{
    position:fixed; top:0; left:0;
    width:22px;height:22px; margin:-11px 0 0 -11px;
    border-radius:50%;
    background:radial-gradient(circle, var(--cyan) 0%, transparent 70%);
    opacity:.5; pointer-events:none; z-index:999;
    transition:opacity .2s var(--ease), width .2s var(--ease), height .2s var(--ease), margin .2s var(--ease);
  }
  .cursor-trail.is-active{width:40px;height:40px;margin:-20px 0 0 -20px;opacity:.8;}

  .flash-black{
    position:fixed; inset:0; background:#000; opacity:0;
    pointer-events:none; z-index:900; transition:opacity .12s linear;
  }
  .flash-black.is-flashing{opacity:1; transition:opacity .05s linear;}

  .flash-red{
    position:fixed; inset:0; z-index:850; pointer-events:none;
    background:radial-gradient(circle at 50% 45%, var(--danger-soft), transparent 65%);
    opacity:0; transition:opacity .3s var(--ease);
  }
  .flash-red.is-visible{opacity:1;}

  .scene{position:relative; width:100%; height:100vh; display:flex; align-items:center; justify-content:center; perspective:1000px;}
  .layer{position:absolute; inset:0; pointer-events:none;}

  .layer-bg{
    background:
      radial-gradient(circle at 50% 40%, var(--wall-warm) 0%, var(--wall) 45%, var(--void) 80%, #000 100%),
      repeating-linear-gradient(100deg, rgba(255,255,255,0.012) 0px, rgba(255,255,255,0.012) 1px, transparent 1px, transparent 40px);
    transition:transform .3s ease-out;
    will-change:transform;
  }

  .layer-rays{
    background:conic-gradient(from 210deg at 50% 32%,
      transparent 0deg, rgba(51,217,232,0.06) 6deg, transparent 16deg,
      transparent 120deg, rgba(209,163,92,0.06) 128deg, transparent 140deg);
    mix-blend-mode:screen;
    animation:rays-spin 40s linear infinite;
    opacity:.7;
  }
  @keyframes rays-spin{to{transform:rotate(360deg);}}

  .fog{position:absolute; bottom:-8%; left:-20%; width:140%; height:32%; background:radial-gradient(ellipse at center, rgba(120,120,130,0.14), transparent 70%); filter:blur(20px);}
  .fog.f1{animation:fog-drift 30s ease-in-out infinite;}
  .fog.f2{bottom:-12%; height:24%; opacity:.6; animation:fog-drift 40s ease-in-out infinite reverse;}
  @keyframes fog-drift{0%,100%{transform:translateX(0);}50%{transform:translateX(5%);}}
  .fog.is-pushed{animation:none !important; transform:translateX(14%) scale(1.1); opacity:.2; transition:transform 1.6s var(--ease), opacity 1.6s var(--ease);}

  .dust{position:absolute; inset:0; overflow:hidden;}
  .mote{position:absolute; bottom:-4%; width:2.5px; height:2.5px; border-radius:50%; background:var(--cyan); opacity:0; animation-name:float-up; animation-timing-function:linear; animation-iteration-count:infinite;}
  @keyframes float-up{0%{opacity:0; transform:translate(0,0);}10%{opacity:.55;}90%{opacity:.3;}100%{opacity:0; transform:translate(var(--drift,20px), -104vh);}}

  /* ================= VAULT ================= */
  .vault-wrap{
    position:relative; z-index:2;
    width:min(84vw, 560px); height:min(84vw, 560px);
    display:flex; align-items:center; justify-content:center;
    transition:transform .35s ease-out, filter .4s var(--ease);
    will-change:transform;
  }
  .vault-wrap.is-dim{filter:brightness(.82);}

  .ring{position:absolute; border-radius:50%; will-change:transform;}
  .ring svg{display:block; width:100%; height:100%;}

  .ring-outer{width:100%; height:100%; animation:spin var(--ring-outer-speed) linear infinite;}
  .ring-mid{width:80%; height:80%; animation:spin var(--ring-mid-speed) linear infinite reverse;}
  .ring-inner{width:58%; height:58%; animation:spin var(--ring-inner-speed) linear infinite;}
  @keyframes spin{to{transform:rotate(360deg);}}
  @keyframes spin-rev{to{transform:rotate(-360deg);}}

  .lock-1-active .ring-outer{animation-play-state:paused;}
  .lock-2-active .ring-mid,
  .lock-2-active .ring-inner{animation-duration:16s;}
  .charging .ring-inner{animation-duration:calc(16s - (var(--charge) * 11s));}
  .rejected .ring-mid{animation-name:spin-rev; animation-duration:2.2s;}

  .scan-sweep{
    position:absolute; inset:6%;
    border-radius:50%;
    background:conic-gradient(from 0deg, transparent 0deg, var(--cyan-soft) 8deg, transparent 20deg);
    opacity:0;
    animation:sweep 3.4s linear infinite;
    pointer-events:none;
  }
  @keyframes sweep{to{transform:rotate(360deg);}}
  .lock-1-active .scan-sweep, .verifying .scan-sweep{opacity:1; animation-duration:1.1s;}
  .rejected .scan-sweep{opacity:1; animation-duration:.8s; background:conic-gradient(from 0deg, transparent 0deg, var(--danger-soft) 10deg, transparent 24deg);}

  .symbol{position:absolute; top:50%; left:50%; width:9px; height:9px; margin:-4.5px 0 0 -4.5px; transform-origin:center;}
  .symbol::before{
    content:""; position:absolute; inset:0;
    background:var(--bronze-bright);
    clip-path:polygon(50% 0%,100% 38%,80% 100%,20% 100%,0% 38%);
    opacity:.3;
    box-shadow:0 0 5px 1px rgba(209,163,92,0.25);
    transition:opacity .4s var(--ease), box-shadow .4s var(--ease), background .4s var(--ease);
  }
  .lock-1-active .symbol::before{opacity:.65;}
  .charging .symbol::before{background:var(--cyan); opacity:calc(.4 + var(--charge) * .5); box-shadow:0 0 calc(6px + var(--charge) * 10px) 1px var(--cyan-soft);}
  .rejected .symbol::before{background:var(--danger); opacity:.85; box-shadow:0 0 8px 2px var(--danger-soft);}

  .pin{position:absolute; top:50%; left:50%; width:3px; height:16px; margin:-8px 0 0 -1.5px; transform-origin:center; background:linear-gradient(180deg, var(--metal-hi), var(--metal-1)); border-radius:1px; transition:transform .5s var(--ease), background .4s var(--ease), box-shadow .4s var(--ease);}
  .lock-1-active .pin{transform:var(--pin-transform) translateY(-3px);}
  .lock-2-active .pin{background:linear-gradient(180deg, var(--cyan), var(--metal-1));}
  .rejected .pin{transform:var(--pin-transform) translateY(4px) scaleY(.7); background:linear-gradient(180deg, var(--danger), var(--metal-1)); box-shadow:0 0 8px 1px var(--danger-soft);}
  .is-open .pin{transform:var(--pin-transform) translateY(-26px); opacity:0;}

  .vault-core{
    position:relative; width:34%; height:34%; border-radius:50%;
    background:
      radial-gradient(circle at 38% 32%, var(--metal-hi), var(--metal-2) 40%, var(--metal-1) 72%, #101216 100%);
    box-shadow:0 0 0 3px rgba(0,0,0,0.5), inset 0 0 24px rgba(0,0,0,0.7), 0 0 30px -6px rgba(0,0,0,0.7);
  }
  .vault-core::before{
    content:"";
    position:absolute; inset:18%;
    border-radius:50%;
    background:radial-gradient(circle, var(--cyan-soft), transparent 70%);
    opacity:calc(.15 + var(--charge) * .55);
    transition:opacity .4s var(--ease);
  }
  .core-pulse{
    position:absolute; inset:-10%;
    border-radius:50%;
    background:radial-gradient(circle, var(--gold), transparent 65%);
    opacity:0;
    pointer-events:none;
  }
  .pulse-once .core-pulse{animation:core-pulse-anim .6s var(--ease);}
  @keyframes core-pulse-anim{0%{opacity:0; transform:scale(.6);}40%{opacity:.9; transform:scale(1.3);}100%{opacity:0; transform:scale(1.8);}}

  .vault-light{
    position:absolute; inset:-20%;
    border-radius:50%;
    background:radial-gradient(circle, var(--gold), rgba(240,217,154,0.3) 40%, transparent 72%);
    opacity:0;
    transition:opacity 1.4s var(--ease);
    pointer-events:none;
  }
  .is-open .vault-light{opacity:1;}
  .is-open .vault-wrap-inner{transform:scale(1.06);}
  .is-open .ring{animation-play-state:paused; transition:transform 1.4s var(--ease), opacity 1.2s var(--ease);}
  .is-open .ring-outer{transform:scale(1.35); opacity:0;}
  .is-open .ring-mid{transform:scale(1.25); opacity:0;}
  .is-open .ring-inner{transform:scale(1.15); opacity:.15;}

  /* ================= CONSOLE ================= */
  .console{
    position:absolute;
    left:50%; bottom:4%;
    transform:translateX(-50%);
    z-index:4;
    width:min(90vw, 320px);
    padding:18px 18px 20px;
    border-radius:8px;
    background:linear-gradient(180deg, rgba(38,40,46,0.86), rgba(15,17,20,0.92));
    border:1px solid rgba(209,163,92,0.22);
    box-shadow:0 24px 50px -20px rgba(0,0,0,0.8), inset 0 1px 0 rgba(255,255,255,0.04);
    backdrop-filter:blur(5px);
    opacity:0;
    transform:translateX(-50%) translateY(14px);
    animation:console-in .8s var(--ease) forwards 3.3s;
  }
  @keyframes console-in{to{opacity:1; transform:translateX(-50%) translateY(0);}}
  .console.is-leaving{opacity:0; transform:translateX(-50%) translateY(-10px) scale(.97); transition:opacity .5s var(--ease), transform .5s var(--ease); pointer-events:none;}
  .console.is-shaking{animation:console-shake .5s var(--ease);}
  @keyframes console-shake{10%,90%{transform:translateX(calc(-50% - 1px));}20%,80%{transform:translateX(calc(-50% + 3px));}30%,50%,70%{transform:translateX(calc(-50% - 6px));}40%,60%{transform:translateX(calc(-50% + 6px));}}

  .console::before, .console::after{
    content:""; position:absolute; width:5px; height:5px; border-radius:50%;
    background:radial-gradient(circle, var(--metal-hi), var(--metal-1));
    box-shadow:0 0 2px rgba(0,0,0,0.6);
  }
  .console::before{top:7px; left:7px;}
  .console::after{top:7px; right:7px;}

  .console-title{
    font-family:'Orbitron',sans-serif; font-weight:700; font-size:11px;
    letter-spacing:.2em; color:var(--bronze-bright); text-align:center;
    margin-bottom:14px;
  }

  .intro-text{position:absolute; left:50%; top:14%; transform:translateX(-50%); z-index:3; text-align:center; height:30px;}
  .intro-text .line{position:absolute; left:50%; top:0; transform:translate(-50%,6px); white-space:nowrap; font-family:'Orbitron',sans-serif; font-weight:600; letter-spacing:.28em; font-size:13px; color:var(--gold); text-shadow:0 0 14px var(--gold-soft); opacity:0;}
  .line-1{animation:line-in-out 2.1s var(--ease) forwards 2.6s;}
  .line-2{animation:line-in-out 2.1s var(--ease) forwards 4.6s;}
  @keyframes line-in-out{0%{opacity:0; transform:translate(-50%,10px);}18%{opacity:1; transform:translate(-50%,0);}82%{opacity:1; transform:translate(-50%,0);}100%{opacity:0; transform:translate(-50%,-8px);}}

  .status-banner{position:relative; z-index:3; text-align:center; max-height:0; opacity:0; overflow:hidden; transition:max-height .3s var(--ease), opacity .25s var(--ease), margin .3s var(--ease);}
  .status-banner.is-visible{max-height:70px; opacity:1; margin-bottom:10px;}
  .status-title{display:block; font-family:'Orbitron',sans-serif; font-weight:700; letter-spacing:.14em; font-size:12.5px;}
  .status-sub{display:block; margin-top:4px; font-size:11.5px; color:var(--text-muted);}
  .status-banner.tone-danger .status-title{color:var(--danger); text-shadow:0 0 10px var(--danger-soft);}
  .status-banner.tone-ok .status-title{color:var(--gold); text-shadow:0 0 10px var(--gold-soft);}
  .status-banner.tone-warn .status-title{color:var(--cyan); text-shadow:0 0 10px var(--cyan-soft);}
  .status-banner.tone-scan .status-title{color:var(--cyan); font-size:10.5px; letter-spacing:.2em;}

  form{display:flex; flex-direction:column; gap:13px;}
  .field label{display:block; font-family:'Orbitron',sans-serif; font-size:9.5px; letter-spacing:.16em; text-transform:uppercase; color:var(--text-muted); margin-bottom:6px;}
  .field-shell{position:relative; display:flex; align-items:center; border:1px solid rgba(209,163,92,0.2); border-radius:5px; background:rgba(0,0,0,0.3); transition:border-color .3s var(--ease), box-shadow .3s var(--ease);}
  .field-shell:focus-within{border-color:var(--bronze-bright); box-shadow:0 0 0 3px rgba(209,163,92,0.15), 0 0 16px -4px var(--gold-soft);}
  #passwordField .field-shell:focus-within{border-color:var(--cyan); box-shadow:0 0 0 3px var(--cyan-soft), 0 0 18px -2px var(--cyan-soft);}
  .field.has-error .field-shell{border-color:var(--danger); box-shadow:0 0 0 3px var(--danger-soft);}
  .field.shake-field{animation:field-shake .4s var(--ease);}
  @keyframes field-shake{20%,80%{transform:translateX(2px);}30%,50%,70%{transform:translateX(-4px);}40%,60%{transform:translateX(4px);}}

  .field-shell input{flex:1; min-width:0; background:transparent; border:none; outline:none; color:var(--text-primary); font-family:'Inter',sans-serif; font-size:13.5px; padding:11px 10px; letter-spacing:.02em;}
  .field-shell input::placeholder{color:#4a4d52;}

  .eye-toggle{flex:none; width:34px; height:34px; border:none; background:transparent; color:var(--text-muted); cursor:pointer; display:flex; align-items:center; justify-content:center; transition:color .2s var(--ease);}
  .eye-toggle:hover{color:var(--gold);}
  .eye-toggle svg{width:15px;height:15px;}
  .eye-toggle .icon-off{display:none;}
  .eye-toggle.is-visible .icon-on{display:none;}
  .eye-toggle.is-visible .icon-off{display:block;}

  .field-msg{font-size:10.5px; color:var(--danger); letter-spacing:.03em; max-height:0; opacity:0; overflow:hidden; transition:max-height .25s var(--ease), opacity .2s var(--ease), margin .25s var(--ease);}
  .field.has-error .field-msg{max-height:22px; opacity:1; margin-top:5px;}

  .switch-btn{
    position:relative; margin-top:2px; height:44px;
    border:1px solid rgba(209,163,92,0.35); border-radius:6px;
    background:linear-gradient(180deg, #2c2d32, #17181b);
    color:var(--gold); font-family:'Orbitron',sans-serif; font-weight:700; font-size:11px; letter-spacing:.16em;
    cursor:pointer; overflow:hidden;
    box-shadow:0 8px 20px -10px rgba(0,0,0,0.7), inset 0 1px 0 rgba(255,255,255,0.05);
    transition:transform .12s var(--ease), box-shadow .3s var(--ease), border-color .3s var(--ease);
  }
  .switch-btn::before{content:""; position:absolute; top:0; left:-60%; width:36%; height:100%; background:linear-gradient(120deg, transparent, rgba(240,217,154,0.3), transparent); transform:skewX(-20deg); transition:left .6s var(--ease);}
  .switch-btn:hover::before{left:120%;}
  .switch-btn:hover{border-color:var(--bronze-bright); box-shadow:0 10px 24px -8px var(--gold-soft), inset 0 1px 0 rgba(255,255,255,0.06);}
  .switch-btn:active, .switch-btn.is-pressed{transform:translateY(2px); box-shadow:inset 0 3px 8px rgba(0,0,0,0.6);}
  .switch-btn:disabled{cursor:default;}
  .switch-btn.is-verifying{color:var(--cyan); border-color:rgba(51,217,232,0.4);}
  .switch-btn.is-granted{color:#12241f; background:linear-gradient(180deg, var(--gold), #c9a24f); border-color:transparent;}

  .forgot-link{display:block; text-align:center; font-size:10.5px; color:var(--text-muted); text-decoration:none; margin-top:2px; transition:color .2s var(--ease);}
  .forgot-link:hover{color:var(--gold);}

  a:focus-visible, button:focus-visible, input:focus-visible{outline:2px solid var(--cyan); outline-offset:2px; border-radius:4px;}

  @media (max-width:600px){
    .vault-wrap{width:78vw; height:78vw;}
    .console{bottom:2%;}
  }

  @media (prefers-reduced-motion: reduce){
    *{animation-duration:.001ms !important; animation-iteration-count:1 !important; transition-duration:.001ms !important;}
    .ring, .layer-rays, .fog, .mote, .scan-sweep{animation:none !important; opacity:.35;}
    .layer-bg, .vault-wrap{transform:none !important;}
    .console{opacity:1; transform:translateX(-50%); animation:none;}
    .intro-text .line{display:none;}
  }
</style>
</head>
<body>

<div class="cursor-trail" id="cursorTrail" aria-hidden="true"></div>
<div class="flash-black" id="flashBlack" aria-hidden="true"></div>
<div class="flash-red" id="flashRed" aria-hidden="true"></div>

<div class="scene" id="scene">
  <div class="layer layer-bg" id="layerBg"></div>
  <div class="layer layer-rays" aria-hidden="true"></div>
  <div class="fog f1" aria-hidden="true" id="fog1"></div>
  <div class="fog f2" aria-hidden="true" id="fog2"></div>
  <div class="dust" id="dustLayer" aria-hidden="true"></div>

  <div class="vault-wrap" id="vaultWrap">
    <div class="vault-wrap-inner" style="position:relative;width:100%;height:100%;display:flex;align-items:center;justify-content:center;transition:transform 1.4s var(--ease);">

      <div class="ring ring-outer" id="ringOuter" aria-hidden="true">
        <svg viewBox="0 0 200 200">
          <circle cx="100" cy="100" r="94" fill="none" stroke="var(--metal-2)" stroke-width="5" stroke-dasharray="3 4.2" opacity="0.55"/>
          <circle cx="100" cy="100" r="86" fill="none" stroke="var(--metal-1)" stroke-width="1" opacity="0.6"/>
        </svg>
      </div>

      <div class="ring ring-mid" id="ringMid" aria-hidden="true">
        <svg viewBox="0 0 200 200">
          <circle cx="100" cy="100" r="92" fill="none" stroke="var(--bronze)" stroke-width="1.4" stroke-dasharray="1 7" opacity="0.6"/>
          <circle cx="100" cy="100" r="70" fill="none" stroke="var(--metal-2)" stroke-width="2" opacity="0.4"/>
        </svg>
      </div>

      <div class="ring ring-inner" id="ringInner" aria-hidden="true">
        <svg viewBox="0 0 200 200">
          <circle cx="100" cy="100" r="90" fill="none" stroke="var(--cyan)" stroke-width="1" stroke-dasharray="10 6" opacity="0.35"/>
        </svg>
      </div>

      <div class="scan-sweep" aria-hidden="true"></div>

      <div class="vault-core" id="vaultCore">
        <span class="core-pulse" aria-hidden="true"></span>
      </div>
      <div class="vault-light" aria-hidden="true"></div>
    </div>
  </div>

  <div class="intro-text" aria-hidden="true">
    <span class="line line-1">THE LAST KEY</span>
    <span class="line line-2">IDENTITY REQUIRED</span>
  </div>

  <div class="console" id="console">
    <div class="console-title">IDENTITY SEQUENCE</div>

    <div class="status-banner" id="statusBanner" role="alert" aria-live="assertive">
      <span class="status-title" id="statusTitle"></span>
      <span class="status-sub" id="statusSub"></span>
    </div>

    <form id="loginForm" novalidate autocomplete="on">
      @csrf

      <div class="field" id="emailField">
        <label for="email">Email</label>
        <div class="field-shell">
          <input type="email" id="email" name="email" placeholder="you@domain.com" value="{{ old('email') }}" required autocomplete="username" autofocus>
        </div>
        <div class="field-msg" id="emailMsg"></div>
      </div>

      <div class="field" id="passwordField">
        <label for="password">Secret Key</label>
        <div class="field-shell">
          <input type="password" id="password" name="password" required autocomplete="current-password">
          <button type="button" class="eye-toggle" id="eyeToggle" aria-label="Show password" aria-pressed="false">
            <svg class="icon-on" viewBox="0 0 24 24" fill="none"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z" stroke="currentColor" stroke-width="1.7"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.7"/></svg>
            <svg class="icon-off" viewBox="0 0 24 24" fill="none"><path d="M3 3l18 18M10.6 10.6a3 3 0 0 0 4.2 4.2M6.5 6.7C4 8.3 2 12 2 12s3.6 7 10 7c1.8 0 3.4-.5 4.7-1.3M17.8 15.4C19.9 13.8 22 12 22 12s-3.6-7-10-7c-.8 0-1.6.1-2.3.3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
          </button>
        </div>
        <div class="field-msg" id="passwordMsg"></div>
      </div>

      <button type="submit" class="switch-btn" id="switchBtn">
        <span id="btnLabel">INITIATE ACCESS</span>
      </button>

      <a href="{{ route('password.request') }}" class="forgot-link">Recover secret key</a>
    </form>
  </div>
</div>

<script>
(function () {
  "use strict";

  /* =========================================================
     CONFIG
     ========================================================= */
  const LOGIN_URL    = "{{ route('login') }}";                     // ← LOGIN_URL
  const REDIRECT_URL = "{{ route('dashboard') ?? '/dashboard' }}"; // ← REDIRECT_URL
  const MIN_CINEMATIC_MS = 1700;   // ← ANIMATION SPEED (min "authenticating" duration)
  const SYMBOL_COUNT_DESKTOP = 8;  // ← PARTICLE COUNT (engraved symbols)
  const SYMBOL_COUNT_MOBILE  = 6;
  const PIN_COUNT_DESKTOP = 12;    // ← PARTICLE COUNT (lock pins)
  const PIN_COUNT_MOBILE  = 8;
  const DUST_COUNT_DESKTOP = 24;   // ← PARTICLE COUNT (floating dust)
  const DUST_COUNT_MOBILE  = 9;

  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const isMobile     = window.matchMedia('(max-width: 640px)').matches || window.matchMedia('(pointer: coarse)').matches;

  const vaultWrap   = document.getElementById('vaultWrap');
  const ringMid     = document.getElementById('ringMid');
  const layerBg     = document.getElementById('layerBg');
  const dustLayer   = document.getElementById('dustLayer');
  const cursorTrail = document.getElementById('cursorTrail');
  const flashBlack  = document.getElementById('flashBlack');
  const flashRed    = document.getElementById('flashRed');
  const fog1        = document.getElementById('fog1');
  const fog2        = document.getElementById('fog2');
  const vaultCore   = document.getElementById('vaultCore');
  const consoleEl   = document.getElementById('console');
  const form        = document.getElementById('loginForm');
  const emailField  = document.getElementById('emailField');
  const passField   = document.getElementById('passwordField');
  const emailInput  = document.getElementById('email');
  const passInput   = document.getElementById('password');
  const emailMsg    = document.getElementById('emailMsg');
  const passMsg     = document.getElementById('passwordMsg');
  const switchBtn   = document.getElementById('switchBtn');
  const btnLabel    = document.getElementById('btnLabel');
  const eyeToggle   = document.getElementById('eyeToggle');
  const statusBanner= document.getElementById('statusBanner');
  const statusTitle = document.getElementById('statusTitle');
  const statusSub   = document.getElementById('statusSub');
  const csrfToken   = document.querySelector('meta[name="csrf-token"]').content;

  let isSubmitting = false;
  let lastPinPulseLen = 0;

  /* ---------- place symbols on mid ring, pins on inner edge ---------- */
  function placeRadial(container, count, radius, className, extraStyleFn) {
    for (let i = 0; i < count; i++) {
      const angle = (360 / count) * i;
      const el = document.createElement('span');
      el.className = className;
      const t = `rotate(${angle}deg) translate(${radius}px)`;
      el.style.setProperty('--pin-transform', t);
      el.style.transform = t + ` rotate(${-angle}deg)`;
      if (extraStyleFn) extraStyleFn(el, angle);
      container.appendChild(el);
    }
  }
  const symbolCount = isMobile ? SYMBOL_COUNT_MOBILE : SYMBOL_COUNT_DESKTOP;
  const pinCount = isMobile ? PIN_COUNT_MOBILE : PIN_COUNT_DESKTOP;
  placeRadial(ringMid, symbolCount, isMobile ? 108 : 148, 'symbol');
  placeRadial(vaultWrap, pinCount, isMobile ? 132 : 182, 'pin', function (el, angle) {
    el.style.setProperty('--pin-transform', `translate(-50%,-50%) rotate(${angle}deg) translateY(-${isMobile ? 132 : 182}px)`);
    el.style.transform = `translate(-50%,-50%) rotate(${angle}deg) translateY(-${isMobile ? 132 : 182}px)`;
    el.style.top = '50%'; el.style.left = '50%'; el.style.margin = '0';
  });

  /* ---------- dust ---------- */
  if (!reduceMotion) {
    const dustCount = isMobile ? DUST_COUNT_MOBILE : DUST_COUNT_DESKTOP;
    for (let i = 0; i < dustCount; i++) {
      const mote = document.createElement('span');
      mote.className = 'mote';
      mote.style.left = (Math.random() * 100) + '%';
      mote.style.setProperty('--drift', (Math.random() * 60 - 30) + 'px');
      mote.style.animationDuration = (10 + Math.random() * 10) + 's';
      mote.style.animationDelay = (-Math.random() * 14) + 's';
      dustLayer.appendChild(mote);
    }
  }

  /* ---------- parallax + cursor ---------- */
  if (!reduceMotion && !isMobile) {
    let tX = 0, tY = 0, cX = 0, cY = 0;
    let cursorX = innerWidth / 2, cursorY = innerHeight / 2, tcX = cursorX, tcY = cursorY;

    window.addEventListener('mousemove', function (e) {
      tX = e.clientX / innerWidth - 0.5;
      tY = e.clientY / innerHeight - 0.5;
      tcX = e.clientX; tcY = e.clientY;
    });

    function tick() {
      cX += (tX - cX) * 0.06;
      cY += (tY - cY) * 0.06;
      layerBg.style.transform = `translate(${cX * -16}px, ${cY * -10}px) scale(1.05)`;
      vaultWrap.style.transform = `translate(${cX * 14}px, ${cY * 9}px)`;
      consoleEl.style.marginLeft = (cX * -6) + 'px';

      cursorX += (tcX - cursorX) * 0.2;
      cursorY += (tcY - cursorY) * 0.2;
      cursorTrail.style.transform = `translate(${cursorX}px, ${cursorY}px)`;
      requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);

    document.querySelectorAll('input, button, a').forEach(function (el) {
      el.addEventListener('mouseenter', () => cursorTrail.classList.add('is-active'));
      el.addEventListener('mouseleave', () => cursorTrail.classList.remove('is-active'));
    });
  } else {
    cursorTrail.style.display = 'none';
  }

  /* ---------- lock 1 : identity / email ---------- */
  emailInput.addEventListener('focus', function () {
    vaultWrap.classList.add('lock-1-active');
    flashStatusScan('IDENTITY SCAN ACTIVE');
  });
  emailInput.addEventListener('blur', function () {
    vaultWrap.classList.remove('lock-1-active');
  });
  emailInput.addEventListener('input', function () {
    const len = emailInput.value.length;
    if (len > 0 && len % 4 === 0 && len !== lastPinPulseLen) {
      lastPinPulseLen = len;
      pulseCore();
    }
  });

  /* ---------- lock 2 : secret key / password ---------- */
  passInput.addEventListener('focus', function () {
    vaultWrap.classList.add('lock-2-active', 'charging');
    vaultWrap.classList.add('is-dim');
  });
  passInput.addEventListener('blur', function () {
    if (!passInput.value) {
      vaultWrap.classList.remove('lock-2-active', 'charging', 'is-dim');
      vaultWrap.style.setProperty('--charge', 0);
    }
  });
  passInput.addEventListener('input', function () {
    const len = passInput.value.length;
    const charge = len <= 0 ? 0 : len <= 3 ? 0.25 : len <= 6 ? 0.5 : len <= 9 ? 0.75 : 1;
    vaultWrap.style.setProperty('--charge', charge.toFixed(2));
  });

  function pulseCore() {
    vaultCore.parentElement.classList.add('pulse-once');
    vaultCore.classList.add('pulse-once');
    setTimeout(() => vaultCore.classList.remove('pulse-once'), 650);
  }

  /* ---------- eye toggle ---------- */
  eyeToggle.addEventListener('click', function () {
    const showing = passInput.type === 'text';
    passInput.type = showing ? 'password' : 'text';
    eyeToggle.classList.toggle('is-visible', !showing);
    eyeToggle.setAttribute('aria-pressed', String(!showing));
    eyeToggle.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
    pulseCore();
  });

  /* ---------- helpers ---------- */
  function isValidEmail(v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); }

  function setFieldError(fieldEl, msgEl, message) { fieldEl.classList.add('has-error'); msgEl.textContent = message; }
  function clearFieldError(fieldEl, msgEl) { fieldEl.classList.remove('has-error'); msgEl.textContent = ''; }
  function shakeField(fieldEl) { fieldEl.classList.remove('shake-field'); void fieldEl.offsetWidth; fieldEl.classList.add('shake-field'); setTimeout(() => fieldEl.classList.remove('shake-field'), 400); }
  function shakeConsole() { consoleEl.classList.remove('is-shaking'); void consoleEl.offsetWidth; consoleEl.classList.add('is-shaking'); setTimeout(() => consoleEl.classList.remove('is-shaking'), 500); }

  function showStatus(tone, title, sub) {
    statusBanner.className = 'status-banner is-visible tone-' + tone;
    statusTitle.textContent = title;
    statusSub.textContent = sub || '';
  }
  function hideStatus() { statusBanner.className = 'status-banner'; statusTitle.textContent = ''; statusSub.textContent = ''; }
  function flashStatusScan(text) {
    showStatus('scan', text, '');
    setTimeout(function () { if (statusTitle.textContent === text) hideStatus(); }, 1300);
  }

  emailInput.addEventListener('input', () => clearFieldError(emailField, emailMsg));
  passInput.addEventListener('input', () => clearFieldError(passField, passMsg));

  function validateClientSide() {
    let ok = true;
    const email = emailInput.value.trim();
    const password = passInput.value;

    if (!email) {
      setFieldError(emailField, emailMsg, 'INVALID IDENTITY'); shakeField(emailField); ok = false;
    } else if (!isValidEmail(email)) {
      setFieldError(emailField, emailMsg, 'INVALID IDENTITY'); shakeField(emailField); ok = false;
    }
    if (!password) {
      setFieldError(passField, passMsg, 'SECURITY KEY REQUIRED'); shakeField(passField); ok = false;
    }
    return ok;
  }

  /* ---------- authentication phases ---------- */
  const phases = ['PHASE 01 · VERIFYING IDENTITY...', 'PHASE 02 · SEARCHING ARCHIVE...', 'PHASE 03 · VALIDATING KEY...', 'PHASE 04 · OPENING SECURITY CHANNEL...'];
  let phaseTimer = null;
  function startPhases() {
    let i = 0;
    showStatus('warn', phases[0]);
    phaseTimer = setInterval(function () {
      i = (i + 1) % phases.length;
      showStatus('warn', phases[i]);
    }, Math.round(MIN_CINEMATIC_MS / phases.length));
  }
  function stopPhases() { clearInterval(phaseTimer); }

  function setBusy(on) {
    switchBtn.disabled = on;
    switchBtn.classList.toggle('is-verifying', on);
    switchBtn.classList.toggle('is-pressed', on);
    vaultWrap.classList.toggle('verifying', on);
    btnLabel.textContent = on ? 'ACCESS SEQUENCE INITIATED' : 'INITIATE ACCESS';
    if (on) { startPhases(); } else { stopPhases(); }
  }

  /* ---------- success ---------- */
  function grantAccess() {
    setBusy(false);
    vaultWrap.classList.remove('rejected', 'charging');
    pulseCore();
    setTimeout(function () {
      vaultWrap.classList.add('is-open');
      fog1.classList.add('is-pushed');
      fog2.classList.add('is-pushed');
      switchBtn.classList.add('is-granted');
      showStatus('ok', 'ACCESS GRANTED', 'WELCOME, TRAVELER');
      consoleEl.classList.add('is-leaving');
    }, 350);
    setTimeout(() => { window.location.href = REDIRECT_URL; }, 2600);
  }

  /* ---------- rejection (401) ---------- */
  function denyAccess(message) {
    setBusy(false);
    flashBlack.classList.add('is-flashing');
    setTimeout(() => flashBlack.classList.remove('is-flashing'), 200);
    flashRed.classList.add('is-visible');
    vaultWrap.classList.add('rejected');
    vaultWrap.classList.remove('charging');
    showStatus('danger', 'ACCESS DENIED', (message || 'THE KEY WAS REJECTED') + ' · THE VAULT REMAINS SEALED');
    shakeConsole();
    setTimeout(function () {
      vaultWrap.classList.remove('rejected');
      flashRed.classList.remove('is-visible');
      hideStatus();
    }, 3000);
  }

  function validationFailed(errors) {
    setBusy(false);
    if (errors && errors.email) setFieldError(emailField, emailMsg, 'INVALID IDENTITY');
    if (errors && errors.password) setFieldError(passField, passMsg, 'SECURITY KEY REQUIRED');
    shakeConsole();
  }

  function connectionInterrupted(sub) {
    setBusy(false);
    vaultWrap.classList.remove('charging');
    showStatus('warn', 'CONNECTION INTERRUPTED', sub || 'THE VAULT COULD NOT VERIFY YOUR IDENTITY.');
    shakeConsole();
    setTimeout(hideStatus, 5000);
  }

  /* ---------- submit ---------- */
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (isSubmitting) return;
    hideStatus();
    if (!validateClientSide()) return;

    isSubmitting = true;
    setBusy(true);

    const startedAt = Date.now();
    const payload = { email: emailInput.value.trim(), password: passInput.value };

    fetch(LOGIN_URL, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify(payload)
    })
    .then(function (response) {
      const remaining = Math.max(0, MIN_CINEMATIC_MS - (Date.now() - startedAt));
      return new Promise(resolve => setTimeout(() => resolve(response), remaining));
    })
    .then(function (response) {
      if (response.status === 200) { grantAccess(); return; }                       // ← SUCCESS HANDLER
      if (response.status === 401) {                                                // ← 401 ERROR HANDLER
        return response.json().catch(() => ({})).then(d => denyAccess(d.message));
      }
      if (response.status === 422) {
        return response.json().catch(() => ({})).then(d => validationFailed(d.errors));
      }
      if (response.status === 419) {
        connectionInterrupted('Session expired. Refreshing…');
        setTimeout(() => window.location.reload(), 1800);
        return;
      }
      connectionInterrupted();
    })
    .catch(function () { connectionInterrupted(); })
    .finally(function () { isSubmitting = false; });
  });

})();
</script>
</body>
</html>
