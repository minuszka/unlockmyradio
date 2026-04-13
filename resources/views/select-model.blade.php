<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Radio Model — UnlockMyRadio</title>
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0A0A0A; color: #fff; font-family: 'Inter', sans-serif; min-height: 100vh; }
        nav { display: flex; justify-content: space-between; align-items: center; padding: 20px 60px; border-bottom: 1px solid #1a1a1a; }
        .logo { font-family: 'Rajdhani', sans-serif; font-size: 26px; font-weight: 700; color: #F5C400; letter-spacing: 1px; text-decoration: none; }
        .logo span { color: #fff; }
        .container { max-width: 600px; margin: 80px auto; padding: 0 20px; text-align: center; }
        .box { background: #141414; border: 1px solid #2a2a2a; border-radius: 16px; padding: 40px; }
        .badge { background: #1a2a1a; border: 1px solid #00C853; color: #00C853; padding: 6px 16px; border-radius: 20px; font-size: 13px; display: inline-block; margin-bottom: 24px; }
        h1 { font-family: 'Rajdhani', sans-serif; font-size: 32px; font-weight: 700; margin-bottom: 8px; }
        .subtitle { color: #aaa; font-size: 15px; margin-bottom: 30px; }
        .serial-display { background: #0A0A0A; border: 1px solid #2a2a2a; border-radius: 10px; padding: 14px; margin-bottom: 30px; font-family: 'Rajdhani', sans-serif; font-size: 18px; letter-spacing: 2px; color: #888; }
        .options { display: flex; flex-direction: column; gap: 12px; margin-bottom: 30px; text-align: left; }
        .option-label { display: flex; align-items: center; gap: 14px; background: #0A0A0A; border: 2px solid #2a2a2a; border-radius: 10px; padding: 16px 20px; cursor: pointer; transition: border-color 0.2s; }
        .option-label:hover { border-color: #F5C400; }
        .option-label input[type="radio"] { accent-color: #F5C400; width: 18px; height: 18px; flex-shrink: 0; }
        .option-label input[type="radio"]:checked + .option-text .option-brand { color: #F5C400; }
        .option-label:has(input:checked) { border-color: #F5C400; }
        .option-text { display: flex; flex-direction: column; gap: 2px; }
        .option-brand { font-family: 'Rajdhani', sans-serif; font-size: 18px; font-weight: 600; color: #fff; }
        .option-make { font-size: 13px; color: #888; }
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
    <div class="box">
        <div class="badge">✓ Code Found in Database</div>
        <h1>Select Your Radio Model</h1>
        <p class="subtitle">Multiple radio models found for this serial.<br>Select the one that matches your radio.</p>
        <div class="serial-display">{{ $serial }}</div>
        <form action="{{ route('search.select') }}" method="POST">
            @csrf
            <input type="hidden" name="serial" value="{{ $serial }}">
            <div class="options">
                @foreach($options as $option)
                    <label class="option-label">
                        <input type="radio" name="radio_code_id" value="{{ $option->id }}" required>
                        <div class="option-text">
                            <span class="option-brand">{{ $option->brand }}</span>
                            <span class="option-make">{{ $option->car_make }}</span>
                        </div>
                    </label>
                @endforeach
            </div>
            <button type="submit" class="btn">CONTINUE →</button>
        </form>
        <a href="/" class="back-link">← Search another serial</a>
    </div>
</div>
</body>
</html>
