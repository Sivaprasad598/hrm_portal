{{-- An error page in the Epic HR look (theme1 404.html): the dark band with the
     card over its edge, the illustration on the right. Used by 403 and 404,
     which pass $code, $message and where "back" goes ($backUrl, or none).
     In the theme's colours (Theme Settings), like the sign-in page. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? $message }}</title>
    <link rel="stylesheet" href="{{ asset('css/epic/fonts.css') }}">
    <style>
        /* Template colours; the ones chosen under Theme Settings come after */
        :root {
            --epic-blue: #3d78e3;
            --epic-green: #67b173;
            --epic-dark: #212529;
            --epic-text: #1d1d1f;
            --epic-muted: #6e6e73;
            --err-panel: 400px;
        }
    </style>
    @include('sections.epic-colors')
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; }
        body {
            font-family: 'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif;
            color: var(--epic-text);
            background: #f6f6f8;
            display: flex;
            align-items: center;
        }
        /* The dark band, lit faintly in the theme's two colours, with a fine
           rule down its edge - as on the sign-in page */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            width: var(--err-panel);
            background:
                radial-gradient(520px 420px at 20% 20%, color-mix(in srgb, var(--epic-blue) 22%, transparent), transparent 70%),
                radial-gradient(420px 360px at 95% 100%, color-mix(in srgb, var(--epic-green) 16%, transparent), transparent 70%),
                var(--epic-dark);
        }
        body::after {
            content: '';
            position: fixed;
            top: 0;
            bottom: 0;
            left: var(--err-panel);
            width: 3px;
            background: linear-gradient(180deg, transparent, var(--epic-blue) 30%, var(--epic-green) 70%, transparent);
        }
        .error-art {
            position: fixed;
            top: 50%;
            right: 6%;
            width: 38%;
            max-width: 560px;
            transform: translateY(-50%);
            pointer-events: none;
        }
        .error-art svg { width: 100%; height: auto; display: block; }
        .error-card {
            position: relative;
            z-index: 1;
            margin-left: 170px;
            width: 380px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 18px 40px rgba(17, 17, 17, .12), 0 1px 2px rgba(17, 17, 17, .08);
            padding: 40px;
        }
        .error-code { font-size: 64px; font-weight: 300; color: #9a9aa2; line-height: 1; margin-bottom: 30px; }
        .error-code span { font-size: 40px; margin-right: 8px; vertical-align: 6px; color: var(--epic-blue); font-weight: 600; }
        h1 {
            font-size: 22px;
            font-weight: 600;
            margin: 0 0 16px;
            padding-bottom: 14px;
            position: relative;
        }
        h1::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 44px;
            height: 3px;
            border-radius: 2px;
            background: linear-gradient(90deg, var(--epic-blue), var(--epic-green));
        }
        p { color: var(--epic-muted); font-size: 15px; margin: 0 0 26px; }
        .btn {
            display: inline-block;
            color: #fff;
            text-decoration: none;
            font-size: 15px;
            font-weight: 600;
            padding: 11px 22px;
            border-radius: 6px;
            background: linear-gradient(135deg, var(--epic-blue), color-mix(in srgb, var(--epic-blue) 65%, var(--epic-green)));
            box-shadow: 0 8px 18px color-mix(in srgb, var(--epic-blue) 30%, transparent);
            transition: transform .15s, box-shadow .15s, filter .15s;
        }
        .btn:hover { filter: brightness(1.06); transform: translateY(-1px); }
        @media (max-width: 991.98px) {
            body { justify-content: center; }
            body::before, body::after, .error-art { display: none; }
            .error-card { margin: 0 16px; }
        }
    </style>
</head>
<body>
    <div class="error-art">@include('sections.epic-login-art')</div>
    <div class="error-card">
        <div class="error-code"><span>!</span>{{ $code }}</div>
        <h1>{{ $heading }}</h1>
        <p>{{ $message }}</p>
        @if ($backUrl)
            <a class="btn" href="{{ $backUrl }}">&larr; {{ $backText }}</a>
        @endif
    </div>
</body>
</html>
