<?php

declare(strict_types=1);

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Laravel\Socialite\Facades\Socialite;

it('sends the PKCE code_verifier when it exchanges the code', function (): void {
    config([
        'services.github.client_id' => 'test-client',
        'services.github.client_secret' => 'test-secret',
    ]);

    $history = [];
    $stack = HandlerStack::create(new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], (string) json_encode(['access_token' => 'gho_test', 'token_type' => 'bearer', 'scope' => 'user:email'])),
        new Response(200, ['Content-Type' => 'application/json'], (string) json_encode(['id' => 4242, 'login' => 'maya', 'name' => 'Maya L', 'email' => 'maya@example.test', 'avatar_url' => null])),
        new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([['email' => 'maya@example.test', 'primary' => true, 'verified' => true]])),
    ]));
    $stack->push(Middleware::history($history));

    // Socialite builds each driver with these Guzzle options, so the exchange hits the mock.
    config(['services.github.guzzle' => ['handler' => $stack]]);

    $redirect = $this->get('/sign-in/github');
    $location = (string) $redirect->headers->get('Location');
    $redirect->assertRedirect();
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    expect($query)->toHaveKey('code_challenge');

    Socialite::forgetDrivers();
    $this->get('/auth/github/callback?code=test-code&state='.urlencode((string) $query['state']));

    expect($history)->not->toBeEmpty();
    parse_str((string) $history[0]['request']->getBody(), $tokenRequest);
    expect($tokenRequest)->toHaveKey('code_verifier')
        ->and($tokenRequest['code_verifier'])->not->toBe('');
});
