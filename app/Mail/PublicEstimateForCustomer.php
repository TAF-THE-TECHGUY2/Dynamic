<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The copy of a website estimate that goes to the person who asked for it.
 *
 * Careful with the wording: this is an estimate at the published rate, not a
 * quote. It becomes a quote when the owner issues one.
 */
final class PublicEstimateForCustomer extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Document $document) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your diesel estimate from Dynamic Solutions — '.$this->document->number,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.estimate-customer',
            with: [
                'document' => $this->document->load('lines'),
                'publicUrl' => $this->document->publicUrl(),
            ],
        );
    }
}
