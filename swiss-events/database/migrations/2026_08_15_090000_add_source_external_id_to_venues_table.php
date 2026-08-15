<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Venues can now be imported in their own right (the "places" side of the
     * site — museums, attractions, things to see), not only created as a side
     * effect of an event. That needs the same stable per-source key events
     * already have, so a re-import updates a place rather than duplicating it
     * when its name changes slightly.
     */
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->string('source_external_id')->nullable()->after('source_id');

            $table->unique(['source_id', 'source_external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropUnique(['source_id', 'source_external_id']);
            $table->dropColumn('source_external_id');
        });
    }
};
