<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Diesel grades. Keyed by their own string id so a document line and a
        // price row both read '50ppm' rather than an opaque number.
        Schema::create('grades', function (Blueprint $table) {
            $table->string('id')->primary();          // '50ppm', '500ppm'
            $table->string('label');                  // 'Diesel 50ppm'
            $table->string('code');                   // 'D50-BULK'
            $table->string('blurb')->nullable();      // 'Low sulphur'
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Append only. Publishing a price inserts a row; nothing ever updates
        // one in place, so the history stays auditable and last week's quote
        // can always be explained.
        Schema::create('price_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('grade_id');
            $table->foreign('grade_id')->references('id')->on('grades')->cascadeOnDelete();
            $table->decimal('price_per_litre', 10, 4);
            $table->boolean('vat_inclusive')->default(true);
            $table->timestamp('effective_from')->useCurrent();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['grade_id', 'effective_from']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            // Nullable because "configured but not yet filled in" is a real
            // state here — the yard day rate before the owner sets one, for
            // instance. The alternative is encoding it as a magic string.
            $table->jsonb('value')->nullable();
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('company');
            $table->string('contact_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('vat_number')->nullable();
            $table->text('delivery_address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('company');
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('doc_type');                   // quote | proforma | invoice
            $table->string('number')->unique();           // DS-2026-0185 | INV-2026-0091
            $table->unsignedInteger('seq');
            $table->unsignedSmallInteger('seq_year');
            $table->foreignUuid('customer_id')->nullable()->constrained()->nullOnDelete();

            // Snapshots. An issued document never joins back to the live
            // customer, price or settings records — see App\Models\Document.
            $table->jsonb('bill_to');

            $table->date('issued_on');
            $table->date('valid_until')->nullable();      // quotes
            $table->date('due_on')->nullable();           // invoices
            $table->string('fulfilment')->default('delivery');
            $table->string('vat_mode')->default('inclusive');
            $table->decimal('vat_rate', 5, 4)->default(0.15);
            $table->bigInteger('subtotal_cents')->default(0);
            $table->bigInteger('vat_cents')->default(0);
            $table->bigInteger('total_cents')->default(0);
            $table->string('status')->default('draft');   // draft|sent|accepted|paid|expired|cancelled
            $table->text('notes')->nullable();
            $table->text('terms');                        // snapshot
            $table->text('banking');                      // snapshot
            // Constrained below: a self-referencing key cannot be added in the
            // same statement that creates the table it points at.
            $table->uuid('converted_from')->nullable();
            $table->string('public_token')->unique()->nullable();
            $table->string('pdf_path')->nullable();       // S3 key once issued
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['doc_type', 'status', 'issued_on']);
        });

        // A quote turned into an invoice points back at the quote it came from.
        Schema::table('documents', function (Blueprint $table) {
            $table->foreign('converted_from')->references('id')->on('documents')->nullOnDelete();
        });

        Schema::create('document_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained()->cascadeOnDelete();
            $table->string('grade_id')->nullable();
            $table->string('code')->nullable();
            $table->string('description');
            $table->decimal('litres', 12, 2)->nullable();

            // The price at the moment of issue, copied here on purpose.
            $table->decimal('price_per_litre', 10, 4)->nullable();

            $table->bigInteger('amount_cents');
            $table->unsignedInteger('sort')->default(0);

            $table->index(['document_id', 'sort']);
        });

        Schema::create('yard_bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('company')->nullable();        // walk-ins without a customer record
            $table->string('vehicle_reg')->nullable();
            $table->string('bay')->nullable();
            $table->timestamp('check_in')->useCurrent();
            $table->timestamp('check_out')->nullable();
            $table->decimal('rate_per_day', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('check_out');
        });

        // One row per document type per year. Locked in a transaction by
        // App\Services\DocumentNumberer so two staff clicking "new quote" at the
        // same moment cannot be handed the same number.
        Schema::create('doc_counters', function (Blueprint $table) {
            $table->string('doc_type');
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_seq')->default(0);

            $table->primary(['doc_type', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doc_counters');
        Schema::dropIfExists('yard_bookings');
        Schema::dropIfExists('document_lines');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('price_history');
        Schema::dropIfExists('grades');
    }
};
