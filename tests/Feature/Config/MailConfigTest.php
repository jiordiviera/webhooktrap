<?php

declare(strict_types=1);

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use PulseSend\Client;
use PulseSend\Laravel\Exceptions\ApiKeyIsMissing;
use PulseSend\Laravel\Transport\PulseSendTransport;

/*
 * Transactional mail through PulseSend (decision 72). No test reaches the network: the key is
 * blank in phpunit.xml, and the transport test gives the SDK a Guzzle mock handler.
 */

/**
 * Evaluates config/mail.php with these variables set, then puts the previous values back.
 *
 * @param  array<string, string>  $vars
 * @return array<string, mixed>
 */
function mailConfigWith(array $vars): array
{
    $previous = [];
    foreach ($vars as $name => $value) {
        $previous[$name] = [getenv($name), $_ENV[$name] ?? null, $_SERVER[$name] ?? null];
        putenv("{$name}={$value}");
        $_ENV[$name] = $_SERVER[$name] = $value;
    }

    try {
        return require config_path('mail.php');
    } finally {
        foreach ($previous as $name => [$env, $envConst, $server]) {
            $env === false ? putenv($name) : putenv("{$name}={$env}");
            if ($envConst === null) {
                unset($_ENV[$name]);
            } else {
                $_ENV[$name] = $envConst;
            }
            if ($server === null) {
                unset($_SERVER[$name]);
            } else {
                $_SERVER[$name] = $server;
            }
        }
    }
}

it('has a pulsesend mailer on the pulsesend transport', function (): void {
    expect(config('mail.mailers.pulsesend'))->toBe(['transport' => 'pulsesend']);
});

it('defaults to the log mailer', function (): void {
    $source = (string) file_get_contents(config_path('mail.php'));

    expect($source)->toContain("env('MAIL_MAILER', 'log')");
});

it('uses PulseSend when MAIL_MAILER is pulsesend and the key is set', function (): void {
    $config = mailConfigWith(['MAIL_MAILER' => 'pulsesend', 'PULSESEND_API_KEY' => 'ps_test_fixture']);

    expect($config['default'])->toBe('pulsesend');
});

it('falls back to the log mailer while the PulseSend key is empty', function (): void {
    $config = mailConfigWith(['MAIL_MAILER' => 'pulsesend', 'PULSESEND_API_KEY' => '']);

    expect($config['default'])->toBe('log');
});

it('sends nothing through PulseSend in tests', function (): void {
    expect(config('mail.default'))->toBe('array')
        ->and(config('pulsesend.api_key'))->toBeEmpty()
        ->and(fn () => app(Client::class))->toThrow(ApiKeyIsMissing::class);
});

it('sends through the PulseSend transport when configured', function (): void {
    config(['pulsesend.api_key' => 'ps_test_fixture', 'mail.from.address' => 'no-reply@webhooktrap.dev']);

    $requests = [];
    $stack = HandlerStack::create(new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], '{"id":"em_fixture","object":"email"}'),
    ]));
    $stack->push(Middleware::history($requests));
    app()->instance(Client::class, new Client('ps_test_fixture', ['http_client' => new Guzzle(['handler' => $stack])]));
    Mail::purge('pulsesend');

    $mailer = Mail::mailer('pulsesend');
    expect($mailer->getSymfonyTransport())->toBeInstanceOf(PulseSendTransport::class);

    $sent = $mailer->raw('Hello.', fn (Message $message) => $message->to('someone@example.test')->subject('Hi'));

    expect($requests)->toHaveCount(1);
    $request = $requests[0]['request'];
    $body = json_decode((string) $request->getBody(), true);

    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toEndWith('/api/v1/emails')
        ->and($request->getHeaderLine('Authorization'))->toBe('Bearer ps_test_fixture')
        ->and($body['from'])->toContain('no-reply@webhooktrap.dev')
        ->and($body['to'])->toBe(['someone@example.test'])
        ->and($body['subject'])->toBe('Hi')
        ->and($sent?->getMessageId())->toBe('em_fixture');
});

it('keeps the PulseSend webhook route off', function (): void {
    expect(config('pulsesend.routes'))->toBeFalse()
        ->and(Route::has('pulsesend.webhook'))->toBeFalse();

    $this->postJson('/pulsesend/webhook', ['type' => 'email.delivered'])->assertNotFound();
});
