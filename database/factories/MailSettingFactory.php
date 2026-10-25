<?php

namespace Database\Factories;

use App\Enums\MailEncryptionEnum;
use App\Models\MailSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MailSetting>
 */
class MailSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'host' => 'smtp.'.fake()->domainName(),
            'port' => 587,
            'encryption' => MailEncryptionEnum::Tls,
            'username' => fake()->safeEmail(),
            'password' => fake()->password(),
            'from_address' => fake()->safeEmail(),
            'from_name' => fake()->name(),
        ];
    }
}
