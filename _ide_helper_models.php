<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property string|null $id
 * @property string|null $name
 * @property string|null $default_host
 * @property int|null $default_port
 * @property string|null $default_username
 * @property string|null $icon
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Driver newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Driver newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Driver query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Driver whereDefaultHost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Driver whereDefaultPort($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Driver whereDefaultUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Driver whereIcon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Driver whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Driver whereName($value)
 */
	class Driver extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $driver_id
 * @property string $project_id
 * @property string|null $host
 * @property int|null $port
 * @property string|null $database
 * @property string|null $username
 * @property string|null $password
 * @property-read \App\Models\Driver|null $driver
 * @property-read string $connection_string
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DriverConfig newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DriverConfig newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DriverConfig query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DriverConfig whereDatabase($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DriverConfig whereDriverId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DriverConfig whereHost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DriverConfig whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DriverConfig wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DriverConfig wherePort($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DriverConfig whereProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DriverConfig whereUsername($value)
 */
	class DriverConfig extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property string $path
 * @property int $user_id
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\DriverConfig|null $driver_config
 * @property-read string $created_at_diff_for_humans
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project wherePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereUserId($value)
 */
	class Project extends \Eloquent {}
}

namespace App\Models{
/**
 * @property \Laravel\Ai\Enums\Lab $id
 * @property string $label
 * @property string|null $api_key
 * @property string|null $api_url
 * @property array<array-key, mixed>|null $models
 * @property bool $custom_openai_compatible
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read string $icon
 * @property-read bool $is_connected
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider connected()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider disconnected()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereApiKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereApiUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereCustomOpenaiCompatible($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereModels($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereUpdatedAt($value)
 */
	class Provider extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $password
 * @property string|null $remember_token
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Ai\Models\Conversation> $conversations
 * @property-read int|null $conversations_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Project> $projects
 * @property-read int|null $projects_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 */
	class User extends \Eloquent {}
}

