<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\DocumentIssued;
use App\Models\Document;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\LaravelPdf\Facades\Pdf;

/**
 * Renders an issued document to PDF, stores it, and emails it out.
 *
 * The PDF comes from resources/views/documents/print.blade.php through headless
 * Chromium — the same template the browser print route uses — so what the owner
 * previewed is exactly what the customer receives.
 *
 * The file goes to the configured disk (S3 in production) and is only ever
 * handed back through a temporary signed URL. The bucket is not public.
 */
final class RenderDocumentPdf implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly string $documentId,
        public readonly bool $sendEmail = false,
    ) {}

    public function handle(): void
    {
        $document = Document::query()->with('lines')->find($this->documentId);

        if ($document === null) {
            return;
        }

        $disk = Storage::disk(config('filesystems.default'));
        $path = sprintf('documents/%d/%s.pdf', $document->issued_on->year, $document->number);

        $disk->put($path, self::render($document), ['visibility' => 'private']);

        $document->update(['pdf_path' => $path]);

        if (! $this->sendEmail) {
            return;
        }

        $recipient = $document->bill_to['email'] ?? null;

        if (blank($recipient)) {
            Log::info('Document issued with no customer email; nothing sent.', [
                'document' => $document->number,
            ]);

            return;
        }

        Mail::to($recipient)->send(new DocumentIssued($document->fresh('lines')));
    }

    /**
     * Render the PDF bytes. Also used by the on-demand download route for
     * documents that have not been issued yet.
     */
    public static function render(Document $document): string
    {
        // base64() is the only accessor that hands back the bytes rather than
        // writing them somewhere; decoding is cheaper than a temp file round trip.
        $bytes = base64_decode(
            Pdf::view('documents.print', [
                'document' => $document->load('lines'),
                'inlineAssets' => true,
            ])
                ->format('a4')
                ->margins(12, 12, 12, 12)
                ->name($document->number.'.pdf')
                ->base64(),
            strict: true,
        );

        if ($bytes === false) {
            throw new RuntimeException("Browsershot returned an unreadable PDF for {$document->number}.");
        }

        return $bytes;
    }
}
