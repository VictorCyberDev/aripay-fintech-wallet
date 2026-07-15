<?php
require 'auth.php';

$msg = "";

// NOTE: Uses the same `cheques` table created for echeque_generate.php.
// No new table needed here.

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['redeem_cheque'])) {
    $serial = trim($_POST['serial_number']);

    $stmt_c = $conn->prepare("SELECT * FROM cheques WHERE serial_number = ? AND wallet_type = ? LIMIT 1");
    $stmt_c->execute([$serial, $mode]);
    $cheque = $stmt_c->fetch(PDO::FETCH_ASSOC);

    if (!$cheque) {
        $msg = notify('error', 'No cheque found matching that serial number.', 'search-x');
    } elseif ($cheque['status'] !== 'active') {
        $msg = notify('warning', 'This cheque has already been ' . htmlspecialchars($cheque['status']) . '.', 'alert-triangle');
    } elseif ($cheque['issuer_id'] == $user_id) {
        $msg = notify('warning', 'You cannot redeem a cheque you issued yourself.', 'alert-triangle');
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
            insert_transaction($conn, $cheque['issuer_id'], $user_id, $mode, $cheque['amount'], $cheque['currency'], $cheque['amount'], $cheque['currency']);

            $conn->commit();
            $msg = notify('success', 'Cheque redeemed: +' . number_format($cheque['amount'], 2) . ' ' . $cheque['currency'] . ' credited to your wallet.', 'check-circle');
        } catch (Exception $e) {
            $conn->rollBack();
            $msg = notify('error', 'Error: ' . htmlspecialchars($e->getMessage()), 'shield-alert');
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
