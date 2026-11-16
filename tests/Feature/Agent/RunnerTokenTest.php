<?php

declare(strict_types=1);

use App\Modules\Agent\Application\RunnerTokens;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;

/*
 * `runner:token` (RN-1, RN-2): the token goes to stdout once and nothing else
 * does; only its SHA-256 and last 4 are stored; rotation and revocation.
 */

/** @return array{exit: int, stdout: string, stderr: string} */
function runnerToken(array $parameters = []): array
{
    $stdout = new class extends BufferedOutput implements ConsoleOutputInterface
    {
        public BufferedOutput $stderr;

        public function __construct()
        {
            parent::__construct();
            $this->stderr = new BufferedOutput;
        }

        public function getErrorOutput(): OutputInterface
        {
            return $this->stderr;
        }

        public function setErrorOutput(OutputInterface $error): void {}

        public function section(): ConsoleSectionOutput
        {
            throw new LogicException('not used');
        }
    };
    $exit = Artisan::call('runner:token', $parameters, $stdout);

    return ['exit' => $exit, 'stdout' => $stdout->fetch(), 'stderr' => $stdout->stderr->fetch()];
}

beforeEach(function (): void {
    config(['identity.instance' => 'selfhost']);
});

it('prints only the token on stdout and stores only its hash and last 4', function (): void {
    $run = runnerToken();

    expect($run['exit'])->toBe(0);
    $token = rtrim($run['stdout'], "\n");
    expect($token)->toMatch('/^wt_rnr_[0-9A-Za-z]{38}$/')
        ->and(RunnerTokens::wellFormed($token))->toBeTrue()
        ->and($run['stderr'])->toContain('not shown again')->not->toContain($token);

    $row = DB::table('runners')->sole();
    $hash = is_resource($row->token_hash) ? stream_get_contents($row->token_hash) : $row->token_hash;
    expect($row->name)->toBe('runner')
        ->and($hash)->toBe(hash('sha256', $token, true))
        ->and($row->last4)->toBe(substr($token, -4))
        ->and((string) json_encode(DB::table('runners')->get(['name', 'last4', 'allowed_cidrs'])))->not->toContain($token);
});

it('rotates under a taken name and keeps the old token live until it is revoked', function (): void {
    $first = rtrim(runnerToken(['name' => 'ci'])['stdout']);
    $second = runnerToken(['name' => 'ci']);

    expect($second['exit'])->toBe(0)
        ->and(rtrim($second['stdout']))->not->toBe($first)
        ->and($second['stderr'])->toContain('ci-2')->toContain('--revoke=ci')
        ->and(DB::table('runners')->whereNull('revoked_at')->pluck('name')->sort()->values()->all())->toBe(['ci', 'ci-2']);

    $revoke = runnerToken(['--revoke' => 'ci']);
    expect($revoke['exit'])->toBe(0)->and($revoke['stdout'])->toBe('')
        ->and(DB::table('runners')->whereNull('revoked_at')->pluck('name')->all())->toBe(['ci-2']);

    expect(runnerToken(['--revoke' => 'ci'])['exit'])->toBe(1)
        ->and(runnerToken(['--revoke' => 'nobody'])['exit'])->toBe(1);
});

it('needs a source network on hosted and refuses a malformed one (RN-1)', function (): void {
    config(['identity.instance' => 'hosted']);

    $missing = runnerToken();
    expect($missing['exit'])->toBe(1)->and($missing['stdout'])->toBe('');

    expect(runnerToken(['--cidr' => ['203.0.113.7/24']])['exit'])->toBe(1)
        ->and(runnerToken(['--cidr' => ['not-a-network']])['exit'])->toBe(1)
        ->and(DB::table('runners')->count())->toBe(0);

    $ok = runnerToken(['--cidr' => ['203.0.113.0/24', '2001:db8::/32']]);
    expect($ok['exit'])->toBe(0)
        ->and(DB::table('runners')->value('allowed_cidrs'))->toBe('{203.0.113.0/24,2001:db8::/32}')
        ->and(DB::table('runners')->value('binding_required'))->toBeTrue();
});

it('lists tokens without their value', function (): void {
    $token = rtrim(runnerToken()['stdout']);

    $list = runnerToken(['--list' => true]);
    expect($list['exit'])->toBe(0)
        ->and($list['stdout'])->toContain('runner')->toContain(substr($token, -4))->not->toContain($token);
});
