<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$_SESSION['wallet_mode'] = (($_SESSION['wallet_mode'] ?? 'live') === 'live') ? 'demo' : 'live';

// Only allow redirecting back to a local page to prevent open redirects
// via an attacker-controlled Referer header.
$allowed = [
    'dashboard.php', 'remittance.php', 'trade.php', 'history.php',
    'prices.php', 'profile.php', 'cheque_in.php',
    'echeque_generate.php', 'echeque_deposit.php', 'payment.php'
];
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$target = basename(parse_url($referer, PHP_URL_PATH) ?? '');
$fallback = in_array($target, $allowed, true) ? $target : 'dashboard.php';

header("Location: " . $fallback);
exit();
