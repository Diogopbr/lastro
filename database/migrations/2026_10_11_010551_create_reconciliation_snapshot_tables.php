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
        Schema::create('runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('conference_id')->unique();
            $table->uuid('orders_source_id');
            $table->uuid('payouts_source_id');
            $table->string('orders_kind', 10)->default('orders');
            $table->string('payouts_kind', 10)->default('payouts');
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['workspace_id', 'id']);
            $table->foreign(['workspace_id', 'conference_id'])->references(['workspace_id', 'id'])->on('conferences')->cascadeOnDelete();
            $table->foreign(['workspace_id', 'conference_id', 'orders_source_id', 'orders_kind'], 'runs_orders_namespace_fk')->references(['workspace_id', 'conference_id', 'id', 'kind'])->on('sources')->deferrable()->initiallyImmediate(false);
            $table->foreign(['workspace_id', 'conference_id', 'payouts_source_id', 'payouts_kind'], 'runs_payouts_namespace_fk')->references(['workspace_id', 'conference_id', 'id', 'kind'])->on('sources')->deferrable()->initiallyImmediate(false);
        });
        Schema::create('results', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('run_id');
            $table->string('order_id', 100);
            $table->string('classification', 20);
            $table->uuid('order_row_id')->nullable();
            $table->uuid('payout_row_id')->nullable();
            $table->bigInteger('difference_cents')->nullable();
            $table->string('review_status', 10)->nullable();
            $table->integer('version')->default(1);
            $table->unique(['run_id', 'order_id']);
            $table->unique(['workspace_id', 'id']);
            $table->foreign(['workspace_id', 'run_id'])->references(['workspace_id', 'id'])->on('runs')->cascadeOnDelete();
            $table->foreign('order_row_id')->references('id')->on('source_rows')->deferrable()->initiallyImmediate(false);
            $table->foreign('payout_row_id')->references('id')->on('source_rows')->deferrable()->initiallyImmediate(false);
            $table->index(['run_id', 'classification', 'review_status']);
        });
        Schema::create('run_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('run_id');
            $table->uuid('actor_id');
            $table->string('event_type', 20);
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['run_id', 'event_type']);
            $table->foreign(['workspace_id', 'run_id'])->references(['workspace_id', 'id'])->on('runs')->cascadeOnDelete();
            $table->foreign(['workspace_id', 'actor_id'])->references(['workspace_id', 'id'])->on('users')->deferrable()->initiallyImmediate(false);
        });
        DB::statement("ALTER TABLE runs ADD CONSTRAINT run_source_kinds CHECK (orders_kind = 'orders' AND payouts_kind = 'payouts')");
        DB::statement("ALTER TABLE run_events ADD CONSTRAINT run_event_type CHECK (event_type = 'reconciled')");
        DB::statement("ALTER TABLE results ADD CONSTRAINT result_version CHECK (version > 0), ADD CONSTRAINT result_shape CHECK (
            (classification = 'matched' AND order_row_id IS NOT NULL AND payout_row_id IS NOT NULL AND difference_cents IS NOT NULL AND difference_cents = 0 AND review_status IS NULL) OR
            (classification = 'amountMismatch' AND order_row_id IS NOT NULL AND payout_row_id IS NOT NULL AND difference_cents IS NOT NULL AND difference_cents <> 0 AND review_status IS NOT NULL AND review_status IN ('pending','reviewed')) OR
            (classification = 'missingOrder' AND order_row_id IS NULL AND payout_row_id IS NOT NULL AND difference_cents IS NULL AND review_status IS NOT NULL AND review_status IN ('pending','reviewed')) OR
            (classification = 'missingPayout' AND order_row_id IS NOT NULL AND payout_row_id IS NULL AND difference_cents IS NULL AND review_status IS NOT NULL AND review_status IN ('pending','reviewed'))
        )");
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION lastro_snapshot_immutable() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' AND pg_trigger_depth() > 1 THEN RETURN OLD; END IF;
                RAISE EXCEPTION 'Snapshot/event is immutable' USING ERRCODE = '23514';
            END $$;
            CREATE TRIGGER runs_immutable BEFORE UPDATE OR DELETE ON runs FOR EACH ROW EXECUTE FUNCTION lastro_snapshot_immutable();
            CREATE TRIGGER run_events_immutable BEFORE UPDATE OR DELETE ON run_events FOR EACH ROW EXECUTE FUNCTION lastro_snapshot_immutable();
            CREATE TRIGGER results_delete_immutable BEFORE DELETE ON results FOR EACH ROW EXECUTE FUNCTION lastro_snapshot_immutable();
            CREATE OR REPLACE FUNCTION lastro_result_integrity() RETURNS trigger LANGUAGE plpgsql AS $$
            DECLARE r runs%ROWTYPE; o source_rows%ROWTYPE; p source_rows%ROWTYPE;
            BEGIN
                IF TG_OP = 'UPDATE' THEN
                    IF (to_jsonb(NEW) - 'version' - 'review_status') IS DISTINCT FROM (to_jsonb(OLD) - 'version' - 'review_status')
                        OR NEW.version <> OLD.version + 1 OR OLD.classification = 'matched' THEN
                        RAISE EXCEPTION 'Result snapshot is immutable' USING ERRCODE = '23514';
                    END IF;
                    RETURN NEW;
                END IF;
                SELECT * INTO r FROM runs WHERE id = NEW.run_id AND workspace_id = NEW.workspace_id;
                IF NOT FOUND THEN RAISE EXCEPTION 'Invalid result run' USING ERRCODE = '23503'; END IF;
                IF NEW.order_row_id IS NOT NULL THEN
                    SELECT * INTO o FROM source_rows WHERE id = NEW.order_row_id AND workspace_id = NEW.workspace_id AND source_id = r.orders_source_id AND kind = 'orders' AND order_id = NEW.order_id;
                    IF NOT FOUND THEN RAISE EXCEPTION 'Invalid order origin' USING ERRCODE = '23503'; END IF;
                END IF;
                IF NEW.payout_row_id IS NOT NULL THEN
                    SELECT * INTO p FROM source_rows WHERE id = NEW.payout_row_id AND workspace_id = NEW.workspace_id AND source_id = r.payouts_source_id AND kind = 'payouts' AND order_id = NEW.order_id;
                    IF NOT FOUND THEN RAISE EXCEPTION 'Invalid payout origin' USING ERRCODE = '23503'; END IF;
                END IF;
                IF NEW.order_row_id IS NOT NULL AND NEW.payout_row_id IS NOT NULL AND NEW.difference_cents IS DISTINCT FROM p.gross_cents - o.gross_cents THEN
                    RAISE EXCEPTION 'Invalid difference' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END $$;
            CREATE TRIGGER results_integrity BEFORE INSERT OR UPDATE ON results FOR EACH ROW EXECUTE FUNCTION lastro_result_integrity();
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('run_events');
        Schema::dropIfExists('results');
        Schema::dropIfExists('runs');
        DB::statement('DROP FUNCTION IF EXISTS lastro_result_integrity()');
        DB::statement('DROP FUNCTION IF EXISTS lastro_snapshot_immutable()');
    }
};
