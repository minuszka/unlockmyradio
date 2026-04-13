<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Radio Model - UnlockMyRadio</title>
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0A0A0A; color: #fff; font-family: 'Inter', sans-serif; min-height: 100vh; }
        nav { display: flex; justify-content: space-between; align-items: center; padding: 20px 60px; border-bottom: 1px solid #1a1a1a; }
        .logo { font-family: 'Rajdhani', sans-serif; font-size: 26px; font-weight: 700; color: #F5C400; letter-spacing: 1px; text-decoration: none; }
        .logo span { color: #fff; }
        .container { max-width: 760px; margin: 60px auto; padding: 0 20px; text-align: center; }
        .box { background: #141414; border: 1px solid #2a2a2a; border-radius: 16px; padding: 40px; }
        .badge { background: #1a2a1a; border: 1px solid #00C853; color: #00C853; padding: 6px 16px; border-radius: 20px; font-size: 13px; display: inline-block; margin-bottom: 24px; }
        h1 { font-family: 'Rajdhani', sans-serif; font-size: 32px; font-weight: 700; margin-bottom: 8px; }
        .subtitle { color: #aaa; font-size: 15px; margin-bottom: 20px; }
        .serial-display { background: #0A0A0A; border: 1px solid #2a2a2a; border-radius: 10px; padding: 14px; margin-bottom: 20px; font-family: 'Rajdhani', sans-serif; font-size: 18px; letter-spacing: 1px; color: #aaa; }
        .help-box { background: #0f0f0f; border: 1px solid #2a2a2a; border-radius: 10px; padding: 14px; text-align: left; margin-bottom: 20px; color: #bbb; font-size: 13px; line-height: 1.6; }
        .help-box strong { color: #F5C400; font-weight: 600; }
        .error { background: #2a0a0a; border: 1px solid #ff3d00; color: #ff6b6b; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; text-align: left; font-size: 14px; }
        .options { display: flex; flex-direction: column; gap: 12px; margin-bottom: 30px; text-align: left; }
        .option-label { display: flex; align-items: flex-start; gap: 14px; background: #0A0A0A; border: 2px solid #2a2a2a; border-radius: 10px; padding: 14px 18px; cursor: pointer; transition: border-color 0.2s; }
        .option-label:hover { border-color: #F5C400; }
        .option-label input[type="radio"] { accent-color: #F5C400; width: 18px; height: 18px; margin-top: 4px; flex-shrink: 0; }
        .option-label:has(input:checked) { border-color: #F5C400; }
        .option-text { display: flex; flex-direction: column; gap: 3px; }
        .option-brand { font-family: 'Rajdhani', sans-serif; font-size: 18px; font-weight: 600; color: #fff; }
        .option-make { font-size: 13px; color: #999; }
        .option-hint { font-size: 12px; color: #ccc; }
        .lookup-key { font-size: 12px; color: #777; letter-spacing: 1px; }
        .btn { width: 100%; background: #F5C400; color: #0A0A0A; border: none; border-radius: 10px; padding: 16px; font-family: 'Rajdhani', sans-serif; font-size: 20px; font-weight: 700; cursor: pointer; transition: background 0.2s; letter-spacing: 1px; }
        .btn:hover { background: #FFD700; }
        .back-link { display: block; margin-top: 20px; color: #555; text-decoration: none; font-size: 14px; }
        .back-link:hover { color: #aaa; }
    </style>
</head>
<body>
@php
    $hasBecker = $options->contains(fn ($option) => strcasecmp($option->brand, 'Becker') === 0);
    $hasChrysler = $options->contains(fn ($option) => strcasecmp($option->brand, 'Chrysler') === 0);
    $hasContinental = $options->contains(fn ($option) => strcasecmp($option->brand, 'Continental') === 0);
    $lookupSerial = optional($options->first())->serial;
@endphp
<nav>
    <a href="/" class="logo">Unlock<span>MyRadio</span></a>
</nav>
<div class="container">
    <div class="box">
        <div class="badge">Code Found in Database</div>
        <h1>Select Your Radio Variant</h1>
        <p class="subtitle">We found multiple possible matches for this serial. Choose the one that matches your unit.</p>
        <div class="serial-display">{{ $serial }}</div>
        @if(session('error'))
            <div class="error">{{ session('error') }}</div>
        @endif

        @if($hasBecker)
            <div class="help-box"><strong>Becker rule:</strong> full serial is detected automatically, lookup uses the last 4 digits ({{ $lookupSerial }}). Choose by button count: 4, 6, or 8.</div>
        @elseif($hasChrysler && $hasContinental)
            <div class="help-box"><strong>Chrysler/Continental overlap:</strong> both families can map to the same short lookup key. Choose the exact radio family and button layout shown on your front panel.</div>
        @elseif($hasChrysler)
            <div class="help-box"><strong>Chrysler rule:</strong> full serial was auto-parsed. If your radio has presets 1-5 choose 5-button; if it has 1-6 choose 6-button.</div>
        @elseif($hasContinental)
            <div class="help-box"><strong>Continental rule:</strong> for A2C/A3C serials we use the last 4 digits ({{ $lookupSerial }}) from your full label serial.</div>
        @endif

        <form action="{{ route('search.select') }}" method="POST">
            @csrf
            <input type="hidden" name="serial" value="{{ $serial }}">
            <div class="options">
                @foreach($options as $option)
                    @php
                        $lower = strtolower($option->car_make);
                        $buttonHint = null;
                        if (str_contains($lower, '4 buttons')) $buttonHint = 'Preset buttons: 1 2 3 4';
                        if (str_contains($lower, '5 buttons')) $buttonHint = 'Preset buttons: 1 2 3 4 5';
                        if (str_contains($lower, '6 buttons')) $buttonHint = 'Preset buttons: 1 2 3 4 5 6';
                        if (str_contains($lower, '8 buttons')) $buttonHint = 'Preset buttons: 1 2 3 4 5 6 7 8';
                        if (str_contains($lower, '4-digit lookup')) $buttonHint = 'Lookup uses last 4 digits from full serial';
                        if (str_contains($lower, 'vp1/vp2')) $buttonHint = 'Lookup uses last 4 digits from A2C/A3C serial';
                    @endphp
                    <label class="option-label">
                        <input type="radio" name="radio_code_id" value="{{ $option->id }}" required>
                        <div class="option-text">
                            <span class="option-brand">{{ $option->brand }}</span>
                            <span class="option-make">{{ $option->car_make }}</span>
                            @if($buttonHint)
                                <span class="option-hint">{{ $buttonHint }}</span>
                            @endif
                            <span class="lookup-key">Lookup key: {{ $option->serial }}</span>
                        </div>
                    </label>
                @endforeach
            </div>
            <button type="submit" class="btn">CONTINUE</button>
        </form>
        <a href="/" class="back-link">Search another serial</a>
    </div>
</div>
</body>
</html>
