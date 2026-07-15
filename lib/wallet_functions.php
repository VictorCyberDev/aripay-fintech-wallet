<?php
/**
 * Ari-Pay shared business logic.
 *
 * Pure, side-effect-free helpers extracted from the page scripts so the core
 * financial logic (currency conversion, validation, identifier generation)
 * can be unit tested in isolation and reused without duplication.
 */

/**
 * Convert an amount in an arbitrary currency into its USD baseline value.
 *
 * All internal exchange rates are pinned relative to 1.00 USD, so the USD
 * value is simply the amount divided by the currency's rate.
 *
 * @param array<string,float> $rates
 */
function convert_to_usd(float $amount, string $currency, array $rates): float
{
    if (!isset($rates[$currency])) {
        throw new InvalidArgumentException("Unknown source currency: {$currency}");
    }
    if ((float) $rates[$currency] == 0.0) {
        throw new InvalidArgumentException("Invalid zero rate for currency: {$currency}");
    }

    return $amount / $rates[$currency];
}

/**
 * Convert a USD baseline value into an arbitrary target currency.
 *
 * @param array<string,float> $rates
 */
function convert_from_usd(float $usd, string $currency, array $rates): float
{
    if (!isset($rates[$currency])) {
        throw new InvalidArgumentException("Unknown target currency: {$currency}");
    }

    return $usd * $rates[$currency];
}

/**
 * Convert an amount between two currencies via the USD cross-rate.
 *
 * @param array<string,float> $rates
 */
function convert_currency(float $amount, string $from, string $to, array $rates): float
{
    return convert_from_usd(convert_to_usd($amount, $from, $rates), $to, $rates);
}

/**
 * Map a currency/asset symbol to its wallet balance column.
 *
 * Returns $default (null unless overridden) when the symbol is not mapped,
 * which callers treat as an unauthorized/unknown asset.
 *
 * @param array<string,string> $map
 */
function resolve_balance_column(string $currency, array $map, ?string $default = null): ?string
{
    return $map[$currency] ?? $default;
}

/**
 * Derive a user's default base currency from their phone routing prefix.
 */
function base_currency_from_phone(string $phone): string
{
    $phone = preg_replace('/\s+/', '', trim($phone));

    if (str_starts_with($phone, '+234')) {
        return 'NGN';
    }
    if (str_starts_with($phone, '+44')) {
        return 'GBP';
    }
    if (str_starts_with($phone, '+27')) {
        return 'ZAR';
    }
    if (str_starts_with($phone, '+33') || str_starts_with($phone, '+49')) {
        return 'EUR';
    }

    return 'USD';
}

/**
 * Validate an email address using PHP's native filter.
 */
function is_valid_email(string $email): bool
{
    return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Enforce the minimum password security policy (at least 8 characters).
 */
function is_strong_password(string $password): bool
{
    return strlen($password) >= 8;
}

/**
 * Display precision (decimal places) for a given asset symbol.
 */
function asset_decimals(string $asset): int
{
    if ($asset === 'BTC') {
        return 6;
    }
    if ($asset === 'SOL' || $asset === 'ARI') {
        return 4;
    }

    return 2;
}

/**
 * Format an asset amount to its canonical precision without grouping.
 */
function format_asset_amount(float $amount, string $asset): string
{
    return number_format($amount, asset_decimals($asset), '.', '');
}

/**
 * Format an internal account identifier as a zero-padded node address.
 */
function format_account_id($id): string
{
    return 'NODE://' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
}

/**
 * Generate a pseudo-random blockchain wallet address (0x + 40 hex chars).
 */
function generate_blockchain_address(): string
{
    return '0x' . bin2hex(random_bytes(20));
}

/**
 * Generate a unique e-cheque serial number: ARI-CHQ-XXXXXXXXXXXX.
 */
function generate_cheque_serial(): string
{
    return 'ARI-CHQ-' . strtoupper(bin2hex(random_bytes(6)));
}

/**
 * Build a deterministic transaction hash (0x + sha256) from a seed string.
 */
function generate_tx_hash(string $seed): string
{
    return '0x' . hash('sha256', $seed);
}

/**
 * Determine whether a cheque can be redeemed by a given user.
 *
 * Returns null when redemption is allowed, otherwise a machine-readable
 * reason code: 'not_found', 'already_<status>' or 'self_issued'.
 *
 * @param array<string,mixed>|null $cheque
 */
function cheque_redemption_error(?array $cheque, $user_id): ?string
{
    if ($cheque === null) {
        return 'not_found';
    }
    if (($cheque['status'] ?? null) !== 'active') {
        return 'already_' . ($cheque['status'] ?? 'unknown');
    }
    if ((string) ($cheque['issuer_id'] ?? '') === (string) $user_id) {
        return 'self_issued';
    }

    return null;
}
