<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('venue_type')->default('generic'); // museum | park | historical_building | theatre | generic
            $table->string('address')->nullable();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('canton_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('website')->nullable();
            $table->string('image')->nullable();
            $table->foreignId('source_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('published'); // draft | published
            $table->timestamps();

            $table->index('venue_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venues');
    }
};
