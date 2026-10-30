{{-- An error page in the Epic HR look (theme1 404.html): the dark band with the
     card over its edge, the illustration on the right. Used by 403 and 404,
     which pass $code, $message and where "back" goes ($backUrl, or none). --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? $message }}</title>
    <link rel="stylesheet" href="{{ asset('css/epic/fonts.css') }}">
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; }
        body {
            font-family: 'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif;
            color: #212529;
            background: #f3f3f9 url({{ asset('css/epic/login-illustration.svg') }}) no-repeat 78% 45% / 34% auto;
            display: flex;
            align-items: center;
        }
        body::before {
            content: '';
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            width: 400px;
            background: #212529;
        }
        .error-card {
            position: relative;
            margin-left: 170px;
            width: 360px;
            background: #fff;
            border-radius: 2px;
            box-shadow: 0 1px 2px rgba(56, 65, 74, .15);
            padding: 40px;
        }
        .error-code { font-size: 64px; font-weight: 300; color: #878a99; line-height: 1; margin-bottom: 36px; }
        .error-code span { font-size: 40px; margin-right: 8px; vertical-align: 6px; }
        h1 {
            font-size: 22px;
            font-weight: 700;
            margin: 0 0 14px;
            background: linear-gradient(145deg, #3d78e3, #67b173);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        p { color: #878a99; font-size: 15px; margin: 0 0 22px; }
        .btn {
            display: inline-block;
            background: #3d78e3;
            color: #fff;
            text-decoration: none;
            font-size: 15px;
            padding: 9px 18px;
            border-radius: 2px;
        }
        .btn:hover { background: #2f66c8; }
        @media (max-width: 991.98px) {
            body { background-image: none; justify-content: center; }
            body::before { display: none; }
            .error-card { margin: 0 16px; }
        }
    </style>
</head>
<body>
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
