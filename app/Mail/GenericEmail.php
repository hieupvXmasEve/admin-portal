<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class GenericEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public string $emailSubject,
        public string $htmlContent,
        public ?string $textContent = null,
        public array $emailAttachments = []
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailSubject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.generic',
            with: [
                'htmlContent' => $this->htmlContent,
                'textContent' => $this->textContent
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $mailAttachments = [];

        foreach ($this->emailAttachments as $attachment) {
            if (is_string($attachment) && file_exists($attachment)) {
                $mailAttachments[] = Attachment::fromPath($attachment);
            } elseif (is_array($attachment) && isset($attachment['path'])) {
                $attach = Attachment::fromPath($attachment['path']);
                
                if (isset($attachment['name'])) {
                    $attach = $attach->as($attachment['name']);
                }
                
                if (isset($attachment['mime'])) {
                    $attach = $attach->withMime($attachment['mime']);
                }
                
                $mailAttachments[] = $attach;
            }
        }

        return $mailAttachments;
    }
}
