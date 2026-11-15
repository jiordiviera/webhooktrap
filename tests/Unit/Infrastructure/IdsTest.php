<?php

declare(strict_types=1);

use App\Infrastructure\Clock\Clock;
use App\Infrastructure\Ids\EntityId;
use App\Infrastructure\Ids\IdGenerator;
use Carbon\CarbonImmutable;

it('encodes a UUID as prefix + 26 Crockford base32 characters and back', function (): void {
    $uuid = '0193a4c2-7f1e-7abc-8def-0123456789ab';

    $id = EntityId::encode('run', $uuid);

    expect($id)->toMatch('/^run_[0-7][0-9A-HJKMNP-TV-Z]{25}$/')
        ->and(EntityId::decode($id))->toBe(['run', $uuid]);
});

it('encodes the extremes of the 128-bit range', function (): void {
    expect(EntityId::encode('usr', '00000000-0000-0000-0000-000000000000'))->toBe('usr_'.str_repeat('0', 26))
        ->and(EntityId::encode('usr', 'ffffffff-ffff-ffff-ffff-ffffffffffff'))->toBe('usr_7'.str_repeat('Z', 25));
});

it('refuses unknown prefixes and malformed ids', function (string $id): void {
    EntityId::decode($id);
})->throws(InvalidArgumentException::class)->with([
    'unknown prefix' => 'abc_01M75F1M56RSH0CVKCTFX4FQWC',
    'too short' => 'run_01M75F1M56',
    'excluded letter' => 'run_01M75F1M56RSH0CVKCTFX4FQWI',
    'overflow' => 'run_81M75F1M56RSH0CVKCTFX4FQWC',
]);

it('generates time-ordered UUIDv7 values from the Clock', function (): void {
    $clock = new class implements Clock
    {
        public CarbonImmutable $at;

        public function now(): CarbonImmutable
        {
            return $this->at;
        }
    };
    $ids = new IdGenerator($clock);

    $clock->at = CarbonImmutable::parse('2026-11-15 10:00:00', 'UTC');
    $first = $ids->uuid();
    $clock->at = CarbonImmutable::parse('2026-11-15 10:00:01', 'UTC');
    $second = $ids->uuid();

    expect($first[14])->toBe('7')
        ->and(strcmp($first, $second))->toBeLessThan(0)
        ->and($ids->external('cap'))->toStartWith('cap_');
});
