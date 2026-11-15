<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use App\Infrastructure\RateLimit\ClientAddress;
use Illuminate\Http\Request;

/**
 * What Identity keeps from a request: the rate-limit address key, the
 * truncated network and the coarse browser label. Never the raw address.
 */
final readonly class RequestContext
{
    public function __construct(
        public string $addressKey,
        public string $ipPrefix,
        public string $uaLabel,
    ) {}

    public static function from(Request $request): self
    {
        return new self(
            ClientAddress::rateKey($request->ip()),
            ClientAddress::networkPrefix($request->ip()),
            UserAgentLabel::from($request->userAgent()),
        );
    }

    public static function console(): self
    {
        return new self('console', '0.0.0.0/0', 'Host console');
    }
}
