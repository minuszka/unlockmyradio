<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Code — UnlockMyRadio</title>
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0A0A0A; color: #fff; font-family: 'Inter', sans-serif; min-height: 100vh; }
        nav { display: flex; justify-content: space-between; align-items: center; padding: 20px 60px; border-bottom: 1px solid #1a1a1a; }
        .logo { font-family: 'Rajdhani', sans-serif; font-size: 26px; font-weight: 700; color: #F5C400; letter-spacing: 1px; text-decoration: none; }
        .logo span { color: #fff; }
        .container { max-width: 600px; margin: 80px auto; padding: 0 20px; text-align: center; }
        .success-box { background: #141414; border: 1px solid #00C853; border-radius: 16px; padding: 40px; }
        .badge { background: #1a2a1a; border: 1px solid #00C853; color: #00C853; padding: 6px 16px; border-radius: 20px; font-size: 13px; display: inline-block; margin-bottom: 24px; }
        h1 { font-family: 'Rajdhani', sans-serif; font-size: 36px; font-weight: 700; margin-bottom: 8px; }
        .car-info { color: #aaa; font-size: 16px; margin-bottom: 30px; }
        .car-info span { color: #F5C400; font-weight: 500; }
        .code-box { background: #0A0A0A; border: 2px solid #F5C400; border-radius: 16px; padding: 40px; margin: 30px 0; }
        .code-box p { color: #aaa; font-size: 14px; margin-bottom: 16px; }
        .code { font-family: 'Rajdhani', sans-serif; font-size: 64px; font-weight: 700; color: #F5C400; letter-spacing: 12px; }
        .serial-display { color: #555; font-size: 14px; margin-bottom: 30px; letter-spacing: 1px; }
        .instructions { background: #0f0f0f; border: 1px solid #2a2a2a; border-radius: 10px; padding: 20px; text-align: left; margin-bottom: 24px; }
        .instructions h3 { font-family: 'Rajdhani', sans-serif; font-size: 18px; margin-bottom: 12px; color: #F5C400; }
        .instructions ol { padding-left: 20px; color: #aaa; font-size: 14px; line-height: 2; }
        .btn { display: block; background: #141414; color: #aaa; border: 1px solid #2a2a2a; border-radius: 10px; padding: 14px; font-family: 'Rajdhani', sans-serif; font-size: 16px; cursor: pointer; text-decoration: none; transition: all 0.2s; margin-top: 16px; }
        .btn:hover { border-color: #F5C400; color: #F5C400; }
    </style>
</head>
<body>
<nav>
    <a href="/" class="logo">🔓 Unlock<span>MyRadio</span></a>
</nav>
<div class="container">
    <div class="success-box">
        <div class="badge">✓ Payment Successful</div>
        <h1>Here Is Your Code!</h1>
        <p class="car-info">Radio: <span>{{ $brand }}</span> — Vehicle: <span>{{ $car_make }}</span></p>
        <p class="serial-display">Serial: {{ $serial }}</p>
        <div class="code-box">
            <p>Your unlock code is:</p>
            <div class="code">{{ $code }}</div>
        </div>
        <div class="instructions">
            <h3>How to enter your code:</h3>
            <ol>
                <li>Turn on your car radio</li>
                <li>The display will show "CODE" or "SAFE"</li>
                <li>Enter the code using the radio buttons</li>
                <li>Press OK or confirm — your radio is unlocked!</li>
            </ol>
        </div>
        <a href="/" class="btn">← Search another code</a>
    </div>
</div>
</body>
</html>

