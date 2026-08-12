<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DocStatus;
use App\Models\Document;
use Illuminate\Contracts\View\View;

final class PublicDocumentController extends Controller
{
    /**
     * The read-only customer link.
     *
     * Everything rendered comes from the document's own snapshotted columns, so
     * a price published this morning cannot change what a customer sees on a
     * quote they were sent last week.
     */
    public function show(string $token): View
    {
        $document = Document::query()
            ->with('lines')
            ->where('public_token', $token)
            ->firstOrFail();

        abort_if($document->status === DocStatus::Cancelled, 404);

        return view('documents.public', [
            'document' => $document,
            // hasLapsed() covers the window between a quote passing its validity
            // date and the hourly documents:expire command catching up with it.
            'expired' => $document->isExpired() || $document->hasLapsed(),
        ]);
    }
}
