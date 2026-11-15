<?php

declare(strict_types=1);

use App\Infrastructure\RateLimit\ClientAddress;
use App\Modules\Identity\Domain\RecoveryCodeGenerator;
use App\Modules\Identity\Domain\Totp;

// RFC 6238 appendix B vectors (SHA-1, secret "12345678901234567890"), last 6 digits.
it('matches the RFC 6238 test vectors', function (int $time, string $code): void {
    expect(Totp::codeAt('12345678901234567890', Totp::step($time)))->toBe($code);
})->with([
    [59, '287082'],
    [1111111109, '081804'],
    [1111111111, '050471'],
    [1234567890, '005924'],
    [2000000000, '279037'],
]);

it('accepts ±1 step and never a step at or before the last used one', function (): void {
    $secret = '12345678901234567890';
    $t = 1234567890;
    $step = Totp::step($t);

    expect(Totp::verify($secret, Totp::codeAt($secret, $step - 1), $t, null))->toBe($step - 1)
        ->and(Totp::verify($secret, Totp::codeAt($secret, $step + 1), $t, null))->toBe($step + 1)
        ->and(Totp::verify($secret, Totp::codeAt($secret, $step + 2), $t, null))->toBeNull()
        ->and(Totp::verify($secret, Totp::codeAt($secret, $step), $t, $step))->toBeNull()
        ->and(Totp::verify($secret, 'abcdef', $t, null))->toBeNull();
});

it('encodes the secret in base32 for the otpauth URI', function (): void {
    expect(Totp::base32('12345678901234567890'))->toBe('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ')
        ->and(Totp::uri('12345678901234567890', 'maya@example.com', 'Webhook Trap'))
        ->toStartWith('otpauth://totp/Webhook%20Trap%3Amaya%40example.com?secret=GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ');
});

it('makes ten recovery codes of 60 bits, normalised before hashing', function (): void {
    $codes = RecoveryCodeGenerator::generate();
    expect($codes)->toHaveCount(10)
        ->and($codes[0])->toMatch('/^[0-9A-HJKMNP-TV-Z]{4}-[0-9A-HJKMNP-TV-Z]{4}-[0-9A-HJKMNP-TV-Z]{4}$/')
        ->and(RecoveryCodeGenerator::normalise(strtolower(str_replace('-', ' ', $codes[0]))))->toBe(RecoveryCodeGenerator::normalise($codes[0]));
});

it('keys IPv6 limits on the /64 and truncates stored networks to /24 and /48', function (): void {
    expect(ClientAddress::rateKey('2001:db8:1:2:aaaa::1'))->toBe(ClientAddress::rateKey('2001:db8:1:2:ffff::9'))
        ->and(ClientAddress::rateKey('2001:db8:1:3::1'))->not->toBe(ClientAddress::rateKey('2001:db8:1:2::1'))
        ->and(ClientAddress::rateKey('203.0.113.7'))->not->toBe(ClientAddress::rateKey('203.0.113.8'))
        ->and(ClientAddress::networkPrefix('203.0.113.7'))->toBe('203.0.113.0/24')
        ->and(ClientAddress::networkPrefix('2001:db8:abcd:12::1'))->toBe('2001:db8:abcd::/48');
});
