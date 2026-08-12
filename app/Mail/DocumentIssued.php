<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * An issued quote, pro forma or invoice, with its PDF attached.
 *
 * Sent by App\Jobs\RenderDocumentPdf once the PDF is safely on S3, so the
 * attachment is the same file the admin console will hand back later.
 */
final class DocumentIssued extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Document $document) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                '%s %s from Dynamic Solutions',
                $this->document->doc_type->label(),
                $this->document->number,
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.document-issued',
            with: [
                'document' => $this->document->load('lines'),
                'publicUrl' => $this->document->publicUrl(),
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        if ($this->document->pdf_path === null) {
            return [];
        }

        return [
            Attachment::fromStorageDisk(config('filesystems.default'), $this->document->pdf_path)
                ->as($this->document->number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }

    public function hasStoredPdf(): bool
    {
        return $this->document->pdf_path !== null
            && Storage::disk(config('filesystems.default'))->exists($this->document->pdf_path);
    }
}
