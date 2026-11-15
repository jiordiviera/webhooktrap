<?php

declare(strict_types=1);

use App\Modules\Capture\Domain\Capture;
use App\Modules\Capture\Domain\CaptureUrl;
use App\Modules\Workspace\Domain\Project;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;

require_once dirname(__DIR__).'/Workspace/WorkspaceHelpers.php';

/*
 * Slice 3 helpers. Loaded once by the capture test files.
 */

function activeKey(string $projectId): string
{
    return (string) CaptureUrl::query()->where('project_id', $projectId)->where('state', 'active')->value('capture_key');
}

function projectIdOfKey(string $projectKey): string
{
    return (string) Project::query()->where('project_key', $projectKey)->value('id');
}

/**
 * Sends a request to the app the way a provider would: headers become
 * server variables, the body is raw, and `REQUEST_URI` is set verbatim
 * (Symfony's Request::create would re-encode the query).
 *
 * @param  array<string, string>  $headers
 * @param  array<string, string>  $server
 */
function sendRaw(string $method, string $rawTarget, string $body = '', array $headers = [], array $server = []): TestResponse
{
    $vars = ['REMOTE_ADDR' => '203.0.113.7', 'HTTP_HOST' => 'localhost'];
    foreach ($headers as $name => $value) {
        $upper = strtoupper(str_replace('-', '_', $name));
        $vars[in_array($upper, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true) ? $upper : 'HTTP_'.$upper] = $value;
    }
    $vars = $server + $vars;
    $path = explode('?', $rawTarget, 2)[0];
    $request = Request::create('http://'.$vars['HTTP_HOST'].$path, $method, [], [], [], $vars, $body);
    $request->server->set('REQUEST_URI', $rawTarget);
    $request->server->set('REQUEST_METHOD', $method);
    // Symfony fills a few defaults; drop them so only the "wire" headers remain.
    foreach (['HTTP_USER_AGENT', 'HTTP_ACCEPT', 'HTTP_ACCEPT_LANGUAGE', 'HTTP_ACCEPT_CHARSET'] as $default) {
        if (! array_key_exists($default, $vars)) {
            $request->server->remove($default);
            $request->headers->remove(strtolower(str_replace('_', '-', substr($default, 5))));
        }
    }

    $kernel = app(Kernel::class);
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    return TestResponse::fromBaseResponse($response, $request);
}

/** What a client can observe of an answer: status, body, headers (minus Date). */
function observable(TestResponse $response): array
{
    $headers = $response->headers->all();
    unset($headers['date']);
    ksort($headers);

    return [$response->getStatusCode(), $response->getContent(), $headers];
}

function capturesOf(string $projectId): int
{
    return Capture::query()->where('project_id', $projectId)->count();
}

function lastCapture(string $projectId): Capture
{
    return Capture::query()->where('project_id', $projectId)->orderByDesc('seq')->firstOrFail();
}

/** Value of a stored header, by case-insensitive name. */
function storedHeader(Capture $capture, string $name): ?string
{
    foreach ($capture->headers as [$n, $v]) {
        if (strcasecmp($n, $name) === 0) {
            return $v;
        }
    }

    return null;
}

/** A fresh `delete_account` confirmation in this browser session (DL-1). */
function confirmDeletion(string $method = 'totp'): void
{
    session()->put('identity.confirmation', ['purpose' => 'delete_account', 'method' => $method, 'second_factor' => true, 'at' => now()->getTimestamp(), 'consumed' => false]);
}
