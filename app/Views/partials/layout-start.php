<?php
/**
 * @var string|null $pageTitle
 */
$pageTitle = $pageTitle ?? 'WhatsAtende';
?>
<!doctype html>
<html lang="pt-BR" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-JgEdRkNvztNFVQVw1Gc7YCOUMIqFZRMVAbwYSEuxsjjXNMTvEfgQ6VbUaz2QHCcm" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/v/bs5/dt-2.0.3/datatables.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4@5.0.12/bootstrap-4.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet" integrity="sha512-vKM265p3nSUQe8Vn0tH3UX0DpD+s/COM24kTx5cDIeEJD7BqXc9E+u6KDAdAm8YGtS+wGGyRlmE7CqCtaA+1mw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="/css/theme.css" rel="stylesheet">
</head>
<body class="app-body">
