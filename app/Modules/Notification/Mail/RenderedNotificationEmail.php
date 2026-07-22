<?php

declare(strict_types=1);

namespace App\Modules\Notification\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class RenderedNotificationEmail extends Mailable
{
    public function __construct(
        private readonly string $subjectLine,
        private readonly string $html,
        private readonly ?string $text = null,
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
                'htmlContent' => $this->html,
                'textContent' => $this->text,
            ],
        );
    }
}
