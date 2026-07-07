<?php
// Global navigation asset included across all sub-pages
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$current_page = basename($_SERVER['PHP_SELF']);
$mode = $_SESSION['wallet_mode'] ?? 'live';
$user_id = $_SESSION['user_id'] ?? 0;
$fullname = $_SESSION['fullname'] ?? 'User Terminal';
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-[#020408]">
<head>
    <meta charset="UTF-8">
    <title>Ari-Pay Pro Platform Terminal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'], mono: ['JetBrains Mono', 'monospace'] } } }
        }
    </script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #03060E; }
        .glass-panel { background: rgba(10, 15, 30, 0.7); border: 1px solid rgba(255, 255, 255, 0.03); backdrop-filter: blur(24px); }
        .input-dark { background: #060913; border: 1px solid rgba(255, 255, 255, 0.04); color: #FFF; transition: all 0.2s ease; }
        .input-dark:focus { border-color: #22d3ee; box-shadow: 0 0 0 2px rgba(34, 211, 238, 0.1); outline: none; }
        .notification-card { display: flex; align-items: center; gap: 12px; border: 1px solid; padding: 16px; border-radius: 16px; font-family: 'JetBrains Mono', monospace; font-size: 11px; margin-bottom: 24px; }
    </style>
</head>
<body class="text-slate-200 antialiased h-full flex flex-col md:flex-row overflow-hidden select-none">

    <aside class="w-full md:w-72 bg-[#050811] border-r border-slate-900/60 flex flex-col justify-between shrink-0 h-auto md:h-full z-20">
        <div>
            <div class="p-6 flex items-center justify-between border-b border-slate-900/40">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-cyan-500 to-indigo-600 flex items-center justify-center shadow-lg shadow-cyan-500/10">
                        <i data-lucide='activity' class='w-4 h-4 text-white stroke-[2.5]'></i>
                    </div>
                    <div>
                        <span class="text-sm font-bold tracking-tight text-white block">ARI-PAY</span>
                        <span class="text-[9px] text-cyan-400 font-mono tracking-wider uppercase">Pro Engine</span>
                    </div>
                </div>
            </div>
            
            <nav class="p-4 space-y-1 mt-4">
                <a href="dashboard.php" class="w-full flex items-center justify-between px-4 py-3.5 text-xs font-semibold rounded-xl transition-all duration-200 group <?= $current_page=='dashboard.php' ? 'bg-[#0E1424] text-white' : 'text-slate-400 hover:bg-slate-900/40 hover:text-white' ?>">
                    <div class="flex items-center gap-3.5">
                        <i data-lucide="layout-dashboard" class="w-4 h-4 <?= $current_page=='dashboard.php' ? 'text-cyan-400' : 'text-slate-400 group-hover:text-cyan-400' ?>"></i>
                        <span>Overview Vault</span>
                    </div>
                </a>
                <a href="remittance.php" class="w-full flex items-center justify-between px-4 py-3.5 text-xs font-semibold rounded-xl transition-all duration-200 group <?= $current_page=='remittance.php' ? 'bg-[#0E1424] text-white' : 'text-slate-400 hover:bg-slate-900/40 hover:text-white' ?>">
                    <div class="flex items-center gap-3.5">
                        <i data-lucide="send" class="w-4 h-4 <?= $current_page=='remittance.php' ? 'text-cyan-400' : 'text-slate-400 group-hover:text-cyan-400' ?>"></i>
                        <span>Remittance Portal</span>
                    </div>
                </a>
                <a href="trade.php" class="w-full flex items-center justify-between px-4 py-3.5 text-xs font-semibold rounded-xl transition-all duration-200 group <?= $current_page=='trade.php' ? 'bg-[#0E1424] text-white' : 'text-slate-400 hover:bg-slate-900/40 hover:text-white' ?>">
                    <div class="flex items-center gap-3.5">
                        <i data-lucide="refresh-cw" class="w-4 h-4 <?= $current_page=='trade.php' ? 'text-emerald-400' : 'text-slate-400 group-hover:text-emerald-400' ?>"></i>
                        <span>Spot Exchange</span>
                    </div>
                </a>
                <a href="history.php" class="w-full flex items-center justify-between px-4 py-3.5 text-xs font-semibold rounded-xl transition-all duration-200 group <?= $current_page=='history.php' ? 'bg-[#0E1424] text-white' : 'text-slate-400 hover:bg-slate-900/40 hover:text-white' ?>">
                    <div class="flex items-center gap-3.5">
                        <i data-lucide="history" class="w-4 h-4 <?= $current_page=='history.php' ? 'text-cyan-400' : 'text-slate-400 group-hover:text-cyan-400' ?>"></i>
                        <span>Ledger History</span>
                    </div>
                </a>
                <a href="prices.php" class="w-full flex items-center justify-between px-4 py-3.5 text-xs font-semibold rounded-xl transition-all duration-200 group <?= $current_page=='prices.php' ? 'bg-[#0E1424] text-white' : 'text-slate-400 hover:bg-slate-900/40 hover:text-white' ?>">
                    <div class="flex items-center gap-3.5">
                        <i data-lucide="trending-up" class="w-4 h-4 <?= $current_page=='prices.php' ? 'text-amber-400' : 'text-slate-400 group-hover:text-amber-400' ?>"></i>
                        <span>Live Price Feeds</span>
                    </div>
                </a>
                <a href="profile.php" class="w-full flex items-center justify-between px-4 py-3.5 text-xs font-semibold rounded-xl transition-all duration-200 group <?= $current_page=='profile.php' ? 'bg-[#0E1424] text-white' : 'text-slate-400 hover:bg-slate-900/40 hover:text-white' ?>">
                    <div class="flex items-center gap-3.5">
                        <i data-lucide="user" class="w-4 h-4 <?= $current_page=='profile.php' ? 'text-cyan-400' : 'text-slate-400 group-hover:text-cyan-400' ?>"></i>
                        <span>Profile Settings</span>
                    </div>
                </a>
            </nav>
        </div>

        <div class="p-4 border-t border-slate-900/60 bg-[#03050C] space-y-3">
            <div class="flex items-center gap-3 p-3 rounded-xl bg-slate-950/20 border border-slate-900">
                <div class="w-7 h-7 rounded-lg bg-slate-900 flex items-center justify-center border border-slate-800"><i data-lucide="user" class="w-4 h-4 text-cyan-400"></i></div>
                <div class="truncate">
                    <p class="text-[10px] font-medium text-slate-300 truncate"><?= htmlspecialchars($fullname) ?></p>
                    <p class="text-[8px] font-mono text-slate-600 truncate">NODE://<?= str_pad($user_id, 5, '0', STR_PAD_LEFT) ?></p>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <a href="toggle_mode.php" class="text-[10px] font-mono font-bold text-center py-2 rounded-lg border tracking-tight transition-all duration-150 <?= $mode == 'live' ? 'bg-emerald-500/5 text-emerald-400 border-emerald-500/10' : 'bg-amber-500/5 text-amber-400 border-amber-500/10' ?>">
                    <?= $mode == 'live' ? '● PRODUCTION' : '⚡ SANDBOX' ?>
                </a>
                <a href="logout.php" class="text-[10px] font-mono text-center py-2 rounded-lg bg-slate-950 border border-slate-900 text-slate-500 hover:text-rose-400 hover:border-rose-500/20 transition-all">DISCONNECT</a>
            </div>
        </div>
    </aside>

    <main class="flex-grow flex flex-col overflow-hidden relative">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(34,211,238,0.03),transparent_50%)] pointer-events-none"></div>
        <div class="flex-grow overflow-y-auto p-6 md:p-10 lg:p-12 w-full max-w-5xl mx-auto z-10">