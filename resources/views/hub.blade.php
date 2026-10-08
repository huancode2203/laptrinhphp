<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Badminton Hub: đặt sân, quản lý lịch sân, cộng đồng và sự kiện cầu lông.">
    <title>Badminton Hub</title>
    @viteReactRefresh
    @vite('resources/js/main.tsx')
</head>
<body><div id="root"></div><noscript>Vui lòng bật JavaScript để sử dụng website.</noscript></body>
</html>
