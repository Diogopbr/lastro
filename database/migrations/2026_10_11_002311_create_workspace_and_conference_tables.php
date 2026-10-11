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
        Schema::create('visitors', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->char('token_hash', 64)->unique();
            $table->timestampTz('created_at')->useCurrent();
        });
        Schema::create('workspaces', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('kind', 10);
            $table->uuid('visitor_id')->nullable();
            $table->foreign('visitor_id')->references('id')->on('visitors')->cascadeOnDelete();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->timestampTz('revoked_at')->nullable();
            $table->integer('next_conference_number')->default(1);
            $table->timestampTz('created_at')->useCurrent();
        });
        DB::statement("ALTER TABLE workspaces ADD CONSTRAINT workspace_kind CHECK (kind IN ('normal', 'demo')), ADD CONSTRAINT workspace_expiry CHECK ((kind = 'normal' AND expires_at IS NULL) OR (kind = 'demo' AND expires_at IS NOT NULL))");
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('workspace_id')->nullable();
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->unique(['workspace_id', 'id']);
        });
        Schema::create('conferences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 120);
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('version')->default(1);
            $table->boolean('period_locked')->default(false);
            $table->timestampsTz();
            $table->unique(['workspace_id', 'code']);
            $table->unique(['workspace_id', 'id']);
        });
        DB::statement('ALTER TABLE conferences ADD CONSTRAINT conference_period CHECK (start_date <= end_date), ADD CONSTRAINT conference_version CHECK (version > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conferences');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['workspace_id', 'id']);
            $table->dropForeign(['workspace_id']);
            $table->dropColumn('workspace_id');
        });
        Schema::dropIfExists('workspaces');
        Schema::dropIfExists('visitors');
    }
};
