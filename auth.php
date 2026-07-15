<?php
// ==========================================
// AUTHENTICATED PAGE BOOTSTRAP
// Wires up the database, session, and access
// guard shared by every signed-in page, and
// exposes the common session parameters.
// ==========================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

ini_set('display_errors', 0); // Production secure fallback

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id       = $_SESSION['user_id'];
$mode          = $_SESSION['wallet_mode'] ?? 'live';
$base_currency = $_SESSION['base_currency'] ?? 'USD';
