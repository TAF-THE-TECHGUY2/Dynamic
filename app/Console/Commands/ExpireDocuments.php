<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\DocStatus;
use App\Enums\DocType;
use App\Models\Document;
use Illuminate\Console\Command;

/**
 * Flips quotes past their validity date to `expired`.
 *
 * Runs hourly. South African diesel reprices monthly, and a quote that outlives
 * a price change is a direct margin loss — which is why validity is enforced
 * rather than printed as a warning and forgotten.
 */
final class ExpireDocuments extends Command
{
    protected $signature = 'documents:expire';

    protected $description = 'Expire quotes that have passed their validity date';

    public function handle(): int
    {
        $expired = Document::query()
            ->where('doc_type', DocType::Quote->value)
            ->whereIn('status', [DocStatus::Draft->value, DocStatus::Sent->value])
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<', today())
            ->update(['status' => DocStatus::Expired->value]);

        $this->info($expired === 0
            ? 'No quotes to expire.'
            : "Expired {$expired} quote(s).");

        return self::SUCCESS;
    }
}
