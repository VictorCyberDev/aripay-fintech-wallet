<?php
// ==========================================
// SECURITY & DATA METRIC ROUTER
// ==========================================
ini_set('display_errors', 0); // Production secure fallback
require 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$mode = $_SESSION['wallet_mode'] ?? 'live';
$base_currency = $_SESSION['base_currency'] ?? 'USD';
$msg = "";

// High-Throughput Baseline Exchange Index Variables (Relative to 1.00 USD)
$rates = [
    'USD'  => 1.0, 
    'NGN'  => 1500.0, 
    'GBP'  => 0.78, 
    'EUR'  => 0.92,
    'BTC'  => 0.000015, // Mock historical compression metric
    'SOL'  => 0.0068,   // $145.00 USD Equilibrium base
    'USDC' => 1.00      // Pegged Stable Tier
];

// Target column parsing parameters
$balance_map = [
    'BTC'  => 'btc_balance',
    'SOL'  => 'sol_balance',
    'USDC' => 'usdc_balance'
];

// ==========================================
// SPOT EXECUTION LOGIC (POST)
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['commit_spot_order'])) {
    $trade_action = $_POST['trade_action']; // 'buy' or 'sell'
    $crypto_asset = $_POST['crypto_asset']; // 'BTC', 'SOL', 'USDC'
    $fiat_volume  = floatval($_POST['fiat_amount']);

    if ($fiat_volume <= 0) {
        $msg = "<div class='notification-card border-rose-500/30 bg-rose-500/10 text-rose-400'><i data-lucide='alert-circle' class='w-4 h-4 shrink-0'></i><span>Execution Denied: Order volume parameters out of algorithmic bounds.</span></div>";
    } else {
        $crypto_col = $balance_map[$crypto_asset] ?? null;

        if (!$crypto_col) {
            $msg = "<div class='notification-card border-rose-500/30 bg-rose-500/10 text-rose-400'><i data-lucide='shield-alert' class='w-4 h-4 shrink-0'></i><span>Routing Failure: Unmapped ledger target symbol asset.</span></div>";
        } else {
            // Read wallet indexes inside an isolated state snapshot
            $stmt_w = $conn->prepare("SELECT fiat_balance, $crypto_col FROM wallets WHERE user_id = ? AND wallet_type = ? LIMIT 1");
            $stmt_w->execute([$user_id, $mode]);
            $wallet = $stmt_w->fetch();

            if (!$wallet) {
                $msg = "<div class='notification-card border-rose-500/30 bg-rose-500/10 text-rose-400'><i data-lucide='user-x' class='w-4 h-4 shrink-0'></i><span>Account Mapping Error: Underlying wallet matrix not instantiated.</span></div>";
            } else {
                // Algorithmic Yield Formulas
                $fiat_in_usd  = $fiat_volume / $rates[$base_currency];
                $crypto_yield = $fiat_in_usd * $rates[$crypto_asset];

                try {
                    // ------------------------------------------
                    // ROUTE A: DEPLOY FIAT TO ASSIMILATE CRYPTO
                    // ------------------------------------------
                    if ($trade_action === 'buy') {
                        if ($wallet['fiat_balance'] >= $fiat_volume) {
                            $conn->beginTransaction();
                            
                            $stmt_sub = $conn->prepare("UPDATE wallets SET fiat_balance = fiat_balance - ? WHERE user_id = ? AND wallet_type = ?");
                            $stmt_sub->execute([$fiat_volume, $user_id, $mode]);
                            
                            $stmt_add = $conn->prepare("UPDATE wallets SET $crypto_col = $crypto_col + ? WHERE user_id = ? AND wallet_type = ?");
                            $stmt_add->execute([$crypto_yield, $user_id, $mode]);
                            
                            $tx_hash = "0x" . hash('sha256', $user_id . time() . mt_rand() . "BUY");
                            $stmt_tx = $conn->prepare("INSERT INTO transactions (sender_id, receiver_id, wallet_type, amount_sent, currency_sent, amount_received, currency_received, tx_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                            // Internal trade registers system user as both sender and destination consumer
                            $stmt_tx->execute([$user_id, $user_id, $mode, $fiat_volume, $base_currency, $crypto_yield, $crypto_asset, $tx_hash]);

                            $conn->commit();
                            $msg = "<div class='notification-card border-emerald-500/30 bg-emerald-500/10 text-emerald-400'><i data-lucide='trending-up' class='w-4 h-4 shrink-0'></i><span>Order Executed: Long position allocated + " . sprintf(($crypto_asset==='BTC'?'%.6f':'%.4f'), $crypto_yield) . " {$crypto_asset}</span></div>";
                        } else {
                            $msg = "<div class='notification-card border-rose-500/30 bg-rose-500/10 text-rose-400'><i data-lucide='alert-triangle' class='w-4 h-4 shrink-0'></i><span>Order Aborted: Insufficient localized fiat pool allocations.</span></div>";
                        }
                    } 
                    // ------------------------------------------
                    // ROUTE B: LIQUIDATE CRYPTO TO CAPTURE FIAT
                    // ------------------------------------------
                    elseif ($trade_action === 'sell') {
                        if ($wallet[$crypto_col] >= $crypto_yield) {
                            $conn->beginTransaction();
                            
                            $stmt_sub = $conn->prepare("UPDATE wallets SET $crypto_col = $crypto_col - ? WHERE user_id = ? AND wallet_type = ?");
                            $stmt_sub->execute([$crypto_yield, $user_id, $mode]);
                            
                            $stmt_add = $conn->prepare("UPDATE wallets SET fiat_balance = fiat_balance + ? WHERE user_id = ? AND wallet_type = ?");
                            $stmt_add->execute([$fiat_volume, $user_id, $mode]);
                            
                            $tx_hash = "0x" . hash('sha256', $user_id . time() . mt_rand() . "SELL");
                            $stmt_tx = $conn->prepare("INSERT INTO transactions (sender_id, receiver_id, wallet_type, amount_sent, currency_sent, amount_received, currency_received, tx_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt_tx->execute([$user_id, $user_id, $mode, $crypto_yield, $crypto_asset, $fiat_volume, $base_currency, $tx_hash]);

                            $conn->commit();
                            $msg = "<div class='notification-card border-emerald-500/30 bg-emerald-500/10 text-emerald-400'><i data-lucide='trending-down' class='w-4 h-4 shrink-0'></i><span>Order Executed: Portfolio liquidated. + " . number_format($fiat_volume, 2) . " {$base_currency} matched.</span></div>";
                        } else {
                            $msg = "<div class='notification-card border-rose-500/30 bg-rose-500/10 text-rose-400'><i data-lucide='alert-triangle' class='w-4 h-4 shrink-0'></i><span>Order Aborted: Portfolio token balance depth insufficient for liquidation.</span></div>";
                        }
                    }
                } catch (Exception $e) {
                    if ($conn->inTransaction()) { $conn->rollBack(); }
                    error_log("trade.php ledger exception: " . $e->getMessage());
                    $msg = "<div class='notification-card border-rose-500/30 bg-rose-500/10 text-rose-400'><i data-lucide='shield-alert' class='w-4 h-4 shrink-0'></i><span>Core Ledger Engine Exception. Please try again later.</span></div>";
                }
            }
        }
    }
}

// Re-read structural layout matrix balances cleanly after any update lifecycle
$stmt_refresh = $conn->prepare("SELECT * FROM wallets WHERE user_id = ? AND wallet_type = ? LIMIT 1");
$stmt_refresh->execute([$user_id, $mode]);
$live_wallet = $stmt_refresh->fetch();

include 'sidebar.php';
?>

<div class="max-w-xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-white sm:text-3xl bg-gradient-to-r from-white to-slate-500 bg-clip-text text-transparent">Liquidity Swap & Spot Desk</h1>
        <p class="text-xs text-slate-400 mt-1 font-light">Direct interaction node mapping for lightning-fast localized portfolio rebalancing.</p>
    </div>

    <?= $msg ?>

    <div class="glass-panel p-6 rounded-2xl shadow-2xl relative">
        <form method="POST" class="space-y-5">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-mono text-slate-400 uppercase tracking-wider mb-2">Order Side Execution</label>
                    <select name="trade_action" id="trade_action" class="input-dark w-full text-xs p-3.5 rounded-xl font-mono cursor-pointer bg-[#060913]">
                        <option value="buy" class="text-emerald-400 font-bold">BUY (Acquire Crypto Asset)</option>
                        <option value="sell" class="text-rose-400 font-bold">SELL (Liquidate to Cash)</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-[10px] font-mono text-slate-400 uppercase tracking-wider mb-2">Target Asset Token Index</label>
                    <select name="crypto_asset" id="crypto_asset" class="input-dark w-full text-xs p-3.5 rounded-xl font-mono cursor-pointer bg-[#060913]">
                        <option value="SOL">SOL (Solana Token Layer)</option>
                        <option value="USDC">USDC (Solana Stable Layer)</option>
                        <option value="BTC">BTC (Bitcoin Asset Base)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-mono text-slate-400 uppercase tracking-wider mb-2">Assigned Capital Reserve Allocation (<?= $base_currency ?>)</label>
                <div class="relative flex items-center">
                    <i data-lucide="coins" class="w-4 h-4 text-slate-600 absolute left-3.5 pointer-events-none"></i>
                    <input type="number" step="0.01" name="fiat_amount" id="fiat_amount" placeholder="0.00" required class="input-dark w-full text-xs p-3.5 pl-11 rounded-xl font-mono">
                </div>
                <span class="text-[9px] font-mono text-slate-600 mt-1.5 block px-1 text-right">
                    Available Pool Capacity: <span class="text-slate-400 font-bold"><?= number_format($live_wallet['fiat_balance'] ?? 0.00, 2) . ' ' . $base_currency ?></span>
                </span>
            </div>

            <div class="bg-slate-950/40 p-4 rounded-xl border border-slate-900/60 font-mono text-xs flex justify-between items-center">
                <span class="text-slate-500" id="preview_label">Estimated Counter-Asset Yield Allocation:</span>
                <span id="trade_preview" class="text-emerald-400 font-bold text-sm">-</span>
            </div>

            <button type="submit" name="commit_spot_order" id="submit_btn" class="w-full bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-slate-950 font-extrabold text-xs p-4 rounded-xl uppercase tracking-wider transition-all duration-150 font-mono shadow-lg shadow-emerald-500/10">
                Commit Long Position Framework
            </button>
        </form>
    </div>
</div>

<script>
    const rates = <?= json_encode($rates) ?>;
    const baseCurrency = "<?= $base_currency ?>";

    const tradeAction = document.getElementById('trade_action');
    const cryptoAsset = document.getElementById('crypto_asset');
    const fiatAmount  = document.getElementById('fiat_amount');
    const tradePreview = document.getElementById('trade_preview');
    const previewLabel = document.getElementById('preview_label');
    const submitBtn    = document.getElementById('submit_btn');

    function calculateTradeMatrix() {
        const fiatVal = parseFloat(fiatAmount.value);
        if(!fiatVal || isNaN(fiatVal)) { 
            tradePreview.innerText = "-"; 
            return; 
        }

        // Processing Conversion Logic Loops
        const fiatInUSD = fiatVal / rates[baseCurrency];
        const cryptoOutput = fiatInUSD * rates[cryptoAsset.value];
        const isBtc = (cryptoAsset.value === 'BTC');
        
        tradePreview.innerText = `${cryptoOutput.toFixed(isBtc ? 6 : 4)} ${cryptoAsset.value}`;

        // Dynamic Text Context Rewriting
        if (tradeAction.value === 'buy') {
            previewLabel.innerText = "Estimated Counter-Asset Yield Allocation:";
            tradePreview.className = "text-emerald-400 font-bold text-sm";
            submitBtn.innerText = "Commit Long Position Framework";
            submitBtn.className = "w-full bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-slate-950 font-extrabold text-xs p-4 rounded-xl uppercase tracking-wider transition-all duration-150 font-mono shadow-lg shadow-emerald-500/10";
        } else {
            previewLabel.innerText = "Liquidation Deductible Volume Metric:";
            tradePreview.className = "text-rose-400 font-bold text-sm";
            submitBtn.innerText = "Execute Portfolio Asset Liquidation";
            submitBtn.className = "w-full bg-gradient-to-r from-rose-500 to-amber-600 hover:from-rose-400 hover:to-amber-500 text-slate-950 font-extrabold text-xs p-4 rounded-xl uppercase tracking-wider transition-all duration-150 font-mono shadow-lg shadow-rose-500/10";
        }
    }

    fiatAmount.addEventListener('input', calculateTradeMatrix);
    tradeAction.addEventListener('change', calculateTradeMatrix);
    cryptoAsset.addEventListener('change', calculateTradeMatrix);
</script>

<?php include 'footer.php'; ?>