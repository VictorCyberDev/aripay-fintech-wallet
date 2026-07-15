<?php
require 'bootstrap.php';
require 'db.php';
session_start();

$error = "";
$success_message = "";

// Capture flash success message from registration redirection
if (isset($_SESSION['registration_success']) && $_SESSION['registration_success'] === true) {
    $success_message = "Registration successful . Authenticate login access below.";
    unset($_SESSION['registration_success']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email    = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $raw_pw   = $_POST['password'];

    if (empty($email) || empty($raw_pw)) {
        $error = "All security parameters must be filled.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid format structure for email addresses.";
    } else {
        try {
            // Fetch account details matching email identifier
            $stmt = $conn->prepare("SELECT id, fullname, password, base_currency FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Cryptographic boundary cross-check verification matching BCRYPT standards
            if ($user && password_verify($raw_pw, $user['password'])) {
                // Regenerate session ID to prevent session fixation attacks
                session_regenerate_id(true);

                // Populate secure session parameters
                $_SESSION['user_id']       = $user['id'];
                $_SESSION['fullname']      = $user['fullname'];
                $_SESSION['base_currency']  = $user['base_currency'];
                $_SESSION['wallet_mode']    = 'demo'; // Default initializing wallet framework state

                header("Location: dashboard.php");
                exit();
            } else {
                // Keep errors generic for system hardening (preventing user enumeration)
                $error = "Authentication failed. Invalid cryptographic credentials.";
            }
        } catch (PDOException $e) {
            log_app_error('Login query failed', $e);
            $error = "System infrastructure conflict: Secure gateway timeout.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gateway Authentication — Ari-Pay Infrastructure</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] }
                }
            }
        }
    </script>
</head>
<body class="bg-[#090D16] text-slate-200 font-sans antialiased min-h-screen flex items-center justify-center p-4 relative overflow-hidden">

    <div class="absolute inset-0 bg-[linear-gradient(to_right,#1e293b_1px,transparent_1px),linear-gradient(to_bottom,#1e293b_1px,transparent_1px)] bg-[size:4rem_4rem] pointer-events-none opacity-[0.10]"></div>

    <div class="relative w-full max-w-md z-10">
        <div class="text-center mb-8">
            <a href="index.php" class="inline-flex items-center gap-2 text-lg font-bold tracking-tight text-white focus:outline-none">
                <span class="h-5 w-2 bg-cyan-400 rounded-sm"></span>ARI-PAY
            </a>
            <p class="text-xs text-slate-500 mt-2 uppercase tracking-widest font-mono">Unified Fintech Ledger Protocol</p>
        </div>

        <div class="border border-slate-800/80 bg-slate-950/60 p-6 sm:p-8 rounded-xl shadow-2xl backdrop-blur-md">
            <h2 class="text-xl font-bold text-white tracking-tight mb-1">Access Page</h2>
            <p class="text-xs text-slate-400 mb-6 font-light"> sign in to your borderless clearance account.</p>
            
            <?php if(!empty($success_message)): ?>
                <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs px-4 py-3 rounded-lg mb-5 flex items-start gap-2 font-mono">
                    <span class="font-bold text-emerald-500">[SUCCESS]</span>
                    <span><?= htmlspecialchars($success_message) ?></span>
                </div>
            <?php endif; ?>

            <?php if(!empty($error)): ?>
                <div class="bg-red-500/10 border border-red-500/20 text-red-400 text-xs px-4 py-3 rounded-lg mb-5 flex items-start gap-2 font-mono">
                    <span class="font-bold text-red-500">[ERROR]</span>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-[11px] font-mono font-medium uppercase tracking-wider text-slate-400 mb-1.5">Node Email Address</label>
                    <input type="email" name="email" required autocomplete="email" placeholder="name@institution.com"
                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                           class="w-full bg-slate-950 border border-slate-800 focus:border-cyan-400 text-sm p-3 rounded-lg text-white placeholder-slate-600 focus:outline-none focus:ring-1 focus:ring-cyan-400 transition">
                </div>

                <div>
                    <div class="flex justify-between items-center mb-1.5">
                        <label class="block text-[11px] font-mono font-medium uppercase tracking-wider text-slate-400">Security Password</label>
                    </div>
                    <input type="password" name="password" required autocomplete="current-password" placeholder="••••••••"
                           class="w-full bg-slate-950 border border-slate-800 focus:border-cyan-400 text-sm p-3 rounded-lg text-white placeholder-slate-600 focus:outline-none focus:ring-1 focus:ring-cyan-400 transition">
                </div>

                <button type="submit" class="w-full bg-cyan-400 hover:bg-cyan-300 text-slate-950 font-bold text-sm p-3.5 rounded-lg transition transform active:scale-[0.99] mt-6 shadow-md shadow-cyan-400/5 focus:outline-none focus:ring-2 focus:ring-cyan-400 focus:ring-offset-2 focus:ring-offset-slate-950">
                    Login
                </button>
            </form>

            <div class="border-t border-slate-900 mt-6 pt-5 text-center">
                <p class="text-xs text-slate-400 font-light">
                    Register <a href="register.php" class="text-cyan-400 hover:underline font-normal ml-0.5">Deploy sandbox architecture &rarr;</a>
                </p>
            </div>
        </div>

        <footer class="text-center mt-8 text-[10px] text-slate-600 font-mono">
            SECURE ACCESS DECRYPTION ACTIVE
        </footer>
    </div>

</body>
</html>