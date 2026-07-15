<?php
require 'auth.php';

$msg = "";

// Institutional Internal Exchange Matrix Rates (Pinned relative to base USD)
$rates = app_rates();

// Map assets safely to database column targets
$balance_map = [
    'USD' => 'fiat_balance', 'NGN' => 'fiat_balance', 'GBP' => 'fiat_balance', 'EUR' => 'fiat_balance',
    'ARI' => 'ari_balance', 'SOL' => 'sol_balance', 'USDC' => 'usdc_balance'
];

// ==========================================
// ATOMIC SETTLEMENT DISPATCH ENGINE (POST)
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['execute_remittance'])) {
    $recipient_phone   = trim($_POST['recipient_phone']);
    $amount_to_send    = floatval($_POST['amount_sent']);
    $currency_sent     = $_POST['currency_sent'];
    $currency_received = $_POST['currency_received'];

    if ($amount_to_send <= 0) {
        $msg = notify('error', 'Transaction aborted: Invalid execution payload volume.', 'alert-circle');
    } else {
        // 1. Map target node destination parameters
        $stmt = $conn->prepare("SELECT id, base_currency FROM users WHERE phone = ? LIMIT 1");
        $stmt->execute([$recipient_phone]);
        $recipient = $stmt->fetch();

        if ($recipient) {
            $rec_id = $recipient['id'];

            if ($rec_id === $user_id) {
                $msg = notify('warning', 'Routing Failure: Source parameters match destination targets.', 'alert-triangle');
            } else {
                // 2. Query source liquidity depths
                $source_col = $balance_map[$currency_sent] ?? null;
                $target_col = $balance_map[$currency_received] ?? null;

                if (!$source_col || !$target_col) {
                    $msg = notify('error', 'Asset Mapping Engine: Unauthorized currency tier token.', 'shield-alert');
                } else {
                    $stmt_bal = $conn->prepare("SELECT $source_col FROM wallets WHERE user_id = ? AND wallet_type = ? LIMIT 1");
                    $stmt_bal->execute([$user_id, $mode]);
                    $sender_balance = $stmt_bal->fetchColumn();

                    // 3. Mathematical Parsing Layer (Convert everything safely via USD cross-rates)
                    $amount_in_usd = $amount_to_send / $rates[$currency_sent];
                    $deduction_amount = $amount_to_send;
                    $credit_amount = $amount_in_usd * $rates[$currency_received];

                    // Structural variance balancing if currency is standard fiat tied to custom localized settings
                    if ($source_col === 'fiat_balance' && $currency_sent !== $base_currency) {
                        $deduction_amount = $amount_in_usd * $rates[$base_currency];
                    }

                    // 4. Verification Check Constraints
                    if ($sender_balance >= $deduction_amount) {
                        try {
                            $conn->beginTransaction();

                            // Subtraction phase
                            $stmt_deduct = $conn->prepare("UPDATE wallets SET $source_col = $source_col - ? WHERE user_id = ? AND wallet_type = ?");
                            $stmt_deduct->execute([$deduction_amount, $user_id, $mode]);

                            // Recipient balance normalization handling
                            $recipient_credit = $credit_amount;
                            if ($target_col === 'fiat_balance') {
                                $rec_base = $recipient['base_currency'] ?: 'USD';
                                $recipient_credit = $amount_in_usd * $rates[$rec_base];
                            }

                            // Dynamic on-the-fly table checking to prevent row initialization failures
                            $stmt_check_rec = $conn->prepare("SELECT COUNT(*) FROM wallets WHERE user_id = ? AND wallet_type = ?");
                            $stmt_check_rec->execute([$rec_id, $mode]);
                            if ($stmt_check_rec->fetchColumn() == 0) {
                                // Initialize non-existent target wallet maps on the fly
                                $init_address = "0x" . hash('sha256', $rec_id . time());
                                $stmt_init = $conn->prepare("INSERT INTO wallets (user_id, wallet_type, blockchain_address, fiat_balance, ari_balance) VALUES (?, ?, ?, 0, 0)");
                                $stmt_init->execute([$rec_id, $mode, $init_address]);
                            }

                            // Credit phase
                            $stmt_credit = $conn->prepare("UPDATE wallets SET $target_col = $target_col + ? WHERE user_id = ? AND wallet_type = ?");
                            $stmt_credit->execute([$recipient_credit, $rec_id, $mode]);

                            // Cryptographic ledger log initialization
                            $tx_hash = insert_transaction($conn, $user_id, $rec_id, $mode, $amount_to_send, $currency_sent, $recipient_credit, $currency_received);

                            $conn->commit();
                            $msg = notify('success', 'Remittance block settled successfully. Tx: ' . substr($tx_hash, 0, 16) . '...', 'check-circle');
                        } catch (Exception $e) {
                            $conn->rollBack();
                            $msg = notify('error', 'Engine Processing Fault: ' . htmlspecialchars($e->getMessage()), 'shield-alert');
                        }
                    } else {
                        $msg = notify('error', 'Transaction rejected: Insufficient liquidity reserves.', 'wallet');
                    }
                }
            }
        } else {
            $msg = notify('error', 'Routing Failure: Specified user phone ledger destination not found.', 'user-x');
        }
    }
}

include 'sidebar.php';
?>

<div class="max-w-xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-white sm:text-3xl bg-gradient-to-r from-white to-slate-400 bg-clip-text text-transparent">Cross-Border Settlement Portal</h1>
        <p class="text-xs text-slate-400 mt-1 font-light">Execute multi-currency outbound transfers natively across decentralized network layers.</p>
    </div>

    <?= $msg ?>

    <div class="glass-panel p-6 rounded-2xl shadow-2xl relative">
        <form method="POST" class="space-y-5">
            <div>
                <label class="block text-[10px] font-mono text-slate-400 uppercase tracking-wider mb-2">Destination Channel ID (User Phone Mapping)</label>
                <div class="relative flex items-center">
                    <i data-lucide="smartphone" class="w-4 h-4 text-slate-600 absolute left-3.5 pointer-events-none"></i>
                    <input type="text" name="recipient_phone" placeholder="e.g. +2348000000000" required class="input-dark w-full text-xs p-3.5 pl-11 rounded-xl font-mono">
                </div>
            </div>
            
            <div>
                <label class="block text-[10px] font-mono text-slate-400 uppercase tracking-wider mb-2">Outbound Principal Amount</label>
                <div class="relative flex items-center">
                    <i data-lucide="banknote" class="w-4 h-4 text-slate-600 absolute left-3.5 pointer-events-none"></i>
                    <input type="number" step="0.000001" name="amount_sent" id="amount_sent" placeholder="0.00" required class="input-dark w-full text-xs p-3.5 pl-11 rounded-xl font-mono">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-mono text-slate-400 uppercase tracking-wider mb-2">Source Layer Asset</label>
                    <select name="currency_sent" id="currency_sent" class="input-dark w-full text-xs p-3.5 rounded-xl font-mono cursor-pointer bg-[#060913]">
                        <option value="USD">USD ($) Fiat</option>
                        <option value="NGN" <?= $base_currency == 'NGN' ? 'selected' : '' ?>>NGN (₦) Fiat</option>
                        <option value="GBP">GBP (£) Fiat</option>
                        <option value="EUR">EUR (€) Fiat</option>
                        <option value="ARI">ARI (Native Gas)</option>
                        <option value="SOL">SOL (Solana Native)</option>
                        <option value="USDC">USDC (Solana Stable)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-mono text-slate-400 uppercase tracking-wider mb-2">Target Settlement Asset</label>
                    <select name="currency_received" id="currency_received" class="input-dark w-full text-xs p-3.5 rounded-xl font-mono cursor-pointer bg-[#060913]">
                        <option value="USD">USD ($) Fiat</option>
                        <option value="NGN">NGN (₦) Fiat</option>
                        <option value="GBP">GBP (£) Fiat</option>
                        <option value="EUR">EUR (€) Fiat</option>
                        <option value="ARI">ARI (Native Gas)</option>
                        <option value="SOL">SOL (Solana Native)</option>
                        <option value="USDC">USDC (Solana Stable)</option>
                    </select>
                </div>
            </div>

            <div class="bg-slate-950/40 p-4 rounded-xl border border-slate-900/60 font-mono text-xs flex justify-between items-center">
                <span class="text-slate-500">Real-Time Yield Valuation Estimate:</span>
                <span id="preview_val" class="text-cyan-400 font-bold text-sm">-</span>
            </div>

            <button type="submit" name="execute_remittance" class="w-full bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-slate-950 font-extrabold text-xs p-4 rounded-xl uppercase tracking-wider transition-all duration-150 font-mono shadow-lg shadow-cyan-500/10">
                Execute Remittance Pipeline
            </button>
        </form>
    </div>
</div>

<script>
    const rates = <?= json_encode($rates) ?>;

    const amtInput = document.getElementById('amount_sent');
    const currSent = document.getElementById('currency_sent');
    const currRec  = document.getElementById('currency_received');
    const preview  = document.getElementById('preview_val');

    function calculateRemittance() {
        const val = parseFloat(amtInput.value);
        if(!val || isNaN(val)) { 
            preview.innerText = "-"; 
            return; 
        }
        
        // Formulate internal baseline reference parsing logic
        const amtInUSD = val / rates[currSent.value];
        const targetConversion = amtInUSD * rates[currRec.value];
        
        // Enforce high-precision layout metrics for digital crypto tokens
        const decimalThreshold = (currRec.value === 'SOL' || currRec.value === 'ARI') ? 4 : 2;
        preview.innerText = `${targetConversion.toFixed(decimalThreshold)} ${currRec.value}`;
    }

    amtInput.addEventListener('input', calculateRemittance);
    currSent.addEventListener('change', calculateRemittance);
    currRec.addEventListener('change', calculateRemittance);
</script>

<?php include 'footer.php'; ?>