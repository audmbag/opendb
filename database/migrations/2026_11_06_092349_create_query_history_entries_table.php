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
        Schema::create('query_history_entries', function (Blueprint $table) {
            $table->string('id', 32)->primary();
            $table->foreignIdFor(Project::class)->constrained()->cascadeOnDelete();
            $table->string('file', 120)->nullable();
            $table->longText('sql');
            $table->boolean('succeeded');
            $table->unsignedInteger('statements');
            $table->unsignedBigInteger('rows')->nullable();
            $table->unsignedInteger('duration');
            $table->text('error')->nullable();
            $table->timestamp('created_at')->nullable();

            // The identifiers are ULIDs: in the order of the runs, which the history reads and prunes by.
            $table->index(['project_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('query_history_entries');
    }
};
