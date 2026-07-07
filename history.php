<?php
// ==========================================
// CORE SECURITY & LEDGER ROUTING INTERFACE
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

// High-performance explicit JOIN optimization to track matching user identities
$query = "SELECT 
            t.id, 
            t.sender_id, 
            t.receiver_id, 
            t.amount_sent, 
            t.currency_sent, 
            t.amount_received, 
            t.currency_received, 
            t.tx_hash, 
            t.created_at,
            s.fullname AS sender_name,
            r.fullname AS receiver_name
          FROM transactions t
          LEFT JOIN users s ON t.sender_id = s.id
          LEFT JOIN users r ON t.receiver_id = r.id
          WHERE (t.sender_id = ? OR t.receiver_id = ?) AND t.wallet_type = ?
          ORDER BY t.created_at DESC";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute([$user_id, $user_id, $mode]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Graceful error fallback tracking for database auditing
    $transactions = [];
    $error_msg = "Ledger Sync Engine Failure: " . $e->getMessage();
}

include 'sidebar.php';
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-900 pb-6">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-white sm:text-3xl bg-gradient-to-r from-white to-slate-400 bg-clip-text text-transparent">Cryptographic Ledger</h1>
            <p class="text-xs text-slate-400 mt-1 font-light">Real-time immutable historic transaction pipeline output logging.</p>
        </div>
        <div class="font-mono text-[10px] bg-slate-950 px-3 py-1.5 rounded-xl border border-slate-900 text-slate-500">
            SYNCED BLOCKS: <span class="text-cyan-400 font-bold"><?= count($transactions) ?> LOGS</span>
        </div>
    </div>

    <?php if (isset($error_msg)): ?>
        <div class="notification-card border-rose-500/30 bg-rose-500/10 text-rose-400">
            <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
            <span><?= htmlspecialchars($error_msg) ?></span>
        </div>
    <?php endif; ?>

    <div class="glass-panel rounded-2xl overflow-hidden shadow-2xl border border-slate-900/50">
        <div class="overflow-x-auto">
            <table class="w-full text-left font-mono text-xs">
                <thead class="bg-[#050812] text-slate-500 uppercase tracking-wider border-b border-slate-900/80 text-[9px] font-bold">
                    <tr>
                        <th class="p-4 pl-6">Transaction Hash ID</th>
                        <th class="p-4">Route Classification</th>
                        <th class="p-4">Asset Payload Delivery</th>
                        <th class="p-4">Counterparty Channel</th>
                        <th class="p-4 pr-6 text-right">Timestamp (UTC)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-900/60 bg-slate-950/10">
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="5" class="p-12 text-center text-slate-600 font-sans text-xs">
                                <div class="flex flex-col items-center justify-center space-y-2">
                                    <i data-lucide="database-zap" class="w-6 h-6 text-slate-700"></i>
                                    <span>No localized cryptographic block interactions synced on current cluster path.</span>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $tx): 
                            // Determine directional flow parameters relative to signed-in operator node
                            $is_sender = ($tx['sender_id'] == $user_id);
                            $is_internal_swap = ($tx['sender_id'] == $tx['receiver_id']);
                        ?>
                            <tr class="hover:bg-slate-900/30 transition-colors duration-150 group">
                                <td class="p-4 pl-6 font-bold text-slate-400 group-hover:text-cyan-400 transition-colors select-all">
                                    <span class="hidden sm:inline"><?= htmlspecialchars($tx['tx_hash']) ?></span>
                                    <span class="sm:hidden"><?= substr(htmlspecialchars($tx['tx_hash']), 0, 14) ?>...</span>
                                </td>
                                
                                <td class="p-4">
                                    <?php if ($is_internal_swap): ?>
                                        <span class="px-2 py-0.5 rounded text-[9px] font-extrabold tracking-tight bg-cyan-500/10 text-cyan-400 border border-cyan-500/10">
                                            SPOT_SWAP
                                        </span>
                                    <?php elseif ($is_sender): ?>
                                        <span class="px-2 py-0.5 rounded text-[9px] font-extrabold tracking-tight bg-rose-500/10 text-rose-400 border border-rose-500/10">
                                            OUTBOUND
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded text-[9px] font-extrabold tracking-tight bg-emerald-500/10 text-emerald-400 border border-emerald-500/10">
                                            INBOUND
                                        </span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="p-4 text-white font-bold tracking-tight">
                                    <?php if ($is_internal_swap): ?>
                                        <div class="flex items-center gap-1.5 text-slate-300">
                                            <span><?= number_format($tx['amount_sent'], (in_array($tx['currency_sent'], ['BTC','SOL','ARI']) ? 5 : 2)) ?> <?= $tx['currency_sent'] ?></span>
                                            <i data-lucide="arrow-right" class="w-3 h-3 text-slate-600"></i>
                                            <span class="text-cyan-400"><?= number_format($tx['amount_received'], (in_array($tx['currency_received'], ['BTC','SOL','ARI']) ? 5 : 2)) ?> <?= $tx['currency_received'] ?></span>
                                        </div>
                                    <?php elseif ($is_sender): ?>
                                        <span class="text-rose-400">
                                            -<?= number_format($tx['amount_sent'], (in_array($tx['currency_sent'], ['BTC','SOL','ARI']) ? 5 : 2)) ?> <span class="text-[10px] text-slate-500 font-normal"><?= $tx['currency_sent'] ?></span>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-emerald-400">
                                            +<?= number_format($tx['amount_received'], (in_array($tx['currency_received'], ['BTC','SOL','ARI']) ? 5 : 2)) ?> <span class="text-[10px] text-slate-500 font-normal"><?= $tx['currency_received'] ?></span>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="p-4 text-slate-400 text-[11px]">
                                    <?php if ($is_internal_swap): ?>
                                        <span class="text-slate-600 font-sans italic">Internal Vault Node</span>
                                    <?php elseif ($is_sender): ?>
                                        <div class="flex items-center gap-1">
                                            <span class="text-slate-500 text-[9px]">TO:</span>
                                            <span class="truncate max-w-[120px] inline-block font-sans"><?= htmlspecialchars($tx['receiver_name'] ?? 'External Address') ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div class="flex items-center gap-1">
                                            <span class="text-slate-500 text-[9px]">FROM:</span>
                                            <span class="truncate max-w-[120px] inline-block font-sans"><?= htmlspecialchars($tx['sender_name'] ?? 'System Core') ?></span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="p-4 pr-6 text-right text-slate-500 select-none">
                                    <?= date('Y-m-d H:i:s', strtotime($tx['created_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>