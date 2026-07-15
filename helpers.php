<?php
// ==========================================
// SHARED APPLICATION UTILITIES
// Pure helpers reused across Ari-Pay pages.
// ==========================================

/**
 * Canonical exchange-rate matrix (pinned relative to 1.00 USD).
 * Kept in one place so every page reads the same rates.
 */
function app_rates(): array
{
    return [
        'USD'  => 1.0,
        'NGN'  => 1500.0,
        'GBP'  => 0.78,
        'EUR'  => 0.92,
        'ARI'  => 0.80,
        'BTC'  => 0.000015,
        'ETH'  => 0.00028,
        'SOL'  => 0.0068,
        'USDC' => 1.00,
        'USDT' => 1.00,
    ];
}

/**
 * Build a notification-card banner with the app's standard markup.
 *
 * @param string      $type    One of 'success', 'error'/'danger', 'warning'.
 * @param string      $message Inner text/markup (caller escapes when needed).
 * @param string|null $icon    Optional lucide icon name.
 */
function notify(string $type, string $message, ?string $icon = null): string
{
    $palette = [
        'success' => 'emerald',
        'error'   => 'rose',
        'danger'  => 'rose',
        'warning' => 'amber',
    ];
    $color = $palette[$type] ?? 'rose';

    $classes = "notification-card border-{$color}-500/30 bg-{$color}-500/10 text-{$color}-400";

    if ($icon !== null) {
        return "<div class='{$classes}'><i data-lucide='{$icon}' class='w-4 h-4 shrink-0'></i><span>{$message}</span></div>";
    }

    return "<div class='{$classes}'>{$message}</div>";
}

/**
 * Generate an opaque, unique "0x..." transaction hash.
 * mt_rand() guarantees uniqueness even for identical inputs.
 */
function new_tx_hash(array $parts): string
{
    return "0x" . hash('sha256', implode('', $parts) . mt_rand());
}

/**
 * Insert a settled transaction row and return its generated hash.
 */
function insert_transaction(
    PDO $conn,
    $sender_id,
    $receiver_id,
    string $wallet_type,
    $amount_sent,
    string $currency_sent,
    $amount_received,
    string $currency_received,
    string $tag = ''
): string {
    $tx_hash = new_tx_hash([$sender_id, $receiver_id, time(), $tag]);
    $stmt = $conn->prepare(
        "INSERT INTO transactions (sender_id, receiver_id, wallet_type, amount_sent, currency_sent, amount_received, currency_received, tx_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $sender_id, $receiver_id, $wallet_type,
        $amount_sent, $currency_sent,
        $amount_received, $currency_received, $tx_hash,
    ]);

    return $tx_hash;
}

/**
 * Fetch the active wallet row for a user in the given mode.
 */
function fetch_active_wallet(PDO $conn, $user_id, string $mode)
{
    $stmt = $conn->prepare("SELECT * FROM wallets WHERE user_id = ? AND wallet_type = ? LIMIT 1");
    $stmt->execute([$user_id, $mode]);
    return $stmt->fetch();
}

/**
 * Fetch a user's transaction ledger (as sender or receiver) with counterparty names.
 *
 * @param int|null $limit Optional row cap; null returns the full ledger.
 */
function user_transactions(PDO $conn, $user_id, string $mode, ?int $limit = null): array
{
    $sql = "SELECT
                t.id, t.sender_id, t.receiver_id,
                t.amount_sent, t.currency_sent,
                t.amount_received, t.currency_received,
                t.tx_hash, t.created_at,
                s.fullname AS sender_name,
                r.fullname AS receiver_name
            FROM transactions t
            LEFT JOIN users s ON t.sender_id = s.id
            LEFT JOIN users r ON t.receiver_id = r.id
            WHERE (t.sender_id = ? OR t.receiver_id = ?) AND t.wallet_type = ?
            ORDER BY t.created_at DESC";

    if ($limit !== null) {
        $sql .= " LIMIT " . (int) $limit;
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute([$user_id, $user_id, $mode]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Format a token amount with higher precision for volatile crypto assets.
 */
function format_token_amount($amount, string $currency): string
{
    $decimals = in_array($currency, ['BTC', 'SOL', 'ARI'], true) ? 5 : 2;
    return number_format((float) $amount, $decimals);
}
