<?php

declare(strict_types=1);

use App\Modules\Admin\Pages\AccountDetail;
use App\Modules\Admin\Pages\Accounts;
use App\Modules\Admin\Pages\Agents;
use App\Modules\Admin\Pages\Dashboard;
use App\Modules\Admin\Pages\Runners;
use App\Modules\Identity\Domain\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
 * Operator admin panel (decision 74): host isolation, access rules,
 * read-only pages, masking and the page-view audit.
 */

const ADMIN = 'https://admin.webhooktrap.test';

function operator(bool $secondFactor = true, bool $verified = true): User
{
    $user = makeUser($verified ? [] : ['email_verified_at' => null]);
    $user->forceFill(['is_operator' => true])->save();
    if ($secondFactor) {
        addKey($user);
    }

    return $user->refresh();
}

it('registers the panel on the admin host only', function (): void {
    expect(Filament::getPanel('admin')->getDomains())->toBe(['admin.webhooktrap.test']);
});

it('answers 404 for the panel on the app host', function (string $path): void {
    $user = operator();
    actAs($user, 'passkey');

    test()->get('https://webhooktrap.test'.$path)->assertNotFound();
})->with(['/accounts', '/agents', '/runners', '/handoff/'.str_repeat('a', 43)]);

it('answers 404 for app routes on the admin host', function (string $path): void {
    test()->get(ADMIN.$path)->assertNotFound();
})->with(['/sign-in', '/projects', '/account/security', '/operator/handoff', '/register']);

it('refuses Livewire updates on the app host', function (): void {
    $path = app('livewire')->getUpdateUri();

    test()->post('https://webhooktrap.test'.$path, [], ['X-Livewire' => '1'])->assertNotFound();
});

it('sends guests to the app sign-in through the handoff', function (): void {
    test()->get(ADMIN.'/accounts')->assertRedirect(ADMIN.'/login');
    test()->get(ADMIN.'/login')->assertRedirect('https://webhooktrap.test/operator/handoff');
    test()->get('https://webhooktrap.test/operator/handoff')->assertRedirect('/sign-in');
});

it('forbids a signed-in account that is not an operator', function (): void {
    actAs(makeUser(), 'passkey');

    test()->get(ADMIN.'/')->assertForbidden();
    test()->get('https://webhooktrap.test/operator/handoff')->assertForbidden();
});

it('forbids an operator without a passkey or authenticator app', function (): void {
    actAs(operator(secondFactor: false), 'password');

    test()->get(ADMIN.'/')->assertForbidden();
    test()->get('https://webhooktrap.test/operator/handoff')->assertForbidden();
});

it('forbids an operator with an unverified email', function (): void {
    actAs(operator(verified: false), 'passkey');

    test()->get(ADMIN.'/')->assertForbidden();
});

it('admits an operator with a passkey', function (string $path): void {
    actAs(operator(), 'passkey');

    $response = test()->get(ADMIN.$path)->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
        ->assertHeader('X-Frame-Options', 'DENY');
    expect((string) $response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->getContent())->not->toContain('ui-avatars.com')
        ->and($response->getContent())->not->toContain('fonts.bunny.net')
        ->and($response->getContent())->toContain('<meta name="robots" content="noindex');
})->with(['/', '/accounts', '/agents', '/runners']);

it('admits an operator with a TOTP authenticator', function (): void {
    $user = operator(secondFactor: false);
    enableTotp($user);
    actAs($user, 'totp');

    test()->get(ADMIN.'/accounts')->assertOk();
});

it('serves a robots file that disallows everything on the admin host', function (): void {
    test()->get(ADMIN.'/robots.txt')->assertOk()->assertSee('Disallow: /')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

it('ends an idle admin session', function (): void {
    $user = operator();
    $row = actAs($user, 'passkey');
    test()->withSession(['admin.last_seen' => now()->subSeconds(901)->getTimestamp()]);

    test()->get(ADMIN.'/accounts')->assertRedirect(ADMIN.'/login');
    expect($row->refresh()->revoked_at)->not->toBeNull();
});

it('ends the admin session when its user_sessions row is revoked', function (): void {
    $row = actAs(operator(), 'passkey');
    $row->forceFill(['revoked_at' => now(), 'revoked_reason' => 'user'])->save();
    cache()->flush();

    test()->get(ADMIN.'/accounts')->assertRedirect(ADMIN.'/login');
});

it('audits every page view: who, which page, when', function (): void {
    $user = operator();
    actAs($user, 'passkey');

    test()->get(ADMIN.'/accounts?tableSearch=someone@example.com')->assertOk();

    $row = DB::table('audit_log')->where('action', 'operator_page_viewed')->sole();
    expect($row->actor_user_id)->toBe($user->id)
        ->and(json_decode((string) $row->after, true))->toBe(['params' => ['page' => '/accounts']])
        ->and($row->at)->not->toBeNull();
});

describe('read-only pages', function (): void {
    beforeEach(function (): void {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->operator = operator();
        test()->actingAs($this->operator);
    });

    it('lists and offers no create, edit or delete action', function (string $page, array $params): void {
        $params = array_map(fn ($p) => $p === ':operator' ? $this->operator->publicId() : $p, $params);
        $component = Livewire::test($page, $params)->assertOk();
        $instance = $component->instance();

        expect($instance->getCachedHeaderActions())->toBe([]);
        if (method_exists($instance, 'getTable')) {
            $table = $instance->getTable();
            expect($table->getHeaderActions())->toBe([])
                ->and($table->getRecordActions())->toBe([])
                ->and($table->getToolbarActions())->toBe([])
                ->and($table->getBulkActions())->toBe([])
                ->and($table->getEmptyStateActions())->toBe([])
                ->and($table->isSelectionEnabled())->toBeFalse();
        }
    })->with([
        'accounts' => [Accounts::class, []],
        'account' => [AccountDetail::class, ['account' => ':operator']],
        'agents' => [Agents::class, []],
        'runners' => [Runners::class, []],
        'dashboard' => [Dashboard::class, []],
    ]);

    it('registers no Filament resource', function (): void {
        expect(Filament::getPanel('admin')->getResources())->toBe([]);
    });

    it('masks addresses except the exact address searched', function (): void {
        $maya = makeUser(['email' => 'maya@bakery.example']);
        makeUser(['email' => 'mayank@bakery.example']);

        Livewire::test(Accounts::class)
            ->searchTable('bakery')
            ->assertSee('m***@b***.example')
            ->assertDontSee('maya@bakery.example')
            ->assertDontSee('mayank@bakery.example');

        Livewire::test(Accounts::class)
            ->searchTable('maya@bakery.example')
            ->assertSee('maya@bakery.example')
            ->assertDontSee('mayank@bakery.example');

        Livewire::test(AccountDetail::class, ['account' => $maya->publicId()])
            ->assertSee('m***@b***.example')
            ->assertDontSee('maya@bakery.example');
    });

    it('never renders secrets, hashes or keys', function (): void {
        $user = makeUser(['email' => 'lea@shop.example']);
        enableTotp($user);
        giveRecoveryCodes($user);
        $page = Livewire::test(Accounts::class)->searchTable('lea@shop.example')->html();

        expect($page)->not->toContain((string) $user->password_hash)
            ->and($page)->not->toContain('argon2id')
            ->and($page)->toContain('lea@shop.example');
    });

    it('shows an account\'s projects with quotas and usage', function (): void {
        $user = makeUser();
        $workspace = DB::table('workspaces')->where('user_id', $user->id)->value('id');
        if ($workspace === null) {
            $workspace = (string) Str::uuid7();
            DB::table('workspaces')->insert(['id' => $workspace, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('projects')->insert([
            'id' => (string) Str::uuid7(), 'workspace_id' => $workspace, 'project_key' => 'abcdefghij',
            'name' => 'Checkout hooks', 'created_at' => now(), 'updated_at' => now(),
            'captures_today' => 12, 'captures_day' => now()->utc()->toDateString(), 'stored_body_bytes' => 2048,
        ]);

        Livewire::test(AccountDetail::class, ['account' => $user->publicId()])
            ->assertSee('Checkout hooks')
            ->assertSee('12 / 5000')
            ->assertSee('2.0 KiB / 512.0 MiB')
            ->assertDontSee('abcdefghij');
    });
});

it('serves Livewire updates for an admitted operator on the admin host only', function (): void {
    $user = operator();
    actAs($user, 'passkey');
    $html = (string) test()->get(ADMIN.'/accounts')->assertOk()->getContent();
    preg_match('/wire:snapshot="([^"]+)"/', $html, $m);
    $snapshot = html_entity_decode($m[1] ?? '', ENT_QUOTES);
    expect($snapshot)->not->toBe('');
    $payload = ['components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => []]]];
    $headers = ['X-Livewire' => '1', 'X-CSRF-TOKEN' => session()->token(), 'Content-Type' => 'application/json'];
    $path = app('livewire')->getUpdateUri();

    test()->postJson(ADMIN.$path, $payload, $headers)->assertOk();
    test()->postJson('https://webhooktrap.test'.$path, $payload, $headers)->assertNotFound();

    test()->artisan('operator:revoke', ['email' => $user->email]);
    test()->actingAs($user->refresh()); // the test client keeps the guard's user between requests
    test()->postJson(ADMIN.$path, $payload, $headers)->assertForbidden();
});
