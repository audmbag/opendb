<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('usage_records', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 64);
            $table->string('model');
            $table->string('feature', 32);
            $table->string('invocation_id', 36)->nullable();
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->unsignedBigInteger('cache_read_input_tokens')->default(0);
            $table->unsignedBigInteger('cache_write_input_tokens')->default(0);
            $table->unsignedBigInteger('reasoning_tokens')->default(0);
            $table->unsignedInteger('duration')->nullable();
            $table->boolean('failed')->default(false);
            $table->timestamp('created_at')->index();

            $table->index(['provider', 'model']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_records');
    }
};
