<?php
/**
 * @var string|null $pageTitle
 */
$pageTitle = $pageTitle ?? 'WhatsAtende';
$themeStorageKey = 'whats-atende-theme-v2';
?>
<!doctype html>
<html lang="pt-BR" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        (function () {
            const root = document.documentElement;
            root.setAttribute('data-bs-theme', 'light');
            root.setAttribute('data-theme', 'light');
            root.classList.remove('dark');
        })();
    </script>
    <link href="<?= htmlspecialchars(asset('css/style.css'), ENT_QUOTES) ?>" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script>
        window.__APP_BASE_PATH = <?= json_encode(rtrim(route_path('/'), '/')) ?>;
        window.__APP_BASE_ORIGIN = <?= json_encode(app_base_origin()) ?>;
        window.__APP_BASE_URL = <?= json_encode(rtrim(url('/'), '/')) ?>;
    </script>
</head>
<body class="auth-body" data-theme="light">
<main class="auth-shell">
