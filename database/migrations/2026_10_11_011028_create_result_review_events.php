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
        Schema::create('review_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('result_id');
            $table->uuid('actor_id');
            $table->string('actor_name');
            $table->string('action', 10);
            $table->text('note');
            $table->integer('result_version');
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['result_id', 'result_version']);
            $table->foreign(['workspace_id', 'result_id'])->references(['workspace_id', 'id'])->on('results')->cascadeOnDelete();
            $table->foreign(['workspace_id', 'actor_id'])->references(['workspace_id', 'id'])->on('users')->deferrable()->initiallyImmediate(false);
        });
        DB::statement("ALTER TABLE review_events ADD CONSTRAINT review_action CHECK (action IN ('reviewed','reopened')), ADD CONSTRAINT review_note CHECK (char_length(note) BETWEEN 3 AND 1000), ADD CONSTRAINT review_version CHECK (result_version > 1)");
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION lastro_review_append_only() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' AND pg_trigger_depth() > 1 THEN RETURN OLD; END IF;
                RAISE EXCEPTION 'Review events are append-only' USING ERRCODE = '23514';
            END $$;
            CREATE TRIGGER review_events_append_only BEFORE UPDATE OR DELETE ON review_events FOR EACH ROW EXECUTE FUNCTION lastro_review_append_only();
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_events');
        DB::statement('DROP FUNCTION IF EXISTS lastro_review_append_only()');
    }
};
