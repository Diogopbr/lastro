<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('conference_id');
            $table->string('kind', 10);
            $table->char('normalized_hash', 64);
            $table->string('display_filename', 200);
            $table->integer('row_count');
            $table->bigInteger('gross_total_cents');
            $table->timestampTz('confirmed_at')->useCurrent();
            $table->foreign(['workspace_id', 'conference_id'])->references(['workspace_id', 'id'])->on('conferences')->cascadeOnDelete();
            $table->unique(['workspace_id', 'conference_id', 'kind']);
            $table->unique(['workspace_id', 'id']);
            $table->unique(['workspace_id', 'conference_id', 'id', 'kind']);
        });
        Schema::create('source_rows', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('conference_id');
            $table->uuid('source_id');
            $table->string('kind', 10);
            $table->integer('line');
            $table->string('order_id', 100);
            $table->string('payout_id', 100)->nullable();
            $table->date('business_date');
            $table->bigInteger('gross_cents');
            $table->bigInteger('fee_cents')->nullable();
            $table->bigInteger('net_cents')->nullable();
            $table->foreign(['workspace_id', 'conference_id', 'source_id', 'kind'], 'source_rows_namespace_foreign')->references(['workspace_id', 'conference_id', 'id', 'kind'])->on('sources')->cascadeOnDelete();
            $table->unique(['source_id', 'order_id']);
        });
        Schema::create('source_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('source_id');
            $table->uuid('actor_id');
            $table->string('event_type', 20);
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign(['workspace_id', 'source_id'])->references(['workspace_id', 'id'])->on('sources')->cascadeOnDelete();
            $table->foreign(['workspace_id', 'actor_id'])->references(['workspace_id', 'id'])->on('users')->deferrable()->initiallyImmediate(false);
            $table->unique(['source_id', 'event_type']);
        });
        DB::statement("ALTER TABLE sources ADD CONSTRAINT source_kind CHECK (kind IN ('orders', 'payouts')), ADD CONSTRAINT source_count CHECK (row_count BETWEEN 1 AND 100), ADD CONSTRAINT source_total CHECK (gross_total_cents > 0 AND gross_total_cents <= 10000000000), ADD CONSTRAINT source_hash CHECK (normalized_hash ~ '^[0-9a-f]{64}$')");
        DB::statement("ALTER TABLE source_rows ADD CONSTRAINT source_row_money CHECK (gross_cents > 0 AND gross_cents <= 100000000), ADD CONSTRAINT source_row_line CHECK (line > 1), ADD CONSTRAINT source_row_kind CHECK ((kind = 'orders' AND payout_id IS NULL AND fee_cents IS NULL AND net_cents IS NULL) OR (kind = 'payouts' AND payout_id IS NOT NULL AND fee_cents IS NOT NULL AND net_cents IS NOT NULL AND fee_cents BETWEEN 0 AND gross_cents AND net_cents = gross_cents - fee_cents))");
        DB::statement("ALTER TABLE source_events ADD CONSTRAINT source_event_type CHECK (event_type = 'confirmed')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('source_events');
        Schema::dropIfExists('source_rows');
        Schema::dropIfExists('sources');
    }
};
