<?php
// ==========================================
// CORE DEBUGGING & SECURITY ARCHITECTURE
// ==========================================
ini_set('display_errors', 0); // Turned off for security; flip to 1 if debugging layout bottlenecks
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

// Institutional Internal Exchange Matrix Rates (Pinned relative to base USD)
$rates = [
    'USD' => 1.0, 
    'NGN' => 1500.0, 
    'GBP' => 0.78, 
    'EUR' => 0.92,
    'ARI' => 0.80, 
    'SOL' => 0.0068,  // $145.00 USD baseline simulation
    'USDC' => 1.00    // Solana Stablecoin Asset 
];

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
        $msg = "<div class='notification-card border-rose-500/30 bg-rose-500/10 text-rose-400'><i data-lucide='alert-circle' class='w-4 h-4 shrink-0'></i><span>Transaction aborted: Invalid execution payload volume.</span></div>";
    } else {
        // 1. Map target node destination parameters
        $stmt = $conn->prepare("SELECT id, base_currency FROM users WHERE phone = ? LIMIT 1");
        $stmt->execute([$recipient_phone]);
        $recipient = $stmt->fetch();

        if ($recipient) {
            $rec_id = $recipient['id'];

            if ($rec_id === $user_id) {
                $msg = "<div class='notification-card border-amber-500/30 bg-amber-500/10 text-amber-400'><i data-lucide='alert-triangle' class='w-4 h-4 shrink-0'></i><span>Routing Failure: Source parameters match destination targets.</span></div>";
            } else {
                // 2. Query source liquidity depths
                $source_col = $balance_map[$currency_sent] ?? null;
                $target_col = $balance_map[$currency_received] ?? null;

                if (!$source_col || !$target_col) {
                    $msg = "<div class='notification-card border-rose-500/30 bg-rose-500/10 text-rose-400'><i data-lucide='shield-alert' class='w-4 h-4 shrink-0'></i><span>Asset Mapping Engine: Unauthorized currency tier token.</span></div>";
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
                            $tx_hash = "0x" . hash('sha256', $user_id . $rec_id . time() . mt_rand());
                            $stmt_tx = $conn->prepare("INSERT INTO transactions (sender_id, receiver_id, wallet_type, amount_sent, currency_sent, amount_received, currency_received, tx_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt_tx->execute([$user_id, $rec_id, $mode, $amount_to_send, $currency_sent, $recipient_credit, $currency_received, $tx_hash]);

                            $conn->commit();
                            $msg = "<div class='notification-card border-emerald-500/30 bg-emerald-500/10 text-emerald-400'><i data-lucide='check-circle' class='w-4 h-4 shrink-0'></i><span>Remittance block settled successfully. Tx: " . substr($tx_hash, 0, 16) . "...</span></div>";
                        } catch (Exception $e) {
                            $conn->rollBack();
                            $msg = "<div class='notification-card border-rose-500/30 bg-rose-500/10 text-rose-400'><i data-lucide='shield-alert' class='w-4 h-4 shrink-0'></i><span>Engine Processing Fault: " . htmlspecialchars($e->getMessage()) . "</span></div>";
                        }
                    } else {
                        $msg = "<div class='notification-card border-rose-500/30 bg-rose-500/10 text-rose-400'><i data-lucide='wallet' class='w-4 h-4 shrink-0'></i><span>Transaction rejected: Insufficient liquidity reserves.</span></div>";
                    }
                }
            }
        } else {
            $msg = "<div class='notification-card border-rose-500/30 bg-rose-500/10 text-rose-400'><i data-lucide='user-x' class='w-4 h-4 shrink-0'></i><span>Routing Failure: Specified user phone ledger destination not found.</span></div>";
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