<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Every Identity email, keyed by A03's template code (e.g. `email-verify`,
 * `password-reset`, `account-deleted`). Plain text only; the copy is A03's
 * and lands with the UI slice. The address is copied into the job, so a
 * notice still reaches an account that was deleted meanwhile (domain §2.10).
 */
final class IdentityMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, string|int|null>  $params
     */
    public function __construct(
        public readonly string $template,
        public readonly array $params = [],
        public readonly ?string $actionUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: config('app.name').': '.str_replace('-', ' ', $this->template));
    }

    public function content(): Content
    {
        return new Content(text: 'emails.identity', with: [
            'template' => $this->template,
            'params' => $this->params,
            'actionUrl' => $this->actionUrl,
        ]);
    }
}
