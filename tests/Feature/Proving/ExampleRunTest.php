<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * S-14 example run (decision 63): `GET /r/example` for everyone, with the
 * `Run/Show` prop shape of contracts §3.3 and `access: 'example'`.
 */

it('serves the example run to an anonymous visitor', function (): void {
    $this->get('/r/example')->assertOk()->assertInertia(fn (Assert $p) => $p
        ->component('Run/Show', shouldExist: false)
        ->where('access', 'example')
        ->where('focus', null)
        ->where('run.number', 3)
        ->where('run.status', 'findings')
        ->where('run.exitCode', 1)
        ->where('run.provenance.executor', 'agent')
        ->where('run.provenance.agentName', 'maya-mbp')
        ->where('run.budget.ms', 10000)
        ->where('run.counts.findings', 3)
        ->has('run.target', fn (Assert $t) => $t->hasAll(['scheme', 'host', 'port', 'path']))
        ->has('scenarios', 8)
        ->where('timeline.lastSeq', 16)
        ->has('timeline.deliveries', 16)
        ->has('timeline.deliveries.4', fn (Assert $d) => $d
            ->where('d', 5)
            ->where('result', 'finding')
            ->where('state.readings.0.name', 'orders.count')
            ->where('state.readings.0.display', ['before' => '1', 'after' => '2', 'expected' => '1'])
            ->where('state.readings.0.flagged', true)
            ->etc())
        ->has('findings', 4)
        ->has('findings.1', fn (Assert $f) => $f
            ->where('n', 2)
            ->where('severity', 'high')
            ->where('level', 2)
            ->where('title', 'Duplicate delivery created a second order')
            ->has('evidence', 4)
            ->hasAll(['what', 'why', 'citation', 'fix', 'likelyCause', 'fingerprint', 'seenBefore'])
            ->etc()));
});

it('serves it to a signed-in user too', function (): void {
    signIn(User::factory()->create());

    $this->get('/r/example')->assertOk()->assertInertia(fn (Assert $p) => $p
        ->component('Run/Show', shouldExist: false)
        ->where('access', 'example'));
});

it('focuses a delivery or a finding from the deep link', function (): void {
    $this->get('/r/example/D5')->assertInertia(fn (Assert $p) => $p
        ->where('focus', ['kind' => 'delivery', 'n' => 5, 'missing' => false]));
    $this->get('/r/example/F2')->assertInertia(fn (Assert $p) => $p
        ->where('focus', ['kind' => 'finding', 'n' => 2, 'missing' => false]));
    $this->get('/r/example/D22')->assertInertia(fn (Assert $p) => $p
        ->where('focus', ['kind' => 'delivery', 'n' => 22, 'missing' => true]));
});

it('answers not found for other paths under the example', function (): void {
    $this->get('/r/example/X1')->assertNotFound();
});
