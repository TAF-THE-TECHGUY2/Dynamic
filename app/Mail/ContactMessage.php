<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class ContactMessage extends Mailable
{
    use Queueable, SerializesModels;

    /** @param  array{name: string, company: ?string, phone: string, email: ?string, message: string}  $fields */
    public function __construct(public readonly array $fields) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Website enquiry — '.$this->fields['name'],
            replyTo: array_filter([$this->fields['email'] ?? null]),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.contact', with: ['fields' => $this->fields]);
    }
}
