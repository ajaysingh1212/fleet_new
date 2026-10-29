<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ config('app.name', 'FleetNova Fleet') }}</title>

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

    </style>
    @endverbatim
</head>
<body>
    <section class="globe-section" aria-label="Global network">
        <canvas id="globe"></canvas>
        <div class="ambient"></div>
        <div class="vignette"></div>

        <div class="scanline"></div>
        <header class="topbar">
            <div class="brand">
                <div class="brand-mark" aria-hidden="true">✦</div>
                <div>
                    <div class="brand-name">{{ strtoupper(config('app.name', 'FleetNova')) }}</div>
                    <div class="brand-sub">Global Fleet Intelligence</div>
                </div>
            </div>
            <div class="top-actions">
                <div class="system-pill"><span class="live-dot"></span> GLOBAL NETWORK ONLINE</div>
                <a class="login-btn" href="{{ url('/login') }}" aria-label="Login to fleet dashboard">
                    <span>Login Portal</span><span class="login-arrow" aria-hidden="true">↗</span>
                </a>
            </div>
        </header>

        <svg class="route-overlay" viewBox="0 0 1440 900" preserveAspectRatio="none" aria-hidden="true">
            <path class="route-path" d="M 730 300 Q 880 160 1010 280 T 1190 470"/>
            <path class="route-path" d="M 700 560 Q 850 690 1030 520 T 1200 600" style="animation-delay:-5s;opacity:.55"/>
            <path class="route-path" d="M 660 360 Q 560 470 720 620" style="animation-delay:-9s;opacity:.4"/>
        </svg>

        <div class="hero-copy">
            <div class="eyebrow">Intelligent fleet ecosystem</div>
            <h1>Every mile.<br><span>In control.</span></h1>
            <p>Track vehicles, connect your fleet, and see your world in motion — all from one intelligent platform.</p>
            <div class="hero-line"><span class="line"></span> Real-time visibility · Smarter journeys</div>
        </div>

        <div class="fleet-marker fleet-a"><span class="vehicle">↗</span><span>Vehicle 024<span class="trail"></span></span></div>
        <div class="fleet-marker fleet-b"><span class="vehicle">↗</span><span>Vehicle 108<span class="trail"></span></span></div>
        <div class="fleet-marker fleet-c"><span class="vehicle">↗</span><span>Vehicle 067<span class="trail"></span></span></div>

        <div class="hud-card hud-one">
            <div class="hud-label">Connected vehicles</div>
            <div class="hud-value"><span id="vehicle-count">1,284</span><small>● LIVE</small></div>
            <div class="hud-note">Across your global fleet</div>
            <div class="mini-bars" aria-hidden="true">
                <i style="height:45%"></i><i style="height:70%"></i><i style="height:54%"></i><i style="height:88%"></i><i style="height:63%"></i><i style="height:100%"></i><i style="height:75%"></i><i style="height:92%"></i><i style="height:58%"></i><i style="height:80%"></i>
            </div>
        </div>
        <div class="hud-card hud-two">
            <div class="hud-label">Network coverage</div>
            <div class="hud-value">Global <small>● ACTIVE</small></div>
            <div class="hud-note">GPS-powered fleet visibility</div>
        </div>
        <div class="hud-card hud-three">
            <div class="hud-label">Tracking intelligence</div>
            <div class="hud-value">24 <small>/ 7</small></div>
            <div class="hud-note">Routes · Signals · Fleet insights</div>
        </div>

        <footer class="bottom-bar">
            <div class="bottom-caption">FleetNova · Fleet intelligence system</div>
            <div class="bottom-stats">
                <span><i class="live-dot"></i> System <b>Operational</b></span>
                <span>GPS <b>Connected</b></span>
                <span>Secure <b>Network</b></span>
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
</body>
</html>