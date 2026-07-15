<?php
require 'db.php';
session_start();

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Sanitize input parameters
    $fullname = trim($_POST['fullname']);
    $email    = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $phone    = preg_replace('/\s+/', '', trim($_POST['phone'])); // Strip whitespaces
    $raw_pw   = $_POST['password'];

    // 2. Server-side Validation
    if (empty($fullname) || empty($email) || empty($phone) || empty($raw_pw)) {
        $error = "All fields are strictly required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please provide a valid corporate or personal email address.";
    } elseif (strlen($raw_pw) < 8) {
        $error = "Security Policy: Password must be at least 8 characters long.";
    } else {
        // 3. Native Secure Hash
        $password = password_hash($raw_pw, PASSWORD_BCRYPT);

        // 4. Robust Currency Routing Logic
        $base_currency = "USD"; // Institutional Fallback
        if (str_starts_with($phone, '+234')) {
            $base_currency = "NGN";
        } elseif (str_starts_with($phone, '+44')) {
            $base_currency = "GBP";
        } elseif (str_starts_with($phone, '+27')) {
            $base_currency = "ZAR";
        } elseif (str_starts_with($phone, '+33') || str_starts_with($phone, '+49')) {
            $base_currency = "EUR";
        }

        try {
            $conn->beginTransaction();

            // Check if user credentials already exist
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR phone = ? LIMIT 1");
            $check_stmt->execute([$email, $phone]);
            if ($check_stmt->fetch()) {
                throw new Exception("Account credentials already provisioned inside the system.", 409);
            }

            // Provision User Account Node
            $stmt = $conn->prepare("INSERT INTO users (fullname, email, phone, password, base_currency) VALUES (?, ?, ?, ?, ?)");
            // FIXED: Removed the accidental trailing comma here
            $stmt->execute([$fullname, $email, $phone, $password, $base_currency]);
            $user_id = $conn->lastInsertId();

            // Generate Cryptographically Secure Pseudo-Random Blockchain Addresses
            $blockchain_address_demo = "0x" . bin2hex(random_bytes(20));
            $blockchain_address_live = "0x" . bin2hex(random_bytes(20));

            // Allocate Assets to Demo Environment ($10,000.00 sandbox allocation)
            $stmt_wallet = $conn->prepare("INSERT INTO wallets (user_id, wallet_type, ari_balance, fiat_balance, blockchain_address) VALUES (?, ?, ?, ?, ?)");
            $stmt_wallet->execute([$user_id, 'demo', 1000.00, 10000.00, $blockchain_address_demo]);

            // Allocate Empty Production Ledger Node
            $stmt_wallet_live = $conn->prepare("INSERT INTO wallets (user_id, wallet_type, ari_balance, fiat_balance, blockchain_address) VALUES (?, ?, ?, ?, ?)");
            $stmt_wallet_live->execute([$user_id, 'live', 0.00, 0.00, $blockchain_address_live]);

            $conn->commit();
            
            $_SESSION['registration_success'] = true;
            header("Location: login.php");
            exit();

        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            
            if ($e->getCode() === 409) {
                $error = $e->getMessage();
            } else {
                error_log("register.php provisioning failure: " . $e->getMessage());
                $error = "System infrastructure conflict. Please try again later.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Sovereign Account — Ari-Pay Infrastructure</title>
    
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
            <h2 class="text-xl font-bold text-white tracking-tight mb-1">Create Account</h2>
            <p class="text-xs text-slate-400 mb-6 font-light">Deploys automated clearing and multi-currency storage capabilities.</p>
            
            <?php if(!empty($error)): ?>
                <div class="bg-red-500/10 border border-red-500/20 text-red-400 text-xs px-4 py-3 rounded-lg mb-5 flex items-start gap-2 font-mono">
                    <span class="font-bold text-red-500">[ERROR]</span>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-[11px] font-mono font-medium uppercase tracking-wider text-slate-400 mb-1.5">Full Name</label>
                    <input type="text" name="fullname" required autocomplete="name" placeholder="John Doe"
                           value="<?= isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : '' ?>"
                           class="w-full bg-slate-950 border border-slate-800 focus:border-cyan-400 text-sm p-3 rounded-lg text-white placeholder-slate-600 focus:outline-none focus:ring-1 focus:ring-cyan-400 transition">
                </div>

                <div>
                    <label class="block text-[11px] font-mono font-medium uppercase tracking-wider text-slate-400 mb-1.5">Email Address</label>
                    <input type="email" name="email" required autocomplete="email" placeholder="name@institution.com"
                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                           class="w-full bg-slate-950 border border-slate-800 focus:border-cyan-400 text-sm p-3 rounded-lg text-white placeholder-slate-600 focus:outline-none focus:ring-1 focus:ring-cyan-400 transition">
                </div>

                <div>
                    <label class="block text-[11px] font-mono font-medium uppercase tracking-wider text-slate-400 mb-1.5">Phone Corridor <span class="text-slate-600 font-sans">(with routing prefix)</span></label>
                    <input type="text" name="phone" placeholder="+2348012345678" required autocomplete="tel"
                           value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>"
                           class="w-full bg-slate-950 border border-slate-800 focus:border-cyan-400 text-sm p-3 rounded-lg text-white placeholder-slate-600 focus:outline-none focus:ring-1 focus:ring-cyan-400 transition font-mono">
                </div>

                <div>
                    <label class="block text-[11px] font-mono font-medium uppercase tracking-wider text-slate-400 mb-1.5">System Password</label>
                    <input type="password" name="password" required autocomplete="new-password" placeholder="••••••••"
                           class="w-full bg-slate-950 border border-slate-800 focus:border-cyan-400 text-sm p-3 rounded-lg text-white placeholder-slate-600 focus:outline-none focus:ring-1 focus:ring-cyan-400 transition">
                </div>

                <button type="submit" class="w-full bg-cyan-400 hover:bg-cyan-300 text-slate-950 font-bold text-sm p-3.5 rounded-lg transition transform active:scale-[0.99] mt-6 shadow-md shadow-cyan-400/5 focus:outline-none focus:ring-2 focus:ring-cyan-400 focus:ring-offset-2 focus:ring-offset-slate-950">
                    Deploy Sandbox Architecture
                </button>
            </form>

            <div class="border-t border-slate-900 mt-6 pt-5 text-center">
                <p class="text-xs text-slate-400 font-light">
                    Existing cleared node? <a href="login.php" class="text-cyan-400 hover:underline font-normal ml-0.5">Sign in to Account &rarr;</a>
                </p>
            </div>
        </div>

        <footer class="text-center mt-8 text-[10px] text-slate-600 font-mono">
            SECURE SHA-256 TRANSMISSION LAYER ACTIVE
        </footer>
    </div>

</body>
</html>