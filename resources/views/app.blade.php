<!DOCTYPE html>
<html lang="vi" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>SmashRank — Xếp Hạng Vận Động Viên Cầu Lông</title>
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#10b981">
    <meta name="description" content="Nền tảng quản lý thứ hạng vận động viên cầu lông: BWF World Tour & Elo phong trào, trang thiết bị, phân tích kỹ năng." />
    @viteReactRefresh
    @vite(['resources/js/main.tsx'])
</head>
<body class="bg-surface-950 text-neutral-100">
    <div id="root"></div>
</body>
</html>
