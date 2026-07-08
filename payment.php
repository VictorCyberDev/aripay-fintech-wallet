<?php
ini_set('display_errors', 0);
require 'db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$user_id = $_SESSION['user_id'];
$mode = $_SESSION['wallet_mode'] ?? 'live';
$base_currency = $_SESSION['base_currency'] ?? 'USD';

// Account + wallet details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$account = $stmt->fetch();

$stmt_w = $conn->prepare("SELECT * FROM wallets WHERE user_id = ? AND wallet_type = ? LIMIT 1");
$stmt_w->execute([$user_id, $mode]);
$wallet = $stmt_w->fetch();

// Recent receipts (same query pattern as history.php, limited to last 10)
$query = "SELECT 
            t.id, t.sender_id, t.receiver_id, t.amount_sent, t.currency_sent,
            t.amount_received, t.currency_received, t.tx_hash, t.created_at,
            s.fullname AS sender_name, r.fullname AS receiver_name
          FROM transactions t
          LEFT JOIN users s ON t.sender_id = s.id
          LEFT JOIN users r ON t.receiver_id = r.id
          WHERE (t.sender_id = ? OR t.receiver_id = ?) AND t.wallet_type = ?
          ORDER BY t.created_at DESC
          LIMIT 10";
$stmt_tx = $conn->prepare($query);
$stmt_tx->execute([$user_id, $user_id, $mode]);
$receipts = $stmt_tx->fetchAll(PDO::FETCH_ASSOC);

include 'sidebar.php';
?>

<div class="space-y-6">
    <div class="border-b border-slate-900 pb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-white sm:text-3xl">Payments</h1>
        <p class="text-xs text-slate-400 mt-1">Account details, receipts, and quick actions.</p>
    </div>

    <!-- Account details -->
    <div class="glass-panel p-6 rounded-2xl">
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-300 mb-4">Account Details</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
            <div>
                <span class="text-slate-500 block mb-1">Full Name</span>
                <span class="text-slate-200 font-semibold"><?= htmlspecialchars($account['fullname']) ?></span>
            </div>
            <div>
                <span class="text-slate-500 block mb-1">Phone</span>
                <span class="text-slate-200 font-semibold"><?= htmlspecialchars($account['phone']) ?></span>
            </div>
            <div>
                <span class="text-slate-500 block mb-1">Account ID</span>
                <span class="text-slate-200 font-mono">NODE://<?= str_pad($user_id, 5, '0', STR_PAD_LEFT) ?></span>
            </div>
            <div>
                <span class="text-slate-500 block mb-1">Base Currency</span>
                <span class="text-slate-200 font-semibold"><?= htmlspecialchars($base_currency) ?></span>
            </div>
        </div>
    </div>

    <!-- Quick actions -->
    <div class="grid grid-cols-3 gap-4">
        <a href="remittance.php" class="glass-panel p-5 rounded-2xl flex flex-col items-center gap-2 hover:border-cyan-500/30 transition-all">
            <div class="w-10 h-10 rounded-xl bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-cyan-400">
                <i data-lucide="send" class="w-5 h-5"></i>
            </div>
            <span class="text-xs font-semibold text-slate-300">Transfer</span>
        </a>
        <a href="withdraw.php" class="glass-panel p-5 rounded-2xl flex flex-col items-center gap-2 hover:border-emerald-500/30 transition-all">
            <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                <i data-lucide="banknote" class="w-5 h-5"></i>
            </div>
            <span class="text-xs font-semibold text-slate-300">Withdraw</span>
        </a>
        <a href="trade.php" class="glass-panel p-5 rounded-2xl flex flex-col items-center gap-2 hover:border-amber-500/30 transition-all">
            <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                <i data-lucide="repeat" class="w-5 h-5"></i>
            </div>
            <span class="text-xs font-semibold text-slate-300">Convert</span>
        </a>
    </div>

    <!-- Receipts -->
    <div class="glass-panel rounded-2xl overflow-hidden">
        <div class="p-5 border-b border-slate-900 bg-slate-950/10 flex items-center justify-between">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-300">Recent Receipts</h3>
            <a href="history.php" class="text-[10px] font-mono text-cyan-400 hover:underline">View all &rarr;</a>
        </div>
        <div class="divide-y divide-slate-900">
            <?php if (empty($receipts)): ?>
                <div class="p-10 text-center text-slate-600 text-xs">No transactions yet.</div>
            <?php else: ?>
                <?php foreach ($receipts as $tx):
                    $is_sender = ($tx['sender_id'] == $user_id);
                    $is_swap = ($tx['sender_id'] == $tx['receiver_id']);
                ?>
                <div class="p-4 flex items-center justify-between hover:bg-slate-900/20 transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-slate-900 border border-slate-800 flex items-center justify-center">
                            <?php if ($is_swap): ?>
                                <i data-lucide="repeat" class="w-4 h-4 text-cyan-400"></i>
                            <?php elseif ($is_sender): ?>
                                <i data-lucide="arrow-up-right" class="w-4 h-4 text-rose-400"></i>
                            <?php else: ?>
                                <i data-lucide="arrow-down-left" class="w-4 h-4 text-emerald-400"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span class="text-xs font-semibold text-slate-200 block">
                                <?= $is_swap ? 'Conversion' : ($is_sender ? 'Sent to ' . htmlspecialchars($tx['receiver_name'] ?? 'External') : 'Received from ' . htmlspecialchars($tx['sender_name'] ?? 'System')) ?>
                            </span>
                            <span class="text-[10px] font-mono text-slate-500"><?= date('M d, Y H:i', strtotime($tx['created_at'])) ?></span>
                        </div>
                    </div>
                    <div class="text-right font-mono text-xs">
                        <?php if ($is_sender && !$is_swap): ?>
                            <span class="text-rose-400 font-bold">-<?= number_format($tx['amount_sent'], 2) ?> <?= $tx['currency_sent'] ?></span>
                        <?php else: ?>
                            <span class="text-emerald-400 font-bold">+<?= number_format($tx['amount_received'], 2) ?> <?= $tx['currency_received'] ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
