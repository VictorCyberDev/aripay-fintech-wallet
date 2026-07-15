<?php
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
            $error = "System infrastructure conflict: Secure gateway timeout.";
        }
    }
}
?>
<?php
$page_title    = 'Gateway Authentication — Ari-Pay Infrastructure';
$card_title    = 'Access Page';
$card_subtitle = ' sign in to your borderless clearance account.';
include 'public_layout_top.php';
?>

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
<?php
$footer_prompt = 'Register <a href="register.php" class="text-cyan-400 hover:underline font-normal ml-0.5">Deploy sandbox architecture &rarr;</a>';
$footer_note   = 'SECURE ACCESS DECRYPTION ACTIVE';
include 'public_layout_bottom.php';
?>