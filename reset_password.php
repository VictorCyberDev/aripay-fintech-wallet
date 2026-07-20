<?php
require 'db.php';

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$msg = "";
$valid = false;

if (empty($token)) {
    $msg = "<div class='notification-card border-rose-500/30 bg-rose-500/10 text-rose-400'>Invalid or missing reset link.</div>";
} else {
    $stmt = $conn->prepare("SELECT id, reset_token_expires FROM users WHERE reset_token = ? LIMIT 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $msg = "<div class='notification-card border-rose-500/30 bg-rose-500/10 text-rose-400'>This reset link is invalid or has already been used.</div>";
    } elseif (strtotime($user['reset_token_expires']) < time()) {
        $msg = "<div class='notification-card border-rose-500/30 bg-rose-500/10 text-rose-400'>This reset link has expired. Please request a new one.</div>";
    } else {
        $valid = true;
    }
}

if ($valid && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset_password'])) {
    $new_pw = $_POST['password'];
    $confirm_pw = $_POST['confirm_password'];

    if (strlen($new_pw) < 8) {
        $msg = "<div class='notification-card border-rose-500/30 bg-rose-500/10 text-rose-400'>Password must be at least 8 characters.</div>";
    } elseif ($new_pw !== $confirm_pw) {
        $msg = "<div class='notification-card border-rose-500/30 bg-rose-500/10 text-rose-400'>Passwords do not match.</div>";
    } else {
        $hashed = password_hash($new_pw, PASSWORD_BCRYPT);
        $stmt_update = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
        $stmt_update->execute([$hashed, $user['id']]);

        $msg = "<div class='notification-card border-emerald-500/30 bg-emerald-500/10 text-emerald-400'>Password updated successfully. You can now log in.</div>";
        $valid = false; // hide the form after success
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password — Ari-Pay</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#090D16] text-slate-200 font-sans antialiased min-h-screen flex items-center justify-center p-4">
    <div class="relative w-full max-w-md z-10">
        <div class="text-center mb-8">
            <a href="index.php" class="inline-flex items-center gap-2 text-lg font-bold tracking-tight text-white">
                <span class="h-5 w-2 bg-cyan-400 rounded-sm"></span>ARI-PAY
            </a>
        </div>

        <div class="border border-slate-800/80 bg-slate-950/60 p-6 sm:p-8 rounded-xl shadow-2xl backdrop-blur-md">
            <h2 class="text-xl font-bold text-white tracking-tight mb-1">Set New Password</h2>

            <?= $msg ?>

            <?php if ($valid): ?>
            <form method="POST" class="space-y-4 mt-4">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <div>
                    <label class="block text-[11px] font-mono font-medium uppercase tracking-wider text-slate-400 mb-1.5">New Password</label>
                    <input type="password" name="password" required placeholder="••••••••"
                           class="w-full bg-slate-950 border border-slate-800 focus:border-cyan-400 text-sm p-3 rounded-lg text-white placeholder-slate-600 focus:outline-none focus:ring-1 focus:ring-cyan-400 transition">
                </div>
                <div>
                    <label class="block text-[11px] font-mono font-medium uppercase tracking-wider text-slate-400 mb-1.5">Confirm Password</label>
                    <input type="password" name="confirm_password" required placeholder="••••••••"
                           class="w-full bg-slate-950 border border-slate-800 focus:border-cyan-400 text-sm p-3 rounded-lg text-white placeholder-slate-600 focus:outline-none focus:ring-1 focus:ring-cyan-400 transition">
                </div>
                <button type="submit" name="reset_password" class="w-full bg-cyan-400 hover:bg-cyan-300 text-slate-950 font-bold text-sm p-3.5 rounded-lg transition mt-2">
                    Update Password
                </button>
            </form>
            <?php else: ?>
            <div class="border-t border-slate-900 mt-6 pt-5 text-center">
                <p class="text-xs text-slate-400 font-light">
                    <a href="login.php" class="text-cyan-400 hover:underline">Back to Login &rarr;</a>
                </p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
