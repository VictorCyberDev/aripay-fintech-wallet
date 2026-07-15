<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the shared Ari-Pay business logic in lib/wallet_functions.php.
 */
final class WalletFunctionsTest extends TestCase
{
    /** @var array<string,float> */
    private array $rates = [
        'USD'  => 1.0,
        'NGN'  => 1500.0,
        'GBP'  => 0.78,
        'EUR'  => 0.92,
        'ARI'  => 0.80,
        'SOL'  => 0.0068,
        'USDC' => 1.00,
        'BTC'  => 0.000015,
    ];

    // ---------------------------------------------------------------------
    // convert_to_usd
    // ---------------------------------------------------------------------

    public function testConvertToUsdForBaseCurrencyIsIdentity(): void
    {
        $this->assertSame(100.0, convert_to_usd(100.0, 'USD', $this->rates));
    }

    public function testConvertToUsdDividesByRate(): void
    {
        // 1500 NGN at a rate of 1500/USD => 1 USD
        $this->assertSame(1.0, convert_to_usd(1500.0, 'NGN', $this->rates));
    }

    public function testConvertToUsdThrowsOnUnknownCurrency(): void
    {
        $this->expectException(InvalidArgumentException::class);
        convert_to_usd(10.0, 'XXX', $this->rates);
    }

    public function testConvertToUsdThrowsOnZeroRate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        convert_to_usd(10.0, 'DEAD', ['DEAD' => 0.0]);
    }

    // ---------------------------------------------------------------------
    // convert_from_usd
    // ---------------------------------------------------------------------

    public function testConvertFromUsdMultipliesByRate(): void
    {
        $this->assertSame(1500.0, convert_from_usd(1.0, 'NGN', $this->rates));
    }

    public function testConvertFromUsdThrowsOnUnknownCurrency(): void
    {
        $this->expectException(InvalidArgumentException::class);
        convert_from_usd(10.0, 'XXX', $this->rates);
    }

    // ---------------------------------------------------------------------
    // convert_currency
    // ---------------------------------------------------------------------

    public function testConvertCurrencySameCurrencyRoundTrips(): void
    {
        $this->assertEqualsWithDelta(250.0, convert_currency(250.0, 'EUR', 'EUR', $this->rates), 1e-9);
    }

    public function testConvertCurrencyCrossRate(): void
    {
        // 1500 NGN -> USD (1.0) -> GBP (0.78)
        $this->assertEqualsWithDelta(0.78, convert_currency(1500.0, 'NGN', 'GBP', $this->rates), 1e-9);
    }

    public function testConvertCurrencyToCrypto(): void
    {
        // 100 USD -> 100 USD -> SOL (0.0068) = 0.68 SOL
        $this->assertEqualsWithDelta(0.68, convert_currency(100.0, 'USD', 'SOL', $this->rates), 1e-9);
    }

    public function testConvertCurrencyIsReversible(): void
    {
        $forward = convert_currency(500.0, 'USD', 'EUR', $this->rates);
        $back    = convert_currency($forward, 'EUR', 'USD', $this->rates);
        $this->assertEqualsWithDelta(500.0, $back, 1e-9);
    }

    // ---------------------------------------------------------------------
    // resolve_balance_column
    // ---------------------------------------------------------------------

    public function testResolveBalanceColumnKnownSymbol(): void
    {
        $map = ['ARI' => 'ari_balance', 'SOL' => 'sol_balance'];
        $this->assertSame('sol_balance', resolve_balance_column('SOL', $map));
    }

    public function testResolveBalanceColumnUnknownReturnsNullByDefault(): void
    {
        $this->assertNull(resolve_balance_column('DOGE', ['ARI' => 'ari_balance']));
    }

    public function testResolveBalanceColumnUsesProvidedDefault(): void
    {
        $this->assertSame(
            'fiat_balance',
            resolve_balance_column('DOGE', ['ARI' => 'ari_balance'], 'fiat_balance')
        );
    }

    // ---------------------------------------------------------------------
    // base_currency_from_phone
    // ---------------------------------------------------------------------

    /**
     * @dataProvider phonePrefixProvider
     */
    public function testBaseCurrencyFromPhone(string $phone, string $expected): void
    {
        $this->assertSame($expected, base_currency_from_phone($phone));
    }

    /**
     * @return array<string,array{0:string,1:string}>
     */
    public static function phonePrefixProvider(): array
    {
        return [
            'Nigeria'             => ['+2348012345678', 'NGN'],
            'United Kingdom'      => ['+447700900000', 'GBP'],
            'South Africa'        => ['+27831234567', 'ZAR'],
            'France'              => ['+33612345678', 'EUR'],
            'Germany'             => ['+491512345678', 'EUR'],
            'Unknown falls to USD' => ['+11234567890', 'USD'],
            'Whitespace stripped' => [' +234 801 234 5678 ', 'NGN'],
            'No prefix'           => ['08012345678', 'USD'],
        ];
    }

    // ---------------------------------------------------------------------
    // is_valid_email
    // ---------------------------------------------------------------------

    public function testValidEmailPasses(): void
    {
        $this->assertTrue(is_valid_email('user@institution.com'));
    }

    public function testValidEmailTrimsWhitespace(): void
    {
        $this->assertTrue(is_valid_email('  user@institution.com  '));
    }

    /**
     * @dataProvider invalidEmailProvider
     */
    public function testInvalidEmailFails(string $email): void
    {
        $this->assertFalse(is_valid_email($email));
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function invalidEmailProvider(): array
    {
        return [
            'no at'      => ['userinstitution.com'],
            'no domain'  => ['user@'],
            'empty'      => [''],
            'spaces only' => ['   '],
        ];
    }

    // ---------------------------------------------------------------------
    // is_strong_password
    // ---------------------------------------------------------------------

    public function testPasswordAtBoundaryIsAccepted(): void
    {
        $this->assertTrue(is_strong_password('12345678'));
    }

    public function testPasswordBelowBoundaryIsRejected(): void
    {
        $this->assertFalse(is_strong_password('1234567'));
    }

    public function testEmptyPasswordIsRejected(): void
    {
        $this->assertFalse(is_strong_password(''));
    }

    // ---------------------------------------------------------------------
    // asset_decimals / format_asset_amount
    // ---------------------------------------------------------------------

    public function testAssetDecimals(): void
    {
        $this->assertSame(6, asset_decimals('BTC'));
        $this->assertSame(4, asset_decimals('SOL'));
        $this->assertSame(4, asset_decimals('ARI'));
        $this->assertSame(2, asset_decimals('USD'));
        $this->assertSame(2, asset_decimals('USDC'));
    }

    public function testFormatAssetAmountPrecision(): void
    {
        $this->assertSame('0.123457', format_asset_amount(0.12345678, 'BTC'));
        $this->assertSame('0.6800', format_asset_amount(0.68, 'SOL'));
        $this->assertSame('1234.57', format_asset_amount(1234.5678, 'USD'));
    }

    public function testFormatAssetAmountHasNoThousandsSeparator(): void
    {
        $this->assertSame('1000000.00', format_asset_amount(1000000, 'USD'));
    }

    // ---------------------------------------------------------------------
    // format_account_id
    // ---------------------------------------------------------------------

    public function testFormatAccountIdPadsShortIds(): void
    {
        $this->assertSame('NODE://00042', format_account_id(42));
    }

    public function testFormatAccountIdDoesNotTruncateLongIds(): void
    {
        $this->assertSame('NODE://123456', format_account_id(123456));
    }

    // ---------------------------------------------------------------------
    // identifier generators
    // ---------------------------------------------------------------------

    public function testGenerateBlockchainAddressFormat(): void
    {
        $address = generate_blockchain_address();
        $this->assertMatchesRegularExpression('/^0x[0-9a-f]{40}$/', $address);
    }

    public function testGenerateBlockchainAddressIsUnique(): void
    {
        $this->assertNotSame(generate_blockchain_address(), generate_blockchain_address());
    }

    public function testGenerateChequeSerialFormat(): void
    {
        $serial = generate_cheque_serial();
        $this->assertMatchesRegularExpression('/^ARI-CHQ-[0-9A-F]{12}$/', $serial);
    }

    public function testGenerateTxHashIsDeterministicForSameSeed(): void
    {
        $this->assertSame(generate_tx_hash('seed-123'), generate_tx_hash('seed-123'));
    }

    public function testGenerateTxHashFormatAndSensitivity(): void
    {
        $hash = generate_tx_hash('seed-123');
        $this->assertMatchesRegularExpression('/^0x[0-9a-f]{64}$/', $hash);
        $this->assertNotSame($hash, generate_tx_hash('seed-124'));
    }

    // ---------------------------------------------------------------------
    // cheque_redemption_error
    // ---------------------------------------------------------------------

    public function testChequeRedemptionAllowedReturnsNull(): void
    {
        $cheque = ['status' => 'active', 'issuer_id' => 5];
        $this->assertNull(cheque_redemption_error($cheque, 9));
    }

    public function testChequeRedemptionNotFound(): void
    {
        $this->assertSame('not_found', cheque_redemption_error(null, 9));
    }

    public function testChequeRedemptionAlreadyRedeemed(): void
    {
        $cheque = ['status' => 'redeemed', 'issuer_id' => 5];
        $this->assertSame('already_redeemed', cheque_redemption_error($cheque, 9));
    }

    public function testChequeRedemptionVoided(): void
    {
        $cheque = ['status' => 'void', 'issuer_id' => 5];
        $this->assertSame('already_void', cheque_redemption_error($cheque, 9));
    }

    public function testChequeRedemptionSelfIssuedBlocked(): void
    {
        $cheque = ['status' => 'active', 'issuer_id' => 9];
        $this->assertSame('self_issued', cheque_redemption_error($cheque, 9));
    }

    public function testChequeRedemptionSelfIssuedComparesLoosely(): void
    {
        // issuer_id from the DB is often a numeric string; user id an int.
        $cheque = ['status' => 'active', 'issuer_id' => '9'];
        $this->assertSame('self_issued', cheque_redemption_error($cheque, 9));
    }
}
