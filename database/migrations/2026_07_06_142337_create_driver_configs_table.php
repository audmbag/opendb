<?php

use App\Models\Driver;
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
        Schema::create('driver_configs', function (Blueprint $table) {
            $table->id();
            $table->string('driver_id', 25);
            $table->foreignUlid("project_id")->constrained()->cascadeOnDelete();
            $table->string('host')->nullable();
            $table->string('port')->nullable();
            $table->string('database')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_configs');
    }
};
