<?php
/**
 * @var string|null $pageTitle
 */
$pageTitle = $pageTitle ?? 'WhatsAtende';
$themeStorageKey = 'whats-atende-theme-v2';
?>
<!doctype html>
<html lang="pt-BR" class="antialiased scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
    <meta name="color-scheme" content="light dark">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        (function () {
            const storageKey = '<?= $themeStorageKey ?>';
            const stored = window.localStorage ? localStorage.getItem(storageKey) : null;
            const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            const theme = stored || 'light';
            const root = document.documentElement;
            root.setAttribute('data-bs-theme', theme);
            root.setAttribute('data-theme', theme);
            root.classList.toggle('dark', theme === 'dark');
        })();
    </script>
    <link href="<?= htmlspecialchars(asset('css/style.css'), ENT_QUOTES) ?>?v=<?= time() ?>" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.3/dist/style.css" rel="stylesheet">
    <script>
        window.__APP_BASE_PATH = <?= json_encode(rtrim(route_path('/'), '/')) ?>;
        window.__APP_BASE_ORIGIN = <?= json_encode(app_base_origin()) ?>;
        window.__APP_BASE_URL = <?= json_encode(rtrim(url('/'), '/')) ?>;
        window.__APP_USER_ROLE = <?= json_encode(auth()?->role ?? null) ?>;
    </script>
</head>
<body class="app-body min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 transition-colors duration-300 selection:bg-primary/10 selection:text-primary overflow-x-hidden">
<div class="app-shell flex flex-col lg:flex-row relative" data-app-shell>
