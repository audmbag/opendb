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
        Schema::create('providers', function (Blueprint $table) {
            $table->string('id', 25)->primary();
            $table->string('label', 25)->unique();
            $table->string('api_key', 255)->nullable();
            $table->string('api_url', 255)->nullable();
            $table->json('models')->nullable();
            $table->boolean('custom_openai_compatible')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
