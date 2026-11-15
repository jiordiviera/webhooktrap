<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\LegacyExportReader;

it('reads only the allow-listed fields and skips hash fields lexically', function (): void {
    $json = '[{"id":1,"email":"a@example.com","fullName":"A","password":"scrypt$16384$8$1$c2FsdA==$aGFzaA==","createdAt":"2025-01-02T03:04:05Z","nested":{"passwordHash":["x",{"y":"}"}]}}]';

    expect((new LegacyExportReader)->read($json))->toBe([
        ['email' => 'a@example.com', 'name' => 'A', 'created_at' => '2025-01-02T03:04:05Z'],
    ]);
});

it('gives the same rows whatever the hash fields hold, malformed included', function (string $hashValue): void {
    $json = '{"users":[{"email":"b@example.com","name":"B","password":'.$hashValue.',"created_at":"2024-06-01"}]}';

    expect((new LegacyExportReader)->read($json))->toBe([
        ['email' => 'b@example.com', 'name' => 'B', 'created_at' => '2024-06-01'],
    ]);
})->with([
    'scrypt-shaped' => '"scrypt$1$2$3$4$5"',
    'not a hash' => '"!!!not-a-hash\\"\\\\"',
    'number' => '12345',
    'null' => 'null',
    'object' => '{"salt":"]","key":[1,2,{"a":"["}]}',
]);
