<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Code Found — UnlockMyRadio</title>
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0A0A0A; color: #fff; font-family: 'Inter', sans-serif; min-height: 100vh; }
        nav { display: flex; justify-content: space-between; align-items: center; padding: 20px 60px; border-bottom: 1px solid #1a1a1a; }
        .logo { font-family: 'Rajdhani', sans-serif; font-size: 26px; font-weight: 700; color: #F5C400; letter-spacing: 1px; text-decoration: none; }
        .logo span { color: #fff; }
        .container { max-width: 600px; margin: 80px auto; padding: 0 20px; text-align: center; }
        .found-box { background: #141414; border: 1px solid #2a2a2a; border-radius: 16px; padding: 40px; }
        .badge { background: #1a2a1a; border: 1px solid #00C853; color: #00C853; padding: 6px 16px; border-radius: 20px; font-size: 13px; display: inline-block; margin-bottom: 24px; }
        h1 { font-family: 'Rajdhani', sans-serif; font-size: 36px; font-weight: 700; margin-bottom: 8px; }
        .car-info { color: #aaa; font-size: 16px; margin-bottom: 30px; }
        .car-info span { color: #F5C400; font-weight: 500; }
        .serial-display { background: #0A0A0A; border: 1px solid #2a2a2a; border-radius: 10px; padding: 16px; margin-bottom: 30px; font-family: 'Rajdhani', sans-serif; font-size: 18px; letter-spacing: 2px; color: #888; }
        .code-preview { background: #0A0A0A; border: 2px dashed #2a2a2a; border-radius: 10px; padding: 30px; margin-bottom: 30px; }
        .code-preview p { color: #aaa; font-size: 14px; margin-bottom: 10px; }
        .code-hidden { font-family: 'Rajdhani', sans-serif; font-size: 48px; font-weight: 700; color: #333; letter-spacing: 8px; }
        .code-hidden.revealed { color: #F5C400; letter-spacing: 6px; }
        .price-tag { color: #F5C400; font-size: 14px; margin-bottom: 20px; }
        .email-input { width: 100%; background: #0A0A0A; border: 2px solid #2a2a2a; border-radius: 10px; padding: 14px 20px; color: #fff; font-size: 16px; font-family: 'Inter', sans-serif; margin-bottom: 16px; transition: border-color 0.2s; }
        .email-input:focus { outline: none; border-color: #F5C400; }
        .email-input::placeholder { color: #444; }
        .btn { width: 100%; background: #F5C400; color: #0A0A0A; border: none; border-radius: 10px; padding: 16px; font-family: 'Rajdhani', sans-serif; font-size: 20px; font-weight: 700; cursor: pointer; transition: background 0.2s; letter-spacing: 1px; }
        .btn:hover { background: #FFD700; }
        .back-link { display: block; margin-top: 20px; color: #555; text-decoration: none; font-size: 14px; }
        .back-link:hover { color: #aaa; }
    </style>
</head>
<body>
<nav>
    <a href="/" class="logo">🔓 Unlock<span>MyRadio</span></a>
</nav>
<div class="container">
    <div class="found-box">
        <div class="badge">✓ Code Found in Database</div>
        <h1>Your Code is Ready!</h1>
        <p class="car-info">Radio: <span>{{ $brand }}</span> — Vehicle: <span>{{ $car_make }}</span></p>
        <div class="serial-display">{{ $serial }}</div>
        <div class="code-preview">
            <p>Your unlock code:</p>
            <div class="code-hidden">● ● ● ●</div>
        </div>
        @if($direct_reveal_enabled ?? false)
            <p class="price-tag">Test mode active: no payment required.</p>
        @else
            <p class="price-tag">Unlock for just $2.99</p>
        @endif
        @if($direct_reveal_enabled ?? false)
            <button id="reveal-code-btn" type="button" class="btn" data-code="{{ $direct_code ?? '' }}">
                PLAY &amp; REVEAL CODE
            </button>
        @else
            <form action="{{ route('checkout') }}" method="POST">
                @csrf
                <input type="hidden" name="serial" value="{{ $serial }}">
                <input type="hidden" name="radio_code_id" value="{{ $radio_code_id }}">
                <input type="email" name="email" class="email-input" placeholder="Your email address" required>
                <button type="submit" class="btn">💳 PAY &amp; REVEAL CODE</button>
            </form>
        @endif
        <a href="/" class="back-link">← Search another serial</a>
    </div>
</div>
@if($direct_reveal_enabled ?? false)
<script>
    (() => {
        const btn = document.getElementById('reveal-code-btn');
        const codeEl = document.querySelector('.code-hidden');
        if (!btn || !codeEl) return;

        btn.addEventListener('click', () => {
            const code = (btn.dataset.code || '').trim();
            if (!code) return;

            codeEl.textContent = code;
            codeEl.classList.add('revealed');
            btn.textContent = 'CODE REVEALED';
            btn.disabled = true;
            btn.style.opacity = '0.8';
            btn.style.cursor = 'default';
        });
    })();
</script>
@endif
</body>
</html>
