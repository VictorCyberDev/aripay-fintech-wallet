<?php
require 'db.php';
session_start();

$msg = "";

// NOTE: Needs ONE new column on the users table. Run once in phpMyAdmin:
// ALTER TABLE users ADD COLUMN reset_token VARCHAR(64) NULL;
// ALTER TABLE users ADD COLUMN reset_token_expires DATETIME NULL;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['request_reset'])) {
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = "<div class='notification-card border-rose-500/30 bg-rose-500/10 text-rose-400'>Enter a valid email address.</div>";
    } else {
        $stmt = $conn->prepare("SELECT id, fullname FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Always show the same message whether or not the account exists,
        // so no one can use this form to check which emails are registered.
        $msg = "<div class='notification-card border-emerald-500/30 bg-emerald-500/10 text-emerald-400'>If an account exists for that email, a reset link has been sent.</div>";

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmt_update = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
            $stmt_update->execute([$token, $expires, $user['id']]);

            $reset_link = "https://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . $token;

            // Send email using PHP's built-in mail() function (works on most
            // Hostinger shared hosting without extra setup/cost).
            $subject = "Reset your Ari-Pay password";
            $body = "Hi " . $user['fullname'] . ",\n\n"
                  . "Click the link below to reset your password. This link expires in 1 hour.\n\n"
                  . $reset_link . "\n\n"
                  . "If you didn't request this, you can ignore this email.";
            $headers = "From: no-reply@" . $_SERVER['HTTP_HOST'];

            @mail($email, $subject, $body, $headers);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — Ari-Pay</title>
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
            <h2 class="text-xl font-bold text-white tracking-tight mb-1">Reset Password</h2>
            <p class="text-xs text-slate-400 mb-6 font-light">Enter your email and we'll send a reset link.</p>

            <?= $msg ?>

            <form method="POST" class="space-y-4 mt-4">
                <div>
                    <label class="block text-[11px] font-mono font-medium uppercase tracking-wider text-slate-400 mb-1.5">Email Address</label>
                    <input type="email" name="email" required placeholder="name@institution.com"
                           class="w-full bg-slate-950 border border-slate-800 focus:border-cyan-400 text-sm p-3 rounded-lg text-white placeholder-slate-600 focus:outline-none focus:ring-1 focus:ring-cyan-400 transition">
                </div>
                <button type="submit" name="request_reset" class="w-full bg-cyan-400 hover:bg-cyan-300 text-slate-950 font-bold text-sm p-3.5 rounded-lg transition mt-2">
                    Send Reset Link
                </button>
            </form>

            <div class="border-t border-slate-900 mt-6 pt-5 text-center">
                <p class="text-xs text-slate-400 font-light">
                    <a href="login.php" class="text-cyan-400 hover:underline">Back to Login &rarr;</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
