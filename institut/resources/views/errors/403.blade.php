@php
    $dir = app()->getLocale() === 'ar' ? 'rtl' : 'ltr';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('general.forbidden_title') }}</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Cairo', 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg, #00B7EB 0%, #2DD4BF 100%);
            color: #0F172A;
        }
        .card {
            background: rgba(255, 255, 255, 0.96);
            border-radius: 1.25rem;
            padding: 2.5rem 3rem;
            max-width: 26rem;
            text-align: center;
            box-shadow: 0 24px 60px -20px rgba(2, 44, 60, 0.35);
        }
        .code { font-size: 3rem; font-weight: 800; margin: 0 0 .25rem; }
        h1 { font-size: 1.25rem; margin: 0 0 .75rem; }
        p { margin: 0 0 1.5rem; line-height: 1.7; opacity: .85; }
        a {
            display: inline-block;
            padding: .6rem 1.5rem;
            border-radius: .75rem;
            text-decoration: none;
            color: #fff;
            background: linear-gradient(135deg, #00B7EB, #2DD4BF);
            box-shadow: 0 10px 20px -10px rgba(0, 183, 235, 0.55);
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="code">403</div>
        <h1>{{ __('general.forbidden_title') }}</h1>
        <p>{{ __('general.forbidden_body') }}</p>
        <a href="{{ url('/admin') }}">{{ __('general.back_dashboard') }}</a>
    </div>
</body>
</html>
