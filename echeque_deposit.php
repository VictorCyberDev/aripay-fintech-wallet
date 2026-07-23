<?php
require 'bootstrap.php';
require 'db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$user_id = $_SESSION['user_id'];
$mode = $_SESSION['wallet_mode'] ?? 'live';
$msg = "";

// NOTE: Uses the same `cheques` table created for echeque_generate.php.
// No new table needed here.

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['redeem_cheque'])) {
    $serial = trim($_POST['serial_number']);

    $stmt_c = $conn->prepare("SELECT * FROM cheques WHERE serial_number = ? AND wallet_type = ? LIMIT 1");
    $stmt_c->execute([$serial, $mode]);
    $cheque = $stmt_c->fetch(PDO::FETCH_ASSOC);

    if (!$cheque) {
        $msg = "<div class='notification-card border-rose-500/30 bg-rose-500/10 text-rose-400'><i data-lucide='search-x' class='w-4 h-4 shrink-0'></i><span>No cheque found matching that serial number.</span></div>";
    } elseif ($cheque['status'] !== 'active') {
        $msg = "<div class='notification-card border-amber-500/30 bg-amber-500/10 text-amber-400'><i data-lucide='alert-triangle' class='w-4 h-4 shrink-0'></i><span>This cheque has already been " . htmlspecialchars($cheque['status']) . ".</span></div>";
    } elseif ($cheque['issuer_id'] == $user_id) {
        $msg = "<div class='notification-card border-amber-500/30 bg-amber-500/10 text-amber-400'><i data-lucide='alert-triangle' class='w-4 h-4 shrink-0'></i><span>You cannot redeem a cheque you issued yourself.</span></div>";
    } else {
        $balance_map = ['USD'=>'fiat_balance','NGN'=>'fiat_balance','GBP'=>'fiat_balance','EUR'=>'fiat_balance','ARI'=>'ari_balance'];
        $col = $balance_map[$cheque['currency']] ?? 'fiat_balance';

        try {
            $conn->beginTransaction();

            // Credit the recipient's wallet
            $stmt_credit = $conn->prepare("UPDATE wallets SET $col = $col + ? WHERE user_id = ? AND wallet_type = ?");
            $stmt_credit->execute([$cheque['amount'], $user_id, $mode]);

            // Mark cheque as redeemed
            $stmt_update = $conn->prepare("UPDATE cheques SET status = 'redeemed', redeemed_at = NOW(), redeemed_by = ? WHERE id = ?");
            $stmt_update->execute([$user_id, $cheque['id']]);

            // Log it in the transactions table too, so it shows in history/receipts
            $tx_hash = "0x" . hash('sha256', $cheque['issuer_id'] . $user_id . time() . mt_rand());
            $stmt_tx = $conn->prepare("INSERT INTO transactions (sender_id, receiver_id, wallet_type, amount_sent, currency_sent, amount_received, currency_received, tx_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_tx->execute([$cheque['issuer_id'], $user_id, $mode, $cheque['amount'], $cheque['currency'], $cheque['amount'], $cheque['currency'], $tx_hash]);

            $conn->commit();
            $msg = "<div class='notification-card border-emerald-500/30 bg-emerald-500/10 text-emerald-400'><i data-lucide='check-circle' class='w-4 h-4 shrink-0'></i><span>Cheque redeemed: +" . number_format($cheque['amount'], 2) . " " . $cheque['currency'] . " credited to your wallet.</span></div>";
        } catch (Exception $e) {
            if ($conn->inTransaction()) { $conn->rollBack(); }
            log_app_error('E-cheque redemption failed', $e);
            $msg = user_error_notice('Could not redeem this cheque. Please try again.');
        }
    }
}

// List cheques this user has redeemed
$stmt_list = $conn->prepare("SELECT * FROM cheques WHERE redeemed_by = ? AND wallet_type = ? ORDER BY redeemed_at DESC LIMIT 10");
$stmt_list->execute([$user_id, $mode]);
$my_redemptions = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

include 'sidebar.php';
?>

<div class="max-w-xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-white sm:text-3xl">E-Cheque In</h1>
        <p class="text-xs text-slate-400 mt-1 font-light">Lodge an e-cheque using its serial number to redeem funds into your wallet.</p>
    </div>

    <?= $msg ?>

    <div class="glass-panel p-6 rounded-2xl shadow-2xl">
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-[10px] font-mono text-slate-400 uppercase tracking-wider mb-2">Cheque Serial Number</label>
                <input type="text" name="serial_number" required placeholder="ARI-CHQ-XXXXXXXXXXXX" class="input-dark w-full text-xs p-3.5 rounded-xl font-mono uppercase">
            </div>
            <button type="submit" name="redeem_cheque" class="w-full bg-cyan-400 hover:bg-cyan-300 text-slate-950 font-bold text-xs p-4 rounded-xl uppercase tracking-wider transition-colors font-mono">
                Lodge E-Cheque
            </button>
        </form>
    </div>

    <!-- Redemption history -->
    <div class="glass-panel rounded-2xl overflow-hidden">
        <div class="p-5 border-b border-slate-900 bg-slate-950/10">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-300">Your Redeemed Cheques</h3>
        </div>
        <div class="divide-y divide-slate-900">
            <?php if (empty($my_redemptions)): ?>
                <div class="p-10 text-center text-slate-600 text-xs">No cheques redeemed yet.</div>
            <?php else: ?>
                <?php foreach ($my_redemptions as $c): ?>
                <div class="p-4 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-mono text-slate-300 block select-all"><?= htmlspecialchars($c['serial_number']) ?></span>
                        <span class="text-[10px] text-slate-500"><?= date('M d, Y', strtotime($c['redeemed_at'])) ?></span>
                    </div>
                    <span class="text-xs font-bold text-emerald-400">+<?= number_format($c['amount'], 2) ?> <?= $c['currency'] ?></span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
