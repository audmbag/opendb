<?php

use App\Models\Project;
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
        Schema::create('query_plans', function (Blueprint $table) {
            $table->string('id', 32)->primary();
            $table->string('conversation_id', 36)->nullable();
            $table->foreignIdFor(Project::class)->constrained()->cascadeOnDelete();
            $table->string('fingerprint', 64);
            $table->json('payload');
            $table->timestamp('created_at')->nullable();

            $table->foreign('conversation_id')->references('id')->on(config('ai.conversations.tables.conversations', 'agent_conversations'))->cascadeOnDelete();
            $table->index(['conversation_id', 'fingerprint', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('query_plans');
    }
};
