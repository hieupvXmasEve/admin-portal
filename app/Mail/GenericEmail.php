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
        protected string $subject,
        protected string $htmlContent,
        protected ?string $textContent = null,
        protected array $attachments = []
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: $this->htmlContent,
            text: $this->textContent,
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

        foreach ($this->attachments as $attachment) {
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
