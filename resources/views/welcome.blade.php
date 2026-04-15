<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UnlockMyRadio - Car Radio Code Finder</title>
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0A0A0A; color: #fff; font-family: 'Inter', sans-serif; min-height: 100vh; }
        nav { display: flex; justify-content: space-between; align-items: center; padding: 20px 60px; border-bottom: 1px solid #1a1a1a; }
        .logo { font-family: 'Rajdhani', sans-serif; font-size: 26px; font-weight: 700; color: #F5C400; letter-spacing: 1px; }
        .logo span { color: #fff; }
        nav ul { display: flex; list-style: none; gap: 40px; }
        nav ul a { color: #aaa; text-decoration: none; font-size: 14px; transition: color 0.2s; }
        nav ul a:hover { color: #F5C400; }
        .hero { text-align: center; padding: 100px 20px 60px; }
        .hero h1 { font-family: 'Rajdhani', sans-serif; font-size: 64px; font-weight: 700; line-height: 1.1; margin-bottom: 20px; }
        .hero h1 span { color: #F5C400; }
        .hero p { color: #aaa; font-size: 18px; margin-bottom: 50px; }
        .search-box { background: #141414; border: 1px solid #2a2a2a; border-radius: 16px; padding: 40px; max-width: 700px; margin: 0 auto; }
        .search-box input { width: 100%; background: #0A0A0A; border: 2px solid #2a2a2a; border-radius: 10px; padding: 16px 20px; color: #fff; font-size: 18px; font-family: 'Rajdhani', sans-serif; letter-spacing: 1px; margin-bottom: 16px; transition: border-color 0.2s; }
        .search-box input:focus { outline: none; border-color: #F5C400; }
        .search-box input::placeholder { color: #555; }
        .btn { width: 100%; background: #F5C400; color: #0A0A0A; border: none; border-radius: 10px; padding: 16px; font-family: 'Rajdhani', sans-serif; font-size: 20px; font-weight: 700; cursor: pointer; transition: background 0.2s; letter-spacing: 1px; }
        .btn:hover { background: #FFD700; }
        .hint { color: #888; font-size: 13px; margin-top: 10px; line-height: 1.6; text-align: left; }
        .hint strong { color: #ddd; font-weight: 500; }
        .live-status { display: none; margin-top: 0; margin-bottom: 12px; border-radius: 10px; border: 1px solid #2a2a2a; padding: 10px 12px; text-align: left; font-size: 13px; line-height: 1.45; }
        .live-status.active { display: block; }
        .live-status.good { background: #0f1d11; border-color: #00C853; color: #8ef5b5; }
        .live-status.warn { background: #21190a; border-color: #F5C400; color: #ffd866; }
        .live-status.neutral { background: #101318; border-color: #2a2a2a; color: #adb3bd; }
        .error { background: #2a0a0a; border: 1px solid #ff3d00; color: #ff6b6b; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; text-align: left; }
        .stats { display: flex; justify-content: center; gap: 60px; padding: 60px 20px; }
        .stat { text-align: center; }
        .stat h2 { font-family: 'Rajdhani', sans-serif; font-size: 42px; font-weight: 700; color: #F5C400; }
        .stat p { color: #aaa; font-size: 14px; }
        .brands { padding: 60px 20px; text-align: center; }
        .brands h2 { font-family: 'Rajdhani', sans-serif; font-size: 32px; margin-bottom: 30px; }
        .brand-grid { display: flex; flex-wrap: wrap; justify-content: center; gap: 12px; max-width: 900px; margin: 0 auto; }
        .brand-tag { background: #141414; border: 1px solid #2a2a2a; padding: 8px 20px; border-radius: 20px; font-size: 14px; color: #ccc; }
        footer { text-align: center; padding: 40px; color: #444; font-size: 13px; border-top: 1px solid #1a1a1a; }
    </style>
</head>
<body>
<nav>
    <div class="logo">Unlock<span>MyRadio</span></div>
    <ul>
        <li><a href="#">Brands</a></li>
        <li><a href="#">How It Works</a></li>
        <li><a href="#">Pricing</a></li>
        <li><a href="#">Contact</a></li>
    </ul>
</nav>
<div class="hero">
    <h1>Unlock Your Car Radio<br><span>Instantly</span></h1>
    <p>Enter the full serial from your radio label. We auto-detect the correct lookup format.</p>
    <div class="search-box">
        @if(session('error'))
            <div class="error">{{ session('error') }}</div>
        @endif
        @if(config('unlock.direct_reveal'))
            <div class="error" style="background:#0f2a0f;border-color:#00C853;color:#7bf0a8;">
                Test mode: payment is disabled. After search, click <strong>PLAY &amp; REVEAL CODE</strong> to show the PIN.
            </div>
        @endif
        <form action="{{ route('search') }}" method="POST">
            @csrf
            <input id="serial-input" type="text" name="serial" placeholder="Enter full serial (e.g. AR670WA8078340, BE1492 Y0010001, A2C96189504000000400)" value="{{ old('serial') }}" autocomplete="off" required>
            <div id="serial-live-status" class="live-status neutral" aria-live="polite"></div>
            <button type="submit" class="btn">FIND MY CODE</button>
            <p class="hint"><strong>Tip:</strong> Always enter full serial. For Becker / Chrysler / Continental the system automatically uses last 4 or 5 digits when needed.</p>
        </form>
    </div>
</div>
<div class="stats">
    <div class="stat"><h2>30M+</h2><p>Radio Codes</p></div>
    <div class="stat"><h2>50+</h2><p>Car Brands</p></div>
    <div class="stat"><h2>Instant</h2><p>Delivery</p></div>
</div>
<div class="brands">
    <h2>Supported Brands</h2>
    <div class="brand-grid">
        <span class="brand-tag">Alfa Romeo</span>
        <span class="brand-tag">Audi</span>
        <span class="brand-tag">Chrysler</span>
        <span class="brand-tag">Fiat</span>
        <span class="brand-tag">Ford</span>
        <span class="brand-tag">General Motors</span>
        <span class="brand-tag">Honda</span>
        <span class="brand-tag">Land Rover</span>
        <span class="brand-tag">Mercedes-Benz</span>
        <span class="brand-tag">Mitsubishi</span>
        <span class="brand-tag">Nissan</span>
        <span class="brand-tag">Opel</span>
        <span class="brand-tag">Peugeot</span>
        <span class="brand-tag">Renault</span>
        <span class="brand-tag">Rover</span>
        <span class="brand-tag">Saab</span>
        <span class="brand-tag">Seat</span>
        <span class="brand-tag">Skoda</span>
        <span class="brand-tag">Suzuki</span>
        <span class="brand-tag">Volkswagen</span>
        <span class="brand-tag">Volvo</span>
    </div>
</div>
<footer>&copy; 2026 UnlockMyRadio.com - All rights reserved.</footer>
<script>
    (() => {
        const input = document.getElementById('serial-input');
        const status = document.getElementById('serial-live-status');
        if (!input || !status) return;

        const familyLabels = {
            fiat_bp_cm: 'Fiat BP/CM serial detected',
            fiat_visteon_m: 'Fiat Visteon M-serial detected',
            continental_vp: 'Continental VP1/VP2 detected',
            chrysler_t: 'Chrysler T-serial detected',
            becker: 'Becker serial detected',
            ford_m: 'M-serial detected (Ford / Fiat Visteon)',
            ford_v: 'Ford V-serial detected',
            vag: 'VAG serial detected',
            grundig_fiat: 'Grundig Fiat serial detected',
            philips_fiat: 'Philips Fiat serial detected',
            short_4digit: 'Short 4-digit input detected',
            short_5digit: 'Short 5-digit input detected',
            unknown: 'Pattern not recognized yet',
        };

        let debounceHandle = null;
        let requestId = 0;

        const setStatus = (text, tone = 'neutral') => {
            status.textContent = text;
            status.classList.add('active');
            status.classList.remove('good', 'warn', 'neutral');
            status.classList.add(tone);
        };

        const clearStatus = () => {
            status.textContent = '';
            status.classList.remove('active', 'good', 'warn', 'neutral');
            status.classList.add('neutral');
        };

        const render = (data) => {
            const family = data.family || 'unknown';
            const label = familyLabels[family] || 'Pattern detected';
            const lookup = data.lookup_serial ? `Lookup key: ${data.lookup_serial}.` : 'Lookup key pending.';
            const lookupMode = typeof data.lookup_mode === 'string' ? data.lookup_mode : '';

            if (family === 'short_4digit') {
                setStatus(`${label}. ${lookup} This can match multiple families. Enter the full serial for auto brand detection.`, 'warn');
                return;
            }

            if (family === 'short_5digit') {
                setStatus(`${label}. ${lookup} This can still be ambiguous. Enter more of the full serial if possible.`, 'warn');
                return;
            }

            if (family === 'unknown') {
                setStatus(`${label}. Keep typing the full label serial.`, 'neutral');
                return;
            }

            if (lookupMode.endsWith('_pending')) {
                setStatus(`${label}. ${lookup} Keep typing more of the full serial.`, 'warn');
                return;
            }

            setStatus(`${label}. ${lookup}`.trim(), 'good');
        };

        const classify = async (value, localRequestId) => {
            const url = new URL(@json(route('serial.classify')), window.location.origin);
            url.searchParams.set('serial', value);

            const response = await fetch(url.toString(), {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                },
            });

            if (localRequestId !== requestId) return;
            if (!response.ok) {
                clearStatus();
                return;
            }

            const payload = await response.json();
            if (!payload || payload.success !== true || !payload.data) {
                clearStatus();
                return;
            }

            render(payload.data);
        };

        input.addEventListener('input', () => {
            const value = input.value.trim();
            if (value.length < 3) {
                clearStatus();
                return;
            }

            if (debounceHandle) {
                clearTimeout(debounceHandle);
            }

            debounceHandle = setTimeout(() => {
                requestId += 1;
                classify(value, requestId).catch(() => {
                    clearStatus();
                });
            }, 350);
        });
    })();
</script>
</body>
</html>
