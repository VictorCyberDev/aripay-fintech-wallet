<?php
require 'db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$user_id = $_SESSION['user_id'];
$status_msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $new_currency = $_POST['base_currency'];
    $stmt = $conn->prepare("UPDATE users SET base_currency = ? WHERE id = ?");
    if($stmt->execute([$new_currency, $user_id])) {
        $_SESSION['base_currency'] = $new_currency;
        $status_msg = "<div class='notification-card border-emerald-500/30 bg-emerald-500/10 text-emerald-400'>Profile adjustments written successfully to remote cloud nodes.</div>";
    }
}

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$account_data = $stmt->fetch();

include 'sidebar.php';
?>

<div class="max-w-xl mx-auto space-y-6">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-white">Security & Profile Settings</h1>
        <p class="text-xs text-slate-400 mt-1">Configure user parameter thresholds, primary regional fiat currency matrices, and cloud identifiers.</p>
    </div>

    <?= $status_msg ?>

    <div class="glass-panel p-6 rounded-2xl space-y-4">
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-[10px] font-mono text-slate-400 uppercase tracking-wider mb-2">Registered Full Name</label>
                <input type="text" disabled value="<?= htmlspecialchars($account_data['fullname']) ?>" class="input-dark w-full text-xs p-3.5 rounded-xl font-mono opacity-50 cursor-not-allowed">
            </div>
            <div>
                <label class="block text-[10px] font-mono text-slate-400 uppercase tracking-wider mb-2">Assigned Target System Base Currency</label>
                <select name="base_currency" class="input-dark w-full text-xs p-3.5 rounded-xl font-mono cursor-pointer bg-[#060913]">
                    <option value="USD" <?= $account_data['base_currency'] == 'USD' ? 'selected' : '' ?>>USD ($) United States Dollar</option>
                    <option value="NGN" <?= $account_data['base_currency'] == 'NGN' ? 'selected' : '' ?>>NGN (₦) Nigerian Naira</option>
                    <option value="GBP" <?= $account_data['base_currency'] == 'GBP' ? 'selected' : '' ?>>GBP (£) British Pound</option>
                    <option value="EUR" <?= $account_data['base_currency'] == 'EUR' ? 'selected' : '' ?>>EUR (€) Euro Zone Currency</option>
                </select>
            </div>
            <button type="submit" name="update_profile" class="w-full bg-cyan-400 hover:bg-cyan-300 text-slate-950 font-bold text-xs p-4 rounded-xl uppercase tracking-wider transition-colors font-mono">
                Update Account Parameters
            </button>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>