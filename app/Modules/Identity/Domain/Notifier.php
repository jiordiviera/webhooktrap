<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use App\Modules\Identity\Domain\Mail\IdentityMail;
use Illuminate\Support\Facades\Mail;

/** Queues an Identity email; the address travels in the job (domain §2.10). */
final class Notifier
{
    /**
     * @param  array<string, string|int|null>  $params
     */
    public function send(string $email, string $template, array $params = [], ?string $actionUrl = null): void
    {
        Mail::to($email)->queue(new IdentityMail($template, $params, $actionUrl));
    }
}
