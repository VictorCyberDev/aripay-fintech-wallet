<?php
require 'auth.php';

$msg = "";
$new_cheque = null;

// NOTE: This requires a `cheques` table. Run this SQL once if it doesn't exist yet:
// CREATE TABLE cheques (
//   id INT AUTO_INCREMENT PRIMARY KEY,
//   serial_number VARCHAR(32) UNIQUE NOT NULL,
//   issuer_id INT NOT NULL,
//   amount DECIMAL(18,2) NOT NULL,
//   currency VARCHAR(10) NOT NULL,
//   status ENUM('active','redeemed','void') DEFAULT 'active',
//   wallet_type VARCHAR(10) NOT NULL,
//   created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
//   redeemed_at TIMESTAMP NULL,
//   redeemed_by INT NULL
// );

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['generate_cheque'])) {
    $amount = floatval($_POST['amount']);
    $currency = $_POST['currency'];

    if ($amount <= 0) {
        $msg = notify('error', 'Enter a valid amount greater than zero.', 'alert-circle');
    } else {
        // Check sender has enough balance in the relevant column
        $balance_map = ['USD'=>'fiat_balance','NGN'=>'fiat_balance','GBP'=>'fiat_balance','EUR'=>'fiat_balance','ARI'=>'ari_balance'];
        $col = $balance_map[$currency] ?? 'fiat_balance';

        $stmt_w = $conn->prepare("SELECT $col FROM wallets WHERE user_id = ? AND wallet_type = ? LIMIT 1");
        $stmt_w->execute([$user_id, $mode]);
        $balance = $stmt_w->fetchColumn();

        if ($balance < $amount) {
            $msg = notify('error', 'Insufficient balance to issue this cheque.', 'wallet');
        } else {
            try {
                $conn->beginTransaction();

                // Generate a unique serial number: ARI-CHQ-XXXXXXXX
                $serial = "ARI-CHQ-" . strtoupper(bin2hex(random_bytes(6)));

                // Hold the funds by deducting from balance now (released back if voided)
                $stmt_deduct = $conn->prepare("UPDATE wallets SET $col = $col - ? WHERE user_id = ? AND wallet_type = ?");
                $stmt_deduct->execute([$amount, $user_id, $mode]);

                $stmt_insert = $conn->prepare("INSERT INTO cheques (serial_number, issuer_id, amount, currency, wallet_type) VALUES (?, ?, ?, ?, ?)");
                $stmt_insert->execute([$serial, $user_id, $amount, $currency, $mode]);

                $conn->commit();
                $new_cheque = ['serial' => $serial, 'amount' => $amount, 'currency' => $currency];
                $msg = notify('success', 'E-cheque generated successfully.', 'check-circle');
            } catch (Exception $e) {
                $conn->rollBack();
                $msg = notify('error', 'Error: ' . htmlspecialchars($e->getMessage()), 'shield-alert');
            }
        }
    }
}

// List of cheques this user has issued
$stmt_list = $conn->prepare("SELECT * FROM cheques WHERE issuer_id = ? AND wallet_type = ? ORDER BY created_at DESC LIMIT 10");
$stmt_list->execute([$user_id, $mode]);
$my_cheques = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

include 'sidebar.php';
?>

<div class="max-w-xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-white sm:text-3xl">Generate E-Cheque</h1>
        <p class="text-xs text-slate-400 mt-1 font-light">Issue a virtual cheque with a unique serial number for transaction purposes.</p>
    </div>

    <?= $msg ?>

    <?php if ($new_cheque): ?>
    <div class="glass-panel p-6 rounded-2xl border-cyan-500/20 bg-cyan-500/5 text-center space-y-2">
        <span class="text-[10px] font-mono text-cyan-400 uppercase tracking-widest block">Cheque Serial Number</span>
        <div class="text-xl font-bold font-mono text-white select-all"><?= $new_cheque['serial'] ?></div>
        <div class="text-xs text-slate-400">Amount: <?= number_format($new_cheque['amount'], 2) ?> <?= $new_cheque['currency'] ?></div>
        <p class="text-[10px] text-slate-500 mt-2">Share this serial number with the recipient. They can lodge it under "E-cheque In" to redeem it.</p>
    </div>
    <?php endif; ?>

    <div class="glass-panel p-6 rounded-2xl shadow-2xl">
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-[10px] font-mono text-slate-400 uppercase tracking-wider mb-2">Amount</label>
                <input type="number" step="0.01" name="amount" required placeholder="0.00" class="input-dark w-full text-xs p-3.5 rounded-xl font-mono">
            </div>
            <div>
                <label class="block text-[10px] font-mono text-slate-400 uppercase tracking-wider mb-2">Currency</label>
                <select name="currency" class="input-dark w-full text-xs p-3.5 rounded-xl font-mono cursor-pointer bg-[#060913]">
                    <option value="USD">USD ($)</option>
                    <option value="NGN" <?= $base_currency == 'NGN' ? 'selected' : '' ?>>NGN (₦)</option>
                    <option value="GBP">GBP (£)</option>
                    <option value="EUR">EUR (€)</option>
                    <option value="ARI">ARI (Native)</option>
                </select>
            </div>
            <button type="submit" name="generate_cheque" class="w-full bg-cyan-400 hover:bg-cyan-300 text-slate-950 font-bold text-xs p-4 rounded-xl uppercase tracking-wider transition-colors font-mono">
                Generate E-Cheque
            </button>
        </form>
    </div>

    <!-- Issued cheques list -->
    <div class="glass-panel rounded-2xl overflow-hidden">
        <div class="p-5 border-b border-slate-900 bg-slate-950/10">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-300">Your Issued Cheques</h3>
        </div>
        <div class="divide-y divide-slate-900">
            <?php if (empty($my_cheques)): ?>
                <div class="p-10 text-center text-slate-600 text-xs">No cheques issued yet.</div>
            <?php else: ?>
                <?php foreach ($my_cheques as $c): ?>
                <div class="p-4 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-mono text-slate-300 block select-all"><?= htmlspecialchars($c['serial_number']) ?></span>
                        <span class="text-[10px] text-slate-500"><?= date('M d, Y', strtotime($c['created_at'])) ?></span>
                    </div>
                    <div class="text-right">
                        <span class="text-xs font-bold text-white block"><?= number_format($c['amount'], 2) ?> <?= $c['currency'] ?></span>
                        <span class="text-[9px] font-mono uppercase <?= $c['status'] == 'active' ? 'text-amber-400' : ($c['status'] == 'redeemed' ? 'text-emerald-400' : 'text-slate-500') ?>">
                            <?= $c['status'] ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
