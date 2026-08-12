<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\DocStatus;
use App\Enums\DocType;
use App\Models\Document;
use App\Models\DocumentLine;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Shapes documents for the React admin console.
 *
 * Amounts cross the wire as integer cents plus a preformatted display string,
 * so the browser never has to reconstruct money from a float.
 */
final class DocumentPresenter
{
    /** @return array<string, mixed> */
    public static function forList(Document $document): array
    {
        return [
            'id' => $document->id,
            'number' => $document->number,
            'doc_type' => $document->doc_type->value,
            'doc_type_label' => $document->doc_type->label(),
            'company' => $document->bill_to['company'] ?? '—',
            'litres' => $document->totalLitres(),
            'litres_display' => Money::litres($document->totalLitres()),
            'total_cents' => $document->total_cents,
            'total_display' => Money::format($document->total_cents),
            'issued_on' => $document->issued_on->toDateString(),
            'issued_display' => $document->issued_on->format('d M'),
            'status' => $document->status->value,
            'status_label' => self::statusLabel($document),
            'pill' => self::pill($document),
            'is_expired' => $document->isExpired() || $document->hasLapsed(),
            'is_overdue' => $document->isOverdue(),
            'public_url' => $document->publicUrl(),
        ];
    }

    /** @return array<string, mixed> */
    public static function forBuilder(Document $document): array
    {
        return [
            'id' => $document->id,
            'number' => $document->number,
            'doc_type' => $document->doc_type->value,
            'status' => $document->status->value,
            'status_label' => $document->status->label(),
            'customer_id' => $document->customer_id,
            'company' => $document->bill_to['company'] ?? '',
            'contact_name' => $document->bill_to['contact_name'] ?? '',
            'phone' => $document->bill_to['phone'] ?? '',
            'email' => $document->bill_to['email'] ?? '',
            'vat_number' => $document->bill_to['vat_number'] ?? '',
            'delivery_address' => $document->bill_to['address'] ?? '',
            'issued_on' => $document->issued_on->toDateString(),
            'valid_until' => $document->valid_until?->toDateString(),
            'due_on' => $document->due_on?->toDateString(),
            'fulfilment' => $document->fulfilment,
            'vat_mode' => $document->vat_mode->value,
            'vat_rate' => $document->vat_rate,
            'notes' => $document->notes ?? '',
            'terms' => $document->terms,
            'banking' => $document->banking,

            // Server-computed. The preview recomputes these client-side as you
            // type, but these are the figures that are actually stored.
            'subtotal_cents' => $document->subtotal_cents,
            'vat_cents' => $document->vat_cents,
            'total_cents' => $document->total_cents,

            'lines' => $document->lines->map(fn (DocumentLine $line) => [
                'id' => $line->id,
                'grade_id' => $line->grade_id,
                'code' => $line->code,
                'description' => $line->description,
                'litres' => $line->litres,
                'price_per_litre' => $line->price_per_litre,
                'amount_cents' => $line->amount_cents,
            ])->values(),

            'is_expired' => $document->isExpired() || $document->hasLapsed(),
            'public_url' => $document->publicUrl(),
            'converted_from' => $document->convertedFrom?->number,
        ];
    }

    /**
     * The three cards above the documents list.
     *
     * @return array<string, mixed>
     */
    public static function listMetrics(): array
    {
        $outstanding = Document::query()->outstanding();

        $awaiting = (clone $outstanding);
        $awaitingCents = (int) (clone $awaiting)->sum('total_cents');
        $awaitingCount = (clone $awaiting)->count();

        $overdue = Document::query()->outstanding()->whereDate('due_on', '<', today());
        $overdueCents = (int) (clone $overdue)->sum('total_cents');
        $overdueCount = (clone $overdue)->count();

        $expiringToday = Document::query()
            ->ofType(DocType::Quote)
            ->whereIn('status', [DocStatus::Draft->value, DocStatus::Sent->value])
            ->whereDate('valid_until', today())
            ->count();

        return [
            'awaiting_display' => Money::format($awaitingCents),
            'awaiting_count' => $awaitingCount,
            'overdue_display' => Money::format($overdueCents),
            'overdue_count' => $overdueCount,
            'expiring_today' => $expiringToday,
        ];
    }

    /**
     * Litres sold, revenue, open quotes and overdue invoices for the dashboard,
     * each against the previous month.
     *
     * @return array<string, mixed>
     */
    public static function dashboardMetrics(): array
    {
        $thisMonth = today()->startOfMonth();
        $lastMonth = $thisMonth->copy()->subMonth();

        $current = self::monthTotals($thisMonth);
        $previous = self::monthTotals($lastMonth);

        $openQuotes = Document::query()
            ->ofType(DocType::Quote)
            ->whereIn('status', [DocStatus::Draft->value, DocStatus::Sent->value])
            ->get(['total_cents']);

        $overdue = Document::query()
            ->outstanding()
            ->whereDate('due_on', '<', today())
            ->get(['total_cents']);

        return [
            'litres' => [
                'value' => Money::litres($current['litres']),
                'delta' => self::deltaPercent((float) $current['litres'], (float) $previous['litres']),
                'previous_label' => $lastMonth->format('F'),
            ],
            'revenue' => [
                'value' => Money::compact($current['revenue_cents']),
                'delta' => self::deltaPercent((float) $current['revenue_cents'], (float) $previous['revenue_cents']),
                'previous_label' => $lastMonth->format('F'),
            ],
            'open_quotes' => [
                'value' => (string) $openQuotes->count(),
                'detail' => Money::format((int) $openQuotes->sum('total_cents')).' in play',
            ],
            'overdue' => [
                'value' => (string) $overdue->count(),
                'detail' => Money::format((int) $overdue->sum('total_cents')).' outstanding',
            ],
            'month_label' => $thisMonth->format('M'),
        ];
    }

    /**
     * Invoiced litres and revenue for a month.
     *
     * Counts invoices only — a quote is not money, and counting it as revenue
     * is how a dashboard starts lying to its owner.
     *
     * @return array{litres: string, revenue_cents: int}
     */
    private static function monthTotals(CarbonInterface $month): array
    {
        $ids = Document::query()
            ->ofType(DocType::Invoice)
            ->whereNotIn('status', [DocStatus::Cancelled->value, DocStatus::Draft->value])
            ->whereBetween('issued_on', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->pluck('id');

        if ($ids->isEmpty()) {
            return ['litres' => '0', 'revenue_cents' => 0];
        }

        $litres = (string) (DB::table('document_lines')
            ->whereIn('document_id', $ids)
            ->whereNotNull('litres')
            ->sum('litres') ?: '0');

        $revenue = (int) Document::query()->whereIn('id', $ids)->sum('total_cents');

        return ['litres' => $litres, 'revenue_cents' => $revenue];
    }

    /**
     * @return array{percent: int, direction: 'up'|'dn'|'flat'}|null
     *                                                               null when there is no previous month to compare against
     */
    private static function deltaPercent(float $current, float $previous): ?array
    {
        if ($previous <= 0.0) {
            return null;
        }

        $change = (int) round((($current - $previous) / $previous) * 100);

        return [
            'percent' => abs($change),
            'direction' => $change > 0 ? 'up' : ($change < 0 ? 'dn' : 'flat'),
        ];
    }

    private static function statusLabel(Document $document): string
    {
        if ($document->isOverdue()) {
            return 'Overdue';
        }

        if ($document->hasLapsed() && ! $document->isExpired()) {
            return 'Expired';
        }

        if ($document->doc_type->isPayable() && $document->status === DocStatus::Sent) {
            return 'Unpaid';
        }

        return $document->status->label();
    }

    private static function pill(Document $document): string
    {
        if ($document->isOverdue()) {
            return 'new';
        }

        if ($document->hasLapsed()) {
            return 'exp';
        }

        return $document->status->pill();
    }
}
