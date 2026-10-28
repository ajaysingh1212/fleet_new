<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ config('app.name', 'Eemotrack Fleet') }}</title>

    @verbatim
    <style>
        /* ===== Page reset ===== */
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; background: #000; overflow: hidden; }
        body { font-family: ui-sans-serif, system-ui, sans-serif; color: #cfefff; }

        /* ===== Globe section (full screen) ===== */
        .globe-section { position: fixed; inset: 0; background: radial-gradient(ellipse at center, #04101f 0%, #01040a 55%, #000 100%); }
        #globe { position: absolute; inset: 0; width: 100%; height: 100%; display: block; cursor: grab; touch-action: none; }
        #globe.dragging { cursor: grabbing; }

        /* Halka neela ambient glow (screen blend) */
        .ambient { position: absolute; inset: 0; pointer-events: none; mix-blend-mode: screen;
            background: radial-gradient(circle at 50% 50%, rgba(20, 90, 170, .22) 0%, rgba(8, 40, 90, .10) 38%, transparent 65%); }

        /* Kinaron par vignette */
        .vignette { position: absolute; inset: 0; pointer-events: none;
            background: radial-gradient(ellipse at center, transparent 48%, rgba(0, 0, 0, .55) 80%, #000 100%); }

        /* ===== Fleet landing-page UI ===== */
        .topbar {
            position: absolute; z-index: 10; top: 26px; left: 32px; right: 32px;
            display: flex; align-items: center; justify-content: space-between; gap: 20px;
            pointer-events: none;
        }
        .brand { display:flex; align-items:center; gap:12px; pointer-events:auto; }
        .brand-mark {
            width:42px;height:42px;border:1px solid rgba(87,225,255,.6);border-radius:14px;
            display:grid;place-items:center;color:#8af3ff;font-size:20px;
            background:linear-gradient(145deg,rgba(26,169,211,.25),rgba(4,18,36,.7));
            box-shadow:0 0 28px rgba(0,199,255,.15),inset 0 0 18px rgba(0,210,255,.1);
        }
        .brand-name { color:#f0fbff;font-size:15px;font-weight:800;letter-spacing:.16em; }
        .brand-sub { margin-top:4px;color:#70a8bd;font-size:9px;letter-spacing:.22em;text-transform:uppercase; }
        .top-actions { display:flex;align-items:center;gap:12px;pointer-events:auto; }
        .system-pill { display:flex;align-items:center;gap:8px;padding:10px 13px;border:1px solid rgba(100,204,233,.18);
            border-radius:99px;background:rgba(5,20,37,.55);backdrop-filter:blur(14px);color:#9bc9d9;font-size:11px;letter-spacing:.08em; }
        .live-dot { width:7px;height:7px;border-radius:50%;background:#5bffc2;box-shadow:0 0 12px #5bffc2;animation:livePulse 1.8s infinite; }
        .login-btn {
            position:relative;overflow:hidden;display:inline-flex;align-items:center;gap:11px;
            padding:13px 19px;border:1px solid rgba(96,232,255,.68);border-radius:14px;
            color:#effcff;text-decoration:none;font-size:12px;font-weight:800;letter-spacing:.13em;text-transform:uppercase;
            background:linear-gradient(120deg,rgba(12,113,151,.48),rgba(8,27,50,.82));
            box-shadow:0 0 26px rgba(0,190,255,.16),inset 0 0 18px rgba(76,220,255,.07);
            transition:transform .25s,border-color .25s,box-shadow .25s;backdrop-filter:blur(16px);
        }
        .login-btn::before { content:"";position:absolute;top:-80%;left:-45%;width:35%;height:260%;transform:rotate(25deg);
            background:rgba(255,255,255,.22);filter:blur(8px);transition:left .6s; }
        .login-btn:hover { transform:translateY(-2px);border-color:#b4f8ff;box-shadow:0 0 35px rgba(0,205,255,.32),inset 0 0 22px rgba(76,220,255,.13); }
        .login-btn:hover::before { left:130%; }
        .login-arrow { font-size:18px;color:#80f1ff;transition:transform .25s; }
        .login-btn:hover .login-arrow { transform:translateX(3px); }
        .hero-copy { position:absolute;z-index:5;left:clamp(24px,7vw,112px);top:50%;transform:translateY(-46%);max-width:410px;pointer-events:none; }
        .eyebrow { display:flex;align-items:center;gap:10px;color:#70dff4;font-size:10px;letter-spacing:.25em;text-transform:uppercase;font-weight:700; }
        .eyebrow::before { content:"";width:26px;height:1px;background:#55d9f4;box-shadow:0 0 10px #55d9f4; }
        .hero-copy h1 { margin:20px 0 16px;color:#f2fbff;font-size:clamp(38px,5.2vw,70px);line-height:.98;letter-spacing:-.055em;font-weight:750;text-shadow:0 4px 35px rgba(0,0,0,.55); }
        .hero-copy h1 span { color:#67e5ff;text-shadow:0 0 32px rgba(49,207,255,.32); }
        .hero-copy p { max-width:340px;margin:0;color:#8eafc3;font-size:14px;line-height:1.8; }
        .hero-line { margin-top:25px;display:flex;align-items:center;gap:12px;color:#8caebe;font-size:10px;letter-spacing:.14em;text-transform:uppercase; }
        .hero-line .line { width:45px;height:1px;background:linear-gradient(90deg,#4de3ff,transparent); }
        .hud-card { position:absolute;z-index:4;min-width:158px;padding:14px 16px;border:1px solid rgba(95,210,238,.18);border-radius:15px;
            background:linear-gradient(135deg,rgba(7,27,47,.82),rgba(3,13,27,.58));backdrop-filter:blur(16px);
            box-shadow:0 12px 40px rgba(0,0,0,.18),inset 0 1px rgba(255,255,255,.025);pointer-events:none; }
        .hud-label { color:#719db1;font-size:9px;letter-spacing:.17em;text-transform:uppercase; }
        .hud-value { margin-top:8px;color:#e8faff;font-size:23px;font-weight:750;letter-spacing:-.04em; }
        .hud-value small { margin-left:5px;color:#6af0c2;font-size:10px;letter-spacing:0; }
        .hud-note { margin-top:5px;color:#62879b;font-size:10px; }
        .hud-one { right:7.5%;top:26%;animation:floatCard 6s ease-in-out infinite; }
        .hud-two { right:5.5%;bottom:24%;animation:floatCard 7s ease-in-out -2s infinite; }
        .hud-three { left:5%;bottom:9%;min-width:210px;animation:floatCard 8s ease-in-out -1s infinite; }
        .mini-bars { display:flex;align-items:flex-end;gap:4px;height:23px;margin-top:10px; }
        .mini-bars i { width:5px;border-radius:4px;background:linear-gradient(#61efff,#168db9);opacity:.8;animation:barPulse 1.2s ease-in-out infinite alternate; }
        .mini-bars i:nth-child(3n) { animation-delay:-.5s; }
        .fleet-marker { position:absolute;z-index:3;pointer-events:none;display:flex;align-items:center;gap:7px;color:#baf8ff;font-size:9px;letter-spacing:.1em;text-transform:uppercase; }
        .fleet-marker .vehicle { width:29px;height:29px;border:1px solid rgba(99,235,255,.65);border-radius:10px;display:grid;place-items:center;
            color:#8ef2ff;background:rgba(4,28,48,.75);box-shadow:0 0 22px rgba(0,204,255,.2);animation:vehicleGlow 2.5s infinite; }
        .fleet-marker .trail { position:absolute;left:14px;top:28px;width:1px;height:36px;background:linear-gradient(#51eaff,transparent);opacity:.7; }
        .fleet-a { left:57%;top:26%;animation:markerFloat 5s ease-in-out infinite; }
        .fleet-b { left:69%;top:61%;animation:markerFloat 6s ease-in-out -2s infinite; }
        .fleet-c { left:48%;top:67%;animation:markerFloat 7s ease-in-out -1s infinite; }
        .route-overlay { position:absolute;inset:0;width:100%;height:100%;z-index:2;pointer-events:none;opacity:.68; }
        .route-path { fill:none;stroke:#51e6ff;stroke-width:1.1;stroke-dasharray:5 8;filter:drop-shadow(0 0 4px #25c9f7);animation:routeDash 14s linear infinite; }
        .bottom-bar { position:absolute;z-index:5;left:32px;right:32px;bottom:24px;display:flex;justify-content:space-between;align-items:center;gap:18px;pointer-events:none; }
        .bottom-caption { color:#60889d;font-size:9px;letter-spacing:.18em;text-transform:uppercase; }
        .bottom-stats { display:flex;gap:22px;align-items:center;color:#7fa6b9;font-size:10px;letter-spacing:.08em;text-transform:uppercase; }
        .bottom-stats span { display:flex;align-items:center;gap:7px; }
        .bottom-stats b { color:#d5f8ff;font-weight:700; }
        .scanline { position:absolute;z-index:1;left:0;right:0;height:1px;top:-10%;pointer-events:none;background:linear-gradient(90deg,transparent,rgba(64,223,255,.14),transparent);animation:scan 11s linear infinite; }
        @keyframes livePulse { 50%{opacity:.45;box-shadow:0 0 4px #5bffc2;} }
        @keyframes floatCard { 0%,100%{transform:translateY(0)}50%{transform:translateY(-9px)} }
        @keyframes barPulse { to{height:35%;opacity:.45} }
        @keyframes vehicleGlow { 50%{box-shadow:0 0 28px rgba(0,204,255,.4);border-color:rgba(130,245,255,.95)} }
        @keyframes markerFloat { 0%,100%{translate:0 0}50%{translate:0 -7px} }
        @keyframes routeDash { to{stroke-dashoffset:-180} }
        @keyframes scan { to{top:110%} }
        @media (max-width:900px) {
            .topbar{top:18px;left:18px;right:18px}.system-pill{display:none}
            .hero-copy{top:43%;left:7%;max-width:330px}.hero-copy h1{font-size:clamp(38px,7vw,58px)}
            .hud-one{right:4%;top:22%;transform:scale(.86);transform-origin:right top}
            .hud-two{right:3%;bottom:17%;transform:scale(.86);transform-origin:right bottom}
            .hud-three{left:18px;bottom:7%;min-width:175px}.bottom-bar{left:18px;right:18px;bottom:14px}
            .bottom-caption{display:none}.bottom-stats{gap:12px}
        }
        @media (max-width:560px) {
            .brand-mark{width:36px;height:36px;border-radius:11px}.brand-name{font-size:12px}.brand-sub{font-size:8px}
            .login-btn{padding:11px 12px;font-size:10px;gap:7px;border-radius:12px}
            .hero-copy{top:41%;left:24px;max-width:78%}.hero-copy h1{font-size:43px;margin:15px 0 12px}
            .hero-copy p{font-size:12px;max-width:270px}.hero-line{margin-top:17px;font-size:8px}
            .hud-card{padding:10px 12px;min-width:128px;border-radius:12px}.hud-label{font-size:8px}.hud-value{font-size:19px}
            .hud-note{font-size:9px}.hud-one{top:auto;bottom:29%;right:4%;transform:scale(.8);transform-origin:right bottom}
            .hud-two{display:none}.hud-three{left:18px;bottom:11%;min-width:155px}
            .fleet-marker{font-size:8px}.fleet-marker .vehicle{width:25px;height:25px}
            .fleet-a{left:61%;top:28%}.fleet-b{left:72%;top:57%}.fleet-c{left:47%;top:70%}
            .bottom-bar{bottom:12px}.bottom-stats{font-size:8px;gap:9px}.bottom-stats span:nth-child(3){display:none}
        }
        @media (prefers-reduced-motion:reduce) {
            .live-dot,.hud-card,.mini-bars i,.fleet-marker,.fleet-marker .vehicle,.route-path,.scanline{animation:none!important}
            .login-btn,.login-arrow{transition:none!important}
        }


        /* ===== Fleet-login experience ===== */
        .login-topbar { z-index: 20; }
        .login-topbar .brand { text-decoration:none; }
        .login-topbar .top-actions { margin-left:auto; }
        .pill-divider { width:1px;height:12px;background:rgba(105,207,233,.25);display:inline-block;margin:0 2px; }
        .login-hero { top:29%;left:6.2%;max-width:440px;z-index:5; }
        .login-hero .eyebrow { display:flex;align-items:center;gap:10px;color:#80d9eb;font-size:10px;letter-spacing:.24em;text-transform:uppercase;font-weight:700; }
        .eyebrow-line { width:25px;height:1px;background:#4de4ff;box-shadow:0 0 12px #4de4ff; }
        .login-hero h1 { font-size:clamp(42px,5.1vw,76px);line-height:1.02;letter-spacing:-.055em;margin:22px 0 18px;color:#f2fbff;text-shadow:0 8px 40px rgba(0,0,0,.3); }
        .login-hero h1 span { color:#65e7ff;text-shadow:0 0 30px rgba(40,198,255,.25); }
        .login-hero p { max-width:385px;line-height:1.8;color:#9bb9c9;font-size:14px; }
        .login-hero .hero-line { margin-top:27px;color:#7da8bb;font-size:9px;letter-spacing:.13em; }
        .login-hero .hero-line b { color:#4fe6ff;margin:0 4px; }
        .login-panel {
            position:absolute;z-index:15;right:6.2%;top:50%;transform:translateY(-48%);
            width:min(420px,38vw);padding:27px 29px 21px;border-radius:22px;
            background:linear-gradient(145deg,rgba(7,24,42,.91),rgba(4,12,25,.86));
            border:1px solid rgba(90,218,246,.25);
            box-shadow:0 28px 90px rgba(0,0,0,.48),0 0 55px rgba(0,172,230,.09),inset 0 1px 0 rgba(210,250,255,.08);
            backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);
            animation:panel-arrive .9s cubic-bezier(.2,.8,.2,1) both .18s;
        }
        .login-panel::before { content:"";position:absolute;top:-1px;left:12%;right:12%;height:1px;background:linear-gradient(90deg,transparent,#74efff,transparent);opacity:.85; }
        .login-panel::after { content:"";position:absolute;inset:8px;border:1px solid rgba(112,207,235,.055);border-radius:16px;pointer-events:none; }
        @keyframes panel-arrive { from { opacity:0;transform:translate(24px,-45%) scale(.98);filter:blur(6px); } to { opacity:1;transform:translate(0,-48%) scale(1);filter:blur(0); } }
        .panel-topline { position:relative;z-index:1;display:flex;align-items:center;gap:9px;font-size:8px;letter-spacing:.16em;color:#79b4c9;font-weight:700;white-space:nowrap; }
        .panel-icon { display:grid;place-items:center;width:25px;height:25px;border:1px solid rgba(79,226,255,.3);border-radius:8px;color:#6feaff;font-size:17px;background:rgba(30,170,220,.09); }
        .panel-live { margin-left:auto;display:flex;align-items:center;gap:6px;color:#7fffd0;font-size:8px;letter-spacing:.12em; }
        .panel-live i,.session-note i { display:inline-block;width:5px;height:5px;border-radius:50%;background:#56ffc2;box-shadow:0 0 9px #56ffc2;animation:livePulse 1.8s infinite; }
        .panel-heading { position:relative;z-index:1;margin:25px 0 23px; }
        .panel-kicker { font-size:9px;letter-spacing:.24em;color:#57dff7;font-weight:800;margin-bottom:9px; }
        .panel-heading h2 { margin:0;color:#f1fbff;font-size:clamp(25px,2.2vw,32px);letter-spacing:-.045em;line-height:1.15;font-weight:750; }
        .panel-heading h2 span { color:#68e7ff; }
        .panel-heading p { color:#8daebf;font-size:11px;line-height:1.7;margin:10px 0 0; }
        .fleet-login-form { position:relative;z-index:1;display:flex;flex-direction:column;gap:17px; }
        .login-field label { display:block;color:#9bbbc9;font-size:8px;font-weight:800;letter-spacing:.16em;margin-bottom:8px; }
        .label-row { display:flex;align-items:center;justify-content:space-between;gap:10px; }
        .label-row label { margin-bottom:8px; }
        .input-shell { height:49px;display:flex;align-items:center;gap:10px;padding:0 13px;border:1px solid rgba(111,191,219,.18);border-radius:11px;background:rgba(1,9,20,.55);transition:border-color .25s,box-shadow .25s,background .25s; }
        .input-shell:focus-within { border-color:rgba(77,226,255,.78);background:rgba(5,25,42,.8);box-shadow:0 0 0 3px rgba(44,201,242,.09),0 0 24px rgba(29,177,227,.08); }
        .input-icon { flex:none;width:17px;height:17px;color:#5da7c0;display:grid;place-items:center; }
        .input-icon svg { width:17px;height:17px; }
        .input-shell input { min-width:0;flex:1;border:0;outline:0;background:transparent;color:#eafaff;font:500 12px 'Inter',system-ui,sans-serif;letter-spacing:.01em; }
        .input-shell input::placeholder { color:#476579; }
        .input-status { width:5px;height:5px;border-radius:50%;background:#3f7185;transition:background .2s,box-shadow .2s; }
        .input-shell:focus-within .input-status { background:#54e9ff;box-shadow:0 0 9px #54e9ff; }
        .password-toggle { flex:none;width:28px;height:28px;display:grid;place-items:center;border:0;background:transparent;color:#6d9eb2;border-radius:7px;cursor:pointer; }
        .password-toggle:hover { color:#8bf1ff;background:rgba(64,205,239,.08); }
        .password-toggle svg { width:17px;height:17px; }
        .eye-closed { display:none; }
        .password-toggle.is-visible .eye-open { display:none; }
        .password-toggle.is-visible .eye-closed { display:block; }
        .forgot-link { color:#6bdcf3;text-decoration:none;font-size:9px;transition:color .2s; }
        .forgot-link:hover { color:#fff; }
        .remember-row { display:flex;align-items:center;gap:9px;color:#8caebd;font-size:10px;cursor:pointer;user-select:none; }
        .remember-row input { position:absolute;opacity:0;width:1px;height:1px; }
        .custom-check { width:15px;height:15px;border:1px solid rgba(103,195,220,.35);border-radius:4px;background:rgba(0,0,0,.25);display:grid;place-items:center;transition:all .2s; }
        .remember-row input:checked + .custom-check { background:#40d6f2;border-color:#40d6f2;box-shadow:0 0 12px rgba(64,214,242,.25); }
        .remember-row input:checked + .custom-check::after { content:"✓";color:#03202b;font-size:10px;font-weight:900; }
        .remember-row input:focus-visible + .custom-check { outline:2px solid #64e8ff;outline-offset:3px; }
        .session-note { display:flex;align-items:center;gap:6px;margin-left:auto;color:#5d91a5;font-size:7px;letter-spacing:.1em; }
        .session-note i { width:4px;height:4px; }
        .access-button { position:relative;width:100%;height:52px;overflow:hidden;border:1px solid rgba(105,239,255,.65);border-radius:11px;background:linear-gradient(105deg,#0a8bb1,#08779b 45%,#07516f);color:#effcff;cursor:pointer;box-shadow:0 9px 24px rgba(0,156,205,.18),inset 0 1px 0 rgba(255,255,255,.16);transition:transform .2s,box-shadow .25s,border-color .25s; }
        .access-button:hover { transform:translateY(-2px);border-color:#a3f7ff;box-shadow:0 13px 30px rgba(0,177,228,.27),0 0 22px rgba(53,214,255,.12),inset 0 1px 0 rgba(255,255,255,.2); }
        .access-button:active { transform:translateY(0) scale(.99); }
        .access-button:disabled { cursor:wait;opacity:.85; }
        .button-shine { position:absolute;inset:-20% auto -20% -50%;width:35%;transform:skewX(-22deg);background:linear-gradient(90deg,transparent,rgba(255,255,255,.24),transparent); }
        .access-button:hover .button-shine { animation:button-sweep .8s ease; }
        @keyframes button-sweep { to { left:130%; } }
        .button-content { position:relative;display:flex;align-items:center;justify-content:center;gap:10px;height:100%;font-size:9px;font-weight:900;letter-spacing:.14em; }
        .button-lock { font-size:15px;line-height:1; }
        .button-arrow { position:absolute;right:16px;font-size:19px;font-weight:400;transition:transform .2s; }
        .access-button:hover .button-arrow { transform:translateX(4px); }
        .button-loader { display:none;position:absolute;width:18px;height:18px;right:16px;top:50%;margin-top:-9px;border:2px solid rgba(255,255,255,.28);border-top-color:#fff;border-radius:50%;animation:spin-loader .7s linear infinite; }
        .access-button.is-loading .button-loader { display:block; }
        .access-button.is-loading .button-arrow { display:none; }
        @keyframes spin-loader { to { transform:rotate(360deg); } }
        .auth-alert { position:relative;z-index:1;display:flex;gap:9px;align-items:center;margin:-8px 0 17px;padding:10px 12px;border-radius:9px;font-size:10px;line-height:1.5; }
        .auth-error { color:#ffb9b5;background:rgba(180,45,57,.12);border:1px solid rgba(255,99,109,.22); }
        .auth-success { color:#a9ffe0;background:rgba(30,175,126,.1);border:1px solid rgba(78,255,190,.2); }
        .alert-symbol { display:grid;place-items:center;flex:none;width:17px;height:17px;border:1px solid currentColor;border-radius:50%;font-weight:800; }
        .field-error { margin-top:6px;color:#ff9f9a;font-size:10px; }
        .panel-foot { position:relative;z-index:1;display:flex;align-items:center;gap:7px;margin-top:20px;padding-top:15px;border-top:1px solid rgba(114,193,218,.12);color:#6e96a8;font-size:9px; }
        .shield-icon { color:#59dff5;font-size:16px; }
        .foot-code { margin-left:auto;color:#426a7c;font-size:8px;letter-spacing:.12em; }
        .login-hud { top:auto;left:6.2%;bottom:14%;right:auto;min-width:190px;z-index:5; }
        .login-hud .hud-label { display:flex;align-items:center;gap:7px; }
        .login-hud .hud-label .live-dot { width:5px;height:5px; }
        .login-bottom { z-index:10; }
        .route-overlay { z-index:1; }
        @media (max-width:1100px) {
            .login-hero { left:4%;max-width:36%;top:25%; }
            .login-hero h1 { font-size:clamp(38px,4.6vw,56px); }
            .login-hero p { font-size:12px; }
            .login-panel { right:4%;width:min(400px,43vw);padding:23px 23px 19px; }
            .login-hud { left:4%;bottom:12%; }
        }
        @media (max-width:760px) {
            html,body { height:auto;min-height:100%;overflow:auto; }
            .globe-section { position:fixed;min-height:100dvh;overflow:hidden; }
            #globe { opacity:.72; }
            .login-topbar { top:15px;left:16px;right:16px; }
            .system-pill { padding:9px 10px;font-size:8px;gap:6px; }
            .login-hero { top:12%;left:22px;right:22px;max-width:none; }
            .login-hero h1 { font-size:35px;margin:11px 0 8px; }
            .login-hero p { display:none; }
            .login-hero .hero-line { display:none; }
            .login-panel { position:absolute;top:47%;left:50%;right:auto;transform:translate(-50%,-10%);width:min(430px,calc(100% - 32px));padding:22px 21px 18px;border-radius:19px;animation:mobile-panel-arrive .8s ease both .15s; }
            @keyframes mobile-panel-arrive { from { opacity:0;transform:translate(-50%,-4%) scale(.98); } to { opacity:1;transform:translate(-50%,-10%) scale(1); } }
            .panel-heading { margin:17px 0 18px; }
            .panel-heading h2 { font-size:27px; }
            .panel-heading p { font-size:10px; }
            .fleet-login-form { gap:14px; }
            .input-shell { height:46px; }
            .access-button { height:49px; }
            .login-hud { display:none; }
            .fleet-marker { opacity:.65; }
            .fleet-a { left:7%;top:39%; }.fleet-b { left:72%;top:35%; }.fleet-c { left:7%;top:75%; }
            .login-bottom { left:16px;right:16px;bottom:10px; }
            .bottom-caption { display:none; }
            .bottom-stats { justify-content:center;gap:13px;font-size:8px; }
            .bottom-stats span:nth-child(3) { display:none; }
        }
        @media (max-width:420px) {
            .brand-mark { width:34px;height:34px;border-radius:10px; }
            .brand-name { font-size:11px;letter-spacing:.12em; }
            .brand-sub { font-size:7px; }
            .system-pill { font-size:7px;padding:8px; }
            .login-hero { top:11%; }
            .login-hero h1 { font-size:31px; }
            .login-panel { top:43%;padding:20px 17px 16px; }
            .panel-topline { font-size:7px;gap:6px; }
            .panel-icon { width:22px;height:22px; }
            .panel-heading h2 { font-size:24px; }
            .panel-heading p { max-width:270px; }
            .session-note { font-size:6px; }
            .button-content { font-size:8px; }
            .panel-foot { font-size:8px; }
        }
        @media (max-height:760px) and (min-width:761px) {
            .login-panel { padding:18px 23px 15px; }
            .panel-heading { margin:15px 0 16px; }
            .panel-heading h2 { font-size:27px; }
            .fleet-login-form { gap:12px; }
            .input-shell { height:44px; }
            .access-button { height:46px; }
            .panel-foot { margin-top:13px;padding-top:11px; }
            .login-hero { top:24%; }
        }
        @media (prefers-reduced-motion:reduce) {
            .login-panel,.login-hero h1,.access-button,.button-shine { animation:none!important;transition:none!important; }
        }
    </style>
    @endverbatim
</head>
<body>
    <section class="globe-section" aria-label="Eemotrack fleet login">
        <canvas id="globe" aria-label="Interactive animated globe"></canvas>
        <div class="ambient"></div>
        <div class="vignette"></div>
        <div class="scanline" aria-hidden="true"></div>

        <header class="topbar login-topbar">
            <a class="brand" href="{{ url('/') }}" aria-label="{{ config('app.name', 'Eemotrack') }} home">
                <div class="brand-mark" aria-hidden="true">✦</div>
                <div>
                    <div class="brand-name">{{ strtoupper(config('app.name', 'EEMOTRACK')) }}</div>
                    <div class="brand-sub">Global Fleet Intelligence</div>
                </div>
            </a>
            <div class="top-actions">
                <div class="system-pill"><span class="live-dot"></span> SECURE NETWORK <span class="pill-divider"></span> ONLINE</div>
            </div>
        </header>

        <div class="hero-copy login-hero">
            <div class="eyebrow"><span class="eyebrow-line"></span> THE WORLD. YOUR FLEET.</div>
            <h1>Every mile.<br><span>In control.</span></h1>
            <p>One connected view of your vehicles, journeys and operations. Welcome back to your fleet command centre.</p>
            <div class="hero-line"><span class="line"></span> LIVE TRACKING <b>·</b> SMARTER JOURNEYS <b>·</b> TOTAL CONTROL</div>
        </div>

        <div class="fleet-marker fleet-a"><span class="vehicle">↗</span><span>UNIT 024<span class="trail"></span></span></div>
        <div class="fleet-marker fleet-b"><span class="vehicle">↗</span><span>UNIT 108<span class="trail"></span></span></div>
        <div class="fleet-marker fleet-c"><span class="vehicle">↗</span><span>UNIT 067<span class="trail"></span></span></div>

        <div class="hud-card hud-one login-hud">
            <div class="hud-label">FLEET NETWORK <span class="live-dot"></span></div>
            <div class="hud-value">Connected <small>● LIVE</small></div>
            <div class="hud-note">GPS signal intelligence</div>
            <div class="mini-bars" aria-hidden="true">
                <i style="height:45%"></i><i style="height:70%"></i><i style="height:54%"></i><i style="height:88%"></i><i style="height:63%"></i><i style="height:100%"></i><i style="height:75%"></i><i style="height:92%"></i><i style="height:58%"></i><i style="height:80%"></i>
            </div>
        </div>

        <aside class="login-panel" id="loginPanel">
            <div class="panel-topline"><span class="panel-icon">⌁</span><span>FLEET COMMAND / AUTH 01</span><span class="panel-live"><i></i> SECURE</span></div>
            <div class="panel-heading">
                <div class="panel-kicker">WELCOME BACK</div>
                <h2>Sign in to <span>your fleet.</span></h2>
                <p>Enter your credentials to access your tracking dashboard.</p>
            </div>

            @if (session('status'))
                <div class="auth-alert auth-success" role="status">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="auth-alert auth-error" role="alert">
                    <span class="alert-symbol">!</span>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="fleet-login-form" id="fleetLoginForm">
                @csrf
                <div class="login-field">
                    <label for="email">EMAIL / USER ID</label>
                    <div class="input-shell">
                        <span class="input-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke="currentColor" stroke-width="1.5"/><path d="m5 7 7 5 7-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="name@company.com" autocomplete="username" required autofocus>
                        <span class="input-status" aria-hidden="true"></span>
                    </div>
                    @error('email') <div class="field-error">{{ $message }}</div> @enderror
                </div>

                <div class="login-field">
                    <div class="label-row"><label for="password">PASSWORD / ACCESS KEY</label>
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="forgot-link">Forgot password?</a>
                        @endif
                    </div>
                    <div class="input-shell">
                        <span class="input-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><rect x="4.5" y="10" width="15" height="10" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M8 10V7a4 4 0 0 1 8 0v3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="12" cy="15" r="1.2" fill="currentColor"/></svg>
                        </span>
                        <input id="password" type="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                        <button class="password-toggle" type="button" id="passwordToggle" aria-label="Show password" aria-pressed="false">
                            <svg class="eye-open" viewBox="0 0 24 24" fill="none"><path d="M2.5 12s3.4-6.5 9.5-6.5 9.5 6.5 9.5 6.5-3.4 6.5-9.5 6.5S2.5 12 2.5 12Z" stroke="currentColor" stroke-width="1.5"/><circle cx="12" cy="12" r="2.8" stroke="currentColor" stroke-width="1.5"/></svg>
                            <svg class="eye-closed" viewBox="0 0 24 24" fill="none"><path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M6.4 6.5C3.9 8.1 2.5 12 2.5 12s3.4 6.5 9.5 6.5c1.6 0 3-.4 4.2-1M10 5.7c.6-.1 1.3-.2 2-.2 6.1 0 9.5 6.5 9.5 6.5s-.9 1.7-2.5 3.2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                        </button>
                    </div>
                    @error('password') <div class="field-error">{{ $message }}</div> @enderror
                </div>

                <label class="remember-row"><input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}><span class="custom-check"></span><span>Keep me signed in</span><span class="session-note"><i></i> ENCRYPTED SESSION</span></label>

                <button type="submit" class="access-button" id="accessButton">
                    <span class="button-shine"></span>
                    <span class="button-content"><span class="button-lock">↗</span><span class="button-label">ACCESS FLEET DASHBOARD</span><span class="button-arrow">→</span></span>
                    <span class="button-loader" aria-hidden="true"></span>
                </button>
            </form>

            <div class="panel-foot"><span class="shield-icon">⬡</span><span>Protected by secure authentication</span><span class="foot-code">EEMO / 01</span></div>
        </aside>

        <footer class="bottom-bar login-bottom">
            <div class="bottom-caption">Eemotrack <span>·</span> Fleet intelligence system</div>
            <div class="bottom-stats">
                <span><i class="live-dot"></i> Network <b>Operational</b></span>
                <span>GPS <b>Connected</b></span>
                <span>Access <b>Encrypted</b></span>
            </div>
        </footer>
    </section>
    <!-- Three.js + post-processing (bloom) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/shaders/CopyShader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/shaders/LuminosityHighPassShader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/postprocessing/EffectComposer.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/postprocessing/RenderPass.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/postprocessing/ShaderPass.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/postprocessing/UnrealBloomPass.js"></script>

    @verbatim
    <script>
    (() => {
        'use strict';

        /* ================= Config ================= */
        const R = 3; // globe ka radius
        const isMobile = matchMedia('(max-width: 768px)').matches || /Mobi|Android/i.test(navigator.userAgent);
        const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;
        const CFG = {
            nodes: isMobile ? 190 : 340,
            arcs: isMobile ? 70 : 135,
            particles: isMobile ? 220 : 650,
            pulses: isMobile ? 35 : 70,
            pixelRatio: Math.min(window.devicePixelRatio || 1, isMobile ? 1.5 : 2),
            autoSpeed: -0.07
        };

        /* ================= Deterministic random (seed fix, reload par layout same) ================= */
        function mulberry32(a) {
            return function () {
                a |= 0; a = a + 0x6D2B79F5 | 0;
                let t = Math.imul(a ^ a >>> 15, 1 | a);
                t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t;
                return ((t ^ t >>> 14) >>> 0) / 4294967296;
            };
        }
        const rnd = mulberry32(20240527);

        /* ================= Geo helpers ================= */
        const toVec = (lat, lon, r = R) => {
            const la = lat * Math.PI / 180, lo = lon * Math.PI / 180;
            return new THREE.Vector3(r * Math.cos(la) * Math.cos(lo), r * Math.sin(la), -r * Math.cos(la) * Math.sin(lo));
        };

        // Simplified continent outlines [lon, lat]
        const LAND = [
            [[-168,66],[-156,71],[-125,70],[-95,72],[-80,68],[-62,60],[-55,50],[-66,44],[-76,35],[-81,25],[-97,26],[-106,23],[-115,30],[-123,38],[-125,48],[-140,60]],
            [[-106,23],[-97,20],[-88,16],[-83,9],[-77,8],[-80,9],[-86,14],[-92,15],[-105,20]],
            [[-78,8],[-70,12],[-60,10],[-50,0],[-35,-6],[-40,-22],[-48,-28],[-58,-38],[-66,-48],[-70,-54],[-74,-48],[-72,-30],[-70,-18],[-81,-6],[-80,2]],
            [[-55,60],[-45,60],[-20,70],[-20,82],[-40,83],[-65,80],[-70,76]],
            [[-10,36],[-9,43],[-2,48],[5,52],[10,57],[20,60],[28,70],[40,68],[60,70],[80,73],[105,77],[130,72],[160,70],[180,68],[180,62],[160,58],[140,54],[135,43],[127,38],[122,30],[120,22],[108,18],[106,10],[100,8],[103,2],[98,10],[92,20],[80,14],[77,8],[72,20],[67,25],[57,25],[56,26],[48,30],[50,24],[56,17],[43,13],[35,28],[34,31],[27,37],[22,37],[18,40],[12,44],[8,44],[3,43],[-5,36]],
            [[-17,21],[-10,30],[-5,36],[10,37],[20,33],[32,31],[35,28],[43,12],[51,12],[40,-3],[40,-15],[35,-25],[20,-35],[17,-30],[12,-17],[9,-1],[9,4],[-8,4],[-17,14]],
            [[114,-22],[122,-18],[130,-12],[137,-12],[142,-11],[146,-19],[153,-26],[150,-37],[140,-38],[130,-32],[115,-34]],
            [[95,5],[105,-6],[120,-8],[140,-8],[140,-3],[120,1],[108,2]],
            [[-6,50],[1,51],[-2,58],[-6,57]],
            [[130,32],[135,34],[141,38],[142,44],[140,41],[133,35]]
        ];

        // Hub cities [lat, lon]
        const HUBS = [
            [40.7,-74],[34,-118],[19.4,-99],[-23.5,-46.6],[-34.6,-58.4],[51.5,0],[48.8,2.3],[52.5,13.4],
            [55.7,37.6],[30,31],[6.5,3.4],[-1.3,36.8],[-33.9,18.4],[25.2,55.3],[19,72.9],[28.6,77.2],
            [25.6,85.1],[1.35,103.8],[39.9,116.4],[31.2,121.5],[35.7,139.7],[-33.9,151.2],[-6.2,106.8],[43.7,-79.4]
        ];

        /* ================= Renderer / scene / camera ================= */
        const canvas = document.getElementById('globe');
        const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, powerPreference: 'high-performance' });
        renderer.setPixelRatio(CFG.pixelRatio);
        renderer.setClearColor(0x000000, 1);

        const scene = new THREE.Scene();
        const camera = new THREE.PerspectiveCamera(40, 1, 0.1, 100);
        const world = new THREE.Group();   // orbits + sab kuch
        const pivot = new THREE.Group();   // globe ka tilt (drag up/down)
        const spin = new THREE.Group();    // globe ka vertical axis rotation
        scene.add(world); world.add(pivot); pivot.add(spin);
        pivot.rotation.x = 0.35;
        spin.rotation.y = -(90 + 80) * Math.PI / 180; // shuru mein Asia/India front mein

        // Shared uniforms
        const U = { uTime: { value: 0 }, uScale: { value: 1 } };

        /* ================= Land mask + texture ================= */
        const TW = 2048, TH = 1024;
        const pathPoly = (g, poly) => {
            g.beginPath();
            poly.forEach(([lo, la], i) => {
                const x = (lo + 180) / 360 * TW, y = (90 - la) / 180 * TH;
                i ? g.lineTo(x, y) : g.moveTo(x, y);
            });
            g.closePath();
        };
        const landCanvas = document.createElement('canvas');
        landCanvas.width = TW; landCanvas.height = TH;
        const lg = landCanvas.getContext('2d');
        lg.fillStyle = '#fff';
        LAND.forEach(p => { pathPoly(lg, p); lg.fill(); });
        const landData = lg.getImageData(0, 0, TW, TH).data;
        const isLand = (lat, lon) => {
            const x = Math.min(TW - 1, Math.floor((lon + 180) / 360 * TW));
            const y = Math.min(TH - 1, Math.floor((90 - lat) / 180 * TH));
            return landData[(y * TW + x) * 4 + 3] > 128;
        };

        function buildGlobeTexture() {
            const c = document.createElement('canvas'); c.width = TW; c.height = TH;
            const g = c.getContext('2d');
            const grd = g.createLinearGradient(0, 0, 0, TH);
            grd.addColorStop(0, '#02060f'); grd.addColorStop(.5, '#06152a'); grd.addColorStop(1, '#02060f');
            g.fillStyle = grd; g.fillRect(0, 0, TW, TH);

            // Graticule (lat/lon lines) - bahut subtle
            g.strokeStyle = 'rgba(40,200,230,0.05)'; g.lineWidth = 1;
            for (let lo = -180; lo <= 180; lo += 15) { const x = (lo + 180) / 360 * TW; g.beginPath(); g.moveTo(x, 0); g.lineTo(x, TH); g.stroke(); }
            for (let la = -75; la <= 75; la += 15) { const y = (90 - la) / 180 * TH; g.beginPath(); g.moveTo(0, y); g.lineTo(TW, y); g.stroke(); }

            // Continents ko tint karke low opacity mein fill
            const tint = document.createElement('canvas'); tint.width = TW; tint.height = TH;
            const tg = tint.getContext('2d');
            tg.drawImage(landCanvas, 0, 0);
            tg.globalCompositeOperation = 'source-in';
            tg.fillStyle = '#1fb8d8'; tg.fillRect(0, 0, TW, TH);
            g.globalAlpha = 0.14; g.drawImage(tint, 0, 0); g.globalAlpha = 1;

            // Dotted land texture
            g.fillStyle = 'rgba(70,230,240,0.30)';
            for (let y = 4; y < TH; y += 9) for (let x = 4; x < TW; x += 9) {
                if (landData[(y * TW + x) * 4 + 3] > 128) { g.beginPath(); g.arc(x, y, 1.4, 0, 6.2832); g.fill(); }
            }
            // Coast outline
            g.strokeStyle = 'rgba(60,220,240,0.22)'; g.lineWidth = 1.5;
            LAND.forEach(p => { pathPoly(g, p); g.stroke(); });

            const tex = new THREE.CanvasTexture(c);
            tex.anisotropy = renderer.capabilities.getMaxAnisotropy();
            return tex;
        }

        /* ================= Globe + atmosphere ================= */
        const globe = new THREE.Mesh(
            new THREE.SphereGeometry(R, 96, 96),
            new THREE.ShaderMaterial({
                uniforms: { uMap: { value: buildGlobeTexture() } },
                vertexShader: `
                    varying vec2 vUv; varying vec3 vN; varying vec3 vV;
                    void main(){
                        vUv = uv; vN = normalize(normalMatrix * normal);
                        vec4 mv = modelViewMatrix * vec4(position, 1.0);
                        vV = normalize(-mv.xyz);
                        gl_Position = projectionMatrix * mv;
                    }`,
                fragmentShader: `
                    uniform sampler2D uMap; varying vec2 vUv; varying vec3 vN; varying vec3 vV;
                    void main(){
                        vec3 tex = texture2D(uMap, vUv).rgb;
                        vec3 L = normalize(vec3(-0.55, 0.5, 0.7));
                        float d = max(dot(vN, L), 0.0) * 0.7 + 0.3;
                        float f = pow(1.0 - max(dot(vN, vV), 0.0), 2.6);
                        vec3 col = tex * d * 1.15 + vec3(0.0, 0.75, 0.9) * f * 0.55;
                        gl_FragColor = vec4(col, 1.0);
                    }`
            })
        );
        spin.add(globe);

        // Atmosphere glow (peeche wala bada sphere)
        const atmosphere = new THREE.Mesh(
            new THREE.SphereGeometry(R * 1.16, 64, 64),
            new THREE.ShaderMaterial({
                vertexShader: `varying vec3 vN; void main(){ vN = normalize(normalMatrix * normal); gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0); }`,
                fragmentShader: `varying vec3 vN; void main(){
                    float i = pow(max(0.64 - dot(vN, vec3(0.0, 0.0, 1.0)), 0.0), 2.6);
                    gl_FragColor = vec4(0.05, 0.75, 0.95, 1.0) * i * 1.6; }`,
                side: THREE.BackSide, blending: THREE.AdditiveBlending, transparent: true, depthWrite: false
            })
        );
        pivot.add(atmosphere);

        /* ================= Points (nodes / particles / pulse heads) ================= */
        function pointMaterial(color, useFace) {
            return new THREE.ShaderMaterial({
                uniforms: { uTime: U.uTime, uScale: U.uScale, uColor: { value: new THREE.Color(color) }, uFace: { value: useFace ? 1 : 0 } },
                vertexShader: `
                    uniform float uTime; uniform float uScale; uniform float uFace;
                    attribute float aSize; attribute float aPhase; attribute float aBase;
                    varying float vA; varying float vFlick;
                    void main(){
                        vec4 wp = modelMatrix * vec4(position, 1.0);
                        vec4 mv = viewMatrix * wp;
                        float face = mix(1.0, smoothstep(-0.25, 0.55, dot(normalize(wp.xyz), normalize(cameraPosition))), uFace);
                        vFlick = (0.7 + 0.3 * sin(uTime * (1.2 + aPhase * 1.5) + aPhase * 6.2831)) * aBase;
                        vA = mix(0.15, 1.0, face);
                        gl_PointSize = max(aSize * uScale / -mv.z, 1.0);
                        gl_Position = projectionMatrix * mv;
                    }`,
                fragmentShader: `
                    uniform vec3 uColor; varying float vA; varying float vFlick;
                    void main(){
                        float d = length(gl_PointCoord - 0.5);
                        if (d > 0.5) discard;
                        float core = smoothstep(0.5, 0.0, d);
                        gl_FragColor = vec4(uColor * (1.0 + core * 0.8), core * core * vA * vFlick);
                    }`,
                transparent: true, depthWrite: false, blending: THREE.AdditiveBlending
            });
        }
        function makePoints(items, material) {
            const n = items.length;
            const pos = new Float32Array(n * 3), size = new Float32Array(n), phase = new Float32Array(n), base = new Float32Array(n);
            items.forEach((it, i) => {
                pos.set([it.v.x, it.v.y, it.v.z], i * 3);
                size[i] = it.size; phase[i] = it.phase; base[i] = it.base;
            });
            const geo = new THREE.BufferGeometry();
            geo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
            geo.setAttribute('aSize', new THREE.BufferAttribute(size, 1));
            geo.setAttribute('aPhase', new THREE.BufferAttribute(phase, 1));
            geo.setAttribute('aBase', new THREE.BufferAttribute(base, 1));
            const pts = new THREE.Points(geo, material);
            pts.frustumCulled = false;
            return pts;
        }

        /* ================= Nodes (hubs + land par random) ================= */
        const hubs = HUBS.map(([la, lo]) => ({ v: toVec(la, lo, R * 1.004), size: 0.085, phase: rnd(), base: 1 }));
        const nodes = [];
        while (nodes.length < CFG.nodes - hubs.length) {
            const lat = Math.asin(2 * rnd() - 1) * 180 / Math.PI; // uniform sphere
            const lon = rnd() * 360 - 180;
            if (!isLand(lat, lon) && rnd() > 0.06) continue; // zyada nodes land par
            nodes.push({ v: toVec(lat, lon, R * 1.004), size: 0.03 + rnd() * 0.03, phase: rnd(), base: 0.55 + rnd() * 0.45 });
        }
        spin.add(makePoints(nodes, pointMaterial(0x3ff3ff, true)));
        spin.add(makePoints(hubs, pointMaterial(0x8ffcff, true)));

        /* ================= Network arcs (Bezier curves) ================= */
        const arcs = [];
        {
            const SEG = 48, pos = [], tt = [], par = [];
            const pickHub = () => hubs[(rnd() * hubs.length) | 0];
            const pickNode = () => nodes[(rnd() * nodes.length) | 0];
            for (let tries = 0; arcs.length < CFG.arcs && tries < 6000; tries++) {
                const a = rnd() < 0.7 ? pickHub() : pickNode();
                const b = rnd() < 0.55 ? pickHub() : pickNode();
                const ang = a.v.angleTo(b.v);
                if (ang < 0.4 || ang > 2.7) continue;
                const h = R * (1.0 + ang * 0.34 * (0.8 + rnd() * 0.5)); // distance ke hisaab se arc height
                const c1 = a.v.clone().lerp(b.v, 0.25).normalize().multiplyScalar(h);
                const c2 = a.v.clone().lerp(b.v, 0.75).normalize().multiplyScalar(h);
                const curve = new THREE.CubicBezierCurve3(a.v.clone(), c1, c2, b.v.clone());
                const off = rnd(), spd = 0.06 + rnd() * 0.12, br = 0.35 + rnd() * 0.65;
                const pts = curve.getPoints(SEG);
                for (let i = 0; i < SEG; i++) {
                    pos.push(pts[i].x, pts[i].y, pts[i].z, pts[i + 1].x, pts[i + 1].y, pts[i + 1].z);
                    tt.push(i / SEG, (i + 1) / SEG);
                    par.push(off, spd, br, off, spd, br);
                }
                arcs.push({ curve, off, spd });
            }
            const geo = new THREE.BufferGeometry();
            geo.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
            geo.setAttribute('aT', new THREE.Float32BufferAttribute(tt, 1));
            geo.setAttribute('aPar', new THREE.Float32BufferAttribute(par, 3));
            const mat = new THREE.ShaderMaterial({
                uniforms: { uTime: U.uTime },
                vertexShader: `
                    attribute float aT; attribute vec3 aPar;
                    varying float vT; varying float vOff; varying float vSpd; varying float vBr; varying float vFace;
                    void main(){
                        vT = aT; vOff = aPar.x; vSpd = aPar.y; vBr = aPar.z;
                        vec4 wp = modelMatrix * vec4(position, 1.0);
                        vFace = smoothstep(-0.25, 0.55, dot(normalize(wp.xyz), normalize(cameraPosition)));
                        gl_Position = projectionMatrix * viewMatrix * wp;
                    }`,
                fragmentShader: `
                    uniform float uTime;
                    varying float vT; varying float vOff; varying float vSpd; varying float vBr; varying float vFace;
                    void main(){
                        float d = fract(uTime * vSpd + vOff - vT);
                        float pulse = exp(-d * 12.0);
                        float ends = smoothstep(0.0, 0.06, vT) * smoothstep(1.0, 0.94, vT);
                        float a = (0.20 + pulse * 1.1) * vBr * ends * mix(0.15, 1.0, vFace);
                        gl_FragColor = vec4(mix(vec3(0.0, 0.75, 0.95), vec3(0.6, 1.0, 1.0), pulse), a);
                    }`,
                transparent: true, depthWrite: false, blending: THREE.AdditiveBlending
            });
            const lines = new THREE.LineSegments(geo, mat);
            lines.frustumCulled = false;
            spin.add(lines);
        }

        // Travelling pulse heads (arc par chalne wale chamakdaar dots)
        const pulseCount = Math.min(CFG.pulses, arcs.length);
        const pulseItems = Array.from({ length: pulseCount }, () => ({ v: new THREE.Vector3(), size: 0.075, phase: rnd(), base: 1 }));
        const pulsePts = makePoints(pulseItems, pointMaterial(0xbffcff, true));
        spin.add(pulsePts);
        const tmpV = new THREE.Vector3();
        function updatePulses(time) {
            const pa = pulsePts.geometry.attributes.position, ba = pulsePts.geometry.attributes.aBase;
            for (let k = 0; k < pulseCount; k++) {
                const a = arcs[k];
                const t = (((time * a.spd + a.off) % 1) + 1) % 1;
                a.curve.getPoint(t, tmpV);
                pa.setXYZ(k, tmpV.x, tmpV.y, tmpV.z);
                ba.setX(k, Math.sin(Math.PI * t));
            }
            pa.needsUpdate = true; ba.needsUpdate = true;
        }

        /* ================= Orbit rings + satellites ================= */
        const orbits = [];
        [[3.95, 0.9, 0.2, 0.18], [4.5, -0.6, 0.5, -0.12], [5.1, 0.25, -0.7, 0.08]].forEach(([r, tx, tz, sp]) => {
            const tilt = new THREE.Group(); tilt.rotation.set(tx, 0, tz);
            const spinner = new THREE.Group();
            const ringPts = [];
            for (let i = 0; i < 256; i++) { const a = i / 256 * Math.PI * 2; ringPts.push(new THREE.Vector3(Math.cos(a) * r, 0, Math.sin(a) * r)); }
            const ring = new THREE.LineLoop(
                new THREE.BufferGeometry().setFromPoints(ringPts),
                new THREE.LineBasicMaterial({ color: 0x1fd6e8, transparent: true, opacity: 0.13, blending: THREE.AdditiveBlending, depthWrite: false })
            );
            const sat = makePoints([{ v: new THREE.Vector3(r, 0, 0), size: 0.11, phase: rnd(), base: 1 }], pointMaterial(0x9ffcff, false));
            spinner.add(sat); tilt.add(ring); tilt.add(spinner); world.add(tilt);
            orbits.push({ spinner, speed: sp });
        });

        /* ================= Ambient particles ================= */
        const dust = [];
        for (let i = 0; i < CFG.particles; i++) {
            const r = 4.2 + rnd() * 5.5;
            const u = rnd() * 2 - 1, th = rnd() * Math.PI * 2, s = Math.sqrt(1 - u * u);
            dust.push({ v: new THREE.Vector3(r * s * Math.cos(th), r * u * 0.8, r * s * Math.sin(th)), size: 0.02 + rnd() * 0.04, phase: rnd(), base: 0.25 + rnd() * 0.6 });
        }
        const dustPts = makePoints(dust, pointMaterial(0x59c9ff, false));
        world.add(dustPts);

        /* ================= Post-processing (bloom) ================= */
        let composer = null;
        if (THREE.EffectComposer && THREE.UnrealBloomPass && THREE.RenderPass) {
            composer = new THREE.EffectComposer(renderer);
            composer.setPixelRatio(CFG.pixelRatio);
            composer.addPass(new THREE.RenderPass(scene, camera));
            composer.bloom = new THREE.UnrealBloomPass(new THREE.Vector2(innerWidth, innerHeight), isMobile ? 0.6 : 0.8, 0.55, 0.12);
            composer.addPass(composer.bloom);
        }

        /* ================= Resize / camera fit ================= */
        let baseDist = 12, zoom = 1, zoomTarget = 1;
        function resize() {
            const w = innerWidth, h = innerHeight;
            renderer.setSize(w, h, false);
            if (composer) composer.setSize(w, h);
            camera.aspect = w / h;
            const tanH = Math.tan(THREE.MathUtils.degToRad(camera.fov / 2));
            // Desktop par globe ~74% height; portrait par ~86% width
            const visH = Math.max((2 * R) / 0.74, (2 * R / 0.86) / camera.aspect);
            baseDist = visH / (2 * tanH);
            U.uScale.value = (h * CFG.pixelRatio) / (2 * tanH);
            camera.updateProjectionMatrix();
        }
        addEventListener('resize', resize);
        resize();

        /* ================= Interaction (drag + wheel) ================= */
        let dragging = false, lastX = 0, lastY = 0, dragDX = 0, inertia = 0;
        const onDown = e => { dragging = true; lastX = e.clientX; lastY = e.clientY; canvas.classList.add('dragging'); canvas.setPointerCapture(e.pointerId); };
        const onMove = e => {
            if (!dragging) return;
            dragDX += e.clientX - lastX;
            pivot.rotation.x = THREE.MathUtils.clamp(pivot.rotation.x + (e.clientY - lastY) * 0.005, -0.9, 0.9);
            lastX = e.clientX; lastY = e.clientY;
        };
        const onUp = e => { dragging = false; canvas.classList.remove('dragging'); try { canvas.releasePointerCapture(e.pointerId); } catch (_) {} };
        const onWheel = e => { e.preventDefault(); zoomTarget = THREE.MathUtils.clamp(zoomTarget + e.deltaY * 0.0009, 0.72, 1.5); };
        canvas.addEventListener('pointerdown', onDown);
        canvas.addEventListener('pointermove', onMove);
        canvas.addEventListener('pointerup', onUp);
        canvas.addEventListener('pointercancel', onUp);
        canvas.addEventListener('wheel', onWheel, { passive: false });

        /* ================= Animation loop ================= */
        const clock = new THREE.Clock();
        let time = 0, raf = 0;
        const timeScale = reduceMotion ? 0 : 1;

        function frame() {
            raf = requestAnimationFrame(frame);
            const dt = Math.min(clock.getDelta(), 0.05);
            time += dt * timeScale;
            U.uTime.value = time;

            // Rotation: drag + inertia + auto-rotate
            if (dragging) {
                const v = (dragDX * 0.006) / Math.max(dt, 0.001);
                inertia = THREE.MathUtils.lerp(inertia, v, 0.35);
                spin.rotation.y += dragDX * 0.006;
                dragDX = 0;
            } else {
                inertia *= Math.pow(0.02, dt); // smooth decay
                spin.rotation.y += inertia * dt + CFG.autoSpeed * dt * timeScale;
            }

            orbits.forEach(o => { o.spinner.rotation.y += o.speed * dt * timeScale; });
            dustPts.rotation.y += 0.012 * dt * timeScale;
            updatePulses(time);

            // Smooth zoom
            zoom += (zoomTarget - zoom) * Math.min(1, dt * 5);
            camera.position.set(0, 0, baseDist * zoom);
            camera.lookAt(0, 0, 0);

            composer ? composer.render() : renderer.render(scene, camera);
        }
        frame();

        /* ================= Cleanup ================= */
        function dispose() {
            cancelAnimationFrame(raf);
            removeEventListener('resize', resize);
            canvas.removeEventListener('pointerdown', onDown);
            canvas.removeEventListener('pointermove', onMove);
            canvas.removeEventListener('pointerup', onUp);
            canvas.removeEventListener('pointercancel', onUp);
            canvas.removeEventListener('wheel', onWheel);
            scene.traverse(o => {
                if (o.geometry) o.geometry.dispose();
                if (o.material) { (Array.isArray(o.material) ? o.material : [o.material]).forEach(m => m.dispose()); }
            });
            renderer.dispose();
        }
        addEventListener('pagehide', dispose, { once: true });
    })();
    </script>
    @endverbatim

    <script>
    (() => {
        const password = document.getElementById('password');
        const toggle = document.getElementById('passwordToggle');
        const form = document.getElementById('fleetLoginForm');
        const button = document.getElementById('accessButton');
        const label = button && button.querySelector('.button-label');
        if (toggle && password) {
            toggle.addEventListener('click', () => {
                const visible = password.type === 'password';
                password.type = visible ? 'text' : 'password';
                toggle.classList.toggle('is-visible', visible);
                toggle.setAttribute('aria-pressed', String(visible));
                toggle.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
                password.focus({ preventScroll: true });
            });
        }
        if (form && button) {
            form.addEventListener('submit', () => {
                if (!form.checkValidity()) return;
                button.disabled = true;
                button.classList.add('is-loading');
                if (label) label.textContent = 'AUTHENTICATING FLEET ACCESS';
            });
        }
        document.querySelectorAll('.input-shell input').forEach(input => {
            const update = () => input.closest('.input-shell')?.classList.toggle('has-value', input.value.length > 0);
            input.addEventListener('input', update);
            update();
        });
    })();
    </script>

</body>
</html>