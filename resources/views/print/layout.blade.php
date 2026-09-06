<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>
        @page { size: A4; margin: 16mm; }
        * { box-sizing: border-box; }
        body {
            font-family: "Times New Roman", Times, serif;
            color: #111;
            font-size: 12pt;
            margin: 0;
        }
        .letterhead { border-bottom: 1px solid #111; padding-bottom: 8px; margin-bottom: 16px; }
        .letterhead h1 { font-size: 14pt; margin: 0 0 4px; }
        .letterhead p { margin: 0; font-size: 10pt; }
        h2 { font-size: 13pt; text-align: center; margin: 16px 0; }
        table { width: 100%; border-collapse: collapse; margin: 12px 0; }
        th, td { border: 1px solid #333; padding: 6px 8px; text-align: left; }
        th { background: #f3f3f3; }
        .amount { text-align: right; font-variant-numeric: tabular-nums; }
        .legal { margin-top: 24px; font-size: 10pt; font-style: italic; }
        .meta { margin: 8px 0; }
        @media print {
            .no-print { display: none !important; }
            a { color: inherit; text-decoration: none; }
        }
        .no-print { margin-bottom: 12px; }
    </style>
</head>
<body>
    <div class="no-print">
        <button type="button" onclick="window.print()">In</button>
    </div>
    <header class="letterhead">
        <h1>{{ $campus->name ?? 'Campus' }}</h1>
        <p>{{ $campus->address ?? '' }}</p>
        <p>Mã cơ sở: {{ $campus->code ?? '' }}</p>
    </header>
    @yield('content')
</body>
</html>
