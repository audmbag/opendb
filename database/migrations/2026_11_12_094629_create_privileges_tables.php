<?php

use App\Enums\PrivilegeEnum;
use App\Enums\PrivilegeLevelEnum;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\Privileges\DefaultRoles;
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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_root')->default(false)->after('password');
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignIdFor(Role::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();

            $table->primary(['role_id', 'user_id']);
        });

        Schema::create('grants', function (Blueprint $table) {
            $table->id();
            $table->morphs('grantee');
            $table->string('privilege', 30);
            $table->foreignIdFor(Project::class)->nullable()->constrained()->cascadeOnDelete();
            $table->string('table_name')->nullable();
            $table->boolean('grant_option')->default(false);
            $table->timestamps();
        });

        DB::table('users')->where('name', config('opendb.default_username'))->update(['is_root' => true]);

        app(DefaultRoles::class)->install();

        $this->grantProjectsToTheirOwner();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grants');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_root');
        });
    }

    /**
     * The projects created before the privileges keep every right for their owner, as the new ones get it, unless the
     * owner is the root, who holds them all anyway.
     */
    private function grantProjectsToTheirOwner(): void
    {
        $now = now();

        DB::table('projects')
            ->whereIn('user_id', DB::table('users')->where('is_root', false)->select('id'))
            ->get(['id', 'user_id'])
            ->each(fn (object $project) => DB::table('grants')->insert(
                array_map(fn (PrivilegeEnum $privilege): array => [
                    'grantee_type' => (new User)->getMorphClass(),
                    'grantee_id' => $project->user_id,
                    'privilege' => $privilege->value,
                    'project_id' => $project->id,
                    'table_name' => null,
                    'grant_option' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], PrivilegeEnum::forLevel(PrivilegeLevelEnum::Project)),
            ));
    }
};
