<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', __('general.receipt')) — {{ $settings->localized_name }}</title>
    <style>
        @font-face {
            font-family: 'Cairo';
            font-style: normal;
            font-weight: 400 800;
            font-display: swap;
            src: url('{{ asset('fonts/Cairo/SLXVc1nY6HkvangtZmpQdkhzfH5lkSscQyyS4J0.woff2') }}') format('woff2'),
                 url('{{ asset('fonts/Cairo/SLXVc1nY6HkvangtZmpQdkhzfH5lkSscSCyS4J0.woff2') }}') format('woff2'),
                 url('{{ asset('fonts/Cairo/SLXVc1nY6HkvangtZmpQdkhzfH5lkSscRiyS.woff2') }}') format('woff2');
            unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD, U+0600-06FF, U+0750-077F, U+0870-088E, U+0890-0891, U+0897-08E1, U+08E3-08FF, U+200C-200E, U+2010-2011, U+204F, U+2E41, U+FB50-FDFF, U+FE70-FE74, U+FE76-FEFC, U+102E0-102FB, U+10E60-10E7E, U+10EC2-10EC4, U+10EFC-10EFF, U+1EE00-1EE03, U+1EE05-1EE1F, U+1EE21-1EE22, U+1EE24, U+1EE27, U+1EE29-1EE32, U+1EE34-1EE37, U+1EE39, U+1EE3B, U+1EE42, U+1EE47, U+1EE49, U+1EE4B, U+1EE4D-1EE4F, U+1EE51-1EE52, U+1EE54, U+1EE57, U+1EE59, U+1EE5B, U+1EE5D, U+1EE5F, U+1EE61-1EE62, U+1EE64, U+1EE67-1EE6A, U+1EE6C-1EE72, U+1EE74-1EE77, U+1EE79-1EE7C, U+1EE7E, U+1EE80-1EE89, U+1EE8B-1EE9B, U+1EEA1-1EEA3, U+1EEA5-1EEA9, U+1EEAB-1EEBB, U+1EEF0-1EEF1, U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Cairo', 'Segoe UI', Tahoma, sans-serif;
            color: #1e293b;
            background: #f1f5f9;
            font-size: 13px;
            line-height: 1.6;
        }
        .page { max-width: 800px; margin: 20px auto; background: #fff; padding: 28px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.1); position: relative; z-index: 1; }
        .page::before { -webkit-print-color-adjust: exact; print-color-adjust: exact;
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background-image: url('{{ $settings->logo_path ? asset(\Illuminate\Support\Facades\Storage::url($settings->logo_path)) : "" }}');
            background-repeat: no-repeat;
            background-position: center center;
            background-size: 300px;
            opacity: 0.03;
            z-index: -1;
            pointer-events: none;
        }
        .no-print { position: sticky; top: 0; z-index: 10; display: flex; justify-content: flex-end; gap: 8px; padding: 12px; }
        .no-print button {
            font-family: inherit; font-size: 14px; font-weight: 700; color: #fff;
            background: #00B7EB; border: 0; border-radius: 8px; padding: 10px 22px; cursor: pointer;
        }
        .no-print button:hover { background: #009fd0; }
        .header { display: flex; flex-direction: column; align-items: center; justify-content: center; border-bottom: 3px solid #00B7EB; padding-bottom: 14px; margin-bottom: 18px; text-align: center; }
        .header-logo { display: flex; flex-direction: column; align-items: center; gap: 8px; }
        .header-logo img { height: 72px; width: 72px; object-fit: contain; }
        .header-name { font-size: 24px; font-weight: 800; color: #0f172a; }
        .header-meta { margin-top: 8px; font-size: 13px; color: #64748b; display: flex; gap: 16px; justify-content: center; }
        /* [dir="rtl"] .header-meta { text-align: right; } */
        h2.title { text-align: center; color: #0f172a; margin-bottom: 16px; font-size: 16px; font-weight: 800; }
        table { width: 100%; border-collapse: collapse; margin: 12px 0; }
        th, td { border: 1px solid #e2e8f0; padding: 8px 10px; text-align: left; }
        [dir="rtl"] th, [dir="rtl"] td { text-align: right; }
        th { background: #f8fafc; font-weight: 700; color: #334155; }
        .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px 24px; margin: 12px 0; }
        .info-grid .item { display: flex; gap: 8px; }
        .info-grid .label { color: #64748b; min-width: 110px; }
        .info-grid .value { font-weight: 700; color: #0f172a; }
        .totals { margin-top: 14px; text-align: left; }
        [dir="rtl"] .totals { text-align: right; }
        .total-line { display: flex; justify-content: space-between; padding: 6px 0; font-size: 14px; }
        .total-line.grand { font-weight: 800; font-size: 16px; border-top: 2px solid #00B7EB; margin-top: 4px; padding-top: 10px; color: #0f172a; }
        .receipt-head { text-align: center; margin-bottom: 14px; }
        .receipt-number { display: inline-flex; align-items: center; gap: 10px; border: 3px solid #00B7EB; border-radius: 12px; padding: 10px 26px; background: #f0faff; }
        .receipt-label { font-size: 14px; font-weight: 700; color: #64748b; }
        .receipt-value { font-size: 26px; font-weight: 800; color: #0f172a; letter-spacing: 1px; }
        .signature-row { display: flex; gap: 48px; margin-top: 40px; }
        .signature { flex: 1; display: flex; flex-direction: column; gap: 6px; }
        .signature-label { font-size: 12px; font-weight: 700; color: #334155; }
        .signature-line { border-bottom: 1px solid #94a3b8; height: 34px; }
        .stamp-box { border: 2px dashed #94a3b8; border-radius: 8px; height: 46px; }
        .footer { margin-top: 24px; border-top: 1px dashed #cbd5e1; padding-top: 12px; font-size: 11px; color: #94a3b8; display: flex; justify-content: space-between; }
        .empty { text-align: center; color: #94a3b8; padding: 24px; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .badge.success { background: #dcfce7; color: #15803d; }
        .badge.danger { background: #fee2e2; color: #b91c1c; }
        .badge.warning { background: #fef3c7; color: #b45309; }
        .badge.info { background: #e0f2fe; color: #0369a1; }
        .badge.gray { background: #f1f5f9; color: #475569; }
        .amount-danger { color: #dc2626; font-weight: 700; }
        .amount-success { color: #16a34a; font-weight: 700; }
        .card-wrap { display: flex; justify-content: center; padding: 20px 0; }
        .id-card {
            width: 85.6mm; min-height: 54mm; background: #fff; border: 2px solid #00B7EB; border-radius: 6mm;
            overflow: hidden; display: flex; flex-direction: column; padding: 12px; gap: 8px;
        }
        .id-card-header { display: flex; align-items: center; gap: 8px; }
        .id-card-logo { height: 24px; width: 24px; object-fit: contain; }
        .id-card-institute { flex: 1; }
        .id-card-name { font-size: 12px; font-weight: 800; color: #0f172a; }
        .id-card-sub { font-size: 8px; color: #00B7EB; font-weight: 700; }
        .id-card-body { display: flex; gap: 10px; flex: 1; }
        .id-card-photo-wrap { width: 88px; height: 105px; overflow: hidden; border-radius: 4px; background: #f1f5f9; flex-shrink: 0; }
        .id-card-photo { width: 100%; height: 100%; object-fit: cover; }
        .id-card-photo-placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: 800; color: #00B7EB; background: linear-gradient(135deg, #e0f2fe, #ccfbf1); }
        .id-card-info { flex: 1; display: flex; flex-direction: column; justify-content: center; gap: 3px; }
        .id-card-row { display: flex; gap: 6px; align-items: baseline; }
        .id-card-label { font-size: 8px; color: #64748b; min-width: 52px; }
        .id-card-value { font-size: 10px; font-weight: 700; color: #0f172a; }
        .id-card-footer { text-align: center; font-size: 8px; color: #64748b; border-top: 1px dashed #cbd5e1; padding-top: 4px; }
        .id-card-stamp { text-align: center; padding-top: 4px; }
        .stamp-small { display: inline-block; border: 2px dashed #94a3b8; border-radius: 6px; width: 130px; height: 22px; }
        @media print {
            body { background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .page { max-width: none; margin: 0; padding: 12mm; box-shadow: none; border-radius: 0; }
            .no-print { display: none !important; }
            @page { size: A4; margin: 0; }
            .id-card { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">{{ __('general.print') }}</button>
    </div>
    <div class="page">
        <div class="header">
            <div class="header-logo">
                @if ($settings->logo_path)
                    <img src="{{ asset(\Illuminate\Support\Facades\Storage::url($settings->logo_path)) }}" alt="">
                @endif
                <div>
                    <div class="header-name">{{ $settings->localized_name }}</div>
                    <div style="font-size:12px;color:#64748b">{{ $settings->address }}</div>
                </div>
            </div>
            <div class="header-meta">
                <div>{{ $settings->phone }}</div>
                <div>{{ $settings->email }}</div>
            </div>
        </div>
        @yield('content')
        <div class="footer" style="position: relative; padding-bottom: 24px;">
            <div style="width: 100%; display: flex; justify-content: space-between;">
                <span>{{ __('general.printed_by') }}: {{ auth()->user()?->name ?? '—' }} — {{ now()->format('d/m/Y H:i') }}</span>
                <span>{{ $settings->localized_name }} — {{ now()->translatedFormat('d F Y') }}</span>
            </div>
            <div style="position: absolute; bottom: 0; left: 0; font-size: 9px; opacity: 0.6; color: #94a3b8;" dir="ltr">{{ __('general.system_name') }}</div>
        </div>
    </div>
</body>
@if (!empty($autoPrint))
<script>window.onload = function () { window.print(); };</script>
@endif
</html>
