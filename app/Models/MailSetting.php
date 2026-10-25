<?php

namespace App\Models;

use App\Enums\MailEncryptionEnum;
use Database\Factories\MailSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['host', 'port', 'encryption', 'username', 'password', 'from_address', 'from_name'])]
#[Hidden(['password'])]
#[Appends(['has_password'])]
class MailSetting extends Model
{
    /** @use HasFactory<MailSettingFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'encryption' => MailEncryptionEnum::class,
            'host' => 'encrypted',
            'username' => 'encrypted',
            'password' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getHasPasswordAttribute(): bool
    {
        return filled($this->password);
    }
}
