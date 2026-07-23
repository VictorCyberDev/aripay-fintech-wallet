<?php
require 'bootstrap.php';
session_start();
$_SESSION['wallet_mode'] = (($_SESSION['wallet_mode'] ?? 'live') === 'live') ? 'demo' : 'live';
$fallback = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';
header("Location: " . $fallback);
exit();