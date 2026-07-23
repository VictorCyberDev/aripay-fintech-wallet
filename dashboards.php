<?php
require 'bootstrap.php';
require 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if (isset($_GET['toggle_mode'])) {
    $_SESSION['wallet_mode'] = ($_SESSION['wallet_mode'] == 'live') ? 'demo' : 'live';
    header("Location: dashboard.php");
    exit();
}

$mode = $_SESSION['wallet_mode'] ?? 'live';
$msg = "";
$active_tab = $_GET['view'] ?? "overview";

$rates = [
    'USD' => 1.0, 'NGN' => 1500.0, 'GBP' => 0.78, 'EUR' => 0.92,
    'ARI' => 0.80, 'BTC' => 0.000015, 'ETH' => 0.00028   
];

// ==========================================
// CORE TRANSACTIONS PIPELINE
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_payment'])) {
    $active_tab = "transfer";
    $recipient_phone   = trim($_POST['recipient_phone']);
    $amount_to_send    = floatval($_POST['amount_sent']);
    $currency_sent     = $_POST['currency_sent'];
    $currency_received = $_POST['currency_received'];

    $balance_map = [
        'USD' => 'fiat_balance', 'NGN' => 'fiat_balance', 'GBP' => 'fiat_balance', 'EUR' => 'fiat_balance',
        'ARI' => 'ari_balance', 'BTC' => 'btc_balance', 'ETH' => 'eth_balance'
    ];

    $source_col = $balance_map[$currency_sent];
    $target_col = $balance_map[$currency_received];

    $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ?");
    $stmt->execute([$recipient_phone]);
    $recipient = $stmt->fetch();

    if ($recipient) {
        if ($recipient['id'] === $user_id) {
            $msg = "<div class='notification-card border-amber-500/30 bg-amber-500/10 text-amber-400'><i data-lucide='alert-triangle' class='w-4 h-4 shrink-0'></i><span>Routing failure: Nodes matching sender parameter rejected.</span></div>";
        } else {
            $rec_id = $recipient['id'];
            $stmt_bal = $conn->prepare("SELECT * FROM wallets WHERE user_id = ? AND wallet_type = ?");
            $stmt_bal->execute([$user_id, $mode]);
            $sender_wallet = $stmt_bal->fetch();

            $amount_in_usd    = $amount_to_send / $rates[$currency_sent];
            $deduction_amount = $amount_to_send;
            $credit_amount    = $amount_in_usd * $rates[$currency_received];

            if ($source_col === 'fiat_balance' && $currency_sent !== $_SESSION['base_currency']) {
                $deduction_amount = $amount_in_usd * $rates[$_SESSION['base_currency']];
            }

            if ($sender_wallet && $sender_wallet[$source_col] >= $deduction_amount) {
                try {
                    $conn->beginTransaction();
                    $stmt_deduct = $conn->prepare("UPDATE wallets SET $source_col = $source_col - ? WHERE user_id = ? AND wallet_type = ?");
                    $stmt_deduct->execute([$deduction_amount, $user_id, $mode]);

                    $recipient_credit = $credit_amount;
                    if ($target_col === 'fiat_balance') {
                        $stmt_rec_base = $conn->prepare("SELECT base_currency FROM users WHERE id = ?");
                        $stmt_rec_base->execute([$rec_id]);
                        $rec_base = $stmt_rec_base->fetchColumn();
                        $recipient_credit = $amount_in_usd * $rates[$rec_base ?: 'USD'];
                    }

                    $stmt_credit = $conn->prepare("UPDATE wallets SET $target_col = $target_col + ? WHERE user_id = ? AND wallet_type = ?");
                    $stmt_credit->execute([$recipient_credit, $rec_id, $mode]);

                    $tx_hash = "0x" . hash('sha256', $user_id . $rec_id . time() . mt_rand());
                    $stmt_tx = $conn->prepare("INSERT INTO transactions (sender_id, receiver_id, wallet_type, amount_sent, currency_sent, amount_received, currency_received, tx_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt_tx->execute([$user_id, $rec_id, $mode, $amount_to_send, $currency_sent, $recipient_credit, $currency_received, $tx_hash]);

                    $conn->commit();
                    $msg = "<div class='notification-card border-emerald-500/30 bg-emerald-500/10 text-emerald-400'><i data-lucide='check-circle' class='w-4 h-4 shrink-0'></i><span>Transfer settled. Tx Hash: " . substr($tx_hash, 0, 16) . "...</span></div>";
                } catch (Exception $e) {
                    if ($conn->inTransaction()) { $conn->rollBack(); }
                    log_app_error('Dashboard transfer failed', $e);
                    $msg = user_error_notice('Transfer could not be completed. No funds were moved.');
                }
            } else {
                $msg = "<div class='notification-card border-rose-500/30 bg-rose-500/10 text-rose-400'><i data-lucide='wallet' class='w-4 h-4 shrink-0'></i><span>Transaction aborted: Insufficient asset balance reserves.</span></div>";
            }
        }
    } else {
        $msg = "<div class='notification-card border-rose-500/30 bg-rose-500/10 text-rose-400'><i data-lucide='user-x' class='w-4 h-4 shrink-0'></i><span>Destination ledger mapping connection missing.</span></div>";
    }
}

// ==========================================
// SPOT EXCHANGE ORDER CORES
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['trade_crypto'])) {
    $active_tab = "trade";
    $trade_action = $_POST['trade_action']; 
    $crypto_asset = $_POST['crypto_asset'];   
    $fiat_input   = floatval($_POST['fiat_amount']); 
    $crypto_col   = strtolower($crypto_asset) . '_balance';
    
    $stmt_w = $conn->prepare("SELECT * FROM wallets WHERE user_id = ? AND wallet_type = ?");
    $stmt_w->execute([$user_id, $mode]);
    $w = $stmt_w->fetch();

    $fiat_to_usd  = $fiat_input / $rates[$_SESSION['base_currency']];
    $crypto_output = $fiat_to_usd * $rates[$crypto_asset];

    try {
        if ($trade_action === 'buy') {
            if ($w && $w['fiat_balance'] >= $fiat_input) {
                $conn->beginTransaction();
                $stmt_sub = $conn->prepare("UPDATE wallets SET fiat_balance = fiat_balance - ? WHERE user_id = ? AND wallet_type = ?");
                $stmt_sub->execute([$fiat_input, $user_id, $mode]);
                $stmt_add = $conn->prepare("UPDATE wallets SET $crypto_col = $crypto_col + ? WHERE user_id = ? AND wallet_type = ?");
                $stmt_add->execute([$crypto_output, $user_id, $mode]);
                $conn->commit();
                $msg = "<div class='notification-card border-emerald-500/30 bg-emerald-500/10 text-emerald-400'><i data-lucide='trending-up' class='w-4 h-4 shrink-0'></i><span>Order filled: Allocated +" . sprintf('%.6f', $crypto_output) . " $crypto_asset</span></div>";
            } else {
                $msg = "<div class='notification-card border-rose-500/30 bg-rose-500/10 text-rose-400'><i data-lucide='alert-circle' class='w-4 h-4 shrink-0'></i><span>Order rejected: Insufficient fiat liquidity pools.</span></div>";
            }
        } elseif ($trade_action === 'sell') {
            if ($w && $w[$crypto_col] >= $crypto_output) {
                $conn->beginTransaction();
                $stmt_sub = $conn->prepare("UPDATE wallets SET $crypto_col = $crypto_col - ? WHERE user_id = ? AND wallet_type = ?");
                $stmt_sub->execute([$crypto_output, $user_id, $mode]);
                $stmt_add = $conn->prepare("UPDATE wallets SET fiat_balance = fiat_balance + ? WHERE user_id = ? AND wallet_type = ?");
                $stmt_add->execute([$fiat_input, $user_id, $mode]);
                $conn->commit();
                $msg = "<div class='notification-card border-emerald-500/30 bg-emerald-500/10 text-emerald-400'><i data-lucide='trending-down' class='w-4 h-4 shrink-0'></i><span>Order filled: Exchanged crypto for +" . number_format($fiat_input, 2) . " " . $_SESSION['base_currency'] . "</span></div>";
            } else {
                $msg = "<div class='notification-card border-rose-500/30 bg-rose-500/10 text-rose-400'><i data-lucide='alert-circle' class='w-4 h-4 shrink-0'></i><span>Order rejected: Insufficient crypto portfolio depth.</span></div>";
            }
        }
    } catch (Exception $e) {
        if ($conn->inTransaction()) { $conn->rollBack(); }
        $msg = "<div class='notification-card border-rose-500/30 bg-rose-500/10 text-rose-400'><i data-lucide='shield-alert' class='w-4 h-4 shrink-0'></i><span>Order execution failure.</span></div>";
    }
}

$stmt_wallet = $conn->prepare("SELECT * FROM wallets WHERE user_id = ? AND wallet_type = ?");
$stmt_wallet->execute([$user_id, $mode]);
$wallet = $stmt_wallet->fetch();
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
            theme: {
                extend: {
                    fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'], mono: ['JetBrains Mono', 'monospace'] }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #03060E; }
        .glass-panel { background: rgba(10, 15, 30, 0.7); border: 1px solid rgba(255, 255, 255, 0.03); backdrop-filter: blur(24px); }
        .tab-view { display: none; opacity: 0; transform: scale(0.99) translateY(4px); transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1); }
        .tab-view.active { display: block; opacity: 1; transform: scale(1) translateY(0); }
        .input-dark { background: #060913; border: 1px solid rgba(255, 255, 255, 0.04); color: #FFF; transition: all 0.2s ease; }
        .input-dark:focus { border-color: #22d3ee; box-shadow: 0 0 0 2px rgba(34, 211, 238, 0.1); outline: none; }
        .notification-card { display: flex; align-items: center; gap: 12px; border: 1px solid; padding: 16px; border-radius: 16px; font-family: 'JetBrains Mono', monospace; font-size: 11px; margin-bottom: 24px; }
    </style>
</head>
<body class="text-slate-200 antialiased h-full flex flex-col md:flex-row overflow-hidden select-none">

    <!-- PLATFORM SIDEBAR INTERFACE -->
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
                <button onclick="switchTab('overview')" id="btn-overview" class="w-full flex items-center justify-between px-4 py-3.5 text-xs font-semibold rounded-xl transition-all duration-200 group text-slate-400 hover:bg-slate-900/40 hover:text-white">
                    <div class="flex items-center gap-3.5">
                        <i data-lucide="layout-dashboard" class="w-4 h-4 text-slate-400 group-hover:text-cyan-400 transition-colors"></i>
                        <span>Overview Vault</span>
                    </div>
                    <i data-lucide="chevron-right" class="w-3.5 h-3.5 opacity-0 group-hover:opacity-100 transition-all transform translate-x-[-4px] group-hover:translate-x-0"></i>
                </button>
                <button onclick="switchTab('transfer')" id="btn-transfer" class="w-full flex items-center justify-between px-4 py-3.5 text-xs font-semibold rounded-xl transition-all duration-200 group text-slate-400 hover:bg-slate-900/40 hover:text-white">
                    <div class="flex items-center gap-3.5">
                        <i data-lucide="send" class="w-4 h-4 text-slate-400 group-hover:text-cyan-400 transition-colors"></i>
                        <span>Remittance Portal</span>
                    </div>
                    <i data-lucide="chevron-right" class="w-3.5 h-3.5 opacity-0 group-hover:opacity-100 transition-all transform translate-x-[-4px] group-hover:translate-x-0"></i>
                </button>
                <button onclick="switchTab('trade')" id="btn-trade" class="w-full flex items-center justify-between px-4 py-3.5 text-xs font-semibold rounded-xl transition-all duration-200 group text-slate-400 hover:bg-slate-900/40 hover:text-white">
                    <div class="flex items-center gap-3.5">
                        <i data-lucide="refresh-cw" class="w-4 h-4 text-slate-400 group-hover:text-emerald-400 transition-colors"></i>
                        <span>Spot Exchange</span>
                    </div>
                    <i data-lucide="chevron-right" class="w-3.5 h-3.5 opacity-0 group-hover:opacity-100 transition-all transform translate-x-[-4px] group-hover:translate-x-0"></i>
                </button>
            </nav>
        </div>

        <div class="p-4 border-t border-slate-900/60 bg-[#03050C] space-y-3">
            <div class="flex items-center gap-3 p-3 rounded-xl bg-slate-950/20 border border-slate-900">
                <div class="w-7 h-7 rounded-lg bg-slate-900 flex items-center justify-center text-slate-300 border border-slate-800">
                    <i data-lucide="shield-check" class="w-4 h-4 text-cyan-400"></i>
                </div>
                <div class="truncate">
                    <p class="text-[10px] font-medium text-slate-300 truncate"><?= htmlspecialchars($_SESSION['fullname']) ?></p>
                    <p class="text-[8px] font-mono text-slate-600 truncate">NODE://<?= str_pad($user_id, 5, '0', STR_PAD_LEFT) ?></p>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <a href="dashboard.php?toggle_mode=1" class="text-[10px] font-mono font-bold text-center py-2 rounded-lg border tracking-tight transition-all duration-150 <?= $mode == 'live' ? 'bg-emerald-500/5 text-emerald-400 border-emerald-500/10 hover:bg-emerald-500/10' : 'bg-amber-500/5 text-amber-400 border-amber-500/10 hover:bg-amber-500/10' ?>">
                    <?= $mode == 'live' ? '● PRODUCTION' : '⚡ SANDBOX' ?>
                </a>
                <a href="logout.php" class="text-[10px] font-mono text-center py-2 rounded-lg bg-slate-950 border border-slate-900 text-slate-500 hover:text-rose-400 hover:border-rose-500/20 transition-all">DISCONNECT</a>
            </div>
        </div>
    </aside>

    <!-- CORE DESKTOP VIEWPORT SPACE -->
    <main class="flex-grow flex flex-col overflow-hidden relative">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(34,211,238,0.03),transparent_50%)] pointer-events-none"></div>

        <div class="flex-grow overflow-y-auto p-6 md:p-10 lg:p-12 w-full max-w-5xl mx-auto z-10">
            <?php if(!empty($msg)) echo $msg; ?>

            <!-- PAGE VIEW 1: SYSTEM OVERVIEW VAULT -->
            <div id="tab-overview" class="tab-view space-y-8">
                <div class="flex items-center justify-between border-b border-slate-900 pb-6">
                    <div>
                        <h1 class="text-2xl font-extrabold tracking-tight text-white sm:text-3xl">Asset Control Vault</h1>
                        <p class="text-xs text-slate-400 mt-1">Institutional liquidity position snapshot summary data metrics.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div class="glass-panel p-6 rounded-2xl relative overflow-hidden group hover:border-slate-800 transition-all duration-300">
                        <div class="flex justify-between items-start mb-4">
                            <span class="text-[10px] font-mono text-slate-500 uppercase tracking-widest block">Available Cash Reserves</span>
                            <div class="w-7 h-7 rounded-lg bg-slate-900 flex items-center justify-center border border-slate-800 text-slate-400"><i data-lucide="banknote" class="w-3.5 h-3.5"></i></div>
                        </div>
                        <div class="text-3xl font-bold tracking-tight text-white font-mono"><?= number_format($wallet['fiat_balance'], 2) ?> <span class="text-xs font-sans text-slate-500 font-normal"><?= $_SESSION['base_currency'] ?></span></div>
                    </div>
                    <div class="glass-panel p-6 rounded-2xl relative overflow-hidden group hover:border-slate-800 transition-all duration-300">
                        <div class="flex justify-between items-start mb-4">
                            <span class="text-[10px] font-mono text-slate-500 uppercase tracking-widest block">Gas Protocols Utility Token</span>
                            <div class="w-7 h-7 rounded-lg bg-cyan-950/20 flex items-center justify-center border border-cyan-900/20 text-cyan-400"><i data-lucide="coins" class="w-3.5 h-3.5"></i></div>
                        </div>
                        <div class="text-3xl font-bold tracking-tight text-cyan-400 font-mono"><?= number_format($wallet['ari_balance'], 2) ?> <span class="text-xs font-sans text-slate-500 font-normal">ARI</span></div>
                    </div>
                    <div class="glass-panel p-6 rounded-2xl relative overflow-hidden group hover:border-slate-800 transition-all duration-300">
                        <div class="flex justify-between items-start mb-4">
                            <span class="text-[10px] font-mono text-slate-500 uppercase tracking-widest block">Consolidated Crypto Value</span>
                            <div class="w-7 h-7 rounded-lg bg-slate-900 flex items-center justify-center border border-slate-800 text-slate-400"><i data-lucide="pie-chart" class="w-3.5 h-3.5"></i></div>
                        </div>
                        <div class="text-3xl font-bold tracking-tight text-white font-mono">
                            <?php 
                            $crypto_val_usd = ($wallet['btc_balance'] / $rates['BTC']) + ($wallet['eth_balance'] / $rates['ETH']);
                            echo number_format($crypto_val_usd * $rates[$_SESSION['base_currency']], 2);
                            ?>
                            <span class="text-xs font-sans text-slate-500 font-normal"><?= $_SESSION['base_currency'] ?></span>
                        </div>
                    </div>
                </div>

                <div class="glass-panel rounded-2xl overflow-hidden">
                    <div class="p-5 border-b border-slate-900 bg-slate-950/10 flex items-center justify-between">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-300 flex items-center gap-2"><i data-lucide="layers" class="w-3.5 h-3.5 text-cyan-400"></i>Asset Portfolio Indexes</h3>
                    </div>
                    <div class="divide-y divide-slate-900">
                        <div class="p-5 flex items-center justify-between hover:bg-slate-900/10 transition-colors">
                            <div class="flex items-center gap-4">
                                <div class="w-9 h-9 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-500"><i data-lucide="bitcoin" class="w-4 h-4"></i></div>
                                <div><span class="text-xs font-bold text-white block">Bitcoin Core Asset</span><span class="text-[10px] font-mono text-slate-500 uppercase">Layer-1 Architecture Network</span></div>
                            </div>
                            <div class="text-right font-mono"><span class="text-sm font-bold text-slate-200 block"><?= sprintf('%.8f', $wallet['btc_balance']) ?> BTC</span><span class="text-[10px] text-slate-500">≈ <?= number_format(($wallet['btc_balance'] / $rates['BTC']) * $rates[$_SESSION['base_currency']], 2) . ' ' . $_SESSION['base_currency'] ?></span></div>
                        </div>
                        <div class="p-5 flex items-center justify-between hover:bg-slate-900/10 transition-colors">
                            <div class="flex items-center gap-4">
                                <div class="w-9 h-9 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400"><i data-lucide="gem" class="w-4 h-4"></i></div>
                                <div><span class="text-xs font-bold text-white block">Ethereum Gas Reserve</span><span class="text-[10px] font-mono text-slate-500 uppercase">EVM Execution Token</span></div>
                            </div>
                            <div class="text-right font-mono"><span class="text-sm font-bold text-slate-200 block"><?= sprintf('%.5f', $wallet['eth_balance']) ?> ETH</span><span class="text-[10px] text-slate-500">≈ <?= number_format(($wallet['eth_balance'] / $rates['ETH']) * $rates[$_SESSION['base_currency']], 2) . ' ' . $_SESSION['base_currency'] ?></span></div>
                        </div>
                    </div>
                </div>

                <div class="p-4 rounded-xl bg-[#060a13] border border-slate-900 font-mono text-[10px] text-slate-500 flex flex-col sm:flex-row gap-2 items-center justify-between">
                    <div class="flex items-center gap-2"><i data-lucide="link-2" class="w-3.5 h-3.5 text-slate-600"></i><span>Cryptographic Settlement Endpoint Key:</span></div>
                    <span class="text-cyan-500 bg-slate-950 px-3 py-1 rounded-md border border-slate-900 select-all tracking-tight"><?= $wallet['blockchain_address'] ?></span>
                </div>
            </div>

            <!-- PAGE VIEW 2: REMITTANCE OPERATIONS DESK -->
            <div id="tab-transfer" class="tab-view max-w-xl mx-auto space-y-6">
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl">Remittance Settlement Operations</h1>
                    <p class="text-xs text-slate-400 mt-1 font-light">Cross-currency transaction execution. Funds convert instantly across target layers.</p>
                </div>

                <div class="glass-panel p-6 rounded-2xl shadow-2xl relative">
                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-mono text-slate-400 uppercase tracking-wider mb-2">Destination Channel ID (Recipient Phone)</label>
                            <div class="relative flex items-center">
                                <i data-lucide="phone" class="w-4 h-4 text-slate-600 absolute left-3.5 pointer-events-none"></i>
                                <input type="text" name="recipient_phone" placeholder="+234..." required class="input-dark w-full text-xs p-3.5 pl-11 rounded-xl font-mono">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-[10px] font-mono text-slate-400 uppercase tracking-wider mb-2">Outbound Principal Amount</label>
                            <div class="relative flex items-center">
                                <i data-lucide="dollar-sign" class="w-4 h-4 text-slate-600 absolute left-3.5 pointer-events-none"></i>
                                <input type="number" step="0.000001" name="amount_sent" id="amount_sent" placeholder="0.00" required class="input-dark w-full text-xs p-3.5 pl-11 rounded-xl font-mono">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-mono text-slate-400 uppercase tracking-wider mb-2">Outbound Base Layer</label>
                                <select name="currency_sent" id="currency_sent" class="input-dark w-full text-xs p-3.5 rounded-xl font-mono cursor-pointer bg-[#060913]">
                                    <option value="USD">USD ($)</option>
                                    <option value="NGN" <?= $_SESSION['base_currency'] == 'NGN' ? 'selected' : '' ?>>NGN (₦)</option>
                                    <option value="GBP">GBP (£)</option>
                                    <option value="EUR">EUR (€)</option>
                                    <option value="ARI">ARI (Native)</option>
                                    <option value="BTC">BTC (Bitcoin)</option>
                                    <option value="ETH">ETH (Ethereum)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-mono text-slate-400 uppercase tracking-wider mb-2">Destination Layer Asset</label>
                                <select name="currency_received" id="currency_received" class="input-dark w-full text-xs p-3.5 rounded-xl font-mono cursor-pointer bg-[#060913]">
                                    <option value="USD">USD ($)</option>
                                    <option value="NGN">NGN (₦)</option>
                                    <option value="GBP">GBP (£)</option>
                                    <option value="EUR">EUR (€)</option>
                                    <option value="ARI">ARI (Native)</option>
                                    <option value="BTC">BTC (Bitcoin)</option>
                                    <option value="ETH">ETH (Ethereum)</option>
                                </select>
                            </div>
                        </div>

                        <div class="bg-slate-950/40 p-4 rounded-xl border border-slate-900 font-mono text-xs flex justify-between items-center">
                            <span class="text-slate-500">Yield Pipeline Estimate:</span>
                            <span id="preview_val" class="text-cyan-400 font-bold text-sm">-</span>
                        </div>

                        <button type="submit" name="send_payment" class="w-full bg-cyan-400 hover:bg-cyan-300 text-slate-950 font-bold text-xs p-4 rounded-xl uppercase tracking-wider transition-colors font-mono shadow-lg shadow-cyan-400/5">
                            Execute Protocol Order
                        </button>
                    </form>
                </div>
            </div>

            <!-- PAGE VIEW 3: LIQUIDITY SPOT EXCHANGE SWAP CORES -->
            <div id="tab-trade" class="tab-view max-w-xl mx-auto space-y-6">
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl">Spot Order Desk</h1>
                    <p class="text-xs text-slate-400 mt-1 font-light">Swap local cash directly into decentralized pools using zero-slippage models.</p>
                </div>

                <div class="glass-panel p-6 rounded-2xl shadow-2xl relative">
                    <form method="POST" class="space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-mono text-slate-400 uppercase tracking-wider mb-2">Order Side Execution</label>
                                <select name="trade_action" id="trade_action" class="input-dark w-full text-xs p-3.5 rounded-xl font-mono cursor-pointer bg-[#060913]">
                                    <option value="buy" class="text-emerald-400">BUY LONG POSITION</option>
                                    <option value="sell" class="text-rose-400">LIQUIDATE (SELL)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-mono text-slate-400 uppercase tracking-wider mb-2">Target Asset Token Index</label>
                                <select name="crypto_asset" id="crypto_asset" class="input-dark w-full text-xs p-3.5 rounded-xl font-mono cursor-pointer bg-[#060913]">
                                    <option value="BTC">BTC (Bitcoin)</option>
                                    <option value="ETH">ETH (Ethereum)</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-mono text-slate-400 uppercase tracking-wider mb-2">Assigned Capital Reserve Allocation (<?= $_SESSION['base_currency'] ?>)</label>
                            <div class="relative flex items-center">
                                <i data-lucide="coins" class="w-4 h-4 text-slate-600 absolute left-3.5 pointer-events-none"></i>
                                <input type="number" step="0.01" name="fiat_amount" id="fiat_amount" placeholder="0.00" required class="input-dark w-full text-xs p-3.5 pl-11 rounded-xl font-mono">
                            </div>
                        </div>

                        <div class="bg-slate-950/40 p-4 rounded-xl border border-slate-900 font-mono text-xs flex justify-between items-center">
                            <span class="text-slate-500">Counter-Asset Allocation Outcome:</span>
                            <span id="trade_preview" class="text-emerald-400 font-bold text-sm">-</span>
                        </div>

                        <button type="submit" name="trade_crypto" class="w-full bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs p-4 rounded-xl uppercase tracking-wider transition-colors font-mono shadow-lg shadow-emerald-500/5">
                            Commit Spot Order Framework
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </main>

    <!-- SYSTEM REACTION RENDERING ENGINE SCRIPT LAYER -->
    <script>
        const rates = <?= json_encode($rates) ?>;
        const baseCurrency = "<?= $_SESSION['base_currency'] ?>";

        function switchTab(tabId) {
            const url = new URL(window.location);
            url.searchParams.set('view', tabId);
            window.history.pushState({}, '', url);

            document.querySelectorAll('.tab-view').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('nav button').forEach(el => {
                el.classList.remove('bg-[#0E1424]', 'text-white');
                el.classList.add('text-slate-400');
            });

            const currentTarget = document.getElementById('tab-' + tabId);
            if(currentTarget) currentTarget.classList.add('active');
            
            const currentBtn = document.getElementById('btn-' + tabId);
            if(currentBtn) currentBtn.classList.add('bg-[#0E1424]', 'text-white');
        }

        switchTab('<?= $active_tab ?>');

        const amtInput = document.getElementById('amount_sent');
        const currSent = document.getElementById('currency_sent');
        const currRec = document.getElementById('currency_received');
        const preview = document.getElementById('preview_val');

        const tradeAction = document.getElementById('trade_action');
        const cryptoAsset = document.getElementById('crypto_asset');
        const fiatAmount  = document.getElementById('fiat_amount');
        const tradePreview = document.getElementById('trade_preview');

        function calculateRemittance() {
            const val = parseFloat(amtInput.value);
            if(!val || isNaN(val)) { preview.innerText = "-"; return; }
            const amtInUSD = val / rates[currSent.value];
            const targetConversion = amtInUSD * rates[currRec.value];
            const decimals = (currRec.value === 'BTC' || currRec.value === 'ETH') ? 6 : 2;
            preview.innerText = `${targetConversion.toFixed(decimals)} ${currRec.value}`;
        }

        function calculateTrade() {
            const fiatVal = parseFloat(fiatAmount.value);
            if(!fiatVal || isNaN(fiatVal)) { tradePreview.innerText = "-"; return; }
            const fiatInUSD = fiatVal / rates[baseCurrency];
            const cryptoOutput = fiatInUSD * rates[cryptoAsset.value];
            tradePreview.innerText = `${cryptoOutput.toFixed(6)} ${cryptoAsset.value}`;
        }

        amtInput.addEventListener('input', calculateRemittance);
        currSent.addEventListener('change', calculateRemittance);
        currRec.addEventListener('change', calculateRemittance);

        fiatAmount.addEventListener('input', calculateTrade);
        tradeAction.addEventListener('change', calculateTrade);
        cryptoAsset.addEventListener('change', calculateTrade);

        lucide.createIcons();
    </script>
</body>
</html>