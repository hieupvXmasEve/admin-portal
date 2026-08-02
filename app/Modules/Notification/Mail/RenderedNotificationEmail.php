<?php

declare(strict_types=1);

namespace App\Modules\Notification\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Carries already-rendered subject/body straight to the transport.
 *
 * Property names are deliberately suffixed: `Mailable` already declares
 * non-readonly `$html` and `$text`, and PHP fatals on redeclaring an inherited
 * property as readonly — which killed the queue worker before it could even
 * record the failure on the delivery row.
 */
class RenderedNotificationEmail extends Mailable
{
    public function __construct(
        private readonly string $subjectLine,
        private readonly string $htmlBody,
        private readonly ?string $textBody = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.generic',
            with: [
                'htmlContent' => $this->htmlBody,
                'textContent' => $this->textBody,
            ],
        );
    }
}
