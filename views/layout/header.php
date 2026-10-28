<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? APP_NAME) ?> – BDvault</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <link rel="shortcut icon" type="image/png" href="assets/img/favicon.png">
    <link rel="apple-touch-icon" href="assets/img/favicon.png">

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Fonts: Lexend -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Custom -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Chart.js (Move to header to ensure availability for page scripts) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <style>
        * {
            font-family: 'Lexend', system-ui, -apple-system, sans-serif !important;
        }
    </style>
</head>
<body>

<!-- Top Premium Navbar -->
<style>
.premium-navbar {
    height: 72px;
    background: #ffffff;
    border-bottom: 1.5px solid #e2e8f0;
    padding: 0 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
}

/* Navbar Search bar with Ambient Glow */
.nav-search-container {
    position: relative;
    padding: 3px;
}
/* The hidden blur halo backdrop */
.nav-search-container::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    border-radius: 14px;
    background: linear-gradient(45deg, #3b82f6, #6366f1, #a855f7, #3b82f6);
    background-size: 400% 400%;
    z-index: -1;
    filter: blur(8px);
    opacity: 0.15;
    transition: opacity 0.4s ease, filter 0.4s ease;
    animation: glowPulse 8s ease infinite;
}
.nav-search-container:hover::before,
.nav-search-container:focus-within::before {
    opacity: 0.45;
    filter: blur(12px);
    animation: glowPulse 3s linear infinite;
}

@keyframes glowPulse {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

.nav-search-wrap {
    display: flex;
    align-items: center;
    background: #ffffff;
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    padding: 6px 14px;
    width: 280px;
    gap: 8px;
    transition: border-color 0.3s;
}
.nav-search-container:focus-within .nav-search-wrap {
    border-color: #6366f1;
}
.nav-search-wrap input {
    background: transparent;
    border: none;
    outline: none;
    font-size: 0.8rem;
    color: #334155;
    width: 100%;
}
.nav-search-wrap input::placeholder { color: #94a3b8; }
.nav-search-wrap i { color: #94a3b8; }

/* Status items */
.nav-status-item {
    font-size: 0.75rem;
    font-weight: 700;
    color: #475569;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 6px 12px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.live-dot-pulse {
    width: 6px;
    height: 6px;
    background: #22c55e;
    border-radius: 50%;
    box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7);
    animation: livePulse 2s infinite;
}
@keyframes livePulse {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(34, 197, 94, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
}

.nav-action-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    position: relative;
    cursor: pointer;
    transition: background 0.2s;
}
.nav-action-icon:hover { background: #f1f5f9; color: #334155; }
.nav-badge-dot {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 6px;
    height: 6px;
    background: #ef4444;
    border-radius: 50%;
}
</style>

<nav class="premium-navbar">
    
    <!-- Left: Spacer for center alignment -->
    <div style="flex: 1;"></div>

    <!-- Center: Search Area -->
    <div class="d-flex align-items-center justify-content-center">
        <div class="nav-search-wrap" style="width: 320px;">
            <i class="bi bi-search"></i>
            <input type="text" placeholder="Search proposals, regions...">
        </div>
    </div>

    <!-- Right: Utility Items -->
    <div style="flex: 1;" class="d-flex align-items-center justify-content-end gap-3">
        
        <!-- Date badge -->
        <div class="nav-status-item">
            <i class="bi bi-calendar3 text-primary"></i>
            <span><?= date('d M Y') ?></span>
        </div>

        <!-- Settings shortcut -->
        <a href="index.php?page=weights" class="nav-action-icon" title="Configuration">
            <i class="bi bi-sliders"></i>
        </a>

    </div>
</nav>

<div class="d-flex" style="margin-top:72px;min-height:calc(100vh - 72px)">
    <!-- Sidebar -->
    <?php require __DIR__ . '/sidebar.php'; ?>

    <!-- Main content -->
    <main class="flex-grow-1 p-4 bg-light-subtle overflow-auto">

        <?php
        $flash = flashGet();
        if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show mb-3" role="alert">
            <?= e($flash['msg']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
