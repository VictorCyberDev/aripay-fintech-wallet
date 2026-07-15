<?php
require 'auth.php';

$msg = "";

// High-Throughput Baseline Exchange Index Variables (Relative to 1.00 USD)
$rates = app_rates();

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
        $msg = notify('error', 'Execution Denied: Order volume parameters out of algorithmic bounds.', 'alert-circle');
    } else {
        $crypto_col = $balance_map[$crypto_asset] ?? null;

        if (!$crypto_col) {
            $msg = notify('error', 'Routing Failure: Unmapped ledger target symbol asset.', 'shield-alert');
        } else {
            // Read wallet indexes inside an isolated state snapshot
            $stmt_w = $conn->prepare("SELECT fiat_balance, $crypto_col FROM wallets WHERE user_id = ? AND wallet_type = ? LIMIT 1");
            $stmt_w->execute([$user_id, $mode]);
            $wallet = $stmt_w->fetch();

            if (!$wallet) {
                $msg = notify('error', 'Account Mapping Error: Underlying wallet matrix not instantiated.', 'user-x');
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
                            
                            // Internal trade registers system user as both sender and destination consumer
                            insert_transaction($conn, $user_id, $user_id, $mode, $fiat_volume, $base_currency, $crypto_yield, $crypto_asset, "BUY");

                            $conn->commit();
                            $msg = notify('success', 'Order Executed: Long position allocated + ' . sprintf(($crypto_asset==='BTC'?'%.6f':'%.4f'), $crypto_yield) . " {$crypto_asset}", 'trending-up');
                        } else {
                            $msg = notify('warning', 'Order Aborted: Insufficient localized fiat pool allocations.', 'alert-triangle');
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
                            
                            insert_transaction($conn, $user_id, $user_id, $mode, $crypto_yield, $crypto_asset, $fiat_volume, $base_currency, "SELL");

                            $conn->commit();
                            $msg = notify('success', 'Order Executed: Portfolio liquidated. + ' . number_format($fiat_volume, 2) . " {$base_currency} matched.", 'trending-down');
                        } else {
                            $msg = notify('warning', 'Order Aborted: Portfolio token balance depth insufficient for liquidation.', 'alert-triangle');
                        }
                    }
                } catch (Exception $e) {
                    if ($conn->inTransaction()) { $conn->rollBack(); }
                    $msg = notify('error', 'Core Ledger Engine Exception: ' . htmlspecialchars($e->getMessage()), 'shield-alert');
                }
            }
        }
    }
}

// Re-read structural layout matrix balances cleanly after any update lifecycle
$live_wallet = fetch_active_wallet($conn, $user_id, $mode);

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