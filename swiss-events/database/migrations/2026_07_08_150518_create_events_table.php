<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->foreignId('venue_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('canton_id')->nullable()->constrained()->nullOnDelete(); // denormalized from venue for fast filtering
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_all_day')->default(false);
            $table->string('recurrence_rule')->nullable(); // RRULE string, unused at MVP
            $table->string('price_info')->nullable();
            $table->string('external_url')->nullable();
            $table->string('image')->nullable();

            // provenance
            $table->foreignId('source_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_external_id')->nullable(); // stable id/guid from the source feed
            $table->string('dedup_hash')->nullable(); // normalized(title)+date+venue, fuzzy dedup fallback
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('submitter_name')->nullable();
            $table->string('submitter_email')->nullable();

            // moderation
            $table->string('status')->default('pending_review'); // draft | pending_review | published | rejected | archived
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->unique(['source_id', 'source_external_id']);
            $table->index(['status', 'starts_at']);
            $table->index('dedup_hash');
            $table->index('canton_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
