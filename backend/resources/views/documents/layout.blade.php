<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>
        @page { margin: 18mm 16mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 11pt; color: #111; }
        .header { width: 100%; border-bottom: 2px solid {{ $settings?->primary_color ?? '#4f46e5' }}; padding-bottom: 8px; }
        .header td { vertical-align: middle; }
        .logo { width: 70px; height: 70px; }
        .school-name { font-size: 15pt; font-weight: bold; text-transform: uppercase; }
        .motto { font-style: italic; font-size: 9pt; color: #444; }
        .contact { font-size: 8.5pt; color: #444; }
        .country { text-align: right; font-size: 8.5pt; line-height: 1.4; }
        @yield('styles')
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
