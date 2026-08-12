<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Document;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** The nudge to the yard that someone is asking for diesel. */
final class PublicEstimateForBusiness extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Document $document) {}

    public function envelope(): Envelope
    {
        $company = $this->document->bill_to['company'] ?? 'Website visitor';

        return new Envelope(
            subject: sprintf(
                'New website estimate — %s, %s',
                $company,
                Money::format($this->document->total_cents),
            ),
            replyTo: array_filter([$this->document->bill_to['email'] ?? null]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.estimate-business',
            with: [
                'document' => $this->document->load('lines'),
                'adminUrl' => route('admin.documents.edit', $this->document),
            ],
        );
    }
}
